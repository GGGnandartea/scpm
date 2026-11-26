<?php
/**
 * Header bersama untuk seluruh halaman toko:
 * - Desktop (>=1024px): Floating frosted glass pill navbar yang menciut jadi orb saat scroll.
 * - Mobile (<1024px): Sticky-to-floating morph header dengan search inline (beranda/katalog)
 *   atau Context Header dengan tombol kembali (halaman detail, checkout, lacak, bantuan).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$storeTagline = $settings['store_tagline'] ?? 'Digital Solutions';
$poweredBy = $settings['powered_by'] ?? 'Digiva Cloud Engine';
$waNumber = $settings['whatsapp_cs'] ?? '6285198090571';

$pageTitle = $pageTitle ?? ($storeName . ' — Toko Produk Digital Otomatis 24 Jam');
$pageDesc = $pageDesc ?? 'Beli aplikasi premium, AI tools, desain & editing, musik, dan e-book digital dengan pembayaran QRIS instan dan garansi aktif.';
$activePage = $activePage ?? 'home';
$showDock = $showDock ?? true;
$showContextHeader = $showContextHeader ?? false;
$contextLabel = $contextLabel ?? $storeTagline;
$backUrl = $backUrl ?? app_url('index.php');
$searchQuery = $searchQuery ?? '';
$useMobileSearchHeader = in_array($activePage, ['home', 'katalog'], true);

$waCsUrl = wa_link($waNumber, "Halo CS {$storeName}, saya ingin bertanya tentang produk digital.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23072D44'/%3E%3Ccircle cx='20' cy='20' r='6' fill='%239CCDDB'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="ocean-canvas <?= $showDock ? 'has-dock' : 'no-dock' ?> page-<?= e($activePage) ?>">
  <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

  <!-- LAPTOP / DESKTOP (>=1024px): Floating Frosted Glass Pill Navbar + Scroll-Collapse Orb -->
  <div class="desktop-pill-position">
    <div class="desktop-glass-pill is-expanded" id="desktop-glass-pill">
      <button
        type="button"
        class="desktop-nav-orb"
        id="desktop-nav-orb"
        aria-label="Perluas atau buka menu <?= e($storeName) ?>"
        aria-expanded="true"
        aria-controls="desktop-pill-content"
      >
        <?= render_brand_monogram('orb-monogram') ?>
      </button>

      <div class="desktop-pill-content" id="desktop-pill-content">
        <a class="desktop-brand-link" href="<?= e(app_url('index.php')) ?>">
          <span class="desktop-brand-text"><?= e($storeName) ?></span>
        </a>

        <nav class="desktop-pill-links" aria-label="Navigasi utama">
          <a
            href="<?= e(app_url('index.php')) ?>"
            class="desktop-pill-link"
            <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>
          >Beranda</a>
          <a
            href="<?= e(app_url('katalog.php')) ?>"
            class="desktop-pill-link"
            <?= in_array($activePage, ['katalog', 'produk'], true) ? 'aria-current="page"' : '' ?>
          >Semua Produk</a>
          <a
            href="<?= e(app_url('lacak.php')) ?>"
            class="desktop-pill-link"
            <?= $activePage === 'lacak' ? 'aria-current="page"' : '' ?>
          >Lacak Pesanan</a>
          <a
            href="<?= e(app_url('bantuan.php#cara-beli')) ?>"
            class="desktop-pill-link"
            <?= $activePage === 'bantuan' ? 'aria-current="page"' : '' ?>
          >Cara Beli</a>
        </nav>

        <a
          class="desktop-pill-whatsapp"
          href="<?= e($waCsUrl) ?>"
          target="_blank"
          rel="noopener noreferrer"
        >
          <?= render_ui_icon('whatsapp', 'wa-icon-sm') ?>
          <span>WhatsApp CS</span>
        </a>
      </div>
    </div>
  </div>

  <?php if ($useMobileSearchHeader): ?>
  <!-- HP / MOBILE (<1024px): Sticky Header yang berubah jadi floating pill saat scroll -->
  <header class="market-header-frame" id="mobile-header-frame">
    <div class="market-header" id="mobile-header">
      <a class="mobile-brand" href="<?= e(app_url('index.php')) ?>" aria-label="<?= e($storeName) ?> Beranda">
        <span class="mobile-brand-orb"><?= render_brand_monogram('mobile-monogram') ?></span>
        <span class="mobile-brand-name"><?= e($storeName) ?></span>
      </a>

      <form class="mobile-header-search" action="<?= e(app_url('katalog.php')) ?>" method="get" role="search">
        <label class="visually-hidden" for="mobile-search-input">Cari produk digital</label>
        <?= render_ui_icon('search', 'search-lead-icon') ?>
        <input
          id="mobile-search-input"
          name="q"
          type="search"
          value="<?= e($searchQuery) ?>"
          placeholder="Cari Netflix, Canva, ChatGPT..."
          autocomplete="off"
        >
      </form>
    </div>
  </header>
  <?php elseif ($showContextHeader): ?>
  <!-- HP / MOBILE & SUB-PAGE: Context Header dengan tombol kembali -->
  <header class="buyer-context-frame">
    <div class="buyer-context-header">
      <a class="context-back-btn" href="<?= e($backUrl) ?>" aria-label="Kembali ke halaman sebelumnya">
        <?= render_ui_icon('arrow-left', 'context-back-icon') ?>
      </a>
      <a class="context-brand" href="<?= e(app_url('index.php')) ?>">
        <span class="context-brand-orb"><?= render_brand_monogram('mobile-monogram') ?></span>
        <span class="context-brand-copy">
          <strong><?= e($storeName) ?></strong>
          <span><?= e($contextLabel) ?></span>
        </span>
      </a>
    </div>
  </header>
  <?php endif; ?>

  <main id="main-content" class="market-main">
