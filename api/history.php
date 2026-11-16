<?php
require_once __DIR__ . '/bootstrap.php';

const HISTORY_LIMIT = 50;

if (empty($_SESSION['user']['id'])) {
    fail('Please log in to view your orders', 401);
}
$userId = (int) $_SESSION['user']['id'];

// The WHERE clause is the whole access-control story: a user can only ever
// read rows whose user_id is their own session's. No order id is accepted
// from the browser, so there is nothing to tamper with.
$stmt = $conn->prepare(
    'SELECT id, total_amount, delivery_address, delivery_lat, delivery_lng, phone,
            payment_method, payment_status, paid_at, status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ' . HISTORY_LIMIT
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$orders) {
    respond(['success' => true, 'orders' => []]);
}

// Two queries, not one per order. Fetching the line items inside a loop would
// be N+1: 50 orders would mean 51 round trips to MySQL.
$orderIds = array_map('intval', array_column($orders, 'id'));
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$stmt = $conn->prepare(
    "SELECT order_id, menu_item_id, menu_item_name, quantity, price_at_purchase
     FROM order_items
     WHERE order_id IN ($placeholders)
     ORDER BY id"
);
$stmt->bind_param(str_repeat('i', count($orderIds)), ...$orderIds);
$stmt->execute();
$itemsByOrder = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $itemsByOrder[(int) $row['order_id']][] = [
        'menuItemId' => (string) $row['menu_item_id'],
        'name' => $row['menu_item_name'],
        'quantity' => (int) $row['quantity'],
        'price' => (float) $row['price_at_purchase'],
    ];
}
$stmt->close();

// Column names are the database's business. The JSON contract is camelCase
// and stays stable even if a column is renamed underneath it.
$payload = array_map(static function (array $order) use ($itemsByOrder): array {
    $id = (int) $order['id'];
    return [
        'id' => $id,
        'status' => $order['status'],
        'total' => (float) $order['total_amount'],
        'address' => $order['delivery_address'],
        'lat' => $order['delivery_lat'] === null ? null : (float) $order['delivery_lat'],
        'lng' => $order['delivery_lng'] === null ? null : (float) $order['delivery_lng'],
        'phone' => $order['phone'],
        'paymentMethod' => $order['payment_method'],
        'paymentStatus' => $order['payment_status'],
        'paidAt' => $order['paid_at'],
        'createdAt' => $order['created_at'],
        'items' => $itemsByOrder[$id] ?? [],
    ];
}, $orders);

respond(['success' => true, 'orders' => $payload]);
