<?php
// The simulator's "bank side" — stands in for GCash's own servers.
//
// Conceptually this file is NOT part of Craveh. It is the external system,
// kept inside the project only so the demo runs without a merchant account.
// It talks back to Craveh the same way a real provider would: a signed
// server-to-server POST to the webhook. Nothing here writes to orders.
//
// Authorisation is the reference itself: it is 24 random hex characters that
// only the customer who started this payment was given. That is how hosted
// checkout pages work — the URL is the capability.
define('CRAVEH_NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/payments/gateway.php';

$config = payment_config();
if ($config['provider'] !== 'mock') {
    fail('The payment simulator is disabled while a real gateway is configured', 403);
}

require_once __DIR__ . '/payments/mock_gateway.php';
$gateway = new MockGateway($config);

function loadPendingPayment(mysqli $conn, string $reference): array
{
    $stmt = $conn->prepare(
        "SELECT p.id, p.order_id, p.amount, p.status, u.name AS customer_name
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         JOIN users u ON u.id = o.user_id
         WHERE p.provider = 'mock' AND p.provider_reference = ?"
    );
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        fail('Unknown payment reference', 404);
    }
    return $payment;
}

// ── GET: what the simulated checkout page should display ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $reference = (string) ($_GET['ref'] ?? '');
    if ($reference === '') {
        fail('A payment reference is required', 400);
    }
    $payment = loadPendingPayment($conn, $reference);

    respond([
        'success' => true,
        'reference' => $reference,
        'orderId' => (int) $payment['order_id'],
        'amount' => (float) $payment['amount'],
        'customerName' => $payment['customer_name'],
        'status' => $payment['status'],
    ]);
}

// ── POST: the customer pressed Authorise or Cancel ──
$input = readJsonBody();
$reference = (string) ($input['ref'] ?? '');
$outcome = (string) ($input['outcome'] ?? '');

if ($reference === '') {
    fail('A payment reference is required', 400);
}
if (!in_array($outcome, ['paid', 'failed'], true)) {
    fail('Outcome must be paid or failed', 400);
}

$payment = loadPendingPayment($conn, $reference);
if ($payment['status'] !== 'pending') {
    respond(['success' => true, 'alreadyProcessed' => true, 'status' => $payment['status']]);
}

// Build the event exactly as a provider would, then sign it.
$event = [
    'id' => 'evt_' . bin2hex(random_bytes(10)),
    'type' => $outcome === 'paid' ? 'payment.paid' : 'payment.failed',
    'created_at' => gmdate('c'),
    'data' => [
        'reference' => $reference,
        'amount' => (float) $payment['amount'],
        'currency' => 'PHP',
    ],
];
$body = json_encode($event);
$signature = $gateway->sign($body);

$webhookUrl = rtrim((string) $config['base_url'], '/') . '/api/payment_webhook.php';

$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Craveh-Signature: ' . $signature,
    ],
]);
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    error_log('mock_pay: could not deliver webhook: ' . $error);
    fail('Could not deliver the payment notification to the merchant', 502);
}

respond([
    'success' => $httpCode >= 200 && $httpCode < 300,
    'orderId' => (int) $payment['order_id'],
    'outcome' => $outcome,
    'webhookStatus' => $httpCode,
    'webhookResponse' => json_decode($response, true),
]);
