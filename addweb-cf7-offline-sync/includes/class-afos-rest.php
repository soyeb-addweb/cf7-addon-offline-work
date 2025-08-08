<?php
namespace AFOS;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Rest {
    public function hooks(): void {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        register_rest_route( 'addweb-cf7/v1', '/submissions', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_submission' ],
                'permission_callback' => '__return_true',
                'args'                => array(
                    'form_id' => array( 'type' => 'integer', 'required' => false ),
                    'form_title' => array( 'type' => 'string', 'required' => false ),
                    'fields'  => array( 'type' => 'object', 'required' => true ),
                    'source'  => array( 'type' => 'string', 'required' => false ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_submissions' ],
                'permission_callback' => function(){ return current_user_can( 'manage_options' ); },
            ),
        ) );

        register_rest_route( 'addweb-cf7/v1', '/export', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'export_csv' ],
                'permission_callback' => function(){ return current_user_can( 'manage_options' ); },
            ),
        ) );

        register_rest_route( 'addweb-cf7/v1', '/events', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'record_event' ],
                'permission_callback' => '__return_true',
                'args'                => array(
                    'type' => array( 'type' => 'string', 'required' => true ),
                    'data' => array( 'type' => 'object', 'required' => false ),
                ),
            ),
        ) );
    }

    private function validate_api_key( WP_REST_Request $request ): bool {
        $key     = (string) get_option( 'afos_sync_api_key', '' );
        $header  = sanitize_text_field( (string) $request->get_header( 'x-afos-api-key' ) );
        $query   = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
        $provided = $header ?: $query;
        if ( empty( $key ) ) { return false; }
        return hash_equals( $key, (string) $provided );
    }

    public function create_submission( WP_REST_Request $request ) {
        if ( ! $this->validate_api_key( $request ) ) {
            Logger::warning( 'Unauthorized submission attempt' );
            return new WP_Error( 'afos_forbidden', __( 'Invalid API key.', 'addweb-cf7-offline-sync' ), array( 'status' => 403 ) );
        }

        $form_id    = (int) $request->get_param( 'form_id' );
        $form_title = sanitize_text_field( (string) $request->get_param( 'form_title' ) );
        $fields     = (array) $request->get_param( 'fields' );
        $source     = sanitize_text_field( (string) ( $request->get_param( 'source' ) ?: 'offline' ) );

        $id = DB::insert_submission( array(
            'form_id'    => $form_id ?: null,
            'form_title' => $form_title ?: null,
            'fields'     => $fields,
            'status'     => 'synced',
            'source'     => $source,
        ) );

        // Email notifications if enabled
        if ( get_option( 'afos_enable_email', false ) ) {
            try {
                $to      = (string) get_option( 'afos_email_recipients', get_option( 'admin_email' ) );
                $subject = sprintf( __( 'Offline submission synced (ID %d)', 'addweb-cf7-offline-sync' ), $id );
                $body    = $this->format_fields_for_email( $fields, $form_title, $form_id );
                wp_mail( $to, $subject, $body );
            } catch ( \Throwable $e ) {
                Logger::error( 'Email notification failed', array( 'error' => $e->getMessage() ) );
            }
        }

        Logger::info( 'Submission synced', array( 'id' => $id, 'form_id' => $form_id ) );
        return new WP_REST_Response( array( 'id' => $id, 'status' => 'synced' ), 201 );
    }

    public function record_event( WP_REST_Request $request ) {
        if ( ! $this->validate_api_key( $request ) ) {
            return new WP_Error( 'afos_forbidden', __( 'Invalid API key.', 'addweb-cf7-offline-sync' ), array( 'status' => 403 ) );
        }
        $type = sanitize_text_field( (string) $request->get_param( 'type' ) );
        $data = (array) $request->get_param( 'data' );
        Logger::info( 'Client event', array( 'type' => $type, 'data' => $data ) );
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    private function format_fields_for_email( array $fields, string $form_title = '', int $form_id = 0 ): string {
        $lines   = array();
        $header  = __( 'Offline submission details', 'addweb-cf7-offline-sync' );
        $lines[] = $header;
        if ( $form_title ) { $lines[] = sprintf( __( 'Form: %s', 'addweb-cf7-offline-sync' ), $form_title ); }
        if ( $form_id ) { $lines[] = sprintf( __( 'Form ID: %d', 'addweb-cf7-offline-sync' ), $form_id ); }
        $lines[] = str_repeat( '-', 30 );
        foreach ( $fields as $key => $value ) {
            $label = sanitize_text_field( (string) $key );
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) );
            } else {
                $value = sanitize_text_field( (string) $value );
            }
            $lines[] = $label . ': ' . $value;
        }
        return implode( "\n", $lines );
    }

    public function list_submissions( WP_REST_Request $request ) {
        $paged    = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
        $rows     = DB::get_submissions( $paged, $per_page );
        $total    = DB::count_submissions();
        return new WP_REST_Response( array( 'data' => $rows, 'total' => $total ), 200 );
    }

    public function export_csv( WP_REST_Request $request ) {
        $csv = DB::export_csv();
        return new WP_REST_Response( $csv, 200, array(
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="afos-submissions.csv"',
        ) );
    }
}