<?php

declare(strict_types=1);

namespace CamerPay\Resources;

use CamerPay\HttpClient;

/**
 * Ressource Payments : initiate / status / refund.
 *
 * Usage :
 *   $tx = $camerpay->payments->initiate([...]);
 *   $tx = $camerpay->payments->status($uuid);
 *   $tx = $camerpay->payments->refund($uuid, amount: 2500, reason: 'Geste commercial');
 */
final class Payments
{
    private const ALLOWED_SCHEMES = ['https'];

    public function __construct(private readonly HttpClient $http) {}

    /**
     * Initie une transaction de paiement.
     *
     * Champs requis :
     *  - amount               (float)  Montant en XAF
     *  - merchant_invoice_id  (string) Votre reference interne
     *  - merchant_callback_url (string) URL HTTPS webhook
     *  - merchant_return_url  (string) URL HTTPS page retour
     *
     * Champs optionnels :
     *  - currency        (string) ISO 4217. Defaut XAF
     *  - payment_method  (string) orange_money|mtn_momo|stripe|paypal
     *  - customer_phone  (string) +237 6XX XX XX XX
     *  - customer_email  (string)
     *  - customer_name   (string)
     *  - idempotency_key (string) Anti-doublon
     *  - source          (string) Tag origine
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function initiate(array $data): array
    {
        $this->validateRequired($data, ['amount', 'merchant_invoice_id', 'merchant_callback_url', 'merchant_return_url']);

        $this->validateHttpsUrl($data['merchant_callback_url'], 'merchant_callback_url');
        $this->validateHttpsUrl($data['merchant_return_url'], 'merchant_return_url');

        return $this->http->post('/payment/initiate', $data);
    }

    /**
     * Recupere l'etat actuel d'une transaction.
     *
     * @return array<string, mixed>
     */
    public function status(string $uuid): array
    {
        return $this->http->get('/payment/' . $uuid . '/status');
    }

    /**
     * Rembourse une transaction (totale ou partielle).
     *
     * @return array<string, mixed>
     */
    public function refund(string $uuid, ?float $amount = null, string $reason = ''): array
    {
        $payload = array_filter([
            'amount' => $amount,
            'reason' => $reason !== '' ? $reason : null,
        ], fn($v) => $v !== null);

        return $this->http->post('/payment/' . $uuid . '/refund', $payload);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $required
     */
    private function validateRequired(array $data, array $required): void
    {
        $missing = array_diff($required, array_keys($data));
        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }

    /** @throws \InvalidArgumentException */
    private function validateHttpsUrl(string $url, string $fieldName): void
    {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException("{$fieldName} must be a valid URL.");
        }
        if (!in_array($parsed['scheme'], self::ALLOWED_SCHEMES, true)) {
            throw new \InvalidArgumentException("{$fieldName} must use HTTPS (got {$parsed['scheme']}).");
        }
    }
}
