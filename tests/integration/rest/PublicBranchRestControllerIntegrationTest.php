<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicBranchRestController;
use StoreBranchRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for PublicBranchRestController
 *
 * Tests the public branch API including:
 * - Branch listing with pagination
 * - Branch detail retrieval
 * - City filtering
 * - Operating hours calculation
 *
 * @covers \PublicBranchRestController
 */
class PublicBranchRestControllerIntegrationTest extends WP_UnitTestCase
{
    private PublicBranchRestController $controller;
    private StoreBranchRepository $branchRepo;

    private int $branch1_id;
    private int $branch2_id;
    private int $branch3_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicBranchRestController();
        $this->branchRepo = new StoreBranchRepository();

        // Set up REST API
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
        $this->controller->register_routes();

        // Create test branches in different cities
        $this->branch1_id = $this->branchRepo->create([
            'name' => 'Tel Aviv Downtown',
            'phone' => '0501111111',
            'city' => 'Tel Aviv',
            'address' => '123 Rothschild Blvd',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['open' => '10:00', 'close' => '22:00'],
                'MONDAY' => ['open' => '10:00', 'close' => '22:00'],
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => ['wheelchair', 'parking'],
            'delivery_enabled' => true,
            'delivery_base_fee' => 15.0,
        ]);

        $this->branch2_id = $this->branchRepo->create([
            'name' => 'Jerusalem Center',
            'phone' => '0502222222',
            'city' => 'Jerusalem',
            'address' => '456 King George St',
            'is_open' => true,
            'activity_times' => [
                'SUNDAY' => ['open' => '09:00', 'close' => '23:00'],
            ],
            'kosher_type' => 'kosher',
            'accessibility_list' => ['wheelchair'],
            'delivery_enabled' => false,
        ]);

        $this->branch3_id = $this->branchRepo->create([
            'name' => 'Tel Aviv North',
            'phone' => '0503333333',
            'city' => 'Tel Aviv',
            'address' => '789 Dizengoff St',
            'is_open' => false,
            'activity_times' => [
                'MONDAY' => ['open' => '11:00', 'close' => '21:00'],
            ],
            'kosher_type' => 'regular',
            'accessibility_list' => [],
            'delivery_enabled' => true,
            'delivery_base_fee' => 20.0,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        foreach ([$this->branch1_id, $this->branch2_id, $this->branch3_id] as $branch_id) {
            if (isset($branch_id)) {
                try {
                    wp_delete_post($branch_id, true);
                } catch (\Exception $e) {
                    // Ignore errors during cleanup
                }
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // BRANCH LISTING TESTS
    // ========================================================================

    public function test_get_branches_returns_all_branches(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertCount(3, $data);
    }

    public function test_get_branches_with_city_filter(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');
        $request->set_param('city', 'Tel Aviv');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(2, $data); // Two Tel Aviv branches
        foreach ($data as $branch) {
            $this->assertEquals('Tel Aviv', $branch['city']);
        }
    }

    public function test_get_branches_pagination(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');
        $request->set_param('per_page', 2);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(2, $data);

        // Check pagination headers
        $headers = $response->get_headers();
        $this->assertArrayHasKey('X-WP-Total', $headers);
        $this->assertEquals(3, $headers['X-WP-Total']);
    }

    public function test_get_branches_with_offset(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');
        $request->set_param('per_page', 2);
        $request->set_param('offset', 2);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // With offset 2, should get 1 remaining branch
        $this->assertCount(1, $data);
    }

    // ========================================================================
    // BRANCH DETAIL TESTS
    // ========================================================================

    public function test_get_branch_by_id_returns_correct_branch(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch1_id}");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals($this->branch1_id, $data['id']);
        $this->assertEquals('Tel Aviv Downtown', $data['name']);
        $this->assertEquals('Tel Aviv', $data['city']);
        $this->assertEquals(true, $data['is_open']);
    }

    public function test_get_branch_by_invalid_id_returns_404(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches/999999');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ========================================================================
    // RESPONSE STRUCTURE TESTS
    // ========================================================================

    public function test_branch_response_contains_required_fields(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch1_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify required fields
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('city', $data);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('is_open', $data);
        $this->assertArrayHasKey('activity_times', $data);
        $this->assertArrayHasKey('kosher_type', $data);
        $this->assertArrayHasKey('accessibility_list', $data);
        $this->assertArrayHasKey('delivery_enabled', $data);

        // Verify types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['phone']);
        $this->assertIsString($data['city']);
        $this->assertIsBool($data['is_open']);
        $this->assertIsArray($data['activity_times']);
        $this->assertIsArray($data['accessibility_list']);
        $this->assertIsBool($data['delivery_enabled']);
    }

    public function test_branch_response_includes_delivery_configuration(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch1_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify delivery fields
        $this->assertArrayHasKey('delivery_enabled', $data);
        $this->assertArrayHasKey('delivery_base_fee', $data);
        $this->assertTrue($data['delivery_enabled']);
        $this->assertEquals(15.0, $data['delivery_base_fee']);
    }

    // ========================================================================
    // FILTERING TESTS
    // ========================================================================

    public function test_filter_by_nonexistent_city_returns_empty(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');
        $request->set_param('city', 'Nonexistent City');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertCount(0, $data);
    }

    public function test_branches_include_operating_status(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/branches');

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        foreach ($data as $branch) {
            $this->assertArrayHasKey('is_open', $branch);
            $this->assertIsBool($branch['is_open']);
        }
    }

    // ========================================================================
    // OPERATING HOURS TESTS
    // ========================================================================

    public function test_branch_includes_activity_times(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch1_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('activity_times', $data);
        $this->assertIsArray($data['activity_times']);
        $this->assertArrayHasKey('SUNDAY', $data['activity_times']);
        $this->assertArrayHasKey('open', $data['activity_times']['SUNDAY']);
        $this->assertArrayHasKey('close', $data['activity_times']['SUNDAY']);
        $this->assertEquals('10:00', $data['activity_times']['SUNDAY']['open']);
        $this->assertEquals('22:00', $data['activity_times']['SUNDAY']['close']);
    }

    // ========================================================================
    // KOSHER TYPE TESTS
    // ========================================================================

    public function test_branch_includes_kosher_type(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch2_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('kosher_type', $data);
        $this->assertEquals('kosher', $data['kosher_type']);
    }

    // ========================================================================
    // ACCESSIBILITY TESTS
    // ========================================================================

    public function test_branch_includes_accessibility_list(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch1_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('accessibility_list', $data);
        $this->assertIsArray($data['accessibility_list']);
        $this->assertContains('wheelchair', $data['accessibility_list']);
        $this->assertContains('parking', $data['accessibility_list']);
    }

    public function test_branch_with_empty_accessibility_list(): void
    {
        $request = new WP_REST_Request('GET', "/squidly/v1/public/branches/{$this->branch3_id}");

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('accessibility_list', $data);
        $this->assertIsArray($data['accessibility_list']);
        $this->assertCount(0, $data['accessibility_list']);
    }
}
