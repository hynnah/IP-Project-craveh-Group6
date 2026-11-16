<?php
// A stand-in GCash gateway that runs on this machine.
//
// It is deliberately built to behave like a real one rather than to be
// convenient: the customer leaves the site, the result comes back as a
// SIGNED server-to-server webhook, and the browser is never trusted to say
// "this order is paid". Only the webhook can do that. Swap `provider` to
// 'xendit' in config.php and the rest of the application does not change.

require_once __DIR__ . '/gateway.php';

class MockGateway implements PaymentGateway
{
    private string $secret;
    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->secret = (string) $config['mock']['webhook_secret'];
        $this->baseUrl = rtrim((string) $config['base_url'], '/');
    }

    public function name(): string
    {
        return 'mock';
    }

    public function createCheckout(int $orderId, float $amount, string $description, array $customer): array
    {
        // A real provider mints this id; we mint our own the same way, from a
        // cryptographically random source so it cannot be guessed.
        $reference = 'mock_' . bin2hex(random_bytes(12));

        return [
            'reference' => $reference,
            'checkoutUrl' => $this->baseUrl . '/mock-gcash.html?ref=' . urlencode($reference),
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signature = payment_header($headers, 'X-Craveh-Signature');
        if (!$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        // hash_equals, not ===. A normal string comparison returns as soon as
        // it finds a differing byte, and the time it took leaks how much of
        // the signature was right — enough to guess one byte at a time.
        return hash_equals($expected, $signature);
    }

    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, $this->secret);
    }

    public function parseEvent(array $payload): ?array
    {
        $type = (string) ($payload['type'] ?? '');
        $reference = (string) ($payload['data']['reference'] ?? '');
        if (!$reference) {
            return null;
        }

        $status = match ($type) {
            'payment.paid' => 'paid',
            'payment.failed' => 'failed',
            'payment.expired' => 'expired',
            default => null,
        };
        if ($status === null) {
            return null;
        }

        return [
            'eventId' => (string) ($payload['id'] ?? ''),
            'type' => $type,
            'reference' => $reference,
            'status' => $status,
        ];
    }
}
