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
        add_action( 'admin_enqueue_scripts', [ $this, 'sidebar_plugin_script_enqueue' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'sidebar_plugin_script_enqueue' ]  );
        add_action('enqueue_block_editor_assets', [ $this, 'sidebar_plugin_script_enqueue' ]);
    }

    /** SIDEBAR */
    public static function sidebar_plugin_register() {
        register_meta( 'post', 'sidebar_plugin_meta_block_field', array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
        ) );
    }
    
    public static function sidebar_plugin_script_enqueue() {
        global $post;
        $plugin_js_path = plugin_dir_url(__DIR__) . '/dist/plugin.bundle.js';
        // $plugin_css_path = "/js/plugin-sidebar.css";
        
        wp_enqueue_script( 
            'colby-groups',
            $plugin_js_path,
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-plugins', 'wp-i18n', 'wp-components'],
            filemtime($plugin_js_path),
            true    
        );
            
        // wp_enqueue_style(
        //     'your-plugin-css',
        //     _get_plugin_url() . $plugin_css_path,
        //     [],
        //     filemtime( _get_plugin_directory() . $plugin_css_path )
        // );

        
        $colbyGroups = [
            'blogId' => (int)get_current_blog_id(),
            'postId' => (int)$post->ID,
            'siteUrl' => get_site_url(),
        ];
        wp_localize_script( 'test', 'colbyGroups', $colbyGroups );
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
        
        add_option( "ccg_db_version", "1.0" );
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
}