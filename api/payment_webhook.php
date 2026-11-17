<?php
// The gateway calling US.
//
// This endpoint is the only thing in the system allowed to mark an order
// paid. The browser is never trusted with that: a redirect back from the
// gateway only means the customer's browser came back, which anyone can
// fake by typing the return URL.
//
// No session: this is a server-to-server call with no logged-in user, and
// holding the session lock here would deadlock against the request that
// triggered it.
define('CRAVEH_NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/payments/gateway.php';

const DUPLICATE_KEY = 1062;

$rawBody = file_get_contents('php://input');
$headers = payment_request_headers();
$gateway = payment_gateway();
$provider = $gateway->name();

// Verify the signature on the RAW bytes, before parsing. Anything that fails
// here never touches the database.
if (!$gateway->verifyWebhook($rawBody, $headers)) {
    error_log('payment_webhook: rejected an unsigned or badly signed request');
    fail('Invalid signature', 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    fail('Invalid JSON payload', 400);
}

$event = $gateway->parseEvent($payload);
if ($event === null) {
    // An event we do not act on. Still a 200: anything else makes the
    // provider retry a message we are deliberately ignoring.
    respond(['success' => true, 'ignored' => true]);
}

$eventId = $event['eventId'] !== '' ? $event['eventId'] : hash('sha256', $rawBody);

// ── The idempotency guard ────────────────────────────────────────────────
// A provider retries until it gets a 2xx, so this exact event may arrive
// several times. Recording the event and acting on it happen in ONE
// transaction:
//
//   - a duplicate fails on the UNIQUE key, so the order is not touched twice
//   - if the update fails, the rollback removes the event row too, so the
//     provider's next retry is processed normally rather than swallowed
//
// Recording the event in a separate transaction would break the second
// property, which is the one people forget.
$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        'INSERT INTO webhook_events (provider, event_id, event_type, payload) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('ssss', $provider, $eventId, $event['type'], $rawBody);
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ($e->getCode() === DUPLICATE_KEY) {
        // Already handled. Same answer as the first time — that is what
        // idempotent means.
        respond(['success' => true, 'duplicate' => true]);
    }
    throw $e;
}

try {
    // Find the attempt this event belongs to, and lock the row so two
    // simultaneous deliveries cannot interleave their updates.
    $stmt = $conn->prepare(
        'SELECT id, order_id, amount, status FROM payments
         WHERE provider = ? AND provider_reference = ? FOR UPDATE'
    );
    $stmt->bind_param('ss', $provider, $event['reference']);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        // Unknown reference. Commit the event row anyway so it is on record,
        // and answer 200 so the provider stops retrying something we can
        // never match.
        $conn->commit();
        error_log('payment_webhook: no payment row for reference ' . $event['reference']);
        respond(['success' => true, 'unmatched' => true]);
    }

    $orderId = (int) $payment['order_id'];
    $status = $event['status'];

    $stmt = $conn->prepare('UPDATE payments SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $payment['id']);
    $stmt->execute();
    $stmt->close();

    if ($status === 'paid') {
        // Payment confirms the order, so the kitchen can start. status is
        // only advanced from 'pending' — a webhook arriving late must not
        // drag an order that is already out for delivery backwards.
        $stmt = $conn->prepare(
            "UPDATE orders
             SET payment_status = 'paid',
                 paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP),
                 status = IF(status = 'pending', 'confirmed', status)
             WHERE id = ?"
        );
    } elseif ($status === 'refunded') {
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
    } else {
        // failed or expired — the order stays, unpaid, so the customer can
        // retry or switch to cash on delivery.
        $stmt = $conn->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('payment_webhook: failed to apply event ' . $eventId . ': ' . $e);
    // A 500 tells the provider to retry. The rollback removed the event row,
    // so that retry will actually be processed.
    fail('Could not apply the payment event', 500);
}

respond(['success' => true, 'orderId' => $orderId, 'status' => $status]);
