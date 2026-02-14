<?php
/**
 * Admin View: New Booking Form
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
    <h1>New Booking</h1>
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

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( 'mybp_booking_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_add_booking">
        <input type="hidden" name="booking_status" value="booked"> <h2>Booking Parameters</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="lake_id">Lake *</label></th>
                <td>
                    <select name="lake_id" id="lake_id" required>
                        <option value="">Select Lake</option>
                        <?php foreach ( $lakes as $lake ) : ?>
                            <?php if ( $lake['lake_status'] === 'enabled' ) : ?>
                                <option value="<?php echo absint( $lake['id'] ); ?>">
                                    <?php echo esc_html( $lake['lake_name'] ); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="date_start">Start Date *</label></th>
                <td>
                    <input type="text" name="date_start" id="date_start" class="regular-text mybp-datepicker" required
                           value="<?php echo esc_attr( $default_start_date ); ?>" readonly>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="date_end">End Date *</label></th>
                <td>
                    <input type="text" name="date_end" id="date_end" class="regular-text mybp-datepicker" required
                           value="<?php echo esc_attr( $default_end_date ); ?>" readonly>
                    <p class="description">Bookings include the end date. A single day booking should have the same start/end date.</p>
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
            <input type="submit" id="submit-booking" class="button button-primary button-large" value="Create Booking" disabled>
        </p>

    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var ajaxUrl = ajaxurl; // WordPress global for the admin AJAX endpoint

    // Initialize date pickers
    $('.mybp-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0, // Prevent booking in the past
        onSelect: function(dateText, inst) {
            // If the start date is later than the end date, update end date to match start date
            if (inst.id === 'date_start' && $('#date_end').val() < dateText) {
                $('#date_end').val(dateText);
            }
            // If the end date is earlier than the start date, update start date to match end date
            if (inst.id === 'date_end' && $('#date_start').val() > dateText) {
                $('#date_start').val(dateText);
            }
            fetchPegAvailability();
        }
    });

    // Event handlers for parameter changes
    $('#lake_id, #date_start, #date_end').on('change', fetchPegAvailability);

    // Initial check (if default values are set)
    fetchPegAvailability();

    /**
     * AJAX function to fetch and render peg availability.
     */
    function fetchPegAvailability() {
        var lakeId = $('#lake_id').val();
        var startDate = $('#date_start').val();
        var endDate = $('#date_end').val();
        var $pegsContainer = $('#pegs-container');
        var $submitButton = $('#submit-booking');

        $submitButton.prop('disabled', true);

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
                    $submitButton.prop('disabled', false);
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

    /**
     * Renders the peg availability table from the fetched data.
     */
    function renderPegsTable(pegs) {
    	// Generate Match Type Dropdown
        var matchTypeSelect = '<select class="match-type-selector header-select">';
        matchTypeSelect += '<option value="">Select Type</option>';
        <?php foreach ( $match_types as $type ) : ?>
            matchTypeSelect += '<option value="<?php echo esc_attr( $type['type_slug'] ); ?>"><?php echo esc_html( $type['type_name'] ); ?></option>';
        <?php endforeach; ?>
        matchTypeSelect += '</select>';

        // Generate Club Dropdown
        var clubSelect = '<select class="club-selector header-select">';
        clubSelect += '<option value="">Select Club</option>';
        <?php foreach ( $clubs as $club ) : ?>
            // Assuming $clubs has 'id' and 'club_name'
            clubSelect += '<option value="<?php echo absint( $club['id'] ); ?>"><?php echo esc_html( $club['club_name'] ); ?></option>';
        <?php endforeach; ?>
        clubSelect += '</select>';
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
                	<tr>
                        <th style="width: 15%;"></th>
                        <th style="width: 10%;"></th>
                        <th style="width: 15%;"></th>
                        <th style="width: 30%;">
                    		${matchTypeSelect}
                            <button id="applyMatchType">Apply</button>
                        </th>
                        <th style="width: 30%;">
                        	${clubSelect}
                            <button id="applyClub">Apply</button>
                        </th>
                    </tr>
                    <script>
                    document.getElementById('applyMatchType').addEventListener('click', function(e) {
                    	e.preventDefault();
					    const headerSelect = document.querySelector('.match-type-selector.header-select');
					    const valueToApply = headerSelect.value;
					    if (!valueToApply) return;										    document.querySelectorAll('.match-type-selector:not(.header-select)').forEach(select => {
					        select.value = valueToApply;
					    });
					});
					document.getElementById('applyClub').addEventListener('click', function(e) {
						e.preventDefault();
					    const headerSelect = document.querySelector('.club-selector.header-select');
					    const valueToApply = headerSelect.value;
					    if (!valueToApply) return;										    document.querySelectorAll('.club-selector:not(.header-select)').forEach(select => {
					        select.value = valueToApply;
					    });
					});
					<\/script>
                    `;

        pegs.forEach(function(peg) {
            var isBooked = peg.is_booked === 'booked';
            var rowClass = isBooked ? 'style="background-color: #fce7e7;"' : 'style="background-color: #e6ffe6;"';
            var statusText = isBooked ? 'BOOKED' : 'Available';
            var statusColor = isBooked ? 'tag-red' : 'tag-green';

            // Generate Match Type Dropdown
            var matchTypeSelect = '<select name="pegs[' + peg.peg_id + '][match_type_slug]" class="match-type-selector" ' + (isBooked ? 'disabled' : 'required') + '>';
            matchTypeSelect += '<option value="">Select Type</option>';
            <?php foreach ( $match_types as $type ) : ?>
                matchTypeSelect += '<option value="<?php echo esc_attr( $type['type_slug'] ); ?>"><?php echo esc_html( $type['type_name'] ); ?></option>';
            <?php endforeach; ?>
            matchTypeSelect += '</select>';

            // Generate Club Dropdown
            var clubSelect = '<select name="pegs[' + peg.peg_id + '][club_id]" class="club-selector" ' + (isBooked ? 'disabled' : 'required') + '>';
            clubSelect += '<option value="">Select Club</option>';
            <?php foreach ( $clubs as $club ) : ?>
                // Assuming $clubs has 'id' and 'club_name'
                clubSelect += '<option value="<?php echo absint( $club['id'] ); ?>"><?php echo esc_html( $club['club_name'] ); ?></option>';
            <?php endforeach; ?>
            clubSelect += '</select>';

            // Generate Status Toggle (Hidden input determines if it's saved as booked)
            var statusInput = '<input type="hidden" name="pegs[' + peg.peg_id + '][status]" value="' + (isBooked ? 'booked' : 'available') + '" class="status-input">';

            // The actual visible checkbox/toggle
            var bookToggle = '<label>' +
                                '<input type="checkbox" class="peg-book-toggle" data-peg-id="' + peg.peg_id + '" ' + (isBooked ? 'checked disabled' : 'checked') + '>' +
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

        // Add event listener for the toggle (Note: The system is designed to only allow booking, not unbooking in this form)
        $('#pegs-container').on('change', '.peg-book-toggle', function() {
            var $row = $(this).closest('tr');
            var isChecked = $(this).is(':checked');

            // Toggle the required attribute and disable status for the selects
            $row.find('.match-type-selector, .club-selector').prop('disabled', !isChecked).prop('required', isChecked);

            // Update the hidden status field
            $row.find('.status-input').val(isChecked ? 'booked' : 'available');

            // Optionally, clear values if unbooked (though usually you don't unbook on the new booking form)
            if (!isChecked) {
                $row.find('.match-type-selector').val('');
                $row.find('.club-selector').val('');
            }
        });
    }

    // Since 'peg options apply button' per peg is complex and usually requires server-side processing,
    // we'll implement a simplified approach where all selections are submitted at once with the main "Create Booking" button.
    // If you need per-peg saving, that would require a separate AJAX endpoint and button for each row.

    // CSS to make the status tags look good
    if ($('style:contains(".tag-green")').length === 0) {
        $('head').append(`
            <style>
                .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
                .tag-red { background-color: #fce7e7; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
            </style>
        `);
    }

    // Set initial dates if empty
    if (!$('#date_start').val()) {
        $('#date_start').val('<?php echo esc_attr( $default_start_date ); ?>');
    }
    if (!$('#date_end').val()) {
        $('#date_end').val('<?php echo esc_attr( $default_end_date ); ?>');
    }
});
</script>