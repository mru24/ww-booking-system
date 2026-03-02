<?php
/**
 * Admin View: Clubs List
 *
 * @var array $clubs
 */

// Exit if accessed directly (security measure)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Clubs</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-club' ) ); ?>" class="page-title-action">Add New</a>
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
                <th>Club Name</th>
                <th>Contact Name</th>
                <th>Postcode</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $clubs ) ) : ?>
                <?php foreach ( $clubs as $club ) : ?>
                    <tr>
                        <td><?php echo esc_html( $club['club_name'] ); ?></td>
                        <td><?php echo esc_html( $club['contact_name'] ); ?></td>
                        <td><?php echo esc_html( $club['postcode'] ); ?></td>
                        <td><?php echo esc_html( $club['email'] ); ?></td>
                        <td><?php echo esc_html( $club['phone'] ); ?></td>
                        <td>
                        	<span class="<?php echo (esc_html( ucfirst( $club['club_status']))  == 'Enabled') ? 'tag-green' : 'tag-yellow'; ?>">
                        		<?php echo esc_html( ucfirst( $club['club_status'] ) ); ?>
                            </span>
                        </td>
                        <td><?php echo ww_format_datetime(esc_html( $club['created_at'] )); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-edit-club&id=' . absint( $club['id'] ) ) ); ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=mybp_delete_club&id=' . absint( $club['id'] ) ), 'mybp_delete_club' ); ?>" onclick="return confirm('Are you sure you want to delete this club?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="8">No clubs found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <style>
        /* Reusing subscription list styles for visual consistency */
        .tag-green { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
        .tag-yellow { background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; font-weight: bold; }
    </style>
</div>