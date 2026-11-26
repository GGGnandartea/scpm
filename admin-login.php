<?php
/**
 * Halaman Login Khusus Admin (/admin-login.php):
 * Terpisah sepenuhnya dari halaman pembeli / storefront.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

$settings = get_settings();
$storeName = $settings['store_name'] ?? 'DIGIVA.ID';

if (!empty($_SESSION['is_admin'])) {
    header('Location: ' . app_url('admin.php'));
    exit;
}

$error = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman dan coba kembali.';
    } else {
        $usernameInput = trim((string) ($_POST['username'] ?? ''));
        $passwordInput = (string) ($_POST['password'] ?? '');
        $validUser = $settings['admin_username'] ?? ADMIN_DEFAULT_USER;
        $validHash = $settings['admin_password_hash'] ?? password_hash(ADMIN_DEFAULT_PASS, PASSWORD_DEFAULT);

        if (hash_equals($validUser, $usernameInput) && password_verify($passwordInput, $validHash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_user'] = $validUser;
            header('Location: ' . app_url('admin.php'));
            exit;
        }
        $error = 'Username atau password admin tidak sesuai.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="robots" content="noindex,nofollow">
  <title>Login Admin &mdash; <?= e($storeName) ?></title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23072D44'/%3E%3Ccircle cx='20' cy='20' r='6' fill='%239CCDDB'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="ocean-canvas admin-auth-body">
  <main class="admin-auth-shell">
    <section class="step-card admin-auth-card" aria-labelledby="admin-login-title">
      <div class="admin-auth-brand">
        <span class="admin-auth-orb"><?= render_brand_monogram('orb-monogram') ?></span>
        <div>
          <div style="font-size:11px; font-weight:800; letter-spacing:0.06em; text-transform:uppercase; color:var(--ocean-blue);">Portal Khusus Pengelola</div>
          <h1 id="admin-login-title" style="font-size:22px; font-weight:800; color:var(--navy-deep); letter-spacing:-0.03em;">Admin <?= e($storeName) ?></h1>
        </div>
      </div>

      <p style="margin:0 0 18px; font-size:13px; color:var(--ink-muted);">
        Masuk untuk mengelola katalog produk, kredensial API PremiumKita, dan konfigurasi Payment Gateway.
      </p>

      <?php if ($error !== ''): ?>
        <div class="step-card" style="padding:12px 14px; margin-bottom:16px; border-color:var(--danger); background:var(--danger-bg); color:var(--danger); font-size:13px; font-weight:700;" role="alert">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(app_url('admin-login.php')) ?>" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="field">
          <label for="admin_user">Username Admin</label>
          <span id="admin-user-hint" class="hint">Masukkan username pengelola toko.</span>
          <div class="input-box">
            <?= render_ui_icon('user', 'search-lead-icon') ?>
            <input
              id="admin_user"
              name="username"
              type="text"
              required
              autofocus
              autocomplete="username"
              placeholder="Username admin"
              value="<?= e($usernameInput) ?>"
              aria-describedby="admin-user-hint"
              data-error="admin-user-error"
            >
          </div>
          <div id="admin-user-error" class="error-msg">Error: Username admin wajib diisi.</div>
        </div>

        <div class="field">
          <label for="admin_pass">Password</label>
          <span id="admin-pass-hint" class="hint">Kata sandi rahasia dashboard admin.</span>
          <div class="input-box">
            <?= render_ui_icon('shield', 'search-lead-icon') ?>
            <input
              id="admin_pass"
              name="password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              aria-describedby="admin-pass-hint"
              data-error="admin-pass-error"
            >
            <button type="button" class="toggle-secret-btn" data-toggle-secret="admin_pass" aria-label="Tampilkan atau sembunyikan password">Lihat</button>
          </div>
          <div id="admin-pass-error" class="error-msg">Error: Password admin wajib diisi.</div>
        </div>

        <button type="submit" class="btn-primary-ocean" style="width:100%;">Masuk ke Panel Admin</button>
      </form>

      <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--border-soft); display:flex; justify-content:space-between; align-items:center; font-size:12px; color:var(--ink-muted);">
        <a href="<?= e(app_url('index.php')) ?>" style="font-weight:700; color:var(--ocean-blue);">&larr; Kembali ke Toko</a>
        <span>Default: <code>admin</code> / <code>digiva2026</code></span>
      </div>
    </section>
  </main>
  <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
