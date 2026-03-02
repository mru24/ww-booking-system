<?php
/**
 * Admin View: Generic Settings Page Template
 * * Includes the specific module view file.
 *
 * @var string $current_module
 * @var string $module_title
 * @var array $data (Data specific to the module, e.g., match types)
 * @var string $view_file (The specific view file to include)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the available tabs (future modules go here)
$tabs = array(
    'my-booking-settings-match-types' => 'Match Types',
    'my-booking-settings-general' => 'General',
    'my-booking-shortcodes' => 'Shortcodes',
);

// Determine the current tab
$current_tab = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : array_key_first( $tabs );
$current_tab = in_array( $current_tab, array_keys( $tabs ) ) ? $current_tab : array_key_first( $tabs );

// Handle success/error messages
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$error_msg = isset( $_GET['error'] ) ? (isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : 'An error occurred.') : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Booking System Settings</h1>

    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $name ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab <?php echo ( $current_tab === $slug ) ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $name ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php
    if ( $message ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }
    if ( $error_msg ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
    }
    ?>

    <div class="settings-module-content">
        <?php
        // Load the specific content view for the selected tab
        if ( isset( $view_file ) ) {
            // Data passed to the module view: $data, $module_title
            include plugin_dir_path( __FILE__ ) . $view_file;
        }
        ?>
    </div>
</div>