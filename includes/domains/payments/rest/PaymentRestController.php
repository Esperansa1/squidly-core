<?php

namespace Squidly\Domains\Payments\Rest;

use Squidly\Domains\Payments\Services\PaymentService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class PaymentRestController extends WP_REST_Controller {
    
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'pay';
    
    private PaymentService $payment_service;
    
    public function __construct() {
        $this->payment_service = new PaymentService();
    }
    
    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/start', [
            'methods' => 'POST',
            'callback' => [$this, 'start_payment'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'order_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'amount' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'billing' => [
                    'required' => false,
                    'type' => 'object',
                    'default' => []
                ]
            ]
        ]);
        
        register_rest_route($this->namespace, '/' . $this->rest_base . '/refund', [
            'methods' => 'POST',
            'callback' => [$this, 'refund_payment'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'order_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'amount' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ],
                'reason' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => ''
                ]
            ]
        ]);
    }
    
    public function start_payment(WP_REST_Request $request): WP_REST_Response {
        try {
            $order_id = $request->get_param('order_id');
            $amount = number_format((float)$request->get_param('amount'), 2, '.', '');
            $billing = $request->get_param('billing') ?? [];
            
            if (!get_post($order_id)) {
                return new WP_REST_Response([
                    'error' => 'Order not found'
                ], 404);
            }
            
            $result = $this->payment_service->startPayment($order_id, $amount, $billing);
            
            if (isset($result['error'])) {
                return new WP_REST_Response([
                    'error' => $result['error']
                ], 500);
            }
            
            return new WP_REST_Response($result, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Payment start failed'
            ], 500);
        }
    }
    
    public function refund_payment(WP_REST_Request $request): WP_REST_Response {
        try {
            $order_id = $request->get_param('order_id');
            $amount = number_format((float)$request->get_param('amount'), 2, '.', '');
            $reason = $request->get_param('reason') ?? '';
            
            if (!get_post($order_id)) {
                return new WP_REST_Response([
                    'error' => 'Order not found'
                ], 404);
            }
            
            $result = $this->payment_service->refund($order_id, $amount, $reason);
            
            if (!$result['success']) {
                return new WP_REST_Response([
                    'error' => $result['message']
                ], 400);
            }
            
            return new WP_REST_Response($result, 200);
            
        } catch (\Exception $e) {
            return new WP_REST_Response([
                'error' => 'Refund failed'
            ], 500);
        }
    }
    
    public function check_admin_permissions(): bool {
        return current_user_can('manage_options');
    }
}