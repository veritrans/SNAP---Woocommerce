Midtrans&nbsp; WooCommerce - Wordpress Payment Gateway Module
=====================================

Midtrans&nbsp; :heart: WooCommerce!
Let your WooCommerce store integrated with Midtrans&nbsp; payment gateway.

### Description

Midtrans&nbsp; payment gateway is an online payment gateway. They strive to make payments simple for both the merchant and customers. With this plugin you can allow online payment on your WooCommerce store using Midtrans&nbsp; payment gateway.

Payment Method Feature:
- Midtrans&nbsp; Snap all payment method fullpayment
- Credit card online & offline installment payment
- Credit card BIN & bank transfer promo payment
- Credit card MIGS acquiring channel

### Installation

#### Minimum Requirements

* WordPress v3.9.1 or greater (tested up to v4.5.x)
* WooCommerce v2.1.11 or greater (tested up to v2.5.x)
* PHP version v5.4 or greater
* MySQL version v5.0 or greater

#### Manual Installation

1. [Download](/archive/master.zip) the plugin from this repository.
2. Extract the plugin, then rename the folder modules as **midtrans-woocommerce**
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.
3. Install & Activate the plugin from the Plugins menu within the WordPress admin panel.
4. Go to menu **WooCommerce > Settings > Checkout > Midtrans**, fill the configuration fields.
  * Fill **Title** with text button that you want to display to customer
  * Select **Environment**, Sandbox is for testing transaction, Production is for real transaction
  * Fill in the **client key** & **server key** with your corresonding [Midtrans&nbsp; account](https://dashboard.midtrans.com/) credentials
  * Note: key for Sandbox & Production is different, make sure you use the correct one.
  * Other configuration are optional, you may leave it as is.

### Midtrans&nbsp; MAP Configuration

1. Login to your [Midtrans&nbsp; Account](https://dashboard.midtrans.com), select your environment (sandbox/production), go to menu **settings > configuration**
  * Insert `http://[your web]/?wc-api=WC_Gateway_Midtrans` as your Payment Notification URL.
  * Insert `http://[your web]/?wc-api=WC_Gateway_Midtrans` link as Finish/Unfinish/Error Redirect URL.

#### Get help

* [SNAP-Woocommerce Wiki](https://github.com/veritrans/SNAP-Woocommerce/wiki)
* [Veritrans registration](https://dashboard.midtrans.com/register)
* [SNAP documentation](http://snap-docs.midtrans.com)
* Can't find answer you looking for? email to [support@midtrans.com](mailto:support@midtrans.com)
