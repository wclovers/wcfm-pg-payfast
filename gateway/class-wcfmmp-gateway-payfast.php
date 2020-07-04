<?php

if (!defined('ABSPATH')) {
    exit;
}

class WCFMmp_Gateway_Payfast extends WCFMmp_Abstract_Gateway {

	public $id;
	public $message = array();
	public $gateway_title;
	public $payment_gateway;
	public $withdrawal_id;
	public $vendor_id;
	public $withdraw_amount = 0;
	public $currency;
	public $transaction_mode;
	private $reciver_email;
	public $test_mode = false;
	public $client_id;
	public $client_secret;
	
	public function __construct() {
		
		$this->id = WCFMpgpf_GATEWAY;
		$this->gateway_title = __( WCFMpgpf_GATEWAY_LABEL, 'wcfm-pg-payfast' );
		$this->payment_gateway = $this->id;
	}
	
	public function gateway_logo() { global $WCFMmp; return $WCFMmp->plugin_url . 'assets/images/'.$this->id.'.png'; }
	
	public function process_payment( $withdrawal_id, $vendor_id, $withdraw_amount, $withdraw_charges, $transaction_mode = 'auto' ) {
	}
	
	public function validate_request() {
		global $WCFMmp;
		return true;
	}
}