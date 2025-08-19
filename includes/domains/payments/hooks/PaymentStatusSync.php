<?php

namespace Squidly\Domains\Payments\Hooks;

class PaymentStatusSync {
    
    public function __construct() {
        $this->registerHooks();
    }
    
    private function registerHooks(): void {
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderStatusChange'], 10, 2);
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderStatusChange'], 10, 2);
        add_action('woocommerce_order_status_failed', [$this, 'handleOrderStatusChange'], 10, 2);
        add_action('woocommerce_order_status_refunded', [$this, 'handleOrderStatusChange'], 10, 2);
        add_action('woocommerce_order_status_cancelled', [$this, 'handleOrderStatusChange'], 10, 2);
        add_action('woocommerce_order_status_on-hold', [$this, 'handleOrderStatusChange'], 10, 2);
    }
    
    public function handleOrderStatusChange(int $order_id, \WC_Order $wc_order): void {
        $squidly_order_id = $wc_order->get_meta('_squidly_order_id');
        
        if (!$squidly_order_id) {
            return;
        }
        
        $wc_status = $wc_order->get_status();
        $squidly_status = $this->mapWooStatusToSquidly($wc_status);
        
        if (!$squidly_status) {
            return;
        }
        
        update_post_meta($squidly_order_id, '_payment_status', $squidly_status);
        
        if ($squidly_status === 'paid') {
            $transaction_id = $wc_order->get_transaction_id();
            if ($transaction_id) {
                update_post_meta($squidly_order_id, '_tx_id', $transaction_id);
            }
        }
    }
    
    private function mapWooStatusToSquidly(string $wc_status): ?string {
        $status_mapping = [
            'processing' => 'paid',
            'completed' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'cancelled' => 'failed',
            'on-hold' => 'pending'
        ];
        
        return $status_mapping[$wc_status] ?? null;
    }
}