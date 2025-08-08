<?php
namespace AFOS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CF7 {
    public function hooks(): void {
        add_filter( 'wpcf7_form_attributes', [ $this, 'add_form_attribute' ] );
        add_action( 'wpcf7_submit', [ $this, 'capture_submission' ], 10, 2 );
    }

    public function add_form_attribute( array $atts ): array {
        $auto = (bool) get_option( 'afos_auto_tag_cf7_forms', true );
        if ( $auto ) {
            $atts['data-offline-form'] = 'true';
        }
        return $atts;
    }

    public function capture_submission( $contact_form, $result ): void { // phpcs:ignore
        try {
            $submission = \WPCF7_Submission::get_instance();
            if ( ! $submission ) { return; }
            $posted_data = (array) $submission->get_posted_data();
            $form_id     = method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
            $form_title  = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';

            // Remove CF7 internal fields
            $fields = array();
            foreach ( $posted_data as $k => $v ) {
                if ( strpos( (string) $k, '_' ) === 0 ) { continue; }
                $fields[ sanitize_key( (string) $k ) ] = is_array( $v ) ? array_map( 'sanitize_text_field', array_map( 'strval', $v ) ) : sanitize_text_field( (string) $v );
            }
            DB::insert_submission( array(
                'form_id'    => $form_id,
                'form_title' => $form_title,
                'fields'     => $fields,
                'status'     => ( isset( $result['status'] ) && $result['status'] === 'mail_sent' ) ? 'online' : 'failed',
                'source'     => 'online',
            ) );
        } catch ( \Throwable $e ) {
            Logger::error( 'CF7 capture failed', array( 'error' => $e->getMessage() ) );
        }
    }
}