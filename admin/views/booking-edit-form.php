<?php
/**
 * Admin View: Edit Booking Form
 *
 * @var array $lakes
 * @var array $match_types
 * @var array $clubs
 * @var array $customers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure jQuery UI Datepicker is loaded
wp_enqueue_script( 'jquery-ui-datepicker' );
wp_enqueue_style( 'jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );

$default_start_date = current_time( 'Y-m-d' );
$default_end_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $default_start_date ) ) );
?>

<div class="wrap">
    <h1><?php echo $title; ?></h1>
    <hr class="wp-header-end">

    <?php
    if ( isset( $_GET['message'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field($_GET['message']) ) . '</p></div>';
    }
    if ( isset( $_GET['error'] ) ) {
        $error_msg = isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : 'An error occurred during booking.';
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
    }
    ?>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
	    <?php wp_nonce_field('mybp_booking_nonce'); ?>
	    <input type="hidden" name="action" value="mybp_update_booking">
	    <input type="hidden" name="booking_id" value="<?php echo isset($booking_data['id']) ? absint($booking_data['id']) : 0; ?>">
	    <input type="hidden" name="booking_status" value="booked">

	    <h2>Booking Parameters</h2>
	    <table class="form-table" role="presentation">
	        <tr>
	            <th scope="row"><label for="lake_id">Lake *</label></th>
	            <td>
	                <select name="lake_id" id="lake_id" required>
	                    <option value="">Select Lake</option>
	                    <?php foreach ($lakes as $lake) : ?>
	                        <?php if ($lake['lake_status'] === 'enabled') : ?>
	                            <option
	                                value="<?php echo absint($lake['id']); ?>"
	                                <?php selected($lake['id'], isset($booking_data['lake_id']) ? $booking_data['lake_id'] : ''); ?>>
	                                <?php echo esc_html($lake['lake_name']); ?>
	                            </option>
	                        <?php endif; ?>
	                    <?php endforeach; ?>
	                </select>
	            </td>
	        </tr>
	        <tr>
	            <th scope="row"><label for="date_start">Start Date *</label></th>
	            <td>
	                <input type="text"
	                       name="date_start"
	                       id="date_start"
	                       class="regular-text mybp-datepicker"
	                       required
	                       value="<?php
	                           echo esc_attr(
	                               isset($booking_data['date_start']) && !empty($booking_data['date_start'])
	                                   ? $booking_data['date_start']
	                                   : $default_start_date
	                           );
	                       ?>"
	                       readonly>
	            </td>
	        </tr>
	        <tr>
	            <th scope="row"><label for="date_end">End Date *</label></th>
	            <td>
	                <input type="text"
	                       name="date_end"
	                       id="date_end"
	                       class="regular-text mybp-datepicker"
	                       required
	                       value="<?php
	                           echo esc_attr(
	                               isset($booking_data['date_end']) && !empty($booking_data['date_end'])
	                                   ? $booking_data['date_end']
	                                   : $default_end_date
	                           );
	                       ?>"
	                       readonly>
	                <p class="description">Bookings include the end date. A single day booking should have the same start/end date.</p>
	            </td>
	        </tr>
	        <tr>
	        	<th scope="row"><label for="booking_status">Booking Status</label></th>
	        	<td>
	        		<input type="text" name="booking_status" id="booking_status" readonly="" value="<?php echo $booking_data['booking_status'] ?>" style="capitalize" />
	        	</td>
	        </tr>
	    </table>

	    <h2>Peg Availability &amp; Selection</h2>

	    <div id="pegs-loader" style="text-align: center; padding: 20px; display: none;">
	        <p>Loading peg availability...</p>
	    </div>

	    <div id="pegs-container">
	        <p class="notice notice-info">Please select a Lake, Start Date, and End Date above to view peg availability.</p>
	    </div>

	    <p class="submit">
	        <input type="submit" id="submit-booking" class="button button-primary button-large" value="Update Booking">
	    </p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
    var ajaxUrl = ajaxurl;
    var currentBookingData = <?php echo json_encode($booking_data); ?>;

    // Initialize date pickers
    $('.mybp-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        onSelect: function(dateText, inst) {
            if (inst.id === 'date_start' && $('#date_end').val() < dateText) {
                $('#date_end').val(dateText);
            }
            if (inst.id === 'date_end' && $('#date_start').val() > dateText) {
                $('#date_start').val(dateText);
            }
            fetchPegAvailability();
        }
    });

    $('#lake_id, #date_start, #date_end').on('change', fetchPegAvailability);

    // Load initial peg data if we have a booking
    if (currentBookingData && currentBookingData.id) {
        fetchPegAvailability();
    }

    function fetchPegAvailability() {
        var lakeId = $('#lake_id').val();
        var startDate = $('#date_start').val();
        var endDate = $('#date_end').val();
        var $pegsContainer = $('#pegs-container');

        if (!lakeId || !startDate || !endDate) {
            $pegsContainer.html('<p class="notice notice-info">Please select a Lake, Start Date, and End Date above to view peg availability.</p>');
            return;
        }

        $('#pegs-loader').show();
        $pegsContainer.empty();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'mybp_get_pegs',
                lake_id: lakeId,
                start_date: startDate,
                end_date: endDate,
            },
            success: function(response) {
                $('#pegs-loader').hide();

                if (response.success && response.data.pegs.length > 0) {
                    renderPegsTable(response.data.pegs);
                } else if (response.success && response.data.pegs.length === 0) {
                    $pegsContainer.html('<p class="notice notice-warning">No open pegs found for this lake.</p>');
                } else {
                    $pegsContainer.html('<p class="notice notice-error">Error fetching availability: ' + (response.data || 'Unknown error') + '</p>');
                }
            },
            error: function() {
                $('#pegs-loader').hide();
                $pegsContainer.html('<p class="notice notice-error">Network error while fetching peg data.</p>');
            }
        });
    }

    function renderPegsTable(pegs) {
        var tableHtml = `
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 15%;">Peg Name</th>
                        <th style="width: 10%;">Book</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 30%;">Match Type</th>
                        <th style="width: 30%;">Club</th>
                    </tr>
                </thead>
                <tbody>
        `;

        pegs.forEach(function(peg) {
            var isBooked = peg.is_booked === 'booked';
            var isCurrentlyBooked = isPegCurrentlyBooked(peg.peg_id);
            var rowClass = isBooked ? 'style="background-color: #fce7e7;"' : 'style="background-color: #e6ffe6;"';
            var statusText = isBooked ? 'BOOKED' : 'Available';
            var statusColor = isBooked ? 'tag-red' : 'tag-green';

            // Generate Match Type Dropdown with current selection
            var matchTypeSelect = '<select name="pegs[' + peg.peg_id + '][match_type_slug]" class="match-type-selector" ' + (isBooked && !isCurrentlyBooked ? 'disabled' : '') + '>';
            matchTypeSelect += '<option value="">Select Type</option>';
            <?php foreach ($match_types as $type) : ?>
                var selected = getCurrentPegValue(peg.peg_id, 'match_type_slug') === '<?php echo esc_attr($type['type_slug']); ?>' ? 'selected' : '';
                matchTypeSelect += '<option value="<?php echo esc_attr($type['type_slug']); ?>" ' + selected + '><?php echo esc_html($type['type_name']); ?></option>';
            <?php endforeach; ?>
            matchTypeSelect += '</select>';

            // Generate Club Dropdown with current selection
            var clubSelect = '<select name="pegs[' + peg.peg_id + '][club_id]" class="club-selector" ' + (isBooked && !isCurrentlyBooked ? 'disabled' : '') + '>';
            clubSelect += '<option value="">Select Club</option>';
            <?php foreach ($clubs as $club) : ?>
                var selected = getCurrentPegValue(peg.peg_id, 'club_id') == '<?php echo absint($club['id']); ?>' ? 'selected' : '';
                clubSelect += '<option value="<?php echo absint($club['id']); ?>" ' + selected + '><?php echo esc_html($club['club_name']); ?></option>';
            <?php endforeach; ?>
            clubSelect += '</select>';

            // FIX: Only check the box if the peg is currently booked in this booking
            var shouldBeBooked = isCurrentlyBooked;
            var statusValue = shouldBeBooked ? 'booked' : 'available';
            var isToggleDisabled = isBooked && !isCurrentlyBooked;

            var statusInput = '<input type="hidden" name="pegs[' + peg.peg_id + '][status]" value="' + statusValue + '" class="status-input">';

            var bookToggle = '<label>' +
                                '<input type="checkbox" class="peg-book-toggle" data-peg-id="' + peg.peg_id + '" ' +
                                (shouldBeBooked ? 'checked' : '') + ' ' +
                                (isToggleDisabled ? 'disabled' : '') + '>' +
                                ' Book' +
                            '</label>';

            tableHtml += `
                <tr ${rowClass} data-peg-id="${peg.peg_id}">
                    <td>${peg.peg_name}</td>
                    <td>${statusInput} ${bookToggle}</td>
                    <td><span class="${statusColor}">${statusText}</span></td>
                    <td>${matchTypeSelect}</td>
                    <td>${clubSelect}</td>
                </tr>
            `;
        });

        tableHtml += '</tbody></table>';
        $('#pegs-container').html(tableHtml);

        // Enable/disable form elements based on booking status
        $('#pegs-container').on('change', '.peg-book-toggle', function() {
            var $row = $(this).closest('tr');
            var isChecked = $(this).is(':checked');

            $row.find('.match-type-selector, .club-selector')
                .prop('disabled', !isChecked)
                .prop('required', isChecked);

            $row.find('.status-input').val(isChecked ? 'booked' : 'available');

            if (!isChecked) {
                $row.find('.match-type-selector').val('');
                $row.find('.club-selector').val('');
            }
        });

        // Initialize the form state for currently booked pegs
        $('.peg-book-toggle:checked').each(function() {
            var $row = $(this).closest('tr');
            $row.find('.match-type-selector, .club-selector').prop('required', true);
        });

        // Disable selects for unbooked pegs initially
        $('.peg-book-toggle:not(:checked)').each(function() {
            var $row = $(this).closest('tr');
            $row.find('.match-type-selector, .club-selector').prop('disabled', true);
        });
    }

    function isPegCurrentlyBooked(pegId) {
        if (!currentBookingData.pegs) return false;
        return currentBookingData.pegs.some(function(peg) {
            return parseInt(peg.peg_id) === parseInt(pegId);
        });
    }

    function getCurrentPegValue(pegId, field) {
        if (!currentBookingData.pegs) return '';
        var peg = currentBookingData.pegs.find(function(p) {
            return parseInt(p.peg_id) === parseInt(pegId);
        });
        return peg ? peg[field] : '';
    }

    // Add CSS for status tags
    if ($('style:contains(".tag-green")').length === 0) {
        $('head').append(`
            <style>
                .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
                .tag-red { background-color: #fce7e7; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
            </style>
        `);
    }
});
</script>