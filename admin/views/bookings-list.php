<?php
/**
 * Admin View: Bookings List
 *
 * @var array $bookings Array of booking objects including 'pegs'
 */

// Get filter parameters
$month_filter = isset($_GET['month_filter']) ? sanitize_text_field($_GET['month_filter']) : '';
$lake_filter = isset($_GET['lake_filter']) ? intval($_GET['lake_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

// Get all lakes for the filter dropdown
global $wpdb;
$lakes_table = $wpdb->prefix . 'booking_lakes';
$lakes = $wpdb->get_results("SELECT id, lake_name FROM {$lakes_table} ORDER BY lake_name ASC", ARRAY_A);

// Build filter URL base
$filter_url = admin_url('admin.php?page=my-booking-bookings');

// Generate month options (last 12 months + future months)
$month_options = array();
$current_date = new DateTime();
for ($i = -6; $i <= 6; $i++) {
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

// Handle pagination
$bookings_per_page = isset( $_GET['bookings_per_page'] ) ? intval( $_GET['bookings_per_page'] ) : 10;

// Handle sorting
$sort_by = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'created_at';
$sort_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'desc';

// Validate sort parameters
$allowed_sort_columns = array('lake_name', 'date_start', 'date_end', 'booking_status', 'created_at');
if ( ! in_array( $sort_by, $allowed_sort_columns ) ) {
    $sort_by = 'created_at';
}
if ( ! in_array( $sort_order, array('asc', 'desc') ) ) {
    $sort_order = 'desc';
}

// Apply filters first
$filtered_bookings = array();
foreach ($bookings as $booking) {
    if ($month_filter) {
        $booking_month = date('Y-m', strtotime($booking['date_start']));
        if ($booking_month !== $month_filter) {
            continue;
        }
    }
    if ($lake_filter && $booking['lake_id'] != $lake_filter) {
        continue;
    }
    if ($status_filter && $booking['booking_status'] != $status_filter) {
        continue;
    }
    $filtered_bookings[] = $booking;
}

// Sort the filtered bookings array
usort( $filtered_bookings, function( $a, $b ) use ( $sort_by, $sort_order ) {
    $a_value = $a[ $sort_by ] ?? '';
    $b_value = $b[ $sort_by ] ?? '';

    if ( in_array( $sort_by, array('date_start', 'date_end', 'created_at') ) ) {
        $a_timestamp = strtotime( $a_value );
        $b_timestamp = strtotime( $b_value );
        if ( $sort_order === 'asc' ) {
            return $a_timestamp - $b_timestamp;
        } else {
            return $b_timestamp - $a_timestamp;
        }
    }

    if ( $sort_order === 'asc' ) {
        return strcasecmp( $a_value, $b_value );
    } else {
        return strcasecmp( $b_value, $a_value );
    }
} );

// Apply pagination
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
        $sort_icon = $sort_order === 'asc' ? ' ↑' : ' ↓';
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
    <h1 class="wp-heading-inline">Bookings</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-new-booking' ) ); ?>" class="page-title-action">Add New Booking</a>
    <hr class="wp-header-end">

    <!-- Controls Section -->
    <div style="margin-top: 20px; margin-bottom: 10px; background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" action="<?php echo esc_url( $filter_url ); ?>">
                    <input type="hidden" name="page" value="my-booking-bookings">
                    <input type="hidden" name="sort_by" value="<?php echo esc_attr($sort_by); ?>">
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr($sort_order); ?>">
                    <input type="hidden" name="bookings_per_page" value="<?php echo esc_attr($bookings_per_page); ?>">

                    <select id="lake_filter" name="lake_filter" style="vertical-align: middle; margin-right: 10px;">
                        <option value="">All Lakes</option>
                        <?php foreach ($lakes as $lake) : ?>
                            <option value="<?php echo esc_attr($lake['id']); ?>" <?php selected($lake_filter, $lake['id']); ?>>
                                <?php echo esc_html($lake['lake_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="month_filter" name="month_filter" style="vertical-align: middle; margin-right: 10px;">
                        <option value="">All Months</option>
                        <?php foreach ($month_options as $value => $display) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($month_filter, $value); ?>>
                                <?php echo esc_html($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="status_filter" name="status_filter" style="vertical-align: middle; margin-right: 10px;">
                        <?php foreach ($status_options as $value => $display) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($status_filter, $value); ?>>
                                <?php echo esc_html($display); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="submit" name="filter_action" id="filter-submit" class="button" value="Filter">

                    <?php if ($month_filter || $lake_filter || $status_filter) : ?>
                        <a href="<?php echo esc_url( add_query_arg( array(
                            'sort_by' => $sort_by,
                            'sort_order' => $sort_order,
                            'bookings_per_page' => $bookings_per_page
                        ), $filter_url ) ); ?>" class="button" style="margin-left: 5px;">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            <div style="float: right; margin-bottom: 15px;">
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
                </span>
            </div>
        </div>
    </div>

    <table class="wp-list-table widefat striped fixed">
        <thead>
            <tr>
                <th width="10%"><?php echo $get_sortable_header( 'lake_name', 'Lake' ); ?></th>
                <th width="20%"><?php echo $get_sortable_header( 'date_start', 'Dates' ); ?></th>
                <th width="15%">Pegs Booked</th>
                <th width="10%"><?php echo $get_sortable_header( 'booking_status', 'Status' ); ?></th>
                <th width="15%"><?php echo $get_sortable_header( 'created_at', 'Created' ); ?></th>
                <th width="30%" class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $displayed_bookings ) ) : ?>
                <?php foreach ( $displayed_bookings as $booking ) : ?>
                    <?php
                    $booking_id = absint( $booking['id'] );
                    $edit_url = admin_url('admin.php?page=my-booking-edit-booking&booking_id=' . $booking_id);
                    ?>
                    <tr id="booking-row-<?php echo $booking_id; ?>">
                        <td><strong><?php echo esc_html( $booking['lake_name'] ); ?></strong></td>
                        <td>
                            <strong>Start date: </strong><?php echo esc_html( date('d-M-Y', strtotime($booking['date_start'])) ); ?><br>
                            <strong>End date: </strong><?php echo esc_html( date('d-M-Y', strtotime($booking['date_end'])) ); ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $booking['pegs'] ) ) : ?>       
                                <strong><?php echo count($booking['pegs']); ?></strong> peg(s) booked                 
                                <div style="margin-top: 5px;">
                                    <button type="button"
                                        class="button button-small view-pegs-details"
                                        data-booking-id="<?php echo $booking_id; ?>"
                                        data-lake-name="<?php echo esc_attr($booking['lake_name']); ?>"
                                        data-start-date="<?php echo esc_attr($booking['date_start']); ?>"
                                        data-end-date="<?php echo esc_attr($booking['date_end']); ?>"
                                        data-total-pegs="<?php echo count($booking['pegs']); ?>"
                                        data-pegs='<?php echo esc_attr( wp_json_encode( $booking['pegs'] ) ); ?>'>
                                        View <?php echo count($booking['pegs']); ?> pegs
                                    </button>
                                </div>
                            <?php else : ?>
                                <em>No pegs recorded.</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="booking-status status-<?php echo esc_attr( $booking['booking_status'] ); ?>">
                                <?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( date('d-M-Y H:i', strtotime($booking['created_at'])) ); ?></td>
                        <td>                              
                            <button class="button button-small delete-booking" data-booking-id="<?php echo $booking_id; ?>" data-delete-nonce="<?php echo wp_create_nonce( 'wp_rest' );?>">Delete</button>

                            <?php if (!empty($booking['order_id'])): ?>                                
                                <a href="<?php echo admin_url('post.php?post=' . $booking['order_id'] . '&action=edit'); ?>" class="button button-small" target="_blank">View Order #<?php echo $booking['order_id']; ?></a>
                            <?php else: ?>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="edit-booking button button-primary button-small" style="margin-left: 5px;">Edit</a>
                                <button class="button button-primary button-small send-to-woocommerce" data-booking-id="<?php echo $booking['id']; ?>" data-nonce="<?php echo wp_create_nonce('send_booking_to_woocommerce'); ?>" style="margin-left: 5px;">Send to WooCommerce</button>
                                <span class="spinner" style="float:none;display:none;"></span>
                            <?php endif; ?> 
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No bookings found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th><?php echo $get_sortable_header( 'lake_name', 'Lake' ); ?></th>
                <th><?php echo $get_sortable_header( 'date_start', 'Dates' ); ?></th>
                <th>Pegs Booked</th>
                <th><?php echo $get_sortable_header( 'booking_status', 'Status' ); ?></th>
                <th><?php echo $get_sortable_header( 'created_at', 'Created' ); ?></th>
                <th class="text-right">Actions</th>
            </tr>
        </tfoot>
    </table>

    <style>
        .text-right { text-align: right; }
        .booking-status { font-weight: bold; padding: 2px 8px; border-radius: 4px; display: inline-block; font-size: 0.9em; }
        .status-confirmed { background-color: #d4edda; color: #155724; }
        .status-booked { background-color: #65a2d3; color: #d7e7f4; }
        .status-draft { background-color: #fff3cd; color: #856404; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        
        .pegs-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 100001;
            min-width: 500px;
            max-width: 700px;
            max-height: 80vh;
            overflow: auto;
        }
        #pegs-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
            display: none;
        }
        .booking-info-box {
            background: #f0f6fc;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #2271b1;
            border-radius: 4px;
        }
        .booking-info-box p {
            margin: 5px 0;
        }
        .booking-info-label {
            font-weight: bold;
            min-width: 100px;
            display: inline-block;
        }
        .total-pegs-badge {
            background: #2271b1;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            margin-left: 8px;
        }
    </style>
</div>

<!-- Modal for viewing peg details -->
<div id="pegs-modal">
    <div class="pegs-modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #f0f0f1; padding-bottom: 10px;">
            <h3 style="margin: 0;">Peg Details for Booking #<span id="modal-booking-id"></span></h3>
            <span id="modal-total-pegs" class="total-pegs-badge"></span>
        </div>
        <div id="booking-info" class="booking-info-box"></div>
        <div id="pegs-list" style="max-height: 350px; overflow-y: auto;"></div>
        <div style="margin-top: 20px; text-align: right;">
            <button type="button" class="button" onclick="closePegsModal()">Close</button>
        </div>
    </div>
</div>

<script>
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closePegsModal() {
    jQuery('#pegs-modal').fadeOut(200);
}

function updateBookingsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('bookings_per_page', value);
    url.searchParams.set('sort_by', '<?php echo esc_js( $sort_by ); ?>');
    url.searchParams.set('sort_order', '<?php echo esc_js( $sort_order ); ?>');
    url.searchParams.set('month_filter', '<?php echo esc_js( $month_filter ); ?>');
    url.searchParams.set('lake_filter', '<?php echo esc_js( $lake_filter ); ?>');
    url.searchParams.set('status_filter', '<?php echo esc_js( $status_filter ); ?>');
    window.location.href = url.toString();
}

jQuery(document).ready(function($) {
    const restRoot = '<?php echo esc_url_raw( get_rest_url( null, 'my-booking-plugin/v1/book/' ) ); ?>';
    
    $('.delete-booking').on('click', function() {
        const $button = $(this);
        const bookingId = $button.data('booking-id');
        const nonce = $button.data('delete-nonce');

        if (confirm('Are you sure you want to delete Booking ID ' + bookingId + '?')) {
            $button.prop('disabled', true).text('Deleting...');
            $.ajax({
                url: restRoot + bookingId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                },
                success: function(response) {
                    $('#booking-row-' + bookingId).fadeOut(300, function() {
                        $(this).remove();
                        alert('Success: ' + response.message);
                    });
                },
                error: function(xhr) {
                    alert('Error deleting booking.');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        }
    });

    $('.view-pegs-details').on('click', function() {
        const $button = $(this);
        const bookingId = $button.data('booking-id');
        const lakeName = $button.data('lake-name');
        const startDate = $button.data('start-date');
        const endDate = $button.data('end-date');
        const totalPegs = $button.data('total-pegs');
        let pegsData = $button.data('pegs');
        
        const formatDate = function(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        };
        
        if (typeof pegsData === 'string') {
            try {
                pegsData = JSON.parse(pegsData);
            } catch(e) {
                pegsData = [];
            }
        }
        
        $('#modal-booking-id').text(bookingId);
        $('#modal-total-pegs').text(totalPegs + ' peg' + (totalPegs !== 1 ? 's' : ''));
        
        let bookingInfoHtml = '<p><span class="booking-info-label">Lake:</span> <strong>' + escapeHtml(lakeName) + '</strong></p>';
        bookingInfoHtml += '<p><span class="booking-info-label">Start Date:</span> ' + escapeHtml(formatDate(startDate)) + '</p>';
        bookingInfoHtml += '<p><span class="booking-info-label">End Date:</span> ' + escapeHtml(formatDate(endDate)) + '</p>';
        bookingInfoHtml += '<p><span class="booking-info-label">Pegs:</span> <strong>' + escapeHtml(totalPegs) + '</strong></p>';
        $('#booking-info').html(bookingInfoHtml);
        
        if (pegsData && pegsData.length > 0) {
            let pegsHtml = '<table class="widefat striped"><thead><tr><th>#</th><th>Peg Name</th><th>Club</th><th>Match Type</th></tr></thead><tbody>';
            pegsData.forEach(function(peg, index) {
                pegsHtml += '<tr>';
                pegsHtml += '<td>' + (index + 1) + '</td>';
                pegsHtml += '<td><strong>' + escapeHtml(peg.peg_name) + '</strong></td>';
                pegsHtml += '<td>' + escapeHtml(peg.club_name) + '</td>';
                pegsHtml += '<td>' + escapeHtml(peg.match_type_slug.replace(/_/g, ' ')) + '</td>';
                pegsHtml += '</tr>';
            });
            pegsHtml += '</tbody></table>';
            $('#pegs-list').html(pegsHtml);
        } else {
            $('#pegs-list').html('<p>No peg details available.</p>');
        }
        
        $('#pegs-modal').fadeIn(200);
    });

    $('.send-to-woocommerce').on('click', function() {
        var button = $(this);
        var bookingId = button.data('booking-id');
        var spinner = button.siblings('.spinner');
        button.siblings('.edit-booking').hide();
        button.prop('disabled', true);
        spinner.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_booking_to_woocommerce',
                booking_id: bookingId,
                nonce: button.data('nonce')
            },
            success: function(response) {
                spinner.hide();
                if (response.success) {
                    button.replaceWith('<a href="' + response.data.order_edit_url + '" class="button button-small" target="_blank">View Order #' + response.data.order_id + '</a>');
                    $('body').append('<div class="notice notice-success is-dismissible" style="position:fixed;top:50px;right:20px;z-index:9999;"><p>Order #' + response.data.order_id + ' created!</p></div>');
                    setTimeout(function() { $('.notice-success').fadeOut(); }, 3000);
                } else {
                    alert('Error: ' + response.data.message);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                spinner.hide();
                alert('AJAX error occurred');
                button.prop('disabled', false);
            }
        });
    });
});

document.addEventListener('click', function(event) {
    const modal = document.getElementById('pegs-modal');
    if (event.target === modal) {
        closePegsModal();
    }
});
</script>