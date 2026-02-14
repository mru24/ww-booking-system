<?php
/**
 * Admin View: Subscriptions List
 *
 * @var array $subscriptions
 */
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Subscriptions</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-subscription' ) ); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Plan Name</th>
                <th>Plan ID</th>
                <th>Status</th>
                <th>Subscription tenure</th>
                <th>Price (£)</th>
                <th>Gateway ID</th>
                <th>Created</th>
                <th>Last updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $subscriptions ) ) : ?>
                <?php foreach ( $subscriptions as $sub ) : ?>
                    <tr>
                        <td><?php echo esc_html( $sub['plan_name'] ); ?></td>
                        <td><?php echo esc_html( $sub['plan_id'] ); ?></td>
                        <td>
                        	<span class="<?php echo (esc_html( ucfirst( $sub['status']))  == 'Active') ? 'tag-green' : 'tag-yellow'; ?>">
                        		<?php echo esc_html( ucfirst( $sub['status'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $sub['tenure'] ); ?></td>
                        <td><?php echo esc_html( number_format($sub['price'],2,'.','') ); ?></td>
                        <td><?php echo esc_html( $sub['payment_gateway_id'] ); ?></td>
                        <td><?php echo ww_format_datetime(esc_html( $sub['created_at'] )); ?></td>
                        <td><?php echo ww_format_datetime(esc_html( $sub['updated_at'] )); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-subscription&id=' . absint( $sub['id'] ) ) ); ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=mybp_delete_subscription&id=' . absint( $sub['id'] ) ), 'mybp_delete_subscription' ); ?>" onclick="return confirm('Are you sure you want to delete this subscription?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="12">No subscriptions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <style>
        .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
        .tag-yellow { background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
        .wp-list-table ul { margin: 0; padding: 0 0 0 15px; }
        .wp-list-table li { margin-bottom: 3px; }
    </style>
</div>
