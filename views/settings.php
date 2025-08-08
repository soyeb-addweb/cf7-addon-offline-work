<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$pages_to_cache       = (array) get_option( 'afos_pages_to_cache', array() );
$enable_email         = (bool) get_option( 'afos_enable_email', false );
$email_recipients     = (string) get_option( 'afos_email_recipients', get_option( 'admin_email' ) );
srand();
$sync_api_key         = (string) get_option( 'afos_sync_api_key', '' );
$enable_debug_logging = (bool) get_option( 'afos_enable_debug_logging', false );
$auto_tag_cf7_forms   = (bool) get_option( 'afos_auto_tag_cf7_forms', true );
$pages = get_pages();
?>
<div class="wrap afos-wrap">
    <h1><?php echo esc_html__( 'CF7 Offline Sync - Settings', 'addweb-cf7-offline-sync' ); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'afos_settings' ); ?>
        <div class="afos-grid">
            <div class="afos-card">
                <h2><?php echo esc_html__( 'Offline Caching', 'addweb-cf7-offline-sync' ); ?></h2>
                <p><?php echo esc_html__( 'Select pages to cache for offline access.', 'addweb-cf7-offline-sync' ); ?></p>
                <select multiple name="afos_pages_to_cache[]" style="width:100%; min-height:160px;">
                    <?php foreach ( $pages as $p ) : ?>
                        <option value="<?php echo (int) $p->ID; ?>" <?php selected( in_array( $p->ID, $pages_to_cache, true ) ); ?>>
                            <?php echo esc_html( $p->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="afos-card">
                <h2><?php echo esc_html__( 'Email Notifications', 'addweb-cf7-offline-sync' ); ?></h2>
                <p><label><input type="checkbox" name="afos_enable_email" value="1" <?php checked( $enable_email ); ?>> <?php echo esc_html__( 'Enable email on sync', 'addweb-cf7-offline-sync' ); ?></label></p>
                <p>
                    <label><?php echo esc_html__( 'Recipients (comma-separated)', 'addweb-cf7-offline-sync' ); ?></label>
                    <input type="text" name="afos_email_recipients" value="<?php echo esc_attr( $email_recipients ); ?>" class="regular-text" />
                </p>
            </div>
            <div class="afos-card">
                <h2><?php echo esc_html__( 'Security & Debug', 'addweb-cf7-offline-sync' ); ?></h2>
                <p><label><?php echo esc_html__( 'Sync API Key', 'addweb-cf7-offline-sync' ); ?></label></p>
                <p>
                    <input type="text" readonly value="<?php echo esc_attr( $sync_api_key ); ?>" class="regular-text" />
                    <em><?php echo esc_html__( 'Used by the client to authenticate offline sync requests.', 'addweb-cf7-offline-sync' ); ?></em>
                </p>
                <p><label><input type="checkbox" name="afos_enable_debug_logging" value="1" <?php checked( $enable_debug_logging ); ?>> <?php echo esc_html__( 'Enable debug logging', 'addweb-cf7-offline-sync' ); ?></label></p>
                <p><label><input type="checkbox" name="afos_auto_tag_cf7_forms" value="1" <?php checked( $auto_tag_cf7_forms ); ?>> <?php echo esc_html__( 'Auto-enable offline on all CF7 forms', 'addweb-cf7-offline-sync' ); ?></label></p>
            </div>
        </div>
        <p class="afos-actions">
            <?php submit_button( __( 'Save Settings', 'addweb-cf7-offline-sync' ), 'primary', 'submit', false ); ?>
            <a class="button" href="<?php echo esc_url( rest_url( 'addweb-cf7/v1/export' ) ); ?>"><?php echo esc_html__( 'Export CSV', 'addweb-cf7-offline-sync' ); ?></a>
        </p>
    </form>
    <hr/>
    <p>
        <strong><?php echo esc_html__( 'How to use:', 'addweb-cf7-offline-sync' ); ?></strong>
        <?php echo esc_html__( 'All Contact Form 7 forms are automatically enabled for offline. If you use a custom form, add data-offline-form="true" attribute to the <form> tag.', 'addweb-cf7-offline-sync' ); ?>
    </p>
</div>