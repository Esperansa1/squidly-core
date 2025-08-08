<?php
declare(strict_types=1);

/**
 * Customer Repository
 * 
 * Handles all database operations for Customer entities.
 * Supports both registered customers and guest customers.
 * Implements vendor-agnostic customer management.
 */
class CustomerRepository implements RepositoryInterface
{
    /**
     * Create a new customer
     */
    public function create(array $data): int
    {
        // Validate required fields before attempting creation
        $this->validateCreateData($data);
        
        // Prepare customer name and phone number for post title
        $phone = $this->normalizePhone($data['phone']);
        $data['phone'] = $phone;
        $full_name = trim($data['first_name'] . ' ' . $data['last_name']);
        
        $post_title = $full_name . ' (' . $phone . ')';
        
        // Create WordPress post
        $post_id = wp_insert_post([
            'post_title' => $post_title,
            'post_type' => CustomerPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_content' => '', // We store everything in meta
        ]);

        if (is_wp_error($post_id)) {
            throw new RuntimeException('Failed to create customer: ' . $post_id->get_error_message());
        }

        // Store all customer data as meta fields
        $this->saveCustomerMeta($post_id, $data);

        return $post_id;
    }

    /**
     * Get customer by ID
     */
    public function get(int $id): ?Customer
    {
        if ($id < 0) {
            return null;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== CustomerPostType::POST_TYPE) {
            error_log("Failed to get post with id {$id}");
            return null;
        }

        try {
            $customer_data = $this->extractCustomerData($post);
            return new Customer($customer_data);
        } catch (Exception $e) {
            error_log("Failed to create Customer object for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all customers
     */
    public function getAll(): array
    {
        $query = new WP_Query([
            'post_type' => CustomerPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => '_is_active',
                    'value' => true,
                    'compare' => '='
                ]
            ]
        ]);

        $customers = [];
        foreach ($query->posts as $post_id) {
            $customer = $this->get((int) $post_id);
            if ($customer) {
                $customers[] = $customer;
            }
        }

        return $customers;
    }

    /**
     * Update customer data
     */
    public function update(int $id, array $data): bool
    {
        if ($id < 0) {
            return false;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== CustomerPostType::POST_TYPE) {
            return false;
        }

        try {
            // Validate update data
            $this->validateUpdateData($data);
            
            // Update post title if name or phone changed
            if (isset($data['first_name']) || isset($data['last_name']) || isset($data['phone'])) {
                $current_data = $this->extractCustomerData($post);
                
                $first_name = $data['first_name'] ?? $current_data['first_name'];
                $last_name = $data['last_name'] ?? $current_data['last_name'];

                $data['phone'] = $this->normalizePhone($data['phone']);
                $phone = $data['phone'] ?? $current_data['phone'];
                
                $new_title = trim($first_name . ' ' . $last_name) . ' (' . $phone . ')';
                
                wp_update_post([
                    'ID' => $id,
                    'post_title' => $new_title,
                ]);
            }

            // Update meta fields
            $this->updateCustomerMeta($id, $data);

            return true;
        } catch (Exception $e) {
            error_log("Failed to update customer {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete customer (soft delete by default)
     */
    public function delete(int $id, bool $force = false): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== CustomerPostType::POST_TYPE) {
            return false;
        }

        // Check for dependencies (orders)
        if (!$force) {
            $order_count = $this->getCustomerOrderCount($id);
            if ($order_count > 0) {
                throw new ResourceInUseException([
                    'Customer has ' . $order_count . ' orders and cannot be deleted'
                ]);
            }
        }

        $result = wp_delete_post($id, $force);
        if (is_wp_error($result)) {
            throw new RuntimeException('Failed to delete customer: ' . $result->get_error_message());
        }

        return (bool) $result;
    }

    /**
     * Find customers by criteria
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array
    {
        $meta_query = ['relation' => 'AND'];
        $search_query = [];
        $date_query = [];

        // Build meta query from criteria
        foreach ($criteria as $key => $value) {
            switch ($key) {
                case 'email':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_email',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'phone':
                    if (!empty($value)) {
                        $normalized = $this->normalizePhone($value);
                        $meta_query[] = [
                            'key' => '_phone',
                            'value' => $normalized,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'auth_provider':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_auth_provider',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'google_id':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_google_id',
                            'value' => $value,
                            'compare' => '='
                        ];
                    }
                    break;
                    
                case 'is_guest':
                    $meta_query[] = [
                        'key' => '_is_guest',
                        'value' => (bool) $value,
                        'compare' => '='
                    ];
                    break;
                    
                case 'is_active':
                    $meta_query[] = [
                        'key' => '_is_active',
                        'value' => (bool) $value,
                        'compare' => '='
                    ];
                    break;
                    
                case 'name':
                    // Search in post title (which contains name)
                    $search_query['s'] = $value;
                    break;
                    
                case 'phone_like':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_phone',
                            'value' => $value,
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'email_like':
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => '_email',
                            'value' => $value,
                            'compare' => 'LIKE'
                        ];
                    }
                    break;
                    
                case 'min_loyalty_points':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_loyalty_points_balance',
                            'value' => (float) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'min_total_spent':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_total_spent',
                            'value' => (float) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'min_orders':
                    if (is_numeric($value)) {
                        $meta_query[] = [
                            'key' => '_total_orders',
                            'value' => (int) $value,
                            'compare' => '>='
                        ];
                    }
                    break;
                    
                case 'registered_after':
                    $date_query[] = [
                        'after' => $value,
                        'inclusive' => true,
                    ];
                    break;
                    
                case 'registered_before':
                    $date_query[] = [
                        'before' => $value,
                        'inclusive' => true,
                    ];
                    break;
            }
        }

        $query_args = [
            'post_type' => CustomerPostType::POST_TYPE,
            'post_status' => 'publish',
            'fields' => 'ids',
            'no_found_rows' => true,
            'offset' => $offset,
        ];

        if ($limit !== null && $limit > 0) {
            $query_args['posts_per_page'] = $limit;
        } else {
            $query_args['posts_per_page'] = -1;
        }

        if (!empty($meta_query) && count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        if (!empty($search_query)) {
            $query_args = array_merge($query_args, $search_query);
        }

        if (!empty($date_query)) {
            $query_args['date_query'] = $date_query;
        }

        $query = new WP_Query($query_args);

        $customers = [];
        foreach ($query->posts as $post_id) {
            $customer = $this->get((int) $post_id);
            if ($customer) {
                $customers[] = $customer;
            }
        }

        return $customers;
    }

    /**
     * Count customers by criteria
     */
    public function countBy(array $criteria): int
    {
        $customers = $this->findBy($criteria);
        return count($customers);
    }

    /**
     * Check if customer exists
     */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $post = get_post($id);
        return $post && $post->post_type === CustomerPostType::POST_TYPE;
    }

    /**
     * Find customer by email
     */
    public function findByEmail(string $email): ?Customer
    {
        $customers = $this->findBy(['email' => $email], 1);
        return $customers[0] ?? null;
    }

    /**
     * Find customer by phone
     */
    public function findByPhone(string $phone): ?Customer
    {
        $customers = $this->findBy(['phone' => $phone], 1);
        return $customers[0] ?? null;
    }

    /**
     * Find customer by Google ID
     */
    public function findByGoogleId(string $googleId): ?Customer
    {
        $customers = $this->findBy(['google_id' => $googleId], 1);
        return $customers[0] ?? null;
    }

    /**
     * Get guest customers (for cleanup)
     */
    public function getGuestCustomers(): array
    {
        return $this->findBy(['is_guest' => true]);
    }

    /**
     * Get customers with loyalty points
     */
    public function getCustomersWithLoyaltyPoints(float $minPoints = 0): array
    {
        $all_customers = $this->findBy(['is_guest' => false]);
        
        return array_filter($all_customers, function($customer) use ($minPoints) {
            return $customer->loyalty_points_balance >= $minPoints;
        });
    }

    /**
     * Validate data for customer creation
     */
    private function validateCreateData(array $data): void
    {
        $required_fields = ['first_name', 'last_name', 'phone', 'auth_provider'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty(trim((string) $data[$field]))) {
                throw new InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }

        // Additional validations
        if (isset($data['email']) && !empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        if (!in_array($data['auth_provider'], ['google', 'phone'], true)) {
            throw new InvalidArgumentException('Invalid auth_provider');
        }
    }

    /**
     * Validate data for customer update
     */
    private function validateUpdateData(array $data): void
    {
        if (isset($data['email']) && !empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        if (isset($data['auth_provider']) && !in_array($data['auth_provider'], ['google', 'phone'], true)) {
            throw new InvalidArgumentException('Invalid auth_provider');
        }

        if (isset($data['loyalty_points_balance']) && (float) $data['loyalty_points_balance'] < 0) {
            throw new InvalidArgumentException('Loyalty points balance cannot be negative');
        }
    }

    /**
     * Save customer meta fields
     */
    private function saveCustomerMeta(int $post_id, array $data): void
    {
        $meta_fields = [
            '_email' => $data['email'] ?? '',
            '_phone' => $data['phone'],
            '_auth_provider' => $data['auth_provider'],
            '_google_id' => $data['google_id'] ?? '',
            '_phone_verified_at' => isset($data['phone_verified_at']) ? $data['phone_verified_at'] : '',
            '_addresses' => $data['addresses'] ?? [],
            '_allow_sms_notifications' => (bool) ($data['allow_sms_notifications'] ?? false),
            '_allow_email_notifications' => (bool) ($data['allow_email_notifications'] ?? false),
            '_order_ids' => $data['order_ids'] ?? [],
            '_total_orders' => (int) ($data['total_orders'] ?? 0),
            '_total_spent' => (float) ($data['total_spent'] ?? 0.0),
            '_last_order_date' => $data['last_order_date'] ?? '',
            '_loyalty_points_balance' => (float) ($data['loyalty_points_balance'] ?? 0.0),
            '_lifetime_points_earned' => (float) ($data['lifetime_points_earned'] ?? 0.0),
            '_staff_labels' => $data['staff_labels'] ?? '',
            '_is_active' => (bool) ($data['is_active'] ?? true),
            '_registration_date' => $data['registration_date'] ?? date('Y-m-d H:i:s'),
            '_is_guest' => (bool) ($data['is_guest'] ?? false),
        ];

        foreach ($meta_fields as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }

    /**
     * Update customer meta fields (only provided fields)
     */
    private function updateCustomerMeta(int $post_id, array $data): void
    {
        $updatable_fields = [
            'email' => '_email',
            'phone' => '_phone',
            'auth_provider' => '_auth_provider',
            'google_id' => '_google_id',
            'phone_verified_at' => '_phone_verified_at',
            'addresses' => '_addresses',
            'allow_sms_notifications' => '_allow_sms_notifications',
            'allow_email_notifications' => '_allow_email_notifications',
            'order_ids' => '_order_ids',
            'total_orders' => '_total_orders',
            'total_spent' => '_total_spent',
            'last_order_date' => '_last_order_date',
            'loyalty_points_balance' => '_loyalty_points_balance',
            'lifetime_points_earned' => '_lifetime_points_earned',
            'staff_labels' => '_staff_labels',
            'is_active' => '_is_active',
            'is_guest' => '_is_guest',
        ];

        foreach ($updatable_fields as $data_key => $meta_key) {
            if (array_key_exists($data_key, $data)) {
                $value = $data[$data_key];
                
                // Type casting for specific fields
                switch ($data_key) {
                    case 'allow_sms_notifications':
                    case 'allow_email_notifications':
                    case 'is_active':
                    case 'is_guest':
                        $value = (bool) $value;
                        break;
                    case 'total_orders':
                        $value = (int) $value;
                        break;
                    case 'total_spent':
                    case 'loyalty_points_balance':
                    case 'lifetime_points_earned':
                        $value = (float) $value;
                        break;
                    case 'order_ids':
                    case 'addresses':
                        $value = is_array($value) ? $value : [];
                        break;
                }
                
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }

    /**
     * Extract customer data from WordPress post
     */
    private function extractCustomerData(WP_Post $post): array
    {
        // Extract name from post title (format: "First Last (phone)")
        $title_parts = explode(' (', $post->post_title);
        $full_name = $title_parts[0];
        $name_parts = explode(' ', $full_name, 2);
        
        return [
            'id' => $post->ID,
            'first_name' => $name_parts[0] ?? '',
            'last_name' => $name_parts[1] ?? '',
            'email' => get_post_meta($post->ID, '_email', true) ?: '',
            'phone' => get_post_meta($post->ID, '_phone', true) ?: '',
            'auth_provider' => get_post_meta($post->ID, '_auth_provider', true) ?: '',
            'google_id' => get_post_meta($post->ID, '_google_id', true) ?: null,
            'phone_verified_at' => get_post_meta($post->ID, '_phone_verified_at', true) ?: null,
            'addresses' => get_post_meta($post->ID, '_addresses', true) ?: [],
            'allow_sms_notifications' => (bool) get_post_meta($post->ID, '_allow_sms_notifications', true),
            'allow_email_notifications' => (bool) get_post_meta($post->ID, '_allow_email_notifications', true),
            'order_ids' => get_post_meta($post->ID, '_order_ids', true) ?: [],
            'total_orders' => (int) get_post_meta($post->ID, '_total_orders', true),
            'total_spent' => (float) get_post_meta($post->ID, '_total_spent', true),
            'last_order_date' => get_post_meta($post->ID, '_last_order_date', true) ?: null,
            'loyalty_points_balance' => (float) get_post_meta($post->ID, '_loyalty_points_balance', true),
            'lifetime_points_earned' => (float) get_post_meta($post->ID, '_lifetime_points_earned', true),
            'staff_labels' => get_post_meta($post->ID, '_staff_labels', true) ?: '',
            'is_active' => (bool) get_post_meta($post->ID, '_is_active', true),
            'registration_date' => get_post_meta($post->ID, '_registration_date', true) ?: $post->post_date,
            'is_guest' => (bool) get_post_meta($post->ID, '_is_guest', true),
        ];
    }

    /**
     * Get order count for customer (for dependency checking)
     */
    private function getCustomerOrderCount(int $customer_id): int
    {
        $order_query = new WP_Query([
            'post_type' => 'order', // Assuming OrderPostType::POST_TYPE will be 'order'
            'post_status' => ['publish', 'private'], // Include all order statuses
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_customer_id',
                    'value' => $customer_id,
                    'compare' => '='
                ]
            ]
        ]);

        return $order_query->found_posts;
    }

    /**
     * Update customer order statistics after new order
     */
    public function updateOrderStats(int $customer_id, int $order_id, float $order_total): bool
    {
        $customer = $this->get($customer_id);
        if (!$customer) {
            return false;
        }

        try {
            $customer->updateOrderStats($order_id, $order_total);
            
            // Save updated stats back to database
            return $this->update($customer_id, [
                'order_ids' => $customer->order_ids,
                'total_orders' => $customer->total_orders,
                'total_spent' => $customer->total_spent,
                'last_order_date' => $customer->last_order_date->format('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log("Failed to update order stats for customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add loyalty points to customer
     */
    public function addLoyaltyPoints(int $customer_id, float $points): bool
    {
        $customer = $this->get($customer_id);
        if (!$customer) {
            return false;
        }

        try {
            $customer->addLoyaltyPoints($points);
            
            return $this->update($customer_id, [
                'loyalty_points_balance' => $customer->loyalty_points_balance,
                'lifetime_points_earned' => $customer->lifetime_points_earned,
            ]);
        } catch (Exception $e) {
            error_log("Failed to add loyalty points for customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Use loyalty points for customer
     */
    public function useLoyaltyPoints(int $customer_id, float $points): bool
    {
        $customer = $this->get($customer_id);
        if (!$customer) {
            return false;
        }

        try {
            if ($customer->useLoyaltyPoints($points)) {
                return $this->update($customer_id, [
                    'loyalty_points_balance' => $customer->loyalty_points_balance,
                ]);
            }
            return false;
        } catch (Exception $e) {
            error_log("Failed to use loyalty points for customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add staff label to customer
     */
    public function addStaffLabel(int $customer_id, string $label): bool
    {
        $customer = $this->get($customer_id);
        if (!$customer) {
            return false;
        }

        try {
            $customer->addStaffLabel($label);
            
            return $this->update($customer_id, [
                'staff_labels' => $customer->staff_labels,
            ]);
        } catch (Exception $e) {
            error_log("Failed to add staff label for customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert guest customer to registered
     */
    public function convertGuestToRegistered(int $customer_id, string $email, string $auth_provider, ?string $google_id = null): bool
    {
        $customer = $this->get($customer_id);
        if (!$customer) {
            return false;
        }

        try {
            $customer->convertToRegistered($email, $auth_provider, $google_id);
            
            return $this->update($customer_id, [
                'email' => $customer->email,
                'auth_provider' => $customer->auth_provider,
                'google_id' => $customer->google_id,
                'phone_verified_at' => $customer->phone_verified_at?->format('Y-m-d H:i:s'),
                'is_guest' => $customer->is_guest,
            ]);
        } catch (Exception $e) {
            error_log("Failed to convert guest customer {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cleanup old guest customers (e.g., older than 30 days)
     */
    public function cleanupOldGuests(int $days_old = 30): int
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $old_guests = get_posts([
            'post_type' => CustomerPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [
                [
                    'before' => $cutoff_date,
                    'inclusive' => false,
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_is_guest',
                    'value' => true,
                    'compare' => '='
                ]
            ]
        ]);

        $deleted_count = 0;
        foreach ($old_guests as $guest_id) {
            try {
                if ($this->delete($guest_id, true)) {
                    $deleted_count++;
                }
            } catch (Exception $e) {
                error_log("Failed to delete old guest customer {$guest_id}: " . $e->getMessage());
            }
        }

        return $deleted_count;
    }

    /**
     * Search customers by name, email, or phone
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = sanitize_text_field($query);
        
        if (strlen($query) < 2) {
            return [];
        }

        // Search in post titles (names) and meta fields (email, phone)
        $wp_query = new WP_Query([
            'post_type' => CustomerPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            's' => $query, // Search in post title (names)
        ]);

        $customers_by_name = array_map(fn($id) => $this->get((int) $id), $wp_query->posts);
        $customers_by_name = array_filter($customers_by_name);

        // Also search by email and phone
        $customers_by_email = $this->findBy(['email' => $query], $limit);
        $customers_by_phone = $this->findBy(['phone' => $query], $limit);

        // Merge and deduplicate
        $all_customers = array_merge($customers_by_name, $customers_by_email, $customers_by_phone);
        $unique_customers = [];
        $seen_ids = [];

        foreach ($all_customers as $customer) {
            if (!in_array($customer->id, $seen_ids, true)) {
                $unique_customers[] = $customer;
                $seen_ids[] = $customer->id;
            }
        }

        return array_slice($unique_customers, 0, $limit);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        
        if (empty($phone)) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }
        
        // Remove all non-digit characters except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        if (empty($cleanPhone)) {
            throw new InvalidArgumentException('Phone number must contain digits');
        }
        
        // Israeli phone number validation
        if (str_starts_with($cleanPhone, '+972')) {
            // Full international format
            if (strlen($cleanPhone) !== 13) {
                throw new InvalidArgumentException('Israeli phone number with +972 must be 13 digits total');
            }
        } elseif (str_starts_with($cleanPhone, '0')) {
            // Local format starting with 0
            if (strlen($cleanPhone) !== 10) {
                throw new InvalidArgumentException('Israeli phone number starting with 0 must be 10 digits');
            }
            // Convert to international format
            $cleanPhone = '+972' . substr($cleanPhone, 1);
        } else {
            throw new InvalidArgumentException('Phone number must start with +972 or 0 for Israeli numbers');
        }
        
        return $cleanPhone;
    }
}