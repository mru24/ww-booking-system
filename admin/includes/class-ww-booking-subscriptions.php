<?php
/**
 * Subscriptions Functions
 * Handles CRUD operations for the 'subscriptions' table.
 */

if ( ! class_exists( 'WW_Booking_Subscriptions' ) ) {

    class WW_Booking_Subscriptions {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Fetch all subscriptions.
         */
        public function get_subscriptions() {
            $table = $this->get_table_name( 'subscriptions' );
            $sql = "SELECT * FROM {$table} ORDER BY created_at DESC";
            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Fetch one subscription by ID.
         */
        public function get_subscription( $subscription_id ) {
            $table = $this->get_table_name( 'subscriptions' );
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $subscription_id );
            return $this->db->get_row( $sql, ARRAY_A );
        }

        /**
         * Insert or update a subscription.
         */
        public function save_subscription( $data, $subscription_id = 0 ) {
            $table  = $this->get_table_name( 'subscriptions' );
            $format = array( '%s', '%s', '%s', '%s', '%s', '%f' );

            $fields = array(
                'plan_name' => sanitize_text_field( $data['plan_name'] ),
                'plan_id' => sanitize_text_field( sanitize_title($data['plan_name']) ),
                'status' => sanitize_text_field( $data['status'] ),
                'tenure' => sanitize_text_field( $data['tenure'] ),
                'payment_gateway_id' => sanitize_text_field( $data['payment_gateway_id'] ),
                'price' => floatval( $data['price'] ),
            );

            if ( $subscription_id > 0 ) {
                $where = array( 'id' => $subscription_id );
                $where_format = array( '%d' );
                return $this->db->update( $table, $fields, $where, $format, $where_format );
            } else {
                return $this->db->insert( $table, $fields, $format );
            }
        }

        /**
         * Delete a subscription.
         */
        public function delete_subscription( $subscription_id ) {
            $table = $this->get_table_name( 'subscriptions' );
            return $this->db->delete( $table, array( 'id' => $subscription_id ), array( '%d' ) );
        }

    }
}
?>
