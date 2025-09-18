<?php
declare(strict_types=1);

/**
 * Ingredient REST API Controller
 * 
 * Handles REST API endpoints for ingredients management
 */
class IngredientRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'ingredients';
    
    private IngredientRepository $repository;

    public function __construct()
    {
        $this->repository = new IngredientRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/ingredients
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

        // GET/PUT/DELETE /squidly/v1/ingredients/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Ingredient ID', 'type' => 'integer']],
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
                'args' => ['id' => ['description' => 'Ingredient ID', 'type' => 'integer']],
            ],
        ]);
    }

    /**
     * Get all ingredients
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
            
            $ingredients = $this->repository->getAll($filters);
            
            $data = array_map(function($ingredient) {
                return $this->prepare_item_for_response($ingredient, new \WP_REST_Request())->get_data();
            }, $ingredients);

            return new \WP_REST_Response(['data' => $data], 200);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch ingredients',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single ingredient
     */
    public function get_item($request)
    {
        try {
            $id = (int)$request['id'];
            $ingredient = $this->repository->get($id);
            
            if (!$ingredient) {
                return new \WP_REST_Response([
                    'error' => 'Ingredient not found'
                ], 404);
            }

            return $this->prepare_item_for_response($ingredient, $request);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch ingredient',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new ingredient
     */
    public function create_item($request)
    {
        try {
            $data = [
                'name' => sanitize_text_field($request['name']),
                'price' => (float)($request['price'] ?? 0),
            ];

            $ingredient_id = $this->repository->create($data);
            $ingredient = $this->repository->get($ingredient_id);

            return $this->prepare_item_for_response($ingredient, $request);
            
        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to create ingredient',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ingredient
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

            $success = $this->repository->update($id, $data);
            
            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Ingredient not found'
                ], 404);
            }

            $ingredient = $this->repository->get($id);
            return $this->prepare_item_for_response($ingredient, $request);
            
        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update ingredient',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ingredient
     */
    public function delete_item($request)
    {
        try {
            $id = (int)$request['id'];
            $success = $this->repository->delete($id);
            
            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Ingredient not found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Ingredient deleted successfully'
            ], 200);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete ingredient',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request)
    {
        // Get branch availability from post meta
        $availability = [];
        
        // Check availability for different branches (0-5 for example)
        for ($branch_id = 0; $branch_id <= 5; $branch_id++) {
            $availability[$branch_id] = (bool) get_post_meta($item->id, '_branch_availability_' . $branch_id, true);
        }
        
        // If no branch availability is set, default to available for all branches
        if (empty(array_filter($availability))) {
            $availability = [
                0 => true,  // Default branch availability
                1 => true,  // Branch 1 availability
                2 => true,  // Branch 2 availability
                3 => true,  // Branch 3 availability
                4 => true,  // Branch 4 availability
                5 => true,  // Branch 5 availability
            ];
            
            // Store the default availability in post meta for future filtering
            for ($branch_id = 0; $branch_id <= 5; $branch_id++) {
                update_post_meta($item->id, '_branch_availability_' . $branch_id, '1');
            }
        }

        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'availability' => $availability,
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
                'description' => 'Search in ingredient names',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'price_min' => [
                'description' => 'Minimum price filter',
                'type' => 'number',
                'sanitize_callback' => 'floatval',
            ],
            'price_max' => [
                'description' => 'Maximum price filter',
                'type' => 'number',
                'sanitize_callback' => 'floatval',
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
                'description' => 'Ingredient name',
                'type' => 'string',
                'required' => $method === \WP_REST_Server::CREATABLE,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param) {
                    return !empty($param);
                },
            ];

            $args['price'] = [
                'description' => 'Ingredient price',
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'sanitize_callback' => 'floatval',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 0;
                },
            ];
        }

        return $args;
    }
}