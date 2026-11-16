<?php
// The payment contract.
//
// Nothing outside this folder knows which company processes the money. The
// order pipeline knows only the three operations below, so swapping Xendit
// for PayMongo (or for the built-in simulator) is a config change, not a
// rewrite. That is what "loose coupling" buys: the consumer depends on the
// contract, not on the provider.

require_once __DIR__ . '/../config.php';

interface PaymentGateway
{
    /**
     * Start a payment. Returns ['reference' => string, 'checkoutUrl' => string].
     * reference is the provider's own id for this attempt — the key we later
     * recognise the webhook by.
     */
    public function createCheckout(int $orderId, float $amount, string $description, array $customer): array;

    /**
     * Is this webhook really from the provider? Called on the RAW body,
     * before any parsing, because a tampered body must fail here.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Normalise a provider payload into our own shape:
     * ['eventId' => string, 'type' => string, 'reference' => string, 'status' => string]
     * status is one of: paid, failed, expired, refunded, pending.
     * Returns null for events we do not care about.
     */
    public function parseEvent(array $payload): ?array;

    public function name(): string;
}

/**
 * Payment settings, with safe defaults so a config.php written before this
 * feature existed still works — it just runs in mock mode.
 */
function payment_config(): array
{
    global $payment_config;

    $defaults = [
        'provider' => 'mock',
        'base_url' => null,
        'mock' => ['webhook_secret' => 'craveh-mock-dev-secret'],
        'xendit' => ['secret_key' => '', 'webhook_token' => ''],
    ];

    $config = array_replace_recursive($defaults, is_array($payment_config ?? null) ? $payment_config : []);

    // Work out where this install lives, so redirect URLs do not have to be
    // hardcoded per machine.
    if (empty($config['base_url'])) {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $root = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php')), '/');
        $config['base_url'] = "$scheme://$host$root";
    }

    return $config;
}

function payment_gateway(): PaymentGateway
{
    $config = payment_config();

    switch ($config['provider']) {
        case 'xendit':
            require_once __DIR__ . '/xendit_gateway.php';
            return new XenditGateway($config);
        case 'mock':
            require_once __DIR__ . '/mock_gateway.php';
            return new MockGateway($config);
        default:
            throw new RuntimeException('Unknown payment provider: ' . $config['provider']);
    }
}

/**
 * Case-insensitive header lookup. PHP spells incoming headers differently
 * depending on the server, so never index $headers directly.
 */
function payment_header(array $headers, string $name): ?string
{
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, $name) === 0) {
            return is_array($value) ? ($value[0] ?? null) : (string) $value;
        }
    }
    return null;
}

function payment_request_headers(): array
{
    if (function_exists('getallheaders')) {
        return getallheaders() ?: [];
    }
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
        }
    }
    return $headers;
}
