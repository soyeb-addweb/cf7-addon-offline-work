<?php
/**
 * Plugin Name: AddWeb CF7 Offline Sync
 * Description: Offline-first support for Contact Form 7 and generic forms. Caches pages for offline, queues submissions in IndexedDB, auto-syncs when online, with admin UI, CSV export, and REST API.
 * Version: 1.0.0
 * Author: AddWeb + GPT Assistant
 * Text Domain: addweb-cf7-offline-sync
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! defined( 'AFOS_PLUGIN_FILE' ) ) {
    define( 'AFOS_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AFOS_PLUGIN_DIR' ) ) {
    define( 'AFOS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'AFOS_PLUGIN_URL' ) ) {
    define( 'AFOS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'AFOS_VERSION' ) ) {
    define( 'AFOS_VERSION', '1.0.0' );
}

// Autoload includes
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-logger.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-db.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-service-worker.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-rest.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-admin.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-cf7.php';
require_once AFOS_PLUGIN_DIR . 'includes/class-afos-plugin.php';

// Initialize plugin
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'addweb-cf7-offline-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    $plugin = \AFOS\Plugin::get_instance();
    $plugin->init();
});

register_activation_hook( __FILE__, function () {
    \AFOS\DB::maybe_create_tables();
    // Set defaults if not present
    $defaults = array(
        'pages_to_cache'          => array(),
        'enable_email'            => false,
        'email_recipients'        => get_option( 'admin_email' ),
        'sync_api_key'            => wp_generate_password( 20, false ),
        'enable_debug_logging'    => true,
        'auto_tag_cf7_forms'      => true,
    );
    foreach ( $defaults as $key => $value ) {
        if ( get_option( 'afos_' . $key, null ) === null ) {
            update_option( 'afos_' . $key, $value );
        }
    }
});

register_uninstall_hook( __FILE__, '\\AFOS\\DB::uninstall' );