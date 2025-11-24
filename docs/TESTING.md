# LINE Pay for FluentCart - Testing Guide

Complete testing guide for LINE Pay gateway integration with FluentCart.

## Prerequisites

- [x] WordPress installed and running
- [x] FluentCart plugin installed and activated
- [x] LINE Pay for FluentCart plugin installed
- [x] LINE Pay Sandbox account credentials

## Test Environment Setup

### 1. Install Required Plugins

```bash
# Ensure FluentCart is installed
wp plugin install fluent-cart --activate

# Install LINE Pay gateway
cd wp-content/plugins
# (plugin should already be in wpbr-fluentcart-linepay/)
wp plugin activate wpbr-fluentcart-linepay
```

### 2. Configure FluentCart

1. Go to **FluentCart > Settings > General**
2. Set **Store Mode**: `Test Mode`
3. Set **Currency**: `TWD (新台幣)`
4. Set **Locale**: `Traditional Chinese (Taiwan)`
5. Configure **Checkout Page** (create if needed)
6. Save settings

### 3. Configure LINE Pay Gateway

1. Go to **FluentCart > Settings > Payment Methods**
2. Find **LINE Pay**
3. Click **Settings**

**Enter Sandbox Credentials:**
```
Sandbox Channel ID: [Your Sandbox Channel ID]
Sandbox Channel Secret: [Your Sandbox Channel Secret]
```

**Settings:**
- Auto Capture: `Enabled` ✓
- Payment Language: `zh-TW`

4. Click **Save Settings**
5. **Activate** LINE Pay payment method

### 4. Create Test Product

1. Go to **FluentCart > Products > Add New**
2. Create a test product:
   - Title: `測試商品 Test Product`
   - Price: `100` TWD
   - Status: `Published`
3. Save product

## Test Cases

### Test Case 1: Successful Payment Flow

**Steps:**
1. Open your store in incognito/private browser
2. Add test product to cart
3. Go to checkout
4. Fill in customer information:
   - Name: `測試用戶`
   - Email: `test@example.com`
   - Other required fields
5. Select **LINE Pay** as payment method
6. Click **Place Order**

**Expected Results:**
- ✅ Order created in FluentCart
- ✅ Redirect to LINE Pay sandbox
- ✅ LINE Pay authorization page loads
- ✅ Can authorize payment
- ✅ Return to store confirmation page
- ✅ Order status: `Paid`
- ✅ Transaction status: `Succeeded`
- ✅ Transaction log entry created

**Check Logs:**
```
FluentCart > Logs > Filter by "payment"
```

Should see:
- LINE Pay Payment Started
- LINE Pay Payment Request
- LINE Pay API Response (returnCode: 0000)
- LINE Pay Confirmation Request
- LINE Pay Payment Confirmed

### Test Case 2: Payment Cancellation

**Steps:**
1. Add product to cart
2. Go to checkout
3. Select LINE Pay
4. Click Place Order
5. On LINE Pay page, click **Cancel** or close window

**Expected Results:**
- ✅ Return to checkout page
- ✅ Error message displayed: "付款已取消"
- ✅ Order status remains `Pending`
- ✅ Can retry payment

### Test Case 3: Full Refund

**Prerequisite:** Complete successful payment first

**Steps:**
1. Go to **FluentCart > Orders**
2. Find the paid order
3. Click to view order details
4. Click **Refund** button
5. Leave amount blank (full refund)
6. Click **Process Refund**

**Expected Results:**
- ✅ Refund processed successfully
- ✅ Success message displayed
- ✅ Order status updated
- ✅ Refund transaction logged
- ✅ LINE Pay shows refund in merchant panel

**Check Logs:**
```
FluentCart > Logs > Filter by "refund"
```

Should see:
- LINE Pay Refund Started
- LINE Pay Refund Request
- LINE Pay API Response (returnCode: 0000)
- LINE Pay Refund Success

### Test Case 4: Partial Refund

**Prerequisite:** Order with amount > 100 TWD

**Steps:**
1. Create order for 200 TWD
2. Complete payment
3. Go to order in admin
4. Click **Refund**
5. Enter amount: `50` TWD
6. Click **Process Refund**

**Expected Results:**
- ✅ Partial refund of 50 TWD processed
- ✅ Remaining balance: 150 TWD
- ✅ Can process additional partial refunds
- ✅ Refund logged with correct amount

### Test Case 5: Currency Validation

**Steps:**
1. Go to **FluentCart > Settings > General**
2. Change currency to **USD**
3. Save settings
4. Try to add product to cart and checkout
5. Select LINE Pay

**Expected Results:**
- ✅ Error message: "LINE Pay 僅支援新台幣 (TWD) 付款"
- ✅ Payment blocked
- ✅ Error logged

### Test Case 6: Missing Credentials

**Steps:**
1. Go to **LINE Pay Settings**
2. Clear Channel ID and Secret
3. Save settings
4. Try to process a payment

**Expected Results:**
- ✅ Error: "LINE Pay 設定不完整"
- ✅ Payment blocked
- ✅ Admin notice shown

### Test Case 7: Duplicate Payment Prevention

**Steps:**
1. Complete a successful payment
2. Note the transaction ID
3. Try to confirm the same transaction again (use browser back)

**Expected Results:**
- ✅ Already processed detection
- ✅ Redirect to success page
- ✅ No duplicate charge
- ✅ Log shows "Already processed"

### Test Case 8: Transaction Logging

**Steps:**
1. Complete a full payment flow
2. Go to **FluentCart > Logs**
3. Filter by type: `payment`

**Expected Log Entries:**
```
1. LINE Pay Payment Started
   - order_id
   - invoice_id
   - amount
   - currency

2. LINE Pay Payment Request
   - order_id
   - amount
   - currency

3. LINE Pay API Response
   - endpoint: /v3/payments/request
   - status_code: 200
   - return_code: 0000
   - return_message: Success

4. LINE Pay Confirmation Request
   - transaction_id
   - amount
   - currency

5. LINE Pay API Response
   - endpoint: /v3/payments/{id}/confirm
   - status_code: 200
   - return_code: 0000

6. LINE Pay Payment Confirmed
   - order_id
   - vendor_charge_id
   - amount
```

### Test Case 9: Sandbox to Production Switch

**Steps:**
1. Complete test in sandbox mode
2. Go to **FluentCart > Settings > General**
3. Change **Store Mode** to `Live Mode`
4. Go to **LINE Pay Settings**
5. Enter **Live credentials**
6. Try a small real transaction (or stop before completing)

**Expected Results:**
- ✅ API calls go to production endpoint
- ✅ No sandbox credentials used
- ✅ Production LINE Pay interface loads
- ⚠️ **Warning**: Use real account for real transactions only

### Test Case 10: Stress Testing

**Steps:**
1. Create 10 orders rapidly
2. Process payments concurrently
3. Check for race conditions

**Expected Results:**
- ✅ All orders process correctly
- ✅ No duplicate charges
- ✅ All logs complete
- ✅ No missing transactions

## API Response Codes

### Success Codes
- `0000` - Success
- `0110` - Already captured (payment confirmed)

### Error Codes
- `1104` - Invalid merchant
- `1105` - Invalid merchant status
- `1106` - Authentication failed
- `1124` - Amount error
- `1125` - Transaction not found
- `1150` - Cannot refund
- `1198` - Invalid API call
- `2101` - Parameter error
- `2102` - JSON format error

## Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs: `wp-content/debug.log`

### Check PHP Error Log

```bash
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
```

### View Transaction Details

```sql
SELECT * FROM wp_fct_order_transactions 
WHERE payment_method = 'linepay' 
ORDER BY id DESC LIMIT 10;
```

### View Logs

```sql
SELECT * FROM wp_fct_logs 
WHERE log_type = 'payment' 
ORDER BY id DESC LIMIT 20;
```

## Performance Testing

### Response Times

Expected response times (sandbox):
- Payment Request: < 2 seconds
- Payment Confirmation: < 2 seconds
- Refund Request: < 2 seconds

### Load Testing

Use Apache Bench to test concurrent requests:
```bash
ab -n 100 -c 10 https://yoursite.com/checkout/
```

## Security Testing

### SSL Certificate
- ✅ HTTPS enabled
- ✅ Valid SSL certificate
- ✅ No mixed content warnings

### Credential Storage
- ✅ Channel Secret encrypted in database
- ✅ Not visible in page source
- ✅ Not logged in plain text

### Signature Validation
- ✅ HMAC-SHA256 signature generated correctly
- ✅ Nonce is unique per request
- ✅ Cannot replay requests

## Known Issues & Limitations

1. **Currency**: Only TWD supported (by design)
2. **Market**: Taiwan only (LINE Pay limitation)
3. **Recurring**: No subscription support (feature not included)
4. **Language**: Traditional Chinese primary (Taiwan market)

## Checklist Before Production

- [ ] All test cases passing
- [ ] Production credentials configured
- [ ] Store mode set to "Live"
- [ ] SSL certificate valid
- [ ] Backup database
- [ ] Monitor logs after launch
- [ ] Test small transaction first
- [ ] Have rollback plan ready

## Support

If tests fail, check:
1. FluentCart version compatibility
2. PHP version (>= 7.4)
3. WordPress version (>= 5.8)
4. Plugin conflicts (disable other plugins)
5. Server logs for errors

## Test Results Template

```markdown
## Test Results - [Date]

**Environment:**
- WordPress: 
- PHP: 
- FluentCart: 
- LINE Pay Plugin: 

**Test Case Results:**
- [ ] TC1: Successful Payment Flow
- [ ] TC2: Payment Cancellation
- [ ] TC3: Full Refund
- [ ] TC4: Partial Refund
- [ ] TC5: Currency Validation
- [ ] TC6: Missing Credentials
- [ ] TC7: Duplicate Prevention
- [ ] TC8: Transaction Logging
- [ ] TC9: Mode Switching
- [ ] TC10: Stress Testing

**Issues Found:**
- None / [List issues]

**Notes:**
[Any additional observations]
```

## Next Steps

After successful testing:
1. Document any issues found
2. Update plugin if needed
3. Prepare for production deployment
4. Create production checklist
5. Schedule go-live date

