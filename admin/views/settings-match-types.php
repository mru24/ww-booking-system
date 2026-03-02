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

$types = $data;

// Check if we are editing or adding
$editing_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$editing_data = array();
$form_title = 'Add New Match Type';

if ( $editing_id > 0 ) {
    $editing_data = array_filter( $types, function( $type ) use ( $editing_id ) {
        return $type['id'] === $editing_id;
    } );
    $editing_data = reset( $editing_data ); // Get the first (and only) match

    if ( $editing_data ) {
        $form_title = 'Edit Match Type: ' . esc_html( $editing_data['type_name'] );
    } else {
        $editing_id = 0; // Reset if ID is invalid
    }
}

// Helper to safely get value or an empty string
$get_type_val = function( $key, $default = '' ) use ( $editing_data ) {
    return isset( $editing_data[ $key ] ) ? esc_attr( $editing_data[ $key ] ) : esc_attr($default);
};
?>

<div class="wrap">
	<div class="match-types-content">

	    <h2><?php echo esc_html( $module_title ); ?></h2>

	    <div class="container">
	        <table class="wp-list-table widefat fixed striped">
	            <thead>
	                <tr>
	                    <th scope="col" style="width: 20%;">Name</th>
	                    <th scope="col" style="width: 20%;">Unique ID (Slug)</th>
	                    <th scope="col" style="width: 45%;">Description</th>
	                    <th scope="col" style="width: 15%;">Actions</th>
	                </tr>
	            </thead>
	            <tbody id="the-list">
	                <?php if ( ! empty( $types ) ) : ?>
	                    <?php foreach ( $types as $type ) : ?>
	                        <tr>
	                            <td data-colname="Name"><strong><?php echo esc_html( $type['type_name'] ); ?></strong></td>
	                            <td data-colname="Unique ID"><?php echo esc_html( $type['type_slug'] ); ?></td>
	                            <td data-colname="Description"><?php echo esc_html( $type['description'] ); ?></td>
	                            <td data-colname="Actions">
	                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-settings-match-types&edit=' . absint( $type['id'] ) ) ); ?>">Edit</a> |
	                                <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=mybp_delete_match_type&id=' . absint( $type['id'] ) ), 'mybp_delete_match_type' ); ?>" onclick="return confirm('Are you sure you want to delete the <?php echo esc_js( $type['type_name'] ); ?> match type?');">Delete</a>
	                            </td>
	                        </tr>
	                    <?php endforeach; ?>
	                <?php else : ?>
	                    <tr><td colspan="4">No match types found.</td></tr>
	                <?php endif; ?>
	            </tbody>
	        </table>

			<hr style="margin:20px 0;">
            <h3><?php echo esc_html( $form_title ); ?></h3>

            <div style="width: 40%; min-width:300px;">
            	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="form-wrap">
				    <?php wp_nonce_field( 'mybp_match_type_nonce' ); ?>
				    <input type="hidden" name="action" value="<?php echo $editing_id ? 'mybp_update_match_type' : 'mybp_add_match_type'; ?>">
				    <input type="hidden" name="type_id" value="<?php echo $editing_id; ?>">

				    <div class="form-field form-required">
				        <label for="type_name">Name *</label>
				        <input name="type_name" id="type_name" type="text" value="<?php echo $get_type_val( 'type_name' ); ?>" size="40" required>
				        <p>The display name for the match type (e.g., "Full Day Match").</p>
				    </div>

				    <div class="form-field">
				        <label for="description">Description</label>
				        <textarea name="description" id="description" rows="5" cols="40"><?php echo $get_type_val( 'description' ); ?></textarea>
				        <p>A brief description of this match type.</p>
				    </div>

				    <p class="submit">
				        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $editing_id ? 'Update Match Type' : 'Add New Match Type'; ?>">
				        <?php if ( $editing_id ) : ?>
				            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-settings-match-types' ) ); ?>" class="button button-secondary">Cancel Edit</a>
				        <?php endif; ?>
				    </p>
				</form>
            </div>
        </div>
    </div>
</div>
