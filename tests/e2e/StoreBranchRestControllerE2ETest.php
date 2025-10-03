<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\E2E;

use StoreBranchRestController;
use StoreBranchRepository;
use ProductRepository;
use IngredientRepository;
use ProductGroupRepository;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * End-to-End tests for StoreBranchRestController.
 * Tests complete user workflows and real-world scenarios.
 *
 * @covers StoreBranchRestController
 * @group e2e
 */
class StoreBranchRestControllerE2ETest extends WP_UnitTestCase
{
    private StoreBranchRestController $controller;
    private StoreBranchRepository $repository;
    private ProductRepository $productRepository;
    private IngredientRepository $ingredientRepository;
    private ProductGroupRepository $groupRepository;
    private int $admin_user_id;
    private int $regular_user_id;
    private array $test_branch_ids = [];
    private array $test_product_ids = [];
    private array $test_ingredient_ids = [];
    private array $test_group_ids = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new StoreBranchRestController();
        $this->repository = new StoreBranchRepository();
        $this->productRepository = new ProductRepository();
        $this->ingredientRepository = new IngredientRepository();
        $this->groupRepository = new ProductGroupRepository();

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
        foreach ($this->test_branch_ids as $id) {
            $this->repository->delete($id, true);
        }
        foreach ($this->test_group_ids as $id) {
            $this->groupRepository->delete($id);
        }
        foreach ($this->test_product_ids as $id) {
            $this->productRepository->delete($id);
        }
        foreach ($this->test_ingredient_ids as $id) {
            $this->ingredientRepository->delete($id);
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
        $route = '/squidly/v1/branches' . $endpoint;

        // Create a proper WP_REST_Request
        $request = new \WP_REST_Request($method, $route);

        // Set authentication context (only if no specific user is already set and auto_authenticate is true)
        if ($auto_authenticate && !get_current_user_id()) {
            wp_set_current_user($this->admin_user_id);
        }

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        } else if (!empty($data)) {
            // Add body data for other methods
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }
        }

        // Dispatch the request through WordPress REST server
        $response = $wp_rest_server->dispatch($request);

        if (is_wp_error($response)) {
            throw new \Exception('REST request failed: ' . $response->get_error_message());
        }

        return $response;
    }

    /**
     * Helper to get response status from WP_REST_Response
     */
    private function getResponseStatus($response): int
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_status();
        }

        // Fallback for any remaining HTTP responses
        return wp_remote_retrieve_response_code($response);
    }

    /**
     * Helper to get response data from WP_REST_Response
     */
    private function getResponseData($response): array
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_data();
        }

        // Fallback for any remaining HTTP responses
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?? [];
    }

    /**
     * Legacy method - calls controller directly (bypasses REST API)
     * WARNING: This method bypasses WordPress permission system
     * Only kept for backward compatibility with existing tests
     */
    private function createAuthenticatedRequest(string $method = 'GET', array $params = []): \WP_REST_Request
    {
        wp_set_current_user($this->admin_user_id);

        $request = new \WP_REST_Request($method);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        return $request;
    }

    /* =====================================================================
     *  COMPLETE RESTAURANT SETUP WORKFLOW
     * ===================================================================*/

    public function testCompleteRestaurantChainSetup(): void
    {
        // Scenario: Restaurant chain owner setting up multiple branches

        // Step 1: Create main branch
        $main_branch_response = $this->controller->create_item(
            $this->createAuthenticatedRequest('POST', [
                'name' => 'Main Branch - Downtown',
                'phone' => '03-1234567',
                'city' => 'Tel Aviv',
                'address' => '123 Dizengoff Street',
                'is_open' => true,
                'activity_times' => [
                    'SUNDAY' => ['08:00-23:00'],
                    'MONDAY' => ['08:00-23:00'],
                    'TUESDAY' => ['08:00-23:00'],
                    'WEDNESDAY' => ['08:00-23:00'],
                    'THURSDAY' => ['08:00-24:00'],
                    'FRIDAY' => ['08:00-15:00'],
                    'SATURDAY' => ['19:00-24:00']
                ],
                'kosher_type' => 'Kosher Dairy',
                'accessibility_list' => ['wheelchair_accessible', 'elevator']
            ])
        );

        $this->assertEquals(200, $main_branch_response->get_status());
        $main_branch_data = $main_branch_response->get_data();
        $main_branch_id = $main_branch_data['id'];
        $this->test_branch_ids[] = $main_branch_id;

        // Step 2: Create secondary branch
        $secondary_branch_response = $this->controller->create_item(
            $this->createAuthenticatedRequest('POST', [
                'name' => 'Secondary Branch - Mall',
                'phone' => '03-7654321',
                'city' => 'Tel Aviv',
                'address' => '456 Mall Avenue',
                'is_open' => true,
                'activity_times' => [
                    'SUNDAY' => ['10:00-22:00'],
                    'MONDAY' => ['10:00-22:00'],
                    'TUESDAY' => ['10:00-22:00'],
                    'WEDNESDAY' => ['10:00-22:00'],
                    'THURSDAY' => ['10:00-22:00'],
                    'FRIDAY' => ['10:00-14:00'],
                    'SATURDAY' => ['20:00-24:00']
                ],
                'kosher_type' => 'Kosher Dairy',
                'accessibility_list' => ['wheelchair_accessible', 'braille_menu']
            ])
        );

        $this->assertEquals(200, $secondary_branch_response->get_status());
        $secondary_branch_data = $secondary_branch_response->get_data();
        $secondary_branch_id = $secondary_branch_data['id'];
        $this->test_branch_ids[] = $secondary_branch_id;

        // Step 3: Create products and ingredients
        $this->createTestProductsAndIngredients();

        // Step 4: Add products to main branch
        foreach ($this->test_product_ids as $i => $product_id) {
            $is_active = $i < 3; // First 3 products active, rest inactive
            $response = $this->controller->add_product(
                $this->createAuthenticatedRequest('POST', [
                    'id' => $main_branch_id,
                    'product_id' => $product_id,
                    'is_active' => $is_active
                ])
            );
            $this->assertEquals(200, $response->get_status());
        }

        // Step 5: Add ingredients to main branch
        foreach ($this->test_ingredient_ids as $ingredient_id) {
            $response = $this->controller->add_ingredient(
                $this->createAuthenticatedRequest('POST', [
                    'id' => $main_branch_id,
                    'ingredient_id' => $ingredient_id,
                    'is_active' => true
                ])
            );
            $this->assertEquals(200, $response->get_status());
        }

        // Step 6: Add selective products to secondary branch
        $selected_products = array_slice($this->test_product_ids, 0, 2); // Only first 2 products
        foreach ($selected_products as $product_id) {
            $response = $this->controller->add_product(
                $this->createAuthenticatedRequest('POST', [
                    'id' => $secondary_branch_id,
                    'product_id' => $product_id,
                    'is_active' => true
                ])
            );
            $this->assertEquals(200, $response->get_status());
        }

        // Step 7: Verify branch data integrity
        $main_branch_full = $this->controller->get_item(
            $this->createAuthenticatedRequest('GET', ['id' => $main_branch_id])
        );
        $main_data = $main_branch_full->get_data();

        $this->assertEquals('Main Branch - Downtown', $main_data['name']);
        $this->assertEquals('Tel Aviv', $main_data['city']);
        $this->assertCount(count($this->test_product_ids), $main_data['products']);
        $this->assertCount(count($this->test_ingredient_ids), $main_data['ingredients']);

        // Step 8: Test filtering by city
        $tel_aviv_branches = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['city' => 'Tel Aviv'])
        );
        $tel_aviv_data = $tel_aviv_branches->get_data();

        $this->assertCount(2, $tel_aviv_data); // Both branches in Tel Aviv
        $branch_names = array_column($tel_aviv_data, 'name');
        $this->assertContains('Main Branch - Downtown', $branch_names);
        $this->assertContains('Secondary Branch - Mall', $branch_names);

        echo "\n✓ Complete restaurant chain setup workflow completed successfully\n";
    }

    /* =====================================================================
     *  DAILY OPERATIONS WORKFLOW
     * ===================================================================*/

    public function testDailyOperationsWorkflow(): void
    {
        // Scenario: Daily restaurant operations - opening, updating availability, closing

        // Setup: Create branch and products
        $branch_id = $this->createTestBranch();
        $this->createTestProductsAndIngredients();

        // Add products to branch
        foreach ($this->test_product_ids as $product_id) {
            $this->controller->add_product(
                $this->createAuthenticatedRequest('POST', [
                    'id' => $branch_id,
                    'product_id' => $product_id,
                    'is_active' => true
                ])
            );
        }

        // Step 1: Open the branch for the day
        $open_response = $this->controller->update_item(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_id,
                'is_open' => true
            ])
        );
        $this->assertEquals(200, $open_response->get_status());
        $this->assertTrue($open_response->get_data()['is_open']);

        // Step 2: Mid-day - some products run out, update availability
        $product_availability = [];
        foreach ($this->test_product_ids as $i => $product_id) {
            $product_availability[$product_id] = $i % 2 === 0; // Even products available
        }

        $availability_response = $this->controller->update_availability(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_id,
                'product_availability' => $product_availability
            ])
        );
        $this->assertEquals(200, $availability_response->get_status());

        // Step 3: Verify availability was updated
        $availability_check = $this->controller->get_availability(
            $this->createAuthenticatedRequest('GET', ['id' => $branch_id])
        );
        $availability_data = $availability_check->get_data();

        foreach ($this->test_product_ids as $i => $product_id) {
            $expected = $i % 2 === 0;
            $this->assertEquals($expected, $availability_data['product_availability'][$product_id]);
        }

        // Step 4: End of day - close the branch
        $close_response = $this->controller->update_item(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_id,
                'is_open' => false
            ])
        );
        $this->assertEquals(200, $close_response->get_status());
        $this->assertFalse($close_response->get_data()['is_open']);

        // Step 5: Verify branch status in listing
        $all_branches = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['is_open' => false])
        );
        $closed_branches = $all_branches->get_data();

        $found_closed_branch = false;
        foreach ($closed_branches as $branch) {
            if ($branch['id'] === $branch_id) {
                $this->assertFalse($branch['is_open']);
                $found_closed_branch = true;
                break;
            }
        }
        $this->assertTrue($found_closed_branch, 'Closed branch should appear in closed branches filter');

        echo "\n✓ Daily operations workflow completed successfully\n";
    }

    /* =====================================================================
     *  ACCESSIBILITY COMPLIANCE WORKFLOW
     * ===================================================================*/

    public function testAccessibilityComplianceWorkflow(): void
    {
        // Scenario: Restaurant chain ensuring accessibility compliance across branches

        // Create branches with different accessibility levels
        $accessibility_data = [
            [
                'name' => 'Fully Accessible Branch',
                'accessibility_list' => ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator']
            ],
            [
                'name' => 'Partially Accessible Branch',
                'accessibility_list' => ['wheelchair_accessible', 'elevator']
            ],
            [
                'name' => 'Basic Branch',
                'accessibility_list' => []
            ]
        ];

        $branch_ids = [];
        foreach ($accessibility_data as $data) {
            $response = $this->controller->create_item(
                $this->createAuthenticatedRequest('POST', array_merge([
                    'phone' => '555-0000',
                    'city' => 'Accessibility City',
                    'address' => 'Test Address',
                    'is_open' => true
                ], $data))
            );

            $this->assertEquals(200, $response->get_status());
            $branch_id = $response->get_data()['id'];
            $branch_ids[] = $branch_id;
            $this->test_branch_ids[] = $branch_id;
        }

        // Test filtering by accessibility features
        $wheelchair_accessible = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['has_accessibility' => 'wheelchair_accessible'])
        );
        $wheelchair_data = $wheelchair_accessible->get_data();
        $this->assertCount(2, $wheelchair_data); // First two branches

        $braille_menu = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['has_accessibility' => 'braille_menu'])
        );
        $braille_data = $braille_menu->get_data();
        $this->assertCount(1, $braille_data); // Only fully accessible branch

        // Upgrade basic branch to include wheelchair accessibility
        $upgrade_response = $this->controller->update_item(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_ids[2],
                'accessibility_list' => ['wheelchair_accessible']
            ])
        );
        $this->assertEquals(200, $upgrade_response->get_status());

        // Verify upgrade
        $upgraded_check = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['has_accessibility' => 'wheelchair_accessible'])
        );
        $upgraded_data = $upgraded_check->get_data();
        $this->assertCount(3, $upgraded_data); // Now all three branches

        echo "\n✓ Accessibility compliance workflow completed successfully\n";
    }

    /* =====================================================================
     *  KOSHER MANAGEMENT WORKFLOW
     * ===================================================================*/

    public function testKosherManagementWorkflow(): void
    {
        // Scenario: Managing kosher certifications across branches

        $kosher_branches = [
            ['name' => 'Dairy Branch', 'kosher_type' => 'Kosher Dairy'],
            ['name' => 'Meat Branch', 'kosher_type' => 'Kosher Meat'],
            ['name' => 'Mehadrin Branch', 'kosher_type' => 'Kosher Mehadrin'],
            ['name' => 'Regular Branch', 'kosher_type' => '']
        ];

        $branch_ids = [];
        foreach ($kosher_branches as $data) {
            $response = $this->controller->create_item(
                $this->createAuthenticatedRequest('POST', array_merge([
                    'phone' => '555-0000',
                    'city' => 'Kosher City',
                    'address' => 'Test Address',
                    'is_open' => true
                ], $data))
            );

            $this->assertEquals(200, $response->get_status());
            $branch_id = $response->get_data()['id'];
            $branch_ids[] = $branch_id;
            $this->test_branch_ids[] = $branch_id;
        }

        // Test filtering by kosher type
        foreach (['Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'] as $kosher_type) {
            $filtered_response = $this->controller->get_items(
                $this->createAuthenticatedRequest('GET', ['kosher_type' => $kosher_type])
            );
            $filtered_data = $filtered_response->get_data();
            $this->assertCount(1, $filtered_data);
            $this->assertEquals($kosher_type, $filtered_data[0]['kosher_type']);
        }

        // Upgrade regular branch to kosher dairy
        $upgrade_response = $this->controller->update_item(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_ids[3],
                'kosher_type' => 'Kosher Dairy'
            ])
        );
        $this->assertEquals(200, $upgrade_response->get_status());

        // Verify we now have 2 dairy branches
        $dairy_branches = $this->controller->get_items(
            $this->createAuthenticatedRequest('GET', ['kosher_type' => 'Kosher Dairy'])
        );
        $dairy_data = $dairy_branches->get_data();
        $this->assertCount(2, $dairy_data);

        echo "\n✓ Kosher management workflow completed successfully\n";
    }

    /* =====================================================================
     *  SECURITY AND PERMISSIONS WORKFLOW
     * ===================================================================*/

    public function testSecurityAndPermissionsWorkflow(): void
    {
        // Scenario: Testing security boundaries and permission enforcement through REST API

        // Step 1: Create branch as admin through REST API
        $admin_branch_response = $this->make_rest_request('POST', '', [
            'name' => 'Admin Created Branch',
            'phone' => '555-ADMIN',
            'city' => 'Admin City',
            'address' => 'Admin Address',
            'is_open' => true
        ]);

        $this->assertEquals(200, $this->getResponseStatus($admin_branch_response));
        $branch_data = $this->getResponseData($admin_branch_response);
        $branch_id = $branch_data['id'];
        $this->test_branch_ids[] = $branch_id;

        // Step 2: Test completely unauthenticated requests (should return 401)
        wp_set_current_user(0); // No user logged in

        $unauth_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_response),
            'Unauthenticated GET request should return 401');

        $unauth_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Unauthorized Branch',
            'phone' => '555-HACK',
            'city' => 'Hack City',
            'address' => 'Hack Address'
        ], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_post_response),
            'Unauthenticated POST request should return 401');

        $unauth_get_item_response = $this->make_rest_request('GET', "/{$branch_id}", [], false);
        $this->assertEquals(401, $this->getResponseStatus($unauth_get_item_response),
            'Unauthenticated GET item request should return 401');

        // Step 3: Test insufficient permissions (subscriber role - should return 403)
        wp_set_current_user($this->regular_user_id); // Subscriber user

        $subscriber_get_response = $this->make_rest_request('GET', '', [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_get_response),
            'Subscriber GET request should return 403');

        $subscriber_post_response = $this->make_rest_request('POST', '', [
            'name' => 'Subscriber Branch',
            'phone' => '555-SUB',
            'city' => 'Sub City',
            'address' => 'Sub Address'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_post_response),
            'Subscriber POST request should return 403');

        $subscriber_put_response = $this->make_rest_request('PUT', "/{$branch_id}", [
            'name' => 'Hacked Branch Name'
        ], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_put_response),
            'Subscriber PUT request should return 403');

        $subscriber_delete_response = $this->make_rest_request('DELETE', "/{$branch_id}", [], false);
        $this->assertEquals(403, $this->getResponseStatus($subscriber_delete_response),
            'Subscriber DELETE request should return 403');

        // Step 4: Verify admin can still access and modify (should return 200)
        wp_set_current_user($this->admin_user_id); // Admin user

        $admin_get_response = $this->make_rest_request('GET', "/{$branch_id}");
        $this->assertEquals(200, $this->getResponseStatus($admin_get_response),
            'Admin GET request should return 200');

        $admin_update_response = $this->make_rest_request('PUT', "/{$branch_id}", [
            'name' => 'Admin Updated Branch'
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_update_response),
            'Admin PUT request should return 200');

        $updated_data = $this->getResponseData($admin_update_response);
        $this->assertEquals('Admin Updated Branch', $updated_data['name'],
            'Branch name should be updated by admin');

        // Step 5: Test admin can perform all CRUD operations
        $admin_get_all_response = $this->make_rest_request('GET', '');
        $this->assertEquals(200, $this->getResponseStatus($admin_get_all_response),
            'Admin GET all branches should return 200');

        $admin_create_response = $this->make_rest_request('POST', '', [
            'name' => 'Second Admin Branch',
            'phone' => '555-ADMIN2',
            'city' => 'Admin City 2',
            'address' => 'Admin Address 2',
            'is_open' => true
        ]);
        $this->assertEquals(200, $this->getResponseStatus($admin_create_response),
            'Admin POST request should return 200');

        $second_branch_data = $this->getResponseData($admin_create_response);
        $this->test_branch_ids[] = $second_branch_data['id'];

        echo "\n✓ Security and permissions workflow completed successfully\n";
    }

    /* =====================================================================
     *  ERROR RECOVERY WORKFLOW
     * ===================================================================*/

    public function testErrorRecoveryWorkflow(): void
    {
        // Scenario: Testing system resilience and error recovery

        // Step 1: Try to create branch with invalid data
        $invalid_branch_response = $this->controller->create_item(
            $this->createAuthenticatedRequest('POST', [
                'name' => '', // Invalid empty name
                'phone' => '555-0000'
            ])
        );
        $this->assertEquals(400, $invalid_branch_response->get_status());

        // Step 2: Create valid branch
        $valid_branch_response = $this->controller->create_item(
            $this->createAuthenticatedRequest('POST', [
                'name' => 'Recovery Test Branch',
                'phone' => '555-RECOVERY',
                'city' => 'Recovery City',
                'address' => 'Recovery Address',
                'is_open' => true
            ])
        );
        $this->assertEquals(200, $valid_branch_response->get_status());
        $branch_id = $valid_branch_response->get_data()['id'];
        $this->test_branch_ids[] = $branch_id;

        // Step 3: Try to update with invalid data
        $invalid_update_response = $this->controller->update_item(
            $this->createAuthenticatedRequest('PUT', [
                'id' => $branch_id,
                'activity_times' => [
                    'INVALIDDAY' => ['09:00-17:00'] // Invalid day
                ]
            ])
        );
        $this->assertEquals(400, $invalid_update_response->get_status());

        // Step 4: Verify branch data wasn't corrupted
        $branch_check = $this->controller->get_item(
            $this->createAuthenticatedRequest('GET', ['id' => $branch_id])
        );
        $this->assertEquals(200, $branch_check->get_status());
        $this->assertEquals('Recovery Test Branch', $branch_check->get_data()['name']);

        // Step 5: Try to access non-existent branch
        $nonexistent_response = $this->controller->get_item(
            $this->createAuthenticatedRequest('GET', ['id' => 99999])
        );
        $this->assertEquals(404, $nonexistent_response->get_status());

        // Step 6: Try to delete non-existent branch
        $delete_nonexistent_response = $this->controller->delete_item(
            $this->createAuthenticatedRequest('DELETE', ['id' => 99999])
        );
        $this->assertEquals(404, $delete_nonexistent_response->get_status());

        // Step 7: Successful deletion
        $delete_response = $this->controller->delete_item(
            $this->createAuthenticatedRequest('DELETE', ['id' => $branch_id])
        );
        $this->assertEquals(200, $delete_response->get_status());

        // Remove from cleanup since it's deleted
        $this->test_branch_ids = array_filter(
            $this->test_branch_ids,
            fn($id) => $id !== $branch_id
        );

        echo "\n✓ Error recovery workflow completed successfully\n";
    }

    /* =====================================================================
     *  HELPER METHODS
     * ===================================================================*/

    private function createTestBranch(): int
    {
        $response = $this->controller->create_item(
            $this->createAuthenticatedRequest('POST', [
                'name' => 'E2E Test Branch',
                'phone' => '555-E2E',
                'city' => 'E2E City',
                'address' => 'E2E Address',
                'is_open' => true,
                'activity_times' => ['SUNDAY' => ['09:00-17:00']],
                'kosher_type' => 'Kosher Dairy',
                'accessibility_list' => ['wheelchair_accessible']
            ])
        );

        $this->assertEquals(200, $response->get_status());
        $branch_id = $response->get_data()['id'];
        $this->test_branch_ids[] = $branch_id;
        return $branch_id;
    }

    private function createTestProductsAndIngredients(): void
    {
        // Create test products
        $products = [
            ['name' => 'Classic Burger', 'price' => 35.00, 'description' => 'Our signature burger'],
            ['name' => 'Cheese Pizza', 'price' => 42.00, 'description' => 'Traditional cheese pizza'],
            ['name' => 'Caesar Salad', 'price' => 28.00, 'description' => 'Fresh caesar salad'],
            ['name' => 'Fish & Chips', 'price' => 38.00, 'description' => 'Fresh fish with fries'],
            ['name' => 'Pasta Alfredo', 'price' => 32.00, 'description' => 'Creamy pasta alfredo']
        ];

        foreach ($products as $product) {
            $product_id = $this->productRepository->create($product);
            $this->test_product_ids[] = $product_id;
        }

        // Create test ingredients
        $ingredients = [
            ['name' => 'Extra Cheese', 'price' => 5.00],
            ['name' => 'Mushrooms', 'price' => 3.00],
            ['name' => 'Olives', 'price' => 4.00],
            ['name' => 'Bacon', 'price' => 8.00],
            ['name' => 'Avocado', 'price' => 6.00]
        ];

        foreach ($ingredients as $ingredient) {
            $ingredient_id = $this->ingredientRepository->create($ingredient);
            $this->test_ingredient_ids[] = $ingredient_id;
        }
    }
}