<?php
/**
 * Halaman Lacak Pesanan (/lacak.php):
 * Verifikasi menggunakan Nomor Invoice / WhatsApp + Email Pembelian
 * untuk membuka status transaksi dan detail akun digital secara aman.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';

$queryRef = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$searched = $queryRef !== '' || $email !== '';
$results = [];
$lookupError = '';

if ($searched) {
    if ($queryRef === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $lookupError = 'Masukkan nomor invoice atau WhatsApp beserta alamat email pembelian yang valid.';
    } else {
        $pdo = get_db();
        $normWa = normalize_wa($queryRef);
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT * FROM orders
            WHERE LOWER(customer_email) = LOWER(?)
              AND (UPPER(invoice) = UPPER(?) OR customer_wa = ?)
            ORDER BY id DESC
        SQL);
        $stmt->execute([$email, $queryRef, $normWa]);
        $results = $stmt->fetchAll();
    }
}

$pageTitle = 'Lacak Pesanan — ' . $storeName;
$activePage = 'lacak';
$showDock = true;
$showContextHeader = true;
$contextLabel = 'Lacak Pesanan';
$backUrl = app_url('index.php');

require __DIR__ . '/includes/header.php';
?>

<section class="ocean-sub-banner">
  <div style="margin-bottom:10px;"><?= render_ui_icon('receipt', 'wa-icon-sm') ?></div>
  <h1>Pesananmu, sudah sampai mana?</h1>
  <p>Masukkan nomor invoice atau WhatsApp untuk melihat statusnya.</p>
</section>

<section class="step-card" style="margin-bottom:20px;">
  <form method="get" action="<?= e(app_url('lacak.php')) ?>" class="form-stack">
    <div class="field">
      <label for="track-ref">Nomor invoice atau WhatsApp</label>
      <span id="ref-hint" class="hint">Contoh: NTR-DEMO-001 atau 081234567890</span>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <div class="input-box" style="flex:1; min-inline-size:220px;">
          <input
            id="track-ref"
            name="ref"
            type="text"
            required
            placeholder="NTR-... atau 0812 3456 7890"
            value="<?= e($queryRef) ?>"
            aria-describedby="ref-hint"
            data-error="ref-error"
          >
        </div>
        <button type="submit" class="btn-primary-ocean">
          <?= render_ui_icon('search', 'wa-icon-sm') ?>
          <span>Lacak pesanan</span>
        </button>
      </div>
      <div id="ref-error" class="error-msg">Error: Masukkan nomor invoice atau nomor WhatsApp.</div>
    </div>

    <div class="field">
      <label for="track-email">Email pembelian <span style="font-weight:500; color:var(--ink-muted);">(untuk membuka detail akun)</span></label>
      <span id="track-email-hint" class="hint">Demo uji coba: invoice <code>NTR-DEMO-001</code> &middot; email <code>demo@digiva.id</code></span>
      <div class="input-box">
        <input
          id="track-email"
          name="email"
          type="email"
          required
          autocomplete="email"
          placeholder="Email yang digunakan saat checkout"
          value="<?= e($email) ?>"
          aria-describedby="track-email-hint"
          data-error="track-email-error"
        >
      </div>
      <div id="track-email-error" class="error-msg">Error: Masukkan alamat email pembelian yang valid.</div>
      <p style="margin:6px 0 0; font-size:12px; color:var(--ink-muted);">Detail akun hanya ditampilkan setelah kontakmu terverifikasi.</p>
    </div>
  </form>
</section>

<?php if ($lookupError !== ''): ?>
  <div class="step-card" style="border-color:var(--danger); background:var(--danger-bg); color:var(--danger); margin-bottom:20px;" role="alert">
    <?= e($lookupError) ?>
  </div>
<?php elseif ($searched): ?>
  <?php if (empty($results)): ?>
    <div class="step-card" style="text-align:center;">
      <p style="font-weight:800; color:var(--navy-deep); margin:0 0 6px;">Pesanan belum ditemukan</p>
      <p style="margin:0 0 14px; font-size:13px; color:var(--ink-muted);">Periksa kembali kombinasi nomor invoice/WhatsApp dan email Anda.</p>
      <a class="btn-wa-outline" href="<?= e(wa_link($waNumber, "Halo CS {$storeName}, saya ingin cek pesanan dengan ref {$queryRef}.")) ?>" target="_blank" rel="noopener noreferrer">
        <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
        <span>Tanya WhatsApp CS</span>
      </a>
    </div>
  <?php else: ?>
    <div style="display:grid; gap:16px; margin-bottom:24px;">
      <?php foreach ($results as $ord): ?>
        <?php $isPaid = $ord['status'] === 'paid'; ?>
        <article class="step-card">
          <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px;">
            <div>
              <strong style="font-size:16px; color:var(--navy-deep);"><?= e($ord['invoice']) ?></strong>
              <div style="font-size:12.5px; color:var(--ink-muted);"><?= e($ord['product_title']) ?> &middot; <?= e($ord['variant_label']) ?> (<?= (int) $ord['quantity'] ?>x)</div>
            </div>
            <span class="status-pill <?= $isPaid ? 'is-paid' : 'is-pending' ?>">
              <?= $isPaid ? 'Lunas &amp; Aktif' : 'Menunggu Pembayaran' ?>
            </span>
          </div>

          <?php if ($isPaid): ?>
            <div class="credential-box">
              <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <strong style="color:#166534;">Detail Akses Akun</strong>
                <button
                  type="button"
                  class="reset-filter-btn"
                  data-copy-target="cred-<?= (int) $ord['id'] ?>"
                >Salin</button>
              </div>
              <pre class="credential-pre" id="cred-<?= (int) $ord['id'] ?>"><?= e($ord['delivered_data']) ?></pre>
            </div>
          <?php else: ?>
            <a class="btn-primary-ocean" href="<?= e(app_url('invoice.php?inv=' . rawurlencode($ord['invoice']))) ?>">
              Lanjutkan Pembayaran QRIS (<?= e(format_rp((int) $ord['total'])) ?>)
            </a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<p style="display:flex; align-items:center; justify-content:center; gap:8px; font-size:12.5px; color:var(--ink-muted); margin:12px 0 6px;">
  <?= render_ui_icon('shield', 'badge-icon-xs') ?>
  <span>Pesanan dan detail aksesmu tersimpan dengan aman.</span>
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
