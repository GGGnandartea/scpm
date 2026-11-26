<?php
/**
 * Halaman Checkout Aman (/checkout.php?product=...):
 * - Validasi form modern (:user-invalid + aria-invalid + server validation)
 * - Menghitung biaya QRIS melalui NandarPay Gateway (jika aktif) atau estimasi lokal
 * - Membuat transaksi NandarPay (POST /api/v2/create-transaction/{slug}/{order_id}) saat PG aktif
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';
$pgCreds = pg_get_credentials($settings);
$pgActive = $pgCreds['enabled'] && $pgCreds['slug'] !== '' && $pgCreds['api_key'] !== '';

$productId = (int) ($_GET['product'] ?? $_POST['product_id'] ?? 1);
$product = get_product_by_id($productId);
if (!$product) {
    header('Location: ' . app_url('katalog.php'));
    exit;
}

$errors = [];
$old = [
    'quantity' => max(1, (int) ($_POST['quantity'] ?? 1)),
    'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
    'customer_wa' => trim((string) ($_POST['customer_wa'] ?? '')),
    'customer_email' => trim((string) ($_POST['customer_email'] ?? '')),
    'promo_code' => strtoupper(trim((string) ($_POST['promo_code'] ?? ''))),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba kembali.';
    }

    $qty = max(1, min(20, (int) ($_POST['quantity'] ?? 1)));
    $stock = (int) $product['stock'];
    if ($stock <= 0) {
        $errors[] = 'Maaf, stok produk ini sedang habis.';
    } elseif ($qty > $stock) {
        $errors[] = "Jumlah melebihi stok tersedia ({$stock} unit).";
    }

    if (mb_strlen($old['customer_name']) < 2) {
        $errors[] = 'Nama lengkap minimal 2 karakter.';
    }

    $normalizedWa = normalize_wa($old['customer_wa']);
    if (strlen($normalizedWa) < 10 || strlen($normalizedWa) > 16) {
        $errors[] = 'Masukkan nomor WhatsApp aktif yang valid (contoh: 081234567890).';
    }

    if (!filter_var($old['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Masukkan alamat email aktif yang valid.';
    }

    if (empty($errors)) {
        $unitPrice = (int) $product['price'];
        $subtotal = $unitPrice * $qty;
        $discount = 0;
        $appliedPromo = '';

        if ($old['promo_code'] !== '') {
            $promoEval = evaluate_promo($old['promo_code'], $subtotal);
            if ($promoEval['valid']) {
                $discount = (int) $promoEval['discount'];
                $appliedPromo = $promoEval['code'];
            }
        }

        $afterDiscount = max(100, $subtotal - $discount);
        $invoice = 'NTR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $pkRefId = 'TRX-' . bin2hex(random_bytes(8));

        $fee = calculate_qris_fee($afterDiscount);
        $total = $afterDiscount + $fee;
        $expiresAt = date('Y-m-d H:i:s', strtotime('+20 minutes'));
        $pgTxnId = '';
        $pgPaymentUrl = '';
        $pgQrString = '';
        $pgUniqueCode = 0;
        $pgIsSandbox = 0;
        $paymentLabel = 'QRIS';

        if ($pgActive) {
            $pgRes = pg_create_transaction($invoice, $afterDiscount);
            if (!$pgRes['ok'] || !is_array($pgRes['json']) || empty($pgRes['json']['txn_id'])) {
                $errors[] = 'Gagal membuat tagihan QRIS NandarPay: ' . ($pgRes['error'] ?: 'Respon gateway tidak valid.');
            } else {
                $pgData = $pgRes['json'];
                $pgTxnId = (string) $pgData['txn_id'];
                $pgPaymentUrl = (string) ($pgData['payment_url'] ?? '');
                $pgQrString = (string) ($pgData['qr_string'] ?? '');
                $pgUniqueCode = (int) ($pgData['unique_code'] ?? 0);
                $pgIsSandbox = !empty($pgData['is_sandbox']) ? 1 : 0;
                $fee = (int) ($pgData['fee'] ?? 0);
                $total = (int) ($pgData['total_payment'] ?? ($afterDiscount + $pgUniqueCode));
                if (!empty($pgData['expired_at'])) {
                    $ts = strtotime((string) $pgData['expired_at']);
                    if ($ts !== false) {
                        $expiresAt = date('Y-m-d H:i:s', $ts);
                    }
                }
                $paymentLabel = 'QRIS NandarPay' . ($pgIsSandbox ? ' (Sandbox)' : '');
            }
        }

        if (empty($errors)) {
            $pdo = get_db();
            $stmt = $pdo->prepare(<<<'SQL'
                INSERT INTO orders (
                    invoice, product_id, product_title, variant_label, quantity, unit_price,
                    subtotal, discount, fee, total, promo_code, customer_name, customer_wa,
                    customer_email, payment_method, status, expires_at,
                    pg_txn_id, pg_payment_url, pg_qr_string, pg_unique_code, pg_is_sandbox, pk_ref_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
            SQL);
            $stmt->execute([
                $invoice,
                (int) $product['id'],
                (string) $product['title'],
                (string) $product['variant_label'],
                $qty,
                $unitPrice,
                $subtotal,
                $discount,
                $fee,
                $total,
                $appliedPromo,
                $old['customer_name'],
                $normalizedWa,
                $old['customer_email'],
                $paymentLabel,
                $expiresAt,
                $pgTxnId,
                $pgPaymentUrl,
                $pgQrString,
                $pgUniqueCode,
                $pgIsSandbox,
                $pkRefId,
            ]);

            header('Location: ' . app_url('invoice.php?inv=' . rawurlencode($invoice)));
            exit;
        }
    }
}

$unitPrice = (int) $product['price'];
$stock = (int) $product['stock'];
$initSubtotal = $unitPrice * $old['quantity'];
$initFee = calculate_qris_fee($initSubtotal);
if ($pgActive) {
    $feeRes = pg_get_payment_fee($initSubtotal, $pgCreds);
    if ($feeRes['ok'] && isset($feeRes['json']['qris'])) {
        $initFee = (int) $feeRes['json']['qris'];
    }
}
$waCheckoutUrl = wa_link($waNumber, "Halo CS {$storeName}, saya butuh bantuan checkout {$product['title']}.");

$pageTitle = 'Checkout Aman — ' . $storeName;
$activePage = 'checkout';
$showDock = false;
$showContextHeader = true;
$contextLabel = 'Checkout Aman';
$backUrl = app_url('produk.php?id=' . (int) $product['id']);

require __DIR__ . '/includes/header.php';
?>

<div class="checkout-topbar">
  <a href="<?= e(app_url('produk.php?id=' . (int) $product['id'])) ?>">
    <?= render_ui_icon('arrow-left', 'badge-icon-xs') ?>
    <span>Kembali</span>
  </a>
  <span class="checkout-safe-tag">
    <span>Checkout aman</span>
    <?= render_ui_icon('shield', 'badge-icon-xs') ?>
  </span>
</div>

<section class="ocean-sub-banner">
  <div class="sub-banner-eyebrow">KONTAK TEPAT &middot; PEMBAYARAN PRAKTIS</div>
  <h1>Checkout Aman &middot; <?= e($storeName) ?></h1>
  <p>Lengkapi kontakmu, lalu bayar praktis dengan QRIS <?= $pgActive ? 'otomatis via NandarPay' : 'instan' ?>.</p>
</section>

<?php if (!empty($errors)): ?>
  <div class="step-card" style="border-color:var(--danger); background:var(--danger-bg); color:var(--danger); margin-block-end:18px;" role="alert">
    <strong>Periksa kembali formulir Anda:</strong>
    <ul style="margin:8px 0 0; padding-inline-start:20px;">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form
  id="checkout-form"
  class="checkout-layout"
  method="post"
  action="<?= e(app_url('checkout.php?product=' . (int) $product['id'])) ?>"
  data-unit-price="<?= $unitPrice ?>"
  data-max-stock="<?= max(1, $stock) ?>"
>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

  <div class="checkout-steps-col">
    <section class="step-card" aria-labelledby="step-1-heading">
      <div class="step-card-head">
        <span class="step-badge">1</span>
        <h2 id="step-1-heading">Produk dipilih</h2>
      </div>

      <div class="checkout-product-row">
        <div class="checkout-prod-info">
          <div class="checkout-mini-tile glossy-brand" data-theme="<?= e((string) $product['brand_key']) ?>">
            <div class="checkout-mini-plate"><?= render_app_logo_svg((string) $product['brand_key']) ?></div>
          </div>
          <div>
            <div style="font-size:14px; font-weight:800; color:var(--navy-deep);"><?= e((string) $product['title']) ?></div>
            <div style="font-size:12px; font-weight:600; color:var(--ink-muted);"><?= e((string) $product['variant_label']) ?> &middot; Stok <?= $stock ?></div>
            <div style="font-size:15px; font-weight:800; color:var(--ocean-blue); margin-top:2px;"><?= e(format_rp($unitPrice)) ?></div>
          </div>
        </div>

        <div class="qty-stepper" aria-label="Jumlah paket">
          <button type="button" id="qty-minus" aria-label="Kurangi jumlah">&minus;</button>
          <input
            type="number"
            id="checkout-qty"
            name="quantity"
            value="<?= $old['quantity'] ?>"
            min="1"
            max="<?= max(1, min(20, $stock)) ?>"
            aria-label="Jumlah paket"
          >
          <button type="button" id="qty-plus" aria-label="Tambah jumlah">+</button>
        </div>
      </div>
    </section>

    <section class="step-card" aria-labelledby="step-2-heading">
      <div class="step-card-head">
        <span class="step-badge">2</span>
        <h2 id="step-2-heading">Informasi pembeli</h2>
      </div>

      <div class="form-stack">
        <div class="field">
          <label for="customer_name">Nama Lengkap</label>
          <span id="name-hint" class="hint">Minimal 2 huruf sesuai identitas penerima pesanan.</span>
          <div class="input-box">
            <?= render_ui_icon('user', 'search-lead-icon') ?>
            <input
              id="customer_name"
              name="customer_name"
              type="text"
              required
              minlength="2"
              autocomplete="name"
              placeholder="Nama lengkapmu"
              value="<?= e($old['customer_name']) ?>"
              aria-describedby="name-hint"
              data-error="name-error"
            >
          </div>
          <div id="name-error" class="error-msg">Error: Masukkan nama lengkap minimal 2 karakter.</div>
        </div>

        <div class="field">
          <label for="customer_wa">Nomor WhatsApp</label>
          <span id="wa-hint" class="hint">Format: 81234567890 atau 081234567890 untuk bantuan garansi.</span>
          <div class="input-box">
            <span class="wa-prefix">ID +62</span>
            <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
            <input
              id="customer_wa"
              name="customer_wa"
              type="tel"
              required
              pattern="[0-9+\-\s]{9,16}"
              autocomplete="tel"
              placeholder="812 3456 7890"
              value="<?= e($old['customer_wa']) ?>"
              aria-describedby="wa-hint"
              data-error="wa-error"
            >
          </div>
          <div id="wa-error" class="error-msg">Error: Masukkan nomor WhatsApp aktif (9-15 digit).</div>
        </div>

        <div class="field">
          <label for="customer_email">Alamat Email Aktif</label>
          <span id="email-hint" class="hint">Format: nama@gmail.com (untuk verifikasi halaman Lacak Pesanan).</span>
          <div class="input-box">
            <?= render_ui_icon('mail', 'search-lead-icon') ?>
            <input
              id="customer_email"
              name="customer_email"
              type="email"
              required
              autocomplete="email"
              placeholder="nama@gmail.com"
              value="<?= e($old['customer_email']) ?>"
              aria-describedby="email-hint"
              data-error="email-error"
            >
          </div>
          <div id="email-error" class="error-msg">Error: Masukkan alamat email yang valid seperti nama@gmail.com.</div>
        </div>
      </div>
    </section>

    <section class="step-card" aria-labelledby="step-3-heading">
      <div class="step-card-head">
        <span class="step-badge">3</span>
        <h2 id="step-3-heading">Metode pembayaran</h2>
      </div>

      <label class="payment-option-card">
        <span style="display:flex; align-items:center; gap:12px;">
          <?= render_ui_icon('qris', 'qris-badge-svg') ?>
          <span>
            <strong style="display:block; font-size:14px; color:var(--navy-deep);">QRIS <?= $pgActive ? 'Otomatis (NandarPay)' : '(Semua E-Wallet & Bank)' ?></strong>
            <span style="font-size:12px; color:var(--ink-muted);">Estimasi biaya layanan <span id="qris-fee-hint"><?= e(format_rp($initFee)) ?></span></span>
          </span>
        </span>
        <input type="radio" name="payment_method" value="QRIS" checked>
      </label>
      <p style="margin:10px 0 0; font-size:12px; color:var(--ink-muted);">
        Pembayaran terverifikasi otomatis 24 jam. Nominal akhir mengikuti tagihan resmi QRIS.
      </p>
    </section>
  </div>

  <aside class="step-card step-card-summary" aria-labelledby="step-4-heading">
    <div class="step-card-head">
      <span class="step-badge">4</span>
      <h2 id="step-4-heading">Detail pesanan</h2>
    </div>

    <div class="summary-rows">
      <div class="summary-row"><span>Produk</span><strong><?= e((string) $product['title']) ?></strong></div>
      <div class="summary-row"><span>Paket</span><strong><?= e((string) $product['variant_label']) ?></strong></div>
      <div class="summary-row"><span>Jumlah</span><strong id="summary-qty"><?= $old['quantity'] ?> paket</strong></div>
      <div class="summary-row"><span>Garansi</span><strong>Bantuan selama masa aktif</strong></div>
      <div class="summary-row"><span>Estimasi Biaya QRIS</span><strong id="summary-fee"><?= e(format_rp($initFee)) ?></strong></div>
      <div class="summary-row" id="summary-discount-row" hidden>
        <span>Diskon Promo</span>
        <strong id="summary-discount" style="color:var(--success);">-Rp 0</strong>
      </div>
    </div>

    <div class="summary-total-row">
      <span style="font-weight:700; color:var(--ink-muted);">Total Bayar</span>
      <strong id="summary-total"><?= e(format_rp($initSubtotal + $initFee)) ?></strong>
    </div>

    <div style="margin-block-end:14px;">
      <label for="promo_code" style="font-size:12.5px; font-weight:800; color:var(--navy-deep);">Punya Kode Promo / Referral?</label>
      <span style="display:block; font-size:11.5px; color:var(--ink-muted); margin-top:2px;">Coba kode: <code>HEMAT10</code> atau <code>DIGIVA5</code></span>
      <div class="promo-inline">
        <input id="promo_code" name="promo_code" type="text" value="<?= e($old['promo_code']) ?>" placeholder="MASUKKAN KODE">
        <button type="button" class="btn-apply-promo" id="apply-promo-btn">Terapkan</button>
      </div>
      <div id="promo-feedback" style="font-size:12px; font-weight:700; margin-top:6px;" aria-live="polite"></div>
    </div>

    <button type="submit" class="btn-primary-ocean" style="width:100%;" <?= $stock <= 0 ? 'disabled' : '' ?>>
      <span><?= $stock > 0 ? 'Lanjutkan Pembayaran' : 'Stok Habis' ?></span>
    </button>

    <p style="margin:10px 0 0; font-size:11.5px; color:var(--ink-muted); text-align:center;">
      Dengan melanjutkan, kamu menyetujui <a href="<?= e(app_url('syarat.php')) ?>" style="text-decoration:underline;">syarat &amp; ketentuan</a> serta <a href="<?= e(app_url('privasi.php')) ?>" style="text-decoration:underline;">kebijakan privasi</a>.
    </p>
  </aside>

  <div class="mobile-sticky-action-bar">
    <a class="mobile-sticky-wa-btn" href="<?= e($waCheckoutUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Bantuan WhatsApp CS">
      <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
    </a>
    <button type="submit" class="btn-primary-ocean" <?= $stock <= 0 ? 'disabled' : '' ?>>
      <span>Lanjutkan Pembayaran</span>
    </button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
