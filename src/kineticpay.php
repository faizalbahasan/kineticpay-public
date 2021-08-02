<?php
/**

 * kineticPay Payment Gateway Classs

 */

class kineticpay extends WC_Payment_Gateway {

	function __construct(){

		add_action( 'woocommerce_api_callback', 'check_kineticpay_callback' );

		$this->id = "kineticPay";
		$this->method_title = __("kineticPay", 'kineticpay');
		$this->method_description = __("Enable your customers to make payments securely via kineticPay.", 'kineticpay');
		$this->title = __("kineticPay", 'kineticpay');
		$this->icon = plugins_url('assets/kineticpay-logo-all.png', __FILE__);
		$this->has_fields = true;
		$this->init_form_fields();
		$this->init_settings();

		foreach ($this->settings as $setting_key => $value) {
			$this->$setting_key = $value;
		}

		if (is_admin()) {
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			));
		}
	}

	public function init_form_fields()

	{

		$this->form_fields = array(
			'kp_enabled'        => array(
				'title'   => __('Enable / Disable', 'kineticpay'),
				'label'   => __('Enable this payment gateway', 'kineticpay'),
				'type'    => 'checkbox',
				'default' => 'no',
			),

			'title'          => array(
				'title'    => __('Title', 'kineticpay'),
				'type'     => 'text',
				'default'  => __('kineticPay', 'kineticpay'),
			),

			'description'    => array(
				'title'    => __('Description', 'kineticpay'),
				'type'     => 'textarea',
				'default'  => __('Pay securely with kineticPay.', 'kineticpay'),
				'css'      => 'max-width:350px;',
			),

			'merchant_key'      => array(
				'title'    => __('Merchant Key', 'kineticpay'),
				'type'     => 'text',
				'desc_tip' => __('Required', 'kineticpay'),
				'description' => __('Obtain your merchant key from your kineticPay dashboard.', 'kineticpay'),
			),
			// Future if category implemented
			/*'category_key' => array(
				'title'    => __('Category Code', 'kineticpay'),
				'type'     => 'text',
				'desc_tip' => __('Required', 'kineticpay'),
				'description' => __('Create a category at your kineticPay dashboard and fill in your category code here.', 'kineticpay'),
			),*/
		);
	}

	public function process_payment($order_id)
	
	{
		do_action( 'woocommerce_loaded' );
		
		$customer_order = wc_get_order($order_id);
		$callbackURL = add_query_arg(array('wc-api' => 'kineticpay', 'order' => $order_id, 'process' => 'processing'), home_url('/'));
		$name = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
		$email = $customer_order->get_billing_email();
		$phone = $customer_order->get_billing_phone();
		$bankid = $customer_order->get_meta('_kineticpay_bank');

		if($name == NULL || $phone == NULL || $email == NULL){
			wc_add_notice( __( 'Error! Please complete your details (Name, phone, and e-mail are compulsory).', 'kineticpay' ), 'error' );
			return;
		}

		return array(
			'result'    => 'success',
			'redirect'  => $callbackURL
		);
	}
	
	public function check_kineticpay_callback()
	
	{
	    do_action( 'woocommerce_loaded' );
	    
	    if (isset($_REQUEST['order'])){
    	    $order_id = absint( $_REQUEST['order'] );
    	    $customer_order = wc_get_order($order_id);
    	    
    	    if ($customer_order && $order_id != 0 && isset($_REQUEST['process'])){
    	        $bankid     = $customer_order->get_meta('_kineticpay_bank');
    	        if ($_REQUEST['process'] == 'processing'){
                    $amount = $customer_order->get_total();
    	            $redirectURL = add_query_arg(array('wc-api' => 'kineticpay'), home_url('/'));
                    $secretkey = $this->merchant_key;
                    // $categorycode = $this->category_key;
					$name = $customer_order->get_billing_first_name() . ' ' . $customer_order->get_billing_last_name();
					$email = $customer_order->get_billing_email();
					$phone = $customer_order->get_billing_phone();
					$description = "Payment for Order No " .  $order_id . ", Buyer Name " . $email . ", Email " . $phone . ", Phone No. " . $phone;
                    
                    $data = [
            			'callback_success'  => $redirectURL,
            			'callback_error'    => $redirectURL,
            			'amount'            => $amount,
            			'description'       => $description,
            			'invoice'           => $order_id,
            			'merchant_key'      => $secretkey,
            			'bank'              => $bankid,
            		];
            		
                    $getstatus = wp_remote_get('https://manage.kineticpay.my/payment/create',array('body' => $data, 'method' => 'POST'));

                    if ( is_wp_error( $getstatus ) ) {
            		    wc_add_notice($response->get_error_message(), 'error');
            		    wp_redirect(wc_get_checkout_url());
                    }

    	            $result = json_decode($getstatus["body"], true);
    	            if (array_key_exists('html', $result)){
    	                $customer_order->add_order_note('Customer made a payment attempt using bank ID '. $bankid .' via kineticPay.');

            		    echo wp_kses( $result["html"], array(
            		    	'form' => array(
            		    		'action' => array(),
            		    		'method' => array(),
            		    		'id' => array(),
            		    	),
            		    	'input' => array(
            		    		'type' => array(),
            		    		'value' => array(),
            		    		'name' => array(),
            		    	),
            		    	'script' => array(
            		    		'src' => array(),
            		    	),
            		    ) );
            		} else {
            		    wc_add_notice('Payment was declined. Something error with payment gateway, please contact store manager.', 'error');
						//var_dump($result);
            		    wp_redirect(wc_get_checkout_url());
            		}
            		exit();
    	        }
    	    }
	    } else 
	    if (isset($_REQUEST['fpx_sellerOrderNo'])){
	        $fpxdata = explode("-", absint( $_REQUEST['fpx_sellerOrderNo']));
	        $order_id = $fpxdata[1];
    	    $customer_order = wc_get_order($order_id);
    	    
    	    $getstatus = wp_remote_get('https://manage.kineticpay.my/payment/status?merchant_key='.$this->merchant_key.'&invoice=' . $order_id);
    	    $result = json_decode($getstatus["body"], true);
    	    
            if (array_key_exists('code', $result) && $result['code'] == "00"){
                $customer_order->add_order_note('Payment via kineticPay was succeed.<br>Transaction ID: ' . $result['id']);
                $customer_order->payment_complete();
                wp_safe_redirect(add_query_arg( array(
                    'kp_notification' => 1,
                    'kp_msg' => __('Payment Successful. Your payment has been successfully completed.', 'kineticpay'),
                    'kp_type' => 'success'),
                $customer_order->get_checkout_order_received_url()));
            } else 
            if (array_key_exists('code', $result)){
                $customer_order->add_order_note('Payment via kineticPay was failed.<br>Error code: ' . $result['code'] . '<br>Transaction ID: ' . $result['id']);
                wp_safe_redirect(add_query_arg( array(
                    'kp_notification' => 1,
                    'kp_msg' => __('Sorry, your payment failed. No charges were made.', 'kineticpay'),
                    'kp_type' => 'error'),
                wc_get_checkout_url()));
            } else {
                $customer_order->add_order_note('Payment via kineticPay was failed without code.');
                wp_safe_redirect(add_query_arg( array(
                    'kp_notification' => 1,
                    'kp_msg' => __('Sorry, your payment failed. No charges were made.', 'kineticpay'),
                    'kp_type' => 'error'),
                wc_get_checkout_url()));
            }
            
            exit();
        }
	}
}