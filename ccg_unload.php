<?php

if ( ! class_exists('CCG_Unload') ) {
    class CCG_Unload {
        public static function unload() {
		global $ccg_user_roles;
		$currentuser = wp_get_current_user();
		
		if ( $currentuser && $ccg_user_roles ) {
			foreach ( $ccg_user_roles as $role ) {
				/* error_log( "CCG: restoring original user role to $role for " . $currentuser->user_login ); */
				$currentuser->set_role( strtolower( $role ) );
			}
		}
	}
		
		public static function ccg_delete_blog( $blog_id, $drop ) {
			global $wpdb;
			
			$dbprefix = $wpdb->base_prefix;
			if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
		
			$stmt = 'DROP TABLE ' . $dbprefix . 'cc_group_roles';
			$wpdb->query( $stmt );
		}
	}
}
