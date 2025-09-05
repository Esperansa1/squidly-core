<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo get_bloginfo('name'); ?> - ניהול מסעדה</title>
    
    <?php
    // Load built assets
    $plugin_url = plugin_dir_url(__FILE__) . '../../';
    $dist_path = plugin_dir_path(__FILE__) . '../../admin-app/dist/';
    
    // Check if built assets exist
    if (file_exists($dist_path . 'assets/')) {
        $assets = glob($dist_path . 'assets/*.css');
        foreach ($assets as $css) {
            $css_url = $plugin_url . 'admin-app/dist/assets/' . basename($css);
            echo '<link rel="stylesheet" href="' . $css_url . '">';
        }
        
        $assets = glob($dist_path . 'assets/*.js');
        foreach ($assets as $js) {
            $js_url = $plugin_url . 'admin-app/dist/assets/' . basename($js);
            echo '<script type="module" src="' . $js_url . '"></script>';
        }
    } else {
        // Development mode - use Vite dev server
        echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
        echo '<script type="module" src="http://localhost:5173/src/main.jsx"></script>';
    }
    ?>
    
    <script>
        // Provide WordPress config to the React app
        window.wpConfig = {
            apiUrl: '<?php echo rest_url('squidly/v1/'); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            wpPath: '<?php echo parse_url(site_url(), PHP_URL_PATH) ?: ''; ?>',
            pluginUrl: '<?php echo plugin_dir_url(__FILE__) . '../../'; ?>',
            user: {
                id: <?php echo get_current_user_id(); ?>,
                can_manage: <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>
            }
        };
        
        // Debug logging
        console.log('WordPress Config:', window.wpConfig);
        console.log('Current User ID:', <?php echo get_current_user_id(); ?>);
        console.log('Can Manage Options:', <?php echo current_user_can('manage_options') ? 'true' : 'false'; ?>);
        
        // Error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            document.getElementById('squidly-admin').innerHTML = '<div style="padding: 20px; color: red;">שגיאה בטעינת הממשק: ' + e.error.message + '</div>';
        });
        
        // Check if React app loaded after timeout
        setTimeout(function() {
            const loadingElement = document.querySelector('.loading');
            if (loadingElement && loadingElement.style.display !== 'none') {
                console.error('React app failed to load within 10 seconds');
                document.getElementById('squidly-admin').innerHTML = '<div style="padding: 20px; color: red;">שגיאה: הממשק לא נטען. בדוק את הקונסולה לפרטים נוספים.</div>';
            }
        }, 10000);
    </script>
    
    <style>
        body { margin: 0; }
        #squidly-admin { min-height: 100vh; }
        .loading { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            font-family: system-ui, -apple-system, sans-serif;
            color: #666;
        }
    </style>
</head>
<body>
    <div id="squidly-admin">
        <div class="loading">טוען ממשק ניהול...</div>
    </div>
</body>
</html>