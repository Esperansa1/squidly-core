<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Loyalty Discount REST API Controller
 * Admin-only endpoints for managing loyalty-based delivery discounts
 */
class DeliveryLoyaltyDiscountRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'delivery-loyalty-discounts';
    private DeliveryLoyaltyDiscountRepository $repository;

    public function __construct()
    {
        $this->repository = new DeliveryLoyaltyDiscountRepository();
    }

    public function register_routes(): void
    {
        // GET/POST /squidly/v1/delivery-loyalty-discounts
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'admin_permissions_check'],
                'args' => ['is_active' => ['type' => 'boolean', 'required' => false]],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
        ]);

        // GET/PUT/DELETE /squidly/v1/delivery-loyalty-discounts/{id}
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
            if (isset($request['is_active'])) {
                $criteria['is_active'] = (bool) $request['is_active'];
            }

            $discounts = empty($criteria) ? $this->repository->getAll() : $this->repository->findBy($criteria);

            return new \WP_REST_Response(array_map(fn($d) => $d->toArray(), $discounts), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function get_item($request)
    {
        try {
            $discount = $this->repository->get((int) $request['id']);
            if (!$discount) {
                return new \WP_REST_Response(['error' => 'Discount not found'], 404);
            }
            return new \WP_REST_Response($discount->toArray(), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function create_item($request)
    {
        try {
            $data = [
                'min_loyalty_points' => (int) $request['min_loyalty_points'],
                'discount_amount' => (float) $request['discount_amount'],
                'discount_type' => sanitize_text_field($request['discount_type']),
                'is_active' => isset($request['is_active']) ? (bool) $request['is_active'] : true,
            ];

            $id = $this->repository->create($data);
            $discount = $this->repository->get($id);

            return new \WP_REST_Response($discount->toArray(), 201);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function update_item($request)
    {
        try {
            $data = [];
            if (isset($request['min_loyalty_points'])) $data['min_loyalty_points'] = (int) $request['min_loyalty_points'];
            if (isset($request['discount_amount'])) $data['discount_amount'] = (float) $request['discount_amount'];
            if (isset($request['discount_type'])) $data['discount_type'] = sanitize_text_field($request['discount_type']);
            if (isset($request['is_active'])) $data['is_active'] = (bool) $request['is_active'];

            $success = $this->repository->update((int) $request['id'], $data);
            if (!$success) {
                return new \WP_REST_Response(['error' => 'Update failed'], 400);
            }

            $discount = $this->repository->get((int) $request['id']);
            return new \WP_REST_Response($discount->toArray(), 200);
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
