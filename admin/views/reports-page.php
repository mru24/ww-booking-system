<?php
/**
 * Admin View: Reports Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$report_types = array(
    'overview' => 'Booking Overview',
    'lake_utilization' => 'Lake Utilization',
    'club_activity' => 'Club Activity',
    'match_types' => 'Popular Match Types',
    'revenue' => 'Revenue Report'
);
?>
<div class="wrap">
    <h1>Booking Reports & Analytics</h1>

    <!-- Report Filters -->
    <div class="report-filters" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
        <form method="get">
            <input type="hidden" name="page" value="my-booking-reports">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="report_type">Report Type</label></th>
                    <td>
                        <select name="report_type" id="report_type">
                            <?php foreach ( $report_types as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $report_type, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="start_date">Start Date</label></th>
                    <td>
                        <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="end_date">End Date</label></th>
                    <td>
                        <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="Generate Report">
                <?php if ( ! empty( $report_data ) && is_array( $report_data ) ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'export', 'csv' ) ); ?>" class="button button-secondary">
                        Export to CSV
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Report Display -->
    <div class="report-results">
        <?php if ( $report_type === 'overview' ) : ?>
            <?php $this->render_overview_report( $report_data ); ?>
        <?php elseif ( $report_type === 'lake_utilization' ) : ?>
            <?php $this->render_lake_utilization_report( $report_data ); ?>
        <?php elseif ( $report_type === 'club_activity' ) : ?>
            <?php $this->render_club_activity_report( $report_data ); ?>
        <?php elseif ( $report_type === 'match_types' ) : ?>
            <?php $this->render_match_types_report( $report_data ); ?>
        <?php elseif ( $report_type === 'revenue' ) : ?>
            <?php $this->render_revenue_report( $report_data ); ?>
        <?php endif; ?>
    </div>
</div>

<style>
.report-stats { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
.stat-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 200px; flex: 1; }
.stat-number { font-size: 2em; font-weight: bold; color: #2271b1; }
.stat-label { color: #666; margin-top: 5px; }
.utilization-high { color: #d63638; }
.utilization-medium { color: #dba617; }
.utilization-low { color: #00a32a; }
</style>