<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Public REST API controller for store branches (no authentication required).
 *
 * Provides public access to branch information including:
 * - Branch details (name, address, phone)
 * - Opening hours (activity_times)
 * - Real-time availability (is_currently_open)
 * - Next opening time
 * - Available products and ingredients
 */
class PublicBranchRestController extends PublicRestController
{
    protected $rest_base = 'branches';

    private StoreBranchRepository $repository;

    public function __construct()
    {
        $this->repository = new StoreBranchRepository();
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void
    {
        // GET /squidly/v1/public/branches - List all branches
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'public_permission_callback'],
                'args'                => $this->get_collection_params(),
            ],
        ]);

        // GET /squidly/v1/public/branches/{id} - Get single branch
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
    }

    /**
     * Get all branches.
     *
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_items($request): WP_REST_Response
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

            // Optional filter: only show currently open branches
            if ($request->get_param('open_now') === 'true') {
                $filters['open_now'] = true;
            }

            // Optional filter: filter by city
            if ($request->get_param('city')) {
                $filters['city'] = sanitize_text_field($request->get_param('city'));
            }

            // Get all branches from repository
            $all_branches = $this->repository->getAll();

            // Apply filters
            if (!empty($filters)) {
                $all_branches = array_filter($all_branches, function($branch) use ($filters) {
                    // Filter by city
                    if (isset($filters['city']) && $branch->city !== $filters['city']) {
                        return false;
                    }

                    // Filter by open_now
                    if (isset($filters['open_now']) && !$branch->isCurrentlyOpen()) {
                        return false;
                    }

                    return true;
                });

                // Re-index array after filtering
                $all_branches = array_values($all_branches);
            }

            $total = count($all_branches);

            // Apply pagination
            $branches = array_slice(
                $all_branches,
                $pagination['offset'],
                $pagination['per_page']
            );

            // Convert to arrays
            $data = array_map(function($branch) {
                return $branch->toArray();
            }, $branches);

            $response = new WP_REST_Response($data, 200);
            $response->header('X-WP-Total', (string)$total);
            $response->header('X-WP-TotalPages', (string)ceil($total / $pagination['per_page']));

            return $response;

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to retrieve branches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single branch by ID.
     *
     * @param WP_REST_Request $request Full request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_item($request): WP_REST_Response
    {
        // Apply rate limiting
        if (!$this->check_rate_limit()) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        try {
            $id = (int) $request->get_param('id');
            $branch = $this->repository->get($id);

            if (!$branch) {
                return new WP_REST_Response([
                    'error' => 'Branch not found'
                ], 404);
            }

            return new WP_REST_Response($branch->toArray(), 200);

        } catch (Exception $e) {
            return new WP_REST_Response([
                'error' => 'Failed to retrieve branch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get collection parameters for branches.
     *
     * @return array Collection parameters
     */
    public function get_collection_params(): array
    {
        return [
            'per_page' => [
                'type'              => 'integer',
                'default'           => 10,
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
            'open_now' => [
                'type'              => 'string',
                'enum'              => ['true', 'false'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}
