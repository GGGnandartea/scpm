<?php
/**
 * Komponen bersama untuk merender kartu produk dan bagian katalog interaktif
 * (pencarian desktop, sortir, filter kategori swipe/bar, dan grid responsif 2/3/4/6 kolom).
 */

declare(strict_types=1);

/**
 * Merender 1 kartu produk bergaya glossy 3D brand card.
 */
function render_product_card(array $p): string
{
    $id = (int) $p['id'];
    $stock = (int) $p['stock'];
    $sold = (int) $p['sold_count'];
    $isEmpty = $stock <= 0;
    $isInstant = ($p['delivery_type'] ?? 'instant') === 'instant';
    $brandKey = (string) ($p['brand_key'] ?? 'nutrya');
    $brandLabel = (string) ($p['brand_label'] ?? 'Digital');

    $badgeHtml = '';
    if ($isEmpty) {
        $badgeHtml = '<span class="market-badge-top market-stock-empty">Stok Kosong</span>';
    } elseif ($isInstant) {
        $badgeHtml = '<span class="market-badge-top market-instant">'
            . render_ui_icon('send', 'badge-icon-xs')
            . '<span>Instan</span></span>';
    } else {
        $badgeHtml = '<span class="market-badge-top market-manual">Proses Manual</span>';
    }

    $emptyClass = $isEmpty ? ' is-empty' : '';
    $stockClass = $isEmpty ? 'stock-zero' : '';
    $url = app_url('produk.php?id=' . $id);
    $iconUrl = trim((string) ($p['icon_url'] ?? ''));
    $plateInner = ($brandKey === 'nutrya' && $iconUrl !== '')
        ? '<img src="' . e($iconUrl) . '" alt="' . e($brandLabel) . '" loading="lazy" style="width:100%;height:100%;object-fit:contain;border-radius:12px;">'
        : render_app_logo_svg($brandKey);

    return '<a href="' . e($url) . '" class="market-product-card' . $emptyClass . '" '
        . 'data-product-card '
        . 'data-title="' . e(mb_strtolower((string) $p['title'])) . '" '
        . 'data-brand="' . e(mb_strtolower($brandLabel)) . '" '
        . 'data-category="' . e((string) $p['category']) . '" '
        . 'data-price="' . (int) $p['price'] . '" '
        . 'data-sold="' . $sold . '" '
        . 'data-featured="' . (int) $p['is_featured'] . '">'
        . '<div class="market-product-visual glossy-brand" data-theme="' . e($brandKey) . '">'
        . $badgeHtml
        . render_brand_monogram('glass-watermark')
        . '<div class="glass-plate">' . $plateInner . '</div>'
        . '<span class="glass-brand-name">' . e($brandLabel) . '</span>'
        . '</div>'
        . '<div class="market-product-info">'
        . '<h3 class="market-product-title">' . e((string) $p['title']) . '</h3>'
        . '<div class="market-product-price">' . e(format_rp((int) $p['price'])) . '</div>'
        . '<div class="market-product-meta">'
        . '<span>Terjual ' . $sold . '</span>'
        . '<span class="' . $stockClass . '">Stok ' . $stock . '</span>'
        . '</div>'
        . '</div>'
        . '</a>';
}

/**
 * Merender section katalog lengkap beserta filter kategori, sortir, dan desktop search bar.
 */
function render_catalog_section(array $products, string $initialCategory = 'all', string $initialQuery = '', string $headingTag = 'h2'): string
{
    $categories = [
        ['key' => 'all', 'label' => 'Semua produk', 'icon' => 'grid'],
        ['key' => 'Streaming', 'label' => 'Streaming', 'icon' => 'play'],
        ['key' => 'AI & Produktivitas', 'label' => 'AI & Produktivitas', 'icon' => 'spark'],
        ['key' => 'Desain & Editing', 'label' => 'Desain & Editing', 'icon' => 'palette'],
        ['key' => 'Musik', 'label' => 'Musik', 'icon' => 'music'],
        ['key' => 'E-Book & Lainnya', 'label' => 'E-Book & Lainnya', 'icon' => 'book'],
    ];

    $totalCount = count($products);
    ob_start();
    ?>
    <!-- Pencarian khusus Laptop (di Mobile tersembunyi karena menyatu di header) -->
    <div class="catalog-search-desktop">
      <label class="visually-hidden" for="desktop-search-input">Cari produk digital</label>
      <?= render_ui_icon('search', 'search-lead-icon') ?>
      <input
        id="desktop-search-input"
        type="search"
        value="<?= e($initialQuery) ?>"
        placeholder="Cari Netflix, Canva, ChatGPT..."
        autocomplete="off"
      >
      <span class="search-hint-text">Temukan paket favoritmu</span>
    </div>

    <section class="market-catalog" id="katalog" aria-labelledby="catalog-heading" data-initial-cat="<?= e($initialCategory) ?>">
      <div class="catalog-top-row">
        <div class="catalog-heading">
          <<?= $headingTag ?> id="catalog-heading">Temukan premium-mu</<?= $headingTag ?>>
          <p class="catalog-subtitle">Pilihan favorit. Harga lebih hemat.</p>
        </div>

        <label class="market-sort">
          <span class="visually-hidden">Urutkan produk</span>
          <select id="catalog-sort" aria-label="Urutkan produk">
            <option value="recommended">Rekomendasi</option>
            <option value="bestseller">Paling laris</option>
            <option value="price-asc">Harga terendah</option>
            <option value="price-desc">Harga tertinggi</option>
          </select>
        </label>
      </div>

      <div class="market-filters" role="group" aria-label="Filter kategori produk">
        <?php foreach ($categories as $cat): ?>
          <?php $isActive = $initialCategory === $cat['key']; ?>
          <button
            type="button"
            class="cat-pill<?= $isActive ? ' is-active' : '' ?>"
            data-cat-filter="<?= e($cat['key']) ?>"
            aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
          >
            <?= render_ui_icon($cat['icon'], 'cat-icon') ?>
            <span><?= e($cat['label']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="market-results">
        <span id="catalog-count-label"><?= $totalCount ?> produk pilihan</span>
        <button type="button" class="reset-filter-btn" id="catalog-reset-btn" hidden>Semua produk</button>
      </div>

      <div class="market-product-grid" id="catalog-product-grid">
        <?php foreach ($products as $p): ?>
          <?= render_product_card($p) ?>
        <?php endforeach; ?>
      </div>

      <div class="empty-catalog-state" id="catalog-empty-state" hidden>
        <p style="font-weight:800; color:var(--navy-deep); margin:0 0 6px;">Produk tidak ditemukan</p>
        <p style="margin:0 0 14px; color:var(--ink-muted); font-size:13.5px;">Coba kata kunci lain atau tampilkan semua kategori.</p>
        <button type="button" class="btn-primary-ocean" id="catalog-empty-reset">Tampilkan Semua Produk</button>
      </div>
    </section>
    <?php
    return (string) ob_get_clean();
}
