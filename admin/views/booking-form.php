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
        <input type="hidden" name="booking_status" value="booked"> 
        
        <h2>Booking Parameters</h2>
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
            clubSelect += '<option value="<?php echo absint( $club['id'] ); ?>"><?php echo esc_html( $club['club_name'] ); ?></option>';
        <?php endforeach; ?>
        clubSelect += '</select>';
        
        var tableHtml = `
            <table class="wp-list-table widefat fixed striped booking-pegs-data">
                <thead>
                    <tr>
                        <th style="width: 15%;">Peg Name</th>
                        <th style="width: 10%;vertical-align:middle;">
                            <label>
                                <input type="checkbox" id="select-all-pegs"> Select all
                            </label>
                        </th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 30%;">Match Type</th>
                        <th style="width: 30%;">Club</th>
                    </tr>
                    <tr class="bulk-actions-row">
                        <th style="width: 15%;"></th>
                        <th colspan="2" style="width:25%">
                            <input type="text" id="peg-numbers-input" placeholder="Eg: 1,2,4-6,8" style="width: 150px;">
                            <button type="button" id="apply-peg-numbers" class="button button-small">Apply</button>
                            <button type="button" id="clear-selection" class="button button-small">&times;</button>
                            <!--<p class="description" style="margin-top: 5px;">Enter row numbers (1 = first peg, 2 = second peg, etc.)</p>-->
                        </th>
                        <th style="width:30%">
                            ${matchTypeSelect}
                            <button id="applyMatchType" class="button button-small">Apply</button>
                        </th>
                        <th style="width:30%">
                            ${clubSelect}
                            <button id="applyClub" class="button button-small">Apply</button>
                        </th>
                    </tr>
                    
                </thead>
                <tbody>`;

        pegs.forEach(function(peg, index) {
            var rowNumber = index + 1; // 1-based row number
            var isBooked = peg.is_booked === 'booked';
            var isChecked = !isBooked;
            var rowClass = isBooked ? 'style="background-color: #fce7e7;"' : 'style="background-color: #e6ffe6;"';
            var statusText = isBooked ? 'BOOKED' : 'Available';
            var statusColor = isBooked ? 'tag-red' : 'tag-green';
            var disabledAttr = isBooked ? 'disabled' : '';
            var checkboxDisabled = isBooked ? 'disabled' : '';

            // Generate Match Type Dropdown
            var matchTypeSelectRow = '<select name="pegs[' + peg.peg_id + '][match_type_slug]" class="match-type-selector" ' + disabledAttr + ' ' + (isChecked ? 'required' : '') + '>';
            matchTypeSelectRow += '<option value="">Select Type</option>';
            <?php foreach ( $match_types as $type ) : ?>
                matchTypeSelectRow += '<option value="<?php echo esc_attr( $type['type_slug'] ); ?>"><?php echo esc_html( $type['type_name'] ); ?></option>';
            <?php endforeach; ?>
            matchTypeSelectRow += '</select>';

            // Generate Club Dropdown
            var clubSelectRow = '<select name="pegs[' + peg.peg_id + '][club_id]" class="club-selector" ' + disabledAttr + ' ' + (isChecked ? 'required' : '') + '>';
            clubSelectRow += '<option value="">Select Club</option>';
            <?php foreach ( $clubs as $club ) : ?>
                clubSelectRow += '<option value="<?php echo absint( $club['id'] ); ?>"><?php echo esc_html( $club['club_name'] ); ?></option>';
            <?php endforeach; ?>
            clubSelectRow += '</select>';

            // Hidden status input
            var statusInput = '<input type="hidden" name="pegs[' + peg.peg_id + '][status]" value="' + (isChecked ? 'booked' : 'available') + '" class="status-input">';

            // Book toggle checkbox
            var bookToggle = '<label>' +
                                '<input type="checkbox" class="peg-book-toggle" data-peg-id="' + peg.peg_id + '" data-row-number="' + rowNumber + '" ' + (isChecked ? 'checked' : '') + ' ' + checkboxDisabled + '>' +
                                ' Book' +
                            '</label>';

            tableHtml += `
                <tr ${rowClass} data-peg-id="${peg.peg_id}" data-row-number="${rowNumber}">
                    <td style="width:15%"><span class="peg-name-display" data-row-number="${rowNumber}">
                    ${rowNumber} - ${peg.peg_name}</span></td>
                    <td style="width:10%">${statusInput} ${bookToggle}</td>
                    <td style="width:15%"><span class="${statusColor}">${statusText}</span></td>
                    <td style="width:30%">${matchTypeSelectRow}</td>
                    <td style="width:30%">${clubSelectRow}</td>
                </tr>
            `;
        });

        tableHtml += '</tbody></div></div></table>';
        $('#pegs-container').html(tableHtml);

        // --- Event Handlers for new features ---

        // 1. Select/Deselect All Checkbox
        $('#select-all-pegs').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.peg-book-toggle:not(:disabled)').prop('checked', isChecked).trigger('change');
        });

        // 2. Clear all selections
        $('#clear-selection').on('click', function() {
            $('.peg-book-toggle:not(:disabled)').prop('checked', false).trigger('change');
            $('#select-all-pegs').prop('checked', false);
            $('#peg-numbers-input').val('');
        });

        // 3. Apply selection by row numbers (supports comma-separated and ranges like 1-5)
        $('#apply-peg-numbers').on('click', function() {
            var inputVal = $('#peg-numbers-input').val().trim();
            if (!inputVal) {
                alert('Please enter row numbers (e.g., 1,2,4-6,8)');
                return;
            }
            
            var selectedRowNumbers = parseRowNumbers(inputVal);
            if (selectedRowNumbers.length === 0) {
                alert('No valid row numbers found. Please use format like: 1,2,4-6,8');
                return;
            }
            
            console.log('Selected row numbers to check:', selectedRowNumbers);
            
            // For each checkbox, check if its row number is in the selected list
            var foundCount = 0;
            $('.peg-book-toggle:not(:disabled)').each(function() {
                var $checkbox = $(this);
                var rowNumber = parseInt($checkbox.data('row-number'), 10);
                
                console.log('Checking row:', rowNumber, 'against selected:', selectedRowNumbers);
                
                var shouldSelect = selectedRowNumbers.includes(rowNumber);
                
                if (shouldSelect) {
                    foundCount++;
                    if (!$checkbox.is(':checked')) {
                        $checkbox.prop('checked', true).trigger('change');
                    }
                }
            });
            
            if (foundCount === 0) {
                alert('No matching pegs found. Make sure the row numbers you entered exist (1 to ' + $('.peg-book-toggle:not(:disabled)').length + ')');
            } else {
                console.log('Found and selected', foundCount, 'pegs');
                // Show success message
                var $tempMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 5px;"><p>Selected ' + foundCount + ' peg(s) by row number(s)!</p></div>');
                $('#pegs-container').before($tempMsg);
                setTimeout(function() { $tempMsg.fadeOut(function() { $(this).remove(); }); }, 2000);
            }
            
            // Update select all checkbox state
            var totalCheckboxes = $('.peg-book-toggle:not(:disabled)').length;
            var checkedCheckboxes = $('.peg-book-toggle:not(:disabled):checked').length;
            $('#select-all-pegs').prop('checked', totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes);
        });
        
        // Helper function to parse row numbers like "1,2,4-6,8"
        function parseRowNumbers(input) {
            // Remove all spaces
            input = input.replace(/\s/g, '');
            
            var parts = input.split(',');
            var numbers = [];
            
            for (var i = 0; i < parts.length; i++) {
                var part = parts[i];
                if (part === '') continue;
                
                if (part.includes('-')) {
                    var range = part.split('-');
                    var start = parseInt(range[0], 10);
                    var end = parseInt(range[1], 10);
                    
                    if (!isNaN(start) && !isNaN(end) && start <= end) {
                        for (var j = start; j <= end; j++) {
                            numbers.push(j);
                        }
                    }
                } else {
                    var num = parseInt(part, 10);
                    if (!isNaN(num)) {
                        numbers.push(num);
                    }
                }
            }
            
            return numbers;
        }

        // 4. Apply Match Type to Selected Pegs
        $('#applyMatchType').on('click', function(e) {
            e.preventDefault();
            var headerSelect = $('.match-type-selector.header-select');
            var valueToApply = headerSelect.val();
            if (!valueToApply) {
                alert('Please select a match type first');
                return;
            }
            
            var appliedCount = 0;
            $('.peg-book-toggle:checked').each(function() {
                var $row = $(this).closest('tr');
                $row.find('.match-type-selector').val(valueToApply);
                appliedCount++;
            });
            
            if (appliedCount > 0) {
                var $tempMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 5px;"><p>Applied match type to ' + appliedCount + ' selected peg(s)!</p></div>');
                $('#pegs-container').before($tempMsg);
                setTimeout(function() { $tempMsg.fadeOut(function() { $(this).remove(); }); }, 2000);
            }
        });
        
        // 5. Apply Club to Selected Pegs
        $('#applyClub').on('click', function(e) {
            e.preventDefault();
            var headerSelect = $('.club-selector.header-select');
            var valueToApply = headerSelect.val();
            if (!valueToApply) {
                alert('Please select a club first');
                return;
            }
            
            var appliedCount = 0;
            $('.peg-book-toggle:checked').each(function() {
                var $row = $(this).closest('tr');
                $row.find('.club-selector').val(valueToApply);
                appliedCount++;
            });
            
            if (appliedCount > 0) {
                var $tempMsg = $('<div class="notice notice-success" style="margin: 10px 0; padding: 5px;"><p>Applied club to ' + appliedCount + ' selected peg(s)!</p></div>');
                $('#pegs-container').before($tempMsg);
                setTimeout(function() { $tempMsg.fadeOut(function() { $(this).remove(); }); }, 2000);
            }
        });

        // Toggle event handler
        $('#pegs-container').on('change', '.peg-book-toggle', function() {
            var $row = $(this).closest('tr');
            var isChecked = $(this).is(':checked');
            var isDisabled = $(this).is(':disabled');
            
            if (isDisabled) return;
            
            // Toggle the required attribute and enable/disable selects
            $row.find('.match-type-selector, .club-selector')
                .prop('disabled', !isChecked)
                .prop('required', isChecked);
            
            // Update the hidden status field
            $row.find('.status-input').val(isChecked ? 'booked' : 'available');
            
            // If unchecking, clear the selects
            if (!isChecked) {
                $row.find('.match-type-selector').val('');
                $row.find('.club-selector').val('');
            }
            
            // Update "Select All" checkbox state
            var totalCheckboxes = $('.peg-book-toggle:not(:disabled)').length;
            var checkedCheckboxes = $('.peg-book-toggle:not(:disabled):checked').length;
            $('#select-all-pegs').prop('checked', totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes);
        });
        
        // Trigger initial "Select All" state
        $('#select-all-pegs').trigger('change');
        
        // Log total available pegs for debugging
        console.log('Total available pegs:', $('.peg-book-toggle:not(:disabled)').length);
        $('.peg-book-toggle:not(:disabled)').each(function() {
            console.log('Row', $(this).data('row-number'), '- Peg ID:', $(this).data('peg-id'));
        });
    }

    // CSS to make the status tags look good
    if ($('style:contains(".tag-green")').length === 0) {
        $('head').append(`
            <style>
                .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
                .tag-red { background-color: #fce7e7; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
                .bulk-actions-row th { padding: 8px; vertical-align: middle; background-color: #f9f9f9; }
                #peg-numbers-input { margin-right: 5px; }
                .button-small { margin: 0 2px; }
                .peg-name-display { font-weight: normal; }
                button { background:red; }
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