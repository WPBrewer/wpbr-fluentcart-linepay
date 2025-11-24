<?php

namespace WPBrewer\FluentCartLinePay\Gateways;

use FluentCart\App\Services\Payments\PaymentInstance;
use WPBrewer\FluentCartLinePay\Settings\LinePaySettings;
use WPBrewer\FluentCartLinePay\Gateways\API\LinePayAPI;

class Processor
{
    public function handlePayment(PaymentInstance $paymentInstance)
    {
        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;
        
        try {
            fluent_cart_add_log('LINE Pay Payment Started', wp_json_encode([
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'amount' => $order->total_amount,
                'currency' => $order->currency
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
            
            // Check currency support
            if (strtoupper($order->currency) !== 'TWD') {
                fluent_cart_add_log('LINE Pay Currency Error', wp_json_encode([
                    'order_id' => $order->id,
                    'currency' => $order->currency
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
                
                throw new \Exception(__('LINE Pay 僅支援新台幣 (TWD) 付款', 'wpbr-fluentcart-linepay'));
            }
            
            $settings = new LinePaySettings();
            $api = new LinePayAPI($settings);
            
            // Convert amount from cents to actual TWD amount
            // FluentCart stores amounts in cents (800 = NT$8), but TWD is zero-decimal
            // LINE Pay expects actual amount without decimals (8 = NT$8)
            $amount = intval($order->total_amount / 100);
            
            // Prepare order data
            $orderData = [
                'amount' => $amount,
                'currency' => 'TWD',
                'order_id' => $order->id,
                'product_name' => sprintf(__('訂單 #%s', 'wpbr-fluentcart-linepay'), $order->id),
                'products' => $this->formatProducts($order->order_items),
                'confirm_url' => $this->getConfirmUrl($transaction),
                'cancel_url' => $this->getCancelUrl()
            ];
            
            // Request payment
            $response = $api->requestPayment($orderData);
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            if (isset($response['returnCode']) && $response['returnCode'] === '0000') {
                // Store transaction ID
                $transaction->vendor_charge_id = $response['info']['transactionId'];
                $transaction->save();
                
                fluent_cart_add_log('LINE Pay Payment Request Success', wp_json_encode([
                    'order_id' => $order->id,
                    'transaction_id' => $response['info']['transactionId'],
                    'payment_url' => $response['info']['paymentUrl']['web']
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
                
                // Return redirect response
                return [
                    'status' => 'success',
                    'redirect_to' => $response['info']['paymentUrl']['web'],
                    'message' => __('正在跳轉至 LINE Pay...', 'wpbr-fluentcart-linepay')
                ];
            }
            
            $errorMessage = $response['returnMessage'] ?? __('付款請求失敗', 'wpbr-fluentcart-linepay');
            fluent_cart_add_log('LINE Pay Payment Request Failed', wp_json_encode([
                'order_id' => $order->id,
                'return_code' => $response['returnCode'] ?? 'unknown',
                'return_message' => $errorMessage
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
            
            throw new \Exception($errorMessage);
            
        } catch (\Exception $e) {
            fluent_cart_add_log('LINE Pay Payment Exception', wp_json_encode([
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
            
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function formatProducts($items)
    {
        $products = [];
        
        // Handle empty items or ensure it's iterable
        if (!$items || (!is_array($items) && !is_iterable($items))) {
            // Return fallback product
            return [[
                'id' => '1',
                'name' => __('商品', 'wpbr-fluentcart-linepay'),
                'quantity' => 1,
                'price' => 1,
                'imageUrl' => ''
            ]];
        }
        
        foreach ($items as $item) {
            // Convert price from cents to actual TWD amount
            $unitPrice = (int) ($item->unit_price ?? 0);
            $unitPrice = intval($unitPrice / 100);
            
            $products[] = [
                'id' => (string) ($item->object_id ?? $item->id ?? '1'),
                'name' => mb_substr($item->title ?? $item->post_title ?? __('商品', 'wpbr-fluentcart-linepay'), 0, 4000),
                'quantity' => (int) ($item->quantity ?? 1),
                'price' => $unitPrice,
                'imageUrl' => '' // Optional but recommended
            ];
        }
        
        // LINE Pay requires at least one product
        if (empty($products)) {
            $products[] = [
                'id' => '1',
                'name' => __('商品', 'wpbr-fluentcart-linepay'),
                'quantity' => 1,
                'price' => 1,
                'imageUrl' => ''
            ];
        }
        
        return $products;
    }
    
    private function getConfirmUrl($transaction)
    {
        return add_query_arg([
            'fluent-cart' => 'linepay_confirm',
            'transaction_id' => $transaction->uuid
        ], site_url('/'));
    }
    
    private function getCancelUrl()
    {
        $storeSettings = new \FluentCart\Api\StoreSettings();
        $checkoutUrl = $storeSettings->getCheckoutPage();
        return add_query_arg([
            'fluent-cart' => 'linepay_cancel'
        ], $checkoutUrl);
    }
}

