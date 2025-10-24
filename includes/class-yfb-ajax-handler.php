<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Ajax_Handler {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_yfb_get_available_slots', array($this, 'get_available_slots'));
        add_action('wp_ajax_nopriv_yfb_get_available_slots', array($this, 'get_available_slots'));

        add_action('wp_ajax_yfb_get_month_availability', array($this, 'get_month_availability'));
        add_action('wp_ajax_nopriv_yfb_get_month_availability', array($this, 'get_month_availability'));

        add_action('wp_ajax_yfb_calculate_delivery_distance', array($this, 'calculate_delivery_distance'));
        add_action('wp_ajax_nopriv_yfb_calculate_delivery_distance', array($this, 'calculate_delivery_distance'));
    }

    public function get_available_slots() {
        check_ajax_referer('yfb_ajax_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (!$product_id || !$date) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'yard-fairy-booking')));
        }

        $calendar_display = YFB_Calendar_Display::instance();
        $slots = $calendar_display->get_available_slots($product_id, $date);

        wp_send_json_success(array('slots' => $slots));
    }

    public function get_month_availability() {
        check_ajax_referer('yfb_ajax_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
        $month = isset($_POST['month']) ? absint($_POST['month']) : date('n');

        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'yard-fairy-booking')));
        }

        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $availability = array();

        $min_block = get_post_meta($product_id, '_yfb_min_block_bookable', true);
        $min_block_unit = get_post_meta($product_id, '_yfb_min_block_bookable_unit', true);
        $max_block = get_post_meta($product_id, '_yfb_max_block_bookable', true);
        $max_block_unit = get_post_meta($product_id, '_yfb_max_block_bookable_unit', true);

        $min_date = new DateTime('now', wp_timezone());
        if ($min_block && $min_block > 0) {
            if ($min_block_unit === 'month') {
                $min_date->add(new DateInterval('P' . $min_block . 'M'));
            } else {
                $min_date->add(new DateInterval('P' . $min_block . 'D'));
            }
        }

        $max_date = null;
        if ($max_block && $max_block > 0) {
            $max_date = new DateTime('now', wp_timezone());
            if ($max_block_unit === 'month') {
                $max_date->add(new DateInterval('P' . $max_block . 'M'));
            } else {
                $max_date->add(new DateInterval('P' . $max_block . 'D'));
            }
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $check_date = new DateTime($date, wp_timezone());

            if ($check_date < $min_date || ($max_date && $check_date > $max_date)) {
                $availability[$day] = false;
                continue;
            }

            $day_name = strtolower($check_date->format('l'));
            $use_custom = get_post_meta($product_id, '_yfb_use_custom_availability', true);
            
            if ($use_custom === 'yes') {
                $enabled = get_post_meta($product_id, '_yfb_day_' . $day_name . '_enabled', true);
                if (empty($enabled)) {
                    $enabled = 'yes';
                }
            } else {
                $enabled = get_option('yfb_default_day_' . $day_name . '_enabled', 'yes');
            }

            if ($enabled === 'yes') {
                $duration = get_post_meta($product_id, '_yfb_duration', true) ?: 1;
                $buffer_period = get_post_meta($product_id, '_yfb_buffer_period', true) ?: 0;
                $buffer_unit = get_post_meta($product_id, '_yfb_buffer_unit', true) ?: 'day';
                $adjacent_buffering = get_post_meta($product_id, '_yfb_adjacent_buffering', true) === 'yes';
                
                $start_date = new DateTime($date, wp_timezone());
                $end_date = clone $start_date;
                $end_date->add(new DateInterval('P' . $duration . 'D'));

                if ($buffer_period > 0) {
                    $buffer_interval = 'P' . $buffer_period . 'D';
                    if ($buffer_unit === 'month') {
                        $buffer_interval = 'P' . $buffer_period . 'M';
                    }

                    if ($adjacent_buffering) {
                        $start_date->sub(new DateInterval($buffer_interval));
                    }
                    $end_date->add(new DateInterval($buffer_interval));
                }

                $max_bookings = get_post_meta($product_id, '_yfb_max_bookings_per_block', true) ?: 1;

                $args = array(
                    'post_type' => 'yfb_booking',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => '_yfb_product_id',
                            'value' => $product_id,
                        ),
                        array(
                            'key' => '_yfb_status',
                            'value' => 'cancelled',
                            'compare' => '!=',
                        ),
                    ),
                );

                $bookings = get_posts($args);
                $overlap_count = 0;

                foreach ($bookings as $booking) {
                    $booking_start = new DateTime(get_post_meta($booking->ID, '_yfb_booking_date', true), wp_timezone());
                    $booking_end_date = get_post_meta($booking->ID, '_yfb_end_date', true);
                    $booking_end = $booking_end_date ? new DateTime($booking_end_date, wp_timezone()) : clone $booking_start;

                    if ($start_date < $booking_end && $end_date > $booking_start) {
                        $overlap_count++;
                        if ($overlap_count >= $max_bookings) {
                            break;
                        }
                    }
                }

                $availability[$day] = $overlap_count < $max_bookings;
            } else {
                $availability[$day] = false;
            }
        }

        wp_send_json_success(array('availability' => $availability));
    }

    public function calculate_delivery_distance() {
        check_ajax_referer('yfb_ajax_nonce', 'nonce');

        $delivery_address = isset($_POST['delivery_address']) ? sanitize_text_field($_POST['delivery_address']) : '';

        if (empty($delivery_address)) {
            wp_send_json_error(array('message' => __('Please enter a delivery address.', 'yard-fairy-booking')));
        }

        $home_base = get_option('yfb_home_base_address');
        $api_key = get_option('yfb_google_maps_api_key');
        $included_mileage = floatval(get_option('yfb_included_mileage', 10));
        $delivery_fee = floatval(get_option('yfb_delivery_fee', 25));
        $max_mileage = floatval(get_option('yfb_max_delivery_mileage', 100));
        $max_mileage_message = get_option('yfb_max_mileage_message', 'Sorry, we cannot deliver to your location. Please contact us for alternative arrangements.');

        if (empty($home_base)) {
            wp_send_json_error(array('message' => __('Home base address not configured.', 'yard-fairy-booking')));
        }

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Google Maps API key not configured.', 'yard-fairy-booking')));
        }

        // Call Google Distance Matrix API
        $url = add_query_arg(array(
            'origins' => urlencode($home_base),
            'destinations' => urlencode($delivery_address),
            'units' => 'imperial',
            'key' => $api_key
        ), 'https://maps.googleapis.com/maps/api/distancematrix/json');

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Failed to calculate distance. Please try again.', 'yard-fairy-booking')));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] !== 'OK' || empty($data['rows'][0]['elements'][0]['distance'])) {
            wp_send_json_error(array('message' => __('Could not calculate distance. Please check the address.', 'yard-fairy-booking')));
        }

        $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];
        $distance_miles = $distance_meters * 0.000621371; // Convert meters to miles
        $distance_miles = round($distance_miles, 2);

        // Check if max mileage is exceeded
        $exceeds_max = $max_mileage > 0 && $distance_miles >= $max_mileage;

        if ($exceeds_max) {
            wp_send_json_success(array(
                'distance' => $distance_miles,
                'included_mileage' => $included_mileage,
                'max_mileage' => $max_mileage,
                'exceeds_max' => true,
                'requires_fee' => false,
                'fee_amount' => 0,
                'message' => sprintf(__('Distance: %.2f miles. %s', 'yard-fairy-booking'), $distance_miles, esc_html($max_mileage_message))
            ));
        }

        $requires_fee = $distance_miles > $included_mileage;
        $fee_amount = $requires_fee ? $delivery_fee : 0;

        wp_send_json_success(array(
            'distance' => $distance_miles,
            'included_mileage' => $included_mileage,
            'max_mileage' => $max_mileage,
            'exceeds_max' => false,
            'requires_fee' => $requires_fee,
            'fee_amount' => $fee_amount,
            'message' => sprintf(
                __('Distance: %.2f miles. %s', 'yard-fairy-booking'),
                $distance_miles,
                $requires_fee ? sprintf(__('Delivery fee of %s will be applied.', 'yard-fairy-booking'), wc_price($fee_amount)) : __('Within included mileage - no delivery fee.', 'yard-fairy-booking')
            )
        ));
    }
}