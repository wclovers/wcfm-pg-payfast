<?php

/**
 * WCFM PG Payfast plugin core
 *
 * Plugin intiate
 *
 * @author 		WC Lovers
 * @package 	wcfm-pg-payfast
 * @version   1.0.0
 */

class WCFM_PG_Payfast {
	
	public $plugin_base_name;
	public $plugin_url;
	public $plugin_path;
	public $version;
	public $token;
	public $text_domain;
	
	public function __construct($file) {

		$this->file = $file;
		$this->plugin_base_name = plugin_basename( $file );
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = WCFMpgpf_TOKEN;
		$this->text_domain = WCFMpgpf_TEXT_DOMAIN;
		$this->version = WCFMpgpf_VERSION;
		
		add_action( 'wcfm_init', array( &$this, 'init' ), 10 );
	}
	
	function init() {
		global $WCFM, $WCFMre;

		$this->payfast_pg = WC()->payment_gateways->payment_gateways()[WCFMpgpf_GATEWAY];
		
		// Init Text Domain
		$this->load_plugin_textdomain();
		
		add_filter( 'wcfm_marketplace_withdrwal_payment_methods', array( &$this, 'wcfmmp_custom_pg' ) );

		// De-register WCFMmp Auto-withdrawal Gateway
		add_filter( 'wcfm_marketplace_disallow_active_order_payment_methods', array( $this, 'wcfmmp_auto_withdrawal_payfast' ), 750 );
		
		add_filter( 'wcfm_marketplace_settings_fields_withdrawal_charges', array( &$this, 'wcfmmp_custom_pg_withdrawal_charges' ), 50, 3 );
		
		add_filter( 'wcfm_marketplace_settings_fields_billing', array( &$this, 'wcfmmp_custom_pg_vendor_setting' ), 50, 2 );

		add_action( 'woocommerce_receipt_payfast', array( &$this, 'receipt_page' ), 9 );

		// By Force Disable Multivendor Checkout
		add_filter( 'wcfmmp_is_disable_multivendor_checkout', array( &$this, 'wcfm_direct_paypal_disable_multivendor_checkout' ), 500 );

		add_action( 'woocommerce_api_wc_gateway_payfast', array( &$this, 'process_withdrawal_after_itn_response' ), 11 );
		
		// Load Gateway Class
		require_once $this->plugin_path . 'gateway/class-wcfmmp-gateway-payfast.php';
		
	}
	
	public function wcfmmp_custom_pg( $payment_methods ) {
		$payment_methods[WCFMpgpf_GATEWAY] = __( WCFMpgpf_GATEWAY_LABEL, 'wcfm-pg-payfast' );
		return $payment_methods;
	}

	public function wcfmmp_auto_withdrawal_payfast( $auto_withdrawal_methods ) {
		if( isset( $auto_withdrawal_methods[WCFMpgpf_GATEWAY] ) )
			unset( $auto_withdrawal_methods[WCFMpgpf_GATEWAY] );
		return $auto_withdrawal_methods;
	}
	
	public function wcfmmp_custom_pg_withdrawal_charges( $withdrawal_charges, $wcfm_withdrawal_options, $withdrawal_charge ) {
		$gateway_slug  = WCFMpgpf_GATEWAY;
		$gateway_label = __( WCFMpgpf_GATEWAY_LABEL, 'wcfm-pg-payfast' ) . ' ';
		
		$withdrawal_charge_brain_tree = isset( $withdrawal_charge[$gateway_slug] ) ? $withdrawal_charge[$gateway_slug] : array();
		$payment_withdrawal_charges = array(  "withdrawal_charge_".$gateway_slug => array( 'label' => $gateway_label . __('Charge', 'wcfm-pg-payfast'), 'type' => 'multiinput', 'name' => 'wcfm_withdrawal_options[withdrawal_charge]['.$gateway_slug.']', 'class' => 'withdraw_charge_block withdraw_charge_'.$gateway_slug, 'label_class' => 'wcfm_title wcfm_ele wcfm_fill_ele withdraw_charge_block withdraw_charge_'.$gateway_slug, 'value' => $withdrawal_charge_brain_tree, 'custom_attributes' => array( 'limit' => 1 ), 'options' => array(
			"percent" => array('label' => __('Percent Charge(%)', 'wcfm-pg-payfast'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'attributes' => array( 'min' => '0.1', 'step' => '0.1') ),
			"fixed" => array('label' => __('Fixed Charge', 'wcfm-pg-payfast'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'attributes' => array( 'min' => '0.1', 'step' => '0.1') ),
			"tax" => array('label' => __('Charge Tax', 'wcfm-pg-payfast'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele', 'label_class' => 'wcfm_title wcfm_ele', 'attributes' => array( 'min' => '0.1', 'step' => '0.1'), 'hints' => __( 'Tax for withdrawal charge, calculate in percent.', 'wcfm-pg-payfast' ) ),
		) ) );
		$withdrawal_charges = array_merge( $withdrawal_charges, $payment_withdrawal_charges );
		return $withdrawal_charges;
	}
	
	public function wcfmmp_custom_pg_vendor_setting( $vendor_payment_fields, $vendor_id ) {
		$gateway_slug  = WCFMpgpf_GATEWAY;
		$gateway_label = __( WCFMpgpf_GATEWAY_LABEL, 'wcfm-pg-payfast' ) . ' ';
		
		$vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
		if( !$vendor_data ) $vendor_data = array();
		$merchant_id = isset( $vendor_data['payment'][$gateway_slug]['merchant_id'] ) ? esc_attr( $vendor_data['payment'][$gateway_slug]['merchant_id'] ) : '' ;
		$new_payment_fileds = array(
			$gateway_slug => array(
				'label' => $gateway_label . __('Merchant ID', 'wcfm-pg-payfast'), 
				'name' => 'payment['.$gateway_slug.'][merchant_id]', 
				'type' => 'text', 
				'class' => 'wcfm-text wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'label_class' => 'wcfm_title wcfm_ele paymode_field paymode_'.$gateway_slug, 
				'value' => $merchant_id 
			),
		);
		$vendor_payment_fields = array_merge( $vendor_payment_fields, $new_payment_fileds );
		return $vendor_payment_fields;
	}

	public function receipt_page( $order_id ) {
		global $WCFMmp;

		// check if order has vendor
		$vendor_id = 0;
		$order = wc_get_order( $order_id );
		foreach ( $order->get_items() as $item_id => $item ) {
   			$product_id = $item->get_product_id();
   			$vendor_id 	= wcfm_get_vendor_id_by_post( $product_id );
   		}

   		if( $vendor_id ) {
   			$gateway_slug  = WCFMpgpf_GATEWAY;
   			$vendor_data = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );
   			if( !$vendor_data ) $vendor_data = array();
   			$merchant_id = isset( $vendor_data['payment'][$gateway_slug]['merchant_id'] ) ? esc_attr( $vendor_data['payment'][$gateway_slug]['merchant_id'] ) : '' ;

   			if( $merchant_id ) {
   				$vendor_order_amount = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission( $vendor_id, $order_id, $order );
   				$vendor_commission = round( $vendor_order_amount['commission_amount'], 2 ) * 100;

   				$split_args = "{ 
   					'split_payment' : {
	    				'merchant_id':$merchant_id,
	           			'amount':$vendor_commission
	           		}
	           	}";

   				echo "<script type='text/javascript'>
					jQuery(function($){
						$( document ).one( 'click', '#submit_payfast_payment_form', function( event ) {
							event.preventDefault();
							console.log( '~~ split payment data injected ~~' );
							let input = document.createElement( 'input' );
							input.type = 'hidden';
							input.name = 'setup';
							input.value = JSON.stringify( $split_args );
							$( '#payfast_payment_form' ).prepend( input );
							$( '#submit_payfast_payment_form' ).trigger('click');
						} );
					});
				</script>";
   			}
   		}
	}


	public function wcfm_direct_paypal_disable_multivendor_checkout( $is_disable ) {
		$is_disable = 'yes';
		return $is_disable;
	}

	public function process_withdrawal_after_itn_response() {
		$data = stripslashes_deep( $_POST );

		$payfast_error  = false;
		$payfast_done   = false;
		$session_id     = $data['custom_str1'];
		$order_id       = absint( $data['custom_str3'] );
		$order_key      = wc_clean( $session_id );
		$order          = wc_get_order( $order_id );
		$original_order = $order;

		if ( false === $data ) {
			$payfast_error  = true;
			$payfast_error_message = PF_ERR_BAD_ACCESS;
		}

		// Verify security signature
		if ( ! $payfast_error && ! $payfast_done ) {
			$signature = md5( $this->_generate_parameter_string( $data, false, false ) ); // false not to sort data
			// If signature different, log for debugging
			if ( ! $this->payfast_pg->validate_signature( $data, $signature ) ) {
				$payfast_error         = true;
				$payfast_error_message = PF_ERR_INVALID_SIGNATURE;
			}
		}

		// Verify source IP (If not in debug mode)
		if ( ! $payfast_error && ! $payfast_done
			&& $this->payfast_pg->get_option('testmode') != 'yes' ) {

			if ( ! $this->payfast_pg->is_valid_ip( $_SERVER['REMOTE_ADDR'] ) ) {
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_BAD_SOURCE_IP;
			}
		}

		// Verify data received
		if ( ! $payfast_error ) {
			$validation_data = $data;
			unset( $validation_data['signature'] );
			$has_valid_response_data = $this->payfast_pg->validate_response_data( $validation_data );

			if ( ! $has_valid_response_data ) {
				$payfast_error = true;
				$payfast_error_message = PF_ERR_BAD_ACCESS;
			}
		}

		// Check data against internal order
		if ( ! $payfast_error && ! $payfast_done ) {

			// Check order amount
			if ( ! $this->payfast_pg->amounts_equal( $data['amount_gross'], $this->payfast_pg::get_order_prop( $order, 'order_total' ) )
				 && ! $this->payfast_pg->order_contains_pre_order( $order_id )
				 && ! $this->payfast_pg->order_contains_subscription( $order_id ) ) {
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_AMOUNT_MISMATCH;
			} elseif ( strcasecmp( $data['custom_str1'], $this->payfast_pg::get_order_prop( $order, 'order_key' ) ) != 0 ) {
				// Check session ID
				$payfast_error  = true;
				$payfast_error_message = PF_ERR_SESSIONID_MISMATCH;
			}
		}

		if( $payfast_error ) wcfm_payfast_log( $payfast_error_message, 'error' );

		// alter order object to be the renewal order if
		// the ITN request comes as a result of a renewal submission request
		$description = json_decode( $data['item_description'] );

		if ( ! empty( $description->renewal_order_id ) ) {
			$order = wc_get_order( $description->renewal_order_id );
		}

		// Get internal order and verify it hasn't already been processed
		if ( ! $payfast_error && ! $payfast_done ) {

			// Check if order has already been processed
			if ( 'completed' === $this->payfast_pg::get_order_prop( $order, 'status' ) ) {
				$payfast_done = true;
			}
		}

		// If an error occurred
		if ( ! $payfast_done ) {

			if ( $this->payfast_pg::get_order_prop( $original_order, 'order_key' ) !== $order_key ) {
				exit;
			}

			$status = strtolower( $data['payment_status'] );

			if ( 'complete' === $status ) {
				global $WCFM, $WCFMmp, $wpdb;

				$split_method = WCFMpgpf_GATEWAY;
				$split_payers = array();
				$vendor_wise_gross_sales = $WCFMmp->wcfmmp_commission->wcfmmp_split_pay_vendor_wise_gross_sales( $order );

				if( $vendor_wise_gross_sales && is_array($vendor_wise_gross_sales) ) {
					foreach( $vendor_wise_gross_sales as $vendor_id => $distribution_info ) {
						$vendor_payment_method = $WCFMmp->wcfmmp_vendor->get_vendor_payment_method( $vendor_id );
						if( ( $vendor_payment_method == $split_method ) && apply_filters( 'wcfmmp_is_allow_vendor_'.$split_method.'_split_pay', true, $vendor_id ) ) {
							
							$vendor_order_amount = $WCFMmp->wcfmmp_commission->wcfmmp_calculate_vendor_order_commission( $vendor_id, $order->get_id(), $order );
							$vendor_commission = round( $vendor_order_amount['commission_amount'], 2 );
							wcfm_payfast_log( "Stripe Split Pay:: #" . $order->get_id() . " => " . $vendor_id . " => " . $vendor_commission, 'info' );
							if( $vendor_commission > 0 ) {
								$split_payers[$vendor_id] = array(
									'commission'  => $vendor_commission,
								);
								if( isset( $vendor_wise_gross_sales[$vendor_id] ) ) {
									$split_payers[$vendor_id]['gross_sales'] = round( $vendor_wise_gross_sales[$vendor_id], 2 );
								}
							}
						}
					}
				}

				if(count($split_payers) > 0) {
					$i = 0;
					foreach($split_payers as $vendor_id => $distribution_info) {
						
						$store_name = $WCFM->wcfm_vendor_support->wcfm_get_vendor_store_name_by_vendor( absint($vendor_id) );
						
						// Fetching Total Commission from Vendor Order Newly 
						$re_total_commission = $wpdb->get_var("SELECT SUM(total_commission) as total_commission FROM `{$wpdb->prefix}wcfm_marketplace_orders` WHERE order_id =" . $order_id . " AND vendor_id = " . $vendor_id);
						
						// Create vendor withdrawal Instance
						$commission_id_list = $wpdb->get_col("SELECT ID FROM `{$wpdb->prefix}wcfm_marketplace_orders` WHERE order_id =" . $order_id . " AND vendor_id = " . $vendor_id);
						
						$withdrawal_id = $WCFMmp->wcfmmp_withdraw->wcfmmp_withdrawal_processed( $vendor_id, $order_id, implode( ',', $commission_id_list ), WCFMpgpf_GATEWAY, $distribution_info['gross_sales'], $re_total_commission, 0, 'pending', 'by_split_pay', 0 );
						
						// Withdrawal Processing
						$WCFMmp->wcfmmp_withdraw->wcfmmp_withdraw_status_update_by_withdrawal( $withdrawal_id, 'completed', __( 'Payfast Split Pay', 'wcfm-pg-payfast' ) );
						
						// Withdrawal Meta
						$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'withdraw_amount', $re_total_commission );
						$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'currency', $order->get_currency() );
						$WCFMmp->wcfmmp_withdraw->wcfmmp_update_withdrawal_meta( $withdrawal_id, 'transaction_id', $data['pf_payment_id'] );
						
						do_action( 'wcfmmp_withdrawal_request_approved', $withdrawal_id );
						
						wcfm_payfast_log( sprintf( '#%s - %s payment processing complete via %s for order %s. Amount: %s', sprintf( '%06u', $withdrawal_id ), $store_name, 'Payfast Split Pay', $order_id, $re_total_commission . ' ' . $order->get_currency() ), 'info' );
					}
				}
			}
		} // End if().
	}

	protected function _generate_parameter_string( $api_data, $sort_data_before_merge = true, $skip_empty_values = true ) {

		// if sorting is required the passphrase should be added in before sort.
		if ( ! empty( $this->payfast_pg->get_option('pass_phrase') ) && $sort_data_before_merge ) {
			$api_data['passphrase'] = $this->payfast_pg->get_option('pass_phrase');
		}

		if ( $sort_data_before_merge ) {
			ksort( $api_data );
		}

		// concatenate the array key value pairs.
		$parameter_string = '';
		foreach ( $api_data as $key => $val ) {

			if ( $skip_empty_values && empty( $val ) ) {
				continue;
			}

			if ( 'signature' !== $key ) {
				$val = urlencode( $val );
				$parameter_string .= "$key=$val&";
			}
		}
		// when not sorting passphrase should be added to the end before md5
		if ( $sort_data_before_merge ) {
			$parameter_string = rtrim( $parameter_string, '&' );
		} elseif ( ! empty( $this->payfast_pg->get_option('pass_phrase') ) ) {
			$parameter_string .= 'passphrase=' . urlencode( $this->payfast_pg->get_option('pass_phrase') );
		} else {
			$parameter_string = rtrim( $parameter_string, '&' );
		}

		return $parameter_string;
	}

	
	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'wcfm-pg-payfast' );
		
		//load_plugin_textdomain( 'wcfm-tuneer-orders' );
		//load_textdomain( 'wcfm-pg-payfast', WP_LANG_DIR . "/wcfm-pg-payfast/wcfm-pg-payfast-$locale.mo");
		load_textdomain( 'wcfm-pg-payfast', $this->plugin_path . "lang/wcfm-pg-payfast-$locale.mo");
		load_textdomain( 'wcfm-pg-payfast', ABSPATH . "wp-content/languages/plugins/wcfm-pg-payfast-$locale.mo");
	}
	
	public function load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}
}