=== LINE Pay for FluentCart ===
Contributors: wpbrewer
Tags: line pay, payment gateway, fluentcart, taiwan, e-commerce
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 0.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LINE Pay payment gateway integration for FluentCart - specifically designed for Taiwan market.

== Description ==

LINE Pay for FluentCart is a payment gateway plugin that integrates LINE Pay with FluentCart, specifically designed for the Taiwan market.

**Features:**

* One-time payment support
* Sandbox and Production modes
* Secure credential storage with encryption
* Support for New Taiwan Dollar (TWD) currency
* Traditional Chinese language support
* Comprehensive transaction logging
* Auto-capture option

**Requirements:**

* FluentCart plugin must be installed and activated
* LINE Pay merchant account (Taiwan)
* SSL certificate (HTTPS) recommended for production

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wpbr-fluentcart-linepay/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to FluentCart > Settings > Payment Methods > LINE Pay
4. Enter your LINE Pay Channel ID and Channel Secret
5. Configure your settings and activate the gateway

== Configuration ==

1. Login to LINE Pay Merchant Dashboard
2. Get your Channel ID and Channel Secret
3. For sandbox testing, use sandbox credentials from LINE Pay Developer Console
4. Enter credentials in FluentCart > Settings > Payment Methods > LINE Pay
5. Set your store mode (Test/Live) in FluentCart > Settings > General
6. Enable LINE Pay payment method

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

No, this plugin is specifically designed for FluentCart. For WooCommerce, please use our other LINE Pay plugin.

= Which currencies are supported? =

This plugin only supports Taiwan New Dollar (TWD) as it's designed specifically for the Taiwan market.

= Can I process refunds? =

Yes, you can process full or partial refunds directly from FluentCart admin panel.

= Is sandbox mode available? =

Yes, you can switch between sandbox and production modes using FluentCart's store mode settings.

== Changelog ==

= 0.9.1 =
* Initial release
* One-time payment support
* Sandbox and production modes


