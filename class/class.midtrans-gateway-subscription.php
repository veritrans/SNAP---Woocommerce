<?php
  /**
   * Midtrans Payment Subscription Gateway Class.
   */
  class WC_Gateway_Midtrans_Subscription extends WC_Gateway_Midtrans_Abstract {
      
    /**
     * Constructor.
     */
    function __construct() {
      $this->id           = 'midtrans_subscription';
      $this->method_title = __( $this->pluginTitle(), 'midtrans-woocommerce' );
      $this->method_description = $this->getSettingsDescription();
      $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Midtrans_Subscription', home_url( '/' ) ) );
      parent::__construct();
       $this->supports = array(
          'refunds',
          'products',
          'subscriptions',
          'subscription_cancellation',
          'subscription_suspension', 
          'subscription_reactivation',
          'subscription_amount_changes',
          'subscription_date_changes',
          'subscription_payment_method_change',
          'subscription_payment_method_change_customer',
          'subscription_payment_method_change_admin',
          'multiple_subscriptions'
        );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); 
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );// Payment page hook
      add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
    }

    /**
     * Admin Panel Options.
     * HTML that will be displayed on Admin Panel.
     * @access public
     * @return void
     */
    public function admin_options() { ?>
      <h3><?php _e( $this->pluginTitle(), 'midtrans-woocommerce' ); ?></h3>
      <p><?php _e('Allows credit card subscription payments using Midtrans.', 'midtrans-woocommerce' ); ?></p>
      <table class="form-table">
        <?php
          // Generate the HTML For the settings form.
          $this->generate_settings_html();
        ?>
      </table><!--/.form-table-->
      <?php
    }
      
    /**
     * initialize Gateway Settings Form Fields.
     */
    function init_form_fields() {
      parent::init_form_fields();
      WC_Midtrans_Utils::array_insert( $this->form_fields, 'enable_3d_secure', array(
        'acquring_bank' => array(
          'title' => __( 'Acquiring Bank', 'midtrans-woocommerce'),
          'type' => 'text',
          'label' => __( 'Acquiring Bank', 'midtrans-woocommerce' ),
          'description' => __( 'Leave blank for default. </br> Specify your acquiring bank for this payment option. </br> Options: BCA, BRI, DANAMON, MAYBANK, BNI, MANDIRI, CIMB, etc (Only choose 1 bank).' , 'midtrans-woocommerce' ),
          'default' => ''
        ),
        'bin_number' => array(
          'title' => __( 'Allowed CC BINs', 'midtrans-woocommerce'),
          'type' => 'text',
          'label' => __( 'Allowed CC BINs', 'midtrans-woocommerce' ),
          'description' => __( 'Leave this blank if you dont understand!</br> Fill with CC BIN numbers (or bank name) that you want to allow to use this payment button. </br> Separate BIN number with coma Example: 4,5,4811,bni,mandiri', 'midtrans-woocommerce' ),
          'default' => ''
        )
      ));
      $this->form_fields['custom_fields']['description'] = __( 'This will allow you to set custom fields that will be displayed on Midtrans dashboard. <br>Up to 2 fields are available, separate by coma (,) <br> Example:  Order from web, Woocommerce', 'midtrans-woocommerce' );
    }

    /**
     * This function auto-triggered by WC when payment process initiated.
     * Serves as WC payment entry point.
     * @param  [String] $order_id auto generated by WC.
     * @return [array] contains redirect_url of payment for customer.
     */
    function process_payment( $order_id ) {
      global $woocommerce;
      
      //create the order object.
      $order = new WC_Order( $order_id );
      // Get response object template.
      $successResponse = $this->getResponseTemplate( $order );
      // Get data for charge to midtrans API.
      $params = $this->getPaymentRequestData( $order_id );

      // add credit card payment.
      $params['enabled_payments'] = ['credit_card'];
      // add bank & channel migs params.
      if (strlen($this->get_option('acquring_bank')) > 0)
        $params['credit_card']['bank'] = strtoupper ($this->get_option('acquring_bank'));
      // add bin params        
      if (strlen($this->get_option('bin_number')) > 0){
        $bins = explode(',', $this->get_option('bin_number'));
        $params['credit_card']['whitelist_bins'] = $bins;
      }
      // add custom field so it can be easily identified on Midtrans Dashboard.
      $params['custom_field3'] = 'woocommerce-subscription-initial';

      // Empty the cart because payment is initiated.
      $woocommerce->cart->empty_cart();
      try {
        $snapResponse = WC_Midtrans_API::createSnapTransaction( $params, $this->id );
      } catch (Exception $e) {
        $this->setLogError( $e->getMessage() );
        WC_Midtrans_Utils::json_print_exception( $e, $this );
        exit();
      }

      if(property_exists($this,'enable_redirect') && $this->enable_redirect == 'yes'){
        $redirectUrl = $snapResponse->redirect_url;
      }else{
        $redirectUrl = $order->get_checkout_payment_url( true )."&snap_token=".$snapResponse->token;
      }

      // Add snap token & snap redirect url to $order metadata.
      $order->update_meta_data('_mt_payment_snap_token',$snapResponse->token);
      $order->update_meta_data('_mt_payment_url',$snapResponse->redirect_url);
      $order->save();
      
      if(property_exists($this,'enable_immediate_reduce_stock') && $this->enable_immediate_reduce_stock == 'yes'){
        wc_reduce_stock_levels($order);
      }

      $successResponse['redirect'] = $redirectUrl;
      return $successResponse;
    }

    /**
     * Hook function that will be called on receipt page.
     * Output HTML for Snap payment page. Including `snap.pay()` part.
     * @param  string $order_id generated by WC.
     * @return string HTML.
     */
    function receipt_page( $order_id ) {
      global $woocommerce;
      $pluginName = 'subscription';
      require_once(dirname(__FILE__) . '/payment-page.php'); 
    }

    /**
     * Scheduled_subscription_payment function.
     * This function is called when renewal order is triggered.
     * 
     * @param float $amount_to_charge float The amount to charge.
     * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
     */
    public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
      if (!$renewal_order) {  
        return array ( 'result' => 'failure' );
      }
      $order_id = $renewal_order->get_id();
      $subscription_order = wcs_get_subscriptions_for_renewal_order( $order_id );
      $checkout_payment_url = $renewal_order->get_checkout_payment_url();

      // Retrieve card token from meta.
      foreach ( $subscription_order as $subscription ) {
        $card_token = $subscription->get_meta('_mt_subscription_card_token');
      }

      // if card_token null, the transaction will fail.
      if ($card_token == '' ) {
        $renewal_order->update_status( 'failed', __('Midtrans subscription payment failed.', 'midtrans-woocommerce') );
        $renewal_order->add_order_note( __( 'Customer didn\'t tick the <b>Save Card Info</b> on previous payment. <br> Please click <a href="'.$checkout_payment_url.'">here</a> to renew the payment.', 'midtrans-woocommerce' ), 1 );
        return array('result' => 'failure');
        exit();
      }

      // Get data for charging to Midtrans API.
      $params = $this->getPaymentRequestData( $order_id );
      $params['credit_card']['secure'] = false;
      $params['credit_card']['token_id'] = $card_token;
      // add custom field so it can be easily identified on Midtrans Dashboard.
      $params['custom_field3'] = 'woocommerce-subscription-renewal';

      // Charge transaction via Midtrans API.
      try {
        $midtransResponse = WC_Midtrans_API::createRecurringTransaction( $params, $this->id );
        return array(
          'result'   => 'success',
        );

      } catch (Exception $e) {
        $this->setLogError( $e->getMessage() );
        WC_Midtrans_Utils::json_print_exception( $e, $this );
        
        // Subscription Payment Failed.
        $renewal_order->update_status( 'failed', __('Midtrans subscription payment failed.<br>' . $e->getMessage(), 'midtrans-woocommerce') );
        $subscription->add_order_note( __( 'Midtrans subscription payment failed. <br> Please click <a href="'.$checkout_payment_url.'">here</a> to renew the payment.', 'midtrans-woocommerce'), 1 );
        return array('result' => 'failure');
        exit();
      }

    }

    /**
     * @return string
     */
    public function pluginTitle() {
      return "Midtrans Credit Card Subscription";
    }

    /**
     * @return string
     */
    protected function getDefaultTitle() {
      return __('Credit Card Subscription Payment via Midtrans', 'midtrans-woocommerce');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription() {
      return __('Pay Subscriptionvia Midtrans.<br>You have to tick the <b>Save Card Info</b> that will be presented when you fill out the credit card details (if you forget to choose, your next payment will fail).', 'midtrans-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription() {
      return __('This method used for recurring payments with WooCommerce Subscriptions. You must have mid recurring to activate this Method. <br> Please contact your Midtrans PIC for further details.', 'midtrans-woocommerce');
    }
    
  }
