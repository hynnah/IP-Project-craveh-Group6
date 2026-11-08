<?php
require_once __DIR__ . '/bootstrap.php';

// Keep the cart so it comes back on next login.
if (isset($_SESSION['user']['id'], $_SESSION['cart'])) {
    $userId = $_SESSION['user']['id'];
    $cartJson = json_encode($_SESSION['cart']);
    $stmt = $conn->prepare('INSERT INTO user_carts (user_id, cart_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE cart_data = VALUES(cart_data)');
    $stmt->bind_param('is', $userId, $cartJson);
    $stmt->execute();
    $stmt->close();
}

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
session_destroy();

respond(['success' => true]);
