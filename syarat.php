<?php
/**
 * Halaman Syarat & Ketentuan (/syarat.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';

$pageTitle = 'Syarat & Ketentuan — ' . $storeName;
$activePage = 'syarat';
$showDock = true;
$showContextHeader = true;
$contextLabel = 'Syarat & Ketentuan';
$backUrl = app_url('index.php');

require __DIR__ . '/includes/header.php';
?>

<section class="ocean-sub-banner">
  <div class="sub-banner-eyebrow">KETENTUAN LAYANAN</div>
  <h1>Syarat &amp; Ketentuan <?= e($storeName) ?></h1>
  <p>Aturan pemesanan, pembayaran QRIS, dan cakupan garansi produk digital.</p>
</section>

<article class="step-card" style="display:grid; gap:16px; line-height:1.7;">
  <div>
    <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:6px;">1. Transaksi &amp; Pembayaran QRIS</h2>
    <p style="margin:0; color:var(--ink-muted);">Setiap invoice QRIS berlaku selama 20 menit setelah dibuat. Pesanan produk instan akan merilis detail akses secara otomatis setelah pembayaran terkonfirmasi.</p>
  </div>
  <div>
    <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:6px;">2. Ketentuan Garansi Produk</h2>
    <p style="margin:0; color:var(--ink-muted);">Garansi berlaku sesuai durasi masing-masing paket apabila pembeli mematuhi aturan pemakaian (tidak mengubah password/email akun sharing, tidak mengubah PIN/nama profil, serta tidak menggunakan lebih dari jumlah perangkat yang diizinkan).</p>
  </div>
  <div>
    <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:6px;">3. Pengembalian Dana</h2>
    <p style="margin:0; color:var(--ink-muted);">Produk digital yang sudah terkirim tidak dapat dibatalkan sepihak. Apabila terjadi kendala teknis yang tidak dapat diganti oleh tim, pengembalian dana diproses melalui WhatsApp CS.</p>
  </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
