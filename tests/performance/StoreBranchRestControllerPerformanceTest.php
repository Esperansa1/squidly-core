<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Performance;

use StoreBranchRestController;
use StoreBranchRepository;
use ProductRepository;
use IngredientRepository;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * Performance tests for StoreBranchRestController.
 * Tests API performance under various load scenarios.
 *
 * @covers StoreBranchRestController
 * @group performance
 */
class StoreBranchRestControllerPerformanceTest extends WP_UnitTestCase
{
    private StoreBranchRestController $controller;
    private StoreBranchRepository $repository;
    private ProductRepository $productRepository;
    private IngredientRepository $ingredientRepository;
    private int $admin_user_id;
    private array $test_branch_ids = [];
    private array $test_product_ids = [];
    private array $test_ingredient_ids = [];

    public function set_up(): void
    {
        parent::set_up();

        $this->controller = new StoreBranchRestController();
        $this->repository = new StoreBranchRepository();
        $this->productRepository = new ProductRepository();
        $this->ingredientRepository = new IngredientRepository();

        // Create admin user
        $this->admin_user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->admin_user_id);

        // Suppress WordPress notices for performance tests
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
    }

    public function tear_down(): void
    {
        // Clean up test data
        foreach ($this->test_branch_ids as $id) {
            $this->repository->delete($id, true);
        }
        foreach ($this->test_product_ids as $id) {
            $this->productRepository->delete($id);
        }
        foreach ($this->test_ingredient_ids as $id) {
            $this->ingredientRepository->delete($id);
        }

        parent::tear_down();
    }

    private function createRequest(string $method = 'GET', array $params = []): WP_REST_Request
    {
        $request = new WP_REST_Request($method);
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    private function measureExecutionTime(callable $function): array
    {
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        $result = $function();

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        return [
            'result' => $result,
            'execution_time' => $end_time - $start_time,
            'memory_used' => $end_memory - $start_memory,
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }

    /* =====================================================================
     *  LARGE DATASET TESTS
     * ===================================================================*/

    public function testGetItemsPerformanceWithLargeDataset(): void
    {
        // Create 100 test branches
        $this->createLargeDataset(100, 20, 30);

        $metrics = $this->measureExecutionTime(function() {
            $request = $this->createRequest();
            return $this->controller->get_items($request);
        });

        $response = $metrics['result'];
        $this->assertEquals(200, $response->get_status());

        // Performance assertions
        $this->assertLessThan(2.0, $metrics['execution_time'],
            "GET /branches should complete in under 2 seconds with 100 branches");

        $this->assertLessThan(50 * 1024 * 1024, $metrics['memory_used'],
            "GET /branches should use less than 50MB memory");

        echo "\nLarge Dataset Performance:\n";
        echo "- Execution time: " . round($metrics['execution_time'], 3) . "s\n";
        echo "- Memory used: " . round($metrics['memory_used'] / 1024 / 1024, 2) . "MB\n";
        echo "- Peak memory: " . round($metrics['peak_memory'] / 1024 / 1024, 2) . "MB\n";
    }

    public function testGetItemsWithFiltersPerformance(): void
    {
        // Create diverse dataset for filtering
        $this->createDiverseDataset(50);

        // Test city filter performance
        $metrics = $this->measureExecutionTime(function() {
            $request = $this->createRequest('GET', ['city' => 'Tel Aviv']);
            return $this->controller->get_items($request);
        });

        $this->assertLessThan(1.0, $metrics['execution_time'],
            "Filtered GET /branches should complete in under 1 second");

        // Test multiple filters performance
        $metrics = $this->measureExecutionTime(function() {
            $request = $this->createRequest('GET', [
                'city' => 'Tel Aviv',
                'is_open' => true,
                'kosher_type' => 'Kosher Dairy'
            ]);
            return $this->controller->get_items($request);
        });

        $this->assertLessThan(1.5, $metrics['execution_time'],
            "Multi-filtered GET /branches should complete in under 1.5 seconds");

        echo "\nFiltered Query Performance:\n";
        echo "- Single filter: " . round($metrics['execution_time'], 3) . "s\n";
    }

    /* =====================================================================
     *  CONCURRENT REQUEST SIMULATION
     * ===================================================================*/

    public function testConcurrentGetRequestsPerformance(): void
    {
        // Create moderate dataset
        $this->createLargeDataset(25, 10, 15);

        // Simulate 10 concurrent requests
        $concurrent_requests = 10;
        $total_time = 0;
        $responses = [];

        for ($i = 0; $i < $concurrent_requests; $i++) {
            $metrics = $this->measureExecutionTime(function() {
                $request = $this->createRequest();
                return $this->controller->get_items($request);
            });

            $responses[] = $metrics['result'];
            $total_time += $metrics['execution_time'];
        }

        $average_time = $total_time / $concurrent_requests;

        // All requests should succeed
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->get_status());
        }

        // Average response time should be reasonable
        $this->assertLessThan(1.0, $average_time,
            "Average response time for concurrent requests should be under 1 second");

        echo "\nConcurrent Requests Performance:\n";
        echo "- {$concurrent_requests} requests total time: " . round($total_time, 3) . "s\n";
        echo "- Average time per request: " . round($average_time, 3) . "s\n";
    }

    /* =====================================================================
     *  CRUD OPERATIONS PERFORMANCE
     * ===================================================================*/

    public function testCrudOperationsPerformance(): void
    {
        // Test create performance
        $create_metrics = $this->measureExecutionTime(function() {
            $request = $this->createRequest('POST', [
                'name' => 'Performance Test Branch',
                'phone' => '555-PERF',
                'city' => 'Performance City',
                'address' => 'Performance Address',
                'is_open' => true,
                'activity_times' => [
                    'SUNDAY' => ['09:00-17:00'],
                    'MONDAY' => ['09:00-17:00'],
                    'TUESDAY' => ['09:00-17:00'],
                    'WEDNESDAY' => ['09:00-17:00'],
                    'THURSDAY' => ['09:00-17:00'],
                    'FRIDAY' => ['09:00-17:00'],
                    'SATURDAY' => ['09:00-17:00']
                ],
                'kosher_type' => 'Kosher Dairy',
                'accessibility_list' => ['wheelchair_accessible', 'braille_menu']
            ]);
            return $this->controller->create_item($request);
        });

        $create_response = $create_metrics['result'];
        $this->assertEquals(200, $create_response->get_status());
        $branch_id = $create_response->get_data()['id'];
        $this->test_branch_ids[] = $branch_id;

        // Test read performance
        $read_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            $request = $this->createRequest('GET', ['id' => $branch_id]);
            return $this->controller->get_item($request);
        });

        $this->assertEquals(200, $read_metrics['result']->get_status());

        // Test update performance
        $update_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            $request = $this->createRequest('PUT', [
                'id' => $branch_id,
                'name' => 'Updated Performance Branch',
                'phone' => '555-UPDATED',
                'activity_times' => [
                    'SUNDAY' => ['10:00-18:00'],
                    'MONDAY' => ['10:00-18:00']
                ]
            ]);
            return $this->controller->update_item($request);
        });

        $this->assertEquals(200, $update_metrics['result']->get_status());

        // Test delete performance
        $delete_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            $request = $this->createRequest('DELETE', ['id' => $branch_id]);
            return $this->controller->delete_item($request);
        });

        $this->assertEquals(200, $delete_metrics['result']->get_status());

        // Remove from cleanup since it's deleted
        $this->test_branch_ids = array_filter(
            $this->test_branch_ids,
            fn($id) => $id !== $branch_id
        );

        // Performance assertions
        $this->assertLessThan(1.0, $create_metrics['execution_time'], "CREATE should be under 1 second");
        $this->assertLessThan(0.5, $read_metrics['execution_time'], "READ should be under 0.5 seconds");
        $this->assertLessThan(1.0, $update_metrics['execution_time'], "UPDATE should be under 1 second");
        $this->assertLessThan(0.5, $delete_metrics['execution_time'], "DELETE should be under 0.5 seconds");

        echo "\nCRUD Operations Performance:\n";
        echo "- CREATE: " . round($create_metrics['execution_time'], 3) . "s\n";
        echo "- READ: " . round($read_metrics['execution_time'], 3) . "s\n";
        echo "- UPDATE: " . round($update_metrics['execution_time'], 3) . "s\n";
        echo "- DELETE: " . round($delete_metrics['execution_time'], 3) . "s\n";
    }

    /* =====================================================================
     *  COMPLEX OPERATIONS PERFORMANCE
     * ===================================================================*/

    public function testProductIngredientManagementPerformance(): void
    {
        // Create test data
        $branch_id = $this->createTestBranch();
        $this->createProducts(10);
        $this->createIngredients(10);

        // Test adding multiple products
        $add_products_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            foreach ($this->test_product_ids as $product_id) {
                $request = $this->createRequest('POST', [
                    'id' => $branch_id,
                    'product_id' => $product_id,
                    'is_active' => true
                ]);
                $response = $this->controller->add_product($request);
                if ($response->get_status() !== 200) {
                    throw new \Exception("Failed to add product {$product_id}");
                }
            }
        });

        // Test adding multiple ingredients
        $add_ingredients_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            foreach ($this->test_ingredient_ids as $ingredient_id) {
                $request = $this->createRequest('POST', [
                    'id' => $branch_id,
                    'ingredient_id' => $ingredient_id,
                    'is_active' => true
                ]);
                $response = $this->controller->add_ingredient($request);
                if ($response->get_status() !== 200) {
                    throw new \Exception("Failed to add ingredient {$ingredient_id}");
                }
            }
        });

        // Test availability update performance
        $update_availability_metrics = $this->measureExecutionTime(function() use ($branch_id) {
            $product_availability = [];
            foreach ($this->test_product_ids as $i => $product_id) {
                $product_availability[$product_id] = $i % 2 === 0; // Alternate availability
            }

            $ingredient_availability = [];
            foreach ($this->test_ingredient_ids as $i => $ingredient_id) {
                $ingredient_availability[$ingredient_id] = $i % 2 === 1; // Alternate availability
            }

            $request = $this->createRequest('PUT', [
                'id' => $branch_id,
                'product_availability' => $product_availability,
                'ingredient_availability' => $ingredient_availability
            ]);
            return $this->controller->update_availability($request);
        });

        $this->assertEquals(200, $update_availability_metrics['result']->get_status());

        // Performance assertions
        $this->assertLessThan(5.0, $add_products_metrics['execution_time'],
            "Adding 10 products should be under 5 seconds");

        $this->assertLessThan(5.0, $add_ingredients_metrics['execution_time'],
            "Adding 10 ingredients should be under 5 seconds");

        $this->assertLessThan(2.0, $update_availability_metrics['execution_time'],
            "Updating availability should be under 2 seconds");

        echo "\nProduct/Ingredient Management Performance:\n";
        echo "- Add 10 products: " . round($add_products_metrics['execution_time'], 3) . "s\n";
        echo "- Add 10 ingredients: " . round($add_ingredients_metrics['execution_time'], 3) . "s\n";
        echo "- Update availability: " . round($update_availability_metrics['execution_time'], 3) . "s\n";
    }

    /* =====================================================================
     *  STRESS TESTS
     * ===================================================================*/

    public function testHighVolumeDataRetrieval(): void
    {
        // Create a very large dataset
        $this->createLargeDataset(200, 50, 75);

        // Test retrieving all branches
        $metrics = $this->measureExecutionTime(function() {
            $request = $this->createRequest();
            return $this->controller->get_items($request);
        });

        $response = $metrics['result'];
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertGreaterThan(200, count($data)); // Should include "All Branches" option

        // Should complete within reasonable time even with large dataset
        $this->assertLessThan(5.0, $metrics['execution_time'],
            "Large dataset retrieval should complete within 5 seconds");

        // Memory usage should be reasonable
        $this->assertLessThan(100 * 1024 * 1024, $metrics['memory_used'],
            "Memory usage should be under 100MB for large dataset");

        echo "\nHigh Volume Data Retrieval:\n";
        echo "- Dataset size: " . (count($data) - 1) . " branches\n";
        echo "- Execution time: " . round($metrics['execution_time'], 3) . "s\n";
        echo "- Memory used: " . round($metrics['memory_used'] / 1024 / 1024, 2) . "MB\n";
    }

    /* =====================================================================
     *  HELPER METHODS
     * ===================================================================*/

    private function createLargeDataset(int $branches, int $products, int $ingredients): void
    {
        // Create products
        $this->createProducts($products);

        // Create ingredients
        $this->createIngredients($ingredients);

        // Create branches with varying complexity
        for ($i = 1; $i <= $branches; $i++) {
            $branch_data = [
                'name' => "Test Branch {$i}",
                'phone' => sprintf('555-%04d', $i),
                'city' => $this->getCityName($i),
                'address' => "{$i} Test Street",
                'is_open' => $i % 4 !== 0, // 75% open
                'activity_times' => $this->getRandomActivityTimes(),
                'kosher_type' => $this->getRandomKosherType(),
                'accessibility_list' => $this->getRandomAccessibilityFeatures()
            ];

            $branch_id = $this->repository->create($branch_data);
            $this->test_branch_ids[] = $branch_id;

            // Add some products and ingredients to branches
            if ($i % 3 === 0) { // Every 3rd branch gets products/ingredients
                $selected_products = array_slice($this->test_product_ids, 0, min(5, count($this->test_product_ids)));
                $selected_ingredients = array_slice($this->test_ingredient_ids, 0, min(5, count($this->test_ingredient_ids)));

                foreach ($selected_products as $product_id) {
                    $this->repository->addProduct($branch_id, $product_id, random_int(0, 1) === 1);
                }

                foreach ($selected_ingredients as $ingredient_id) {
                    $this->repository->addIngredient($branch_id, $ingredient_id, random_int(0, 1) === 1);
                }
            }
        }
    }

    private function createDiverseDataset(int $count): void
    {
        $cities = ['Tel Aviv', 'Jerusalem', 'Haifa', 'Beer Sheva', 'Eilat'];
        $kosher_types = ['Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'];

        for ($i = 1; $i <= $count; $i++) {
            $branch_data = [
                'name' => "Diverse Branch {$i}",
                'phone' => sprintf('555-%04d', $i),
                'city' => $cities[($i - 1) % count($cities)],
                'address' => "{$i} Diverse Street",
                'is_open' => $i % 2 === 1,
                'kosher_type' => $kosher_types[($i - 1) % count($kosher_types)],
                'activity_times' => [],
                'accessibility_list' => []
            ];

            $branch_id = $this->repository->create($branch_data);
            $this->test_branch_ids[] = $branch_id;
        }
    }

    private function createTestBranch(): int
    {
        $branch_id = $this->repository->create([
            'name' => 'Performance Test Branch',
            'phone' => '555-PERF',
            'city' => 'Test City',
            'address' => 'Test Address',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => '',
            'accessibility_list' => []
        ]);

        $this->test_branch_ids[] = $branch_id;
        return $branch_id;
    }

    private function createProducts(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $product_id = $this->productRepository->create([
                'name' => "Performance Product {$i}",
                'price' => random_int(1000, 5000) / 100, // $10.00 - $50.00
                'description' => "Performance test product {$i}"
            ]);
            $this->test_product_ids[] = $product_id;
        }
    }

    private function createIngredients(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $ingredient_id = $this->ingredientRepository->create([
                'name' => "Performance Ingredient {$i}",
                'price' => random_int(100, 1000) / 100 // $1.00 - $10.00
            ]);
            $this->test_ingredient_ids[] = $ingredient_id;
        }
    }

    private function getCityName(int $index): string
    {
        $cities = [
            'Tel Aviv', 'Jerusalem', 'Haifa', 'Beer Sheva', 'Eilat',
            'Netanya', 'Ashdod', 'Petah Tikva', 'Holon', 'Rishon LeZion'
        ];
        return $cities[($index - 1) % count($cities)];
    }

    private function getRandomKosherType(): string
    {
        $types = ['', 'Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'];
        return $types[array_rand($types)];
    }

    private function getRandomAccessibilityFeatures(): array
    {
        $features = ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator'];
        $count = random_int(0, 2); // 0-2 features
        return array_slice($features, 0, $count);
    }

    private function getRandomActivityTimes(): array
    {
        $days = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
        $times = [];

        foreach ($days as $day) {
            if (random_int(0, 1) === 1) { // 50% chance for each day
                $times[$day] = ['09:00-17:00'];
            }
        }

        return $times;
    }
}