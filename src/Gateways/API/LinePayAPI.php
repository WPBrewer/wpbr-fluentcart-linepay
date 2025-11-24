<?php

namespace WPBrewer\FluentCartLinePay\Gateways\API;

use WPBrewer\FluentCartLinePay\Settings\LinePaySettings;

class LinePayAPI
{
    private $settings;
    private $apiBaseUrl;
    
    public function __construct(LinePaySettings $settings)
    {
        $this->settings = $settings;
        $this->apiBaseUrl = $settings->getApiBaseUrl();
    }
    
    /**
     * Request payment - LINE Pay API v3
     */
    public function requestPayment($orderData)
    {
        $endpoint = '/v3/payments/request';
        
        $payload = [
            'amount' => (int) $orderData['amount'],
            'currency' => $orderData['currency'],
            'orderId' => (string) $orderData['order_id'],
            'packages' => [
                [
                    'id' => (string) ('pkg_' . $orderData['order_id']),
                    'amount' => (int) $orderData['amount'],
                    'products' => $orderData['products']
                ]
            ],
            'redirectUrls' => [
                'confirmUrl' => $orderData['confirm_url'],
                'cancelUrl' => $orderData['cancel_url']
            ]
        ];
        
        // Only add options if auto_capture is explicitly set
        if ($this->settings->get('auto_capture') === 'yes') {
            $payload['options'] = [
                'payment' => [
                    'capture' => true
                ]
            ];
        }
        
        fluent_cart_add_log('LINE Pay Payment Request', wp_json_encode([
            'order_id' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'payload' => $payload
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
        
        return $this->makeRequest('POST', $endpoint, $payload);
    }
    
    /**
     * Confirm payment after user authorization
     */
    public function confirmPayment($transactionId, $amount, $currency)
    {
        $endpoint = "/v3/payments/{$transactionId}/confirm";
        
        $payload = [
            'amount' => $amount,
            'currency' => $currency
        ];
        
        fluent_cart_add_log('LINE Pay Confirmation Request', wp_json_encode([
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
        
        return $this->makeRequest('POST', $endpoint, $payload);
    }
    
    /**
     * Refund payment - LINE Pay API v3
     */
    public function refundPayment($transactionId, $refundAmount = null)
    {
        $endpoint = "/v3/payments/{$transactionId}/refund";
        
        $payload = [];
        if ($refundAmount !== null) {
            $payload['refundAmount'] = $refundAmount;
        }
        
        fluent_cart_add_log('LINE Pay Refund Request', wp_json_encode([
            'transaction_id' => $transactionId,
            'refund_amount' => $refundAmount ?? 'full'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'refund']);
        
        return $this->makeRequest('POST', $endpoint, $payload);
    }
    
    /**
     * Generate signature for API request
     */
    private function generateSignature($channelSecret, $uri, $body, $nonce)
    {
        $data = $channelSecret . $uri . $body . $nonce;
        return base64_encode(hash_hmac('sha256', $data, $channelSecret, true));
    }
    
    /**
     * Make HTTP request to LINE Pay API
     */
    private function makeRequest($method, $endpoint, $payload = [])
    {
        $channelId = $this->settings->getChannelId();
        $channelSecret = $this->settings->getChannelSecret();
        
        if (empty($channelId) || empty($channelSecret)) {
            fluent_cart_add_log('LINE Pay API Error', 'Channel ID or Secret is missing', 'error', ['log_type' => 'payment']);
            return new \WP_Error('linepay_config_error', __('LINE Pay 設定不完整', 'wpbr-fluentcart-linepay'));
        }
        
        $nonce = wp_generate_uuid4();
        $uri = $endpoint;
        $body = json_encode($payload);
        
        $signature = $this->generateSignature($channelSecret, $uri, $body, $nonce);
        
        $headers = [
            'Content-Type' => 'application/json',
            'X-LINE-ChannelId' => $channelId,
            'X-LINE-Authorization-Nonce' => $nonce,
            'X-LINE-Authorization' => $signature
        ];
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ];
        
        // Log full request details
        fluent_cart_add_log('LINE Pay API Request Details', wp_json_encode([
            'endpoint' => $endpoint,
            'url' => $this->apiBaseUrl . $uri,
            'method' => $method,
            'headers' => $headers,
            'body' => json_decode($body, true),
            'body_raw' => $body
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
        
        $response = wp_remote_request($this->apiBaseUrl . $uri, $args);
        
        if (is_wp_error($response)) {
            fluent_cart_add_log('LINE Pay API Error', wp_json_encode([
                'endpoint' => $endpoint,
                'error' => $response->get_error_message()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
            return $response;
        }
        
        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $result = json_decode($responseBody, true);
        
        // Log response
        $logLevel = ($statusCode >= 200 && $statusCode < 300) ? 'info' : 'error';
        fluent_cart_add_log('LINE Pay API Response', wp_json_encode([
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'return_code' => $result['returnCode'] ?? null,
            'return_message' => $result['returnMessage'] ?? null,
            'full_response' => $result
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $logLevel, ['log_type' => 'payment']);
        
        return $result;
    }
}

