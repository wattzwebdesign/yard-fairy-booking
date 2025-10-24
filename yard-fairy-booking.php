<?php
/**
 * Plugin Name: Yard Fairies Booking
 * Plugin URI: https://codewattz.com
 * Description: WooCommerce booking plugin with Google Calendar integration and front-end calendar display
 * Version: 1.0.2
 * Author: Code Wattz
 * Author URI: https://codewattz.com
 * Text Domain: yard-fairy-booking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YFB_VERSION', '1.0.2');
define('YFB_PLUGIN_FILE', __FILE__);
define('YFB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YFB_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!class_exists('Yard_Fairy_Booking')) {

    class Yard_Fairy_Booking {

        private static $instance = null;

        public static function instance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct() {
            add_action('plugins_loaded', array($this, 'init'));
            add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        }

        public function declare_hpos_compatibility() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        }

        public function init() {
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            $this->includes();
            $this->init_hooks();
        }

        private function includes() {
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-booking-post-type.php';
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-product-booking.php';
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-google-calendar.php';
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-calendar-display.php';
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-ajax-handler.php';
            require_once YFB_PLUGIN_DIR . 'includes/class-yfb-settings.php';
        }

        private function init_hooks() {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

            YFB_Booking_Post_Type::instance();
            YFB_Product_Booking::instance();
            YFB_Google_Calendar::instance();
            YFB_Calendar_Display::instance();
            YFB_Ajax_Handler::instance();
            YFB_Settings::instance();
        }

        public function enqueue_scripts() {
            wp_enqueue_style('yfb-frontend', YFB_PLUGIN_URL . 'assets/css/frontend.css', array(), YFB_VERSION);
            wp_enqueue_script('yfb-frontend', YFB_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), YFB_VERSION, true);
            
            wp_localize_script('yfb-frontend', 'yfb_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yfb_ajax_nonce')
            ));
        }

        public function admin_enqueue_scripts() {
            wp_enqueue_style('yfb-admin', YFB_PLUGIN_URL . 'assets/css/admin.css', array(), YFB_VERSION);
            wp_enqueue_script('yfb-admin', YFB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), YFB_VERSION, true);
        }

        public function woocommerce_missing_notice() {
            echo '<div class="error"><p><strong>Yard Fairies Booking</strong> requires WooCommerce to be installed and active.</p></div>';
        }
    }
}

function YFB() {
    return Yard_Fairy_Booking::instance();
}

YFB();