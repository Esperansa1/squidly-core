<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery Time Surcharge REST API Controller
 * Admin-only endpoints for managing time-based delivery surcharges
 */
class DeliveryTimeSurchargeRestController extends \WP_REST_Controller
{
    protected $namespace = 'squidly/v1';
    protected $rest_base = 'delivery-time-surcharges';
    private DeliveryTimeSurchargeRepository $repository;

    public function __construct()
    {
        $this->repository = new DeliveryTimeSurchargeRepository();
    }

    public function register_routes(): void
    {
        // GET/POST /squidly/v1/delivery-time-surcharges
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'admin_permissions_check'],
                'args' => [
                    'branch_id' => ['type' => 'integer', 'required' => false],
                    'is_active' => ['type' => 'boolean', 'required' => false],
                ],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'admin_permissions_check'],
            ],
        ]);

        // GET/PUT/DELETE /squidly/v1/delivery-time-surcharges/{id}
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
            if (isset($request['is_active'])) {
                $criteria['is_active'] = (bool) $request['is_active'];
            }

            $surcharges = empty($criteria) ? $this->repository->getAll() : $this->repository->findBy($criteria);

            return new \WP_REST_Response(array_map(fn($s) => $s->toArray(), $surcharges), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function get_item($request)
    {
        try {
            $surcharge = $this->repository->get((int) $request['id']);
            if (!$surcharge) {
                return new \WP_REST_Response(['error' => 'Surcharge not found'], 404);
            }
            return new \WP_REST_Response($surcharge->toArray(), 200);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function create_item($request)
    {
        try {
            $data = [
                'branch_id' => (int) $request['branch_id'],
                'day_of_week' => (int) $request['day_of_week'],
                'start_time' => sanitize_text_field($request['start_time']),
                'end_time' => sanitize_text_field($request['end_time']),
                'surcharge_amount' => (float) $request['surcharge_amount'],
                'surcharge_type' => sanitize_text_field($request['surcharge_type']),
                'is_active' => isset($request['is_active']) ? (bool) $request['is_active'] : true,
            ];

            $id = $this->repository->create($data);
            $surcharge = $this->repository->get($id);

            return new \WP_REST_Response($surcharge->toArray(), 201);
        } catch (Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function update_item($request)
    {
        try {
            $data = [];
            if (isset($request['branch_id'])) $data['branch_id'] = (int) $request['branch_id'];
            if (isset($request['day_of_week'])) $data['day_of_week'] = (int) $request['day_of_week'];
            if (isset($request['start_time'])) $data['start_time'] = sanitize_text_field($request['start_time']);
            if (isset($request['end_time'])) $data['end_time'] = sanitize_text_field($request['end_time']);
            if (isset($request['surcharge_amount'])) $data['surcharge_amount'] = (float) $request['surcharge_amount'];
            if (isset($request['surcharge_type'])) $data['surcharge_type'] = sanitize_text_field($request['surcharge_type']);
            if (isset($request['is_active'])) $data['is_active'] = (bool) $request['is_active'];

            $success = $this->repository->update((int) $request['id'], $data);
            if (!$success) {
                return new \WP_REST_Response(['error' => 'Update failed'], 400);
            }

            $surcharge = $this->repository->get((int) $request['id']);
            return new \WP_REST_Response($surcharge->toArray(), 200);
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
