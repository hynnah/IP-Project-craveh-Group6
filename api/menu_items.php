<?php
require_once __DIR__ . '/bootstrap.php';

$result = $conn->query(
    'SELECT id, name, description, price, image_url, category
     FROM menu_items
     WHERE is_available = 1
     ORDER BY id'
);
if (!$result) {
    fail('Failed to load menu items', 500);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (string) $row['id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? '',
        'price' => (float) $row['price'],
        'image' => $row['image_url'] ?? '',
        'category' => $row['category'] ?? 'Other',
    ];
}

respond(['success' => true, 'items' => $items]);
