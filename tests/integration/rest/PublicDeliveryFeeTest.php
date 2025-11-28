<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration\Rest;

use PublicOrderRestController;
use StoreBranchRepository;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tests for Public Delivery Fee Endpoint
 *
 * Tests the delivery fee calculation endpoint including:
 * - Basic fee calculation
 * - Free delivery threshold
 * - Branch validation
 * - Delivery availability check
 * - Response structure
 *
 * @covers \PublicOrderRestController::get_delivery_fee
 */
class PublicDeliveryFeeTest extends WP_UnitTestCase
{
    private PublicOrderRestController $controller;
    private StoreBranchRepository $branchRepo;

    private int $branch_with_delivery;
    private int $branch_without_delivery;
    private int $branch_with_threshold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new PublicOrderRestController();
        $this->branchRepo = new StoreBranchRepository();

        // Set up REST API
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
        $this->controller->register_routes();

        // Branch with delivery (flat fee: 15.0, no threshold)
        $this->branch_with_delivery = $this->branchRepo->create([
            'name' => 'Branch With Delivery',
            'phone' => '0501111111',
            'city' => 'Tel Aviv',
            'address' => '123 Test St',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => 'regular',
            'accessibility_list' => [],
            'delivery_enabled' => true,
            'delivery_base_fee' => 15.0,
            'delivery_free_threshold' => 0, // No free delivery
        ]);

        // Branch without delivery
        $this->branch_without_delivery = $this->branchRepo->create([
            'name' => 'Branch Without Delivery',
            'phone' => '0502222222',
            'city' => 'Jerusalem',
            'address' => '456 Test Ave',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => 'regular',
            'accessibility_list' => [],
            'delivery_enabled' => false,
        ]);

        // Branch with free delivery threshold
        $this->branch_with_threshold = $this->branchRepo->create([
            'name' => 'Branch With Threshold',
            'phone' => '0503333333',
            'city' => 'Haifa',
            'address' => '789 Test Blvd',
            'is_open' => true,
            'activity_times' => [],
            'kosher_type' => 'regular',
            'accessibility_list' => [],
            'delivery_enabled' => true,
            'delivery_base_fee' => 20.0,
            'delivery_free_threshold' => 100.0, // Free over 100
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->branch_with_delivery, $this->branch_without_delivery, $this->branch_with_threshold] as $id) {
            if (isset($id)) {
                try {
                    wp_delete_post($id, true);
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }

        parent::tearDown();
    }

    // ========================================================================
    // BASIC FEE CALCULATION TESTS
    // ========================================================================

    public function test_get_delivery_fee_returns_flat_fee(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);
        $request->set_param('address', '100 Customer St, Tel Aviv');
        $request->set_param('subtotal', 50.0);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertArrayHasKey('delivery_fee', $data);
        $this->assertEquals(15.0, $data['delivery_fee']);
        $this->assertArrayHasKey('is_deliverable', $data);
        $this->assertTrue($data['is_deliverable']);
        $this->assertArrayHasKey('branch_id', $data);
        $this->assertEquals($this->branch_with_delivery, $data['branch_id']);
    }

    public function test_get_delivery_fee_without_subtotal(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);
        $request->set_param('address', '200 Customer Ave');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Should still return fee even without subtotal
        $this->assertEquals(15.0, $data['delivery_fee']);
    }

    // ========================================================================
    // FREE DELIVERY THRESHOLD TESTS
    // ========================================================================

    public function test_free_delivery_when_above_threshold(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_threshold);
        $request->set_param('address', '300 Customer Rd, Haifa');
        $request->set_param('subtotal', 150.0); // Above 100 threshold

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        $this->assertEquals(0.0, $data['delivery_fee']);
        $this->assertTrue($data['is_free_delivery']);
        $this->assertEquals(100.0, $data['free_delivery_threshold']);
    }

    public function test_charged_delivery_when_at_threshold(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_threshold);
        $request->set_param('address', '400 Customer St');
        $request->set_param('subtotal', 100.0); // Exactly at threshold

        $response = rest_get_server()->dispatch($request);

        $data = $response->get_data();

        // At threshold should be free (>= not >)
        $this->assertEquals(0.0, $data['delivery_fee']);
        $this->assertTrue($data['is_free_delivery']);
    }

    public function test_charged_delivery_when_below_threshold(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_threshold);
        $request->set_param('address', '500 Customer Blvd');
        $request->set_param('subtotal', 50.0); // Below 100 threshold

        $response = rest_get_server()->dispatch($request);

        $data = $response->get_data();

        $this->assertEquals(20.0, $data['delivery_fee']);
        $this->assertFalse($data['is_free_delivery']);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_delivery_fee_fails_for_nonexistent_branch(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', 99999);
        $request->set_param('address', '600 Nowhere St');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('not found', $data['error']);
    }

    public function test_delivery_fee_fails_when_delivery_disabled(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_without_delivery);
        $request->set_param('address', '700 Pickup Only St');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Delivery not available', $data['error']);
    }

    public function test_delivery_fee_requires_branch_id(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('address', '800 Missing Branch St');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_delivery_fee_requires_address(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_delivery_fee_validates_branch_id_format(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', 'invalid');
        $request->set_param('address', '900 Test St');

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(400, $response->get_status());
    }

    // ========================================================================
    // RESPONSE STRUCTURE TESTS
    // ========================================================================

    public function test_delivery_fee_response_contains_required_fields(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);
        $request->set_param('address', '1000 Complete Response St');
        $request->set_param('subtotal', 75.0);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Verify all required response fields
        $this->assertArrayHasKey('delivery_fee', $data);
        $this->assertArrayHasKey('is_deliverable', $data);
        $this->assertArrayHasKey('branch_id', $data);
        $this->assertArrayHasKey('free_delivery_threshold', $data);
        $this->assertArrayHasKey('is_free_delivery', $data);

        // Verify types
        $this->assertIsFloat($data['delivery_fee']);
        $this->assertIsBool($data['is_deliverable']);
        $this->assertIsInt($data['branch_id']);
        $this->assertIsNumeric($data['free_delivery_threshold']);
        $this->assertIsBool($data['is_free_delivery']);
    }

    // ========================================================================
    // EDGE CASE TESTS
    // ========================================================================

    public function test_delivery_fee_with_zero_subtotal(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_threshold);
        $request->set_param('address', '1100 Empty Cart St');
        $request->set_param('subtotal', 0);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // With zero subtotal, should charge fee
        $this->assertEquals(20.0, $data['delivery_fee']);
        $this->assertFalse($data['is_free_delivery']);
    }

    public function test_delivery_fee_with_negative_subtotal(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);
        $request->set_param('address', '1200 Invalid Subtotal St');
        $request->set_param('subtotal', -50.0);

        $response = rest_get_server()->dispatch($request);

        // Should still return fee (service doesn't validate negative subtotals)
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(15.0, $data['delivery_fee']);
    }

    public function test_delivery_fee_with_very_large_subtotal(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_threshold);
        $request->set_param('address', '1300 Big Order St');
        $request->set_param('subtotal', 10000.0);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();

        // Large order should get free delivery
        $this->assertEquals(0.0, $data['delivery_fee']);
        $this->assertTrue($data['is_free_delivery']);
    }

    public function test_delivery_fee_with_special_characters_in_address(): void
    {
        $request = new WP_REST_Request('GET', '/squidly/v1/public/delivery-fee');
        $request->set_param('branch_id', $this->branch_with_delivery);
        $request->set_param('address', "123 O'Brien St, Apt #5, Tel Aviv-Yafo");

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals(15.0, $data['delivery_fee']);
    }
}
