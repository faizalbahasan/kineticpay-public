<?php
/**
 * Plugin Name: kineticPay for WooCommerce
 * Plugin URI: https://kineticpay.my/
 * Description: Receive payment on your WooCommerce site via kineticPay.
 * Version: 1.0.0
 * Author: kineticPay
 * Author URI: https://kineticpay.my/
 * WC requires at least: 2.6.0
 * WC tested up to: 5.5.1
 **/

// No direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Init kineticPay
add_action( 'plugins_loaded', 'kineticpay_init', 0 );
function kineticpay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/kineticpay.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_kineticpay_to_woocommerce' );
	function add_kineticpay_to_woocommerce( $methods ) {
		$methods[] = 'kineticPay';

		return $methods;
	}
}

// Add setting link to plugin list
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kineticpay_links' );
function kineticpay_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=kineticpay' ) . '">' . __( 'Settings', 'kineticpay' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

// Check init response
add_action( 'init', 'kineticpay_check_response', 15 );
function kineticpay_check_response() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/kineticpay.php' );

	$kineticpay = new kineticpay();
	$kineticpay->check_kineticpay_callback();
}

// Add bank list to Checkout page
add_filter( 'woocommerce_gateway_description', 'kineticpay_bank_list', 20, 2 );
function kineticpay_bank_list( $description, $payment_id ){
    if( 'kineticPay' === $payment_id ){
        ob_start();
        $bank_name = array(
            '' => 'Select Bank',
            'ABMB0212' => 'Alliance Bank Malaysia Berhad',
            'ABB0233' => 'Affin Bank Berhad',
            //'ABB0234' => 'Affin Bank Berhad',
            'AMBB0209' => 'AmBank (M) Berhad',
            //'BPMBMYKL' => 'AGROBANK',
            'BCBB0235' => 'CIMB Bank Berhad',
            'BIMB0340' => 'Bank Islam Malaysia Berhad',
            'BKRM0602' => 'Bank Kerjasama Rakyat Malaysia Berhad',
            'BMMB0341' => 'Bank Muamalat (Malaysia) Berhad',
            'BSN0601' => 'Bank Simpanan Nasional Berhad',
            'CIT0219' => 'Citibank Berhad',
            'HLB0224' => 'Hong Leong Bank Berhad',
            //'HBMBMYKL' => 'HSBC Bank Malaysia Berhad',
            'HSBC0223' => 'HSBC Bank Malaysia Berhad',
            'KFH0346' => 'Kuwait Finance House',
            'MB2U0227' => 'Maybank2u / Malayan Banking Berhad',
            //'MBBEMYKL' => 'Maybank2u / Malayan Banking Berhad',
            //'MBB0227' => 'Maybank2E / Malayan Banking Berhad E',
            'MBB0228' => 'Maybank2E / Malayan Banking Berhad E',
            'OCBC0229' => 'OCBC Bank (Malaysia) Berhad',
            'PBB0233' => 'Public Bank Berhad',
            //'RJHIMYKL' => 'AL RAJHI BANKING & INVESTMENT CORPORATION (MALAYSIA) BERHAD',
            //'RHBBMYKL' => 'RHB Bank Berhad',
            'RHB0218' => 'RHB Bank Berhad',
            'SCB0216' => 'Standard Chartered Bank (Malaysia) Berhad',
            'UOB0226' => 'United Overseas Bank (Malaysia) Berhad',
            //'UOB0229' => 'United Overseas Bank (Malaysia) Berhad',
        );

        echo '<div class="kineticpay-bank" style="padding:10px 0;">';
        woocommerce_form_field( 'kineticpay_bank', array(
            'type'          => 'select',
            'label'         => __("Choose Payment Method", "woocommerce"),
            'class'         => array('form-row-wide'),
            'required'      => true,
            'options'       => $bank_name,
            'default'       => '',
        ), '');
        echo '<div>';
        $description .= ob_get_clean();
    }
    return $description;
}

// Check if bank is selected
add_action('woocommerce_checkout_process', 'kineticpay_check_bank' );
function kineticpay_check_bank() {
    if ( isset($_POST['payment_method']) && $_POST['payment_method'] === 'kineticPay'
    && isset($_POST['kineticpay_bank']) && empty($_POST['kineticpay_bank']) ) {
        wc_add_notice( __( 'Please select bank name for payment, please.' ), 'error' );
    }
}

// Add bank code to order
add_action('woocommerce_checkout_create_order', 'kineticpay_write_to_meta_data', 10, 2 );
function kineticpay_write_to_meta_data( $order, $data ) {
    if ( isset($_POST['kineticpay_bank']) && ! empty($_POST['kineticpay_bank']) ) {
        $order->update_meta_data( '_kineticpay_bank' , sanitize_text_field($_POST['kineticpay_bank']) );
    }
}

// Help to pass error on Checkout or Thank You page
add_action('woocommerce_before_checkout_form','kineticpay_err_param');
add_action('woocommerce_thankyou','kineticpay_err_param');
function kineticpay_err_param() { 
    if (isset($_REQUEST['kp_notification'])) {
        wc_print_notice(esc_html( $_REQUEST['kp_msg'] ), esc_attr( $_REQUEST['kp_type'] ));
    }
}

// Add requery option to edit Order
add_action( 'woocommerce_order_actions', 'kineticpay_order_requery' );
function kineticpay_order_requery( $actions ) {
    global $theorder;

    if ( $theorder->is_paid() ) {
        return $actions;
    }

    $actions['kineticpay_do_query'] = __( 'Requery payment status from kineticPay', 'kineticPay' );
    return $actions;
}

// Process requery from kineticPay server
add_action( 'woocommerce_order_action_kineticpay_do_query', 'kineticpay_requery_process' );
function kineticpay_requery_process( $customer_order ) {
	$order_id = $customer_order->get_id();
    $getstatus = wp_remote_get('https://manage.kineticpay.my/payment/status?merchant_key='. get_option('woocommerce_kineticPay_settings')['merchant_key'] .'&invoice=' . $order_id);
	$result = json_decode($getstatus["body"], true);
	
    if (array_key_exists('code', $result) && $result['code'] == "00"){
		$customer_order->add_order_note('Payment via kineticPay was succeed.<br>Transaction ID: ' . $result['id']);
		$customer_order->payment_complete();
	} else 
	if (array_key_exists('code', $result)){
		$customer_order->add_order_note('Payment via kineticPay was failed.<br>Error code: ' . $result['code'] . '<br>Transaction ID: ' . $result['id']);
	} else {
		$customer_order->add_order_note('Payment via kineticPay was failed without code.');
	}
}