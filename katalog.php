<?php
/**
 * Halaman Semua Produk (/katalog.php) dengan dukungan query pencarian (?q=) dan kategori (?kat=).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';
require_once __DIR__ . '/includes/catalog_section.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';
$products = get_all_products();

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$initialCategory = trim((string) ($_GET['kat'] ?? 'all'));
if ($initialCategory === '') {
    $initialCategory = 'all';
}

$pageTitle = 'Semua Produk — ' . $storeName;
$activePage = 'katalog';
$showDock = true;

require __DIR__ . '/includes/header.php';
?>

<?= render_catalog_section($products, $initialCategory, $searchQuery, 'h1') ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
