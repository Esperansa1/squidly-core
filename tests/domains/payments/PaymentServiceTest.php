<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Squidly\Domains\Payments\Services\PaymentService;
use Squidly\Domains\Payments\Interfaces\PaymentProvider;

class PaymentServiceTest extends TestCase {
    
    private PaymentService $service;
    private MockObject $mockProvider;
    
    protected function setUp(): void {
        $this->mockProvider = $this->createMock(PaymentProvider::class);
        
        add_filter('squidly/payments/provider', function() {
            return $this->mockProvider;
        });
        
        $this->service = new PaymentService();
    }
    
    protected function tearDown(): void {
        remove_all_filters('squidly/payments/provider');
    }
    
    public function testStartPaymentCallsProvider(): void {
        $expected_result = ['checkout_url' => 'https://example.com/checkout'];
        
        $this->mockProvider
            ->expects($this->once())
            ->method('startPayment')
            ->with(123, '100.00', ['email' => 'test@example.com'])
            ->willReturn($expected_result);
        
        $result = $this->service->startPayment(123, '100.00', ['email' => 'test@example.com']);
        
        $this->assertEquals($expected_result, $result);
    }
    
    public function testRefundCallsProvider(): void {
        $expected_result = ['success' => true, 'message' => 'Refund processed'];
        
        $this->mockProvider
            ->expects($this->once())
            ->method('refund')
            ->with(123, '50.00', 'Customer request')
            ->willReturn($expected_result);
        
        $result = $this->service->refund(123, '50.00', 'Customer request');
        
        $this->assertEquals($expected_result, $result);
    }
    
    public function testGetProviderLabelCallsProvider(): void {
        $expected_label = 'Test Provider';
        
        $this->mockProvider
            ->expects($this->once())
            ->method('label')
            ->willReturn($expected_label);
        
        $result = $this->service->getProviderLabel();
        
        $this->assertEquals($expected_label, $result);
    }
    
    public function testStartPaymentWithEmptyBillingFields(): void {
        $expected_result = ['checkout_url' => 'https://example.com/checkout'];
        
        $this->mockProvider
            ->expects($this->once())
            ->method('startPayment')
            ->with(123, '100.00', [])
            ->willReturn($expected_result);
        
        $result = $this->service->startPayment(123, '100.00');
        
        $this->assertEquals($expected_result, $result);
    }
    
    public function testRefundWithEmptyReason(): void {
        $expected_result = ['success' => true, 'message' => 'Refund processed'];
        
        $this->mockProvider
            ->expects($this->once())
            ->method('refund')
            ->with(123, '50.00', '')
            ->willReturn($expected_result);
        
        $result = $this->service->refund(123, '50.00');
        
        $this->assertEquals($expected_result, $result);
    }
    
    public function testInvalidProviderThrowsException(): void {
        remove_all_filters('squidly/payments/provider');
        
        add_filter('squidly/payments/provider', function() {
            return new \stdClass();
        });
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment provider must implement PaymentProvider interface');
        
        new PaymentService();
    }
}