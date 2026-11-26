<?php
/**
 * Panel Admin Toko (/admin.php):
 * Terlindungi sesi admin (redirect otomatis ke /admin-login.php jika belum login).
 * Tabbed interface:
 * 1. Pesanan & Webhook (order monitor, sync bayar, retry order ke PremiumKita, log webhook)
 * 2. Katalog & Sync PremiumKita (sync produk ready/semua, CRUD produk lokal/provider)
 * 3. Integrasi API & Payment Gateway (secret key PremiumKita + NandarPay + Test Koneksi)
 * 4. Pengaturan Toko & Keamanan Admin (identitas toko, WA CS, ubah username/password admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin'], $_SESSION['admin_user']);
    header('Location: ' . app_url('admin-login.php'));
    exit;
}

if (empty($_SESSION['is_admin'])) {
    header('Location: ' . app_url('admin-login.php'));
    exit;
}

$pdo = get_db();
$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';

$flash = '';
$flashType = 'success';
$activeTab = (string) ($_GET['tab'] ?? $_POST['active_tab'] ?? 'tab-orders');
$validTabs = ['tab-orders', 'tab-catalog', 'tab-integrations', 'tab-settings'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'tab-orders';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $flash = 'Token keamanan tidak valid. Muat ulang halaman dan coba kembali.';
        $flashType = 'error';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_settings') {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
            $stmt->execute(['store_name', trim((string) ($_POST['store_name'] ?? $storeName)) ?: 'DIGIVA.ID']);
            $stmt->execute(['store_tagline', trim((string) ($_POST['store_tagline'] ?? 'Digital Solutions')) ?: 'Digital Solutions']);
            $stmt->execute(['powered_by', trim((string) ($_POST['powered_by'] ?? 'Digiva Cloud Engine')) ?: 'Digiva Cloud Engine']);
            $stmt->execute(['whatsapp_cs', normalize_wa((string) ($_POST['whatsapp_cs'] ?? '6285198090571'))]);
            $flash = 'Pengaturan identitas toko berhasil diperbarui.';
            $activeTab = 'tab-settings';
        } elseif ($action === 'save_admin_credentials') {
            $currPass = (string) ($_POST['current_password'] ?? '');
            $newUser = trim((string) ($_POST['admin_username'] ?? ''));
            $newPass = (string) ($_POST['new_password'] ?? '');
            $currHash = $settings['admin_password_hash'] ?? password_hash(ADMIN_DEFAULT_PASS, PASSWORD_DEFAULT);

            if (!password_verify($currPass, $currHash)) {
                $flash = 'Password saat ini tidak sesuai.';
                $flashType = 'error';
            } elseif (mb_strlen($newUser) < 3) {
                $flash = 'Username admin minimal 3 karakter.';
                $flashType = 'error';
            } else {
                $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
                $stmt->execute(['admin_username', $newUser]);
                if ($newPass !== '') {
                    if (strlen($newPass) < 6) {
                        $flash = 'Password baru minimal 6 karakter.';
                        $flashType = 'error';
                    } else {
                        $stmt->execute(['admin_password_hash', password_hash($newPass, PASSWORD_DEFAULT)]);
                        $flash = 'Username & password admin berhasil diperbarui.';
                    }
                } else {
                    $flash = 'Username admin berhasil diperbarui.';
                }
            }
            $activeTab = 'tab-settings';
        } elseif ($action === 'save_pk_api' || $action === 'test_pk_api') {
            $pkEnabled = isset($_POST['pk_enabled']) ? '1' : '0';
            $pkBaseUrl = trim((string) ($_POST['pk_base_url'] ?? 'https://premiumkita.com/app/')) ?: 'https://premiumkita.com/app/';
            $pkIdUser = trim((string) ($_POST['pk_id_user'] ?? ''));
            $pkKeyUser = trim((string) ($_POST['pk_key_user'] ?? ''));
            $pkMarkupPct = (string) max(0, (float) ($_POST['pk_markup_percent'] ?? 0));
            $pkMarkupFix = (string) max(0, (int) ($_POST['pk_markup_fixed'] ?? 0));

            $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
            $stmt->execute(['pk_enabled', $pkEnabled]);
            $stmt->execute(['pk_base_url', $pkBaseUrl]);
            $stmt->execute(['pk_id_user', $pkIdUser]);
            $stmt->execute(['pk_key_user', $pkKeyUser]);
            $stmt->execute(['pk_markup_percent', $pkMarkupPct]);
            $stmt->execute(['pk_markup_fixed', $pkMarkupFix]);

            if ($action === 'test_pk_api') {
                $test = pk_test_connection([
                    'base_url' => $pkBaseUrl,
                    'id_user' => $pkIdUser,
                    'key_user' => $pkKeyUser,
                ]);
                $flash = $test['message'];
                $flashType = $test['ok'] ? 'success' : 'error';
            } else {
                $flash = 'Konfigurasi PremiumKita Open API berhasil disimpan.';
            }
            $activeTab = 'tab-integrations';
        } elseif ($action === 'save_pg_api' || $action === 'test_pg_api') {
            $pgEnabled = isset($_POST['pg_enabled']) ? '1' : '0';
            $pgBaseUrl = rtrim(trim((string) ($_POST['pg_base_url'] ?? 'https://pay.nandarstore.my.id')), '/') ?: 'https://pay.nandarstore.my.id';
            $pgSlug = trim((string) ($_POST['pg_slug'] ?? ''));
            $pgApiKey = trim((string) ($_POST['pg_api_key'] ?? ''));
            $pgWebhookSecret = trim((string) ($_POST['pg_webhook_secret'] ?? ''));
            $pgIsSandbox = (string) (($_POST['pg_is_sandbox'] ?? '1') === '1' ? '1' : '0');

            $stmt = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
            $stmt->execute(['pg_enabled', $pgEnabled]);
            $stmt->execute(['pg_base_url', $pgBaseUrl]);
            $stmt->execute(['pg_slug', $pgSlug]);
            $stmt->execute(['pg_api_key', $pgApiKey]);
            $stmt->execute(['pg_webhook_secret', $pgWebhookSecret]);
            $stmt->execute(['pg_is_sandbox', $pgIsSandbox]);

            if ($action === 'test_pg_api') {
                $test = pg_test_connection([
                    'base_url' => $pgBaseUrl,
                    'slug' => $pgSlug,
                    'api_key' => $pgApiKey,
                    'webhook_secret' => $pgWebhookSecret,
                ]);
                $flash = $test['message'];
                $flashType = $test['ok'] ? 'success' : 'error';
            } else {
                $flash = 'Konfigurasi Payment Gateway NandarPay berhasil disimpan.';
            }
            $activeTab = 'tab-integrations';
        } elseif ($action === 'sync_pk_ready' || $action === 'sync_pk_all') {
            $syncRes = pk_sync_products_to_db($action === 'sync_pk_all');
            $flash = $syncRes['message'];
            $flashType = $syncRes['ok'] ? 'success' : 'error';
            $activeTab = 'tab-catalog';
        } elseif ($action === 'save_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $providerSource = ($_POST['provider_source'] ?? 'local') === 'premiumkita' ? 'premiumkita' : 'local';
            $fields = [
                trim((string) ($_POST['group_slug'] ?? 'custom')) ?: 'custom',
                trim((string) ($_POST['brand_key'] ?? 'nutrya')) ?: 'nutrya',
                trim((string) ($_POST['brand_label'] ?? 'Digital')) ?: 'Digital',
                trim((string) ($_POST['title'] ?? 'PRODUK BARU')),
                trim((string) ($_POST['variant_label'] ?? '1 BULAN')) ?: '1 BULAN',
                trim((string) ($_POST['category'] ?? 'Streaming')),
                max(0, (int) ($_POST['price'] ?? 0)),
                max(0, (int) ($_POST['stock'] ?? 0)),
                in_array($_POST['delivery_type'] ?? 'instant', ['instant', 'manual'], true) ? $_POST['delivery_type'] : 'instant',
                isset($_POST['is_featured']) ? 1 : 0,
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['account_template'] ?? '')),
                $providerSource,
                trim((string) ($_POST['provider_product_id'] ?? '')),
            ];

            if ($id > 0) {
                $stmt = $pdo->prepare(<<<'SQL'
                    UPDATE products SET
                        group_slug = ?, brand_key = ?, brand_label = ?, title = ?,
                        variant_label = ?, category = ?, price = ?, stock = ?,
                        delivery_type = ?, is_featured = ?, description = ?, account_template = ?,
                        provider_source = ?, provider_product_id = ?
                    WHERE id = ?
                SQL);
                $stmt->execute([...$fields, $id]);
                $flash = 'Produk berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare(<<<'SQL'
                    INSERT INTO products (
                        group_slug, brand_key, brand_label, title, variant_label, category,
                        price, stock, delivery_type, is_featured, description, account_template,
                        provider_source, provider_product_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                SQL);
                $stmt->execute($fields);
                $flash = 'Produk baru berhasil ditambahkan.';
            }
            $activeTab = 'tab-catalog';
        } elseif ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
                    $stmt->execute([$id]);
                    $flash = 'Produk berhasil dihapus.';
                } catch (Throwable $e) {
                    $pdo->prepare('UPDATE products SET stock = 0 WHERE id = ?')->execute([$id]);
                    $flash = 'Produk memiliki riwayat transaksi sehingga stok diatur menjadi 0.';
                }
            }
            $activeTab = 'tab-catalog';
        } elseif ($action === 'fulfill_order') {
            $inv = trim((string) ($_POST['invoice'] ?? ''));
            if ($inv !== '') {
                fulfill_order($inv);
                $flash = "Invoice {$inv} ditandai Lunas dan akses produk telah dirilis.";
            }
            $activeTab = 'tab-orders';
        } elseif ($action === 'sync_pg_order') {
            $inv = trim((string) ($_POST['invoice'] ?? ''));
            if ($inv !== '') {
                $updated = sync_order_payment_status($inv);
                $flash = $updated
                    ? "Status pembayaran {$inv}: " . strtoupper((string) $updated['status']) . '.'
                    : 'Invoice tidak ditemukan.';
            }
            $activeTab = 'tab-orders';
        } elseif ($action === 'retry_pk_order') {
            $inv = trim((string) ($_POST['invoice'] ?? ''));
            if ($inv !== '') {
                $updated = retry_premiumkita_fulfillment($inv);
                if ($updated && ($updated['pk_status'] ?? '') === 'sukses') {
                    $flash = "Order {$inv} berhasil diteruskan ke PremiumKita!";
                } else {
                    $flash = 'Gagal kirim ulang ke PremiumKita: ' . ($updated['pk_error'] ?? 'Error tidak diketahui');
                    $flashType = 'error';
                }
            }
            $activeTab = 'tab-orders';
        }

        $settings = get_settings();
        $storeName = $settings['store_name'] ?? 'DIGIVA.ID';
    }
}

$products = get_all_products();
$orders = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 40')->fetchAll();
$webhookLogs = $pdo->query('SELECT * FROM webhook_logs ORDER BY id DESC LIMIT 15')->fetchAll();
$stats = [
    'total_products' => count($products),
    'total_orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'paid_revenue' => (int) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'paid'")->fetchColumn(),
];

$pkCreds = pk_get_credentials($settings);
$pgCreds = pg_get_credentials($settings);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$webhookFullUrl = $scheme . '://' . $host . app_url('webhook.php');

$pageTitle = 'Admin Panel — ' . $storeName;
$activePage = 'admin';
$showDock = false;
$showContextHeader = false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23072D44'/%3E%3Ccircle cx='20' cy='20' r='6' fill='%239CCDDB'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="ocean-canvas no-dock page-admin">
  <header class="admin-topbar">
    <div class="admin-topbar-inner">
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="inline-size:34px; block-size:34px; color:var(--navy-deep);"><?= render_brand_monogram('orb-monogram') ?></span>
        <div>
          <strong style="display:block; font-size:15px; color:var(--navy-deep);">Admin <?= e($storeName) ?></strong>
          <span style="font-size:11.5px; color:var(--ink-muted);">Panel Manajemen &amp; Integrasi API</span>
        </div>
      </div>

      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <a href="<?= e(app_url('index.php')) ?>" class="reset-filter-btn" style="padding:8px 14px;">Lihat Toko &rarr;</a>
        <a href="<?= e(app_url('admin.php?logout=1')) ?>" class="btn-wa-outline" style="min-block-size:38px; padding:6px 14px; font-size:12.5px; border-color:#fecaca; color:var(--danger);">Keluar</a>
      </div>
    </div>
  </header>

  <main class="market-main" style="padding-top:22px;">
    <?php if ($flash !== ''): ?>
      <div
        class="step-card"
        style="margin-bottom:18px; border-color:<?= $flashType === 'error' ? 'var(--danger)' : 'var(--success)' ?>; background:<?= $flashType === 'error' ? 'var(--danger-bg)' : '#f0fdf4' ?>; color:<?= $flashType === 'error' ? 'var(--danger)' : '#166534' ?>;"
        role="status"
      >
        <strong><?= e($flash) ?></strong>
      </div>
    <?php endif; ?>

    <div class="admin-kpi-grid">
      <div class="step-card" style="padding:16px 18px;">
        <div style="font-size:11.5px; color:var(--ink-muted); font-weight:700;">Total Produk</div>
        <div style="font-size:22px; font-weight:800; color:var(--navy-deep);"><?= $stats['total_products'] ?> SKU</div>
      </div>
      <div class="step-card" style="padding:16px 18px;">
        <div style="font-size:11.5px; color:var(--ink-muted); font-weight:700;">Total Pesanan</div>
        <div style="font-size:22px; font-weight:800; color:var(--navy-deep);"><?= $stats['total_orders'] ?></div>
      </div>
      <div class="step-card" style="padding:16px 18px;">
        <div style="font-size:11.5px; color:var(--ink-muted); font-weight:700;">Omzet Lunas</div>
        <div style="font-size:22px; font-weight:800; color:var(--ocean-blue);"><?= e(format_rp($stats['paid_revenue'])) ?></div>
      </div>
      <div class="step-card" style="padding:16px 18px;">
        <div style="font-size:11.5px; color:var(--ink-muted); font-weight:700;">Status Integrasi</div>
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
          <span class="status-pill <?= $pkCreds['enabled'] && $pkCreds['key_user'] !== '' ? 'is-paid' : 'is-pending' ?>">
            PK: <?= $pkCreds['enabled'] && $pkCreds['key_user'] !== '' ? 'Aktif' : 'Off' ?>
          </span>
          <span class="status-pill <?= $pgCreds['enabled'] && $pgCreds['api_key'] !== '' ? 'is-paid' : 'is-pending' ?>">
            PG: <?= $pgCreds['enabled'] && $pgCreds['api_key'] !== '' ? ($pgCreds['is_sandbox'] ? 'Sandbox' : 'Live') : 'Off' ?>
          </span>
        </div>
      </div>
    </div>

    <div class="admin-tabs-wrap" data-tabs-root>
      <div class="admin-tablist" focusgroup="tablist nomemory" role="tablist" aria-label="Menu panel admin">
        <button type="button" role="tab" class="admin-tab-btn" id="btn-tab-orders" data-tab-target="tab-orders" aria-selected="<?= $activeTab === 'tab-orders' ? 'true' : 'false' ?>" <?= $activeTab === 'tab-orders' ? 'focusgroupstart' : '' ?>>Pesanan &amp; Webhook</button>
        <button type="button" role="tab" class="admin-tab-btn" id="btn-tab-catalog" data-tab-target="tab-catalog" aria-selected="<?= $activeTab === 'tab-catalog' ? 'true' : 'false' ?>" <?= $activeTab === 'tab-catalog' ? 'focusgroupstart' : '' ?>>Katalog &amp; Sync PremiumKita</button>
        <button type="button" role="tab" class="admin-tab-btn" id="btn-tab-integrations" data-tab-target="tab-integrations" aria-selected="<?= $activeTab === 'tab-integrations' ? 'true' : 'false' ?>" <?= $activeTab === 'tab-integrations' ? 'focusgroupstart' : '' ?>>Integrasi API &amp; Payment</button>
        <button type="button" role="tab" class="admin-tab-btn" id="btn-tab-settings" data-tab-target="tab-settings" aria-selected="<?= $activeTab === 'tab-settings' ? 'true' : 'false' ?>" <?= $activeTab === 'tab-settings' ? 'focusgroupstart' : '' ?>>Pengaturan &amp; Keamanan</button>
      </div>

      <!-- TAB 1: PESANAN & WEBHOOK -->
      <div id="tab-orders" role="tabpanel" aria-labelledby="btn-tab-orders" <?= $activeTab !== 'tab-orders' ? 'hidden="until-found"' : 'tabindex="0"' ?>>
        <div class="admin-panel-stack">
          <section class="step-card">
            <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:12px;">Pesanan Terbaru</h2>
            <div class="table-scroll">
              <table class="ocean-table">
                <thead>
                  <tr>
                    <th>Invoice &amp; Gateway</th>
                    <th>Produk</th>
                    <th>Pembeli</th>
                    <th>Total</th>
                    <th>Status Bayar &amp; PK</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $ord): ?>
                    <tr>
                      <td>
                        <strong><?= e($ord['invoice']) ?></strong>
                        <?php if (!empty($ord['pg_txn_id'])): ?>
                          <br><small style="color:var(--ink-muted);">PG: <code><?= e($ord['pg_txn_id']) ?></code> <?= !empty($ord['pg_is_sandbox']) ? '(Sandbox)' : '' ?></small>
                        <?php endif; ?>
                      </td>
                      <td><?= e($ord['product_title']) ?> (<?= (int) $ord['quantity'] ?>x)</td>
                      <td><?= e($ord['customer_name']) ?><br><small style="color:var(--ink-muted);"><?= e($ord['customer_email']) ?></small></td>
                      <td><strong><?= e(format_rp((int) $ord['total'])) ?></strong></td>
                      <td>
                        <span class="status-pill <?= $ord['status'] === 'paid' ? 'is-paid' : ($ord['status'] === 'cancelled' ? 'is-cancelled' : 'is-pending') ?>">
                          <?= $ord['status'] === 'paid' ? 'Lunas' : ($ord['status'] === 'cancelled' ? 'Batal' : 'Pending') ?>
                        </span>
                        <?php if (($ord['pk_status'] ?? '') === 'sukses'): ?>
                          <br><small style="color:var(--success); font-weight:700;">PK: Sukses (<?= e($ord['pk_invoice_number']) ?>)</small>
                        <?php elseif (($ord['pk_status'] ?? '') === 'failed'): ?>
                          <br><small style="color:var(--danger); font-weight:700;" title="<?= e($ord['pk_error']) ?>">PK Gagal: <?= e($ord['pk_error']) ?></small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                          <a class="reset-filter-btn" href="<?= e(app_url('invoice.php?inv=' . rawurlencode($ord['invoice']))) ?>">Lihat</a>
                          <?php if ($ord['status'] === 'pending' && !empty($ord['pg_txn_id'])): ?>
                            <form method="post" action="<?= e(app_url('admin.php')) ?>" style="display:inline;">
                              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="sync_pg_order">
                              <input type="hidden" name="invoice" value="<?= e($ord['invoice']) ?>">
                              <button type="submit" class="reset-filter-btn">Sync Bayar</button>
                            </form>
                          <?php endif; ?>
                          <?php if ($ord['status'] === 'pending'): ?>
                            <form method="post" action="<?= e(app_url('admin.php')) ?>" style="display:inline;">
                              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="fulfill_order">
                              <input type="hidden" name="invoice" value="<?= e($ord['invoice']) ?>">
                              <button type="submit" class="reset-filter-btn" style="background:var(--ocean-blue); color:#fff; border-color:transparent;">Set Lunas</button>
                            </form>
                          <?php elseif (($ord['pk_status'] ?? '') === 'failed'): ?>
                            <form method="post" action="<?= e(app_url('admin.php')) ?>" style="display:inline;">
                              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="retry_pk_order">
                              <input type="hidden" name="invoice" value="<?= e($ord['invoice']) ?>">
                              <button type="submit" class="reset-filter-btn" style="background:var(--danger); color:#fff; border-color:transparent;">Kirim Ulang PK</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="step-card">
            <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:10px;">Log Webhook NandarPay Terbaru</h2>
            <?php if (empty($webhookLogs)): ?>
              <p style="margin:0; font-size:13px; color:var(--ink-muted);">Belum ada notifikasi webhook masuk ke <code>webhook.php</code>.</p>
            <?php else: ?>
              <div class="table-scroll">
                <table class="ocean-table">
                  <thead>
                    <tr>
                      <th>Waktu</th>
                      <th>Order ID</th>
                      <th>Txn ID</th>
                      <th>Status</th>
                      <th>Signature</th>
                      <th>HTTP</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($webhookLogs as $log): ?>
                      <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><strong><?= e($log['order_id'] ?: '-') ?></strong></td>
                        <td><code><?= e($log['txn_id'] ?: '-') ?></code></td>
                        <td><?= e($log['status'] ?: '-') ?></td>
                        <td><span class="status-pill <?= (int) $log['signature_valid'] === 1 ? 'is-paid' : 'is-cancelled' ?>"><?= (int) $log['signature_valid'] === 1 ? 'Valid' : 'Invalid' ?></span></td>
                        <td><?= (int) $log['http_code'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
        </div>
      </div>

      <!-- TAB 2: KATALOG & SYNC PREMIUMKITA -->
      <div id="tab-catalog" role="tabpanel" aria-labelledby="btn-tab-catalog" <?= $activeTab !== 'tab-catalog' ? 'hidden="until-found"' : 'tabindex="0"' ?>>
        <div class="admin-panel-stack">
          <section class="step-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
              <div>
                <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep);">Katalog Produk Digital (<?= count($products) ?>)</h2>
                <p style="margin:3px 0 0; font-size:12.5px; color:var(--ink-muted);">Tambah manual atau tarik langsung dari API PremiumKita (<code>get_ready_product</code> / <code>get_all_product</code>).</p>
              </div>
              <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <form method="post" action="<?= e(app_url('admin.php')) ?>" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="sync_pk_ready">
                  <button type="submit" class="btn-wa-outline" style="min-block-size:42px; padding:8px 14px; border-color:var(--border-ice);">Sync Produk Ready (PK)</button>
                </form>
                <form method="post" action="<?= e(app_url('admin.php')) ?>" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="sync_pk_all">
                  <button type="submit" class="btn-wa-outline" style="min-block-size:42px; padding:8px 14px; border-color:var(--border-ice);">Sync Semua Produk (PK)</button>
                </form>
                <button type="button" class="btn-primary-ocean" style="min-block-size:42px; padding:8px 16px;" id="open-add-product-btn">+ Tambah Produk</button>
              </div>
            </div>

            <div class="table-scroll">
              <table class="ocean-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Produk</th>
                    <th>Sumber API</th>
                    <th>Kategori</th>
                    <th>Harga Jual</th>
                    <th>Stok</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $p): ?>
                    <tr>
                      <td>#<?= (int) $p['id'] ?></td>
                      <td>
                        <strong><?= e($p['title']) ?></strong><br>
                        <small style="color:var(--ink-muted);"><?= e($p['brand_label']) ?> &middot; <?= e($p['variant_label']) ?></small>
                      </td>
                      <td>
                        <?php if (($p['provider_source'] ?? 'local') === 'premiumkita'): ?>
                          <span class="status-pill is-paid">PremiumKita #<?= e($p['provider_product_id']) ?></span>
                          <?php if ((int) ($p['provider_cost'] ?? 0) > 0): ?>
                            <br><small style="color:var(--ink-muted);">Modal: <?= e(format_rp((int) $p['provider_cost'])) ?></small>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="status-pill" style="background:#f1f5f9; color:var(--ink-muted);">Lokal</span>
                        <?php endif; ?>
                      </td>
                      <td><?= e($p['category']) ?></td>
                      <td><strong><?= e(format_rp((int) $p['price'])) ?></strong></td>
                      <td><span class="<?= (int) $p['stock'] <= 0 ? 'stock-zero' : '' ?>"><?= (int) $p['stock'] ?></span></td>
                      <td>
                        <div style="display:flex; gap:6px;">
                          <button
                            type="button"
                            class="reset-filter-btn js-edit-product"
                            data-product='<?= e(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>'
                          >Edit</button>
                          <form method="post" action="<?= e(app_url('admin.php')) ?>" onsubmit="return confirm('Hapus produk ini?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="reset-filter-btn" style="color:var(--danger); border-color:#fecaca;">Hapus</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>

      <!-- TAB 3: INTEGRASI API & PAYMENT GATEWAY -->
      <div id="tab-integrations" role="tabpanel" aria-labelledby="btn-tab-integrations" <?= $activeTab !== 'tab-integrations' ? 'hidden="until-found"' : 'tabindex="0"' ?>>
        <div class="admin-integrations-grid">
          <!-- PremiumKita Open API -->
          <section class="step-card">
            <div class="step-card-head">
              <span class="step-badge">API</span>
              <h2>PremiumKita Open API (<code>/app/</code>)</h2>
            </div>
            <p style="margin:0 0 14px; font-size:12.5px; color:var(--ink-muted);">
              Digunakan untuk sinkronisasi katalog (<code>get_all_product</code>) dan pemesanan otomatis (<code>koneksi=order</code>) setelah pembayaran lunas.
            </p>

            <form method="post" action="<?= e(app_url('admin.php')) ?>" class="form-stack">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="active_tab" value="tab-integrations">

              <label style="display:flex; align-items:center; gap:9px; font-weight:700; font-size:13.5px; color:var(--navy-deep);">
                <input type="checkbox" name="pk_enabled" value="1" <?= $pkCreds['enabled'] ? 'checked' : '' ?>>
                <span>Aktifkan Order Otomatis ke PremiumKita</span>
              </label>

              <div class="field">
                <label for="pk_base_url">Base URL Endpoint</label>
                <div class="input-box">
                  <input id="pk_base_url" name="pk_base_url" type="url" required value="<?= e($pkCreds['base_url']) ?>">
                </div>
              </div>

              <div class="field">
                <label for="pk_id_user">ID User VIP Reseller (<code>id_user</code>)</label>
                <div class="input-box">
                  <input id="pk_id_user" name="pk_id_user" type="text" placeholder="Contoh: PK0012" value="<?= e($pkCreds['id_user']) ?>">
                </div>
              </div>

              <div class="field">
                <label for="pk_key_user">Secret Key Open API (<code>key_user</code> / <code>X-API-Secret</code>)</label>
                <div class="input-box">
                  <input id="pk_key_user" name="pk_key_user" type="password" autocomplete="off" placeholder="Tempel Secret Key dari Profil > Open API Reseller" value="<?= e($pkCreds['key_user']) ?>">
                  <button type="button" class="toggle-secret-btn" data-toggle-secret="pk_key_user">Lihat</button>
                </div>
              </div>

              <div class="form-2col">
                <div class="field">
                  <label for="pk_markup_percent">Markup Sync (%)</label>
                  <div class="input-box"><input id="pk_markup_percent" name="pk_markup_percent" type="number" min="0" step="0.5" value="<?= e((string) $pkCreds['markup_percent']) ?>"></div>
                </div>
                <div class="field">
                  <label for="pk_markup_fixed">Markup Tetap (+Rp)</label>
                  <div class="input-box"><input id="pk_markup_fixed" name="pk_markup_fixed" type="number" min="0" step="100" value="<?= e((string) $pkCreds['markup_fixed']) ?>"></div>
                </div>
              </div>

              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" name="action" value="save_pk_api" class="btn-primary-ocean" style="flex:1;">Simpan PremiumKita</button>
                <button type="submit" name="action" value="test_pk_api" class="btn-wa-outline" style="border-color:var(--border-ice);">Tes Koneksi API</button>
              </div>
            </form>
          </section>

          <!-- NandarPay Payment Gateway -->
          <section class="step-card">
            <div class="step-card-head">
              <span class="step-badge">PAY</span>
              <h2>Payment Gateway NandarPay v2</h2>
            </div>
            <p style="margin:0 0 14px; font-size:12.5px; color:var(--ink-muted);">
              Membuat tagihan QRIS otomatis via <code>POST /api/v2/create-transaction/{slug}/{order_id}</code> dan verifikasi webhook <code>X-PG-Signature</code>.
            </p>

            <form method="post" action="<?= e(app_url('admin.php')) ?>" class="form-stack">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="active_tab" value="tab-integrations">

              <label style="display:flex; align-items:center; gap:9px; font-weight:700; font-size:13.5px; color:var(--navy-deep);">
                <input type="checkbox" name="pg_enabled" value="1" <?= $pgCreds['enabled'] ? 'checked' : '' ?>>
                <span>Aktifkan Tagihan QRIS NandarPay saat Checkout</span>
              </label>

              <div class="field">
                <label for="pg_base_url">Base URL Gateway</label>
                <div class="input-box">
                  <input id="pg_base_url" name="pg_base_url" type="url" required value="<?= e($pgCreds['base_url']) ?>">
                </div>
              </div>

              <div class="form-2col">
                <div class="field">
                  <label for="pg_slug">Project Slug (<code>slug</code>)</label>
                  <div class="input-box">
                    <input id="pg_slug" name="pg_slug" type="text" placeholder="Contoh: stpreku" value="<?= e($pgCreds['slug']) ?>">
                  </div>
                </div>
                <div class="field">
                  <label for="pg_is_sandbox">Mode Transaksi</label>
                  <div class="input-box">
                    <select id="pg_is_sandbox" name="pg_is_sandbox">
                      <option value="1" <?= $pgCreds['is_sandbox'] ? 'selected' : '' ?>>Sandbox (Uji Simulasi)</option>
                      <option value="0" <?= !$pgCreds['is_sandbox'] ? 'selected' : '' ?>>Live (Produksi)</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="field">
                <label for="pg_api_key">API Key (Header <code>X-Api-Key</code>)</label>
                <div class="input-box">
                  <input id="pg_api_key" name="pg_api_key" type="password" autocomplete="off" placeholder="Tempel X-Api-Key dari NandarPay" value="<?= e($pgCreds['api_key']) ?>">
                  <button type="button" class="toggle-secret-btn" data-toggle-secret="pg_api_key">Lihat</button>
                </div>
              </div>

              <div class="field">
                <label for="pg_webhook_secret">Webhook Secret (<code>whsec_...</code>)</label>
                <div class="input-box">
                  <input id="pg_webhook_secret" name="pg_webhook_secret" type="password" autocomplete="off" placeholder="Secret verifikasi HMAC-SHA256 X-PG-Signature" value="<?= e($pgCreds['webhook_secret']) ?>">
                  <button type="button" class="toggle-secret-btn" data-toggle-secret="pg_webhook_secret">Lihat</button>
                </div>
              </div>

              <div style="padding:12px 14px; border-radius:12px; background:#f8fafc; border:1px solid var(--border-soft);">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px;">
                  <strong style="font-size:12px; color:var(--navy-deep);">URL Webhook Callback Anda</strong>
                  <button type="button" class="reset-filter-btn" data-copy-target="webhook-url-copy" data-copy-message="URL Webhook berhasil disalin!">Salin URL</button>
                </div>
                <code id="webhook-url-copy" style="font-size:12px; color:var(--ocean-blue);"><?= e($webhookFullUrl) ?></code>
              </div>

              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" name="action" value="save_pg_api" class="btn-primary-ocean" style="flex:1;">Simpan Gateway</button>
                <button type="submit" name="action" value="test_pg_api" class="btn-wa-outline" style="border-color:var(--border-ice);">Tes Koneksi Gateway</button>
              </div>
            </form>
          </section>
        </div>
      </div>

      <!-- TAB 4: PENGATURAN TOKO & KEAMANAN ADMIN -->
      <div id="tab-settings" role="tabpanel" aria-labelledby="btn-tab-settings" <?= $activeTab !== 'tab-settings' ? 'hidden="until-found"' : 'tabindex="0"' ?>>
        <div class="admin-integrations-grid">
          <section class="step-card">
            <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:14px;">Identitas &amp; WhatsApp CS Toko</h2>
            <form method="post" action="<?= e(app_url('admin.php')) ?>" class="form-stack">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_settings">
              <div class="field">
                <label for="set_name">Nama Toko</label>
                <div class="input-box"><input id="set_name" name="store_name" type="text" required value="<?= e($storeName) ?>"></div>
              </div>
              <div class="field">
                <label for="set_tag">Tagline</label>
                <div class="input-box"><input id="set_tag" name="store_tagline" type="text" required value="<?= e($settings['store_tagline'] ?? 'Digital Solutions') ?>"></div>
              </div>
              <div class="field">
                <label for="set_powered">Label Powered By</label>
                <div class="input-box"><input id="set_powered" name="powered_by" type="text" required value="<?= e($settings['powered_by'] ?? 'Digiva Cloud Engine') ?>"></div>
              </div>
              <div class="field">
                <label for="set_wa">WhatsApp CS (format 628...)</label>
                <div class="input-box"><input id="set_wa" name="whatsapp_cs" type="text" required value="<?= e($settings['whatsapp_cs'] ?? '6285198090571') ?>"></div>
              </div>
              <button type="submit" class="btn-primary-ocean">Simpan Identitas Toko</button>
            </form>
          </section>

          <section class="step-card">
            <h2 style="font-size:17px; font-weight:800; color:var(--navy-deep); margin-bottom:14px;">Keamanan Akun Admin</h2>
            <form method="post" action="<?= e(app_url('admin.php')) ?>" class="form-stack">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_admin_credentials">
              <div class="field">
                <label for="sec_username">Username Admin</label>
                <div class="input-box"><input id="sec_username" name="admin_username" type="text" required minlength="3" value="<?= e($settings['admin_username'] ?? ADMIN_DEFAULT_USER) ?>"></div>
              </div>
              <div class="field">
                <label for="sec_curr">Password Saat Ini (Wajib untuk konfirmasi)</label>
                <div class="input-box"><input id="sec_curr" name="current_password" type="password" required autocomplete="current-password"></div>
              </div>
              <div class="field">
                <label for="sec_new">Password Baru (Kosongkan jika tidak mengubah)</label>
                <div class="input-box"><input id="sec_new" name="new_password" type="password" minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter"></div>
              </div>
              <button type="submit" class="btn-primary-ocean">Perbarui Akses Admin</button>
            </form>
          </section>
        </div>
      </div>
    </div>

    <!-- Native Modal Dialog Tambah/Edit Produk (closedby="any" + overscroll contain) -->
    <dialog id="product-editor-dialog" closedby="any" aria-labelledby="product-dialog-title">
      <form method="post" action="<?= e(app_url('admin.php')) ?>" class="form-stack" style="padding:22px; gap:14px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="id" id="prod_id" value="0">

        <div style="display:flex; justify-content:space-between; align-items:center;">
          <h1 id="product-dialog-title" style="font-size:18px; font-weight:800; color:var(--navy-deep);">Tambah Produk Digital</h1>
          <button type="button" class="reset-filter-btn" id="close-product-dialog" aria-label="Tutup dialog">&times; Tutup</button>
        </div>

        <div class="form-2col">
          <div class="field">
            <label for="prod_title">Nama Produk</label>
            <div class="input-box"><input id="prod_title" name="title" type="text" required autofocus></div>
          </div>
          <div class="field">
            <label for="prod_variant">Label Paket</label>
            <div class="input-box"><input id="prod_variant" name="variant_label" type="text" required value="1 BULAN"></div>
          </div>
        </div>

        <div class="form-2col">
          <div class="field">
            <label for="prod_source">Sumber Pengiriman</label>
            <div class="input-box">
              <select id="prod_source" name="provider_source">
                <option value="local">Stok Lokal / Template</option>
                <option value="premiumkita">PremiumKita Open API</option>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="prod_provider_id">ID Produk PremiumKita (<code>id_produk</code>)</label>
            <div class="input-box"><input id="prod_provider_id" name="provider_product_id" type="text" placeholder="Contoh: 101"></div>
          </div>
        </div>

        <div class="form-2col">
          <div class="field">
            <label for="prod_brand_key">Tema / Ikon Brand</label>
            <div class="input-box">
              <select id="prod_brand_key" name="brand_key">
                <?php foreach (['netflix','spotify','youtube','canva','openai','capcut','adobe','alight','apple','bstation','disney','viu','gemini','claude','microsoft','zoom','camscanner','nordvpn','beauty','ebook','nutrya'] as $bk): ?>
                  <option value="<?= e($bk) ?>"><?= e(ucfirst($bk)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="prod_brand_label">Label Brand Kartu</label>
            <div class="input-box"><input id="prod_brand_label" name="brand_label" type="text" required value="Netflix"></div>
          </div>
        </div>

        <div class="form-2col">
          <div class="field">
            <label for="prod_group">Slug Grup Varian</label>
            <div class="input-box"><input id="prod_group" name="group_slug" type="text" required value="netflix"></div>
          </div>
          <div class="field">
            <label for="prod_category">Kategori</label>
            <div class="input-box">
              <select id="prod_category" name="category">
                <option value="Streaming">Streaming</option>
                <option value="AI & Produktivitas">AI &amp; Produktivitas</option>
                <option value="Desain & Editing">Desain &amp; Editing</option>
                <option value="Musik">Musik</option>
                <option value="E-Book & Lainnya">E-Book &amp; Lainnya</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-3col">
          <div class="field">
            <label for="prod_price">Harga (Rp)</label>
            <div class="input-box"><input id="prod_price" name="price" type="number" min="0" required value="25000"></div>
          </div>
          <div class="field">
            <label for="prod_stock">Stok</label>
            <div class="input-box"><input id="prod_stock" name="stock" type="number" min="0" required value="50"></div>
          </div>
          <div class="field">
            <label for="prod_delivery">Pengiriman</label>
            <div class="input-box">
              <select id="prod_delivery" name="delivery_type">
                <option value="instant">Instan</option>
                <option value="manual">Manual CS</option>
              </select>
            </div>
          </div>
        </div>

        <div class="field">
          <label for="prod_desc">Deskripsi &amp; S&amp;K</label>
          <div class="input-box" style="padding:10px 14px;">
            <textarea id="prod_desc" name="description" rows="3" style="resize:vertical;"></textarea>
          </div>
        </div>

        <div class="field">
          <label for="prod_tpl">Template Akses Lokal (jika Sumber Lokal)</label>
          <div class="input-box" style="padding:10px 14px;">
            <textarea id="prod_tpl" name="account_template" rows="2" style="resize:vertical;"></textarea>
          </div>
        </div>

        <label style="display:inline-flex; align-items:center; gap:8px; font-weight:700; font-size:13px;">
          <input type="checkbox" id="prod_featured" name="is_featured" value="1">
          <span>Tampilkan di produk rekomendasi unggulan</span>
        </label>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:4px;">
          <button type="button" class="btn-wa-outline" id="cancel-product-dialog">Batal</button>
          <button type="submit" class="btn-primary-ocean">Simpan Produk</button>
        </div>
      </form>
    </dialog>
  </main>

  <div class="toast-stack" id="toast-stack" role="status" aria-live="polite"></div>
  <script>window.DIGIVA_BASE = <?= json_encode(app_url(), JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
