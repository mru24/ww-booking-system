<?php
/**
 * Holidays Functions
 * Handles CRUD operations for holidays
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WW_Booking_Holidays' ) ) {

    class WW_Booking_Holidays {

        protected $db;
        protected $table_prefix;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
        }

        /**
         * Helper: get full table name
         */
        protected function get_table_name( $name = 'holidays' ) {
            return $this->db->prefix . $this->table_prefix . $name;
        }

        /**
         * Get all holidays
         */
        public function get_holidays() {
            $table = $this->get_table_name();
            return $this->db->get_results(
                "SELECT * FROM {$table} ORDER BY start_date ASC",
                ARRAY_A
            );
        }

        /**
         * Get holiday by ID with associated lakes
         */
        public function get_holiday( $holiday_id ) {
            $table = $this->get_table_name();
            $holiday = $this->db->get_row(
                $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $holiday_id ),
                ARRAY_A
            );

            if ( $holiday ) {
                $holiday['lakes'] = $this->get_holiday_lakes( $holiday_id );
            }

            return $holiday;
        }

        /**
         * Get lakes associated with a holiday
         */
        public function get_holiday_lakes( $holiday_id ) {
            $table = $this->get_table_name( 'holiday_lakes' );
            $lakes_table = $this->get_table_name( 'lakes' );

            return $this->db->get_results(
                $this->db->prepare( "
                    SELECT hl.lake_id, l.lake_name
                    FROM {$table} hl
                    INNER JOIN {$lakes_table} l ON hl.lake_id = l.id
                    WHERE hl.holiday_id = %d
                    ORDER BY l.lake_name ASC
                ", $holiday_id ),
                ARRAY_A
            );
        }

        /**
         * Check if a date is a holiday for a specific lake
         */
        public function is_holiday( $date, $lake_id = null ) {
            $table = $this->get_table_name();
            $holiday_lakes_table = $this->get_table_name( 'holiday_lakes' );

            // Base query for holidays
            $query = "
                SELECT h.id
                FROM {$table} h
                WHERE %s BETWEEN h.start_date AND h.end_date
            ";

            $params = array( $date );

            // Add lake-specific conditions
            if ( $lake_id !== null ) {
                $query .= " AND (
                    h.applies_to = 'all_lakes'
                    OR (
                        h.applies_to = 'specific_lakes'
                        AND EXISTS (
                            SELECT 1 FROM {$holiday_lakes_table} hl
                            WHERE hl.holiday_id = h.id AND hl.lake_id = %d
                        )
                    )
                )";
                $params[] = $lake_id;
            }

            $count = $this->db->get_var(
                $this->db->prepare( $query, $params )
            );

            return $count > 0;
        }

        /**
         * Get holidays for a specific year and lake
         */
        public function get_holidays_for_year( $year, $lake_id = null ) {
            $table = $this->get_table_name();
            $holiday_lakes_table = $this->get_table_name( 'holiday_lakes' );

            $query = "
                SELECT DISTINCT h.*
                FROM {$table} h
                WHERE (YEAR(h.start_date) = %d OR h.holiday_type = 'annual')
            ";

            $params = array( $year );

            if ( $lake_id !== null ) {
                $query .= " AND (
                    h.applies_to = 'all_lakes'
                    OR (
                        h.applies_to = 'specific_lakes'
                        AND EXISTS (
                            SELECT 1 FROM {$holiday_lakes_table} hl
                            WHERE hl.holiday_id = h.id AND hl.lake_id = %d
                        )
                    )
                )";
                $params[] = $lake_id;
            }

            $query .= " ORDER BY h.start_date ASC";

            return $this->db->get_results(
                $this->db->prepare( $query, $params ),
                ARRAY_A
            );
        }

        /**
         * Get holiday ranges for frontend calendar
         */
        public function get_holiday_ranges_for_year( $year, $lake_id = null ) {
            $holidays = $this->get_holidays_for_year( $year, $lake_id );
            $ranges = [];

            foreach ( $holidays as $holiday ) {
                if ( $holiday['holiday_type'] === 'annual' ) {
                    // For annual holidays, use the current year with the same month/day
                    $start_date = $year . '-' . date( 'm-d', strtotime( $holiday['start_date'] ) );
                    $end_date = $year . '-' . date( 'm-d', strtotime( $holiday['end_date'] ) );
                } else {
                    // For one-time holidays, use the exact dates
                    $start_date = $holiday['start_date'];
                    $end_date = $holiday['end_date'];
                }

                $ranges[] = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'name' => $holiday['holiday_name'],
                    'type' => $holiday['holiday_type'],
                    'applies_to' => $holiday['applies_to']
                ];
            }

            return $ranges;
        }

        /**
         * Save holiday
         */
        public function save_holiday( $data, $holiday_id = 0 ) {
            $table = $this->get_table_name();
            $holiday_lakes_table = $this->get_table_name( 'holiday_lakes' );

            // Validate dates
            if ( empty( $data['start_date'] ) || empty( $data['end_date'] ) ) {
                return false;
            }

            // Ensure end_date is not before start_date
            if ( strtotime( $data['end_date'] ) < strtotime( $data['start_date'] ) ) {
                $data['end_date'] = $data['start_date'];
            }

            $holiday_data = array(
                'holiday_name' => sanitize_text_field( $data['holiday_name'] ),
                'start_date' => sanitize_text_field( $data['start_date'] ),
                'end_date' => sanitize_text_field( $data['end_date'] ),
                'holiday_type' => sanitize_key( $data['holiday_type'] ),
                'applies_to' => sanitize_key( $data['applies_to'] ),
                'description' => sanitize_textarea_field( $data['description'] ?? '' ),
                'created_at' => current_time( 'mysql', true ),
            );

            $this->db->query( 'START TRANSACTION' );

            try {
                if ( $holiday_id > 0 ) {
                    // Update existing
                    $result = $this->db->update(
                        $table,
                        $holiday_data,
                        array( 'id' => $holiday_id ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                        array( '%d' )
                    );
                } else {
                    // Insert new
                    $holiday_data['id'] = $this->generate_holiday_id();
                    $result = $this->db->insert(
                        $table,
                        $holiday_data,
                        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                    );
                    $holiday_id = $holiday_data['id'];
                }

                if ( $result === false ) {
                    throw new Exception( 'Failed to save holiday' );
                }

                // Save lake associations if applies to specific lakes
                if ( $holiday_data['applies_to'] === 'specific_lakes' && isset( $data['lakes'] ) ) {
                    // Delete existing associations
                    $this->db->delete(
                        $holiday_lakes_table,
                        array( 'holiday_id' => $holiday_id ),
                        array( '%d' )
                    );

                    // Insert new associations
                    foreach ( $data['lakes'] as $lake_id ) {
                        $lake_assoc = array(
                            'holiday_id' => $holiday_id,
                            'lake_id' => absint( $lake_id ),
                            'created_at' => current_time( 'mysql', true ),
                        );
                        $this->db->insert(
                            $holiday_lakes_table,
                            $lake_assoc,
                            array( '%d', '%d', '%s' )
                        );
                    }
                } elseif ( $holiday_data['applies_to'] === 'all_lakes' ) {
                    // Remove any specific lake associations
                    $this->db->delete(
                        $holiday_lakes_table,
                        array( 'holiday_id' => $holiday_id ),
                        array( '%d' )
                    );
                }

                $this->db->query( 'COMMIT' );
                return $holiday_id;

            } catch ( Exception $e ) {
                $this->db->query( 'ROLLBACK' );
                error_log( 'Holiday save failed: ' . $e->getMessage() );
                return false;
            }
        }

        /**
         * Generate unique holiday ID
         */
        protected function generate_holiday_id() {
            return generateUniqueId();
        }

        /**
         * Delete holiday
         */
        public function delete_holiday( $holiday_id ) {
            $table = $this->get_table_name();
            $holiday_lakes_table = $this->get_table_name( 'holiday_lakes' );

            $this->db->query( 'START TRANSACTION' );

            try {
                // Delete lake associations first
                $this->db->delete(
                    $holiday_lakes_table,
                    array( 'holiday_id' => $holiday_id ),
                    array( '%d' )
                );

                // Delete holiday
                $result = $this->db->delete(
                    $table,
                    array( 'id' => $holiday_id ),
                    array( '%d' )
                );

                $this->db->query( 'COMMIT' );
                return $result;

            } catch ( Exception $e ) {
                $this->db->query( 'ROLLBACK' );
                error_log( 'Holiday deletion failed: ' . $e->getMessage() );
                return false;
            }
        }

/**
 * Get all available lakes for selection
 */
public function get_available_lakes() {
    $lakes_table = $this->get_table_name( 'lakes' );
    return $this->db->get_results(
        "SELECT id, lake_name FROM {$lakes_table} WHERE lake_status = 'enabled' ORDER BY lake_name ASC",
        ARRAY_A
    );
}
    }
}
?>