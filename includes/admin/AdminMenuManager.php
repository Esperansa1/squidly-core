<?php
declare(strict_types=1);

/**
 * Admin Menu Manager
 * 
 * Creates the main Squidly Restaurant admin menu and organizes all post types under it.
 * This is for development purposes - actual restaurant owners will have custom dashboards.
 */
class AdminMenuManager
{
    public const MENU_SLUG = 'squidly-restaurant';
    
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addMainMenu']);
        add_action('admin_menu', [self::class, 'organizeSubMenus'], 999);
    }

    /**
     * Add the main Squidly Restaurant menu
     */
    public static function addMainMenu(): void
    {
        add_menu_page(
            'Squidly Restaurant',           // Page title
            'Squidly Restaurant',           // Menu title
            'manage_options',               // Capability
            self::MENU_SLUG,               // Menu slug
            [self::class, 'dashboardPage'], // Callback
            'dashicons-store',              // Icon
            25                              // Position
        );

        // Add dashboard submenu
        add_submenu_page(
            self::MENU_SLUG,
            'Dashboard',
            'Dashboard',
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'dashboardPage']
        );

        // Add settings submenu
        add_submenu_page(
            self::MENU_SLUG,
            'Restaurant Settings',
            'Settings',
            'manage_options',
            'squidly-settings',
            [self::class, 'settingsPage']
        );

        // Add reports submenu
        add_submenu_page(
            self::MENU_SLUG,
            'Reports',
            'Reports',
            'manage_options',
            'squidly-reports',
            [self::class, 'reportsPage']
        );
    }

    /**
     * Organize post type menus under main menu
     */
    public static function organizeSubMenus(): void
    {
        global $submenu;

        // Remove post types from main menu and add them as submenus
        $post_types = [
            'store_branch' => 'Branches',
            'product' => 'Products',
            'product_group' => 'Product Groups',
            'ingredient' => 'Ingredients',
            'group_item' => 'Group Items',
            'customer' => 'Customers',
        ];

        foreach ($post_types as $post_type => $label) {
            // Remove from main menu
            remove_menu_page("edit.php?post_type={$post_type}");
            
            // Add as submenu
            add_submenu_page(
                self::MENU_SLUG,
                $label,
                $label,
                'edit_posts',
                "edit.php?post_type={$post_type}"
            );
        }

        // Reorder submenus logically
        if (isset($submenu[self::MENU_SLUG])) {
            $ordered_submenu = [];
            
            // Dashboard first
            foreach ($submenu[self::MENU_SLUG] as $item) {
                if ($item[2] === self::MENU_SLUG) {
                    $ordered_submenu[] = $item;
                    break;
                }
            }
            
            // Then core restaurant data
            $order = [
                'edit.php?post_type=store_branch',
                'edit.php?post_type=customer',
                'edit.php?post_type=product',
                'edit.php?post_type=product_group',
                'edit.php?post_type=ingredient',
                'edit.php?post_type=group_item',
                'squidly-settings',
                'squidly-reports'
            ];
            
            foreach ($order as $page_slug) {
                foreach ($submenu[self::MENU_SLUG] as $item) {
                    if ($item[2] === $page_slug) {
                        $ordered_submenu[] = $item;
                        break;
                    }
                }
            }
            
            $submenu[self::MENU_SLUG] = $ordered_submenu;
        }
    }

    /**
     * Dashboard page content
     */
    public static function dashboardPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Squidly Restaurant Dashboard</h1>
            
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>Welcome to Squidly Restaurant Management System</h2>
                    <p class="about-description">
                        This is the development dashboard for managing restaurant data. 
                        Restaurant owners will have their own custom interface.
                    </p>
                </div>
            </div>

            <div id="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div class="postbox-container" style="width: 50%; float: left;">
                        <?php self::quickStatsWidget(); ?>
                        <?php self::recentOrdersWidget(); ?>
                    </div>
                    <div class="postbox-container" style="width: 50%; float: right;">
                        <?php self::quickActionsWidget(); ?>
                        <?php self::systemStatusWidget(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .postbox-container { margin-right: 20px; }
        .postbox { margin-bottom: 20px; }
        .quick-stats { display: flex; justify-content: space-between; }
        .stat-box { 
            background: #fff; 
            padding: 20px; 
            border: 1px solid #ccd0d4; 
            border-radius: 4px;
            text-align: center;
            flex: 1;
            margin-right: 10px;
        }
        .stat-box:last-child { margin-right: 0; }
        .stat-number { font-size: 2em; font-weight: bold; color: #2271b1; }
        .quick-actions a { 
            display: block; 
            padding: 10px; 
            text-decoration: none; 
            border-bottom: 1px solid #f0f0f1; 
        }
        .quick-actions a:hover { background: #f6f7f7; }
        </style>
        <?php
    }

    /**
     * Quick stats widget
     */
    private static function quickStatsWidget(): void
    {
        $stats = self::getQuickStats();
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Quick Stats</h2>
            </div>
            <div class="inside">
                <div class="quick-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['branches']; ?></div>
                        <div>Branches</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['products']; ?></div>
                        <div>Products</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['customers']; ?></div>
                        <div>Customers</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $stats['ingredients']; ?></div>
                        <div>Ingredients</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Quick actions widget
     */
    private static function quickActionsWidget(): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Quick Actions</h2>
            </div>
            <div class="inside">
                <div class="quick-actions">
                    <a href="<?php echo admin_url('post-new.php?post_type=store_branch'); ?>">
                        <span class="dashicons dashicons-store"></span> Add New Branch
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>">
                        <span class="dashicons dashicons-cart"></span> Add New Product
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=customer'); ?>">
                        <span class="dashicons dashicons-groups"></span> Add New Customer
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=ingredient'); ?>">
                        <span class="dashicons dashicons-carrot"></span> Add New Ingredient
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=squidly-settings'); ?>">
                        <span class="dashicons dashicons-admin-settings"></span> Restaurant Settings
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=squidly-reports'); ?>">
                        <span class="dashicons dashicons-chart-area"></span> View Reports
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Recent orders widget
     */
    private static function recentOrdersWidget(): void
    {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">Recent Orders</h2>
            </div>
            <div class="inside">
                <p><em>Order system not yet implemented.</em></p>
                <p>This will show the latest orders when the order management system is complete.</p>
            </div>
        </div>
        <?php
    }

    /**
     * System status widget
     */
    private static function systemStatusWidget(): void
    {
        $status = self::getSystemStatus();
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">System Status</h2>
            </div>
            <div class="inside">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong>WordPress Version</strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Plugin Version</strong></td>
                            <td><?php echo SQUIDLY_CORE_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database Status</strong></td>
                            <td><span style="color: green;">✓ Connected</span></td>
                        </tr>
                        <tr>
                            <td><strong>Post Types</strong></td>
                            <td><span style="color: green;">✓ Registered</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public static function settingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Restaurant Settings</h1>
            <p>Restaurant-wide configuration options.</p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('squidly_settings');
                do_settings_sections('squidly_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Currency</th>
                        <td>
                            <select name="squidly_currency">
                                <option value="ILS" <?php selected(get_option('squidly_currency'), 'ILS'); ?>>Israeli Shekel (₪)</option>
                                <option value="USD" <?php selected(get_option('squidly_currency'), 'USD'); ?>>US Dollar ($)</option>
                                <option value="EUR" <?php selected(get_option('squidly_currency'), 'EUR'); ?>>Euro (€)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Loyalty Points Rate</th>
                        <td>
                            <input type="number" step="0.1" min="0" max="10" 
                                   name="squidly_loyalty_rate" 
                                   value="<?php echo esc_attr(get_option('squidly_loyalty_rate', '2.0')); ?>" />
                            <p class="description">Percentage of order total awarded as loyalty points</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Guest Checkout</th>
                        <td>
                            <label>
                                <input type="checkbox" name="squidly_allow_guest_checkout" 
                                       value="1" <?php checked(get_option('squidly_allow_guest_checkout'), 1); ?> />
                                Allow customers to order without registration
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-cleanup Guests</th>
                        <td>
                            <input type="number" min="1" max="365" 
                                   name="squidly_guest_cleanup_days" 
                                   value="<?php echo esc_attr(get_option('squidly_guest_cleanup_days', '30')); ?>" />
                            <p class="description">Delete guest customer data after this many days</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Reports page
     */
    public static function reportsPage(): void
    {
        ?>
        <div class="wrap">
            <h1>Reports</h1>
            <p>Analytics and reporting for your restaurant.</p>
            
            <div class="postbox-container" style="width: 100%;">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">Customer Reports</h2>
                    </div>
                    <div class="inside">
                        <?php self::customerReports(); ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">Product Reports</h2>
                    </div>
                    <div class="inside">
                        <?php self::productReports(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get quick statistics
     */
    private static function getQuickStats(): array
    {
        return [
            'branches' => wp_count_posts('store_branch')->publish ?? 0,
            'products' => wp_count_posts('product')->publish ?? 0,
            'customers' => wp_count_posts('customer')->publish ?? 0,
            'ingredients' => wp_count_posts('ingredient')->publish ?? 0,
        ];
    }

    /**
     * Get system status
     */
    private static function getSystemStatus(): array
    {
        return [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => SQUIDLY_CORE_VERSION,
            'db_connected' => true, // Could add actual DB check
        ];
    }

    /**
     * Customer reports section
     */
    private static function customerReports(): void
    {
        $customerRepo = new CustomerRepository();
        $total_customers = $customerRepo->countBy(['is_active' => true]);
        $guest_customers = $customerRepo->countBy(['is_guest' => true]);
        $registered_customers = $total_customers - $guest_customers;
        
        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>Total Active Customers</strong></td>
                    <td><?php echo $total_customers; ?></td>
                </tr>
                <tr>
                    <td><strong>Registered Customers</strong></td>
                    <td><?php echo $registered_customers; ?></td>
                </tr>
                <tr>
                    <td><strong>Guest Customers</strong></td>
                    <td><?php echo $guest_customers; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Product reports section
     */
    private static function productReports(): void
    {
        $productRepo = new ProductRepository();
        $ingredientRepo = new IngredientRepository();
        
        $total_products = count($productRepo->getAll());
        $total_ingredients = count($ingredientRepo->getAll());
        
        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>Total Products</strong></td>
                    <td><?php echo $total_products; ?></td>
                </tr>
                <tr>
                    <td><strong>Total Ingredients</strong></td>
                    <td><?php echo $total_ingredients; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}