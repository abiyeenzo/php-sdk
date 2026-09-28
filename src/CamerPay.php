<?php

declare(strict_types=1);

namespace CamerPay;

use CamerPay\Resources\Payments;
use CamerPay\Resources\Payouts;
use CamerPay\Webhooks\Verifier;

/**
 * CamerPay SDK — entry point.
 *
 * Usage minimal :
 *
 *   $camerpay = new \CamerPay\CamerPay('VOTRE_TOKEN_BEARER');
 *
 *   $tx = $camerpay->payments->initiate([
 *       'amount'                => 5000,
 *       'currency'              => 'XAF',
 *       'customer_phone'        => '+237690000000',
 *       'merchant_invoice_id'   => 'FACT-001',
 *       'merchant_callback_url' => 'https://votre-site.com/webhooks/camerpay',
 *       'merchant_return_url'   => 'https://votre-site.com/merci',
 *   ]);
 *
 *   echo $tx['pay_url'];
 *
 * Configuration avancee :
 *
 *   $camerpay = new \CamerPay\CamerPay(
 *       apiKey: $_ENV['CAMERPAY_TOKEN'],
 *       baseUrl: 'https://camerpay.biz/api',
 *       timeout: 30,
 *   );
 */
final class CamerPay
{
    public const VERSION = '1.0.0';

    public const DEFAULT_BASE_URL = 'https://camerpay.biz/api';
    public const DEFAULT_TIMEOUT  = 30;

    public readonly Payments $payments;
    public readonly Payouts  $payouts;

    private HttpClient $http;

    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int    $timeout = self::DEFAULT_TIMEOUT,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('CamerPay API key is required.');
        }

        $this->http = new HttpClient(
            apiKey: $apiKey,
            baseUrl: rtrim($baseUrl, '/'),
            timeout: $timeout,
        );

        $this->payments = new Payments($this->http);
        $this->payouts  = new Payouts($this->http);
    }

    /**
     * Helper de verification webhook signe HMAC-SHA256.
     * Pas besoin d'instancier CamerPay si on veut juste verifier
     * un webhook : utiliser Verifier::isValid() en static.
     */
    public static function webhooks(string $secret): Verifier
    {
        return new Verifier($secret);
    }
}
