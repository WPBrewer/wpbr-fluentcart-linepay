# LINE Pay for FluentCart

A payment gateway plugin that integrates LINE Pay with FluentCart, specifically designed for the Taiwan market.

## Features

- ✅ **One-time Payments** - Complete payment processing with LINE Pay API v3
- ✅ **Sandbox Mode** - Test payments in sandbox environment
- ✅ **Secure Credentials** - Encrypted storage of Channel Secret
- ✅ **Taiwan Market** - TWD currency support with Traditional Chinese interface
- ✅ **Comprehensive Logging** - Detailed transaction logs for debugging
- ✅ **Auto-capture** - Configurable payment capture settings

## Requirements

- **WordPress** 5.8 or higher
- **PHP** 8.0 or higher
- **FluentCart** (must be installed and activated)
- **LINE Pay Merchant Account** (Taiwan)
- **SSL Certificate** (HTTPS recommended for production)

## Installation

### Via WordPress Admin

1. Upload the plugin folder to `/wp-content/plugins/wpbr-fluentcart-linepay/`
2. Activate the plugin through 'Plugins' menu in WordPress
3. Go to **FluentCart > Settings > Payment Methods**
4. Find **LINE Pay** and configure settings

### Via Composer

```bash
cd wp-content/plugins
git clone [repository-url] wpbr-fluentcart-linepay
cd wpbr-fluentcart-linepay
composer install --no-dev
```

Then activate the plugin in WordPress admin.

## Configuration

### 1. Get LINE Pay Credentials

**For Sandbox Testing:**
1. Go to [LINE Pay Sandbox Console](https://pay.line.me/tw/developers/techsupport/sandbox/creation)
2. Create a sandbox merchant account
3. Get your Sandbox Channel ID and Channel Secret

**For Production:**
1. Apply for LINE Pay merchant account
2. Login to [LINE Pay Merchant Center](https://pay.line.me/portal/tw/main)
3. Go to **Technical Integration > Channel Settings**
4. Get your Production Channel ID and Channel Secret

### 2. Configure Plugin

1. In WordPress admin, go to **FluentCart > Settings > General**
2. Set **Store Mode** to:
   - **Test Mode** - for sandbox testing
   - **Live Mode** - for production

3. Go to **FluentCart > Settings > Payment Methods > LINE Pay**

4. Enter credentials based on your mode:

**Test Mode Settings:**
- Sandbox Channel ID
- Sandbox Channel Secret

**Live Mode Settings:**
- Live Channel ID
- Live Channel Secret

5. Configure additional settings:
   - **Auto Capture**: Enable for automatic payment capture (recommended)

6. Click **Save Settings**

7. **Activate** the LINE Pay payment method

### 3. Currency Settings

⚠️ **Important**: Set your store currency to **TWD (New Taiwan Dollar)**

Go to **FluentCart > Settings > General > Currency** and select **TWD**

## Usage

### Customer Checkout Flow

1. Customer adds products to cart
2. Goes to checkout
3. Selects **LINE Pay** as payment method
4. Clicks **Place Order**
5. Redirects to LINE Pay authorization page
6. Customer authorizes payment in LINE Pay
7. Returns to store with payment confirmed
8. Order marked as paid

### Processing Refunds

1. Go to **FluentCart > Orders**
2. Click on the order to refund
3. Click **Refund** button
4. Enter refund amount:
   - Full refund: Leave blank or enter full amount
   - Partial refund: Enter specific amount
5. Click **Process Refund**
6. Refund is processed through LINE Pay API

## Testing

### Sandbox Testing Flow

1. Set FluentCart to **Test Mode**
2. Enter **Sandbox credentials** in LINE Pay settings
3. Create a test order
4. Use LINE Pay sandbox environment for payment
5. Test credentials provided by LINE Pay Sandbox

### Test Checklist

- [ ] Payment request creates successfully
- [ ] Redirect to LINE Pay works
- [ ] Payment authorization successful
- [ ] Return to store and confirm payment
- [ ] Order status updates to "Paid"
- [ ] Transaction logs recorded
- [ ] Full refund processes correctly
- [ ] Partial refund processes correctly
- [ ] Currency validation (non-TWD rejected)
- [ ] Missing credentials show error

## Transaction Logs

View detailed transaction logs:

**FluentCart > Logs**

Log types:
- `payment` - Payment requests, confirmations, errors
- `refund` - Refund requests and results

Each log entry includes:
- Timestamp
- Order ID
- Transaction ID
- LINE Pay response codes
- Error messages (if any)

## Folder Structure

```
wpbr-fluentcart-linepay/
├── src/
│   ├── Plugin.php                    # Main plugin class
│   ├── Settings/
│   │   └── LinePaySettings.php       # Settings management
│   └── Gateways/
│       ├── LinePay.php               # Main gateway class
│       ├── Processor.php             # Payment processing
│       ├── Confirmations.php         # Payment confirmation
│       └── API/
│           └── LinePayAPI.php        # LINE Pay API v3 wrapper
├── assets/
│   ├── images/                       # Logos and icons
│   ├── js/                          # Frontend JavaScript
│   └── css/                         # Admin styles
├── languages/                        # Translation files
├── vendor/                           # Composer autoloader
├── composer.json                     # Composer configuration
├── wpbr-fluentcart-linepay.php      # Main plugin file
└── readme.txt                        # WordPress.org readme

```

## API Reference

### LINE Pay API v3 Endpoints Used

**Request Payment**
```
POST /v3/payments/request
```

**Confirm Payment**
```
POST /v3/payments/{transactionId}/confirm
```

**Refund Payment**
```
POST /v3/payments/{transactionId}/refund
```

### Authentication

All requests use HMAC-SHA256 signature authentication:

```php
signature = base64(HMAC-SHA256(channelSecret + URI + body + nonce))
```

Headers:
- `X-LINE-ChannelId`: Channel ID
- `X-LINE-Authorization-Nonce`: Unique nonce (UUID)
- `X-LINE-Authorization`: Signature

## Troubleshooting

### Payment Request Fails

**Issue**: "LINE Pay 設定不完整" error

**Solution**:
- Verify Channel ID and Channel Secret are entered correctly
- Check that credentials match the store mode (test/live)
- Ensure store mode setting matches credentials

### Currency Not Supported

**Issue**: "LINE Pay 僅支援新台幣 (TWD) 付款"

**Solution**:
- Go to **FluentCart > Settings > General > Currency**
- Change currency to **TWD**

### Payment Confirmation Fails

**Issue**: User returns but payment not confirmed

**Solution**:
- Check transaction logs for error messages
- Verify LINE Pay API credentials
- Ensure confirm URL is accessible
- Check for any firewall blocking return URL

### Refund Fails

**Issue**: "無法退款：缺少 LINE Pay 交易編號"

**Solution**:
- Verify the order was paid through LINE Pay
- Check that transaction has vendor_charge_id
- Ensure payment was confirmed successfully

## Support

- **Documentation**: [LINE Pay API Docs](https://developers-pay.line.me/online)
- **FluentCart Docs**: [dev.fluentcart.com](https://dev.fluentcart.com)
- **Plugin Support**: Create an issue in the repository

## Development

### Running Tests

```bash
# Install dev dependencies
composer install

# Run PHP linting
composer run lint

# Run unit tests
composer run test
```

### Code Standards

- PSR-4 autoloading
- WordPress Coding Standards
- Comprehensive inline documentation
- Transaction logging for all API calls

## License

GPL v2 or later

## Credits

- Developed by [WPBrewer](https://wpbrewer.com)
- LINE Pay integration based on [LINE Pay API v3](https://developers-pay.line.me/online)
- Built for [FluentCart](https://fluentcart.com)

## Changelog

### 1.0.0 (2024-01-01)
- Initial release
- One-time payment support
- Full and partial refund support
- Sandbox and production modes
- Taiwan market (TWD currency)
- Traditional Chinese language support
- Comprehensive transaction logging
- Auto-capture configuration
- Secure credential storage

