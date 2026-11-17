<?php
require_once __DIR__ . '/bootstrap.php';

$input = readJsonBody();
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    fail('Email and password are required', 400);
}

$stmt = $conn->prepare('SELECT id, email, name, phone, delivery_address, password_hash, created_at FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// An account created through Google has no local password. Say so plainly
// instead of "invalid email or password", which would send the customer off
// trying to remember a password that never existed.
if ($user && $user['password_hash'] === null) {
    fail('This account signs in with Google. Use the Sign in with Google button.', 409);
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    fail('Invalid email or password', 401);
}
unset($user['password_hash']);
$user = decodeUserAddress($user);

// Restore the cart saved at last logout, if any.
$stmt = $conn->prepare('SELECT cart_data FROM user_carts WHERE user_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

session_regenerate_id(true);
$_SESSION['user'] = $user;
// A saved cart wins; otherwise keep what the guest added before logging in.
$savedCart = $row ? (json_decode($row['cart_data'], true) ?? []) : [];
$_SESSION['cart'] = $savedCart ?: ($_SESSION['cart'] ?? []);

respond(['success' => true, 'user' => $user]);
