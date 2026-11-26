<?php
/**
 * Beranda toko produk digital:
 * - Hero Carousel 3 slide dengan kartu 3D miring
 * - Trust Strip (khusus Laptop/Tablet >=768px)
 * - Standalone Search (khusus Laptop >=1024px) & Katalog Lengkap
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';
require_once __DIR__ . '/includes/catalog_section.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$products = get_all_products();

$pageTitle = $storeName . ' — Toko Produk Digital Otomatis 24 Jam';
$activePage = 'home';
$showDock = true;

$slides = [
    [
        'tag' => 'Premium, tanpa ribet',
        'title' => "Favoritmu.\nVersi premiumnya.",
        'desc' => 'Bayar QRIS. Paket instan langsung aktif dalam hitungan detik.',
        'cta' => 'Jelajahi produk',
        'href' => '#katalog',
        'cat' => 'all',
        'tiles' => [
            ['theme' => 'netflix', 'label' => 'Netflix', 'key' => 'netflix'],
            ['theme' => 'spotify', 'label' => 'Spotify', 'key' => 'spotify'],
            ['theme' => 'youtube', 'label' => 'YouTube', 'key' => 'youtube'],
        ],
    ],
    [
        'tag' => 'Ruang untuk ide besarmu',
        'title' => "Ide besar.\nPaket kreatif.",
        'desc' => 'ChatGPT, Canva, dan CapCut. Upgrade cara berkarya dengan hemat.',
        'cta' => 'Lihat paket kreatif',
        'href' => '#katalog',
        'cat' => 'Desain & Editing',
        'tiles' => [
            ['theme' => 'canva', 'label' => 'Canva', 'key' => 'canva'],
            ['theme' => 'openai', 'label' => 'ChatGPT', 'key' => 'openai'],
            ['theme' => 'capcut', 'label' => 'CapCut', 'key' => 'capcut'],
        ],
    ],
    [
        'tag' => 'Beli nyaman, pakai tenang',
        'title' => "Pakai tenang.\nKami bantu.",
        'desc' => 'Ada kendala selama pemakaian? Tim CS siap membantu penuh.',
        'cta' => 'Temukan paketmu',
        'href' => '#katalog',
        'cat' => 'AI & Produktivitas',
        'tiles' => [
            ['theme' => 'adobe', 'label' => 'Adobe', 'key' => 'adobe'],
            ['theme' => 'claude', 'label' => 'Claude', 'key' => 'claude'],
            ['theme' => 'zoom', 'label' => 'Zoom', 'key' => 'zoom'],
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<section class="promo-carousel" aria-label="Sorotan produk unggulan" data-carousel>
  <div class="promo-decor-rings" aria-hidden="true"></div>
  <div class="promo-orb orb-one" aria-hidden="true"></div>
  <div class="promo-orb orb-two" aria-hidden="true"></div>

  <div class="promo-track">
    <?php foreach ($slides as $idx => $slide): ?>
      <div class="promo-slide<?= $idx === 0 ? ' is-active' : '' ?>" data-slide="<?= $idx ?>">
        <div class="promo-copy">
          <div class="promo-tag">
            <?= render_ui_icon('spark', 'promo-tag-icon') ?>
            <span><?= e($slide['tag']) ?></span>
          </div>
          <h1 class="promo-title"><?= nl2br(e($slide['title'])) ?></h1>
          <p class="promo-desc"><?= e($slide['desc']) ?></p>
          <a class="promo-cta" href="<?= e($slide['href']) ?>" data-hero-cat="<?= e($slide['cat']) ?>">
            <span><?= e($slide['cta']) ?></span>
            <span aria-hidden="true">&#8599;</span>
          </a>
        </div>

        <div class="promo-art" aria-hidden="true">
          <?php foreach ($slide['tiles'] as $tIdx => $tile): ?>
            <div class="hero-tile glossy-brand hero-tile-<?= $tIdx + 1 ?>" data-theme="<?= e($tile['theme']) ?>">
              <div class="hero-tile-plate">
                <?= render_app_logo_svg($tile['key']) ?>
              </div>
              <span class="hero-tile-label"><?= e($tile['label']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="promo-dots" role="tablist" aria-label="Pilih slide promo">
    <?php foreach ($slides as $idx => $slide): ?>
      <button
        type="button"
        class="promo-dot<?= $idx === 0 ? ' is-active' : '' ?>"
        data-slide-to="<?= $idx ?>"
        aria-label="Slide <?= $idx + 1 ?>: <?= e($slide['tag']) ?>"
        aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>"
      ></button>
    <?php endforeach; ?>
  </div>
</section>

<!-- Trust Strip: Tampil di Laptop/Tablet, disembunyikan di HP agar katalog langsung terlihat -->
<div class="market-trust-strip">
  <div class="trust-items">
    <span class="trust-item">
      <?= render_ui_icon('receipt', 'trust-icon') ?>
      <span>Pembayaran QRIS</span>
    </span>
    <span class="trust-item">
      <?= render_ui_icon('check', 'trust-icon') ?>
      <span>Tanpa daftar akun</span>
    </span>
    <span class="trust-item">
      <?= render_ui_icon('shield', 'trust-icon') ?>
      <span>Bantuan selama masa aktif</span>
    </span>
  </div>
  <a class="trust-how-link" href="<?= e(app_url('bantuan.php#cara-beli')) ?>">Cara beli &rsaquo;</a>
</div>

<?= render_catalog_section($products, 'all', '', 'h2') ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
