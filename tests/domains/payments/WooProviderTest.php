<?php

use PHPUnit\Framework\TestCase;
use Squidly\Domains\Payments\Gateways\WooProvider;

class WooProviderTest extends TestCase {
    
    private WooProvider $provider;
    
    protected function setUp(): void {
        $this->provider = new WooProvider();
        
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                return $default;
            }
        }
        
        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $meta_key, $meta_value) {
                return true;
            }
        }
        
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $meta_key, $single = false) {
                return $single ? '' : [];
            }
        }
    }
    
    public function testLabelReturnsWooCommerce(): void {
        $label = $this->provider->label();
        $this->assertEquals('WooCommerce', $label);
    }
    
    public function testStartPaymentReturnsErrorWhenWooNotActive(): void {
        if (class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is active, cannot test inactive state');
        }
        
        $result = $this->provider->startPayment(1, '100.00', []);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('WooCommerce is not active', $result['error']);
    }
    
    public function testStartPaymentReturnsErrorWhenNoPaymentProduct(): void {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce not available for testing');
        }
        
        if (!function_exists('wc_get_product')) {
            function wc_get_product($product_id) {
                return false;
            }
        }
        
        $result = $this->provider->startPayment(1, '100.00', []);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Payment product not configured', $result['error']);
    }
    
    public function testRefundReturnsErrorWhenNoWcOrderId(): void {
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $meta_key, $single = false) {
                return $single ? null : [];
            }
        }
        
        $result = $this->provider->refund(1, '50.00', 'Test refund');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
        $this->assertEquals('No WooCommerce order found', $result['message']);
    }
    
    public function testMapBillingFieldsCorrectly(): void {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('mapBillingFields');
        $method->setAccessible(true);
        
        $input_fields = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
            'address' => '123 Main St',
            'city' => 'Anytown',
            'postcode' => '12345',
            'country' => 'US'
        ];
        
        $expected_output = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
            'address_1' => '123 Main St',
            'city' => 'Anytown',
            'postcode' => '12345',
            'country' => 'US'
        ];
        
        $result = $method->invoke($this->provider, $input_fields);
        
        $this->assertEquals($expected_output, $result);
    }
    
    public function testMapBillingFieldsWithPartialData(): void {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('mapBillingFields');
        $method->setAccessible(true);
        
        $input_fields = [
            'first_name' => 'John',
            'email' => 'john@example.com'
        ];
        
        $expected_output = [
            'first_name' => 'John',
            'email' => 'john@example.com'
        ];
        
        $result = $method->invoke($this->provider, $input_fields);
        
        $this->assertEquals($expected_output, $result);
    }
    
    public function testMapBillingFieldsWithEmptyArray(): void {
        $reflection = new ReflectionClass($this->provider);
        $method = $reflection->getMethod('mapBillingFields');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->provider, []);
        
        $this->assertEquals([], $result);
    }
}