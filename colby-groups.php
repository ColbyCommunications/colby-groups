<?php
/*
/**
 * Plugin Name: Colby Groups
 * Plugin URI:  [none]
 * Description: Colby Groups applies active directory groups to WP roles for WordPress blogs
 * Authors:      Keith McGlauflin, Brandon Waltz
 * Version:     1.0.0
 * Text Domain: ccg
 * Domain Path: /languages/
 * Min WP Version: 3.4

   Copyright (C) 2014-2015 Colby College - use with permission only!!!
*/

require "vendor/autoload.php";

use ColbyCollege\Plugins\ColbyGroups\ColbyGroups;
use ColbyCollege\Plugins\ColbyGroups\ColbyTicket;
use ColbyCollege\Plugins\ColbyGroups\Capabilities;
use ColbyCollege\Plugins\ColbyGroups\Dashboard;


$colby_groups = new ColbyGroups();
$colby_ticket = new ColbyTicket();
$colby_groups_capabilities = new Capabilities();
$colby_groups_dashboard = new Dashboard();

register_activation_hook( [ $colby_groups(), 'plugin_activate' ] );

