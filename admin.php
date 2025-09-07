<?php
/**
 * Squidly Admin Interface Entry Point
 * 
 * Completely decoupled admin interface that communicates via REST API
 * Users will not know this runs on WordPress
 */

// Load WordPress environment for API access
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php', 
        '../../../../../wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            require_once __DIR__ . '/' . $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress environment not found');
    }
}

// Security check - ensure user can manage options
if (!current_user_can('manage_options')) {
    // Redirect to WordPress login
    wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    exit;
}

// Get plugin info
$plugin_url = plugin_dir_url(__FILE__);
$plugin_path = plugin_dir_path(__FILE__);
$assets_url = $plugin_url . 'admin-app/dist/assets/';

// Check if built assets exist (with hashed names)
$dist_path = $plugin_path . 'admin-app/dist/assets/';
$js_files = glob($dist_path . 'main-*.js');
$css_files = glob($dist_path . 'main-*.css');

$has_built_assets = !empty($js_files) && !empty($css_files);
$js_file = $has_built_assets ? basename($js_files[0]) : '';
$css_file = $has_built_assets ? basename($css_files[0]) : '';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Squidly Admin - ניהול מסעדה</title>
  
  <!-- Primary Meta Tags -->
  <meta name="title" content="Squidly Admin - ניהול מסעדה">
  <meta name="description" content="מערכת ניהול מסעדה מתקדמת עם תמיכה בעברית">
  
  <!-- Theme colors for mobile browsers -->
  <meta name="theme-color" content="#D12525">
  <meta name="msapplication-TileColor" content="#D12525">
  
  <!-- Preload critical resources -->
  <link rel="preconnect" href="<?php echo get_site_url(); ?>">
  
  <?php if ($has_built_assets): ?>
    <!-- Built CSS -->
    <link rel="stylesheet" href="<?php echo $assets_url . $css_file; ?>">
  <?php else: ?>
    <!-- Development mode - load Tailwind from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
  <?php endif; ?>
  
  <!-- Minimal WordPress integration styles -->
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: system-ui, -apple-system, sans-serif;
    }
    
    #squidly-admin-root {
      min-height: 100vh;
    }
  </style>
</head>
<body>
  <!-- React App Container -->
  <div id="squidly-admin-root"></div>
  
  <!-- Configuration for React App -->
  <script>
    window.SQUIDLY_CONFIG = {
      apiUrl: '<?php echo rest_url('squidly/v1/'); ?>',
      nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
      baseUrl: '<?php echo get_site_url(); ?>',
      assetsUrl: '<?php echo $plugin_url; ?>',
      userId: <?php echo get_current_user_id(); ?>,
      userCan: {
        manageOptions: <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>
      }
    };
    
    // Global error handler for debugging
    window.addEventListener('error', function(event) {
      console.error('Global error:', event.error);
    });
  </script>
  
  <?php if ($has_built_assets): ?>
    <!-- Built JavaScript -->
    <script src="<?php echo $assets_url . $js_file; ?>"></script>
  <?php else: ?>
    <!-- Development mode -->
    <script>
      console.warn('Admin assets not built. Run: npm run build in admin-app directory');
    </script>
  <?php endif; ?>
</body>
</html>