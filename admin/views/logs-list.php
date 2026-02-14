<?php
/**
 * Admin View: Activity Logs
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get logs with filters
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 50;

$filters = array(
    'page'        => $page,
    'per_page'    => $per_page,
    'object_type' => isset( $_GET['object_type'] ) ? sanitize_text_field( $_GET['object_type'] ) : '',
    'action_type' => isset( $_GET['action_type'] ) ? sanitize_text_field( $_GET['action_type'] ) : '',
    'user_id'     => isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : '',
    'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
    'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
    'search'      => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
);

$logs_data = $this->logger->get_logs( $filters );
$logs = $logs_data['logs'];
$total_pages = $logs_data['pages'];

// Get filter options
$action_types = $this->logger->get_action_types();
$object_types = $this->logger->get_object_types();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Activity Logs</h1>
    <a href="<?php echo esc_url( add_query_arg( array( 'export' => 'csv' ) + $_GET ) ); ?>" class="page-title-action">Export CSV</a>
    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="ww-log-filters">
        <form method="get">
            <input type="hidden" name="page" value="my-booking-logs">

            <div class="tablenav top">
                <div class="alignleft actions">
                    <!-- Object Type Filter -->
                    <select name="object_type">
                        <option value="">All Object Types</option>
                        <?php foreach ( $object_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['object_type'], $type ); ?>>
                                <?php echo esc_html( ucfirst( $type ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Action Type Filter -->
                    <select name="action_type">
                        <option value="">All Action Types</option>
                        <?php foreach ( $action_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['action_type'], $type ); ?>>
                                <?php echo esc_html( ucfirst( $type ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Date Range -->
                    <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" placeholder="From Date">
                    <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" placeholder="To Date">

                    <!-- Search -->
                    <input type="text" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Search...">

                    <input type="submit" class="button" value="Filter">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-logs' ) ); ?>" class="button">Reset</a>
                </div>

                <!-- Pagination -->
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format( $logs_data['total'] ); ?> items</span>
                    <?php if ( $total_pages > 1 ) : ?>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links( array(
                                'base'    => add_query_arg( 'paged', '%#%' ),
                                'format'  => '',
                                'current' => $page,
                                'total'   => $total_pages,
                            ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="15%">Date/Time</th>
                <th width="15%">User</th>
                <th width="10%">Action</th>
                <th width="10%">Object Type</th>
                <th width="10%">Object ID</th>
                <th width="20%">Object Name</th>
                <th width="10%">IP Address</th>
                <th width="10%">Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $logs ) ) : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( date( 'M j, Y H:i', strtotime( $log['created_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( $log['user_name'] ); ?></td>
                        <td>
                            <span class="log-action log-action-<?php echo esc_attr( $log['action_type'] ); ?>">
                                <?php echo esc_html( ucfirst( $log['action_type'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( ucfirst( $log['object_type'] ) ); ?></td>
                        <td><?php echo esc_html( $log['object_id'] ); ?></td>
                        <td><?php echo esc_html( $log['object_name'] ); ?></td>
                        <td><?php echo esc_html( $log['ip_address'] ); ?></td>
                        <td>
                            <?php if ( $log['old_values'] || $log['new_values'] ) : ?>
                                <button class="button button-small view-log-details"
                                        data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
                                    View Changes
                                </button>
                            <?php else: ?>
                                <em>No changes</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8" class="no-items">No activity logs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Log Details Modal -->
    <div id="log-details-modal" style="display: none;">
        <div class="log-modal-content">
            <span class="close-modal">&times;</span>
            <h3>Change Details</h3>
            <div id="log-details-content"></div>
        </div>
    </div>
</div>

<style>
.log-action { padding: 4px 8px; border-radius: 3px; font-size: 0.9em; font-weight: bold; }
.log-action-created { background: #d4edda; color: #155724; }
.log-action-updated { background: #fff3cd; color: #856404; }
.log-action-deleted { background: #f8d7da; color: #721c24; }

#log-details-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.log-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 5px;
}

.close-modal {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-modal:hover {
    color: black;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle export
    <?php if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) : ?>
        window.location.href = '<?php echo esc_url( add_query_arg( array( 'export' => 'csv' ) + $_GET ) ); ?>';
    <?php endif; ?>

    // View log details
    $('.view-log-details').on('click', function() {
        const logId = $(this).data('log-id');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mybp_get_log_details',
                log_id: logId,
                nonce: '<?php echo wp_create_nonce( 'mybp_log_details' ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#log-details-content').html(response.data.html);
                    $('#log-details-modal').show();
                }
            }
        });
    });

    // Close modal
    $('.close-modal').on('click', function() {
        $('#log-details-modal').hide();
    });
});
</script>