<?php
/**
 * Admin View: Add/Edit Subscription
 *
 * @var array  $subscription_data
 * @var string $title
 */
?>

<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( 'mybp_subscription_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_add_subscription">
        <input type="hidden" name="subscription_id" value="<?php echo isset( $subscription_data['id'] ) ? absint( $subscription_data['id'] ) : 0; ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="plan_name">Plan Name *</label></th>
                <td><input type="text" name="plan_name" id="plan_name" class="regular-text" required value="<?php echo isset( $subscription_data['plan_name'] ) ? esc_attr( $subscription_data['plan_name'] ) : ''; ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="plan_id">Plan ID *</label></th>
                <td><?php echo isset( $subscription_data['plan_id'] ) ? esc_attr( $subscription_data['plan_id'] ) : ''; ?></td>
            </tr>
            <tr>
                <th scope="row"><label for="status">Status</label></th>
                <td>
                    <?php $status = isset( $subscription_data['status'] ) ? $subscription_data['status'] : 'active'; ?>
                    <select name="status" id="status">
                        <option value="active" <?php selected( $status, 'active' ); ?>>Active</option>
                        <option value="paused" <?php selected( $status, 'paused' ); ?>>Paused</option>
                        <option value="cancelled" <?php selected( $status, 'cancelled' ); ?>>Cancelled</option>
                        <option value="expired" <?php selected( $status, 'expired' ); ?>>Expired</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tenure">Subscription tenure</label></th>
                <td>
                    <?php $tenure = isset( $subscription_data['tenure'] ) ? $subscription_data['tenure'] : '1 year'; ?>
                    <select name="tenure" id="tenure">
                        <option value="1 year" <?php selected( $tenure, '1 year' ); ?>>1 year</option>
                        <option value="1 month" <?php selected( $tenure, '1 month' ); ?>>1 month</option>
                        <option value="1 week" <?php selected( $tenure, '1 week' ); ?>>1 week</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="price">Price (£)</label></th>
                <td><input type="number" name="price" id="price" class="small-text" step="0.01" value="<?php echo isset( $subscription_data['price'] ) ? esc_attr( $subscription_data['price'] ) : ''; ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="payment_gateway_id">Payment Gateway ID</label></th>
                <td><input type="text" name="payment_gateway_id" id="payment_gateway_id" class="regular-text" value="<?php echo isset( $subscription_data['payment_gateway_id'] ) ? esc_attr( $subscription_data['payment_gateway_id'] ) : ''; ?>"></td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-large" value="<?php echo isset( $subscription_data['id'] ) ? 'Update Subscription' : 'Add Subscription'; ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-subscriptions' ) ); ?>" class="button button-secondary button-large">Cancel</a>
        </p>
    </form>
</div>
