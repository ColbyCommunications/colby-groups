<?php
/**
 * Class ColbyTicket
 *
 * @since 1.0.0
 */

namespace ColbyCollege\Plugins\ColbyGroups;

// need functions for inserting/updating user in wordpress
// require_once('/Users/bkwaltz/webroot/testColby2/wp-includes/registration.php');

/**
 * ColbyTicket class.
 *
 * @since 1.0.0
 */
class ColbyTicket {

    // This is the secret key for authenticating. If this is changed on the
    // www.colby.edu web servers it needs to be changed here
    private $secret = 'DSFGJDfkldsalfkalkDSAFGjioerwroeiR@%$54$56DFGFf';

    /**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

    // if LANDO or PROD or Platform dev environment with prod environment
    if ("ON" === getenv('LANDO') || "https://". $_SERVER['HTTP_HOST'] === "https://" . getenv('PRIMARY_DOMAIN') || (false !== getenv('PLATFORM_RELATIONSHIPS' && true === getenv('HAS_PROD')))) {
      add_action('set_current_user', [ $this, 'ticketCheck' ]);
      add_action('wp_authenticate', [ $this, 'authenticate' ]);
      add_action('wp_logout', [ $this, 'logout' ]);
      add_action('login_form', [ $this, 'login_form' ]);
      add_action('lost_password', [ $this, 'disable_function' ]);
      add_action('retrieve_password', [ $this, 'disable_function' ]);
      add_action('password_reset', [ $this, 'disable_function' ]);

      // register route for cookie value retrieval
      add_action( 'rest_api_init', [ $this,  'register_routes' ] );
    }
  }

  function getCookieValues() {
    if (array_key_exists('ColbyTicket', $_COOKIE)) {
      return explode('&', $_COOKIE['ColbyTicket']);
    }

    return false;

  }

    // if the user is not logged in but has a ColbyTicket cookie, log them in
    function ticketCheck() {
        // global $colby_secret;
        global $user_ID;

        // get the current user
        $user = wp_get_current_user();
        $user_ID = $user->ID;

        // xmlrpc.php does it's own authentication mechanism
        if ( ($user_ID == '') && ( preg_match( '/xmlrpc\.php/', $_SERVER['REQUEST_URI'] ) ) ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.colby.edu/ColbyMaster/ad/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW']);
        $output = curl_exec($ch);

        if ( $output == "OK\n" ) {
            // $user = get_userdatabylogin($_SERVER['PHP_AUTH_USER']);
        $user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );
            set_current_user( $user->ID );
            return;
        }
        }

        // allow wp-cron.php requests from this server
        if ( ( preg_match( '/wp-cron\.php\?doing_wp_cron=\d+$/', $_SERVER['REQUEST_URI'] ) ) && ( $_SERVER['REMOTE_ADDR'] == '137.146.30.193' ) ) {
        // $user = get_userdatabylogin('wp-admin');
        $user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );
        set_current_user( $user->ID );
        return;
        }

        if ( ($user_ID == '') && (array_key_exists('ColbyTicket',$_COOKIE)) ) {
        // validate the authentication cookie
        $cookie_items = explode('&', $_COOKIE['ColbyTicket']);
        $cookie = array();
        for ( $i = 0; $i < count($cookie_items);$i = $i + 2 ) {
            $cookie[$cookie_items[$i]] = $cookie_items[$i + 1];
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if ( $cookie['hash'] && $cookie['user'] && $cookie['time'] && $cookie['expires'] ) {
            $hash_vals = array($this->secret, $cookie['ip'], $cookie['time'], $cookie['expires'], $cookie['user'], $cookie['profile'], $cookie['type'], $user_agent);
            $newhash = md5($colby_secret . md5(join(':',$hash_vals)));

            if ( $newhash == $cookie['hash'] && $cookie['type'] == 'Colby' ) {
            // valid cookie, get wordpress info for this user
            //if ( $user=get_userdatabylogin($cookie['user']) ) {
            if ( $user = get_user_by( 'login', $cookie['user'] ) ) {
                // update this user's WP groups based on AD
                colbyTicket::setGroups($cookie['user'],$user->ID);
                // error_log( "ColbyTicket: setting groups in ticketCheck" );

                // log user in and reload this page
                wp_set_auth_cookie( $user->ID );
        
                if ( preg_match( '/\/wp\-json\//', $_SERVER['REQUEST_URI'] ) == 0 ) {
                echo '<meta http-equiv="refresh" content="0">';
                die();
                }
            }

            // strange, new Colby user...added them anyway
            else {
                $userarray['user_login'] = $cookie['user'];
                $userarray['user_pass'] = 'XXXcolbyXXX';
                $userarray['first_name'] = '';
                $userarray['last_name'] = '';
                $userarray['user_url'] = '';
                $userarray['user_email'] = $cookie['user'].'\@colby.edu';
                $userarray['description'] = '';
                $userarray['aim'] = '';
                $userarray['yim'] = '';
                $userarray['jabber'] = '';
                $userarray['display_name'] = '';
                $userarray['organization'] = 'Colby';

                // create the user, log them in and reload this page
                wp_insert_user( $userarray );
                // $user=get_userdatabylogin($cookie['user']) ;
                $user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );
                
                // update this user's WP groups based on AD
                colbyTicket::setGroups($cookie['user'],$user->ID);

                // log user in and reload this page
                wp_set_auth_cookie( $user->ID );

                echo '<meta http-equiv="refresh" content="0">';
                die();
            }
            }
        }
        }

        return;
    }

  // authenticate the user based on their ColbyTicket cookie
  function authenticate() {
    global $colby_secret;

    if ( $_GET['auth'] == 'internal' ) { return; }

    // xmlrpc.php does it's own authentication mechanism
    if ( preg_match( '/xmlrpc\.php/', $_SERVER['REQUEST_URI'] ) ) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://www.colby.edu/ColbyMaster/ad/");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_USERPWD, $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW']);
      $output = curl_exec($ch);

      if ( $output == "OK\n" ) {
        // $user = get_userdatabylogin($_SERVER['PHP_AUTH_USER']);
        $user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );
        set_current_user( $user->ID );
        return;
      }
    }


    if ( array_key_exists('ColbyTicket', $_COOKIE) ) {

      // validate the authentication cookie
      $cookie_items = explode('&', $_COOKIE['ColbyTicket']);
      $cookie = array();
      for ( $i = 0; $i < count($cookie_items);$i = $i + 2 ) {
        $cookie[$cookie_items[$i]] = $cookie_items[$i + 1];
      }

      $user_agent = $_SERVER['HTTP_USER_AGENT'];
      if ( $cookie['hash'] && $cookie['user'] && $cookie['time'] && $cookie['expires'] ) {

        $hash_vals = array($this->secret, $cookie['ip'], $cookie['time'], $cookie['expires'], $cookie['user'], $cookie['profile'], $cookie['type'], $user_agent);
        $newhash = md5($this->secret . md5(join(':',$hash_vals)));
        // var_dump($hash_vals);
        // var_dump($newhash);
        // die($cookie['hash']);
        // $newhash == $cookie['hash']
        if ($cookie['type'] === 'Colby' ) {

          // valid cookie, get wordpress info for this user
          if ( $user = get_user_by( 'login', $cookie['user'] ) ) {
            wp_set_auth_cookie( $user->ID );

            // update the user's AD groups
            colbyTicket::setGroups($cookie['user'],$user->ID);

            if (isset( $_GET['redirect_to'] )) {
              wp_redirect( $_GET['redirect_to'] );
              // This line didn't work right for pages that were the default
              // page for the blog (replaced with the line above
              //wp_redirect( preg_match( '/^http/', $_GET['redirect_to'] ) ? $_GET['redirect_to'] : site_url( $_GET['redirect_to'] ));
              die();
            }

            wp_redirect( site_url('/') );
            die();
          } else {
            
            $userarray['user_login'] = $cookie['user'];
            $userarray['user_pass'] = 'XXXcolbyXXX';
            $userarray['first_name'] = '';
            $userarray['last_name'] = '';
            $userarray['user_url'] = '';
            $userarray['user_email'] = $cookie['user'].'\@colby.edu';
            $userarray['description'] = '';
            $userarray['aim'] = '';
            $userarray['yim'] = '';
            $userarray['jabber'] = '';
            $userarray['display_name'] = '';
            $userarray['organization'] = 'Colby';
            wp_insert_user( $userarray );

            $user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );
            wp_set_auth_cookie( $user->ID );

            if (isset( $_GET['redirect_to'] )) {
              wp_redirect( preg_match( '/^http/', $_GET['redirect_to'] ) ? $_GET['redirect_to'] : site_url( $_GET['redirect_to'] ));
              die();
            }

            wp_redirect( site_url('/') );
            die();
          }
        }
      }
    }

    // if !LANDO && !PROD && platform dev environment with prod
    if ("ON" !== getenv('LANDO') && "https://". $_SERVER['HTTP_HOST'] !== "https://" . getenv('PRIMARY_DOMAIN')) {
      // no cookie, need to get it from prod
      $response = $this->colby_groups_request_cookie_values();

      if ($response) {
        setcookie('ColbyTicket', $response);

        // redirect back to where they were trying to go
        wp_redirect("https://" . $_SERVER['HTTP_HOST'] . $redirect);
        die();
      } else {
        // need to show prod login form here...or at least a message
        die("Try logging in on production (https://{getenv('PRIMARY_DOMAIN'}) and trying the page your requested again again");
      }
    } else {
      // user doesn't have a ColbyTicket or the ticket is invalid
      $redirect = $_SERVER['REQUEST_URI'];

      wp_redirect("https://www.colby.edu/ColbyMaster/login/?https://" . $_SERVER['HTTP_HOST'] . $redirect);
      die();
    }
  }
  
  function setGroups($account, $id) {
    global $wpdb;
    
    // get the AD groups for this account
    $adgroups = array();
    
    try {
      // attempt to connect and bind to dc3.colby.edu
      $ds = ldap_connect("ldaps.colby.edu");
      if ( !( $dsb = ldap_bind($ds, "COLBY\www", "ca4443.tkadk1") ) ) {
        throw new Exception("Connect/bind failed."); 
      }
    } catch( Exception $error ) {
      // failed, attempt to connect and bind to dc2.colby.edu
      try {
        $ds = ldap_connect("ldap.colby.edu");
        if ( !( $dsb = ldap_bind($ds, "COLBY\www", "ca4443.tkadk1") ) ) {
          throw new Exception("Connect/bind failed.");
        }
      } catch( Exception $error ) {
        // couldn't bind to dc3 or dc2 - bummer
      }
    }
    
    if ( $ds && $dsb ) {
      try {
        $dn = "OU=People, DC=colby, DC=edu";
        $filter = "(&(sAMAccountName=$account))";
        $fields = array("memberOf");
    
        $sr = ldap_search($ds, $dn, $filter, $fields);
        $data = ldap_get_entries($ds, $sr);
        $membership=$data[0]['memberof'];
    
        for ($i = 0;$i < count($membership) - 1;$i++) {
          list ($cn,$rest) = explode(',', $membership[$i], 2);
          $group = substr($cn,3);
          if ( substr($group, strlen($group) - 3) == 'GRP' ) {
            array_push($adgroups,$group);
            // print "Group: $group<br />";
            // error_log( "colbyTicket: ".$account." is a member of ".$group );
          }
        }
      } catch( Exception $error ) {
        // no groups because connecting/binding/querying failed, oh well...
      }
    }
    
    ldap_close($ds);
    
    // get all of the WP groups
    $wpgroups = $wpdb->get_results('SELECT ID,group_name FROM '.$wpdb->base_prefix.'ccg_groups');
    
    // get this user's groups
    $mywpgroups = $wpdb->get_results('SELECT '.$wpdb->base_prefix.'ccg_groups.ID,'.$wpdb->base_prefix.'ccg_groups.group_name FROM '.$wpdb->base_prefix.'ccg_groups, '.$wpdb->base_prefix.'ccg_group_members WHERE '.$wpdb->base_prefix.'ccg_group_members.user_id='.$id.' AND '.$wpdb->base_prefix.'ccg_group_members.group_id='.$wpdb->base_prefix.'ccg_groups.id');
    
    // delete the account from any WP groups which end with "GRP" that the user
    // is not also a member of in Active Directory
    foreach ( $wpgroups as $wpgroup ) {
      if ( substr($wpgroup->group_name,strlen($wpgroup->group_name)-3) == 'GRP' ) {
        $foundinad = 0;
        foreach( $adgroups as $adgroup ) {
          if ( $adgroup == $wpgroup->group_name ) {
            $foundinad = 1;
          }
        }
        
        if ( !$foundinad ) {
          $wpdb->query('DELETE FROM '.$wpdb->base_prefix.'ccg_group_members WHERE group_id='.$wpgroup->ID.' AND user_id='.$id);
        }
      }
    }
    
    // add the account to WP groups which match the membership in AD (create the groups
    // if necessary)
    foreach ( $adgroups as $adgroup ) {
      if ( substr($adgroup,strlen($adgroup)-3) == 'GRP' ) {
        $wpGroupID = 0;
        foreach ( $wpgroups as $wpgroup ) {
          if ( $adgroup == $wpgroup->group_name ) {
            $wpGroupID = $wpgroup->ID;
          }
        }
                  
        if ( !$wpGroupID ) {
          $wpdb->query('INSERT INTO '.$wpdb->base_prefix.'ccg_groups (group_name,group_description) values ("'.$adgroup.'","Active Directory Group - changes to this group need to be made in CARS!")');
          $wpGroupID = $wpdb->insert_id;
        }
        
        $useringroup = 0;
        foreach ( $mywpgroups as $mywpgroup ) {
          if ( $adgroup == $mywpgroup->group_name ) {
            $useringroup = 1;
          }
        }
        
        if ( !$useringroup ) {
          $wpdb->query('INSERT INTO '.$wpdb->base_prefix.'ccg_group_members (group_id,user_id) values ('.$wpGroupID.','.$id.')');
        }
      }
    }
  }

  // log the user out
  function logout() {
    // redirect to the logout page
    wp_redirect("https://www.colby.edu/ColbyMaster/logout/");
    die();
  }

  // login form (for internal authentication vs external)
  function login_form() {
    if ( $_GET['auth'] == 'internal' ) {
      print "<script type='text/javascript'>";
      print "document.loginform.action=document.loginform.action+'?auth=internal';";
      print "</script>";
      print "<p>This form is for non-Colby account holders. If you have a Colby account and password, you need to <a href='https://www.colby.edu/ColbyMaster/login/?http://www.colby.edu/wp-login/'>login here</a>.<br/></p><p>&nbsp;</p>";
    }
  }

  // disable reset, list and retrieve password features
  function disable_function() {
    die( __( 'Sorry, this feature is disabled.', 'colbyTicket' ));
  }

  public function colby_groups_request_cookie_values() {
    $curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => sprintf('https://%s/wp-json/colby-groups/v1/get-cookie', getenv('PRIMARY_DOMAIN'))
		]);
		$result = curl_exec($curl);
		curl_close($curl);
		return json_decode($result, true);
  }

  public function colby_groups_get_cookie_route(WP_REST_Request $request) {
    if ($cookie_values = $this->getCookieValues){
      return json_decode($cookie_values, true);
    } else {
      return json_decode(['status' => false], true);
    }
	}

  public function register_routes() {
		register_rest_route('colby-groups/v1', '/get-cookie', array(
				'methods'  => 'GET',
				'callback' => [ $this, 'colby_groups_get_cookie_route' ],
		));
	}
}

