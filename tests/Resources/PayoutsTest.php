<?php

declare(strict_types=1);

namespace CamerPay\Tests\Resources;

use CamerPay\Resources\Payouts;
use PHPUnit\Framework\TestCase;

final class PayoutsTest extends TestCase
{
    public function testCreateBatchRequiresBeneficiaries(): void
    {
        $http = $this->createMockHttpClient([]);
        $payouts = new Payouts($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: beneficiaries (array)');

        $payouts->createBatch([
            'reference' => 'BATCH-001',
        ]);
    }

    public function testCreateBatchRejectsMoreThan100Beneficiaries(): void
    {
        $http = $this->createMockHttpClient([]);
        $payouts = new Payouts($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max 100 beneficiaries per batch (BEAC limit)');

        $beneficiaries = [];
        for ($i = 0; $i <= 100; $i++) {
            $beneficiaries[] = ['phone' => '+237612345678', 'amount' => 1000, 'operator' => 'orange_money', 'name' => 'User'];
        }

        $payouts->createBatch([
            'reference'     => 'BATCH-001',
            'beneficiaries' => $beneficiaries,
        ]);
    }

    public function testCreateBatchRejectsNonHttpsCallbackUrl(): void
    {
        $http = $this->createMockHttpClient([]);
        $payouts = new Payouts($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('callback_url must use HTTPS (got http)');

        $payouts->createBatch([
            'reference'     => 'BATCH-001',
            'callback_url'  => 'http://example.com/webhook',
            'beneficiaries' => [
                ['phone' => '+237612345678', 'amount' => 1000, 'operator' => 'orange_money', 'name' => 'User'],
            ],
        ]);
    }

    public function testCreateBatchAcceptsHttpsCallbackUrl(): void
    {
        $http = $this->createMockHttpClient([
            'batch_uuid'       => 'batch-123',
            'status'           => 'created',
            'total_amount'     => 10000,
            'beneficiary_count' => 1,
        ]);

        $payouts = new Payouts($http);

        $result = $payouts->createBatch([
            'reference'     => 'BATCH-001',
            'callback_url'  => 'https://example.com/webhook',
            'beneficiaries' => [
                ['phone' => '+237612345678', 'amount' => 1000, 'operator' => 'orange_money', 'name' => 'User'],
            ],
        ]);

        $this->assertSame('batch-123', $result['batch_uuid']);
    }

    public function testGetBatchSendsCorrectPath(): void
    {
        $http = $this->createMockHttpClient([
            'batch_uuid' => 'batch-123',
            'status'     => 'processing',
        ]);

        $payouts = new Payouts($http);

        $result = $payouts->getBatch('batch-123');

        $this->assertSame('batch-123', $result['batch_uuid']);
        $this->assertSame('processing', $result['status']);
    }

    /** @return \PHPUnit\Framework\MockObject\MockObject&\CamerPay\HttpClient */
    private function createMockHttpClient(array $payload): \PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->getMockBuilder(\CamerPay\HttpClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['post', 'get'])
            ->getMock();

        $mock->method('post')
            ->willReturn($payload);

        $mock->method('get')
            ->willReturn($payload);

        return $mock;
    }
}
