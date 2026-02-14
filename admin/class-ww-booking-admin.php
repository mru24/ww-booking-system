<?php
/**
 * Admin Class: Handles all admin-side menus, hooks, and form rendering.
 * Delegates customer CRUD to WW_Booking_Customers class.
 */

if ( ! class_exists( 'WW_Booking_Admin' ) ) {

    class WW_Booking_Admin {

        protected static $instance = null;
        protected $db;
        protected $table_prefix;
		protected $customers;
    	protected $subscriptions;
		protected $bookings;
    	protected $clubs;
		protected $lakes;
    	protected $pegs;
		protected $match_types;
		private $active_modules = array();
		protected $reports;
		protected $logger;
		protected $holidays;

        /**
         * Singleton pattern with dependency injection for $wpdb.
         */
        public static function get_instance( $db, $table_prefix ) {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self( $db, $table_prefix );
            }
            return self::$instance;
        }

        private function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;

			$this->load_active_modules();

			// Load plugin functions
			require_once plugin_dir_path( __FILE__ ) . 'includes/ww-plugin-functions.php';

			// Load Booking Functions
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-bookings.php';
            $this->bookings = new WW_Booking_Bookings( $db, $table_prefix );

            // Load Customer Functions
            if ( ! empty( $this->active_modules['customers'] ) ) {
            	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-customers.php';
            	$this->customers = new WW_Booking_Customers( $db, $table_prefix );
			}

			// Load Subscription Functions
			if ( ! empty( $this->active_modules['subscriptions'] ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-subscriptions.php';
				$this->subscriptions = new WW_Booking_Subscriptions( $db, $table_prefix );
			}

			// Load Clubs Functions
			if ( ! empty( $this->active_modules['clubs'] ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-clubs.php';
				$this->clubs = new WW_Booking_Clubs( $db, $table_prefix );
			}

			// Load Lakes Functions
			if ( ! empty( $this->active_modules['lakes'] ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-lakes.php';
				$this->lakes = new WW_Booking_Lakes( $db, $table_prefix );
			}

			// Load Pegs Functions
			if ( ! empty( $this->active_modules['lakes'] ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-pegs.php';
				$this->pegs = new WW_Booking_Pegs( $db, $table_prefix );
			}
			// Load Reports Functions
			if ( ! empty( $this->active_modules['reports'] ) ) {
    			require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-reports.php';
    			$this->reports = new WW_Booking_Reports( $db, $table_prefix );
			}

			// Load Match Type Functions
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-match-types.php';
            $this->match_types = new WW_Booking_Match_Types( $db, $table_prefix );

			// Holidays
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-holidays.php';
    		$this->holidays = new WW_Booking_Holidays( $db, $table_prefix );

			// Initialize logger
			if ( ! empty( $this->active_modules['logs'] ) ) {
			    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ww-booking-logger.php';
			    $this->logger = new WW_Booking_Logger( $db, $table_prefix );
			}

            // Core WordPress Hooks
            add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
            add_action( 'admin_init', array( $this, 'setup_admin_hooks' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }
		private function load_active_modules() {
		    $defaults = array(
		        'customers'     => false,
		        'subscriptions' => true,
		        'bookings'      => true,
		        'clubs'         => true,
		        'lakes'         => true,
		        'match_types'   => true,
		        'reports'         => true,
		        'logs'         => true,
		        'holidays'         => true,
        		'enable_booking_popup' => true,
		    );

		    $saved = get_option( 'mybp_active_modules', array() );

		    // Merge user preferences with defaults
		    $this->active_modules = wp_parse_args( $saved, $defaults );
		}

		public function setup_admin_hooks() {

			// Bookings admin hooks
            add_action( 'admin_post_mybp_add_booking', array( $this, 'process_booking_actions' ) );
			add_action('admin_post_mybp_update_booking', array($this, 'process_booking_update'));

            // Bookings AJAX hooks
            add_action( 'wp_ajax_mybp_get_pegs', array( $this, 'ajax_get_peg_availability' ) );
            // Note: Since this is an admin booking form, we only need the admin AJAX hook
            // If the front-end needed this, we'd add 'wp_ajax_nopriv_mybp_get_pegs'

        	// Club admin hooks
        	if ( ! empty( $this->active_modules['clubs'] ) ) {
	            add_action( 'admin_post_mybp_add_club', array( $this, 'process_club_actions' ) );
	            add_action( 'admin_post_mybp_delete_club', array( $this, 'process_delete_club' ) );
				add_action( 'wp_ajax_mybp_check_club_email', array( $this, 'ajax_check_club_email' ) );
			}

			// Lake admin hooks
			if ( ! empty( $this->active_modules['lakes'] ) ) {
	            add_action( 'admin_post_mybp_add_lake', array( $this, 'process_lake_actions' ) );
	            add_action( 'admin_post_mybp_delete_lake', array( $this, 'process_delete_lake' ) );
			}

            // Customer admin hooks
            if ( ! empty( $this->active_modules['customers'] ) ) {
	            add_action( 'admin_post_mybp_customer_submit', array( $this, 'process_customer_actions' ) );
	            add_action( 'admin_post_mybp_delete_customer', array( $this, 'process_delete_customer' ) );
			}

            // Subscription admin hooks
            if ( ! empty( $this->active_modules['subscriptions'] ) ) {
	            add_action( 'admin_post_mybp_add_subscription', array( $this, 'process_subscription_actions' ) );
	            add_action( 'admin_post_mybp_update_subscription', array( $this, 'process_subscription_actions' ) );
	            add_action( 'admin_post_mybp_delete_subscription', array( $this, 'process_delete_subscription' ) );
			}

			// Settings / Match Types admin hooks
			add_action( 'admin_post_mybp_add_match_type', array( $this, 'process_match_type_actions' ) );
            add_action( 'admin_post_mybp_delete_match_type', array( $this, 'process_delete_match_type' ) );

			// Logs
			if ( ! empty( $this->active_modules['logs'] ) ) {
				add_action( 'wp_ajax_mybp_get_log_details', array( $this, 'ajax_get_log_details' ) );
			}

			// Holidays
			add_action( 'admin_post_mybp_add_holiday', array( $this, 'process_holiday_actions' ) );
    		add_action( 'wp_ajax_mybp_delete_holiday', array( $this, 'ajax_delete_holiday' ) );

			if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'my-booking-settings' ) {
		        wp_redirect( admin_url( 'admin.php?page=my-booking-settings-match-types' ) );
		        exit;
    		}
        }

        // --- ADMIN MENU SETUP ---

        public function add_plugin_menu() {
            add_menu_page(
                'Bookings',
                'My Bookings',
                'manage_options',
                'my-booking-main',
                array( $this, 'render_dashboard_page' ),
                'dashicons-calendar-alt',
                6
            );
            add_submenu_page(
	            'my-booking-main',          // Parent slug (main menu)
	            'Bookings',                   // Page title
	            'Bookings',                   // Menu title
	            'manage_options',             // Capability
	            'my-booking-bookings',        // Menu slug
	            array( $this, 'render_bookings_list_page' ) // Callback function
	        );
			add_submenu_page(
		        null,
		        'New Booking',
		        'New Booking',
		        'manage_options',
		        'my-booking-new-booking',
		        array( $this, 'render_new_booking_page' )
		    );
			if ( ! empty( $this->active_modules['lakes'] ) ) {
				add_submenu_page(
	                'my-booking-main',
	                'Lakes',
	                'Lakes',
	                'manage_options',
	                'my-booking-lakes',
	                array( $this, 'render_lakes_page' )
	            );
			};
			if ( ! empty( $this->active_modules['clubs'] ) ) {
	            add_submenu_page(
	            	'my-booking-main',
	            	'Clubs',
	            	'Clubs',
	            	'manage_options',
	            	'my-booking-clubs',
	            	array( $this, 'render_clubs_page' )
				);
			};
			if ( ! empty( $this->active_modules['customers'] ) ) {
	            add_submenu_page(
	            	'my-booking-main',
	            	'Customers',
	            	'Customers',
	            	'manage_options',
	            	'my-booking-customers',
	            	array( $this, 'render_customers_page' )
				);
			};
			if ( ! empty( $this->active_modules['reports'] ) ) {
				add_submenu_page(
			        null,
			        'Reports',
			        'Reports',
			        'manage_options',
			        'my-booking-reports',
			        array( $this, 'render_reports_page' )
			    );
			};
			if ( ! empty( $this->active_modules['subscriptions'] ) ) {
	            add_submenu_page(
	            	'my-booking-main',
	            	'Subscriptions',
	            	'Subscriptions',
	            	'manage_options',
	            	'my-booking-subscriptions',
	            	array( $this, 'render_subscriptions_page' )
				);
			};
			add_submenu_page(
		        'my-booking-main', // Parent slug
		        'Booking Settings',
		        'Settings', // Label for the separator
		        'manage_options',
		        'my-booking-settings', // Use this as the settings page slug
		        array( $this, 'render_settings_page' )
		    );
			if ( ! empty( $this->active_modules['holidays'] ) ) {
			    add_submenu_page(
			        'my-booking-main',
			        'Holidays',
			        'Holidays',
			        'manage_options',
			        'my-booking-holidays',
			        array( $this, 'render_holidays_page' )
			    );

			    // Add edit holiday page
			    add_submenu_page(
			        null,
			        'Edit Holiday',
			        'Edit Holiday',
			        'manage_options',
			        'my-booking-edit-holiday',
			        array( $this, 'render_edit_holiday_page' )
			    );
			}

			// *********************************
			add_submenu_page(
		        'my-booking-settings', // Parent slug (same as the main menu)
		        'Match Types',
		        'Match Types',
		        'manage_options',
		        'my-booking-settings-match-types',
		        array( $this, 'render_settings_page' )
		    );
			add_submenu_page(
			    'my-booking-settings', // Parent slug (same as the main menu)
			    'General Settings',
			    'General Settings',
			    'manage_options',
			    'my-booking-settings-general',
			    array( $this, 'render_settings_page' )
			);
			add_submenu_page(
			    'my-booking-settings', // Parent slug (same as the main menu)
			    'Shortcodes',
			    'Shortcodes',
			    'manage_options',
			    'my-booking-shortcodes',
			    array( $this, 'render_settings_page' )
			);
			add_submenu_page(
				null,
				'Manage Resource Exceptions',
				'Exceptions',
				'manage_options',
				'my-booking-resources-exceptions',
				array( $this, 'render_resource_exceptions_page' )
			);
			add_submenu_page(
				null,
				'Manage Resource Availability',
				'Availability',
				'manage_options',
				'my-booking-resources-availability',
				array( $this, 'render_resource_availability_page' )
			);
            add_submenu_page(
                null,
                'Edit Lake',
                'Edit Lake',
                'manage_options',
                'my-booking-edit-lake',
                array( $this, 'render_edit_lake_page' )
            );
            add_submenu_page(
                null,
                'Edit Booking',
                'Edit Booking',
                'manage_options',
                'my-booking-edit-booking',
                array( $this, 'render_edit_booking_page' )
            );
			add_submenu_page(
                null,
                'Edit Club',
                'Edit Club',
                'manage_options',
                'my-booking-edit-club',
                array( $this, 'render_edit_club_page' )
            );
			add_submenu_page(
				null,
				'Add/Edit Customer',
				'Add/Edit Customer',
				'manage_options',
				'my-booking-edit-customer',
				array( $this, 'render_edit_customer_page' )
			);
            add_submenu_page(
	            null,
	            'Edit Subscription',
	            'Edit Subscription',
	            'manage_options',
	            'my-booking-edit-subscription',
	            array( $this, 'render_edit_subscription_page' )
			);
			if ( ! empty( $this->active_modules['logs'] ) ) {
				add_submenu_page(
			        'my-booking-main',
			        'Activity Logs',
			        'Activity Logs',
			        'manage_options',
			        'my-booking-logs',
			        array( $this, 'render_logs_page' )
			    );
			}
        }

		public function render_bookings_list_page() {
            // Fetch all bookings with their peg details
            $bookings = $this->bookings->get_all_bookings_with_details();

            // Load the view file
    		require_once plugin_dir_path( __FILE__ ) . 'views/bookings-list.php';
        }

		// --- New Booking Pages Renderer ---   <-- ADD FROM HERE
        public function render_new_booking_page() {
            // Fetch all necessary dropdown data upfront
            $lakes = $this->lakes->get_lakes();
            $match_types = $this->match_types->get_types();
            $clubs = $this->clubs->get_clubs(); // Assuming this is defined
            require_once plugin_dir_path( __FILE__ ) . 'views/booking-form.php';
        }

        public function render_edit_booking_page() {
            $booking_id = isset( $_GET['booking_id'] ) ? intval( $_GET['booking_id'] ) : 0;
			$lakes = $this->lakes->get_lakes();
            $match_types = $this->match_types->get_types();
            $clubs = $this->clubs->get_clubs(); // Assuming this is defined
    	    $booking_data = array();
    	    $title = '';

    	    if ( $booking_id > 0 ) {
    	        $booking_data = $this->bookings->get_booking_with_details( $booking_id );
                // 2. If booking exists, get its lake info
        		if ( $booking_data ) {
            		$title = 'Edit Booking: ' . esc_html( $booking_data['id'] );

		            if ( ! empty( $booking_data['lake_id'] ) ) {
		                $lake_data = $this->lakes->get_lake( $booking_data['lake_id'] );
		            }
		        }
    	    }
    	    require_once plugin_dir_path( __FILE__ ) . 'views/booking-edit-form.php';
        }

        // --- AJAX Handler for Peg Availability ---
        public function ajax_get_peg_availability() {
            // Ensure user has permission
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Permission denied' );
            }
            // Basic nonce check (optional but recommended for internal admin actions)
            // if ( ! check_ajax_referer( 'mybp_booking_nonce', 'security', false ) ) {
            //     wp_send_json_error( 'Security check failed.' );
            // }
            $lake_id    = isset( $_POST['lake_id'] ) ? absint( $_POST['lake_id'] ) : 0;
            $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
            $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';
            if ( $lake_id === 0 || empty( $start_date ) || empty( $end_date ) ) {
                wp_send_json_error( 'Missing date or lake ID.' );
            }
            $pegs_data = $this->bookings->get_pegs_with_availability( $lake_id, $start_date, $end_date );
            // Pass the data back as JSON
            wp_send_json_success( array( 'pegs' => $pegs_data ) );
        }

        // --- Admin-Post Handler for Booking Submission ---
		public function process_booking_actions() {
		    if (!current_user_can('manage_options')) {
		        wp_die('You do not have sufficient permissions.');
		    }
		    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mybp_booking_nonce')) {
		        wp_die('Security check failed.');
		    }
		    $result = $this->bookings->save_booking($_POST);
		    if ($result !== false) {
		        // Redirect to bookings list with success message
		        $message = 'Booking created successfully (ID: ' . absint($result) . ').';
		        wp_redirect(admin_url('admin.php?page=my-booking-bookings&message=' . urlencode($message)));
		        exit;
		    } else {
		        $error_message = 'Error creating booking. Please check logs for details.';
		        wp_redirect(admin_url('admin.php?page=my-booking-bookings&error=1&msg=' . urlencode($error_message)));
		        exit;
		    }
		}

		/**
		 * Process booking update actions
		 */
		public function process_booking_update() {
		    if (!current_user_can('manage_options')) {
		        wp_die('You do not have sufficient permissions.');
		    }
		    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mybp_booking_nonce')) {
		        wp_die('Security check failed.');
		    }
		    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

		    if ($booking_id === 0) {
		        wp_redirect(admin_url('admin.php?page=my-booking-bookings&error=1&msg=' . urlencode('Invalid booking ID.')));
		        exit;
		    }
		    $result = $this->bookings->update_booking($booking_id, $_POST);

		    if ($result !== false) {
		        $message = 'Booking updated successfully.';
		        // Redirect to bookings list instead of edit page
		        wp_redirect(admin_url('admin.php?page=my-booking-bookings&message=' . urlencode($message)));
		        exit;
		    } else {
		        $error_message = 'Error updating booking. Please check logs for details.';
		        wp_redirect(admin_url('admin.php?page=my-booking-bookings&error=1&msg=' . urlencode($error_message)));
		        exit;
		    }
		}

		// --- CLUBS PAGES ---
        public function render_clubs_page() {
            $clubs = $this->clubs->get_clubs();
    		require_once plugin_dir_path( __FILE__ ) . 'views/clubs-list.php';
        }

		public function render_edit_club_page() {
		    $club_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		    $club_data = array();
		    $title = 'Add New Club';

		    if ( $club_id > 0 ) {
		        $club_data = $this->clubs->get_club( $club_id );
		        if ( $club_data ) {
		            $title = esc_html( $club_data['club_name'] );
		        }
		    }
		    // Make clubs instance available to the view
		    $clubs_instance = $this->clubs;
		    require_once plugin_dir_path( __FILE__ ) . 'views/club-form.php';
		}

		public function process_club_actions() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_die( 'You do not have sufficient permissions to access this page.' );
		    }
		    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_club_nonce' ) ) {
		        wp_die( 'Security check failed.' );
		    }
		    $club_id = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : 0;

		    // Check required fields
		    if ( empty( $_POST['club_name'] ) ) {
		        wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&error=1&msg=' . urlencode('Club Name and Email are required.') ) );
		        exit;
		    }

		    // Validate email format
		    // if ( ! is_email( $_POST['email'] ) ) {
		        // wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&error=1&msg=' . urlencode('Please enter a valid email address.') ) );
		        // exit;
		    // }

		    $result = $this->clubs->save_club( $_POST, $club_id );

		    if ( is_wp_error( $result ) ) {
		        // Handle email duplicate error
		        if ( $result->get_error_code() === 'email_exists' ) {
		            wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&error=1&msg=' . urlencode( $result->get_error_message() ) ) );
		            exit;
		        }
		    }

		    if ( $result !== false && ! is_wp_error( $result ) ) {
		        $message = ( $club_id > 0 ) ? 'Club updated successfully.' : 'Club added successfully.';
		        wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&message=' . urlencode( $message ) ) );
		        exit;
		    } else {
		        $error_message = ( $club_id > 0 ) ? 'Error updating club.' : 'Error adding club.';
		        wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&error=2&msg=' . urlencode( $error_message ) ) );
		        exit;
		    }
		}

		/**
		 * AJAX handler for checking if club email exists
		 */
		public function ajax_check_club_email() {
		    // Verify nonce
		    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mybp_email_check' ) ) {
		        wp_send_json_error( 'Security check failed.' );
		    }

		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_send_json_error( 'Permission denied.' );
		    }

		    $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		    $club_id = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : 0;

		    if ( empty( $email ) ) {
		        wp_send_json_success( array( 'exists' => false ) );
		    }

		    $exists = $this->clubs->email_exists( $email, $club_id );

		    wp_send_json_success( array( 'exists' => $exists ) );
		}

        public function process_delete_club() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have sufficient permissions to access this page.' );
            }
            $club_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            if ( $club_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'mybp_delete_club' ) ) {
                $result = $this->clubs->delete_club( $club_id );
                if ( $result !== false ) {
                    $message = 'Club deleted successfully.';
                    wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&message=' . urlencode( $message ) ) );
                    exit;
                }
            }
            wp_redirect( admin_url( 'admin.php?page=my-booking-clubs&error=1' ) );
            exit;
        }

		// --- LAKES PAGES
        public function render_lakes_page() {
            $lakes = $this->lakes->get_lakes();
    		require_once plugin_dir_path( __FILE__ ) . 'views/lakes-list.php';
        }
        public function render_edit_lake_page() {
            $lake_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    	    $lake_data = array();
    	    $pegs_data = array();
    	    $title = 'Add New Lake';

    	    if ( $lake_id > 0 ) {
    	        $lake_data = $this->lakes->get_lake( $lake_id );
                $pegs_data = $this->pegs->get_pegs_by_lake_id( $lake_id );
    	        if ( $lake_data ) {
    	            $title = 'Edit Lake: ' . esc_html( $lake_data['lake_name'] );
    	        }
    	    }
    	    require_once plugin_dir_path( __FILE__ ) . 'views/lake-form.php';
        }

		public function process_lake_actions() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have sufficient permissions to access this page.' );
            }
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_lake_nonce' ) ) {
                wp_die( 'Security check failed.' );
            }
            $lake_id = isset( $_POST['lake_id'] ) ? intval( $_POST['lake_id'] ) : 0;
            $pegs_data = isset( $_POST['pegs'] ) ? wp_unslash( $_POST['pegs'] ) : array();
            if ( empty( $_POST['lake_name'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=my-booking-lakes&error=1&msg=' . urlencode('Lake Name is required.') ) );
                exit;
            }
            $inserted_id = $this->lakes->save_lake( $_POST, $lake_id );
            if ( $inserted_id ) {
                // 2. Save Pegs Data using the returned Lake ID
                $this->pegs->save_pegs( $inserted_id, $pegs_data );
                $message = ( $lake_id > 0 ) ? 'Lake updated successfully.' : 'Lake added successfully.';
                wp_redirect( admin_url( 'admin.php?page=my-booking-lakes&message=' . urlencode( $message ) ) );
                exit;
            } else {
                $error_message = ( $lake_id > 0 ) ? 'Error updating lake.' : 'Error adding lake.';
                wp_redirect( admin_url( 'admin.php?page=my-booking-lakes&error=2&msg=' . urlencode( $error_message ) ) );
                exit;
            }
        }

		public function process_delete_lake() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have sufficient permissions to access this page.' );
            }
            $lake_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            if ( $lake_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'mybp_delete_lake' ) ) {
                // 1. Delete all associated pegs first
                $this->pegs->delete_pegs_by_lake_id( $lake_id );
                // 2. Delete the lake record
                $result = $this->lakes->delete_lake( $lake_id );
                if ( $result !== false ) {
                    $message = 'Lake and all associated pegs deleted successfully.';
                    wp_redirect( admin_url( 'admin.php?page=my-booking-lakes&message=' . urlencode( $message ) ) );
                    exit;
                }
            }
            wp_redirect( admin_url( 'admin.php?page=my-booking-lakes&error=1' ) );
            exit;
        }

        // --- CUSTOMER PAGES ---
        public function render_customers_page() {
            $customers = $this->customers->get_all();
            require_once plugin_dir_path( __FILE__ ) . 'views/customers-list.php';
        }

        public function render_edit_customer_page() {
            $customer_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
            $customer_data = $customer_id ? $this->customers->get( $customer_id ) : array();
            $title = $customer_id && $customer_data
                ? 'Edit Customer: ' . esc_html( $customer_data['last_name'] )
                : 'Add New Customer';
            require_once plugin_dir_path( __FILE__ ) . 'views/customer-form.php';
        }

		// Render SETTINGS page
		public function render_settings_page() {
		    // Determine which settings view to load based on the URL slug
		    $current_module = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'my-booking-settings';

		    // Map the menu slug to the required data/view file
		    switch ( $current_module ) {
		    	case 'my-booking-settings-modules':
		            // Handle save
		            if ( isset( $_POST['save_modules'] ) && check_admin_referer( 'mybp_modules_nonce' ) ) {
		                $new_modules = isset( $_POST['modules'] ) ? array_map( 'boolval', $_POST['modules'] ) : array();
		                // Always keep bookings active
		                $new_modules['bookings'] = true;
		                update_option( 'mybp_active_modules', $new_modules );
		                $this->active_modules = $new_modules;
		                echo '<div class="updated"><p>Module settings saved.</p></div>';
		            }
		            $data = array( 'active_modules' => $this->active_modules );
		            $view_file = 'settings-module-view.php';
		            $module_title = 'Modules';
		            break;
		        case 'my-booking-settings-match-types':
		            $data = $this->match_types->get_types();
		            $view_file = 'settings-match-types.php';
		            $module_title = 'Match Types';
		            break;
				case 'my-booking-settings-general':
			        $data = array();
			        $view_file = 'settings-general-settings.php';
			        $module_title = 'General Settings';
			        break;
				case 'my-booking-shortcodes':
			        $data = array();
			        $view_file = 'settings-shortcodes.php';
			        $module_title = 'Shortcodes';
			        break;
		        default:
		            // The redirect is now handled by the admin_init hook.
		            // If we reach here, we should display a default/fallback view.
		            $data = array();
		            $view_file = 'settings-match-types.php'; // Fallback to the first actual module view
		            $module_title = 'Settings Module';
		            break;
		    }
			if ( isset( $_POST['mybp_save_modules'] ) && check_admin_referer( 'mybp_modules_nonce' ) ) {
		        $selected = array(
		            'customers'     => ! empty( $_POST['customers'] ),
		            'subscriptions' => ! empty( $_POST['subscriptions'] ),
		            'bookings'      => true, // Always on
		            'clubs'         => ! empty( $_POST['clubs'] ),
		            'lakes'         => ! empty( $_POST['lakes'] ),
		            'logs'         => ! empty( $_POST['logs'] ),
		            'match_types'   => ! empty( $_POST['match_types'] ),
        			'enable_booking_popup' => ! empty( $_POST['enable_booking_popup'] ),
		        );
		        update_option( 'mybp_active_modules', $selected );
		        echo '<div class="updated"><p>Modules updated!</p></div>';
		        $this->active_modules = $selected;
		    }

		    // The main settings page template handles the tabs and includes the specific module view
		    require_once plugin_dir_path( __FILE__ ) . 'views/settings-page.php';
		}

        // --- CUSTOMER CRUD PROCESSORS ---

        public function process_customer_actions() {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' );
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_customer_nonce' ) ) wp_die( 'Security check failed.' );

            $customer_id = intval( $_POST['customer_id'] ?? 0 );
            $data = array(
                'first_name'        => sanitize_text_field( $_POST['first_name'] ),
                'last_name'         => sanitize_text_field( $_POST['last_name'] ),
                'email'             => sanitize_email( $_POST['email'] ),
                'primary_phone'     => sanitize_text_field( $_POST['primary_phone'] ),
                'secondary_phone'   => sanitize_text_field( $_POST['secondary_phone'] ),
                'membership_status' => sanitize_text_field( $_POST['membership_status'] ),
                'subscriptions'     => sanitize_textarea_field( $_POST['subscriptions'] ),
                'address_locality'  => sanitize_text_field( $_POST['address_locality'] ),
                'address_town'      => sanitize_text_field( $_POST['address_town'] ),
                'address_postcode'  => sanitize_text_field( $_POST['address_postcode'] ),
                'address_country'   => sanitize_text_field( $_POST['address_country'] ),
            );

            if ( $customer_id > 0 ) {
                $result = $this->customers->update( $customer_id, $data );
                $message = $result ? 1 : 2;
            } else {
            	// INSERT NEW
            	$unique_id = generateUniqueId();
				$data['id'] = $unique_id;
				$data['created_at'] = current_time( 'mysql', true ); // Manual datetime stamp
                $result = $this->customers->insert( $data );
                $message = $result ? 3 : 4;
            }

            wp_redirect( admin_url( 'admin.php?page=my-booking-customers&message=' . $message ) );
            exit;
        }

        public function process_delete_customer() {
            if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' );
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'mybp_delete_customer' ) ) wp_die( 'Security check failed.' );
            $customer_id = intval( $_GET['id'] ?? 0 );
            $result = $customer_id ? $this->customers->delete( $customer_id ) : false;
            $message = $result ? 5 : 6;
            wp_redirect( admin_url( 'admin.php?page=my-booking-customers&message=' . $message ) );
            exit;
        }

		// --- Match Type Handlers ---
		public function process_match_type_actions() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have sufficient permissions.' );
            }
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_match_type_nonce' ) ) {
                wp_die( 'Security check failed.' );
            }
            $type_id = isset( $_POST['type_id'] ) ? intval( $_POST['type_id'] ) : 0;
            // Pass the entire POST data to the CRUD class
            $result = $this->match_types->save_type( $_POST, $type_id );
            if ( $result !== false ) {
                $message = ( $type_id > 0 ) ? 'Match Type updated successfully.' : 'Match Type added successfully.';
                wp_redirect( admin_url( 'admin.php?page=my-booking-settings-match-types&message=' . urlencode( $message ) ) );
                exit;
            } else {
                $error_message = 'Error saving Match Type. Name may be missing or the unique ID already exists.';
                wp_redirect( admin_url( 'admin.php?page=my-booking-settings-match-types&error=1&msg=' . urlencode( $error_message ) ) );
                exit;
            }
        }
        public function process_delete_match_type() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have sufficient permissions.' );
            }
            $type_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

            if ( $type_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'mybp_delete_match_type' ) ) {
                $result = $this->match_types->delete_type( $type_id );
                if ( $result !== false ) {
                    $message = 'Match Type deleted successfully.';
                    wp_redirect( admin_url( 'admin.php?page=my-booking-settings-match-types&message=' . urlencode( $message ) ) );
                    exit;
                }
            }
            wp_redirect( admin_url( 'admin.php?page=my-booking-settings-match-types&error=1' ) );
            exit;
        }

		// Render logs page
		public function render_logs_page() {
		    require_once plugin_dir_path( __FILE__ ) . 'views/logs-list.php';
		}

        // --- SUBSCRIPTION PROCESSORS ---

		public function process_subscription_actions() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_die( 'You do not have sufficient permissions to access this page.' );
		    }

		    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_subscription_nonce' ) ) {
		        wp_die( 'Security check failed.' );
		    }

		    $subscription_id = isset( $_POST['subscription_id'] ) ? intval( $_POST['subscription_id'] ) : 0;

		    $data = array(
		        'plan_name'          => $_POST['plan_name'],
		        'plan_id'          => sanitize_title($_POST['plan_name']),
		        'status'             => $_POST['status'],
		        'tenure'             => $_POST['tenure'],
		        'price'              => $_POST['price'],
		        'payment_gateway_id' => $_POST['payment_gateway_id'],
		    );

		    $result = $this->subscriptions->save_subscription( $data, $subscription_id );
		    $message = $result ? 1 : 2; // 1=success, 2=error

		    wp_redirect( admin_url( 'admin.php?page=my-booking-subscriptions&message=' . $message ) );
		    exit;
		}

		public function process_delete_subscription() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_die( 'You do not have sufficient permissions to access this page.' );
		    }

		    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'mybp_delete_subscription' ) ) {
		        wp_die( 'Security check failed.' );
		    }

		    $subscription_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		    $result = $this->subscriptions->delete_subscription( $subscription_id );
		    $message = $result ? 3 : 4; // 3=success, 4=error

		    wp_redirect( admin_url( 'admin.php?page=my-booking-subscriptions&message=' . $message ) );
		    exit;
		}

		// AJAX handler for log details
		public function ajax_get_log_details() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_send_json_error( 'Permission denied' );
		    }

		    if ( ! check_ajax_referer( 'mybp_log_details', 'nonce', false ) ) {
		        wp_send_json_error( 'Security check failed' );
		    }

		    $log_id = isset( $_POST['log_id'] ) ? intval( $_POST['log_id'] ) : 0;

		    if ( $log_id === 0 ) {
		        wp_send_json_error( 'Invalid log ID' );
		    }
		    // Get log details
		    $log = $this->db->get_row(
		        $this->db->prepare( "SELECT * FROM {$this->logger->log_table} WHERE id = %d", $log_id ),
		        ARRAY_A
		    );

		    if ( ! $log ) {
		        wp_send_json_error( 'Log not found' );
		    }

		    $html = $this->render_log_details( $log );
		    wp_send_json_success( array( 'html' => $html ) );
		}

		// Render log details
		private function render_log_details( $log ) {
		    ob_start();
		    ?>
		    <div class="log-details">
		        <h4>Action: <?php echo esc_html( ucfirst( $log['action_type'] ) ); ?></h4>
		        <p><strong>User:</strong> <?php echo esc_html( $log['user_name'] ); ?> (ID: <?php echo esc_html( $log['user_id'] ); ?>)</p>
		        <p><strong>IP Address:</strong> <?php echo esc_html( $log['ip_address'] ); ?></p>
		        <p><strong>Timestamp:</strong> <?php echo esc_html( $log['created_at'] ); ?></p>

		        <?php if ( $log['old_values'] && $log['new_values'] ) : ?>
		            <h4>Changes Made:</h4>
		            <table class="widefat">
		                <thead>
		                    <tr>
		                        <th>Field</th>
		                        <th>Old Value</th>
		                        <th>New Value</th>
		                    </tr>
		                </thead>
		                <tbody>
		                    <?php
		                    $old_data = json_decode( $log['old_values'], true );
		                    $new_data = json_decode( $log['new_values'], true );

		                    foreach ( $new_data as $key => $value ) :
		                        if ( ! isset( $old_data[ $key ] ) || $old_data[ $key ] !== $value ) :
		                            $old_value = isset( $old_data[ $key ] ) ? $old_data[ $key ] : '(empty)';
		                    ?>
		                        <tr>
		                            <td><strong><?php echo esc_html( $key ); ?></strong></td>
		                            <td><?php echo esc_html( is_array( $old_value ) ? json_encode( $old_value ) : $old_value ); ?></td>
		                            <td><?php echo esc_html( is_array( $value ) ? json_encode( $value ) : $value ); ?></td>
		                        </tr>
		                    <?php endif; endforeach; ?>
		                </tbody>
		            </table>
		        <?php else: ?>
		            <p><em>No detailed changes recorded.</em></p>
		        <?php endif; ?>
		    </div>
		    <?php
		    return ob_get_clean();
		}

        // --- OTHER PAGES ---
		public function render_dashboard_page() {
		    $table = $this->db->prefix . $this->table_prefix;
		    // Get counts for dashboard
		    $clubs_count = $this->db->get_var("SELECT COUNT(*) FROM {$table}clubs");
		    $lakes_count = $this->db->get_var("SELECT COUNT(*) FROM {$table}lakes");
		    $bookings_count = $this->db->get_var("SELECT COUNT(*) FROM {$table}bookings");
		    // Make reports and bookings instances available to the view
		    $reports_instance = $this->reports;
		    $bookings_instance = $this->bookings;
		    require_once plugin_dir_path( __FILE__ ) . 'views/dashboard.php';
		}

        public function render_subscriptions_page() {
            $subscriptions = $this->subscriptions->get_subscriptions();
    		require_once plugin_dir_path( __FILE__ ) . 'views/subscriptions-list.php';
        }

        public function render_resource_availability_page() {
            require_once plugin_dir_path( __FILE__ ) . 'views/resource-availability.php';
        }

        public function render_resource_exceptions_page() {
            require_once plugin_dir_path( __FILE__ ) . 'views/resource-exceptions.php';
        }

        public function render_edit_subscription_page() {
            $subscription_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		    $subscription_data = array();
		    $title = 'Add New Subscription';

		    if ( $subscription_id > 0 ) {
		        $subscription_data = $this->subscriptions->get_subscription( $subscription_id );
		        if ( $subscription_data ) {
		            $title = 'Edit Subscription: ' . esc_html( $subscription_data['plan_name'] );
		        }
		    }
		    require_once plugin_dir_path( __FILE__ ) . 'views/subscription-form.php';
        }

		// Add the reports page renderer
		public function render_reports_page() {
		    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-01' );
		    $end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-t' );
		    $report_type = isset( $_GET['report_type'] ) ? sanitize_key( $_GET['report_type'] ) : 'overview';

		    // Handle CSV export
		    if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
		        $this->handle_report_export( $report_type, $start_date, $end_date );
		    }

		    $report_data = $this->get_report_data( $report_type, $start_date, $end_date );

		    require_once plugin_dir_path( __FILE__ ) . 'views/dashboard.php';
		}

		// Helper method to get report data
		private function get_report_data( $report_type, $start_date, $end_date ) {
		    switch ( $report_type ) {
		        case 'lake_utilization':
		            return $this->reports->get_lake_utilization( $start_date, $end_date );
		        case 'club_activity':
		            return $this->reports->get_club_activity( $start_date, $end_date );
		        case 'match_types':
		            return $this->reports->get_popular_match_types( $start_date, $end_date );
		        case 'revenue':
		            return $this->reports->get_revenue_report( $start_date, $end_date );
		        case 'overview':
		        default:
		            return $this->reports->get_booking_stats( $start_date, $end_date );
		    }
		}

		// Handle CSV exports
		private function handle_report_export( $report_type, $start_date, $end_date ) {
		    $data = $this->get_report_data( $report_type, $start_date, $end_date );

		    if ( is_array( $data ) && ! empty( $data ) ) {
		        $this->reports->export_to_csv( $data, $report_type . '_report' );
		    }
		}

		/**
		 * Render overview report
		 */
		private function render_overview_report( $data ) {
		    $booking_counts = $data['booking_counts'] ?? array();
		    ?>
		    <div class="report-stats">
		        <div class="stat-card">
		            <div class="stat-number"><?php echo esc_html( $booking_counts['total_bookings'] ?? 0 ); ?></div>
		            <div class="stat-label">Total Bookings</div>
		        </div>
		        <div class="stat-card">
		            <div class="stat-number"><?php echo esc_html( $booking_counts['booked_bookings'] ?? 0 ); ?></div>
		            <div class="stat-label">Booked</div>
		        </div>
		        <div class="stat-card">
		            <div class="stat-number"><?php echo esc_html( $booking_counts['confirmed_bookings'] ?? 0 ); ?></div>
		            <div class="stat-label">Confirmed</div>
		        </div>
		        <div class="stat-card">
		            <div class="stat-number"><?php echo esc_html( $booking_counts['draft_bookings'] ?? 0 ); ?></div>
		            <div class="stat-label">Draft</div>
		        </div>
		        <div class="stat-card">
		            <div class="stat-number"><?php echo esc_html( $data['pegs_booked'] ?? 0 ); ?></div>
		            <div class="stat-label">Pegs Booked</div>
		        </div>
		    </div>

		    <!-- Debug info (remove this after testing) -->
		    <div style="background: #f0f0f1; padding: 10px; margin: 10px 0; border-left: 4px solid #2271b1;">
		        <strong>Debug Info:</strong><br>
		        Total Bookings Query: <?php echo esc_html( $booking_counts['total_bookings'] ?? 'N/A' ); ?><br>
		        Booked Status Count: <?php echo esc_html( $booking_counts['booked_bookings'] ?? 'N/A' ); ?><br>
		        Confirmed Status Count: <?php echo esc_html( $booking_counts['confirmed_bookings'] ?? 'N/A' ); ?><br>
		        Draft Status Count: <?php echo esc_html( $booking_counts['draft_bookings'] ?? 'N/A' ); ?><br>
		        Pegs Booked: <?php echo esc_html( $data['pegs_booked'] ?? 'N/A' ); ?>
		    </div>

		    <?php if ( ! empty( $data['bookings_by_lake'] ) ) : ?>
		        <h3>Bookings by Lake</h3>
		        <table class="wp-list-table widefat fixed striped">
		            <thead>
		                <tr>
		                    <th>Lake</th>
		                    <th>Bookings</th>
		                </tr>
		            </thead>
		            <tbody>
		                <?php foreach ( $data['bookings_by_lake'] as $lake ) : ?>
		                    <tr>
		                        <td><?php echo esc_html( $lake['lake_name'] ); ?></td>
		                        <td><?php echo esc_html( $lake['booking_count'] ); ?></td>
		                    </tr>
		                <?php endforeach; ?>
		            </tbody>
		        </table>
		    <?php endif; ?>

		    <?php if ( ! empty( $data['bookings_by_club'] ) ) : ?>
		        <h3>Bookings by Club</h3>
		        <table class="wp-list-table widefat fixed striped">
		            <thead>
		                <tr>
		                    <th>Club</th>
		                    <th>Bookings</th>
		                </tr>
		            </thead>
		            <tbody>
		                <?php foreach ( $data['bookings_by_club'] as $club ) : ?>
		                    <tr>
		                        <td><?php echo esc_html( $club['club_name'] ); ?></td>
		                        <td><?php echo esc_html( $club['booking_count'] ); ?></td>
		                    </tr>
		                <?php endforeach; ?>
		            </tbody>
		        </table>
		    <?php endif; ?>
		    <?php
		}

		/**
		 * Render lake utilization report
		 */
		private function render_lake_utilization_report( $data ) {
		    ?>
		    <h3>Lake Utilization Report</h3>
		    <table class="wp-list-table widefat fixed striped">
		        <thead>
		            <tr>
		                <th>Lake</th>
		                <th>Total Pegs</th>
		                <th>Available Pegs</th>
		                <th>Booked Pegs</th>
		                <th>Utilization Rate</th>
		            </tr>
		        </thead>
		        <tbody>
		            <?php foreach ( $data as $lake ) : ?>
		                <tr>
		                    <td><?php echo esc_html( $lake['lake_name'] ); ?></td>
		                    <td><?php echo esc_html( $lake['total_pegs'] ); ?></td>
		                    <td><?php echo esc_html( $lake['available_pegs'] ); ?></td>
		                    <td><?php echo esc_html( $lake['booked_pegs'] ); ?></td>
		                    <td>
		                        <span class="<?php
		                            if ( $lake['utilization_rate'] > 80 ) echo 'utilization-high';
		                            elseif ( $lake['utilization_rate'] > 50 ) echo 'utilization-medium';
		                            else echo 'utilization-low';
		                        ?>">
		                            <?php echo esc_html( $lake['utilization_rate'] ); ?>%
		                        </span>
		                    </td>
		                </tr>
		            <?php endforeach; ?>
		        </tbody>
		    </table>
		    <?php
		}

		/**
		 * Render club activity report
		 */
		private function render_club_activity_report( $data ) {
		    ?>
		    <h3>Club Activity Report</h3>
		    <table class="wp-list-table widefat fixed striped">
		        <thead>
		            <tr>
		                <th>Club</th>
		                <th>Total Bookings</th>
		                <th>Unique Sessions</th>
		                <th>Lakes Used</th>
		                <th>First Booking</th>
		                <th>Last Booking</th>
		            </tr>
		        </thead>
		        <tbody>
		            <?php foreach ( $data as $club ) : ?>
		                <tr>
		                    <td><?php echo esc_html( $club['club_name'] ); ?></td>
		                    <td><?php echo esc_html( $club['total_bookings'] ); ?></td>
		                    <td><?php echo esc_html( $club['unique_booking_sessions'] ); ?></td>
		                    <td><?php echo esc_html( $club['lakes_used'] ); ?></td>
		                    <td><?php echo esc_html( date( 'M j, Y', strtotime( $club['first_booking'] ) ) ); ?></td>
		                    <td><?php echo esc_html( date( 'M j, Y', strtotime( $club['last_booking'] ) ) ); ?></td>
		                </tr>
		            <?php endforeach; ?>
		        </tbody>
		    </table>
		    <?php
		}

		/**
		 * Render match types report
		 */
		private function render_match_types_report( $data ) {
		    ?>
		    <h3>Popular Match Types</h3>
		    <table class="wp-list-table widefat fixed striped">
		        <thead>
		            <tr>
		                <th>Match Type</th>
		                <th>Usage Count</th>
		                <th>Unique Bookings</th>
		            </tr>
		        </thead>
		        <tbody>
		            <?php foreach ( $data as $match_type ) : ?>
		                <tr>
		                    <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $match_type['match_type_slug'] ) ) ); ?></td>
		                    <td><?php echo esc_html( $match_type['usage_count'] ); ?></td>
		                    <td><?php echo esc_html( $match_type['unique_bookings'] ); ?></td>
		                </tr>
		            <?php endforeach; ?>
		        </tbody>
		    </table>
		    <?php
		}

		/**
		 * Render revenue report
		 */
		private function render_revenue_report( $data ) {
		    ?>
		    <div class="notice notice-info">
		        <p><?php echo esc_html( $data['message'] ); ?></p>
		    </div>
		    <?php
		}
		public function enqueue_admin_scripts( $hook ) {
		    // Only load scripts on our lake edit page
		    if ( ! isset( $_GET['page'] ) || 'my-booking-edit-lake' !== $_GET['page'] ) {
		        return;
		    }
		    wp_enqueue_media();
		}

		/**
		 * Holidays
		 */
		public function render_holidays_page() {
		    $holidays = $this->holidays->get_holidays();
		    require_once plugin_dir_path( __FILE__ ) . 'views/holidays-list.php';
		}

		// Render edit holiday page
		public function render_edit_holiday_page() {
		    $holiday_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		    $holiday_data = array();
		    $title = 'Add New Holiday';

		    if ( $holiday_id > 0 ) {
		        $holiday_data = $this->holidays->get_holiday( $holiday_id );
		        if ( $holiday_data ) {
		            $title = 'Edit Holiday: ' . esc_html( $holiday_data['holiday_name'] );
		        }
		    }

		    require_once plugin_dir_path( __FILE__ ) . 'views/holiday-form.php';
		}

		// Process holiday actions (add/edit)
		public function process_holiday_actions() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_die( 'You do not have sufficient permissions.' );
		    }
		    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'mybp_holiday_nonce' ) ) {
		        wp_die( 'Security check failed.' );
		    }

		    $holiday_id = isset( $_POST['holiday_id'] ) ? intval( $_POST['holiday_id'] ) : 0;
		    $result = $this->holidays->save_holiday( $_POST, $holiday_id );

		    if ( $result !== false ) {
		        $message = ( $holiday_id > 0 ) ? 'Holiday updated successfully.' : 'Holiday added successfully.';
		        wp_redirect( admin_url( 'admin.php?page=my-booking-holidays&message=' . urlencode( $message ) ) );
		        exit;
		    } else {
		        $error_message = 'Error saving holiday.';
		        wp_redirect( admin_url( 'admin.php?page=my-booking-holidays&error=1&msg=' . urlencode( $error_message ) ) );
		        exit;
		    }
		}

		// Process holiday deletion
		public function ajax_delete_holiday() {
		    if ( ! current_user_can( 'manage_options' ) ) {
		        wp_die( 'Permission denied' );
		    }

		    check_ajax_referer( 'mybp_delete_holiday', 'nonce' );

		    $holiday_id = isset( $_POST['holiday_id'] ) ? $_POST['holiday_id'] : 0;
		    $result = $this->holidays->delete_holiday( $holiday_id );

		    if ( $result ) {
		        wp_send_json_success( 'Holiday deleted successfully' );
		    } else {
		        wp_send_json_error( 'Failed to delete holiday' );
		    }
		}
		/**
		 * Get holidays instance
		 */
		public function get_holidays_instance() {
		    return $this->holidays;
		}
    }
}
