<?php
/**
 * Konfigurasi utama toko digital DIGIVA.ID.
 * Mengatur path dasar (kompatibel XAMPP /jualan maupun php -S),
 * sesi aman, helper escaping, format rupiah, dan token CSRF.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', __DIR__);
define('DB_PATH', APP_ROOT . '/data/store.db');
define('ADMIN_DEFAULT_USER', 'admin');
define('ADMIN_DEFAULT_PASS', 'digiva2026');

/**
 * Mengembalikan base path URL secara otomatis agar bekerja baik di
 * http://localhost/jualan/ maupun http://localhost:8080/.
 */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = rtrim($scriptDir, '/');
    return $base === '' ? '' : $base;
}

/**
 * Membuat URL relatif terhadap root aplikasi.
 */
function app_url(string $path = ''): string
{
    $base = app_base_path();
    $trimmed = ltrim($path, '/');
    if ($trimmed === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $trimmed;
}

/**
 * Escaping HTML aman untuk mencegah XSS.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format angka menjadi Rupiah Indonesia (contoh: Rp 25.500).
 */
function format_rp(int|float $amount): string
{
    return 'Rp ' . number_format((int) round($amount), 0, ',', '.');
}

/**
 * Normalisasi nomor WhatsApp Indonesia ke format 628...
 */
function normalize_wa(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        return '62' . substr($digits, 1);
    }
    if (str_starts_with($digits, '8')) {
        return '62' . $digits;
    }
    return $digits;
}

/**
 * Membuat link WhatsApp CS dengan pesan pre-filled.
 */
function wa_link(string $waNumber, string $message = ''): string
{
    $clean = normalize_wa($waNumber);
    $url = 'https://wa.me/' . rawurlencode($clean);
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

/**
 * Mengambil atau menghasilkan CSRF token sesi.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memvalidasi CSRF token dari request POST.
 */
function verify_csrf(?string $token): bool
{
    if (!is_string($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
