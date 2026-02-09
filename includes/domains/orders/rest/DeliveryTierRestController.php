<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Tier REST API Controller
 * Admin-only endpoints for managing order-value based delivery pricing
 */
class DeliveryTierRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'delivery-tiers';
    private DeliveryTierRepository $repository;

    public function __construct()
    {
        $this->repository = new DeliveryTierRepository();
    }

    public function register_routes(): void
    {
        // GET/POST /squidly/v1/delivery-tiers
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'admin_permissions_check'],
                'args' => ['branch_id' => ['type' => 'integer', 'required' => false]],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
        ]);

        // GET/PUT/DELETE /squidly/v1/delivery-tiers/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
        ]);
    }

    public function get_items($request)
    {
        try {
            $criteria = [];
            if (!empty($request['branch_id'])) {
                $criteria['branch_id'] = (int) $request['branch_id'];
            }

            $tiers = empty($criteria) ? $this->repository->getAll() : $this->repository->findBy($criteria);

            return new \WP_REST_Response(array_map(fn($tier) => $tier->toArray(), $tiers), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function get_item($request)
    {
        try {
            $tier = $this->repository->get((int) $request['id']);
            if (!$tier) {
                return new \WP_REST_Response(['error' => 'Tier not found'], 404);
            }
            return new \WP_REST_Response($tier->toArray(), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function create_item($request)
    {
        try {
            $data = [
                'branch_id' => (int) $request['branch_id'],
                'min_order_value' => (float) $request['min_order_value'],
                'delivery_fee' => (float) $request['delivery_fee'],
            ];

            $id = $this->repository->create($data);
            $tier = $this->repository->get($id);

            return new \WP_REST_Response($tier->toArray(), 201);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function update_item($request)
    {
        try {
            $data = [];
            if (isset($request['branch_id'])) $data['branch_id'] = (int) $request['branch_id'];
            if (isset($request['min_order_value'])) $data['min_order_value'] = (float) $request['min_order_value'];
            if (isset($request['delivery_fee'])) $data['delivery_fee'] = (float) $request['delivery_fee'];

            $success = $this->repository->update((int) $request['id'], $data);
            if (!$success) {
                return new \WP_REST_Response(['error' => 'Update failed'], 400);
            }

            $tier = $this->repository->get((int) $request['id']);
            return new \WP_REST_Response($tier->toArray(), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function delete_item($request)
    {
        try {
            $success = $this->repository->delete((int) $request['id']);
            if (!$success) {
                return new \WP_REST_Response(['error' => 'Delete failed'], 400);
            }
            return new \WP_REST_Response(['success' => true], 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function admin_permissions_check($request)
    {
        return current_user_can('manage_options');
    }
}
