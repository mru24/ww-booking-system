<?php
/**
 * Admin View: Shortcodes
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
    <div class="postbox">
        <div class="inside">
            <table class="form-table">
                <tr>
                    <th><label for="first_name">Booking calendar</label></th>
                    <td>
                    	<input type="text" class="regular-text" value='[ww_booking_calendar]' readonly />
                	</td>
                </tr>
                <tr>
                    <th><label for="first_name">Booking calendar with lake ID</label></th>
                    <td>
                    	<input type="text" class="regular-text" value='[ww_booking_calendar lake_id="12345"]' readonly />
                	</td>
                </tr>
                <tr>
                    <th><label for="first_name">Lake description</label></th>
                    <td>
                    	<input type="text" class="regular-text" value='[ww_lake_description lake_id="12345"]' readonly />
                	</td>
                </tr>
                <tr>
                    <th><label for="first_name">Lake image</label></th>
                    <td>
                    	<input type="text" class="regular-text" value='[ww_lake_image lake_id="12345"]' readonly />
                	</td>
                </tr>
            </table>
        </div>
    </div>

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