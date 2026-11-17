<?php
// Sign in with Google.
//
// The browser gets an ID token (a signed JWT) from Google and posts it here.
// That token is a CLAIM, not a fact, until this server checks it: anyone can
// POST a handcrafted JSON blob to this endpoint. Trusting the email inside an
// unverified token would let anybody log in as anybody.

require_once __DIR__ . '/bootstrap.php';

// Google's own endpoint for validating an ID token. A round trip per login,
// in exchange for not having to implement JWT signature checking and key
// rotation. A production system would instead cache Google's public keys and
// verify the signature locally.
const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo?id_token=';
const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

global $google_config;
$clientId = (string) ($google_config['client_id'] ?? '');
if ($clientId === '' || str_starts_with($clientId, 'YOUR_CLIENT_ID')) {
    fail('Google sign-in is not configured on this server', 503);
}

$input = readJsonBody();
$credential = trim((string) ($input['credential'] ?? ''));
if ($credential === '') {
    fail('Missing Google credential', 400);
}

// ── Ask Google whether this token is real ──
$ch = curl_init(TOKENINFO_URL . urlencode($credential));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    error_log('google_login: could not reach Google: ' . $curlError);
    fail('Could not verify your Google account right now. Please try again.', 502);
}

$token = json_decode($raw, true);
if (!is_array($token) || $httpCode >= 400) {
    fail('That Google sign-in could not be verified', 401);
}

// ── The three checks that matter ──
// aud: was this token issued for THIS application? A valid token minted for
//      some other site is still a valid token — without this check, any
//      developer could log in as any of our users using their own app's token.
if (!isset($token['aud']) || !hash_equals($clientId, (string) $token['aud'])) {
    error_log('google_login: token audience mismatch');
    fail('That Google sign-in was not issued for this application', 401);
}
// iss: did Google issue it?
if (!in_array((string) ($token['iss'] ?? ''), VALID_ISSUERS, true)) {
    fail('That Google sign-in could not be verified', 401);
}
// email_verified: Google lets an account exist with an unconfirmed address.
// Linking on an unverified email would let someone claim another person's
// account by signing up with their address.
if (($token['email_verified'] ?? 'false') !== 'true' && ($token['email_verified'] ?? false) !== true) {
    fail('Your Google email address is not verified', 403);
}

$googleId = (string) ($token['sub'] ?? '');
$email = strtolower(trim((string) ($token['email'] ?? '')));
$name = trim((string) ($token['name'] ?? ''));
$avatar = (string) ($token['picture'] ?? '');

if ($googleId === '' || $email === '') {
    fail('That Google sign-in is missing required details', 401);
}
if ($name === '') {
    $name = ucfirst(explode('@', $email)[0]);
}

// ── Identity resolution ──
// The same human can arrive three ways. This is the "is this the same
// customer?" problem: two systems, two different keys for one person.
//
//   1. google_id already known      → returning Google user
//   2. email matches a local account → same person, link the two identities
//   3. neither                       → genuinely new customer
//
// Matching on google_id FIRST matters: the Google account's email address can
// change, and sub is the only identifier Google promises is stable.
$stmt = $conn->prepare(
    'SELECT id, email, name, phone, delivery_address, google_id, auth_provider, created_at
     FROM users WHERE google_id = ? OR email = ?
     ORDER BY (google_id = ?) DESC LIMIT 1'
);
$stmt->bind_param('sss', $googleId, $email, $googleId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

try {
    if (!$user) {
        // New customer. password_hash stays NULL — there is no local password
        // to store, and inventing one would be worse than leaving it empty.
        $stmt = $conn->prepare(
            "INSERT INTO users (email, name, google_id, auth_provider, avatar_url, role)
             VALUES (?, ?, ?, 'google', ?, 'customer')"
        );
        $stmt->bind_param('ssss', $email, $name, $googleId, $avatar);
        $stmt->execute();
        $userId = (int) $stmt->insert_id;
        $stmt->close();
    } else {
        $userId = (int) $user['id'];

        // Found by email with no google_id yet: this person signed up with a
        // password before and is now using Google. Link, do not duplicate —
        // two rows for one customer is exactly the duplicate-identity failure
        // the integration notes warn about.
        if (empty($user['google_id'])) {
            $stmt = $conn->prepare('UPDATE users SET google_id = ?, avatar_url = COALESCE(NULLIF(?, \'\'), avatar_url) WHERE id = ?');
            $stmt->bind_param('ssi', $googleId, $avatar, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log('google_login: could not create or link account: ' . $e);
    fail('Could not complete Google sign-in', 500);
}

// Re-read the row so the session holds exactly what the database holds.
$stmt = $conn->prepare(
    'SELECT id, email, name, phone, delivery_address, created_at FROM users WHERE id = ?'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$user = decodeUserAddress($user);

// Restore the cart saved at last logout, if any — same behaviour as
// password login, because it is the same customer either way.
$stmt = $conn->prepare('SELECT cart_data FROM user_carts WHERE user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// New session id on privilege change, so a session id captured before login
// cannot be reused afterwards (session fixation).
session_regenerate_id(true);
$_SESSION['user'] = $user;
$savedCart = $row ? (json_decode($row['cart_data'], true) ?? []) : [];
$_SESSION['cart'] = $savedCart ?: ($_SESSION['cart'] ?? []);

respond(['success' => true, 'user' => $user]);
