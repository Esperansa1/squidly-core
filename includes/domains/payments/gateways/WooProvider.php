<?php

namespace Squidly\Domains\Payments\Gateways;

use Squidly\Domains\Payments\Interfaces\PaymentProvider;

class WooProvider implements PaymentProvider {
    
    public function startPayment(int $squidly_order_id, string $amount, array $billing_fields): array {
        if (!$this->validateWooCommerceActive()) {
            return ['error' => 'WooCommerce is not active'];
        }
        
        $payment_product_id = $this->getPaymentProductId();
        if (!$payment_product_id) {
            return ['error' => 'Payment product not configured'];
        }
        
        try {
            $wc_order = $this->createWooOrder($squidly_order_id, $amount, $billing_fields, $payment_product_id);
            
            $this->updateSquidlyOrderMeta($squidly_order_id, $wc_order->get_id());
            
            $checkout_url = $wc_order->get_checkout_payment_url();
            
            return ['checkout_url' => $checkout_url];
            
        } catch (\Exception $e) {
            return ['error' => 'Failed to create payment: ' . $e->getMessage()];
        }
    }
    
    public function refund(int $squidly_order_id, string $amount, string $reason = ''): array {
        try {
            $wc_order_id = get_post_meta($squidly_order_id, '_wc_order_id', true);
            
            if (!$wc_order_id) {
                return ['success' => false, 'message' => 'No WooCommerce order found'];
            }
            
            $wc_order = wc_get_order($wc_order_id);
            if (!$wc_order) {
                return ['success' => false, 'message' => 'WooCommerce order not found'];
            }
            
            $refund = wc_create_refund([
                'order_id' => $wc_order_id,
                'amount' => $amount,
                'reason' => $reason
            ]);
            
            if (is_wp_error($refund)) {
                return ['success' => false, 'message' => $refund->get_error_message()];
            }
            
            update_post_meta($squidly_order_id, '_payment_status', 'refunded');
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refund->get_id()
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Refund failed: ' . $e->getMessage()];
        }
    }
    
    public function label(): string {
        return 'WooCommerce';
    }
    
    private function validateWooCommerceActive(): bool {
        return class_exists('WooCommerce');
    }
    
    private function getPaymentProductId(): ?int {
        $product_id = get_option('squidly_wc_payment_product_id');
        
        if (!$product_id || !wc_get_product($product_id)) {
            return null;
        }
        
        return (int) $product_id;
    }
    
    private function createWooOrder(int $squidly_order_id, string $amount, array $billing_fields, int $product_id): \WC_Order {
        $wc_order = wc_create_order([
            'status' => 'pending',
            'created_via' => 'squidly'
        ]);
        
        $product = wc_get_product($product_id);
        $wc_order->add_product($product, 1);
        
        $billing_mapped = $this->mapBillingFields($billing_fields);
        $wc_order->set_address($billing_mapped, 'billing');
        
        $wc_order->set_total($amount);
        
        $wc_order->add_meta_data('_squidly_order_id', $squidly_order_id, true);
        
        $wc_order->save();
        
        return $wc_order;
    }
    
    private function updateSquidlyOrderMeta(int $squidly_order_id, int $wc_order_id): void {
        update_post_meta($squidly_order_id, '_wc_order_id', $wc_order_id);
        update_post_meta($squidly_order_id, '_payment_status', 'pending');
    }
    
    private function mapBillingFields(array $billing_fields): array {
        $field_mapping = [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'address' => 'address_1',
            'city' => 'city',
            'postcode' => 'postcode',
            'country' => 'country'
        ];
        
        $mapped = [];
        foreach ($field_mapping as $squidly_field => $wc_field) {
            if (isset($billing_fields[$squidly_field])) {
                $mapped[$wc_field] = $billing_fields[$squidly_field];
            }
        }
        
        return $mapped;
    }
}