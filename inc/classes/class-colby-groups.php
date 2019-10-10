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
        add_action( 'admin_enqueue_scripts', [ $this,  'colby_groups_script_enqueue' ] );
        add_action( 'wp_enqueue_scripts', [$this, 'colby_groups_script_enqueue' ]  );
    }

    public static function colby_groups_script_enqueue() {
        $bundle_js_path = WP_PLUGIN_DIR . '/colby-groups/build/js.bundle.filename';
        $bundle_css_path = WP_PLUGIN_DIR . '/colby-groups/build/css.bundle.filename';

        // I don't love this, but only load full contents if on dev and running webpack-dev-server
        // otherwise use relative path from the plugin root
        if ("ON" !== getenv('LANDO')) {
            $js_bundle_filename_contents = PLUGIN_URL . fgets(fopen($bundle_js_path, 'r'));
            if (file_exists($bundle_css_path)) {
                $css_bundle_filename_contents = PLUGIN_URL . fgets(fopen($bundle_css_path, 'r'));
            }
        } else {
            $js_bundle_filename_contents = fgets(fopen($bundle_js_path, 'r'));
            if (file_exists($bundle_css_path)) {
                $css_bundle_filename_contents = fgets(fopen($bundle_css_path, 'r'));
            }
        }

        wp_enqueue_script( 
            'colby-groups',
            $js_bundle_filename_contents,
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-plugins', 'wp-i18n', 'wp-components'],
            '',
            true    
        );

        if (file_exists($bundle_css_path)) {
            wp_enqueue_style( 
                'colby-groups',
                $css_bundle_filename_contents,
                [],
                '',
                'screen'
            );
        }
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
    
    public function colby_groups_get_groups_for_site($request) {
        return ['groups' => get_option("site_groups")];
    }

    public function colby_groups_set_groups_for_site($request) {
        $params = $request->get_params();
        update_option("site_groups", $request['groups']);
        return "success";
    }
    
    public function register_routes() {
        register_rest_route('colby-groups/v1', '/groups', array(
            'methods'  => 'GET',
            'callback' => [ $this, 'colby_groups_index' ],
        ));
        register_rest_route('colby-groups/v1', '/set-site-groups', array(
            'methods'  => 'POST',
            'callback' => [ $this, 'colby_groups_set_groups_for_site' ],
        ));
        register_rest_route('colby-groups/v1', '/get-site-groups', array(
            'methods'  => 'GET',
            'callback' => [ $this, 'colby_groups_get_groups_for_site' ],
        ));
    }
}