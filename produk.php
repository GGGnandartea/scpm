<?php
/**
 * Halaman Detail Produk (/produk.php?id=...):
 * - Laptop: Split 2 kolom dengan tombol aksi inline.
 * - Mobile: Stacked 1 kolom dengan Context Header di atas dan Sticky Bottom Action Bar di bawah (tanpa dock).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';
require_once __DIR__ . '/includes/catalog_section.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';

$productId = (int) ($_GET['id'] ?? 1);
$product = get_product_by_id($productId);
if (!$product) {
    $all = get_all_products();
    $product = $all[0] ?? null;
}

if (!$product) {
    http_response_code(404);
    exit('Produk belum tersedia.');
}

$variants = get_product_variants((string) $product['group_slug']);
$related = get_related_products((int) $product['id'], 6);
$stock = (int) $product['stock'];
$inStock = $stock > 0;
$isInstant = ($product['delivery_type'] ?? 'instant') === 'instant';
$brandKey = (string) $product['brand_key'];
$waProductUrl = wa_link($waNumber, "Halo CS {$storeName}, saya ingin bertanya tentang {$product['title']} ({$product['variant_label']}).");
$checkoutUrl = app_url('checkout.php?product=' . (int) $product['id']);

$pageTitle = $product['title'] . ' — ' . $storeName;
$activePage = 'produk';
$showDock = false;
$showContextHeader = true;
$contextLabel = 'Detail Produk';
$backUrl = app_url('katalog.php');

require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
  <a href="<?= e(app_url('index.php')) ?>">Beranda</a>
  <span aria-hidden="true">&rsaquo;</span>
  <a href="<?= e(app_url('katalog.php')) ?>">Katalog</a>
  <span aria-hidden="true">&rsaquo;</span>
  <span><?= e(strtoupper((string) $product['brand_label'])) ?></span>
</nav>

<section class="detail-grid">
  <div class="detail-art glossy-brand" data-theme="<?= e($brandKey) ?>">
    <a class="detail-art-back" href="<?= e(app_url('katalog.php')) ?>" aria-label="Kembali ke katalog">
      <?= render_ui_icon('arrow-left', 'context-back-icon') ?>
    </a>
    <?= render_brand_monogram('glass-watermark') ?>
    <div class="glass-plate"><?= render_app_logo_svg($brandKey) ?></div>
    <span class="glass-brand-name"><?= e((string) $product['brand_label']) ?></span>
    <span class="detail-delivery-pill">
      <?= render_ui_icon('send', 'badge-icon-xs') ?>
      <span><?= $isInstant ? 'Pengiriman Instan' : 'Proses Manual CS' ?></span>
    </span>
  </div>

  <div class="detail-info">
    <div class="detail-eyebrow">
      <?= render_ui_icon('spark', 'badge-icon-xs') ?>
      <span><?= e((string) $product['category']) ?></span>
    </div>

    <h1 class="detail-title"><?= e((string) $product['title']) ?></h1>

    <div class="detail-badges">
      <span class="detail-pill-stat">
        <?= render_ui_icon('bag', 'badge-icon-xs') ?>
        <span>Terjual <?= (int) $product['sold_count'] ?></span>
      </span>
      <span class="detail-pill-stat">
        <span class="<?= $inStock ? 'dot-live' : 'dot-empty' ?>" aria-hidden="true"></span>
        <span>Stok: <?= $stock ?></span>
      </span>
      <span class="detail-pill-stat">
        <?= render_ui_icon('send', 'badge-icon-xs') ?>
        <span><?= $isInstant ? 'Otomatis / Instan' : 'Diproses Admin' ?></span>
      </span>
    </div>

    <div class="detail-price-row">
      <span class="detail-price"><?= e(format_rp((int) $product['price'])) ?></span>
      <span class="detail-unit">/ paket</span>
    </div>

    <?php if (count($variants) > 0): ?>
      <div class="variant-section-label">Pilih paket</div>
      <div class="variant-pills">
        <?php foreach ($variants as $v): ?>
          <?php $selected = (int) $v['id'] === (int) $product['id']; ?>
          <a
            href="<?= e(app_url('produk.php?id=' . (int) $v['id'])) ?>"
            class="variant-pill<?= $selected ? ' is-selected' : '' ?>"
            <?= $selected ? 'aria-current="true"' : '' ?>
          >
            <span><?= e((string) $v['variant_label']) ?></span>
            <span class="variant-stock"><?= (int) $v['stock'] ?> stok</span>
            <?php if ($selected): ?>
              <span aria-hidden="true">&#10003;</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Aksi Desktop (di Mobile dipindah ke Sticky Bottom Bar) -->
    <div class="detail-desktop-actions">
      <?php if ($inStock): ?>
        <a class="btn-primary-ocean" href="<?= e($checkoutUrl) ?>">
          <?= render_ui_icon('bag', 'wa-icon-sm') ?>
          <span>Beli Sekarang</span>
        </a>
      <?php else: ?>
        <button type="button" class="btn-primary-ocean" disabled>Stok Kosong</button>
      <?php endif; ?>
      <a class="btn-wa-outline" href="<?= e($waProductUrl) ?>" target="_blank" rel="noopener noreferrer">
        <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
        <span>WhatsApp CS</span>
      </a>
    </div>

    <div class="accordion-stack">
      <details class="ocean-details" open>
        <summary>Deskripsi Produk</summary>
        <div class="details-body">
          <p style="margin:0; white-space:pre-line;"><?= e((string) $product['description']) ?></p>
          <ul class="details-checklist">
            <li><?= render_ui_icon('check', 'badge-icon-xs') ?> Detail paket sesuai deskripsi provider</li>
            <li><?= render_ui_icon('check', 'badge-icon-xs') ?> Dukungan selama masa aktif</li>
          </ul>
        </div>
      </details>

      <details class="ocean-details">
        <summary>Bagaimana cara menerima akses akun?</summary>
        <div class="details-body">
          <?= $isInstant
            ? 'Setelah pembayaran QRIS terkonfirmasi, detail akses (email/password atau link invite) langsung muncul otomatis di halaman invoice dan halaman Lacak Pesanan.'
            : 'Setelah pembayaran QRIS terkonfirmasi, admin akan memproses aktivasi secara manual maksimal 1x15 menit jam operasional dan menginformasikan via WhatsApp serta halaman Lacak Pesanan.' ?>
        </div>
      </details>

      <details class="ocean-details">
        <summary>Apakah ada garansi pemakaian?</summary>
        <div class="details-body">
          Ya, setiap paket dilindungi garansi penggantian atau bantuan teknis penuh selama masa aktif paket selama ketentuan penggunaan (tidak mengubah profil/password/VPN terlarang) dipatuhi.
        </div>
      </details>
    </div>
  </div>
</section>

<section class="related-section" aria-labelledby="related-heading">
  <div class="related-head">
    <h2 id="related-heading">Produk lainnya</h2>
    <a href="<?= e(app_url('katalog.php')) ?>">Lihat semua &rsaquo;</a>
  </div>
  <div class="market-product-grid">
    <?php foreach ($related as $rel): ?>
      <?= render_product_card($rel) ?>
    <?php endforeach; ?>
  </div>
</section>

<!-- Sticky Mobile Bottom Bar (menggantikan dock di halaman detail) -->
<div class="mobile-sticky-action-bar">
  <a class="mobile-sticky-wa-btn" href="<?= e($waProductUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Tanya via WhatsApp CS">
    <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
  </a>
  <?php if ($inStock): ?>
    <a class="btn-primary-ocean" href="<?= e($checkoutUrl) ?>">
      <?= render_ui_icon('bag', 'wa-icon-sm') ?>
      <span>Beli Sekarang</span>
    </a>
  <?php else: ?>
    <button type="button" class="btn-primary-ocean" disabled>Stok Kosong</button>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
