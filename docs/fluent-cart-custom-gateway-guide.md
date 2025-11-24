# FluentCart Custom Payment Gateway Development Guide

Complete guide for extending FluentCart to register a custom payment gateway based on codebase analysis.

## Table of Contents
- [Overview](#overview)
- [Architecture](#architecture)
- [Required Components](#required-components)
- [Implementation Steps](#implementation-steps)
- [Code Examples](#code-examples)
- [Registration Process](#registration-process)
- [Key Methods Reference](#key-methods-reference)

---

## Overview

FluentCart uses a modular payment gateway system where custom gateways extend `AbstractPaymentGateway` and are registered via the global API. The system follows WordPress conventions with hooks, filters, and a clean separation of concerns.

### Core Files Involved
- `fluent-cart/app/Modules/PaymentMethods/Core/AbstractPaymentGateway.php` - Base gateway class
- `fluent-cart/app/Modules/PaymentMethods/Core/PaymentGatewayInterface.php` - Gateway interface
- `fluent-cart/app/Modules/PaymentMethods/Core/BaseGatewaySettings.php` - Settings base class
- `fluent-cart/app/Modules/PaymentMethods/Core/GatewayManager.php` - Gateway registry
- `fluent-cart/api/FluentCartGeneralApi.php` - Registration API
- `fluent-cart/boot/globals.php` - Global helper functions

---

## Architecture

```
Custom Gateway Plugin/Module
│
├── YourGatewaySettings.php (extends BaseGatewaySettings)
│   ├── Manages payment gateway settings
│   ├── Stores/retrieves credentials
│   └── Defines default configuration
│
├── YourGateway.php (extends AbstractPaymentGateway)
│   ├── implements PaymentGatewayInterface
│   ├── Processes payments
│   ├── Handles webhooks/IPN
│   └── Defines gateway metadata
│
└── Optional: YourGatewaySubscriptions.php (extends AbstractSubscriptionModule)
    └── Handles recurring billing
```

### Gateway Registration Flow

```
1. WordPress loads → fluent_cart/init action fires
2. Your gateway's register() method is called
3. fluent_cart_api()->registerCustomPaymentMethod() is invoked
4. GatewayManager::register() adds gateway to registry
5. Gateway boot() method is called (if exists)
6. Gateway appears in admin payment methods list
```

---

## Required Components

### 1. Settings Class (extends BaseGatewaySettings)

The settings class manages gateway configuration and credentials.

**Required Properties:**
- `$settings` - Array of gateway settings
- `$methodHandler` - Unique option key (e.g., `fluent_cart_payment_settings_your_gateway`)

**Required Methods:**
- `getDefaults()` - Return default settings array
- `get($key = '')` - Get setting by key or all settings
- `getMode()` - Return 'test' or 'live' mode
- `isActive()` - Check if gateway is enabled

**Example Structure:**
```php
namespace YourNamespace\PaymentMethods\YourGateway;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

class YourGatewaySettings extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_your_gateway';

    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test',
            'test_api_key' => '',
            'live_api_key' => '',
            'test_secret_key' => '',
            'live_secret_key' => '',
        ];
    }

    public function isActive(): bool
    {
        return $this->settings['is_active'] == 'yes';
    }

    public function getMode()
    {
        return (new StoreSettings)->get('order_mode');
    }

    public function get($key = '')
    {
        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $this->settings;
    }

    // Optional: Add helper methods
    public function getApiKey($mode = '')
    {
        if (!$mode) {
            $mode = $this->getMode();
        }
        return $this->get($mode . '_api_key');
    }
}
```

---

### 2. Gateway Class (extends AbstractPaymentGateway)

The main gateway class handles payment processing and gateway logic.

**Required Properties:**
- `$supportedFeatures` - Array of features: `['payment', 'refund', 'subscriptions', 'webhook']`

**Required Methods from PaymentGatewayInterface:**
- `meta()` - Gateway metadata (title, slug, logo, etc.)
- `makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)` - Process payments
- `handleIPN()` - Handle webhook/IPN notifications
- `getOrderInfo(array $data)` - Get order info for checkout
- `fields()` - Admin settings form configuration

**Optional Methods:**
- `boot()` - Initialize hooks, webhooks, IPN handlers
- `validateSettings($data)` - Validate API credentials
- `beforeSettingsUpdate($data, $oldSettings)` - Pre-process settings before save
- `getEnqueueScriptSrc($hasSubscription)` - Enqueue frontend scripts
- `getEnqueueStyleSrc()` - Enqueue frontend styles
- `getLocalizeData()` - Pass data to JavaScript
- `processRefund($transaction, $amount, $args)` - Handle refunds
- `isCurrencySupported()` - Check currency compatibility

---

## Implementation Steps

### Step 1: Create Settings Class

```php
<?php
namespace YourNamespace\PaymentMethods\YourGateway;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

class YourGatewaySettings extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_your_gateway';

    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test',
            'test_api_key' => '',
            'live_api_key' => '',
        ];
    }

    public function isActive(): bool
    {
        return $this->settings['is_active'] == 'yes';
    }

    public function getMode()
    {
        return (new StoreSettings)->get('order_mode');
    }

    public function get($key = '')
    {
        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $this->settings;
    }
}
```

### Step 2: Create Gateway Class

```php
<?php
namespace YourNamespace\PaymentMethods\YourGateway;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\Framework\Support\Arr;

class YourGateway extends AbstractPaymentGateway
{
    public array $supportedFeatures = ['payment', 'refund'];

    public function __construct()
    {
        parent::__construct(
            new YourGatewaySettings()
        );
    }

    /**
     * Gateway metadata - displayed in admin
     */
    public function meta(): array
    {
        return [
            'title' => __('Your Gateway', 'your-domain'),
            'route' => 'your_gateway',
            'slug' => 'your_gateway',
            'description' => __('Pay securely with Your Gateway', 'your-domain'),
            'logo' => plugins_url('assets/images/your-gateway-logo.svg', __FILE__),
            'icon' => plugins_url('assets/images/your-gateway-icon.svg', __FILE__),
            'brand_color' => '#3B82F6',
            'upcoming' => false, // Set true to show as "coming soon"
            'status' => $this->settings->get('is_active') === 'yes',
        ];
    }

    /**
     * Boot method - register hooks and webhooks
     */
    public function boot()
    {
        // Register webhook handler
        add_action('wp_ajax_nopriv_your_gateway_webhook', [$this, 'handleIPN']);
        add_action('wp_ajax_your_gateway_webhook', [$this, 'handleIPN']);
        
        // Register custom filters
        add_filter('fluent_cart/payment_methods_with_custom_checkout_buttons', function ($methods) {
            $methods[] = 'your_gateway';
            return $methods;
        });
    }

    /**
     * Process payment from checkout
     */
    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        try {
            $order = $paymentInstance->order;
            $items = $paymentInstance->items;
            
            // Your payment processing logic
            // Example: Create charge via API
            $charge = $this->createCharge([
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'order_id' => $order->id,
                // ... other data
            ]);

            if ($charge['success']) {
                // Update transaction
                $transaction = $paymentInstance->transaction;
                $transaction->vendor_charge_id = $charge['id'];
                $transaction->status = 'succeeded';
                $transaction->save();

                return [
                    'status' => 'success',
                    'message' => __('Payment processed successfully', 'your-domain'),
                    'redirect_to' => $this->getSuccessUrl($transaction)
                ];
            }

            throw new \Exception($charge['error_message']);

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook/IPN notifications
     */
    public function handleIPN()
    {
        // Verify webhook signature
        // Process webhook data
        // Update order/transaction status
        
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        // Process based on event type
        switch ($data['event_type']) {
            case 'payment.succeeded':
                // Handle successful payment
                break;
            case 'payment.failed':
                // Handle failed payment
                break;
        }
        
        http_response_code(200);
        exit;
    }

    /**
     * Get order info for checkout
     */
    public function getOrderInfo(array $data)
    {
        // Prepare checkout data
        $checkoutData = [
            'client_key' => $this->settings->getApiKey(),
            'amount' => $data['total_amount'],
            'currency' => $data['currency'],
        ];

        wp_send_json([
            'status' => 'success',
            'payment_args' => $checkoutData,
            'message' => __('Order info retrieved', 'your-domain')
        ], 200);
    }

    /**
     * Admin settings form fields
     */
    public function fields(): array
    {
        return [
            'notice' => [
                'value' => $this->renderStoreModeNotice(),
                'label' => __('Store Mode', 'your-domain'),
                'type' => 'notice'
            ],
            'payment_mode' => [
                'type' => 'tabs',
                'schema' => [
                    [
                        'type' => 'tab',
                        'label' => __('Live Credentials', 'your-domain'),
                        'value' => 'live',
                        'schema' => [
                            'live_api_key' => [
                                'value' => '',
                                'label' => __('Live API Key', 'your-domain'),
                                'type' => 'password',
                                'placeholder' => __('Your live API key', 'your-domain'),
                                'help_text' => __('Get your API key from gateway dashboard', 'your-domain')
                            ]
                        ]
                    ],
                    [
                        'type' => 'tab',
                        'label' => __('Test Credentials', 'your-domain'),
                        'value' => 'test',
                        'schema' => [
                            'test_api_key' => [
                                'value' => '',
                                'label' => __('Test API Key', 'your-domain'),
                                'type' => 'password',
                                'placeholder' => __('Your test API key', 'your-domain'),
                                'help_text' => __('Get your test API key from gateway dashboard', 'your-domain')
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Validate settings when saving
     */
    public static function validateSettings($data): array
    {
        $mode = Arr::get($data, 'payment_mode', 'test');
        $apiKey = Arr::get($data, $mode . '_api_key');

        if (empty($apiKey)) {
            return [
                'status' => 'failed',
                'message' => __('API key is required', 'your-domain')
            ];
        }

        // Test API connection
        try {
            // Your API validation logic
            return [
                'status' => 'success',
                'message' => __('Credentials verified successfully', 'your-domain')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process refunds
     */
    public function processRefund($transaction, $amount, $args)
    {
        try {
            // Your refund logic via API
            $refund = $this->createRefund([
                'charge_id' => $transaction->vendor_charge_id,
                'amount' => $amount,
            ]);

            if ($refund['success']) {
                return true;
            }

            return new \WP_Error('refund_failed', $refund['error_message']);

        } catch (\Exception $e) {
            return new \WP_Error('refund_error', $e->getMessage());
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        return [
            [
                'handle' => 'your-gateway-sdk',
                'src' => 'https://cdn.yourgateway.com/sdk.js',
            ],
            [
                'handle' => 'your-gateway-checkout',
                'src' => plugins_url('assets/js/checkout.js', __FILE__),
                'deps' => ['your-gateway-sdk']
            ]
        ];
    }

    /**
     * Pass data to frontend JavaScript
     */
    public function getLocalizeData(): array
    {
        return [
            'your_gateway_data' => [
                'api_key' => $this->settings->getApiKey(),
                'mode' => $this->settings->getMode(),
                'translations' => [
                    'Pay Now' => __('Pay Now', 'your-domain'),
                    'Payment Failed' => __('Payment Failed', 'your-domain'),
                ]
            ]
        ];
    }

    /**
     * Static registration method
     */
    public static function register()
    {
        fluent_cart_api()->registerCustomPaymentMethod('your_gateway', new self());
    }
}
```

### Step 3: Register Gateway

**In your plugin main file or module initialization:**

```php
<?php
// Method 1: Hook into fluent_cart/init
add_action('fluent_cart/init', function() {
    \YourNamespace\PaymentMethods\YourGateway\YourGateway::register();
});

// Method 2: Direct registration (if already in init context)
if (function_exists('fluent_cart_api')) {
    fluent_cart_api()->registerCustomPaymentMethod(
        'your_gateway', 
        new \YourNamespace\PaymentMethods\YourGateway\YourGateway()
    );
}
```

---

## Code Examples

### Example 1: Simple Cash on Delivery (Simplest Gateway)

From FluentCart Core's COD gateway:

```php
class Cod extends AbstractPaymentGateway
{
    public array $supportedFeatures = ['payment', 'refund'];

    public function __construct()
    {
        parent::__construct(new CodSettingsBase());
    }

    public function meta(): array
    {
        return [
            'title' => 'Cash',
            'route' => 'offline_payment',
            'slug' => 'offline_payment',
            'description' => __('Pay with cash upon delivery','fluent-cart'),
            'logo' => Vite::getAssetUrl("images/payment-methods/offline-payment.svg"),
            'icon' => Vite::getAssetUrl("images/payment-methods/cod-icon.svg"),
            'brand_color' => '#136196',
            'upcoming' => false,
            'status' => $this->settings->get('is_active') === 'yes',
        ];
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        return [
            'status' => 'success',
            'message' => __('Order has been placed successfully', 'fluent-cart'),
            'redirect_to' => $this->getSuccessUrl($paymentInstance->transaction)
        ];
    }

    public function handleIPN() { /* No IPN for COD */ }
    
    public function getOrderInfo(array $data) { /* No special order info */ }
    
    public function fields(): array
    {
        return [
            'cod_description' => [
                'value' => '<p>Customers can pay by cash upon delivery.</p>',
                'label' => __('Description', 'fluent-cart'),
                'type' => 'html_attr'
            ],
        ];
    }
}
```

### Example 2: Paddle Gateway (Full-Featured)

From FluentCart Pro's Paddle gateway:

```php
class Paddle extends AbstractPaymentGateway
{
    public array $supportedFeatures = [
        'payment',
        'refund',
        'webhook',
        'subscriptions',
        'custom_payment'
    ];

    public function __construct()
    {
        parent::__construct(
            new PaddleSettings(),
            new PaddleSubscriptions()  // Subscription support
        );

        add_filter('fluent_cart/payment_methods_with_custom_checkout_buttons', 
            function ($methods) {
                $methods[] = 'paddle';
                return $methods;
            }
        );
    }

    public function boot()
    {
        (new IPN())->init();
        (new Confirmations())->init();
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        if ($paymentInstance->subscription) {
            return (new Processor())->handleSubscriptionPayment($paymentInstance);
        }
        return (new Processor())->handleSinglePayment($paymentInstance);
    }

    public function processRefund($transaction, $amount, $args)
    {
        return PaddleHelper::processRemoteRefund($transaction, $amount, $args);
    }

    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        return [
            [
                'handle' => 'paddle-js-sdk',
                'src' => 'https://cdn.paddle.com/paddle/v2/paddle.js',
            ],
            [
                'handle' => 'fluent-cart-paddle-checkout',
                'src' => Vite::getEnqueuePath('public/payment-methods/paddle-checkout.js'),
                'deps' => ['paddle-js-sdk']
            ]
        ];
    }

    public static function register() {
        fluent_cart_api()->registerCustomPaymentMethod('paddle', new self());
    }
}
```

---

## Registration Process

### The Registration API

**Global Function:**
```php
fluent_cart_api()
```
Returns instance of `FluentCart\Api\FluentCartGeneralApi`

**Registration Method:**
```php
fluent_cart_api()->registerCustomPaymentMethod($name, $paymentGatewayInstance)
```

**Parameters:**
- `$name` (string) - Unique gateway identifier (e.g., 'your_gateway')
- `$paymentGatewayInstance` (AbstractPaymentGateway) - Instance of your gateway class

**What Happens During Registration:**

1. **Validation:** Checks if instance extends `AbstractPaymentGateway`
2. **Manager Registration:** Calls `GatewayManager::getInstance()->register()`
3. **Boot Method:** Calls gateway's `boot()` method if it exists
4. **Store Settings:** Injects `StoreSettings` instance via `setStoreSettings()`
5. **Gateway Storage:** Adds gateway to internal registry array

**From FluentCartGeneralApi.php:**
```php
public function registerCustomPaymentMethod($name, $paymentGatewayInstance)
{
    if(! $paymentGatewayInstance instanceof AbstractPaymentGateway) {
        throw new \Exception(sprintf(
            __('The payment gateway class "%s" is not valid. It must extend AbstractPaymentGateway.', 'fluent-cart'), 
            $paymentGatewayClass
        ));
    }
    
    (GatewayManager::getInstance())->register($name, $paymentGatewayInstance);
}
```

**From GatewayManager.php:**
```php
public function register(string $name, PaymentGatewayInterface $gateway)
{
    // Call boot method to allow each gateway to hook AJAX/IPN/webhooks
    if (method_exists($gateway, 'boot')) {
        $gateway->boot();
    }

    if (method_exists($gateway, 'setStoreSettings')) {
        $gateway->setStoreSettings(self::storeSettings());
    }

    $this->gateways[$name] = $gateway;
}
```

### Hook Timing

**Correct:** Use `fluent_cart/init` action
```php
add_action('fluent_cart/init', function() {
    YourGateway::register();
});
```

**Why?** Because FluentCart must be loaded first. The `fluent_cart/init` action fires after FluentCart core initialization.

**Incorrect:** Registering too early
```php
// DON'T DO THIS - FluentCart not loaded yet
add_action('plugins_loaded', function() {
    YourGateway::register(); // Will fail!
});
```

---

## Key Methods Reference

### AbstractPaymentGateway Methods

#### Core Abstract Methods (Must Implement)

```php
// Define gateway metadata
abstract public function meta(): array;

// Process payment from checkout
abstract public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance);

// Handle webhook/IPN notifications
abstract public function handleIPN();

// Get order information for checkout
abstract public function getOrderInfo(array $data);

// Define admin settings fields
abstract public function fields();
```

#### Utility Methods (Available to Use)

```php
// Get gateway metadata
public function getMeta($key = '');

// Check if feature is supported
public function has(string $feature): bool;

// Check if gateway is enabled
public function isEnabled(): bool;

// Get success URL after payment
public function getSuccessUrl($transaction, $args = []);

// Get webhook/IPN listener URL
protected function getListenerUrl($args = null);

// Get order by hash
public function getOrderByHash($orderHash);

// Update order and transaction data
public function updateOrderDataByOrder($order, $transactionData, $transaction);

// Validate payment method for checkout
public function validatePaymentMethod($data);

// Get checkout items
public function getCheckoutItems(): array;

// Render store mode notice (test/live)
public function renderStoreModeNotice(): string;
```

#### Hook Methods (Override to Customize)

```php
// Initialize hooks and webhooks
public function init(): void;

// Called after registration
public function boot();

// Before rendering payment method on checkout
public function beforeRenderPaymentMethod($hasSubscription): void;

// Render payment method (logo or text)
public function render($mode = 'logo');

// Validate settings before save
public static function validateSettings($data): array;

// Modify settings before save
public static function beforeSettingsUpdate($data, $oldSettings): array;

// Update settings
public function updateSettings($data);

// Process refund
public function processRefund($transaction, $amount, $args);

// Check currency support
public function isCurrencySupported(): bool;
```

#### Asset Loading Methods

```php
// Enqueue scripts
public function getEnqueueScriptSrc($hasSubscription): array;

// Enqueue styles
public function getEnqueueStyleSrc(): array;

// Get version for assets
public function getEnqueueVersion();

// Pass data to JavaScript
public function getLocalizeData(): array;
```

### BaseGatewaySettings Methods

```php
// Get default settings
abstract public static function getDefaults(): array;

// Get setting value(s)
abstract public function get($key = '');

// Get payment mode (test/live)
abstract public function getMode();

// Check if gateway is active
abstract public function isActive(): bool;

// Get cached settings
public function getCachedSettings();
```

---

## Field Types for settings form

The `fields()` method returns an array defining the admin settings form. Available field types:

### Basic Input Types
- `text` - Text input
- `password` - Password input (masked)
- `textarea` - Multi-line text
- `number` - Numeric input
- `email` - Email input
- `url` - URL input
- `color` - Color picker

### Selection Types
- `select` - Dropdown select
- `radio` - Radio buttons
- `checkbox` - Single checkbox

### Special Types
- `html_attr` - Raw HTML content
- `notice` - Info/warning notice
- `tabs` - Tabbed interface (for test/live credentials)

### Example Field Definition

```php
public function fields(): array
{
    return [
        'api_key' => [
            'value' => '',                              // Default value
            'label' => __('API Key', 'your-domain'),    // Field label
            'type' => 'password',                       // Field type
            'placeholder' => __('Enter key', 'your-domain'),
            'help_text' => __('Get from dashboard', 'your-domain'),
            'tooltip' => __('Required field', 'your-domain')
        ],
        'enable_logs' => [
            'value' => 'no',
            'label' => __('Enable Logging', 'your-domain'),
            'type' => 'checkbox',
        ],
        'webhook_mode' => [
            'value' => 'automatic',
            'label' => __('Webhook Mode', 'your-domain'),
            'type' => 'select',
            'options' => [
                'automatic' => [
                    'label' => __('Automatic', 'your-domain'),
                    'value' => 'automatic'
                ],
                'manual' => [
                    'label' => __('Manual', 'your-domain'),
                    'value' => 'manual'
                ]
            ]
        ],
        'payment_mode' => [
            'type' => 'tabs',
            'schema' => [
                [
                    'type' => 'tab',
                    'label' => __('Live', 'your-domain'),
                    'value' => 'live',
                    'schema' => [
                        'live_api_key' => [/* ... */]
                    ]
                ],
                [
                    'type' => 'tab',
                    'label' => __('Test', 'your-domain'),
                    'value' => 'test',
                    'schema' => [
                        'test_api_key' => [/* ... */]
                    ]
                ]
            ]
        ]
    ];
}
```

---

## Supported Features

Declare features in `$supportedFeatures` array:

```php
public array $supportedFeatures = [
    'payment',          // Basic payment processing
    'refund',           // Refund support
    'subscriptions',    // Recurring billing
    'webhook',          // Webhook/IPN notifications
    'custom_payment',   // Custom payment flow
];
```

Check feature support:
```php
if ($gateway->has('subscriptions')) {
    // Handle subscription
}
```

---

## Payment Processing Flow

### 1. Checkout Initiated
User clicks "Pay Now" → Frontend calls `getOrderInfo()`

### 2. Payment Instance Created
FluentCart creates `PaymentInstance` object containing:
- `$order` - Order model
- `$transaction` - Transaction model
- `$items` - Order items array
- `$subscription` - Subscription model (if applicable)

### 3. Process Payment
Gateway's `makePaymentFromPaymentInstance()` is called

### 4. Return Response
```php
// Success
return [
    'status' => 'success',
    'message' => 'Payment processed',
    'redirect_to' => $successUrl
];

// Failure
return [
    'status' => 'failed',
    'message' => 'Payment declined',
];
```

### 5. Webhook Notification (async)
Gateway sends webhook → `handleIPN()` processes → Update order status

---

## Helper Functions Available

```php
// Get FluentCart API instance
fluent_cart_api()

// Get/update options
fluent_cart_get_option($key, $default = '')
fluent_cart_update_option($key, $value)

// Logging
fluent_cart_add_log($title, $content, $status = 'info', $args = [])
fluent_cart_error_log($title, $content, $args = [])

// Get current product (in product context)
fluent_cart_get_current_product()
```

---

## Real-World Examples from FluentCart

### Minimal Gateway: Cash on Delivery (COD)
- **Location:** `fluent-cart/app/Modules/PaymentMethods/Cod/`
- **Features:** Basic payment only
- **Use Case:** Simple offline payment method
- **Files:** `Cod.php`, `CodSettingsBase.php`, `CodHandler.php`

### Full-Featured Gateway: Paddle
- **Location:** `fluent-cart-pro/app/Modules/PaymentMethods/PaddleGateway/`
- **Features:** Payment, refunds, subscriptions, webhooks
- **Use Case:** Complete payment gateway integration
- **Files:** 
  - `Paddle.php` - Main gateway
  - `PaddleSettings.php` - Settings management
  - `Processor.php` - Payment processing
  - `API/API.php` - API communication
  - `Webhook/IPN.php` - Webhook handling
  - `PaddleSubscriptions.php` - Subscription management
  - `Confirmations.php` - Payment confirmations

### LINE Pay Gateway (Redirect-Based with Confirmation)
- **Repository:** https://github.com/WPBrewer/wpbr-fluentcart-linepay
- **Features:** Payment, refunds, redirect flow, payment confirmation
- **Use Case:** Taiwan market payment gateway with LINE Pay v3 API
- **Key Components:**
  - `LinePay.php` - Main gateway class
  - `LinePaySettings.php` - Settings with encrypted credentials
  - `Processor.php` - Payment request handling
  - `Confirmations.php` - Payment confirmation after redirect
  - `API/LinePayAPI.php` - LINE Pay v3 API integration
  - `Plugin.php` - Action registration and zero-decimal currency handling

**Key Implementation Details:**
```php
// 1. Payment Request - Redirect to LINE Pay
public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
{
    $order = $paymentInstance->order;
    $amount = intval($order->total_amount / 100); // Convert for zero-decimal
    
    $response = $api->requestPayment([
        'amount' => $amount,
        'currency' => 'TWD',
        'order_id' => $order->id,
        'confirm_url' => $this->getConfirmUrl($transaction),
        'cancel_url' => $this->getCancelUrl()
    ]);
    
    return [
        'status' => 'success',
        'redirect_to' => $response['info']['paymentUrl']['web'],
        'message' => __('Redirecting to LINE Pay...', 'text-domain')
    ];
}

// 2. Confirm URL - Must use correct routing
private function getConfirmUrl($transaction)
{
    return add_query_arg([
        'fluent-cart' => 'linepay_confirm', // Action name here!
        'transaction_id' => $transaction->uuid
    ], site_url('/'));
}

// 3. Action Registration
add_action('fluent_cart_action_linepay_confirm', [$this, 'handleConfirmation']);

// 4. Confirmation Handler
public function handleConfirmation()
{
    $transactionId = $_GET['transactionId']; // From LINE Pay
    $transaction = OrderTransaction::where('uuid', $_GET['transaction_id'])->first();
    
    // Call confirm API
    $amount = intval($order->total_amount / 100);
    $response = $api->confirmPayment($transactionId, $amount, 'TWD');
    
    if ($response['returnCode'] === '0000') {
        // Update transaction
        $transaction->vendor_charge_id = $transactionId;
        $transaction->status = Status::TRANSACTION_SUCCEEDED;
        $transaction->meta = array_merge($transaction->meta ?? [], [
            'payment_note' => 'Payment successful',
            'linepay_response' => $response
        ]);
        $transaction->save();
        
        // Update order status properly
        (new StatusHelper($order))
            ->updateTotalPaid($order->total_amount)
            ->syncOrderStatuses($transaction);
        
        // Redirect to receipt page
        wp_redirect($transaction->getReceiptPageUrl(true));
        exit;
    }
}
```

### Other Core Examples
- **Stripe:** `fluent-cart/app/Modules/PaymentMethods/StripeGateway/`
- **PayPal:** `fluent-cart/app/Modules/PaymentMethods/PayPalGateway/`
- **Square:** `fluent-cart/app/Modules/PaymentMethods/SquareGateway/`
- **Razorpay:** `fluent-cart/app/Modules/PaymentMethods/RazorpayGateway/`

---

## Testing Your Gateway

### 1. Enable Test Mode
Set store to test mode in FluentCart settings

### 2. Add Gateway Credentials
Navigate to: **FluentCart → Settings → Payment Methods → Your Gateway**

### 3. Test Checkout Flow
1. Add product to cart
2. Go to checkout
3. Select your gateway
4. Complete payment
5. Verify order status

### 4. Test Webhooks
Use tools like:
- Webhook.site
- ngrok (for local testing)
- Postman

### 5. Check Logs
```php
fluent_cart_add_log('Gateway Debug', print_r($data, true), 'info', [
    'module_id' => $order->id,
    'module_name' => 'Order'
]);
```

---

## Common Issues & Solutions

### Issue: Gateway Not Appearing
**Solution:** Check hook timing - must use `fluent_cart/init` action

### Issue: Settings Not Saving
**Solution:** Ensure `$methodHandler` is unique and properly formatted

### Issue: Webhooks Not Working
**Solution:** Check webhook URL, verify signature validation, ensure proper HTTP response

### Issue: Payment Fails Silently
**Solution:** Add logging in `makePaymentFromPaymentInstance()`, check for exceptions

### Issue: Refunds Not Working
**Solution:** Implement `processRefund()` method properly, handle API errors

### Issue: Confirmation Handler Not Triggered (Shows Blog Page)
**Problem:** Using `?fluent-cart=fct_action&action=your_confirm` doesn't trigger your handler

**Solution:** FluentCart's routing uses the `fluent-cart` parameter value to determine which action to call:

```php
// ❌ WRONG - This won't work
return add_query_arg([
    'fluent-cart' => 'fct_action',
    'action' => 'your_confirm',
    'transaction_id' => $uuid
], site_url('/'));

// ✅ CORRECT - Use action name as fluent-cart value
return add_query_arg([
    'fluent-cart' => 'your_confirm',
    'transaction_id' => $uuid
], site_url('/'));
```

Then register the action:
```php
add_action('fluent_cart_action_your_confirm', [$this, 'handleConfirm']);
```

**Why:** FluentCart's routing (in `WebRoutes.php`) does:
```php
$actionName = sanitize_text_field($_REQUEST['fluent-cart']);
do_action('fluent_cart_action_' . $actionName, $_REQUEST);
```

### Issue: Database Error "Unknown column 'payment_note'"
**Problem:** Trying to save `$transaction->payment_note` causes SQL error

**Solution:** FluentCart transactions don't have a `payment_note` column. Use the `meta` JSON field instead:

```php
// ❌ WRONG - Column doesn't exist
$transaction->payment_note = 'Payment successful';
$transaction->save();

// ✅ CORRECT - Use meta field
$transaction->meta = array_merge($transaction->meta ?? [], [
    'payment_note' => 'Payment successful',
    'gateway_response' => $apiResponse
]);
$transaction->save();
```

### Issue: Wrong Amount Sent to Payment Gateway (Off by 100x)
**Problem:** Sending NT$8 but gateway receives NT$800

**Solution:** FluentCart stores amounts in **cents** (smallest currency unit). For **zero-decimal currencies** (JPY, TWD, KRW, etc.), divide by 100:

```php
// FluentCart stores NT$8 as 800 (cents)
$order->total_amount; // Returns 800

// For zero-decimal currencies, convert to actual amount
$amount = intval($order->total_amount / 100); // 8

// For regular currencies (USD, EUR), keep as-is
$amount = intval($order->total_amount); // 800 cents = $8.00
```

**Force Zero-Decimal Display:**
```php
add_filter('fluent_cart/global_currency_setting', function($settings) {
    if (strtoupper($settings['currency']) === 'TWD') {
        $settings['is_zero_decimal'] = true;
        $settings['decimal_points'] = 0;
    }
    return $settings;
});
```

### Issue: Order Status Not Updating After Payment
**Problem:** Transaction succeeds but order stays in "Pending" status

**Solution:** Use FluentCart's `StatusHelper` to properly sync order status and trigger events:

```php
// ❌ WRONG - Manually updating doesn't trigger events
$order->payment_status = 'paid';
$order->save();

// ✅ CORRECT - Use StatusHelper
use FluentCart\App\Helpers\StatusHelper;

(new StatusHelper($order))
    ->updateTotalPaid($order->total_amount)
    ->syncOrderStatuses($transaction);
```

This automatically:
- Updates order payment status
- Triggers `OrderPaid` event
- Sends confirmation emails
- Completes cart
- Runs post-payment hooks

---

## Best Practices

1. **Error Handling:** Always wrap API calls in try-catch blocks
2. **Logging:** Use `fluent_cart_add_log()` with proper formatting (see Logging Best Practices below)
3. **Security:** Validate webhook signatures, sanitize input data
4. **Translations:** Use `__()` for all user-facing strings
5. **Assets:** Enqueue scripts/styles only when needed
6. **Testing:** Test in both test and live modes
7. **Documentation:** Comment your code thoroughly
8. **Validation:** Validate settings before saving
9. **Currency Support:** Check currency compatibility and handle zero-decimal currencies
10. **Subscriptions:** Implement `AbstractSubscriptionModule` for recurring billing

### Logging Best Practices

FluentCart's `fluent_cart_add_log()` expects the `$content` parameter to be a **string**, not an array.

```php
// ❌ WRONG - Arrays display as empty content
fluent_cart_add_log('Payment Request', [
    'order_id' => $order->id,
    'amount' => $amount
], 'info', ['log_type' => 'payment']);

// ✅ CORRECT - Use wp_json_encode with formatting
fluent_cart_add_log('Payment Request', wp_json_encode([
    'order_id' => $order->id,
    'amount' => $amount,
    'currency' => $order->currency,
    'api_response' => $response
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
```

**Log Types:**
- `'info'` - General information
- `'error'` - Errors and failures
- `'warning'` - Warnings
- `'success'` - Successful operations

**Log Metadata:**
```php
fluent_cart_add_log('Title', 'Content', 'info', [
    'log_type' => 'payment',      // payment, refund, api, activity
    'module_id' => $order->id,    // Related order/product ID
    'module_name' => 'order'      // order, product, customer
]);
```

---

## Additional Resources

- **FluentCart Documentation:** https://dev.fluentcart.com/
- **GitHub Repository:** Check official FluentCart repository for latest updates
- **Support:** FluentCart support channels for assistance

---

## Summary

To create a custom payment gateway for FluentCart:

1. **Create Settings Class** extending `BaseGatewaySettings`
2. **Create Gateway Class** extending `AbstractPaymentGateway`
3. **Implement Required Methods:** `meta()`, `makePaymentFromPaymentInstance()`, `handleIPN()`, `getOrderInfo()`, `fields()`
4. **Register Gateway** using `fluent_cart_api()->registerCustomPaymentMethod()`
5. **Hook Registration** to `fluent_cart/init` action
6. **Test Thoroughly** in test mode before going live

**Key Takeaway:** The architecture is clean and extensible. Follow the existing gateway patterns (like COD for simple or Paddle for complex), and you'll have a working gateway quickly.

---

**Last Updated:** Based on FluentCart v1.2.2 analysis + LINE Pay implementation lessons
**Analysis Date:** October 21, 2025

**Changelog:**
- Added LINE Pay gateway as a real-world redirect-based payment example
- Documented common issues and solutions discovered during implementation
- Added logging best practices for array content
- Clarified FluentCart routing mechanism for custom actions
- Added zero-decimal currency handling examples
- Documented proper use of StatusHelper for order updates
- Fixed transaction meta storage examples

---

## Quick Reference: Common Patterns

### Register Gateway
```php
add_action('fluent_cart/init', function() {
    fluent_cart_api()->registerCustomPaymentMethod('gateway_slug', new YourGateway());
});
```

### Redirect to Payment Page
```php
return [
    'status' => 'success',
    'redirect_to' => $paymentUrl,
    'message' => __('Redirecting...', 'text-domain')
];
```

### Setup Confirmation Handler
```php
// 1. Create confirm URL
return add_query_arg([
    'fluent-cart' => 'your_confirm',  // This becomes action name
    'transaction_id' => $uuid
], site_url('/'));

// 2. Register action
add_action('fluent_cart_action_your_confirm', [$this, 'handleConfirm']);
```

### Update Transaction Status
```php
// Update transaction
$transaction->vendor_charge_id = $chargeId;
$transaction->status = Status::TRANSACTION_SUCCEEDED;
$transaction->meta = array_merge($transaction->meta ?? [], [
    'payment_note' => 'Success',
    'gateway_response' => $response
]);
$transaction->save();

// Update order and trigger events
(new StatusHelper($order))
    ->updateTotalPaid($order->total_amount)
    ->syncOrderStatuses($transaction);
```

### Handle Zero-Decimal Currencies
```php
// Check if currency is zero-decimal
$isZeroDecimal = CurrenciesHelper::isZeroDecimal('TWD'); // true

// Convert amount for API
$amount = $isZeroDecimal 
    ? intval($order->total_amount / 100)  // 800 → 8
    : intval($order->total_amount);        // 800 (keep as cents)

// Force zero-decimal display
add_filter('fluent_cart/global_currency_setting', function($settings) {
    if ($settings['currency'] === 'TWD') {
        $settings['is_zero_decimal'] = true;
        $settings['decimal_points'] = 0;
    }
    return $settings;
});
```

### Log with Array Data
```php
fluent_cart_add_log('API Request', wp_json_encode([
    'endpoint' => $endpoint,
    'payload' => $payload,
    'response' => $response
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'info', ['log_type' => 'payment']);
```

### Process Refunds
```php
public function processRefund($transaction, $amount, $args)
{
    try {
        // Convert for zero-decimal if needed
        $refundAmount = $amount ? intval($amount / 100) : null;
        
        $response = $api->refund($transaction->vendor_charge_id, $refundAmount);
        
        if ($response['success']) {
            fluent_cart_add_log('Refund Success', wp_json_encode([
                'transaction_id' => $transaction->id,
                'amount' => $refundAmount
            ], JSON_PRETTY_PRINT), 'info', ['log_type' => 'refund']);
            
            return true;
        }
        
        return new \WP_Error('refund_failed', $response['error']);
        
    } catch (\Exception $e) {
        return new \WP_Error('refund_exception', $e->getMessage());
    }
}
```

### Get Receipt/Success URL
```php
// After payment success
$receiptUrl = $transaction->getReceiptPageUrl(true);
wp_redirect($receiptUrl);
exit;
```

