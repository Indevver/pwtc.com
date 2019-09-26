<?php

class PwtcMileage_Admin {

    private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	private static function init_hooks() {
		self::$initiated = true;

		// Register admin menu creation callback
		add_action( 'admin_menu', 
			array( 'PwtcMileage_Admin', 'plugin_menu' ) );

		// Register script and style enqueue callbacks
		add_action( 'admin_enqueue_scripts', 
			array( 'PwtcMileage_Admin', 'load_admin_scripts' ) );

		// Register ajax callbacks
		add_action( 'wp_ajax_pwtc_mileage_lookup_posts', 
			array( 'PwtcMileage_Admin', 'lookup_posts_callback') );
		add_action( 'wp_ajax_pwtc_mileage_lookup_rides', 
			array( 'PwtcMileage_Admin', 'lookup_rides_callback') );
		add_action( 'wp_ajax_pwtc_mileage_create_ride', 
			array( 'PwtcMileage_Admin', 'create_ride_callback') );
		add_action( 'wp_ajax_pwtc_mileage_rename_ride', 
			array( 'PwtcMileage_Admin', 'rename_ride_callback') );
		add_action( 'wp_ajax_pwtc_mileage_associate_ride', 
			array( 'PwtcMileage_Admin', 'associate_ride_callback') );
		add_action( 'wp_ajax_pwtc_mileage_create_ride_from_event', 
			array( 'PwtcMileage_Admin', 'create_ride_from_event_callback') );
		add_action( 'wp_ajax_pwtc_mileage_remove_ride', 
			array( 'PwtcMileage_Admin', 'remove_ride_callback') );
		add_action( 'wp_ajax_pwtc_mileage_lookup_ridesheet', 
			array( 'PwtcMileage_Admin', 'lookup_ridesheet_callback') );
		add_action( 'wp_ajax_pwtc_mileage_next_rider_id', 
			array( 'PwtcMileage_Admin', 'next_rider_id_callback') );
		add_action( 'wp_ajax_pwtc_mileage_lookup_riders', 
			array( 'PwtcMileage_Admin', 'lookup_riders_callback') );
		add_action( 'wp_ajax_pwtc_mileage_create_rider', 
			array( 'PwtcMileage_Admin', 'create_rider_callback') );
		add_action( 'wp_ajax_pwtc_mileage_remove_rider', 
			array( 'PwtcMileage_Admin', 'remove_rider_callback') );
		add_action( 'wp_ajax_pwtc_mileage_sync_rider', 
			array( 'PwtcMileage_Admin', 'sync_rider_callback') );
		add_action( 'wp_ajax_pwtc_mileage_get_rider', 
			array( 'PwtcMileage_Admin', 'get_rider_callback') );
		add_action( 'wp_ajax_pwtc_mileage_xfer_ridesheets', 
			array( 'PwtcMileage_Admin', 'xfer_ridesheets_callback') );
		add_action( 'wp_ajax_pwtc_mileage_xfer_user_profile', 
			array( 'PwtcMileage_Admin', 'xfer_user_profile_callback') );
		add_action( 'wp_ajax_pwtc_mileage_purge_rider', 
			array( 'PwtcMileage_Admin', 'purge_rider_callback') );
		add_action( 'wp_ajax_pwtc_mileage_remove_leader', 
			array( 'PwtcMileage_Admin', 'remove_leader_callback') );
		add_action( 'wp_ajax_pwtc_mileage_remove_mileage', 
			array( 'PwtcMileage_Admin', 'remove_mileage_callback') );
		add_action( 'wp_ajax_pwtc_mileage_add_leader', 
			array( 'PwtcMileage_Admin', 'add_leader_callback') );
		add_action( 'wp_ajax_pwtc_mileage_add_mileage', 
			array( 'PwtcMileage_Admin', 'add_mileage_callback') );
		add_action( 'wp_ajax_pwtc_mileage_generate_report', 
			array( 'PwtcMileage_Admin', 'generate_report_callback') );

		add_action( 'wc_memberships_for_teams_process_team_meta', 
			array( 'PwtcMileage_Admin', 'process_team_meta_callback' ), 999, 2 );

    }    

	/*************************************************************/
	/* Script and style enqueue callback functions
	/*************************************************************/

	public static function load_admin_scripts($hook) {
		if (!strpos($hook, "pwtc_mileage")) {
            return;
        }
		wp_enqueue_style('pwtc_mileage_admin_css', 
			PWTC_MILEAGE__PLUGIN_URL . 'admin-style.css', array(),
			filemtime(PWTC_MILEAGE__PLUGIN_DIR . 'admin-style.css'));
		wp_enqueue_style('pwtc_mileage_datepicker_css', 
			PWTC_MILEAGE__PLUGIN_URL . 'datepicker.css', array(),
			filemtime(PWTC_MILEAGE__PLUGIN_DIR . 'datepicker.css'));
		wp_enqueue_style('wp-jquery-ui-dialog');
		wp_enqueue_script('jquery-ui-datepicker');   
		wp_enqueue_script('pwtc_mileage_admin_js', 
			PWTC_MILEAGE__PLUGIN_URL . 'admin-scripts.js', array('jquery-ui-dialog'), 
			filemtime(PWTC_MILEAGE__PLUGIN_DIR . 'admin-scripts.js'), true);
		wp_enqueue_script('pwtc_mileage_dateformatter_js', 
			PWTC_MILEAGE__PLUGIN_URL . 'php-date-formatter.min.js', array('jquery'), 
			filemtime(PWTC_MILEAGE__PLUGIN_DIR . 'php-date-formatter.min.js'), true);
	}

	/*************************************************************/
	/* Ajax callback functions
	/*************************************************************/

	public function process_team_meta_callback( $post_id, \WP_Post $post ) {
		$team = wc_memberships_for_teams_get_team( $post->ID );
		if ($team) {
			$user_memberships = $team->get_user_memberships();
			foreach ( $user_memberships as $user_membership ) {
				PwtcMileage::adjust_team_member_data_callback(false, $team, $user_membership);
			}	
		}
	}

	public static function lookup_posts_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to lookup posted rides.'
			);
			echo wp_json_encode($response);
		}
		else if (isset($_POST['startdate']) and isset($_POST['enddate'])) {
			$startdate = trim($_POST['startdate']);	
			$enddate = trim($_POST['enddate']);	
			if (!PwtcMileage::validate_date_str($startdate)) {
				$response = array(
					'error' => 'Start date entry "' . $startdate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($enddate)) {
				$response = array(
					'error' => 'End date entry "' . $enddate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else {
				$posts = PwtcMileage_DB::fetch_posts_without_rides($startdate, $enddate);
				$response = array('posts' => $posts);
				echo wp_json_encode($response);
			}
		}
		else {
			$posts = PwtcMileage_DB::fetch_posts_without_rides();
			$response = array('posts' => $posts);
			echo wp_json_encode($response);
		}
		wp_die();
	}

	public static function lookup_rides_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to lookup ridesheets.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['startdate']) or !isset($_POST['enddate']) or
			!isset($_POST['title'])) {
			$response = array(
				'error' => 'Input parameters needed to lookup ridesheets are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$startdate = trim($_POST['startdate']);	
			$enddate = trim($_POST['enddate']);	
			$title = sanitize_text_field($_POST['title']);	
			if (!PwtcMileage::validate_date_str($startdate)) {
				$response = array(
					'error' => 'Start date entry "' . $startdate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($enddate)) {
				$response = array(
					'error' => 'End date entry "' . $enddate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else {
				$rides = PwtcMileage_DB::fetch_club_rides($title, $startdate, $enddate);
				$response = array(
					'title' => $title,
					'startdate' => $startdate,
					'enddate' => $enddate,
					'rides' => $rides);
				echo wp_json_encode($response);
			}
		}
		wp_die();
	}

	public static function create_ride_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to create ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['startdate']) or !isset($_POST['title']) or
			!isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to create ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$startdate = trim($_POST['startdate']);	
			$title = sanitize_text_field($_POST['title']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_create_ride')) {
				$response = array(
					'error' => 'Nonce security check failed attempting to create ridesheet.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_ride_title_str($title)) {
				$response = array(
					'error' => 'Title entry "' . $title . '" is invalid, must start with a letter or digit.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($startdate)) {
				$response = array(
					'error' => 'Start date entry "' . $startdate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else {
				$status = PwtcMileage_DB::insert_ride($title, $startdate);
				if (false === $status or 0 === $status) {
					$response = array(
						'error' => 'Could not insert ridesheet into database.'
					);
					echo wp_json_encode($response);
				}
				else {
					$ride_id = PwtcMileage_DB::get_new_ride_id();
					$leaders = PwtcMileage_DB::fetch_ride_leaders($ride_id);
					$mileage = PwtcMileage_DB::fetch_ride_mileage($ride_id);
					$response = array(
						'ride_id' => $ride_id,
						'title' => $title,
						'startdate' => $startdate, 
						'leaders' => $leaders,
						'mileage' => $mileage);
					echo wp_json_encode($response);
				}
			}
		}
		wp_die();
	}

	public static function rename_ride_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to rename ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['title']) or
			!isset($_POST['date']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to rename ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$ride_id = trim($_POST['ride_id']);	
			$title = sanitize_text_field($_POST['title']);	
			$date = trim($_POST['date']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_rename_ride')) {
				$response = array(
					'error' => 'Nonce security check failed attempting to rename ridesheet.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($ride_id)) {
				$response = array(
					'error' => 'Ride ID "' . $ride_id . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_ride_title_str($title)) {
				$response = array(
					'error' => 'Title entry "' . $title . '" is invalid, must start with a letter or digit.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($date)) {
				$response = array(
					'error' => 'Date entry "' . $date . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else {
				$status = PwtcMileage_DB::update_ride(intval($ride_id), $title, $date);
				if (false === $status or 0 === $status) {
					$response = array(
						'error' => 'Did not update ridesheet, it might not have changed.'
					);
					echo wp_json_encode($response);
				}
				else {
					$response = array(
						'ride_id' => $ride_id,
						'title' => $title,
						'date' => $date);
					echo wp_json_encode($response);
				}
			}
		}
		wp_die();
	}

	public static function associate_ride_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to associate ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['post_id']) or
			!isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to associate ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$ride_id = trim($_POST['ride_id']);	
			$post_id = trim($_POST['post_id']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_associate_ride')) {
				$response = array(
					'error' => 'Nonce security check failed attempting to associate ridesheet.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($ride_id)) {
				$response = array(
					'error' => 'Ride ID "' . $ride_id . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($post_id)) {
				$response = array(
					'error' => 'Posted ride ID "' . $post_id . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$postid = intval($post_id);
				if ($postid != 0) {
					$posts = pwtc_mileage_fetch_posted_ride($postid);
					if ($posts) {
						$title = $posts[0][1];
						$date = $posts[0][2];
						if (!PwtcMileage::validate_ride_title_str($title)) {
							$response = array(
								'error' => 'Title entry "' . $title . '" is invalid, must start with a letter or digit.'
							);
							echo wp_json_encode($response);
						}
						else {
							$status = PwtcMileage_DB::update_ride(
								intval($ride_id), $title, $date , $postid);
							if (false === $status or 0 === $status) {
								$response = array(
									'error' => 'Did not update ridesheet, it might not have changed.');
								echo wp_json_encode($response);
							}
							else {
								$response = array(
									'ride_id' => $ride_id,
									'title' => $title,
									'date' => $date ,
									'post_id' => $post_id);
								$url = get_permalink($postid);
								if ($url) {
									$response['post_url'] = $url;
								}
								echo wp_json_encode($response);									
							}
						}
					}
					else {
						$response = array(
							'error' => 'Posted ride ID ' . $post_id . ' not found.'
						);
						echo wp_json_encode($response);		
					}
				}
				else {
					$status = PwtcMileage_DB::update_ride_post_id(
						intval($ride_id), 0);
					if (false === $status or 0 === $status) {
						$response = array(
							'error' => 'Did not update ridesheet, it might not have changed.');
						echo wp_json_encode($response);
					}
					else {
						$response = array(
							'ride_id' => $ride_id,
							'post_id' => 0);
						echo wp_json_encode($response);									
					}
				}
			}
		}
		wp_die();
	}

	public static function create_ride_from_event_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to create ridesheet from posted ride.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['startdate']) or !isset($_POST['title']) or
			!isset($_POST['post_id']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to create ridesheet from posted ride are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$startdate = trim($_POST['startdate']);	
			$title = sanitize_text_field($_POST['title']);	
			$postid = trim($_POST['post_id']);
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_create_ride_from_event')) {
				$response = array(
					'error' => 'Nonce security check failed attempting to create ridesheet from posted ride.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_ride_title_str($title)) {
				$response = array(
					'error' => 'Title entry "' . $title . '" is invalid, must start with a letter or digit.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($startdate)) {
				$response = array(
					'error' => 'Start date entry "' . $startdate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($postid)) {
				$response = array(
					'error' => 'Post ID "' . $postid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$results = PwtcMileage_DB::fetch_ride_by_post_id(intval($postid));
				if (count($results) > 0) {
					$response = array(
						'error' => 'Posted ride already has ridesheet.'
					);
					echo wp_json_encode($response);
				}
				else {
					$status = PwtcMileage_DB::insert_ride_with_postid($title, $startdate, intval($postid));
					if (false === $status or 0 === $status) {
						$response = array(
							'error' => 'Could not insert ridesheet into database.'
						);
						echo wp_json_encode($response);
					}
					else {
						$ride_id = PwtcMileage_DB::get_new_ride_id();
						$larray = pwtc_mileage_fetch_ride_leader_ids(intval($postid));
						foreach ($larray as $item) {
							$result = PwtcMileage_DB::fetch_rider($item);
							if (count($result) > 0) {
								PwtcMileage_DB::insert_ride_leader($ride_id, $item);
							}
						}
						$leaders = PwtcMileage_DB::fetch_ride_leaders($ride_id);
						$mileage = PwtcMileage_DB::fetch_ride_mileage($ride_id);
						$response = array(
							'ride_id' => $ride_id,
							'title' => $title,
							'startdate' => $startdate, 
							'leaders' => $leaders,
							'mileage' => $mileage);
						$guid = get_permalink(intval($postid));
						if ($guid) {
							$response['post_guid'] = $guid;
						}
						echo wp_json_encode($response);
					}
				}
			}
		}
		wp_die();
	}

	public static function remove_ride_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to remove a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to remove a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_remove_ride')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$mcnt = PwtcMileage_DB::fetch_ride_has_mileage(intval($rideid));
				$lcnt = PwtcMileage_DB::fetch_ride_has_leaders(intval($rideid));
				if ($mcnt > 0 or $lcnt > 0) {
					$response = array(
						'error' => 'Cannot delete a ridesheet that has riders.'
					);
					echo wp_json_encode($response);
				}
				else {
					$status = PwtcMileage_DB::delete_ride(intval($rideid));
					if (false === $status or 0 === $status) {
						$response = array(
							'error' => 'Could not delete ridesheet from database.'
						);
						echo wp_json_encode($response);
					}
					else {
						$response = array(
							'ride_id' => $rideid);
						echo wp_json_encode($response);
					}
				}
			}
		}
		wp_die();	
	}

	public static function lookup_ridesheet_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to lookup a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id'])) {
			$response = array(
				'error' => 'Input parameters needed to lookup a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$results = PwtcMileage_DB::fetch_ride(intval($rideid));
				if (count($results) > 0) {
					$title = $results[0]['title'];
					$startdate = $results[0]['date'];
					$postid = intval($results[0]['post_id']);
					$leaders = PwtcMileage_DB::fetch_ride_leaders(intval($rideid));
					$mileage = PwtcMileage_DB::fetch_ride_mileage(intval($rideid));
					$response = array(
						'startdate' => $startdate,
						'ride_id' => $rideid,
						'title' => $title,
						'leaders' => $leaders,
						'mileage' => $mileage);
					if ($postid > 0) {
						$guid = get_permalink($postid);
						if ($guid) {
							$response['post_guid'] = $guid;
						}
					}
					echo wp_json_encode($response);
				}
				else {
					$response = array(
						'error' => 'Could not fetch ridesheet from database.'
					);
					echo wp_json_encode($response);
				}
			}
		}
		wp_die();
	}

	public static function lookup_riders_callback() {
		if (!current_user_can(PwtcMileage::VIEW_MILEAGE_CAP) and
			!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP) and
			!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to lookup a rider.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['lastname']) or !isset($_POST['firstname'])) {
			$response = array(
				'error' => 'Input parameters needed to lookup a rider are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$lastname = sanitize_text_field($_POST['lastname']);	
			$firstname = sanitize_text_field($_POST['firstname']);
			$memberid = '';
			if (isset($_POST['memberid'])) {
				$memberid = sanitize_text_field($_POST['memberid']);
			}
			$active = 'false';
			if (isset($_POST['active'])) {
				$active = trim($_POST['active']);
			}
			$members = null;
			if ($memberid == '' and $firstname == '' and $lastname == '') {
				$members = array();
			}
			else {
				$options = PwtcMileage::get_plugin_options();
				$test_date = '';
				if ($active == 'true' and $options['user_lookup_mode'] != 'woocommerce') {
					$test_date = PwtcMileage::get_date_for_expir_check();
				}
				$results = PwtcMileage_DB::fetch_riders($lastname, $firstname, $memberid, $test_date);
				if ($active == 'true' and $options['user_lookup_mode'] == 'woocommerce') {
					$members = array();
					foreach ($results as $item) {
						$users = pwtc_mileage_lookup_user($item['member_id']);
						if (!empty($users)) {
							$members[] = $item;
						}
					}
				}
				else {
					$members = $results;
				}
			}	
			$response = array(
				'memberid' => $memberid,
				'lastname' => $lastname,
				'firstname' => $firstname,
				'members' => $members);
			echo wp_json_encode($response);
		}
		wp_die();
	}

	public static function lookup_user_memberships($memberid) {
		$add_edit_link = current_user_can('manage_options');
		$users = array();
		$profiles = pwtc_mileage_lookup_user($memberid);
		foreach ($profiles as $profile) {
			$info = get_userdata($profile->ID);
			$note = '';
			$expir_date = '';
			if (function_exists('wc_memberships_get_user_memberships')) {
				$memberships = wc_memberships_get_user_memberships($profile->ID);
				if (empty($memberships)) {
					$note = 'no membership';
				}
				else if (count($memberships) > 1) {
					$note = 'multiple memberships';
				}
				else {
					$expir_date = pwtc_mileage_get_expiration_date($memberships[0]);
				}
			}
			else {
				$note = 'cannot access membership';
			}
			$role = implode(", ", $info->roles);
			$riderid = get_field('rider_id', 'user_'.$profile->ID);
            if (!$riderid) {
                $riderid = '';
            }
			$item = array(
				'userid' => $profile->ID,
				'first_name' => trim($info->first_name),
				'last_name' => trim($info->last_name),
				'email' => trim($info->user_email),
				'expir_date' => $expir_date,
				'note' => $note,
				'role' => $role,
				'riderid' => $riderid
			);
			if ($add_edit_link) {
				$href = admin_url('user-edit.php?user_id=' . $profile->ID);
				$edit_url = '<a title="Edit user account profile." target="_blank" href="' . $href . '">Edit</a>';
				$item['editurl'] = $edit_url;
			}
			$users[] = $item;
		}
		return $users;
	}

	public static function next_rider_id_callback() {
		if (!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to generate the next rider ID.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = PwtcMileage_DB::gen_new_member_id();
			if ($memberid == '') {
				$response = array(
					'error' => 'Cannot generate next rider ID.'
				);
				echo wp_json_encode($response);					
			}
			else {
				$response = array(
					'next_member_id' => $memberid);
				echo wp_json_encode($response);
			}
		}
		wp_die();
	}

	public static function create_rider_callback() {
		if (!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to create a rider.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['member_id']) or !isset($_POST['nonce']) or
			!isset($_POST['lastname']) or !isset($_POST['firstname']) or
			!isset($_POST['exp_date']) or !isset($_POST['mode'])) {
			$response = array(
				'error' => 'Input parameters needed to create a rider are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = sanitize_text_field($_POST['member_id']);	
			$nonce = $_POST['nonce'];	
			$lastname = sanitize_text_field($_POST['lastname']);	
			$firstname = sanitize_text_field($_POST['firstname']);
			$expdate = sanitize_text_field($_POST['exp_date']);
			$mode = $_POST['mode'];
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_create_rider')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (($mode == 'insert' and $memberid != '') and 
				!PwtcMileage::validate_member_id_str($memberid)) {
				$response = array(
					'error' => 'ID entry "' . $memberid . '" is invalid, must be a 5 digit number.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_member_name_str($lastname)) {
				$response = array(
					'error' => 'Last name entry "' . $lastname . '" is invalid, must start with a letter.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_member_name_str($firstname)) {
				$response = array(
					'error' => 'First name entry "' . $firstname . '" is invalid, must start with a letter.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_date_str($expdate)) {
				$response = array(
					'error' => 'Expiration date entry "' . $expdate . '" is invalid.'
				);
				echo wp_json_encode($response);
			}
			else {
				if ($memberid == '') {
					$response = array(
						'error' => 'Cannot generate new member id.'
					);
					echo wp_json_encode($response);					
				}
				else {
					$no_overwrite = false;
					if ($mode == 'insert') {
						if (count(PwtcMileage_DB::fetch_rider($memberid)) > 0) {
							$no_overwrite = true;
						}
					}
					if ($no_overwrite) {
						$response = array(
							'error' => 'ID ' . $memberid . ' already exists.'
						);
						echo wp_json_encode($response);
					}
					else {
						$status = PwtcMileage_DB::insert_rider($memberid, $lastname, $firstname, $expdate);	
						if (false === $status or 0 === $status) {
							if ($mode == 'update') {							
								$response = array(
									'error' => 'Did not update rider info, it might not have changed.'
								);
							}
							else {
								$response = array(
									'error' => 'Could not insert rider into database.'
								);
							}
							echo wp_json_encode($response);
						}
						else {
							$more_details = $mode === 'update';
							$not_found_msg = 'Insert/Update completed, but access of information for rider ' . $memberid . ' failed.';
							$response = self::get_rider_details($memberid, $not_found_msg, $more_details);
							echo wp_json_encode($response);
						}
					}
				}
			}
		}
		wp_die();
	}

	public static function remove_rider_callback() {
		if (!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to remove a rider.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['member_id']) or !isset($_POST['nonce'])){
			$response = array(
				'error' => 'Input parameters needed to remove a rider are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = sanitize_text_field($_POST['member_id']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_remove_rider')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else {
				$mcnt = PwtcMileage_DB::fetch_member_has_mileage($memberid);
				$lcnt = PwtcMileage_DB::fetch_member_has_leaders($memberid);
				if ($mcnt > 0 or $lcnt > 0) {
					$response = array(
						'error' => 'Cannot delete a rider that is entered on a ridesheet.'
					);
					echo wp_json_encode($response);
				}
				else {
					$plugin_options = PwtcMileage::get_plugin_options();
					$profile_found = true;
					if ($plugin_options['user_lookup_mode'] == 'woocommerce') {
						$users = pwtc_mileage_lookup_user($memberid);
						if (!empty($users)) {
							$profile_found = false;
						}
					}
					if ($profile_found) {
						$status = PwtcMileage_DB::delete_rider($memberid);	
						if (false === $status or 0 === $status) {
							$response = array(
								'error' => 'Could not delete rider from database.'
							);
							echo wp_json_encode($response);
						}
						else {
							$response = array('member_id' => $memberid);
							echo wp_json_encode($response);
						}
					}
					else {
						$response = array(
							'error' => 'Cannot delete a rider that is referenced in a user account.'
						);
						echo wp_json_encode($response);	
					}
				}
			}
		}
		wp_die();
	}

	public static function sync_rider_callback() {
		if (!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to sync a rider.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['member_id']) or !isset($_POST['nonce'])){
			$response = array(
				'error' => 'Input parameters needed to sync a rider are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = sanitize_text_field($_POST['member_id']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_sync_rider')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else {
				$users = pwtc_mileage_lookup_user($memberid);
				if (empty($users)) {
					$response = array(
						'error' => 'Cannot sync, no user account found for rider ' . $memberid . '.'
					);
					echo wp_json_encode($response);
				}
				else if (count($users) > 1) {
					$response = array(
						'error' => 'Cannot sync, multiple user accounts found for rider ' . $memberid . '.'
					);
					echo wp_json_encode($response);
				} 
				else {
					$user = $users[0];
					if (function_exists('wc_memberships_get_user_memberships')) {
						$memberships = wc_memberships_get_user_memberships($user->ID);
						if (empty($memberships)) {
							$response = array(
								'error' => 'Cannot sync, no membership found for rider ' . $memberid  . '.'
							);
							echo wp_json_encode($response);		
						}
						else if (count($memberships) > 1) {
							$response = array(
								'error' => 'Cannot sync, multiple memberships found for rider ' . $memberid  . '.'
							);
							echo wp_json_encode($response);		
						}
						else {
							$lastname = trim($user->last_name);
							$firstname = trim($user->first_name);
							$exp_date = pwtc_mileage_get_expiration_date($memberships[0]);
							try {
								pwtc_mileage_update_rider($memberid, $lastname, $firstname, $exp_date);
								$not_found_msg = 'Sync completed, but access of information for rider ' . $memberid . ' failed.';
								$response = self::get_rider_details($memberid, $not_found_msg, true);
								echo wp_json_encode($response);		
							}
							catch (Exception $e) {
								$msg = $e->getMessage();
								$response = array(
									'error' => $msg
								);
								echo wp_json_encode($response);		
							}
						}
					}
					else {
						$response = array(
							'error' => 'Cannot sync, membership system is not active.'
						);
						echo wp_json_encode($response);
					}
				}
			}
		}
		wp_die();
	}

	public static function get_rider_details($memberid, $not_found_msg, $more_details) {
		$result = PwtcMileage_DB::fetch_rider($memberid);
		if (count($result) == 0) {
			$response = array(
				'error' => $not_found_msg
			);
		}
		else {
			$response = array(
				'member_id' => $result[0]['member_id'],
				'lastname' => $result[0]['last_name'],
				'firstname' => $result[0]['first_name'],
				'exp_date' => $result[0]['expir_date']
			);
			if ($more_details) {
				$response['mileage_count'] = PwtcMileage_DB::fetch_member_has_mileage($memberid);;
				$response['leader_count'] = PwtcMileage_DB::fetch_member_has_leaders($memberid);
				$response['user_profiles'] = self::lookup_user_memberships($memberid);
			}
		}
		return $response;
	}

	public static function get_rider_callback() {
		if (!current_user_can(PwtcMileage::EDIT_RIDERS_CAP)) {
			$response = array(
				'error' => 'You are not allowed to fetch a rider.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['member_id']) or !isset($_POST['mode'])) {
			$response = array(
				'error' => 'Input parameters needed to fetch a rider are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = sanitize_text_field($_POST['member_id']);	
			$result = PwtcMileage_DB::fetch_rider($memberid);
			$more_details = $_POST['mode'] == 'view';
			$not_found_msg = 'Could not find rider ' . $memberid . '.';
			$response = self::get_rider_details($memberid, $not_found_msg, $more_details);
			echo wp_json_encode($response);						
		}	
		wp_die();
	}

	public static function xfer_ridesheets_callback() {
		if (!current_user_can('manage_options')) {
			$response = array(
				'error' => 'You are not allowed to transfer ridesheets.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['from_memberid']) or !isset($_POST['to_memberid']) or !isset($_POST['nonce'])){
			$response = array(
				'error' => 'Input parameters needed to transfer ridesheets are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$from_memberid = sanitize_text_field($_POST['from_memberid']);	
			$to_memberid = sanitize_text_field($_POST['to_memberid']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_xfer_ridesheets')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if ($from_memberid === $to_memberid) {
				$response = array(
					'error' => 'Cannot transfer, the transferee is the same as the transferer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$users = pwtc_mileage_lookup_user($from_memberid);
				if (empty($users)) {
					$status1 = PwtcMileage_DB::transfer_mileage_ownership($from_memberid, $to_memberid);
					$status2 = PwtcMileage_DB::transfer_leader_ownership($from_memberid, $to_memberid);
					if (false === $status1 and false === $status2) {
						$response = array(
							'error' => 'Transfer failed with a database error.'
						);
						echo wp_json_encode($response);
					}
					else {
						$dberr = '';
						if (false === $status1) {
							$dberr = 'Transfer completed for ride leaders but failed for mileage.';
						}
						else if (false === $status2) {
							$dberr = 'Transfer completed for mileage but failed for ride leaders.';
						}
						$not_found_msg = 'Transfer completed, but access of information for rider ' . $from_memberid . ' failed.';
						$response = self::get_rider_details($from_memberid, $not_found_msg, true);
						if (!empty($dberr)) {
							$response['dberr'] = $dberr;
						}
						echo wp_json_encode($response);	
					}
				}
				else {
					$response = array(
						'error' => 'Cannot transfer ridesheets, rider ' . $from_memberid . ' has a user account.'
					);
					echo wp_json_encode($response);
				}
			}
		}	
		wp_die();
	}

	public static function xfer_user_profile_callback() {
		if (!current_user_can('manage_options')) {
			$response = array(
				'error' => 'You are not allowed to transfer user accounts.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['from_memberid']) or !isset($_POST['to_memberid']) or !isset($_POST['nonce'])){
			$response = array(
				'error' => 'Input parameters needed to transfer user accounts are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$from_memberid = sanitize_text_field($_POST['from_memberid']);	
			$to_memberid = sanitize_text_field($_POST['to_memberid']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_xfer_user_profile')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if ($from_memberid === $to_memberid) {
				$response = array(
					'error' => 'Cannot transfer, the transferee is the same as the transferer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$users = pwtc_mileage_lookup_user($from_memberid);
				if (empty($users)) {
					$response = array(
						'error' => 'Cannot transfer, no user account found for rider ' . $from_memberid . '.'
					);
					echo wp_json_encode($response);
				}
				else if (count($users) > 1) {
					$response = array(
						'error' => 'Cannot transfer, multiple user accounts found for rider ' . $from_memberid . '.'
					);
					echo wp_json_encode($response);
				} 
				else {
					$from_user_id = $users[0]->ID;
					$users = pwtc_mileage_lookup_user($to_memberid);
					if (empty($users)) {
						if (update_field('rider_id', $to_memberid, 'user_'.$from_user_id)) {
							$not_found_msg = 'Transfer completed, but access of information for rider ' . $from_memberid . ' failed.';
							$response = self::get_rider_details($from_memberid, $not_found_msg, true);		
							echo wp_json_encode($response);	
						}
						else {
							$response = array(
								'error' => 'Cannot transfer, reset of user account for rider ' . $from_memberid . ' failed.'
							);
							echo wp_json_encode($response);	
						}
					}
					else {
						$response = array(
							'error' => 'Cannot transfer, rider ' . $to_memberid . ' already has a user account.'
						);
						echo wp_json_encode($response);	
					}
				}
			}
		}	
		wp_die();
	}

	public static function purge_rider_callback() {
		if (!current_user_can('manage_options')) {
			$response = array(
				'error' => 'You are not allowed to purge ridesheets from riders.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['member_id']) or !isset($_POST['nonce'])){
			$response = array(
				'error' => 'Input parameters needed to purge ridesheets from riders are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$memberid = sanitize_text_field($_POST['member_id']);	
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_purge_rider')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else {
				$users = pwtc_mileage_lookup_user($memberid);
				if (empty($users)) {
					$status1 = PwtcMileage_DB::purge_mileage($memberid);
					$status2 = PwtcMileage_DB::purge_leaders($memberid);
					if (false === $status1 or false === $status2) {
						$response = array(
							'error' => 'Purge failed with a database error.'
						);
						echo wp_json_encode($response);
					}
					else {
						$not_found_msg = 'Purge completed, but access of information for rider ' . $memberid . ' failed.';
						$response = self::get_rider_details($memberid, $not_found_msg, true);
						echo wp_json_encode($response);	
					}
				}
				else {
					$response = array(
						'error' => 'Cannot purge ridesheets, rider ' . $memberid . ' has a user account.'
					);
					echo wp_json_encode($response);
				}
			}
		}	
		wp_die();
	}

	public static function remove_leader_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to remove a leader from a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['member_id']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to remove a leader from a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			$memberid = trim($_POST['member_id']);
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_remove_leader')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$status = PwtcMileage_DB::delete_ride_leader(intval($rideid), $memberid);
				if (false === $status or 0 === $status) {
					$response = array(
						'error' => 'Could not delete ride leader from database.'
					);
					echo wp_json_encode($response);
				}
				else {
					$leaders = PwtcMileage_DB::fetch_ride_leaders(intval($rideid));
					$response = array(
						'ride_id' => $rideid,
						'leaders' => $leaders
					);
					echo wp_json_encode($response);
				}
			}
		}
		wp_die();
	}

	public static function remove_mileage_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to remove mileage from a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['member_id']) or 
			!isset($_POST['line_no']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to remove mileage from a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			$memberid = trim($_POST['member_id']);
			$lineno = trim($_POST['line_no']);
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_remove_mileage')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($lineno)) {
				$response = array(
					'error' => 'Line number "' . $lineno . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$status = PwtcMileage_DB::delete_ride_mileage(
					intval($rideid), $memberid, intval($lineno));
				if (false === $status or 0 === $status) {
					$response = array(
						'error' => 'Could not delete ride mileage from database.'
					);
					echo wp_json_encode($response);
				}
				else {
					$mileage = PwtcMileage_DB::fetch_ride_mileage(intval($rideid));
					$response = array(
						'ride_id' => $rideid,
						'mileage' => $mileage
					);
					echo wp_json_encode($response);
				}
			}
		}
		wp_die();
	}

	public static function check_expir_date($memberid) {
		$errormsg = null;
		$plugin_options = PwtcMileage::get_plugin_options();
		if (!$plugin_options['disable_expir_check']) {
			$rider = PwtcMileage_DB::fetch_rider($memberid);
			if (count($rider) > 0) {
				$r = $rider[0];
				$name = $r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['member_id'] . ')';
				if ($plugin_options['user_lookup_mode'] == 'woocommerce') {
					$users = pwtc_mileage_lookup_user($r['member_id']);
					if (empty($users)) {
						$errormsg = $name . ' has no user account and therefore no membership.';
					}
					else if (count($users) > 1) {
						$errormsg = 'Found multiple user accounts for ' . $name . ', notify website admin to correct.';
					}
					else {
						$user = $users[0];
						if (function_exists('wc_memberships_get_user_memberships')) {
							$memberships = wc_memberships_get_user_memberships($user->ID);
							if (empty($memberships)) {
								$errormsg = $name . ' has a user account but no membership.';
							}
							else if (count($memberships) > 1) {
								$errormsg = $name . ' has multiple memberships, notify website admin to correct.';
							}
							else {
								$exp_date = pwtc_mileage_get_expiration_date($memberships[0]);
								if (strtotime($exp_date) < strtotime(PwtcMileage::get_date_for_expir_check())) {
									$errormsg = 'The membership of ' . 
									$name . ' has expired on ' . 
									date('D M j Y', strtotime($exp_date)) . '.';
								}
							}
						}
						else {
							$errormsg = 'Membership system is not active, notify website admin to correct.';
						}	
					}
				}
				else {
					if (strtotime($r['expir_date']) < strtotime(PwtcMileage::get_date_for_expir_check())) {
						$errormsg = 'The membership of ' . $name . ' has expired on ' . 
						date('D M j Y', strtotime($r['expir_date'])) . '.';
					}
				}
			}
			else {
				$errormsg = 'Cannot find rider ' + $memberid . ' in mileage database, notify website admin to correct.';
			}
		}
		return $errormsg;
	}

	public static function add_leader_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to add a leader to a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['member_id']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to add a leader to a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			$memberid = trim($_POST['member_id']);
			$override = 'false';
			if (isset($_POST['override'])) {
				$override = trim($_POST['override']);
			}
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_add_leader')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$error = null;
				if ($override == 'false') {
					$error = self::check_expir_date($memberid);
				}
				if ($error != null) {
					$response = array(
						'error' => $error
					);
					echo wp_json_encode($response);
				}
				else {
					if (count(PwtcMileage_DB::fetch_ride_member_leaders(
						$memberid, intval($rideid))) > 0) {
						$response = array(
							'error' => 'Ride leader already exists.'
						);
						echo wp_json_encode($response);
					}
					else {
						$status = PwtcMileage_DB::insert_ride_leader(intval($rideid), $memberid);
						if (false === $status or 0 === $status) {
							$response = array(
								'error' => 'Could not insert ride leader into database.'
							);
							echo wp_json_encode($response);
						}
						else {
							$leaders = PwtcMileage_DB::fetch_ride_leaders(intval($rideid));
							$response = array(
								'ride_id' => $rideid,
								'leaders' => $leaders
							);
							echo wp_json_encode($response);
						}
					}
				}
			}
		}
		wp_die();
	}

	public static function add_mileage_callback() {
		if (!current_user_can(PwtcMileage::EDIT_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to add mileage to a ridesheet.'
			);
			echo wp_json_encode($response);
		}
		else if (!isset($_POST['ride_id']) or !isset($_POST['member_id']) or 
			!isset($_POST['line_no']) or !isset($_POST['mileage']) or 
			!isset($_POST['mode']) or !isset($_POST['nonce'])) {
			$response = array(
				'error' => 'Input parameters needed to add mileage to a ridesheet are missing.'
			);
			echo wp_json_encode($response);
		}
		else {
			$rideid = trim($_POST['ride_id']);
			$memberid = trim($_POST['member_id']);
			$lineno = trim($_POST['line_no']);
			$mileage = trim($_POST['mileage']);
			$override = 'false';
			if (isset($_POST['override'])) {
				$override = trim($_POST['override']);
			}
			$mode = trim($_POST['mode']);
			$nonce = $_POST['nonce'];	
			if (!wp_verify_nonce($nonce, 'pwtc_mileage_add_mileage')) {
				$response = array(
					'error' => 'Access security check failed.'
				);
				echo wp_json_encode($response);
			}
			else if (!PwtcMileage::validate_number_str($rideid)) {
				$response = array(
					'error' => 'Ride ID "' . $rideid . '" is invalid, must be nonnegative integer.'
				);
				echo wp_json_encode($response);
			}
			else {
				$error = null;
				if ($override == 'false') {
					$error = self::check_expir_date($memberid);
				}
				if ($error != null) {
					$response = array(
						'error' => $error
					);
					echo wp_json_encode($response);
				}
				else {
					if (!PwtcMileage::validate_mileage_str($mileage)) {
						$response = array(
							'error' => 'Mileage entry "' . $mileage . '" is invalid, must be a non-negative number.'
						);
						echo wp_json_encode($response);
					}
					else {
						if ($mode == 'add' and count(PwtcMileage_DB::fetch_ride_member_mileage(
							$memberid, intval($rideid))) > 0) {
							$response = array(
								'error' => 'Rider mileage already exists.'
							);
							echo wp_json_encode($response);
						}
						else {
							$err = '';
							if ($mode == 'add') {
								$status = PwtcMileage_DB::insert_ride_mileage(
									intval($rideid), $memberid, intval($mileage));
								if (false === $status or 0 === $status) {
									$err = 'Could not insert ride mileage into database.';
								}
							}
							else {
								if (!PwtcMileage::validate_number_str($lineno)) {
									$err = 'Line number "' . $lineno . '" is invalid, must be nonnegative integer.';
								}
								else {
									$status = PwtcMileage_DB::update_ride_mileage(
										intval($rideid), $memberid, intval($lineno), intval($mileage));
									if (false === $status or 0 === $status) {
										$err = 'Did not update ride mileage, it might not have changed.';
									}
								}
							}
							if ($err == '') {
								$mileage = PwtcMileage_DB::fetch_ride_mileage(intval($rideid));
								$response = array(
									'ride_id' => $rideid,
									'mileage' => $mileage
								);
								echo wp_json_encode($response);
							}
							else {
								$response = array(
									'error' => $err
								);
								echo wp_json_encode($response);	
							}
						}
					}
				}
			}
		}
		wp_die();
	}

	public static function generate_report() {
		if (!current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
			$response = array(
				'error' => 'You are not allowed to generate a mileage report.'
			);
			return $response;
		}
		else if (!isset($_POST['report_id'])) {
			$response = array(
				'error' => 'Input parameters needed to generate a mileage report are missing.'
			);
			return $response;
		}
		else {
			$reportid = trim($_POST['report_id']);
			$plugin_options = PwtcMileage::get_plugin_options();
			$error = null;
			$data = array();
			$meta = null;
			$state = null;
			switch ($reportid) {
				case "ytd_attendence":
				case "ly_attendence":
					if (!isset($_POST['sort'])) {
						$error = 'Input parameters needed to generate an attendence report are missing.';
					}
					else {
						$sort = $_POST['sort'];
						$state = array(
							'action' => 'pwtc_mileage_generate_report',
							'report_id' => $reportid,
							'sort' => $sort
						);	
						$sortby = 'date';
						if ($sort == 'title') {
							$sortby = 'title';
						}
						else if ($sort == 'riders') {
							$sortby = 'riders desc';
						}
						switch ($reportid) {			
							case "ytd_attendence":
								$meta = PwtcMileage_DB::meta_ytd_attendence();
								$data = PwtcMileage_DB::fetch_ytd_attendence(ARRAY_N, $sortby);
								break;
							case "ly_attendence":
								$meta = PwtcMileage_DB::meta_ly_attendence();
								$data = PwtcMileage_DB::fetch_ly_attendence(ARRAY_N, $sortby);
								break;
						}
					}
					break;
				case "ytd_miles":
				case "ly_miles":
				case "lt_miles":
					if (!isset($_POST['sort'])) {
						$error = 'Input parameters needed to generate a mileage report are missing.';
					}
					else {
						$showid = 'false';
						if (isset($_POST['showid'])) {
							$showid = trim($_POST['showid']);
						}
						$hide_id = true;
						if ($showid == 'true') {
							$hide_id = false;
						}					
						$sort = $_POST['sort'];
						$state = array(
							'action' => 'pwtc_mileage_generate_report',
							'report_id' => $reportid,
							'sort' => $sort,
							'showid' => $showid
						);						
						$sortby = 'mileage desc';
						if ($sort == 'name') {
							$sortby = 'last_name, first_name';
						}
						else if ($sort == 'rides' and $reportid != 'lt_miles') {
							$sortby = 'rides desc';
						}
						switch ($reportid) {			
							case "ytd_miles":
								$meta = PwtcMileage_DB::meta_ytd_miles($hide_id);
								$data = PwtcMileage_DB::fetch_ytd_miles(ARRAY_N, $sortby, 0, $hide_id);
								break;
							case "ly_miles":
								$meta = PwtcMileage_DB::meta_ly_miles($hide_id);
								$data = PwtcMileage_DB::fetch_ly_miles(ARRAY_N, $sortby, 0, $hide_id);
								break;
							case "lt_miles":
								$meta = PwtcMileage_DB::meta_lt_miles($hide_id);
								$data = PwtcMileage_DB::fetch_lt_miles(ARRAY_N, $sortby, 0, $hide_id);
								break;
						}
					}
					break;
				case "ytd_led":
				case "ly_led":
				case "pre_ly_led":
					if (!isset($_POST['sort'])) {
						$error = 'Input parameters needed to generate a mileage report are missing.';
					}
					else {
						$showid = 'false';
						if (isset($_POST['showid'])) {
							$showid = trim($_POST['showid']);
						}
						$hide_id = true;
						if ($showid == 'true') {
							$hide_id = false;
						}					
						$sort = $_POST['sort'];
						$state = array(
							'action' => 'pwtc_mileage_generate_report',
							'report_id' => $reportid,
							'sort' => $sort,
							'showid' => $showid							
						);						
						$sortby = 'rides_led desc';
						if ($sort == 'name') {
							$sortby = 'last_name, first_name';
						}
						switch ($reportid) {			
							case "ytd_led":
								$meta = PwtcMileage_DB::meta_ytd_led($hide_id);
								$data = PwtcMileage_DB::fetch_ytd_led(ARRAY_N, $sortby, 0, $hide_id);
								break;
							case "ly_led":
								$meta = PwtcMileage_DB::meta_ly_led(0, $hide_id);
								$data = PwtcMileage_DB::fetch_ly_led(ARRAY_N, $sortby, 0, false, $hide_id);
								break;
							case "pre_ly_led":
								$meta = PwtcMileage_DB::meta_pre_ly_led($hide_id);
								$data = PwtcMileage_DB::fetch_pre_ly_led(ARRAY_N, $sortby, 0, $hide_id);
								break;
						}
					}
					break;
				case "ytd_rides":
				case "ly_rides":
				case "ytd_rides_led":
				case "ly_rides_led":
					if (!isset($_POST['member_id']) or !isset($_POST['name'])) {
						$error = 'Input parameters needed to generate a mileage report are missing.';
					}
					else {
						$memberid = $_POST['member_id'];
						$name = $_POST['name'];
						$state = array(
							'action' => 'pwtc_mileage_generate_report',
							'report_id' => $reportid,
							'member_id' => $memberid,
							'name' => $name
						);						
						switch ($reportid) {			
							case "ytd_rides":
								$meta = PwtcMileage_DB::meta_ytd_rides($name);
								$data = PwtcMileage_DB::fetch_ytd_rides(ARRAY_N, $memberid);
								break;
							case "ly_rides":
								$meta = PwtcMileage_DB::meta_ly_rides($name);
								$data = PwtcMileage_DB::fetch_ly_rides(ARRAY_N, $memberid);
								break;
							case "ytd_rides_led":
								$meta = PwtcMileage_DB::meta_ytd_rides_led($name);
								$data = PwtcMileage_DB::fetch_ytd_rides_led(ARRAY_N, $memberid);
								break;
							case "ly_rides_led":
								$meta = PwtcMileage_DB::meta_ly_rides_led($name);
								$data = PwtcMileage_DB::fetch_ly_rides_led(ARRAY_N, $memberid);
								break;
						}
					}
					break;
				case "award_achvmnt":
				case "award_top_miles":
				case "award_members":
				case "award_leaders":
				case "award_members_500":
				case "award_leaders_12":
					$showid = 'false';
					if (isset($_POST['showid'])) {
						$showid = trim($_POST['showid']);
					}
					$hide_id = true;
					if ($showid == 'true') {
						$hide_id = false;
					}					
					$state = array(
						'action' => 'pwtc_mileage_generate_report',
						'report_id' => $reportid,
						'showid' => $showid							
					);						
					switch ($reportid) {
						case "award_achvmnt":
							$meta = PwtcMileage_DB::meta_ly_lt_achvmnt($hide_id);
							$data = PwtcMileage_DB::fetch_ly_lt_achvmnt(ARRAY_N, 'nachievement, last_name, first_name', $hide_id);
							break;
						case "award_top_miles":
							$meta = PwtcMileage_DB::meta_ly_miles($hide_id);
							$data = PwtcMileage_DB::fetch_ly_miles(ARRAY_N, 'mileage desc', 1, $hide_id);
							break;
						case "award_leaders":
							$meta = PwtcMileage_DB::meta_ly_led(0, $hide_id);
							$data = PwtcMileage_DB::fetch_ly_led(ARRAY_N, 'last_name, first_name', 0, true, $hide_id);
							break;
						case "award_members":
							$meta = PwtcMileage_DB::meta_annual_accum_miles(0, $hide_id);
							$data = PwtcMileage_DB::fetch_annual_accum_miles(ARRAY_N, 0, $hide_id);
							break;
						case "award_leaders_12":
							$meta = PwtcMileage_DB::meta_ly_led(12, $hide_id);
							$data = PwtcMileage_DB::fetch_ly_led(ARRAY_N, 'last_name, first_name', 12, true, $hide_id);
							break;
						case "award_members_500":
							$meta = PwtcMileage_DB::meta_annual_accum_miles(500, $hide_id);
							$data = PwtcMileage_DB::fetch_annual_accum_miles(ARRAY_N, 500, $hide_id);
							break;
					}			
					break;
				case "dup_members":
				case "riders_inactive":
				case "riders_w_mileage":
				case "riders_w_leaders":
					$state = array(
						'action' => 'pwtc_mileage_generate_report',
						'report_id' => $reportid
					);						
					switch ($reportid) {
						case "dup_members":
							$meta = PwtcMileage_DB::meta_member_duplicates();
							$data = PwtcMileage_DB::fetch_member_duplicates();
							break;
						case "riders_inactive":
							$meta = PwtcMileage_DB::meta_member_list('Inactive Riders');
							$data = PwtcMileage_DB::fetch_member_list(ARRAY_N, 0);
							break;
						case "riders_w_mileage":
							$meta = PwtcMileage_DB::meta_member_list('Riders With Mileage');
							$data = PwtcMileage_DB::fetch_member_list(ARRAY_N, 2);
							break;
						case "riders_w_leaders":
							$meta = PwtcMileage_DB::meta_member_list('Riders Leading Rides');
							$data = PwtcMileage_DB::fetch_member_list(ARRAY_N, 1);
							break;
					}
					break;
				default:
					$error = 'Report type ' . $reportid . ' not found.';
			}
			if (null === $error) {
				if ($meta['date_idx'] >= 0) {
					$i = $meta['date_idx'];
					foreach( $data as $key => $row ):
						$data[$key][$i] = date('D M j Y', strtotime($row[$i]));
					endforeach;					
				}
				$response = array(
					'report_id' => $reportid,
					'state' => $state,
					'title' => $meta['title'],
					'header' => $meta['header'],
					'width' => $meta['width'],
					'align' => $meta['align'],
					'data' => $data
				);
				return $response;			
			}
			else {
				$response = array(
					'report_id' => $reportid,
					'error' => $error
				);
				return $response;
			}
		}
	}

	public static function generate_report_callback() {
		echo wp_json_encode(self::generate_report());
		wp_die();
	}

	/*************************************************************/
	/* Admin menu and pages creation functions
	/*************************************************************/

	public static function plugin_menu() {
		$plugin_options = PwtcMileage::get_plugin_options();

    	$page_title = $plugin_options['plugin_menu_label'];
    	$menu_title = $plugin_options['plugin_menu_label'];
    	$capability = 'manage_options';
    	$parent_menu_slug = 'pwtc_mileage_menu';
    	$function = array( 'PwtcMileage_Admin', 'plugin_menu_page');
    	$icon_url = '';
    	$position = $plugin_options['plugin_menu_location'];
		add_menu_page($page_title, $menu_title, $capability, $parent_menu_slug, $function, $icon_url, $position);

		$page_title = $plugin_options['plugin_menu_label'] . ' - Create Ride Sheets';
    	$menu_title = 'Create Ride Sheets';
    	$menu_slug = 'pwtc_mileage_create_ride_sheets';
    	$capability = PwtcMileage::EDIT_MILEAGE_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_create_ride_sheets');
		add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

    	$page_title = $plugin_options['plugin_menu_label'] . ' - Manage Ride Sheets';
    	$menu_title = 'Manage Ride Sheets';
    	$menu_slug = 'pwtc_mileage_manage_ride_sheets';
    	$capability = PwtcMileage::EDIT_MILEAGE_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_manage_ride_sheets');
		add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

    	$page_title = $plugin_options['plugin_menu_label'] . ' - Manage Riders';
    	$menu_title = 'Manage Riders';
    	$menu_slug = 'pwtc_mileage_manage_riders';
    	$capability = PwtcMileage::EDIT_RIDERS_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_manage_riders');
		add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

    	$page_title = $plugin_options['plugin_menu_label'] . ' - View Reports';
    	$menu_title = 'View Reports';
    	$menu_slug = 'pwtc_mileage_generate_reports';
    	$capability = PwtcMileage::VIEW_MILEAGE_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_generate_reports');
		$page = add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		add_action('load-' . $page, array('PwtcMileage_Admin','download_report_pdf'));
		add_action('load-' . $page, array('PwtcMileage_Admin','download_report_csv'));
		add_action('load-' . $page, array('PwtcMileage_Admin','download_report_html'));
		add_action('load-' . $page, array('PwtcMileage_Admin','download_report_txt'));

    	$page_title = $plugin_options['plugin_menu_label'] . ' - Database Operations';
    	$menu_title = 'Database Ops';
    	$menu_slug = 'pwtc_mileage_manage_year_end';
    	$capability = PwtcMileage::DB_OPS_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_manage_year_end');
		$page = add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		add_action('load-' . $page, array('PwtcMileage_Admin','download_csv'));

		$page_title = $plugin_options['plugin_menu_label'] . ' - User Guide';
    	$menu_title = 'User Guide';
    	$menu_slug = 'pwtc_mileage_user_guide';
    	$capability = PwtcMileage::VIEW_MILEAGE_CAP;
    	$function = array( 'PwtcMileage_Admin', 'page_user_guide');
		$page = add_submenu_page($parent_menu_slug, $page_title, $menu_title, $capability, $menu_slug, $function);

		remove_submenu_page($parent_menu_slug, $parent_menu_slug);

		$page_title = $plugin_options['plugin_menu_label'] . ' - Settings';
    	$menu_title = $plugin_options['plugin_menu_label'];
    	$menu_slug = 'pwtc_mileage_settings';
    	$capability = 'manage_options';
    	$function = array( 'PwtcMileage_Admin', 'page_manage_settings');
		add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $menu_slug, $function);
	}

	public static function plugin_menu_page() {
	}

	public static function page_create_ride_sheets() {
		$plugin_options = PwtcMileage::get_plugin_options();
		$running_jobs = PwtcMileage_DB::num_running_jobs();
		$capability = PwtcMileage::EDIT_MILEAGE_CAP;
		$create_mode = true;
		include('admin-man-ridesheets.php');
	}

	public static function page_manage_ride_sheets() {
		$plugin_options = PwtcMileage::get_plugin_options();
		$running_jobs = PwtcMileage_DB::num_running_jobs();
		$capability = PwtcMileage::EDIT_MILEAGE_CAP;
		$create_mode = false;
		include('admin-man-ridesheets.php');
	}

	public static function page_generate_reports() {
		$plugin_options = PwtcMileage::get_plugin_options();
		$running_jobs = PwtcMileage_DB::num_running_jobs();
		$capability = PwtcMileage::VIEW_MILEAGE_CAP;
		include('admin-gen-reports.php');
	}

	public static function page_manage_riders() {
		$plugin_options = PwtcMileage::get_plugin_options();
		$running_jobs = PwtcMileage_DB::num_running_jobs();
		$capability = PwtcMileage::EDIT_RIDERS_CAP;
		include('admin-man-riders.php');
	}

	public static function write_export_pdf_file($pdf, $data, $header, $title, $width, $align) {
		$rows_per_page = 40;
		$table_width = 190;
		$tcell_width = $table_width/count($header);
		$tcell_align = 'C';
		$pdf->SetAutoPageBreak(false);
		$pdf->SetFont('Arial', '', 14);
		if (count($data) == 0) {
			$pdf->AddPage();
			$pdf->SetTextColor(0);
			$pdf->SetFont('','B');
			$pdf->Write(5, $title);
			$pdf->Ln();
			$pdf->Ln();
			$pdf->SetFont('','I');
			$pdf->Write(5, 'table is empty');
		}
		else {
			$row_count = 9999;
			$page_count = 0;
			$fill = false;
			foreach ( $data as $datum ) {
				if ($row_count > $rows_per_page) {
					if ($page_count > 0) {
						$pdf->Cell($table_width,0,'','T');
					}
					$pdf->AddPage();
					if ($page_count == 0) {
						$pdf->SetTextColor(0);
						$pdf->SetFont('','B');
						$pdf->Write(5, $title);
						$pdf->Ln();
						$pdf->Ln();
					}
					$pdf->SetFillColor(255,0,0);
					$pdf->SetTextColor(255);
					$pdf->SetDrawColor(128,0,0);
					$pdf->SetLineWidth(.3);
					$pdf->SetFont('','B');
					$col_count = 0;
					foreach ( $header as $item ) {
						if (count($width) > 0) {
							$tcell_width = $table_width*($width[$col_count]/100.0);
						}
						$pdf->Cell($tcell_width,7,$item,1,0,'C',true);
						$col_count++;
					}
					$pdf->Ln();
					$pdf->SetFillColor(224,235,255);
					$pdf->SetTextColor(0);
					$pdf->SetFont('');
					$row_count = 0;
					$page_count++;
					$fill = false;
				}
				$col_count = 0;
				foreach ( $datum as $col ) {
					if (count($width) > 0) {
						$tcell_width = $table_width*($width[$col_count]/100.0);
					}
					if (count($align) > 0) {
						$tcell_align = $align[$col_count];
					}
					$pdf->Cell($tcell_width,6,$col,'LR',0,$tcell_align,true);
					$col_count++;
				}
				$pdf->Ln();
				if ($fill) {
					$pdf->SetFillColor(224,235,255);
				}
				else {
					$pdf->SetFillColor(255,255,255);
				}
				$fill = !$fill;
				$row_count++;
			}
			$pdf->Cell($table_width,0,'','T');
		}
	}

	public static function write_export_html_file($fp, $data, $header, $title) {
		fwrite($fp, '<html><body><table>');
		fwrite($fp, '<caption>');
		fwrite($fp, $title);
		fwrite($fp, '<caption>');
		fwrite($fp, '<tr>');
		foreach ( $header as $item ) {
			fwrite($fp, '<th>');
			fwrite($fp, $item);
			fwrite($fp, '</th>');
		}
		fwrite($fp, '</tr>');
		foreach ( $data as $datum ) {
			fwrite($fp, '<tr>');
			foreach ( $datum as $col ) {
				fwrite($fp, '<td>');
				fwrite($fp, $col);
				fwrite($fp, '</td>');
			}
			fwrite($fp, '</tr>');
		}
		fwrite($fp, '</table></body></html>');
	}

	public static function write_export_tab_file($fp, $data, $header, $title) {
		fwrite($fp, $title);
		fwrite($fp, "\r\n");
		$firstpass = true;
		foreach ( $header as $item ) {
			if ($firstpass) {
				$firstpass = false;
			}
			else {
				fwrite($fp, "\t");
			}
			fwrite($fp, $item);
		}
		fwrite($fp, "\r\n");
		foreach ( $data as $datum ) {
			$firstpass = true;
			foreach ( $datum as $col ) {
				if ($firstpass) {
					$firstpass = false;
				}
				else {
					fwrite($fp, "\t");
				}
				fwrite($fp, $col);
			}
			fwrite($fp, "\r\n");
		}
	}

	public static function write_export_txt_file($fp, $data, $header, $title, $width) {
		fwrite($fp, $title);
		fwrite($fp, "\r\n");
		$col_count = 0;
		foreach ( $header as $item ) {
			$tcell_width = 75*($width[$col_count]/100.0);
			fwrite($fp, $item);
			fwrite($fp, self::create_string_pad(strlen($item), $tcell_width));
			$col_count++;
		}
		fwrite($fp, "\r\n");
		foreach ( $data as $datum ) {
			$col_count = 0;
			foreach ( $datum as $col ) {
				$tcell_width = 75*($width[$col_count]/100.0);
				fwrite($fp, $col);
				fwrite($fp, self::create_string_pad(strlen($col), $tcell_width));
				$col_count++;
			}
			fwrite($fp, "\r\n");
		}
	}

	public static function create_string_pad($len, $total) {
		$pad = '';
		if ($len >= $total) {
			$pad .= ' ';
		}
		else {
			$count = $total - $len;
			for ($i = 0; $i < $count; $i++) {
				$pad .= ' ';
			}
		}
		return $pad;
	}

	public static function download_report_csv() {
		if (current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
			if (isset($_POST['export_csv'])) {
				$response = self::generate_report();
				if (isset($response['error'])) {
					header('Content-Description: File Transfer');
					header("Content-type: text/txt");
					header("Content-Disposition: attachment; filename=error.txt");
					echo $response['error'];
				}
				else {
					$today = date('Y-m-d', current_time('timestamp'));
					$report_id = $response['report_id'];
					header('Content-Description: File Transfer');
					header("Content-type: text/csv");
					header("Content-Disposition: attachment; filename={$today}_{$report_id}.csv");
					$fh = fopen('php://output', 'w');
					PwtcMileage::write_export_csv_file($fh, $response['data'], $response['header']);
					fclose($fh);
				}
				die;
			}
		}
	}

	public static function download_report_pdf() {
		if (current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
			if (isset($_POST['export_pdf'])) {
				$response = self::generate_report();
				if (isset($response['error'])) {
					header('Content-Description: File Transfer');
					header("Content-type: text/txt");
					header("Content-Disposition: attachment; filename=error.txt");
					echo $response['error'];
				}
				else {
					$today = date('Y-m-d', current_time('timestamp'));
					$report_id = $response['report_id'];
					header('Content-Description: File Transfer');
					header("Content-type: application/pdf");
					header("Content-Disposition: attachment; filename={$today}_{$report_id}.pdf");
					require('fpdf.php');	
					$pdf = new FPDF();
					self::write_export_pdf_file($pdf, $response['data'], $response['header'], 
						$response['title'], $response['width'], $response['align']);
					//$pdf->Output();
					$pdf->Output('F', 'php://output');
				}
				die;
			}
		}
	}

	public static function download_report_html() {
		if (current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
			if (isset($_POST['export_html'])) {
				$response = self::generate_report();
				if (isset($response['error'])) {
					header('Content-Description: File Transfer');
					header("Content-type: text/txt");
					header("Content-Disposition: attachment; filename=error.txt");
					echo $response['error'];
				}
				else {
					$today = date('Y-m-d', current_time('timestamp'));
					$report_id = $response['report_id'];
					header('Content-Description: File Transfer');
					header("Content-type: text/html");
					header("Content-Disposition: attachment; filename={$today}_{$report_id}.html");
					$fh = fopen('php://output', 'w');
					self::write_export_html_file($fh, $response['data'], $response['header'], $response['title']);
					fclose($fh);
				}
				die;
			}
		}
	}

	public static function download_report_txt() {
		if (current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
			if (isset($_POST['export_txt'])) {
				$response = self::generate_report();
				if (isset($response['error'])) {
					header('Content-Description: File Transfer');
					header("Content-type: text/txt");
					header("Content-Disposition: attachment; filename=error.txt");
					echo $response['error'];
				}
				else {
					$today = date('Y-m-d', current_time('timestamp'));
					$report_id = $response['report_id'];
					header('Content-Description: File Transfer');
					header("Content-type: text/txt");
					header("Content-Disposition: attachment; filename={$today}_{$report_id}.txt");
					$fh = fopen('php://output', 'w');
					self::write_export_tab_file($fh, $response['data'], $response['header'], $response['title']);
					fclose($fh);
				}
				die;
			}
		}
	}

	public static function download_csv() {
		if (current_user_can(PwtcMileage::DB_OPS_CAP)) {
			if (isset($_POST['export_members'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_export')) {
					die('Nonce security check failed!'); 
				}			
				$today = date('Y-m-d', current_time('timestamp'));
				header('Content-Description: File Transfer');
				header("Content-type: text/csv");
				header("Content-Disposition: attachment; filename={$today}_members.csv");
				$fh = fopen('php://output', 'w');
				PwtcMileage::write_export_csv_file($fh, PwtcMileage_DB::fetch_members_for_export());
				fclose($fh);
				die;
			}
			else if (isset($_POST['export_rides'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_export')) {
					die('Nonce security check failed!'); 
				}			
				$today = date('Y-m-d', current_time('timestamp'));
				header('Content-Description: File Transfer');
				header("Content-type: text/csv");
				header("Content-Disposition: attachment; filename={$today}_rides.csv");
				$fh = fopen('php://output', 'w');
				PwtcMileage::write_export_csv_file($fh, PwtcMileage_DB::fetch_rides_for_export());
				fclose($fh);
				die;
			}
			else if (isset($_POST['export_mileage'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_export')) {
					die('Nonce security check failed!'); 
				}			
				$today = date('Y-m-d', current_time('timestamp'));
				header('Content-Description: File Transfer');
				header("Content-type: text/csv");
				header("Content-Disposition: attachment; filename={$today}_mileage.csv");
				$fh = fopen('php://output', 'w');
				PwtcMileage::write_export_csv_file($fh, PwtcMileage_DB::fetch_mileage_for_export());
				fclose($fh);
				die;
			}
			else if (isset($_POST['export_leaders'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_export')) {
					die('Nonce security check failed!'); 
				}			
				$today = date('Y-m-d', current_time('timestamp'));
				header('Content-Description: File Transfer');
				header("Content-type: text/csv");
				header("Content-Disposition: attachment; filename={$today}_leaders.csv");
				$fh = fopen('php://output', 'w');
				PwtcMileage::write_export_csv_file($fh, PwtcMileage_DB::fetch_leaders_for_export());
				fclose($fh);
				die;
			}
		}
	}

	public static function page_manage_year_end() {
		if (current_user_can(PwtcMileage::DB_OPS_CAP)) {
			$use_cron = false;
			$plugin_options = PwtcMileage::get_plugin_options();
			if (isset($_POST['consolidate'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_consolidate')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_set_status(PwtcMileage::RIDE_MERGE_ACT, PwtcMileage_DB::TRIGGERED_STATUS);
				if ($use_cron) {
					wp_schedule_single_event(time(), 'pwtc_mileage_consolidation');
				}
				else {
					do_action('pwtc_mileage_consolidation');
				}
			}
			else if (isset($_POST['member_sync'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_member_sync')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_set_status(PwtcMileage::MEMBER_SYNC_ACT, PwtcMileage_DB::TRIGGERED_STATUS);
				if ($use_cron) {
					wp_schedule_single_event(time(), 'pwtc_mileage_member_sync');
				}
				else {
					do_action('pwtc_mileage_member_sync');
				}
			}
			else if (isset($_POST['purge_nonriders'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_purge_nonriders')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_set_status(PwtcMileage::RIDER_PURGE_ACT, PwtcMileage_DB::TRIGGERED_STATUS);
				if ($use_cron) {
					wp_schedule_single_event(time(), 'pwtc_mileage_purge_nonriders');
				}
				else {
					do_action('pwtc_mileage_purge_nonriders');
				}
			}
			else if (isset($_POST['restore'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_restore')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_set_status(PwtcMileage::DB_RESTORE_ACT, PwtcMileage_DB::TRIGGERED_STATUS);
				$files = array(
					self::generate_file_record(
						'members_file', 'members', '_members', PwtcMileage_DB::MEMBER_TABLE),
					self::generate_file_record(
						'rides_file', 'rides', '_rides', PwtcMileage_DB::RIDE_TABLE),
					self::generate_file_record(
						'mileage_file', 'mileage', '_mileage', PwtcMileage_DB::MILEAGE_TABLE),
					self::generate_file_record(
						'leaders_file', 'leaders', '_leaders', PwtcMileage_DB::LEADER_TABLE)
				);
				$error = self::validate_uploaded_files($files);
				if ($error) {
					PwtcMileage_DB::job_set_status(PwtcMileage::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, $error);
				}
				else {
					$error = self::move_uploaded_files($files);
					if ($error) {
						PwtcMileage_DB::job_set_status(PwtcMileage::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, $error);
					}
					else {
						if ($use_cron) {
							wp_schedule_single_event(time(), 'pwtc_mileage_cvs_restore');
						}
						else {
							do_action('pwtc_mileage_cvs_restore');
						}
					}
				}
			}
			else if (isset($_POST['updmembs'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_updmembs')) {
					wp_die('Nonce security check failed!'); 
				}	
				PwtcMileage_DB::job_set_status(PwtcMileage::MEMBER_SYNC_ACT, PwtcMileage_DB::TRIGGERED_STATUS);
				$error = self::validate_uploaded_dbf_file();
				if ($error) {
					PwtcMileage_DB::job_set_status(PwtcMileage::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, $error);
				}
				else {
					$error = self::move_uploaded_dbf_file();
					if ($error) {
						PwtcMileage_DB::job_set_status(PwtcMileage::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, $error);
					}
					else {
						if ($use_cron) {
							wp_schedule_single_event(time(), 'pwtc_mileage_updmembs_load');
						}
						else {
							do_action('pwtc_mileage_updmembs_load');
						}
					}
				}
			}		
			else if (isset($_POST['clear_errs'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_clear_errs')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_remove_failed();
				PwtcMileage_DB::job_remove_success();
			}
			else if (isset($_POST['clear_lock'])) {
				if (!isset($_POST['_wpnonce']) or
					!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_clear_lock')) {
					wp_die('Nonce security check failed!'); 
				}			
				PwtcMileage_DB::job_remove_running();
			}

			$job_status = PwtcMileage_DB::job_get_all_status();

			$max_timestamp = PwtcMileage_DB::max_job_timestamp();
			$show_clear_lock = false;
			if ($max_timestamp !== null && time()-$max_timestamp > $plugin_options['db_lock_time_limit']) {
				$show_clear_lock = true;
			}
			$thisyear = date('Y', current_time('timestamp'));
			$yearbeforelast = intval($thisyear) - 2;
			$maxdate = '' . $yearbeforelast . '-12-31';
			$rides_to_consolidate = PwtcMileage_DB::get_num_rides_before_date($maxdate);

			$member_count = PwtcMileage_DB::count_members();
			$ride_count = PwtcMileage_DB::count_rides();
			$mileage_count = PwtcMileage_DB::count_mileage();
			$leader_count = PwtcMileage_DB::count_leaders();
			$wordpress_timestamp = date('Y-m-d H:i:s', current_time('timestamp'));
			$database_timestamp = PwtcMileage_DB::fetch_current_time();
			$database_version = PwtcMileage_DB::get_db_version();
			if ($database_version == false) {
				$database_version = '1.1';
			}

			$capability = PwtcMileage::DB_OPS_CAP;

			include('admin-man-yearend.php');
		}	
	}

	public static function page_user_guide() {
		$capability = PwtcMileage::VIEW_MILEAGE_CAP;
		$plugin_options = PwtcMileage::get_plugin_options();
		include('admin-user-guide.php');
	}

	public static function generate_file_record($id, $label, $suffix, $tblname) {
		return array(
			'id' => $id,
			'label' => $label,
			'pattern' => '/' . '\d{4}-\d{2}-\d{2}' . $suffix . '.*\.csv' . '/',
			'tblname' => $tblname
		);
	}

	public static function validate_uploaded_files($files) {
		$errmsg = null;
		if (empty($_FILES)) {
			$errmsg = 'Input parameters needed to upload files are missing';
		}
		else {	
			foreach ( $files as $file ) {
				if (!isset($_FILES[$file['id']])) {
					$errmsg = $file['id'] . ' input parameter needed to upload file is missing';
					break;
				}
				else if ($_FILES[$file['id']]['size'] == 0) {
					$errmsg = $file['label'] . ' file empty or not selected';
					break;
				}
				else if ($_FILES[$file['id']]['error'] != UPLOAD_ERR_OK) {
					$errmsg = $file['label'] . ' file upload error code ' . $_FILES[$file]['error'];
					break;
				}
				else if (preg_match($file['pattern'], $_FILES[$file['id']]['name']) !== 1) {
					$errmsg = $file['label'] . ' file name pattern mismatch';
					break;
				}
			}
		}

		return $errmsg;
	}

	public static function move_uploaded_files($files) {
		$errmsg = null;
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/pwtc_mileage';
		if (!file_exists($plugin_upload_dir)) {
    		wp_mkdir_p($plugin_upload_dir);
		}
		foreach ( $files as $file ) {
			$uploadfile = $plugin_upload_dir . '/' . $file['tblname'] . '.csv';
			if (!move_uploaded_file($_FILES[$file['id']]['tmp_name'], $uploadfile)) {
				$errmsg = $file['label'] . ' file upload could not be moved';
				break;
			}
		}
		return $errmsg;
	}

	public static function validate_uploaded_dbf_file() {
		$errmsg = null;
		if (empty($_FILES)) {
			$errmsg = 'Input parameters needed to upload files are missing';
		}
		else {	
			if (!isset($_FILES['updmembs_file'])) {
				$errmsg = 'Updmembs input parameter needed to upload file is missing';
			}
			else if ($_FILES['updmembs_file']['size'] == 0) {
				$errmsg = 'Updmembs file empty or not selected';
			}
			else if ($_FILES['updmembs_file']['error'] != UPLOAD_ERR_OK) {
				$errmsg = 'Updmembs file upload error code ' . $_FILES['updmembs_file']['error'];
			}
		}
		return $errmsg;
	}

	public static function move_uploaded_dbf_file() {
		$errmsg = null;
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/pwtc_mileage';
		if (!file_exists($plugin_upload_dir)) {
    		wp_mkdir_p($plugin_upload_dir);
		}
		$uploadfile = $plugin_upload_dir . '/updmembs.dbf';
		if (!move_uploaded_file($_FILES['updmembs_file']['tmp_name'], $uploadfile)) {
			$errmsg = 'Updmembs file upload could not be moved';
		}
		return $errmsg;
	}

	public static function page_manage_settings() {
		if (isset($_POST['_wpnonce'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'], 'pwtc_mileage_settings')) {
     			die('Nonce security check failed!'); 
			}			
		}
		$plugin_options = PwtcMileage::get_plugin_options();
		$form_submitted = false;
		$error_msgs = array();
    	if (isset($_POST['plugin_menu_label'])) {
			$form_submitted = true;
			$entry = sanitize_text_field($_POST['plugin_menu_label']);
			if (!PwtcMileage::validate_label_str($entry)) {
				array_push($error_msgs,
					'Plugin Menu Label field must contain a valid string.');
			}
			else {
				$plugin_options['plugin_menu_label'] = $entry;
			}
    	} 
    	if (isset($_POST['plugin_menu_location'])) {
			$form_submitted = true;
			$entry = sanitize_text_field($_POST['plugin_menu_location']);
			if (!PwtcMileage::validate_number_str($entry)) {
				array_push($error_msgs,
					'Plugin Menu Location field must contain a non-negative number.');
			}
			else {
				$plugin_options['plugin_menu_location'] = intval($entry);
			}
    	} 
    	if (isset($_POST['db_lock_time_limit'])) {
			$form_submitted = true;
			$entry = sanitize_text_field($_POST['db_lock_time_limit']);
			if (!PwtcMileage::validate_number_str($entry)) {
				array_push($error_msgs,
					'DB Batch Job Lock Time Limit field must contain a non-negative number.');
			}
			else {
				$plugin_options['db_lock_time_limit'] = intval($entry);
			}
    	} 
    	if (isset($_POST['expire_grace_period'])) {
			$form_submitted = true;
			$entry = sanitize_text_field($_POST['expire_grace_period']);
			if (!PwtcMileage::validate_number_str($entry)) {
				array_push($error_msgs,
					'Expiration Grace Period field must contain a non-negative number.');
			}
			else {
				$plugin_options['expire_grace_period'] = intval($entry);
			}
    	} 
    	if (isset($_POST['ride_lookback_date'])) {
			$form_submitted = true;
			$entry = sanitize_text_field($_POST['ride_lookback_date']);
			if ($entry != '' and !PwtcMileage::validate_date_str($entry)) {
				array_push($error_msgs,
					'Posted Ride Maximum Lookback Date field must contain a valid date.');
			}
			else {
				$plugin_options['ride_lookback_date'] = $entry;
			}
    	} 
    	if (isset($_POST['user_lookup_mode'])) {
			$form_submitted = true;
			$plugin_options['user_lookup_mode'] = $_POST['user_lookup_mode'];
		}
		if ($form_submitted) {
			if (isset($_POST['admin_maint_mode'])) {
				$plugin_options['admin_maint_mode'] = true;
			}
			else {
				$plugin_options['admin_maint_mode'] = false;
			}
			if (isset($_POST['drop_db_on_delete'])) {
				$plugin_options['drop_db_on_delete'] = true;
			}
			else {
				$plugin_options['drop_db_on_delete'] = false;
			}
			if (isset($_POST['disable_expir_check'])) {
				$plugin_options['disable_expir_check'] = true;
			}
			else {
				$plugin_options['disable_expir_check'] = false;
			}
			if (isset($_POST['disable_delete_confirm'])) {
				$plugin_options['disable_delete_confirm'] = true;
			}
			else {
				$plugin_options['disable_delete_confirm'] = false;
			}
			if (isset($_POST['show_ride_ids'])) {
				$plugin_options['show_ride_ids'] = true;
			}
			else {
				$plugin_options['show_ride_ids'] = false;
			}
			PwtcMileage::update_plugin_options($plugin_options);
			$plugin_options = PwtcMileage::get_plugin_options();			
		}
		$capability = 'manage_options';
		include('admin-man-settings.php');
	}

}