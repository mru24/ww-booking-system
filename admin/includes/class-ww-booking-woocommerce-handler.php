<?php
/**
 * Enhanced WooCommerce Handler for WW Booking System
 * Includes clubs and pegs data in orders
 */

if (!class_exists('WW_Booking_Woocommerce_Handler')) {

    class WW_Booking_Woocommerce_Handler {
        
        protected $db;
        protected $table_prefix;
        protected $bookings;
        protected $clubs;
        protected $pegs;
        
        public function __construct($db, $table_prefix, $bookings_instance, $clubs_instance = null, $pegs_instance = null) {
            $this->db = $db;
            $this->table_prefix = $table_prefix;
            $this->bookings = $bookings_instance;
            $this->clubs = $clubs_instance;
            $this->pegs = $pegs_instance;
            
            // Register AJAX handler
            add_action('wp_ajax_send_booking_to_woocommerce', array($this, 'handle_send_to_woocommerce'));
            
            // Add booking data to order emails
            add_filter('woocommerce_email_order_items_args', array($this, 'add_booking_data_to_emails'));
            
            // Display booking data in admin order view
            add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_booking_data_in_admin'));
            
            // Add booking data to order item meta (visible in admin)
            add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_booking_data_to_line_item'), 10, 4);
            
            // Enqueue scripts
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
        
        /**
         * Enqueue admin scripts
         */
        public function enqueue_scripts($hook) {
            if ($hook === 'my-booking_page_my-booking-bookings' || $hook === 'post.php') {
                wp_enqueue_script(
                    'ww-booking-woo-sync',
                    plugin_dir_url(__FILE__) . '../js/woocommerce-sync.js',
                    array('jquery'),
                    '1.0',
                    true
                );
                
                // Pass data to JavaScript
                wp_localize_script('ww-booking-woo-sync', 'wwBookingWoo', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ww_booking_woo_nonce')
                ));
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
            
            // Get booking details with all related data
            $booking = $this->bookings->get_booking_with_details($booking_id);
            
            if (!$booking) {
                wp_send_json_error(array('message' => 'Booking not found'));
            }
            
            // Check if order already exists
            if (!empty($booking['order_id'])) {
                wp_send_json_error(array('message' => 'Order already exists for this booking'));
            }
            
            // Create WooCommerce order with enhanced data
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
         * Create WooCommerce order from booking with clubs and pegs data
         */
        protected function create_order_from_booking($booking) {
            try {
                // Create order
                $order = wc_create_order();
                
                // Calculate price (customize this based on your needs)
                $price = $this->calculate_booking_price($booking);
                
                // Get or create product
                $product_id = $this->get_or_create_booking_product($booking);
                
                // Add product to order with detailed meta
                $item_id = $order->add_product(
                    wc_get_product($product_id), 
                    1, 
                    array(
                        'subtotal' => $price,
                        'total' => $price,
                    )
                );
                
                // Get the order item object
                $item = $order->get_item($item_id);
                
                // Add booking metadata to order item (shows in order details)
                $item->add_meta_data('_booking_id', $booking['id']);
                $item->add_meta_data('_booking_lake', $booking['lake_name']);
                $item->add_meta_data('_booking_dates', $booking['date_start'] . ' to ' . $booking['date_end']);
                
                // Add clubs and pegs data to order meta
                $this->add_clubs_and_pegs_to_order($order, $booking, $item);
                
                // Add booking metadata to order (for quick reference)
                $order->update_meta_data('_ww_booking_id', $booking['id']);
                $order->update_meta_data('_ww_booking_details', $this->format_booking_details($booking));
                $order->update_meta_data('_ww_booking_lake', $booking['lake_name']);
                $order->update_meta_data('_ww_booking_dates', $booking['date_start'] . ' to ' . $booking['date_end']);
                $order->update_meta_data('_ww_booking_clubs_count', count($this->get_unique_clubs_from_booking($booking)));
                $order->update_meta_data('_ww_booking_pegs_count', count($booking['pegs']));
                
                // Calculate totals
                $order->calculate_totals();
                $order->save();
                
                // Link booking to order in database
                $this->link_booking_to_order($booking['id'], $order->get_id());
                
                // Update booking status
                $this->update_booking_status($booking['id'], 'booked');
                
                return array(
                    'success' => true,
                    'order_id' => $order->get_id()
                );
                
            } catch (Exception $e) {
                error_log('WooCommerce order creation failed: ' . $e->getMessage());
                return array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
            }
        }
        
        /**
         * Add clubs and pegs data to order with proper formatting
         */
        protected function add_clubs_and_pegs_to_order($order, $booking, $order_item) {
            if (empty($booking['pegs'])) {
                return;
            }
            
            $clubs_data = array();
            $pegs_list = array();
            $clubs_summary = array();
            
            foreach ($booking['pegs'] as $index => $peg) {
                // Get club details
                $club_id = $peg['club_id'] ?? 0;
                $club_name = $peg['club_name'] ?? 'Unknown Club';
                
                // Build peg data
                $peg_data = array(
                    'peg_name' => $peg['peg_name'],
                    'peg_id' => $peg['peg_id'],
                    'club_id' => $club_id,
                    'club_name' => $club_name,
                    'match_type' => $peg['match_type_slug'] ?? 'Unknown'
                );
                
                // Add to pegs list
                $pegs_list[] = $peg_data;
                
                // Group by club for summary
                if (!isset($clubs_summary[$club_id])) {
                    $clubs_summary[$club_id] = array(
                        'club_id' => $club_id,
                        'club_name' => $club_name,
                        'pegs' => array()
                    );
                    
                    // Store club data separately
                    $clubs_data[] = array(
                        'club_id' => $club_id,
                        'club_name' => $club_name,
                        'contact' => $peg['club_contact'] ?? '',
                        'phone' => $peg['club_phone'] ?? '',
                        'email' => $peg['club_email'] ?? ''
                    );
                }
                
                $clubs_summary[$club_id]['pegs'][] = $peg_data;
            }
            
            // Add to order meta (serialized but readable)
            $order->update_meta_data('_ww_booking_pegs', $pegs_list);
            $order->update_meta_data('_ww_booking_clubs', $clubs_data);
            $order->update_meta_data('_ww_booking_clubs_summary', $clubs_summary);
            
            // Add human-readable summary to order item
            $order_item->add_meta_data('Booking Summary', $this->format_booking_summary($booking, $clubs_summary));
            
            // Add individual peg data as line item meta (visible in admin)
            foreach ($pegs_list as $index => $peg) {
                $order_item->add_meta_data(
                    sprintf('Peg %d', $index + 1),
                    sprintf('%s - Club: %s (%s)', 
                        $peg['peg_name'], 
                        $peg['club_name'],
                        $peg['match_type']
                    )
                );
            }
        }
        
        /**
         * Format booking summary for display
         */
        protected function format_booking_summary($booking, $clubs_summary) {
            $summary = "Booking #{$booking['id']}\n";
            $summary .= "Lake: {$booking['lake_name']}\n";
            $summary .= "Dates: {$booking['date_start']} to {$booking['date_end']}\n";
            $summary .= "Total Pegs: " . count($booking['pegs']) . "\n\n";
            $summary .= "Clubs & Pegs:\n";
            
            foreach ($clubs_summary as $club) {
                $summary .= "• {$club['club_name']}:\n";
                foreach ($club['pegs'] as $peg) {
                    $summary .= "  - Peg {$peg['peg_name']} ({$peg['match_type']})\n";
                }
            }
            
            return $summary;
        }
        
        /**
         * Get unique clubs from booking
         */
        protected function get_unique_clubs_from_booking($booking) {
            $clubs = array();
            
            if (!empty($booking['pegs'])) {
                foreach ($booking['pegs'] as $peg) {
                    $club_id = $peg['club_id'] ?? 0;
                    if ($club_id && !isset($clubs[$club_id])) {
                        $clubs[$club_id] = array(
                            'id' => $club_id,
                            'name' => $peg['club_name'] ?? 'Unknown',
                            'contact' => $peg['club_contact'] ?? '',
                            'phone' => $peg['club_phone'] ?? ''
                        );
                    }
                }
            }
            
            return $clubs;
        }
        
        /**
         * Calculate booking price with club-based pricing (customize this!)
         */
        protected function calculate_booking_price($booking) {
            $total = 0;
            
            // Example pricing structure:
            // - Base price per peg per day
            // - Different prices for different clubs
            // - Match type surcharges
            
            $days = (strtotime($booking['date_end']) - strtotime($booking['date_start'])) / DAY_IN_SECONDS + 1;
            $pegs_count = count($booking['pegs']);
            
            // Get pricing from settings or subscriptions
            $price_per_peg_per_day = get_option('ww_booking_price_per_peg', 10); // Default £10
            
            // Calculate base price
            $total = $pegs_count * $days * $price_per_peg_per_day;
            
            // Add club-specific pricing if needed
            if (!empty($booking['pegs'])) {
                $unique_clubs = $this->get_unique_clubs_from_booking($booking);
                
                // Example: Discount for multiple clubs? Surcharge for certain clubs?
                if (count($unique_clubs) > 1) {
                    // Add multi-club surcharge?
                    $total *= 1.1; // 10% surcharge for multiple clubs
                }
            }
            
            return round($total, 2);
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
            
            // Create new product with detailed description
            $product = new WC_Product_Simple();
            $product->set_name('Lake Booking: ' . $booking['lake_name']);
            $product->set_status('publish');
            $product->set_catalog_visibility('hidden'); // Hide from shop
            $product->set_virtual(true); // No shipping needed
            $product->set_downloadable(false);
            $product->set_price(0); // Price set at order level
            $product->set_regular_price(0);
            
            // Create detailed description
            $description = "Booking for {$booking['lake_name']}\n";
            $description .= "Date Range: {$booking['date_start']} to {$booking['date_end']}\n";
            $description .= "This is a booking product created automatically by the WW Booking System.\n";
            $description .= "Details about clubs and pegs will appear in the order metadata.";
            
            $product->set_description($description);
            $product->set_short_description("Lake booking for {$booking['lake_name']}");
            
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
                'lake' => array(
                    'id' => $booking['lake_id'],
                    'name' => $booking['lake_name']
                ),
                'dates' => array(
                    'start' => $booking['date_start'],
                    'end' => $booking['date_end'],
                    'days' => (strtotime($booking['date_end']) - strtotime($booking['date_start'])) / DAY_IN_SECONDS + 1
                ),
                'status' => $booking['booking_status'],
                'pegs' => array(),
                'clubs' => array()
            );
            
            if (!empty($booking['pegs'])) {
                $club_ids = array();
                
                foreach ($booking['pegs'] as $peg) {
                    // Add peg details
                    $peg_detail = array(
                        'peg_id' => $peg['peg_id'],
                        'peg_name' => $peg['peg_name'],
                        'match_type' => $peg['match_type_slug'],
                        'club_id' => $peg['club_id'],
                        'club_name' => $peg['club_name']
                    );
                    
                    $details['pegs'][] = $peg_detail;
                    
                    // Track unique clubs
                    if (!in_array($peg['club_id'], $club_ids)) {
                        $club_ids[] = $peg['club_id'];
                        $details['clubs'][] = array(
                            'id' => $peg['club_id'],
                            'name' => $peg['club_name']
                        );
                    }
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
        
        /**
         * Add booking data to order emails
         */
        public function add_booking_data_to_emails($args) {
            $order = $args['order'];
            
            if ($order && $order->get_meta('_ww_booking_id')) {
                add_action('woocommerce_email_order_meta', array($this, 'display_booking_data_in_email'), 10, 3);
            }
            
            return $args;
        }
        
        /**
         * Display booking data in admin order view
         */
        public function display_booking_data_in_admin($order) {
            $booking_id = $order->get_meta('_ww_booking_id');
            
            if (!$booking_id) {
                return;
            }
            
            $booking_details = $order->get_meta('_ww_booking_details');
            $clubs = $order->get_meta('_ww_booking_clubs');
            $pegs = $order->get_meta('_ww_booking_pegs');
            
            ?>
            <div class="order_data_column" style="clear:both; padding-top:20px; width:100%;">
                <h3><?php _e('Booking Details', 'ww-booking-system'); ?></h3>
                
                <div class="address">
                    <p>
                        <strong><?php _e('Booking ID:', 'ww-booking-system'); ?></strong>
                        <a href="<?php echo admin_url('admin.php?page=my-booking-edit-booking&booking_id=' . $booking_id); ?>">
                            #<?php echo $booking_id; ?>
                        </a>
                    </p>
                    
                    <?php if ($booking_details): ?>
                        <p>
                            <strong><?php _e('Lake:', 'ww-booking-system'); ?></strong>
                            <?php echo $booking_details['lake']['name']; ?>
                        </p>
                        <p>
                            <strong><?php _e('Dates:', 'ww-booking-system'); ?></strong>
                            <?php echo $booking_details['dates']['start']; ?> to <?php echo $booking_details['dates']['end']; ?>
                            (<?php echo $booking_details['dates']['days']; ?> days)
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($clubs)): ?>
                        <p><strong><?php _e('Clubs:', 'ww-booking-system'); ?></strong></p>
                        <ul style="margin-left:20px;">
                            <?php foreach ($clubs as $club): ?>
                                <li>
                                    <?php echo $club['club_name']; ?>
                                    <?php if (!empty($club['contact'])): ?>
                                        <br><small>Contact: <?php echo $club['contact']; ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($club['phone'])): ?>
                                        <br><small>Phone: <?php echo $club['phone']; ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($pegs)): ?>
                        <p><strong><?php _e('Pegs:', 'ww-booking-system'); ?></strong></p>
                        <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                            <thead>
                                <tr style="background:#f8f8f8;">
                                    <th style="padding:8px; border:1px solid #ddd;width:32%;">Peg</th>
                                    <th style="padding:8px; border:1px solid #ddd;width:32%;">Club</th>
                                    <th style="padding:8px; border:1px solid #ddd;width:32%;">Match Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pegs as $peg): ?>
                                    <tr>
                                        <td style="padding:8px; border:1px solid #ddd;"><?php echo $peg['peg_name']; ?></td>
                                        <td style="padding:8px; border:1px solid #ddd;"><?php echo $peg['club_name']; ?></td>
                                        <td style="padding:8px; border:1px solid #ddd;"><?php echo $peg['match_type']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        
        /**
         * Display booking data in emails
         */
        public function display_booking_data_in_email($order, $sent_to_admin, $plain_text) {
            $booking_id = $order->get_meta('_ww_booking_id');
            
            if (!$booking_id) {
                return;
            }
            
            $booking_details = $order->get_meta('_ww_booking_details');
            $pegs = $order->get_meta('_ww_booking_pegs');
            
            if ($plain_text) {
                // Plain text email
                echo "\n\n========== BOOKING DETAILS ==========\n";
                echo "Booking ID: #{$booking_id}\n";
                
                if ($booking_details) {
                    echo "Lake: {$booking_details['lake']['name']}\n";
                    echo "Dates: {$booking_details['dates']['start']} to {$booking_details['dates']['end']}\n";
                }
                
                if (!empty($pegs)) {
                    echo "\nPegs:\n";
                    foreach ($pegs as $peg) {
                        echo "- Peg {$peg['peg_name']} - Club: {$peg['club_name']} ({$peg['match_type']})\n";
                    }
                }
                echo "====================================\n\n";
            } else {
                // HTML email
                ?>
                <div style="margin-bottom: 40px;">
                    <h2><?php _e('Booking Details', 'ww-booking-system'); ?></h2>
                    
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:12px; background:#f8f8f8; border:1px solid #ddd;">
                                <strong>Booking ID:</strong> #<?php echo $booking_id; ?>
                            </td>
                            <?php if ($booking_details): ?>
                                <td style="padding:12px; background:#f8f8f8; border:1px solid #ddd;">
                                    <strong>Lake:</strong> <?php echo $booking_details['lake']['name']; ?>
                                </td>
                                <td style="padding:12px; background:#f8f8f8; border:1px solid #ddd;">
                                    <strong>Dates:</strong> <?php echo $booking_details['dates']['start']; ?> to <?php echo $booking_details['dates']['end']; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    </table>
                    
                    <?php if (!empty($pegs)): ?>
                        <h3 style="margin-top:20px;">Pegs & Clubs</h3>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f8f8f8;">
                                    <th style="padding:12px; border:1px solid #ddd;">Peg</th>
                                    <th style="padding:12px; border:1px solid #ddd;">Club</th>
                                    <th style="padding:12px; border:1px solid #ddd;">Match Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pegs as $peg): ?>
                                    <tr>
                                        <td style="padding:12px; border:1px solid #ddd;"><?php echo $peg['peg_name']; ?></td>
                                        <td style="padding:12px; border:1px solid #ddd;"><?php echo $peg['club_name']; ?></td>
                                        <td style="padding:12px; border:1px solid #ddd;"><?php echo $peg['match_type']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php
            }
        }
        
        /**
         * Add booking data to line item during checkout
         */
        public function add_booking_data_to_line_item($item, $cart_item_key, $values, $order) {
            // This will be populated when creating the order from booking
            if (isset($values['ww_booking_data'])) {
                $item->add_meta_data('_ww_booking_data', $values['ww_booking_data']);
            }
        }
    }
}