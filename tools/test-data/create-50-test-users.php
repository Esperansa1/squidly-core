<?php
/**
 * Create 50 Test Users
 *
 * Generates 50 test users with:
 * - Different roles (administrator, restaurant_manager, restaurant_staff)
 * - Realistic Hebrew names
 * - Unique usernames and emails
 * - Random registration dates over past 180 days
 *
 * Usage: Access this file via browser while logged in as admin, or run via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../../../wp-load.php');
}

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>👥 Creating 50 Test Users</h1>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; } h1 { color: #2271b1; } h2 { color: #50575e; margin-top: 20px; } .success { color: #00a32a; } .error { color: #d63638; } .info { color: #2c3338; margin: 10px 0; } table { border-collapse: collapse; width: 100%; margin-top: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: right; } th { background-color: #f0f0f1; }</style>";

// Hebrew first names
$firstNames = [
    'דוד', 'משה', 'יוסף', 'דניאל', 'אבי', 'אברהם', 'יצחק', 'יעקב', 'שמואל', 'נתן',
    'שרה', 'רחל', 'לאה', 'רבקה', 'מרים', 'דינה', 'תמר', 'רות', 'אסתר', 'חנה',
    'אריאל', 'נועם', 'עומר', 'יונתן', 'איתי', 'רועי', 'עידן', 'גיא', 'עדי', 'שי',
    'מיכל', 'נועה', 'תמר', 'יעל', 'שירה', 'הדס', 'ליאל', 'מאיה', 'עדן', 'רוני'
];

// Hebrew last names
$lastNames = [
    'כהן', 'לוי', 'מזרחי', 'פרץ', 'ביטון', 'אוחיון', 'אברהם', 'יוסף', 'דוד', 'חיים',
    'בן דוד', 'אלמוג', 'שפירא', 'רוזנברג', 'שטרן', 'גולדברג', 'פרידמן', 'כץ', 'שוורץ', 'ברק',
    'אבוקסיס', 'אזולאי', 'עזרא', 'דהן', 'בוזגלו', 'מלכה', 'עטיה', 'סעדון', 'זוהר', 'ששון'
];

// Role distribution (will create a mix of roles)
$roles = [
    'restaurant_staff' => 30,      // 30 staff members
    'restaurant_manager' => 15,    // 15 managers
    'administrator' => 5           // 5 administrators
];

$successCount = 0;
$errorCount = 0;
$createdUsers = [];

echo "<h2>🚀 Starting User Creation</h2>";
echo "<div class='info'>Target: 50 users</div>";

$userNumber = 1;
$startTime = microtime(true);

foreach ($roles as $role => $count) {
    echo "<h3>Creating {$count} {$role}(s)...</h3>";

    for ($i = 0; $i < $count; $i++) {
        // Generate random name
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $displayName = "{$firstName} {$lastName}";

        // Generate unique username (transliterated to English)
        $baseUsername = 'testuser' . str_pad($userNumber, 3, '0', STR_PAD_LEFT);
        $username = $baseUsername;

        // Ensure username is unique
        $usernameAttempt = 1;
        while (username_exists($username)) {
            $username = $baseUsername . '_' . $usernameAttempt;
            $usernameAttempt++;
        }

        // Generate unique email
        $email = $username . '@squidly-test.local';
        $emailAttempt = 1;
        while (email_exists($email)) {
            $email = $username . '_' . $emailAttempt . '@squidly-test.local';
            $emailAttempt++;
        }

        // Random registration date (past 180 days)
        $daysAgo = rand(1, 180);
        $registrationDate = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

        // Create user
        $userData = [
            'user_login' => $username,
            'user_pass' => 'Test123456!', // Default password for all test users
            'user_email' => $email,
            'display_name' => $displayName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'user_registered' => $registrationDate
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            echo "<div class='error'>❌ Error creating user {$username}: " . $userId->get_error_message() . "</div>";
            $errorCount++;
        } else {
            echo "<div class='success'>✅ Created: {$displayName} ({$username}) - {$role}</div>";
            $successCount++;

            $createdUsers[] = [
                'id' => $userId,
                'username' => $username,
                'display_name' => $displayName,
                'email' => $email,
                'role' => $role,
                'registered' => $registrationDate
            ];
        }

        $userNumber++;
    }
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "<h2>📊 Summary</h2>";
echo "<div class='info'>";
echo "<strong>Total Created:</strong> {$successCount} users<br>";
echo "<strong>Errors:</strong> {$errorCount}<br>";
echo "<strong>Duration:</strong> {$duration} seconds<br>";
echo "<strong>Default Password:</strong> Test123456!<br>";
echo "</div>";

// Display created users in a table
if (!empty($createdUsers)) {
    echo "<h2>📋 Created Users</h2>";
    echo "<table>";
    echo "<thead><tr><th>ID</th><th>Username</th><th>Display Name</th><th>Email</th><th>Role</th><th>Registered</th></tr></thead>";
    echo "<tbody>";

    foreach ($createdUsers as $user) {
        $roleName = '';
        switch ($user['role']) {
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
                $roleName = $user['role'];
        }

        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['display_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$roleName}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($user['registered'])) . "</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
}

echo "<h2>✅ Done!</h2>";
echo "<div class='info'>";
echo "You can now test the pagination in the User Management page.<br>";
echo "All test users have the password: <strong>Test123456!</strong><br>";
echo "</div>";

echo "<hr>";
echo "<h3>🧹 Cleanup</h3>";
echo "<div class='info'>";
echo "To delete all test users created by this script, you can use the WordPress Users admin page<br>";
echo "or run a SQL query to delete users with usernames starting with 'testuser'.<br>";
echo "</div>";
