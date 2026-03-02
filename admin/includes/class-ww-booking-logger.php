<?php
/**
 * Logging Controller
 * Handles tracking and reporting of all edits and actions within the booking system.
 */

if ( ! class_exists( 'WW_Booking_Logger' ) ) {

    class WW_Booking_Logger {

        protected $db;
        protected $table_prefix;
        protected $log_table;

        public function __construct( $db, $table_prefix ) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
            $this->log_table = $this->db->prefix . $this->table_prefix . 'activity_logs';

            // Create log table if it doesn't exist
            $this->create_log_table();
        }

        /**
         * Create activity logs table
         */
        private function create_log_table() {
            $charset_collate = $this->db->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                object_type VARCHAR(100) NOT NULL,
                object_id BIGINT UNSIGNED NOT NULL,
                object_name VARCHAR(255) NULL,
                old_values LONGTEXT NULL,
                new_values LONGTEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY action_type (action_type),
                KEY object_type (object_type),
                KEY object_id (object_id),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        /**
         * Log an action
         */
        public function log_action( $action_type, $object_type, $object_id, $object_name = '', $old_values = array(), $new_values = array() ) {
            $current_user = wp_get_current_user();

            $log_data = array(
                'user_id'     => $current_user->ID,
                'user_name'   => $current_user->display_name ?: $current_user->user_login,
                'action_type' => sanitize_text_field( $action_type ),
                'object_type' => sanitize_text_field( $object_type ),
                'object_id'   => absint( $object_id ),
                'object_name' => sanitize_text_field( $object_name ),
                'old_values'  => ! empty( $old_values ) ? wp_json_encode( $old_values, JSON_PRETTY_PRINT ) : null,
                'new_values'  => ! empty( $new_values ) ? wp_json_encode( $new_values, JSON_PRETTY_PRINT ) : null,
                'ip_address'  => $this->get_client_ip(),
                'user_agent'  => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
                'created_at'  => current_time( 'mysql', true ),
            );

            return $this->db->insert( $this->log_table, $log_data, array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        }

        /**
         * Get client IP address
         */
        private function get_client_ip() {
            $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

            foreach ( $ip_keys as $key ) {
                if ( ! empty( $_SERVER[ $key ] ) ) {
                    $ip = $_SERVER[ $key ];
                    if ( strpos( $ip, ',' ) !== false ) {
                        $ips = explode( ',', $ip );
                        $ip = trim( $ips[0] );
                    }
                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return sanitize_text_field( $ip );
                    }
                }
            }

            return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
        }

        /**
         * Get logs with filters
         */
        public function get_logs( $args = array() ) {
            $defaults = array(
                'page'        => 1,
                'per_page'    => 50,
                'object_type' => '',
                'action_type' => '',
                'user_id'     => '',
                'date_from'   => '',
                'date_to'     => '',
                'search'      => '',
            );

            $args = wp_parse_args( $args, $defaults );

            $where = array( '1=1' );
            $prepare_values = array();

            // Build WHERE conditions
            if ( ! empty( $args['object_type'] ) ) {
                $where[] = 'object_type = %s';
                $prepare_values[] = $args['object_type'];
            }

            if ( ! empty( $args['action_type'] ) ) {
                $where[] = 'action_type = %s';
                $prepare_values[] = $args['action_type'];
            }

            if ( ! empty( $args['user_id'] ) ) {
                $where[] = 'user_id = %d';
                $prepare_values[] = absint( $args['user_id'] );
            }

            if ( ! empty( $args['date_from'] ) ) {
                $where[] = 'created_at >= %s';
                $prepare_values[] = sanitize_text_field( $args['date_from'] );
            }

            if ( ! empty( $args['date_to'] ) ) {
                $where[] = 'created_at <= %s';
                $prepare_values[] = sanitize_text_field( $args['date_to'] );
            }

            if ( ! empty( $args['search'] ) ) {
                $where[] = '(object_name LIKE %s OR user_name LIKE %s)';
                $search_term = '%' . $this->db->esc_like( $args['search'] ) . '%';
                $prepare_values[] = $search_term;
                $prepare_values[] = $search_term;
            }

            $where_sql = implode( ' AND ', $where );

            // Pagination
            $offset = ( $args['page'] - 1 ) * $args['per_page'];
            $limit = $this->db->prepare( 'LIMIT %d, %d', $offset, $args['per_page'] );

            // Main query
            $sql = $this->db->prepare(
                "SELECT * FROM {$this->log_table} WHERE {$where_sql} ORDER BY created_at DESC {$limit}",
                $prepare_values
            );

            $logs = $this->db->get_results( $sql, ARRAY_A );

            // Count query for pagination
            $count_sql = $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->log_table} WHERE {$where_sql}",
                $prepare_values
            );

            $total = $this->db->get_var( $count_sql );

            return array(
                'logs'  => $logs,
                'total' => $total,
                'pages' => ceil( $total / $args['per_page'] ),
            );
        }

        /**
         * Get available action types
         */
        public function get_action_types() {
            $sql = "SELECT DISTINCT action_type FROM {$this->log_table} ORDER BY action_type";
            return $this->db->get_col( $sql );
        }

        /**
         * Get available object types
         */
        public function get_object_types() {
            $sql = "SELECT DISTINCT object_type FROM {$this->log_table} ORDER BY object_type";
            return $this->db->get_col( $sql );
        }

        /**
         * Clean old logs (keep only last X days)
         */
        public function clean_old_logs( $days_to_keep = 90 ) {
            $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_to_keep} days" ) );
            $sql = $this->db->prepare( "DELETE FROM {$this->log_table} WHERE created_at < %s", $cutoff_date );
            return $this->db->query( $sql );
        }

        /**
         * Export logs to CSV
         */
        public function export_logs_to_csv( $args = array() ) {
            $logs_data = $this->get_logs( $args );
            $logs = $logs_data['logs'];

            if ( empty( $logs ) ) {
                return false;
            }

            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="activity-logs-' . date( 'Y-m-d' ) . '.csv"' );

            $output = fopen( 'php://output', 'w' );

            // Headers
            fputcsv( $output, array(
                'Date/Time',
                'User',
                'Action',
                'Object Type',
                'Object ID',
                'Object Name',
                'IP Address',
                'Changes Made'
            ) );

            // Data
            foreach ( $logs as $log ) {
                $changes = '';
                if ( $log['old_values'] && $log['new_values'] ) {
                    $old_data = json_decode( $log['old_values'], true );
                    $new_data = json_decode( $log['new_values'], true );

                    $changes_array = array();
                    foreach ( $new_data as $key => $value ) {
                        if ( ! isset( $old_data[ $key ] ) || $old_data[ $key ] !== $value ) {
                            $old_value = isset( $old_data[ $key ] ) ? $old_data[ $key ] : '(empty)';
                            $changes_array[] = "{$key}: {$old_value} → {$value}";
                        }
                    }
                    $changes = implode( '; ', $changes_array );
                }

                fputcsv( $output, array(
                    $log['created_at'],
                    $log['user_name'],
                    $log['action_type'],
                    $log['object_type'],
                    $log['object_id'],
                    $log['object_name'],
                    $log['ip_address'],
                    $changes
                ) );
            }

            fclose( $output );
            exit;
        }
    }
}
?>