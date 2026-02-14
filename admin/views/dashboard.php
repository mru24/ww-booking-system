<?php
/**
 * Admin View: Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current month stats
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$stats = $this->reports->get_booking_stats($current_month_start, $current_month_end);

// Get lake utilization
$lake_utilization = $this->reports->get_lake_utilization($current_month_start, $current_month_end);

// Get recent bookings (last 7 days)
$recent_start = date('Y-m-d', strtotime('-7 days'));
$recent_end = date('Y-m-t');
$recent_bookings = $this->bookings->get_all_bookings_with_details();

// Filter recent bookings
$recent_bookings = array_filter($recent_bookings, function($booking) use ($recent_start) {
    return strtotime($booking['created_at']) >= strtotime($recent_start);
});

// Limit to 5 most recent
$recent_bookings = array_slice($recent_bookings, 0, 5);

$booking_counts = $stats['booking_counts'] ?? array();
?>

<div class="wrap">
    <h1>Booking Dashboard</h1>

    <div class="dashboard-widgets">
        <!-- Quick Stats -->
        <div class="dashboard-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($clubs_count); ?></div>
                <div class="stat-label">Total Clubs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($lakes_count); ?></div>
                <div class="stat-label">Total Lakes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($bookings_count); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($booking_counts['total_bookings'] ?? 0); ?></div>
                <div class="stat-label">This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($booking_counts['booked_bookings'] ?? 0); ?></div>
                <div class="stat-label">Booked</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo esc_html($stats['pegs_booked'] ?? 0); ?></div>
                <div class="stat-label">Pegs Booked</div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="dashboard-main">
            <!-- Left Column -->
            <div class="dashboard-column">
                <!-- Lake Utilization -->
                <!--<div class="dashboard-widget">
                    <div class="widget-header">
                        <h3>Lake Utilization (This Month)</h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-reports&report_type=lake_utilization')); ?>" class="button button-small">View Full Report</a>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($lake_utilization)) : ?>
                            <table class="widefat fixed">
                                <thead>
                                    <tr>
                                        <th>Lake</th>
                                        <th>Utilization</th>
                                        <th>Booked/Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lake_utilization as $lake) : ?>
                                        <tr>
                                            <td><?php echo esc_html($lake['lake_name']); ?></td>
                                            <td>
                                                <div class="utilization-bar-container">
                                                    <div class="utilization-bar" style="width: <?php echo esc_attr(min($lake['utilization_rate'], 100)); ?>%;
                                                        background-color: <?php
                                                            echo $lake['utilization_rate'] > 80 ? '#d63638' :
                                                                ($lake['utilization_rate'] > 50 ? '#dba617' : '#00a32a');
                                                        ?>;">
                                                    </div>
                                                    <span class="utilization-text"><?php echo esc_html($lake['utilization_rate']); ?>%</span>
                                                </div>
                                            </td>
                                            <td><?php echo esc_html($lake['booked_pegs']); ?>/<?php echo esc_html($lake['available_pegs']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No lake utilization data available.</p>
                        <?php endif; ?>
                    </div>
                </div>-->

                <!-- Recent Bookings -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3>Recent Bookings (Last 7 Days)</h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-bookings')); ?>" class="button button-small">View All</a>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($recent_bookings)) : ?>
                            <table class="widefat fixed">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Lake</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking) : ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-edit-booking&booking_id=' . $booking['id'])); ?>">
                                                    #<?php echo esc_html($booking['id']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html($booking['lake_name']); ?></td>
                                            <td>
                                                <?php echo esc_html(date('M j', strtotime($booking['date_start']))); ?> -
                                                <?php echo esc_html(date('M j', strtotime($booking['date_end']))); ?>
                                            </td>
                                            <td>
                                                <span class="booking-status status-<?php echo esc_attr($booking['booking_status']); ?>">
                                                    <?php echo esc_html(ucfirst($booking['booking_status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No recent bookings.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="dashboard-column">
                <!-- Quick Actions -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="widget-content">
                        <div class="quick-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-new-booking')); ?>" class="button button-primary button-large">
                                Create New Booking
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-bookings')); ?>" class="button button-large">
                                Manage Bookings
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-lakes')); ?>" class="button button-large">
                                Manage Lakes
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-clubs')); ?>" class="button button-large">
                                Manage Clubs
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-booking-reports')); ?>" class="button button-large">
                                View Full Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bookings by Status -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3>Bookings Overview (This Month)</h3>
                    </div>
                    <div class="widget-content">
                        <div class="status-breakdown">
                            <div class="status-item">
                                <span class="status-dot status-booked"></span>
                                <span class="status-label">Booked:</span>
                                <span class="status-count"><?php echo esc_html($booking_counts['booked_bookings'] ?? 0); ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-dot status-confirmed"></span>
                                <span class="status-label">Confirmed:</span>
                                <span class="status-count"><?php echo esc_html($booking_counts['confirmed_bookings'] ?? 0); ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-dot status-draft"></span>
                                <span class="status-label">Draft:</span>
                                <span class="status-count"><?php echo esc_html($booking_counts['draft_bookings'] ?? 0); ?></span>
                            </div>
                            <div class="status-item">
                                <span class="status-dot status-cancelled"></span>
                                <span class="status-label">Cancelled:</span>
                                <span class="status-count"><?php echo esc_html($booking_counts['cancelled_bookings'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Info -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3>System Information</h3>
                    </div>
                    <div class="widget-content">
                        <div class="system-info">
                            <div class="info-item">
                                <span class="info-label">Current Month:</span>
                                <span class="info-value"><?php echo esc_html(date('F Y')); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date Range:</span>
                                <span class="info-value"><?php echo esc_html(date('M j', strtotime($current_month_start))); ?> - <?php echo esc_html(date('M j', strtotime($current_month_end))); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Pegs Booked:</span>
                                <span class="info-value"><?php echo esc_html($stats['pegs_booked'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-widgets { margin: 20px 0; }
.dashboard-row { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
.dashboard-main { display: flex; gap: 20px; }
.dashboard-column { flex: 1; display: flex; flex-direction: column; gap: 20px; }
.dashboard-widget { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
.widget-header { padding: 15px 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; }
.widget-header h3 { margin: 0; }
.widget-content { padding: 20px; }

.stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    min-width: 150px;
    flex: 1;
    text-align: center;
}
.stat-number { font-size: 2em; font-weight: bold; color: #2271b1; line-height: 1; }
.stat-label { color: #666; margin-top: 5px; font-size: 0.9em; }

.utilization-bar-container {
    position: relative;
    background: #f0f0f1;
    height: 24px;
    border-radius: 12px;
    overflow: hidden;
}
.utilization-bar {
    height: 100%;
    transition: width 0.3s ease;
    border-radius: 12px;
}
.utilization-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.8em;
    font-weight: bold;
    color: #000;
    text-shadow: 1px 1px 0 #fff;
}

.quick-actions { display: flex; flex-direction: column; gap: 10px; }
.quick-actions .button { text-align: center; }

.status-breakdown { display: flex; flex-direction: column; gap: 10px; }
.status-item { display: flex; align-items: center; gap: 10px; }
.status-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
.status-booked { background-color: #65a2d3; }
.status-confirmed { background-color: #19dd16; }
.status-draft { background-color: #fff3cd; }
.status-cancelled { background-color: #f8d7da; }
.status-label { flex: 1; }
.status-count { font-weight: bold; }

.system-info { display: flex; flex-direction: column; gap: 8px; }
.info-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f0f0f1; }
.info-item:last-child { border-bottom: none; }
.info-label { font-weight: 500; }
.info-value { color: #666; }

.booking-status {
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
    font-size: 0.85em;
}
.status-booked { background-color: #65a2d3; color: #d7e7f4; }
.status-confirmed { background-color: #19dd16; color: #d7e7f4; }
.status-draft { background-color: #fff3cd; color: #856404; }
.status-cancelled { background-color: #f8d7da; color: #721c24; }

@media (max-width: 1200px) {
    .dashboard-main { flex-direction: column; }
}
</style>