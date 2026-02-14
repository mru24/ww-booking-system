<?php
/**
 * Admin View: Holidays List
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the holidays instance properly
global $ww_booking_system;
$holidays_instance = $ww_booking_system->get_holidays_instance();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Holidays</h1>
    <a href="<?php echo admin_url( 'admin.php?page=my-booking-edit-holiday' ); ?>" class="page-title-action">Add New Holiday</a>

    <hr class="wp-header-end">

    <?php if ( isset( $_GET['message'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( urldecode( $_GET['message'] ) ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['error'] ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo isset( $_GET['msg'] ) ? esc_html( urldecode( $_GET['msg'] ) ) : 'An error occurred.'; ?></p>
        </div>
    <?php endif; ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <!-- Bulk actions can be added here -->
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column">Holiday Name</th>
                <th scope="col" class="manage-column">Date Range</th>
                <th scope="col" class="manage-column">Duration</th>
                <th scope="col" class="manage-column">Type</th>
                <th scope="col" class="manage-column">Applies To</th>
                <th scope="col" class="manage-column">Description</th>
                <th scope="col" class="manage-column">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $holidays ) ) : ?>
                <?php foreach ( $holidays as $holiday ) :
                    $start_date = strtotime( $holiday['start_date'] );
                    $end_date = strtotime( $holiday['end_date'] );
                    $duration = ( ( $end_date - $start_date ) / DAY_IN_SECONDS ) + 1;
                    $is_single_day = ( $holiday['start_date'] === $holiday['end_date'] );

                    // Get lakes for this holiday
                    $holiday_lakes = $holidays_instance->get_holiday_lakes( $holiday['id'] );
                    $applies_to_text = ( $holiday['applies_to'] === 'all_lakes' ) ? 'All Lakes' : 'Specific Lakes';
                    if ( $holiday['applies_to'] === 'specific_lakes' && ! empty( $holiday_lakes ) ) {
                        $lake_names = wp_list_pluck( $holiday_lakes, 'lake_name' );
                        $applies_to_text = implode( ', ', $lake_names );
                    } elseif ( $holiday['applies_to'] === 'specific_lakes' && empty( $holiday_lakes ) ) {
                        $applies_to_text = 'No lakes selected';
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html( $holiday['holiday_name'] ); ?></td>
                        <td>
                            <?php echo esc_html( date( 'M j, Y', $start_date ) ); ?>
                            <?php if ( ! $is_single_day ) : ?>
                                to <?php echo esc_html( date( 'M j, Y', $end_date ) ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $duration . ' day(s)' ); ?></td>
                        <td><?php echo esc_html( ucfirst( $holiday['holiday_type'] ) ); ?></td>
                        <td><?php echo esc_html( $applies_to_text ); ?></td>
                        <td><?php echo esc_html( $holiday['description'] ); ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=my-booking-edit-holiday&id=' . $holiday['id'] ); ?>" class="button">Edit</a>
                            <a href="#" class="button button-link-delete delete-holiday" data-holiday-id="<?php echo esc_attr( $holiday['id'] ); ?>" data-nonce="<?php echo wp_create_nonce( 'mybp_delete_holiday' ); ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7">No holidays found. <a href="<?php echo admin_url( 'admin.php?page=my-booking-edit-holiday' ); ?>">Add your first holiday</a></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    $('.delete-holiday').on('click', function(e) {
        e.preventDefault();

        var holidayId = $(this).data('holiday-id');
        var nonce = $(this).data('nonce');

        if (confirm('Are you sure you want to delete this holiday?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mybp_delete_holiday',
                    holiday_id: holidayId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete holiday.');
                    }
                },
                error: function() {
                    alert('Error deleting holiday.');
                }
            });
        }
    });
});
</script>