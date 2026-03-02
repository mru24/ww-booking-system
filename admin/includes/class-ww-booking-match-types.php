<?php
/**
 * Match Types Functions
 * Handles CRUD operations for the 'match_types' table.
 */

if ( ! class_exists( 'WW_Booking_Match_Types' ) ) {

    class WW_Booking_Match_Types {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name() {
            return $this->db->prefix . $this->table_prefix . 'match_types';
        }

        /**
         * Fetch all match types.
         */
        public function get_types() {
            $table = $this->get_table_name();
            $sql = "SELECT * FROM {$table} ORDER BY type_name ASC";
            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Fetch one match type by ID.
         */
        public function get_type( $type_id ) {
            $table = $this->get_table_name();
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $type_id );
            return $this->db->get_row( $sql, ARRAY_A );
        }

        /**
         * Insert or update a match type.
         */
        public function save_type( $data, $type_id = 0 ) {
            $table  = $this->get_table_name();
            // Generate unique slug from name for the unique ID
            $type_name = sanitize_text_field( $data['type_name'] );
            $type_slug = sanitize_title( $type_name );
            // Check for required field
            if ( empty( $type_name ) ) {
                return false;
            }
            $fields = array(
                'type_name'   => $type_name,
                'type_slug'   => $type_slug,
                'description' => sanitize_textarea_field( $data['description'] ),
            );
            $format = array( '%s', '%s', '%s' );
            if ( $type_id > 0 ) {
            	// UPDATE
                $where = array( 'id' => $type_id );
                $where_format = array( '%d' );
                return $this->db->update( $table, $fields, $where, $format, $where_format );
            } else {
            	// INSERT NEW
            	$unique_id = generateUniqueId();
				$fields['id'] = $unique_id;
                $fields['created_at'] = current_time( 'mysql', true ); // Manual datetime stamp
                $format[] = '%d'; // for lake_id
                $format[] = '%s';
                return $this->db->insert( $table, $fields, $format );
            }
        }

        /**
         * Delete a match type.
         */
        public function delete_type( $type_id ) {
            $table = $this->get_table_name();
            $where = array( 'id' => $type_id );
            $where_format = array( '%d' );
            return $this->db->delete( $table, $where, $where_format );
        }
    }
}