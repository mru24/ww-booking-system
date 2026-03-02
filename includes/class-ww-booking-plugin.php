<?php
/**
 * Core Plugin Class: Handles database, activation, and class loading.
 */

// Ensure the Admin class is available when needed.
require_once WWBP_PLUGIN_DIR . 'admin/class-ww-booking-admin.php';
// Frontend class
require_once WWBP_PLUGIN_DIR . 'frontend/class-ww-booking-frontend.php';

if ( ! class_exists( 'WW_Booking_Plugin' ) ) {
    class WW_Booking_Plugin {
        protected static $instance = null;
        protected $db;
        protected $table_prefix = 'booking_';
        protected $admin; // Holds the admin class instance
        protected $frontend; // Holds the frontend class instance

        /**
         * Singleton pattern ensures only one instance of the class exists.
         */
        public static function get_instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            global $wpdb;
            $this->db = $wpdb;

            // Activation hook (must remain here)
            register_activation_hook( WWBP_PLUGIN_DIR . 'ww_booking_system.php', array( $this, 'WW_Booking_Plugin_activate' ) );

            // Core WordPress Hooks
            add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );

            // Load Admin functionality
    		$this->admin = WW_Booking_Admin::get_instance( $this->db, $this->table_prefix );

    		// Load Frontend functionality
    		$this->frontend = new WW_Booking_Frontend( $this->db, $this->table_prefix );
        }

        /**
         * Get the full database table name with prefix.
         */
        public function get_table_name( $name ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }


        // --- ACTIVATION AND DB SETUP (Keep activation logic here) ---

        /**
         * Creates custom database tables upon plugin activation.
         */
        public function WW_Booking_Plugin_activate() {
        	global $wpdb;
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            $charset_collate = $this->db->get_charset_collate();
			$table_prefix    = $this->table_prefix;

            // All table creation SQL remains here for activation
            $tables = [
            	'bookings' => "CREATE TABLE " . $this->get_table_name( 'bookings' ) . " (
            		id BIGINT UNSIGNED NOT NULL UNIQUE,
				    lake_id BIGINT NOT NULL,
				    booking_pegs_id BIGINT NOT NULL,
				    date_start date NOT NULL,
				    date_end date NOT NULL,
				    booking_status enum('draft','booked','confirmed','cancelled') NOT NULL DEFAULT 'draft',
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    KEY lake_id (lake_id),
				    KEY date_range (date_start, date_end)
            	) $charset_collate;",
            	'booking_pegs' => "CREATE TABLE " . $this->get_table_name( 'booking_pegs' ) . " (
            		id BIGINT UNSIGNED NOT NULL UNIQUE,
				    booking_id BIGINT NOT NULL,
				    match_type_slug varchar(100) NOT NULL,
				    club_id BIGINT NOT NULL,
				    peg_id BIGINT NOT NULL,
				    status enum('available','booked') NOT NULL DEFAULT 'booked',
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    UNIQUE KEY booking_peg (booking_id, id),
				    KEY peg_id (peg_id)
            	) $charset_collate;",
            	'match_types' => "CREATE TABLE " . $this->get_table_name( 'match_types' ) . " (
            		id BIGINT UNSIGNED NOT NULL UNIQUE,
				    type_name varchar(100) NOT NULL,
				    type_slug varchar(100) NOT NULL,
				    description text NULL,
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    UNIQUE KEY type_slug (type_slug)
            	) $charset_collate;",
            	'lakes' => "CREATE TABLE " . $this->get_table_name( 'lakes' ) . " (
            		id BIGINT UNSIGNED NOT NULL UNIQUE,
				    lake_name varchar(100) NOT NULL,
				    lake_status enum('enabled','disabled') NOT NULL DEFAULT 'enabled',
				    lake_image_id bigint(20) NOT NULL DEFAULT '0',
    				lake_image_visibility enum('visible','invisible') NOT NULL DEFAULT 'visible',
				    description text NULL,
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id)
            	) $charset_collate;",
            	'pegs' => "CREATE TABLE " . $this->get_table_name( 'pegs' ) . " (
            		id BIGINT UNSIGNED NOT NULL UNIQUE,
				    lake_id BIGINT NOT NULL,
				    peg_name varchar(100) NOT NULL,
				    peg_status enum('open','closed') NOT NULL DEFAULT 'open',
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    KEY lake_id (lake_id),
				    UNIQUE KEY lake_peg (lake_id, peg_name)
            	) $charset_collate;",
            	'clubs' => "CREATE TABLE " . $this->get_table_name( 'clubs' ) . " (
	                id BIGINT UNSIGNED NOT NULL UNIQUE,
				    club_name varchar(255) NOT NULL,
				    club_address text NOT NULL,
				    postcode varchar(10) NOT NULL,
				    country varchar(100) NOT NULL,
				    contact_name varchar(255) NOT NULL,
				    phone varchar(20) NOT NULL,
				    email varchar(100) NOT NULL,
				    club_status enum('enabled','disabled') NOT NULL DEFAULT 'enabled',
				    created_at datetime NOT NULL,
    				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id)
	            ) $charset_collate;",
                'customers' => "CREATE TABLE " . $this->get_table_name( 'customers' ) . " (
                    id BIGINT UNSIGNED NOT NULL UNIQUE,
                    first_name varchar(100) NOT NULL,
                    last_name varchar(100) NOT NULL,
                    email varchar(150) NOT NULL,
                    primary_phone varchar(50) NULL,
                    secondary_phone varchar(50) NULL,
                    membership_status varchar(50) NULL,
                    subscriptions varchar(50) NULL,
                    address_locality varchar(50) NULL,
                    address_town text NULL,
                    address_postcode varchar(50) NULL,
                    address_country varchar(50) NULL,
				    created_at datetime NOT NULL,
    				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY email (email)
                ) $charset_collate;",
                'subscriptions' => "CREATE TABLE " . $this->get_table_name( 'subscriptions' ) . " (
                    id BIGINT UNSIGNED NOT NULL UNIQUE,
                    plan_name varchar(100) NOT NULL,
                    plan_id varchar(100) NOT NULL,
                    status varchar(20) DEFAULT 'active' NOT NULL,
                    tenure varchar(20) DEFAULT '1 year' NOT NULL,
                    price int(11) DEFAULT NULL,
                    payment_gateway_id varchar(255) NULL,
				    created_at datetime NOT NULL,
    				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY plan_id (plan_id)
                ) $charset_collate;",
				'holidays' => "CREATE TABLE " . $this->get_table_name( 'holidays' ) . " (
				    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				    holiday_name varchar(255) NOT NULL,
				    start_date date NOT NULL,
				    end_date date NOT NULL,
				    holiday_type enum('annual','one_time') NOT NULL DEFAULT 'annual',
				    applies_to enum('all_lakes','specific_lakes') NOT NULL DEFAULT 'all_lakes',
				    description text NULL,
				    created_at datetime NOT NULL,
				    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				    PRIMARY KEY (id),
				    UNIQUE KEY holiday_date (holiday_date)
				) $charset_collate;",
				'holiday_lakes' => "CREATE TABLE " . $this->get_table_name( 'holiday_lakes' ) . " (
				    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				    holiday_id BIGINT UNSIGNED NOT NULL,
				    lake_id BIGINT UNSIGNED NOT NULL,
				    created_at datetime NOT NULL,
				    PRIMARY KEY (id),
				    UNIQUE KEY holiday_lake (holiday_id, lake_id),
				    KEY holiday_id (holiday_id),
				    KEY lake_id (lake_id)
				) $charset_collate;",
            ];

            foreach ($tables as $sql) {
                dbDelta( $sql );
            }
			$this->add_booking_capabilities();

            add_option( 'wwbp_db_version', WWBP_DB_VERSION );
        }
		/**
		 * Add custom capabilities to roles.
		 */
		protected function add_booking_capabilities() {
		    // Get the Administrator role object
		    $role = get_role( 'administrator' );

		    if ( null === $role ) {
		        return; // Role not found
		    }

		    // Add the custom capabilities to the Administrator role
		    $role->add_cap( 'edit_bookings', true );
		    $role->add_cap( 'delete_bookings', true );
		    $role->add_cap( 'manage_bookings', true ); // A general capability might also be useful
		}

        // --- REST API SETUP (Still here for core functionality) ---

        /**
         * Initializes REST API routes for frontend communication.
         */
        public function init_rest_api() {
            register_rest_route( 'ww-booking-plugin/v1', '/availability/(?P<lake_id>\d+)', array(
                'methods'             => 'GET',
                'callback'            => array( $this->frontend, 'get_available_slots' ),
                'permission_callback' => '__return_true', // Public endpoint for now
            ) );
            register_rest_route( 'my-booking-plugin/v1', '/book', array(
                'methods'             => 'POST',
                // 'callback'            => array( $this, 'process_booking_request' ),
                'callback'            => array( $this->frontend, 'process_booking_request' ),
                'permission_callback' => '__return_true', // Requires customer data
            ) );
			register_rest_route( 'my-booking-plugin/v1', '/book/(?P<id>\d+)', array(
		        'methods'             => 'POST',
		        'callback'            => array( $this->frontend, 'update_booking' ),
		        'permission_callback' => '__return_true', // Public endpoint for now
		        // 'permission_callback' => function() {
		            // return current_user_can( 'manage_options' ); // Only Admins can manage options
		        // },
		    ) );
		    // --- NEW: DELETE BOOKING Route (DELETE) ---
		    register_rest_route( 'my-booking-plugin/v1', '/book/(?P<id>\d+)', array(
		        'methods'             => 'DELETE',
		        'callback'            => array( $this->frontend, 'delete_booking' ),
		        // *** QUICK FIX: Use a standard Admin capability ***
		        'permission_callback' => function() {
		            return current_user_can( 'manage_options' ); // Only Admins can manage options
		        },
		    ) );
        }
/**
 * Get holidays instance
 */
public function get_holidays_instance() {
    return $this->admin->get_holidays_instance();
}
    }
}

