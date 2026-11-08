<?php
require_once __DIR__ . '/bootstrap.php';

respond([
    'success' => true,
    'user' => $_SESSION['user'] ?? null,
    'cart' => $_SESSION['cart'] ?? [],
]);
