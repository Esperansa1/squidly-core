<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use AdminMenuManager;
use CustomerRepository;
use ProductRepository;
use WP_UnitTestCase;

/**
 * Integration tests for AdminMenuManager.
 * 
 * Tests admin menu functionality, dashboard widgets, and statistical calculations.
 */
class AdminMenuManagerTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize AdminMenuManager
        AdminMenuManager::init();
    }

    /* ---------------------------------------------------------------------
     *  Basic Functionality Tests
     * -------------------------------------------------------------------*/

    public function test_menu_slug_constant_is_defined(): void
    {
        $this->assertEquals('squidly-restaurant', AdminMenuManager::MENU_SLUG);
    }

    public function test_dashboard_page_renders_without_errors(): void
    {
        // Test that dashboard page can be called without throwing errors
        ob_start();
        AdminMenuManager::dashboardPage();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Squidly Restaurant Dashboard', $output);
        $this->assertStringContainsString('Welcome to Squidly Restaurant', $output);
        $this->assertStringContainsString('Quick Stats', $output);
        $this->assertStringContainsString('Quick Actions', $output);
    }

    public function test_settings_page_renders_form(): void
    {
        ob_start();
        AdminMenuManager::settingsPage();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Restaurant Settings', $output);
        $this->assertStringContainsString('Currency', $output);
        $this->assertStringContainsString('Loyalty Points Rate', $output);
        $this->assertStringContainsString('Guest Checkout', $output);
        $this->assertStringContainsString('<form', $output);
    }

    public function test_reports_page_renders_sections(): void
    {
        ob_start();
        AdminMenuManager::reportsPage();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Reports', $output);
        $this->assertStringContainsString('Customer Reports', $output);
        $this->assertStringContainsString('Product Reports', $output);
    }

    /* ---------------------------------------------------------------------
     *  Statistics and Data Tests
     * -------------------------------------------------------------------*/

    public function test_statistics_with_real_data(): void
    {
        // Create some test data
        $customerId = wp_insert_post([
            'post_title' => 'Test Customer',
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);
        
        $productId = wp_insert_post([
            'post_title' => 'Test Product',
            'post_type' => 'product', 
            'post_status' => 'publish'
        ]);

        // Test dashboard with real data
        ob_start();
        AdminMenuManager::dashboardPage();
        $output = ob_get_clean();
        
        // Should contain the widgets and structure
        $this->assertStringContainsString('Quick Stats', $output);
        $this->assertStringContainsString('Customers', $output);
        $this->assertStringContainsString('Products', $output);
        $this->assertStringContainsString('stat-number', $output); // CSS class for stats

        // Clean up
        wp_delete_post($customerId, true);
        wp_delete_post($productId, true);
    }

    /* ---------------------------------------------------------------------
     *  Widget Content Tests
     * -------------------------------------------------------------------*/

    public function test_quick_actions_widget_contains_proper_links(): void
    {
        ob_start();
        
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        $method = $reflection->getMethod('quickActionsWidget');
        $method->setAccessible(true);
        
        $method->invoke(null);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Quick Actions', $output);
        $this->assertStringContainsString('Add New Branch', $output);
        $this->assertStringContainsString('Add New Product', $output);
        $this->assertStringContainsString('Add New Customer', $output);
        $this->assertStringContainsString('Add New Ingredient', $output);
        $this->assertStringContainsString('Restaurant Settings', $output);
        $this->assertStringContainsString('View Reports', $output);
        
        // Check for proper URLs
        $this->assertStringContainsString('post-new.php?post_type=store_branch', $output);
        $this->assertStringContainsString('post-new.php?post_type=product', $output);
        $this->assertStringContainsString('post-new.php?post_type=customer', $output);
        $this->assertStringContainsString('post-new.php?post_type=ingredient', $output);
    }

    public function test_system_status_widget_shows_version_info(): void
    {
        ob_start();
        
        $reflection = new \ReflectionClass(AdminMenuManager::class);
        $method = $reflection->getMethod('systemStatusWidget');
        $method->setAccessible(true);
        
        $method->invoke(null);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('System Status', $output);
        $this->assertStringContainsString('WordPress Version', $output);
        $this->assertStringContainsString('PHP Version', $output);
        $this->assertStringContainsString('Plugin Version', $output);
        $this->assertStringContainsString('Database Status', $output);
        $this->assertStringContainsString('Post Types', $output);
        
        // Should show version numbers
        $this->assertStringContainsString(get_bloginfo('version'), $output);
        $this->assertStringContainsString(PHP_VERSION, $output);
    }

    /* ---------------------------------------------------------------------
     *  Reports Integration Tests
     * -------------------------------------------------------------------*/

    public function test_customer_reports_renders_with_real_data(): void
    {
        // Create test customer data
        $guestId = wp_insert_post([
            'post_title' => 'Guest Customer',
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);
        update_post_meta($guestId, '_is_guest', true);
        update_post_meta($guestId, '_is_active', true);

        $registeredId = wp_insert_post([
            'post_title' => 'Registered Customer', 
            'post_type' => 'customer',
            'post_status' => 'publish'
        ]);
        update_post_meta($registeredId, '_is_guest', false);
        update_post_meta($registeredId, '_is_active', true);

        // Test the reports page with real data
        ob_start();
        AdminMenuManager::reportsPage();
        $output = ob_get_clean();
        
        // Should contain report structure
        $this->assertStringContainsString('Customer Reports', $output);
        $this->assertStringContainsString('Product Reports', $output);
        $this->assertStringContainsString('<table', $output);

        // Clean up
        wp_delete_post($guestId, true);
        wp_delete_post($registeredId, true);
    }

}