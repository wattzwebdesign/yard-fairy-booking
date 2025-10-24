<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Product_Booking {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('product_type_options', array($this, 'add_bookable_product_option'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_booking_fields'));
        add_action('woocommerce_admin_process_product_object', array($this, 'save_booking_fields'));
        add_action('woocommerce_add_to_cart_validation', array($this, 'validate_booking_data'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_booking_data_to_cart'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_booking_data_in_cart'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_prices'), 10, 1);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_booking_data_to_order_item'), 10, 4);
        add_action('woocommerce_order_status_processing', array($this, 'create_booking_from_order'));
        add_action('woocommerce_order_status_completed', array($this, 'create_booking_from_order'));
    }

    public function add_bookable_product_option($options) {
        $options['yfb_bookable'] = array(
            'id' => '_yfb_bookable',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label' => __('Bookable', 'yard-fairy-booking'),
            'description' => __('Enable booking for this product', 'yard-fairy-booking'),
            'default' => 'no',
        );
        return $options;
    }

    public function add_booking_fields() {
        global $product_object;

        if (!$product_object) {
            global $post;
            $product_object = wc_get_product($post->ID);
        }

        $product_id = $product_object ? $product_object->get_id() : 0;

        echo '<div class="options_group show_if_yfb_bookable">';

        echo '<p class="form-field">';
        echo '<label>' . __('Booking Duration', 'yard-fairy-booking') . '</label>';
        echo '<span class="wrap">';
        echo '<select name="_yfb_duration_type" id="_yfb_duration_type" class="short" style="width: auto; margin-right: 5px;">';
        $duration_type = $product_id ? get_post_meta($product_id, '_yfb_duration_type', true) : 'fixed';
        echo '<option value="fixed" ' . selected($duration_type, 'fixed', false) . '>' . __('Fixed blocks of', 'yard-fairy-booking') . '</option>';
        echo '<option value="customer" ' . selected($duration_type, 'customer', false) . '>' . __('Customer defined blocks of', 'yard-fairy-booking') . '</option>';
        echo '</select>';
        
        $duration = $product_id ? get_post_meta($product_id, '_yfb_duration', true) : '1';
        echo '<input type="number" name="_yfb_duration" id="_yfb_duration" value="' . esc_attr($duration) . '" placeholder="1" min="1" step="1" style="width: 60px; margin-right: 5px;">';
        
        $duration_unit = $product_id ? get_post_meta($product_id, '_yfb_duration_unit', true) : 'day';
        if (empty($duration_unit)) $duration_unit = 'day';
        echo '<select name="_yfb_duration_unit" id="_yfb_duration_unit" class="short" style="width: auto;">';
        echo '<option value="day" ' . selected($duration_unit, 'day', false) . '>' . __('Day(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="month" ' . selected($duration_unit, 'month', false) . '>' . __('Month(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="hour" ' . selected($duration_unit, 'hour', false) . '>' . __('Hour(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="minute" ' . selected($duration_unit, 'minute', false) . '>' . __('Minute(s)', 'yard-fairy-booking') . '</option>';
        echo '</select>';
        echo '</span>';
        echo '</p>';

        woocommerce_wp_select(array(
            'id' => '_yfb_calendar_display_mode',
            'label' => __('Calendar Display Mode', 'yard-fairy-booking'),
            'options' => array(
                'always_visible' => __('Calendar always visible', 'yard-fairy-booking'),
                'click_to_show' => __('Click to show calendar', 'yard-fairy-booking'),
            ),
            'desc_tip' => true,
            'description' => __('Choose how the booking calendar is displayed', 'yard-fairy-booking'),
        ));

        woocommerce_wp_checkbox(array(
            'id' => '_yfb_requires_confirmation',
            'label' => __('Requires Confirmation?', 'yard-fairy-booking'),
            'description' => __('Check this if booking requires admin approval. Payment will not be taken during checkout.', 'yard-fairy-booking'),
        ));

        woocommerce_wp_checkbox(array(
            'id' => '_yfb_can_be_cancelled',
            'label' => __('Can be Cancelled?', 'yard-fairy-booking'),
            'description' => __('Check this if booking can be cancelled by customer after purchase. A refund will not be sent automatically.', 'yard-fairy-booking'),
        ));

        echo '</div>';

        echo '<div class="options_group show_if_yfb_bookable">';

        woocommerce_wp_text_input(array(
            'id' => '_yfb_max_bookings_per_block',
            'label' => __('Max Bookings Per Block', 'yard-fairy-booking'),
            'placeholder' => '2',
            'desc_tip' => true,
            'description' => __('Maximum number of bookings allowed per time slot', 'yard-fairy-booking'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '1',
                'min' => '1',
            ),
            'value' => $product_id ? get_post_meta($product_id, '_yfb_max_bookings_per_block', true) : '2',
        ));

        echo '<p class="form-field">';
        echo '<label>' . __('Minimum Block Bookable', 'yard-fairy-booking') . '</label>';
        echo '<span class="wrap">';
        
        $min_block = $product_id ? get_post_meta($product_id, '_yfb_min_block_bookable', true) : '3';
        echo '<input type="number" name="_yfb_min_block_bookable" id="_yfb_min_block_bookable" value="' . esc_attr($min_block) . '" placeholder="3" min="0" step="1" style="width: 60px; margin-right: 5px;">';
        
        $min_unit = $product_id ? get_post_meta($product_id, '_yfb_min_block_bookable_unit', true) : 'day';
        echo '<select name="_yfb_min_block_bookable_unit" id="_yfb_min_block_bookable_unit" class="short" style="width: auto; margin-right: 5px;">';
        echo '<option value="day" ' . selected($min_unit, 'day', false) . '>' . __('Day(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="month" ' . selected($min_unit, 'month', false) . '>' . __('Month(s)', 'yard-fairy-booking') . '</option>';
        echo '</select>';
        
        echo __('into the future', 'yard-fairy-booking');
        echo '</span>';
        echo '</p>';

        echo '<p class="form-field">';
        echo '<label>' . __('Maximum Block Bookable', 'yard-fairy-booking') . '</label>';
        echo '<span class="wrap">';
        
        $max_block = $product_id ? get_post_meta($product_id, '_yfb_max_block_bookable', true) : '180';
        echo '<input type="number" name="_yfb_max_block_bookable" id="_yfb_max_block_bookable" value="' . esc_attr($max_block) . '" placeholder="3" min="1" step="1" style="width: 60px; margin-right: 5px;">';
        
        $max_unit = $product_id ? get_post_meta($product_id, '_yfb_max_block_bookable_unit', true) : 'day';
        echo '<select name="_yfb_max_block_bookable_unit" id="_yfb_max_block_bookable_unit" class="short" style="width: auto; margin-right: 5px;">';
        echo '<option value="day" ' . selected($max_unit, 'day', false) . '>' . __('Day(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="month" ' . selected($max_unit, 'month', false) . '>' . __('Month(s)', 'yard-fairy-booking') . '</option>';
        echo '</select>';
        
        echo __('into the future', 'yard-fairy-booking');
        echo '</span>';
        echo '</p>';

        echo '<p class="form-field">';
        echo '<label>' . __('Require a Buffer Period of', 'yard-fairy-booking') . '</label>';
        echo '<span class="wrap">';
        
        $buffer_period = $product_id ? get_post_meta($product_id, '_yfb_buffer_period', true) : '1';
        echo '<input type="number" name="_yfb_buffer_period" id="_yfb_buffer_period" value="' . esc_attr($buffer_period) . '" placeholder="1" min="0" step="1" style="width: 60px; margin-right: 5px;">';
        
        $buffer_unit = $product_id ? get_post_meta($product_id, '_yfb_buffer_unit', true) : 'day';
        if (empty($buffer_unit)) $buffer_unit = 'day';
        echo '<select name="_yfb_buffer_unit" id="_yfb_buffer_unit" class="short" style="width: auto; margin-right: 5px;">';
        echo '<option value="day" ' . selected($buffer_unit, 'day', false) . '>' . __('Day(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="month" ' . selected($buffer_unit, 'month', false) . '>' . __('Month(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="hour" ' . selected($buffer_unit, 'hour', false) . '>' . __('Hour(s)', 'yard-fairy-booking') . '</option>';
        echo '<option value="minute" ' . selected($buffer_unit, 'minute', false) . '>' . __('Minute(s)', 'yard-fairy-booking') . '</option>';
        echo '</select>';
        
        echo __('days between bookings', 'yard-fairy-booking');
        echo '</span>';
        echo '</p>';

        woocommerce_wp_checkbox(array(
            'id' => '_yfb_adjacent_buffering',
            'label' => __('Adjacent Buffering?', 'yard-fairy-booking'),
            'description' => __('By default buffer period applies forward. Enable to apply adjacently (before and after).', 'yard-fairy-booking'),
            'value' => $product_id ? get_post_meta($product_id, '_yfb_adjacent_buffering', true) : 'yes',
        ));

        echo '</div>';

        echo '<div class="options_group show_if_yfb_bookable">';

        woocommerce_wp_checkbox(array(
            'id' => '_yfb_use_custom_availability',
            'label' => __('Use Custom Availability', 'yard-fairy-booking'),
            'description' => __('Override default availability settings for this product', 'yard-fairy-booking'),
        ));

        $days = array(
            'monday' => __('Monday', 'yard-fairy-booking'),
            'tuesday' => __('Tuesday', 'yard-fairy-booking'),
            'wednesday' => __('Wednesday', 'yard-fairy-booking'),
            'thursday' => __('Thursday', 'yard-fairy-booking'),
            'friday' => __('Friday', 'yard-fairy-booking'),
            'saturday' => __('Saturday', 'yard-fairy-booking'),
            'sunday' => __('Sunday', 'yard-fairy-booking'),
        );

        echo '<div class="yfb-custom-availability-section">';
        echo '<p class="form-field"><label>' . __('Custom Available Days', 'yard-fairy-booking') . '</label></p>';

        foreach ($days as $key => $label) {
            $enabled = $product_id ? get_post_meta($product_id, '_yfb_day_' . $key . '_enabled', true) : '';

            echo '<p class="form-field yfb-availability-day">';
            echo '<label><input type="checkbox" name="_yfb_day_' . $key . '_enabled" value="yes" ' . checked($enabled, 'yes', false) . '> ' . $label . '</label>';
            echo '</p>';
        }
        echo '</div>';

        echo '</div>';
    }

    public function save_booking_fields($product) {
        $bookable_value = isset($_POST['_yfb_bookable']) ? 'yes' : 'no';
        $product->update_meta_data('_yfb_bookable', $bookable_value);

        $fields = array(
            '_yfb_duration_type',
            '_yfb_duration',
            '_yfb_duration_unit',
            '_yfb_calendar_display_mode',
            '_yfb_max_bookings_per_block',
            '_yfb_min_block_bookable',
            '_yfb_min_block_bookable_unit',
            '_yfb_max_block_bookable',
            '_yfb_max_block_bookable_unit',
            '_yfb_buffer_period',
            '_yfb_buffer_unit',
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                $product->update_meta_data($field, $value);
            }
        }

        $checkbox_fields = array(
            '_yfb_use_custom_availability',
            '_yfb_requires_confirmation',
            '_yfb_can_be_cancelled',
            '_yfb_adjacent_buffering',
        );

        foreach ($checkbox_fields as $field) {
            $value = isset($_POST[$field]) ? 'yes' : 'no';
            $product->update_meta_data($field, $value);
        }

        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        foreach ($days as $day) {
            $enabled = isset($_POST['_yfb_day_' . $day . '_enabled']) ? 'yes' : 'no';
            $product->update_meta_data('_yfb_day_' . $day . '_enabled', $enabled);
        }
    }

    public function display_booking_calendar() {
        global $product;

        $is_bookable = get_post_meta($product->get_id(), '_yfb_bookable', true);

        if ($is_bookable !== 'yes') {
            return;
        }

        echo '<div id="yfb-booking-calendar-container" data-product-id="' . esc_attr($product->get_id()) . '">';
        echo '<h3>' . __('Select Your Booking Date and Time', 'yard-fairy-booking') . '</h3>';
        echo '<div id="yfb-booking-calendar"></div>';
        echo '<input type="hidden" name="yfb_booking_date" id="yfb_booking_date" value="">';
        echo '<input type="hidden" name="yfb_booking_time" id="yfb_booking_time" value="">';
        echo '</div>';
    }

    public function validate_booking_data($passed, $product_id, $quantity) {
        $is_bookable = get_post_meta($product_id, '_yfb_bookable', true);

        if ($is_bookable === 'yes') {
            if (empty($_POST['yfb_booking_date'])) {
                wc_add_notice(__('Please select a booking date.', 'yard-fairy-booking'), 'error');
                return false;
            }

            $booking_date = sanitize_text_field($_POST['yfb_booking_date']);
            $booking_end_date = isset($_POST['yfb_booking_end_date']) ? sanitize_text_field($_POST['yfb_booking_end_date']) : $booking_date;

            // Validate delivery address
            if (empty($_POST['yfb_delivery_address'])) {
                wc_add_notice(__('Please enter a delivery address.', 'yard-fairy-booking'), 'error');
                return false;
            }

            foreach (WC()->cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $product_id && isset($cart_item['yfb_booking_date'])) {
                    $cart_start = $cart_item['yfb_booking_date'];
                    $cart_end = isset($cart_item['yfb_booking_end_date']) ? $cart_item['yfb_booking_end_date'] : $cart_start;

                    // Check for date overlap
                    if ($booking_date <= $cart_end && $booking_end_date >= $cart_start) {
                        wc_add_notice(__('You already have this product booked for overlapping dates in your cart.', 'yard-fairy-booking'), 'error');
                        return false;
                    }
                }
            }

            // Check availability for all dates in range
            if ($booking_end_date && $booking_end_date !== $booking_date) {
                $is_available = $this->check_date_range_availability($product_id, $booking_date, $booking_end_date);
            } else {
                $is_available = $this->check_date_availability($product_id, $booking_date);
            }

            if (!$is_available) {
                wc_add_notice(__('One or more dates in your selection are no longer available.', 'yard-fairy-booking'), 'error');
                return false;
            }
        }

        return $passed;
    }

    public function add_booking_data_to_cart($cart_item_data, $product_id) {
        $is_bookable = get_post_meta($product_id, '_yfb_bookable', true);

        if ($is_bookable === 'yes' && isset($_POST['yfb_booking_date'])) {
            $cart_item_data['yfb_booking_date'] = sanitize_text_field($_POST['yfb_booking_date']);

            if (isset($_POST['yfb_booking_end_date']) && !empty($_POST['yfb_booking_end_date'])) {
                $cart_item_data['yfb_booking_end_date'] = sanitize_text_field($_POST['yfb_booking_end_date']);

                // Calculate multi-day pricing
                $start = new DateTime($cart_item_data['yfb_booking_date']);
                $end = new DateTime($cart_item_data['yfb_booking_end_date']);
                $days = $start->diff($end)->days + 1;

                if ($days > 1) {
                    $cart_item_data['yfb_booking_days'] = $days;
                }
            }

            // Add delivery information
            if (isset($_POST['yfb_delivery_address']) && !empty($_POST['yfb_delivery_address'])) {
                $cart_item_data['yfb_delivery_address'] = sanitize_text_field($_POST['yfb_delivery_address']);
            }

            if (isset($_POST['yfb_delivery_distance']) && !empty($_POST['yfb_delivery_distance'])) {
                $cart_item_data['yfb_delivery_distance'] = floatval($_POST['yfb_delivery_distance']);
            }

            if (isset($_POST['yfb_delivery_fee']) && !empty($_POST['yfb_delivery_fee'])) {
                $cart_item_data['yfb_delivery_fee'] = floatval($_POST['yfb_delivery_fee']);
            }

            $cart_item_data['unique_key'] = md5(microtime().rand());
        }

        return $cart_item_data;
    }

    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['yfb_booking_date'])) {
            $cart_item['yfb_booking_date'] = $values['yfb_booking_date'];
        }
        if (isset($values['yfb_booking_end_date'])) {
            $cart_item['yfb_booking_end_date'] = $values['yfb_booking_end_date'];
        }
        if (isset($values['yfb_booking_days'])) {
            $cart_item['yfb_booking_days'] = $values['yfb_booking_days'];
        }
        if (isset($values['yfb_delivery_address'])) {
            $cart_item['yfb_delivery_address'] = $values['yfb_delivery_address'];
        }
        if (isset($values['yfb_delivery_distance'])) {
            $cart_item['yfb_delivery_distance'] = $values['yfb_delivery_distance'];
        }
        if (isset($values['yfb_delivery_fee'])) {
            $cart_item['yfb_delivery_fee'] = $values['yfb_delivery_fee'];
        }
        return $cart_item;
    }

    public function update_cart_item_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $base_price = floatval($product->get_price());

            if ($base_price <= 0) {
                $base_price = floatval($product->get_regular_price());
            }

            $total_price = $base_price;

            // Check if this is a multi-day booking
            if (isset($cart_item['yfb_booking_days']) && $cart_item['yfb_booking_days'] > 1) {
                // First day full price, subsequent days 50% off
                $days = intval($cart_item['yfb_booking_days']);
                $total_price = $base_price + (($days - 1) * ($base_price * 0.5));
            }

            // Add delivery fee if applicable (one-time fee per product)
            if (isset($cart_item['yfb_delivery_fee']) && $cart_item['yfb_delivery_fee'] > 0) {
                $total_price += floatval($cart_item['yfb_delivery_fee']);
            }

            if ($total_price != $base_price) {
                $cart_item['data']->set_price($total_price);
            }
        }
    }

    public function display_booking_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['yfb_booking_date'])) {
            if (isset($cart_item['yfb_booking_end_date']) && !empty($cart_item['yfb_booking_end_date'])) {
                $start_date = date_i18n(get_option('date_format'), strtotime($cart_item['yfb_booking_date']));
                $end_date = date_i18n(get_option('date_format'), strtotime($cart_item['yfb_booking_end_date']));
                $days = isset($cart_item['yfb_booking_days']) ? $cart_item['yfb_booking_days'] : 1;

                $item_data[] = array(
                    'name' => __('Booking Dates', 'yard-fairy-booking'),
                    'value' => sprintf('<strong>%s</strong> to <strong>%s</strong> (%d day%s)', $start_date, $end_date, $days, $days > 1 ? 's' : ''),
                );

                if ($days > 1) {
                    // Get base price for display
                    $product = $cart_item['data'];
                    $base_price = floatval($product->get_regular_price());

                    $item_data[] = array(
                        'name' => __('Pricing', 'yard-fairy-booking'),
                        'value' => sprintf(
                            __('Day 1: %s (full price)<br>Days 2-%d: %s each (50%% off)', 'yard-fairy-booking'),
                            wc_price($base_price),
                            $days,
                            wc_price($base_price * 0.5)
                        ),
                    );
                }
            } else {
                $item_data[] = array(
                    'name' => __('Booking Date', 'yard-fairy-booking'),
                    'value' => date_i18n(get_option('date_format'), strtotime($cart_item['yfb_booking_date'])),
                );
            }
        }

        // Display delivery information
        if (isset($cart_item['yfb_delivery_address']) && !empty($cart_item['yfb_delivery_address'])) {
            $item_data[] = array(
                'name' => __('Delivery Address', 'yard-fairy-booking'),
                'value' => esc_html($cart_item['yfb_delivery_address']),
            );

            if (isset($cart_item['yfb_delivery_distance'])) {
                $distance = floatval($cart_item['yfb_delivery_distance']);
                $included_mileage = floatval(get_option('yfb_included_mileage', 10));

                $item_data[] = array(
                    'name' => __('Delivery Distance', 'yard-fairy-booking'),
                    'value' => sprintf(__('%.2f miles', 'yard-fairy-booking'), $distance),
                );

                if (isset($cart_item['yfb_delivery_fee']) && $cart_item['yfb_delivery_fee'] > 0) {
                    $item_data[] = array(
                        'name' => __('Delivery Fee', 'yard-fairy-booking'),
                        'value' => sprintf(
                            __('%s (beyond %s mile included radius)', 'yard-fairy-booking'),
                            wc_price($cart_item['yfb_delivery_fee']),
                            $included_mileage
                        ),
                    );
                } else {
                    $item_data[] = array(
                        'name' => __('Delivery Fee', 'yard-fairy-booking'),
                        'value' => __('Free (within included radius)', 'yard-fairy-booking'),
                    );
                }
            }
        }

        return $item_data;
    }

    public function add_booking_data_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['yfb_booking_date'])) {
            if (isset($values['yfb_booking_end_date']) && !empty($values['yfb_booking_end_date'])) {
                $start_date = date_i18n('F j, Y', strtotime($values['yfb_booking_date']));
                $end_date = date_i18n('F j, Y', strtotime($values['yfb_booking_end_date']));
                $days = isset($values['yfb_booking_days']) ? $values['yfb_booking_days'] : 1;

                $item->add_meta_data(__('Booked Dates', 'yard-fairy-booking'), sprintf('%s to %s (%d day%s)', $start_date, $end_date, $days, $days > 1 ? 's' : ''));
                $item->add_meta_data('_yfb_booking_date', $values['yfb_booking_date']);
                $item->add_meta_data('_yfb_booking_end_date', $values['yfb_booking_end_date']);
                $item->add_meta_data('_yfb_booking_days', $days);
            } else {
                $formatted_date = date_i18n('F j, Y', strtotime($values['yfb_booking_date']));
                $item->add_meta_data(__('Booked Date', 'yard-fairy-booking'), $formatted_date);
                $item->add_meta_data('_yfb_booking_date', $values['yfb_booking_date']);
            }
        }

        // Add delivery information to order
        if (isset($values['yfb_delivery_address']) && !empty($values['yfb_delivery_address'])) {
            $item->add_meta_data(__('Delivery Address', 'yard-fairy-booking'), $values['yfb_delivery_address']);
            $item->add_meta_data('_yfb_delivery_address', $values['yfb_delivery_address']);
        }

        if (isset($values['yfb_delivery_distance'])) {
            $item->add_meta_data(__('Delivery Distance', 'yard-fairy-booking'), sprintf('%.2f miles', $values['yfb_delivery_distance']));
            $item->add_meta_data('_yfb_delivery_distance', $values['yfb_delivery_distance']);
        }

        if (isset($values['yfb_delivery_fee']) && $values['yfb_delivery_fee'] > 0) {
            $item->add_meta_data(__('Delivery Fee', 'yard-fairy-booking'), wc_price($values['yfb_delivery_fee']));
            $item->add_meta_data('_yfb_delivery_fee', $values['yfb_delivery_fee']);
        }
    }

    public function create_booking_from_order($order_id) {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item_id => $item) {
            $booking_date = $item->get_meta('_yfb_booking_date');

            if (!$booking_date) {
                continue;
            }

            $existing_booking = get_post_meta($order_id, '_yfb_booking_created_' . $item_id, true);
            if ($existing_booking) {
                continue;
            }

            $product_id = $item->get_product_id();
            $product = $item->get_product();

            // Check if this is a multi-day booking
            $booking_end_date_meta = $item->get_meta('_yfb_booking_end_date');
            if ($booking_end_date_meta) {
                $end_date = $booking_end_date_meta;
            } else {
                $duration = get_post_meta($product_id, '_yfb_duration', true) ?: 1;
                $end_datetime = new DateTime($booking_date, wp_timezone());
                $end_datetime->add(new DateInterval('P' . $duration . 'D'));
                $end_date = $end_datetime->format('Y-m-d');
            }

            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $customer_email = $order->get_billing_email();
            $customer_phone = $order->get_billing_phone();

            $title_template = get_option('yfb_google_event_title', '{product_name} - {customer_name}');
            
            $replacements = array(
                '{product_name}' => $product->get_name(),
                '{customer_name}' => $customer_name,
                '{customer_email}' => $customer_email,
                '{customer_phone}' => $customer_phone,
                '{booking_date}' => date_i18n(get_option('date_format'), strtotime($booking_date)),
                '{order_id}' => '#' . $order_id,
            );

            $booking_title = str_replace(array_keys($replacements), array_values($replacements), $title_template);

            $booking_id = wp_insert_post(array(
                'post_type' => 'yfb_booking',
                'post_title' => $booking_title,
                'post_status' => 'publish',
            ));

            if ($booking_id) {
                update_post_meta($booking_id, '_yfb_product_id', $product_id);
                update_post_meta($booking_id, '_yfb_booking_date', $booking_date);
                update_post_meta($booking_id, '_yfb_end_date', $end_date);
                update_post_meta($booking_id, '_yfb_customer_name', $customer_name);
                update_post_meta($booking_id, '_yfb_customer_email', $customer_email);
                update_post_meta($booking_id, '_yfb_customer_phone', $customer_phone);
                update_post_meta($booking_id, '_yfb_status', 'confirmed');
                update_post_meta($booking_id, '_yfb_order_id', $order_id);

                update_post_meta($order_id, '_yfb_booking_created_' . $item_id, $booking_id);

                do_action('yfb_booking_created_from_order', $booking_id, $order_id, $item_id);

                $gcal = YFB_Google_Calendar::instance();
                $gcal->sync_booking($booking_id);
            }
        }
    }

    private function check_date_range_availability($product_id, $start_date, $end_date) {
        $current_date = new DateTime($start_date, wp_timezone());
        $end = new DateTime($end_date, wp_timezone());

        // Check each date in the range
        while ($current_date <= $end) {
            if (!$this->check_date_availability($product_id, $current_date->format('Y-m-d'))) {
                return false;
            }
            $current_date->add(new DateInterval('P1D'));
        }

        return true;
    }

    private function check_date_availability($product_id, $date) {
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

        $max_bookings = get_post_meta($product_id, '_yfb_max_bookings_per_block', true) ?: 2;

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
            $booking_end = new DateTime(get_post_meta($booking->ID, '_yfb_end_date', true), wp_timezone());

            if ($start_date < $booking_end && $end_date > $booking_start) {
                $overlap_count++;
                if ($overlap_count >= $max_bookings) {
                    return false;
                }
            }
        }

        return true;
    }
}