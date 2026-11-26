<?php
/**
 * Endpoint Webhook NandarPay (/webhook.php):
 * Menerima notifikasi pembayaran masuk (POST JSON), memverifikasi header
 * HMAC-SHA256 X-PG-Signature menggunakan Webhook Secret dari Admin,
 * lalu menandai pesanan lunas & memicu pengiriman otomatis ke PremiumKita.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Gunakan POST.']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON tidak valid.']);
    exit;
}

$creds = pg_get_credentials();
$sigHeader = (string) ($_SERVER['HTTP_X_PG_SIGNATURE'] ?? '');
$sigValid = $creds['webhook_secret'] !== ''
    ? pg_verify_webhook_signature($rawBody, $sigHeader, $creds['webhook_secret'])
    : false;

$orderId = trim((string) ($payload['order_id'] ?? ''));
$txnId = trim((string) ($payload['txn_id'] ?? ''));
$pgStatus = strtolower(trim((string) ($payload['status'] ?? '')));

$pdo = get_db();

if (!$sigValid) {
    $pdo->prepare('INSERT INTO webhook_logs (order_id, txn_id, status, http_code, signature_valid, payload) VALUES (?, ?, ?, 401, 0, ?)')
        ->execute([$orderId, $txnId, $pgStatus, $rawBody]);
    http_response_code(401);
    echo json_encode(['error' => 'Signature X-PG-Signature tidak valid atau Webhook Secret belum diatur.']);
    exit;
}

$httpCode = 200;
if ($orderId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE invoice = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $httpCode = 404;
    } elseif ($pgStatus === 'completed') {
        fulfill_order($orderId);
    } elseif ($pgStatus === 'canceled' && $order['status'] === 'pending') {
        $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE invoice = ?")->execute([$orderId]);
    }
}

$pdo->prepare('INSERT INTO webhook_logs (order_id, txn_id, status, http_code, signature_valid, payload) VALUES (?, ?, ?, ?, 1, ?)')
    ->execute([$orderId, $txnId, $pgStatus, $httpCode, $rawBody]);

http_response_code($httpCode);
echo json_encode([
    'ok' => $httpCode === 200,
    'order_id' => $orderId,
    'status' => $pgStatus,
]);
