<?php
/**
 * Admin View: Lakes List
 *
 * @var array $lakes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Lakes</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-lake' ) ); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <?php
    if ( isset( $_GET['message'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field($_GET['message']) ) . '</p></div>';
    }
    if ( isset( $_GET['error'] ) ) {
        $error_msg = isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : 'An error occurred.';
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
    }
    ?>

    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Lake Name</th>
                <th>Status</th>
                <th>Created</th>
                <th>Last updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $lakes ) ) : ?>
                <?php foreach ( $lakes as $lake ) : ?>
                    <tr>
                        <td><?php echo esc_html( $lake['lake_name'] ); ?></td>
                        <td>
                        	<span class="<?php echo (esc_html( ucfirst( $lake['lake_status']))  == 'Enabled') ? 'tag-green' : 'tag-yellow'; ?>">
                        		<?php echo esc_html( ucfirst( $lake['lake_status'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo ww_format_datetime(esc_html( $lake['created_at'] )); ?></td>
                        <td><?php echo ww_format_datetime(esc_html( $lake['updated_at'] )); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-lake&id=' . absint( $lake['id'] ) ) ); ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=mybp_delete_lake&id=' . absint( $lake['id'] ) ), 'mybp_delete_lake' ); ?>" onclick="return confirm('WARNING: Are you sure you want to delete this lake? This will also delete all associated pegs!');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="4">No lakes found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <style>
        /* Reusing club/subscription list styles */
        .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
        .tag-yellow { background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
    </style>
</div>