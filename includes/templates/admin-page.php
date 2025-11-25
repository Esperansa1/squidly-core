<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo get_bloginfo('name'); ?> - ניהול מסעדה</title>

    <?php
    // Enqueue WordPress media uploader (but don't output yet)
    wp_enqueue_media();

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
    
    <style>
        /* Basic reset and layout */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }
        
        #squidly-admin {
            min-height: 100vh;
        }
    </style>
    
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
        /* Hide WordPress admin elements */
        #wpadminbar,
        .wp-admin,
        #wp-admin-bar-root-default,
        body.admin-bar {
            display: none !important;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
        }

        html {
            margin-top: 0 !important;
        }

        #squidly-admin {
            min-height: 100vh;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: system-ui, -apple-system, sans-serif;
            color: #666;
        }
    </style>

    <?php
    // Output only media uploader scripts (without admin bar and other WordPress UI)
    // Remove admin bar
    show_admin_bar(false);

    // Print only the essential styles and scripts
    wp_print_styles();
    wp_print_scripts();
    ?>
</head>
<body>
    <div id="squidly-admin">
        <div class="loading">טוען ממשק ניהול...</div>
    </div>

    <?php
    // Output footer scripts (needed for media uploader)
    wp_print_footer_scripts();

    // Output media templates (required for media uploader to work)
    // These are Underscore.js templates that the media uploader needs
    do_action('admin_footer', '');
    do_action('admin_print_footer_scripts');
    ?>
</body>
</html>