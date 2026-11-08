<?php
require_once __DIR__ . '/bootstrap.php';

const MAX_CART_ITEMS = 50;
const MAX_QUANTITY = 50;

// Keep only the fields the pages use. Prices here are for display only;
// orders must take prices from menu_items.
function sanitizeCart($cart) {
    if (!is_array($cart) || count($cart) > MAX_CART_ITEMS) {
        fail('Invalid cart', 400);
    }
    $clean = [];
    foreach ($cart as $item) {
        $id = is_array($item) ? (string) ($item['id'] ?? '') : '';
        $quantity = is_array($item)
            ? filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_QUANTITY]])
            : false;
        if (!ctype_digit($id) || $quantity === false) {
            fail('Invalid cart item', 400);
        }
        $clean[] = [
            'id' => $id,
            'name' => (string) ($item['name'] ?? ''),
            'price' => (float) ($item['price'] ?? 0),
            'image' => (string) ($item['image'] ?? ''),
            'quantity' => $quantity,
        ];
    }
    return $clean;
}

$input = readJsonBody();
$action = $input['action'] ?? '';
$userId = $_SESSION['user']['id'] ?? null;

if ($action === 'get') {
    respond(['success' => true, 'cart' => $_SESSION['cart'] ?? []]);
}

if ($action === 'save') {
    $cart = sanitizeCart($input['cart'] ?? null);
    $_SESSION['cart'] = $cart;

    if ($userId) {
        $cartJson = json_encode($cart);
        $stmt = $conn->prepare('INSERT INTO user_carts (user_id, cart_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE cart_data = VALUES(cart_data)');
        $stmt->bind_param('is', $userId, $cartJson);
        $stmt->execute();
        $stmt->close();
    }
    respond(['success' => true, 'cart' => $cart]);
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];

    if ($userId) {
        $stmt = $conn->prepare('DELETE FROM user_carts WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    respond(['success' => true]);
}

fail('Unknown cart action', 400);
