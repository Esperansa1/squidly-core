<?php
declare(strict_types=1);

/**
 * Ingredient Group REST API Controller
 * 
 * Handles REST API endpoints for ingredient groups management
 */
class IngredientGroupRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'ingredient-groups';
    
    private ProductGroupRepository $repository; // Same repository, different type filter

    public function __construct()
    {
        $this->repository = new ProductGroupRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/ingredient-groups
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

        // GET/PUT/DELETE /squidly/v1/ingredient-groups/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Ingredient group ID', 'type' => 'integer']],
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
                'args' => ['id' => ['description' => 'Ingredient group ID', 'type' => 'integer']],
            ],
        ]);
    }

    /**
     * Get all ingredient groups (filter by type = 'ingredient')
     */
    public function get_items($request)
    {
        try {
            // Get only ingredient type groups using the convenience method
            $groups = $this->repository->getIngredientGroups();
            
            $data = array_map(function($group) {
                return $this->prepare_item_for_response($group, new \WP_REST_Request())->get_data();
            }, $groups);

            return new \WP_REST_Response($data, 200);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch ingredient groups',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new ingredient group
     */
    public function create_item($request)
    {
        try {
            $data = [
                'name' => sanitize_text_field($request['name']),
                'type' => 'ingredient', // Force ingredient type
                'group_item_ids' => $request['group_item_ids'] ?? [],
                'status' => $request['status'] ?? 'active',
                'branch_id' => $request['branch_id'] ?? null,
            ];

            $group_id = $this->repository->create($data);
            $group = $this->repository->get($group_id);

            return $this->prepare_item_for_response($group, $request);
            
        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to create ingredient group',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single ingredient group
     */
    public function get_item($request)
    {
        try {
            $id = (int)$request['id'];
            $group = $this->repository->get($id);
            
            if (!$group || $group->type->value !== 'ingredient') {
                return new \WP_REST_Response([
                    'error' => 'Ingredient group not found'
                ], 404);
            }

            return $this->prepare_item_for_response($group, $request);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch ingredient group',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ingredient group
     */
    public function update_item($request)
    {
        try {
            $id = (int)$request['id'];
            $data = ['type' => 'ingredient']; // Ensure type remains ingredient

            if (isset($request['name'])) {
                $data['name'] = sanitize_text_field($request['name']);
            }
            
            if (isset($request['group_item_ids'])) {
                $data['group_item_ids'] = $request['group_item_ids'];
            }
            
            if (isset($request['status'])) {
                $data['status'] = sanitize_text_field($request['status']);
            }

            $success = $this->repository->update($id, $data);
            
            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Ingredient group not found'
                ], 404);
            }

            $group = $this->repository->get($id);
            return $this->prepare_item_for_response($group, $request);
            
        } catch (InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to update ingredient group',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ingredient group
     */
    public function delete_item($request)
    {
        try {
            $id = (int)$request['id'];
            $success = $this->repository->delete($id);
            
            if (!$success) {
                return new \WP_REST_Response([
                    'error' => 'Ingredient group not found'
                ], 404);
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Ingredient group deleted successfully'
            ], 200);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete ingredient group',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare item for response
     */
    public function prepare_item_for_response($item, $request)
    {
        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type->value,
            'group_item_ids' => $item->group_item_ids,
            'status' => 'active', // Add status logic based on your requirements
            'items_count' => count($item->group_item_ids),
        ];

        return new \WP_REST_Response($data, 200);
    }

    /**
     * Permission checks - same as ProductGroupRestController
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
            'status' => [
                'description' => 'Filter by status',
                'type' => 'string',
                'enum' => ['active', 'inactive'],
            ],
        ];
    }
}