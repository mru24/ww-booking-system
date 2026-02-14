<?php
/**
 * Admin View: Add/Edit Club
 *
 * @var array  $club_data
 * @var string $title
 */

// Exit if accessed directly (security measure)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Helper to safely get value or an empty string
$get_club_val = function( $key ) use ( $club_data ) {
    return isset( $club_data[ $key ] ) ? esc_attr( $club_data[ $key ] ) : '';
};

// Get bookings for this club if editing an existing club
$club_bookings = array();
if ( isset( $club_data['id'] ) && $club_data['id'] > 0 ) {
    $club_bookings = $this->clubs->get_bookings_by_club( $club_data['id'] );
}

// Get all lakes for the filter dropdown
global $wpdb;
$lakes_table = $wpdb->prefix . 'booking_lakes';
$lakes = $wpdb->get_results("SELECT id, lake_name FROM {$lakes_table} ORDER BY lake_name ASC", ARRAY_A);

// Generate month options (last 12 months + future months)
$month_options = array();
$current_date = new DateTime();
for ($i = -6; $i <= 6; $i++) { // 6 months past and 6 months future
    $date = clone $current_date;
    $date->modify("{$i} months");
    $month_value = $date->format('Y-m');
    $month_display = $date->format('F Y');
    $month_options[$month_value] = $month_display;
}

// Define booking status options
$status_options = array(
    '' => 'All Statuses',
    'draft' => 'Draft',
    'booked' => 'Booked',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled'
);

// Get filter parameters
$month_filter = isset($_GET['month_filter']) ? sanitize_text_field($_GET['month_filter']) : '';
$lake_filter = isset($_GET['lake_filter']) ? intval($_GET['lake_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

// Handle pagination
$bookings_per_page = isset( $_GET['bookings_per_page'] ) ? intval( $_GET['bookings_per_page'] ) : 10;

// Handle sorting
$sort_by = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'date_start';
$sort_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'desc';

// Validate sort parameters
$allowed_sort_columns = array('booking_id', 'date_start', 'date_end', 'lake_name', 'peg_name', 'match_type_slug', 'booking_status', 'created_at');
if ( ! in_array( $sort_by, $allowed_sort_columns ) ) {
    $sort_by = 'date_start';
}
if ( ! in_array( $sort_order, array('asc', 'desc') ) ) {
    $sort_order = 'desc';
}

// Apply filters first
$filtered_bookings = array();
foreach ($club_bookings as $booking) {
    // Apply month filter
    if ($month_filter) {
        $booking_month = date('Y-m', strtotime($booking['date_start']));
        if ($booking_month !== $month_filter) {
            continue;
        }
    }
    // Apply lake filter
    if ($lake_filter) {
        // We need to get the lake_id for this booking
        // Since the booking data might not have lake_id directly, we'll need to handle this
        // For now, assuming we can filter by lake_name or we need to modify the get_bookings_by_club method
        if ($booking['lake_name'] && $lake_filter) {
            $lake_match = false;
            foreach ($lakes as $lake) {
                if ($lake['id'] == $lake_filter && $lake['lake_name'] == $booking['lake_name']) {
                    $lake_match = true;
                    break;
                }
            }
            if (!$lake_match) {
                continue;
            }
        }
    }
    // Apply status filter
    if ($status_filter && $booking['booking_status'] != $status_filter) {
        continue;
    }
    $filtered_bookings[] = $booking;
}

// Sort the filtered bookings array
usort( $filtered_bookings, function( $a, $b ) use ( $sort_by, $sort_order ) {
    $a_value = $a[ $sort_by ] ?? '';
    $b_value = $b[ $sort_by ] ?? '';

    // Handle date fields specially
    if ( in_array( $sort_by, array('date_start', 'date_end', 'created_at') ) ) {
        $a_timestamp = strtotime( $a_value );
        $b_timestamp = strtotime( $b_value );

        if ( $sort_order === 'asc' ) {
            return $a_timestamp - $b_timestamp;
        } else {
            return $b_timestamp - $a_timestamp;
        }
    }

    // Handle numeric fields
    if ( $sort_by === 'booking_id' ) {
        $a_value = intval( $a_value );
        $b_value = intval( $b_value );
    }

    // Default string comparison
    if ( $sort_order === 'asc' ) {
        return strcasecmp( $a_value, $b_value );
    } else {
        return strcasecmp( $b_value, $a_value );
    }
} );

if ( $bookings_per_page === -1 ) {
    $displayed_bookings = $filtered_bookings;
} else {
    $displayed_bookings = array_slice( $filtered_bookings, 0, $bookings_per_page );
}

// Available options for bookings per page
$bookings_per_page_options = array(10, 25, 50, 75, 100, -1);

// Function to generate sortable header
$get_sortable_header = function( $column_key, $display_name ) use ( $sort_by, $sort_order, $month_filter, $lake_filter, $status_filter, $bookings_per_page ) {
    $current_order = ( $sort_by === $column_key ) ? $sort_order : 'desc';
    $new_order = ( $sort_by === $column_key && $sort_order === 'desc' ) ? 'asc' : 'desc';
    $sort_icon = '';

    if ( $sort_by === $column_key ) {
        $sort_icon = $sort_order === 'asc' ? '<span class="sortable-arrow">↑</span>' : '<span class="sortable-arrow">↓</span>';
    }

    $url = add_query_arg( array(
        'sort_by' => $column_key,
        'sort_order' => $new_order,
        'bookings_per_page' => $bookings_per_page,
        'month_filter' => $month_filter,
        'lake_filter' => $lake_filter,
        'status_filter' => $status_filter
    ) );

    return '<a href="' . esc_url( $url ) . '" style="text-decoration: none; color: inherit; display: block; padding: 8px 10px;">'
           . esc_html( $display_name ) . $sort_icon . '</a>';
};
?>

<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <?php
    // Display error messages if any
    if ( isset( $_GET['error'] ) && isset( $_GET['msg'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['msg'] ) ) . '</p></div>';
    }
    ?>

    <?php if ( isset( $club_data['id'] ) && $club_data['id'] > 0 ) : ?>
        <!-- Existing Related Bookings section remains the same -->
        <hr style="margin: 20px 0;">

        <h2>Related Bookings</h2>

        <!-- Controls Section -->
        <div style="margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">

            <!-- Filters Section -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                        <input type="hidden" name="id" value="<?php echo esc_attr($club_data['id']); ?>">
                        <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
                        <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
                        <input type="hidden" name="bookings_per_page" value="<?php echo esc_attr($bookings_per_page); ?>">

                        <!-- Lake Filter -->
                        <label for="lake_filter" class="screen-reader-text">Filter by lake</label>
                        <select id="lake_filter"
                                name="lake_filter"
                                style="vertical-align: middle; margin-right: 10px;">
                            <option value="">All Lakes</option>
                            <?php foreach ($lakes as $lake) : ?>
                                <option value="<?php echo esc_attr($lake['id']); ?>"
                                        <?php selected($lake_filter, $lake['id']); ?>>
                                    <?php echo esc_html($lake['lake_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Month Filter -->
                        <label for="month_filter" class="screen-reader-text">Filter by month</label>
                        <select id="month_filter"
                                name="month_filter"
                                style="vertical-align: middle; margin-right: 10px;">
                            <option value="">All Months</option>
                            <?php foreach ($month_options as $value => $display) : ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected($month_filter, $value); ?>>
                                    <?php echo esc_html($display); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Status Filter -->
                        <label for="status_filter" class="screen-reader-text">Filter by status</label>
                        <select id="status_filter"
                                name="status_filter"
                                style="vertical-align: middle; margin-right: 10px;">
                            <?php foreach ($status_options as $value => $display) : ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected($status_filter, $value); ?>>
                                    <?php echo esc_html($display); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Filter Button -->
                        <input type="submit"
                               name="filter_action"
                               id="filter-submit"
                               class="button"
                               value="Filter">

                        <!-- Clear Filters -->
                        <?php if ($month_filter || $lake_filter || $status_filter) : ?>
                            <a href="<?php echo esc_url( add_query_arg( array(
                                'page' => $_GET['page'],
                                'id' => $club_data['id'],
                                'sort_by' => $sort_by,
                                'sort_order' => $sort_order,
                                'bookings_per_page' => $bookings_per_page
                            ), admin_url('admin.php') ) ); ?>"
                               class="button"
                               style="margin-left: 5px;">
                                Clear Filters
                            </a>
                        <?php endif; ?>
                    </form>
                </div>


            <!-- Bookings Per Page Selector -->
            <div style="float: right;">
                <label for="bookings_per_page" style="font-weight: bold; margin-right: 10px;">Show bookings:</label>
                <select name="bookings_per_page" id="bookings_per_page" onchange="updateBookingsPerPage(this.value)">
                    <?php foreach ( $bookings_per_page_options as $option ) : ?>
                        <option value="<?php echo $option; ?>" <?php selected( $bookings_per_page, $option ); ?>>
                            <?php echo $option === -1 ? 'All' : $option; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="margin-left: 10px; color: #666;">
                    Showing <?php echo count( $displayed_bookings ); ?> of <?php echo count( $filtered_bookings ); ?> bookings
                    <?php if ( $sort_by !== 'date_start' || $sort_order !== 'desc' ) : ?>
                        • Sorted by: <?php echo esc_html( str_replace('_', ' ', $sort_by) ); ?> (<?php echo esc_html( $sort_order ); ?>)
                    <?php endif; ?>
                </span>
            </div>
            </div>

            <!-- Active Filters Info -->
            <?php if ($month_filter || $lake_filter || $status_filter) : ?>
                <div style="margin-top: 10px; padding: 8px 12px; background: #f0f6fc; border-left: 4px solid #72aee6;">
                    <strong>Active Filters:</strong>
                    <?php
                    $active_filters = array();
                    if ($month_filter) {
                        $active_filters[] = 'Month: ' . date('F Y', strtotime($month_filter . '-01'));
                    }
                    if ($lake_filter) {
                        $lake_name = '';
                        foreach ($lakes as $lake) {
                            if ($lake['id'] == $lake_filter) {
                                $lake_name = $lake['lake_name'];
                                break;
                            }
                        }
                        if ($lake_name) {
                            $active_filters[] = 'Lake: ' . $lake_name;
                        }
                    }
                    if ($status_filter) {
                        $active_filters[] = 'Status: ' . ucfirst($status_filter);
                    }
                    echo esc_html(implode(', ', $active_filters));
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $displayed_bookings ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo $get_sortable_header( 'booking_id', 'Booking ID' ); ?></th>
                        <th><?php echo $get_sortable_header( 'date_start', 'Dates' ); ?></th>
                        <th><?php echo $get_sortable_header( 'lake_name', 'Lake' ); ?></th>
                        <th><?php echo $get_sortable_header( 'peg_name', 'Peg' ); ?></th>
                        <th><?php echo $get_sortable_header( 'match_type_slug', 'Match Type' ); ?></th>
                        <th><?php echo $get_sortable_header( 'booking_status', 'Status' ); ?></th>
                        <th><?php echo $get_sortable_header( 'created_at', 'Created' ); ?></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $displayed_bookings as $booking ) : ?>
                        <tr>
                            <td>
                                <strong>#<?php echo esc_html( $booking['booking_id'] ); ?></strong>
                            </td>
                            <td>
                                <strong>From:</strong> <?php echo esc_html( date( 'd-M-Y', strtotime( $booking['date_start'] ) ) ); ?><br>
                                <strong>To:</strong> <?php echo esc_html( date( 'd-M-Y', strtotime( $booking['date_end'] ) ) ); ?>
                            </td>
                            <td><?php echo esc_html( $booking['lake_name'] ); ?></td>
                            <td><?php echo esc_html( $booking['peg_name'] ); ?></td>
                            <td>
                                <span style="text-transform: capitalize;">
                                    <?php echo esc_html( str_replace( '_', ' ', $booking['match_type_slug'] ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="booking-status status-<?php echo esc_attr( $booking['booking_status'] ); ?>">
                                    <?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( date( 'd-M-Y H:i', strtotime( $booking['created_at'] ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-booking&booking_id=' . $booking['booking_id'] ) ); ?>"
                                   class="button button-small button-primary">
                                    Edit
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-bookings' ) ); ?>"
                                   class="button button-small">
                                    View All
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $bookings_per_page !== -1 && count( $filtered_bookings ) > $bookings_per_page ) : ?>
                <div style="margin-top: 15px; text-align: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
                    <p style="color: #666; margin: 0;">
                        Showing <?php echo count( $displayed_bookings ); ?> of <?php echo count( $filtered_bookings ); ?> bookings.
                        <a href="#" onclick="updateBookingsPerPage(-1); return false;">Show all bookings</a>
                    </p>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="notice notice-info">
                <p>
                    <?php if ($month_filter || $lake_filter || $status_filter) : ?>
                        No bookings found matching your filters.
                        <a href="<?php echo esc_url( add_query_arg( array(
                            'page' => $_GET['page'],
                            'id' => $club_data['id'],
                            'sort_by' => $sort_by,
                            'sort_order' => $sort_order,
                            'bookings_per_page' => $bookings_per_page
                        ), admin_url('admin.php') ) ); ?>">Clear filters</a> to see all bookings.
                    <?php else : ?>
                        No bookings found for this club.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        <style>
            .booking-status {
                font-weight: bold;
                padding: 4px 8px;
                border-radius: 4px;
                display: inline-block;
                font-size: 0.85em;
            }
            .status-confirmed { background-color: #d4edda; color: #155724; }
            .status-booked { background-color: #65a2d3; color: #d7e7f4; }
            .status-draft { background-color: #fff3cd; color: #856404; }
            .status-cancelled { background-color: #f8d7da; color: #721c24; }

            /* Style for sortable headers */
            th a {
                display: block;
                padding: 8px 10px;
                font-weight: bold;
            }
            th a:hover {
                background-color: #f0f0f1;
            }
            .sortable-arrow {
                display: inline-block;
                padding-left: 8px;
                font-size: 22px;
                font-weight: bold;
                line-height: 0;
                position: relative;
                top: 2px;
            }

            /* Filter styles */
            .tablenav .actions {

            }
            .tablenav .actions select {
                height: 32px;
                line-height: 32px;
            }
        </style>
    <?php endif; ?>

    <hr style="margin: 20px 0;">

    <h2>Edit club</h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="club-form">

        <?php wp_nonce_field( 'mybp_club_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_add_club">
        <input type="hidden" name="club_id" value="<?php echo isset( $club_data['id'] ) ? absint( $club_data['id'] ) : 0; ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="club_name">Club Name *</label></th>
                <td>
                    <input type="text" name="club_name" id="club_name" class="regular-text" required value="<?php echo $get_club_val( 'club_name' ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="club_address">Address *</label></th>
                <td>
                    <textarea name="club_address" id="club_address" class="large-text" rows="4" required><?php echo esc_textarea( $get_club_val( 'club_address' ) ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="postcode">Postcode</label></th>
                <td>
                    <input type="text" name="postcode" id="postcode" class="regular-text" value="<?php echo $get_club_val( 'postcode' ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="country">Country *</label></th>
                <td>
                    <input type="text" name="country" id="country" class="regular-text" required value="<?php echo $get_club_val( 'country' ) ? $get_club_val( 'country' ) : 'United Kingdom'; ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="contact_name">Contact Name *</label></th>
                <td>
                    <input type="text" name="contact_name" id="contact_name" class="regular-text" required value="<?php echo $get_club_val( 'contact_name' ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="phone">Phone</label></th>
                <td>
                    <input type="text" name="phone" id="phone" class="regular-text" value="<?php echo $get_club_val( 'phone' ); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="email">Email</label></th>
                <td>
                    <input type="email" name="email" id="email" class="regular-text" value="<?php echo $get_club_val( 'email' ); ?>">
                    <!-- <p class="description" id="email-validation-message" style="display: none; color: #dc3232; font-weight: bold;"></p> -->
                </td>
            </tr>
            <tr>
                <?php $club_status = $get_club_val( 'club_status' ) ? $get_club_val( 'club_status' ) : 'enabled'; ?>
                <th scope="row"><label for="club_status">Club Status</label></th>
                <td>
                    <select name="club_status" id="club_status">
                        <option value="enabled" <?php selected( $club_status, 'enabled' ); ?>>Enabled</option>
                        <option value="disabled" <?php selected( $club_status, 'disabled' ); ?>>Disabled</option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-large" value="<?php echo isset( $club_data['id'] ) ? 'Update Club' : 'Add Club'; ?>" id="submit-club">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-clubs' ) ); ?>" class="button button-secondary button-large">Cancel</a>
        </p>

    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // var emailCheckTimeout;
    var currentClubId = <?php echo isset( $club_data['id'] ) ? $club_data['id'] : 0; ?>;

    // $('#email').on('input', function() {
        // var email = $(this).val();
        // var $validationMessage = $('#email-validation-message');
        // var $submitButton = $('#submit-club');
//
        // // Clear previous timeout
        // clearTimeout(emailCheckTimeout);
//
        // // Hide validation message while typing
        // $validationMessage.hide();
        // $submitButton.prop('disabled', false);
//
        // // Only check if email is not empty and looks like a valid email
        // if (email && email.indexOf('@') > -1) {
            // emailCheckTimeout = setTimeout(function() {
                // $.ajax({
                    // url: ajaxurl,
                    // type: 'POST',
                    // data: {
                        // action: 'mybp_check_club_email',
                        // email: email,
                        // club_id: currentClubId,
                        // nonce: '<?php echo wp_create_nonce("mybp_email_check"); ?>'
                    // },
                    // success: function(response) {
                        // if (response.success) {
                            // if (response.data.exists) {
                                // $validationMessage.text('This email is already registered to another club.').show();
                                // $submitButton.prop('disabled', true);
                            // } else {
                                // $validationMessage.hide();
                                // $submitButton.prop('disabled', false);
                            // }
                        // }
                    // }
                // });
            // }, 500); // Wait 500ms after user stops typing
        // }
    // });

    // Basic email format validation on form submit
    // $('#club-form').on('submit', function(e) {
        // var email = $('#email').val();
        // if (email && !isValidEmail(email)) {
            // e.preventDefault();
            // $('#email-validation-message').text('Please enter a valid email address.').show();
            // return false;
        // }
    // });

    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});

// Function to update the bookings per page
function updateBookingsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('bookings_per_page', value);

    // Preserve current settings
    url.searchParams.set('sort_by', '<?php echo esc_js( $sort_by ); ?>');
    url.searchParams.set('sort_order', '<?php echo esc_js( $sort_order ); ?>');
    url.searchParams.set('month_filter', '<?php echo esc_js( $month_filter ); ?>');
    url.searchParams.set('lake_filter', '<?php echo esc_js( $lake_filter ); ?>');
    url.searchParams.set('status_filter', '<?php echo esc_js( $status_filter ); ?>');

    window.location.href = url.toString();
}
</script>