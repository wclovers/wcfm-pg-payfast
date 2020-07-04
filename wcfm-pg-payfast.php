<?php
/**
 * Plugin Name: WCFM Marketplace Vendor Payment - Payfast
 * Plugin URI: https://wclovers.com/product/woocommerce-multivendor-membership
 * Description: WCFM Marketplace payfast vendor payment gateway 
 * Author: WC Lovers
 * Version: 1.0.0
 * Author URI: https://wclovers.com
 *
 * Text Domain: wcfm-pg-payfast
 * Domain Path: /lang/
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.0
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if(!defined('WCFM_TOKEN')) return;
if(!defined('WCFM_TEXT_DOMAIN')) return;

if ( ! class_exists( 'WCFMpgpf_Dependencies' ) )
	require_once 'helpers/class-wcfm-pg-payfast-dependencies.php';

if( !WCFMpgpf_Dependencies::woocommerce_plugin_active_check() )
	return;

if( !WCFMpgpf_Dependencies::wcfm_plugin_active_check() )
	return;

if( !WCFMpgpf_Dependencies::wcfmmp_plugin_active_check() )
	return;

require_once 'helpers/wcfm-pg-payfast-core-functions.php';
require_once 'wcfm-pg-payfast-config.php';

if(!class_exists('WCFM_PG_Payfast')) {
	include_once( 'core/class-wcfm-pg-payfast.php' );
	global $WCFM, $WCFMpgpf, $WCFM_Query;
	$WCFMpgpf = new WCFM_PG_Payfast( __FILE__ );
	$GLOBALS['WCFMpgpf'] = $WCFMpgpf;
}