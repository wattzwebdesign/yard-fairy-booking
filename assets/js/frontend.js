jQuery(document).ready(function($) {

    let currentDate = new Date();
    let selectedDate = null;
    let selectedTime = null;
    let startDate = null;
    let endDate = null;
    let isSelectingRange = false;
    let deliveryDistanceCalculated = false;
    let deliveryDistance = 0;
    let deliveryFeeAmount = 0;
    let exceedsMaxMileage = false;
    let autocomplete = null;

    // Initialize Google Places Autocomplete
    function initAutocomplete() {
        const addressInput = document.getElementById('yfb_delivery_address');
        if (!addressInput) {
            return;
        }

        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            return;
        }

        try {
            autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['address'],
                componentRestrictions: { country: 'us' }
            });

            // When user selects an address from autocomplete
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();

                if (!place.formatted_address) {
                    return;
                }

                // Set the input value to the formatted address
                addressInput.value = place.formatted_address;

                // Trigger the blur event to calculate distance
                setTimeout(function() {
                    $(addressInput).trigger('blur');
                }, 100);
            });
        } catch (error) {
            console.error('Error initializing Google Places Autocomplete:', error);
        }
    }

    // Initialize autocomplete when Google API is loaded
    if (typeof yfb_ajax !== 'undefined' && yfb_ajax.google_places_enabled) {
        // Check if Google Maps is already loaded
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            setTimeout(initAutocomplete, 100);
        } else {
            // Wait for Google Maps API to load via callback
            $(document).on('yfb-google-maps-loaded', function() {
                setTimeout(initAutocomplete, 100);
            });
        }
    }

    function renderCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        
        $('.yfb-cal-month-year').text(
            new Date(year, month).toLocaleDateString('default', { month: 'long', year: 'numeric' })
        );

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        const productId = $('.yfb-calendar-wrapper').data('product-id');
        
        $.ajax({
            url: yfb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfb_get_month_availability',
                nonce: yfb_ajax.nonce,
                product_id: productId,
                year: year,
                month: month + 1
            },
            success: function(response) {
                if (response.success) {
                    let html = '';
                    
                    for (let i = 0; i < firstDay; i++) {
                        html += '<div class="yfb-cal-day yfb-cal-day-empty"></div>';
                    }
                    
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        const isAvailable = response.data.availability[day];
                        const isToday = new Date().toDateString() === new Date(year, month, day).toDateString();
                        const isSelected = selectedDate === dateStr;
                        const isStartDate = startDate === dateStr;
                        const isEndDate = endDate === dateStr;
                        const isInRange = startDate && endDate && dateStr >= startDate && dateStr <= endDate;

                        let classes = 'yfb-cal-day';
                        if (isToday) classes += ' yfb-cal-today';
                        if (isAvailable) classes += ' yfb-cal-available';
                        if (isSelected) classes += ' yfb-cal-selected';
                        if (isStartDate) classes += ' yfb-cal-range-start';
                        if (isEndDate) classes += ' yfb-cal-range-end';
                        if (isInRange) classes += ' yfb-cal-in-range';
                        if (!isAvailable) classes += ' yfb-cal-disabled';

                        html += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
                    }
                    
                    $('.yfb-cal-days').html(html);
                }
            }
        });
    }

    function loadTimeSlots(date) {
        const productId = $('.yfb-calendar-wrapper').data('product-id');
        
        $('.yfb-time-slots').html('<p>' + yfb_calendar_data.i18n.loading + '</p>');
        $('.yfb-time-slots-container').show();
        
        $.ajax({
            url: yfb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfb_get_available_slots',
                nonce: yfb_ajax.nonce,
                product_id: productId,
                date: date
            },
            success: function(response) {
                if (response.success && response.data.slots.length > 0) {
                    let html = '<div class="yfb-time-slots-grid">';
                    
                    response.data.slots.forEach(function(slot) {
                        if (slot.available) {
                            html += `<button type="button" class="yfb-time-slot" data-time="${slot.time}">${slot.time}</button>`;
                        } else {
                            html += `<button type="button" class="yfb-time-slot yfb-time-slot-disabled" disabled>${slot.time}</button>`;
                        }
                    });
                    
                    html += '</div>';
                    $('.yfb-time-slots').html(html);
                } else {
                    $('.yfb-time-slots').html('<p>' + yfb_calendar_data.i18n.no_slots + '</p>');
                }
            }
        });
    }

    $(document).on('click', '.yfb-cal-prev', function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar(currentDate);
    });

    $(document).on('click', '.yfb-cal-next', function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar(currentDate);
    });

    $(document).on('click', '.yfb-cal-day:not(.yfb-cal-disabled):not(.yfb-cal-day-empty)', function() {
        if (!$(this).hasClass('yfb-cal-available')) {
            return;
        }

        const clickedDate = $(this).data('date');

        // First click or clicking after a completed selection - start new selection
        if (!startDate) {
            startDate = clickedDate;
            endDate = clickedDate;
            isSelectingRange = false;
            renderCalendar(currentDate);

            const dateParts = startDate.split('-');
            const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
            const formattedDate = dateObj.toLocaleDateString('default', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });

            $('.yfb-selected-datetime').html(
                `<strong>${formattedDate}</strong> (1 day) <span style="font-size: 0.9em; color: #666;">- Click another date to extend range</span>`
            );
            $('.yfb-selected-booking').show();

            // Set hidden fields with date range
            $('#yfb_booking_date').val(startDate);

            // Add end date field if it doesn't exist
            if ($('#yfb_booking_end_date').length === 0) {
                $('.yfb-calendar-wrapper').append('<input type="hidden" name="yfb_booking_end_date" id="yfb_booking_end_date" value="">');
            }
            $('#yfb_booking_end_date').val(endDate);

            $('.yfb-time-slots-container').hide();
            return;
        }

        // Second click - modify the range
        if (startDate && endDate) {
            // If clicking the same date that's already selected as single day, reset selection
            if (clickedDate === startDate && clickedDate === endDate) {
                startDate = null;
                endDate = null;
                isSelectingRange = false;
                renderCalendar(currentDate);
                $('.yfb-selected-booking').hide();
                $('#yfb_booking_date').val('');
                $('#yfb_booking_end_date').val('');
                return;
            }

            // Determine new start and end based on clicked date
            if (clickedDate < startDate) {
                // Clicked date becomes new start
                startDate = clickedDate;
            } else if (clickedDate > endDate) {
                // Clicked date becomes new end
                endDate = clickedDate;
            } else {
                // Clicked date is between start and end, set as new single day range
                startDate = clickedDate;
                endDate = clickedDate;
            }

            renderCalendar(currentDate);

            // Calculate number of days
            const start = new Date(startDate);
            const end = new Date(endDate);
            const daysDiff = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;

            const startFormatted = start.toLocaleDateString('default', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            const endFormatted = end.toLocaleDateString('default', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });

            if (daysDiff === 1) {
                $('.yfb-selected-datetime').html(
                    `<strong>${startFormatted}</strong> (1 day) <span style="font-size: 0.9em; color: #666;">- Click another date to extend range</span>`
                );
            } else {
                $('.yfb-selected-datetime').html(
                    `<strong>${startFormatted}</strong> to <strong>${endFormatted}</strong> (${daysDiff} days)`
                );
            }
            $('.yfb-selected-booking').show();

            // Update hidden fields
            $('#yfb_booking_date').val(startDate);
            $('#yfb_booking_end_date').val(endDate);

            $('.yfb-time-slots-container').hide();
        }
    });

    $(document).on('click', '.yfb-time-slot:not(.yfb-time-slot-disabled)', function() {
        $('.yfb-time-slot').removeClass('yfb-time-slot-selected');
        $(this).addClass('yfb-time-slot-selected');
        
        selectedTime = $(this).data('time');
        
        const dateObj = new Date(selectedDate + ' ' + selectedTime);
        const formattedDate = dateObj.toLocaleDateString('default', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        $('.yfb-selected-datetime').text(`${formattedDate} at ${selectedTime}`);
        $('.yfb-selected-booking').show();
        
        $('#yfb_booking_date').val(selectedDate);
        $('#yfb_booking_time').val(selectedTime);
    });

    if ($('.yfb-calendar-wrapper').length > 0) {
        const calendarMode = $('.yfb-calendar-wrapper').data('calendar-mode');
        
        if (calendarMode === 'always_visible') {
            renderCalendar(currentDate);
        }
    }

    $(document).on('click', '.yfb-show-calendar-btn', function() {
        const $wrapper = $(this).closest('.yfb-calendar-wrapper');
        $wrapper.find('.yfb-calendar-header, .yfb-calendar-grid, .yfb-selected-booking').show();
        $(this).hide();
        renderCalendar(currentDate);
    });

    $('form.cart').on('submit', function(e) {
        if ($('.yfb-calendar-wrapper').length > 0) {
            const bookingDate = $('#yfb_booking_date').val();
            const bookingEndDate = $('#yfb_booking_end_date').val();
            const deliveryAddress = $('#yfb_delivery_address').val();
            const deliveryDistance = $('#yfb_delivery_distance').val();
            const deliveryFee = $('#yfb_delivery_fee').val();

            if (!bookingDate) {
                return true;
            }

            // Validate delivery address
            if (!deliveryAddress || deliveryAddress.trim() === '') {
                e.preventDefault();
                alert('Please enter a delivery address.');
                $('#yfb_delivery_address').focus();
                return false;
            }

            if (!deliveryDistanceCalculated) {
                e.preventDefault();
                alert('Please wait for delivery distance calculation to complete, or check that the address is valid.');
                return false;
            }

            // Prevent submission if max mileage exceeded
            if (exceedsMaxMileage) {
                e.preventDefault();
                alert('Cannot add to cart. The delivery address exceeds our maximum delivery distance.');
                return false;
            }

            // Add all booking fields to form
            if ($(this).find('input[name="yfb_booking_date"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_booking_date" value="' + bookingDate + '">');
            }

            if (bookingEndDate && $(this).find('input[name="yfb_booking_end_date"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_booking_end_date" value="' + bookingEndDate + '">');
            }

            if ($(this).find('input[name="yfb_delivery_address"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_delivery_address" value="' + deliveryAddress + '">');
            }

            if (deliveryDistance && $(this).find('input[name="yfb_delivery_distance"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_delivery_distance" value="' + deliveryDistance + '">');
            }

            if (deliveryFee && $(this).find('input[name="yfb_delivery_fee"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_delivery_fee" value="' + deliveryFee + '">');
            }
        }
    });

    if ($('#yfb-booking-calendar').length > 0) {
        const productId = $('#yfb-booking-calendar-container').data('product-id');
        
        const calendarHtml = `
            <div class="yfb-calendar-wrapper" data-product-id="${productId}">
                <div class="yfb-calendar-header">
                    <button type="button" class="yfb-cal-prev">&laquo;</button>
                    <span class="yfb-cal-month-year"></span>
                    <button type="button" class="yfb-cal-next">&raquo;</button>
                </div>
                <div class="yfb-calendar-grid">
                    <div class="yfb-cal-day-header">Sun</div>
                    <div class="yfb-cal-day-header">Mon</div>
                    <div class="yfb-cal-day-header">Tue</div>
                    <div class="yfb-cal-day-header">Wed</div>
                    <div class="yfb-cal-day-header">Thu</div>
                    <div class="yfb-cal-day-header">Fri</div>
                    <div class="yfb-cal-day-header">Sat</div>
                    <div class="yfb-cal-days"></div>
                </div>
                <div class="yfb-time-slots-container" style="display: none;">
                    <h4>Available Time Slots</h4>
                    <div class="yfb-time-slots"></div>
                </div>
                <div class="yfb-selected-booking" style="display: none;">
                    <p><strong>Selected:</strong> <span class="yfb-selected-datetime"></span></p>
                </div>
            </div>
        `;
        
        $('#yfb-booking-calendar').html(calendarHtml);
        renderCalendar(currentDate);
    }

    // Delivery address distance calculation
    let deliveryCalculationTimeout;
    $(document).on('input', '#yfb_delivery_address', function() {
        clearTimeout(deliveryCalculationTimeout);
        deliveryDistanceCalculated = false;
        $('.yfb-delivery-info').hide();
    });

    $(document).on('blur', '#yfb_delivery_address', function() {
        const address = $(this).val().trim();

        if (!address) {
            return;
        }

        clearTimeout(deliveryCalculationTimeout);
        deliveryCalculationTimeout = setTimeout(function() {
            calculateDeliveryDistance(address);
        }, 500);
    });

    function calculateDeliveryDistance(address) {
        $('.yfb-delivery-info').html('<span class="dashicons dashicons-update dashicons-spin"></span> Calculating distance...').show();

        $.ajax({
            url: yfb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yfb_calculate_delivery_distance',
                nonce: yfb_ajax.nonce,
                delivery_address: address
            },
            success: function(response) {
                if (response.success) {
                    deliveryDistanceCalculated = true;
                    deliveryDistance = response.data.distance;
                    deliveryFeeAmount = response.data.fee_amount;
                    exceedsMaxMileage = response.data.exceeds_max || false;

                    $('#yfb_delivery_distance').val(deliveryDistance);
                    $('#yfb_delivery_fee').val(deliveryFeeAmount);

                    // Handle max mileage exceeded
                    if (exceedsMaxMileage) {
                        $('.yfb-delivery-info')
                            .html('<strong style="color: #d9534f;">' + response.data.message + '</strong>')
                            .removeClass('yfb-delivery-fee-required yfb-delivery-no-fee')
                            .addClass('yfb-delivery-max-exceeded')
                            .show();

                        // Hide entire add to cart section
                        $('form.cart').hide();

                        // Add error message after the calendar wrapper
                        if ($('.yfb-max-mileage-notice').length === 0) {
                            $('.yfb-calendar-wrapper').after(
                                '<p class="yfb-max-mileage-notice" style="color: #d9534f; font-weight: bold; margin-top: 10px;">Cannot add to cart - delivery address exceeds maximum distance.</p>'
                            );
                        }
                    } else {
                        // Show add to cart form
                        $('form.cart').show();

                        // Remove error message
                        $('.yfb-max-mileage-notice').remove();

                        let infoClass = response.data.requires_fee ? 'yfb-delivery-fee-required' : 'yfb-delivery-no-fee';
                        $('.yfb-delivery-info')
                            .html(response.data.message)
                            .removeClass('yfb-delivery-fee-required yfb-delivery-no-fee yfb-delivery-max-exceeded')
                            .addClass(infoClass)
                            .show();
                    }
                } else {
                    $('.yfb-delivery-info')
                        .html('<span style="color: red;">' + response.data.message + '</span>')
                        .show();
                }
            },
            error: function() {
                $('.yfb-delivery-info')
                    .html('<span style="color: red;">Error calculating distance. Please try again.</span>')
                    .show();
            }
        });
    }


    $(document).on('click', '.yfb-sync-gcal, .yfb-resync-gcal', function() {
        const $button = $(this);
        const bookingId = $button.data('booking-id');
        const action = $button.hasClass('yfb-sync-gcal') ? 'yfb_sync_to_gcal' : 'yfb_resync_to_gcal';
        const originalText = $button.text();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top: 3px;"></span> Syncing...');
        
        $.ajax({
            url: yfb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: yfb_ajax.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});