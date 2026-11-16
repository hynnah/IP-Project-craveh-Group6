<?php
// Starts (or resumes) a GCash payment for an order the caller owns.
// Returns the gateway's checkout URL for the browser to redirect to.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/payments/gateway.php';

if (empty($_SESSION['user']['id'])) {
    fail('Please log in to pay for this order', 401);
}
$userId = (int) $_SESSION['user']['id'];

$input = readJsonBody();
$orderId = filter_var($input['orderId'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId) {
    fail('A valid orderId is required', 400);
}

// AND user_id = ? is the authorisation check. Without it, changing the
// orderId in the request would start a payment against someone else's order.
$stmt = $conn->prepare(
    'SELECT o.id, o.total_amount, o.payment_method, o.payment_status, o.phone,
            u.name AS customer_name, u.email AS customer_email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.id = ? AND o.user_id = ?'
);
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    fail('Order not found', 404);
}
if ($order['payment_method'] !== 'gcash') {
    fail('This order is not set to be paid online', 400);
}
if ($order['payment_status'] === 'paid') {
    fail('This order has already been paid', 409);
}

$gateway = payment_gateway();
$provider = $gateway->name();

// Idempotency on our side of the conversation. Double-clicking "Pay", or
// reloading the checkout page, must not open a second invoice for the same
// order — the customer would be looking at two live payment pages and could
// pay twice. An attempt that is still pending is reused.
$stmt = $conn->prepare(
    "SELECT provider_reference, checkout_url
     FROM payments
     WHERE order_id = ? AND provider = ? AND status = 'pending'
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param('is', $orderId, $provider);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing && !empty($existing['checkout_url'])) {
    respond([
        'success' => true,
        'checkoutUrl' => $existing['checkout_url'],
        'reference' => $existing['provider_reference'],
        'resumed' => true,
    ]);
}

$amount = (float) $order['total_amount'];

try {
    $checkout = $gateway->createCheckout(
        $orderId,
        $amount,
        'Craveh order #' . $orderId,
        [
            'name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['phone'],
        ]
    );
} catch (Throwable $e) {
    // The gateway being down is a normal failure mode, not a crash. The order
    // survives; the customer can retry or switch to cash on delivery.
    error_log('create_payment: gateway failed: ' . $e);
    fail('Could not reach the payment provider. Please try again in a moment.', 502);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO payments (order_id, provider, provider_reference, amount, currency, status, checkout_url)
         VALUES (?, ?, ?, ?, 'PHP', 'pending', ?)"
    );
    $stmt->bind_param('issds', $orderId, $provider, $checkout['reference'], $amount, $checkout['checkoutUrl']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE orders SET payment_reference = ?, payment_status = 'pending' WHERE id = ?"
    );
    $stmt->bind_param('si', $checkout['reference'], $orderId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('create_payment: could not record payment: ' . $e);
    fail('Could not start the payment', 500);
}

respond([
    'success' => true,
    'checkoutUrl' => $checkout['checkoutUrl'],
    'reference' => $checkout['reference'],
    'resumed' => false,
]);
