<?php
/**
 * Pegs Functions
 * Handles CRUD operations for the 'pegs' table, linked to 'lakes'.
 */

if ( ! class_exists( 'WW_Booking_Pegs' ) ) {

    class WW_Booking_Pegs {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name = 'pegs' ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Fetch all pegs for a single lake.
         * Now fetches the new 'peg_id' field automatically via SELECT *.
         */
        public function get_pegs_by_lake_id( $lake_id ) {
            $table = $this->get_table_name();
            $sql = $this->db->prepare( "SELECT * FROM {$table} WHERE lake_id = %d ORDER BY peg_name ASC", $lake_id );
            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Bulk saves pegs (update or insert). Deletes any existing pegs not in the current list.
         */
        public function save_pegs( $lake_id, $peg_data ) {
            $lake_id = absint( $lake_id );
            if ( $lake_id === 0 ) {
                return false;
            }

            $table = $this->get_table_name();
            $existing_peg_ids_map = array(); // Map [internal_id => unique_peg_id_slug]
            $existing_peg_ids = array();
            $current_peg_ids = array();

            // Format for INSERT: lake_id, peg_name, peg_id, peg_status, created_at
            $insert_format = array( '%d', '%s', '%d', '%s', '%s' );

            // Format for UPDATE: lake_id, peg_name, peg_id, peg_status
            $update_format = array( '%d', '%s', '%s' );

            // 1. Get existing pegs and create map
            $existing_pegs = $this->get_pegs_by_lake_id( $lake_id );
            if ( $existing_pegs ) {
                // Collect existing internal IDs for deletion logic
                $existing_peg_ids = wp_list_pluck( $existing_pegs, 'id' );

                // Create map to look up the unique 'peg_id' slug for updates
                foreach ( $existing_pegs as $peg ) {
                    $existing_peg_ids_map[ $peg['id'] ] = $peg['id'];
                }
            }

            // 2. Insert/Update pegs
            if ( ! empty( $peg_data ) && is_array( $peg_data ) ) {
                foreach ( $peg_data as $peg ) {
                    $internal_id = absint( $peg['id'] ); // The auto-incrementing 'id'
                    $peg_name = sanitize_text_field( $peg['peg_name'] );
                    $peg_status = sanitize_key( $peg['peg_status'] );

                    if ( empty( $peg_name ) ) {
                        continue; // Skip pegs without a name
                    }

                    // --- UNIQUE PEG ID LOGIC ---
                    $unique_peg_id_slug = '';

                    if ( $internal_id > 0 ) {
                        // A. UPDATE: Retrieve the existing unique peg_id slug
                        if ( isset( $existing_peg_ids_map[ $internal_id ] ) ) {
                            $unique_peg_id_slug = $existing_peg_ids_map[ $internal_id ];
                        }
                    }

                    // B. INSERT or missing ID: Generate a new unique peg_id slug
                    if ( empty( $unique_peg_id_slug ) ) {
                         // Generates a slug like 'lake-1-peg-a'
                        $unique_peg_id_slug = sanitize_title( 'lake-' . $lake_id . '-' . $peg_name );
                    }
                    // --- END UNIQUE PEG ID LOGIC ---


                    $data_to_save = array(
                        'lake_id'      => $lake_id,
                        'peg_name'     => $peg_name,
                        'peg_status'   => $peg_status,
                    );

                    if ( $internal_id > 0 ) {
                        // Update existing peg
                        $this->db->update(
                            $table,
                            $data_to_save,
                            array( 'id' => $internal_id ),
                            $update_format, // Uses the 4 item format
                            array( '%d' )
                        );
                        $current_peg_ids[] = $internal_id;
                    } else {
                        // Insert new peg
                        $unique_id = generateUniqueId();
						$data_to_save['id'] = $unique_id;
                        $data_to_save['created_at'] = current_time( 'mysql', true ); // Manual datetime stamp
                        $this->db->insert(
                            $table,
                            $data_to_save,
                            $insert_format // Uses the 5 item format
                        );
                        $current_peg_ids[] = $this->db->insert_id;
                    }
                }
            }

            // 3. Delete old pegs (those that existed but were not in the submitted list)
            $pegs_to_delete = array_diff( $existing_peg_ids, $current_peg_ids );
            if ( ! empty( $pegs_to_delete ) ) {
                $ids_in = implode( ',', array_map( 'absint', $pegs_to_delete ) );
                $sql = "DELETE FROM {$table} WHERE id IN ({$ids_in}) AND lake_id = %d";
                $this->db->query( $this->db->prepare( $sql, $lake_id ) );
            }

            return true;
        }

        /**
         * Delete all pegs associated with a lake ID. Used when deleting the lake itself.
         */
        public function delete_pegs_by_lake_id( $lake_id ) {
            $table = $this->get_table_name( 'pegs' );
            $where = array( 'lake_id' => absint( $lake_id ) );
            $where_format = array( '%d' );
            return $this->db->delete( $table, $where, $where_format );
        }
    }
}