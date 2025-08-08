<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap afos-wrap">
    <h1><?php echo esc_html__( 'Logs', 'addweb-cf7-offline-sync' ); ?></h1>
    <?php if ( isset( $_GET['logs_cleared'] ) && $_GET['logs_cleared'] === '1' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-success"><p><?php echo esc_html__( 'Logs cleared.', 'addweb-cf7-offline-sync' ); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0;">
        <?php wp_nonce_field( 'afos_clear_logs' ); ?>
        <input type="hidden" name="action" value="afos_clear_logs" />
        <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'addweb-cf7-offline-sync' ) ); ?>');">
            <?php echo esc_html__( 'Clear Logs', 'addweb-cf7-offline-sync' ); ?>
        </button>
    </form>
    <table class="afos-table">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Time', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Level', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Message', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Context', 'addweb-cf7-offline-sync' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo esc_html( $log['created_at'] ); ?></td>
                    <td><?php echo esc_html( strtoupper( $log['level'] ) ); ?></td>
                    <td><?php echo esc_html( $log['message'] ); ?></td>
                    <td><pre><?php echo esc_html( $log['context'] ); ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>