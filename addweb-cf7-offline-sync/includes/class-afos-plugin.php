<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static $instance = null;

    public static function get_instance(): Plugin {
        if ( self::$instance === null ) {
            self::$instance = new Plugin();
        }
        return self::$instance;
    }

    public function init(): void {
        // Admin UI
        ( new Admin() )->hooks();
        // REST endpoints
        ( new Rest() )->hooks();
        // Service Worker endpoint
        ( new Service_Worker() )->hooks();
        // CF7 integration
        ( new CF7() )->hooks();

        // Frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );

        // Log that plugin initialized
        Logger::info( 'AFOS plugin initialized', [ 'version' => AFOS_VERSION ] );
    }

    public function enqueue_frontend(): void {
        $handle = 'afos-offline-forms';
        wp_register_script(
            $handle,
            AFOS_PLUGIN_URL . 'assets/js/offline-forms.js',
            array(),
            AFOS_VERSION,
            true
        );

        $pages_to_cache = get_option( 'afos_pages_to_cache', array() );
        $page_urls      = array();
        if ( is_array( $pages_to_cache ) ) {
            foreach ( $pages_to_cache as $page_id ) {
                $url = get_permalink( (int) $page_id );
                if ( $url ) {
                    $page_urls[] = esc_url_raw( $url );
                }
            }
        }

        wp_localize_script( $handle, 'AFOS_SETTINGS', array(
            'restUrl'        => esc_url_raw( rest_url( 'addweb-cf7/v1' ) ),
            'swUrl'          => esc_url_raw( admin_url( 'admin-ajax.php?action=afos_sw' ) ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'apiKey'         => (string) get_option( 'afos_sync_api_key', '' ),
            'pagesToCache'   => $page_urls,
            'enableDebug'    => (bool) get_option( 'afos_enable_debug_logging', false ),
            'textDomain'     => 'addweb-cf7-offline-sync',
        ) );

        wp_enqueue_style( 'afos-frontend', AFOS_PLUGIN_URL . 'assets/css/frontend.css', array(), AFOS_VERSION );
        wp_enqueue_script( $handle );
    }
}