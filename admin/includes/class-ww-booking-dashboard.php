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
         * Insert or update a club.
         */
        public function save_club( $data, $club_id = 0 ) {
            $table  = $this->get_table_name( 'clubs' );

            $fields = array(
                'club_name'    => sanitize_text_field( $data['club_name'] ),
                'club_address' => sanitize_textarea_field( $data['club_address'] ),
                'postcode'     => sanitize_text_field( $data['postcode'] ),
                'country'      => sanitize_text_field( $data['country'] ),
                'contact_name' => sanitize_text_field( $data['contact_name'] ),
                'phone'        => sanitize_text_field( $data['phone'] ),
                'email'        => sanitize_email( $data['email'] ),
                'club_status'  => sanitize_key( $data['club_status'] ), // 'enabled' or 'disabled'
            );

            // Using all %s for simplicity, except for club_status which is a key
            $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

            if ( $club_id > 0 ) {
            	// UPDATE
                $where = array( 'id' => $club_id );
                $where_format = array( '%d' );
                return $this->db->update( $table, $fields, $where, $format, $where_format );
            } else {
            	// INSERT NEW
            	$unique_id = generateUniqueId();
				$fields['id'] = $unique_id;
        		$fields['created_at'] = current_time( 'mysql', true );

				$format[] = '%d'; // for club_id
                $format[] = '%s'; // Add %s for created_at
                return $this->db->insert( $table, $fields, $format );
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
        }
    }
}