<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class DB {
    public static function maybe_create_tables(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $submissions     = self::submissions_table();
        $logs            = Logger::logs_table();

        $sql = "CREATE TABLE {$submissions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NULL,
            form_title VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            source VARCHAR(20) NOT NULL DEFAULT 'offline',
            fields LONGTEXT NULL,
            errors LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            synced_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level)
        ) {$charset_collate};";

        dbDelta( $sql );
        dbDelta( $sql2 );
    }

    public static function uninstall(): void {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::submissions_table() );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . Logger::logs_table() );
        delete_option( 'afos_pages_to_cache' );
        delete_option( 'afos_enable_email' );
        delete_option( 'afos_email_recipients' );
        delete_option( 'afos_sync_api_key' );
        delete_option( 'afos_enable_debug_logging' );
        delete_option( 'afos_auto_tag_cf7_forms' );
    }

    public static function submissions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'afos_submissions';
    }

    public static function insert_submission( array $args ): int {
        global $wpdb;
        $table = self::submissions_table();
        $data  = array(
            'form_id'    => isset( $args['form_id'] ) ? (int) $args['form_id'] : null,
            'form_title' => isset( $args['form_title'] ) ? sanitize_text_field( (string) $args['form_title'] ) : null,
            'status'     => isset( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : 'queued',
            'source'     => isset( $args['source'] ) ? sanitize_text_field( (string) $args['source'] ) : 'offline',
            'fields'     => isset( $args['fields'] ) ? wp_json_encode( $args['fields'] ) : null,
            'errors'     => isset( $args['errors'] ) ? wp_json_encode( $args['errors'] ) : null,
            'created_at' => current_time( 'mysql', 1 ),
            'synced_at'  => null,
        );
        $wpdb->insert( $table, $data );
        return (int) $wpdb->insert_id;
    }

    public static function update_submission_status( int $id, string $status, ?string $error = null ): void {
        global $wpdb;
        $table = self::submissions_table();
        $data  = array( 'status' => sanitize_text_field( $status ) );
        if ( $status === 'synced' ) {
            $data['synced_at'] = current_time( 'mysql', 1 );
        }
        if ( $error ) {
            $data['errors'] = wp_json_encode( array( 'message' => wp_strip_all_tags( $error ) ) );
        }
        $wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
    }

    public static function get_submissions( int $paged = 1, int $per_page = 20 ): array {
        global $wpdb;
        $table  = self::submissions_table();
        $offset = max( 0, ( $paged - 1 ) * $per_page );
        $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
        return array_map( [ __CLASS__, 'prepare_submission_row' ], $rows );
    }

    public static function count_submissions(): int {
        global $wpdb;
        $table = self::submissions_table();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    public static function prepare_submission_row( array $row ): array {
        $row['id']         = (int) $row['id'];
        $row['form_id']    = isset( $row['form_id'] ) ? (int) $row['form_id'] : null;
        $row['form_title'] = isset( $row['form_title'] ) ? (string) $row['form_title'] : '';
        $row['fields']     = ! empty( $row['fields'] ) ? json_decode( (string) $row['fields'], true ) : array();
        $row['errors']     = ! empty( $row['errors'] ) ? json_decode( (string) $row['errors'], true ) : array();
        return $row;
    }

    public static function export_csv(): string {
        $rows = self::get_submissions( 1, 10000 );
        $fh   = fopen( 'php://temp', 'w+' );
        fputcsv( $fh, array( 'ID', 'Form ID', 'Form Title', 'Status', 'Source', 'Fields', 'Created At', 'Synced At' ) );
        foreach ( $rows as $r ) {
            fputcsv( $fh, array(
                $r['id'],
                $r['form_id'],
                $r['form_title'],
                $r['status'],
                $r['source'],
                wp_json_encode( $r['fields'] ),
                $r['created_at'],
                $r['synced_at'],
            ) );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return (string) $csv;
    }
}