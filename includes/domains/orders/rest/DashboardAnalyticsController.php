<?php
declare(strict_types=1);

/**
 * Dashboard Analytics REST Controller
 *
 * Provides comprehensive analytics data for the management dashboard.
 * Includes KPIs, comparisons, charts data, and top products.
 */
class DashboardAnalyticsController extends \WP_REST_Controller
{
    private OrderRepository $orderRepository;
    private CustomerRepository $customerRepository;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->customerRepository = new CustomerRepository();
        $this->namespace = 'squidly/v1';
        $this->rest_base = 'dashboard';
    }

    /**
     * Register the routes for the controller
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/analytics', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_dashboard_analytics'],
                'permission_callback' => [$this, 'get_analytics_permissions_check'],
                'args'                => $this->get_analytics_params(),
            ],
        ]);
    }

    /**
     * Get comprehensive dashboard analytics
     */
    public function get_dashboard_analytics(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();

            // Parse date range from period parameter or custom dates
            $dateRange = $this->parse_date_range($params);
            $currentPeriod = $dateRange['current'];
            $previousPeriod = $dateRange['previous'];

            // Get current period data
            $currentStats = $this->calculate_period_stats($currentPeriod);

            // Get previous period data for comparison
            $previousStats = $this->calculate_period_stats($previousPeriod);

            // Calculate percentage changes
            $comparisons = $this->calculate_comparisons($currentStats, $previousStats);

            // Get revenue chart data (daily breakdown within period)
            $revenueChart = $this->get_revenue_chart_data($currentPeriod);

            // Get orders chart data (daily breakdown within period)
            $ordersChart = $this->get_orders_chart_data($currentPeriod);

            // Get top products
            $topProducts = $this->get_top_products($currentPeriod, (int)($params['top_products_limit'] ?? 10));

            $analytics = [
                'period' => [
                    'current' => $currentPeriod,
                    'previous' => $previousPeriod,
                ],
                'kpis' => [
                    'total_revenue' => [
                        'value' => $currentStats['total_revenue'],
                        'change' => $comparisons['revenue_change'],
                        'change_type' => $comparisons['revenue_change'] >= 0 ? 'increase' : 'decrease',
                    ],
                    'total_orders' => [
                        'value' => $currentStats['total_orders'],
                        'change' => $comparisons['orders_change'],
                        'change_type' => $comparisons['orders_change'] >= 0 ? 'increase' : 'decrease',
                    ],
                    'new_customers' => [
                        'value' => $currentStats['new_customers'],
                        'change' => $comparisons['customers_change'],
                        'change_type' => $comparisons['customers_change'] >= 0 ? 'increase' : 'decrease',
                    ],
                    'average_order_value' => [
                        'value' => $currentStats['average_order_value'],
                        'change' => $comparisons['aov_change'],
                        'change_type' => $comparisons['aov_change'] >= 0 ? 'increase' : 'decrease',
                    ],
                ],
                'charts' => [
                    'revenue' => $revenueChart,
                    'orders' => $ordersChart,
                ],
                'top_products' => $topProducts,
            ];

            return rest_ensure_response($analytics);

        } catch (Exception $e) {
            return new \WP_Error(
                'dashboard_analytics_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Parse date range from request parameters
     */
    private function parse_date_range(array $params): array
    {
        $period = $params['period'] ?? 'this_month';

        if ($period === 'custom' && isset($params['date_from']) && isset($params['date_to'])) {
            $dateFrom = new \DateTime($params['date_from']);
            $dateTo = new \DateTime($params['date_to']);

            // Calculate previous period of same length
            $interval = $dateFrom->diff($dateTo);
            $previousTo = clone $dateFrom;
            $previousTo->modify('-1 day');
            $previousFrom = clone $previousTo;
            $previousFrom->sub($interval);

            return [
                'current' => [
                    'from' => $dateFrom->format('Y-m-d'),
                    'to' => $dateTo->format('Y-m-d'),
                ],
                'previous' => [
                    'from' => $previousFrom->format('Y-m-d'),
                    'to' => $previousTo->format('Y-m-d'),
                ],
            ];
        }

        return $this->get_predefined_period($period);
    }

    /**
     * Get predefined period date ranges
     */
    private function get_predefined_period(string $period): array
    {
        $now = new \DateTime();

        switch ($period) {
            case 'today':
                $currentFrom = clone $now;
                $currentTo = clone $now;
                $previousFrom = (clone $now)->modify('-1 day');
                $previousTo = (clone $now)->modify('-1 day');
                break;

            case 'yesterday':
                $currentFrom = (clone $now)->modify('-1 day');
                $currentTo = (clone $now)->modify('-1 day');
                $previousFrom = (clone $now)->modify('-2 days');
                $previousTo = (clone $now)->modify('-2 days');
                break;

            case 'this_week':
                $currentFrom = (clone $now)->modify('monday this week');
                $currentTo = clone $now;
                $previousFrom = (clone $currentFrom)->modify('-7 days');
                $previousTo = (clone $currentTo)->modify('-7 days');
                break;

            case 'last_week':
                $currentFrom = (clone $now)->modify('monday last week');
                $currentTo = (clone $now)->modify('sunday last week');
                $previousFrom = (clone $currentFrom)->modify('-7 days');
                $previousTo = (clone $currentTo)->modify('-7 days');
                break;

            case 'this_month':
                $currentFrom = (clone $now)->modify('first day of this month');
                $currentTo = clone $now;
                $previousFrom = (clone $now)->modify('first day of last month');
                $previousTo = (clone $now)->modify('last day of last month');
                break;

            case 'last_month':
                $currentFrom = (clone $now)->modify('first day of last month');
                $currentTo = (clone $now)->modify('last day of last month');
                $previousFrom = (clone $now)->modify('first day of last month')->modify('-1 month');
                $previousTo = (clone $now)->modify('last day of last month')->modify('-1 month');
                break;

            default:
                // Default to this month
                $currentFrom = (clone $now)->modify('first day of this month');
                $currentTo = clone $now;
                $previousFrom = (clone $now)->modify('first day of last month');
                $previousTo = (clone $now)->modify('last day of last month');
                break;
        }

        return [
            'current' => [
                'from' => $currentFrom->format('Y-m-d'),
                'to' => $currentTo->format('Y-m-d'),
            ],
            'previous' => [
                'from' => $previousFrom->format('Y-m-d'),
                'to' => $previousTo->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Calculate statistics for a given period
     */
    private function calculate_period_stats(array $period): array
    {
        // Get orders for period
        $orders = $this->orderRepository->findBy([
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ]);

        // Calculate revenue (only from completed/paid orders)
        $paidOrders = array_filter($orders, function($order) {
            return in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_READY, Order::STATUS_PREPARING])
                || $order->payment_status === Order::PAYMENT_PAID;
        });

        $totalRevenue = array_reduce($paidOrders, fn($sum, $order) => $sum + $order->total_amount, 0.0);
        $totalOrders = count($orders);
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;

        // Get new customers for period (query by post_date directly)
        $customerQuery = new \WP_Query([
            'post_type' => 'customer',
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $period['from'],
                    'before' => $period['to'] . ' 23:59:59',
                    'inclusive' => true,
                ],
            ],
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);
        $newCustomers = $customerQuery->found_posts;

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => round($averageOrderValue, 2),
            'new_customers' => $newCustomers,
        ];
    }

    /**
     * Calculate percentage changes between periods
     */
    private function calculate_comparisons(array $current, array $previous): array
    {
        return [
            'revenue_change' => $this->calculate_percentage_change(
                $previous['total_revenue'],
                $current['total_revenue']
            ),
            'orders_change' => $this->calculate_percentage_change(
                $previous['total_orders'],
                $current['total_orders']
            ),
            'customers_change' => $this->calculate_percentage_change(
                $previous['new_customers'],
                $current['new_customers']
            ),
            'aov_change' => $this->calculate_percentage_change(
                $previous['average_order_value'],
                $current['average_order_value']
            ),
        ];
    }

    /**
     * Calculate percentage change
     */
    private function calculate_percentage_change(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100.0 : 0.0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Get revenue chart data (daily breakdown)
     */
    private function get_revenue_chart_data(array $period): array
    {
        $orders = $this->orderRepository->findBy([
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ]);

        // Group by date
        $dailyData = [];
        $dateFrom = new \DateTime($period['from']);
        $dateTo = new \DateTime($period['to']);

        // Initialize all dates in range with 0
        $current = clone $dateFrom;
        while ($current <= $dateTo) {
            $dateKey = $current->format('Y-m-d');
            $dailyData[$dateKey] = [
                'date' => $dateKey,
                'revenue' => 0.0,
                'orders' => 0,
            ];
            $current->modify('+1 day');
        }

        // Aggregate orders by date
        foreach ($orders as $order) {
            $dateKey = (new \DateTime($order->order_date))->format('Y-m-d');

            if (isset($dailyData[$dateKey])) {
                // Only count paid/completed orders
                if (in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_READY, Order::STATUS_PREPARING])
                    || $order->payment_status === Order::PAYMENT_PAID) {
                    $dailyData[$dateKey]['revenue'] += $order->total_amount;
                }
                $dailyData[$dateKey]['orders']++;
            }
        }

        return array_values($dailyData);
    }

    /**
     * Get orders chart data (daily breakdown)
     */
    private function get_orders_chart_data(array $period): array
    {
        $orders = $this->orderRepository->findBy([
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ]);

        // Group by date
        $dailyData = [];
        $dateFrom = new \DateTime($period['from']);
        $dateTo = new \DateTime($period['to']);

        // Initialize all dates in range with 0
        $current = clone $dateFrom;
        while ($current <= $dateTo) {
            $dateKey = $current->format('Y-m-d');
            $dailyData[$dateKey] = [
                'date' => $dateKey,
                'orders' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
            $current->modify('+1 day');
        }

        // Aggregate orders by date and status
        foreach ($orders as $order) {
            $dateKey = (new \DateTime($order->order_date))->format('Y-m-d');

            if (isset($dailyData[$dateKey])) {
                $dailyData[$dateKey]['orders']++;

                if ($order->status === Order::STATUS_COMPLETED) {
                    $dailyData[$dateKey]['completed']++;
                } elseif ($order->status === Order::STATUS_CANCELLED) {
                    $dailyData[$dateKey]['cancelled']++;
                }
            }
        }

        return array_values($dailyData);
    }

    /**
     * Get top selling products
     */
    private function get_top_products(array $period, int $limit): array
    {
        $orders = $this->orderRepository->findBy([
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ]);

        $productStats = [];
        $totalRevenue = 0.0;

        foreach ($orders as $order) {
            // Only count paid/completed orders
            if (!in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_READY, Order::STATUS_PREPARING])
                && $order->payment_status !== Order::PAYMENT_PAID) {
                continue;
            }

            foreach ($order->order_items as $item) {
                $key = $item->product_id;

                if (!isset($productStats[$key])) {
                    $productStats[$key] = [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'unit_price' => $item->unit_price,
                        'total_quantity' => 0,
                        'total_revenue' => 0.0,
                        'revenue_percentage' => 0.0,
                    ];
                }

                $productStats[$key]['total_quantity'] += $item->quantity;
                $productStats[$key]['total_revenue'] += $item->total_price;
                $totalRevenue += $item->total_price;
            }
        }

        // Calculate revenue percentage
        foreach ($productStats as &$stat) {
            $stat['revenue_percentage'] = $totalRevenue > 0
                ? round(($stat['total_revenue'] / $totalRevenue) * 100, 1)
                : 0.0;
            $stat['total_revenue'] = round($stat['total_revenue'], 2);
        }

        // Sort by quantity and limit
        uasort($productStats, fn($a, $b) => $b['total_quantity'] - $a['total_quantity']);

        return array_slice(array_values($productStats), 0, $limit);
    }

    /**
     * Check permissions
     */
    public function get_analytics_permissions_check(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('view_shop_reports'));
    }

    /**
     * Get analytics parameters
     */
    private function get_analytics_params(): array
    {
        return [
            'period' => [
                'description' => __('Time period for analytics.'),
                'type'        => 'string',
                'enum'        => ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'],
                'default'     => 'this_month',
            ],
            'date_from' => [
                'description' => __('Custom start date (Y-m-d format). Required if period is "custom".'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'date_to' => [
                'description' => __('Custom end date (Y-m-d format). Required if period is "custom".'),
                'type'        => 'string',
                'format'      => 'date',
            ],
            'top_products_limit' => [
                'description' => __('Number of top products to return.'),
                'type'        => 'integer',
                'default'     => 10,
                'minimum'     => 1,
                'maximum'     => 50,
            ],
        ];
    }
}
