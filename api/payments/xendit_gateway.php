<?php
// Xendit Test Mode (https://docs.xendit.co) — the real third-party gateway.
//
// Only reached when config.php sets 'provider' => 'xendit' and a test secret
// key is present. Everything above this file is identical either way: that is
// the whole point of the PaymentGateway contract.
//
// NOTE: verify the request/response field names against the current Xendit
// docs before the demo. Provider contracts change; ours does not.

require_once __DIR__ . '/gateway.php';

class XenditGateway implements PaymentGateway
{
    private const INVOICE_ENDPOINT = 'https://api.xendit.co/v2/invoices';
    private const TIMEOUT_SECONDS = 20;

    private string $secretKey;
    private string $webhookToken;
    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->secretKey = (string) $config['xendit']['secret_key'];
        $this->webhookToken = (string) $config['xendit']['webhook_token'];
        $this->baseUrl = rtrim((string) $config['base_url'], '/');

        if ($this->secretKey === '') {
            throw new RuntimeException('Xendit secret key is not configured');
        }
    }

    public function name(): string
    {
        return 'xendit';
    }

    public function createCheckout(int $orderId, float $amount, string $description, array $customer): array
    {
        $body = [
            // external_id is OUR id for this attempt. It is what lets us
            // recognise the invoice later even if we lost the response.
            'external_id' => 'craveh-order-' . $orderId . '-' . time(),
            'amount' => round($amount, 2),
            'currency' => 'PHP',
            'description' => $description,
            'payment_methods' => ['GCASH'],
            'invoice_duration' => 3600,
            // Where the customer's BROWSER lands afterwards. Note this is not
            // where the order is marked paid — only the webhook does that.
            'success_redirect_url' => $this->baseUrl . '/history.html?order=' . $orderId,
            'failure_redirect_url' => $this->baseUrl . '/history.html?order=' . $orderId,
            'customer' => [
                'given_names' => $customer['name'] ?? 'Craveh Customer',
                'email' => $customer['email'] ?? null,
                'mobile_number' => $customer['phone'] ?? null,
            ],
        ];

        $response = $this->post(self::INVOICE_ENDPOINT, $body);

        if (empty($response['id']) || empty($response['invoice_url'])) {
            throw new RuntimeException('Xendit did not return an invoice: ' . json_encode($response));
        }

        return [
            'reference' => (string) $response['id'],
            'checkoutUrl' => (string) $response['invoice_url'],
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $token = payment_header($headers, 'x-callback-token');
        if (!$token || $this->webhookToken === '') {
            return false;
        }
        return hash_equals($this->webhookToken, $token);
    }

    public function parseEvent(array $payload): ?array
    {
        $reference = (string) ($payload['id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));
        if (!$reference || !$status) {
            return null;
        }

        $mapped = match ($status) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
            default => null,
        };
        if ($mapped === null) {
            return null;
        }

        return [
            // Xendit invoice callbacks carry no separate event id, so the
            // invoice id plus its status is the thing that must not be
            // processed twice.
            'eventId' => $reference . ':' . $status,
            'type' => 'invoice.' . strtolower($status),
            'reference' => $reference,
            'status' => $mapped,
        ];
    }

    private function post(string $url, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                // Xendit uses HTTP Basic auth: secret key as the username,
                // empty password.
                'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
            ],
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // A gateway that does not answer is a failure mode to handle, not an
        // exception to let bubble into a blank page.
        if ($raw === false) {
            throw new RuntimeException('Could not reach Xendit: ' . $error);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Xendit returned a non-JSON response (HTTP ' . $httpCode . ')');
        }
        if ($httpCode >= 400) {
            throw new RuntimeException('Xendit error ' . $httpCode . ': ' . ($decoded['message'] ?? $raw));
        }

        return $decoded;
    }
}
