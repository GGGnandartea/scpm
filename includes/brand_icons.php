<?php
/**
 * Pustaka SVG kustom untuk monogram toko, ikon UI dual-tone,
 * ikon WhatsApp, logo QRIS resmi bergaya vektor, serta ikon 18+ brand produk digital.
 */

declare(strict_types=1);

/**
 * Mengembalikan SVG monogram toko (lingkaran dinamis dual-tone bergaya modern).
 */
function render_brand_monogram(string $class = 'brand-monogram'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 40 40" fill="none" aria-hidden="true">'
        . '<path d="M20 4C11.163 4 4 11.163 4 20c0 5.5 2.775 10.35 7 13.23V20a9 9 0 0 1 17.65-2.5h4.24C31.73 9.85 26.44 4 20 4Z" fill="currentColor"/>'
        . '<path d="M20 36c8.837 0 16-7.163 16-16 0-5.5-2.775-10.35-7-13.23V20a9 9 0 0 1-17.65 2.5H7.11C8.27 30.15 13.56 36 20 36Z" fill="currentColor" opacity="0.72"/>'
        . '<circle cx="20" cy="20" r="4.2" fill="currentColor"/>'
        . '</svg>';
}

/**
 * Mengembalikan logo aplikasi di dalam kotak putih kartu produk / hero tile.
 */
function render_app_logo_svg(string $brandKey): string
{
    static $uid = 0;
    $uid++;
    $canvaId = 'canvaGrad-' . $uid;
    $gemId = 'gemGrad-' . $uid;

    return match ($brandKey) {
        'netflix' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<path d="M14 8h6.5l11 32H25L14 8Z" fill="#B1060F"/>'
            . '<path d="M14 8h6.5v32H14V8Z" fill="#E50914"/>'
            . '<path d="M27.5 8H34v32h-6.5V8Z" fill="#E50914"/>'
            . '<path d="M14 8h6.5l13.5 32h-6.5L14 8Z" fill="#FF1E2B"/>'
            . '</svg>',

        'spotify' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<circle cx="24" cy="24" r="18" fill="#1DB954"/>'
            . '<path d="M14.5 18.8c6.2-1.8 14.5-1.5 20.1 1.6" stroke="#fff" stroke-width="3.1" stroke-linecap="round"/>'
            . '<path d="M16.2 24.5c5.1-1.4 11.7-1.1 16.3 1.4" stroke="#fff" stroke-width="2.7" stroke-linecap="round"/>'
            . '<path d="M18 30c3.9-1 8.8-.7 12.2 1.1" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>'
            . '</svg>',

        'youtube' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="6" y="12" width="36" height="24" rx="7" fill="#FF0000"/>'
            . '<path d="M20.5 18.5 31 24l-10.5 5.5v-11Z" fill="#fff"/>'
            . '</svg>',

        'canva' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<defs><linearGradient id="' . $canvaId . '" x1="6" y1="6" x2="42" y2="42" gradientUnits="userSpaceOnUse">'
            . '<stop stop-color="#00C4CC"/><stop offset="0.55" stop-color="#5A32FA"/><stop offset="1" stop-color="#7D2AE8"/></linearGradient></defs>'
            . '<circle cx="24" cy="24" r="18" fill="url(#' . $canvaId . ')"/>'
            . '<path d="M29.8 29.2c-1.3 1.8-3.5 2.8-5.9 2.8-4.4 0-7.5-3.4-7.5-8 0-4.7 3.3-8.1 7.8-8.1 2.4 0 4.4.9 5.5 2.4l-2.6 2c-.7-.8-1.6-1.3-2.9-1.3-2.5 0-4.2 2-4.2 5s1.6 4.9 4.2 4.9c1.4 0 2.4-.6 3.2-1.5l2.4 1.8Z" fill="#fff"/>'
            . '</svg>',

        'openai' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<g stroke="#111827" stroke-width="2.5" stroke-linejoin="round">'
            . '<path d="M24 9.5a6.5 6.5 0 0 1 11.4 4.2l.1 7.6-6.6-3.8-4.9-8Z"/>'
            . '<path d="M36.5 17a6.5 6.5 0 0 1 2 11.9l-6.6 3.8V25l4.6-8Z"/>'
            . '<path d="M36.5 31a6.5 6.5 0 0 1-9.4 7.8L20.5 35l6.6-3.8 9.4-.2Z"/>'
            . '<path d="M24 38.5a6.5 6.5 0 0 1-11.4-4.2l-.1-7.6 6.6 3.8 4.9 8Z"/>'
            . '<path d="M11.5 31a6.5 6.5 0 0 1-2-11.9l6.6-3.8V23l-4.6 8Z"/>'
            . '<path d="M11.5 17a6.5 6.5 0 0 1 9.4-7.8L27.5 13l-6.6 3.8-9.4.2Z"/>'
            . '</g></svg>',

        'capcut' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<path d="M12 14h24l-4.5 5.2H16.5L12 14Zm0 20h24l-4.5-5.2H16.5L12 34Z" fill="#111827"/>'
            . '<path d="M12 18.5 36 30.2v3.3L12 21.8v-3.3Zm0 11L36 17.8v-3.3L12 26.2v3.3Z" fill="#111827"/>'
            . '</svg>',

        'adobe' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="8" fill="#0B253A"/>'
            . '<text x="24" y="30" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="16" fill="#38BDF8">Ad</text>'
            . '</svg>',

        'alight' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="9" fill="#0D2230"/>'
            . '<path d="M24 14a10 10 0 1 0 10 10" stroke="#2DD4BF" stroke-width="2.8" stroke-linecap="round"/>'
            . '<path d="M24 18a6 6 0 1 0 6 6" stroke="#34D399" stroke-width="2.6" stroke-linecap="round"/>'
            . '<circle cx="24" cy="24" r="2.2" fill="#5EEAD4"/>'
            . '</svg>',

        'apple' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="9" fill="#E13049"/>'
            . '<path d="M29.5 14v13.8a3.7 3.7 0 1 1-2.4-3.4V18l-8 2.1v10.7a3.7 3.7 0 1 1-2.4-3.4V17.4l12.8-3.4Z" fill="#fff"/>'
            . '</svg>',

        'bstation' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="13" width="32" height="23" rx="6" fill="#0EA5E9"/>'
            . '<path d="m17 9 4.5 4M31 9l-4.5 4" stroke="#0EA5E9" stroke-width="2.8" stroke-linecap="round"/>'
            . '<circle cx="19" cy="23.5" r="2.2" fill="#fff"/><circle cx="29" cy="23.5" r="2.2" fill="#fff"/>'
            . '<path d="M21 28c1.5 1.4 4.5 1.4 6 0" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>'
            . '</svg>',

        'disney' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<circle cx="24" cy="24" r="18" fill="#0B3B8C"/>'
            . '<path d="M13 24c4-6 14-8 22-4" stroke="#38BDF8" stroke-width="2.5" stroke-linecap="round"/>'
            . '<text x="24" y="30" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="11" fill="#fff">D+</text>'
            . '</svg>',

        'viu' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<circle cx="24" cy="24" r="18" fill="#F59E0B"/>'
            . '<text x="24" y="29" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="14" fill="#fff">viu</text>'
            . '</svg>',

        'gemini' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<defs><linearGradient id="' . $gemId . '" x1="10" y1="10" x2="38" y2="38" gradientUnits="userSpaceOnUse">'
            . '<stop stop-color="#3B82F6"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient></defs>'
            . '<path d="M24 6c1.5 9.8 8.2 16.5 18 18-9.8 1.5-16.5 8.2-18 18-1.5-9.8-8.2-16.5-18-18 9.8-1.5 16.5-8.2 18-18Z" fill="url(#' . $gemId . ')"/>'
            . '</svg>',

        'claude' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<circle cx="24" cy="24" r="18" fill="#D97757"/>'
            . '<path d="M24 12v24M12 24h24M15.5 15.5l17 17M32.5 15.5l-17 17" stroke="#fff" stroke-width="3" stroke-linecap="round"/>'
            . '</svg>',

        'microsoft' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="10" y="10" width="13" height="13" rx="2" fill="#F25022"/>'
            . '<rect x="25" y="10" width="13" height="13" rx="2" fill="#7FBA00"/>'
            . '<rect x="10" y="25" width="13" height="13" rx="2" fill="#00A4EF"/>'
            . '<rect x="25" y="25" width="13" height="13" rx="2" fill="#FFB900"/>'
            . '</svg>',

        'zoom' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<circle cx="24" cy="24" r="18" fill="#2D8CFF"/>'
            . '<rect x="14" y="17.5" width="14" height="13" rx="3" fill="#fff"/>'
            . '<path d="m29.5 21 5-3.2v12.4l-5-3.2V21Z" fill="#fff"/>'
            . '</svg>',

        'camscanner' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="8" fill="#1E293B"/>'
            . '<rect x="8" y="31" width="32" height="9" rx="3" fill="#14B8A6"/>'
            . '<text x="24" y="27" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="15" fill="#fff">CS</text>'
            . '</svg>',

        'nordvpn' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="9" fill="#EF4444"/>'
            . '<path d="M24 14 14 32h5.2l4.8-9 4.8 9H34L24 14Z" fill="#fff"/>'
            . '</svg>',

        'beauty' => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="8" y="8" width="32" height="32" rx="10" fill="#FB7185"/>'
            . '<rect x="14" y="16" width="20" height="16" rx="4" fill="#fff"/>'
            . '<circle cx="24" cy="24" r="4.5" fill="#EC4899"/>'
            . '</svg>',

        default => '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true">'
            . '<rect x="10" y="9" width="28" height="30" rx="5" fill="#064469"/>'
            . '<path d="M16 17h16M16 23h16M16 29h10" stroke="#9CCDDB" stroke-width="2.6" stroke-linecap="round"/>'
            . '</svg>',
    };
}

/**
 * Mengembalikan ikon kategori / UI kecil (semua dekoratif jika berada di dalam tombol berlabel).
 */
function render_ui_icon(string $name, string $class = 'ui-svg'): string
{
    return match ($name) {
        'whatsapp' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
            . '<path d="M12 2a9.9 9.9 0 0 0-8.5 15l-1.3 4.8 5-1.3A10 10 0 1 0 12 2Z" fill="#25D366"/>'
            . '<path d="M16.7 14.3c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-1.4-.6-2.5-1.6-3.2-2.9-.2-.2 0-.4.1-.5.1-.1.3-.3.4-.5.1-.1.2-.3.2-.4.1-.2 0-.3 0-.5-.1-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.8.8-1 1.9-.4 3 .8 1.7 2.2 3.1 4 4 .5.2 1.2.5 1.8.6.7.1 1.4 0 1.9-.4.5-.4.8-1 .9-1.5.1-.2 0-.4-.2-.5Z" fill="#fff"/>'
            . '</svg>',

        'whatsapp-outline' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M12 21a9 9 0 1 0-7.7-4.3L3 21l4.4-1.2A9 9 0 0 0 12 21Z"/>'
            . '<path d="M9.5 9.2c.2-.4.5-.4.7-.4h.5c.2 0 .4.1.5.4l.7 1.6c.1.2 0 .4-.1.6l-.5.6c-.1.1-.1.3 0 .5.5.9 1.2 1.6 2.1 2.1.2.1.4.1.5 0l.6-.5c.2-.1.4-.2.6-.1l1.6.7c.3.1.4.3.4.5v.5c0 .4-.3.9-.8 1.2-.7.4-1.6.5-2.6.2-2-.6-4.2-2.7-4.9-4.7-.4-1.1-.3-2.1.2-2.8Z"/>'
            . '</svg>',

        'search' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/>'
            . '</svg>',

        'home' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-9.5Z"/>'
            . '</svg>',

        'grid' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/>'
            . '<rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>'
            . '</svg>',

        'receipt' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>'
            . '</svg>',

        'book' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M2 5.5A2.5 2.5 0 0 1 4.5 3H12v17H4.5A2.5 2.5 0 0 0 2 22V5.5ZM22 5.5A2.5 2.5 0 0 0 19.5 3H12v17h7.5A2.5 2.5 0 0 1 22 22V5.5Z"/>'
            . '</svg>',

        'send' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/>'
            . '</svg>',

        'shield' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>'
            . '</svg>',

        'bag' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M6 8h12l1 12H5L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>'
            . '</svg>',

        'qris' => '<svg class="' . e($class) . '" viewBox="0 0 88 26" fill="none" aria-hidden="true">'
            . '<rect x="1" y="4" width="18" height="18" rx="3" stroke="#072D44" stroke-width="2"/>'
            . '<rect x="5" y="8" width="4" height="4" fill="#072D44"/><rect x="11" y="8" width="4" height="4" fill="#064469"/>'
            . '<rect x="5" y="14" width="4" height="4" fill="#064469"/><rect x="11" y="14" width="4" height="4" fill="#072D44"/>'
            . '<text x="24" y="18.5" font-family="Manrope,sans-serif" font-weight="800" font-size="14" letter-spacing="-0.5" fill="#072D44">QRIS</text>'
            . '</svg>',

        'arrow-left' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>'
            . '</svg>',

        'spark' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/>'
            . '</svg>',

        'play' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3V9Z"/>'
            . '</svg>',

        'palette' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="12" r="9"/><circle cx="9.5" cy="9.5" r="1"/><circle cx="14.5" cy="9.5" r="1"/><circle cx="9" cy="14" r="1"/>'
            . '</svg>',

        'music' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/>'
            . '</svg>',

        'user' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 5-6 8-6s6.5 2 8 6"/>'
            . '</svg>',

        'mail' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'
            . '</svg>',

        'check' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M20 6 9 17l-5-5"/>'
            . '</svg>',

        'copy' => '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
            . '</svg>',

        default => '',
    };
}

/**
 * Menghasilkan QRIS SVG deterministik berdasarkan kode invoice (visual siap scan simulasi).
 */
function render_qris_matrix_svg(string $invoice, int $total): string
{
    $seed = hash('sha256', $invoice . ':' . $total);
    $size = 21;
    $rects = '';
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $inTL = $x < 7 && $y < 7;
            $inTR = $x >= $size - 7 && $y < 7;
            $inBL = $x < 7 && $y >= $size - 7;
            if ($inTL || $inTR || $inBL) {
                continue;
            }
            $idx = ($y * $size + $x) % strlen($seed);
            $val = hexdec($seed[$idx]);
            if (($val + $x * 3 + $y * 5) % 2 === 0) {
                $px = 14 + $x * 8;
                $py = 14 + $y * 8;
                $rects .= "<rect x=\"{$px}\" y=\"{$py}\" width=\"7\" height=\"7\" rx=\"1\" fill=\"#072D44\"/>";
            }
        }
    }

    $finder = function (int $ox, int $oy): string {
        return "<rect x=\"{$ox}\" y=\"{$oy}\" width=\"54\" height=\"54\" rx=\"6\" fill=\"none\" stroke=\"#072D44\" stroke-width=\"7\"/>"
            . '<rect x="' . ($ox + 15) . '" y="' . ($oy + 15) . '" width="24" height="24" rx="3" fill="#064469"/>';
    };

    return '<svg viewBox="0 0 196 196" width="196" height="196" role="img" aria-label="Kode QRIS untuk invoice ' . e($invoice) . '">'
        . '<rect width="196" height="196" rx="14" fill="#FFFFFF"/>'
        . $finder(14, 14)
        . $finder(128, 14)
        . $finder(14, 128)
        . $rects
        . '<rect x="78" y="78" width="40" height="40" rx="10" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2"/>'
        . '<circle cx="98" cy="98" r="12" fill="#064469"/>'
        . '<circle cx="98" cy="98" r="4.5" fill="#9CCDDB"/>'
        . '</svg>';
}
