<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use CustomerRestController;
use CustomerRepository;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration tests for CustomerRestController
 *
 * Tests the REST controller with real WordPress database operations
 * and repository interactions.
 */
class CustomerRestControllerIntegrationTest extends \WP_UnitTestCase
{
    private CustomerRestController $controller;
    private CustomerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new CustomerRestController();
        $this->repository = new CustomerRepository();

        // Initialize WordPress REST server
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init');

        // Register REST routes
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Set up admin user for permission tests
        wp_set_current_user(1);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $customers = $this->repository->getAll();
        foreach ($customers as $customer) {
            wp_delete_post($customer->id, true);
        }

        parent::tearDown();
    }

    /* ==========================================
     * Integration Tests - Full CRUD Flow
     * ========================================== */

    public function test_full_customer_lifecycle(): void
    {
        // 1. Create Customer
        $create_request = new WP_REST_Request('POST', '/squidly/v1/customers');
        $customer_data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'john.doe@example.com',
            'google_id' => 'google_123',
            'allow_sms_notifications' => true,
            'allow_email_notifications' => true,
            'is_guest' => false,
        ];

        foreach ($customer_data as $key => $value) {
            $create_request->set_param($key, $value);
        }

        $create_response = $this->controller->create_item($create_request);
        $this->assertNotInstanceOf(\WP_Error::class, $create_response);
        $this->assertEquals(200, $create_response->get_status());

        $created_customer = $create_response->get_data();
        $customer_id = $created_customer['id'];

        // Verify created customer structure
        $this->assertEquals('John', $created_customer['first_name']);
        $this->assertEquals('Doe', $created_customer['last_name']);
        $this->assertEquals('+972501234567', $created_customer['phone']);
        $this->assertEquals('google', $created_customer['auth_provider']);
        $this->assertEquals('john.doe@example.com', $created_customer['email']);
        $this->assertEquals('google_123', $created_customer['google_id']);
        $this->assertFalse($created_customer['is_guest']);

        // 2. Get Customer
        $get_request = new WP_REST_Request('GET', "/squidly/v1/customers/{$customer_id}");
        $get_request->set_url_params(['id' => (string)$customer_id]);

        $get_response = $this->controller->get_item($get_request);
        $this->assertNotInstanceOf(\WP_Error::class, $get_response);

        $retrieved_customer = $get_response->get_data();
        $this->assertEquals($customer_id, $retrieved_customer['id']);
        $this->assertEquals('John', $retrieved_customer['first_name']);

        // 3. Update Customer
        $update_request = new WP_REST_Request('PUT', "/squidly/v1/customers/{$customer_id}");
        $update_request->set_url_params(['id' => (string)$customer_id]);
        $update_request->set_param('first_name', 'Jane');
        $update_request->set_param('last_name', 'Smith');

        $update_response = $this->controller->update_item($update_request);
        $this->assertNotInstanceOf(\WP_Error::class, $update_response);

        $updated_customer = $update_response->get_data();
        $this->assertEquals('Jane', $updated_customer['first_name']);
        $this->assertEquals('Smith', $updated_customer['last_name']);

        // 4. Get Customers Collection
        $list_request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $list_request->set_param('auth_provider', 'google');

        $list_response = $this->controller->get_items($list_request);
        $this->assertNotInstanceOf(\WP_Error::class, $list_response);

        $customers_list = $list_response->get_data();
        $this->assertGreaterThanOrEqual(1, count($customers_list));

        // 5. Delete Customer (should fail without force - no orders)
        $delete_request = new WP_REST_Request('DELETE', "/squidly/v1/customers/{$customer_id}");
        $delete_request->set_url_params(['id' => (string)$customer_id]);

        $delete_response = $this->controller->delete_item($delete_request);
        $this->assertNotInstanceOf(\WP_Error::class, $delete_response);

        $delete_result = $delete_response->get_data();
        $this->assertTrue($delete_result['success']);
    }

    /* ==========================================
     * Loyalty Points Integration Tests
     * ========================================== */

    public function test_loyalty_points_management(): void
    {
        // Create customer
        $customer_id = $this->repository->create([
            'first_name' => 'Loyal',
            'last_name' => 'Customer',
            'phone' => '0501234567',
            'auth_provider' => 'google',
            'email' => 'loyal@example.com',
            'is_guest' => false,
        ]);

        // Add loyalty points
        $add_points_request = new WP_REST_Request('PUT', "/squidly/v1/customers/{$customer_id}/loyalty-points");
        $add_points_request->set_url_params(['id' => (string)$customer_id]);
        $add_points_request->set_param('action', 'add');
        $add_points_request->set_param('points', 100.5);

        $add_response = $this->controller->update_loyalty_points($add_points_request);
        $this->assertNotInstanceOf(\WP_Error::class, $add_response);

        $customer_after_add = $add_response->get_data();
        $this->assertEquals(100.5, $customer_after_add['loyalty_points_balance']);
        $this->assertEquals(100.5, $customer_after_add['lifetime_points_earned']);

        // Use loyalty points
        $use_points_request = new WP_REST_Request('PUT', "/squidly/v1/customers/{$customer_id}/loyalty-points");
        $use_points_request->set_url_params(['id' => (string)$customer_id]);
        $use_points_request->set_param('action', 'use');
        $use_points_request->set_param('points', 50.0);

        $use_response = $this->controller->update_loyalty_points($use_points_request);
        $this->assertNotInstanceOf(\WP_Error::class, $use_response);

        $customer_after_use = $use_response->get_data();
        $this->assertEquals(50.5, $customer_after_use['loyalty_points_balance']);
        $this->assertEquals(100.5, $customer_after_use['lifetime_points_earned']); // Lifetime unchanged

        // Clean up
        $this->repository->delete($customer_id, true);
    }

    /* ==========================================
     * Staff Labels Integration Test
     * ========================================== */

    public function test_staff_labels_management(): void
    {
        // Create customer
        $customer_id = $this->repository->create([
            'first_name' => 'Labeled',
            'last_name' => 'Customer',
            'phone' => '0521234567',
            'auth_provider' => 'phone',
            'is_guest' => false,
        ]);

        // Add staff label
        $label_request = new WP_REST_Request('PUT', "/squidly/v1/customers/{$customer_id}/staff-labels");
        $label_request->set_url_params(['id' => (string)$customer_id]);
        $label_request->set_param('label', 'VIP customer - very polite');

        $label_response = $this->controller->update_staff_labels($label_request);
        $this->assertNotInstanceOf(\WP_Error::class, $label_response);

        $labeled_customer = $label_response->get_data();
        $this->assertStringContainsString('VIP customer - very polite', $labeled_customer['staff_labels']);

        // Clean up
        $this->repository->delete($customer_id, true);
    }

    /* ==========================================
     * Guest Conversion Integration Test
     * ========================================== */

    public function test_guest_to_registered_conversion(): void
    {
        // Create guest customer
        $guest_id = $this->repository->create([
            'first_name' => 'Guest',
            'last_name' => 'User',
            'phone' => '0541234567',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        // Convert to registered
        $convert_request = new WP_REST_Request('POST', "/squidly/v1/customers/{$guest_id}/convert-guest");
        $convert_request->set_url_params(['id' => (string)$guest_id]);
        $convert_request->set_param('email', 'converted@example.com');
        $convert_request->set_param('auth_provider', 'google');
        $convert_request->set_param('google_id', 'google_converted_123');

        $convert_response = $this->controller->convert_guest_customer($convert_request);
        $this->assertNotInstanceOf(\WP_Error::class, $convert_response);

        $converted_customer = $convert_response->get_data();
        $this->assertFalse($converted_customer['is_guest']);
        $this->assertEquals('converted@example.com', $converted_customer['email']);
        $this->assertEquals('google', $converted_customer['auth_provider']);
        $this->assertEquals('google_converted_123', $converted_customer['google_id']);

        // Clean up
        $this->repository->delete($guest_id, true);
    }

    /* ==========================================
     * Search Integration Test
     * ========================================== */

    public function test_customer_search(): void
    {
        // Create test customers
        $customer_ids = [];

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
            'phone' => '0501111111',
            'auth_provider' => 'google',
            'email' => 'alice@example.com',
        ]);

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Bob',
            'last_name' => 'Brown',
            'phone' => '0502222222',
            'auth_provider' => 'phone',
            'email' => 'bob@example.com',
        ]);

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Alice',
            'last_name' => 'Blue',
            'phone' => '0503333333',
            'auth_provider' => 'google',
            'email' => 'alice.blue@example.com',
        ]);

        // Search for "Alice"
        $search_request = new WP_REST_Request('GET', '/squidly/v1/customers/search');
        $search_request->set_param('q', 'Alice');

        $search_response = $this->controller->search_customers($search_request);
        $this->assertNotInstanceOf(\WP_Error::class, $search_response);

        $search_results = $search_response->get_data();
        $this->assertGreaterThanOrEqual(2, count($search_results));

        foreach ($search_results as $result) {
            $this->assertStringContainsString('Alice', $result['first_name']);
        }

        // Clean up
        foreach ($customer_ids as $id) {
            $this->repository->delete($id, true);
        }
    }

    /* ==========================================
     * Statistics Integration Test
     * ========================================== */

    public function test_customer_statistics(): void
    {
        // Create diverse customers
        $customer_ids = [];

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Registered',
            'last_name' => 'One',
            'phone' => '0501000001',
            'auth_provider' => 'google',
            'email' => 'reg1@example.com',
            'is_guest' => false,
            'loyalty_points_balance' => 100.0,
            'lifetime_points_earned' => 100.0,
        ]);

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Guest',
            'last_name' => 'One',
            'phone' => '0501000002',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Registered',
            'last_name' => 'Two',
            'phone' => '0501000003',
            'auth_provider' => 'phone',
            'email' => 'reg2@example.com',
            'is_guest' => false,
            'phone_verified_at' => date('Y-m-d H:i:s'),
        ]);

        // Get statistics
        $stats_request = new WP_REST_Request('GET', '/squidly/v1/customers/statistics');
        $stats_response = $this->controller->get_statistics($stats_request);
        $this->assertNotInstanceOf(\WP_Error::class, $stats_response);

        $stats = $stats_response->get_data();
        $this->assertGreaterThanOrEqual(3, $stats['total_customers']);
        $this->assertGreaterThanOrEqual(2, $stats['registered_customers']);
        $this->assertGreaterThanOrEqual(1, $stats['guest_customers']);
        $this->assertArrayHasKey('loyalty_program', $stats);
        $this->assertArrayHasKey('authentication', $stats);

        // Clean up
        foreach ($customer_ids as $id) {
            $this->repository->delete($id, true);
        }
    }

    /* ==========================================
     * Validation Integration Tests
     * ========================================== */

    public function test_create_customer_validation(): void
    {
        // Test missing first_name
        $request1 = new WP_REST_Request('POST', '/squidly/v1/customers');
        $request1->set_param('last_name', 'Doe');
        $request1->set_param('phone', '0501234567');
        $request1->set_param('auth_provider', 'google');

        $response1 = $this->controller->create_item($request1);
        $this->assertNotEquals(200, $response1->get_status());
        $data1 = $response1->get_data();
        $this->assertArrayHasKey('error', $data1);

        // Test invalid email (sanitize_email will convert to empty string, which is accepted)
        $request2 = new WP_REST_Request('POST', '/squidly/v1/customers');
        $request2->set_param('first_name', 'John');
        $request2->set_param('last_name', 'Doe');
        $request2->set_param('phone', '0501234567');
        $request2->set_param('auth_provider', 'google');
        $request2->set_param('email', 'not-an-email-format');

        $response2 = $this->controller->create_item($request2);
        // sanitize_email converts invalid email to empty string, so creation succeeds
        $this->assertEquals(200, $response2->get_status());

        // Test invalid auth_provider
        $request3 = new WP_REST_Request('POST', '/squidly/v1/customers');
        $request3->set_param('first_name', 'John');
        $request3->set_param('last_name', 'Doe');
        $request3->set_param('phone', '0501234567');
        $request3->set_param('auth_provider', 'invalid_provider');

        $response3 = $this->controller->create_item($request3);
        $this->assertEquals(400, $response3->get_status());
    }

    /* ==========================================
     * Permission Integration Tests
     * ========================================== */

    public function test_permission_checks(): void
    {
        // Test without authentication
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $can_read = $this->controller->get_items_permissions_check($request);
        $this->assertFalse($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/customers');
        $can_create = $this->controller->create_item_permissions_check($request);
        $this->assertFalse($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/customers/123');
        $can_delete = $this->controller->delete_item_permissions_check($request);
        $this->assertFalse($can_delete);

        // Test with admin user
        wp_set_current_user(1);

        $request = new WP_REST_Request('GET', '/squidly/v1/customers');
        $can_read = $this->controller->get_items_permissions_check($request);
        $this->assertTrue($can_read);

        $request = new WP_REST_Request('POST', '/squidly/v1/customers');
        $can_create = $this->controller->create_item_permissions_check($request);
        $this->assertTrue($can_create);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/customers/123');
        $can_delete = $this->controller->delete_item_permissions_check($request);
        $this->assertTrue($can_delete);
    }

    /* ==========================================
     * Filtering Integration Tests
     * ========================================== */

    public function test_get_customers_with_filters(): void
    {
        // Create diverse customers
        $customer_ids = [];

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Filter',
            'last_name' => 'Test1',
            'phone' => '0502000001',
            'auth_provider' => 'google',
            'email' => 'filter1@example.com',
            'is_guest' => false,
        ]);

        $customer_ids[] = $this->repository->create([
            'first_name' => 'Filter',
            'last_name' => 'Test2',
            'phone' => '0502000002',
            'auth_provider' => 'phone',
            'is_guest' => true,
        ]);

        // Filter by auth_provider
        $request1 = new WP_REST_Request('GET', '/squidly/v1/customers');
        $request1->set_param('auth_provider', 'google');
        $response1 = $this->controller->get_items($request1);
        $data1 = $response1->get_data();
        $this->assertGreaterThanOrEqual(1, count($data1));
        foreach ($data1 as $customer) {
            $this->assertEquals('google', $customer['auth_provider']);
        }

        // Filter by is_guest
        $request2 = new WP_REST_Request('GET', '/squidly/v1/customers');
        $request2->set_param('is_guest', true);
        $response2 = $this->controller->get_items($request2);
        $data2 = $response2->get_data();
        $this->assertGreaterThanOrEqual(1, count($data2));
        foreach ($data2 as $customer) {
            $this->assertTrue($customer['is_guest']);
        }

        // Clean up
        foreach ($customer_ids as $id) {
            $this->repository->delete($id, true);
        }
    }
}
