<?php
/**
 * WC Dependency Checker
 *
 */
class WCFMpgpf_Dependencies {
	
	private static $active_plugins;
	
	static function init() {
		self::$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() )
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
	}
	
	static function woocommerce_plugin_active_check() {
		if ( ! self::$active_plugins ) self::init();
		return in_array( 'woocommerce/woocommerce.php', self::$active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', self::$active_plugins );
		return false;
	}
	
	// WC Frontend Manager
	static function wcfm_plugin_active_check() {
		if ( ! self::$active_plugins ) self::init();
		return in_array( 'wc-frontend-manager/wc_frontend_manager.php', self::$active_plugins ) || array_key_exists( 'wc-frontend-manager/wc_frontend_manager.php', self::$active_plugins );
		return false;
	}
	
	// WCFM Marketplace
	static function wcfmmp_plugin_active_check() {
		if ( ! self::$active_plugins ) self::init();
		return in_array( 'wc-multivendor-marketplace/wc-multivendor-marketplace.php', self::$active_plugins ) || array_key_exists( 'wc-multivendor-marketplace/wc-multivendor-marketplace.php', self::$active_plugins );
		return false;
	}
}