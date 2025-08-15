<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use AdminEnhancements;
use WP_UnitTestCase;

/**
 * Integration tests for AdminEnhancements.
 * 
 * Tests admin UI functionality including column customization
 * and display logic for customer and product admin lists.
 */
class AdminEnhancementsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Ensure AdminEnhancements is initialized
        AdminEnhancements::init();
    }

    /* ---------------------------------------------------------------------
     *  Customer List Column Tests
     * -------------------------------------------------------------------*/

    public function test_customer_list_columns_adds_required_columns(): void
    {
        $originalColumns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date'
        ];

        $result = AdminEnhancements::customerListColumns($originalColumns);

        // Should preserve original columns
        $this->assertArrayHasKey('cb', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('date', $result);

        // Should add new columns after title
        $this->assertArrayHasKey('customer_phone', $result);
        $this->assertArrayHasKey('customer_type', $result);
        $this->assertArrayHasKey('customer_orders', $result);
        $this->assertArrayHasKey('customer_loyalty', $result);

        // Check column labels
        $this->assertEquals('Phone', $result['customer_phone']);
        $this->assertEquals('Type', $result['customer_type']);
        $this->assertEquals('Orders', $result['customer_orders']);
        $this->assertEquals('Loyalty Points', $result['customer_loyalty']);
    }

    public function test_customer_list_column_content_with_real_customer(): void
    {
        // Create a real customer with actual data
        $customerId = wp_insert_post([
            'post_title' => 'Test Customer (0501234567)',
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);
        
        // Set customer meta data
        update_post_meta($customerId, '_phone', '0501234567');
        update_post_meta($customerId, '_is_guest', false);
        update_post_meta($customerId, '_total_orders', 3);
        update_post_meta($customerId, '_total_spent', 125.50);
        update_post_meta($customerId, '_loyalty_points_balance', 15.25);

        // Test phone display
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_phone', $customerId);
        $phoneOutput = ob_get_clean();
        $this->assertStringContainsString('0501234567', $phoneOutput);

        // Test registered customer type display
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_type', $customerId);
        $typeOutput = ob_get_clean();
        $this->assertStringContainsString('Registered', $typeOutput);

        // Test order stats display
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_orders', $customerId);
        $ordersOutput = ob_get_clean();
        $this->assertStringContainsString('3 orders', $ordersOutput);
        $this->assertStringContainsString('125.50', $ordersOutput);

        // Test loyalty points display
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_loyalty', $customerId);
        $loyaltyOutput = ob_get_clean();
        $this->assertStringContainsString('15.3 pts', $loyaltyOutput);

        // Clean up
        wp_delete_post($customerId, true);
    }

    public function test_customer_list_column_content_with_guest_customer(): void
    {
        // Create a guest customer
        $guestId = wp_insert_post([
            'post_title' => 'Guest Customer (0507654321)',
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);
        
        update_post_meta($guestId, '_phone', '0507654321');
        update_post_meta($guestId, '_is_guest', true);

        // Test guest type display
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_type', $guestId);
        $typeOutput = ob_get_clean();
        $this->assertStringContainsString('Guest', $typeOutput);

        // Test loyalty points N/A for guests
        ob_start();
        AdminEnhancements::customerListColumnContent('customer_loyalty', $guestId);
        $loyaltyOutput = ob_get_clean();
        $this->assertStringContainsString('N/A', $loyaltyOutput);

        // Clean up
        wp_delete_post($guestId, true);
    }

    /* ---------------------------------------------------------------------
     *  Product List Column Tests
     * -------------------------------------------------------------------*/

    public function test_product_list_columns_adds_product_columns(): void
    {
        $originalColumns = [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'date' => 'Date'
        ];

        $result = AdminEnhancements::productListColumns($originalColumns);

        $this->assertArrayHasKey('product_price', $result);
        $this->assertArrayHasKey('product_category', $result);
        $this->assertArrayHasKey('product_groups', $result);

        $this->assertEquals('Price', $result['product_price']);
        $this->assertEquals('Category', $result['product_category']);
        $this->assertEquals('Groups', $result['product_groups']);
    }

    public function test_product_list_column_content_with_real_product(): void
    {
        // Create a real product
        $productId = wp_insert_post([
            'post_title' => 'Test Product',
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);
        
        // Set product meta data
        update_post_meta($productId, '_regular_price', 25.50);
        update_post_meta($productId, '_category', 'Beverages');

        // Test regular price display
        ob_start();
        AdminEnhancements::productListColumnContent('product_price', $productId);
        $priceOutput = ob_get_clean();
        $this->assertStringContainsString('25.50', $priceOutput);
        $this->assertStringNotContainsString('<del>', $priceOutput);

        // Test category display
        ob_start();
        AdminEnhancements::productListColumnContent('product_category', $productId);
        $categoryOutput = ob_get_clean();
        $this->assertStringContainsString('Beverages', $categoryOutput);

        // Test empty product groups
        ob_start();
        AdminEnhancements::productListColumnContent('product_groups', $productId);
        $groupsOutput = ob_get_clean();
        $this->assertStringContainsString('None', $groupsOutput);

        // Clean up
        wp_delete_post($productId, true);
    }

    public function test_product_list_column_content_with_sale_price(): void
    {
        // Create product with sale price
        $productId = wp_insert_post([
            'post_title' => 'Sale Product',
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);
        
        update_post_meta($productId, '_regular_price', 30.00);
        update_post_meta($productId, '_sale_price', 25.00);

        // Test sale price display
        ob_start();
        AdminEnhancements::productListColumnContent('product_price', $productId);
        $priceOutput = ob_get_clean();
        
        $this->assertStringContainsString('<del>', $priceOutput); // Strikethrough regular price
        $this->assertStringContainsString('<strong>', $priceOutput); // Bold sale price
        $this->assertStringContainsString('30.00', $priceOutput); // Regular price
        $this->assertStringContainsString('25.00', $priceOutput); // Sale price

        // Clean up
        wp_delete_post($productId, true);
    }
}