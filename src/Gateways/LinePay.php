<?php

namespace WPBrewer\FluentCartLinePay\Gateways;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\Api\CurrencySettings;
use WPBrewer\FluentCartLinePay\Settings\LinePaySettings;
use FluentCart\Framework\Support\Arr;
use FluentCart\App\Helpers\Helper;

class LinePay extends AbstractPaymentGateway
{
    public array $supportedFeatures = [
        'payment',
        'refund'
    ];
    
    public function __construct()
    {
        parent::__construct(new LinePaySettings());
    }
    
    public function meta(): array
    {
        return [
            'title' => __('LINE Pay', 'wpbr-fluentcart-linepay'),
            'route' => 'linepay',
            'slug' => 'linepay',
            'description' => __('使用 LINE Pay 安全付款', 'wpbr-fluentcart-linepay'),
            'logo' => WPBR_LINEPAY_URL . 'assets/images/linepay-logo47.png',
            'icon' => WPBR_LINEPAY_URL . 'assets/images/linepay-icon.png',
            'brand_color' => '#00C300',
            'status' => $this->settings->get('is_active') === 'yes',
            'upcoming' => false,
        ];
    }
    
    public function boot()
    {
        // Enqueue a small frontend helper to enable checkout button
        // Safe to enqueue everywhere - only activates on checkout when LINE Pay loads
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script(
                'wpbr-fluentcart-linepay-frontend',
                WPBR_LINEPAY_URL . 'assets/js/linepay-frontend.js',
                array(),
                WPBR_LINEPAY_VERSION,
                true
            );
            wp_localize_script('wpbr-fluentcart-linepay-frontend', 'fct_linepay_frontend', array(
                'method' => $this->getMeta('slug')
            ));
        });
    }
    
    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        return (new Processor())->handlePayment($paymentInstance);
    }
    
    public function processRefund($transaction, $amount, $args)
    {
        try {
            fluent_cart_add_log('LINE Pay Refund Started', wp_json_encode([
                'transaction_id' => $transaction->id,
                'vendor_charge_id' => $transaction->vendor_charge_id,
                'amount' => $amount,
                'order_id' => $transaction->order_id
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'refund']);
            
            if (empty($transaction->vendor_charge_id)) {
                fluent_cart_add_log('LINE Pay Refund Failed', 'Transaction ID is missing', 'error', [
                    'transaction_id' => $transaction->id,
                    'log_type' => 'refund'
                ]);
                return new \WP_Error(
                    'linepay_refund_error',
                    __('無法退款：缺少 LINE Pay 交易編號', 'wpbr-fluentcart-linepay')
                );
            }
            
            $settings = new LinePaySettings();
            $api = new API\LinePayAPI($settings);
            
            // Convert refund amount from cents to actual TWD amount
            $refundAmount = $amount ? intval($amount / 100) : null;
            
            $response = $api->refundPayment($transaction->vendor_charge_id, $refundAmount);
            
            if (is_wp_error($response)) {
                fluent_cart_add_log('LINE Pay Refund Error', wp_json_encode([
                    'transaction_id' => $transaction->id,
                    'error' => $response->get_error_message()
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'refund']);
                return $response;
            }
            
            if (isset($response['returnCode']) && $response['returnCode'] === '0000') {
                fluent_cart_add_log('LINE Pay Refund Success', wp_json_encode([
                    'transaction_id' => $transaction->id,
                    'vendor_charge_id' => $transaction->vendor_charge_id,
                    'refund_amount' => $refundAmount ?? 'full',
                    'refund_transaction_id' => $response['info']['refundTransactionId'] ?? null
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'refund']);
                
                return true;
            }
            
            $errorMessage = $response['returnMessage'] ?? __('退款失敗', 'wpbr-fluentcart-linepay');
            fluent_cart_add_log('LINE Pay Refund Failed', wp_json_encode([
                'transaction_id' => $transaction->id,
                'return_code' => $response['returnCode'] ?? 'unknown',
                'return_message' => $errorMessage
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'refund']);
            
            return new \WP_Error('linepay_refund_failed', $errorMessage);
            
        } catch (\Exception $e) {
            fluent_cart_add_log('LINE Pay Refund Exception', wp_json_encode([
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'refund']);
            
            return new \WP_Error('linepay_refund_exception', $e->getMessage());
        }
    }
    
    public function handleIPN(): void
    {
        // LINE Pay v3 uses redirect flow, not webhooks
        // This method is required but can be empty
    }
    
    public function getOrderInfo(array $data)
    {
        // This is called during checkout to prepare payment data
        // For redirect-based gateways like LINE Pay, we just need to signal readiness
        wp_send_json([
            'status' => 'success',
            'payment_args' => [
                'mode' => $this->settings->getMode(),
                'payment_method' => 'linepay',
            ],
            'data' => [],
            'message' => __('準備跳轉至 LINE Pay...', 'wpbr-fluentcart-linepay')
        ], 200);
    }
    
    public function fields(): array
    {
        return [
            'notice' => [
                'value' => $this->renderStoreModeNotice(),
                'label' => __('商店模式', 'wpbr-fluentcart-linepay'),
                'type' => 'notice'
            ],
            'taiwan_notice' => [
                'value' => '<p style="padding:10px;background:#e7f3ff;border-left:4px solid #2271b1;">' 
                    . __('此外掛專為台灣市場設計，僅支援新台幣 (TWD) 付款', 'wpbr-fluentcart-linepay') 
                    . '</p>',
                'label' => __('市場限制', 'wpbr-fluentcart-linepay'),
                'type' => 'html_attr'
            ],
            'payment_mode' => [
                'type' => 'tabs',
                'schema' => [
                    [
                        'type' => 'tab',
                        'label' => __('正式環境憑證', 'wpbr-fluentcart-linepay'),
                        'value' => 'live',
                        'schema' => [
                            'live_channel_id' => [
                                'value' => '',
                                'label' => __('Channel ID', 'wpbr-fluentcart-linepay'),
                                'type' => 'text',
                                'placeholder' => __('請輸入正式環境 Channel ID', 'wpbr-fluentcart-linepay'),
                                'help_text' => __('從 LINE Pay 開發者後台取得', 'wpbr-fluentcart-linepay')
                            ],
                            'live_channel_secret' => [
                                'value' => '',
                                'label' => __('Channel Secret', 'wpbr-fluentcart-linepay'),
                                'type' => 'password',
                                'placeholder' => __('請輸入正式環境 Channel Secret', 'wpbr-fluentcart-linepay'),
                                'help_text' => __('從 LINE Pay 開發者後台取得', 'wpbr-fluentcart-linepay')
                            ]
                        ]
                    ],
                    [
                        'type' => 'tab',
                        'label' => __('測試環境憑證', 'wpbr-fluentcart-linepay'),
                        'value' => 'test',
                        'schema' => [
                            'test_channel_id' => [
                                'value' => '',
                                'label' => __('Sandbox Channel ID', 'wpbr-fluentcart-linepay'),
                                'type' => 'text',
                                'placeholder' => __('請輸入測試環境 Channel ID', 'wpbr-fluentcart-linepay'),
                                'help_text' => __('從 LINE Pay Sandbox 取得', 'wpbr-fluentcart-linepay')
                            ],
                            'test_channel_secret' => [
                                'value' => '',
                                'label' => __('Sandbox Channel Secret', 'wpbr-fluentcart-linepay'),
                                'type' => 'password',
                                'placeholder' => __('請輸入測試環境 Channel Secret', 'wpbr-fluentcart-linepay'),
                                'help_text' => __('從 LINE Pay Sandbox 取得', 'wpbr-fluentcart-linepay')
                            ]
                        ]
                    ]
                ]
            ],
            'auto_capture' => [
                'value' => 'yes',
                'label' => __('自動請款', 'wpbr-fluentcart-linepay'),
                'type' => 'checkbox',
                'tooltip' => __('啟用後將自動完成請款（建議開啟）', 'wpbr-fluentcart-linepay')
            ]
        ];
    }
    
    public static function validateSettings($data): array
    {
        $mode = Arr::get($data, 'payment_mode', 'test');
        $channelId = Arr::get($data, $mode . '_channel_id');
        $channelSecret = Arr::get($data, $mode . '_channel_secret');
        
        if (empty($channelId) || empty($channelSecret)) {
            return [
                'status' => 'failed',
                'message' => __('請輸入 Channel ID 和 Channel Secret', 'wpbr-fluentcart-linepay')
            ];
        }
        
        return [
            'status' => 'success',
            'message' => __('LINE Pay 憑證設定成功！', 'wpbr-fluentcart-linepay')
        ];
    }
    
    public static function beforeSettingsUpdate($data, $oldSettings): array
    {
        $mode = Arr::get($data, 'payment_mode', 'test');
        $secretField = $mode . '_channel_secret';
        
        // Encrypt the channel secret
        if (!empty($data[$secretField])) {
            $data[$secretField] = Helper::encryptKey($data[$secretField]);
        }
        
        return $data;
    }
    
    public function isCurrencySupported(): bool
    {
        // Taiwan only - TWD currency
        $currentCurrency = CurrencySettings::get('currency');
        return strtoupper($currentCurrency) === 'TWD';
    }
    
    public static function register()
    {
        fluent_cart_api()->registerCustomPaymentMethod('linepay', new self());
    }
}

