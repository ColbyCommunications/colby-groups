<?php
/*
/**
 * Plugin Name: Colby Groups
 * Plugin URI:  [none]
 * Description: Colby Groups applies active directory groups to WP roles for WordPress blogs
 * Author:      Keith McGlauflin
 * Author URI:  http://www.colby.edu/directory/keith.mcglauflin/
 * Version:     1.0.0
 * Text Domain: ccg
 * Domain Path: /languages/
 * Min WP Version: 3.4

   Copyright (C) 2014-2015 Colby College - use with permission only!!!
*/

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! file_exists( ABSPATH . 'wp-content/plugins/colbyTicket.php' ) ) {
	return;
}

define( 'LOCATION_401', '/web/prod/colby/search_cs/401_wp.php' );
define( 'LOCATION_403', '/web/prod/colby/search_cs/403.html' );
define( 'LOCATION_404', '/web/prod/colby/search_cs/search.html' );
define( 'CCG_DEBUG',  0);
/* some plugins might require this to be a different value, because they also muck
   with blog privacy settings...not sure how to detect what the next value should be */
define( 'CCG_BLOG_PUBLIC_COLBY_ONLY', -1 );
define( 'CCG_RESTRICT_EDITORS', 1);

if ( ! defined( 'ABSPATH' ) ) exit;

if ( defined( 'CCG_FOLDER' ) ) {
	require_once( dirname(__FILE__).'/lib/ccg_bootstrap_lib.php' );
	ccg_error('multiple_ccg');
} else {
	define( 'CCG_FILE', __FILE__ );
	define( 'CCG_BASENAME', plugin_basename( __FILE__ ) );
	define( 'CCG_FOLDER', dirname( plugin_basename( __FILE__ ) ) );

	add_action( 'plugins_loaded', '_ccg_act_load', -10, 0);
        add_action( 'admin_init', '_add_grav_forms' );

        function _add_grav_forms() {
                $role = get_role( 'editor' );
		if ( method_exists( $role, "add_cap") ) {
	                $role->add_cap( 'gform_full_access' );
	                $role->add_cap( 'gravityforms_api_settings' );
	                $role->add_cap( 'gravityforms_create_form' );
	                $role->add_cap( 'gravityforms_delete_entries' );
	                $role->add_cap( 'gravityforms_delete_forms' );
	                $role->add_cap( 'gravityforms_edit_entries' );
	                $role->add_cap( 'gravityforms_edit_entry_notes' );
	                $role->add_cap( 'gravityforms_edit_forms' );
	                $role->add_cap( 'gravityforms_edit_settings' );
	                $role->add_cap( 'gravityforms_export_entries' );
	                $role->add_cap( 'gravityforms_preview_forms' );
	                $role->add_cap( 'gravityforms_uninstall' );
	                $role->add_cap( 'gravityforms_view_addons' );
	                $role->add_cap( 'gravityforms_view_entries' );
	                $role->add_cap( 'gravityforms_view_entry_notes' );
	                $role->add_cap( 'gravityforms_view_settings' );
	                $role->add_cap( 'gravityforms_view_updates' );
		}

		$role = get_role( 'acad_editor' );
                if ( method_exists( $role, "add_cap") ) {
                        $role->add_cap( 'gform_full_access' );
                        $role->add_cap( 'gravityforms_api_settings' );
                        $role->add_cap( 'gravityforms_create_form' );
                        $role->add_cap( 'gravityforms_delete_entries' );
                        $role->add_cap( 'gravityforms_delete_forms' );
                        $role->add_cap( 'gravityforms_edit_entries' );
                        $role->add_cap( 'gravityforms_edit_entry_notes' );
                        $role->add_cap( 'gravityforms_edit_forms' );
                        $role->add_cap( 'gravityforms_edit_settings' );
                        $role->add_cap( 'gravityforms_export_entries' );
                        $role->add_cap( 'gravityforms_preview_forms' );
                        $role->add_cap( 'gravityforms_uninstall' );
                        $role->add_cap( 'gravityforms_view_addons' );
                        $role->add_cap( 'gravityforms_view_entries' );
                        $role->add_cap( 'gravityforms_view_entry_notes' );
                        $role->add_cap( 'gravityforms_view_settings' );
                        $role->add_cap( 'gravityforms_view_updates' );
                        $role->add_cap( 'tablepress_list_tables' );
                        $role->add_cap( 'tablepress_add_tables' );
                        $role->add_cap( 'tablepress_import_tables' );
                        $role->add_cap( 'tablepress_export_tables' );
                        $role->add_cap( 'tablepress_access_options_screen' );
                        $role->add_cap( 'tablepress_access_about_screen' );
			$role->add_cap( 'tablepress_edit_tables' );
			$role->add_cap( 'tablepress_copy_tables' );
			$role->add_cap( 'tablepress_delete_tables' );
                }
        }

	function _ccg_act_load() {
		$min_wp_version = '3.4';

		require_once( dirname(__FILE__).'/lib/ccg_bootstrap_lib.php' );

		global $wp_version;
		if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
			ccg_error( 'old_wp', $min_wp_version );
		}

		require_once( dirname(__FILE__).'/ccg_load.php' );
	}

	register_activation_hook( __FILE__, 'ccg_activate' );
	register_deactivation_hook( __FILE__, 'ccg_deactivate' );

	function ccg_activate( $network_wide ) {
		global $wpdb;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$blogs=array();
		
		if ( $network_wide ) {
			/* create an array of all of the blogs on this network */
			$args = array(
				'network_id' => $wpdb->siteid,
				'public'     => null,
				'archived'   => null,
				'mature'     => null,
				'spam'       => null,
				'deleted'    => null,
				'limit'      => 10000,
				'offset'     => 0,
			);
			
			$siteblogs = wp_get_sites( $args );
			foreach ( $siteblogs as $blog ) {
				array_push( $blogs, $blog[ 'blog_id' ] );
			}
			
			/* error_log( "CCG: this is a network-wide activation" ); */
		} else {
			/* activating for this site only */
			array_push( $blogs, get_current_blog_id() );
		}
		
		$charset_collate = '';
	
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
	
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}
		
		/* create the groups table */
		$sql = "CREATE TABLE ".$wpdb->base_prefix."ccg_groups (
			ID bigint(20) NOT NULL AUTO_INCREMENT,
			group_name text NOT NULL,
			group_description text NOT NULL,
                        group_type ENUM('AD','CC') default 'AD' NOT NULL,
			PRIMARY KEY  (ID)
		)";
		dbDelta( $sql );
		
		/* create the group membership table */
		$sql = "CREATE TABLE ".$wpdb->base_prefix."ccg_group_members (
			group_id bigint(20) unsigned NOT NULL DEFAULT '0',
			user_id bigint(20) unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY  (group_id,user_id)
		)";
		dbDelta( $sql );
		
		foreach ( $blogs as $blog ) {
			if ( $blog != 1 ) { $table_name = $wpdb->base_prefix . $blog . '_cc_group_roles'; }
				else { $table_name = $wpdb->base_prefix . 'cc_group_roles'; }
		
			$sql = "CREATE TABLE $table_name (
				ID bigint(20) NOT NULL AUTO_INCREMENT,
				group_id bigint(20) NOT NULL,
				post_id bigint(20) default 0,
				roles longtext,
				UNIQUE KEY ID (ID)
			) $charset_collate;";

			dbDelta( $sql );
		}
		
		add_option( "ccg_db_version", "1.0" );
	}

	function ccg_deactivate( $network_wide ) {
		global $wpdb;
		
		/* do we need to do anything here? */
		/* error_log( "CCG: deactivating plugin" ); */
	}
}

?>
