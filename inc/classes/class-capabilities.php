<?php
/**
 * Class Capabilities
 *
 * @since 1.0.0
 */
namespace ColbyCollege\Plugins\ColbyGroups;
define( 'CCG_RESTRICT_EDITORS', 1);
define( 'CCG_BLOG_PUBLIC_COLBY_ONLY', -1 );

/**
 * Capabilities class.
 *
 * @since 1.0.0
 */
class Capabilities {

    /**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
        add_filter( 'user_has_cap', [ $this, 'ccg_filter' ], 100, 3 );
        add_action( 'template_redirect', [ $this, 'ccg_access_check' ] );
        add_action( 'admin_head', [ $this, 'prevent_editor_frontpage' ] );
        // add_filter( '404_template', [ $this, 'ccg_404_template' ], 2000, 1 );
        add_action( 'switch_to_user', [ $this, 'ccg_switch_to_user' ] );
    }
    
    public static function ccg_filter( $allcaps, $cap, $args ) {
        global $wpdb;
        global $post;

        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }

        /* get the AD group roles for this user */

        /* if post_id is passed in $args[2] use that in the WHERE clause to get perm for post as well the site */
        if ( count( $args ) == 3 ) {
            $post_clause = ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=' . $args[2] . ' OR ' . $dbprefix . 'cc_group_roles.post_id=0 )';
        } else {
            $post_clause = ' AND ' . $dbprefix . 'cc_group_roles.post_id=0';
        }

        /* error_log( "CCG: db query SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $args[1] . $post_clause ); */
        
        $roles = $wpdb->get_results(
            "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $args[1] . $post_clause
        );

        if ( $roles ) {
            foreach ( $roles as $role ) {
                $grouproles = unserialize( $role->roles );
                foreach ( $grouproles as $grouprole ) {
                    foreach ( $cap as $mycap ) {
                        $roleperms = get_role( $grouprole );
                        if ( $roleperms->capabilities[ $mycap ] ) {
                            $allcaps[$mycap] = TRUE;
                        }
                    }
                }
            }
        }
        
        
        /* get the Colby group roles (eg "faculty") for this user */
        if ( array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
            $cookie_items = explode( '&', $_COOKIE[ 'ColbyTicket' ] );
            $cookie = array();
            for ( $i = 0; $i < count( $cookie_items ); $i = $i + 2 ) {
                $cookie[ $cookie_items[ $i ] ] = $cookie_items[ $i + 1 ];
            }
            
            if ( $cookie[ 'profile' ] ) {
                $in_clause = '';
                $cc_groups = explode( '/', $cookie[ 'profile' ] );
                foreach ( $cc_groups as $cc_group ) {
                    $in_clause .= $in_clause ? ", '$cc_group'" : "'$cc_group'";
                }
                
                if ( $in_clause ) {
                    /* if post_id is passed in $args[2] use that in the WHERE clause to get perm for post as well the site */
                    
                   

                    if ( count( $args ) == 3 ) {
                        $post_clause = ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=' . $args[2] . ' OR ' . $dbprefix . 'cc_group_roles.post_id=0 )';
                    } else {
                        $post_clause = ' AND ' . $dbprefix . 'cc_group_roles.post_id=0';
                    }
                    
                    $roles = $wpdb->get_results(
                        "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $dbprefix . "cc_group_roles JOIN " . $wpdb->base_prefix . "ccg_groups ON " . $dbprefix . "cc_group_roles.group_id=" . $wpdb->base_prefix . "ccg_groups.id WHERE " . $wpdb->base_prefix . "ccg_groups.group_type='CC' AND " . $wpdb->base_prefix . "ccg_groups.group_name IN ( $in_clause )" . $post_clause
                    );

                    if ( $roles ) {
                        foreach ( $roles as $role ) {
                            $grouproles = unserialize( $role->roles );
                            foreach ( $grouproles as $grouprole ) {
                                foreach ( $cap as $mycap ) {
                                    $roleperms = get_role( $grouprole );
                                    if ( $roleperms->capabilities[ $mycap ] ) {
                                        $allcaps[$mycap] = TRUE;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $allcaps;
    }

    public static function ccg_access_check() {
        global $wpdb;
        global $post;

        /* get the current user's ID */
        $user_ID = get_current_user_id();
        if ( !is_admin() ) {
            // get groups for individual post
            $is_post_restricted = get_post_meta( $post->ID, 'colby_groups_meta_restrict_to_groups', true);
            $post_groups = get_post_meta( $post->ID, 'colby_groups_meta_selected_groups', true);
            $is_site_restricted = CCG_BLOG_PUBLIC_COLBY_ONLY === get_option( 'blog_public' );
            
            if ($is_site_restricted ) {
                /* This blog is marked as "Colby Groups Only" */
                // no cookie, they need to login
                if ( 0 === array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
                    $protocol = $_SERVER['SERVER_PORT'] == '80' ? 'http' : 'https';
                    wp_redirect( $protocol . '://' . $_SERVER['SERVER_NAME'] . '/ColbyMaster/login/?' . $_SERVER['SCRIPT_URI'] );
                    exit();
                } else {
                    /* error_log( "CCG: can this user access this Colby Only blog???" ); */

                    $ad_read_ok = self::ad_user_can_read( $user_ID, $post_groups, $post->ID );
                    $cc_read_ok = self::cc_user_can_read( $user_ID, $post_groups, $post->ID );
                    $read_ok = $ad_read_ok + $cc_read_ok;

                    /* for each role, does this role have the read post capability? */

                    /* if no read post capability for all groups/roles then redirect to the 403 page */
                    if ( 0 == $read_ok ) {
                        header( 'Status: 403 Forbidden', true, 403 );
                        
                        // need to response 403 and have current theme show page
                        exit();
                    }
                }
            } else if ( $is_post_restricted ) {
                // die("here");
                /* check this post, its parent page might be private */
                
                list ( $parent_limit, $parent_id ) = self::parent_limit( $post->post_parent );

                if ( true === $parent_limit ) {
                    
                    $ad_read_ok = self::ad_user_can_read( $user_ID, $parent_limit, $parent_id );
                    $cc_read_ok = self::cc_user_can_read( $user_ID, $parent_limit, $parent_id );
                    $read_ok = $ad_read_ok + $cc_read_ok;
                    
                    /* if no read post capability for all groups/roles then redirect to the Login or 403 page */
                    if ( 0 == $read_ok ) {
                        if ( 0 == array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
                            /* if the user is not authenticated, send them to the login page */
                            $redirect = $_SERVER['REQUEST_URI'];
                            wp_redirect("https://www.colby.edu/ColbyMaster/login/?https://" . $_SERVER['HTTP_HOST'] . $redirect);
                            exit();
                        } else {
                            header( 'Status: 403 Forbidden', true, 403 );
                            exit();
                        }
                    }
                } else {
                    
                    $ad_read_ok = self::ad_user_can_read( $user_ID, $is_post_restricted, $post->ID );
                    $cc_read_ok = self::cc_user_can_read( $user_ID, $is_post_restricted, $post->ID );
                    $read_ok = $ad_read_ok + $cc_read_ok;
                    /* if no read post capability for all groups/roles then redirect to the Login or 403 page */
                    if ( 0 == $read_ok ) {
                        if ( 0 == array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
                            /* if the user is not authenticated, send them to the login page */
                            $redirect = $_SERVER['REQUEST_URI'];
                            wp_redirect("https://www.colby.edu/ColbyMaster/login/?https://" . $_SERVER['HTTP_HOST'] . $redirect);
                            exit();
                        } else {
                            header( 'Status: 403 Forbidden', true, 403 );
                            exit();
                        }
                    }
                }
            }
        }
    }
    
    /* check get the Colby groups limit status of the top-most parent post, return true/false for limited status and the ID */
    public static function parent_limit( $post_parent ) {
        $parent = get_post( $post_parent );
        
        $limit = ( get_post_meta( $post_parent, 'colby_groups_meta_restrict_to_groups', true) ) ? true : false;
        if ( 0 != $parent->post_parent && false == $limit ) { 
            return self::parent_limit( $parent->post_parent ); 
        }
        return array( $limit, $parent->ID );
    }
    
    function ad_user_can_read( $user_ID, $post_limit, $post_id  ) {
        global $wpdb;
        
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() !== 1 ) { 
            $dbprefix .= get_current_blog_id() . '_'; 
        }
        /* look for the post_id in group_roles if the post's status is "private" */
        $post_id_sql = '';
        if ( $post_limit ) {
            $post_id_sql = 'OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post_id;
        }
    
        /* get AD group roles for this blog */
        $roles = $wpdb->get_results(
            $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $user_ID . ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0 ' . $post_id_sql . ' )', ARRAY_N )
        );
        $read_ok = 0;
    
        if ( $roles ) {
            foreach ( $roles as $role ) {
                /* for each role, does this role have the read post capability? */
                $grouproles = unserialize( $role->roles );
                foreach ( $grouproles as $grouprole ) {
                    $roleperms = get_role( $grouprole );
                    foreach ( $roleperms->capabilities as $cap => $value ) {
                        if ( ( $cap === 'read' ) && $value ) {
                            $read_ok = 1;
                        }
                    }
                }
            }
        }
        
        return $read_ok;
    }	/* ad_user_can_read */
    
    function cc_user_can_read( $user_ID, $post_limit, $post_id ) {
        global $wpdb;
        global $post;
        
        $read_ok = 0;
        
        if ( 1 == array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
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
                    $dbprefix = $wpdb->base_prefix;
                    if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
        
                    $post_clause = ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0';
                    if ( true == $post_limit ) {
                        $post_clause .= ' OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post_id;
                    }
                    $post_clause .= ' )';
        
                    $roles = $wpdb->get_row(
                        $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $dbprefix . "cc_group_roles JOIN " . $wpdb->base_prefix . "ccg_groups ON " . $dbprefix . "cc_group_roles.group_id=" . $wpdb->base_prefix . "ccg_groups.id WHERE " . $wpdb->base_prefix . "ccg_groups.group_type='CC' AND " . $wpdb->base_prefix . "ccg_groups.group_name IN ( $in_clause )" . $post_clause, ARRAY_N )
                    );

                    if ( $roles ) {
                        foreach ( $roles as $role ) {
                            /* error_log( "CCG: checking CC role " . $role ); */
                            $grouproles = unserialize( $role );
                            foreach ( $grouproles as $grouprole ) {
                                $roleperms = get_role( $grouprole );
                                foreach ( $roleperms->capabilities as $cap => $value ) {
                                    /* error_log( "CCG: checking cap $cap => $value" ); */
                                    if ( ( $cap == 'read' ) && 1 == $value ) {
                                        /* error_log( "CCG: Read allowed through AD groups" ); */
                                        $read_ok = 1;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        
        return $read_ok;
    } /* ccg_user_can_read */
    
    // public static function ccg_404_template( $template ) {
    //     global $wpdb;
        
    //     $parts=explode('/',$_SERVER['REQUEST_URI']);
    //     $blogs = $wpdb->get_results( "SELECT blog_id FROM $wpdb->blogs WHERE path='/".$parts[1]."/'", OBJECT_K );
        
    //     /* get the current user's ID */
    //     $user_ID = get_current_user_id();
        
        
    //     if ( $blogs ) {
    //         foreach ( $blogs as $blog ) {
    //             $which = count( $parts ) - 2;
    //             switch_to_blog( $blog->blog_id );
                
    //             /* $post = get_page_by_path( $parts[ count( $parts ) - 2 ] ); */
    //             if ( preg_match( '/[a-zA-Z0-9]$/', $_SERVER['REQUEST_URI'] ) ) {
    //                 $post = get_page_by_path( $parts[ count( $parts ) - 1 ] );
    //             } else {
    //                 $post = get_page_by_path( $parts[ count( $parts ) - 2 ] );
    //             }

    //             if ( $post ) {
    //                 if ( $user_ID ) {
    //                     /* user already logged in, just do a 403 */
    //                     return LOCATION_403;
    //                 } else {
    //                     /* user not logged in, redirect to the authentication page (401/412) */
    //                     return LOCATION_401;
    //                 }
    //             } else {
    //                 /* no post/page for this post, just do the theme's 404 */
    //                 return LOCATION_404;
    //             }
    //         }
    //     } else {
    //         /* no site for this URL, just do the theme's 404 */
    //         return LOCATION_404;
    //     }

    //     return $template;
    // } /* ccg_404_template */

    public static function prevent_editor_frontpage() {
        global $post;
        global $submenu;

        if ( !CCG_RESTRICT_EDITORS ) return;

        $user = wp_get_current_user();

        $grouproles = self::ccg_group_roles( $user->ID );

        //if ( in_array( 'editor', $user->roles) || in_array( 'editor' , $grouproles ) ) {
        // make sure the user or group role isn't administrator
        if ( ( !in_array( 'administrator', $grouproles ) && !in_array( 'administrator', $user->roles ) ) && ( in_array( 'acad_editor', $grouproles ) || in_array( 'acad_editor', $user->roles ) ) && !is_super_admin( $user->ID ) ) {

            // Editors shouldn't be able to edit the front page...
            if( $post->ID == get_option('page_on_front') && $_GET['action'] == 'edit' ) {
                wp_die("The front page can only be edited by the website administrator. Please contact your website administrator or web@colby.edu for more information.");
                exit();
            }

            remove_submenu_page( 'themes.php', 'themes.php' );	// Remove access to theme switching
            unset($submenu['themes.php'][6]);					// Remove access to customizer
            unset($submenu['themes.php'][15]);					// Remove access to header
            unset($submenu['themes.php'][17]);					// Remove access to theme options

        }

        /*
        if ( in_array( 'editor', $grouproles ) || in_array( 'editor', $user->roles ) ) {
            show_admin_bar( true );
        }
        */
    }	/* prevent_editor_frontpage */

    public static function ccg_switch_to_user( $newuser, $olduser ) {
        
        // Get the username for the current user
        $user = get_user_by( 'id', $newuser );
        
        // Call colbyTicket::setGroups($account,$id)
        colbyTicket::setGroups( $user->user_login, $user->ID );
    }
    
    function ccg_group_roles( $user_ID ) {
        global $wpdb;
        global $post;
        
        $user_group_roles = array();
        
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
    
        /* look for the post_id in group_roles if the post's status is "private" */
        $post_id_sql = '';
        if ( '' != $post->ID ) {
            $post_id_sql = 'OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post->ID;
        }
    
        /* get AD group roles for this blog */
        $roles = $wpdb->get_results(
            "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $wpdb->base_prefix . "ccg_group_members JOIN " . $dbprefix . "cc_group_roles ON " . $wpdb->base_prefix . "ccg_group_members.group_id=" . $dbprefix . "cc_group_roles.group_id WHERE " . $wpdb->base_prefix . "ccg_group_members.user_id=" . $user_ID . ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0 ' . $post_id_sql . ' )'
        );
    
        $read_ok = 0;
    
        if ( $roles ) {
            foreach ( $roles as $role ) {
                /* for each role, store it as a group role */
                $grouproles = unserialize( $role->roles );
                foreach ( $grouproles as $grouprole ) {
                    array_push( $user_group_roles, $grouprole );
                }
            }
        }
        
        if ( 1 == array_key_exists( 'ColbyTicket', $_COOKIE ) ) {
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
    
                if ( $in_clause ) {
                    $dbprefix = $wpdb->base_prefix;
                    if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
        
                    $post_clause = ' AND ( ' . $dbprefix . 'cc_group_roles.post_id=0';
                    if ( true == $post_limit ) {
                        $post_clause .= ' OR ' . $dbprefix . 'cc_group_roles.post_id=' . $post_id;
                    }
                    $post_clause .= ' )';
        
                    $roles = $wpdb->get_results(
                        "SELECT " . $dbprefix . "cc_group_roles.roles FROM " . $dbprefix . "cc_group_roles JOIN " . $wpdb->base_prefix . "ccg_groups ON " . $dbprefix . "cc_group_roles.group_id=" . $wpdb->base_prefix . "ccg_groups.id WHERE " . $wpdb->base_prefix . "ccg_groups.group_type='CC' AND " . $wpdb->base_prefix . "ccg_groups.group_name IN ( $in_clause )" . $post_clause
                    );

                    if ( $roles ) {
                        foreach ( $roles as $role ) {
                            /* error_log( "CCG: checking CC role " . $role ); */
                            $grouproles = unserialize( $role->roles );
                            foreach ( $grouproles as $grouprole ) {
                                array_push( $user_group_roles, $grouprole );
                            }
                        }
                    }
                }
            }
        }
        
        return $user_group_roles;
    }
}

