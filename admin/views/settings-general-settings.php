<?php
/**
 * Admin View: Settings - Match Types Module
 *
 * This file is included by settings-page.php.
 * * @var array $data (Contains the list of match types)
 * @var string $module_title
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1>Display Modules</h1>
    <form method="post">
        <?php wp_nonce_field( 'mybp_modules_nonce' ); ?>
        <p>
        	<input type="checkbox" name="customers" <?php checked( $this->active_modules['customers'] ); ?>>
        	<label>Customers</label>
        </p>
        <p>
        	<input type="checkbox" name="subscriptions" <?php checked( $this->active_modules['subscriptions'] ); ?>>
        	<label> Subscriptions</label>
        </p>
        <p>
        	<input type="checkbox" name="clubs" <?php checked( $this->active_modules['clubs'] ); ?>>
        	<label> Clubs</label>
        </p>
        <p>
        	<input type="checkbox" name="lakes" <?php checked( $this->active_modules['lakes'] ); ?>>
        	<label> Lakes</label>
        </p>
        <p>
        	<input type="checkbox" name="logs" <?php checked( $this->active_modules['logs'] ); ?>>
        	<label> Logs</label>
        </p>
        <h2>Calendar Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Booking Popup</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span>Booking Popup</span></legend>
                        <label for="enable_booking_popup">
                            <input type="checkbox" name="enable_booking_popup" id="enable_booking_popup"
                                   value="1" <?php checked( $this->active_modules['enable_booking_popup'] ?? true ); ?>>
                            Enable booking popup on calendar click
                        </label>
                        <p class="description">
                            When enabled, clicking on an available date in the calendar will open the booking popup.
                            When disabled, users can only view availability without making bookings.
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <input type="submit" name="mybp_save_modules" class="button-primary" value="Save Changes">
    </form>
</div>
<style>
/* Style to make the two-column layout look neat like other settings */
#col-container { display: flex; flex-wrap: wrap; margin-right: -20px; }
#col-left { width: 33%; margin-right: 20px; }
#col-right { width: 66%; }
#col-left .col-wrap, #col-right .col-wrap { padding: 0; }
@media screen and (max-width: 960px) {
    #col-left { width: 100%; margin-right: 0; margin-bottom: 20px; }
    #col-right { width: 100%; }
}

/* Improve form styling */
.form-table th {
    width: 200px;
}
.form-table fieldset label {
    display: block;
    margin-bottom: 8px;
}
.description {
    color: #666;
    font-style: italic;
    margin-top: 4px;
}
</style>