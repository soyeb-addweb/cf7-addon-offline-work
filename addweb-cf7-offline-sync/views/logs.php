<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap afos-wrap">
    <h1><?php echo esc_html__( 'Logs', 'addweb-cf7-offline-sync' ); ?></h1>
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