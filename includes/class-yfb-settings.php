<?php

if (!defined('ABSPATH')) {
    exit;
}

class YFB_Settings {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_yfb_google_oauth_callback', array($this, 'handle_oauth_callback'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=yfb_booking',
            __('Settings', 'yard-fairy-booking'),
            __('Settings', 'yard-fairy-booking'),
            'manage_options',
            'yfb-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('yfb_settings_group', 'yfb_google_client_id');
        register_setting('yfb_settings_group', 'yfb_google_client_secret');
        register_setting('yfb_settings_group', 'yfb_google_calendar_id');
        
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        foreach ($days as $day) {
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_enabled');
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_start');
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_end');
        }

        add_settings_section(
            'yfb_availability_settings',
            __('Default Availability', 'yard-fairy-booking'),
            array($this, 'availability_settings_callback'),
            'yfb-settings'
        );

        add_settings_field(
            'yfb_default_availability',
            __('Default Available Days', 'yard-fairy-booking'),
            array($this, 'default_availability_callback'),
            'yfb-settings',
            'yfb_availability_settings'
        );

        add_settings_section(
            'yfb_google_settings',
            __('Google Calendar Integration', 'yard-fairy-booking'),
            array($this, 'google_settings_callback'),
            'yfb-settings'
        );

        add_settings_field(
            'yfb_google_client_id',
            __('Google Client ID', 'yard-fairy-booking'),
            array($this, 'client_id_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        add_settings_field(
            'yfb_google_client_secret',
            __('Google Client Secret', 'yard-fairy-booking'),
            array($this, 'client_secret_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        add_settings_field(
            'yfb_google_calendar_id',
            __('Google Calendar ID', 'yard-fairy-booking'),
            array($this, 'calendar_id_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        add_settings_field(
            'yfb_google_event_title',
            __('Event Title Template', 'yard-fairy-booking'),
            array($this, 'event_title_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        add_settings_field(
            'yfb_google_event_description',
            __('Event Description Template', 'yard-fairy-booking'),
            array($this, 'event_description_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        add_settings_field(
            'yfb_google_auth',
            __('Authorization', 'yard-fairy-booking'),
            array($this, 'auth_callback'),
            'yfb-settings',
            'yfb_google_settings'
        );

        register_setting('yfb_settings_group', 'yfb_google_event_title');
        register_setting('yfb_settings_group', 'yfb_google_event_description');
    }

    public function render_settings_page() {
        if (isset($_GET['auth'])) {
            if ($_GET['auth'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully connected to Google Calendar!', 'yard-fairy-booking') . '</p></div>';
            } elseif ($_GET['auth'] === 'error') {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to connect to Google Calendar. Please check your credentials and try again.', 'yard-fairy-booking') . '</p></div>';
            } elseif ($_GET['auth'] === 'no_refresh') {
                echo '<div class="notice notice-warning is-dismissible"><p>' . __('Google did not provide a refresh token. Please go to your <a href="https://myaccount.google.com/permissions" target="_blank">Google Account Permissions</a>, revoke access to this app, then authorize again.', 'yard-fairy-booking') . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('yfb_settings_group');
                do_settings_sections('yfb-settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Setup Instructions', 'yard-fairy-booking'); ?></h2>
            <div class="yfb-setup-instructions">
                <h3><?php _e('1. Create Google Cloud Project', 'yard-fairy-booking'); ?></h3>
                <ol>
                    <li><?php _e('Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>', 'yard-fairy-booking'); ?></li>
                    <li><?php _e('Create a new project or select an existing one', 'yard-fairy-booking'); ?></li>
                    <li><?php _e('Enable the Google Calendar API', 'yard-fairy-booking'); ?></li>
                </ol>

                <h3><?php _e('2. Create OAuth 2.0 Credentials', 'yard-fairy-booking'); ?></h3>
                <ol>
                    <li><?php _e('Go to APIs & Services > Credentials', 'yard-fairy-booking'); ?></li>
                    <li><?php _e('Click "Create Credentials" > "OAuth client ID"', 'yard-fairy-booking'); ?></li>
                    <li><?php _e('Choose "Web application"', 'yard-fairy-booking'); ?></li>
                    <li><?php _e('Add this redirect URI:', 'yard-fairy-booking'); ?>
                        <br><code><?php echo admin_url('admin-ajax.php?action=yfb_google_oauth_callback'); ?></code>
                        <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo admin_url('admin-ajax.php?action=yfb_google_oauth_callback'); ?>')">
                            <?php _e('Copy', 'yard-fairy-booking'); ?>
                        </button>
                    </li>
                    <li><?php _e('Copy the Client ID and Client Secret to the fields above', 'yard-fairy-booking'); ?></li>
                </ol>

                <h3><?php _e('3. Authorize Access', 'yard-fairy-booking'); ?></h3>
                <p><?php _e('After entering your Client ID and Secret, save the settings and click the "Authorize with Google" button.', 'yard-fairy-booking'); ?></p>
            </div>

            <style>
            .yfb-setup-instructions {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
            }
            .yfb-setup-instructions h3 {
                margin-top: 20px;
                margin-bottom: 10px;
            }
            .yfb-setup-instructions code {
                background: #fff;
                padding: 5px 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                display: inline-block;
                margin: 5px 0;
            }
            .yfb-auth-status {
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
            }
            .yfb-auth-status.connected {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            .yfb-auth-status.disconnected {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            </style>
        </div>
        <?php
    }

    public function availability_settings_callback() {
        echo '<p>' . __('Set the default availability for all bookable products. Products can override these settings individually.', 'yard-fairy-booking') . '</p>';
    }

    public function default_availability_callback() {
        $days = array(
            'monday' => __('Monday', 'yard-fairy-booking'),
            'tuesday' => __('Tuesday', 'yard-fairy-booking'),
            'wednesday' => __('Wednesday', 'yard-fairy-booking'),
            'thursday' => __('Thursday', 'yard-fairy-booking'),
            'friday' => __('Friday', 'yard-fairy-booking'),
            'saturday' => __('Saturday', 'yard-fairy-booking'),
            'sunday' => __('Sunday', 'yard-fairy-booking'),
        );

        echo '<table class="form-table">';
        foreach ($days as $key => $label) {
            $enabled = get_option('yfb_default_day_' . $key . '_enabled', 'yes');

            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="yfb_default_day_' . $key . '_enabled" value="yes" ' . checked($enabled, 'yes', false) . '> ' . __('Available', 'yard-fairy-booking') . '</label>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function google_settings_callback() {
        echo '<p>' . __('Configure your Google Calendar integration settings below.', 'yard-fairy-booking') . '</p>';
    }

    public function client_id_callback() {
        $value = get_option('yfb_google_client_id', '');
        echo '<input type="text" name="yfb_google_client_id" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function client_secret_callback() {
        $value = get_option('yfb_google_client_secret', '');
        echo '<input type="text" name="yfb_google_client_secret" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function event_title_callback() {
        $value = get_option('yfb_google_event_title', '{product_name} - {customer_name}');
        echo '<input type="text" name="yfb_google_event_title" value="' . esc_attr($value) . '" class="large-text">';
        echo '<p class="description">' . __('Available tags: {product_name}, {customer_name}, {customer_email}, {customer_phone}, {booking_date}, {order_id}', 'yard-fairy-booking') . '</p>';
    }

    public function event_description_callback() {
        $value = get_option('yfb_google_event_description', "Booking for {product_name}\nCustomer: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}");
        echo '<textarea name="yfb_google_event_description" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Available tags: {product_name}, {customer_name}, {customer_email}, {customer_phone}, {booking_date}, {order_id}', 'yard-fairy-booking') . '</p>';
    }

    public function calendar_id_callback() {
        $value = get_option('yfb_google_calendar_id', 'primary');
        $refresh_token = get_option('yfb_google_refresh_token');
        
        if ($refresh_token) {
            $calendars = $this->get_available_calendars();
            
            if (!empty($calendars)) {
                echo '<select name="yfb_google_calendar_id" class="regular-text">';
                foreach ($calendars as $calendar) {
                    $selected = selected($value, $calendar['id'], false);
                    echo '<option value="' . esc_attr($calendar['id']) . '" ' . $selected . '>' . esc_html($calendar['name']) . '</option>';
                }
                echo '</select>';
                echo '<p class="description">' . __('Select the calendar where bookings will be synced.', 'yard-fairy-booking') . '</p>';
            } else {
                echo '<input type="text" name="yfb_google_calendar_id" value="' . esc_attr($value) . '" class="regular-text">';
                echo '<p class="description">' . __('Enter "primary" for your main calendar or the specific calendar ID.', 'yard-fairy-booking') . '</p>';
            }
        } else {
            echo '<input type="text" name="yfb_google_calendar_id" value="' . esc_attr($value) . '" class="regular-text">';
            echo '<p class="description">' . __('Connect to Google Calendar first to see available calendars.', 'yard-fairy-booking') . '</p>';
        }
    }

    public function auth_callback() {
        if (isset($_POST['yfb_disconnect_google']) && wp_verify_nonce($_POST['yfb_disconnect_nonce'], 'yfb_disconnect_google')) {
            delete_option('yfb_google_refresh_token');
            delete_option('yfb_google_access_token');
            echo '<div class="notice notice-success inline"><p>' . __('Disconnected from Google Calendar.', 'yard-fairy-booking') . '</p></div>';
        }

        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        if (!$client_id || !$client_secret) {
            echo '<p class="yfb-auth-status disconnected">' . __('Please enter your Client ID and Client Secret first.', 'yard-fairy-booking') . '</p>';
            return;
        }

        if ($refresh_token) {
            echo '<p class="yfb-auth-status connected">' . __('✓ Connected to Google Calendar', 'yard-fairy-booking') . '</p>';
            echo '<button type="button" class="button button-secondary" onclick="if(confirm(\'' . esc_js(__('Are you sure you want to disconnect?', 'yard-fairy-booking')) . '\')) { document.getElementById(\'yfb-disconnect-form\').submit(); }">' . __('Disconnect', 'yard-fairy-booking') . '</button>';
            echo '<form id="yfb-disconnect-form" method="post" style="display:none;">';
            echo '<input type="hidden" name="yfb_disconnect_google" value="1">';
            wp_nonce_field('yfb_disconnect_google', 'yfb_disconnect_nonce');
            echo '</form>';
        } else {
            echo '<p class="yfb-auth-status disconnected">' . __('Not connected to Google Calendar', 'yard-fairy-booking') . '</p>';
            
            if (!file_exists(YFB_PLUGIN_DIR . 'vendor/autoload.php')) {
                echo '<div class="notice notice-warning inline"><p>';
                echo __('Google Calendar integration requires the Google API client library. Run <code>composer install</code> in the plugin directory or upload the plugin with vendor folder included.', 'yard-fairy-booking');
                echo '</p></div>';
            } else {
                $auth_url = $this->get_google_auth_url();
                if ($auth_url) {
                    echo '<a href="' . esc_url($auth_url) . '" class="button button-primary">' . __('Authorize with Google', 'yard-fairy-booking') . '</a>';
                }
            }
        }
    }

    private function get_google_auth_url() {
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');

        if (!$client_id || !$client_secret) {
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
        $client->setRedirectUri(admin_url('admin-ajax.php?action=yfb_google_oauth_callback'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes(array('https://www.googleapis.com/auth/calendar'));

        return $client->createAuthUrl();
    }

    public function handle_oauth_callback() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'yard-fairy-booking'));
        }

        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';

        if (!$code) {
            wp_die(__('Authorization code not received.', 'yard-fairy-booking'));
        }

        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');

        if (!class_exists('Google_Client')) {
            require_once YFB_PLUGIN_DIR . 'vendor/autoload.php';
        }

        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri(admin_url('admin-ajax.php?action=yfb_google_oauth_callback'));
        $client->setAccessType('offline');

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['access_token'])) {
                update_option('yfb_google_access_token', $token);
                
                if (isset($token['refresh_token'])) {
                    update_option('yfb_google_refresh_token', $token['refresh_token']);
                    wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=success'));
                    exit;
                } else {
                    $existing_refresh = get_option('yfb_google_refresh_token');
                    if ($existing_refresh) {
                        wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=success'));
                        exit;
                    } else {
                        error_log('YFB OAuth: No refresh token received. User may need to revoke access first.');
                        wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=no_refresh'));
                        exit;
                    }
                }
            } else {
                wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=error'));
                exit;
            }
        } catch (Exception $e) {
            error_log('YFB OAuth Error: ' . $e->getMessage());
            wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=error'));
            exit;
        }
    }

    private function get_available_calendars() {
        $autoload_path = YFB_PLUGIN_DIR . 'vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            return array();
        }

        if (!class_exists('Google_Client')) {
            require_once $autoload_path;
        }

        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        if (!$client_id || !$client_secret || !$refresh_token) {
            return array();
        }

        try {
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setAccessType('offline');
            $client->setAccessToken(get_option('yfb_google_access_token'));

            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($refresh_token);
                update_option('yfb_google_access_token', $client->getAccessToken());
            }

            $service = new Google_Service_Calendar($client);
            $calendar_list = $service->calendarList->listCalendarList();
            
            $calendars = array();
            foreach ($calendar_list->getItems() as $calendar) {
                $calendars[] = array(
                    'id' => $calendar->getId(),
                    'name' => $calendar->getSummary(),
                );
            }

            return $calendars;

        } catch (Exception $e) {
            error_log('YFB Calendar List Error: ' . $e->getMessage());
            return array();
        }
    }
}