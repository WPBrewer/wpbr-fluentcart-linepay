<?php

namespace WPBrewer\FluentCartLinePay\Gateways;

use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\Status;
use WPBrewer\FluentCartLinePay\Settings\LinePaySettings;
use WPBrewer\FluentCartLinePay\Gateways\API\LinePayAPI;

class Confirmations
{
    public function handleReturn()
    {
        $transactionId = $_GET['transactionId'] ?? '';
        $transactionUuid = $_GET['transaction_id'] ?? '';
        
        fluent_cart_add_log('LINE Pay Confirmation Started', wp_json_encode([
            'transaction_id' => $transactionUuid,
            'linepay_transaction_id' => $transactionId,
            'request_params' => $_REQUEST
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
        
        if (empty($transactionId) || empty($transactionUuid)) {
            fluent_cart_add_log('LINE Pay Confirmation Error', 'Missing transaction parameters', 'error', ['log_type' => 'payment']);
            $this->redirectToError(__('無效的交易資訊', 'wpbr-fluentcart-linepay'));
            return;
        }
        
        // Find transaction
        $transaction = OrderTransaction::where('uuid', $transactionUuid)->first();
        
        if (!$transaction) {
            fluent_cart_add_log('LINE Pay Confirmation Error', [
                'transaction_uuid' => $transactionUuid,
                'error' => 'Transaction not found'
            ], 'error', ['log_type' => 'payment']);
            
            $this->redirectToError(__('找不到交易記錄', 'wpbr-fluentcart-linepay'));
            return;
        }
        
        // Already processed
        if ($transaction->status === Status::TRANSACTION_SUCCEEDED) {
            fluent_cart_add_log('LINE Pay Confirmation Skipped', wp_json_encode([
                'transaction_id' => $transactionUuid,
                'reason' => 'Already processed'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
            
            $this->redirectToSuccess($transaction);
            return;
        }
        
        try {
            $settings = new LinePaySettings();
            $api = new LinePayAPI($settings);
            
            // Confirm payment
            $order = $transaction->order;
            // Convert amount from cents to actual TWD amount
            $amount = intval($order->total_amount / 100);
            $response = $api->confirmPayment(
                $transactionId,
                $amount,
                'TWD'
            );
            
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }
            
            if (isset($response['returnCode']) && $response['returnCode'] === '0000') {
                // Update transaction
                $transaction->vendor_charge_id = $transactionId;
                $transaction->status = Status::TRANSACTION_SUCCEEDED;
                $transaction->meta = array_merge($transaction->meta ?? [], [
                    'payment_note' => __('LINE Pay 付款成功', 'wpbr-fluentcart-linepay'),
                    'linepay_response' => $response
                ]);
                $transaction->save();
                
                fluent_cart_add_log('LINE Pay Payment Confirmed', wp_json_encode([
                    'order_id' => $order->id,
                    'transaction_id' => $transactionUuid,
                    'vendor_charge_id' => $transactionId,
                    'amount' => $order->total_amount
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
                
                // Use FluentCart's status helper to sync order and trigger events
                (new \FluentCart\App\Helpers\StatusHelper($order))
                    ->updateTotalPaid($order->total_amount)
                    ->syncOrderStatuses($transaction);
                
                $this->redirectToSuccess($transaction);
            } else {
                $errorMessage = $response['returnMessage'] ?? __('付款確認失敗', 'wpbr-fluentcart-linepay');
                
                fluent_cart_add_log('LINE Pay Confirmation Failed', wp_json_encode([
                    'transaction_id' => $transactionUuid,
                    'return_code' => $response['returnCode'] ?? 'unknown',
                    'return_message' => $errorMessage
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
                
                throw new \Exception($errorMessage);
            }
            
        } catch (\Exception $e) {
            fluent_cart_add_log('LINE Pay Confirmation Exception', wp_json_encode([
                'transaction_id' => $transactionUuid,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'error', ['log_type' => 'payment']);
            
            $this->redirectToError($e->getMessage());
        }
    }
    
    private function redirectToSuccess($transaction)
    {
        // Use the transaction's built-in receipt page URL method
        $receiptUrl = $transaction->getReceiptPageUrl(true);
        wp_redirect($receiptUrl);
        exit;
    }
    
    private function redirectToError($message)
    {
        $storeSettings = new \FluentCart\Api\StoreSettings();
        $checkoutUrl = $storeSettings->getCheckoutPage();
        $redirectUrl = add_query_arg('error', urlencode($message), $checkoutUrl);
        wp_redirect($redirectUrl);
        exit;
    }
}

