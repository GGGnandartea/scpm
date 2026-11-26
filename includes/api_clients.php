<?php
/**
 * Klien HTTP resmi untuk:
 * 1. PremiumKita Open API (https://premiumkita.com/app/) — POST x-www-form-urlencoded
 * 2. NandarPay Payment Gateway v2 (https://pay.nandarstore.my.id/api/v2/...) — REST JSON + X-Api-Key + HMAC-SHA256
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Helper HTTP cURL serbaguna untuk request keluar ke API eksternal.
 *
 * @return array{ok:bool, http_code:int, raw:string, json:?array, error:string}
 */
function api_http_request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 20): array
{
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'http_code' => 0,
            'raw' => '',
            'json' => null,
            'error' => 'Ekstensi PHP cURL tidak aktif di server.',
        ];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $raw === false) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'raw' => '',
            'json' => null,
            'error' => 'Koneksi gagal: ' . ($err ?: 'cURL error #' . $errno),
        ];
    }

    $decoded = json_decode((string) $raw, true);
    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'raw' => (string) $raw,
        'json' => is_array($decoded) ? $decoded : null,
        'error' => ($httpCode >= 200 && $httpCode < 300)
            ? ''
            : (is_array($decoded) && !empty($decoded['message'])
                ? (string) $decoded['message']
                : 'HTTP ' . $httpCode),
    ];
}

/* ==========================================================================
   1. PREMIUMKITA OPEN API CLIENT (https://premiumkita.com/app/)
   ========================================================================== */

/**
 * Mengambil konfigurasi kredensial PremiumKita aktif.
 */
function pk_get_credentials(?array $settings = null): array
{
    $s = $settings ?? get_settings();
    return [
        'enabled' => ($s['pk_enabled'] ?? '0') === '1',
        'base_url' => trim((string) ($s['pk_base_url'] ?? 'https://premiumkita.com/app/')) ?: 'https://premiumkita.com/app/',
        'id_user' => trim((string) ($s['pk_id_user'] ?? '')),
        'key_user' => trim((string) ($s['pk_key_user'] ?? '')),
        'markup_percent' => (float) ($s['pk_markup_percent'] ?? 0),
        'markup_fixed' => (int) ($s['pk_markup_fixed'] ?? 0),
    ];
}

/**
 * Mengirim request POST ke endpoint tunggal PremiumKita dengan header X-API-Secret & POST body.
 */
function pk_call(string $koneksi, array $extraParams = [], ?array $overrideCreds = null): array
{
    $creds = $overrideCreds ? array_merge(pk_get_credentials(), $overrideCreds) : pk_get_credentials();
    if ($creds['id_user'] === '' || $creds['key_user'] === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'raw' => '',
            'json' => null,
            'error' => 'ID User dan Secret Key PremiumKita belum diatur di Admin.',
        ];
    }

    $payload = array_merge([
        'koneksi' => $koneksi,
        'id_user' => $creds['id_user'],
        'key_user' => $creds['key_user'],
    ], $extraParams);

    $res = api_http_request(
        'POST',
        $creds['base_url'],
        [
            'Content-Type: application/x-www-form-urlencoded',
            'X-API-Secret: ' . $creds['key_user'],
        ],
        http_build_query($payload)
    );

    if (is_array($res['json']) && isset($res['json']['status']) && $res['json']['status'] === 'error') {
        $res['ok'] = false;
        $res['error'] = (string) ($res['json']['message'] ?? 'API PremiumKita mengembalikan error.');
    }
    return $res;
}

/**
 * Menguji kredensial PremiumKita lewat aksi koneksi=get_kategori.
 */
function pk_test_connection(?array $overrideCreds = null): array
{
    $res = pk_call('get_kategori', [], $overrideCreds);
    if (!$res['ok'] || !is_array($res['json'])) {
        return ['ok' => false, 'message' => $res['error'] ?: 'Respon JSON tidak valid dari PremiumKita.'];
    }
    $count = isset($res['json']['data']) && is_array($res['json']['data'])
        ? count($res['json']['data'])
        : count($res['json']);
    return [
        'ok' => true,
        'message' => "Terhubung ke PremiumKita! Ditemukan {$count} kategori produk.",
    ];
}

/**
 * Mengirim order pembelian produk ke PremiumKita (koneksi=order) dengan ref_id anti-duplikat.
 */
function pk_create_order(string $idProduk, string $refId, array $extra = []): array
{
    $params = array_filter([
        'id_produk' => $idProduk,
        'ref_id' => $refId,
        'buyer' => (string) ($extra['buyer'] ?? ''),
        'email' => (string) ($extra['email'] ?? ''),
        'durasi' => (string) ($extra['durasi'] ?? ''),
        'catatan' => (string) ($extra['catatan'] ?? ''),
    ], static fn ($v) => $v !== '');

    return pk_call('order', $params);
}

/**
 * Mengambil riwayat transaksi dari PremiumKita (koneksi=get_riwayat).
 */
function pk_get_riwayat(int $limit = 50): array
{
    return pk_call('get_riwayat', ['limit' => max(1, min(100, $limit))]);
}

/**
 * Memetakan nama produk & kategori dari PremiumKita ke tema kartu dan kategori toko lokal.
 *
 * @return array{brand_key:string, brand_label:string, group_slug:string, category:string, variant_label:string}
 */
function pk_infer_product_meta(string $name, string $providerCategory): array
{
    $lower = mb_strtolower($name);
    $brandMap = [
        'netflix' => ['netflix', 'Netflix'],
        'spotify' => ['spotify', 'Spotify'],
        'youtube' => ['youtube', 'YouTube'],
        'canva' => ['canva', 'Canva'],
        'chatgpt' => ['openai', 'ChatGPT'],
        'openai' => ['openai', 'ChatGPT'],
        'capcut' => ['capcut', 'CapCut'],
        'adobe' => ['adobe', 'Adobe'],
        'lightroom' => ['adobe', 'Adobe'],
        'alight' => ['alight', 'Alight Motion'],
        'apple music' => ['apple', 'Apple Music'],
        'bstation' => ['bstation', 'Bstation'],
        'disney' => ['disney', 'Disney+'],
        'viu' => ['viu', 'Viu'],
        'gemini' => ['gemini', 'Gemini'],
        'claude' => ['claude', 'Claude'],
        'microsoft' => ['microsoft', 'Microsoft'],
        'zoom' => ['zoom', 'Zoom'],
        'camscanner' => ['camscanner', 'CamScanner'],
        'vpn' => ['nordvpn', 'VPN'],
        'beauty' => ['beauty', 'BeautyPlus'],
        'ebook' => ['ebook', 'E-Book'],
        'e-book' => ['ebook', 'E-Book'],
    ];

    $brandKey = 'nutrya';
    $brandLabel = 'Digital';
    foreach ($brandMap as $needle => [$bKey, $bLabel]) {
        if (str_contains($lower, $needle)) {
            $brandKey = $bKey;
            $brandLabel = $bLabel;
            break;
        }
    }

    $catLower = mb_strtolower($providerCategory);
    if (str_contains($lower, 'spotify') || str_contains($lower, 'apple music') || str_contains($catLower, 'musik')) {
        $category = 'Musik';
    } elseif (str_contains($lower, 'canva') || str_contains($lower, 'capcut') || str_contains($lower, 'adobe') || str_contains($lower, 'alight') || str_contains($catLower, 'edit') || str_contains($catLower, 'desain')) {
        $category = 'Desain & Editing';
    } elseif (str_contains($lower, 'chatgpt') || str_contains($lower, 'gemini') || str_contains($lower, 'claude') || str_contains($lower, 'zoom') || str_contains($lower, 'microsoft') || str_contains($lower, 'vpn') || str_contains($catLower, 'ai') || str_contains($catLower, 'produk')) {
        $category = 'AI & Produktivitas';
    } elseif (str_contains($lower, 'ebook') || str_contains($lower, 'e-book') || str_contains($lower, 'novel')) {
        $category = 'E-Book & Lainnya';
    } else {
        $category = 'Streaming';
    }

    $variant = 'PAKET STANDAR';
    if (preg_match('/(\d+\s*(?:bulan|bln|hari|day|tahun|thn|minggu))/i', $name, $m)) {
        $variant = strtoupper(trim($m[1]));
    }

    $groupSlug = $brandKey !== 'nutrya'
        ? $brandKey
        : (preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(explode(' ', trim($name))[0] ?? 'pk')) ?: 'pk');

    return [
        'brand_key' => $brandKey,
        'brand_label' => $brandLabel,
        'group_slug' => trim($groupSlug, '-'),
        'category' => $category,
        'variant_label' => $variant,
    ];
}

/**
 * Menyinkronkan katalog produk dari PremiumKita ke SQLite lokal.
 *
 * @return array{ok:bool, synced:int, message:string}
 */
function pk_sync_products_to_db(bool $includeOutOfStock = true): array
{
    $creds = pk_get_credentials();
    $koneksi = $includeOutOfStock ? 'get_all_product' : 'get_ready_product';
    $res = pk_call($koneksi, [], $creds);

    if (!$res['ok'] || !is_array($res['json'])) {
        return [
            'ok' => false,
            'synced' => 0,
            'message' => 'Gagal sync PremiumKita: ' . ($res['error'] ?: 'Respon JSON tidak valid'),
        ];
    }

    $groups = $res['json']['data'] ?? $res['json'];
    if (!is_array($groups)) {
        return ['ok' => false, 'synced' => 0, 'message' => 'Struktur data katalog PremiumKita tidak dikenali.'];
    }

    $pdo = get_db();
    $findStmt = $pdo->prepare("SELECT id FROM products WHERE provider_source = 'premiumkita' AND provider_product_id = ?");
    $updStmt = $pdo->prepare(<<<'SQL'
        UPDATE products SET
            group_slug = ?, brand_key = ?, brand_label = ?, title = ?, variant_label = ?,
            category = ?, price = ?, stock = ?, description = ?, icon_url = ?, provider_cost = ?
        WHERE id = ?
    SQL);
    $insStmt = $pdo->prepare(<<<'SQL'
        INSERT INTO products (
            group_slug, brand_key, brand_label, title, variant_label, category,
            price, stock, sold_count, delivery_type, is_featured, description,
            account_template, provider_source, provider_product_id, provider_cost, icon_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'instant', 0, ?, '', 'premiumkita', ?, ?, ?)
    SQL);

    $synced = 0;
    foreach ($groups as $grp) {
        if (!is_array($grp)) {
            continue;
        }
        $catName = (string) ($grp['nama_kategori'] ?? 'Aplikasi Premium');
        $items = $grp['produk'] ?? (isset($grp['id_produk']) ? [$grp] : []);
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id_produk'])) {
                continue;
            }
            $pid = (string) $item['id_produk'];
            $title = strtoupper(trim((string) ($item['nama_produk'] ?? 'PRODUK PREMIUM')));
            $cost = max(0, (int) ($item['harga'] ?? 0));
            $stock = max(0, (int) ($item['stok'] ?? 0));
            $desc = trim((string) ($item['deskripsi'] ?? 'Produk digital resmi via PremiumKita Open API.'));
            $iconUrl = trim((string) ($item['icon'] ?? ''));

            $sellPrice = (int) ceil($cost * (1 + max(0, $creds['markup_percent']) / 100)) + max(0, $creds['markup_fixed']);
            $meta = pk_infer_product_meta($title, $catName);

            $findStmt->execute([$pid]);
            $existingId = $findStmt->fetchColumn();
            if ($existingId) {
                $updStmt->execute([
                    $meta['group_slug'],
                    $meta['brand_key'],
                    $meta['brand_label'],
                    $title,
                    $meta['variant_label'],
                    $meta['category'],
                    $sellPrice,
                    $stock,
                    $desc,
                    $iconUrl,
                    $cost,
                    (int) $existingId,
                ]);
            } else {
                $insStmt->execute([
                    $meta['group_slug'],
                    $meta['brand_key'],
                    $meta['brand_label'],
                    $title,
                    $meta['variant_label'],
                    $meta['category'],
                    $sellPrice,
                    $stock,
                    $desc,
                    $pid,
                    $cost,
                    $iconUrl,
                ]);
            }
            $synced++;
        }
    }

    return [
        'ok' => true,
        'synced' => $synced,
        'message' => "Berhasil menyinkronkan {$synced} produk dari PremiumKita.",
    ];
}

/* ==========================================================================
   2. NANDARPAY PAYMENT GATEWAY v2 (https://pay.nandarstore.my.id)
   ========================================================================== */

/**
 * Mengambil konfigurasi NandarPay aktif dari database.
 */
function pg_get_credentials(?array $settings = null): array
{
    $s = $settings ?? get_settings();
    return [
        'enabled' => ($s['pg_enabled'] ?? '0') === '1',
        'base_url' => rtrim(trim((string) ($s['pg_base_url'] ?? 'https://pay.nandarstore.my.id')), '/') ?: 'https://pay.nandarstore.my.id',
        'slug' => trim((string) ($s['pg_slug'] ?? '')),
        'api_key' => trim((string) ($s['pg_api_key'] ?? '')),
        'webhook_secret' => trim((string) ($s['pg_webhook_secret'] ?? '')),
        'is_sandbox' => ($s['pg_is_sandbox'] ?? '1') === '1',
    ];
}

/**
 * Memanggil endpoint NandarPay Payment Fee (GET /api/v2/payment-fee/{amount}) tanpa API key.
 */
function pg_get_payment_fee(int $amount, ?array $overrideCreds = null): array
{
    $creds = $overrideCreds ? array_merge(pg_get_credentials(), $overrideCreds) : pg_get_credentials();
    $url = $creds['base_url'] . '/api/v2/payment-fee/' . max(100, $amount);
    return api_http_request('GET', $url, ['Accept: application/json'], null, 10);
}

/**
 * Membuat tagihan QRIS baru (POST /api/v2/create-transaction/{slug}/{order_id}).
 */
function pg_create_transaction(string $orderId, int $amount, ?bool $isSandbox = null): array
{
    $creds = pg_get_credentials();
    if ($creds['slug'] === '' || $creds['api_key'] === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'raw' => '',
            'json' => null,
            'error' => 'Project Slug dan API Key NandarPay belum diisi di Admin.',
        ];
    }

    $sandboxFlag = $isSandbox ?? $creds['is_sandbox'];
    $url = $creds['base_url'] . '/api/v2/create-transaction/' . rawurlencode($creds['slug']) . '/' . rawurlencode($orderId);

    return api_http_request(
        'POST',
        $url,
        [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Api-Key: ' . $creds['api_key'],
        ],
        json_encode([
            'amount' => max(100, $amount),
            'is_sandbox' => (bool) $sandboxFlag,
        ], JSON_UNESCAPED_SLASHES)
    );
}

/**
 * Mengecek status transaksi (GET /api/v2/transaction-status/{slug}/{txn_id}).
 */
function pg_check_transaction_status(string $txnId): array
{
    $creds = pg_get_credentials();
    if ($creds['slug'] === '' || $creds['api_key'] === '') {
        return ['ok' => false, 'http_code' => 0, 'raw' => '', 'json' => null, 'error' => 'Kredensial NandarPay belum lengkap.'];
    }
    $url = $creds['base_url'] . '/api/v2/transaction-status/' . rawurlencode($creds['slug']) . '/' . rawurlencode($txnId);
    return api_http_request('GET', $url, ['Accept: application/json', 'X-Api-Key: ' . $creds['api_key']]);
}

/**
 * Membatalkan transaksi pending (POST /api/v2/cancel-transaction/{slug}/{txn_id}).
 */
function pg_cancel_transaction(string $txnId): array
{
    $creds = pg_get_credentials();
    if ($creds['slug'] === '' || $creds['api_key'] === '') {
        return ['ok' => false, 'http_code' => 0, 'raw' => '', 'json' => null, 'error' => 'Kredensial NandarPay belum lengkap.'];
    }
    $url = $creds['base_url'] . '/api/v2/cancel-transaction/' . rawurlencode($creds['slug']) . '/' . rawurlencode($txnId);
    return api_http_request('POST', $url, ['Accept: application/json', 'X-Api-Key: ' . $creds['api_key']]);
}

/**
 * Simulasi pembayaran Sandbox (POST /api/v2/payment-simulation/{slug}/{txn_id}).
 */
function pg_simulate_sandbox_payment(string $txnId): array
{
    $creds = pg_get_credentials();
    if ($creds['slug'] === '' || $creds['api_key'] === '') {
        return ['ok' => false, 'http_code' => 0, 'raw' => '', 'json' => null, 'error' => 'Kredensial NandarPay belum lengkap.'];
    }
    $url = $creds['base_url'] . '/api/v2/payment-simulation/' . rawurlencode($creds['slug']) . '/' . rawurlencode($txnId);
    return api_http_request('POST', $url, ['Accept: application/json', 'X-Api-Key: ' . $creds['api_key']]);
}

/**
 * Menguji koneksi endpoint publik & kelengkapan kredensial NandarPay.
 */
function pg_test_connection(?array $overrideCreds = null): array
{
    $creds = $overrideCreds ? array_merge(pg_get_credentials(), $overrideCreds) : pg_get_credentials();
    $feeRes = pg_get_payment_fee(12000, $creds);
    if (!$feeRes['ok'] || !is_array($feeRes['json'])) {
        return [
            'ok' => false,
            'message' => 'Gagal menghubungi gateway NandarPay: ' . ($feeRes['error'] ?: 'HTTP ' . $feeRes['http_code']),
        ];
    }
    $qrisFee = $feeRes['json']['qris'] ?? '-';
    $missing = [];
    if ($creds['slug'] === '') {
        $missing[] = 'Project Slug';
    }
    if ($creds['api_key'] === '') {
        $missing[] = 'X-Api-Key';
    }
    if (!empty($missing)) {
        return [
            'ok' => false,
            'message' => 'Gateway dapat dijangkau (fee Rp 12.000 = Rp ' . $qrisFee . '), tetapi ' . implode(' & ', $missing) . ' masih kosong.',
        ];
    }
    return [
        'ok' => true,
        'message' => "Gateway NandarPay merespons normal (uji fee Rp 12.000 = Rp {$qrisFee}). Kredensial project '{$creds['slug']}' siap digunakan.",
    ];
}

/**
 * Memverifikasi header X-PG-Signature HMAC-SHA256 dari webhook NandarPay.
 */
function pg_verify_webhook_signature(string $rawBody, string $signatureHeader, string $secret): bool
{
    if ($secret === '' || $signatureHeader === '') {
        return false;
    }
    $provided = strtolower(trim($signatureHeader));
    if (str_starts_with($provided, 'sha256=')) {
        $provided = substr($provided, 7);
    }
    $expected = strtolower(hash_hmac('sha256', $rawBody, $secret));
    return hash_equals($expected, $provided);
}
