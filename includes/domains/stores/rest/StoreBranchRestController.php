<?php
declare(strict_types=1);

/**
 * Store Branch REST API Controller
 * 
 * Handles REST API endpoints for store branches management
 */
class StoreBranchRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'branches';
    
    private StoreBranchRepository $repository;

    public function __construct()
    {
        $this->repository = new StoreBranchRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // GET/POST /squidly/v1/branches
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

        // GET/PUT/DELETE /squidly/v1/branches/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Branch ID', 'type' => 'integer']],
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
                'args' => ['id' => ['description' => 'Branch ID', 'type' => 'integer']],
            ],
        ]);

        // GET/PUT /squidly/v1/branches/{id}/availability
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/availability', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_availability'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Branch ID', 'type' => 'integer']],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_availability'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => [
                    'id' => ['description' => 'Branch ID', 'type' => 'integer'],
                    'product_availability' => [
                        'description' => 'Product availability mapping',
                        'type' => 'object',
                        'required' => false,
                    ],
                    'ingredient_availability' => [
                        'description' => 'Ingredient availability mapping',
                        'type' => 'object',
                        'required' => false,
                    ],
                ],
            ],
        ]);

        // POST/DELETE /squidly/v1/branches/{id}/products/{product_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/products/(?P<product_id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_product'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => [
                    'id' => ['description' => 'Branch ID', 'type' => 'integer'],
                    'product_id' => ['description' => 'Product ID', 'type' => 'integer'],
                    'is_active' => ['description' => 'Product availability status', 'type' => 'boolean', 'default' => true],
                ],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove_product'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => [
                    'id' => ['description' => 'Branch ID', 'type' => 'integer'],
                    'product_id' => ['description' => 'Product ID', 'type' => 'integer'],
                ],
            ],
        ]);

        // POST/DELETE /squidly/v1/branches/{id}/ingredients/{ingredient_id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/ingredients/(?P<ingredient_id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_ingredient'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => [
                    'id' => ['description' => 'Branch ID', 'type' => 'integer'],
                    'ingredient_id' => ['description' => 'Ingredient ID', 'type' => 'integer'],
                    'is_active' => ['description' => 'Ingredient availability status', 'type' => 'boolean', 'default' => true],
                ],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove_ingredient'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => [
                    'id' => ['description' => 'Branch ID', 'type' => 'integer'],
                    'ingredient_id' => ['description' => 'Ingredient ID', 'type' => 'integer'],
                ],
            ],
        ]);
    }

    /**
     * Get all store branches
     */
    public function get_items($request)
    {
        try {
            // Build filters from request parameters
            $filters = [];

            if (!empty($request['city'])) {
                $filters['city'] = sanitize_text_field($request['city']);
            }

            if (!empty($request['city_like'])) {
                $filters['city_like'] = sanitize_text_field($request['city_like']);
            }

            if (isset($request['is_open'])) {
                $filters['is_open'] = (bool)$request['is_open'];
            }

            if (!empty($request['kosher_type'])) {
                $filters['kosher_type'] = sanitize_text_field($request['kosher_type']);
            }

            if (!empty($request['has_accessibility'])) {
                $filters['has_accessibility'] = sanitize_text_field($request['has_accessibility']);
            }

            if (!empty($request['has_product'])) {
                $filters['has_product'] = (int)$request['has_product'];
            }

            if (!empty($request['has_ingredient'])) {
                $filters['has_ingredient'] = (int)$request['has_ingredient'];
            }

            if (!empty($request['search'])) {
                $filters['name'] = sanitize_text_field($request['search']);
            }

            // Get branches using filters or all if no filters
            if (!empty($filters)) {
                $branches = $this->repository->findBy($filters);
            } else {
                $branches = $this->repository->getAll();
            }

            $data = array_map(function($branch) {
                return $this->prepare_item_for_response($branch, new \WP_REST_Request())->get_data();
            }, $branches);

            // Add "All Branches" option at the beginning if no specific filters
            if (empty($filters) || (count($filters) == 1 && isset($filters['name']))) {
                array_unshift($data, [
                    'id' => 0,
                    'name' => 'כל הסניפים',
                    'city' => '',
                    'address' => '',
                    'phone' => '',
                    'is_open' => true,
                    'activity_times' => [],
                    'kosher_type' => '',
                    'accessibility_list' => [],
                    'products' => [],
                    'ingredients' => [],
                    'product_availability' => [],
                    'ingredient_availability' => []
                ]);
            }

            return new \WP_REST_Response($data, 200);

        } catch (Exception $e) {
            // Log the error for debugging but return empty array to frontend
            error_log("StoreBranchRestController get_items error: " . $e->getMessage());
            return new \WP_REST_Response([], 200);
        }
    }

    /**
     * Get single branch
     */
    public function get_item($request)
    {
        try {
            $id = (int)$request['id'];
            $branch = $this->repository->get($id);
            
            if (!$branch) {
                return new \WP_REST_Response([
                    'error' => 'Branch not found'
                ], 404);
            }

            return $this->prepare_item_for_response($branch, $request);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request)
    {
        // Prepare products array with minimal data to avoid circular references
        $products = array_map(function($product) use ($item) {
            if (!$product) return null;
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'available' => $item->isProductAvailable($product->id)
            ];
        }, $item->products);
        $products = array_filter($products); // Remove null values

        // Prepare ingredients array with minimal data
        $ingredients = array_map(function($ingredient) use ($item) {
            if (!$ingredient) return null;
            return [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'price' => $ingredient->price,
                'available' => $item->isIngredientAvailable($ingredient->id)
            ];
        }, $item->ingredients);
        $ingredients = array_filter($ingredients); // Remove null values

        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'phone' => $item->phone,
            'city' => $item->city,
            'address' => $item->address,
            'is_open' => $item->is_open,
            'activity_times' => $item->activity_times,
            'kosher_type' => $item->kosher_type,
            'accessibility_list' => $item->accessibility_list,
            'products' => array_values($products),
            'ingredients' => array_values($ingredients),
            'product_availability' => $item->product_availability,
            'ingredient_availability' => $item->ingredient_availability,
        ];

        return new \WP_REST_Response($data, 200);
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
     * Create new store branch
     */
    public function create_item($request)
    {
        try {
            // Validate required fields
            if (empty($request['name'])) {
                return new \WP_REST_Response([
                    'error' => 'Validation failed',
                    'message' => 'Name is required'
                ], 400);
            }

            // Validate activity times if provided
            if (!empty($request['activity_times'])) {
                $validation_result = $this->validate_activity_times($request['activity_times']);
                if ($validation_result !== true) {
                    return new \WP_REST_Response([
                        'error' => 'Validation failed',
                        'message' => $validation_result
                    ], 400);
                }
            }

            $data = [
                'name' => sanitize_text_field($request['name']),
                'phone' => sanitize_text_field($request['phone'] ?? ''),
                'city' => sanitize_text_field($request['city'] ?? ''),
                'address' => sanitize_text_field($request['address'] ?? ''),
                'is_open' => (bool)($request['is_open'] ?? true),
                'activity_times' => $request['activity_times'] ?? [],
                'kosher_type' => sanitize_text_field($request['kosher_type'] ?? ''),
                'accessibility_list' => $request['accessibility_list'] ?? [],
                'products' => $request['products'] ?? [],
                'ingredients' => $request['ingredients'] ?? [],
                'product_availability' => $request['product_availability'] ?? [],
                'ingredient_availability' => $request['ingredient_availability'] ?? [],
            ];

            $branch_id = $this->repository->create($data);
            $branch = $this->repository->get($branch_id);

            return $this->prepare_item_for_response($branch, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to create store branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update store branch
     */
    public function update_item($request)
    {
        try {
            $id = (int)$request['id'];
            $data = [];

            // Validate name if provided
            if (isset($request['name'])) {
                if (empty($request['name'])) {
                    return new \WP_REST_Response([
                        'error' => 'Validation failed',
                        'message' => 'Name cannot be empty'
                    ], 400);
                }
                $data['name'] = sanitize_text_field($request['name']);
            }

            if (isset($request['phone'])) {
                $data['phone'] = sanitize_text_field($request['phone']);
            }

            if (isset($request['city'])) {
                $data['city'] = sanitize_text_field($request['city']);
            }

            if (isset($request['address'])) {
                $data['address'] = sanitize_text_field($request['address']);
            }

            if (isset($request['is_open'])) {
                $data['is_open'] = (bool)$request['is_open'];
            }

            if (isset($request['activity_times'])) {
                // Validate activity times if provided
                $validation_result = $this->validate_activity_times($request['activity_times']);
                if ($validation_result !== true) {
                    return new \WP_REST_Response([
                        'error' => 'Validation failed',
                        'message' => $validation_result
                    ], 400);
                }
                $data['activity_times'] = $request['activity_times'];
            }

            if (isset($request['kosher_type'])) {
                $data['kosher_type'] = sanitize_text_field($request['kosher_type']);
            }

            if (isset($request['accessibility_list'])) {
                $data['accessibility_list'] = $request['accessibility_list'];
            }

            if (isset($request['products'])) {
                $data['products'] = $request['products'];
            }

            if (isset($request['ingredients'])) {
                $data['ingredients'] = $request['ingredients'];
            }

            if (isset($request['product_availability'])) {
                $data['product_availability'] = $request['product_availability'];
            }

            if (isset($request['ingredient_availability'])) {
                $data['ingredient_availability'] = $request['ingredient_availability'];
            }

            $success = $this->repository->update($id, $data);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Store branch not found'
                ], 404);
            }

            $branch = $this->repository->get($id);
            return $this->prepare_item_for_response($branch, $request);

        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update store branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete store branch
     */
    public function delete_item($request)
    {
        try {
            $id = (int)$request['id'];
            $force = (bool)($request['force'] ?? false);

            $success = $this->repository->delete($id, $force);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Store branch not found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Store branch deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete store branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get branch availability info
     */
    public function get_availability($request)
    {
        try {
            $id = (int)$request['id'];
            $branch = $this->repository->get($id);

            if (!$branch) {
                return new \WP_REST_Response([
                    'error' => 'Store branch not found'
                ], 404);
            }

            $data = [
                'branch_id' => $id,
                'product_availability' => $branch->product_availability,
                'ingredient_availability' => $branch->ingredient_availability,
            ];

            return new \WP_REST_Response($data, 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to get availability info',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update branch availability settings
     */
    public function update_availability($request)
    {
        try {
            $id = (int)$request['id'];
            $data = [];

            if (isset($request['product_availability'])) {
                $data['product_availability'] = $request['product_availability'];
            }

            if (isset($request['ingredient_availability'])) {
                $data['ingredient_availability'] = $request['ingredient_availability'];
            }

            $success = $this->repository->update($id, $data);

            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Store branch not found'
                ], 404);
            }

            return $this->get_availability($request);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add product to branch
     */
    public function add_product($request)
    {
        try {
            $branch_id = (int)$request['id'];
            $product_id = (int)$request['product_id'];
            $is_active = (bool)($request['is_active'] ?? true);

            $this->repository->addProduct($branch_id, $product_id, $is_active);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Product added to branch successfully',
                'branch_id' => $branch_id,
                'product_id' => $product_id,
                'is_active' => $is_active
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to add product to branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove product from branch
     */
    public function remove_product($request)
    {
        try {
            $branch_id = (int)$request['id'];
            $product_id = (int)$request['product_id'];

            $this->repository->removeProduct($branch_id, $product_id);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Product removed from branch successfully',
                'branch_id' => $branch_id,
                'product_id' => $product_id
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to remove product from branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add ingredient to branch
     */
    public function add_ingredient($request)
    {
        try {
            $branch_id = (int)$request['id'];
            $ingredient_id = (int)$request['ingredient_id'];
            $is_active = (bool)($request['is_active'] ?? true);

            $this->repository->addIngredient($branch_id, $ingredient_id, $is_active);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Ingredient added to branch successfully',
                'branch_id' => $branch_id,
                'ingredient_id' => $ingredient_id,
                'is_active' => $is_active
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to add ingredient to branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove ingredient from branch
     */
    public function remove_ingredient($request)
    {
        try {
            $branch_id = (int)$request['id'];
            $ingredient_id = (int)$request['ingredient_id'];

            $this->repository->removeIngredient($branch_id, $ingredient_id);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Ingredient removed from branch successfully',
                'branch_id' => $branch_id,
                'ingredient_id' => $ingredient_id
            ], 200);

        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to remove ingredient from branch',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get collection parameters
     */
    public function get_collection_params(): array
    {
        return [
            'city' => [
                'description' => 'Filter by exact city name',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city_like' => [
                'description' => 'Filter by city name (partial match)',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'is_open' => [
                'description' => 'Filter by open/closed status',
                'type' => 'boolean',
            ],
            'kosher_type' => [
                'description' => 'Filter by kosher certification type',
                'type' => 'string',
                'enum' => ['Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'has_accessibility' => [
                'description' => 'Filter by accessibility feature',
                'type' => 'string',
                'enum' => ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'has_product' => [
                'description' => 'Filter branches offering specific product',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'has_ingredient' => [
                'description' => 'Filter branches with specific ingredient',
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'description' => 'Search in branch names',
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
                'description' => 'Store branch name',
                'type' => 'string',
                'required' => $method === \WP_REST_Server::CREATABLE,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    return !empty($param);
                },
            ];

            $args['phone'] = [
                'description' => 'Store branch phone number',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ];

            $args['city'] = [
                'description' => 'Store branch city',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ];

            $args['address'] = [
                'description' => 'Store branch address',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ];

            $args['is_open'] = [
                'description' => 'Whether the branch is currently open',
                'type' => 'boolean',
                'required' => false,
                'default' => true,
            ];

            $args['activity_times'] = [
                'description' => 'Business hours by day of week',
                'type' => 'object',
                'required' => false,
                'default' => [],
                'validate_callback' => function($param) {
                    if (empty($param)) return true;

                    $valid_days = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

                    foreach (array_keys($param) as $day) {
                        if (!in_array(strtoupper($day), $valid_days, true)) {
                            return false;
                        }
                    }

                    return true;
                },
            ];

            $args['kosher_type'] = [
                'description' => 'Kosher certification type',
                'type' => 'string',
                'required' => false,
                'enum' => ['', 'Kosher Dairy', 'Kosher Meat', 'Kosher Mehadrin'],
                'sanitize_callback' => 'sanitize_text_field',
            ];

            $args['accessibility_list'] = [
                'description' => 'List of accessibility features',
                'type' => 'array',
                'required' => false,
                'default' => [],
                'items' => [
                    'type' => 'string',
                    'enum' => ['wheelchair_accessible', 'braille_menu', 'hearing_loop', 'elevator']
                ],
            ];

            $args['products'] = [
                'description' => 'Array of product IDs offered by this branch',
                'type' => 'array',
                'required' => false,
                'default' => [],
                'items' => ['type' => 'integer'],
            ];

            $args['ingredients'] = [
                'description' => 'Array of ingredient IDs available at this branch',
                'type' => 'array',
                'required' => false,
                'default' => [],
                'items' => ['type' => 'integer'],
            ];

            $args['product_availability'] = [
                'description' => 'Product availability mapping (product_id => boolean)',
                'type' => 'object',
                'required' => false,
                'default' => [],
            ];

            $args['ingredient_availability'] = [
                'description' => 'Ingredient availability mapping (ingredient_id => boolean)',
                'type' => 'object',
                'required' => false,
                'default' => [],
            ];
        }

        if ($method === \WP_REST_Server::DELETABLE) {
            $args['force'] = [
                'description' => 'Whether to force delete (bypass trash)',
                'type' => 'boolean',
                'default' => false,
            ];
        }

        return $args;
    }

    /**
     * Validate activity times format
     */
    private function validate_activity_times($activity_times): mixed
    {
        if (empty($activity_times)) {
            return true;
        }

        if (!is_array($activity_times)) {
            return 'Activity times must be an array';
        }

        $valid_days = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];

        foreach (array_keys($activity_times) as $day) {
            if (!in_array(strtoupper($day), $valid_days, true)) {
                return "Invalid day: {$day}. Valid days are: " . implode(', ', $valid_days);
            }
        }

        return true;
    }
}