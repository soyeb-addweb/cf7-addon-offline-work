<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$total_pages = max( 1, (int) ceil( $total / 20 ) );
$current_url = esc_url( add_query_arg( array() ) );
?>
<div class="wrap afos-wrap">
    <h1><?php echo esc_html__( 'Submissions', 'addweb-cf7-offline-sync' ); ?></h1>
    <table class="afos-table">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'ID', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Form', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Status', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Source', 'addweb-cf7-offline-sync' ); ?></th>
                <th><?php echo esc_html__( 'Created', 'addweb-cf7-offline-sync' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $submissions as $row ) : ?>
                <tr>
                    <td><?php echo (int) $row['id']; ?></td>
                    <td>
                        <?php echo esc_html( $row['form_title'] ?: ( $row['form_id'] ? ( 'ID ' . (int) $row['form_id'] ) : '-' ) ); ?>
                        <details><summary><?php echo esc_html__( 'View Fields', 'addweb-cf7-offline-sync' ); ?></summary>
                            <pre><?php echo esc_html( wp_json_encode( $row['fields'], JSON_PRETTY_PRINT ) ); ?></pre>
                        </details>
                    </td>
                    <td><?php echo esc_html( $row['status'] ); ?></td>
                    <td><?php echo esc_html( $row['source'] ); ?></td>
                    <td><?php echo esc_html( $row['created_at'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ( $total_pages > 1 ) : ?>
        <p class="tablenav-pages">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                <a class="button <?php echo $i === $paged ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'paged', $i, $current_url ) ); ?>"><?php echo (int) $i; ?></a>
            <?php endfor; ?>
        </p>
    <?php endif; ?>
</div>