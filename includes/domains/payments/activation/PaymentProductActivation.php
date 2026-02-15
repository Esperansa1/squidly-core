<?php

namespace Squidly\Domains\Payments\Activation;

class PaymentProductActivation {
    
    public static function createPaymentProduct(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $existing_product_id = get_option('squidly_wc_payment_product_id');
        
        if ($existing_product_id && wc_get_product($existing_product_id)) {
            return;
        }
        
        $product = new \WC_Product_Simple();
        $product->set_name('[System] Payment Processor - Do Not Delete');
        $product->set_description('Internal product used for payment processing. Required for Squidly orders. Do not modify or delete.');
        $product->set_status('publish');  // Must be 'publish' for orders to be payable
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_price(0);
        $product->set_catalog_visibility('hidden');  // Hidden from catalog but still purchasable

        // Add meta to identify as system product
        $product->add_meta_data('_squidly_system_product', 'yes', true);
        $product->add_meta_data('_squidly_product_type', 'payment_processor', true);
        
        $product_id = $product->save();
        
        if ($product_id) {
            update_option('squidly_wc_payment_product_id', $product_id);
        }
    }
    
    public static function cleanupPaymentProduct(): void {
        $product_id = get_option('squidly_wc_payment_product_id');

        if ($product_id && class_exists('WooCommerce')) {
            wp_delete_post($product_id, true);
        }

        delete_option('squidly_wc_payment_product_id');
    }

    /**
     * Update existing payment product to 'publish' status if it's 'private'
     */
    public static function updatePaymentProductStatus(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $product_id = get_option('squidly_wc_payment_product_id');
        if (!$product_id) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Update status to 'publish' if it's not already
        if ($product->get_status() !== 'publish') {
            $product->set_status('publish');
            $product->save();
        }
    }
}