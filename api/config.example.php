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

// ── Payment gateway ──────────────────────────────────────────────────────
// provider:
//   'mock'   — the built-in GCash simulator. Needs no account and works
//              offline; the whole flow (redirect, signed webhook, idempotent
//              handler) is real, only the bank is not.
//   'xendit' — Xendit Test Mode. Fill in the two values below, set the
//              webhook URL in the Xendit dashboard, and nothing else in the
//              application changes.
//
// base_url is only needed if auto-detection guesses wrong (for example when
// running behind ngrok). Leave it null otherwise.
$payment_config = [
    'provider' => 'mock',
    'base_url' => null,

    'mock' => [
        // Signs the simulator's webhooks. Any non-empty string; change it
        // and the handler will reject webhooks signed with the old one.
        'webhook_secret' => 'craveh-mock-dev-secret',
    ],

    'xendit' => [
        'secret_key' => 'xnd_development_YOUR_TEST_SECRET_KEY',
        'webhook_token' => 'YOUR_WEBHOOK_VERIFICATION_TOKEN',
    ],
];

// ── Google OAuth ─────────────────────────────────────────────────────────
// Client ID from Google Cloud Console > APIs & Services > Credentials.
// The client ID is public (it ships in the page); the secret is not needed
// for the browser sign-in flow used here.
$google_config = [
    'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
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
