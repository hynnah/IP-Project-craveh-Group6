<?php
// Shared setup for every API endpoint: JSON header, safe session cookie, DB connection.
header('Content-Type: application/json');
ini_set('display_errors', '0');

// Any uncaught error (e.g. a failed query) becomes a JSON 500 instead of an HTML error page.
set_exception_handler(function ($e) {
    error_log($e);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
});

// Server-to-server endpoints (the payment webhook and the simulator) define
// CRAVEH_NO_SESSION before including this file. They have no logged-in user,
// and PHP locks the session file for the whole request — so a request that
// holds the lock while calling another endpoint that also wants it would
// deadlock until one of them times out.
if (!defined('CRAVEH_NO_SESSION')) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/config.php';

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function fail($error, $status) {
    respond(['success' => false, 'error' => $error], $status);
}

function readJsonBody() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        fail('Invalid JSON payload', 400);
    }
    return $input;
}

// delivery_address is stored as JSON text; return it decoded (or null).
function decodeUserAddress($user) {
    $user['delivery_address'] = !empty($user['delivery_address'])
        ? json_decode($user['delivery_address'], true)
        : null;
    return $user;
}
