jQuery(document).ready(function($) {
    
    let currentDate = new Date();
    let selectedDate = null;
    let selectedTime = null;

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
                        
                        let classes = 'yfb-cal-day';
                        if (isToday) classes += ' yfb-cal-today';
                        if (isAvailable) classes += ' yfb-cal-available';
                        if (isSelected) classes += ' yfb-cal-selected';
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

        $('.yfb-cal-day').removeClass('yfb-cal-selected');
        $(this).addClass('yfb-cal-selected');
        
        selectedDate = $(this).data('date');
        
        const dateParts = selectedDate.split('-');
        const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
        const formattedDate = dateObj.toLocaleDateString('default', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        $('.yfb-selected-datetime').text(formattedDate);
        $('.yfb-selected-booking').show();
        
        $('input[name="yfb_booking_date"]').val(selectedDate);
        
        $('.yfb-time-slots-container').hide();
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
            const bookingDate = $('input[name="yfb_booking_date"]').val();
            
            if (!bookingDate) {
                return true;
            }
            
            if ($(this).find('input[name="yfb_booking_date"]').length === 0) {
                $(this).append('<input type="hidden" name="yfb_booking_date" value="' + bookingDate + '">');
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