<?php
// Copy this file to config.php and fill in your credentials.
// config.php is gitignored; never commit real passwords.
$db_config = [
    'local' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'craveh_group6'
    ],
    'online' => [
        'host' => 'localhost',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASSWORD',
        'name' => 'YOUR_DB_NAME'
    ]
];

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$environment = in_array($serverName, ['localhost', '127.0.0.1'], true) ? 'local' : 'online';
$config = $db_config[$environment];

// PHP 8.1+ throws on connection errors; catch it so the client still gets JSON.
try {
    $conn = mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name']);
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');

function getDbConnection() {
    global $conn;
    return $conn;
}
