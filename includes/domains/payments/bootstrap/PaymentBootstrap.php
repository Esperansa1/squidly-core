<?php

namespace Squidly\Domains\Payments\Bootstrap;

use Squidly\Domains\Payments\Rest\PaymentRestController;
use Squidly\Domains\Payments\Hooks\PaymentStatusSync;
use Squidly\Domains\Payments\Admin\PaymentAdminActions;
use Squidly\Domains\Payments\Activation\PaymentProductActivation;

class PaymentBootstrap {
    
    public static function init(): void {
        add_action('init', [self::class, 'initialize_components']);
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
        
        register_activation_hook(__FILE__, [PaymentProductActivation::class, 'createPaymentProduct']);
        register_deactivation_hook(__FILE__, [PaymentProductActivation::class, 'cleanupPaymentProduct']);
    }
    
    public static function initialize_components(): void {
        if (class_exists('WooCommerce')) {
            new PaymentStatusSync();
        }
        
        if (is_admin()) {
            new PaymentAdminActions();
        }
    }
    
    public static function register_rest_routes(): void {
        $payment_rest_controller = new PaymentRestController();
        $payment_rest_controller->register_routes();
    }
}