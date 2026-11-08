<?php
require_once __DIR__ . '/bootstrap.php';

const MAX_ORDER_ITEMS = 50;
const MAX_QUANTITY = 50;

if (empty($_SESSION['user']['id'])) {
    fail('Please log in to place an order', 401);
}
$userId = (string) $_SESSION['user']['id'];

$input = readJsonBody();
$address = trim((string) ($input['address'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$items = $input['items'] ?? null;

// userId is optional; if sent it must be the logged-in user.
if (isset($input['userId']) && (string) $input['userId'] !== $userId) {
    fail('User ID mismatch', 403);
}
if (strlen($address) < 10 || strlen($address) > 1000) {
    fail('Address must be 10 to 1000 characters', 400);
}
if (!preg_match('/^\d{7,15}$/', $phone)) {
    fail('Phone must be 7-15 digits', 400);
}
if (!is_array($items) || count($items) === 0 || count($items) > MAX_ORDER_ITEMS) {
    fail('Order must contain 1 to ' . MAX_ORDER_ITEMS . ' items', 400);
}

// Merge quantities by menu item; the client's name and price are ignored.
$quantities = [];
foreach ($items as $item) {
    $id = is_array($item) ? (string) ($item['menuItemId'] ?? '') : '';
    $quantity = is_array($item)
        ? filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_QUANTITY]])
        : false;
    if (!ctype_digit($id) || $quantity === false) {
        fail('Each item needs a valid menuItemId and a quantity of 1-' . MAX_QUANTITY, 400);
    }
    $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
    if ($quantities[$id] > MAX_QUANTITY) {
        fail('Quantity per item cannot exceed ' . MAX_QUANTITY, 400);
    }
}

// Look up current name, price and availability from the database.
$ids = array_map('intval', array_keys($quantities));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id, name, price, is_available FROM menu_items WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$menu = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $menu[(string) $row['id']] = $row;
}
$stmt->close();

$unavailable = [];
foreach (array_keys($quantities) as $id) {
    if (!isset($menu[$id]) || !$menu[$id]['is_available']) {
        $unavailable[] = $menu[$id]['name'] ?? "item #$id";
    }
}
if ($unavailable) {
    fail('No longer available: ' . implode(', ', $unavailable), 409);
}

// Snapshot in the shape admin code expects: [{menuItemId, name, price, quantity}].
$orderItems = [];
$totalCents = 0;
foreach ($quantities as $id => $quantity) {
    $priceCents = (int) round($menu[$id]['price'] * 100);
    $totalCents += $priceCents * $quantity;
    $orderItems[] = [
        'menuItemId' => (string) $id,
        'name' => $menu[$id]['name'],
        'price' => $priceCents / 100,
        'quantity' => $quantity,
    ];
}
$total = $totalCents / 100;
$itemsJson = json_encode($orderItems);

$status = 'pending';
$stmt = $conn->prepare('INSERT INTO client_orders (client_user_id, total_amount, delivery_address, phone, items, status) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sdssss', $userId, $total, $address, $phone, $itemsJson, $status);
if (!$stmt->execute()) {
    fail('Could not save the order', 500);
}
$orderId = $stmt->insert_id;
$stmt->close();

// The order holds the whole cart, so empty it.
$_SESSION['cart'] = [];
$stmt = $conn->prepare('DELETE FROM user_carts WHERE user_id = ?');
$stmt->bind_param('i', $_SESSION['user']['id']);
$stmt->execute();
$stmt->close();

respond(['success' => true, 'orderId' => $orderId]);
