<?php

/**
 * Customer REST Controller E2E Tests
 *
 * Comprehensive end-to-end testing for Customer REST API endpoints
 * Tests all CRUD operations, security, validation, and special features
 */
class CustomerRestControllerE2ETest extends WP_UnitTestCase
{
    private CustomerRestController $controller;
    private CustomerRepository $customerRepository;
    private array $test_customer_ids = [];
    private int $admin_user_id;
    private int $regular_user_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new CustomerRestController();
        $this->customerRepository = new CustomerRepository();

        // Register the REST routes for testing using proper WordPress pattern
        add_action('rest_api_init', [$this->controller, 'register_routes']);
        do_action('rest_api_init');

        // Create test users
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        $this->regular_user_id = $this->factory->user->create([
            'role' => 'subscriber'
        ]);
    }

    public function tearDown(): void
    {
        // Clean up test data
        foreach ($this->test_customer_ids as $id) {
            $this->customerRepository->delete($id, true);
        }

        parent::tearDown();
    }

    private function make_rest_request(string $method, string $endpoint = '', array $data = [], bool $auto_authenticate = true): \WP_REST_Response
    {
        // Use WordPress internal REST server instead of external HTTP requests
        global $wp_rest_server;

        // Ensure REST server is initialized
        if (!$wp_rest_server) {
            $wp_rest_server = new \WP_REST_Server();
            do_action('rest_api_init');
        }

        // Build the route path
        $route = '/squidly/v1/customers' . $endpoint;

        // Create a proper WP_REST_Request
        $request = new \WP_REST_Request($method, $route);

        // Set authentication context (only if no specific user is already set and auto_authenticate is true)
        if ($auto_authenticate && !get_current_user_id()) {
            wp_set_current_user($this->admin_user_id);
        }

        // Set request data
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        }

        // Execute the request
        return $wp_rest_server->dispatch($request);
    }

    private function getResponseStatus(\WP_REST_Response $response): int
    {
        return $response->get_status();
    }

    private function getResponseData(\WP_REST_Response $response): array
    {
        return $response->get_data() ?? [];
    }

    /**
     * Helper to create test customer for various tests
     */
    private function createTestCustomer(bool $is_guest = false): int
    {
        $customer_id = $this->customerRepository->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '+972501234567',
            'auth_provider' => 'phone',
            'email' => 'test@example.com',
            'is_guest' => $is_guest,
            'allow_sms_notifications' => true,
            'allow_email_notifications' => false
        ]);

        $this->test_customer_ids[] = $customer_id;
        return $customer_id;
    }

    /* =====================================================================
     *  SECURITY AND AUTHENTICATION TESTS
     * ===================================================================*/

    public function testSecurityAndPermissionsWorkflow(): void
    {
        // Scenario: Testing security boundaries and permission enforcement through REST API

        // Step 1: Create customer as admin through REST API
        wp_set_current_user($this->admin_user_id);
        $admin_customer_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Security',
            'last_name' => 'Test',
            'phone' => '+972507654321',
            'auth_provider' => 'phone',
            'email' => 'security@example.com'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($admin_customer_response));
        $customer_data = $this->getResponseData($admin_customer_response);
        $customer_id = $customer_data['id'];
        $this->test_customer_ids[] = $customer_id;

        // Step 2: Test completely unauthenticated requests (should return 401)
        wp_set_current_user(0);

        $unauth_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_response),
            'Unauthenticated GET request should return 401');

        $unauth_post_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Unauthorized',
            'last_name' => 'Customer',
            'phone' => '+972507777777',
            'auth_provider' => 'phone'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_post_response),
            'Unauthenticated POST request should return 401');

        $unauth_get_item_response = $this->make_rest_request('GET', "/{$customer_id}", [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_item_response),
            'Unauthenticated GET item request should return 401');

        // Step 3: Test insufficient permissions (subscriber role should return 403)
        wp_set_current_user($this->regular_user_id);

        $subscriber_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_get_response),
            'Subscriber GET request should return 403');

        $subscriber_post_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Subscriber',
            'last_name' => 'Customer',
            'phone' => '+972508888888',
            'auth_provider' => 'phone'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_post_response),
            'Subscriber POST request should return 403');

        $subscriber_put_response = $this->make_rest_request('PUT', "/{$customer_id}", [
            'first_name' => 'Hacked Customer Name'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_put_response),
            'Subscriber PUT request should return 403');

        $subscriber_delete_response = $this->make_rest_request('DELETE', "/{$customer_id}", [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_delete_response),
            'Subscriber DELETE request should return 403');

        // Step 4: Verify admin can access all endpoints (should return 200 or appropriate business logic response)
        wp_set_current_user($this->admin_user_id);

        $admin_get_all_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, $this->getResponseStatus($admin_get_all_response),
            'Admin GET all customers should return 200');

        $admin_create_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Second Admin',
            'last_name' => 'Customer',
            'phone' => '+972509999999',
            'auth_provider' => 'google',
            'email' => 'second@example.com',
            'google_id' => 'google123'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_create_response),
            'Admin POST request should return 200');

        $second_customer_data = $this->getResponseData($admin_create_response);
        $second_customer_id = $second_customer_data['id'];
        $this->test_customer_ids[] = $second_customer_id;

        $admin_get_item_response = $this->make_rest_request('GET', "/{$customer_id}");
        $this->assertEquals(200, $this->getResponseStatus($admin_get_item_response),
            'Admin GET item request should return 200');

        $admin_put_response = $this->make_rest_request('PUT', "/{$customer_id}", [
            'first_name' => 'Updated Security'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_put_response),
            'Admin PUT request should return 200');

        echo "\n✓ Customer security and permissions workflow completed successfully\n";
    }

    /* =====================================================================
     *  CRUD OPERATIONS TESTS
     * ===================================================================*/

    public function testCustomerCRUDOperations(): void
    {
        // Scenario: Test complete CRUD lifecycle for customers

        wp_set_current_user($this->admin_user_id);

        // CREATE: Test customer creation with various auth providers
        $phone_customer_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Phone',
            'last_name' => 'Customer',
            'phone' => '+972501111111',
            'auth_provider' => 'phone',
            'email' => 'phone@example.com',
            'allow_sms_notifications' => true,
            'allow_email_notifications' => false
        ]);

        $this->assertEquals(200, $this->getResponseStatus($phone_customer_response));
        $phone_customer = $this->getResponseData($phone_customer_response);
        $this->test_customer_ids[] = $phone_customer['id'];

        $this->assertEquals('Phone', $phone_customer['first_name']);
        $this->assertEquals('Customer', $phone_customer['last_name']);
        $this->assertEquals('phone', $phone_customer['auth_provider']);
        $this->assertTrue($phone_customer['allow_sms_notifications']);
        $this->assertFalse($phone_customer['allow_email_notifications']);

        $google_customer_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Google',
            'last_name' => 'Customer',
            'phone' => '+972502222222',
            'auth_provider' => 'google',
            'email' => 'google@example.com',
            'google_id' => 'google_123456',
            'is_guest' => false
        ]);

        $this->assertEquals(200, $this->getResponseStatus($google_customer_response));
        $google_customer = $this->getResponseData($google_customer_response);
        $this->test_customer_ids[] = $google_customer['id'];

        $this->assertEquals('google', $google_customer['auth_provider']);
        $this->assertEquals('google_123456', $google_customer['google_id']);
        $this->assertFalse($google_customer['is_guest']);

        // READ: Test retrieving customers
        $get_all_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, $this->getResponseStatus($get_all_response));
        $all_customers = $this->getResponseData($get_all_response);
        $this->assertGreaterThanOrEqual(2, count($all_customers));

        $get_item_response = $this->make_rest_request('GET', '/' . $phone_customer['id']);
        $this->assertEquals(200, $this->getResponseStatus($get_item_response));
        $retrieved_customer = $this->getResponseData($get_item_response);
        $this->assertEquals($phone_customer['id'], $retrieved_customer['id']);

        // UPDATE: Test customer updates
        $update_response = $this->make_rest_request('PUT', '/' . $phone_customer['id'], [
            'first_name' => 'UpdatedPhone',
            'email' => 'updated-phone@example.com',
            'allow_email_notifications' => true,
            'staff_labels' => 'VIP customer'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($update_response));
        $updated_customer = $this->getResponseData($update_response);
        $this->assertEquals('UpdatedPhone', $updated_customer['first_name']);
        $this->assertEquals('updated-phone@example.com', $updated_customer['email']);
        $this->assertTrue($updated_customer['allow_email_notifications']);
        $this->assertEquals('VIP customer', $updated_customer['staff_labels']);

        // DELETE: Test customer deletion (should fail first due to business logic, then succeed with force)
        $delete_response = $this->make_rest_request('DELETE', '/' . $google_customer['id']);
        $this->assertEquals(200, $this->getResponseStatus($delete_response));

        // Verify deletion
        $get_deleted_response = $this->make_rest_request('GET', '/' . $google_customer['id']);
        $this->assertEquals(404, $this->getResponseStatus($get_deleted_response));

        echo "\n✓ Customer CRUD operations completed successfully\n";
    }

    /* =====================================================================
     *  VALIDATION AND ERROR HANDLING TESTS
     * ===================================================================*/

    public function testCustomerValidationAndErrorHandling(): void
    {
        // Scenario: Test validation rules and error responses

        wp_set_current_user($this->admin_user_id);

        // Test missing required fields
        $missing_name_response = $this->make_rest_request('POST', '', [
            'last_name' => 'Customer',
            'phone' => '+972503333333',
            'auth_provider' => 'phone'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($missing_name_response),
            'Missing first_name should return 400 validation error');

        $missing_phone_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'auth_provider' => 'phone'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($missing_phone_response),
            'Missing phone should return 400 validation error');

        // Test invalid email format
        $invalid_email_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '+972504444444',
            'auth_provider' => 'phone',
            'email' => 'invalid-email-format'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($invalid_email_response),
            'Invalid email format should return 400 validation error');

        // Test invalid auth_provider
        $invalid_auth_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '+972505555555',
            'auth_provider' => 'invalid_provider'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($invalid_auth_response),
            'Invalid auth_provider should return 400 validation error');

        // Test invalid phone format (should be handled by repository)
        $invalid_phone_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '123',
            'auth_provider' => 'phone'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($invalid_phone_response),
            'Invalid phone format should return 400 validation error');

        // Test successful creation with valid data
        $valid_customer_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Valid',
            'last_name' => 'Customer',
            'phone' => '+972506666666',
            'auth_provider' => 'google',
            'email' => 'valid@example.com',
            'google_id' => 'valid_google_id'
        ]);

        $this->assertEquals(200, $this->getResponseStatus($valid_customer_response));
        $customer_data = $this->getResponseData($valid_customer_response);
        $this->test_customer_ids[] = $customer_data['id'];

        // Test updating non-existent customer
        $update_nonexistent_response = $this->make_rest_request('PUT', '/99999', [
            'first_name' => 'Nonexistent'
        ]);
        $this->assertEquals(404, $this->getResponseStatus($update_nonexistent_response),
            'Updating non-existent customer should return 404');

        echo "\n✓ Customer validation and error handling completed successfully\n";
    }

    /* =====================================================================
     *  SPECIAL FEATURES TESTS
     * ===================================================================*/

    public function testCustomerSpecialFeatures(): void
    {
        // Scenario: Test loyalty points, staff labels, guest conversion, and search functionality

        wp_set_current_user($this->admin_user_id);

        // Create test customers
        $regular_customer_id = $this->createTestCustomer(false);
        $guest_customer_id = $this->createTestCustomer(true);

        // Test loyalty points management
        $add_points_response = $this->make_rest_request('PUT', "/{$regular_customer_id}/loyalty-points", [
            'action' => 'add',
            'points' => 100.50
        ]);
        $this->assertEquals(200, $this->getResponseStatus($add_points_response));
        $customer_with_points = $this->getResponseData($add_points_response);
        $this->assertEquals(100.50, $customer_with_points['loyalty_points_balance']);

        $use_points_response = $this->make_rest_request('PUT', "/{$regular_customer_id}/loyalty-points", [
            'action' => 'use',
            'points' => 25.25
        ]);
        $this->assertEquals(200, $this->getResponseStatus($use_points_response));
        $customer_after_use = $this->getResponseData($use_points_response);
        $this->assertEquals(75.25, $customer_after_use['loyalty_points_balance']);

        // Test staff labels
        $add_label_response = $this->make_rest_request('PUT', "/{$regular_customer_id}/staff-labels", [
            'label' => 'Frequent customer'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($add_label_response));
        $customer_with_label = $this->getResponseData($add_label_response);
        $this->assertStringContainsString('Frequent customer', $customer_with_label['staff_labels']);

        // Test guest customer conversion
        $convert_guest_response = $this->make_rest_request('POST', "/{$guest_customer_id}/convert-guest", [
            'email' => 'converted@example.com',
            'auth_provider' => 'google',
            'google_id' => 'converted_google_id'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($convert_guest_response));
        $converted_customer = $this->getResponseData($convert_guest_response);
        $this->assertFalse($converted_customer['is_guest']);
        $this->assertEquals('converted@example.com', $converted_customer['email']);
        $this->assertEquals('google', $converted_customer['auth_provider']);

        // Test customer search
        $search_response = $this->make_rest_request('GET', '/search', [
            'q' => 'Test',
            'limit' => 10
        ]);
        $this->assertEquals(200, $this->getResponseStatus($search_response));
        $search_results = $this->getResponseData($search_response);
        $this->assertGreaterThan(0, count($search_results));

        // Verify search results contain expected customers
        $found_names = array_map(fn($c) => $c['first_name'], $search_results);
        $this->assertContains('Test', $found_names);

        echo "\n✓ Customer special features completed successfully\n";
    }

    /* =====================================================================
     *  FILTERING AND STATISTICS TESTS
     * ===================================================================*/

    public function testCustomerFilteringAndStatistics(): void
    {
        // Scenario: Test filtering capabilities and statistics endpoint

        wp_set_current_user($this->admin_user_id);

        // Create test customers with different characteristics
        $phone_customer_id = $this->createTestCustomer(false);
        $guest_customer_id = $this->createTestCustomer(true);

        // Update one customer to have Google auth
        $this->make_rest_request('PUT', "/{$phone_customer_id}", [
            'auth_provider' => 'google',
            'google_id' => 'filter_test_google'
        ]);

        // Test filtering by auth_provider
        $google_filter_response = $this->make_rest_request('GET', '', [
            'auth_provider' => 'google'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($google_filter_response));
        $google_customers = $this->getResponseData($google_filter_response);

        foreach ($google_customers as $customer) {
            $this->assertEquals('google', $customer['auth_provider']);
        }

        // Test filtering by guest status
        $guest_filter_response = $this->make_rest_request('GET', '', [
            'is_guest' => true
        ]);
        $this->assertEquals(200, $this->getResponseStatus($guest_filter_response));
        $guest_customers = $this->getResponseData($guest_filter_response);

        foreach ($guest_customers as $customer) {
            $this->assertTrue($customer['is_guest']);
        }

        // Test filtering by active status
        $active_filter_response = $this->make_rest_request('GET', '', [
            'is_active' => true
        ]);
        $this->assertEquals(200, $this->getResponseStatus($active_filter_response));
        $active_customers = $this->getResponseData($active_filter_response);

        foreach ($active_customers as $customer) {
            $this->assertTrue($customer['is_active']);
        }

        // Test statistics endpoint
        $stats_response = $this->make_rest_request('GET', '/statistics');
        $this->assertEquals(200, $this->getResponseStatus($stats_response));
        $stats = $this->getResponseData($stats_response);

        // Verify statistics structure
        $this->assertArrayHasKey('total_customers', $stats);
        $this->assertArrayHasKey('registered_customers', $stats);
        $this->assertArrayHasKey('guest_customers', $stats);
        $this->assertArrayHasKey('active_customers', $stats);
        $this->assertArrayHasKey('authentication', $stats);
        $this->assertArrayHasKey('loyalty_program', $stats);
        $this->assertArrayHasKey('revenue', $stats);

        // Verify authentication breakdown
        $this->assertArrayHasKey('google', $stats['authentication']);
        $this->assertArrayHasKey('phone', $stats['authentication']);

        // Verify loyalty program stats
        $this->assertArrayHasKey('total_points_in_system', $stats['loyalty_program']);
        $this->assertArrayHasKey('customers_with_points', $stats['loyalty_program']);

        // Verify revenue stats
        $this->assertArrayHasKey('total_spent', $stats['revenue']);
        $this->assertArrayHasKey('total_orders', $stats['revenue']);

        echo "\n✓ Customer filtering and statistics completed successfully\n";
    }

    /* =====================================================================
     *  EDGE CASES AND BUSINESS LOGIC TESTS
     * ===================================================================*/

    public function testCustomerEdgeCasesAndBusinessLogic(): void
    {
        // Scenario: Test edge cases and complex business logic scenarios

        wp_set_current_user($this->admin_user_id);

        // Test creating customer with maximum allowed data
        $max_data_response = $this->make_rest_request('POST', '', [
            'first_name' => 'Maximum',
            'last_name' => 'Data Customer',
            'phone' => '+972507777777',
            'auth_provider' => 'google',
            'email' => 'max@example.com',
            'google_id' => 'max_google_id_12345',
            'addresses' => [
                [
                    'street' => '123 Test Street',
                    'city' => 'Tel Aviv',
                    'postal_code' => '12345',
                    'country' => 'Israel'
                ]
            ],
            'allow_sms_notifications' => true,
            'allow_email_notifications' => true,
            'is_guest' => false,
            'is_active' => true
        ]);

        $this->assertEquals(200, $this->getResponseStatus($max_data_response));
        $max_customer = $this->getResponseData($max_data_response);
        $this->test_customer_ids[] = $max_customer['id'];

        $this->assertEquals('Maximum', $max_customer['first_name']);
        $this->assertEquals('max@example.com', $max_customer['email']);
        $this->assertTrue($max_customer['allow_sms_notifications']);
        $this->assertTrue($max_customer['allow_email_notifications']);
        $this->assertFalse($max_customer['is_guest']);

        // Test loyalty points edge cases
        $zero_points_response = $this->make_rest_request('PUT', "/{$max_customer['id']}/loyalty-points", [
            'action' => 'use',
            'points' => 0.01
        ]);
        // Should fail because customer has no points
        $this->assertEquals(400, $this->getResponseStatus($zero_points_response));

        // Test invalid loyalty points action
        $invalid_action_response = $this->make_rest_request('PUT', "/{$max_customer['id']}/loyalty-points", [
            'action' => 'invalid_action',
            'points' => 10.0
        ]);
        $this->assertEquals(400, $this->getResponseStatus($invalid_action_response));

        // Test search with minimum query length
        $short_search_response = $this->make_rest_request('GET', '/search', [
            'q' => 'a' // Too short
        ]);
        $this->assertEquals(400, $this->getResponseStatus($short_search_response));

        // Test search with exact phone number
        $phone_search_response = $this->make_rest_request('GET', '/search', [
            'q' => '+972507777777'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($phone_search_response));
        $phone_results = $this->getResponseData($phone_search_response);
        $this->assertGreaterThan(0, count($phone_results));

        // Test converting non-guest customer (should fail)
        $convert_non_guest_response = $this->make_rest_request('POST', "/{$max_customer['id']}/convert-guest", [
            'email' => 'should-fail@example.com',
            'auth_provider' => 'phone'
        ]);
        $this->assertEquals(400, $this->getResponseStatus($convert_non_guest_response));

        echo "\n✓ Customer edge cases and business logic completed successfully\n";
    }
}