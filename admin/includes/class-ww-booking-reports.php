<?php
/**
 * Reports Functions
 * Handles all reporting and analytics for the booking system.
 */

if ( ! class_exists( 'WW_Booking_Reports' ) ) {

    class WW_Booking_Reports {

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
 * Get booking statistics for a date range
 */
public function get_booking_stats( $start_date = '', $end_date = '' ) {
    $bookings_table = $this->get_table_name( 'bookings' );
    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
    $lakes_table = $this->get_table_name( 'lakes' );
    $clubs_table = $this->get_table_name( 'clubs' );

    // Default to current month if no dates provided
    if ( empty( $start_date ) ) {
        $start_date = date( 'Y-m-01' ); // First day of current month
    }
    if ( empty( $end_date ) ) {
        $end_date = date( 'Y-m-t' ); // Last day of current month
    }

    $stats = array();

    // Debug: Check what booking statuses exist in the database
    $debug_sql = "SELECT DISTINCT booking_status FROM {$bookings_table}";
    $debug_statuses = $this->db->get_results( $debug_sql, ARRAY_A );
    //error_log( 'Debug - Booking statuses in DB: ' . print_r( $debug_statuses, true ) );

    // Total bookings count - include ALL statuses for total count
    $sql = $this->db->prepare( "
        SELECT COUNT(*) as total_bookings,
               SUM(CASE WHEN booking_status = 'booked' THEN 1 ELSE 0 END) as booked_bookings,
               SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
               SUM(CASE WHEN booking_status = 'draft' THEN 1 ELSE 0 END) as draft_bookings,
               SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
        FROM {$bookings_table}
        WHERE date_start >= %s AND date_start <= %s
    ", $start_date, $end_date );

    $stats['booking_counts'] = $this->db->get_row( $sql, ARRAY_A );

    // Debug the results
    //error_log( 'Debug - Booking counts: ' . print_r( $stats['booking_counts'], true ) );

    // Total pegs booked - use 'booked' status (your actual status)
    $sql = $this->db->prepare( "
        SELECT COUNT(*) as total_pegs_booked
        FROM {$booking_pegs_table} bp
        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
        WHERE b.date_start >= %s AND b.date_start <= %s
        AND b.booking_status = 'booked'
    ", $start_date, $end_date );

    $stats['pegs_booked'] = $this->db->get_var( $sql );

    // Bookings by lake - use 'booked' status
    $sql = $this->db->prepare( "
        SELECT l.lake_name, COUNT(*) as booking_count
        FROM {$bookings_table} b
        INNER JOIN {$lakes_table} l ON b.lake_id = l.id
        WHERE b.date_start >= %s AND b.date_start <= %s
        AND b.booking_status = 'booked'
        GROUP BY b.lake_id
        ORDER BY booking_count DESC
    ", $start_date, $end_date );

    $stats['bookings_by_lake'] = $this->db->get_results( $sql, ARRAY_A );

    // Bookings by club - use 'booked' status
    $sql = $this->db->prepare( "
        SELECT c.club_name, COUNT(*) as booking_count
        FROM {$booking_pegs_table} bp
        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
        INNER JOIN {$clubs_table} c ON bp.club_id = c.id
        WHERE b.date_start >= %s AND b.date_start <= %s
        AND b.booking_status = 'booked'
        GROUP BY bp.club_id
        ORDER BY booking_count DESC
    ", $start_date, $end_date );

    $stats['bookings_by_club'] = $this->db->get_results( $sql, ARRAY_A );

    // Daily booking trends - use 'booked' status
    $sql = $this->db->prepare( "
        SELECT DATE(date_start) as booking_date, COUNT(*) as daily_bookings
        FROM {$bookings_table}
        WHERE date_start >= %s AND date_start <= %s
        AND booking_status = 'booked'
        GROUP BY DATE(date_start)
        ORDER BY booking_date ASC
    ", $start_date, $end_date );

    $stats['daily_trends'] = $this->db->get_results( $sql, ARRAY_A );

    return $stats;
}

/**
 * Get lake utilization report
 */
public function get_lake_utilization( $start_date = '', $end_date = '' ) {
    if ( empty( $start_date ) ) {
        $start_date = date( 'Y-m-01' );
    }
    if ( empty( $end_date ) ) {
        $end_date = date( 'Y-m-t' );
    }

    $lakes_table = $this->get_table_name( 'lakes' );
    $pegs_table = $this->get_table_name( 'pegs' );
    $bookings_table = $this->get_table_name( 'bookings' );
    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );

    // Get all lakes with their total pegs
    $sql = "
        SELECT l.id, l.lake_name,
               COUNT(p.id) as total_pegs,
               SUM(CASE WHEN p.peg_status = 'open' THEN 1 ELSE 0 END) as available_pegs
        FROM {$lakes_table} l
        LEFT JOIN {$pegs_table} p ON l.id = p.lake_id
        WHERE l.lake_status = 'enabled'
        GROUP BY l.id
    ";

    $lakes = $this->db->get_results( $sql, ARRAY_A );

    // Calculate booked pegs for each lake in the date range
    foreach ( $lakes as &$lake ) {
        $sql = $this->db->prepare( "
            SELECT COUNT(DISTINCT bp.peg_id) as booked_pegs
            FROM {$booking_pegs_table} bp
            INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
            WHERE b.lake_id = %d
            AND b.date_start >= %s AND b.date_start <= %s
            AND b.booking_status = 'booked'
        ", $lake['id'], $start_date, $end_date );

        $booked_pegs = $this->db->get_var( $sql );
        $lake['booked_pegs'] = $booked_pegs ?: 0;
        $lake['utilization_rate'] = $lake['available_pegs'] > 0 ?
            round( ( $lake['booked_pegs'] / $lake['available_pegs'] ) * 100, 2 ) : 0;
    }

    return $lakes;
}

/**
 * Get club activity report
 */
public function get_club_activity( $start_date = '', $end_date = '' ) {
    if ( empty( $start_date ) ) {
        $start_date = date( 'Y-m-01' );
    }
    if ( empty( $end_date ) ) {
        $end_date = date( 'Y-m-t' );
    }

    $clubs_table = $this->get_table_name( 'clubs' );
    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
    $bookings_table = $this->get_table_name( 'bookings' );
    $lakes_table = $this->get_table_name( 'lakes' );

    $sql = $this->db->prepare( "
        SELECT c.club_name,
               COUNT(bp.id) as total_bookings,
               COUNT(DISTINCT b.id) as unique_booking_sessions,
               GROUP_CONCAT(DISTINCT l.lake_name) as lakes_used,
               MIN(b.date_start) as first_booking,
               MAX(b.date_start) as last_booking
        FROM {$clubs_table} c
        INNER JOIN {$booking_pegs_table} bp ON c.id = bp.club_id
        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
        INNER JOIN {$lakes_table} l ON b.lake_id = l.id
        WHERE b.date_start >= %s AND b.date_start <= %s
        AND b.booking_status = 'booked'
        GROUP BY c.id
        ORDER BY total_bookings DESC
    ", $start_date, $end_date );

    return $this->db->get_results( $sql, ARRAY_A );
}

        /**
         * Get revenue report (if you add pricing later)
         */
        public function get_revenue_report( $start_date = '', $end_date = '' ) {
            // Placeholder for future revenue tracking
            // This would integrate with a pricing/payments system
            return array(
                'message' => 'Revenue tracking not yet implemented. Add pricing to bookings to enable this report.'
            );
        }

/**
 * Get popular match types
 */
public function get_popular_match_types( $start_date = '', $end_date = '' ) {
    if ( empty( $start_date ) ) {
        $start_date = date( 'Y-m-01' );
    }
    if ( empty( $end_date ) ) {
        $end_date = date( 'Y-m-t' );
    }

    $booking_pegs_table = $this->get_table_name( 'booking_pegs' );
    $bookings_table = $this->get_table_name( 'bookings' );

    $sql = $this->db->prepare( "
        SELECT match_type_slug,
               COUNT(*) as usage_count,
               COUNT(DISTINCT booking_id) as unique_bookings
        FROM {$booking_pegs_table} bp
        INNER JOIN {$bookings_table} b ON bp.booking_id = b.id
        WHERE b.date_start >= %s AND b.date_start <= %s
        AND b.booking_status = 'booked'
        GROUP BY match_type_slug
        ORDER BY usage_count DESC
    ", $start_date, $end_date );

    return $this->db->get_results( $sql, ARRAY_A );
}

        /**
         * Export report data to CSV
         */
        public function export_to_csv( $data, $filename = 'report' ) {
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '_' . date( 'Y-m-d' ) . '.csv"' );

            $output = fopen( 'php://output', 'w' );

            if ( ! empty( $data ) ) {
                // Output headers
                fputcsv( $output, array_keys( $data[0] ) );

                // Output data
                foreach ( $data as $row ) {
                    fputcsv( $output, $row );
                }
            }

            fclose( $output );
            exit;
        }
    }
}