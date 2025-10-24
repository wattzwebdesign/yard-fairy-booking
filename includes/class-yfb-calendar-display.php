<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Calendar_Display {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('yfb_booking_calendar', array($this, 'render_calendar_shortcode'));
    }

    public function render_calendar_shortcode($atts) {
        global $product;

        $atts = shortcode_atts(array(
            'product_id' => 0,
        ), $atts);

        $product_id = absint($atts['product_id']);

        if (!$product_id && is_product() && $product) {
            $product_id = $product->get_id();
        }

        if (!$product_id) {
            return '<p>' . __('Please provide a product ID or use on a product page.', 'yard-fairy-booking') . '</p>';
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return '<p>' . __('Product not found.', 'yard-fairy-booking') . '</p>';
        }

        $is_bookable = get_post_meta($product_id, '_yfb_bookable', true);
        if ($is_bookable !== 'yes') {
            return '<p>' . __('This product is not bookable.', 'yard-fairy-booking') . '</p>';
        }

        ob_start();
        $this->render_calendar($product_id);
        return ob_get_clean();
    }

    public function render_calendar($product_id) {
        $duration = get_post_meta($product_id, '_yfb_duration', true) ?: 1;
        $min_block = get_post_meta($product_id, '_yfb_min_block_bookable', true) ?: 3;
        $min_block_unit = get_post_meta($product_id, '_yfb_min_block_bookable_unit', true) ?: 'day';
        $max_block = get_post_meta($product_id, '_yfb_max_block_bookable', true) ?: 3;
        $max_block_unit = get_post_meta($product_id, '_yfb_max_block_bookable_unit', true) ?: 'month';
        $calendar_mode = get_post_meta($product_id, '_yfb_calendar_display_mode', true) ?: 'always_visible';

        $availability = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $use_custom = get_post_meta($product_id, '_yfb_use_custom_availability', true);

        foreach ($days as $day) {
            if ($use_custom === 'yes') {
                $enabled = get_post_meta($product_id, '_yfb_day_' . $day . '_enabled', true);
                if (empty($enabled)) {
                    $enabled = 'yes';
                }
            } else {
                $enabled = get_option('yfb_default_day_' . $day . '_enabled', 'yes');
            }

            if ($enabled === 'yes') {
                $availability[$day] = true;
            }
        }

        wp_localize_script('yfb-frontend', 'yfb_calendar_data', array(
            'product_id' => $product_id,
            'duration' => $duration,
            'min_block' => $min_block,
            'min_block_unit' => $min_block_unit,
            'max_block' => $max_block,
            'max_block_unit' => $max_block_unit,
            'calendar_mode' => $calendar_mode,
            'availability' => $availability,
            'i18n' => array(
                'select_date' => __('Select a date', 'yard-fairy-booking'),
            ),
        ));

        ?>
        <div class="yfb-calendar-wrapper" data-product-id="<?php echo esc_attr($product_id); ?>" data-calendar-mode="<?php echo esc_attr($calendar_mode); ?>" style="<?php echo $calendar_mode === 'click_to_show' ? 'display:none;' : ''; ?>">
            <?php if ($calendar_mode === 'click_to_show'): ?>
                <button type="button" class="yfb-show-calendar-btn" style="margin-bottom: 10px;"><?php _e('Select Booking Date', 'yard-fairy-booking'); ?></button>
            <?php endif; ?>
            <div class="yfb-calendar-header">
                <button type="button" class="yfb-cal-prev">&laquo;</button>
                <span class="yfb-cal-month-year"></span>
                <button type="button" class="yfb-cal-next">&raquo;</button>
            </div>
            <div class="yfb-calendar-grid">
                <div class="yfb-cal-day-header"><?php _e('Sun', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Mon', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Tue', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Wed', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Thu', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Fri', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-day-header"><?php _e('Sat', 'yard-fairy-booking'); ?></div>
                <div class="yfb-cal-days"></div>
            </div>
            <div class="yfb-selected-booking" style="display: none;">
                <p><strong><?php _e('Selected:', 'yard-fairy-booking'); ?></strong> <span class="yfb-selected-datetime"></span></p>
            </div>
            <div class="yfb-delivery-address-wrapper" style="margin-top: 20px;">
                <label for="yfb_delivery_address" style="display: block; margin-bottom: 8px; font-weight: 600;">
                    <?php _e('Delivery Address:', 'yard-fairy-booking'); ?> <span style="color: red;">*</span>
                </label>
                <input type="text" name="yfb_delivery_address" id="yfb_delivery_address" class="yfb-delivery-address-input" placeholder="<?php esc_attr_e('Enter full delivery address', 'yard-fairy-booking'); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required>
                <p class="yfb-delivery-info" style="margin-top: 8px; font-size: 0.9em; color: #666; display: none;"></p>
            </div>
            <input type="hidden" name="yfb_booking_date" id="yfb_booking_date" value="">
            <input type="hidden" name="yfb_booking_end_date" id="yfb_booking_end_date" value="">
            <input type="hidden" name="yfb_booking_time" id="yfb_booking_time" value="">
            <input type="hidden" name="yfb_delivery_distance" id="yfb_delivery_distance" value="">
            <input type="hidden" name="yfb_delivery_fee" id="yfb_delivery_fee" value="">
        </div>
        <?php
    }

    public function get_available_slots($product_id, $date) {
        $day_name = strtolower(date('l', strtotime($date)));
        $use_custom = get_post_meta($product_id, '_yfb_use_custom_availability', true);
        
        if ($use_custom === 'yes') {
            $enabled = get_post_meta($product_id, '_yfb_day_' . $day_name . '_enabled', true);
            if (empty($enabled)) {
                $enabled = 'yes';
            }
            $start_time = get_post_meta($product_id, '_yfb_day_' . $day_name . '_start', true) ?: '09:00';
            $end_time = get_post_meta($product_id, '_yfb_day_' . $day_name . '_end', true) ?: '17:00';
        } else {
            $enabled = get_option('yfb_default_day_' . $day_name . '_enabled', 'yes');
            $start_time = get_option('yfb_default_day_' . $day_name . '_start', '09:00');
            $end_time = get_option('yfb_default_day_' . $day_name . '_end', '17:00');
        }

        if ($enabled !== 'yes') {
            return array();
        }

        $duration = get_post_meta($product_id, '_yfb_duration', true) ?: 1;
        $buffer = get_post_meta($product_id, '_yfb_buffer_time', true) ?: 0;

        $min_notice = get_post_meta($product_id, '_yfb_min_booking_notice', true) ?: 24;
        $min_datetime = new DateTime('now', wp_timezone());
        $min_datetime->add(new DateInterval('PT' . $min_notice . 'H'));

        $slots = array();
        $current_time = new DateTime($date . ' ' . $start_time, wp_timezone());
        $end_datetime = new DateTime($date . ' ' . $end_time, wp_timezone());

        $bookings = $this->get_bookings_for_date_range($product_id, $date, $duration);

        while ($current_time < $end_datetime) {
            $is_available = true;

            if ($current_time < $min_datetime) {
                $is_available = false;
            }

            if ($is_available && !empty($bookings)) {
                $slot_start_date = new DateTime($date, wp_timezone());
                $slot_end_date = clone $slot_start_date;
                $slot_end_date->add(new DateInterval('P' . $duration . 'D'));

                foreach ($bookings as $booking) {
                    $booking_start = new DateTime($booking['start_date'] . ' ' . $booking['start_time'], wp_timezone());
                    $booking_end = new DateTime($booking['end_date'] . ' ' . $booking['end_time'], wp_timezone());

                    if (($slot_start_date < $booking_end && $slot_end_date > $booking_start)) {
                        $is_available = false;
                        break;
                    }
                }
            }

            $slots[] = array(
                'time' => $current_time->format('H:i'),
                'available' => $is_available,
            );

            $current_time->add(new DateInterval('PT1H'));
            
            if ($current_time >= $end_datetime) {
                break;
            }
        }

        return $slots;
    }

    private function get_bookings_for_date($product_id, $date) {
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
                    'key' => '_yfb_booking_date',
                    'value' => $date,
                ),
                array(
                    'key' => '_yfb_status',
                    'value' => 'cancelled',
                    'compare' => '!=',
                ),
            ),
        );

        $posts = get_posts($args);
        $bookings = array();

        foreach ($posts as $post) {
            $bookings[] = array(
                'start_time' => get_post_meta($post->ID, '_yfb_start_time', true),
                'end_time' => get_post_meta($post->ID, '_yfb_end_time', true),
            );
        }

        return $bookings;
    }

    private function get_bookings_for_date_range($product_id, $start_date, $duration_days) {
        $end_date = new DateTime($start_date, wp_timezone());
        $end_date->add(new DateInterval('P' . $duration_days . 'D'));
        $end_date_str = $end_date->format('Y-m-d');

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

        $posts = get_posts($args);
        $bookings = array();

        foreach ($posts as $post) {
            $booking_date = get_post_meta($post->ID, '_yfb_booking_date', true);
            $start_time = get_post_meta($post->ID, '_yfb_start_time', true);
            $end_time = get_post_meta($post->ID, '_yfb_end_time', true);
            
            $booking_start_dt = new DateTime($booking_date, wp_timezone());
            $booking_end_dt = new DateTime($booking_date . ' ' . $end_time, wp_timezone());
            
            $check_start_dt = new DateTime($start_date, wp_timezone());
            $check_end_dt = new DateTime($end_date_str, wp_timezone());
            
            if ($booking_start_dt < $check_end_dt && $booking_end_dt > $check_start_dt) {
                $bookings[] = array(
                    'start_date' => $booking_date,
                    'start_time' => $start_time,
                    'end_date' => $booking_end_dt->format('Y-m-d'),
                    'end_time' => $end_time,
                );
            }
        }

        return $bookings;
    }
}