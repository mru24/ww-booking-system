<?php
/**
 * Lakes Functions
 * Handles CRUD operations for the 'lakes' table.
 */

if ( ! class_exists( 'WW_Booking_Lakes' ) ) {

    class WW_Booking_Lakes {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name = 'lakes' ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Fetch all lakes.
         */
        public function get_lakes() {
            $table = $this->get_table_name();
            $sql = "SELECT * FROM {$table} ORDER BY lake_name ASC";
            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Fetch one lake by ID.
         */
        public function get_lake( $lake_id ) {
            $table = $this->get_table_name();
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $lake_id );
            return $this->db->get_row( $sql, ARRAY_A );
        }

        /**
         * Insert or update a lake. Does NOT handle pegs (Pegs class does that).
         * Returns the ID of the inserted/updated lake.
         */
        public function save_lake( $data, $lake_id = 0 ) {
            $table  = $this->get_table_name( 'lakes' );
            $fields = array(
                'lake_name' => sanitize_text_field( $data['lake_name'] ),
                'lake_status' => sanitize_key( $data['lake_status'] ),
                'lake_image_id' => absint( $data['lake_image_id'] ),
                'lake_image_visibility' => sanitize_key( $data['lake_image_visibility'] ),
                'description' => wp_kses_post($_POST['description'])
            );
            $format = array( '%s', '%s', '%d', '%s', '%s' ); // name, status, image_id, visibility, description

            if ( $lake_id > 0 ) {
            	// UPDATE
                $where = array( 'id' => $lake_id );
                $where_format = array( '%d' );
                $this->db->update( $table, $fields, $where, $format, $where_format );
                return $lake_id;
            } else {
            	// INSERT
            	$unique_id = generateUniqueId();
				$fields['id'] = $unique_id;
        		$fields['created_at'] = current_time( 'mysql', true );

				$format[] = '%d'; // for lake_id
                $format[] = '%s'; // for created_at
                $this->db->insert( $table, $fields, $format );
                return $fields['id'];
            }
        }

        /**
         * Delete a lake.
         */
        public function delete_lake( $lake_id ) {
            $table = $this->get_table_name( 'lakes' );
            $where = array( 'id' => $lake_id );
            $where_format = array( '%d' );
            return $this->db->delete( $table, $where, $where_format );
        }


		/**
		 * Fetch all active (enabled) lakes for the frontend.
		 * @return array List of enabled lakes.
		 */
		public function get_active_lakes() {
		    $table = $this->get_table_name();
		    $sql = $this->db->prepare(
		        "SELECT id, lake_name FROM {$table} WHERE lake_status = %s ORDER BY lake_name ASC",
		        'enabled' // Only fetch lakes with the status 'enabled'
		    );
		    // Returning results as an array of objects for simpler iteration
		    return $this->db->get_results( $sql );
		}
    }
}