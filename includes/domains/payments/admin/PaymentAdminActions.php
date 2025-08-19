<?php

namespace Squidly\Domains\Payments\Admin;

class PaymentAdminActions {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('post_row_actions', [$this, 'add_payment_row_actions'], 10, 2);
    }
    
    public function enqueue_admin_scripts($hook): void {
        if ($hook !== 'edit.php' || !isset($_GET['post_type'])) {
            return;
        }
        
        if ($_GET['post_type'] !== 'squidly_order') {
            return;
        }
        
        wp_enqueue_script(
            'squidly-payment-admin',
            plugins_url('js/payment-admin.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('squidly-payment-admin', 'squidly_payment', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('squidly/v1/pay/'),
            'nonce' => wp_create_nonce('squidly_payment_nonce')
        ]);
    }
    
    public function add_payment_row_actions(array $actions, \WP_Post $post): array {
        if ($post->post_type !== 'squidly_order') {
            return $actions;
        }
        
        if (!current_user_can('manage_options')) {
            return $actions;
        }
        
        $payment_status = get_post_meta($post->ID, '_payment_status', true);
        
        if ($payment_status !== 'paid') {
            $pay_nonce = wp_create_nonce('squidly_pay_' . $post->ID);
            $actions['pay'] = sprintf(
                '<a href="#" class="squidly-pay-action" data-order-id="%d" data-nonce="%s">%s</a>',
                $post->ID,
                $pay_nonce,
                __('Pay', 'squidly')
            );
        }
        
        if (in_array($payment_status, ['paid', 'processing'])) {
            $refund_nonce = wp_create_nonce('squidly_refund_' . $post->ID);
            $actions['refund'] = sprintf(
                '<a href="#" class="squidly-refund-action" data-order-id="%d" data-nonce="%s">%s</a>',
                $post->ID,
                $refund_nonce,
                __('Refund', 'squidly')
            );
        }
        
        return $actions;
    }
}