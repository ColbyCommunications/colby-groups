<?php
if ( !defined( 'CCG_DEBUG' ) )
        define( 'CCG_DEBUG', false );

if ( ! function_exists('ccg_error') ) {
	function ccg_error( $err_slug, $arg2 = '' ) {
	        include_once( dirname(__FILE__).'/error_ccg.php');
	        return ( 'old_wp' == $err_slug ) ? CCG_Error::old_wp( 'Colby College Groups', $arg2 ) : CCG_Error::error_notice( $err_slug );
	}
}
