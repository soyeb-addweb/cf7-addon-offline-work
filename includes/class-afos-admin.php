<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Admin {
    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'admin_post_afos_clear_logs', [ $this, 'handle_clear_logs' ] );
    }

    public function enqueue_admin( string $hook ): void {
        if ( strpos( $hook, 'afos' ) === false ) { return; }
        wp_enqueue_style( 'afos-admin', AFOS_PLUGIN_URL . 'assets/css/admin.css', array(), AFOS_VERSION );
    }

    public function menu(): void {
        add_menu_page(
            __( 'CF7 Offline Sync', 'addweb-cf7-offline-sync' ),
            __( 'CF7 Offline Sync', 'addweb-cf7-offline-sync' ),
            'manage_options',
            'afos',
            [ $this, 'render_settings' ],
            'dashicons-cloud',
            56
        );
        add_submenu_page( 'afos', __( 'Submissions', 'addweb-cf7-offline-sync' ), __( 'Submissions', 'addweb-cf7-offline-sync' ), 'manage_options', 'afos-submissions', [ $this, 'render_submissions' ] );
        add_submenu_page( 'afos', __( 'Logs', 'addweb-cf7-offline-sync' ), __( 'Logs', 'addweb-cf7-offline-sync' ), 'manage_options', 'afos-logs', [ $this, 'render_logs' ] );
    }

    public function register_settings(): void {
        register_setting( 'afos_settings', 'afos_pages_to_cache', array( 'type' => 'array', 'sanitize_callback' => [ $this, 'sanitize_pages' ] ) );
        register_setting( 'afos_settings', 'afos_enable_email', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ) );
        register_setting( 'afos_settings', 'afos_email_recipients', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'afos_settings', 'afos_sync_api_key', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'afos_settings', 'afos_enable_debug_logging', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ) );
        register_setting( 'afos_settings', 'afos_auto_tag_cf7_forms', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean' ) );
    }

    public function sanitize_pages( $value ) {
        $ids = array();
        if ( is_array( $value ) ) {
            foreach ( $value as $id ) { $ids[] = (int) $id; }
        }
        return $ids;
    }

    public function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        include AFOS_PLUGIN_DIR . 'views/settings.php';
    }

    public function render_submissions(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page    = 20;
        $submissions = DB::get_submissions( $paged, $per_page );
        $total       = DB::count_submissions();
        include AFOS_PLUGIN_DIR . 'views/submissions.php';
    }

    public function render_logs(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        global $wpdb;
        $table = Logger::logs_table();
        $logs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", ARRAY_A );
        include AFOS_PLUGIN_DIR . 'views/logs.php';
    }

    public function handle_clear_logs(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Unauthorized', 'addweb-cf7-offline-sync' ) ); }
        check_admin_referer( 'afos_clear_logs' );
        global $wpdb;
        $table = Logger::logs_table();
        $wpdb->query( "DELETE FROM {$table}" );
        Logger::info( 'Logs cleared by admin' );
        wp_safe_redirect( add_query_arg( 'logs_cleared', '1', admin_url( 'admin.php?page=afos-logs' ) ) );
        exit;
    }
}