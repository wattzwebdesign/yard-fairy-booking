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

    /**
     * Get a valid access token, refreshing if necessary
     */
    private function get_access_token() {
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return false;
        }

        $access_token_data = get_option('yfb_google_access_token');

        // Check if token is expired
        if ($access_token_data && isset($access_token_data['expires_at'])) {
            if (time() < $access_token_data['expires_at']) {
                return $access_token_data['access_token'];
            }
        }

        // Refresh the access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ),
        ));

        if (is_wp_error($response)) {
            error_log('YFB: Failed to refresh access token: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            error_log('YFB: No access token in refresh response: ' . $body);
            return false;
        }

        // Save the new access token with expiration time
        $token_data = array(
            'access_token' => $data['access_token'],
            'expires_at' => time() + (isset($data['expires_in']) ? $data['expires_in'] : 3600) - 300, // 5 min buffer
        );
        update_option('yfb_google_access_token', $token_data);

        return $data['access_token'];
    }

    /**
     * Make a request to the Google Calendar API
     */
    private function calendar_request($method, $endpoint, $body = null) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', __('Could not get access token.', 'yard-fairy-booking'));
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
        );

        if ($body !== null) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            return new WP_Error('api_error', $error_message, array('code' => $response_code, 'body' => $response_body));
        }

        return $data;
    }

    public function get_client() {
        // This method is kept for backwards compatibility
        // Returns true if credentials are configured, false otherwise
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        return !empty($client_id) && !empty($client_secret) && !empty($refresh_token);
    }

    public function sync_booking($booking_id) {
        if (!$this->get_client()) {
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

        $event_data = array(
            'summary' => $event_title,
            'description' => $event_description,
            'start' => array(
                'date' => $booking_date,
            ),
            'end' => array(
                'date' => $end_date,
            ),
        );

        $gcal_event_id = get_post_meta($booking_id, '_yfb_gcal_event_id', true);

        if ($gcal_event_id) {
            // Update existing event
            $endpoint = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
                urlencode($calendar_id),
                urlencode($gcal_event_id)
            );
            $result = $this->calendar_request('PUT', $endpoint, $event_data);
        } else {
            // Create new event
            $endpoint = sprintf(
                'https://www.googleapis.com/calendar/v3/calendars/%s/events',
                urlencode($calendar_id)
            );
            $result = $this->calendar_request('POST', $endpoint, $event_data);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['id'])) {
            return new WP_Error('sync_failed', __('Failed to get event ID from Google Calendar.', 'yard-fairy-booking'));
        }

        $event_id = $result['id'];

        update_post_meta($booking_id, '_yfb_gcal_event_id', $event_id);
        update_post_meta($booking_id, '_yfb_gcal_synced', true);
        update_post_meta($booking_id, '_yfb_gcal_last_sync', current_time('mysql'));

        return $event_id;
    }

    public function delete_booking_from_calendar($booking_id) {
        if (!$this->get_client()) {
            return false;
        }

        $gcal_event_id = get_post_meta($booking_id, '_yfb_gcal_event_id', true);
        if (!$gcal_event_id) {
            return false;
        }

        $calendar_id = get_option('yfb_google_calendar_id', 'primary');
        $endpoint = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            urlencode($calendar_id),
            urlencode($gcal_event_id)
        );

        $result = $this->calendar_request('DELETE', $endpoint);

        if (is_wp_error($result)) {
            error_log('YFB Google Calendar delete error: ' . $result->get_error_message());
            return false;
        }

        delete_post_meta($booking_id, '_yfb_gcal_event_id');
        delete_post_meta($booking_id, '_yfb_gcal_synced');
        delete_post_meta($booking_id, '_yfb_gcal_last_sync');

        return true;
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
        if (!$this->get_client()) {
            return array();
        }

        $calendar_id = get_option('yfb_google_calendar_id', 'primary');

        $start = new DateTime($start_date, wp_timezone());
        $end = new DateTime($end_date, wp_timezone());

        $endpoint = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?timeMin=%s&timeMax=%s&singleEvents=true&orderBy=startTime',
            urlencode($calendar_id),
            urlencode($start->format(DateTime::RFC3339)),
            urlencode($end->format(DateTime::RFC3339))
        );

        $result = $this->calendar_request('GET', $endpoint);

        if (is_wp_error($result)) {
            error_log('YFB Google Calendar fetch error: ' . $result->get_error_message());
            return array();
        }

        return isset($result['items']) ? $result['items'] : array();
    }
}
