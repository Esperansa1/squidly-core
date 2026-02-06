<?php
/**
 * Delete Test Users
 *
 * Deletes all test users created by create-50-test-users.php
 * (users with username starting with "testuser")
 *
 * Usage: Access this file via browser while logged in as admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>🗑️ Delete Test Users</h1>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } h1 { color: #d63638; } h2 { color: #50575e; margin-top: 20px; } .success { color: #00a32a; } .error { color: #d63638; } .warning { color: #dba617; } .info { color: #2c3338; margin: 10px 0; } table { border-collapse: collapse; width: 100%; margin-top: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: right; } th { background-color: #f0f0f1; }</style>";

// Check if deletion is confirmed
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirmed) {
    // Show confirmation page
    echo "<h2>⚠️ Warning</h2>";
    echo "<div class='warning'>";
    echo "This will delete ALL users with usernames starting with 'testuser'.<br>";
    echo "This action cannot be undone!<br><br>";
    echo "</div>";

    // Find and display test users
    $testUsers = get_users([
        'search' => 'testuser*',
        'search_columns' => ['user_login'],
        'number' => -1
    ]);

    if (empty($testUsers)) {
        echo "<div class='info'>No test users found.</div>";
    } else {
        echo "<div class='info'><strong>Found " . count($testUsers) . " test user(s):</strong></div>";
        echo "<table>";
        echo "<thead><tr><th>ID</th><th>Username</th><th>Display Name</th><th>Email</th><th>Role</th><th>Registered</th></tr></thead>";
        echo "<tbody>";

        foreach ($testUsers as $user) {
            $userRoles = $user->roles;
            $role = !empty($userRoles) ? $userRoles[0] : 'No role';

            $roleName = '';
            switch ($role) {
                case 'administrator':
                    $roleName = 'מנהל';
                    break;
                case 'restaurant_manager':
                    $roleName = 'מנהל מסעדה';
                    break;
                case 'restaurant_staff':
                    $roleName = 'צוות';
                    break;
                default:
                    $roleName = $role;
            }

            echo "<tr>";
            echo "<td>{$user->ID}</td>";
            echo "<td>{$user->user_login}</td>";
            echo "<td>{$user->display_name}</td>";
            echo "<td>{$user->user_email}</td>";
            echo "<td>{$roleName}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($user->user_registered)) . "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";

        echo "<div style='margin-top: 30px;'>";
        echo "<a href='?confirm=yes' style='background: #d63638; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;'>🗑️ Confirm Delete All Test Users</a> ";
        echo "<a href='javascript:history.back()' style='background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block;'>← Cancel</a>";
        echo "</div>";
    }
} else {
    // Perform deletion
    echo "<h2>🚀 Starting Deletion</h2>";

    $testUsers = get_users([
        'search' => 'testuser*',
        'search_columns' => ['user_login'],
        'number' => -1
    ]);

    if (empty($testUsers)) {
        echo "<div class='info'>No test users found to delete.</div>";
    } else {
        $successCount = 0;
        $errorCount = 0;
        $currentUserId = get_current_user_id();

        foreach ($testUsers as $user) {
            // Skip if trying to delete current user
            if ($user->ID === $currentUserId) {
                echo "<div class='warning'>⚠️ Skipped: {$user->user_login} (cannot delete current user)</div>";
                $errorCount++;
                continue;
            }

            // Delete user and reassign their content to current user
            $deleted = wp_delete_user($user->ID, $currentUserId);

            if ($deleted) {
                echo "<div class='success'>✅ Deleted: {$user->user_login} ({$user->display_name})</div>";
                $successCount++;
            } else {
                echo "<div class='error'>❌ Error deleting: {$user->user_login}</div>";
                $errorCount++;
            }
        }

        echo "<h2>📊 Summary</h2>";
        echo "<div class='info'>";
        echo "<strong>Total Deleted:</strong> {$successCount} users<br>";
        echo "<strong>Errors/Skipped:</strong> {$errorCount}<br>";
        echo "</div>";

        echo "<h2>✅ Done!</h2>";
        echo "<div class='info'>";
        echo "All test users have been deleted.<br>";
        echo "<a href='delete-test-users.php' style='color: #2271b1;'>Run again</a> to check if any remain.<br>";
        echo "</div>";
    }
}
