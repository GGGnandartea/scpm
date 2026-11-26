<?php
/**
 * Halaman Invoice & Pembayaran QRIS (/invoice.php?inv=...):
 * - Mendukung pembayaran langsung via NandarPay Gateway (qr_string + payment_url + auto polling status + sandbox simulation + cancel)
 * - Mendukung mode simulasi demo lokal bila NandarPay belum diaktifkan
 * - Otomatis merilis akses akun digital (lokal maupun via PremiumKita API) setelah status Lunas
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';

$invoiceCode = trim((string) ($_GET['inv'] ?? $_POST['invoice'] ?? ''));
$pdo = get_db();
$actionMessage = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoiceCode !== '' && verify_csrf($_POST['csrf_token'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
    $stmt->execute([$invoiceCode]);
    $curOrder = $stmt->fetch();

    if ($curOrder) {
        if ($action === 'simulate_pay' && empty($curOrder['pg_txn_id'])) {
            fulfill_order($invoiceCode);
            header('Location: ' . app_url('invoice.php?inv=' . rawurlencode($invoiceCode) . '&paid=1'));
            exit;
        } elseif ($action === 'simulate_sandbox' && !empty($curOrder['pg_txn_id']) && !empty($curOrder['pg_is_sandbox'])) {
            $simRes = pg_simulate_sandbox_payment((string) $curOrder['pg_txn_id']);
            if ($simRes['ok']) {
                // Force immediate check after sandbox trigger
                $pdo->prepare('UPDATE orders SET last_pg_poll_at = NULL WHERE invoice = ?')->execute([$invoiceCode]);
                sync_order_payment_status($invoiceCode);
                header('Location: ' . app_url('invoice.php?inv=' . rawurlencode($invoiceCode)));
                exit;
            }
            $actionError = 'Gagal simulasi sandbox: ' . ($simRes['error'] ?: 'Error gateway');
        } elseif ($action === 'check_pg_status' && !empty($curOrder['pg_txn_id'])) {
            sync_order_payment_status($invoiceCode);
            header('Location: ' . app_url('invoice.php?inv=' . rawurlencode($invoiceCode)));
            exit;
        } elseif ($action === 'cancel_pg' && !empty($curOrder['pg_txn_id']) && $curOrder['status'] === 'pending') {
            $cancelRes = pg_cancel_transaction((string) $curOrder['pg_txn_id']);
            if ($cancelRes['ok']) {
                $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE invoice = ?")->execute([$invoiceCode]);
                header('Location: ' . app_url('invoice.php?inv=' . rawurlencode($invoiceCode)));
                exit;
            }
            $actionError = 'Gagal membatalkan tagihan di gateway: ' . ($cancelRes['error'] ?: 'Coba lagi');
        }
    }
}

$order = $invoiceCode !== '' ? sync_order_payment_status($invoiceCode) : null;

$pageTitle = ($order ? 'Invoice ' . $order['invoice'] : 'Invoice Tidak Ditemukan') . ' — ' . $storeName;
$activePage = 'invoice';
$showDock = false;
$showContextHeader = true;
$contextLabel = 'Status Pembayaran';
$backUrl = app_url('lacak.php');

$extraFooterScript = '';
if ($order && $order['status'] === 'pending' && !empty($order['pg_qr_string'])) {
    $extraFooterScript = '<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js" defer></script>';
}

require __DIR__ . '/includes/header.php';
?>

<?php if (!$order): ?>
  <section class="step-card" style="max-inline-size:620px; margin-inline:auto; text-align:center;">
    <h1 style="font-size:22px; font-weight:800; color:var(--navy-deep); margin-bottom:8px;">Invoice Tidak Ditemukan</h1>
    <p style="color:var(--ink-muted); margin-bottom:18px;">Pastikan nomor invoice benar atau lacak menggunakan nomor WhatsApp dan email pembelian.</p>
    <a class="btn-primary-ocean" href="<?= e(app_url('lacak.php')) ?>">Lacak Pesanan</a>
  </section>
<?php else: ?>
  <?php
  $isPaid = $order['status'] === 'paid';
  $isCancelled = $order['status'] === 'cancelled';
  $hasGateway = !empty($order['pg_txn_id']);
  $waHelpUrl = wa_link($waNumber, "Halo CS {$storeName}, saya butuh bantuan terkait invoice {$order['invoice']}.");
  ?>
  <div class="invoice-card" <?= (!$isPaid && !$isCancelled && $hasGateway) ? 'data-poll-invoice="' . e($order['invoice']) . '"' : '' ?>>
    <section class="ocean-sub-banner" style="margin-bottom:0;">
      <div class="sub-banner-eyebrow">INVOICE &middot; <?= e($order['invoice']) ?></div>
      <h1><?= $isPaid ? 'Pembayaran Diterima &amp; Akses Siap' : ($isCancelled ? 'Tagihan Dibatalkan' : 'Selesaikan Pembayaran QRIS') ?></h1>
      <p>
        <?= $isPaid
          ? 'Terima kasih! Detail akses pesananmu sudah aktif di bawah ini dan dapat dibuka kapan saja di Lacak Pesanan.'
          : ($isCancelled
            ? 'Tagihan QRIS ini telah dibatalkan. Silakan buat pesanan baru dari katalog.'
            : 'Scan QRIS dengan GoPay, OVO, DANA, ShopeePay, atau Mobile Banking sebelum batas waktu berakhir.') ?>
      </p>
    </section>

    <?php if ($actionError !== ''): ?>
      <div class="step-card" style="border-color:var(--danger); background:var(--danger-bg); color:var(--danger);" role="alert">
        <strong><?= e($actionError) ?></strong>
      </div>
    <?php endif; ?>

    <div class="checkout-layout">
      <div class="qris-panel">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;">
          <span style="font-weight:800; color:var(--navy-deep); font-size:14px;"><?= e($order['invoice']) ?></span>
          <span class="status-pill <?= $isPaid ? 'is-paid' : ($isCancelled ? 'is-cancelled' : 'is-pending') ?>">
            <?= $isPaid ? 'Lunas &amp; Terkirim' : ($isCancelled ? 'Dibatalkan' : 'Menunggu Pembayaran') ?>
          </span>
        </div>

        <?php if ($isPaid): ?>
          <div class="credential-box">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
              <strong style="color:#166534; font-size:15px;">Detail Akses Produk Digital</strong>
              <button
                type="button"
                class="reset-filter-btn"
                data-copy-target="delivered-credentials"
                aria-label="Salin detail akses"
              >Salin Detail</button>
            </div>
            <pre class="credential-pre" id="delivered-credentials"><?= e($order['delivered_data']) ?></pre>
          </div>
          <p style="margin:14px 0 0; font-size:12.5px; color:var(--ink-muted);">
            Simpan nomor invoice <strong><?= e($order['invoice']) ?></strong>. Anda selalu dapat membukanya kembali lewat halaman <a href="<?= e(app_url('lacak.php')) ?>" style="color:var(--ocean-blue); font-weight:700;">Lacak Pesanan</a>.
          </p>
        <?php elseif ($isCancelled): ?>
          <p style="margin:16px 0; color:var(--ink-muted);">Tagihan QRIS untuk transaksi ini telah dibatalkan.</p>
          <a class="btn-primary-ocean" href="<?= e(app_url('katalog.php')) ?>">Pilih Produk Lain</a>
        <?php else: ?>
          <div style="margin-top:8px;">
            <?= render_ui_icon('qris', 'qris-badge-svg') ?>
          </div>

          <div class="qris-frame" id="qris-qr-container" data-qr-string="<?= e((string) ($order['pg_qr_string'] ?? '')) ?>">
            <?= render_qris_matrix_svg((string) $order['invoice'], (int) $order['total']) ?>
          </div>

          <div style="font-size:12px; color:var(--ink-muted); font-weight:700;">Total Tagihan QRIS</div>
          <div style="font-size:28px; font-weight:800; color:var(--navy-deep); letter-spacing:-0.04em; margin:2px 0 8px;">
            <?= e(format_rp((int) $order['total'])) ?>
          </div>
          <div
            style="font-size:12.5px; font-weight:700; color:var(--ocean-blue); margin-bottom:16px;"
            data-countdown-until="<?= e($order['expires_at']) ?>"
          >
            Berlaku hingga batas waktu pembayaran
          </div>

          <div style="display:grid; gap:10px;">
            <?php if ($hasGateway): ?>
              <?php if (!empty($order['pg_payment_url'])): ?>
                <a class="btn-primary-ocean" href="<?= e((string) $order['pg_payment_url']) ?>" target="_blank" rel="noopener noreferrer">
                  <span>Buka Halaman Bayar NandarPay &#8599;</span>
                </a>
              <?php endif; ?>

              <form method="post" action="<?= e(app_url('invoice.php?inv=' . rawurlencode($order['invoice']))) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="invoice" value="<?= e($order['invoice']) ?>">
                <input type="hidden" name="action" value="check_pg_status">
                <button type="submit" class="btn-wa-outline" style="width:100%; border-color:var(--border-ice);">
                  <span>Cek Status Pembayaran</span>
                </button>
              </form>

              <?php if (!empty($order['pg_is_sandbox'])): ?>
                <form method="post" action="<?= e(app_url('invoice.php?inv=' . rawurlencode($order['invoice']))) ?>">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="invoice" value="<?= e($order['invoice']) ?>">
                  <input type="hidden" name="action" value="simulate_sandbox">
                  <button type="submit" class="btn-wa-outline" style="width:100%; border-color:#fcd34d; color:#92400e; background:#fffbeb;">
                    <span>Simulasi Bayar Sandbox (NandarPay)</span>
                  </button>
                </form>
              <?php endif; ?>

              <form method="post" action="<?= e(app_url('invoice.php?inv=' . rawurlencode($order['invoice']))) ?>" onsubmit="return confirm('Batalkan tagihan QRIS ini?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="invoice" value="<?= e($order['invoice']) ?>">
                <input type="hidden" name="action" value="cancel_pg">
                <button type="submit" class="reset-filter-btn" style="width:100%; padding:10px; color:var(--danger); border-color:#fecaca;">
                  Batalkan Tagihan
                </button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= e(app_url('invoice.php?inv=' . rawurlencode($order['invoice']))) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="invoice" value="<?= e($order['invoice']) ?>">
                <input type="hidden" name="action" value="simulate_pay">
                <button type="submit" class="btn-primary-ocean" style="width:100%;">
                  <?= render_ui_icon('check', 'wa-icon-sm') ?>
                  <span>Konfirmasi Bayar QRIS (Simulasi Instan)</span>
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="step-card step-card-summary">
        <div class="step-card-head">
          <span class="step-badge">&#10003;</span>
          <h2>Ringkasan Transaksi</h2>
        </div>

        <div class="summary-rows">
          <div class="summary-row"><span>Produk</span><strong><?= e($order['product_title']) ?></strong></div>
          <div class="summary-row"><span>Paket</span><strong><?= e($order['variant_label']) ?></strong></div>
          <div class="summary-row"><span>Jumlah</span><strong><?= (int) $order['quantity'] ?> paket</strong></div>
          <div class="summary-row"><span>Pembeli</span><strong><?= e($order['customer_name']) ?></strong></div>
          <div class="summary-row"><span>Email</span><strong><?= e($order['customer_email']) ?></strong></div>
          <?php if ((int) $order['discount'] > 0): ?>
            <div class="summary-row"><span>Diskon (<?= e($order['promo_code']) ?>)</span><strong style="color:var(--success);">-<?= e(format_rp((int) $order['discount'])) ?></strong></div>
          <?php endif; ?>
          <div class="summary-row"><span>Biaya QRIS</span><strong><?= e(format_rp((int) $order['fee'])) ?></strong></div>
          <?php if ((int) ($order['pg_unique_code'] ?? 0) > 0): ?>
            <div class="summary-row"><span>Kode Unik</span><strong>+<?= (int) $order['pg_unique_code'] ?></strong></div>
          <?php endif; ?>
        </div>

        <div class="summary-total-row">
          <span style="font-weight:700; color:var(--ink-muted);">Total</span>
          <strong><?= e(format_rp((int) $order['total'])) ?></strong>
        </div>

        <div style="display:grid; gap:10px;">
          <a class="btn-wa-outline" href="<?= e($waHelpUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
            <span>Butuh Bantuan CS</span>
          </a>
          <a class="reset-filter-btn" style="text-align:center; padding:10px;" href="<?= e(app_url('index.php')) ?>">Kembali ke Beranda</a>
        </div>
      </aside>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
