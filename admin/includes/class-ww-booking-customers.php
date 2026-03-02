<?php
/**
 * Customer Functions Class: Handles CRUD and data retrieval for customers.
 */

if ( ! class_exists( 'WW_Booking_Customers' ) ) {

    class WW_Booking_Customers {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        protected function get_table_name() {
            return $this->db->prefix . $this->table_prefix . 'customers';
        }

        public function get_all() {
            $table = $this->get_table_name();
            $sql = "SELECT * FROM {$table} ORDER BY last_name ASC";
            return $this->db->get_results( $sql, ARRAY_A );
        }

        public function get( $customer_id ) {
            $table = $this->get_table_name();
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $customer_id );
            return $this->db->get_row( $sql, ARRAY_A );
        }

        public function insert( $data ) {
            $table = $this->get_table_name();
            $format = array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s' );
            return $this->db->insert( $table, $data, $format );
        }

        public function update( $id, $data ) {
            $table = $this->get_table_name();
            $format = array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' );
            return $this->db->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
        }

        public function delete( $id ) {
            $table = $this->get_table_name();
            return $this->db->delete( $table, array( 'id' => $id ), array( '%d' ) );
        }
    }
}
