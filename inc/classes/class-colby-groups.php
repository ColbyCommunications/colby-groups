<?php
/**
 * Class ColbyGroups
 *
 * @since 1.0.0
 */

namespace ColbyCollege\Plugins\ColbyGroups;


/**
 * ColbyGroups class.
 *
 * @since 1.0.0
 */
class ColbyGroups {
		
	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load' ], -10, 0);
        add_action( 'delete_blog', [ $this, 'delete_blog' ] );
        add_action( 'wpmu_new_blog', [ $this, 'activate' ] );
    }

    public function activate() {
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
		
    public static function delete_blog( $blog_id, $drop ) {
        global $wpdb;
        
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
    
        $stmt = 'DROP TABLE ' . $dbprefix . 'cc_group_roles';
        $wpdb->query( $stmt );
    }
	

}