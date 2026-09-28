<?php

declare(strict_types=1);

namespace CamerPay\Tests\Resources;

use CamerPay\Resources\Payments;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    public function testInitiateValidatesRequiredFields(): void
    {
        $http = $this->createMockHttpClient([]);
        $payments = new Payments($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: merchant_callback_url');

        $payments->initiate([
            'amount'               => 5000,
            'merchant_invoice_id'  => 'INV-001',
            'merchant_return_url'  => 'https://example.com/return',
        ]);
    }

    public function testInitiateRejectsNonHttpsCallbackUrl(): void
    {
        $http = $this->createMockHttpClient([]);
        $payments = new Payments($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('merchant_callback_url must use HTTPS (got http)');

        $payments->initiate([
            'amount'               => 5000,
            'merchant_invoice_id'  => 'INV-001',
            'merchant_callback_url' => 'http://example.com/webhook',
            'merchant_return_url'  => 'https://example.com/return',
        ]);
    }

    public function testInitiateRejectsNonHttpsReturnUrl(): void
    {
        $http = $this->createMockHttpClient([]);
        $payments = new Payments($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('merchant_return_url must use HTTPS (got http)');

        $payments->initiate([
            'amount'               => 5000,
            'merchant_invoice_id'  => 'INV-001',
            'merchant_callback_url' => 'https://example.com/webhook',
            'merchant_return_url'  => 'http://example.com/return',
        ]);
    }

    public function testInitiateSendsValidPayload(): void
    {
        $http = $this->createMockHttpClient([
            'success'          => true,
            'transaction_uuid' => 'tx-123',
            'status'           => 'pending',
            'pay_url'          => 'https://camerpay.biz/pay/tx-123',
            'redirect_url'     => 'https://example.com/return',
        ]);

        $payments = new Payments($http);

        $result = $payments->initiate([
            'amount'                => 5000,
            'currency'              => 'XAF',
            'payment_method'        => 'orange_money',
            'merchant_invoice_id'   => 'INV-001',
            'merchant_callback_url' => 'https://example.com/webhook',
            'merchant_return_url'   => 'https://example.com/return',
            'customer_phone'        => '+237612345678',
            'customer_email'        => 'client@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('tx-123', $result['transaction_uuid']);
    }

    /** @return \PHPUnit\Framework\MockObject\MockObject&\CamerPay\HttpClient */
    private function createMockHttpClient(array $payload): \PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->getMockBuilder(\CamerPay\HttpClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post'])
            ->getMock();

        $mock->method('post')
            ->willReturn($payload);

        return $mock;
    }
}
