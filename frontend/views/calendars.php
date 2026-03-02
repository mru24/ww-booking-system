<?php
$lakeId =  esc_js( $selected_lake_id );
$calendar_id = 'ww-calendar-' . uniqid(); // Generate unique ID for each calendar
?>

<div class="ww-booking-calendar" id="<?php echo $calendar_id; ?>" data-calendar-id="<?php echo $calendar_id; ?>">

    <!-- Lake Selection -->
	<?php if(isset($lakeId) && !empty($lakeId)) : ?>
	<input type="hidden" class="ww-preselected-lake-id" value="<?php echo $lakeId ?>" />
	<?php else : ?>
    <div class="ww-lake-selector">
        <!-- <label for="ww-lake-select">Select Lake:</label> -->
        <select class="ww-lake-select">
            <option value="">Select a lake</option>
            <?php if (!empty($lakes)): ?>
                <?php foreach ($lakes as $lake): ?>
                    <option value="<?php echo esc_attr($lake->id); ?>">
                        <?php echo esc_html($lake->lake_name); ?>
                    </option>
                <?php endforeach; ?>
            <?php else: ?>
                <option value="">No lakes available</option>
            <?php endif; ?>
        </select>
    </div>
    <?php endif; ?>

	<div class="ww-calendar-header">
		<div class="ww-current-lake-name"></div>
		<div class="ww-calendar-legend">
			<p>
				<span class="dot club_match"></span> Club Match
				<span class="dot open_match"></span> Open Match
				<span class="dot league_match"></span> League Match
			</p>
		</div>
	    <div class="ww-calendar-nav">
	        <button class="ww-prev-month ww-nav-btn">
	        	<svg viewBox="0 0 640 640" xmlns="http://www.w3.org/2000/svg">
  					<style>.st0 { fill: #649B64; }</style>
				  	<path class="st0" d="M320,96c123.7,0,224,100.3,224,224S443.7,544,320,544S96,443.7,96,320S196.3,96,320,96z M320,576
				    c141.4,0,256-114.6,256-256S461.4,64,320,64S64,178.6,64,320S178.6,576,320,576z M228.7,308.7c-6.2,6.2-6.2,16.4,0,22.6l72,72
				    c6.2,6.2,16.4,6.2,22.6,0c6.2-6.2,6.2-16.4,0-22.6L278.6,336H400c8.8,0,16-7.2,16-16s-7.2-16-16-16H278.6l44.7-44.7
				    c6.2-6.2,6.2-16.4,0-22.6c-6.2-6.2-16.4-6.2-22.6,0L228.7,308.7z"/>
				</svg>
	        </button>
	        <span class="ww-current-month"><?php echo date('F Y'); ?></span>
	        <button class="ww-next-month ww-nav-btn">
	        	<svg viewBox="0 0 640 640" xmlns="http://www.w3.org/2000/svg">
  					<style>.st0{fill:#649B64;}</style>
  					<path class="st0" d="M320,96c123.7,0,224,100.3,224,224S443.7,544,320,544S96,443.7,96,320S196.3,96,320,96z M320,576
  c141.4,0,256-114.6,256-256S461.4,64,320,64S64,178.6,64,320S178.6,576,320,576z M411.3,331.3c6.2-6.2,6.2-16.4,0-22.6l-72-72
  c-6.2-6.2-16.4-6.2-22.6,0c-6.2,6.2-6.2,16.4,0,22.6l44.7,44.7H240c-8.8,0-16,7.2-16,16s7.2,16,16,16h121.4l-44.7,44.7
  c-6.2,6.2-6.2,16.4,0,22.6c6.2,6.2,16.4,6.2,22.6,0L411.3,331.3z"/>
				</svg>
			</button>
	    </div>
	</div>


    <!-- Calendar Grid -->
    <div class="ww-calendar-grid">
        <!-- Calendar will be populated via JavaScript -->
    </div>

    <!-- Booking Modal -->
    <div class="ww-booking-modal ww-modal" style="display: none;">
        <div class="ww-modal-content">
            <span class="ww-close-modal">&times;</span>
            <h3>Book Pegs for <span class="ww-booking-date"></span></h3>

            <div class="ww-peg-selection">
                <div class="ww-peg-list">
                    <!-- Table will be populated here -->
                </div>
            </div>

            <div class="ww-booking-actions">
                <button class="ww-submit-booking ww-btn-primary">Confirm Booking</button>
                <button class="ww-cancel-booking ww-btn-secondary">Cancel</button>
            </div>

            <div class="ww-booking-help">
                <p><small>✓ Check the pegs you want to book</small></p>
                <p><small>✓ Select match type and club for each selected peg</small></p>
                <p><small>✓ Only checked pegs will be booked</small></p>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="ww-loading" style="display: none;">
    <div class="ww-spinner"></div>
</div>

<style>
	th.ww-match-type .ww-form-control.ww-match-type-select.header-select,
	th.ww-club .ww-form-control.ww-club-select.header-select {
		width: 70%;
	}
	th.ww-match-type button,
	th.ww-club button {
		width: 25%;
	    background: #e5e5e5;
	    padding: 7px 5px 8px;
	    margin: 0 4px;
	    border-radius: 6px;
	}
</style>