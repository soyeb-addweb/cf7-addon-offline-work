<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Logger {
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warning';
    public const LEVEL_ERROR = 'error';

    public static function debug( string $message, array $context = array() ): void {
        self::log( self::LEVEL_DEBUG, $message, $context );
    }
    public static function info( string $message, array $context = array() ): void {
        self::log( self::LEVEL_INFO, $message, $context );
    }
    public static function warning( string $message, array $context = array() ): void {
        self::log( self::LEVEL_WARN, $message, $context );
    }
    public static function error( string $message, array $context = array() ): void {
        self::log( self::LEVEL_ERROR, $message, $context );
    }

    public static function log( string $level, string $message, array $context = array() ): void {
        $enable_db   = true;
        $enable_file = defined( 'WP_DEBUG' ) && WP_DEBUG;

        $sanitized_message = wp_strip_all_tags( $message );
        $json_context      = wp_json_encode( self::sanitize_context( $context ) );

        global $wpdb;
        if ( $enable_db && isset( $wpdb ) ) {
            $table = self::logs_table();
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
                $wpdb->insert(
                    $table,
                    array(
                        'level'      => $level,
                        'message'    => $sanitized_message,
                        'context'    => $json_context,
                        'created_at' => current_time( 'mysql', 1 ),
                    ),
                    array( '%s', '%s', '%s', '%s' )
                );
            }
        }

        if ( $enable_file ) {
            error_log( sprintf( '[AFOS][%s] %s %s', strtoupper( $level ), $sanitized_message, $json_context ) );
        }
    }

    private static function sanitize_context( array $context ): array {
        $clean = array();
        foreach ( $context as $key => $value ) {
            $safe_key = sanitize_key( (string) $key );
            if ( is_scalar( $value ) ) {
                $clean[ $safe_key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
            } else {
                $clean[ $safe_key ] = $value; // wp_json_encode will handle
            }
        }
        return $clean;
    }

    public static function logs_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'afos_logs';
    }
}