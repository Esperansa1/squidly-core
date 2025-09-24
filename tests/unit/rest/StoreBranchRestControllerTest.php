<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use StoreBranchRestController;
use StoreBranchRepository;
use StoreBranch;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use InvalidArgumentException;
use Exception;

// Override WordPress functions for unit testing
// We need to ensure this function is available and always returns true for unit tests

// Force define current_user_can to always return true for unit tests
function current_user_can($capability) {
    return true;
}

/**
 * Unit tests for StoreBranchRestController.
 * Tests the controller logic without database interactions.
 *
 * @covers StoreBranchRestController
 */
class StoreBranchRestControllerTest extends TestCase
{
    private StoreBranchRestController $controller;
    private MockObject $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock WordPress functions for testing
        // Try to override current_user_can if possible
        if (function_exists('runkit_function_redefine')) {
            runkit_function_redefine('current_user_can', '$capability', 'return true;');
        } elseif (function_exists('uopz_set_return')) {
            uopz_set_return('current_user_can', true);
        }

        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return $str;
            }
        }

        if (!function_exists('error_log')) {
            function error_log($message) {
                // No-op for tests
            }
        }

        // Create mock repository
        $this->mockRepository = $this->createMock(StoreBranchRepository::class);

        // Create controller instance
        $this->controller = new StoreBranchRestController();

        // Use reflection to inject mock repository
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockRepository);
    }

    private function createMockBranch(int $id = 1, array $override_data = []): StoreBranch
    {
        $default_data = [
            'id' => $id,
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'is_open' => true,
            'activity_times' => ['SUNDAY' => ['09:00-17:00']],
            'kosher_type' => 'Kosher Dairy',
            'accessibility_list' => ['wheelchair_accessible'],
            'products' => [],
            'ingredients' => [],
            'product_availability' => [],
            'ingredient_availability' => []
        ];

        $data = array_merge($default_data, $override_data);

        // Create a real StoreBranch object instead of a mock
        return new StoreBranch($data);
    }

    private function createRequest(string $method = 'GET', array $params = []): WP_REST_Request
    {
        $request = new WP_REST_Request($method);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    /* =====================================================================
     *  PERMISSION TESTS
     * ===================================================================*/

    public function testPermissionChecksReturnTrue(): void
    {
        // Set up admin user for permission checks
        if (function_exists('wp_set_current_user')) {
            // Create admin user if WordPress is available
            if (function_exists('wp_insert_user')) {
                $admin_user_id = wp_insert_user([
                    'user_login' => 'test_admin',
                    'user_email' => 'admin@test.com',
                    'user_pass' => 'test_password',
                    'role' => 'administrator'
                ]);
                wp_set_current_user($admin_user_id);
            }
        }

        $request = $this->createRequest();

        // Test permission checks - should return true for admin user
        $this->assertTrue($this->controller->get_items_permissions_check($request));
        $this->assertTrue($this->controller->get_item_permissions_check($request));
        $this->assertTrue($this->controller->create_item_permissions_check($request));
        $this->assertTrue($this->controller->update_item_permissions_check($request));
        $this->assertTrue($this->controller->delete_item_permissions_check($request));
    }

    /* =====================================================================
     *  GET ITEMS TESTS
     * ===================================================================*/

    public function testGetItemsWithEmptyResult(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);

        $request = $this->createRequest();
        $response = $this->controller->get_items($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertCount(1, $data); // Should contain "All Branches" option
        $this->assertEquals(0, $data[0]['id']);
        $this->assertEquals('כל הסניפים', $data[0]['name']);
    }

    public function testGetItemsWithBranches(): void
    {
        $mockBranch1 = $this->createMockBranch(1, [
            'name' => 'Branch 1',
            'phone' => '555-0001',
            'city' => 'City 1',
            'address' => 'Address 1',
            'is_open' => true
        ]);

        $mockBranch2 = $this->createMockBranch(2, [
            'name' => 'Branch 2',
            'phone' => '555-0002',
            'city' => 'City 2',
            'address' => 'Address 2',
            'is_open' => false
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([$mockBranch1, $mockBranch2]);

        $request = $this->createRequest();
        $response = $this->controller->get_items($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(3, $data); // 2 branches + "All Branches" option
    }

    public function testGetItemsWithCityFilter(): void
    {
        $mockBranch = $this->createMockBranch(1);

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['city' => 'Tel Aviv'])
            ->willReturn([$mockBranch]);

        $request = $this->createRequest('GET', ['city' => 'Tel Aviv']);
        $response = $this->controller->get_items($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertCount(1, $data); // No "All Branches" when filtered
    }

    public function testGetItemsWithMultipleFilters(): void
    {
        $expectedFilters = [
            'city' => 'Tel Aviv',
            'is_open' => true,
            'kosher_type' => 'Kosher Dairy'
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findBy')
            ->with($expectedFilters)
            ->willReturn([]);

        $request = $this->createRequest('GET', $expectedFilters);
        $response = $this->controller->get_items($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function testGetItemsHandlesException(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('getAll')
            ->willThrowException(new Exception('Database error'));

        $request = $this->createRequest();
        $response = $this->controller->get_items($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /* =====================================================================
     *  GET SINGLE ITEM TESTS
     * ===================================================================*/

    public function testGetItemSuccess(): void
    {
        $mockBranch = $this->createMockBranch(1, [
            'name' => 'Test Branch',
            'phone' => '555-0123',
            'city' => 'Test City',
            'address' => 'Test Address',
            'is_open' => true
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($mockBranch);

        $request = $this->createRequest('GET', ['id' => 1]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Test Branch', $data['name']);
    }

    public function testGetItemNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $request = $this->createRequest('GET', ['id' => 999]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Branch not found', $data['error']);
    }

    public function testGetItemHandlesException(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new Exception('Database error'));

        $request = $this->createRequest('GET', ['id' => 1]);
        $response = $this->controller->get_item($request);

        $this->assertEquals(500, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Failed to fetch branch', $data['error']);
    }

    /* =====================================================================
     *  CREATE ITEM TESTS
     * ===================================================================*/

    public function testCreateItemSuccess(): void
    {
        $requestData = [
            'name' => 'New Branch',
            'phone' => '555-9999',
            'city' => 'New City',
            'address' => 'New Address',
            'is_open' => true,
            'kosher_type' => 'Kosher Meat'
        ];

        $expectedData = [
            'name' => 'New Branch',
            'phone' => '555-9999',
            'city' => 'New City',
            'address' => 'New Address',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => 'Kosher Meat',
            'accessibility_list' => [],
            'products' => [],
            'ingredients' => [],
            'product_availability' => [],
            'ingredient_availability' => []
        ];

        $mockBranch = $this->createMockBranch(123, [
            'name' => 'New Branch',
            'phone' => '555-9999',
            'city' => 'New City',
            'address' => 'New Address',
            'is_open' => true,
            'kosher_type' => 'Kosher Meat'
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($expectedData)
            ->willReturn(123);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($mockBranch);

        $request = $this->createRequest('POST', $requestData);
        $response = $this->controller->create_item($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
        $this->assertEquals('New Branch', $data['name']);
    }

    public function testCreateItemWithActivityTimes(): void
    {
        $activityTimes = [
            'SUNDAY' => ['08:00-14:00', '18:00-23:00'],
            'MONDAY' => ['09:00-21:00']
        ];

        $requestData = [
            'name' => 'Branch with Hours',
            'activity_times' => $activityTimes
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($activityTimes) {
                return $data['activity_times'] === $activityTimes;
            }))
            ->willReturn(1);

        $mockBranch = $this->createMockBranch(1);
        $this->mockRepository->method('get')->willReturn($mockBranch);

        $request = $this->createRequest('POST', $requestData);
        $response = $this->controller->create_item($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function testCreateItemValidationFailure(): void
    {
        // With the new validation logic, the repository create method should NOT be called
        // when validation fails, as validation happens before the repository call
        $this->mockRepository
            ->expects($this->never())
            ->method('create');

        $request = $this->createRequest('POST', ['phone' => '555-0000']);
        $response = $this->controller->create_item($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertEquals('Name is required', $data['message']);
    }

    public function testCreateItemGeneralException(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new Exception('Database error'));

        $request = $this->createRequest('POST', ['name' => 'Test']);
        $response = $this->controller->create_item($request);

        $this->assertEquals(500, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Failed to create store branch', $data['error']);
    }

    /* =====================================================================
     *  UPDATE ITEM TESTS
     * ===================================================================*/

    public function testUpdateItemSuccess(): void
    {
        $updateData = [
            'name' => 'Updated Branch',
            'phone' => '555-8888'
        ];

        $mockBranch = $this->createMockBranch(1, [
            'name' => 'Updated Branch',
            'phone' => '555-8888',
            'city' => 'Test City',
            'address' => 'Test Address',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => [],
            'products' => [],
            'ingredients' => [],
            'product_availability' => [],
            'ingredient_availability' => []
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, $updateData)
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($mockBranch);

        $request = $this->createRequest('PUT', array_merge(['id' => 1], $updateData));
        $response = $this->controller->update_item($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Updated Branch', $data['name']);
        $this->assertEquals('555-8888', $data['phone']);
    }

    public function testUpdateItemNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $request = $this->createRequest('PUT', ['id' => 999, 'name' => 'Updated']);
        $response = $this->controller->update_item($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Store branch not found', $data['error']);
    }

    public function testUpdateItemValidationFailure(): void
    {
        // With the new validation logic, the repository update method should NOT be called
        // when validation fails, as validation happens before the repository call
        $this->mockRepository
            ->expects($this->never())
            ->method('update');

        $request = $this->createRequest('PUT', [
            'id' => 1,
            'activity_times' => ['INVALIDDAY' => ['09:00-17:00']]
        ]);
        $response = $this->controller->update_item($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Validation failed', $data['error']);
        $this->assertStringContainsString('Invalid day: INVALIDDAY', $data['message']);
    }

    /* =====================================================================
     *  DELETE ITEM TESTS
     * ===================================================================*/

    public function testDeleteItemSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(1, false)
            ->willReturn(true);

        $request = $this->createRequest('DELETE', ['id' => 1]);
        $response = $this->controller->delete_item($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('Store branch deleted successfully', $data['message']);
    }

    public function testDeleteItemWithForce(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(1, true)
            ->willReturn(true);

        $request = $this->createRequest('DELETE', ['id' => 1, 'force' => true]);
        $response = $this->controller->delete_item($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function testDeleteItemNotFound(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $request = $this->createRequest('DELETE', ['id' => 999]);
        $response = $this->controller->delete_item($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Store branch not found', $data['error']);
    }

    /* =====================================================================
     *  AVAILABILITY TESTS
     * ===================================================================*/

    public function testGetAvailabilitySuccess(): void
    {
        $mockBranch = $this->createMockBranch(1, [
            'product_availability' => [1 => true, 2 => false],
            'ingredient_availability' => [10 => true, 11 => false]
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn($mockBranch);

        $request = $this->createRequest('GET', ['id' => 1]);
        $response = $this->controller->get_availability($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(1, $data['branch_id']);
        $this->assertEquals([1 => true, 2 => false], $data['product_availability']);
        $this->assertEquals([10 => true, 11 => false], $data['ingredient_availability']);
    }

    public function testUpdateAvailabilitySuccess(): void
    {
        $newProductAvailability = [1 => false, 2 => true];
        $newIngredientAvailability = [10 => false, 11 => true];

        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(1, [
                'product_availability' => $newProductAvailability,
                'ingredient_availability' => $newIngredientAvailability
            ])
            ->willReturn(true);

        // Mock the get_availability call that happens after update
        $mockBranch = $this->createMockBranch(1, [
            'product_availability' => $newProductAvailability,
            'ingredient_availability' => $newIngredientAvailability
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockBranch);

        $request = $this->createRequest('PUT', [
            'id' => 1,
            'product_availability' => $newProductAvailability,
            'ingredient_availability' => $newIngredientAvailability
        ]);

        $response = $this->controller->update_availability($request);
        $this->assertEquals(200, $response->get_status());
    }

    /* =====================================================================
     *  PRODUCT MANAGEMENT TESTS
     * ===================================================================*/

    public function testAddProductSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('addProduct')
            ->with(1, 5, true);

        $request = $this->createRequest('POST', [
            'id' => 1,
            'product_id' => 5,
            'is_active' => true
        ]);

        $response = $this->controller->add_product($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['branch_id']);
        $this->assertEquals(5, $data['product_id']);
        $this->assertTrue($data['is_active']);
    }

    public function testAddProductDefaultActive(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('addProduct')
            ->with(1, 5, true); // Default should be true

        $request = $this->createRequest('POST', [
            'id' => 1,
            'product_id' => 5
            // is_active not specified
        ]);

        $response = $this->controller->add_product($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function testRemoveProductSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('removeProduct')
            ->with(1, 5);

        $request = $this->createRequest('DELETE', [
            'id' => 1,
            'product_id' => 5
        ]);

        $response = $this->controller->remove_product($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['branch_id']);
        $this->assertEquals(5, $data['product_id']);
    }

    /* =====================================================================
     *  INGREDIENT MANAGEMENT TESTS
     * ===================================================================*/

    public function testAddIngredientSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('addIngredient')
            ->with(1, 10, false);

        $request = $this->createRequest('POST', [
            'id' => 1,
            'ingredient_id' => 10,
            'is_active' => false
        ]);

        $response = $this->controller->add_ingredient($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['branch_id']);
        $this->assertEquals(10, $data['ingredient_id']);
        $this->assertFalse($data['is_active']);
    }

    public function testRemoveIngredientSuccess(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('removeIngredient')
            ->with(1, 10);

        $request = $this->createRequest('DELETE', [
            'id' => 1,
            'ingredient_id' => 10
        ]);

        $response = $this->controller->remove_ingredient($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['branch_id']);
        $this->assertEquals(10, $data['ingredient_id']);
    }

    /* =====================================================================
     *  SCHEMA TESTS
     * ===================================================================*/

    public function testGetCollectionParamsStructure(): void
    {
        $params = $this->controller->get_collection_params();

        // Test all expected parameters exist
        $expectedParams = [
            'city', 'city_like', 'is_open', 'kosher_type',
            'has_accessibility', 'has_product', 'has_ingredient', 'search'
        ];

        foreach ($expectedParams as $param) {
            $this->assertArrayHasKey($param, $params);
        }

        // Test enum values
        $this->assertEquals(
            ['Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'],
            $params['kosher_type']['enum']
        );

        $this->assertEquals(
            ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator'],
            $params['has_accessibility']['enum']
        );
    }

    public function testGetEndpointArgsStructure(): void
    {
        $createArgs = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);
        $updateArgs = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE);
        $deleteArgs = $this->controller->get_endpoint_args_for_item_schema(WP_REST_Server::DELETABLE);

        // Test create args
        $expectedCreateFields = [
            'name', 'phone', 'city', 'address', 'is_open',
            'activity_times', 'kosher_type', 'accessibility_list',
            'products', 'ingredients', 'product_availability', 'ingredient_availability'
        ];

        foreach ($expectedCreateFields as $field) {
            $this->assertArrayHasKey($field, $createArgs);
        }

        // Test required fields
        $this->assertTrue($createArgs['name']['required']);
        $this->assertFalse($createArgs['phone']['required']);

        // Test update args (name should not be required)
        $this->assertFalse($updateArgs['name']['required']);

        // Test delete args
        $this->assertArrayHasKey('force', $deleteArgs);
        $this->assertFalse($deleteArgs['force']['default']);
    }

    /* =====================================================================
     *  ERROR HANDLING TESTS
     * ===================================================================*/

    public function testExceptionHandlingInMethods(): void
    {
        // Test all methods handle exceptions gracefully
        $methods = [
            'add_product' => ['id' => 1, 'product_id' => 5],
            'remove_product' => ['id' => 1, 'product_id' => 5],
            'add_ingredient' => ['id' => 1, 'ingredient_id' => 10],
            'remove_ingredient' => ['id' => 1, 'ingredient_id' => 10],
            'get_availability' => ['id' => 1],
            'update_availability' => ['id' => 1, 'product_availability' => []]
        ];

        foreach ($methods as $method => $params) {
            // Setup repository to throw exception
            $this->mockRepository = $this->createMock(StoreBranchRepository::class);
            $this->mockRepository->method($this->anything())->willThrowException(new Exception('Test error'));

            // Inject mock into controller
            $reflection = new \ReflectionClass($this->controller);
            $property = $reflection->getProperty('repository');
            $property->setAccessible(true);
            $property->setValue($this->controller, $this->mockRepository);

            $request = $this->createRequest('POST', $params);
            $response = $this->controller->$method($request);

            $this->assertEquals(500, $response->get_status());
            $data = $response->get_data();
            $this->assertArrayHasKey('error', $data);
            $this->assertStringContainsString('Failed to', $data['error']);
        }
    }
}