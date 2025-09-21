<?php
declare(strict_types=1);

/**
 * Product REST API Controller
 *
 * Handles REST API endpoints for products management
 */
class ProductRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'products';

    private $repository;

    public function __construct()
    {
        $this->repository = new ProductRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/products
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::CREATABLE),
            ],
        ]);

        // GET/PUT/DELETE /squidly/v1/products/{id}
        $route_registered = register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Product ID', 'type' => 'integer']],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
                'args' => ['id' => ['description' => 'Product ID', 'type' => 'integer']],
            ],
        ]);
    }

    /**
     * Get all products
     */
    public function get_items($request)
    {
        try {
            $filters = [];

            if (!empty($request['branch_id'])) {
                $filters['branch_id'] = (int)$request['branch_id'];
            }

            if (!empty($request['search'])) {
                $filters['search'] = sanitize_text_field($request['search']);
            }

            if (isset($request['price_min'])) {
                $filters['price_min'] = (float)$request['price_min'];
            }

            if (isset($request['price_max'])) {
                $filters['price_max'] = (float)$request['price_max'];
            }

            if (!empty($request['category'])) {
                $filters['category'] = sanitize_text_field($request['category']);
            }

            $products = $this->repository->getAll($filters);

            $data = array_map(function($product) {
                return $this->prepare_item_for_response($product, new \WP_REST_Request())->get_data();
            }, $products);

            return new \WP_REST_Response(['data' => $data], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single product
     */
    public function get_item($request)
    {
        try {
            $id = (int)$request['id'];
            $product = $this->repository->get($id);

            if (!$product) {
                return new \WP_REST_Response([
                    'error' => 'Product not found'
                ], 404);
            }

            return $this->prepare_item_for_response($product, $request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new product
     */
    public function create_item($request)
    {
        try {
            $data = [
                'name' => sanitize_text_field($request['name']),
                'price' => (float)($request['price'] ?? 0),
                'description' => wp_kses_post($request['description'] ?? ''),
            ];

            // Handle optional fields
            if (isset($request['discounted_price'])) {
                $data['discounted_price'] = (float)$request['discounted_price'];
            }

            if (!empty($request['category'])) {
                $data['category'] = sanitize_text_field($request['category']);
            }

            if (!empty($request['tags']) && is_array($request['tags'])) {
                $data['tags'] = array_map('sanitize_text_field', $request['tags']);
            }

            if (!empty($request['product_group_ids']) && is_array($request['product_group_ids'])) {
                $data['product_group_ids'] = array_map('intval', $request['product_group_ids']);
            }

            // Handle branch availability if provided
            if (!empty($request['availability']) && is_array($request['availability'])) {
                $data['availability'] = $request['availability'];
            }

            $product_id = $this->repository->create($data);
            $product = $this->repository->get($product_id);

            return $this->prepare_item_for_response($product, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to create product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product
     */
    public function update_item($request)
    {
        try {
            $id = (int)$request['id'];
            $data = [];

            if (isset($request['name'])) {
                $data['name'] = sanitize_text_field($request['name']);
            }

            if (isset($request['price'])) {
                $data['price'] = (float)$request['price'];
            }

            if (isset($request['description'])) {
                $data['description'] = wp_kses_post($request['description']);
            }

            if (isset($request['discounted_price'])) {
                $data['discounted_price'] = (float)$request['discounted_price'];
            }

            if (isset($request['category'])) {
                $data['category'] = sanitize_text_field($request['category']);
            }

            if (isset($request['tags']) && is_array($request['tags'])) {
                $data['tags'] = array_map('sanitize_text_field', $request['tags']);
            }

            if (isset($request['product_group_ids']) && is_array($request['product_group_ids'])) {
                $data['product_group_ids'] = array_map('intval', $request['product_group_ids']);
            }

            // Handle branch availability updates if provided
            if (!empty($request['availability']) && is_array($request['availability'])) {
                $data['availability'] = $request['availability'];
            }

            $success = $this->repository->update($id, $data);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Product not found'
                ], 404);
            }

            $product = $this->repository->get($id);
            return $this->prepare_item_for_response($product, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function delete_item($request)
    {
        try {
            $id = (int)$request['id'];
            $success = $this->repository->delete($id);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Product not found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Product deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request)
    {
        try {
            // Get actual branch IDs from the database
            $branch_repository = new StoreBranchRepository();
            $branches = $branch_repository->getAll();

        // Get branch availability from post meta using actual branch IDs
        $availability = [];

        foreach ($branches as $branch) {
            $availability[$branch->id] = (bool) get_post_meta($item->id, '_branch_availability_' . $branch->id, true);
        }

        // If no branches exist, return empty availability
        if (empty($branches)) {
            $availability = [];
        }

            $data = [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $item->price,
                'discounted_price' => $item->discounted_price,
                'category' => $item->category,
                'tags' => $item->tags,
                'product_group_ids' => $item->product_group_ids,
                'availability' => $availability,
            ];

            return new \WP_REST_Response($data, 200);

        } catch (Exception $e) {
            // Return a minimal response to avoid breaking the API
            return new \WP_REST_Response([
                'error' => 'Failed to prepare response',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permission checks
     */
    public function get_items_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function get_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function create_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function update_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    public function delete_item_permissions_check($request)
    {
        return current_user_can('manage_options');
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params(): array
    {
        return [
            'branch_id' => [
                'description' => 'Filter by branch ID',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'description' => 'Search in product names and descriptions',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'price_min' => [
                'description' => 'Minimum price filter',
                'type' => 'number',
                'sanitize_callback' => function($param) {
                    return floatval($param);
                },
            ],
            'price_max' => [
                'description' => 'Maximum price filter',
                'type' => 'number',
                'sanitize_callback' => function($param) {
                    return floatval($param);
                },
            ],
            'category' => [
                'description' => 'Filter by product category',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Get endpoint args for item schema
     */
    public function get_endpoint_args_for_item_schema($method = \WP_REST_Server::CREATABLE): array
    {
        $args = [];

        if ($method === \WP_REST_Server::CREATABLE || $method === \WP_REST_Server::EDITABLE) {
            $args['name'] = [
                'description' => 'Product name',
                'type' => 'string',
                'required' => $method === \WP_REST_Server::CREATABLE,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    return !empty($param);
                },
            ];

            $args['price'] = [
                'description' => 'Product regular price',
                'type' => 'number',
                'required' => $method === \WP_REST_Server::CREATABLE,
                'default' => 0,
                'sanitize_callback' => function($param) {
                    return floatval($param);
                },
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 0;
                },
            ];

            $args['description'] = [
                'description' => 'Product description',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'wp_kses_post',
            ];

            $args['discounted_price'] = [
                'description' => 'Product discounted price',
                'type' => 'number',
                'required' => false,
                'sanitize_callback' => function($param) {
                    return $param !== null ? floatval($param) : null;
                },
                'validate_callback' => function($param) {
                    return $param === null || (is_numeric($param) && $param >= 0);
                },
            ];

            $args['category'] = [
                'description' => 'Product category',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ];

            $args['tags'] = [
                'description' => 'Product tags array',
                'type' => 'array',
                'required' => false,
                'items' => [
                    'type' => 'string',
                ],
                'validate_callback' => function($param) {
                    return empty($param) || is_array($param);
                },
            ];

            $args['product_group_ids'] = [
                'description' => 'Product group IDs array',
                'type' => 'array',
                'required' => false,
                'items' => [
                    'type' => 'integer',
                ],
                'validate_callback' => function($param) {
                    if (empty($param)) {
                        return true;
                    }

                    if (!is_array($param)) {
                        return false;
                    }

                    foreach ($param as $id) {
                        if (!is_numeric($id) || $id <= 0) {
                            return false;
                        }
                    }

                    return true;
                },
            ];

            $args['availability'] = [
                'description' => 'Branch availability mapping (object with branch_id as keys and boolean as values)',
                'type' => 'object',
                'required' => false,
                'validate_callback' => function($param) {
                    // Allow empty or null
                    if (empty($param)) {
                        return true;
                    }

                    // Must be an array/object
                    if (!is_array($param)) {
                        return false;
                    }

                    // All keys should be numeric (branch IDs) and values should be boolean-ish
                    foreach ($param as $key => $value) {
                        if (!is_numeric($key)) {
                            return false;
                        }
                        // Allow boolean, numeric 0/1, or string "0"/"1"/"true"/"false"
                        if (!is_bool($value) && !is_numeric($value) && !in_array($value, ['true', 'false', '0', '1'], true)) {
                            return false;
                        }
                    }

                    return true;
                },
            ];
        }

        return $args;
    }
}