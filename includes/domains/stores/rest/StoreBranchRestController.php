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
        // GET /squidly/v1/branches
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
        ]);

        // GET /squidly/v1/branches/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => ['id' => ['description' => 'Branch ID', 'type' => 'integer']],
            ],
        ]);
    }

    /**
     * Get all store branches
     */
    public function get_items($request)
    {
        try {
            $branches = $this->repository->getAll();
            
            $data = array_map(function($branch) {
                return $this->prepare_item_for_response($branch, new \WP_REST_Request())->get_data();
            }, $branches);

            // Add "All Branches" option at the beginning
            array_unshift($data, [
                'id' => 0,
                'name' => 'כל הסניפים',
                'city' => '',
                'is_open' => true
            ]);

            return new \WP_REST_Response($data, 200);
            
        } catch (Exception $e) {
            return new \WP_REST_Response([
                'error' => 'Failed to fetch branches',
                'message' => $e->getMessage()
            ], 500);
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
        $data = [
            'id' => $item->id,
            'name' => $item->name,
            'phone' => $item->phone,
            'city' => $item->city,
            'address' => $item->address,
            'is_open' => $item->is_open,
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
}