<?php
/**
 * Halaman Kebijakan Privasi (/privasi.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';

$pageTitle = 'Kebijakan Privasi — ' . $storeName;
$activePage = 'privasi';
$showDock = true;
$showContextHeader = true;
$contextLabel = 'Kebijakan Privasi';
$backUrl = app_url('index.php');

require __DIR__ . '/includes/header.php';
?>

<section class="ocean-sub-banner">
  <div class="sub-banner-eyebrow">PERLINDUNGAN DATA</div>
  <h1>Kebijakan Privasi <?= e($storeName) ?></h1>
  <p>Bagaimana kami menjaga kontak WhatsApp, email, dan riwayat akses pesanan Anda.</p>
</section>

<article class="step-card" style="display:grid; gap:16px; line-height:1.7;">
  <div>
    <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:6px;">1. Data yang Dikumpulkan</h2>
    <p style="margin:0; color:var(--ink-muted);">Kami hanya menyimpan nama, nomor WhatsApp, dan alamat email aktif yang Anda masukkan saat checkout untuk keperluan pengiriman produk, verifikasi halaman Lacak Pesanan, dan layanan garansi.</p>
  </div>
  <div>
    <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:6px;">2. Keamanan Akses</h2>
    <p style="margin:0; color:var(--ink-muted);">Detail akses akun digital hanya ditampilkan setelah kombinasi nomor invoice/WhatsApp dan alamat email pembelian terverifikasi cocok.</p>
  </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
