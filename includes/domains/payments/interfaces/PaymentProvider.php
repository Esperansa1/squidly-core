<?php

namespace Squidly\Domains\Payments\Interfaces;

interface PaymentProvider {
    
    public function startPayment(int $squidly_order_id, string $amount, array $billing_fields): array;
    
    public function refund(int $squidly_order_id, string $amount, string $reason = ''): array;
    
    public function label(): string;
}