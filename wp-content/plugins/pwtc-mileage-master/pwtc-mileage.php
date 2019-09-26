<?php
/*
Plugin Name: PWTC Mileage
Plugin URI: https://github.com/markhartel/pwtc-mileage
Description: Stores and manages the mileage of Portland Bicycling Club members.
Version: 1.5
Author: Mark Hartel
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'PWTC_MILEAGE__VERSION', '1.5' );
define( 'PWTC_MILEAGE__DB_VERSION', '1.2' );
define( 'PWTC_MILEAGE__MINIMUM_WP_VERSION', '3.2' );
define( 'PWTC_MILEAGE__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PWTC_MILEAGE__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'PwtcMileage', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'PwtcMileage', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'PwtcMileage', 'plugin_uninstall' ) );

require_once( PWTC_MILEAGE__PLUGIN_DIR . 'class.pwtcmileage-db.php' );
require_once( PWTC_MILEAGE__PLUGIN_DIR . 'pwtc-mileage-hooks.php' );
require_once( PWTC_MILEAGE__PLUGIN_DIR . 'class.pwtcmileage.php' );

add_action( 'init', array( 'PwtcMileage', 'init' ) );

if ( is_admin() ) {
	require_once( PWTC_MILEAGE__PLUGIN_DIR . 'class.pwtcmileage-admin.php' );
	add_action( 'init', array( 'PwtcMileage_Admin', 'init' ) );
}