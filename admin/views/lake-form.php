<?php
/**
 * Admin View: Add/Edit Lake with Pegs
 *
 * @var array  $lake_data
 * @var array  $pegs_data
 * @var string $title
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$get_lake_val = function( $key ) use ( $lake_data ) {
    return isset( $lake_data[ $key ] ) ? esc_attr( $lake_data[ $key ] ) : '';
};
?>

<div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

        <?php wp_nonce_field( 'mybp_lake_nonce' ); ?>
        <input type="hidden" name="action" value="mybp_add_lake">
        <input type="hidden" name="lake_id" id="lake_id" value="<?php echo isset( $lake_data['id'] ) ? absint( $lake_data['id'] ) : 0; ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="lake_name">Lake Name *</label></th>
                <td><input type="text" name="lake_name" id="lake_name" class="regular-text" required value="<?php echo $get_lake_val( 'lake_name' ); ?>"></td>
            </tr>
            <tr>
                <?php $lake_status = $get_lake_val( 'lake_status' ) ? $get_lake_val( 'lake_status' ) : 'enabled'; ?>
                <th scope="row"><label for="lake_status">Lake Status</label></th>
                <td>
                    <select name="lake_status" id="lake_status">
                        <option value="enabled" <?php selected( $lake_status, 'enabled' ); ?>>Enabled</option>
                        <option value="disabled" <?php selected( $lake_status, 'disabled' ); ?>>Disabled</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Lake Picture</th>
                <td>
                    <?php
                    $image_id = absint( $get_lake_val( 'lake_image_id' ) );
                    $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
                    $visibility = $get_lake_val( 'lake_image_visibility' ) ? $get_lake_val( 'lake_image_visibility' ) : 'visible';
                    ?>

                    <input type="hidden" name="lake_image_id" id="lake_image_id" value="<?php echo $image_id; ?>">

                    <div id="image-preview-wrapper" style="margin-bottom: 10px; <?php echo $image_id ? '' : 'display: none;'; ?>">
                        <img id="image-preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width: 300px; height: auto;">
                    </div>

                    <button type="button" class="button button-secondary" id="upload-image-button"><?php echo $image_id ? 'Change Image' : 'Upload Image'; ?></button>
                    <button type="button" class="button button-secondary" id="remove-image-button" style="<?php echo $image_id ? '' : 'display: none;'; ?>">Remove Image</button>

                    <p class="description" style="margin-top: 10px;">
                        <label>
                            <input type="radio" name="lake_image_visibility" value="visible" <?php checked( $visibility, 'visible' ); ?>> Visible
                        </label>
                        <label style="margin-left: 15px;">
                            <input type="radio" name="lake_image_visibility" value="invisible" <?php checked( $visibility, 'invisible' ); ?>> Invisible (Image saved but hidden)
                        </label>
                    </p>
                </td>
            </tr>
			<tr>
			    <th scope="row">Lake Description</th>
			    <td>
			        <?php
			        // Use wp_kses_post to preserve HTML but allow safe tags
			        $description = isset( $lake_data['description'] ) ? wp_kses_post( $lake_data['description'] ) : 'no description';
			        $editor_id = 'description';
			        $editor_settings = array(
			            'textarea_name' => 'description',
			            'textarea_rows' => 20,
			            'media_buttons' => true,
			            'teeny' => false,
			            'quicktags' => true,
			            'default_editor' => 'tinymce',
			            'tinymce' => array(
			                'toolbar1' => 'bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink',
			                'toolbar2' => 'formatselect,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo'
			            )
			        );
			        wp_editor($description, $editor_id, $editor_settings);
			        ?>
			        <p>A brief description of this lake.</p>
			    </td>
			</tr>
        </table>

        <h2>Pegs</h2>
        <table class="wp-list-table widefat fixed striped" id="pegs-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Peg Name *</th>
                    <th style="width: 30%;">Status</th>
                    <th style="width: 20%;">Action</th>
                </tr>
            </thead>
            <tbody id="pegs-list">
                <?php
                if ( ! empty( $pegs_data ) ) {
                    foreach ( $pegs_data as $i => $peg ) {
                        ?>
                        <tr class="peg-row" data-index="<?php echo $i; ?>">
                            <td>
                                <input type="text" name="pegs[<?php echo $i; ?>][peg_name]" class="regular-text" required value="<?php echo esc_attr( $peg['peg_name'] ); ?>">
                                <input type="hidden" name="pegs[<?php echo $i; ?>][id]" value="<?php echo absint( $peg['id'] ); ?>">
                            </td>
                            <td>
                                <select name="pegs[<?php echo $i; ?>][peg_status]">
                                    <option value="open" <?php selected( $peg['peg_status'], 'open' ); ?>>Open</option>
                                    <option value="closed" <?php selected( $peg['peg_status'], 'closed' ); ?>>Closed</option>
                                </select>
                            </td>
                            <td><button type="button" class="button button-secondary remove-peg">Remove</button></td>
                        </tr>
                        <?php
                    }
                } else {
                    // Start with one empty row if adding new lake
                    if ( !isset($lake_data['id']) || absint($lake_data['id']) == 0 ) {
                        ?>
                        <tr class="peg-row" data-index="0">
                            <td>
                                <input type="text" name="pegs[0][peg_name]" class="regular-text" value="">
                                <input type="hidden" name="pegs[0][id]" value="0">
                            </td>
                            <td>
                                <select name="pegs[0][peg_status]">
                                    <option value="open">Open</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </td>
                            <td><button type="button" class="button button-secondary remove-peg">Remove</button></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">
                        <button type="button" id="add-peg" class="button button-primary">Add New Peg</button>
                    </td>
                </tr>
            </tfoot>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary button-large" value="<?php echo isset( $lake_data['id'] ) ? 'Update Lake' : 'Add Lake'; ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-booking-lakes' ) ); ?>" class="button button-secondary button-large">Cancel</a>
        </p>

    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var nextIndex = <?php echo isset($pegs_data) && !empty($pegs_data) ? count($pegs_data) : 1; ?>;
    var mediaUploader;

    // Function to add a new peg row
    function addPegRow(name = '', status = 'open', id = 0) {
        var rowHtml = `
            <tr class="peg-row" data-index="${nextIndex}">
                <td>
                    <input type="text" name="pegs[${nextIndex}][peg_name]" class="regular-text" required value="${name}">
                    <input type="hidden" name="pegs[${nextIndex}][id]" value="${id}">
                </td>
                <td>
                    <select name="pegs[${nextIndex}][peg_status]">
                        <option value="open" ${status === 'open' ? 'selected' : ''}>Open</option>
                        <option value="closed" ${status === 'closed' ? 'selected' : ''}>Closed</option>
                    </select>
                </td>
                <td><button type="button" class="button button-secondary remove-peg">Remove</button></td>
            </tr>
        `;
        $('#pegs-list').append(rowHtml);
        nextIndex++;
    }

    // Add Peg button handler
    $('#add-peg').on('click', function() {
        // If the table is empty and we're adding the first row, make sure to use index 0.
        if ($('#pegs-list').find('.peg-row').length === 0) {
            nextIndex = 0;
        }
        addPegRow();
    });

    // Remove Peg button handler (uses event delegation for dynamically added rows)
    $('#pegs-list').on('click', '.remove-peg', function() {
        // Only remove if there is more than one row OR if we are on an existing lake
        var totalRows = $('#pegs-list').find('.peg-row').length;
        var lakeId = $('#lake_id').val();

        if (totalRows > 1 || lakeId > 0) {
             $(this).closest('.peg-row').remove();
        } else {
            // For a brand new lake, prevent removing the last row but just clear the fields.
            $(this).closest('.peg-row').find('input[type="text"]').val('');
            $(this).closest('.peg-row').find('select').val('open');
        }
    });

    // If the lake is new (ID=0) and the initial row was removed, add it back on save attempt
    $('form').on('submit', function(e) {
        if ($('#pegs-list').find('.peg-row').length === 0) {
            alert("Please add at least one Peg to the lake.");
            e.preventDefault();
            return false;
        }
        return true;
    });

    // Initial check: if no pegs exist and it's an existing lake, add one empty row
    if ($('#lake_id').val() > 0 && $('#pegs-list').find('.peg-row').length === 0) {
        addPegRow();
    }

    // MEDIA UPLOADER
    $('#upload-image-button').on('click', function(e) {
        e.preventDefault();

        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Lake Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });

        // When a file is selected, grab the ID and URL
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            // Set the input field's value to the attachment ID
            $('#lake_image_id').val(attachment.id);

            // Update the preview image source and show wrapper
            $('#image-preview').attr('src', attachment.url);
            $('#image-preview-wrapper').show();

            // Update button text and show remove button
            $('#upload-image-button').text('Change Image');
            $('#remove-image-button').show();
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    // Remove image handler
    $('#remove-image-button').on('click', function(e) {
        e.preventDefault();

        // Clear the input field
        $('#lake_image_id').val('0');

        // Hide the preview and wrapper
        $('#image-preview').attr('src', '');
        $('#image-preview-wrapper').hide();

        // Reset button text and hide remove button
        $('#upload-image-button').text('Upload Image');
        $('#remove-image-button').hide();
    });
});
</script>