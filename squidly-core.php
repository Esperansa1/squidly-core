<?php
/**
 * Plugin Name: Squidly Core
 * Plugin URI: https://squidly.local
 * Description: Core functionality for the Squidly restaurant system.
 * Version: 1.0.0
 * Author: Esperansa
 * License: MIT
 * Text Domain: squidly-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 🔒 Prevent direct access
}


require_once __DIR__ . '/vendor/autoload.php';	

// 📍 Define plugin constants
define( 'SQUIDLY_CORE_VERSION', '1.0.0' );
define( 'SQUIDLY_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SQUIDLY_CORE_URL', plugin_dir_url( __FILE__ ) );

# Register Post-Types
require_once __DIR__ . '/includes/PostTypeRegistry.php';

PostTypeRegistry::register_all();


spl_autoload_register(function ($class) {
    $paths = [
        'includes/models/',
        'includes/models/enums/',
        'includes/repositories/',
    ];

    foreach ($paths as $path) {
        $file = SQUIDLY_CORE_PATH . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

