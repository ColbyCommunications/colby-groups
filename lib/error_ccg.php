<?php

if ( ! class_exists('CCG_Error') ) {
	class CCG_Error {
		public static function old_ccg( $ext_title, $min_ccg_version ) {
			self::error_box( sprintf( __('%1$s won&#39;t work until you upgrade Colby College Groups to version %2$s or later.', 'pp'), $ext_title, $min_pp_version ) );
			return true;
		}

		public static function old_wp( $ext_title, $min_wp_version ) {
			self::error_box( sprintf( __('%1$s won&#39;t work until you upgrade WordPress to version %2$s or later.', 'pp'), $ext_title, $min_wp_version ) );
			return false;
		}

		public static function error_notice( $err ) {
			switch( $err ) {
				case '' :
					break;
				default :
					print "<p>Unknown error: $err</p>";
					break;
			}
		}

		static function error_box( $msg ) {
			global $pagenow;

			if ( isset( $pagenow ) && ( 'update.php' != $pagenow ) ) {
				$func_body = "echo '" .
				'<div id="message" class="error fade" style="color: black"><p><strong>' . $msg . '</strong></p></div>' .
				"';";

				add_action('all_admin_notices', create_function('', $func_body) );
			}
		}
	}
}
