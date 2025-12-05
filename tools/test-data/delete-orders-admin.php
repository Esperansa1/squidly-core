<?php
/**
 * Temporary Admin Page to Delete All Orders
 *
 * Usage:
 * 1. Visit: http://squidly.local/wp-admin/admin.php?page=squidly-delete-orders
 * 2. Click the "Delete All Orders" button
 * 3. Delete this file after use for security
 */

add_action('admin_menu', function() {
    add_menu_page(
        'Delete Orders',
        'Delete Orders',
        'manage_options',
        'squidly-delete-orders',
        'squidly_delete_orders_page',
        'dashicons-trash',
        100
    );
});

function squidly_delete_orders_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // Handle deletion if form submitted
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'DELETE') {
        check_admin_referer('squidly_delete_orders');

        echo '<div class="wrap">';
        echo '<h1>Deleting All Orders...</h1>';
        echo '<pre>';

        // Run the deletion script
        include __DIR__ . '/force-delete-all-orders.php';

        echo '</pre>';
        echo '</div>';
        return;
    }

    // Show confirmation form
    ?>
    <div class="wrap">
        <h1>⚠️ Delete All Squidly Orders</h1>

        <div class="notice notice-error" style="padding: 20px; margin: 20px 0;">
            <h2 style="margin-top: 0; color: #d63638;">DANGER ZONE</h2>
            <p><strong>This will permanently delete ALL orders from the database.</strong></p>
            <p>This action:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Cannot be undone</li>
                <li>Deletes all Squidly orders permanently</li>
                <li>Deletes all WooCommerce orders permanently</li>
                <li>Should only be used for testing/development</li>
            </ul>
        </div>

        <form method="post" style="margin: 20px 0;">
            <?php wp_nonce_field('squidly_delete_orders'); ?>

            <p>
                <label for="confirm_delete" style="font-size: 16px;">
                    <strong>Type "DELETE" to confirm:</strong>
                </label>
                <br>
                <input
                    type="text"
                    id="confirm_delete"
                    name="confirm_delete"
                    class="regular-text"
                    placeholder="Type DELETE"
                    style="margin-top: 10px;"
                >
            </p>

            <p>
                <button
                    type="submit"
                    class="button button-primary button-large"
                    style="background: #d63638; border-color: #d63638;"
                >
                    🗑️ Delete All Orders (No Undo!)
                </button>
            </p>
        </form>

        <hr style="margin: 40px 0;">

        <h3>Current Order Count</h3>
        <?php
        // Squidly orders
        $count = wp_count_posts('order');
        $total = 0;
        foreach ($count as $status => $num) {
            $total += $num;
        }
        echo "<p><strong>Squidly Orders:</strong> {$total}</p>";
        echo "<ul>";
        foreach ($count as $status => $num) {
            if ($num > 0) {
                echo "<li>{$status}: {$num}</li>";
            }
        }
        echo "</ul>";

        // WooCommerce orders
        if (class_exists('WooCommerce')) {
            $wc_count = wp_count_posts('shop_order');
            $wc_total = 0;
            foreach ($wc_count as $status => $num) {
                $wc_total += $num;
            }
            echo "<p><strong>WooCommerce Orders:</strong> {$wc_total}</p>";
            echo "<ul>";
            foreach ($wc_count as $status => $num) {
                if ($num > 0) {
                    echo "<li>{$status}: {$num}</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p><em>WooCommerce not active</em></p>";
        }
        ?>
    </div>
    <?php
}
