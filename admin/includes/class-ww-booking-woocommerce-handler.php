<?php
/**
 * Handle sending bookings to WooCommerce
 */

if (!class_exists('WW_Booking_Woocommerce_Handler')) {

    class WW_Booking_Woocommerce_Handler {
        
        protected $db;
        protected $table_prefix;
        protected $bookings;
        
        public function __construct($db, $table_prefix, $bookings_instance) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
            $this->bookings = $bookings_instance;
            
            // Register AJAX handler
            add_action('wp_ajax_send_booking_to_woocommerce', array($this, 'handle_send_to_woocommerce'));
            
            // Enqueue script
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
        
        /**
         * Enqueue admin scripts
         */
        public function enqueue_scripts($hook) {
            if ($hook === 'my-booking_page_my-booking-bookings') {
                wp_enqueue_script(
                    'ww-booking-woo-sync',
                    plugin_dir_url(__FILE__) . '../js/woocommerce-sync.js',
                    array('jquery'),
                    '1.0',
                    true
                );
            }
        }
        
        /**
         * Handle AJAX request to send booking to WooCommerce
         */
        public function handle_send_to_woocommerce() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'send_booking_to_woocommerce')) {
                wp_send_json_error(array('message' => 'Security check failed'));
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
            }
            
            $booking_id = intval($_POST['booking_id']);
            
            if (!$booking_id) {
                wp_send_json_error(array('message' => 'Invalid booking ID'));
            }
            
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                wp_send_json_error(array('message' => 'WooCommerce is not active'));
            }
            
            // Get booking details
            $booking = $this->bookings->get_booking_with_details($booking_id);
            
            if (!$booking) {
                wp_send_json_error(array('message' => 'Booking not found'));
            }
            
            // Check if order already exists
            if (!empty($booking['order_id'])) {
                wp_send_json_error(array('message' => 'Order already exists for this booking'));
            }
            
            // Create WooCommerce order
            $result = $this->create_order_from_booking($booking);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'order_id' => $result['order_id'],
                    'order_edit_url' => admin_url('post.php?post=' . $result['order_id'] . '&action=edit')
                ));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        }
        
        /**
         * Create WooCommerce order from booking
         */
        protected function create_order_from_booking($booking) {
            try {
                // Create order
                $order = wc_create_order();
                
                // Calculate price (customize this based on your needs)
                $price = $this->calculate_booking_price($booking);
                
                // Get or create product
                $product_id = $this->get_or_create_booking_product($booking);
                
                // Add product to order
                $item_id = $order->add_product(
                    wc_get_product($product_id), 
                    1, 
                    array(
                        'subtotal' => $price,
                        'total' => $price,
                    )
                );
                
                // Add booking metadata to order
                $order->update_meta_data('_ww_booking_id', $booking['id']);
                $order->update_meta_data('_ww_booking_details', $this->format_booking_details($booking));
                $order->update_meta_data('_ww_booking_lake', $booking['lake_name']);
                $order->update_meta_data('_ww_booking_dates', 
                    $booking['date_start'] . ' to ' . $booking['date_end']
                );
                
                // If you have customer data, add it
                // $order->set_billing_email($customer_email);
                // $order->set_billing_first_name($first_name);
                // $order->set_billing_last_name($last_name);
                
                // Calculate totals
                $order->calculate_totals();
                $order->save();
                
                // Link booking to order in database
                $this->link_booking_to_order($booking['id'], $order->get_id());
                
                // Optional: Update booking status
                $this->update_booking_status($booking['id'], 'booked');
                
                return array(
                    'success' => true,
                    'order_id' => $order->get_id()
                );
                
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        /**
         * Calculate booking price (customize this!)
         */
        protected function calculate_booking_price($booking) {
            $total = 0;
            
            // Example pricing: £10 per peg per day
            $days = (strtotime($booking['date_end']) - strtotime($booking['date_start'])) / DAY_IN_SECONDS + 1;
            $pegs_count = count($booking['pegs']);
            
            // You can get this from a setting or subscription
            $price_per_peg_per_day = 10; // £10
            
            $total = $pegs_count * $days * $price_per_peg_per_day;
            
            return $total;
        }
        
        /**
         * Get or create a product for this booking
         */
        protected function get_or_create_booking_product($booking) {
            // Try to get existing product for this lake
            $product_id = get_option('ww_booking_product_lake_' . $booking['lake_id']);
            
            if ($product_id && wc_get_product($product_id)) {
                return $product_id;
            }
            
            // Create new product
            $product = new WC_Product_Simple();
            $product->set_name('Lake Booking: ' . $booking['lake_name']);
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden'); // Hide from shop
            $product->set_virtual(true); // No shipping needed
            $product->set_downloadable(false);
            $product->set_price(0); // Price set at order level
            $product->set_regular_price(0);
            
            // Add description
            $product->set_description('Booking for ' . $booking['lake_name'] . 
                                      ' from ' . $booking['date_start'] . 
                                      ' to ' . $booking['date_end']);
            
            $product_id = $product->save();
            
            // Save for future use
            update_option('ww_booking_product_lake_' . $booking['lake_id'], $product_id);
            
            return $product_id;
        }
        
        /**
         * Format booking details for order meta
         */
        protected function format_booking_details($booking) {
            $details = array(
                'lake' => $booking['lake_name'],
                'start_date' => $booking['date_start'],
                'end_date' => $booking['date_end'],
                'pegs' => array()
            );
            
            if (!empty($booking['pegs'])) {
                foreach ($booking['pegs'] as $peg) {
                    $details['pegs'][] = array(
                        'peg_name' => $peg['peg_name'],
                        'club' => $peg['club_name'] ?? 'Unknown',
                        'match_type' => $peg['match_type_slug'] ?? 'Unknown'
                    );
                }
            }
            
            return $details;
        }
        
        /**
         * Link booking to order in database
         */
        protected function link_booking_to_order($booking_id, $order_id) {
            $table = $this->db->prefix . $this->table_prefix . 'bookings';
            
            $this->db->update(
                $table,
                array(
                    'order_id' => $order_id,
                    'order_created' => current_time('mysql', true)
                ),
                array('id' => $booking_id),
                array('%d', '%s'),
                array('%d')
            );
        }
        
        /**
         * Update booking status
         */
        protected function update_booking_status($booking_id, $status) {
            $table = $this->db->prefix . $this->table_prefix . 'bookings';
            
            $this->db->update(
                $table,
                array('booking_status' => $status),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
        }
    }
}