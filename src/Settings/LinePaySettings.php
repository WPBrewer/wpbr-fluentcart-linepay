<?php

namespace WPBrewer\FluentCartLinePay\Settings;

use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;
use FluentCart\Api\StoreSettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\Framework\Support\Arr;

class LinePaySettings extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_linepay';
    
    public function __construct()
    {
        parent::__construct();
        
        $settings = fluent_cart_get_option($this->methodHandler, []);
        $defaults = static::getDefaults();
        
        if (!$settings || !is_array($settings)) {
            $settings = $defaults;
        } else {
            $settings = wp_parse_args($settings, $defaults);
        }
        
        if (is_array($settings)) {
            $settings = Arr::mergeMissingValues($settings, $defaults);
        }
        
        $this->settings = $settings;
    }
    
    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test',
            'test_channel_id' => '',
            'test_channel_secret' => '',
            'live_channel_id' => '',
            'live_channel_secret' => '',
            'test_is_encrypted' => 'no',
            'live_is_encrypted' => 'no',
            'payment_language' => 'zh-TW',
            'auto_capture' => 'yes',
        ];
    }
    
    public function isActive(): bool
    {
        return $this->settings['is_active'] === 'yes';
    }
    
    public function get($key = '')
    {
        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $this->settings;
    }
    
    public function getMode()
    {
        return (new StoreSettings)->get('order_mode');
    }
    
    public function getChannelId($mode = ''): string
    {
        if (!$mode) {
            $mode = $this->getMode();
        }
        return $this->get($mode . '_channel_id');
    }
    
    public function getChannelSecret($mode = ''): string
    {
        if (!$mode) {
            $mode = $this->getMode();
        }
        $secret = $this->get($mode . '_channel_secret');
        return Helper::decryptKey($secret);
    }
    
    public function getApiBaseUrl($mode = ''): string
    {
        if (!$mode) {
            $mode = $this->getMode();
        }
        
        return $mode === 'test'
            ? 'https://sandbox-api-pay.line.me'
            : 'https://api-pay.line.me';
    }
}

