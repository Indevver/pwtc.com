<?php
/*
Plugin Name: PWTC Members
Plugin URI: https://github.com/markhartel/pwtc-members
Description: Provides additional shortcodes, admin pages and customizations to enhance the WooCommerce membership plugins for the Portland Bicycling Club website.
Version: 1.3
Author: Mark Hartel
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'PWTC_MEMBERS__VERSION', '1.3' );
define( 'PWTC_MEMBERS__MINIMUM_WP_VERSION', '3.2' );
define( 'PWTC_MEMBERS__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PWTC_MEMBERS__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'PwtcMembers', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'PwtcMembers', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'PwtcMembers', 'plugin_uninstall' ) );

require_once( PWTC_MEMBERS__PLUGIN_DIR . 'acf-hooks.php' );
require_once( PWTC_MEMBERS__PLUGIN_DIR . 'pwtc-members-hooks.php' );
require_once( PWTC_MEMBERS__PLUGIN_DIR . 'class.pwtcmembers.php' );

add_action( 'init', array( 'PwtcMembers', 'init' ) );

if ( is_admin() ) {
	require_once( PWTC_MEMBERS__PLUGIN_DIR . 'class.pwtcmembers-admin.php' );
	add_action( 'init', array( 'PwtcMembers_Admin', 'init' ) );
}
