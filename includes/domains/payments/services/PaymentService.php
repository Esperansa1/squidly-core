<?php

namespace Squidly\Domains\Payments\Services;

use Squidly\Domains\Payments\Interfaces\PaymentProvider;
use Squidly\Domains\Payments\Gateways\WooProvider;

class PaymentService {
    
    private PaymentProvider $provider;
    
    public function __construct(?PaymentProvider $provider = null) {
        $this->provider = $provider ?? $this->getProvider();
    }
    
    public function startPayment(int $squidly_order_id, string $amount, array $billing_fields = []): array {
        return $this->provider->startPayment($squidly_order_id, $amount, $billing_fields);
    }
    
    public function refund(int $squidly_order_id, string $amount, string $reason = ''): array {
        return $this->provider->refund($squidly_order_id, $amount, $reason);
    }
    
    public function getProviderLabel(): string {
        return $this->provider->label();
    }
    
    private function getProvider(): PaymentProvider {
        $provider = apply_filters('squidly/payments/provider', new WooProvider());
        
        if (!$provider instanceof PaymentProvider) {
            throw new \InvalidArgumentException('Payment provider must implement PaymentProvider interface');
        }
        
        return $provider;
    }
}