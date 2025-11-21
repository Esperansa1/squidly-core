<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Public REST API controller for products (no authentication required).
 *
 * Provides public access to product information including:
 * - Product details (name, description, price)
 * - Product groups/categories
 * - Ingredients and customization options
 * - Branch-based availability filtering
 */
class PublicProductRestController extends PublicRestController
{
    protected $rest_base = 'products';

    private ProductRepository $repository;
    private ProductGroupRepository $groupRepository;
    private StoreBranchRepository $branchRepository;

    public function __construct()
    {
        $this->repository = new ProductRepository();
        $this->groupRepository = new ProductGroupRepository();
        $this->branchRepository = new StoreBranchRepository();
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/public/products - List all products
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_collection_params(),
            ],
        ]);

        // GET /squidly/v1/public/products/{id} - Get single product
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ],
                ],
            ],
        ]);

        // GET /squidly/v1/public/products/categories - Get all product categories
        register_rest_route($this->namespace, '/' . $this->rest_base . '/categories', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_categories'],
                'permission_callback' => [$this, 'public_permission_callback'],
            ],
        ]);
    }

    /**
     * Get all products.
     *
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response Response object
     */
    public function get_items($request)
    {
        // Apply rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        try {
            $pagination = $this->get_pagination_params($request);
            $filters = [];

            // Optional filter: branch_id - only show products available at branch
            $branch_id = $request->get_param('branch_id');
            if ($branch_id) {
                $filters['branch_id'] = absint($branch_id);
            }

            // Optional filter: category - filter by product group
            $category = $request->get_param('category');
            if ($category) {
                $filters['category'] = absint($category);
            }

            // Optional filter: search - search by product name
            $search = $request->get_param('search');
            if ($search) {
                $filters['search'] = sanitize_text_field($search);
            }

            // Get all products
            $all_products = $this->repository->getAll();

            // Apply filters
            if (!empty($filters)) {
                $all_products = $this->filter_products($all_products, $filters);
            }

            $total = count($all_products);

            // Apply pagination
            $products = array_slice(
                $all_products,
                $pagination['offset'],
                $pagination['per_page']
            );

            // Convert to arrays and enrich with availability data
            $data = array_map(function($product) use ($filters) {
                $product_data = $product->toArray();

                // Add availability info if branch filter is applied
                if (isset($filters['branch_id'])) {
                    $branch = $this->branchRepository->get($filters['branch_id']);
                    if ($branch) {
                        $product_data['available_at_branch'] = $branch->isProductAvailable($product->id);
                    }
                }

                return $product_data;
            }, $products);

            $response = new WP_REST_Response($data, 200);
            $response->header('X-WP-Total', (string)$total);
            $response->header('X-WP-TotalPages', (string)ceil($total / $pagination['per_page']));

            return $response;

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to retrieve products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single product by ID.
     *
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response Response object
     */
    public function get_item($request)
    {
        // Apply rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        try {
            $id = (int) $request->get_param('id');
            $product = $this->repository->get($id);

            if (!$product) {
                return new WP_REST_Response([
                    'error' => 'Product not found'
                ], 404);
            }

            $product_data = $product->toArray();

            // Add availability info if branch_id is provided
            $branch_id = $request->get_param('branch_id');
            if ($branch_id) {
                $branch = $this->branchRepository->get(absint($branch_id));
                if ($branch) {
                    $product_data['available_at_branch'] = $branch->isProductAvailable($product->id);
                }
            }

            return new WP_REST_Response($product_data, 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to retrieve product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all product categories (product groups).
     *
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response Response object
     */
    public function get_categories($request)
    {
        // Apply rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        try {
            // Get all product groups (item_type = 'product')
            $groups = $this->groupRepository->findBy(['item_type' => 'product']);

            // Convert to simple array format
            $categories = array_map(function($group) {
                return [
                    'id'   => $group->id,
                    'name' => $group->name,
                ];
            }, $groups);

            return new WP_REST_Response($categories, 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to retrieve categories: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filter products based on criteria.
     *
     * @param array $products Array of Product objects
     * @param array $filters Filter criteria
     * @return array Filtered products
     */
    private function filter_products(array $products, array $filters): array
    {
        return array_values(array_filter($products, function($product) use ($filters) {
            // Filter by branch availability
            if (isset($filters['branch_id'])) {
                $branch = $this->branchRepository->get($filters['branch_id']);
                if ($branch && !$branch->isProductAvailable($product->id)) {
                    return false;
                }
            }

            // Filter by category (product group)
            if (isset($filters['category'])) {
                if (!in_array($filters['category'], $product->product_group_ids)) {
                    return false;
                }
            }

            // Filter by search term
            if (isset($filters['search'])) {
                $search_term = strtolower($filters['search']);
                $product_name = strtolower($product->name);
                if (strpos($product_name, $search_term) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Get collection parameters for products.
     *
     * @return array Collection parameters
     */
    public function get_collection_params(): array
    {
        return [
            'per_page' => [
                'type'              => 'integer',
                'default'           => 20,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'offset' => [
                'type'              => 'integer',
                'default'           => 0,
                'minimum'           => 0,
                'sanitize_callback' => 'absint',
            ],
            'branch_id' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'category' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}
