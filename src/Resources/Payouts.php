<?php

declare(strict_types=1);

namespace CamerPay\Resources;

use CamerPay\HttpClient;

/**
 * Ressource Payouts : versements groupes (Mass Payout).
 *
 * Usage :
 *   $batch = $camerpay->payouts->createBatch([
 *       'reference' => 'SALAIRES-2026-06',
 *       'callback_url' => 'https://votre-site.com/webhooks/payouts',
 *       'beneficiaries' => [
 *           ['phone' => '+237690000001', 'amount' => 75000, 'operator' => 'orange_money', 'name' => 'Jean'],
 *           ['phone' => '+237670000002', 'amount' => 50000, 'operator' => 'mtn_momo',     'name' => 'Aisha'],
 *       ],
 *   ]);
 *
 *   $state = $camerpay->payouts->getBatch($batch['batch_uuid']);
 */
final class Payouts
{
    private const ALLOWED_SCHEMES = ['https'];

    public function __construct(private readonly HttpClient $http) {}

    /**
     * Cree un batch de payouts.
     *
     * Limites BEAC :
     *  - 100 beneficiaires max par batch
     *  - 1 000 000 XAF max par beneficiaire
     *  - 2 000 000 XAF max cumul par batch
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createBatch(array $data): array
    {
        if (!isset($data['beneficiaries']) || !is_array($data['beneficiaries'])) {
            throw new \InvalidArgumentException('Missing required field: beneficiaries (array)');
        }
        if (count($data['beneficiaries']) > 100) {
            throw new \InvalidArgumentException('Max 100 beneficiaries per batch (BEAC limit)');
        }

        if (isset($data['callback_url'])) {
            $this->validateHttpsUrl($data['callback_url'], 'callback_url');
        }

        return $this->http->post('/payouts/batch', $data);
    }

    /**
     * Recupere l'etat d'un batch.
     *
     * @return array<string, mixed>
     */
    public function getBatch(string $batchUuid): array
    {
        return $this->http->get('/payouts/batch/' . $batchUuid);
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
