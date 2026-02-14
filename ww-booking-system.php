<?php
/**
 * Plugin Name: WW Booking System
 * Description: A comprehensive booking and subscription management system.
 * Version: 1.1.0
 * Author: Val Wroblewski
 * License: GPL2
 */

// Exit if accessed directly (security measure)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
if ( ! defined( 'WWBP_PLUGIN_DIR' ) ) {
    // Defines the full path to the plugin directory (e.g., /wp-content/plugins/ww-booking-system/)
    define( 'WWBP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WWBP_DB_VERSION' ) ) {
    // Version used for checking and running database migrations/updates
    define( 'WWBP_DB_VERSION', '1.13' );
}

/**
 * Core Plugin Loader
 */
if ( ! class_exists( 'WW_Booking_Plugin' ) ) {
    // Include the main plugin class which holds all the logic and handles loading the admin/public components.
    require_once WWBP_PLUGIN_DIR . 'includes/class-ww-booking-plugin.php';

    /**
     * Instantiates the main plugin class and assigns it to a global variable.
     */
    function WW_Booking_Plugin_run() {
        // We use a global variable to make the plugin instance accessible throughout WordPress.
        $GLOBALS['ww-booking-system'] = WW_Booking_Plugin::get_instance();
    }

    // Start the plugin
    WW_Booking_Plugin_run();
}

