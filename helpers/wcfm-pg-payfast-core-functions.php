<?php
if( !function_exists( 'wcfm_payfast_log' ) ) {
	function wcfm_payfast_log( $message, $level = 'debug' ) {
		wcfm_create_log( $message, $level, 'wcfm-payfast' );
	}
}