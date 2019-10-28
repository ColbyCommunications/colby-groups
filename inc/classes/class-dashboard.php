<?php
/**
 * Class Dashboard
 *
 * @since 1.0.0
 */

namespace ColbyCollege\Plugins\ColbyGroups;
define( 'CCG_BLOG_PUBLIC_COLBY_ONLY', -1 );

/**
 * Dashboard class.
 *
 * @since 1.0.0
 */
class Dashboard {
    /**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() { 
        add_action( 'admin_menu', [ $this, 'menus' ] );
        add_action( 'blog_privacy_selector', [ $this, 'add_privacy_options' ] );
        add_action( 'admin_bar_menu', [ $this, 'colbygroups_toolbar_mods' ], 50 );
        add_filter( 'get_blogs_of_user', [ $this, 'colbygroups_add_group_blogs' ] );
        add_filter( 'wp_dropdown_users_args', [ $this, 'colbygroups_dropdown_users' ], 10, 2 );
        add_action( 'the_title', [ $this, 'colbygroups_remove_private' ] );
        add_filter( 'wp_dropdown_pages', [ $this, 'colbygroups_private_parent_metabox' ] );

    }

    public static function menus() {
        add_submenu_page( 'users.php', 'Colby Groups Administration', 'Colby Groups', 'remove_users', 'colbyGroups/colbyGroups.php', [ $this,'menu_processing' ] );
    }

    public static function menu_processing( $post_id = 0 ) {
        echo '<div id="colby-groups-admin-page"></div>';
    }


        public static function colbygroups_dropdown_users( $query_args, $r ) {
                    global $wpdb;

                    $dbprefix = $wpdb->base_prefix;
                    if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }

                    // Get all of the groups for this blog
                    $savedGroups = $wpdb->get_results(
                            $wpdb->prepare( 'SELECT ' . $wpdb->base_prefix . 'ccg_groups.group_name FROM ' . $wpdb->base_prefix.'ccg_groups JOIN ' . $dbprefix . 'cc_group_roles ON ' . $wpdb->base_prefix . 'ccg_groups.ID = ' . $dbprefix . 'cc_group_roles.group_id where roles != \'a:1:{i:0;s:10:"subscriber";}\' and post_id=0', ARRAY_N )
                    );

                    // Get all of the users for each group (use LDAP as it won't include closed accounts)
                    $include = Array();

                    // Get all of the users for this blog with author perms
                    $users = get_users( array( 'blog_id' =>  get_current_blog_id(), 'who' => 'authors' ) );
                    //$users = get_users( 'blog_id=' . get_current_blog_id() . '&role=author' );
                    foreach ( $users as $user ) {
                            //echo "Adding user ID " . $user->user_login . "/" . $user->ID . "<br/>";
                            array_push( $include, $user->ID );
                    }

                    if ( $savedGroups ) {
                            try {
                                    $ds=ldap_connect("ldaps.colby.edu");
                                    if ( !( $dsb=ldap_bind($ds,"COLBY\www","ca4443.tkadk1") ) ) {
                                            throw new Exception("Connect/bind failed.");
                                    }
                            } catch( Exception $error ) {
                                    try {
                                            $ds=ldap_connect("ldap.colby.edu");
                                                if ( !( $dsb=ldap_bind($ds,"COLBY\www","ca4443.tkadk1") ) ) {
                                                    throw new Exception("Connect/bind failed.");
                                            }
                                    } catch( Exception $error ) {
                                            // couldn't bind to dc3 or dc2 - bummer
                                    }
                            }

                            foreach ( $savedGroups as $group ) {
                                    if ( $ds && $dsb ) {
                                            try {
                                                    $dn = "OU=Groups, DC=colby, DC=edu";
                                                    $filter = "(&(cn=" . $group->group_name . "))";
                                                    $fields = array("member");

                                                    $sr=ldap_search($ds, $dn, $filter, $fields);
                                                    $data = ldap_get_entries($ds, $sr);
                                                    $membership=$data[0]['member'];

                                                    for ( $i=0; $i<count($membership); $i++ ) {
                                                            if ( '' != $membership[$i] ) {
                                                                    // get the account name for this member
                                                                    list( $cn, $junk ) = explode( ',OU=', $membership[$i], 2 );
                                                                    $cn = str_replace( '\\', '', $cn );
                                                                    $cn = str_replace( 'CN=', '', $cn );

                                                                    $dn = "OU=People, DC=colby, DC=edu";
                                                                    $filter="(&(CN=$cn))";
                                                                    $fields = array("sAMAccountName");

                                                                    $sr=ldap_search($ds, $dn, $filter, $fields);
                                                                    $data = ldap_get_entries($ds, $sr);
                                                                    $info = $data[0]['samaccountname'];

                                                                    // get the ID of the user from WP
                                                                    $ad_user = get_user_by( 'login', $info[0] );
                                                                    if ( '' != $ad_user->ID ) {
                                                                            array_push( $include, $ad_user->ID );
                                                                    }
                                                            }
                                                    }

                                            } catch( Exception $error ) {
                                                    // no groups because connecting/binding/querying failed, oh well...
                                            }
                                    }
                            }

                            ldap_close($ds);
                    }

                    //$query_args[ 'who' ] = '';
                    unset( $query_args['who'] );
                    $query_args[ 'include' ] = $include;
                    $query_args[ 'blog_id' ] = Array( '1' );
                    return $query_args;
            }

    
    public static function add_privacy_options( $options ) {
        echo '<label class="checkbox" for="blog-private' . CCG_BLOG_PUBLIC_COLBY_ONLY . '"><input id="blog-private' . CCG_BLOG_PUBLIC_COLBY_ONLY . '" type="radio" name="blog_public" value="' . CCG_BLOG_PUBLIC_COLBY_ONLY . '" ';
        checked(CCG_BLOG_PUBLIC_COLBY_ONLY, get_option('blog_public'));
        echo '/> Only selected Colby Groups (see <a href="users.php?page=colbyGroups/colbyGroups.php">Users -> Colby Groups</a>)</label>';
    }
    
    public static function colbygroups_add_group_blogs( $blogs ) {
        global $wpdb;
        global $post;
        
        /* get the current user's ID */
        $user_ID = get_current_user_id();
    
        /* prefix for site DB tables */
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
        
        /* look for the post_id in group_roles if the post's status is "private" */
        $post_id_sql = '';
        if ( $post && $post->post_status == 'private' ) {
            $post_id_sql = 'OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post->ID;
        }
        
        /* get AD group roles for this blog */
        $roles = $wpdb->get_row(
            $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $user_ID . ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0 ' . $post_id_sql . ' )', ARRAY_N )
        );
        
        if ( $roles ) {
            $this_blog = get_blog_details();
            array_push( $blogs, $this_blog );
        }
        
        /* get the CC group roles for this blog */
        if ( $_COOKIE[ 'ColbyTicket' ] ) {
            $cookie_items = explode( '&', $_COOKIE[ 'ColbyTicket' ] );
            $cookie = array();
            for ( $i = 0; $i < count( $cookie_items ); $i = $i + 2 ) {
                $cookie[ $cookie_items[ $i ] ] = $cookie_items[ $i + 1 ];
            }
        
            if ( $cookie[ 'profile' ] ) {
                $in_clause = '';
                $cc_groups = explode( '/', $cookie[ 'profile' ] );
                foreach ( $cc_groups as $cc_group ) {
                    /* error_log( "CCG: profile group is " . $cc_group ); */
                    $in_clause .= $in_clause ? ", '$cc_group'" : "'$cc_group'";
                }
            
                if ( $in_clause ) {
                    $post_clause = ' AND ' . $dbprefix . 'cc_group_roles.post_id=0';
                
                    $roles = $wpdb->get_row(
                        $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $dbprefix . "cc_group_roles JOIN " . $wpdb->base_prefix . "ccg_groups ON " . $dbprefix . "cc_group_roles.group_id=" . $wpdb->base_prefix . "ccg_groups.id WHERE " . $wpdb->base_prefix . "ccg_groups.group_type='CC' AND " . $wpdb->base_prefix . "ccg_groups.group_name IN ( $in_clause )" . $post_clause, ARRAY_N )
                    );

                    if ( $roles ) {
                        $this_blog = get_blog_details();
                        array_push( $blogs, $this_blog );
                    }
                }
            }
        }

        return $blogs;
    }
    
    function colbygroups_private_parent_metabox($output){
        global $post;
        
        $args = array(
            'post_type'			=> $post->post_type,
            'exclude_tree'		=> $post->ID,
            'selected'			=> $post->post_parent,
            'name'				=> 'parent_id',
            'show_option_none'	=> __('(no parent)'),
            'sort_column'		=> 'menu_order, post_title',
            'echo'				=> 0,
            'post_status'		=> array('publish', 'private'),
        );

        $defaults = array(
            'depth'					=> 0,
            'child_of'				=> 0,
            'selected'				=> 0,
            'echo'					=> 1,
            'name'					=> 'page_id',
            'id'					=> '',
            'show_option_none'		=> '',
            'show_option_no_change'	=> '',
            'option_none_value'		=> '',
        );

        $r = wp_parse_args($args, $defaults);
        extract($r, EXTR_SKIP);

        $pages = get_pages($r);
        $name = esc_attr($name);
        // Back-compat with old system where both id and name were based on $name argument
        if (empty($id))
        {
            $id = $name;
        }

        if (!empty($pages))
        {
            $output = "<select name=\"$name\" id=\"$id\">\n";

            if ($show_option_no_change)
            {
                $output .= "\t<option value=\"-1\">$show_option_no_change</option>";
            }
            if ($show_option_none)
            {
                $output .= "\t<option value=\"" . esc_attr($option_none_value) . "\">$show_option_none</option>\n";
            }
            $output .= walk_page_dropdown_tree($pages, $depth, $r);
            $output .= "</select>\n";
        }

        return $output;
    }
    
    public static function colbygroups_remove_private( $title ) {
        global $post;
        if ( isset($post->post_status) && 'private' == $post->post_status ) {
            if ( substr($title,0,9) == 'Private: ' ){
                $title = substr($title,9);
            }
        }
        return $title;
    }
    
    public static function colbygroups_toolbar_mods( $wp_toolbar ) {
        global $wpdb;
        global $post;
        
        if ( !is_admin() ) {
            $all_toolbar_nodes = $wp_toolbar->get_nodes();
            $site_title = get_bloginfo( 'name' );
        
            $has_site_links = 0;
            foreach ( $all_toolbar_nodes as $node ) {
                if ( $node->title == $site_title && $node->parent == '' ) $has_site_links = 1;
            }
        
            if ( !$has_site_links ) {
                /* does this user have a group role in this blog? */
                $has_group_role = 0;
                
                /* does this user belong to any editor or author groups? */
                $has_edit_or_admin = 0;
                
                /* get the current user's ID */
                $user_ID = get_current_user_id();
            
                /* prefix for site DB tables */
                $dbprefix = $wpdb->base_prefix;
                if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
            
                /* look for the post_id in group_roles if the post's status is "private" */
                $post_id_sql = '';
                if ( $post->post_status == 'private' ) {
                    $post_id_sql = 'OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post->ID;
                }
                
                /* get AD group roles for this blog */
                $roles = $wpdb->get_row(
                    $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $user_ID . ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0 ' . $post_id_sql . ' )', ARRAY_N )
                );
                
                if ( $roles ) {
                    $has_group_role = 1;
                    /* error_log( "CCG: checked roles for " . $user_ID ); */
                    foreach ( $roles as $role ) {
                        /* for each role, does this role have the read post capability? */
                        $grouproles = unserialize( $role );
                        foreach ( $grouproles as $grouprole ) {
                            if ( $grouprole == 'administrator' || $grouprole == 'editor' ) $has_edit_or_admin = 1;
                        }
                    }
                }
                
                if ( $_COOKIE[ 'ColbyTicket' ] ) {
                    /* get the CC group roles for this blog */
                    $cookie_items = explode( '&', $_COOKIE[ 'ColbyTicket' ] );
                    $cookie = array();
                    for ( $i = 0; $i < count( $cookie_items ); $i = $i + 2 ) {
                        $cookie[ $cookie_items[ $i ] ] = $cookie_items[ $i + 1 ];
                    }
            
                    if ( $cookie[ 'profile' ] ) {
                        $in_clause = '';
                        /* error_log( "CCG: profile is " . $cookie[ 'profile' ] ); */
                        $cc_groups = explode( '/', $cookie[ 'profile' ] );
                        foreach ( $cc_groups as $cc_group ) {
                            /* error_log( "CCG: profile group is " . $cc_group ); */
                            $in_clause .= $in_clause ? ", '$cc_group'" : "'$cc_group'";
                        }
                
                        /* error_log( "CCG: in clause is $in_clause" ); */
                
                        if ( $in_clause ) {
                            $post_clause = ' AND ' . $dbprefix . 'cc_group_roles.post_id=0';
                    
                            $roles = $wpdb->get_row(
                                $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $dbprefix . "cc_group_roles JOIN " . $wpdb->base_prefix . "ccg_groups ON " . $dbprefix . "cc_group_roles.group_id=" . $wpdb->base_prefix . "ccg_groups.id WHERE " . $wpdb->base_prefix . "ccg_groups.group_type='CC' AND " . $wpdb->base_prefix . "ccg_groups.group_name IN ( $in_clause )" . $post_clause, ARRAY_N )
                            );

                            if ( $roles ) {
                                $has_group_role = 1;
                                foreach ( $roles as $role ) {
                                    /* error_log( "CCG: checking CC role " . $role ); */
                                    $grouproles = unserialize( $role );
                                    foreach ( $grouproles as $grouprole ) {
                                        if ( $grouprole == 'administrator' || $grouprole == 'editor' ) $has_edit_or_admin = 1;
                                    }
                                }
                            }
                        }
                    }
                }

                if ( $has_group_role ) {
                    $wpurl = get_bloginfo( 'wpurl' );
                
                    /* add the site menu */
                    $wp_toolbar->add_node(array(
                        'id' => 'site-name',
                        'title' => $site_title,
                        'class' => 'menupop',
                        'href' => $wpurl
                    ));
                
                    /* add the Dashboard menu item */
                    $wp_toolbar->add_node(array(
                        'id' => 'dashboard',
                        'title' => 'Dashboard',
                        'parent' => 'site-name',
                        'href' => $wpurl . '/wp-admin/'
                    ));
                
                    if ( $has_edit_or_admin ) {
                        /* add the Themes menu item */
                        if ( !CCG_RESTRICT_EDITORS ) {
                            $wp_toolbar->add_node(array(
                                'id' => 'themes',
                                'title' => 'Themes',
                                'parent' => 'site-name',
                                'href' => $wpurl . '/wp-admin/themes.php'
                            ));
                
                            /* add the Customize menu item */
                            $wp_toolbar->add_node(array(
                                'id' => 'customize',
                                'title' => 'Customize',
                                'parent' => 'site-name',
                                'href' => $wpurl . '/wp-admin/customize.php?url=' . urlencode( $wpurl . '/' )
                            ));
                        }
                
                        /* add the Widgets menu item */
                        $wp_toolbar->add_node(array(
                            'id' => 'widgets',
                            'title' => 'Widgets',
                            'parent' => 'site-name',
                            'href' => $wpurl . '/wp-admin/widgets.php'
                        ));
                
                        /* add the Menus menu item */
                        $wp_toolbar->add_node(array(
                            'id' => 'menus',
                            'title' => 'Menus',
                            'parent' => 'site-name',
                            'href' => $wpurl . '/wp-admin/nav-menus.php'
                        ));
                
                        if ( !CCG_RESTRICT_EDITORS ) {
                            /* add the Header menu item */
                            $wp_toolbar->add_node(array(
                                'id' => 'header',
                                'title' => 'Header',
                                'parent' => 'site-name',
                                'href' => $wpurl . '/wp-admin/themes.php?page=custom-header'
                            ));
                        }
                    }
                }
            }
        }
    }
    
    /* check get the Colby groups limit status of the top-most parent post, return true/false for limited status and the ID */
    function parent_limit( $post_parent ) {
        $parent = get_post( $post_parent );
        
        $limit = ( get_post_meta( $post_parent, 'ccg_limit_groups_' . $post_parent, true ) ) ? true : false;
        

        if ( 0 != $parent->post_parent && false == $limit ) {
            return self::parent_limit( $parent->post_parent );
        }
    
        return array( $limit, $parent->ID );
    }
}
