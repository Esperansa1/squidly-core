<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit\Rest;

use ProductRestController;
use ProductRepository;
use Product;
use WP_REST_Request;
use WP_REST_Response;
use InvalidArgumentException;
use ResourceInUseException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ProductRestController
 */
class ProductRestControllerTest extends TestCase
{
    private ProductRestController $controller;
    private ProductRepository|MockObject $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(ProductRepository::class);
        $this->controller = new ProductRestController();

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($this->controller, $this->mockRepository);
    }

    public function test_get_products_returns_successful_response(): void
    {
        $products = [
            $this->createMockProduct(1, 'Pizza'),
            $this->createMockProduct(2, 'Burger')
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($products);

        $request = new WP_REST_Request('GET', '/squidly/v1/products');
        $response = $this->controller->get_items($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_get_product_returns_product_when_found(): void
    {
        $product = $this->createMockProduct(123, 'Test Product');

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($product);

        $this->mockRepository
            ->expects($this->once())
            ->method('getAvailabilityInfo')
            ->with(123)
            ->willReturn([
                'direct_availability' => [],
                'final_availability' => [],
                'group_restrictions' => []
            ]);

        $request = new WP_REST_Request('GET', '/squidly/v1/products/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->get_item($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_get_product_returns_404_when_not_found(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn(null);

        $request = new WP_REST_Request('GET', '/squidly/v1/products/999');
        $request->set_url_params(['id' => '999']);

        $response = $this->controller->get_item($request);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_create_product_returns_created_product(): void
    {
        $productData = [
            'name' => 'New Product',
            'price' => 25.99,
            'description' => 'Test description'
        ];

        $createdProduct = $this->createMockProduct(999, 'New Product');

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn(999);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(999)
            ->willReturn($createdProduct);

        $this->mockRepository
            ->expects($this->once())
            ->method('getAvailabilityInfo')
            ->with(999)
            ->willReturn([
                'direct_availability' => [],
                'final_availability' => [],
                'group_restrictions' => []
            ]);

        $request = new WP_REST_Request('POST', '/squidly/v1/products');
        foreach ($productData as $key => $value) {
            $request->set_param($key, $value);
        }

        $response = $this->controller->create_item($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_create_product_handles_validation_error(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willThrowException(new InvalidArgumentException('Name is required'));

        $request = new WP_REST_Request('POST', '/squidly/v1/products');
        $response = $this->controller->create_item($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_update_product_returns_updated_product(): void
    {
        $updatedProduct = $this->createMockProduct(123, 'Updated Product');

        $this->mockRepository
            ->expects($this->once())
            ->method('update')
            ->with(123, $this->anything())
            ->willReturn(true);

        $this->mockRepository
            ->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($updatedProduct);

        $this->mockRepository
            ->expects($this->once())
            ->method('getAvailabilityInfo')
            ->with(123)
            ->willReturn([
                'direct_availability' => [],
                'final_availability' => [],
                'group_restrictions' => []
            ]);

        $request = new WP_REST_Request('PUT', '/squidly/v1/products/123');
        $request->set_url_params(['id' => '123']);
        $request->set_param('name', 'Updated Product');

        $response = $this->controller->update_item($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_delete_product_returns_success(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with(123)
            ->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/squidly/v1/products/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->delete_item($request);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_delete_product_handles_resource_in_use_exception(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new ResourceInUseException(['Product is in use']));

        $request = new WP_REST_Request('DELETE', '/squidly/v1/products/123');
        $request->set_url_params(['id' => '123']);

        $response = $this->controller->delete_item($request);
        $this->assertEquals(409, $response->get_status());
    }

    private function createMockProduct(int $id, string $name): Product
    {
        $product = $this->createMock(Product::class);
        $product->id = $id;
        $product->name = $name;
        $product->price = 25.99;
        $product->discounted_price = null;
        $product->category = null;
        $product->description = 'Test description';
        $product->tags = [];
        $product->product_group_ids = [];
        return $product;
    }
}
