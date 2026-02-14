<?php
/**
 * Admin View: Holiday Form
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get available lakes properly
global $ww_booking_system;
$holidays_instance = $ww_booking_system->get_holidays_instance();
$available_lakes = $holidays_instance->get_available_lakes();
$selected_lakes = isset( $holiday_data['lakes'] ) ? wp_list_pluck( $holiday_data['lakes'], 'lake_id' ) : array();
?>

<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="holiday-form">
        <?php wp_nonce_field( 'mybp_holiday_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_add_holiday">
        <input type="hidden" name="holiday_id" value="<?php echo esc_attr( $holiday_id ); ?>">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="holiday_name">Holiday Name *</label>
                </th>
                <td>
                    <input type="text" name="holiday_name" id="holiday_name"
                           value="<?php echo esc_attr( $holiday_data['holiday_name'] ?? '' ); ?>"
                           class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="start_date">Start Date *</label>
                </th>
                <td>
                    <input type="date" name="start_date" id="start_date"
                           value="<?php echo esc_attr( $holiday_data['start_date'] ?? '' ); ?>"
                           class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="end_date">End Date *</label>
                </th>
                <td>
                    <input type="date" name="end_date" id="end_date"
                           value="<?php echo esc_attr( $holiday_data['end_date'] ?? $holiday_data['start_date'] ?? '' ); ?>"
                           class="regular-text" required>
                    <p class="description">
                        For single-day holidays, set the same date for start and end.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="holiday_type">Holiday Type</label>
                </th>
                <td>
                    <select name="holiday_type" id="holiday_type" class="regular-text">
                        <option value="annual" <?php selected( $holiday_data['holiday_type'] ?? '', 'annual' ); ?>>Annual (repeats every year)</option>
                        <option value="one_time" <?php selected( $holiday_data['holiday_type'] ?? '', 'one_time' ); ?>>One Time (specific dates only)</option>
                    </select>
                    <p class="description">
                        Annual holidays repeat every year on the same dates. One-time holidays only apply to the specific date range.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="applies_to">Applies To</label>
                </th>
                <td>
                    <select name="applies_to" id="applies_to" class="regular-text">
                        <option value="all_lakes" <?php selected( $holiday_data['applies_to'] ?? 'all_lakes', 'all_lakes' ); ?>>All Lakes</option>
                        <option value="specific_lakes" <?php selected( $holiday_data['applies_to'] ?? '', 'specific_lakes' ); ?>>Specific Lakes Only</option>
                    </select>
                    <p class="description">
                        Choose whether this holiday applies to all lakes or only specific ones.
                    </p>
                </td>
            </tr>
            <tr id="lakes-selection" style="display: <?php echo ( isset( $holiday_data['applies_to'] ) && $holiday_data['applies_to'] === 'specific_lakes' ) ? 'table-row' : 'none'; ?>">
                <th scope="row">
                    <label>Select Lakes</label>
                </th>
                <td>
                    <div class="lakes-checkbox-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <?php if ( ! empty( $available_lakes ) ) : ?>
                            <?php foreach ( $available_lakes as $lake ) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="lakes[]" value="<?php echo esc_attr( $lake['id'] ); ?>"
                                        <?php checked( in_array( $lake['id'], $selected_lakes ) ); ?>>
                                    <?php echo esc_html( $lake['lake_name'] ); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p>No lakes available.</p>
                        <?php endif; ?>
                    </div>
                    <p class="description">
                        Select the lakes this holiday applies to. If none selected, holiday will apply to all lakes when "Specific Lakes Only" is chosen.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="description">Description</label>
                </th>
                <td>
                    <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $holiday_data['description'] ?? '' ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label>Duration</label>
                </th>
                <td>
                    <div id="holiday-duration" style="padding: 8px; background: #f9f9f9; border-radius: 4px;">
                        <?php
                        if ( isset( $holiday_data['start_date'] ) && isset( $holiday_data['end_date'] ) ) {
                            $start = strtotime( $holiday_data['start_date'] );
                            $end = strtotime( $holiday_data['end_date'] );
                            $days = ( ( $end - $start ) / DAY_IN_SECONDS ) + 1;
                            echo esc_html( $days . ' day(s)' );
                        } else {
                            echo '1 day (default)';
                        }
                        ?>
                    </div>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Holiday">
            <a href="<?php echo admin_url( 'admin.php?page=my-booking-holidays' ); ?>" class="button">Cancel</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function updateDuration() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();

        if (startDate && endDate) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            var timeDiff = end - start;
            var daysDiff = Math.floor(timeDiff / (1000 * 60 * 60 * 24)) + 1;

            $('#holiday-duration').text(daysDiff + ' day(s)');
        }
    }

    // Toggle lakes selection based on applies_to
    $('#applies_to').on('change', function() {
        if ($(this).val() === 'specific_lakes') {
            $('#lakes-selection').show();
        } else {
            $('#lakes-selection').hide();
        }
    });

    // Update duration when dates change
    $('#start_date, #end_date').on('change', updateDuration);

    // Validate end date is not before start date
    $('#holiday-form').on('submit', function(e) {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();

        if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
            alert('End date cannot be before start date.');
            e.preventDefault();
            return false;
        }

        // Validate specific lakes selection
        if ($('#applies_to').val() === 'specific_lakes') {
            var lakesSelected = $('input[name="lakes[]"]:checked').length;
            if (lakesSelected === 0) {
                alert('Please select at least one lake for this holiday.');
                e.preventDefault();
                return false;
            }
        }
    });

    // Initialize duration display
    updateDuration();
});
</script>

<style>
.form-table th {
    width: 200px;
}
#holiday-duration {
    font-weight: bold;
    color: #2271b1;
}
.lakes-checkbox-container {
    background: #f9f9f9;
}
</style>