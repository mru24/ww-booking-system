<?php
/**
 * Bookings Functions
 * Handles CRUD and complex data retrieval for the 'bookings' and 'booking_pegs' tables.
 */

if ( ! class_exists( 'WW_Booking_Bookings' ) ) {

    class WW_Booking_Bookings {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name = 'bookings' ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Fetches all pegs for a given lake, cross-referencing existing bookings for a date range.
         * This is the core logic for the booking form view.
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
                    l.id
                FROM {$pegs_table} p
                INNER JOIN {$lakes_table} l ON p.lake_id = l.id
                WHERE p.lake_id = %d AND p.peg_status = 'open'
                ORDER BY p.peg_name ASC
            ", $lake_id );

            $pegs = $this->db->get_results( $sql_pegs, ARRAY_A );

            if ( empty( $pegs ) ) {
                return array();
            }

            // 2. Find ALL existing bookings for these pegs overlapping the date range
            // We use two checks:
            // - Existing booking starts BEFORE our end_date AND
            // - Existing booking ends AFTER our start_date
            $sql_booked_pegs = $this->db->prepare( "
                SELECT
                    bp.peg_id,
                    bp.status,
                    bp.match_type_slug,
                    bp.club_id,
                    b.id AS booking_id,
                    b.booking_status
                FROM {$booking_pegs_table} bp
                INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
                WHERE
                    bp.peg_id IN (" . implode( ',', wp_list_pluck( $pegs, 'peg_id' ) ) . ")
                    AND b.booking_status IN ('draft', 'booked')
                    AND b.date_start <= %s
                    AND b.date_end >= %s
            ", $end_date, $start_date );

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
                    $peg['is_booked'] = $booked_pegs_map[ $peg_id ]['status'];
                    $peg['booking_details'] = $booked_pegs_map[ $peg_id ];
                }
                $output[] = $peg;
            }

            return $output;
        }

		/**
		 * Update an existing booking with new data
		 */
		public function update_booking($booking_id, $data) {
		    $bookings_table = $this->get_table_name('bookings');
		    $booking_pegs_table = $this->get_table_name('booking_pegs');

		    $booking_id = absint($booking_id);
		    if ($booking_id === 0) {
		        return false;
		    }

		    $this->db->query('START TRANSACTION');
		    try {
		        // 1. Update booking header
		        $booking_data = array(
		            'lake_id' => absint($data['lake_id']),
		            'date_start' => sanitize_text_field($data['date_start']),
		            'date_end' => sanitize_text_field($data['date_end']),
		            'booking_status' => sanitize_key($data['booking_status']),
		            'updated_at' => current_time('mysql', true),
		        );

		        $result = $this->db->update(
		            $bookings_table,
		            $booking_data,
		            array('id' => $booking_id),
		            array('%d', '%s', '%s', '%s', '%s'),
		            array('%d')
		        );

		        if ($result === false) {
		            throw new Exception('Failed to update booking header');
		        }

		        // 2. Remove existing booking pegs
		        $this->db->delete(
		            $booking_pegs_table,
		            array('booking_id' => $booking_id),
		            array('%d')
		        );

		        // 3. Insert new booking pegs
		        if (!empty($data['pegs']) && is_array($data['pegs'])) {
		            foreach ($data['pegs'] as $peg_id => $peg_data) {
		                // Only process pegs that are marked as booked and have required data
		                if (isset($peg_data['status']) && $peg_data['status'] === 'booked' &&
		                    !empty($peg_data['match_type_slug']) && !empty($peg_data['club_id'])) {

		                    $booking_peg_id = generateUniqueId();
		                    $booking_peg = array(
		                        'id' => $booking_peg_id,
		                        'booking_id' => $booking_id,
		                        'peg_id' => absint($peg_id),
		                        'match_type_slug' => sanitize_key($peg_data['match_type_slug']),
		                        'club_id' => absint($peg_data['club_id']),
		                        'status' => 'booked',
		                        'created_at' => current_time('mysql', true),
		                    );

		                    $this->db->insert($booking_pegs_table, $booking_peg,
		                        array('%d', '%d', '%d', '%s', '%d', '%s', '%s'));
		                }
		            }
		        }

		        $this->db->query('COMMIT');
		        return $booking_id;

		    } catch (Exception $e) {
		        $this->db->query('ROLLBACK');
		        error_log('Booking update failed: ' . $e->getMessage());
		        return false;
		    }
		}

		/**
		 * Save a new booking (header) and its associated pegs (details).
		 * Supports partial booking where only selected pegs are saved.
		 */
		public function save_booking( $data ) {
		    $bookings_table = $this->get_table_name( 'bookings' );
		    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
		    $this->db->query( 'START TRANSACTION' );
		    try {
		        // ... (1. Prepare and Save Booking Header - Unchanged) ...
		        $booking_id = generateUniqueId();
		        $booking_header = array(
		            'id' => $booking_id,
		            'lake_id' => absint( $data['lake_id'] ),
		            'date_start' => sanitize_text_field( $data['date_start'] ),
		            'date_end' => sanitize_text_field( $data['date_end'] ),
		            'booking_status' => sanitize_key( $data['booking_status'] ),
		            'created_at' => current_time( 'mysql', true ),
		        );
		        $format = array( '%d', '%d', '%s', '%s', '%s', '%s' );
		        $this->db->insert( $bookings_table, $booking_header, $format );
		        if ( ! $booking_id ) {
		            throw new Exception( 'Failed to create booking header.' );
		        }

		        // --- 2. Save Booking Pegs ---
		        if ( ! empty( $data['pegs'] ) && is_array( $data['pegs'] ) ) {

		            // ** CRITICAL: FILTER PEGS TO PROCESS **
		            // We only want to process pegs where the intent is to book them.
		            // If the frontend sends unselected pegs with status 'available', we ignore them.
		            $pegs_to_book = array_filter( $data['pegs'], function( $peg_data ) {
		                // Check for explicit 'booked' status OR presence of required fields.
		                // Assuming 'match_type_slug' and 'club_id' are REQUIRED for a booking.
		                return ( ! empty( $peg_data['match_type_slug'] ) && ! empty( $peg_data['club_id'] ) );
		            } );

		            if ( empty( $pegs_to_book ) ) {
		                // If the booking header was created but no pegs were selected/valid,
		                // this entire transaction should likely fail or be noted.
		                // For now, let's allow a booking header without pegs but log a warning.
		                error_log('Warning: Booking created without any valid pegs selected.');
		                $this->db->query( 'COMMIT' );
		                return $booking_id;
		            }

		            // OPTION A: Derive common data from the first valid peg (best practice for shared data)
		            $first_peg_data     = current( $pegs_to_book ); // Get the data of the first valid peg
		            $default_match_type = sanitize_key( $first_peg_data['match_type_slug'] ?? 'default' );
		            $default_club_id    = absint( $first_peg_data['club_id'] ?? 0 );

		            foreach ( $pegs_to_book as $peg_id => $peg_data ) {
		                $booking_peg_id = generateUniqueId();

		                // Safely retrieve the match type slug, falling back to the default/common value
		                $match_type_slug = sanitize_key( $peg_data['match_type_slug'] ?? $default_match_type );

		                // Safely retrieve the club ID, falling back to the default/common value (0)
		                $club_id = absint( $peg_data['club_id'] ?? $default_club_id );

		                // Ensure the status is set to 'booked' for successful inserts
		                $status = 'booked';
		                $booking_peg = array(
		                    'id'              => $booking_peg_id,
		                    'booking_id'      => $booking_id,
		                    'peg_id'          => absint( $peg_id ),
		                    'match_type_slug' => $match_type_slug,
		                    'club_id'         => $club_id,
		                    'status'          => $status,
		                    'created_at'      => current_time( 'mysql', true ),
		                );
		                $format = array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' );
		                $result = $this->db->insert( $booking_pegs_table, $booking_peg, $format );

						$this->log_booking_action( 'created', $booking_id, $data );

		                if ( false === $result || $this->db->last_error ) {
		                    error_log('Database error on peg insert: ' . $this->db->last_error);
		                    throw new Exception( 'Failed to insert booking peg detail.' );
		                }
		            }
		        }
		        $this->db->query( 'COMMIT' );
		        return $booking_id;
		    } catch ( Exception $e ) {
		        $this->db->query( 'ROLLBACK' );
		        error_log('Booking transaction failed: ' . $e->getMessage());
		        return false;
		    }
		}

		/**
		 * Update an existing booking (header) and replace its associated pegs (details).
		 * Performs a transaction to ensure all related data is updated or rolled back.
		 */
		public function edit_booking( $booking_id, $data ) {
		    $bookings_table     = $this->get_table_name( 'bookings' );
		    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
		    $booking_id         = absint( $booking_id );

		    if ( $booking_id === 0 ) {
		        return false;
		    }

		    $this->db->query( 'START TRANSACTION' );
		    try {
		        // 1. Prepare and Update Booking Header
		        $booking_header = array(
		            'lake_id'        => absint( $data['lake_id'] ),
		            'date_start'     => sanitize_text_field( $data['date_start'] ),
		            'date_end'       => sanitize_text_field( $data['date_end'] ),
		            'booking_status' => sanitize_key( $data['booking_status'] ),
		            'updated_at'     => current_time( 'mysql', true ),
		        );

		        $where = array( 'id' => $booking_id );
		        $format = array( '%d', '%s', '%s', '%s', '%s' );

		        $result_header = $this->db->update( $bookings_table, $booking_header, $where, $format );

		        // If update fails and it wasn't just a status change (i.e., data was identical)
		        if ( false === $result_header && ! is_wp_error( $this->db->last_error ) ) {
		            // Check if any rows were affected; if not, assume data was identical, otherwise throw error.
		            if ( $this->db->rows_affected === 0 ) {
		                // If no changes, proceed to update pegs or skip to commit
		            } else {
		                 throw new Exception( 'Failed to update booking header.' );
		            }
		        }

		        // 2. Remove ALL existing pegs for this booking
		        $this->db->delete( $booking_pegs_table, array( 'booking_id' => $booking_id ), array( '%d' ) );

		        // Check for error after deletion attempt
		        if ( $this->db->last_error ) {
		            throw new Exception( 'Failed to delete old booking pegs.' );
		        }

		        // 3. Insert NEW Booking Pegs (similar logic to save_booking)
		        if ( ! empty( $data['pegs'] ) && is_array( $data['pegs'] ) ) {

		            // Filter and prepare pegs to be booked
		            $pegs_to_book = array_filter( $data['pegs'], function( $peg_data ) {
		                return ( ! empty( $peg_data['match_type_slug'] ) && ! empty( $peg_data['club_id'] ) );
		            } );

		            // Derive common data from the first valid peg
		            $first_peg_data     = current( $pegs_to_book );
		            $default_match_type = sanitize_key( $first_peg_data['match_type_slug'] ?? 'default' );
		            $default_club_id    = absint( $first_peg_data['club_id'] ?? 0 );

		            foreach ( $pegs_to_book as $peg_id => $peg_data ) {
		                // Use a new unique ID for the booking peg detail
		                $booking_peg_id = generateUniqueId();

		                $booking_peg = array(
		                    'id'              => $booking_peg_id,
		                    'booking_id'      => $booking_id,
		                    'peg_id'          => absint( $peg_id ),
		                    'match_type_slug' => sanitize_key( $peg_data['match_type_slug'] ?? $default_match_type ),
		                    'club_id'         => absint( $peg_data['club_id'] ?? $default_club_id ),
		                    'status'          => 'booked', // Always 'booked' for an active booking detail
		                    'created_at'      => current_time( 'mysql', true ),
		                );

		                $format = array( '%d', '%d', '%d', '%s', '%d', '%s', '%s' );
		                $result = $this->db->insert( $booking_pegs_table, $booking_peg, $format );

						$this->log_booking_action( 'updated', $booking_id, $data, $old_data );

		                if ( false === $result || $this->db->last_error ) {
		                    throw new Exception( 'Failed to insert updated booking peg detail.' );
		                }
		            }
		        }

		        $this->db->query( 'COMMIT' );
		        return $booking_id;
		    } catch ( Exception $e ) {
		        $this->db->query( 'ROLLBACK' );
		        error_log('Booking update failed: ' . $e->getMessage());
		        return false;
		    }
		}

		/**
		 * Delete a booking (header) and all associated peg details.
		 */
		public function delete_booking( $booking_id ) {
		    $bookings_table     = $this->get_table_name( 'bookings' );
		    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
		    $booking_id         = absint( $booking_id );

		    if ( $booking_id === 0 ) {
		        return false;
		    }

		    $this->db->query( 'START TRANSACTION' );
		    try {
		        // 1. Delete ALL associated peg details first
		        $result_pegs = $this->db->delete( $booking_pegs_table, array( 'booking_id' => $booking_id ), array( '%d' ) );

		        if ( false === $result_pegs && $this->db->last_error ) {
		            // Only throw error if it wasn't just zero rows affected (i.e. already empty)
		            throw new Exception( 'Failed to delete associated booking pegs.' );
		        }

		        // 2. Delete the main Booking Header
		        $result_header = $this->db->delete( $bookings_table, array( 'id' => $booking_id ), array( '%d' ) );

				$this->log_booking_action( 'deleted', $booking_id, array() );

		        if ( false === $result_header || $this->db->rows_affected === 0 ) {
		            // Check rows affected to ensure the booking header actually existed
		            throw new Exception( 'Failed to delete booking header or booking not found.' );
		        }
		        $this->db->query( 'COMMIT' );
		        return true;
		    } catch ( Exception $e ) {
		        $this->db->query( 'ROLLBACK' );
		        error_log('Booking deletion failed: ' . $e->getMessage());
		        return false;
		    }
		}
	    /**
	     * Helper: Fetch all booking pegs and group them by booking_id.
	     */
	    protected function get_pegs_grouped_by_booking_id( $booking_ids ) {
	        if ( empty( $booking_ids ) ) {
	            return array();
	        }

	        $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
	        $pegs_table = $this->get_table_name( 'pegs' );
	        // Assuming a clubs table exists
	        $clubs_table = $this->get_table_name( 'clubs' );

	        $ids_in = implode( ',', array_map( 'absint', $booking_ids ) );

	        // Fetch all peg details for the given booking IDs, joining peg names and club names
	        $sql = "
	            SELECT
	                bp.*,
	                p.peg_name,
	                c.club_name
	            FROM {$booking_pegs_table} bp
	            INNER JOIN {$pegs_table} p ON bp.peg_id = p.id
	            INNER JOIN {$clubs_table} c ON bp.club_id = c.id
	            WHERE bp.booking_id IN ({$ids_in})
	            ORDER BY bp.booking_id ASC, p.peg_name ASC
	        ";

	        $results = $this->db->get_results( $sql, ARRAY_A );
	        $pegs_map = array();

	        // Group pegs by booking_id for easy merging
	        foreach ( $results as $peg ) {
	            $booking_id = $peg['booking_id'];
	            if ( ! isset( $pegs_map[ $booking_id ] ) ) {
	                $pegs_map[ $booking_id ] = array();
	            }
	            $pegs_map[ $booking_id ][] = $peg;
	        }

	        return $pegs_map;
	    }


	    /**
	     * Fetch all bookings with associated lake and peg details.
	     */
	    public function get_all_bookings_with_details() {
	        $bookings_table = $this->get_table_name( 'bookings' );
	        // Assuming a lakes table exists
	        $lakes_table = $this->get_table_name( 'lakes' );

	        // 1. Fetch Booking Headers (Header + Lake Name)
	        $sql_headers = "
	            SELECT
	                b.*,
	                l.lake_name
	            FROM {$bookings_table} b
	            INNER JOIN {$lakes_table} l ON b.lake_id = l.id
	            ORDER BY b.date_start DESC, b.id DESC
	        ";

	        $bookings = $this->db->get_results( $sql_headers, ARRAY_A );

	        if ( empty( $bookings ) ) {
	            return array();
	        }

	        $booking_ids = wp_list_pluck( $bookings, 'id' );

	        // 2. Fetch all Peg Details grouped by Booking ID
	        $pegs_map = $this->get_pegs_grouped_by_booking_id( $booking_ids );

	        // 3. Merge pegs into their respective booking headers
	        foreach ( $bookings as &$booking ) {
	            $booking['pegs'] = isset( $pegs_map[ $booking['id'] ] ) ? $pegs_map[ $booking['id'] ] : array();
	        }

	        return $bookings;
	    }
		/*
		 * Fetch one booking
		 */
		public function get_booking_with_details( $booking_id ) {
		    global $wpdb;

		    $bookings_table = $this->get_table_name( 'bookings' );
		    $lakes_table = $this->get_table_name( 'lakes' );

		    // 1. Fetch the booking header (with lake name)
		    $sql = $wpdb->prepare("
		        SELECT
		            b.*,
		            l.lake_name
		        FROM {$bookings_table} b
		        INNER JOIN {$lakes_table} l ON b.lake_id = l.id
		        WHERE b.id = %d
		        LIMIT 1
		    ", $booking_id);

		    $booking = $this->db->get_row( $sql, ARRAY_A );

		    if ( empty( $booking ) ) {
		        return null; // Booking not found
		    }

		    // 2. Fetch pegs related to this booking
		    $pegs_map = $this->get_pegs_grouped_by_booking_id( array( $booking_id ) );

		    // 3. Merge pegs into the booking
		    $booking['pegs'] = isset( $pegs_map[ $booking_id ] ) ? $pegs_map[ $booking_id ] : array();

		    return $booking;
		}

		protected function is_date_available( $date ) {
		    global $ww_booking_system;

		    // Check if it's a weekend
		    $day_of_week = date( 'N', strtotime( $date ) );
		    $is_weekend = ( $day_of_week >= 6 ); // 6 = Saturday, 7 = Sunday

		    // Check if it's a holiday
		    $is_holiday = false;
		    if ( isset( $ww_booking_system->admin->holidays ) ) {
		        $is_holiday = $ww_booking_system->admin->holidays->is_holiday( $date );
		    }

		    return array(
		        'available' => !$is_holiday,
		        'is_weekend' => $is_weekend,
		        'is_holiday' => $is_holiday,
		        'day_of_week' => $day_of_week
		    );
		}
		/**
		 * Fetches daily availability for each day in a date range
		 */
		public function get_daily_availability( $lake_id, $start_date, $end_date ) {
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

		    $all_pegs = $this->db->get_results( $sql_pegs, ARRAY_A );
		    $total_pegs = count( $all_pegs );

		    if ( empty( $all_pegs ) ) {
		        return array();
		    }

		    $peg_ids = wp_list_pluck( $all_pegs, 'peg_id' );
		    $peg_ids_placeholder = implode( ',', array_fill( 0, count( $peg_ids ), '%d' ) );

		    // 2. Get ALL bookings that overlap with ANY day in our date range
		    $sql_bookings = $this->db->prepare( "
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
		            AND bp.status = 'booked'
		            AND b.date_start <= %s
		            AND b.date_end >= %s
		    ", array_merge( $peg_ids, [ $end_date, $start_date ] ) );

		    $all_bookings = $this->db->get_results( $sql_bookings, ARRAY_A );

		    // 3. Create a map of booked pegs per day
		    $booked_pegs_per_day = array();

		    // Initialize all days with empty booked pegs array
		    $current_date = new DateTime( $start_date );
		    $end_date_obj = new DateTime( $end_date );

		    while ( $current_date <= $end_date_obj ) {
		        $date_str = $current_date->format( 'Y-m-d' );
		        $booked_pegs_per_day[ $date_str ] = array();
		        $current_date->modify( '+1 day' );
		    }

		    // 4. For each booking, mark the pegs as booked for each day they're occupied
		    foreach ( $all_bookings as $booking ) {
		        $booking_start = new DateTime( $booking['date_start'] );
		        $booking_end = new DateTime( $booking['date_end'] );
		        $peg_id = $booking['peg_id'];

		        // Iterate through each day of the booking
		        $current_booking_date = clone $booking_start;
		        while ( $current_booking_date <= $booking_end ) {
		            $date_str = $current_booking_date->format( 'Y-m-d' );

		            // Only track days within our requested range
		            if ( $date_str >= $start_date && $date_str <= $end_date ) {
		                if ( ! in_array( $peg_id, $booked_pegs_per_day[ $date_str ] ) ) {
		                    $booked_pegs_per_day[ $date_str ][] = $peg_id;
		                }
		            }

		            $current_booking_date->modify( '+1 day' );
		        }
		    }

		    // 5. Build daily availability data
		    $daily_availability = array();
		    $current_date = new DateTime( $start_date );
		    $end_date_obj = new DateTime( $end_date );

		    while ( $current_date <= $end_date_obj ) {
		        $date_str = $current_date->format( 'Y-m-d' );

				$date_availability = $this->is_date_available( $date_str );

		        $booked_pegs_count = count( $booked_pegs_per_day[ $date_str ] );
		        $available_pegs_count = $total_pegs - $booked_pegs_count;

				// Determine status - holidays override everything
		        if ( $date_availability['is_holiday'] ) {
		            $status = 'holiday';
		        } else if ( $booked_pegs_count === $total_pegs && $total_pegs > 0 ) {
		            $status = 'fully-booked';
		        } elseif ( $booked_pegs_count > 0 ) {
		            $status = 'partially-booked';
		        } else {
		            $status = 'available';
		        }

		        // Determine status
		        $status = 'available';
		        if ( $booked_pegs_count === $total_pegs && $total_pegs > 0 ) {
		            $status = 'fully-booked';
		        } elseif ( $booked_pegs_count > 0 ) {
		            $status = 'partially-booked';
		        }

		        // Get detailed peg information for this specific day
		        $daily_pegs = array();
		        foreach ( $all_pegs as $peg ) {
		            $is_booked = in_array( $peg['peg_id'], $booked_pegs_per_day[ $date_str ] );
		            $daily_pegs[] = array_merge( $peg, array(
		                'is_booked' => $is_booked ? 'booked' : 'available',
		                'booking_details' => $is_booked ? $this->get_booking_details_for_peg_date( $peg['peg_id'], $date_str ) : null
		            ) );
		        }

		        $daily_availability[] = array(
		            'date' => $date_str,
		            'status' => $status,
		            'total_pegs' => $total_pegs,
		            'booked_pegs' => $booked_pegs_count,
		            'available_pegs' => $available_pegs_count,
		            'pegs' => $daily_pegs,
		            'date_info' => $date_availability,
		            'name' => 'test'
		        );

		        $current_date->modify( '+1 day' );
		    }

		    return $daily_availability;
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
		 * Get all active match types
		 */
		public function get_match_types() {
		    $match_types_table = $this->get_table_name( 'match_types' );

		    $sql = "SELECT id, type_name, type_slug, description
		            FROM {$match_types_table}
		            ORDER BY type_name ASC";

		    return $this->db->get_results( $sql, ARRAY_A );
		}

		/**
		 * Get all enabled clubs
		 */
		public function get_clubs() {
		    $clubs_table = $this->get_table_name( 'clubs' );

		    $sql = "SELECT id, club_name, club_address, postcode, country, contact_name, phone, email
		            FROM {$clubs_table}
		            WHERE club_status = 'enabled'
		            ORDER BY club_name ASC";

		    return $this->db->get_results( $sql, ARRAY_A );
		}

		// Add to save_booking method (after successful booking creation)
		private function log_booking_action( $action, $booking_id, $data, $old_data = array() ) {
		    global $ww_booking_system;

		    if ( isset( $ww_booking_system->admin->logger ) ) {
		        $logger = $ww_booking_system->admin->logger;

		        // Get lake name for object name
		        $lakes_table = $this->get_table_name( 'lakes' );
		        $lake_name = $this->db->get_var(
		            $this->db->prepare( "SELECT lake_name FROM {$lakes_table} WHERE id = %d", $data['lake_id'] )
		        );

		        $object_name = "Booking #{$booking_id} - {$lake_name}";

		        $logger->log_action(
		            $action,
		            'booking',
		            $booking_id,
		            $object_name,
		            $old_data,
		            $data
		        );
		    }
		}
    }
}