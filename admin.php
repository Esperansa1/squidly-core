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
$assets_url = $plugin_url . 'admin-app/dist/';

// Check if built assets exist
$js_file = $plugin_path . 'admin-app/dist/main.js';
$css_file = $plugin_path . 'admin-app/dist/main.css';

$has_built_assets = file_exists($js_file) && file_exists($css_file);
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
    <link rel="stylesheet" href="<?php echo $assets_url; ?>main.css?v=<?php echo filemtime($css_file); ?>">
  <?php else: ?>
    <!-- Development mode - load Tailwind from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: {
                DEFAULT: '#D12525',
                50: '#F8E6E6',
                500: '#D12525',
                600: '#B01D1D',
                700: '#8F1515',
              }
            }
          }
        }
      }
    </script>
  <?php endif; ?>
  
  <style>
    body {
      font-family: 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Arial', sans-serif;
      margin: 0;
      padding: 0;
      direction: rtl;
      text-align: right;
      background-color: #F2F2F2;
      color: #374151;
    }
    
    .loading-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #F2F2F2;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    
    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #D12525;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-bottom: 20px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .app-loaded .loading-screen {
      display: none;
    }
    
    .error-screen {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #F2F2F2;
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      text-align: center;
    }
    
    .error-screen.show {
      display: flex;
    }
    
    #squidly-admin-root {
      min-height: 100vh;
    }
  </style>
</head>
<body>
  <!-- Loading Screen -->
  <div class="loading-screen" id="loading-screen">
    <div class="loading-spinner"></div>
    <div style="font-size: 16px; color: #6B7280; font-weight: 500;">
      טוען מערכת ניהול המסעדה...
    </div>
  </div>
  
  <!-- Error Screen -->
  <div class="error-screen" id="error-screen">
    <div style="font-size: 48px; color: #EF4444; margin-bottom: 20px;">⚠️</div>
    <h1 style="font-size: 24px; color: #1F2937; margin-bottom: 10px;">שגיאה בטעינת המערכת</h1>
    <p style="font-size: 16px; color: #6B7280; margin-bottom: 30px; max-width: 500px;" id="error-message">
      אירעה שגיאה בטעינת מערכת ניהול המסעדה. אנא נסה שוב או פנה לתמיכה טכנית.
    </p>
    <button onclick="window.location.reload()" style="background-color: #D12525; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer;">
      טען מחדש
    </button>
  </div>
  
  <!-- Main App Container -->
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
    
    // Global error handler
    window.addEventListener('error', function(event) {
      console.error('Global error:', event.error);
      showErrorScreen('שגיאה טכנית: ' + event.error.message);
    });
    
    function showErrorScreen(message) {
      document.getElementById('error-message').textContent = message;
      document.getElementById('error-screen').classList.add('show');
      document.getElementById('loading-screen').style.display = 'none';
    }
    
    function hideLoadingScreen() {
      document.body.classList.add('app-loaded');
    }
    
    // Timeout for loading
    setTimeout(function() {
      if (!document.body.classList.contains('app-loaded')) {
        showErrorScreen('המערכת לא הצליחה להיטען בזמן הקצוב. אנא נסה שוב.');
      }
    }, 15000);
  </script>
  
  <?php if ($has_built_assets): ?>
    <!-- Built JavaScript -->
    <script src="<?php echo $assets_url; ?>main.js?v=<?php echo filemtime($js_file); ?>"></script>
  <?php else: ?>
    <!-- Development mode message -->
    <script>
      console.warn('Admin assets not built. Run: npm run build in admin-app directory');
      document.addEventListener('DOMContentLoaded', function() {
        showErrorScreen('מערכת הניהול דורשת בנייה מחדש. אנא פנה למפתח המערכת.');
      });
    </script>
  <?php endif; ?>
  
  <script>
    // Signal that app loaded successfully
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(hideLoadingScreen, 100);
    });
  </script>
</body>
</html>