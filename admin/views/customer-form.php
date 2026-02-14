<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( 'mybp_customer_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_customer_submit">
        <input type="hidden" name="customer_id" value="<?php echo isset( $customer_data['id'] ) ? absint( $customer_data['id'] ) : 0; ?>">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-1">
                <div id="postbox-container-1" class="postbox-container">

                    <!-- Core Customer Data -->
                    <div class="postbox">
                        <h2 class="hndle"><span>Core Customer Data</span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="first_name">First Name *</label></th>
                                    <td><input type="text" name="first_name" id="first_name" required class="regular-text" value="<?php echo isset( $customer_data['first_name'] ) ? esc_attr( $customer_data['first_name'] ) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="last_name">Last Name *</label></th>
                                    <td><input type="text" name="last_name" id="last_name" required class="regular-text" value="<?php echo isset( $customer_data['last_name'] ) ? esc_attr( $customer_data['last_name'] ) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="membership_status">Membership Status</label></th>
                                    <td>
                                        <?php $status = isset( $customer_data['membership_status'] ) ? $customer_data['membership_status'] : 'Active'; ?>
                                        <select name="membership_status" id="membership_status">
                                            <option value="Active" <?php selected( $status, 'Active' ); ?>>Active</option>
                                            <option value="Inactive" <?php selected( $status, 'Inactive' ); ?>>Inactive</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="postbox">
                        <h2 class="hndle"><span>Contact Information</span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="email">Email *</label></th>
                                    <td><input type="email" name="email" id="email" class="regular-text" value="<?php echo isset( $customer_data['email'] ) ? esc_attr( $customer_data['email'] ) : ''; ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="primary_phone">Primary Telephone *</label></th>
                                    <td><input type="text" name="primary_phone" id="primary_phone" class="regular-text" value="<?php echo isset( $customer_data['primary_phone'] ) ? esc_attr( $customer_data['primary_phone'] ) : ''; ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="secondary_phone">Secondary Telephone</label></th>
                                    <td><input type="text" name="secondary_phone" id="secondary_phone" class="regular-text" value="<?php echo isset( $customer_data['secondary_phone'] ) ? esc_attr( $customer_data['secondary_phone'] ) : ''; ?>"></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="postbox">
                        <h2 class="hndle"><span>Address (UK Format)</span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th><label for="address_locality">Locality / Street</label></th>
                                    <td><input type="text" name="address_locality" id="address_locality" class="regular-text" value="<?php echo isset( $customer_data['address_locality'] ) ? esc_attr( $customer_data['address_locality'] ) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_town">Town / City</label></th>
                                    <td><input type="text" name="address_town" id="address_town" class="regular-text" value="<?php echo isset( $customer_data['address_town'] ) ? esc_attr( $customer_data['address_town'] ) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_postcode">Postcode</label></th>
                                    <td><input type="text" name="address_postcode" id="address_postcode" class="regular-text" value="<?php echo isset( $customer_data['address_postcode'] ) ? esc_attr( $customer_data['address_postcode'] ) : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_country">Country</label></th>
                                    <td><input type="text" name="address_country" id="address_country" class="regular-text" value="<?php echo isset( $customer_data['address_country'] ) ? esc_attr( $customer_data['address_country'] ) : 'United Kingdom'; ?>"></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Subscriptions -->
                    <div class="postbox">
                        <h2 class="hndle"><span>Subscriptions (Comma-separated)</span></h2>
                        <div class="inside">
                            <p class="description">Enter all active subscriptions separated by commas (e.g., Premium Plan, Newsletter, SMS Alerts).</p>
                            <textarea name="subscriptions" id="subscriptions" rows="5" class="large-text"><?php echo isset( $customer_data['subscriptions'] ) ? esc_textarea( $customer_data['subscriptions'] ) : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Submit -->
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php echo isset( $customer_data['id'] ) ? 'Update Customer' : 'Add Customer'; ?>">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-customers' ) ); ?>" class="button button-secondary button-large">Cancel</a>
                    </p>

                </div><!-- /#postbox-container-1 -->
            </div><!-- /#post-body -->
        </div><!-- /#poststuff -->
    </form>
</div>

<?php
// Add collapsible behavior
add_action( 'admin_footer', function() {
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    postboxes.add_postbox_toggles(pagenow);
});
</script>
<?php
});
?>
