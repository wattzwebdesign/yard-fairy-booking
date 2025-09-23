<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Booking_Post_Type {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_yfb_booking', array($this, 'save_booking_meta'));
        add_filter('manage_yfb_booking_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_yfb_booking_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('admin_footer-edit.php', array($this, 'add_sync_all_button'));
        add_action('wp_ajax_yfb_sync_all_bookings', array($this, 'ajax_sync_all_bookings'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => _x('Bookings', 'post type general name', 'yard-fairy-booking'),
            'singular_name'      => _x('Booking', 'post type singular name', 'yard-fairy-booking'),
            'menu_name'          => _x('Bookings', 'admin menu', 'yard-fairy-booking'),
            'add_new'            => _x('Add New', 'booking', 'yard-fairy-booking'),
            'add_new_item'       => __('Add New Booking', 'yard-fairy-booking'),
            'edit_item'          => __('Edit Booking', 'yard-fairy-booking'),
            'new_item'           => __('New Booking', 'yard-fairy-booking'),
            'view_item'          => __('View Booking', 'yard-fairy-booking'),
            'search_items'       => __('Search Bookings', 'yard-fairy-booking'),
            'not_found'          => __('No bookings found', 'yard-fairy-booking'),
            'not_found_in_trash' => __('No bookings found in Trash', 'yard-fairy-booking'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 56,
            'menu_icon'           => 'dashicons-calendar-alt',
            'supports'            => array('title'),
        );

        register_post_type('yfb_booking', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'yfb_booking_details',
            __('Booking Details', 'yard-fairy-booking'),
            array($this, 'booking_details_meta_box'),
            'yfb_booking',
            'normal',
            'high'
        );

        add_meta_box(
            'yfb_google_calendar_sync',
            __('Google Calendar Sync', 'yard-fairy-booking'),
            array($this, 'google_calendar_meta_box'),
            'yfb_booking',
            'side',
            'default'
        );
    }

    public function booking_details_meta_box($post) {
        wp_nonce_field('yfb_booking_meta_box', 'yfb_booking_meta_box_nonce');

        $product_id = get_post_meta($post->ID, '_yfb_product_id', true);
        $booking_date = get_post_meta($post->ID, '_yfb_booking_date', true);
        $start_time = get_post_meta($post->ID, '_yfb_start_time', true);
        $end_time = get_post_meta($post->ID, '_yfb_end_time', true);
        $customer_name = get_post_meta($post->ID, '_yfb_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_yfb_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_yfb_customer_phone', true);
        $status = get_post_meta($post->ID, '_yfb_status', true) ?: 'pending';
        $order_id = get_post_meta($post->ID, '_yfb_order_id', true);

        $products = wc_get_products(array(
            'limit' => -1,
            'meta_key' => '_yfb_bookable',
            'meta_value' => 'yes'
        ));

        ?>
        <table class="form-table">
            <tr>
                <th><label for="yfb_product_id"><?php _e('Product', 'yard-fairy-booking'); ?></label></th>
                <td>
                    <select name="yfb_product_id" id="yfb_product_id" style="width: 100%;">
                        <option value=""><?php _e('Select a product', 'yard-fairy-booking'); ?></option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($product_id, $product->get_id()); ?>>
                                <?php echo esc_html($product->get_name()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="yfb_booking_date"><?php _e('Booking Date', 'yard-fairy-booking'); ?></label></th>
                <td><input type="date" name="yfb_booking_date" id="yfb_booking_date" value="<?php echo esc_attr($booking_date); ?>" style="width: 100%;"></td>
            </tr>
            <tr>
                <th><label for="yfb_customer_name"><?php _e('Customer Name', 'yard-fairy-booking'); ?></label></th>
                <td><input type="text" name="yfb_customer_name" id="yfb_customer_name" value="<?php echo esc_attr($customer_name); ?>" style="width: 100%;"></td>
            </tr>
            <tr>
                <th><label for="yfb_customer_email"><?php _e('Customer Email', 'yard-fairy-booking'); ?></label></th>
                <td><input type="email" name="yfb_customer_email" id="yfb_customer_email" value="<?php echo esc_attr($customer_email); ?>" style="width: 100%;"></td>
            </tr>
            <tr>
                <th><label for="yfb_customer_phone"><?php _e('Customer Phone', 'yard-fairy-booking'); ?></label></th>
                <td><input type="tel" name="yfb_customer_phone" id="yfb_customer_phone" value="<?php echo esc_attr($customer_phone); ?>" style="width: 100%;"></td>
            </tr>
            <tr>
                <th><label for="yfb_status"><?php _e('Status', 'yard-fairy-booking'); ?></label></th>
                <td>
                    <select name="yfb_status" id="yfb_status" style="width: 100%;">
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'yard-fairy-booking'); ?></option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php _e('Confirmed', 'yard-fairy-booking'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'yard-fairy-booking'); ?></option>
                        <option value="completed" <?php selected($status, 'completed'); ?>><?php _e('Completed', 'yard-fairy-booking'); ?></option>
                    </select>
                </td>
            </tr>
            <?php if ($order_id): ?>
            <tr>
                <th><?php _e('Order', 'yard-fairy-booking'); ?></th>
                <td>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . absint($order_id) . '&action=edit')); ?>">
                        <?php printf(__('Order #%s', 'yard-fairy-booking'), $order_id); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function google_calendar_meta_box($post) {
        $gcal_event_id = get_post_meta($post->ID, '_yfb_gcal_event_id', true);
        $gcal_synced = get_post_meta($post->ID, '_yfb_gcal_synced', true);
        $gcal_last_sync = get_post_meta($post->ID, '_yfb_gcal_last_sync', true);

        ?>
        <div class="yfb-gcal-sync-status">
            <?php if ($gcal_synced && $gcal_event_id): ?>
                <p><strong><?php _e('Status:', 'yard-fairy-booking'); ?></strong> <span class="yfb-synced"><?php _e('Synced', 'yard-fairy-booking'); ?></span></p>
                <p><strong><?php _e('Event ID:', 'yard-fairy-booking'); ?></strong> <?php echo esc_html($gcal_event_id); ?></p>
                <?php if ($gcal_last_sync): ?>
                    <p><strong><?php _e('Last Synced:', 'yard-fairy-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gcal_last_sync))); ?></p>
                <?php endif; ?>
                <button type="button" class="button button-secondary yfb-resync-gcal" data-booking-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e('Re-sync to Google Calendar', 'yard-fairy-booking'); ?>
                </button>
            <?php else: ?>
                <p><strong><?php _e('Status:', 'yard-fairy-booking'); ?></strong> <span class="yfb-not-synced"><?php _e('Not Synced', 'yard-fairy-booking'); ?></span></p>
                <button type="button" class="button button-primary yfb-sync-gcal" data-booking-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e('Sync to Google Calendar', 'yard-fairy-booking'); ?>
                </button>
            <?php endif; ?>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.yfb-sync-gcal, .yfb-resync-gcal').on('click', function() {
                var $button = $(this);
                var bookingId = $button.data('booking-id');
                var action = $button.hasClass('yfb-sync-gcal') ? 'yfb_sync_to_gcal' : 'yfb_resync_to_gcal';
                var originalText = $button.text();
                
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top: 3px;"></span> Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: '<?php echo wp_create_nonce('yfb_ajax_nonce'); ?>',
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
        </script>
        <?php
    }

    public function save_booking_meta($post_id) {
        if (!isset($_POST['yfb_booking_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['yfb_booking_meta_box_nonce'], 'yfb_booking_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'yfb_product_id',
            'yfb_booking_date',
            'yfb_customer_name',
            'yfb_customer_email',
            'yfb_customer_phone',
            'yfb_status'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }

        if (isset($_POST['yfb_booking_date']) && isset($_POST['yfb_product_id'])) {
            $product_id = sanitize_text_field($_POST['yfb_product_id']);
            $booking_date = sanitize_text_field($_POST['yfb_booking_date']);
            $product = wc_get_product($product_id);
            
            if ($product) {
                $duration = get_post_meta($product_id, '_yfb_duration', true) ?: 1;
                $end_datetime = new DateTime($booking_date, wp_timezone());
                $end_datetime->add(new DateInterval('P' . $duration . 'D'));
                update_post_meta($post_id, '_yfb_end_date', $end_datetime->format('Y-m-d'));

                $customer_name = isset($_POST['yfb_customer_name']) ? sanitize_text_field($_POST['yfb_customer_name']) : '';
                $customer_email = isset($_POST['yfb_customer_email']) ? sanitize_text_field($_POST['yfb_customer_email']) : '';
                $customer_phone = isset($_POST['yfb_customer_phone']) ? sanitize_text_field($_POST['yfb_customer_phone']) : '';
                $order_id = get_post_meta($post_id, '_yfb_order_id', true);

                $title_template = get_option('yfb_google_event_title', '{product_name} - {customer_name}');
                
                $replacements = array(
                    '{product_name}' => $product->get_name(),
                    '{customer_name}' => $customer_name,
                    '{customer_email}' => $customer_email,
                    '{customer_phone}' => $customer_phone,
                    '{booking_date}' => date_i18n(get_option('date_format'), strtotime($booking_date)),
                    '{order_id}' => $order_id ? '#' . $order_id : '',
                );

                $booking_title = str_replace(array_keys($replacements), array_values($replacements), $title_template);
                
                remove_action('save_post_yfb_booking', array($this, 'save_booking_meta'));
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $booking_title,
                ));
                add_action('save_post_yfb_booking', array($this, 'save_booking_meta'));
            }
        }

        $old_status = get_post_meta($post_id, '_yfb_status', true);
        $new_status = isset($_POST['yfb_status']) ? sanitize_text_field($_POST['yfb_status']) : 'pending';

        if ($old_status !== $new_status) {
            do_action('yfb_booking_status_changed', $post_id, $old_status, $new_status);
        }

        if ($new_status === 'confirmed') {
            $gcal_event_id = get_post_meta($post_id, '_yfb_gcal_event_id', true);
            
            remove_action('save_post_yfb_booking', array($this, 'save_booking_meta'));
            $gcal = YFB_Google_Calendar::instance();
            $gcal->sync_booking($post_id);
            add_action('save_post_yfb_booking', array($this, 'save_booking_meta'));
        }
    }

    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['product'] = __('Product', 'yard-fairy-booking');
        $new_columns['booking_date'] = __('Booking Date', 'yard-fairy-booking');
        $new_columns['time'] = __('Time', 'yard-fairy-booking');
        $new_columns['customer'] = __('Customer', 'yard-fairy-booking');
        $new_columns['status'] = __('Status', 'yard-fairy-booking');
        $new_columns['gcal_sync'] = __('Google Calendar', 'yard-fairy-booking');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'product':
                $product_id = get_post_meta($post_id, '_yfb_product_id', true);
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        echo '<a href="' . esc_url(get_edit_post_link($product_id)) . '">' . esc_html($product->get_name()) . '</a>';
                    }
                }
                break;

            case 'booking_date':
                $booking_date = get_post_meta($post_id, '_yfb_booking_date', true);
                if ($booking_date) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($booking_date)));
                }
                break;

            case 'time':
                $start_time = get_post_meta($post_id, '_yfb_start_time', true);
                $end_time = get_post_meta($post_id, '_yfb_end_time', true);
                if ($start_time && $end_time) {
                    echo esc_html($start_time . ' - ' . $end_time);
                }
                break;

            case 'customer':
                $customer_name = get_post_meta($post_id, '_yfb_customer_name', true);
                $customer_email = get_post_meta($post_id, '_yfb_customer_email', true);
                if ($customer_name) {
                    echo esc_html($customer_name);
                    if ($customer_email) {
                        echo '<br><small>' . esc_html($customer_email) . '</small>';
                    }
                }
                break;

            case 'status':
                $status = get_post_meta($post_id, '_yfb_status', true) ?: 'pending';
                $status_labels = array(
                    'pending' => __('Pending', 'yard-fairy-booking'),
                    'confirmed' => __('Confirmed', 'yard-fairy-booking'),
                    'cancelled' => __('Cancelled', 'yard-fairy-booking'),
                    'completed' => __('Completed', 'yard-fairy-booking'),
                );
                echo '<span class="yfb-status yfb-status-' . esc_attr($status) . '">' . esc_html($status_labels[$status]) . '</span>';
                break;

            case 'gcal_sync':
                $gcal_synced = get_post_meta($post_id, '_yfb_gcal_synced', true);
                if ($gcal_synced) {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span>';
                } else {
                    echo '<span class="dashicons dashicons-minus" style="color: #ccc;"></span>';
                }
                break;
        }
    }

    public function add_sync_all_button() {
        global $current_screen;
        
        if ($current_screen->post_type !== 'yfb_booking') {
            return;
        }

        $refresh_token = get_option('yfb_google_refresh_token');
        if (!$refresh_token) {
            return;
        }

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wrap h1.wp-heading-inline').after('<button type="button" class="page-title-action yfb-sync-all-btn" style="margin-left: 10px;">Sync All to Google Calendar</button>');
            
            $('.yfb-sync-all-btn').on('click', function() {
                var $btn = $(this);
                
                if (!confirm('This will sync all bookings to Google Calendar. Continue?')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'yfb_sync_all_bookings',
                        nonce: '<?php echo wp_create_nonce('yfb_sync_all_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                            $btn.prop('disabled', false).text('Sync All to Google Calendar');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                        $btn.prop('disabled', false).text('Sync All to Google Calendar');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_sync_all_bookings() {
        check_ajax_referer('yfb_sync_all_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'yard-fairy-booking')));
        }

        $args = array(
            'post_type' => 'yfb_booking',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        $bookings = get_posts($args);
        $synced_count = 0;
        $error_count = 0;

        $gcal = YFB_Google_Calendar::instance();

        foreach ($bookings as $booking) {
            $status = get_post_meta($booking->ID, '_yfb_status', true);
            
            if ($status === 'cancelled') {
                continue;
            }

            $result = $gcal->sync_booking($booking->ID);
            
            if (is_wp_error($result)) {
                $error_count++;
                error_log('YFB Sync All Error for booking ' . $booking->ID . ': ' . $result->get_error_message());
            } else {
                $synced_count++;
            }
        }

        $message = sprintf(
            __('Synced %d bookings successfully.', 'yard-fairy-booking'),
            $synced_count
        );

        if ($error_count > 0) {
            $message .= ' ' . sprintf(
                __('%d bookings failed to sync.', 'yard-fairy-booking'),
                $error_count
            );
        }

        wp_send_json_success(array('message' => $message));
    }
}