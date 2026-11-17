<?php
require_once __DIR__ . '/bootstrap.php';

const MAX_ORDER_ITEMS = 50;
const MAX_QUANTITY = 50;
const PAYMENT_METHODS = ['cod', 'gcash'];

if (empty($_SESSION['user']['id'])) {
    fail('Please log in to place an order', 401);
}
$userId = (int) $_SESSION['user']['id'];

$input = readJsonBody();
$address = trim((string) ($input['address'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$items = $input['items'] ?? null;
$paymentMethod = strtolower(trim((string) ($input['paymentMethod'] ?? 'cod')));

// userId is optional; if sent it must be the logged-in user.
if (isset($input['userId']) && (int) $input['userId'] !== $userId) {
    fail('User ID mismatch', 403);
}
if (strlen($address) < 10 || strlen($address) > 1000) {
    fail('Address must be 10 to 1000 characters', 400);
}
if (!preg_match('/^\d{7,15}$/', $phone)) {
    fail('Phone must be 7-15 digits', 400);
}
if (!in_array($paymentMethod, PAYMENT_METHODS, true)) {
    fail('Unsupported payment method', 400);
}
if (!is_array($items) || count($items) === 0 || count($items) > MAX_ORDER_ITEMS) {
    fail('Order must contain 1 to ' . MAX_ORDER_ITEMS . ' items', 400);
}

// Optional delivery pin dropped on the map. Absent is fine; nonsense is not.
$lat = ($input['lat'] ?? '') === '' ? null : filter_var($input['lat'], FILTER_VALIDATE_FLOAT);
$lng = ($input['lng'] ?? '') === '' ? null : filter_var($input['lng'], FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false
    || ($lat !== null && ($lat < -90 || $lat > 90))
    || ($lng !== null && ($lng < -180 || $lng > 180))) {
    fail('Invalid delivery coordinates', 400);
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

// Price snapshot. Money is summed in centavos so repeated float addition
// cannot drift; the order stores what the item cost on the day it was bought,
// not what it costs today.
$lines = [];
$totalCents = 0;
foreach ($quantities as $id => $quantity) {
    $priceCents = (int) round($menu[$id]['price'] * 100);
    $totalCents += $priceCents * $quantity;
    $lines[] = [
        'menuItemId' => (int) $id,
        'name' => $menu[$id]['name'],
        'price' => $priceCents / 100,
        'quantity' => $quantity,
    ];
}
$total = $totalCents / 100;

// COD is owed on arrival, so it starts 'unpaid'. GCash starts 'pending':
// the customer is about to be sent to the gateway, and only the webhook —
// never the browser — is allowed to turn that into 'paid'.
$status = 'pending';
$paymentStatus = $paymentMethod === 'gcash' ? 'pending' : 'unpaid';

// The order header and its line items are one business fact. Writing the
// header and then failing on the items would leave a ₱0-looking order in the
// database, so both go in a single transaction: all of it, or none of it.
$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        'INSERT INTO orders
            (user_id, total_amount, delivery_address, delivery_lat, delivery_lng, phone, payment_method, payment_status, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('idsddssss', $userId, $total, $address, $lat, $lng, $phone, $paymentMethod, $paymentStatus, $status);
    $stmt->execute();
    $orderId = (int) $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        'INSERT INTO order_items
            (order_id, menu_item_id, menu_item_name, quantity, price_at_purchase)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($lines as $line) {
        // bind_param takes its arguments by reference, so each value needs
        // to live in a variable of its own.
        $menuItemId = $line['menuItemId'];
        $itemName = $line['name'];
        $itemQuantity = $line['quantity'];
        $itemPrice = $line['price'];
        $stmt->bind_param('iisid', $orderId, $menuItemId, $itemName, $itemQuantity, $itemPrice);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('save_order failed: ' . $e);
    fail('Could not save the order', 500);
}

// The order holds the whole cart, so empty it.
$_SESSION['cart'] = [];
$stmt = $conn->prepare('DELETE FROM user_carts WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

respond([
    'success' => true,
    'orderId' => $orderId,
    'paymentMethod' => $paymentMethod,
    'paymentStatus' => $paymentStatus,
    'total' => $total,
]);
