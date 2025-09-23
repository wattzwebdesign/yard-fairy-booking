jQuery(document).ready(function($) {
    
    $('#_yfb_bookable').on('change', function() {
        if ($(this).is(':checked')) {
            $('.show_if_yfb_bookable').show();
        } else {
            $('.show_if_yfb_bookable').hide();
        }
    }).trigger('change');

    $('#_yfb_use_custom_availability').on('change', function() {
        if ($(this).is(':checked')) {
            $('.yfb-custom-availability-section').show();
        } else {
            $('.yfb-custom-availability-section').hide();
        }
    }).trigger('change');

});