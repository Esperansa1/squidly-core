<?php
declare(strict_types=1);

/**
 * Order Repository
 * 
 * Handles all database operations for Order entities.
 * Manages order lifecycle, items, payment tracking, and status updates.
 */
class OrderRepository implements RepositoryInterface
{
    /**
     * Create a new order
     */
    public function create(array $data): int
    {
        $this->validateCreateData($data);

        $post_title = $this->generateOrderTitle($data);

        $post_id = wp_insert_post([
            'post_title'   => $post_title,
            'post_type'    => OrderPostType::POST_TYPE,
            'post_status'  => 'publish',
            'post_content' => '',
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create order: ' . $post_id->get_error_message());
        }

        // Save order meta data
        $this->saveOrderMeta((int)$post_id, $data);

        return (int)$post_id;
    }

    /**
     * Get order by ID
     */
    public function get(int $id): ?Order
    {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== OrderPostType::POST_TYPE) {
            return null;
        }

        return Order::fromWordPress($post);
    }

    /**
     * Update an existing order
     */
    public function update(int $id, array $data): bool
    {
        $order = $this->get($id);
        if (!$order) {
            return false;
        }

        $this->validateUpdateData($data);

        // Update post if needed
        $post_data = [];
        if (isset($data['notes'])) {
            $post_data['post_content'] = $data['notes'];
        }

        if (!empty($post_data)) {
            $post_data['ID'] = $id;
            wp_update_post($post_data);
        }

        // Update meta fields
        $this->updateOrderMeta($id, $data);

        return true;
    }

    /**
     * Delete an order
     */
    public function delete(int $id, bool $force_delete = false): bool
    {
        $order = $this->get($id);
        if (!$order) {
            return false;
        }

        // Check if order can be deleted
        if (!$force_delete && $order->isCompleted() && $order->payment_status === Order::PAYMENT_PAID) {
            throw new ResourceInUseException(['Cannot delete completed paid orders. Use force_delete if necessary.']);
        }

        $result = wp_delete_post($id, $force_delete);
        return (bool) $result;
    }

    /**
     * Get all orders with optional filters
     */
    public function getAll(array $filters = []): array
    {
        $args = [
            'post_type'      => OrderPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $filters['limit'] ?? -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [],
            'fields'         => 'ids', // Only get IDs like CustomerRepository
            'no_found_rows'  => true,
        ];

        // Apply filters
        if (isset($filters['customer_id'])) {
            $args['meta_query'][] = [
                'key'   => '_customer_id',
                'value' => $filters['customer_id'],
            ];
        }

        if (isset($filters['status'])) {
            $args['meta_query'][] = [
                'key'   => '_status',
                'value' => $filters['status'],
            ];
        }

        if (isset($filters['payment_status'])) {
            $args['meta_query'][] = [
                'key'   => '_payment_status',
                'value' => $filters['payment_status'],
            ];
        }

        if (isset($filters['date_from'])) {
            $args['date_query']['after'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $args['date_query']['before'] = $filters['date_to'];
        }

        // Use WP_Query to get post IDs, then use get() method like CustomerRepository
        $wp_query = new \WP_Query($args);
        $post_ids = $wp_query->posts;
        wp_reset_postdata();
        
        // Convert IDs to Order objects using the get() method
        $orders = [];
        foreach ($post_ids as $post_id) {
            $order = $this->get((int) $post_id);
            if ($order) {
                $orders[] = $order;
            }
        }
        
        return $orders;
    }

    /**
     * Get orders by customer ID
     */
    public function getByCustomer(int $customer_id): array
    {
        return $this->findBy(['customer_id' => $customer_id]);
    }

    /**
     * Get orders by status
     */
    public function getByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }

    /**
     * Update order status
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, Order::getValidStatuses())) {
            throw new InvalidArgumentException("Invalid order status: {$status}");
        }

        return update_post_meta($id, '_status', $status) !== false;
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(int $id, string $payment_status): bool
    {
        if (!in_array($payment_status, Order::getValidPaymentStatuses())) {
            throw new InvalidArgumentException("Invalid payment status: {$payment_status}");
        }

        return update_post_meta($id, '_payment_status', $payment_status) !== false;
    }

    /**
     * Add item to order
     */
    public function addItem(int $order_id, OrderItem $item): bool
    {
        $order = $this->get($order_id);
        if (!$order) {
            return false;
        }

        $order->order_items[] = $item;
        $order->calculateTotals();

        return $this->update($order_id, [
            'order_items' => array_map(fn($item) => $item->toArray(), $order->order_items),
            'subtotal' => $order->subtotal,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
        ]);
    }

    /**
     * Find orders by criteria with pagination
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $query_args = [
            'post_type' => OrderPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit ?? -1,
            'fields' => 'ids', // Only get IDs like CustomerRepository
        ];
        
        // Only add offset if not zero to match working pattern
        if ($offset > 0) {
            $query_args['offset'] = $offset;
        }

        // For single criteria, use simple meta_key/meta_value
        if (count($criteria) === 1) {
            $key = array_key_first($criteria);
            $value = $criteria[$key];
            
            switch ($key) {
                case 'customer_id':
                    $query_args['meta_key'] = '_customer_id';
                    $query_args['meta_value'] = $value;
                    break;
                case 'status':
                    $query_args['meta_key'] = '_status';
                    $query_args['meta_value'] = $value;
                    break;
                case 'payment_status':
                    $query_args['meta_key'] = '_payment_status';
                    $query_args['meta_value'] = $value;
                    break;
                case 'payment_method':
                    $query_args['meta_key'] = '_payment_method';
                    $query_args['meta_value'] = $value;
                    break;
                default:
                    // For other criteria, fall back to meta_query
                    $query_args['meta_query'] = [
                        [
                            'key' => "_{$key}",
                            'value' => $value,
                            'compare' => '='
                        ]
                    ];
                    break;
            }
        } else {
            // Multiple criteria - use meta_query
            $meta_query = ['relation' => 'AND'];
            $date_query = [];

            foreach ($criteria as $key => $value) {
                switch ($key) {
                    case 'customer_id':
                    case 'status':
                    case 'payment_status':
                    case 'payment_method':
                        $meta_query[] = [
                            'key' => "_{$key}",
                            'value' => $value,
                            'compare' => '='
                        ];
                        break;
                    case 'total_min':
                        $meta_query[] = [
                            'key' => '_total_amount',
                            'value' => $value,
                            'type' => 'NUMERIC',
                            'compare' => '>='
                        ];
                        break;
                    case 'total_max':
                        $meta_query[] = [
                            'key' => '_total_amount',
                            'value' => $value,
                            'type' => 'NUMERIC',
                            'compare' => '<='
                        ];
                        break;
                    case 'date_from':
                        $date_query['after'] = $value;
                        break;
                    case 'date_to':
                        $date_query['before'] = $value;
                        break;
                }
            }

            if (!empty($meta_query) && count($meta_query) > 1) {
                $query_args['meta_query'] = $meta_query;
            }
            
            if (!empty($date_query)) {
                $query_args['date_query'] = $date_query;
            }
        }

        // Use WP_Query to get post IDs, then use get() method like CustomerRepository
        $wp_query = new \WP_Query($query_args);
        $post_ids = $wp_query->posts;
        wp_reset_postdata();
        
        // Convert IDs to Order objects using the get() method
        $orders = [];
        foreach ($post_ids as $post_id) {
            $order = $this->get((int) $post_id);
            if ($order) {
                $orders[] = $order;
            }
        }
        
        return $orders;
    }

    /**
     * Count orders matching criteria
     */
    public function countBy(array $criteria): int
    {
        $orders = $this->findBy($criteria);
        return count($orders);
    }

    /**
     * Check if order exists
     */
    public function exists(int $id): bool
    {
        return $this->get($id) !== null;
    }

    /**
     * Get order statistics
     */
    public function getStatistics(array $filters = []): array
    {
        // Convert getAll-style filters to findBy-style criteria
        $criteria = [];
        if (isset($filters['customer_id'])) {
            $criteria['customer_id'] = $filters['customer_id'];
        }
        if (isset($filters['status'])) {
            $criteria['status'] = $filters['status'];
        }
        if (isset($filters['payment_status'])) {
            $criteria['payment_status'] = $filters['payment_status'];
        }
        if (isset($filters['date_from'])) {
            $criteria['date_from'] = $filters['date_from'];
        }
        if (isset($filters['date_to'])) {
            $criteria['date_to'] = $filters['date_to'];
        }
        
        $orders = $this->findBy($criteria, $filters['limit'] ?? null);
        
        return [
            'total_orders' => count($orders),
            'total_revenue' => array_reduce($orders, fn($sum, $order) => $sum + $order->total_amount, 0.0),
            'average_order_value' => count($orders) > 0 ? 
                array_reduce($orders, fn($sum, $order) => $sum + $order->total_amount, 0.0) / count($orders) : 0,
            'status_breakdown' => array_count_values(array_column($orders, 'status')),
            'payment_breakdown' => array_count_values(array_column($orders, 'payment_status')),
        ];
    }

    /**
     * Validate create data
     */
    private function validateCreateData(array $data): void
    {
        if (!isset($data['customer_id']) || !is_numeric($data['customer_id'])) {
            throw new InvalidArgumentException('Customer ID is required');
        }

        if (!isset($data['order_items']) || !is_array($data['order_items']) || empty($data['order_items'])) {
            throw new InvalidArgumentException('Order must contain at least one item');
        }

        // Validate order items
        foreach ($data['order_items'] as $item_data) {
            if (!isset($item_data['product_id'], $item_data['quantity'], $item_data['unit_price'])) {
                throw new InvalidArgumentException('Each order item must have product_id, quantity, and unit_price');
            }

            if ($item_data['quantity'] < 1) {
                throw new InvalidArgumentException('Item quantity must be at least 1');
            }

            if ($item_data['unit_price'] < 0) {
                throw new InvalidArgumentException('Item unit price cannot be negative');
            }
        }

        // Validate status if provided
        if (isset($data['status']) && !in_array($data['status'], Order::getValidStatuses())) {
            throw new InvalidArgumentException('Invalid order status');
        }

        // Validate payment status if provided
        if (isset($data['payment_status']) && !in_array($data['payment_status'], Order::getValidPaymentStatuses())) {
            throw new InvalidArgumentException('Invalid payment status');
        }

        // Validate payment method if provided
        if (isset($data['payment_method']) && !in_array($data['payment_method'], Order::getValidPaymentMethods())) {
            throw new InvalidArgumentException('Invalid payment method');
        }
    }

    /**
     * Validate update data
     */
    private function validateUpdateData(array $data): void
    {
        // Similar validations as create but optional fields
        if (isset($data['status']) && !in_array($data['status'], Order::getValidStatuses())) {
            throw new InvalidArgumentException('Invalid order status');
        }

        if (isset($data['payment_status']) && !in_array($data['payment_status'], Order::getValidPaymentStatuses())) {
            throw new InvalidArgumentException('Invalid payment status');
        }

        if (isset($data['payment_method']) && !in_array($data['payment_method'], Order::getValidPaymentMethods())) {
            throw new InvalidArgumentException('Invalid payment method');
        }

        if (isset($data['order_items'])) {
            foreach ($data['order_items'] as $item_data) {
                if (isset($item_data['quantity']) && $item_data['quantity'] < 1) {
                    throw new InvalidArgumentException('Item quantity must be at least 1');
                }

                if (isset($item_data['unit_price']) && $item_data['unit_price'] < 0) {
                    throw new InvalidArgumentException('Item unit price cannot be negative');
                }
            }
        }
    }

    /**
     * Generate order title for display
     */
    private function generateOrderTitle(array $data): string
    {
        $customer_id = $data['customer_id'];
        $order_date = date('Y-m-d H:i:s');
        return "Order #{$customer_id} - {$order_date}";
    }

    /**
     * Save order meta data
     */
    private function saveOrderMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            '_customer_id' => $data['customer_id'],
            '_status' => $data['status'] ?? Order::STATUS_PENDING,
            '_subtotal' => $data['subtotal'] ?? 0.0,
            '_tax_amount' => $data['tax_amount'] ?? 0.0,
            '_delivery_fee' => $data['delivery_fee'] ?? 0.0,
            '_total_amount' => $data['total_amount'] ?? 0.0,
            '_payment_status' => $data['payment_status'] ?? Order::PAYMENT_PENDING,
            '_payment_method' => $data['payment_method'] ?? Order::PAYMENT_CASH,
            '_notes' => $data['notes'] ?? '',
            '_delivery_address' => $data['delivery_address'] ?? null,
            '_pickup_time' => $data['pickup_time'] ?? null,
            '_special_instructions' => $data['special_instructions'] ?? null,
            '_order_items' => $data['order_items'] ?? [],
        ];

        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    /**
     * Update order meta data
     */
    private function updateOrderMeta(int $post_id, array $data): void
    {
        $updatable_fields = [
            'status' => '_status',
            'subtotal' => '_subtotal',
            'tax_amount' => '_tax_amount',
            'delivery_fee' => '_delivery_fee',
            'total_amount' => '_total_amount',
            'payment_status' => '_payment_status',
            'payment_method' => '_payment_method',
            'notes' => '_notes',
            'delivery_address' => '_delivery_address',
            'pickup_time' => '_pickup_time',
            'special_instructions' => '_special_instructions',
            'order_items' => '_order_items',
        ];

        foreach ($updatable_fields as $data_key => $meta_key) {
            if (array_key_exists($data_key, $data)) {
                update_post_meta($post_id, $meta_key, $data[$data_key]);
            }
        }
    }
}