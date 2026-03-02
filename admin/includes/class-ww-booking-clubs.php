<?php
/**
 * Clubs Functions
 * Handles CRUD operations for the 'clubs' table.
 */

if ( ! class_exists( 'WW_Booking_Clubs' ) ) {

    class WW_Booking_Clubs {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name = 'clubs' ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Fetch all clubs.
         */
        public function get_clubs() {
            $table = $this->get_table_name();
            $sql = "SELECT * FROM {$table} ORDER BY club_name ASC";
            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Fetch one club by ID.
         */
        public function get_club( $club_id ) {
            $table = $this->get_table_name();
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $club_id );
            return $this->db->get_row( $sql, ARRAY_A );
        }

		/**
		 * Check if email already exists in the database
		 */
		public function email_exists( $email, $exclude_club_id = 0 ) {
		    $table = $this->get_table_name();
		    $email = sanitize_email( $email );
		    $exclude_club_id = absint( $exclude_club_id );

		    if ( empty( $email ) ) {
		        return false;
		    }

		    $sql = $this->db->prepare( "
		        SELECT COUNT(*)
		        FROM {$table}
		        WHERE email = %s AND id != %d
		    ", $email, $exclude_club_id );

		    return $this->db->get_var( $sql ) > 0;
		}

		/**
		 * Fetch all bookings for a specific club.
		 */
		public function get_bookings_by_club( $club_id ) {
		    $club_id = absint( $club_id );
		    if ( $club_id === 0 ) {
		        return array();
		    }

		    $bookings_table = $this->db->prefix . $this->table_prefix . 'bookings';
		    $booking_pegs_table = $this->db->prefix . $this->table_prefix . 'booking_pegs';
		    $lakes_table = $this->db->prefix . $this->table_prefix . 'lakes';
		    $pegs_table = $this->db->prefix . $this->table_prefix . 'pegs';

		    $sql = $this->db->prepare( "
		        SELECT
		            b.id AS booking_id,
		            b.date_start,
		            b.date_end,
		            b.booking_status,
		            b.created_at,
		            l.lake_name,
		            p.peg_name,
		            bp.match_type_slug
		        FROM {$booking_pegs_table} bp
		        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
		        INNER JOIN {$lakes_table} l ON b.lake_id = l.id
		        INNER JOIN {$pegs_table} p ON bp.peg_id = p.id
		        WHERE bp.club_id = %d
		        ORDER BY b.created_at DESC
		    ", $club_id );

		    return $this->db->get_results( $sql, ARRAY_A );
		}

		/**
		 * Insert or update a club.
		 */
		public function save_club( $data, $club_id = 0 ) {
		    $table  = $this->get_table_name( 'clubs' );

		    $email = sanitize_email( $data['email'] );
		    $club_name = sanitize_text_field( $data['club_name'] );

		    // Validate required fields
		    // if ( empty( $club_name ) || empty( $email ) ) {
		        // return false;
		    // }

		    // Check if email already exists (for new clubs or when email is changed)
		    // if ( $this->email_exists( $email, $club_id ) ) {
		        // return new WP_Error( 'email_exists', 'A club with this email address already exists.' );
		    // }

		    $fields = array(
		        'club_name'    => $club_name,
		        'club_address' => sanitize_textarea_field( $data['club_address'] ),
		        'postcode'     => sanitize_text_field( strtoupper($data['postcode']) ),
		        'country'      => sanitize_text_field( $data['country'] ),
		        'contact_name' => sanitize_text_field( $data['contact_name'] ),
		        'phone'        => sanitize_text_field( $data['phone'] ),
		        'email'        => $email,
		        'club_status'  => sanitize_key( $data['club_status'] ), // 'enabled' or 'disabled'
		    );

		    $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		    if ( $club_id > 0 ) {
		        // UPDATE
		        $where = array( 'id' => $club_id );
		        $where_format = array( '%d' );
		        $result = $this->db->update( $table, $fields, $where, $format, $where_format );

				$this->log_club_action( 'updated', $club_id, $data );

		        return $result !== false ? $club_id : false;
		    } else {
		        // INSERT NEW
		        $unique_id = generateUniqueId();
		        $fields['id'] = $unique_id;
		        $fields['created_at'] = current_time( 'mysql', true );

		        $format[] = '%d'; // for club_id
		        $format[] = '%s'; // Add %s for created_at

		        $result = $this->db->insert( $table, $fields, $format );

				$this->log_club_action( 'created', $unique_id, $data );

		        return $result !== false ? $unique_id : false;
		    }
		}

        /**
         * Delete a club.
         */
        public function delete_club( $club_id ) {
            $table = $this->get_table_name( 'clubs' );
            $where = array( 'id' => $club_id );
            $where_format = array( '%d' );
            return $this->db->delete( $table, $where, $where_format );

			$this->log_club_action( 'deleted', $club_id, array() );

        }

		// Example for clubs in class-ww-booking-clubs.php
		private function log_club_action( $action, $club_id, $data, $old_data = array() ) {
		    global $ww_booking_system;

		    if ( isset( $ww_booking_system->admin->logger ) ) {
		        $logger = $ww_booking_system->admin->logger;
		        $object_name = "Club: " . ( $data['club_name'] ?? 'Unknown' );

		        $logger->log_action(
		            $action,
		            'club',
		            $club_id,
		            $object_name,
		            $old_data,
		            $data
		        );
		    }
		}
    }
}