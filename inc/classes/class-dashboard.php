<?php
/**
 * Class Dashboard
 *
 * @since 1.0.0
 */

namespace ColbyCollege\Plugins\ColbyGroups\Dashboard;

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
        add_action( 'add_meta_boxes', [ $this, 'colbygroups_add_meta_box' ] );
        add_action( 'save_post', [ $this, 'colbygroups_save_meta_box_data' ] );
        add_action( 'post_submitbox_misc_actions', [ $this, 'colbygroups_publish_metabox' ] );
        add_action( 'admin_bar_menu', [ $this, 'colbygroups_toolbar_mods' ], 50 );
        add_filter( 'get_blogs_of_user', [ $this, 'colbygroups_add_group_blogs' ] );
        add_filter( 'wp_dropdown_users_args', [ $this, 'colbygroups_dropdown_users' ], 10, 2 );
        add_action( 'the_title', [ $this, 'colbygroups_remove_private' ] );
        add_filter( 'wp_dropdown_pages', [ $this, 'colbygroups_private_parent_metabox' ] );

    }

    public static function menus() {
        add_submenu_page( 'users.php', __('Colby Groups', 'CCG_Dashboard'), __('Colby Groups', 'CCG_Dashboard'), 'remove_users', 'colbyGroups/colbyGroups.php', array('CCG_Dashboard','menu_processing') );
    }

    public static function menu_processing( $post_id = 0 ) {
        global $wpdb;

        if ( $post_id == '' ) { $post_id=0; }
        
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }

        /* handle deleting groups from the current blog */
        if ( isset( $_POST['del_group'] ) ) {
            foreach ( $_POST['del_group'] as $delgroup ) {
                $wpdb->delete( $dbprefix . "cc_group_roles", array( 'ID' => $delgroup ) );
            }
        }

        /* handle adding a group to the current blog */
        if ( isset( $_POST['group'] ) ) {
            foreach ( $_POST['group'] as $newgroup ) {
                if ( CCG_DEBUG ) error_log( "Adding group ID " . $newgroup . " to " . get_current_blog_id() );
                $new_role=serialize(array($_POST['role']));
                $data=array( 'group_id' => $newgroup,
                                'roles' => $new_role,
                                'post_id' => 0 );
                $wpdb->insert( $dbprefix . "cc_group_roles", $data, array( '%d', '%s' ) );
            }
        }

        $s_groups = $wpdb->get_results(
            $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles, " . $dbprefix . "cc_group_roles.ID, ".$wpdb->base_prefix."ccg_groups.group_name FROM ".$wpdb->base_prefix."ccg_groups JOIN " . $dbprefix . "cc_group_roles ON ".$wpdb->base_prefix."ccg_groups.ID=" . $dbprefix . 'cc_group_roles.group_id WHERE ' . $dbprefix . 'cc_group_roles.post_id=' . $post_id . ' ORDER BY '.$wpdb->base_prefix."ccg_groups.group_name", ARRAY_N )
        );

        print <<<EOT
<style>
table.groups tbody tr:nth-child(odd) { background-color:#f0f0f0; }
table.groups tbody tr:nth-child(even){ background-color:#fff; }
div.groups:nth-child(odd) { background-color:#f0f0f0; }
div.groups:nth-child(even){ background-color:#fff; }
.groups-odd { background-color:#f0f0f0; }
.groups-even { background-color:#fff; }
</style>
EOT;

        echo "<div class='wrap'>";

        if ( $post_id == 0 ) {
            echo "<h2>Colby Groups</h2>";
            echo "<p>The active directory groups below have the listed roles for this site. Adding users to a group or creating new groups must be done in the CX database.</p>";
            echo "<form method='post'>";
            echo '<div class="alignleft actions bulkactions"><select name="action_ccg"><option value="-1" selected="selected">Bulk Actions</option><option value="delete-selected">Delete</option></select> <input type="submit" name="" id="doaction" class="button action" value="Apply" /></div><br/>';
        } else {
            echo "<p>The active directory groups below have the listed roles for this post (if no groups are selected the Colby Group settings for the site are used). Adding users to a group or creating new groups must be done in the CX database.</p>";
            echo '<div class="alignleft actions bulkactions"><select id="action1" name="action_ccg"><option value="-1" selected="selected">Bulk Actions</option><option value="delete-selected">Delete</option></select> <input type="button" name="" id="doaction" class="button action" value="Apply" onclick="deleteGroups(\'1\')" /></div><br/>';
        }

        echo '<table class="wp-list-table widefat fixed groups" cellspacing="0">';
        echo '<thead><tr><th scope="col" id="cb" class="manage-column column-cb check-column"  style=""><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox" /></th><th>Group</th><th>Role</th></tr></thead>';
        echo '<tbody id="the-group-list">';

        $my_groups=array();
        $my_group_names=array();
        if ( $s_groups ) {
            foreach ( $s_groups as $group ) {
                array_push($my_groups,array( 'group' => "'".$group->group_name."'", 'roles' => $group->roles ) );
                array_push($my_group_names, "'".$group->group_name."'" );
                $grouproles = unserialize( $group->roles );
                echo "<tr id='tr-".$group->group_name."'><td><input type='checkbox' id='del_group".$group->group_name."' name='del_group[]' class='deletecheckbox' value='".$group->ID."' />".'<td onclick="jQuery(\'#del_group'.$group->group_name.'\').prop(\'checked\',!jQuery(\'#del_group'.$group->group_name.'\').prop(\'checked\'));">'.$group->group_name."</td><td>";
                foreach ( $grouproles as $grouprole ) {
                    echo $grouprole.'&nbsp;&nbsp;';
                }
                echo "</td></tr>";
            }
        }

        echo '</tbody>';
        echo '<tfoot><tr><th scope="col" id="cb" class="manage-column column-cb check-column"  style=""><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox" /></th><th>Group</th><th>Role</th></tr></tfoot>';
        echo '</table>';

        if ( $post_id == 0 ) {
            echo '<div class="alignleft actions bulkactions"><select name="action_ccg"><option value="-1" selected="selected">Bulk Actions</option><option value="delete-selected">Delete</option></select> <input type="submit" name="" id="doaction" class="button action" value="Apply" /></div><br/><br/>';
            echo '</form>';
        } else {
            echo '<div class="alignleft actions bulkactions"><select id="action2" name="action_ccg"><option value="-1" selected="selected">Bulk Actions</option><option value="delete-selected">Delete</option></select> <input type="button" name="" id="doaction" class="button action" value="Apply" onclick="deleteGroups(\'2\')" /></div><br/><br/>';
        }
        
        $a_stmt = "SELECT ".$wpdb->base_prefix."ccg_groups.group_name, " . $wpdb->base_prefix."ccg_groups.ID, " . $wpdb->base_prefix."ccg_groups.group_description, " . $wpdb->base_prefix."ccg_groups.group_type  FROM ".$wpdb->base_prefix."ccg_groups WHERE ".$wpdb->base_prefix."ccg_groups.group_name like 'WP%' OR ".$wpdb->base_prefix."ccg_groups.group_type = 'CC' " ;

        /* add a WHERE clause to get groups not already selected (posts need all groups) */
        if ( count( $my_group_names ) && ( $post_id == 0 ) ) { $a_stmt .= "AND group_name NOT IN (".implode(',',$my_group_names).") "; }
        $a_stmt .= "ORDER BY ".$wpdb->base_prefix."ccg_groups.group_name";

        $a_groups = $wpdb->get_results( $wpdb->prepare( $a_stmt, ARRAY_N ) );

        echo '<h3>Available Colby Groups</h3>';

        if ( $post_id == 0 ) {
            echo "<p>Select groups to assign to this site and the role for those groups then click the 'Assign this Role to the selected Group(s)' button.</p>";
            echo '<form method="post">';
        } else {
            echo "<p>Select groups to assign to this post and the role for those groups then click the 'Assign this Role to the selected Group(s)' button.</p>";
        }

        echo '<div id="available-groups" style="width: 400px; height: 300px; overflow-x: auto; overflow-y: none; border: 1px solid #333;">';
        if ( $a_groups ) {
            foreach ( $a_groups as $group ) {
                $value = $group->ID;
                $display = 'block';
                $checked = '';
                foreach ( $my_groups as $mgroup ) {
                    if ( "'".$group->group_name."'" == $mgroup['group'] ) {
                        $userRoles = '';
                        foreach ( unserialize( $mgroup['roles'] ) as $userRole ) { $userRoles = $userRoles ? $userRoles+','+$userRole : $userRole; } 
                        $value .= ':' . $userRoles;
                        $display = 'none';
                        $checked = 'checked="yes"';
                    }
                }

                echo '<div id="group' . $group->group_name . 'div" class="groups" style="padding: 3px; display: '.$display.'"><input type="checkbox" id="group'.$group->group_name.'" class="groupcheckbox" name="group[]" value="' . $value . '" '.$checked.' /> <span onclick="jQuery(\'#group'.$group->group_name.'\').prop(\'checked\',!jQuery(\'#group'.$group->group_name.'\').prop(\'checked\'));"> '.$group->group_name;
                if ( $group->group_type == 'CC' ) { echo ' - ' . $group->group_description; }
                echo '</span></div>';
            }
        }
        echo '</div>';
        echo '<div>Role for selected groups: <select id="grouprole" name="role">';
        
        foreach (get_editable_roles() as $role_name => $role_info) {
            echo '<option value="'.$role_name.'">'.$role_info['name'].'</option>';
        }
        
        echo '</select></div>';

        if ( $post_id == 0 ) {
            echo '<input class="button action" type="submit" value="Assign this Role to the selected Group(s)" />';
            echo '</form>';
        } else {
            echo '<input class="button action" type="button" value="Assign this Role to the selected Group(s)" onclick="addGroups()" />';
        }
        echo '</div><p>&nbsp;</p>';
        $checked = get_post_meta( $post_id, 'ccg_publicly_viewable', true ) ? ' checked' : '';
        echo "<input type=\"checkbox\" id=\"ccg_publicly_viewable\" name=\"ccg_publicly_viewable\"$checked />
            <label for=\"ccg_publicly_viewable\">Make this post publicly viewable?</label>";
    }

    public static function colbygroups_add_meta_box() {
            $screens = array( 'post', 'page', 'catalog-entry' );
            foreach ( $screens as $screen ) {
                    add_meta_box(
                            'colbygroups_perms_id',
                            'Colby Groups',
                            array( 'CCG_Dashboard', 'colbygroups_perms_callback' ),
                            $screen
                    );
            }
    }
    
    public static function colbygroups_publish_metabox() {
        global $post;
        
        $type = get_post_type( $post );
        if ( 'page' != $type && 'post' != $type && 'catalog-entry' != $type ) return;
        
        list ( $parent_limit, $parent_id ) = self::parent_limit( $post->post_parent );
        
        $checked = ( get_post_meta( $post->ID, 'ccg_limit_groups_' . $post->ID, true ) ) ? 'checked="yes"' : '';
        
        echo '<div id="ccg_limit_groups_div" class="misc-pub-section">';
        echo '<input name="ccg_limit_groups" id="ccg_limit_groups" type="checkbox" '.$checked.' value="1"> <label for="ccg_limit_groups">Restrict to Colby Groups</label>';
        echo '</div>';
        
        if ( true == $parent_limit && CCG_BLOG_PUBLIC_COLBY_ONLY != get_option( 'blog_public' ) ) {
            $show_public = 'none';
            if ( '' == $checked && true == $parent_limit ) {
                $show_public = 'block';
            }
        
            $limited = get_post_meta( $post->ID, 'ccg_limit_groups_' . $post->ID, true );
            if ( CCG_DEBUG ) error_log( "CCG: public_checked is " . $public_checked );
            if ( '0' == $limited ) { $public_checked = 'checked="yes"'; }
                else { $public_checked = ''; }
        
            echo '<div id="ccg_public_div" class="misc-pub-section" style="display: ' . $show_public . '">';
            echo '<input name="ccg_public" id="ccg_public" type="checkbox" ' . $public_checked . ' value="1"> <label for="ccg_public">Visible to the Public</label>';
            echo '</div>';
        }
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

    public static function colbygroups_perms_callback( $post ) {
        if ( get_post_meta( $post->ID, 'ccg_limit_groups_' . $post->ID, true ) ) {
            $ui_display = 'block';
            $ui_note_display = 'none';
        } else {
            $ui_display = 'none';
            $ui_note_display = 'block';
        };
        
        list ( $parent_limit, $parent_id ) = self::parent_limit( $post->post_parent );
        
        if ( CCG_BLOG_PUBLIC_COLBY_ONLY == get_option( 'blog_public') ) {
            /* entire blog restricted to Colby Groups */
            if ( true == $parent_limit ) {
                echo "<div id='colbygroups_note' style='display: block;'>While " . $post->post_type . "s on this site are restricted to specific Colby Groups this " . $post->post_type . " has a parent page with additional restrictions which also apply to this page. Use the 'Restrict to Colby Groups' checkbox to specify different restrictions.</div>";
            } else {
                echo "<div id='colbygroups_note' style='display: " . $ui_note_display . ";'>This " . $post->post_type . " is restricted to the Colby Groups specified for this site. If you would like to apply additional restrictions check the 'Restrict to Colby Groups' checkbox (in the upper right, under 'Publish') then select the groups from the menu that will appear below.</div>";
            }
        } else {
            if ( true == $parent_limit ) {
                echo "<div id='colbygroups_note' style='display: block;'>This " . $post->post_type . " has a parent that has restricted Colby Groups access (see 'Page Attributes' to the right for the parent page) which also makes this " . $post->post_type . " restricted, regardless of the 'Restrict to Colby Groups' selection. If you want this " . $post->post_type . " to be visible to the public check the 'Visible to the Public' checkbox under 'Publish'.</div>";
            } else {
                echo "<div id='colbygroups_note' style='display: " . $ui_note_display . ";'>To limit access to this " . $post->post_type . " to Colby groups check the 'Restrict to Colby Groups' checkbox (in the upper right, under 'Publish') then select the groups from the menu that will appear below.</div>";
            }
        }
        
        echo "<div id='colbygroups_select' style='display: " . $ui_display . ";'>";

        self::menu_processing( $post->ID );

        echo "</div>";

        /* add some javascript for the visibility radio buttons */
        print <<<EOT
<script type='text/javascript'>
jQuery('#ccg_limit_groups').click( function() {
if ( jQuery('#ccg_limit_groups').is(':checked') ) { 
    jQuery('#colbygroups_note').hide();
    jQuery('#colbygroups_select').show(500);
    jQuery('#ccg_public_div').hide();
    document.getElementById("colbygroups_perms_id").scrollIntoView();
} else {
    jQuery('#colbygroups_select').hide();
    jQuery('#colbygroups_note').show(500);
    jQuery('#ccg_public_div').show(500);
}
} );

function deleteGroups(which) {
if ( jQuery('#action'+which+' option:selected').attr('value') == 'delete-selected' ) {
    jQuery('.deletecheckbox').each( function( index ) {
        if ( jQuery(this).is(':checked') ) {
            group = jQuery(this).attr('id').substring(9);
            jQuery('#group'+group).prop('checked',false);
            jQuery('#group'+group+'div').show();
            jQuery('#group'+group).attr('value',jQuery('#group'+group).attr('value').split(':')[0]);
            jQuery(this).closest('tr').remove();
        }
    } );
    redoAltRows();
}
}

function addGroups() {
jQuery('.groupcheckbox').each( function( index ) {
    if ( jQuery(this).is(':checked') && jQuery(this).closest('div').css('display') == 'block' ) {
        jQuery(this).attr('value',jQuery(this).attr('value')+':'+jQuery('#grouprole option:selected').attr('value'));
        jQuery('#'+jQuery(this).attr('id')+'div').hide();
        var appended = 0;
        var newgroup = jQuery(this).attr('id').substring(5);
        jQuery('#the-group-list tr').each( function(index) {
            if ( !appended && jQuery(this).attr('id') > 'tr-'+newgroup ) {
                jQuery(this).before('<tr id=\'tr-'+newgroup+'\'><td><input type=\'checkbox\' id=\'del_group' + newgroup + '\' name=\'del_group[]\' class=\'deletecheckbox\' value=\'' + newgroup + ':' + jQuery('#grouprole option:selected').attr('value') + '\' /></td><td> ' + newgroup + '</td><td>' + jQuery('#grouprole option:selected').attr('value') + '</td></tr>');
                appended = 1;
            }
        } );

        if ( !appended ) {
            /* add to the start of the list */
            jQuery('#the-group-list').append('<tr id=\'tr-'+newgroup+'\'><td><input type=\'checkbox\' id=\'del_group' + newgroup + '\' name=\'del_group[]\' class=\'deletecheckbox\' value=\'' + newgroup + ':' + jQuery('#grouprole option:selected').attr('value') + '\' /></td><td> ' + newgroup + '</td><td>' + jQuery('#grouprole option:selected').attr('value') + '</td></tr>');
        }
    }
} );
redoAltRows();
}

function redoAltRows() {
var next=0;
jQuery('#available-groups div').each( function(index) {
    if ( next % 2 == 1 && jQuery(this).is(':visible') ) { jQuery(this).css('background-color','#f0f0f0'); next++; }
        else if ( jQuery(this).is(':visible') ) { jQuery(this).css('background-color','#fff'); next++; }
} );
}
</script>
EOT;
    }

    public static function colbygroups_save_meta_box_data( $post_id ) {
        global $wpdb;

        
        update_post_meta(
            $post_id,
            'ccg_publicly_viewable',
            $_POST['ccg_publicly_viewable'] && 'on' === $_POST['ccg_publicly_viewable']
        );
        //if ( CCG_DEBUG ) error_log( "CCG: setting the groups for page ID ".$post_id );
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return;

        /* if the parent is restricted... */
        list ( $parent_limit, $parent_id ) = self::parent_limit( $post->post_parent );
        if ( 'true' == $parent_limit ) {
            if ( CCG_DEBUG ) error_log( "CCG: this post has a limited parent" );
            /* ...and the public override input is not specified delete the public override setting */
            if ( empty( $_POST[ 'ccg_public' ] ) ) {
                if ( CCG_DEBUG ) error_log( "CCG: ccg_public is not set" );
                delete_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, 0 );
            } else if ( '1' == $_POST[ 'ccg_public' ] && '1' != $_POST['ccg_limit_groups'] ) {
                if ( CCG_DEBUG ) error_log( "CCG: ccg_public is set" );
                add_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, 0, true ) || update_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, 0 );
            }
        
            /* ...and the public override input is specified set the public override setting */
        }
        
        if ( empty( $_POST[ 'ccg_limit_groups' ] ) || '1' != $_POST['ccg_limit_groups'] ) {
            /* not limiting this page by Colby groups */
            if ( CCG_DEBUG ) error_log( "CCG: restrict to groups checkbox not checked - " . $_POST['ccg_limit_groups'] );
            delete_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, true );
            return;
        }
        
        /* save the 'ccg_limit_groups_' . $post_id post_meta */
        add_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, true, true ) || update_post_meta( $post_id, 'ccg_limit_groups_' . $post_id, true );
        
        $dbprefix = $wpdb->base_prefix;
        if ( get_current_blog_id() != 1 ) { $dbprefix .= get_current_blog_id() . '_'; }
        
        /* get all of the currently assigned groups */
        $savedGroups = $wpdb->get_results(
            $wpdb->prepare( "SELECT " . $dbprefix . "cc_group_roles.roles, " . $dbprefix . "cc_group_roles.ID, ".$wpdb->base_prefix."ccg_groups.group_name, ".$wpdb->base_prefix."ccg_groups.ID as group_id FROM ".$wpdb->base_prefix."ccg_groups JOIN " . $dbprefix . "cc_group_roles ON ".$wpdb->base_prefix."ccg_groups.ID=" . $dbprefix . 'cc_group_roles.group_id WHERE ' . $dbprefix . 'cc_group_roles.post_id=' . $post_id . ' ORDER BY '.$wpdb->base_prefix."ccg_groups.group_name", ARRAY_N )
        );
        
        //if ( CCG_DEBUG ) error_log( "CCG: comparing new groups to saved groups" );
        if ( $savedGroups ) {
            foreach ( $savedGroups as $oldGroup ) {
                /* for each currently assigned group, delete those that are not in the new groups list */
                $oldGroupFound = 0;
                
                foreach ( $_POST['group'] as $newGroup ) {
                    $parts = explode( ':', $newGroup );
                    $newGroupID = $parts[0];
                    $newGroupRole = $parts[1];
                    
                    if ( $oldGroup->group_id == $newGroupID ) {
                        $oldGroupFound = 1;
                    }
                }
                
                if ( $oldGroupFound == 0 ) {
                    $wpdb->delete( $dbprefix . "cc_group_roles", array( 'ID' => $oldGroup->ID ) );
                }
            }
        }
        
        if ( array_key_exists( 'group', $_POST ) ) {
            foreach ( $_POST['group'] as $group ) {
                //if ( CCG_DEBUG ) error_log( "CCG: the group ID to assign is ".$group );
                $oldGroupFound = 0;
                $oldGroupRole = '';
                $oldGroupID = 0;
            
                $parts = explode( ':', $group );
                $newGroupID = $parts[0];
                $newGroupRole = $parts[1];

                /* for each new group */
                foreach ( $savedGroups as $oldGroup ) {
                    if ( $newGroupID == $oldGroup->group_id ) {
                        $oldGroupFound = 1;
                        $oldGroupRole = $oldGroup->roles;
                        $oldGroupID = $oldGroup->ID;
                    }
                }
            
                if ( $oldGroupFound == 0 ) {
                    /* if no old group then add the new group */
                    $newGroupRoleSerialized = serialize( array($newGroupRole) );
                
                    //if ( CCG_DEBUG ) error_log( "CCG: new group $newGroupID with role $newGroupRoleSerialized" );
                
                    $data=array( 
                        'group_id' => $newGroupID,
                        'roles' => $newGroupRoleSerialized,
                        'post_id' => $post_id 
                    );
                
                    $wpdb->insert( $dbprefix . "cc_group_roles", $data, array( '%d', '%s' ) );
                } else {
                    $newGroupRoleSerialized = serialize( array( $newGroupRole ) );
                
                    /* update the role if different from the old role */
                    if ( $newGroupRoleSerialized != $oldGroupRole ) {
                        //if ( CCG_DEBUG ) error_log( "CCG: the role for this group needs to be updated to $newGroupRoleSerialized" );
                        $wpdb->update( $dbprefix . "cc_group_roles", 
                                        array( 'roles' => $newGroupRoleSerialized ),
                                        array( 'ID' => $oldGroupID ),
                                        array( '%s' ),
                                        array( '%d' ) 
                        );
                    }
                }
            }
        }
        
        //if ( CCG_DEBUG ) error_log( "CCG: done setting page group permissions" );
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
