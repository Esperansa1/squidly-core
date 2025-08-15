<?php
declare(strict_types=1);

class AdminEnhancements
{
    public static function init(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminScripts']);
        add_filter('manage_edit-customer_columns', [self::class, 'customerListColumns']);
        add_action('manage_customer_posts_custom_column', [self::class, 'customerListColumnContent'], 10, 2);
        add_filter('manage_edit-product_columns', [self::class, 'productListColumns']);
        add_action('manage_product_posts_custom_column', [self::class, 'productListColumnContent'], 10, 2);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueueAdminScripts($hook): void
    {
        // Only load on our post type pages
        $post_types = PostTypeRegistry::getRegisteredPostTypes();
        $current_screen = get_current_screen();
        
        if (!$current_screen || !PostTypeRegistry::isSquidlyPostType($current_screen->post_type)) {
            return;
        }

        wp_enqueue_style(
            'squidly-admin',
            SQUIDLY_CORE_URL . 'assets/css/admin.css',
            [],
            SQUIDLY_CORE_VERSION
        );

        wp_enqueue_script(
            'squidly-admin',
            SQUIDLY_CORE_URL . 'assets/js/admin.js',
            ['jquery'],
            SQUIDLY_CORE_VERSION,
            true
        );

        wp_localize_script('squidly-admin', 'squidlyAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('squidly_admin_nonce'),
        ]);
    }

    /**
     * Customize customer list columns
     */
    public static function customerListColumns($columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['customer_phone'] = 'Phone';
                $new_columns['customer_type'] = 'Type';
                $new_columns['customer_orders'] = 'Orders';
                $new_columns['customer_loyalty'] = 'Loyalty Points';
            }
        }
        
        return $new_columns;
    }

    /**
     * Customer list column content
     */
    public static function customerListColumnContent($column, $post_id): void
    {
        switch ($column) {
            case 'customer_phone':
                echo esc_html(get_post_meta($post_id, '_phone', true));
                break;
                
            case 'customer_type':
                $is_guest = get_post_meta($post_id, '_is_guest', true);
                if ($is_guest) {
                    echo '<span style="color: #666;">Guest</span>';
                } else {
                    echo '<span style="color: #2271b1;">Registered</span>';
                }
                break;
                
            case 'customer_orders':
                $total_orders = get_post_meta($post_id, '_total_orders', true) ?: 0;
                $total_spent = get_post_meta($post_id, '_total_spent', true) ?: 0;
                echo $total_orders . ' orders<br>';
                echo '<small>₪' . number_format((float)$total_spent, 2) . ' total</small>';
                break;
                
            case 'customer_loyalty':
                $is_guest = get_post_meta($post_id, '_is_guest', true);
                if ($is_guest) {
                    echo '<span style="color: #666;">N/A</span>';
                } else {
                    $points = get_post_meta($post_id, '_loyalty_points_balance', true) ?: 0;
                    echo number_format((float)$points, 1) . ' pts';
                }
                break;
        }
    }

    /**
     * Customize product list columns
     */
    public static function productListColumns($columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['product_price'] = 'Price';
                $new_columns['product_category'] = 'Category';
                $new_columns['product_groups'] = 'Groups';
            }
        }
        
        return $new_columns;
    }

    /**
     * Product list column content
     */
    public static function productListColumnContent($column, $post_id): void
    {
        switch ($column) {
            case 'product_price':
                $regular_price = get_post_meta($post_id, '_regular_price', true);
                $sale_price = get_post_meta($post_id, '_sale_price', true);
                
                if ($sale_price) {
                    echo '<del>₪' . number_format((float)$regular_price, 2) . '</del><br>';
                    echo '<strong>₪' . number_format((float)$sale_price, 2) . '</strong>';
                } else {
                    echo '₪' . number_format((float)$regular_price, 2);
                }
                break;
                
            case 'product_category':
                echo esc_html(get_post_meta($post_id, '_category', true));
                break;
                
            case 'product_groups':
                $group_ids = get_post_meta($post_id, '_product_group_ids', true) ?: [];
                if (empty($group_ids)) {
                    echo '<span style="color: #666;">None</span>';
                } else {
                    $group_names = [];
                    foreach ($group_ids as $group_id) {
                        $group = get_post($group_id);
                        if ($group) {
                            $group_names[] = $group->post_title;
                        }
                    }
                    echo esc_html(implode(', ', $group_names));
                }
                break;
        }
    }
}

// Initialize admin enhancements
AdminEnhancements::init();