<?php

declare(strict_types=1);

namespace CamerPay;

use CamerPay\Exceptions\AuthenticationException;
use CamerPay\Exceptions\CamerPayException;
use CamerPay\Exceptions\NotFoundException;
use CamerPay\Exceptions\QuotaExceededException;
use CamerPay\Exceptions\ServerException;
use CamerPay\Exceptions\ValidationException;

/**
 * Client HTTP minimaliste base sur cURL natif (zero deps).
 * Volontairement pas de Guzzle pour eviter un conflit de version chez
 * les clients qui ont deja Guzzle 6 ou 7.
 *
 * Conversions :
 *  - 200/201  -> retour array decodee JSON
 *  - 401      -> AuthenticationException
 *  - 402      -> QuotaExceededException (KYC tier / plan)
 *  - 404      -> NotFoundException
 *  - 422      -> ValidationException avec field errors
 *  - 5xx      -> ServerException
 *  - autre    -> CamerPayException generique
 */
class HttpClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int    $timeout,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: CamerPay-PHP-SDK/' . CamerPay::VERSION,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);
        $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new CamerPayException("HTTP request failed: {$curlError}");
        }

        $response = json_decode((string) $rawResponse, true) ?? [];

        return match (true) {
            $httpCode >= 200 && $httpCode < 300 => $response,
            $httpCode === 401                   => throw new AuthenticationException($response['message'] ?? 'Authentication failed', $httpCode, $response),
            $httpCode === 402                   => throw new QuotaExceededException($response['message'] ?? 'Quota exceeded', $httpCode, $response),
            $httpCode === 404                   => throw new NotFoundException($response['message'] ?? 'Resource not found', $httpCode, $response),
            $httpCode === 422                   => throw new ValidationException($response['message'] ?? 'Validation failed', $httpCode, $response),
            $httpCode >= 500                    => throw new ServerException($response['message'] ?? 'Server error', $httpCode, $response),
            default                             => throw new CamerPayException($response['message'] ?? "Unexpected HTTP {$httpCode}", $httpCode, $response),
        };
    }
}
