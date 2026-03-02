<?php
/**
 * Frontend Class
 * Handles shortcodes, script enqueuing, and public-facing REST API endpoints.
 */
if ( ! defined( 'WWBP_PLUGIN_DIR_FRONT' ) ) {
    define( 'WWBP_PLUGIN_DIR_FRONT', plugin_dir_url( dirname( __FILE__ ) ) );
}
if ( ! class_exists( 'WW_Booking_Frontend' ) ) {

    class WW_Booking_Frontend {
        protected $db;
        protected $table_prefix;
        protected $bookings_functions;
		protected $lake;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;

            // Load the Bookings functions class
            require_once WWBP_PLUGIN_DIR . 'admin/includes/class-ww-booking-bookings.php';
            $this->bookings_functions = new WW_Booking_Bookings( $this->db, $this->table_prefix );
            $this->lake = new WW_Booking_Lakes( $this->db, $this->table_prefix );

			 // Load the Lakes class
		    require_once WWBP_PLUGIN_DIR . 'admin/includes/class-ww-booking-lakes.php';
		    $this->lake = new WW_Booking_Lakes( $this->db, $this->table_prefix );

            // Hook the necessary frontend actions
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'init', array( $this, 'register_shortcodes' ) );

			add_action('rest_api_init', array($this, 'register_rest_routes'));
        }

        /**
         * Enqueue necessary styles and scripts for the calendar.
         */
        public function enqueue_scripts() {
            wp_enqueue_style('ww-booking-calendar-style', WWBP_PLUGIN_DIR_FRONT . 'assets/css/calendar.css', array(), rand());
			wp_enqueue_style('ww-booking-frontend-style', WWBP_PLUGIN_DIR_FRONT . 'assets/css/style.css', array(), rand());
		    wp_enqueue_script('ww-booking-calendar-script', WWBP_PLUGIN_DIR_FRONT . 'assets/js/calendar.js', array('jquery'), rand(), true);

			// Get the active modules setting
    		$active_modules = get_option('mybp_active_modules', array());
    		$enable_booking_popup = isset($active_modules['enable_booking_popup']) ? $active_modules['enable_booking_popup'] : true;

		    // Localize script for REST API
		    wp_localize_script('ww-booking-calendar-script', 'wwBooking', array(
		        'rest_url' => rest_url(),
		        'nonce' => wp_create_nonce('wp_rest'),
		        'enable_booking_popup' => $enable_booking_popup
		    ));
			error_log('Sending to JS - enable_booking_popup: ' . ($enable_booking_popup ? 'true' : 'false'));
        }

		/**
		 * Register REST API routes for frontend
		 */
		public function register_rest_routes() {
		    register_rest_route('ww-booking/v1', '/availability', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_available_slots'),
		        'permission_callback' => '__return_true'
		    ));

			register_rest_route('ww-booking/v1', '/lake_name', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_lake'),
		        'permission_callback' => '__return_true'
		    ));

		    register_rest_route('ww-booking/v1', '/bookings', array(
		        'methods' => 'POST',
		        'callback' => array($this, 'create_booking'),
		        'permission_callback' => array($this, 'check_booking_permissions')
		    ));

		    register_rest_route('ww-booking/v1', '/bookings/(?P<id>\d+)', array(
		        'methods' => 'PUT',
		        'callback' => array($this, 'update_booking'),
		        'permission_callback' => array($this, 'check_booking_permissions')
		    ));

		    register_rest_route('ww-booking/v1', '/bookings/(?P<id>\d+)', array(
		        'methods' => 'DELETE',
		        'callback' => array($this, 'delete_booking'),
		        'permission_callback' => array($this, 'check_booking_permissions')
		    ));

		    register_rest_route('ww-booking/v1', '/lakes', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_lakes'),
		        'permission_callback' => '__return_true'
		    ));
			register_rest_route('ww-booking/v1', '/daily-availability/(?P<lake_id>\d+)/(?P<date>[0-9\-]+)', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_daily_availability'),
		        'permission_callback' => '__return_true'
		    ));
			register_rest_route('ww-booking/v1', '/match-types', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_match_types'),
		        'permission_callback' => '__return_true'
		    ));

		    register_rest_route('ww-booking/v1', '/clubs', array(
		        'methods' => 'GET',
		        'callback' => array($this, 'get_clubs'),
		        'permission_callback' => '__return_true'
		    ));

			register_rest_route('ww-booking/v1', '/holidays', array(
			    'methods' => 'GET',
			    'callback' => array($this, 'get_holidays_api'),
			    'permission_callback' => '__return_true'
			));
		}


        /**
         * Register the shortcodes
         */
        public function register_shortcodes() {
            add_shortcode( 'ww_booking_calendar', array( $this, 'render_calendar_shortcode' ) );  // [ww_booking_calendar]
            add_shortcode( 'ww_lake_description', array( $this, 'render_lake_description_shortcode' ) ); // [ww_lake_description]
    		add_shortcode( 'ww_lake_image', array( $this, 'render_lake_image_shortcode' ) ); // [ww_lake_image]
        }

        /**
         * Shortcode callback function.
         */
        public function render_calendar_shortcode( $atts ) {
        	$atts = shortcode_atts(
		        array(
		            'lake_id' => '',
		        ), $atts, 'ww_booking_calendar'
		    );
			$selected_lake_id = sanitize_text_field( $atts['lake_id'] );
		    $lakes_table = $this->db->prefix . $this->table_prefix . 'lakes';
		    $lakes = $this->db->get_results("SELECT id, lake_name FROM {$lakes_table} WHERE lake_status = 'enabled' ORDER BY lake_name ASC");
		    ob_start();
		    include WWBP_PLUGIN_DIR . 'frontend/views/calendars.php';
		    return ob_get_clean();
        }

		/**
		 * Shortcode: Display lake description
		 * Usage: [ww_lake_description lake_id="123"]
		 */
		public function render_lake_description_shortcode( $atts ) {
		    $atts = shortcode_atts( array(
		        'lake_id' => 0,
		    ), $atts, 'ww_lake_description' );
		    $lake_id = absint( $atts['lake_id'] );
		    if ( $lake_id === 0 ) {
		        return '<p>Please provide a valid lake ID.</p>';
		    }

		    $lake_data = $this->lake->get_lake( $lake_id );

		    if ( ! $lake_data || empty( $lake_data['description'] ) ) {
		        return '<p>No description available for this lake.</p>';
		    }
		    // Apply WordPress content filters to process shortcodes, auto-paragraphs, etc.
		    return apply_filters( 'the_content', $lake_data['description'] );
		}

		/**
		 * Shortcode: Display lake image
		 * Usage: [ww_lake_image lake_id="123" size="medium" class="custom-class"]
		 */
		public function render_lake_image_shortcode( $atts ) {
		    $atts = shortcode_atts( array(
		        'lake_id' => 0,
		        'size'    => 'medium',
		        'class'   => 'ww-lake-image',
		    ), $atts, 'ww_lake_image' );

		    $lake_id = absint( $atts['lake_id'] );

		    if ( $lake_id === 0 ) {
		        return '<p>Please provide a valid lake ID.</p>';
		    }

		    $lake_data = $this->lake->get_lake( $lake_id );

		    if ( ! $lake_data ) {
		        return '<p>Lake not found.</p>';
		    }
		    // Check if image is set to invisible
		    if ( $lake_data['lake_image_visibility'] === 'invisible' ) {
		        return ''; // Return empty string if image is invisible
		    }
		    $image_id = absint( $lake_data['lake_image_id'] );

		    if ( $image_id === 0 ) {
		        return '<p>No image available for this lake.</p>';
		    }
		    // Get the image HTML
		    $image_html = wp_get_attachment_image(
		        $image_id,
		        'full',
		        false,
		        array( 'class' => esc_attr( $atts['class'] ) )
		    );
		    if ( ! $image_html ) {
		        return '<p>Image not found.</p>';
		    }
		    return $image_html;
		}

		/**
		 * Shortcode: Display lake image with fallback
		 * Usage: [ww_lake_image_fallback lake_id="123" fallback_url="https://example.com/default.jpg"]
		 */
		public function render_lake_image_fallback_shortcode( $atts ) {
		    $atts = shortcode_atts( array(
		        'lake_id'       => 0,
		        'size'          => 'medium',
		        'class'         => 'ww-lake-image',
		        'fallback_url'  => '', // URL to fallback image
		    ), $atts, 'ww_lake_image_fallback' );

		    $lake_id = absint( $atts['lake_id'] );

		    if ( $lake_id === 0 ) {
		        return '<p>Please provide a valid lake ID.</p>';
		    }

		    $lake_data = $this->lake->get_lake( $lake_id );

		    if ( ! $lake_data ) {
		        return '<p>Lake not found.</p>';
		    }
		    // Check if image is set to invisible
		    if ( $lake_data['lake_image_visibility'] === 'invisible' ) {
		        return ''; // Return empty string if image is invisible
		    }
		    $image_id = absint( $lake_data['lake_image_id'] );
		    // If no image ID but fallback URL is provided
		    if ( $image_id === 0 && ! empty( $atts['fallback_url'] ) ) {
		        return sprintf(
		            '<img src="%s" class="%s" alt="%s" />',
		            esc_url( $atts['fallback_url'] ),
		            esc_attr( $atts['class'] ),
		            esc_attr( $lake_data['lake_name'] )
		        );
		    }
		    if ( $image_id === 0 ) {
		        return '<p>No image available for this lake.</p>';
		    }
		    $image_html = wp_get_attachment_image(
		        $image_id,
		        $atts['size'],
		        false,
		        array( 'class' => esc_attr( $atts['class'] ) )
		    );

		    return $image_html ?: '<p>Image not found.</p>';
		}

		/**
		 * REST API Callback: Fetch daily availability for calendar
		 */
		public function get_available_slots( $request ) {
		    $lake_id    = absint( $request['lake_id'] );
		    $start_date = sanitize_text_field( $request->get_param( 'start' ) );
		    $end_date   = sanitize_text_field( $request->get_param( 'end' ) );

		    // error_log("get_available_slots called - Lake ID: {$lake_id}, Start: {$start_date}, End: {$end_date}");

		    if ( $lake_id === 0 || empty( $start_date ) || empty( $end_date ) ) {
		        //error_log("Invalid parameters - Lake ID: {$lake_id}, Start: {$start_date}, End: {$end_date}");
		        return new WP_REST_Response( array( 'message' => 'Invalid parameters' ), 400 );
		    }
		    try {
		        // Use the new daily availability method
		        $daily_availability = $this->bookings_functions->get_daily_availability( $lake_id, $start_date, $end_date );
		        //error_log("Daily availability data retrieved: " . count($daily_availability) . " days");

		        return new WP_REST_Response( $daily_availability, 200 );
		    } catch ( Exception $e ) {
		        // error_log("Error in get_available_slots: " . $e->getMessage());
		        return new WP_REST_Response( array( 'message' => 'Server error loading availability' ), 500 );
		    }
		}
		/**
		 * Improved: Calculate daily availability status based on actual bookings
		 */
		protected function calculate_daily_status( $start_date, $end_date, $total_pegs, $pegs_with_availability ) {
		    $daily_events = array();

		    // Create date objects
		    $current_date = new DateTime( $start_date );
		    $end_limit    = new DateTime( $end_date );

		    // Count booked pegs for the entire period
		    $booked_pegs_count = 0;
		    foreach ( $pegs_with_availability as $peg ) {
		        if ( $peg['is_booked'] === 'booked' ) {
		            $booked_pegs_count++;
		        }
		    }

		    // For calendar view, we need to check each day individually
		    // This is a simplified version - for production you'd want to check each day against actual booking dates
		    while ( $current_date <= $end_limit ) {
		        $date_str = $current_date->format( 'Y-m-d' );

		        // In a real implementation, you'd check bookings for each specific day
		        // For now, using the same count for all days in the range
		        $status = 'available';
		        if ( $booked_pegs_count === $total_pegs && $total_pegs > 0 ) {
		            $status = 'fully-booked';
		        } elseif ( $booked_pegs_count > 0 ) {
		            $status = 'partially-booked';
		        }

		        $daily_events[] = array(
		            'date'        => $date_str,
		            'status'      => $status,
		            'total_pegs'  => $total_pegs,
		            'booked_pegs' => $booked_pegs_count,
		            'pegs'        => $pegs_with_availability // Include full peg details for modal
		        );

		        $current_date->modify( '+1 day' );
		    }

		    return $daily_events;
		}

		/**
		 * Get detailed daily availability (for modal)
		 */
		/**
		 * REST API: Get detailed availability for a specific day
		 */
		public function get_daily_availability( $request ) {
		    $lake_id = absint( $request['lake_id'] );
		    $date    = sanitize_text_field( $request['date'] );

		    if ( $lake_id === 0 || empty( $date ) ) {
		        return new WP_REST_Response( array( 'message' => 'Invalid parameters' ), 400 );
		    }

		    // Get availability for just this single day
		    $daily_availability = $this->bookings_functions->get_daily_availability( $lake_id, $date, $date );

		    if ( ! empty( $daily_availability ) ) {
		        return new WP_REST_Response( $daily_availability[0], 200 );
		    } else {
		        return new WP_REST_Response( array( 'message' => 'No availability data found' ), 404 );
		    }
		}

		/**
		 * Helper: Get booking details for a specific peg on a specific date
		 */
		protected function get_booking_details_for_peg_date( $peg_id, $date ) {
		    $bookings_table = $this->get_table_name( 'bookings' );
		    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );

		    $sql = $this->db->prepare( "
		        SELECT
		            bp.*,
		            b.date_start,
		            b.date_end,
		            b.booking_status
		        FROM {$booking_pegs_table} bp
		        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
		        WHERE
		            bp.peg_id = %d
		            AND bp.status = 'booked'
		            AND b.booking_status IN ('draft', 'booked')
		            AND b.date_start <= %s
		            AND b.date_end >= %s
		        LIMIT 1
		    ", $peg_id, $date, $date );

		    return $this->db->get_row( $sql, ARRAY_A );
		}

		/**
		 * REST API Callback: Handles updating an existing booking.
		 */
		public function update_booking( WP_REST_Request $request ) {
		    // 1. Get and Validate Booking ID
		    $booking_id = absint( $request['id'] );
		    if ( $booking_id === 0 ) {
		        return new WP_REST_Response( array( 'message' => 'Invalid booking ID.' ), 400 );
		    }

		    // 2. Extract Data (request parameters are already sanitized by WP_REST_Request)
		    // The request should contain the full booking data structure, including the 'pegs' array.
		    $data = $request->get_params();

		    // Basic validation check
		    if ( empty( $data['date_start'] ) || empty( $data['pegs'] ) ) {
		        return new WP_REST_Response( array( 'message' => 'Missing required fields for update.' ), 400 );
		    }

		    // 3. Delegate to the Bookings Class (where the core logic is)
		    $result = $this->bookings_functions->edit_booking( $booking_id, $data );

		    if ( $result ) {
		        return new WP_REST_Response( array(
		            'message' => 'Booking successfully updated.',
		            'booking_id' => $result
		        ), 200 );
		    } else {
		        return new WP_REST_Response( array(
		            'message' => 'Booking update failed due to a database error or validation issue.'
		        ), 500 );
		    }
		}

		/**
		 * REST API Callback: Handles deleting a booking.
		 */
		public function delete_booking( WP_REST_Request $request ) {
		    // 1. Get and Validate Booking ID
		    $booking_id = absint( $request['id'] );
		    if ( $booking_id === 0 ) {
		        return new WP_REST_Response( array( 'message' => 'Invalid booking ID.' ), 400 );
		    }

		    // 2. Delegate to the Bookings Class
		    $result = $this->bookings_functions->delete_booking( $booking_id );

		    if ( $result ) {
		        return new WP_REST_Response( array(
		            'message' => 'Booking successfully deleted.',
		            'booking_id' => $booking_id
		        ), 200 );
		    } else {
		        return new WP_REST_Response( array(
		            'message' => 'Booking deletion failed. The booking may not exist or a database error occurred.'
		        ), 500 );
		    }
		}

        /**
         * Get the total count of 'open' pegs for a lake.
         */
        protected function get_total_open_pegs( $lake_id ) {
            $pegs_table = $this->db->prefix . $this->table_prefix . 'pegs';
            $sql = $this->db->prepare( "
                SELECT COUNT(id)
                FROM {$pegs_table}
                WHERE lake_id = %d AND peg_status = 'open'
            ", $lake_id );

            return absint( $this->db->get_var( $sql ) );
        }


		/**
		 * Check if user can make bookings
		 */
		public function check_booking_permissions() {
		    return is_user_logged_in(); // Or your custom logic
		}

		/**
		 * REST API: Create new booking
		 */
		public function create_booking($request) {
		    $data = $request->get_params();

		    // Validate required fields
		    if (empty($data['lake_id']) || empty($data['date_start']) || empty($data['date_end']) || empty($data['pegs'])) {
		        return new WP_REST_Response(array('message' => 'Missing required fields'), 400);
		    }

		    $result = $this->bookings_functions->save_booking($data);

		    if ($result) {
		        return new WP_REST_Response(array(
		            'message' => 'Booking created successfully',
		            'booking_id' => $result
		        ), 201);
		    } else {
		        return new WP_REST_Response(array('message' => 'Booking creation failed'), 500);
		    }
		}

		/**
		 * REST API: Get lakes list
		 */
		public function get_lakes($request) {
		    $lakes_table = $this->db->prefix . $this->table_prefix . 'lakes';
		    $sql = "SELECT id, lake_name FROM {$lakes_table} WHERE status = 'active' ORDER BY lake_name ASC";
		    $lakes = $this->db->get_results($sql, ARRAY_A);

		    return new WP_REST_Response($lakes, 200);
		}
		public function get_lake( $request ) {
		    $lake_id    = absint( $request['lake_id'] );
		    $lakes_table = $this->db->prefix . $this->table_prefix . 'lakes';
			$pegs_table = $this->db->prefix . $this->table_prefix . 'pegs';
		    // Use get_var with prepare for single value
		    $lake_name = $this->db->get_var(
		        $this->db->prepare("SELECT lake_name FROM {$lakes_table} WHERE id = %d", $lake_id)
		    );
			$pegs_number = $this->db->get_var(
		        $this->db->prepare("SELECT COUNT(*) FROM {$pegs_table} WHERE lake_id = %d", $lake_id)
		    );
		    return [
		        'lake_name'   => $lake_name,
		        'pegs_number' => $pegs_number,
		    ];
		}

		/**
		 * Fetches all pegs for a given lake, cross-referencing existing bookings for a date range.
		 * Updated to properly use booking_pegs table structure.
		 */
		public function get_pegs_with_availability( $lake_id, $start_date, $end_date ) {
		    $lakes_table = $this->get_table_name( 'lakes' );
		    $pegs_table = $this->get_table_name( 'pegs' );
		    $bookings_table = $this->get_table_name( 'bookings' );
		    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );

		    $lake_id    = absint( $lake_id );
		    $start_date = sanitize_text_field( $start_date );
		    $end_date   = sanitize_text_field( $end_date );

		    if ( $lake_id === 0 || empty( $start_date ) || empty( $end_date ) ) {
		        return array();
		    }
		    // 1. Get ALL available pegs for the selected lake
		    $sql_pegs = $this->db->prepare( "
		        SELECT
		            p.id AS peg_id,
		            p.peg_name,
		            p.peg_status,
		            l.id AS lake_id,
		            l.lake_name
		        FROM {$pegs_table} p
		        INNER JOIN {$lakes_table} l ON p.lake_id = l.id
		        WHERE p.lake_id = %d AND p.peg_status = 'open'
		        ORDER BY p.peg_name ASC
		    ", $lake_id );
		    $pegs = $this->db->get_results( $sql_pegs, ARRAY_A );

		    if ( empty( $pegs ) ) {
		        return array();
		    }
		    $peg_ids = wp_list_pluck( $pegs, 'peg_id' );
		    $peg_ids_placeholder = implode( ',', array_fill( 0, count( $peg_ids ), '%d' ) );
		    // 2. Find ALL existing bookings for these pegs overlapping the date range
		    $sql_booked_pegs = $this->db->prepare( "
		        SELECT
		            bp.peg_id,
		            bp.status,
		            bp.match_type_slug,
		            bp.club_id,
		            b.id AS booking_id,
		            b.booking_status,
		            b.date_start,
		            b.date_end
		        FROM {$booking_pegs_table} bp
		        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
		        WHERE
		            bp.peg_id IN ($peg_ids_placeholder)
		            AND b.booking_status IN ('draft', 'booked')
		            AND b.date_start <= %s
		            AND b.date_end >= %s
		            AND bp.status = 'booked'
		    ", array_merge( $peg_ids, [ $end_date, $start_date ] ) );
		    $booked_pegs = $this->db->get_results( $sql_booked_pegs, ARRAY_A );
		    $booked_pegs_map = array();
		    // Map booked pegs for fast lookup
		    foreach ( $booked_pegs as $booked_peg ) {
		        $booked_pegs_map[ $booked_peg['peg_id'] ] = $booked_peg;
		    }
		    // 3. Merge the availability data
		    $output = array();
		    foreach ( $pegs as $peg ) {
		        $peg_id = absint( $peg['peg_id'] );
		        $peg['is_booked'] = 'available';
		        $peg['booking_details'] = null;
		        if ( isset( $booked_pegs_map[ $peg_id ] ) ) {
		            $peg['is_booked'] = 'booked';
		            $peg['booking_details'] = $booked_pegs_map[ $peg_id ];
		        }
		        $output[] = $peg;
		    }
		    return $output;
		}

		/**
		 * REST API: Get all match types
		 */
		public function get_match_types( $request ) {
		    try {
		        $match_types = $this->bookings_functions->get_match_types();
		        return new WP_REST_Response( $match_types, 200 );
		    } catch ( Exception $e ) {
		        // error_log("Error getting match types: " . $e->getMessage());
		        return new WP_REST_Response( array( 'message' => 'Error loading match types' ), 500 );
		    }
		}

		/**
		 * REST API: Get all clubs
		 */
		public function get_clubs( $request ) {
		    try {
		        $clubs = $this->bookings_functions->get_clubs();
		        return new WP_REST_Response( $clubs, 200 );
		    } catch ( Exception $e ) {
		        // error_log("Error getting clubs: " . $e->getMessage());
		        return new WP_REST_Response( array( 'message' => 'Error loading clubs' ), 500 );
		    }
		}

		/**
		 * REST API: Get holidays for a specific year and lake
		 */
		public function get_holidays_api( $request ) {
		    try {
		        $year = isset( $request['year'] ) ? intval( $request['year'] ) : date('Y');
		        $lake_id = isset( $request['lake_id'] ) ? intval( $request['lake_id'] ) : null;

		        // Create holidays instance directly
		        require_once WWBP_PLUGIN_DIR . 'admin/includes/class-ww-booking-holidays.php';
		        $holidays_instance = new WW_Booking_Holidays( $this->db, $this->table_prefix );

		        // Get holiday ranges for specific lake or all lakes
		        $holiday_ranges = $holidays_instance->get_holiday_ranges_for_year( $year, $lake_id );

		        return new WP_REST_Response( array(
		            'holidays' => $holiday_ranges
		        ), 200 );

		    } catch ( Exception $e ) {
		        error_log("Error in holidays API: " . $e->getMessage());
		        return new WP_REST_Response( array(
		            'message' => 'Error loading holidays',
		            'holidays' => array()
		        ), 500 );
		    }
		}
    }
}