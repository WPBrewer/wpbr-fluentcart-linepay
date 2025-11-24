<?php
/**
 * Plugin Name: LINE Pay for FluentCart
 * Plugin URI: https://wpbrewer.com/plugins/wpbr-fluentcart-linepay
 * Description: LINE Pay payment gateway for FluentCart - Taiwan market
 * Version: 0.9.1
 * Author: WPBrewer
 * Author URI: https://wpbrewer.com
 * License: GPL v2 or later
 * Text Domain: wpbr-fluentcart-linepay
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 5.8
 */

defined('ABSPATH') || exit;

// Plugin constants
define('WPBR_LINEPAY_VERSION', '0.9.1');
define('WPBR_LINEPAY_FILE', __FILE__);
define('WPBR_LINEPAY_DIR', plugin_dir_path(__FILE__));
define('WPBR_LINEPAY_URL', plugin_dir_url(__FILE__));

// Composer autoloader
if (file_exists(WPBR_LINEPAY_DIR . 'vendor/autoload.php')) {
    require_once WPBR_LINEPAY_DIR . 'vendor/autoload.php';
}

// Initialize plugin
add_action('plugins_loaded', function() {
    // Check FluentCart dependency
    if (!class_exists('FluentCart\App\App')) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                __('LINE Pay for FluentCart requires FluentCart to be installed and activated.', 'wpbr-fluentcart-linepay')
            );
        });
        return;
    }
    
    // Initialize plugin
    WPBrewer\FluentCartLinePay\Plugin::getInstance()->init();
}, 20);
