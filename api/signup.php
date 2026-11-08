<?php
require_once __DIR__ . '/bootstrap.php';

$input = readJsonBody();
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

foreach (['name' => $name, 'email' => $email, 'password' => $password] as $field => $value) {
    if ($value === '') {
        fail("Missing or empty field: $field", 400);
    }
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Invalid email address', 400);
}
if (strlen($password) < 6) {
    fail('Password must be at least 6 characters', 400);
}

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    fail('Email already exists', 409);
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $email, $hash, $name);
if (!$stmt->execute()) {
    fail('Could not create account', 500);
}
$userId = $stmt->insert_id;
$stmt->close();

$stmt = $conn->prepare('SELECT id, email, name, phone, delivery_address, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = decodeUserAddress($stmt->get_result()->fetch_assoc());
$stmt->close();

session_regenerate_id(true);
$_SESSION['user'] = $user;
// Keep anything the guest added before signing up.
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

respond(['success' => true, 'user' => $user]);
