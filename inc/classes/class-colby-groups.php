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
        add_action( 'delete_blog', [ $this, 'delete_blog' ] );
        add_action( 'wpmu_new_blog', [ $this, 'activate' ] );
        add_action('init', [ $this, 'sidebar_plugin_register' ] );
        add_action( 'rest_api_init', [ $this,  'register_routes' ] );

        // don't need to enqueue any gutenberg sidebars scripts because we handle that with
        // webpack globally from the app
    }

    /** SIDEBAR */
    public static function sidebar_plugin_register() {
        register_meta( 'post', 'colby_groups_meta_restrcit_to_groups', array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
        ) );

        register_meta( 'post', 'colby_groups_meta_selected_groups', array(
            'type'		=> 'string',
            'single'	=> true,
            'show_in_rest'	=> true,
        ) );
    }
    
    public static function activate() {

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
    
        $blogs = array();
        
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
            if ( $blog != 1 ) { 
                $table_name = $wpdb->base_prefix . $blog . '_cc_group_roles'; 
            } else { 
                $table_name = $wpdb->base_prefix . 'cc_group_roles'; 
            }
        
            $sql = "CREATE TABLE $table_name (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                group_id bigint(20) NOT NULL,
                post_id bigint(20) default 0,
                roles longtext,
                UNIQUE KEY ID (ID)
            ) $charset_collate;";
    
            dbDelta( $sql );
        }
    }

    public static function unload() {
		global $ccg_user_roles;
		$currentuser = wp_get_current_user();
		
		if ( $currentuser && $ccg_user_roles ) {
			foreach ( $ccg_user_roles as $role ) {
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


    public function colby_groups_get_groups_for_post_endpoint($request) {
        global $wpdb;
        $user = wp_get_current_user();
        if (!in_array( 'subscriber', $user->roles)) {
            $dbprefix = $wpdb->base_prefix;
            if ($request['blog_id'] != 1) { 
                $dbprefix .= $request['blog_id'] . '_'; 
            }
            if (isset($request['id'])) {
                $s_groups = $wpdb->get_results($wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles, " . $dbprefix . "cc_group_roles.ID, ".$wpdb->base_prefix."ccg_groups.group_name FROM ".$wpdb->base_prefix."ccg_groups JOIN " . $dbprefix . "cc_group_roles ON ".$wpdb->base_prefix."ccg_groups.ID=" . $dbprefix . 'cc_group_roles.group_id WHERE ' . $dbprefix . 'cc_group_roles.post_id=' . $request['id'] . ' ORDER BY '.$wpdb->base_prefix."ccg_groups.group_name", ARRAY_N ), ARRAY_A);
                return $s_groups;
            } else {
                return rest_ensure_response( 'Err: Invalid Post Id.' );
            }
        } else {
            return rest_ensure_response( 'Err: Forbidden.' );
        }
    }
    
    public function colby_groups_index($request) {
        global $wpdb;
        $user = wp_get_current_user();
        if ( !in_array( 'subscriber', $user->roles ) ) {
            $a_stmt = "SELECT ".$wpdb->base_prefix."ccg_groups.group_name, " . $wpdb->base_prefix."ccg_groups.ID, " . $wpdb->base_prefix."ccg_groups.group_description, " . $wpdb->base_prefix."ccg_groups.group_type FROM ".$wpdb->base_prefix."ccg_groups ORDER BY ".$wpdb->base_prefix."ccg_groups.group_name";
            $a_groups = $wpdb->get_results( $wpdb->prepare( $a_stmt, ARRAY_N ), ARRAY_A );
            
            return $a_groups;
        } else {
            return rest_ensure_response( 'Forbidden.' );
        }
    }
    
      public function register_routes() {
        register_rest_route('colby-groups/v1', '/groups', array(
                'methods'  => 'GET',
                'callback' => [ $this, 'colby_groups_index' ],
        ));
        register_rest_route('colby-groups/v1', '/groupsForPost/(?P<blog_id>[\d]+)/(?P<id>[\d]+)', array(
            'methods'  => 'GET',
            'callback' => 'colby_groups_get_groups_for_post_endpoint',
        ));
    }
}