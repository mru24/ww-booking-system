<?php
// Handle messages from CRUD actions
if ( isset( $_GET['message'] ) ) {
    $message_class = 'notice-success';
    $message_text = '';
    switch ( intval( $_GET['message'] ) ) {
        case 1: $message_text = 'Customer updated successfully.'; break;
        case 2: $message_class = 'notice-error'; $message_text = 'Error updating customer.'; break;
        case 3: $message_text = 'Customer added successfully.'; break;
        case 4: $message_class = 'notice-error'; $message_text = 'Error adding customer.'; break;
        case 5: $message_text = 'Customer deleted successfully.'; break;
        case 6: $message_class = 'notice-error'; $message_text = 'Error deleting customer.'; break;
        default: $message_class = ''; $message_text = ''; break;
    }
    if ( ! empty( $message_text ) ) {
        printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $message_class ), esc_html( $message_text ) );
    }
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline">Customers</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-customer' ) ); ?>" class="page-title-action">
    	Add New Customer
    </a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Contact Info</th>
                <th>Subscriptions</th>
                <th>Address</th>
                <th>Created</th>
                <th>Lasy updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $customers ) ): ?>
                <?php foreach ( $customers as $customer ):
                    $edit_url = admin_url( 'admin.php?page=my-booking-edit-customer&id=' . absint( $customer['id'] ) );
                    $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=mybp_delete_customer&id=' . absint( $customer['id'] ) ), 'mybp_delete_customer' );
                ?>
                    <tr>
                        <td data-colname="Name">
                            <strong><?php echo esc_html( $customer['last_name'] ); ?> <?php echo esc_html( $customer['first_name'] ); ?></strong>
                        </td>
                        <td data-colname="Status">
                            <span class="
                                <?php echo ($customer['membership_status'] == 'Active') ? 'tag-green' : 'tag-yellow'; ?>
                            ">
                                <?php echo esc_html( $customer['membership_status'] ); ?>
                            </span>
                        </td>
                        <td data-colname="Contact Info">
                            Email: <?php echo esc_html( $customer['email'] ); ?><br>
                            Phone: <?php echo esc_html( $customer['primary_phone'] ); ?>
                        </td>
                        <td data-colname="Subscriptions">
                            <?php
                            $subs = explode(',', $customer['subscriptions']);
                            if (!empty($subs) && $subs[0] != '') {
                                echo '<ul>';
                                foreach($subs as $sub) {
                                    echo '<li>' . esc_html( trim($sub) ) . '</li>';
                                }
                                echo '</ul>';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td data-colname="Address">
                            <?php echo esc_html( $customer['address_locality'] . ', ' . $customer['address_town'] ); ?><br>
                            <?php echo esc_html( $customer['address_postcode'] ); ?>
                        </td>
                        <td><?php echo ww_format_datetime(esc_html( $customer['created_at'] )); ?></td>
                        <td><?php echo ww_format_datetime(esc_html( $customer['updated_at'] )); ?></td>
                        <td data-colname="Actions">
                        	<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-secondary">Edit</a>
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                                class="button button-secondary delete-link"
                                onclick="return confirm('Are you sure you want to delete this customer?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No customers found.</td>
                </tr>
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
