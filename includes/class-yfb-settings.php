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
        add_action('admin_init', array($this, 'handle_disconnect'));
        add_action('admin_init', array($this, 'handle_google_settings_save'));
        add_action('wp_ajax_yfb_google_oauth_callback', array($this, 'handle_oauth_callback'));

        // DEBUG: Add hooks to track option changes
        add_action('updated_option', array($this, 'debug_option_update'), 10, 3);
        add_action('deleted_option', array($this, 'debug_option_delete'), 10, 1);
    }

    /**
     * Debug: Track option updates
     */
    public function debug_option_update($option_name, $old_value, $new_value) {
        if (strpos($option_name, 'yfb_google') === 0) {
            error_log('YFB DEBUG: Option UPDATED - ' . $option_name);
            if ($option_name === 'yfb_google_refresh_token' || $option_name === 'yfb_google_access_token') {
                error_log('YFB DEBUG: TOKEN CHANGED! Old: ' . (empty($old_value) ? 'EMPTY' : 'EXISTS') . ' New: ' . (empty($new_value) ? 'EMPTY' : 'EXISTS'));
                error_log('YFB DEBUG: Backtrace: ' . wp_debug_backtrace_summary());
            }
        }
    }

    /**
     * Debug: Track option deletions
     */
    public function debug_option_delete($option_name) {
        if (strpos($option_name, 'yfb_google') === 0) {
            error_log('YFB DEBUG: Option DELETED - ' . $option_name);
            if ($option_name === 'yfb_google_refresh_token' || $option_name === 'yfb_google_access_token') {
                error_log('YFB DEBUG: TOKEN DELETED!');
                error_log('YFB DEBUG: Backtrace: ' . wp_debug_backtrace_summary());
            }
        }
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
        // DEBUG: Log all registered options at init
        error_log('YFB DEBUG: Registering settings');

        // Google Calendar settings are handled separately - NOT part of this settings group
        register_setting('yfb_settings_group', 'yfb_home_base_address');
        register_setting('yfb_settings_group', 'yfb_included_mileage');
        register_setting('yfb_settings_group', 'yfb_delivery_fee');
        register_setting('yfb_settings_group', 'yfb_max_delivery_mileage');
        register_setting('yfb_settings_group', 'yfb_max_mileage_message');
        register_setting('yfb_settings_group', 'yfb_google_maps_api_key');

        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        foreach ($days as $day) {
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_enabled');
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_start');
            register_setting('yfb_settings_group', 'yfb_default_day_' . $day . '_end');
        }

        add_settings_section(
            'yfb_delivery_settings',
            __('Delivery Settings', 'yard-fairy-booking'),
            array($this, 'delivery_settings_callback'),
            'yfb-settings'
        );

        add_settings_field(
            'yfb_google_maps_api_key',
            __('Google Maps API Key', 'yard-fairy-booking'),
            array($this, 'google_maps_api_key_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

        add_settings_field(
            'yfb_home_base_address',
            __('Home Base Address', 'yard-fairy-booking'),
            array($this, 'home_base_address_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

        add_settings_field(
            'yfb_included_mileage',
            __('Included Mileage (miles)', 'yard-fairy-booking'),
            array($this, 'included_mileage_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

        add_settings_field(
            'yfb_delivery_fee',
            __('Delivery Fee', 'yard-fairy-booking'),
            array($this, 'delivery_fee_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

        add_settings_field(
            'yfb_max_delivery_mileage',
            __('Maximum Delivery Mileage (miles)', 'yard-fairy-booking'),
            array($this, 'max_delivery_mileage_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

        add_settings_field(
            'yfb_max_mileage_message',
            __('Max Mileage Exceeded Message', 'yard-fairy-booking'),
            array($this, 'max_mileage_message_callback'),
            'yfb-settings',
            'yfb_delivery_settings'
        );

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

        // Google Calendar settings are NOT registered here - handled separately
    }

    /**
     * Handle Google Calendar settings save (separate form)
     */
    public function handle_google_settings_save() {
        if (!isset($_POST['yfb_save_google_settings']) || !isset($_POST['yfb_google_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['yfb_google_settings_nonce'], 'yfb_save_google_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // DEBUG: Log before save
        error_log('YFB DEBUG: Before saving Google Calendar settings');
        error_log('YFB DEBUG: Refresh token exists: ' . (get_option('yfb_google_refresh_token') ? 'YES' : 'NO'));
        error_log('YFB DEBUG: Access token exists: ' . (get_option('yfb_google_access_token') ? 'YES' : 'NO'));

        // Save Google Calendar settings
        if (isset($_POST['yfb_google_client_id'])) {
            update_option('yfb_google_client_id', sanitize_text_field($_POST['yfb_google_client_id']));
            error_log('YFB DEBUG: Saved client_id');
        }

        if (isset($_POST['yfb_google_client_secret'])) {
            update_option('yfb_google_client_secret', sanitize_text_field($_POST['yfb_google_client_secret']));
            error_log('YFB DEBUG: Saved client_secret');
        }

        if (isset($_POST['yfb_google_calendar_id'])) {
            update_option('yfb_google_calendar_id', sanitize_text_field($_POST['yfb_google_calendar_id']));
            error_log('YFB DEBUG: Saved calendar_id');
        }

        if (isset($_POST['yfb_google_event_title'])) {
            update_option('yfb_google_event_title', sanitize_text_field($_POST['yfb_google_event_title']));
            error_log('YFB DEBUG: Saved event_title');
        }

        if (isset($_POST['yfb_google_event_description'])) {
            update_option('yfb_google_event_description', sanitize_textarea_field($_POST['yfb_google_event_description']));
            error_log('YFB DEBUG: Saved event_description');
        }

        // DEBUG: Log after save
        error_log('YFB DEBUG: After saving Google Calendar settings');
        error_log('YFB DEBUG: Refresh token exists: ' . (get_option('yfb_google_refresh_token') ? 'YES' : 'NO'));
        error_log('YFB DEBUG: Access token exists: ' . (get_option('yfb_google_access_token') ? 'YES' : 'NO'));

        // Redirect with success message
        wp_redirect(add_query_arg('google_saved', '1', admin_url('edit.php?post_type=yfb_booking&page=yfb-settings')));
        exit;
    }

    public function handle_disconnect() {
        if (isset($_POST['yfb_disconnect_google']) &&
            isset($_POST['yfb_disconnect_nonce']) &&
            wp_verify_nonce($_POST['yfb_disconnect_nonce'], 'yfb_disconnect_google') &&
            current_user_can('manage_options')) {

            delete_option('yfb_google_refresh_token');
            delete_option('yfb_google_access_token');

            wp_redirect(add_query_arg('disconnected', '1', admin_url('edit.php?post_type=yfb_booking&page=yfb-settings')));
            exit;
        }
    }

    public function render_settings_page() {
        if (isset($_GET['disconnected']) && $_GET['disconnected'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Successfully disconnected from Google Calendar.', 'yard-fairy-booking') . '</p></div>';
        }

        if (isset($_GET['google_saved']) && $_GET['google_saved'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Google Calendar settings saved successfully.', 'yard-fairy-booking') . '</p></div>';
        }

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

            <!-- Google Calendar Integration Section (Separate Form) -->
            <h2><?php _e('Google Calendar Integration', 'yard-fairy-booking'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('yfb_save_google_settings', 'yfb_google_settings_nonce'); ?>
                <input type="hidden" name="yfb_save_google_settings" value="1">

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="yfb_google_client_id"><?php _e('Google Client ID', 'yard-fairy-booking'); ?></label></th>
                            <td>
                                <input type="text" name="yfb_google_client_id" id="yfb_google_client_id" value="<?php echo esc_attr(get_option('yfb_google_client_id', '')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="yfb_google_client_secret"><?php _e('Google Client Secret', 'yard-fairy-booking'); ?></label></th>
                            <td>
                                <input type="text" name="yfb_google_client_secret" id="yfb_google_client_secret" value="<?php echo esc_attr(get_option('yfb_google_client_secret', '')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="yfb_google_calendar_id"><?php _e('Google Calendar ID', 'yard-fairy-booking'); ?></label></th>
                            <td>
                                <?php
                                $calendar_id_value = get_option('yfb_google_calendar_id', 'primary');
                                $refresh_token = get_option('yfb_google_refresh_token');

                                if ($refresh_token) {
                                    $calendars = $this->get_available_calendars();

                                    if (!empty($calendars)) {
                                        echo '<select name="yfb_google_calendar_id" id="yfb_google_calendar_id" class="regular-text">';
                                        foreach ($calendars as $calendar) {
                                            $selected = selected($calendar_id_value, $calendar['id'], false);
                                            echo '<option value="' . esc_attr($calendar['id']) . '" ' . $selected . '>' . esc_html($calendar['name']) . '</option>';
                                        }
                                        echo '</select>';
                                        echo '<p class="description">' . __('Select the calendar where bookings will be synced.', 'yard-fairy-booking') . '</p>';
                                    } else {
                                        echo '<input type="text" name="yfb_google_calendar_id" id="yfb_google_calendar_id" value="' . esc_attr($calendar_id_value) . '" class="regular-text">';
                                        echo '<p class="description">' . __('Enter "primary" for your main calendar or the specific calendar ID.', 'yard-fairy-booking') . '</p>';
                                    }
                                } else {
                                    echo '<input type="text" name="yfb_google_calendar_id" id="yfb_google_calendar_id" value="' . esc_attr($calendar_id_value) . '" class="regular-text">';
                                    echo '<p class="description">' . __('Connect to Google Calendar first to see available calendars.', 'yard-fairy-booking') . '</p>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="yfb_google_event_title"><?php _e('Event Title Template', 'yard-fairy-booking'); ?></label></th>
                            <td>
                                <input type="text" name="yfb_google_event_title" id="yfb_google_event_title" value="<?php echo esc_attr(get_option('yfb_google_event_title', '{product_name} - {customer_name}')); ?>" class="large-text">
                                <p class="description"><?php _e('Available tags: {product_name}, {customer_name}, {customer_email}, {customer_phone}, {booking_date}, {order_id}', 'yard-fairy-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="yfb_google_event_description"><?php _e('Event Description Template', 'yard-fairy-booking'); ?></label></th>
                            <td>
                                <textarea name="yfb_google_event_description" id="yfb_google_event_description" rows="5" class="large-text"><?php echo esc_textarea(get_option('yfb_google_event_description', "Booking for {product_name}\nCustomer: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}")); ?></textarea>
                                <p class="description"><?php _e('Available tags: {product_name}, {customer_name}, {customer_email}, {customer_phone}, {booking_date}, {order_id}', 'yard-fairy-booking'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Authorization', 'yard-fairy-booking'); ?></th>
                            <td>
                                <?php
                                $client_id = get_option('yfb_google_client_id');
                                $client_secret = get_option('yfb_google_client_secret');
                                $refresh_token = get_option('yfb_google_refresh_token');

                                if (!$client_id || !$client_secret) {
                                    echo '<p class="yfb-auth-status disconnected">' . __('Please enter your Client ID and Client Secret first.', 'yard-fairy-booking') . '</p>';
                                } elseif ($refresh_token) {
                                    echo '<p class="yfb-auth-status connected">' . __('✓ Connected to Google Calendar', 'yard-fairy-booking') . '</p>';
                                    // Disconnect button will be shown outside the form
                                } else {
                                    echo '<p class="yfb-auth-status disconnected">' . __('Not connected to Google Calendar', 'yard-fairy-booking') . '</p>';
                                    $auth_url = $this->get_google_auth_url();
                                    if ($auth_url) {
                                        echo '<a href="' . esc_url($auth_url) . '" class="button button-primary">' . __('Authorize with Google', 'yard-fairy-booking') . '</a>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Google Calendar Settings', 'yard-fairy-booking')); ?>
            </form>

            <?php
            // Disconnect form (separate, outside the main form)
            $refresh_token = get_option('yfb_google_refresh_token');
            if ($refresh_token) {
                ?>
                <form method="post" action="" style="margin-top: 10px;">
                    <?php wp_nonce_field('yfb_disconnect_google', 'yfb_disconnect_nonce'); ?>
                    <input type="hidden" name="yfb_disconnect_google" value="1">
                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to disconnect from Google Calendar?', 'yard-fairy-booking')); ?>');">
                        <?php _e('Disconnect from Google Calendar', 'yard-fairy-booking'); ?>
                    </button>
                </form>
                <?php
            }
            ?>

            <hr style="margin: 40px 0;">

            <!-- Main Settings Form -->
            <h2><?php _e('General Settings', 'yard-fairy-booking'); ?></h2>
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

    public function delivery_settings_callback() {
        echo '<p>' . __('Configure delivery address validation and fee calculation. A Google Maps API key is required for distance calculation.', 'yard-fairy-booking') . '</p>';
    }

    public function google_maps_api_key_callback() {
        $value = get_option('yfb_google_maps_api_key', '');
        echo '<input type="text" name="yfb_google_maps_api_key" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Get your API key from <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a>. Enable the Distance Matrix API.', 'yard-fairy-booking') . '</p>';
    }

    public function home_base_address_callback() {
        $value = get_option('yfb_home_base_address', '');
        echo '<input type="text" name="yfb_home_base_address" value="' . esc_attr($value) . '" class="large-text" placeholder="123 Main St, City, State ZIP">';
        echo '<p class="description">' . __('Enter your home base address. Delivery distances will be calculated from this location.', 'yard-fairy-booking') . '</p>';
    }

    public function included_mileage_callback() {
        $value = get_option('yfb_included_mileage', '10');
        echo '<input type="number" name="yfb_included_mileage" value="' . esc_attr($value) . '" min="0" step="0.1" class="small-text"> ' . __('miles', 'yard-fairy-booking');
        echo '<p class="description">' . __('Delivery within this distance is free. Distances beyond this will incur the delivery fee.', 'yard-fairy-booking') . '</p>';
    }

    public function delivery_fee_callback() {
        $value = get_option('yfb_delivery_fee', '25');
        echo '<input type="number" name="yfb_delivery_fee" value="' . esc_attr($value) . '" min="0" step="0.01" class="small-text"> ' . get_woocommerce_currency_symbol();
        echo '<p class="description">' . __('One-time fee added to the cart for deliveries beyond the included mileage.', 'yard-fairy-booking') . '</p>';
    }

    public function max_delivery_mileage_callback() {
        $value = get_option('yfb_max_delivery_mileage', '100');
        echo '<input type="number" name="yfb_max_delivery_mileage" value="' . esc_attr($value) . '" min="0" step="0.1" class="small-text"> ' . __('miles', 'yard-fairy-booking');
        echo '<p class="description">' . __('Maximum delivery distance allowed. Addresses beyond this distance cannot place orders.', 'yard-fairy-booking') . '</p>';
    }

    public function max_mileage_message_callback() {
        $value = get_option('yfb_max_mileage_message', 'Sorry, we cannot deliver to your location. Please contact us for alternative arrangements.');
        echo '<textarea name="yfb_max_mileage_message" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Message displayed when delivery address exceeds maximum mileage. The add to cart button will be hidden.', 'yard-fairy-booking') . '</p>';
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


    private function get_google_auth_url() {
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');

        if (!$client_id || !$client_secret) {
            return false;
        }

        $redirect_uri = admin_url('admin-ajax.php?action=yfb_google_oauth_callback');

        $params = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
        );

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
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
        $redirect_uri = admin_url('admin-ajax.php?action=yfb_google_oauth_callback');

        // Exchange authorization code for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ),
        ));

        if (is_wp_error($response)) {
            error_log('YFB OAuth Error: ' . $response->get_error_message());
            wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=error'));
            exit;
        }

        $body = wp_remote_retrieve_body($response);
        $token = json_decode($body, true);

        if (isset($token['access_token'])) {
            $token_data = array(
                'access_token' => $token['access_token'],
                'expires_at' => time() + (isset($token['expires_in']) ? $token['expires_in'] : 3600) - 300,
            );
            update_option('yfb_google_access_token', $token_data);

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
            error_log('YFB OAuth Error: ' . $body);
            wp_redirect(admin_url('edit.php?post_type=yfb_booking&page=yfb-settings&auth=error'));
            exit;
        }
    }

    private function get_available_calendars() {
        $gcal = YFB_Google_Calendar::instance();

        // Use the same method from the calendar class to get access token
        $client_id = get_option('yfb_google_client_id');
        $client_secret = get_option('yfb_google_client_secret');
        $refresh_token = get_option('yfb_google_refresh_token');

        if (!$client_id || !$client_secret || !$refresh_token) {
            return array();
        }

        // Get access token (will refresh if needed)
        $access_token_data = get_option('yfb_google_access_token');

        // Check if token is expired
        if ($access_token_data && isset($access_token_data['expires_at'])) {
            if (time() >= $access_token_data['expires_at']) {
                // Refresh the token
                $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                    'body' => array(
                        'client_id' => $client_id,
                        'client_secret' => $client_secret,
                        'refresh_token' => $refresh_token,
                        'grant_type' => 'refresh_token',
                    ),
                ));

                if (is_wp_error($response)) {
                    return array();
                }

                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (empty($data['access_token'])) {
                    return array();
                }

                $access_token_data = array(
                    'access_token' => $data['access_token'],
                    'expires_at' => time() + (isset($data['expires_in']) ? $data['expires_in'] : 3600) - 300,
                );
                update_option('yfb_google_access_token', $access_token_data);
            }
        } else {
            return array();
        }

        // Fetch calendar list
        $response = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token_data['access_token'],
            ),
        ));

        if (is_wp_error($response)) {
            error_log('YFB Calendar List Error: ' . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['items'])) {
            return array();
        }

        $calendars = array();
        foreach ($data['items'] as $calendar) {
            $calendars[] = array(
                'id' => $calendar['id'],
                'name' => $calendar['summary'],
            );
        }

        return $calendars;
    }
}