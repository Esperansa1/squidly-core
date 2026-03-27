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
            $filters = ['type' => 'ingredient'];

            // Add search filter if provided
            if (!empty($request['search'])) {
                $filters['search'] = sanitize_text_field($request['search']);
            }

            // Get pagination parameters
            $per_page = isset($request['per_page']) ? (int) $request['per_page'] : null;
            $offset = isset($request['offset']) ? (int) $request['offset'] : 0;

            // Get only ingredient type groups
            $groups = $this->repository->findBy($filters, $per_page, $offset);

            // Pre-load all GroupItem meta to avoid N+1 queries in prepare_item_for_response
            $all_group_item_ids = [];
            foreach ($groups as $group) {
                if (!empty($group->group_item_ids)) {
                    $all_group_item_ids = array_merge($all_group_item_ids, $group->group_item_ids);
                }
            }
            $all_group_item_ids = array_unique(array_filter(array_map('intval', $all_group_item_ids)));
            if (!empty($all_group_item_ids)) {
                update_meta_cache('post', $all_group_item_ids);
            }

            $data = array_map(function($group) {
                return $this->prepare_item_for_response($group, new \WP_REST_Request())->get_data();
            }, $groups);

            // Add pagination headers
            $total = $this->repository->countBy($filters);
            $response = rest_ensure_response($data);
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) ceil($total / ($per_page ?? 10)));

            return $response;
            
        } catch (Exception $e) {
            // Log the error for debugging but return empty array to frontend
            error_log("IngredientGroupRestController get_items error: " . $e->getMessage());
            return new \WP_REST_Response([], 200);
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
                'description' => isset($request['description']) ? sanitize_textarea_field($request['description']) : '',
                'type' => 'ingredient', // Force ingredient type
                'availability' => $request['availability'] ?? [],
                'status' => $request['status'] ?? 'active',
                'branch_id' => $request['branch_id'] ?? null,
            ];

            // Convert raw item IDs to GroupItem IDs
            if (isset($request['item_ids']) && is_array($request['item_ids'])) {
                $data['group_item_ids'] = $this->convertToGroupItemIds($request['item_ids'], 'ingredient');
            } else {
                $data['group_item_ids'] = [];
            }

            // Handle min/max selection constraints
            if (isset($request['min_selections'])) {
                $data['min_selections'] = max(0, (int) $request['min_selections']);
            }
            if (isset($request['max_selections'])) {
                $data['max_selections'] = max(0, (int) $request['max_selections']);
            }

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

            if (isset($request['description'])) {
                $data['description'] = sanitize_textarea_field($request['description']);
            }
            
            // Convert raw item IDs to GroupItem IDs
            if (isset($request['item_ids']) && is_array($request['item_ids'])) {
                $data['group_item_ids'] = $this->convertToGroupItemIds($request['item_ids'], 'ingredient');
            }

            if (isset($request['availability'])) {
                $data['availability'] = $request['availability'];
            }

            if (isset($request['status'])) {
                $data['status'] = sanitize_text_field($request['status']);
            }

            // Handle min/max selection constraints
            if (isset($request['min_selections'])) {
                $data['min_selections'] = max(0, (int) $request['min_selections']);
            }
            if (isset($request['max_selections'])) {
                $data['max_selections'] = max(0, (int) $request['max_selections']);
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
            
        } catch (ResourceInUseException $e) {
            return new \WP_REST_Response([
                'error' => 'Cannot delete ingredient group',
                'message' => 'קבוצה זו בשימוש על ידי המוצרים הבאים: ' . implode(', ', $e->dependants),
                'dependants' => $e->dependants
            ], 409); // 409 Conflict - resource is in use
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
        // Get final availability (combines manual and calculated)
        $final_availability = $item->getFinalAvailability();
        $calculated_availability = $item->calculateAvailability();

        // Resolve GroupItem IDs to actual item data for frontend
        $resolved_items = [];
        $group_item_repo = new GroupItemRepository();

        foreach ($item->group_item_ids as $group_item_id) {
            $group_item = $group_item_repo->get($group_item_id);
            if ($group_item) {
                $resolved_items[] = [
                    'id' => $group_item->item_id,
                    'type' => $group_item->item_type->value,
                    'group_item_id' => $group_item->id
                ];
            }
        }

        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description ?? '',
            'type' => $item->type->value,
            'group_item_ids' => $item->group_item_ids,
            'resolved_items' => $resolved_items, // Add resolved item data for frontend
            'availability' => $item->availability, // Manual availability settings
            'calculated_availability' => $calculated_availability, // Auto-calculated based on items
            'final_availability' => $final_availability, // Final combined availability
            'status' => 'active', // Add status logic based on your requirements
            'items_count' => count($item->group_item_ids),
            'min_selections' => $item->min_selections,
            'max_selections' => $item->max_selections,
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
            'search' => [
                'description' => 'Search ingredient groups by name',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'description' => 'Filter by type',
                'type' => 'string',
                'default' => 'ingredient',
            ],
            'per_page' => [
                'description' => 'Maximum number of items to return',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'offset' => [
                'description' => 'Offset for pagination',
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ];
    }

    /**
     * Get endpoint args for item schema
     */
    public function get_endpoint_args_for_item_schema($method = \WP_REST_Server::CREATABLE): array
    {
        $args = [
            'name' => [
                'description' => 'Ingredient group name',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'description' => 'Ingredient group description',
                'type' => 'string',
                'required' => false,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'type' => [
                'description' => 'Group type',
                'type' => 'string',
                'default' => 'ingredient',
                'enum' => ['ingredient', 'product'],
            ],
            'group_item_ids' => [
                'description' => 'Array of GroupItem IDs in this group (deprecated)',
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'default' => [],
            ],
            'item_ids' => [
                'description' => 'Array of raw ingredient IDs to include in this group',
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'default' => [],
            ],
            'availability' => [
                'description' => 'Manual availability settings per branch',
                'type' => 'object',
                'default' => [],
            ],
            'min_selections' => [
                'description' => 'Minimum number of items a customer must select (0 = optional)',
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
            'max_selections' => [
                'description' => 'Maximum number of items a customer can select (0 = unlimited)',
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0,
            ],
        ];

        if ($method === \WP_REST_Server::EDITABLE) {
            $args['name']['required'] = false;
        }

        return $args;
    }

    /**
     * Convert raw item IDs to GroupItem IDs
     * Creates GroupItem objects for raw ingredient IDs
     * Reuses existing GroupItems when available to avoid duplicates
     */
    private function convertToGroupItemIds(array $item_ids, string $type): array
    {
        $group_item_ids = [];
        $group_item_repo = new GroupItemRepository();

        foreach ($item_ids as $item_id) {
            $item_id = (int) $item_id;
            if ($item_id <= 0) continue;

            // Check if GroupItem already exists for this item
            $existing_group_items = $group_item_repo->findByReferencedItem($item_id, $type);

            if (!empty($existing_group_items)) {
                // Use existing GroupItem
                $group_item_ids[] = $existing_group_items[0]->id;
            } else {
                // Create new GroupItem
                try {
                    $group_item_id = $group_item_repo->create([
                        'item_id' => $item_id,
                        'item_type' => $type,
                        'override_price' => null
                    ]);
                    $group_item_ids[] = $group_item_id;
                } catch (Exception $e) {
                    // Skip invalid items but continue with others
                    continue;
                }
            }
        }

        return $group_item_ids;
    }
}