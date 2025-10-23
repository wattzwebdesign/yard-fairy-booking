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
}