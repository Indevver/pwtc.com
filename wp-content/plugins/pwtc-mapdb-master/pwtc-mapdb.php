<?php
/*
Plugin Name: PWTC Map DB
Plugin URI: https://github.com/markhartel/pwtc-mapdb
Description: Provides searchable access to the Portland Bicycling Club route map database.
Version: 1.4
Author: Mark Hartel
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'PWTC_MAPDB__VERSION', '1.4' );
define( 'PWTC_MAPDB__MINIMUM_WP_VERSION', '3.2' );
define( 'PWTC_MAPDB__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PWTC_MAPDB__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'PwtcMapdb', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'PwtcMapdb', 'plugin_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'PwtcMapdb', 'plugin_uninstall' ) );

require_once( PWTC_MAPDB__PLUGIN_DIR . 'pwtc-mapdb-hooks.php' );
require_once( PWTC_MAPDB__PLUGIN_DIR . 'class.pwtcmapdb.php' );

add_action( 'init', array( 'PwtcMapdb', 'init' ) );
