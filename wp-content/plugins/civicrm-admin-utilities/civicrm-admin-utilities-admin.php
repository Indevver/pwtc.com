<?php

/**
 * CiviCRM Admin Utilities Admin Class.
 *
 * A class that encapsulates admin functionality.
 *
 * @since 0.1
 */
class CiviCRM_Admin_Utilities_Admin {

	/**
	 * Settings page reference.
	 *
	 * @since 0.1
	 * @access public
	 * @var array $settings_page The reference to the settings page.
	 */
	public $settings_page;

	/**
	 * Settings data.
	 *
	 * @since 0.1
	 * @access public
	 * @var array $settings The plugin settings data.
	 */
	public $settings = array();



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// init
		$this->initialise();

	}



	/**
	 * Perform activation tasks.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// kick out if we are re-activating
		if ( civicrm_admin_utilities_site_option_get( 'civicrm_admin_utilities_installed', 'false' ) == 'true' ) return;

		// store default settings
		civicrm_admin_utilities_site_option_set( 'civicrm_admin_utilities_settings', $this->settings_get_defaults() );

		// store installed flag
		civicrm_admin_utilities_site_option_set( 'civicrm_admin_utilities_installed', 'true' );

	}



	/**
	 * Perform deactivation tasks.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// we delete our options in uninstall.php

	}



	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// load settings array
		$this->settings = civicrm_admin_utilities_site_option_get( 'civicrm_admin_utilities_settings', $this->settings );

		// is this the back end?
		if ( is_admin() ) {

			// multisite?
			if ( is_multisite() ) {

				// add admin page to Network menu
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 30 );

			} else {

				// add admin page to menu
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			}

		}

	}



	/**
	 * Get default settings values for this plugin.
	 *
	 * @since 0.1
	 *
	 * @return array $settings The default values for this plugin.
	 */
	public function settings_get_defaults() {

		// init return
		$settings = array();

		// do not restrict to main site only
		$settings['main_site_only'] = '0';

		// prettify menu
		$settings['prettify_menu'] = '1';

		// fix WordPress Access Control table
		$settings['prettify_access'] = '1';

		// init post types with defaults
		$settings['post_types'] = array( 'post', 'page' );

		// add menu to admin bar
		$settings['admin_bar'] = '1';

		// --<
		return $settings;

	}



	/**
	 * Add an admin page for this plugin.
	 *
	 * @since 0.1
	 */
	public function admin_menu() {

		// we must be network admin in multisite
		if ( is_multisite() AND ! is_super_admin() ) return;

		// check user permissions
		if ( ! current_user_can( 'manage_options' ) ) return;

		// try and update options
		$saved = $this->update_options();

		// multisite?
		if ( is_multisite() ) {

			// add the admin page to the Network Settings menu
			$this->settings_page = add_submenu_page(
				'settings.php',
				__( 'CiviCRM Admin Utilities', 'civicrm-admin-utilities' ),
				__( 'CiviCRM Admin Utilities', 'civicrm-admin-utilities' ),
				'manage_options',
				'civicrm_admin_utilities',
				array( $this, 'admin_form' )
			);

		} else {

			// add the admin page to the Settings menu
			$this->settings_page = add_options_page(
				__( 'CiviCRM Admin Utilities', 'civicrm-admin-utilities' ),
				__( 'CiviCRM Admin Utilities', 'civicrm-admin-utilities' ),
				'manage_options',
				'civicrm_admin_utilities',
				array( $this, 'admin_form' )
			);

		}

		// add styles only on our admin page, see:
		// http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Load_scripts_only_on_plugin_pages
		//add_action( 'admin_print_styles-' . $this->settings_page, array( $this, 'add_admin_styles' ) );

	}



	/**
	 * Show our admin page.
	 *
	 * @since 0.1
	 */
	public function admin_form() {

		// we must be network admin in multisite
		if ( is_multisite() AND ! is_super_admin() ) {

			// disallow
			wp_die( __( 'You do not have permission to access this page.', 'civicrm-admin-utilities' ) );

		}

		// get sanitised admin page url
		$url = $this->admin_form_url_get();

		// open admin page
		echo '

		<div class="wrap" id="civicrm_admin_utilities_wrapper">

		<div class="icon32" id="icon-options-general"><br/></div>

		<h2>' . __( 'CiviCRM Admin Utilities', 'civicrm-admin-utilities' ) . '</h2>

		<hr>

		<form method="post" action="' . $url . '">

		' . wp_nonce_field( 'civicrm_admin_utilities_admin_action', 'civicrm_admin_utilities_nonce', true, false ) . '

		';

		// open div
		echo '<div id="civicrm_admin_utilities_admin_options">';

		// multisite
		$this->admin_form_multisite_options();

		// styling
		$this->admin_form_styling_options();

		// access form
		$this->admin_form_access_options();

		// admin bar
		$this->admin_form_admin_bar_options();

		// post types
		$this->admin_form_post_type_options();

		// useful utilities
		$this->admin_form_utilities();

		// close div
		echo '</div>';

		// show submit button
		echo '

		<p class="submit">
			<input type="submit" id="civicrm_admin_utilities_submit" name="civicrm_admin_utilities_submit" value="' . __( 'Submit', 'civicrm-admin-utilities' ) . '" class="button-primary" />
		</p>

		';

		// close form
		echo '

		</form>

		</div>
		' . "\n\n\n\n";

	}



	/**
	 * Get multisite options.
	 *
	 * @since 0.1
	 */
	public function admin_form_multisite_options() {

		// bail if not network activated
		if ( ! is_multisite() ) return;

		// init checkbox
		$main_site_only = '';
		if ( $this->setting_get( 'main_site_only', '0' ) == '1' ) $main_site_only = ' checked="checked"';

		// show sync
		echo '
		<h3>' . __( 'Multisite Options', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'In multisite, CiviCRM currently loads on every sub-site. This may not be what you want - especially when multisite uses subdirectories - because CiviCRM makes assumptions about the path to WordPress admin and as a result the CiviCRM menu always bounces users to the main site. Furthermore, public-facing pages will not distinguish between sub-sites and the main site and will always appear on the main site. So check this option to restrict the appearance of the CiviCRM menu item and CiviCRM shortcode button to the main site only.', 'civicrm-admin-utilities' ) . '</p>

		<table class="form-table">

			<tr>
				<th scope="row">' . __( 'Restrict CiviCRM', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_main_site" id="civicrm_admin_utilities_main_site" value="1"' . $main_site_only . ' />
					<label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_main_site">' . __( 'Restrict CiviCRM to main site only.', 'civicrm-admin-utilities' ) . '</label>
				</td>
			</tr>

		</table>

		<hr>' . "\n\n";

	}



	/**
	 * Render "Prettify Menu" options.
	 *
	 * @since 0.1
	 */
	public function admin_form_styling_options() {

		// init checkbox
		$prettify_menu = '';
		if ( $this->setting_get( 'prettify_menu', '0' ) == '1' ) $prettify_menu = ' checked="checked"';

		// show sync
		echo '
		<h3>' . __( 'Prettify CiviCRM Menu', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'Checking this option applies some styling tweaks that make the CiviCRM menu look a little better.', 'civicrm-admin-utilities' ) . '</p>

		<table class="form-table">

			<tr>
				<th scope="row">' . __( 'Prettify CiviCRM Menu', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_menu" id="civicrm_admin_utilities_menu" value="1"' . $prettify_menu . ' />
					<label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_menu">' . __( 'Check this to prettify the CiviCRM menu.', 'civicrm-admin-utilities' ) . '</label>
				</td>
			</tr>

		</table>

		<hr>' . "\n\n";

	}



	/**
	 * Render "WordPress Access Control" options.
	 *
	 * @since 0.3.2
	 */
	public function admin_form_access_options() {

		// init checkbox
		$prettify_access = '';
		if ( $this->setting_get( 'prettify_access', '0' ) == '1' ) $prettify_access = ' checked="checked"';

		// show sync
		echo '
		<h3>' . __( 'Fix WordPress Access Control form', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'Checking this option fixes the appearance of the WordPress Access Control form.', 'civicrm-admin-utilities' ) . '</li>
		</ol>

		<table class="form-table">

			<tr>
				<th scope="row">' . __( 'Fix WordPress Access Control form', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_access" id="civicrm_admin_utilities_access" value="1"' . $prettify_access . ' />
					<label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_access">' . __( 'Check this to fix the appearance of the WordPress Access Control form.', 'civicrm-admin-utilities' ) . '</label>
				</td>
			</tr>

		</table>

		<hr>' . "\n\n";

	}



	/**
	 * Get admin bar options.
	 *
	 * @since 0.1
	 */
	public function admin_form_admin_bar_options() {

		// init checkbox
		$admin_bar = '';
		if ( $this->setting_get( 'admin_bar', '0' ) == '1' ) $admin_bar = ' checked="checked"';

		// show
		echo '
		<h3>' . __( 'Admin Bar Options', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'Some people find it helpful to have links directly to CiviCRM components available from the WordPress admin bar.', 'civicrm-admin-utilities' ) . '</p>

		<table class="form-table">

			<tr>
				<th scope="row">' . __( 'Shortcuts Menu', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_admin_bar" id="civicrm_admin_utilities_admin_bar" value="1"' . $admin_bar . ' />
					<label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_admin_bar">' . __( 'Check this to add a CiviCRM Shortcuts Menu to the WordPress admin bar.', 'civicrm-admin-utilities' ) . '</label>
				</td>
			</tr>

		</table>

		<hr>' . "\n\n";

	}



	/**
	 * Get post type options.
	 *
	 * @since 0.1
	 */
	public function admin_form_post_type_options() {

		// get CPTs with admin UI
		$args = array(
			'public'   => true,
			'show_ui' => true,
		);

		$output = 'names'; // names or objects, note names is the default
		$operator = 'and'; // 'and' or 'or'

		// get post types
		$post_types = get_post_types( $args, $output, $operator );

		// init outputs
		$output = array();
		$options = '';

		// get chosen post types
		$selected_types = $this->setting_get( 'post_types', array() );

		// sanity check
		if ( count( $post_types ) > 0 ) {

			foreach( $post_types AS $post_type ) {

				// filter only those which have an editor
				if ( post_type_supports( $post_type, 'editor' ) ) {

					$checked = '';
					if ( in_array( $post_type, $selected_types ) ) $checked = ' checked="checked"';

					// add checkbox
					$output[] = '<p><input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_post_types[]" value="' . $post_type . '"' . $checked . ' /> <label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_post_types">' . $post_type . '</label></p>';

				}

			}

			// implode
			$options = implode( "\n", $output );

		}

		// show sync
		echo '
		<h3>' . __( 'Post Type Options', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'Select which post types you want the CiviCRM shortcode button to appear on.', 'civicrm-admin-utilities' ) . '</p>

		' . $options . '

		<hr>' . "\n\n";

	}



	/**
	 * Get utility links.
	 *
	 * @since 0.1
	 */
	public function admin_form_utilities() {

		// show sync
		echo '
		<h3>' . __( 'Miscellaneous Utilities', 'civicrm-admin-utilities' ) . '</h3>

		<p>' . __( 'Some useful functions and shortcuts to various commonly used CiviCRM admin pages.', 'civicrm-admin-utilities' ) . '</p>

		<table class="form-table">

			<tr>
				<th scope="row">' . __( 'Clear Caches', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="civicrm_admin_utilities_cache" id="civicrm_admin_utilities_cache" value="1" />
					<label class="civicrm_admin_utilities_settings_label" for="civicrm_admin_utilities_cache">' . __( 'Check this to clear the CiviCRM caches.', 'civicrm-admin-utilities' ) . '</label>
				</td>
			</tr>

			<tr>
				<th scope="row">' . __( 'Rebuild Menu', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<a href="' . admin_url( 'admin.php' ) . '?page=CiviCRM&q=civicrm/menu/rebuild?reset=1">' . __( 'Click this to rebuild the CiviCRM menu.', 'civicrm-admin-utilities' ) . '</a>
				</td>
			</tr>

			<tr>
				<th scope="row">' . __( 'Rebuild Database Triggers', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<a href="' . admin_url( 'admin.php' ) . '?page=CiviCRM&q=civicrm/menu/rebuild?reset=1&triggerRebuild=1">' . __( 'Click this to rebuild the triggers in the CiviCRM database.', 'civicrm-admin-utilities' ) . '</a>
				</td>
			</tr>

			<tr>
				<th scope="row">' . __( 'Upgrade CiviCRM', 'civicrm-admin-utilities' ) . '</th>
				<td>
					<a href="' . admin_url( 'admin.php' ) . '?page=CiviCRM&q=civicrm/upgrade&reset=1">' . __( 'Click this to upgrade CiviCRM.', 'civicrm-admin-utilities' ) . '</a>
				</td>
			</tr>

		</table>

		<hr>' . "\n\n";

	}



	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.1
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function admin_form_url_get() {

		// sanitise admin page url
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );

		if ( $url_array ) {

			// strip flag if present
			$url_array[0] = str_replace( '&amp;updated=true', '', $url_array[0] );

			// rebuild
			$target_url = htmlentities( $url_array[0] . '&updated=true' );

		}

		// --<
		return $target_url;

	}




	//##########################################################################



	/**
	 * Update options supplied by our admin page.
	 *
	 * @since 0.1
	 */
	public function update_options() {

	 	// was the form submitted?
		if( isset( $_POST['civicrm_admin_utilities_submit'] ) ) {

			// check that we trust the source of the data
			check_admin_referer( 'civicrm_admin_utilities_admin_action', 'civicrm_admin_utilities_nonce' );

			// init vars
			$civicrm_admin_utilities_main_site = '';
			$civicrm_admin_utilities_menu = '';
			$civicrm_admin_utilities_access = '';
			$civicrm_admin_utilities_post_types = array();
			$civicrm_admin_utilities_cache = '';
			$civicrm_admin_utilities_admin_bar = '';

			// get variables
			extract( $_POST );

			// init force cache-lcearing flag
			$force = false;

			// did we ask to remove the menu on sub-sites?
			if ( $civicrm_admin_utilities_main_site == '1' ) {
				$this->setting_set( 'main_site_only', '1' );
			} else {
				$this->setting_set( 'main_site_only', '0' );
			}

			// get existing menu setting
			$existing_menu = $this->setting_get( 'prettify_menu', '0' );
			if ( $civicrm_admin_utilities_menu != $existing_menu ) {
				$force = true;
			}

			// did we ask to prettify the menu?
			if ( $civicrm_admin_utilities_menu == '1' ) {
				$this->setting_set( 'prettify_menu', '1' );
			} else {
				$this->setting_set( 'prettify_menu', '0' );
			}

			// get existing access setting
			$existing_access = $this->setting_get( 'prettify_access', '0' );
			if ( $civicrm_admin_utilities_access != $existing_access ) {
				$force = true;
			}

			// did we ask to fix the access form?
			if ( $civicrm_admin_utilities_access == '1' ) {
				$this->setting_set( 'prettify_access', '1' );
			} else {
				$this->setting_set( 'prettify_access', '0' );
			}

			// which post types are we enabling the CiviCRM button on?
			if ( count( $civicrm_admin_utilities_post_types ) > 0 ) {

				// sanitise array
				array_walk(
					$civicrm_admin_utilities_post_types,
					function( &$item ) {
						$item = esc_sql( trim( $item ) );
					}
				);

				// set option
				$this->setting_set( 'post_types', $civicrm_admin_utilities_post_types );

			} else {
				$this->setting_set( 'post_types', array() );
			}

			// did we ask to add the shortcuts menu to the admin bar?
			if ( $civicrm_admin_utilities_admin_bar == '1' ) {
				$this->setting_set( 'admin_bar', '1' );
			} else {
				$this->setting_set( 'admin_bar', '0' );
			}

			// save options
			$this->settings_save();

			// clear caches if asked to - or if forced to do so
			if ( $civicrm_admin_utilities_cache == '1' OR $force ) {
				$this->clear_caches();
			}

		}

	}



	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool True if CiviCRM active, false otherwise.
	 */
	public function is_active() {

		// bail if no CiviCRM init function
		if ( ! function_exists( 'civi_wp' ) ) return false;

		// try and init CiviCRM
		return civi_wp()->initialize();

	}



	/**
	 * Check if CiviCRM's WordPress Access Control template has been fixed.
	 *
	 * @since 0.3.2
	 *
	 * @return bool $fixed True if fixed, false otherwise.
	 */
	public function access_form_fixed() {

		// init return
		$fixed = false;

		// get current version
		$version = CRM_Utils_System::version();

		// find major version
		$parts = explode( '.', $version );
		$major_version = $parts[0] . '.' . $parts[1];

		// CiviCRM 4.6 is LTS and may have the fix back-ported
		if ( version_compare( $major_version, '4.6', '=' ) ) {
			if ( version_compare( $version, '4.6.34', '>=' ) ) $fixed = true;
		} else {
			if ( version_compare( $version, '4.7.28', '>=' ) ) $fixed = true;
		}

		// --<
		return $fixed;

	}



	/**
	 * Clear CiviCRM caches.
	 *
	 * Another way to do this might be:
	 * CRM_Core_Invoke::rebuildMenuAndCaches(TRUE);
	 *
	 * @since 0.1
	 */
	public function clear_caches() {

		// init or die
		if ( ! $this->is_active() ) return;

		// access config object
		$config = CRM_Core_Config::singleton();

		// clear db cache
		CRM_Core_Config::clearDBCache();

		// cleanup the templates_c directory
		$config->cleanup( 1, TRUE );

		// cleanup the session object
		$session = CRM_Core_Session::singleton();
		$session->reset( 1 );

	}



	/**
	 * Save array as site option.
	 *
	 * @since 0.1
	 *
	 * @return bool Success or failure.
	 */
	public function settings_save() {

		// save array as site option
		return civicrm_admin_utilities_site_option_set( 'civicrm_admin_utilities_settings', $this->settings );

	}



	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @return bool Whether or not the setting exists.
	 */
	public function setting_exists( $setting_name = '' ) {

		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_exists()', 'civicrm-admin-utilities' ) );
		}

		// get existence of setting in array
		return array_key_exists( $setting_name, $this->settings );

	}



	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @param mixed $default The default value if the setting does not exist.
	 * @return mixed The setting or the default.
	 */
	public function setting_get( $setting_name = '', $default = false ) {

		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_get()', 'civicrm-admin-utilities' ) );
		}

		// get setting
		return ( array_key_exists( $setting_name, $this->settings ) ) ? $this->settings[$setting_name] : $default;

	}



	/**
	 * Sets a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @param mixed $value The value of the setting.
	 */
	public function setting_set( $setting_name = '', $value = '' ) {

		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_set()', 'civicrm-admin-utilities' ) );
		}

		// test for other than string
		if ( ! is_string( $setting_name ) ) {
			die( __( 'You must supply the setting as a string to setting_set()', 'civicrm-admin-utilities' ) );
		}

		// set setting
		$this->settings[$setting_name] = $value;

	}



	/**
	 * Deletes a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 */
	public function setting_delete( $setting_name = '' ) {

		// test for null
		if ( $setting_name == '' ) {
			die( __( 'You must supply an setting to setting_delete()', 'civicrm-admin-utilities' ) );
		}

		// unset setting
		unset( $this->settings[$setting_name] );

	}



} // class ends



/**
 * =============================================================================
 * Globally-available utility functions
 * =============================================================================
 *
 * The "site_option" functions below are useful because - in multisite - they
 * access Network Options, while in single-site they access Blog Options.
 *
 * =============================================================================
 */



/**
 * Test existence of a specified site option.
 *
 * @since 0.1
 *
 * @param str $option_name The name of the option.
 * @return bool $exists Whether or not the option exists.
 */
function civicrm_admin_utilities_site_option_exists( $option_name = '' ) {

	// test for empty
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civicrm_admin_utilities_site_option_exists()', 'civicrm-admin-utilities' ) );
	}

	// test by getting option with unlikely default
	if ( civicrm_admin_utilities_site_option_get( $option_name, 'fenfgehgefdfdjgrkj' ) == 'fenfgehgefdfdjgrkj' ) {
		return false;
	} else {
		return true;
	}

}



/**
 * Return a value for a specified site option.
 *
 * @since 0.1
 *
 * @param str $option_name The name of the option.
 * @param str $default The default value of the option if it has no value.
 * @return mixed $value the value of the option.
 */
function civicrm_admin_utilities_site_option_get( $option_name = '', $default = false ) {

	// test for empty
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civicrm_admin_utilities_site_option_get()', 'civicrm-admin-utilities' ) );
	}

	// get option
	return get_site_option( $option_name, $default );

}



/**
 * Set a value for a specified site option.
 *
 * @since 0.1
 *
 * @param str $option_name The name of the option.
 * @param mixed $value The value to set the option to.
 * @return bool $success True if the value of the option was successfully saved.
 */
function civicrm_admin_utilities_site_option_set( $option_name = '', $value = '' ) {

	// test for empty
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civicrm_admin_utilities_site_option_set()', 'civicrm-admin-utilities' ) );
	}

	// set option
	return update_site_option( $option_name, $value );

}



/**
 * Delete a specified site option.
 *
 * @since 0.1
 *
 * @param str $option_name The name of the option.
 * @return bool $success True if the value of the option was successfully deleted.
 */
function civicrm_admin_utilities_site_option_delete( $option_name = '' ) {

	// test for empty
	if ( $option_name == '' ) {
		die( __( 'You must supply an option to civicrm_admin_utilities_site_option_delete()', 'civicrm-admin-utilities' ) );
	}

	// delete option
	return delete_site_option( $option_name );

}



