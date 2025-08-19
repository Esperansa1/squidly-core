<?php

use PHPUnit\Framework\TestCase;
use Squidly\Domains\Payments\Interfaces\PaymentProvider;

class MockPaymentProvider implements PaymentProvider {
    
    public function startPayment(int $squidly_order_id, string $amount, array $billing_fields): array {
        return ['checkout_url' => 'https://example.com/checkout'];
    }
    
    public function refund(int $squidly_order_id, string $amount, string $reason = ''): array {
        return ['success' => true, 'message' => 'Refund processed', 'refund_id' => '123'];
    }
    
    public function label(): string {
        return 'Mock Provider';
    }
}

class PaymentProviderTest extends TestCase {
    
    private PaymentProvider $provider;
    
    protected function setUp(): void {
        $this->provider = new MockPaymentProvider();
    }
    
    public function testStartPaymentReturnsCheckoutUrl(): void {
        $result = $this->provider->startPayment(1, '100.00', []);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertEquals('https://example.com/checkout', $result['checkout_url']);
    }
    
    public function testRefundReturnsSuccessResult(): void {
        $result = $this->provider->refund(1, '50.00', 'Test refund');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('refund_id', $result);
        $this->assertTrue($result['success']);
        $this->assertEquals('Refund processed', $result['message']);
        $this->assertEquals('123', $result['refund_id']);
    }
    
    public function testLabelReturnsString(): void {
        $label = $this->provider->label();
        
        $this->assertIsString($label);
        $this->assertEquals('Mock Provider', $label);
    }
    
    public function testStartPaymentAcceptsCorrectParameters(): void {
        $billing_fields = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ];
        
        $result = $this->provider->startPayment(123, '250.50', $billing_fields);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('checkout_url', $result);
    }
    
    public function testRefundAcceptsOptionalReason(): void {
        $result_without_reason = $this->provider->refund(1, '25.00');
        $result_with_reason = $this->provider->refund(1, '25.00', 'Customer request');
        
        $this->assertIsArray($result_without_reason);
        $this->assertIsArray($result_with_reason);
        $this->assertArrayHasKey('success', $result_without_reason);
        $this->assertArrayHasKey('success', $result_with_reason);
    }
}