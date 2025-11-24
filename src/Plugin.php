<?php

namespace WPBrewer\FluentCartLinePay;

class Plugin
{
    private static $instance = null;
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function init()
    {
        // Register gateway with FluentCart
        add_action('fluent_cart/init', [$this, 'registerGateway']);
        
        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);
        
        // Force zero-decimal display for TWD in FluentCart
        add_filter('fluent_cart/global_currency_setting', [$this, 'forceZeroDecimalsForTwd']);

        // Handle payment confirmation from LINE Pay
        add_action('fluent_cart_action_linepay_confirm', [$this, 'handleConfirmation']);
        
        // Handle payment cancellation
        add_action('fluent_cart_action_linepay_cancel', [$this, 'handleCancellation']);
    }
    
    public function registerGateway()
    {
        fluent_cart_api()->registerCustomPaymentMethod(
            'linepay',
            new Gateways\LinePay()
        );
    }
    
    public function forceZeroDecimalsForTwd($settings)
    {
        $currency = isset($settings['currency']) ? strtoupper($settings['currency']) : '';
        if ($currency === 'TWD') {
            $settings['is_zero_decimal'] = true;
            $settings['decimal_points'] = 0;
        }
        return $settings;
    }

    public function loadTextDomain()
    {
        load_plugin_textdomain(
            'wpbr-fluentcart-linepay',
            false,
            dirname(plugin_basename(WPBR_LINEPAY_FILE)) . '/languages'
        );
    }
    
    public function handleConfirmation()
    {
        $confirmHandler = new Gateways\Confirmations();
        $confirmHandler->handleReturn();
    }
    
    public function handleCancellation()
    {
        // Redirect back to checkout with error message
        $checkoutUrl = \FluentCart\Api\StoreSettings::getCheckoutPage();
        $message = __('付款已取消', 'wpbr-fluentcart-linepay');
        wp_redirect(add_query_arg('error', urlencode($message), $checkoutUrl));
        exit;
    }
}

