<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo get_bloginfo('name'); ?> - Order Online</title>

    <?php
    // Load built assets from customer-app
    $plugin_url = plugin_dir_url(__FILE__) . '../../';
    $dist_path = plugin_dir_path(__FILE__) . '../../customer-app/dist/';

    // Check if built assets exist
    if (file_exists($dist_path . 'assets/')) {
        // Load CSS files
        $assets = glob($dist_path . 'assets/*.css');
        foreach ($assets as $css) {
            $css_url = $plugin_url . 'customer-app/dist/assets/' . basename($css);
            echo '<link rel="stylesheet" href="' . $css_url . '">';
        }

        // Load JS files
        $assets = glob($dist_path . 'assets/*.js');
        foreach ($assets as $js) {
            $js_url = $plugin_url . 'customer-app/dist/assets/' . basename($js);
            echo '<script type="module" src="' . $js_url . '"></script>';
        }
    } else {
        echo '<!-- Customer app not built yet. Run: cd customer-app && npm run build -->';
    }
    ?>

    <style>
        /* Basic reset and layout */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        #squidly-customer-app {
            min-height: 100vh;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: system-ui, -apple-system, sans-serif;
            color: #666;
            font-size: 18px;
        }
    </style>

    <script>
        // Provide WordPress config to the React app
        <?php $squidly_branding = get_option('squidly_branding', []); ?>
        window.wpConfig = {
            apiUrl: '<?php echo rest_url('squidly/v1/'); ?>',
            publicApiUrl: '<?php echo rest_url('squidly/v1/public/'); ?>',
            wpPath: '<?php echo parse_url(site_url(), PHP_URL_PATH) ?: ''; ?>',
            pluginUrl: '<?php echo plugin_dir_url(__FILE__) . '../../'; ?>',
            siteUrl: '<?php echo site_url(); ?>',
            currency: '<?php echo get_option('squidly_currency', 'ILS'); ?>',
            currencySymbol: '<?php echo get_option('squidly_currency_symbol', '₪'); ?>',
            theme: {
                primary_color:   '<?php echo esc_js($squidly_branding['primary_color']   ?? '#D12525'); ?>',
                secondary_color: '<?php echo esc_js($squidly_branding['secondary_color'] ?? '#F2F2F2'); ?>',
                accent_color:    '<?php echo esc_js($squidly_branding['accent_color']    ?? '#D12525'); ?>',
                restaurant_name: '<?php echo esc_js($squidly_branding['restaurant_name'] ?? get_bloginfo('name')); ?>',
                logo_url:        '<?php echo esc_js($squidly_branding['logo_url']        ?? ''); ?>'
            },
            // Payment return detection (WooCommerce redirect)
            paymentReturn: {
                isReturn: <?php echo (isset($_GET['payment']) || isset($_GET['order_id'])) ? 'true' : 'false'; ?>,
                orderId: <?php echo isset($_GET['order_id']) ? intval($_GET['order_id']) : 'null'; ?>,
                status: '<?php echo isset($_GET['payment']) ? sanitize_text_field($_GET['payment']) : ''; ?>',
                key: '<?php echo isset($_GET['key']) ? sanitize_text_field($_GET['key']) : ''; ?>',
            },
        };

        // Debug logging
        console.log('Squidly Customer App - WordPress Config:', window.wpConfig);

        // Error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            const appElement = document.getElementById('squidly-customer-app');
            if (appElement) {
                appElement.innerHTML = '<div style="padding: 20px; color: red;">Error loading app: ' + e.error.message + '</div>';
            }
        });

        // Check if React app loaded after timeout
        setTimeout(function() {
            const loadingElement = document.querySelector('.loading');
            if (loadingElement && loadingElement.style.display !== 'none') {
                console.error('React app failed to load within 10 seconds');
                const appElement = document.getElementById('squidly-customer-app');
                if (appElement) {
                    appElement.innerHTML = '<div style="padding: 20px; color: red;">Error: App did not load. Check console for details.</div>';
                }
            }
        }, 10000);
    </script>
</head>
<body>
    <div id="squidly-customer-app">
        <div class="loading">Loading Squidly...</div>
    </div>
</body>
</html>
