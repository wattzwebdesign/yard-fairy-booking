<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Google_Calendar {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('yfb_booking_status_changed', array($this, 'sync_booking_on_status_change'), 10, 3);
        add_action('wp_ajax_yfb_sync_to_gcal', array($this, 'ajax_sync_to_gcal'));
        add_action('wp_ajax_yfb_resync_to_gcal', array($this, 'ajax_resync_to_gcal'));
    }

    public function get_client() {
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return false;
        }

        $autoload_path = YFB_PLUGIN_DIR . 'vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            return false;
        }

        if (!class_exists('Google_Client')) {
            require_once $autoload_path;
        }

        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setAccessType('offline');
        $client->setScopes(array('https://www.googleapis.com/auth/calendar'));

        $client->setAccessToken(get_option('yfb_google_access_token'));

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($refresh_token);
            update_option('yfb_google_access_token', $client->getAccessToken());
        }

        return $client;
    }

    public function sync_booking($booking_id) {
        $client = $this->get_client();
        if (!$client) {
            return new WP_Error('no_client', __('Google Calendar is not configured.', 'yard-fairy-booking'));
        }

        $booking_date = get_post_meta($booking_id, '_yfb_booking_date', true);
        $end_date = get_post_meta($booking_id, '_yfb_end_date', true);
        $product_id = get_post_meta($booking_id, '_yfb_product_id', true);
        $customer_name = get_post_meta($booking_id, '_yfb_customer_name', true);
        $customer_email = get_post_meta($booking_id, '_yfb_customer_email', true);

        if (empty($booking_date)) {
            return new WP_Error('missing_data', __('Booking date is required.', 'yard-fairy-booking'));
        }

        if (empty($end_date)) {
            $end_date = $booking_date;
        }

        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : __('Unknown Product', 'yard-fairy-booking');
        $order_id = get_post_meta($booking_id, '_yfb_order_id', true);

        $title_template = get_option('yfb_google_event_title', '{product_name} - {customer_name}');
        $description_template = get_option('yfb_google_event_description', "Booking for {product_name}\nCustomer: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}");

        $replacements = array(
            '{product_name}' => $product_name,
            '{customer_name}' => $customer_name,
            '{customer_email}' => $customer_email,
            '{customer_phone}' => get_post_meta($booking_id, '_yfb_customer_phone', true),
            '{booking_date}' => date_i18n(get_option('date_format'), strtotime($booking_date)),
            '{order_id}' => $order_id ? '#' . $order_id : '',
        );

        $event_title = str_replace(array_keys($replacements), array_values($replacements), $title_template);
        $event_description = str_replace(array_keys($replacements), array_values($replacements), $description_template);

        $calendar_id = get_option('yfb_google_calendar_id', 'primary');

        try {
            $service = new Google_Service_Calendar($client);

            $event = new Google_Service_Calendar_Event(array(
                'summary' => $event_title,
                'description' => $event_description,
                'start' => array(
                    'date' => $booking_date,
                ),
                'end' => array(
                    'date' => $end_date,
                ),
            ));

            $gcal_event_id = get_post_meta($booking_id, '_yfb_gcal_event_id', true);

            if ($gcal_event_id) {
                $updated_event = $service->events->update($calendar_id, $gcal_event_id, $event);
                $event_id = $updated_event->getId();
            } else {
                $created_event = $service->events->insert($calendar_id, $event);
                $event_id = $created_event->getId();
            }

            update_post_meta($booking_id, '_yfb_gcal_event_id', $event_id);
            update_post_meta($booking_id, '_yfb_gcal_synced', true);
            update_post_meta($booking_id, '_yfb_gcal_last_sync', current_time('mysql'));

            return $event_id;

        } catch (Exception $e) {
            return new WP_Error('sync_failed', $e->getMessage());
        }
    }

    public function delete_booking_from_calendar($booking_id) {
        $client = $this->get_client();
        if (!$client) {
            return false;
        }

        $gcal_event_id = get_post_meta($booking_id, '_yfb_gcal_event_id', true);
        if (!$gcal_event_id) {
            return false;
        }

        try {
            $service = new Google_Service_Calendar($client);
            $calendar_id = get_option('yfb_google_calendar_id', 'primary');
            $service->events->delete($calendar_id, $gcal_event_id);

            delete_post_meta($booking_id, '_yfb_gcal_event_id');
            delete_post_meta($booking_id, '_yfb_gcal_synced');
            delete_post_meta($booking_id, '_yfb_gcal_last_sync');

            return true;

        } catch (Exception $e) {
            error_log('YFB Google Calendar delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function sync_booking_on_status_change($booking_id, $old_status, $new_status) {
        if ($new_status === 'cancelled') {
            $this->delete_booking_from_calendar($booking_id);
        } elseif ($new_status === 'confirmed') {
            $this->sync_booking($booking_id);
        }
    }

    public function ajax_sync_to_gcal() {
        check_ajax_referer('yfb_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'yard-fairy-booking')));
        }

        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if (!$booking_id) {
            wp_send_json_error(array('message' => __('Invalid booking ID.', 'yard-fairy-booking')));
        }

        $result = $this->sync_booking($booking_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('Successfully synced to Google Calendar.', 'yard-fairy-booking'),
                'event_id' => $result
            ));
        }
    }

    public function ajax_resync_to_gcal() {
        $this->ajax_sync_to_gcal();
    }

    public function get_bookings_from_calendar($start_date, $end_date) {
        $client = $this->get_client();
        if (!$client) {
            return array();
        }

        try {
            $service = new Google_Service_Calendar($client);
            $calendar_id = get_option('yfb_google_calendar_id', 'primary');

            $start = new DateTime($start_date, wp_timezone());
            $end = new DateTime($end_date, wp_timezone());

            $events = $service->events->listEvents($calendar_id, array(
                'timeMin' => $start->format(DateTime::RFC3339),
                'timeMax' => $end->format(DateTime::RFC3339),
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ));

            return $events->getItems();

        } catch (Exception $e) {
            error_log('YFB Google Calendar fetch error: ' . $e->getMessage());
            return array();
        }
    }
}