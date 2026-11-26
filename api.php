<?php
/**
 * Endpoint JSON ringan (/api.php) untuk:
 * - check_promo: validasi kode promo real-time
 * - check_payment: polling status pembayaran NandarPay (rate-limit 4 detik) + auto-fulfill
 * - payment_fee: cek estimasi fee QRIS dinamis
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'check_promo') {
    $code = (string) ($_GET['code'] ?? $_POST['code'] ?? '');
    $subtotal = max(0, (int) ($_GET['subtotal'] ?? $_POST['subtotal'] ?? 0));
    $result = evaluate_promo($code, $subtotal);
    $after = max(100, $subtotal - $result['discount']);

    $pgCreds = pg_get_credentials();
    $fee = calculate_qris_fee($after);
    if ($pgCreds['enabled']) {
        $feeRes = pg_get_payment_fee($after, $pgCreds);
        if ($feeRes['ok'] && isset($feeRes['json']['qris'])) {
            $fee = (int) $feeRes['json']['qris'];
        }
    }

    echo json_encode([
        'valid' => $result['valid'],
        'code' => $result['code'],
        'discount' => $result['discount'],
        'fee' => $fee,
        'total' => $after + $fee,
        'message' => $result['message'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'payment_fee') {
    $amount = max(100, (int) ($_GET['amount'] ?? 0));
    $pgCreds = pg_get_credentials();
    $fee = calculate_qris_fee($amount);
    if ($pgCreds['enabled']) {
        $feeRes = pg_get_payment_fee($amount, $pgCreds);
        if ($feeRes['ok'] && isset($feeRes['json']['qris'])) {
            $fee = (int) $feeRes['json']['qris'];
        }
    }
    echo json_encode(['qris' => $fee]);
    exit;
}

if ($action === 'check_payment') {
    $inv = trim((string) ($_GET['inv'] ?? ''));
    if ($inv === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Nomor invoice wajib diisi.']);
        exit;
    }
    $order = sync_order_payment_status($inv);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice tidak ditemukan.']);
        exit;
    }
    echo json_encode([
        'invoice' => $order['invoice'],
        'status' => $order['status'],
        'paid' => $order['status'] === 'paid',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Aksi tidak dikenal.']);
