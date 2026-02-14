jQuery(document).ready(function($) {
    // Calendar instances manager
    const WWCalendarManager = {
        instances: {},

        init: function(container) {
            const calendarId = $(container).attr('id') || 'calendar-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            this.instances[calendarId] = new WWCalendar(container, calendarId);
            return this.instances[calendarId];
        },

        get: function(calendarId) {
            return this.instances[calendarId];
        }
    };

    // Individual calendar class
    class WWCalendar {
        constructor(container, calendarId) {
            this.container = $(container);
            this.calendarId = calendarId;
            this.currentDate = new Date();
            this.lakeId = this.container.find('.ww-preselected-lake-id').val() || '';
            this.selectedLake = this.lakeId;
            this.selectedDate = '';
            this.matchTypes = [];
            this.clubs = [];
            this.maxMonthsAhead = 2;
            this.holidaysCache = {}; // Cache for holidays by year

            // console.log('Initializing calendar:', this.calendarId, 'with lake:', this.selectedLake);
            this.init();
        }

        init() {
            this.initializeBookingData();
            this.renderCalendar();
            this.setupEventListeners();
        }

        // Get scoped selectors within this calendar instance
        getSelector(selector) {
            return this.container.find(selector);
        }

// In the getHolidaysForYear method, add lake_id parameter
getHolidaysForYear(year, lakeId = null) {
    const self = this;

    // Return cached holidays if available
    const cacheKey = lakeId ? `${year}-${lakeId}` : year;
    if (this.holidaysCache[cacheKey]) {
        return Promise.resolve(this.holidaysCache[cacheKey]);
    }

    return new Promise((resolve) => {
        const data = { year: year };
        if (lakeId) {
            data.lake_id = lakeId;
        }

        $.ajax({
            url: wwBooking.rest_url + 'ww-booking/v1/holidays',
            method: 'GET',
            data: data,
            timeout: 5000,
            success: function(response) {
                const holidays = response.holidays || [];
                // Cache the results
                self.holidaysCache[cacheKey] = holidays;
                resolve(holidays);
            },
            error: function(error) {
                console.error('Error loading holidays from API for year ' + year + ' and lake ' + lakeId + ':', error);
                // Fallback to static holidays
                const staticHolidays = self.getStaticHolidaysForYear(year);
                self.holidaysCache[cacheKey] = staticHolidays;
                resolve(staticHolidays);
            }
        });
    });
}

        // Fallback static holidays method
        getStaticHolidaysForYear(year) {
            const staticHolidays = [
                { date: `${year}-12-25`, name: 'Christmas Day' },
                { date: `${year}-12-26`, name: 'Boxing Day' },
                { date: `${year}-01-01`, name: 'New Years Day' },
                { date: `${year}-01-02`, name: 'New Years Holiday' },
            ];
            // console.log('Using static holidays for ' + year);
            return staticHolidays;
        }

		// Check if date is within any holiday range
		checkDateSpecial(date, holidays) {
		    const dateObj = new Date(date);
		    const dayOfWeek = dateObj.getDay(); // 0 = Sunday, 6 = Saturday

		    // Check if it's a weekend
		    const isWeekend = (dayOfWeek === 0 || dayOfWeek === 6);

		    // Check if it's within any holiday range
		    const isHoliday = holidays.some(holiday => {
		        const holidayStart = new Date(holiday.start_date);
		        const holidayEnd = new Date(holiday.end_date);
		        const currentDate = new Date(date);

		        return currentDate >= holidayStart && currentDate <= holidayEnd;
		    });

		    return {
		        isWeekend: isWeekend,
		        isHoliday: isHoliday,
		        dayOfWeek: dayOfWeek
		    };
		}

        // Initialize booking data
        initializeBookingData() {
            const self = this;

            function loadMatchTypes() {
                return $.ajax({
                    url: wwBooking.rest_url + 'ww-booking/v1/match-types',
                    method: 'GET',
                    success: function(response) {
                        self.matchTypes = response;
                        // console.log('Match types loaded for', self.calendarId + ':', self.matchTypes);
                    },
                    error: function(xhr) {
                        console.error('Error loading match types for ' + self.calendarId + ':', xhr.responseJSON);
                        self.matchTypes = [
                            { id: 1, type_slug: 'match', type_name: 'Match' },
                            { id: 2, type_slug: 'pleasure', type_name: 'Pleasure' },
                            { id: 3, type_slug: 'competition', type_name: 'Competition' }
                        ];
                    }
                });
            }

            function loadClubs() {
                return $.ajax({
                    url: wwBooking.rest_url + 'ww-booking/v1/clubs',
                    method: 'GET',
                    success: function(response) {
                        self.clubs = response;
                        // console.log('Clubs loaded for', self.calendarId + ':', self.clubs);
                    },
                    error: function(xhr) {
                        console.error('Error loading clubs for ' + self.calendarId + ':', xhr.responseJSON);
                        self.clubs = [];
                    }
                });
            }

            $.when(loadMatchTypes(), loadClubs()).then(function() {
                // console.log('All booking data loaded successfully for:', self.calendarId);
            }).fail(function() {
                console.error('Some booking data failed to load for:', self.calendarId);
            });
        }

        // Check if navigation to next month is allowed
        canNavigateToNextMonth() {
            const current = new Date(this.currentDate);
            const nextMonth = new Date(current.getFullYear(), current.getMonth() + 1, 1);
            const maxAllowed = new Date();
            maxAllowed.setMonth(maxAllowed.getMonth() + this.maxMonthsAhead);

            return nextMonth <= maxAllowed;
        }

        // Render calendar for current month
renderCalendar() {
    const self = this;
    const year = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();

    this.getSelector('.ww-current-month').text(new Date(year, month).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric'
    }));

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    const startDate = this.formatDate(firstDay);
    const endDate = this.formatDate(lastDay);

    let calendarHTML = '';

    // Day headers
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    dayNames.forEach(day => {
        calendarHTML += `<div class="ww-calendar-day-header">${day}</div>`;
    });

    // Empty cells for days before first day of month
    for (let i = 0; i < firstDay.getDay(); i++) {
        calendarHTML += `<div class="ww-calendar-day other-month"></div>`;
    }

    // Days of the month
    const daysInMonth = lastDay.getDate();
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = this.formatDate(new Date(year, month, day));

        calendarHTML += `
            <div class="ww-calendar-day" data-date="${dateStr}">
                <div class="ww-day-header">
                    <div class="ww-day-number">${day}</div>
                    <div class="ww-day-booking-status"></div>
                </div>
                <div class="ww-availability-info">
                </div>
            </div>
        `;
    }

    this.getSelector('.ww-calendar-grid').html(calendarHTML);

    // Load holidays and apply styles - pass the selected lake ID
    this.getHolidaysForYear(year, this.selectedLake).then(function(holidays) {
        self.applyDateStyles(year, month, holidays);

        // Load availability if lake is selected
        if (self.selectedLake) {
            self.loadMonthAvailability(startDate, endDate);
        }
    });
}

        // Apply holiday and weekend styles
applyDateStyles(year, month, holidays) {
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = this.formatDate(new Date(year, month, day));
        const dateInfo = this.checkDateSpecial(dateStr, holidays);

        const dayElement = this.getSelector(`.ww-calendar-day[data-date="${dateStr}"]`);

        // Build CSS classes
        let specialClasses = '';
        if (dateInfo.isWeekend) {
            specialClasses += ' weekend';
            if (dateInfo.dayOfWeek === 0) specialClasses += ' sunday';
            if (dateInfo.dayOfWeek === 6) specialClasses += ' saturday';
        }
        if (dateInfo.isHoliday) {
            specialClasses += ' holiday';
        }

        dayElement.addClass(specialClasses);

        // Add holiday indicator
        if (dateInfo.isHoliday) {
            dayElement.find('.ww-day-number').append('<span class="ww-holiday-indicator">🎄</span>');
        }
    }
}

        // Date formatting function
        formatDate(date) {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Load availability for the month
        loadMonthAvailability(startDate, endDate) {
            if (!this.selectedLake) return;

            this.showLoading(true);

            // Load lake name
            $.ajax({
                url: wwBooking.rest_url + 'ww-booking/v1/lake_name',
                method: 'GET',
                data: {
                    lake_id: this.selectedLake,
                },
                success: (response) => {
                    this.displayLakeName(response);
                },
                error: (xhr, status, error) => {
                    console.error('Lake name AJAX error for ' + this.calendarId + ':', error);
                }
            });

            // Load availability data
            $.ajax({
                url: wwBooking.rest_url + 'ww-booking/v1/availability',
                method: 'GET',
                data: {
                    lake_id: this.selectedLake,
                    start: startDate,
                    end: endDate
                },
                success: (response) => {
                    this.updateCalendarAvailability(response);
                },
                error: (xhr, status, error) => {
                    console.error('Availability AJAX error for ' + this.calendarId + ':', error);
                    let errorMessage = 'Error loading availability data.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage += ' ' + xhr.responseJSON.message;
                    }
                    alert(errorMessage);
                },
                complete: () => {
                    this.showLoading(false);
                }
            });
        }

        // Update calendar with availability data
        updateCalendarAvailability(availabilityData) {
            this.getSelector('.ww-calendar-day').each(function() {
                const date = $(this).data('date');
                const dayData = availabilityData.find(item => item.date === date);

                // Check if it's a holiday
                const isHoliday = $(this).hasClass('holiday');

                if (dayData) {
                    const statusClass = dayData.status;
                    const statusText = dayData.status.replace('-', ' ');
                    let matchTypeHTML = '';
                    let club_matchType = 0;
                    let open_matchType = 0;
                    let league_matchType = 0;

                    dayData.pegs.forEach(peg => {
                        if(peg.booking_details) {
                            if(peg.booking_details.match_type_slug==='club-match') club_matchType++;
                            if(peg.booking_details.match_type_slug==='open-match') open_matchType++;
                            if(peg.booking_details.match_type_slug==='league-match') league_matchType++;
                        }
                    });

                    if(club_matchType>0) matchTypeHTML+=`<span class="dot club_match">${club_matchType}</span>`;
                    if(open_matchType>0) matchTypeHTML+=`<span class="dot open_match">${open_matchType}</span>`;
                    if(league_matchType>0) matchTypeHTML+=`<span class="dot league_match">${league_matchType}</span>`;

                    $(this).find('.ww-day-booking-status').html(matchTypeHTML);

                    // Override status if it's a holiday
                    let finalStatus = statusClass;
                    let finalStatusClass = statusClass;

                    if (isHoliday) {
                        finalStatus = 'holiday';
                        finalStatusClass = 'holiday-unavailable';
                        $(this).addClass('holiday-override');
                    }

                    $(this).addClass(finalStatusClass)
                          .data('status', finalStatus)
                          .find('.ww-availability-info')
                          .html(`
                            <span class="${finalStatusClass}"></span>
                            <span class="ww-availability-status ww-status-available ${dayData.available_pegs === 0 ? 'disabled' : ''} ${finalStatusClass}">
                                <span>AVAILABLE</span>
                                <span class="full-bg">${isHoliday ? '0' : dayData.available_pegs}</span>
                            </span>
                            <span class="ww-availability-status ww-status-booked ${dayData.total_pegs - dayData.available_pegs === 0 ? 'disabled' : ''} ${finalStatusClass}">
                                <span>BOOKED</span>
                                <span class="full-bg">${isHoliday ? '0' : (dayData.total_pegs - dayData.available_pegs)}</span>
                            </span>
                          `);
                } else {
                    // Day not in response - check if holiday
                    const isHoliday = $(this).hasClass('holiday');
                    let statusClass = 'available';
                    let statusText = 'available';

                    if (isHoliday) {
                        statusClass = 'holiday-unavailable';
                        statusText = 'holiday';
                        $(this).addClass('holiday-override');
                    }

                    $(this).addClass(statusClass)
                          .data('status', statusText)
                          .find('.ww-availability-info')
                          .html(`<span class="ww-availability-status ww-status-${statusClass}">${statusText.replace('-', ' ')}</span>`);
                }
            });
        }

        displayLakeName(response) {
            this.getSelector(".ww-current-lake-name").html('<h2>' + response.lake_name +'<span>('+ response.pegs_number+' pegs)</span></h2>');
        }

        updateLakeName(response) {
            this.getSelector(".ww-current-lake-name h2").append('<small> (' + response + 'pegs)</small>');
        }

        // Setup event listeners
        setupEventListeners() {
            const self = this;

            // Lake selection
            this.getSelector('.ww-lake-select').on('change', function() {
                self.selectedLake = $(this).val();
                if (self.selectedLake) {
                    self.renderCalendar();
                }
            });

            // Month navigation
            this.getSelector('.ww-prev-month').on('click', function() {
                self.currentDate.setMonth(self.currentDate.getMonth() - 1);
                self.renderCalendar();
                if (self.canNavigateToNextMonth()) {
                    self.getSelector('.ww-next-month').prop('disabled', false).css('opacity', '1');
                } else {
                    self.getSelector('.ww-next-month').prop('disabled', true).css('opacity', '0.5');
                }
            });

            this.getSelector('.ww-next-month').on('click', function() {
                if (self.canNavigateToNextMonth()) {
                    self.currentDate.setMonth(self.currentDate.getMonth() + 1);
                    self.renderCalendar();
                } else {
                    alert('You can only book up to ' + self.maxMonthsAhead + ' months in advance.');
                }
                if (self.canNavigateToNextMonth()) {
                    self.getSelector('.ww-next-month').prop('disabled', false).css('opacity', '1');
                } else {
                    self.getSelector('.ww-next-month').prop('disabled', true).css('opacity', '0.5');
                }
            });

            // Day click
            this.container.on('click', '.ww-calendar-day:not(.other-month)', function() {
                if (!self.selectedLake) {
                    alert('Please select a lake first.');
                    return;
                }

                const date = $(this).data('date');
                const status = $(this).data('status');
                const isHoliday = $(this).hasClass('holiday') || $(this).hasClass('holiday-unavailable');

                // Prevent booking on holidays
                if (isHoliday) {
                    alert('Booking is not available on holidays.');
                    return;
                }

                if (status !== 'fully-booked') {
                    if (wwBooking.enable_booking_popup==1) {
                        self.selectedDate = date;
                        self.openBookingModal(date);
                    }
                }
            });

            // Modal controls
            this.getSelector('.ww-close-modal, .ww-cancel-booking').on('click', function() {
                self.closeBookingModal();
            });

            // Peg checkbox handling
            this.container.on('click', '.ww-peg-checkbox-input', function() {
                const pegId = $(this).data('peg-id');
                const isChecked = $(this).is(':checked');
                const $row = $(this).closest('.ww-peg-row');

                $row.find('.ww-match-type-select, .ww-club-select').prop('disabled', !isChecked);

                if (!isChecked) {
                    $row.find('.ww-match-type-select, .ww-club-select').removeClass('ww-required-field');
                    $row.find('.ww-field-required').hide();
                }
            });

            // Select field change listeners
            this.container.on('change', '.ww-match-type-select, .ww-club-select', function() {
                if ($(this).val()) {
                    $(this).removeClass('ww-required-field');
                    const pegId = $(this).data('peg-id');
                    self.getSelector(`#match-required-${pegId}, #club-required-${pegId}`).hide();
                }
            });

            // Apply match type to all
            this.container.on('click', '#applyMatchType', function() {
                const headerSelect = self.getSelector('.ww-match-type-select.header-select');
                const valueToApply = headerSelect.val();
                if (!valueToApply) return;
                self.getSelector('.ww-form-control.ww-match-type-select:not(.ww-match-type-select.header-select)').each(function() {
                    $(this).val(valueToApply);
                });
            });

            // Apply club to all
            this.container.on('click', '#applyClub', function() {
                const headerSelect = self.getSelector('.ww-club-select.header-select');
                const valueToApply = headerSelect.val();
                if (!valueToApply) return;
                self.getSelector('.ww-form-control.ww-club-select:not(.ww-club-select.header-select)').each(function() {
                    $(this).val(valueToApply);
                });
            });

            // Submit booking
            this.getSelector('.ww-submit-booking').on('click', function() {
                self.submitBooking();
            });
        }

        // Open booking modal
        openBookingModal(date) {
            this.getSelector('.ww-booking-date').text(new Date(date).toLocaleDateString());
            this.selectedDate = date;
            this.loadPegsForDate(date);
            this.getSelector('.ww-booking-modal').show();

            // Reset any previous validation
            this.getSelector('.ww-required-field').removeClass('ww-required-field');
            this.getSelector('.ww-field-required').hide();
        }

        // Close booking modal
        closeBookingModal() {
            this.getSelector('.ww-booking-modal').hide();
        }

        // Load pegs for selected date
        loadPegsForDate(date) {
            this.showLoading(true);

            $.ajax({
                url: `${wwBooking.rest_url}ww-booking/v1/daily-availability/${this.selectedLake}/${date}`,
                method: 'GET',
                success: (response) => {
                    this.displayPegs(response);
                },
                error: (xhr) => {
                    alert('Error loading pegs data.');
                    console.error('Availability error for ' + this.calendarId + ':', xhr.responseJSON);
                },
                complete: () => {
                    this.showLoading(false);
                }
            });
        }

        // Display pegs in table format
        displayPegs(dayData) {
            let tableHTML = '';

            // Check if data is still loading
            if (this.matchTypes.length === 0 || this.clubs.length === 0) {
                tableHTML = `
                    <div class="ww-loading-data">
                        <p>Loading booking data...</p>
                        <div class="ww-spinner"></div>
                    </div>
                `;
                this.getSelector('.ww-peg-list').html(tableHTML);

                // Retry after a short delay if data hasn't loaded
                setTimeout(() => {
                    if (this.matchTypes.length === 0 || this.clubs.length === 0) {
                        this.initializeBookingData();
                        setTimeout(() => this.displayPegs(dayData), 1000);
                    }
                }, 1000);
                return;
            }

            if (dayData && dayData.pegs && dayData.pegs.length > 0) {
                tableHTML = `
                    <table class="ww-peg-selection-table">
                        <thead>
                            <tr>
                                <th class="ww-peg-checkbox">Book</th>
                                <th class="ww-peg-name">Peg Name</th>
                                <th class="ww-peg-status-col">Status</th>
                                <th class="ww-match-type">Match Type</th>
                                <th class="ww-club">Club</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                // Generate match type options
                let matchTypeOptions = '<option value="">Select Match Type</option>';
                this.matchTypes.forEach(matchType => {
                    matchTypeOptions += `<option value="${matchType.type_slug}">${matchType.type_name}</option>`;
                });

                // Generate club options
                let clubOptions = '<option value="">Select Club</option>';
                this.clubs.forEach(club => {
                    clubOptions += `<option value="${club.id}">${club.club_name}</option>`;
                });

                tableHTML += `
                    <tr>
                        <th class="ww-peg-checkbox"></th>
                        <th class="ww-peg-name"></th>
                        <th class="ww-peg-status-col"></th>
                        <th class="ww-match-type">
                            <select class="ww-form-control ww-match-type-select header-select">
                                ${matchTypeOptions}
                            </select>
                            <button id="applyMatchType">Apply</button>
                        </th>
                        <th class="ww-club">
                            <select class="ww-form-control ww-club-select header-select">
                                ${clubOptions}
                            </select>
                            <button id="applyClub">Apply</button>
                        </th>
                    </tr>
                `;

                dayData.pegs.forEach(peg => {
                    const isBooked = peg.is_booked === 'booked';
                    const rowClass = isBooked ? 'disabled' : '';
                    const disabledAttr = isBooked ? 'disabled' : '';
                    const checkedAttr = !isBooked ? 'checked' : '';

                    tableHTML += `
                        <tr class="ww-peg-row ${rowClass}" data-peg-id="${peg.peg_id}">
                            <td class="ww-peg-checkbox">
                                <input type="checkbox" class="ww-peg-checkbox-input"
                                       data-peg-id="${peg.peg_id}"
                                       ${checkedAttr} ${disabledAttr}>
                            </td>
                            <td class="ww-peg-name">
                                <strong>${peg.peg_name}</strong>
                                ${isBooked && peg.booking_details ? `
                                    <div class="ww-booking-details">
                                        <small>Already booked</small>
                                    </div>
                                ` : ''}
                            </td>
                            <td class="ww-peg-status-col">
                                <span class="ww-peg-status ${isBooked ? 'booked' : 'available'}">
                                    ${isBooked ? 'Booked' : 'Available'}
                                </span>
                            </td>
                            <td class="ww-match-type">
                                <select class="ww-form-control ww-match-type-select"
                                        data-peg-id="${peg.peg_id}" ${disabledAttr}>
                                    ${matchTypeOptions}
                                </select>
                                <div class="ww-field-required" id="match-required-${peg.peg_id}">
                                    Match type is required
                                </div>
                            </td>
                            <td class="ww-club">
                                <select class="ww-form-control ww-club-select"
                                        data-peg-id="${peg.peg_id}" ${disabledAttr}>
                                    ${clubOptions}
                                </select>
                                <div class="ww-field-required" id="club-required-${peg.peg_id}">
                                    Club is required
                                </div>
                            </td>
                        </tr>
                    `;
                });

                tableHTML += '</tbody></table>';
            } else {
                tableHTML = '<p>No pegs available for this lake.</p>';
            }

            this.getSelector('.ww-peg-list').html(tableHTML);
        }

        // Get all selected pegs with their data
        getSelectedPegs() {
            const selectedPegs = [];
            const self = this;

            this.getSelector('.ww-peg-checkbox-input:checked').each(function() {
                const pegId = $(this).data('peg-id');
                const matchType = self.getSelector(`.ww-match-type-select[data-peg-id="${pegId}"]`).val();
                const clubId = self.getSelector(`.ww-club-select[data-peg-id="${pegId}"]`).val();

                selectedPegs.push({
                    pegId: pegId,
                    matchType: matchType,
                    clubId: clubId
                });
            });

            return selectedPegs;
        }

        // Validate the booking form
        validateBookingForm(selectedPegs) {
            let isValid = true;

            // Clear previous validation
            this.getSelector('.ww-required-field').removeClass('ww-required-field');
            this.getSelector('.ww-field-required').hide();

            // Validate each selected peg
            selectedPegs.forEach(peg => {
                const matchTypeSelect = this.getSelector(`.ww-match-type-select[data-peg-id="${peg.pegId}"]`);
                const clubSelect = this.getSelector(`.ww-club-select[data-peg-id="${peg.pegId}"]`);

                if (!peg.matchType) {
                    matchTypeSelect.addClass('ww-required-field');
                    this.getSelector(`#match-required-${peg.pegId}`).show();
                    isValid = false;
                }

                if (!peg.clubId) {
                    clubSelect.addClass('ww-required-field');
                    this.getSelector(`#club-required-${peg.pegId}`).show();
                    isValid = false;
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields for the selected pegs.');
            }

            return isValid;
        }

        // Submit booking
        submitBooking() {
            const selectedPegs = this.getSelectedPegs();

            if (selectedPegs.length === 0) {
                alert('Please select at least one peg to book.');
                return;
            }

            if (!this.validateBookingForm(selectedPegs)) {
                return;
            }

            const bookingData = {
                lake_id: this.selectedLake,
                date_start: this.selectedDate,
                date_end: this.selectedDate,
                booking_status: 'booked',
                pegs: {}
            };

            selectedPegs.forEach(peg => {
                bookingData.pegs[peg.pegId] = {
                    match_type_slug: peg.matchType,
                    club_id: parseInt(peg.clubId)
                };
            });

            this.showLoading(true);

            $.ajax({
                url: wwBooking.rest_url + 'ww-booking/v1/bookings',
                method: 'POST',
                data: JSON.stringify(bookingData),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wwBooking.nonce);
                },
                success: (response) => {
                    alert('Booking created successfully!');
                    this.closeBookingModal();
                    const year = this.currentDate.getFullYear();
                    const month = this.currentDate.getMonth() + 1;
                    const startDate = `${year}-${String(month).padStart(2, '0')}-01`;
                    const endDate = new Date(year, month, 0).toISOString().split('T')[0];
                    this.loadMonthAvailability(startDate, endDate);
                },
                error: (xhr) => {
                    const errorMessage = xhr.responseJSON?.message || 'Unknown error occurred';
                    alert('Error creating booking: ' + errorMessage);
                    console.error('Booking error for ' + this.calendarId + ':', xhr.responseJSON);
                },
                complete: () => {
                    this.showLoading(false);
                }
            });
        }

        // Show/hide loading
        showLoading(show) {
            if (show) {
                this.getSelector('.ww-loading').show();
            } else {
                this.getSelector('.ww-loading').hide();
            }
        }
    }

    // Initialize all calendars on the page
    $('.ww-booking-calendar').each(function() {
        WWCalendarManager.init(this);
    });
});