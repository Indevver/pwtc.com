<?php

class PwtcMileage {

	const VIEW_MILEAGE_CAP = 'pwtc_view_mileage';
	const EDIT_MILEAGE_CAP = 'pwtc_edit_mileage';
	const EDIT_RIDERS_CAP = 'pwtc_edit_riders';
	const DB_OPS_CAP = 'pwtc_mileage_db_ops';

	const MEMBER_SYNC_ACT = 'Synchronize';
	const RIDE_MERGE_ACT = 'Consolidate';
	const DB_RESTORE_ACT = 'Restore';
	const RIDER_PURGE_ACT = 'Purge';

    private static $initiated = false;

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	// Initializes plugin WordPress hooks.
	private static function init_hooks() {
		self::$initiated = true;

		add_action( 'template_redirect', 
			array( 'PwtcMileage', 'download_riderid' ) );

		// Register script and style enqueue callbacks
		add_action( 'wp_enqueue_scripts', 
			array( 'PwtcMileage', 'load_report_scripts' ) );

		// Register shortcode callbacks
		add_shortcode('pwtc_rider_report', 
			array( 'PwtcMileage', 'shortcode_rider_report'));
		add_shortcode('pwtc_attendence_year_to_date', 
			array( 'PwtcMileage', 'shortcode_ytd_attendence'));
		add_shortcode('pwtc_attendence_last_year', 
			array( 'PwtcMileage', 'shortcode_ly_attendence'));
		add_shortcode('pwtc_mileage_year_to_date', 
			array( 'PwtcMileage', 'shortcode_ytd_miles'));
		add_shortcode('pwtc_mileage_last_year', 
			array( 'PwtcMileage', 'shortcode_ly_miles'));
		add_shortcode('pwtc_mileage_lifetime', 
			array( 'PwtcMileage', 'shortcode_lt_miles'));
		add_shortcode('pwtc_rides_led_year_to_date', 
			array( 'PwtcMileage', 'shortcode_ytd_led'));
		add_shortcode('pwtc_rides_led_last_year', 
			array( 'PwtcMileage', 'shortcode_ly_led'));
		add_shortcode('pwtc_rides_year_to_date', 
			array( 'PwtcMileage', 'shortcode_ytd_rides'));
		add_shortcode('pwtc_rides_last_year', 
			array( 'PwtcMileage', 'shortcode_ly_rides'));
		add_shortcode('pwtc_led_rides_year_to_date', 
			array( 'PwtcMileage', 'shortcode_ytd_led_rides'));
		add_shortcode('pwtc_led_rides_last_year', 
			array( 'PwtcMileage', 'shortcode_ly_led_rides'));
		add_shortcode('pwtc_posted_rides_wo_sheets', 
			array( 'PwtcMileage', 'shortcode_rides_wo_sheets'));
		add_shortcode('pwtc_riderid_download', 
			array( 'PwtcMileage', 'shortcode_riderid_download'));

		// Register background action task callbacks 
		add_action( 'pwtc_mileage_consolidation', 
			array( 'PwtcMileage', 'consolidation_callback') );  
		add_action( 'pwtc_mileage_member_sync', 
			array( 'PwtcMileage', 'member_sync_callback') );  
		add_action( 'pwtc_mileage_purge_nonriders', 
			array( 'PwtcMileage', 'purge_nonriders_callback') );  
		add_action( 'pwtc_mileage_cvs_restore', 
			array( 'PwtcMileage', 'cvs_restore_callback') );  
		add_action( 'pwtc_mileage_updmembs_load', 
			array( 'PwtcMileage', 'updmembs_load_callback2') ); 
		
		$plugin_options = self::get_plugin_options();
		$mode = $plugin_options['user_lookup_mode'];
		if ($mode == 'woocommerce') {
			add_action('wc_memberships_user_membership_saved', 
				array('PwtcMileage', 'membership_created_callback'), 10, 2);
			add_action('wc_memberships_user_membership_created', 
				array('PwtcMileage', 'membership_created_callback'), 10, 2);
			add_action('wc_memberships_csv_import_user_membership', 
				array('PwtcMileage', 'membership_updated_callback'));
			add_action('wc_memberships_user_membership_deleted', 
				array('PwtcMileage', 'membership_deleted_callback'));
			add_action('wc_memberships_for_teams_add_team_member', 
				array('PwtcMileage', 'adjust_team_member_data_callback' ), 10, 3);
			add_action('wc_memberships_for_teams_team_created', 
				array('PwtcMileage', 'adjust_team_members_data_callback' ));
			add_action('woocommerce_account_dashboard',
				array('PwtcMileage', 'add_card_download_callback'));
		}

	}

	public static function membership_created_callback($membership_plan, $args = array()) {
		$update_rider = true;
		$log_updates = false;
		$user_membership_id = isset($args['user_membership_id']) ? absint($args['user_membership_id']) : null;
		$user_id = isset($args['user_id']) ? absint($args['user_id']) : null;

		if (!$user_membership_id) {
			return;
		}
		if (!$user_id) {
			return;
		}

		$user_membership = wc_memberships_get_user_membership($user_membership_id);
		if (!$user_membership) {
			return;			
		}
		
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		if ($user_membership->get_status() == 'auto-draft' or $user_membership->get_status() == 'trash') {
			return;
		}

		$expdate = pwtc_mileage_get_expiration_date($user_membership);

		$rider_id = get_field('rider_id', 'user_'.$user_id);
		if ($rider_id) {
			$rider_id = trim($rider_id);
		}
		else {
			$rider_id = '';
		}
		if (empty($rider_id)) {
			try {
				$new_rider_id = pwtc_mileage_insert_new_rider(
					$user_data->last_name, $user_data->first_name, $expdate);
				update_field('rider_id', $new_rider_id, 'user_'.$user_id);
				$user_membership->add_note('PWTC Mileage plugin assigned new Rider ID ' . $new_rider_id . ' to this member.');
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
				$user_membership->add_note('PWTC Mileage plugin error assigning new Rider ID to this member: ' . $msg);
			}
		}
		else if ($update_rider) {
			try {
				pwtc_mileage_update_rider(
					$rider_id, $user_data->last_name, $user_data->first_name, $expdate);
				if ($log_updates) {
					$user_membership->add_note('PWTC Mileage plugin updated information for Rider ID ' . $rider_id);
				}
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
				$user_membership->add_note('PWTC Mileage plugin error updating information for Rider ID ' . $rider_id . ': ' . $msg);
			}
		}
	}

	public static function membership_updated_callback($user_membership) {
		$update_rider = true;
		$log_updates = false;
		$user_id = $user_membership->get_user_id();
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		if ($user_membership->get_status() == 'auto-draft' or $user_membership->get_status() == 'trash') {
			return;
		}

		if ($update_rider) {
			$rider_id = get_field('rider_id', 'user_'.$user_id);
			if ($rider_id) {
				$rider_id = trim($rider_id);
			}
			else {
				$rider_id = '';
			}
			if (!empty($rider_id)) {
				$expdate = pwtc_mileage_get_expiration_date($user_membership);
				try {
					pwtc_mileage_update_rider(
						$rider_id, $user_data->last_name, $user_data->first_name, $expdate);
					if ($log_updates) {
						$user_membership->add_note('PWTC Mileage plugin updated information for Rider ID ' . $rider_id);
					}
				}
				catch (Exception $e) {
					$msg = $e->getMessage();
					$user_membership->add_note('PWTC Mileage plugin error updating information for Rider ID ' . $rider_id . ': ' . $msg);
				}
			}
		}
	}

	public static function membership_deleted_callback($user_membership) {
		$update_rider = false;
		$user_id = $user_membership->get_user_id();
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		if ($update_rider) {
			$rider_id = get_field('rider_id', 'user_'.$user_id);
			if ($rider_id) {
				$rider_id = trim($rider_id);
			}
			else {
				$rider_id = '';
			}
			if (!empty($rider_id)) {
				$expdate = date('Y-m-d', current_time('timestamp'));
				try {
					pwtc_mileage_update_rider(
						$rider_id, $user_data->last_name, $user_data->first_name, $expdate);
				}
				catch (Exception $e) {
					$msg = $e->getMessage();
					pwtc_mileage_write_log('membership_deleted_callback: ' . $msg);
				}
			}
		}
	}

	public static function adjust_team_member_data_callback($team_member, $team, $user_membership) {
		$update_rider = true;
		$log_updates = false;
		$datetime = $team->get_local_membership_end_date('mysql');
		if ($datetime) {
			$pieces = explode(' ', $datetime);
			$team_end_date = $pieces[0];
		}
		else {
			$team_end_date = '2099-01-01';
		}
		$user_id = $user_membership->get_user_id();
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}
		$rider_id = get_field('rider_id', 'user_'.$user_id);
		if ($rider_id) {
			$rider_id = trim($rider_id);
		}
		else {
			$rider_id = '';
		}
		if (empty($rider_id)) {
			try {
				$new_rider_id = pwtc_mileage_insert_new_rider(
					$user_data->last_name, $user_data->first_name, $team_end_date);
				update_field('rider_id', $new_rider_id, 'user_'.$user_id);
				$user_membership->add_note('PWTC Mileage plugin assigned new Rider ID ' . $new_rider_id . ' to this member.');
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
				$user_membership->add_note('PWTC Mileage plugin error assigning new Rider ID to this member: ' . $msg);
			}
		}
		else if ($update_rider) {
			try {
				pwtc_mileage_update_rider(
					$rider_id, $user_data->last_name, $user_data->first_name, $team_end_date);
				if ($log_updates) {
					$user_membership->add_note('PWTC Mileage plugin updated information for Rider ID ' . $rider_id);
				}
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
				$user_membership->add_note('PWTC Mileage plugin error updating information for Rider ID ' . $rider_id . ': ' . $msg);
			}
		}	
	}

	public static function adjust_team_members_data_callback($team) {
		$user_memberships = $team->get_user_memberships();
		foreach ( $user_memberships as $user_membership ) {
			self::adjust_team_member_data_callback(false, $team, $user_membership);
		}	
	}

	public static function add_card_download_callback() {
		echo '<p>Click the button below to download your rider ID card.';
		echo do_shortcode('[pwtc_riderid_download]');
		echo '</p>';
	}

	public static function download_riderid() {
		if (isset($_POST['pwtc_mileage_download_riderid']) and isset($_POST['rider_id']) and isset($_POST['user_id'])) {
			$current_user = wp_get_current_user();
			if ( 0 == $current_user->ID ) {
			}
			else {	
				$result = pwtc_mileage_get_rider_card_info(intval($_POST['user_id']), $_POST['rider_id']);
				if ($result === false) {
				}
				else {
					$lastname = $result['last_name'];
					$firstname = $result['first_name'];
					$name = $firstname . ' ' . $lastname;
					$exp_date = $result['expir_date'];
					$family_id = $result['family_id'];
					$fmtdate = date('M Y', strtotime($exp_date));
					header('Content-Description: File Transfer');
					header("Content-type: application/pdf");
					header("Content-Disposition: attachment; filename=rider_card.pdf");
					require('fpdf.php');	
					$pdf = new FPDF();
					$pdf->AddPage();
					$x_off = 0;
					$y_off = 0;
					$w_card = 95;
					$h_card = 60;
					$pdf->Rect($x_off, $y_off, $w_card, $h_card);
					$w_sub = (int)($w_card * 0.3);
					$pdf->Rect($x_off, $y_off, $w_sub, $h_card);
					$pdf->Image(PWTC_MILEAGE__PLUGIN_DIR . 'pbc_logo.png', $x_off + 1, $y_off + 10, $w_sub - 2, $w_sub - 2);
					$pdf->SetXY($x_off, $y_off + 38);
					$pdf->SetFont('Arial', '', 12);
					$pdf->MultiCell($w_sub, 5, 'Portland Bicycling Club', 0, 'C');
					$pdf->SetXY($x_off + $w_sub, $y_off + 8);
					$pdf->SetFont('Arial', 'I', 18);
					$pdf->MultiCell($w_card - $w_sub, 10, $name, 0,'C');
					$pdf->SetFont('Arial', '', 14);
					$pdf->Text($x_off + $w_sub + 25, $y_off + 34, $_POST['rider_id']);
					$pdf->Text($x_off + $w_sub + 40, $y_off + 50, $fmtdate);
					$pdf->SetFont('Arial', '', 5);
					$pdf->Text($x_off + $w_sub + 25, $y_off + 38, 'RIDER ID');
					$pdf->Text($x_off + $w_sub + 40, $y_off + 54, 'EXPIRES');
					if (!empty($family_id)) {
						$pdf->Text($x_off + $w_sub + 5, $y_off + 50, $family_id);
						$pdf->Text($x_off + $w_sub + 5, $y_off + 54, 'FAMILY ID');
					}
					$pdf->Rect($x_off, $y_off + $h_card, $w_card, $h_card);
					$pdf->SetXY($x_off, $y_off + $h_card + 5);
					$pdf->SetFont('Arial', 'I', 12);
					$pdf->SetTextColor(255, 0, 0);
					$pdf->Cell($w_card, 6, 'Portland Bicycling Club', 0, 2,'C');
					$pdf->SetFont('Arial', 'U', 12);
					$pdf->SetTextColor(0, 0, 255);
					$pdf->Cell($w_card, 6, 'PortlandBicyclingClub.com', 0, 2,'C');
					$pdf->SetFont('Arial', '', 12);
					$pdf->SetTextColor(0, 0, 0);
					$pdf->Cell($w_card, 6, 'Information Hotline: 503.666.5796', 0, 2,'C');
					$pdf->SetXY($x_off, $y_off + $h_card + 27);
					$pdf->SetFont('Arial', '', 8);
					$pdf->Cell($w_card, 4, 'Daily and multi-day rides', 0, 2,'C');
					$pdf->Cell($w_card, 4, 'Cycling friendships', 0, 2,'C');
					$pdf->Cell($w_card, 4, 'Volunteer opportunities', 0, 2,'C');
					$pdf->Cell($w_card, 4, 'Bike shop discounts', 0, 2,'C');
					$pdf->SetXY($x_off, $y_off + $h_card + 50);
					$pdf->SetFont('Arial', 'I', 12);
					$pdf->SetTextColor(255, 0, 0);
					$pdf->Cell($w_card, 6, 'Take Life By The Handlebars! ' . chr(174), 0, 0,'C');
					$pdf->SetFont('Arial', 'I', 10);
					$pdf->SetTextColor(0, 0, 0);
					$pdf->SetXY($x_off, $y_off + $h_card*2);
					$pdf->Cell($w_card, 10, 'To assemble card, cut out and fold', 0, 0,'C');
					$pdf->Output('F', 'php://output');
					die;
				}
			}
		}
	}

	/*************************************************************/
	/* Script and style enqueue callback functions
	/*************************************************************/

	public static function load_report_scripts() {
		wp_enqueue_style('pwtc_mileage_report_css', 
			PWTC_MILEAGE__PLUGIN_URL . 'reports-style.css', array(),
			filemtime(PWTC_MILEAGE__PLUGIN_DIR . 'reports-style.css'));
	}

	/*************************************************************/
	/* Background action task callbacks
	/*************************************************************/

	public static function consolidation_callback() {
		PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT, PwtcMileage_DB::STARTED_STATUS);

		$thisyear = date('Y', current_time('timestamp'));
		$yearbeforelast = intval($thisyear) - 2;
		$title = '[Totals Through ' . $yearbeforelast . ']';
		$maxdate = '' . $yearbeforelast . '-12-31';

		$num_rides = PwtcMileage_DB::get_num_rides_before_date($maxdate);	
		if ($num_rides == 0) {
			PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT, PwtcMileage_DB::FAILED_STATUS, 
				'no ridesheets were found for ' . $yearbeforelast);
		}
		else if ($num_rides == 1) {
			PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT, PwtcMileage_DB::FAILED_STATUS, 
				'' . $yearbeforelast . ' ridesheets are already consolidated');
		}
		else {
			$status = PwtcMileage_DB::insert_ride($title, $maxdate);
			if (false === $status or 0 === $status) {
				PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT,PwtcMileage_DB::FAILED_STATUS, 'could not insert new ridesheet, mileage database may be corrupted. Contact administrator.');
			}
			else {
				$rideid = PwtcMileage_DB::get_new_ride_id();
				if (isset($rideid) and is_int($rideid)) {
					$status = PwtcMileage_DB::rollup_ridesheets($rideid, $maxdate);
					if (isset($status['error'])) {
						PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT,PwtcMileage_DB::FAILED_STATUS, $status['error'] . ', mileage database may be corrupted. Contact administrator.');
					}
					else {
						PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT, PwtcMileage_DB::SUCCESS_STATUS, 
							$status['m_inserts'] . ' mileages inserted, ' . 
							$status['m_deletes'] . ' mileages deleted, ' . 
							$status['l_inserts'] . ' leaders inserted, ' . 
							$status['l_deletes'] . ' leaders deleted, ' . 
							'1 ridesheet inserted, ' . 
							$status['r_deletes'] . ' ridesheets deleted');
					}
				}
				else {
					PwtcMileage_DB::job_set_status(self::RIDE_MERGE_ACT, PwtcMileage_DB::FAILED_STATUS, 'new ridesheet ID is invalid, mileage database may be corrupted. Contact administrator.');
				}
			}
		}	
	}

	public static function member_sync_callback() {
		PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::STARTED_STATUS);
		$members = pwtc_mileage_fetch_membership();
		if (count($members) == 0) {
			PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'no members in membership list');
		}
		else {
			$results = self::update_membership_list($members);
			if ($results['insert_fail'] > 0) {
				PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 
					$results['insert_fail'] . ' failed updates, ' . 
					$results['validate_fail'] . ' failed validations, ' . 
					$results['insert_succeed'] . ' members inserted, ' .
					$results['update_succeed'] . ' members updated, ' .
					$results['duplicate_record'] . ' duplicates found');
			}
			else if ($results['validate_fail'] > 0) {
				PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 
					$results['validate_fail'] . ' failed validations, ' .
					$results['insert_succeed'] . ' members inserted, ' .
					$results['update_succeed'] . ' members updated, ' .
					$results['duplicate_record'] . ' duplicates found');
			}
			else {
				PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::SUCCESS_STATUS, 
					$results['insert_succeed'] . ' members inserted, ' . 
					$results['update_succeed'] . ' members updated, ' .
					$results['duplicate_record'] . ' duplicates found');
			}	
		}
	}

	public static function purge_nonriders_callback() {
		PwtcMileage_DB::job_set_status(self::RIDER_PURGE_ACT, PwtcMileage_DB::STARTED_STATUS);
		$status = PwtcMileage_DB::delete_all_nonriders();
		if (false === $status or 0 === $status) {
			PwtcMileage_DB::job_set_status(self::RIDER_PURGE_ACT, PwtcMileage_DB::FAILED_STATUS, 'database delete failed');
		}
		else {
			PwtcMileage_DB::job_set_status(self::RIDER_PURGE_ACT, PwtcMileage_DB::SUCCESS_STATUS, 
				$status . ' riders deleted');
		}
	}

	public static function updmembs_load_callback() {
		PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::STARTED_STATUS);
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/pwtc_mileage';
		$members_file = $plugin_upload_dir . '/updmembs.dbf';
		if (!file_exists($members_file)) {
			PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'updmembs.dbf file does not exist');
		}
		else {
			include('dbf_class.php');
			try {			
				$dbf = new dbf_class($members_file);
				if (self::validate_updmembs_file($dbf)) {
					$results = self::process_updmembs_file($dbf);
					if ($results['insert_fail'] > 0) {
						PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 
							$results['insert_fail'] . 'failed updates, ' . 
							$results['validate_fail'] . ' failed validations, ' . 
							$results['insert_succeed'] . ' members inserted, ' .
							$results['update_succeed'] . ' members updated, ' .
							$results['duplicate_record'] . ' duplicates found');
					}
					else if ($results['validate_fail'] > 0) {
						PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 
							$results['validate_fail'] . ' failed validations, ' .
							$results['insert_succeed'] . ' members inserted, ' .
							$results['update_succeed'] . ' members updated, ' .
							$results['duplicate_record'] . ' duplicates found');
					}
					else {
						PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::SUCCESS_STATUS, 
							$results['insert_succeed'] . ' members inserted, ' . 
							$results['update_succeed'] . ' members updated, ' .
							$results['duplicate_record'] . ' duplicates found');
					}	
				}
				else {
					PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'invalid dbf file contents');
				}
			} 
			catch (Exception $e) {
				pwtc_mileage_write_log('Exception thrown from dbf_class: ' . $e->getMessage());
				PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'invalid dbf file');
			}
			unlink($members_file);
		}
	}

	public static function updmembs_load_callback2() {
		PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::STARTED_STATUS);
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/pwtc_mileage';
		$members_file = $plugin_upload_dir . '/updmembs.dbf';
		$members_csv = $plugin_upload_dir . '/updmembs.csv';
		$plugin_upload_url = $upload_dir['baseurl'] . '/pwtc_mileage';
		$members_url = $plugin_upload_url . '/updmembs.csv';
		if (!file_exists($members_file)) {
			PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'updmembs.dbf file does not exist');
		}
		else {
			include('dbf_class.php');
			try {			
				$dbf = new dbf_class($members_file);
				if (self::validate_updmembs_file($dbf)) {
					$results = self::process_updmembs_file2($dbf);
					$fh = fopen($members_csv, 'w');
					self::write_export_csv_file($fh, $results['data']);
					fclose($fh);					
					$status = PwtcMileage_DB::load_members_for_update($members_url);
					if ($results['id_val_fail'] > 0 or $results['lname_val_fail'] > 0 or
						$results['fname_val_fail'] > 0 or $results['expir_val_fail'] > 0) {
						PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, 
							PwtcMileage_DB::SUCCESS_STATUS, 
							'updmembs file loaded, ' . $results['id_val_fail'] . ' invalid IDs, ' . 
							$results['fname_val_fail'] . ' invalid first names, ' .
							$results['lname_val_fail'] . ' invalid last names, ' .
							$results['expir_val_fail'] . ' invalid expiration dates');	
					}
					else {
						PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, 
							PwtcMileage_DB::SUCCESS_STATUS, 
							'updmembs file loaded, no validation errors');	
					}
					unlink($members_csv);
				}
				else {
					PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'invalid dbf file contents');
				}
			} 
			catch (Exception $e) {
				pwtc_mileage_write_log('Exception thrown from dbf_class: ' . $e->getMessage());
				PwtcMileage_DB::job_set_status(self::MEMBER_SYNC_ACT, PwtcMileage_DB::FAILED_STATUS, 'invalid dbf file');
			}
			unlink($members_file);
		}
	}

	public static function cvs_restore_callback() {
		PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::STARTED_STATUS);
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/pwtc_mileage';
		$members_file = $plugin_upload_dir . '/' . PwtcMileage_DB::MEMBER_TABLE . '.csv';
		$rides_file = $plugin_upload_dir . '/' . PwtcMileage_DB::RIDE_TABLE . '.csv';
		$mileage_file = $plugin_upload_dir . '/' . PwtcMileage_DB::MILEAGE_TABLE . '.csv';
		$leaders_file = $plugin_upload_dir . '/' . PwtcMileage_DB::LEADER_TABLE . '.csv';
		if (!file_exists($members_file)) {
			PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, 'members upload file does not exist');
		}
		else if (!file_exists($rides_file)) {
			PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, 'rides upload file does not exist');
		}
		else if (!file_exists($mileage_file)) {
			PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, 'mileage upload file does not exist');
		}
		else if (!file_exists($leaders_file)) {
			PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::FAILED_STATUS, 'leaders upload file does not exist');
		}
		else {
			$plugin_upload_url = $upload_dir['baseurl'] . '/pwtc_mileage';
			$members_url = $plugin_upload_url . '/' . PwtcMileage_DB::MEMBER_TABLE . '.csv';
			$rides_url = $plugin_upload_url . '/' . PwtcMileage_DB::RIDE_TABLE . '.csv';
			$mileage_url = $plugin_upload_url . '/' . PwtcMileage_DB::MILEAGE_TABLE . '.csv';
			$leaders_url = $plugin_upload_url . '/' . PwtcMileage_DB::LEADER_TABLE . '.csv';

			$delete_l = PwtcMileage_DB::delete_leaders_for_restore();
			$delete_m = PwtcMileage_DB::delete_mileage_for_restore();
			$delete_r = PwtcMileage_DB::delete_rides_for_restore();
			$delete_p = PwtcMileage_DB::delete_members_for_restore();

			PwtcMileage_DB::load_members_for_restore($members_url);
			PwtcMileage_DB::load_rides_for_restore($rides_url);
			PwtcMileage_DB::load_mileage_for_restore($mileage_url);
			PwtcMileage_DB::load_leaders_for_restore($leaders_url);

			$load_p = PwtcMileage_DB::count_members();
			$load_r = PwtcMileage_DB::count_rides();
			$load_m = PwtcMileage_DB::count_mileage();
			$load_l = PwtcMileage_DB::count_leaders();

			unlink($members_file);
			unlink($rides_file);
			unlink($mileage_file);
			unlink($leaders_file);

			PwtcMileage_DB::job_set_status(self::DB_RESTORE_ACT, PwtcMileage_DB::SUCCESS_STATUS, 
				$delete_l . ' leaders deleted, ' . 
				$delete_m . ' mileages deleted, ' . 
				$delete_r . ' ridesheets deleted, ' . 
				$delete_p . ' members deleted, ' . 
				$load_p . ' members loaded, ' . 
				$load_r . ' ridesheets loaded, ' . 
				$load_m . ' mileages loaded, ' . 
				$load_l . ' leaders loaded');
		}	
	}

	/*************************************************************/
	/* Background action task utility functions.
	/*************************************************************/

	public static function validate_updmembs_file($dbf) {
		if ($dbf->dbf_num_field < 4) {
			return false;
		}
		if ($dbf->dbf_names[0]['type'] != 'C') {
			return false;
		}
		if ($dbf->dbf_names[1]['type'] != 'C') {
			return false;
		}
		if ($dbf->dbf_names[2]['type'] != 'C') {
			return false;
		}
		if ($dbf->dbf_names[3]['type'] != 'D') {
			return false;
		}
		return true;
	}

	public static function process_updmembs_file($dbf) {
		$val_fail_count = 0;
		$ins_fail_count = 0;
		$ins_succ_count = 0;
		$upd_succ_count = 0;
		$dup_rec_count = 0;
		$hashmap = self::create_member_hashmap();
    	$num_rec = $dbf->dbf_num_rec;
		for ($i=0; $i<$num_rec; $i++) {
			if ($row = $dbf->getRow($i)) {
				$memberid = trim($row[0]);
				$firstname = trim($row[1]);
				$lastname = trim($row[2]);
				$date = trim($row[3]);
				$expirdate = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
				$status = self::update_member_item($hashmap, $memberid, $firstname, $lastname, $expirdate);
				switch ($status) {
					case "val_fail":
						pwtc_mileage_write_log($row);
						$val_fail_count++;
						break;
					case "dup_rec":
						$dup_rec_count++;
						break;
					case "ins_fail":
						$ins_fail_count++;
						break;
					case "insert":
						$ins_succ_count++;
						break;
					case "update":
						$upd_succ_count++;
						break;
				}
			}
		}		
		return array('validate_fail' => $val_fail_count,
			'insert_fail' => $ins_fail_count,
			'insert_succeed' => $ins_succ_count,
			'update_succeed' => $upd_succ_count,
			'duplicate_record' => $dup_rec_count);
	}

	public static function process_updmembs_file2($dbf) {
		$id_val_fail = 0;
		$fname_val_fail = 0;
		$lname_val_fail = 0;
		$expir_val_fail = 0;
		$data = array();
    	$num_rec = $dbf->dbf_num_rec;
		for ($i=0; $i<$num_rec; $i++) {
			if ($row = $dbf->getRow($i)) {
				$memberid = trim($row[0]);
				$firstname = trim($row[1]);
				$lastname = trim($row[2]);
				$date = trim($row[3]);
				$expirdate = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
				$val_fail = false;
				if (!self::validate_member_id_str($memberid)) {
					$id_val_fail++;
					$val_fail = true;
				}
				else if (!self::validate_member_name_str($lastname)) {
					$lname_val_fail++;
					$val_fail = true;
				}
				else if (!self::validate_member_name_str($firstname)) {
					$fname_val_fail++;
					$val_fail = true;
				}
				else if (!self::validate_date_str($expirdate)) {
					$expir_val_fail++;
					$val_fail = true;
				}
				else {
					array_push($data, array($memberid, $firstname, $lastname, $expirdate));
				}
				if ($val_fail) {
					pwtc_mileage_write_log('Updmembs file record validation failure:');
					pwtc_mileage_write_log($row);
				}
			}	
		}		
		return array('id_val_fail' => $id_val_fail,
			'lname_val_fail' => $lname_val_fail,
			'fname_val_fail' => $fname_val_fail,
			'expir_val_fail' => $expir_val_fail,
			'data' => $data);
	}

	public static function write_export_csv_file($fp, $data, $header = null) {
		if ($header != null) {
			fputcsv($fp, $header);
		}
		foreach ($data as $item) {
    		fputcsv($fp, $item);
		}		
	}

	public static function create_member_hashmap() {
		$riders = PwtcMileage_DB::fetch_members_for_export();
		$hashmap = array();
		foreach ( $riders as $item ) {
			$hashmap[$item[0]] = $item;
		}
		return $hashmap;		
	}

	public static function update_member_item($hashmap, $memberid, $firstname, $lastname, $expirdate) {
		if (!self::validate_member_id_str($memberid)) {
			return 'val_fail';
		}
		else if (!self::validate_member_name_str($lastname)) {
			return 'val_fail';
		}
		else if (!self::validate_member_name_str($firstname)) {
			return 'val_fail';
		}
		else if (!self::validate_date_str($expirdate)) {
			return 'val_fail';
		}
		$result = 'insert';
		if (array_key_exists($memberid, $hashmap)) {
			$rider = $hashmap[$memberid];
			if ($firstname == $rider[1] and $lastname == $rider[2] and $expirdate == $rider[3]) {
				return 'dup_rec';
			}
			$result = 'update';
		}
		$status = PwtcMileage_DB::insert_rider($memberid, $lastname, $firstname, $expirdate);	
		if (false === $status or 0 === $status) {
			return 'ins_fail';
		}
		return $result;
	}

	public static function update_membership_list($members) {
		$val_fail_count = 0;
		$ins_fail_count = 0;
		$ins_succ_count = 0;
		$upd_succ_count = 0;
		$dup_rec_count = 0;
		$hashmap = self::create_member_hashmap();
		foreach ( $members as $item ) {
			$memberid = trim($item[0]);
			$firstname = trim($item[1]);
			$lastname = trim($item[2]);
			$expirdate = trim($item[3]);
			$status = self::update_member_item($hashmap, $memberid, $firstname, $lastname, $expirdate);
			switch ($status) {
				case "val_fail":
					$val_fail_count++;
					break;
				case "dup_rec":
					$dup_rec_count++;
					break;
				case "ins_fail":
					$ins_fail_count++;
					break;
				case "insert":
					$ins_succ_count++;
					break;
				case "update":
					$upd_succ_count++;
					break;
			}
		}
		return array('validate_fail' => $val_fail_count,
			'insert_fail' => $ins_fail_count,
			'insert_succeed' => $ins_succ_count,
			'update_succeed' => $upd_succ_count,
			'duplicate_record' => $dup_rec_count);
	}

	/*************************************************************/
	/* Shortcode report table utility functions.
	/*************************************************************/

	// Returns a rider's display name (first and last) given the rider ID.
	public static function get_rider_name($id) {
		$rider = PwtcMileage_DB::fetch_rider($id);
		$name = '';
		if (count($rider) > 0) {
			$r = $rider[0];
			$name = $r['first_name'] . ' ' . $r['last_name'];
		}
		else {
			$name = $id;
		}
		return $name;
	}

	// Generates the HTML for a shortcode report table.
	public static function shortcode_build_table($meta, $data, $atts, $content = null) {
		$plugin_options = self::get_plugin_options();
		$hide_id = true;
		if ($atts['show_id'] == 'on') {
			$hide_id = false;
		}
		$id = null;
		if ($meta['id_idx'] >= 0 and $atts['highlight_user'] == 'on') {
			try {
				$id = pwtc_mileage_get_member_id();
			}
			catch (Exception $e) {
			}
		}
		$out = '';  
		if (count($data) == 0 and empty($content) and $atts['caption'] != 'on') {
			$out .= '<div class="callout small warning"><p>No records found.</p></div>';
			return $out;
		}
		$out .= '<table class="pwtc-mileage-rwd-table">';
		if (empty($content)) {
			if ($atts['caption'] == 'on') {
				$out .= '<caption>' . $meta['title'] . '</caption>';
			}
		}
		else {
			$out .= '<caption>' . do_shortcode($content) . '</caption>';
		}
		if (count($data) > 0) {
			$out .= '<tr>';
			$i = 0;
			foreach( $meta['header'] as $item ):
				if ($meta['id_idx'] === $i) {
					if (!$hide_id) {
						$out .= '<th>' . $item . '</th>';						
					}
				} 
				else {
					$out .= '<th>' . $item . '</th>';
				}
				$i++;
			endforeach;	
			$out .= '</tr>';
			foreach( $data as $row ):
				$outrow = '';
				$i = 0;
				$highlight = false;
				foreach( $row as $item ):
					$label = $meta['header'][$i];
					$lbl_attr = 'data-th="' . $label . '"';
					if ($meta['date_idx'] == $i) {
						$fmtdate = date('D M j Y', strtotime($item));
						$outrow .= '<td ' . $lbl_attr . '>' . $fmtdate . '</td>';
					}
					else if ($meta['id_idx'] === $i) {
						if ($id !== null and $id == $item) {
							$highlight = true;
						}
						if (!$hide_id) {
							$outrow .= '<td ' . $lbl_attr . '>' . $item . '</td>';						
						}
					}
					else {
						if (0 === strpos($item, 'http://') or 0 === strpos($item, 'https://')) {
							$outrow .= '<td ' . $lbl_attr . '><a href="' . $item . 
								'" target="_blank">View</a></td>';
						}
						else {
							$outrow .= '<td ' . $lbl_attr . '>' . $item . '</td>';
						}
					}
					$i++;
				endforeach;	
				if ($highlight) {
					$out .= '<tr class="highlight">' . $outrow . '</tr>';
				}
				else {
					$out .= '<tr>' . $outrow . '</tr>';
				}
			endforeach;
		}
		else {
			$out .= '<tr><td data-th="Message">No records found.</td></tr>';
		}
		$out .= '</table>';
		$out .= '';
		return $out;
	}

	// Generates the default attribute object for a shortcode report table.
	public static function normalize_atts($atts) {
    	$a = shortcode_atts(array(
        		'show_id' => 'off',
       			'highlight_user' => 'on',
				'sort_by' => 'off',
				'sort_order' => 'asc',
				'minimum' => 1,
				'caption' => 'on'
			), $atts);
		return $a;
	}

	// Generates the SQL 'order by' clause from a shortcode mileage report table attribute object.
	public static function build_mileage_sort($atts) {
		$order = 'asc';
		if ($atts['sort_order'] == 'desc') {
			$order = 'desc';
		}
		$sort = 'mileage ' . $order;
		if ($atts['sort_by'] == 'name') {
			$sort = 'last_name ' . $order . ', first_name ' . $order;
		}
		else if ($atts['sort_by'] == 'rides') {
			$sort = 'rides ' . $order;
		}
		return $sort;
	}

	public static function build_attendence_sort($atts) {
		$order = 'asc';
		if ($atts['sort_order'] == 'desc') {
			$order = 'desc';
		}
		$sort = 'date ' . $order;
		if ($atts['sort_by'] == 'title') {
			$sort = 'title ' . $order;
		}
		else if ($atts['sort_by'] == 'riders') {
			$sort = 'riders ' . $order;
		}
		return $sort;
	}

	public static function build_mileage_sort2($atts) {
		$order = 'asc';
		if ($atts['sort_order'] == 'desc') {
			$order = 'desc';
		}
		$sort = 'mileage ' . $order;
		if ($atts['sort_by'] == 'name') {
			$sort = 'last_name ' . $order . ', first_name ' . $order;
		}
		return $sort;
	}

	// Generates the SQL 'order by' clause from a shortcode leader report table attribute object.
	public static function build_rides_led_sort($atts) {
		$order = 'asc';
		if ($atts['sort_order'] == 'desc') {
			$order = 'desc';
		}
		$sort = 'rides_led ' . $order;
		if ($atts['sort_by'] == 'name') {
			$sort = 'last_name ' . $order . ', first_name ' . $order;
		}
		return $sort;
	}

	// Gets the minimum value from a shortcode report table attribute object.
	public static function get_minimum_val($atts) {
		$min = 0;
		if ($atts['minimum'] > 0) {
			$min = $atts['minimum'];
		}
		return $min;
	}

	/*************************************************************/
	/* Shortcode report generation functions
	/*************************************************************/
 
	// Generates the [pwtc_rider_report] shortcode.
	public static function shortcode_rider_report($atts) {
    	$a = shortcode_atts(array('type' => 'both'), $atts);
		$out = '';
		try {
			$id = pwtc_mileage_get_member_id();
			$result = PwtcMileage_DB::fetch_rider($id);
			if (count($result) > 0) {
				$out .= '<strong>' . $result[0]['first_name'] . ' ' . $result[0]['last_name'] . 
					'</strong>, your rider ID is <strong>' . $id . '</strong>.'; 
			}
			else {
				$out .= 'Your rider ID is <strong>' . $id . '</strong>.';
			}
			if ($a['type'] == 'mileage' or $a['type'] == 'both') {
				$out .= ' You have ridden <strong>';
				$out .= PwtcMileage_DB::get_ytd_rider_mileage($id);
				$out .= '</strong> miles with the club so far this year. Last year you rode <strong>';
				$out .= PwtcMileage_DB::get_ly_rider_mileage($id);
				$out .= '</strong> miles. Your total lifetime club mileage is <strong>';
				$out .= PwtcMileage_DB::get_lt_rider_mileage($id);
				$out .= '</strong> miles.';
			}
			if ($a['type'] == 'leader' or $a['type'] == 'both') {
				$out .= ' You have led <strong>';
				$out .= PwtcMileage_DB::get_ytd_rider_led($id);
				$out .= '</strong> club rides so far this year. Last year you led <strong>';
				$out .= PwtcMileage_DB::get_ly_rider_led($id);
				$out .= '</strong> rides.';
			}
		}
		catch (Exception $e) { 
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= 'Please log in to view your club rider report.';
					break;
				case "idnotset":
					$out .= 'Cannot view your club rider report, rider ID not assigned.';
					break;
				case "idnotfound":
					$out .= 'Cannot view your club rider report, rider ID not found - please contact website admin.';
					break;
				case "multidfound":
					$out .= 'Cannot view your club rider report, multiple rider IDs found - please contact website admin.';
					break;
				default:
					$out .= 'Cannot view your club rider report, unknown error - please contact website admin.';
			}
		}
		$out .= '';
		return $out;
	}

/*
	public static function shortcode_mileage_ridesheet($atts) {
		if (!current_user_can(self::VIEW_MILEAGE_CAP)) {
			return "<p>You are not permitted to view the ride sheet.</p>";
		}
		if (isset($_GET['postid']) && $_GET['postid']) {
			$postid = $_GET['postid'];
			if (is_numeric($postid) and intval($postid) > 0) {
				$data = pwtc_mileage_fetch_posted_ride(intval($postid));
				if (count($data) > 0) {
					$post_title = $data[0][1];
					$post_date = $data[0][2];
					$data = PwtcMileage_DB::fetch_ride_by_post_id(intval($postid));
					if (count($data) > 1) {
						$out = '<p><strong>Error:</strong> multiple ride sheets are linked to this ride.<br/>';
						foreach( $data as $row ):
							$out .= '"' . $row['title'] . '" on ' . $row['date'] . '<br/>';
						endforeach;
						$out .= '</p>';		
						return $out;
					}
					else if (count($data) > 0) {
						$out = '';
						$rideid = intval($data[0]['ID']);
						$out .= '<h3>"' . $data[0]['title'] . '" on ' . $data[0]['date'] . '</h3>';
						$leaders = PwtcMileage_DB::fetch_ride_leaders($rideid);
						if (count($leaders)) {
							$out .= '<p><strong>Ride Leaders:</strong><br/>';
							$i = 0;
							foreach( $leaders as $leader ):
								if ($i > 0) {
									$out .= ', ';
								}
								$out .= $leader['first_name'] . ' ' . $leader['last_name'];
								$i++;
							endforeach;	
							$out .= '</p>';
						}
						else {
							$out .= '<p><em>No ride leaders entered.</em></p>';
						}	
						$riders = PwtcMileage_DB::fetch_ride_mileage($rideid);
						if (count($riders)) {
							$out .= '<p><strong>Riders:</strong><br/>';
							$i = 0;
							foreach( $riders as $rider ):
								if ($i > 0) {
									$out .= ', ';
								}
								$out .= $rider['first_name'] . ' ' . $rider['last_name'];
								$i++;
							endforeach;	
							$out .= '</p>';
						}	
						else {
							$out .= '<p><em>No riders entered.</em></p>';
						}
						if ($post_date <> $data[0]['date'])	{
							$out .= '<p><strong>Warning:</strong> date of ride (' . $post_date . ') does not match date of ride sheet (' . $data[0]['date'] . ').</p>';
						}
						return $out;
					}
					else {
						return '<p>No ride sheet created yet for the ride "' . $post_title . '" on ' . $post_date . '.</p>';
					}
				}
				else {
					return '<p><strong>Error:</strong> cannot lookup ride sheet, ride post ID "' . $postid . '" is not found.</p>';
				}
			}
			else {
				return '<p><strong>Error:</strong> cannot lookup the ride sheet, ride post ID "' . $postid . '" is invalid.</p>';
			}
		}
		else {
			return '<p><strong>Error:</strong> cannot lookup the ride sheet, ride post ID is not specified.</p>';
		}
	}
*/

	// Generates the [pwtc_attendence_year_to_date] shortcode.
	public static function shortcode_ytd_attendence($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_attendence_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ytd_attendence();
		$data = PwtcMileage_DB::fetch_ytd_attendence(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_attendence_last_year] shortcode.
	public static function shortcode_ly_attendence($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_attendence_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ly_attendence();
		$data = PwtcMileage_DB::fetch_ly_attendence(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_mileage_year_to_date] shortcode.
	public static function shortcode_ytd_miles($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_mileage_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ytd_miles();
		$data = PwtcMileage_DB::fetch_ytd_miles(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_mileage_last_year] shortcode.
	public static function shortcode_ly_miles($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_mileage_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ly_miles();
		$data = PwtcMileage_DB::fetch_ly_miles(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_mileage_lifetime] shortcode.
	public static function shortcode_lt_miles($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_mileage_sort2($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_lt_miles();
		$data = PwtcMileage_DB::fetch_lt_miles(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_rides_led_year_to_date] shortcode.
	public static function shortcode_ytd_led($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_rides_led_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ytd_led();
		$data = PwtcMileage_DB::fetch_ytd_led(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_rides_led_last_year] shortcode.
	public static function shortcode_ly_led($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		$sort = self::build_rides_led_sort($a);
		$min = self::get_minimum_val($a);
		$meta = PwtcMileage_DB::meta_ly_led($min);
		$data = PwtcMileage_DB::fetch_ly_led(ARRAY_N, $sort, $min);
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}

	// Generates the [pwtc_rides_year_to_date] shortcode.
	public static function shortcode_ytd_rides($atts, $content = null) {
		$out = '';
		try {
			$member_id = pwtc_mileage_get_member_id();
			$name = self::get_rider_name($member_id);
			$a = self::normalize_atts($atts);
			$meta = PwtcMileage_DB::meta_ytd_rides($name);
			$data = PwtcMileage_DB::fetch_ytd_rides(ARRAY_N, $member_id);
			$out .= self::shortcode_build_table($meta, $data, $a, $content);
		}
		catch (Exception $e) {
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
					break;
				case "idnotset":
					$out .= '<div class="callout small warning"><p>Cannot view this report, rider ID not assigned.</p></div>';
					break;
				case "idnotfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, rider ID not found - please contact website admin.</p></div>';
					break;
				case "multidfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, multiple rider IDs found - please contact website admin.</p></div>';
					break;
				default:
					$out .= '<div class="callout small alert"><p>Cannot view this report, unknown error - please contact website admin.</p></div>';
			}
		}
		$out .= '';
		return $out;
	}

	// Generates the [pwtc_rides_last_year] shortcode.
	public static function shortcode_ly_rides($atts, $content = null) {
		$out = '';
		try {
			$member_id = pwtc_mileage_get_member_id();
			$name = self::get_rider_name($member_id);
			$a = self::normalize_atts($atts);
			$meta = PwtcMileage_DB::meta_ly_rides($name);
			$data = PwtcMileage_DB::fetch_ly_rides(ARRAY_N, $member_id);
			$out .= self::shortcode_build_table($meta, $data, $a, $content);
		}
		catch (Exception $e) {
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
					break;
				case "idnotset":
					$out .= '<div class="callout small warning"><p>Cannot view this report, rider ID not assigned.</p></div>';
					break;
				case "idnotfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, rider ID not found - please contact website admin.</p></div>';
					break;
				case "multidfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, multiple rider IDs found - please contact website admin.</p></div>';
					break;
				default:
					$out .= '<div class="callout small alert"><p>Cannot view this report, unknown error - please contact website admin.</p></div>';
			}
		}
		$out .= '';
		return $out;
	}

	// Generates the [pwtc_led_rides_year_to_date] shortcode.
	public static function shortcode_ytd_led_rides($atts, $content = null) {
		$out = '';
		try {
			$member_id = pwtc_mileage_get_member_id();
			$name = self::get_rider_name($member_id);
			$a = self::normalize_atts($atts);
			$meta = PwtcMileage_DB::meta_ytd_rides_led($name);
			$data = PwtcMileage_DB::fetch_ytd_rides_led(ARRAY_N, $member_id);
			$out .= self::shortcode_build_table($meta, $data, $a, $content);
		}
		catch (Exception $e) {
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
					break;
				case "idnotset":
					$out .= '<div class="callout small warning"><p>Cannot view this report, rider ID not assigned.</p></div>';
					break;
				case "idnotfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, rider ID not found - please contact website admin.</p></div>';
					break;
				case "multidfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, multiple rider IDs found - please contact website admin.</p></div>';
					break;
				default:
					$out .= '<div class="callout small alert"><p>Cannot view this report, unknown error - please contact website admin.</p></div>';
			}
		}
		$out .= '';
		return $out;
	}

	// Generates the [pwtc_led_rides_last_year] shortcode.
	public static function shortcode_ly_led_rides($atts, $content = null) {
		$out = '';
		try {
			$member_id = pwtc_mileage_get_member_id();
			$name = self::get_rider_name($member_id);
			$a = self::normalize_atts($atts);
			$meta = PwtcMileage_DB::meta_ly_rides_led($name);
			$data = PwtcMileage_DB::fetch_ly_rides_led(ARRAY_N, $member_id);
			$out .= self::shortcode_build_table($meta, $data, $a, $content);
		}
		catch (Exception $e) {
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= '<div class="callout small warning"><p>Please log in to view this report</p></div>';
					break;
				case "idnotset":
					$out .= '<div class="callout small warning"><p>Cannot view this report, rider ID not assigned.</p></div>';
					break;
				case "idnotfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, rider ID not found - please contact website admin.</p></div>';
					break;
				case "multidfound":
					$out .= '<div class="callout small alert"><p>Cannot view this report, multiple rider IDs found - please contact website admin.</p></div>';
					break;
				default:
					$out .= '<div class="callout small alert"><p>Cannot view this report, unknown error - please contact website admin.</p></div>';
			}
		}
		$out .= '';
		return $out;
	}

	// Generates the [pwtc_posted_rides_wo_sheets] shortcode.
	public static function shortcode_rides_wo_sheets($atts, $content = null) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view this report.</p></div>';
		}	
		$a = self::normalize_atts($atts);
		//$meta = PwtcMileage_DB::meta_posts_without_rides2();
		//$data = PwtcMileage_DB::fetch_posts_without_rides2();
		$meta = PwtcMileage_DB::meta_posts_without_rides();
		$data = PwtcMileage_DB::fetch_posts_without_rides();
		$out = self::shortcode_build_table($meta, $data, $a, $content);
		return $out;
	}
		
	// Generates the [pwtc_riderid_download] shortcode.
	public static function shortcode_riderid_download($atts, $content = null) {
		$out = '';
		try {
			$member_id = pwtc_mileage_get_member_id(true);
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
			$out .= '<form method="POST">';
			$out .= '<button class="dark button" type="submit" name="pwtc_mileage_download_riderid"><i class="fa fa-download"></i> Rider Card</button>';
			$out .= '<input type="hidden" name="rider_id" value="' . $member_id . '"/>';
			$out .= '<input type="hidden" name="user_id" value="' . $user_id . '"/>';
			$out .= '</form>';
		}
		catch (Exception $e) {
			switch ($e->getMessage()) {
				case "notloggedin":
					$out .= '<div class="callout small warning"><p>Please log in to download your rider card.</p></div>';
					break;
				case "notmember":
					$out .= '<div class="callout small warning"><p>Cannot download your rider card, you are not a member.</p></div>';
					break;
				case "idnotset":
					$out .= '<div class="callout small warning"><p>Cannot download your rider card, rider ID not assigned.</p></div>';
					break;
				case "idnotfound":
					$out .= '<div class="callout small alert"><p>Cannot download your rider card, rider ID not found - please contact website admin.</p></div>';
					break;
				case "multimember":
					$out .= '<div class="callout small alert"><p>Cannot download your rider card, you have multiple memberships - please contact website admin.</p></div>';
					break;
				case "multidfound":
					$out .= '<div class="callout small alert"><p>Cannot download your rider card, multiple rider IDs found - please contact website admin.</p></div>';
					break;
				default:
					$out .= '<div class="callout small alert"><p>Cannot download your rider card, unknown error - please contact website admin.</p></div>';
			}
		}
		return $out;
	}

	/*************************************************************/
	/* User input validation functions
	/*************************************************************/

	public static function validate_date_str($datestr) {
		$ok = true;
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datestr) !== 1) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_member_id_str($memberid) {
		$ok = true;
		if (preg_match('/^\d{5}$/', $memberid) !== 1) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_member_name_str($name) {
		$ok = true;
		if (preg_match('/^[A-Za-z].*/', $name) !== 1) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_ride_title_str($title) {
		$ok = true;
		if (preg_match('/^[A-Za-z0-9].*/', $title) !== 1) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_label_str($label) {
		$ok = true;
		if (preg_match('/^[A-Za-z].*/', $label) !== 1) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_mileage_str($mileage) {
		$ok = true;
		if (!is_numeric($mileage)) {
			$ok = false;
		}
		else if (intval($mileage) < 0) {
			$ok = false;
		}
		return $ok;
	}

	public static function validate_number_str($number) {
		$ok = true;
		if (!is_numeric($number)) {
			$ok = false;
		}
		else if (intval($number) < 0) {
			$ok = false;
		}
		return $ok;
	}

	/*************************************************************/
	/* Plugin options access functions
	/*************************************************************/

	public static function create_default_plugin_options() {
		$data = array(
			'admin_maint_mode' => false,
			'user_lookup_mode' => 'wordpress',
			'drop_db_on_delete' => false,
			'plugin_menu_label' => 'Rider Mileage',
			'plugin_menu_location' => 50,
			'ride_lookback_date' => '',
			'disable_expir_check' => false,
			'disable_delete_confirm' => false,
			'show_ride_ids' => false,
			'expire_grace_period' => 60,
			'db_lock_time_limit' => 60);
		add_option('pwtc_mileage_options', $data);
	}

	public static function get_plugin_options() {
		return get_option('pwtc_mileage_options');
	}

	public static function delete_plugin_options() {
		delete_option('pwtc_mileage_options');
	}

	public static function update_plugin_options($data) {
		update_option('pwtc_mileage_options', $data);
	}

	public static function get_date_for_expir_check() {
		$plugin_options = self::get_plugin_options();
		$time = $plugin_options['expire_grace_period'] * 24 * 60 * 60; // convert grace period from days to seconds
		return date('Y-m-d', current_time('timestamp') - $time);
	}

	/*************************************************************/
	/* Plugin capabilities management functions for admin role.
	/*************************************************************/

	public static function add_caps_admin_role() {
		$admin = get_role('administrator');
		$admin->add_cap(self::VIEW_MILEAGE_CAP);
		$admin->add_cap(self::EDIT_MILEAGE_CAP);
		$admin->add_cap(self::EDIT_RIDERS_CAP);
		$admin->add_cap(self::DB_OPS_CAP);
		pwtc_mileage_write_log('PWTC Mileage plugin added capabilities to administrator role');
	}

	public static function remove_caps_admin_role() {
		$admin = get_role('administrator');
		$admin->remove_cap(self::VIEW_MILEAGE_CAP);
		$admin->remove_cap(self::EDIT_MILEAGE_CAP);
		$admin->remove_cap(self::EDIT_RIDERS_CAP);
		$admin->remove_cap(self::DB_OPS_CAP);
		pwtc_mileage_write_log('PWTC Mileage plugin removed capabilities from administrator role');
	}

	/*************************************************************/
	/* Plugin installation and removal functions.
	/*************************************************************/

	public static function plugin_activation() {
		pwtc_mileage_write_log( 'PWTC Mileage plugin activated' );
		if ( version_compare( $GLOBALS['wp_version'], PWTC_MILEAGE__MINIMUM_WP_VERSION, '<' ) ) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('PWTC Mileage plugin requires Wordpress version of at least ' . PWTC_MILEAGE__MINIMUM_WP_VERSION);
		}
		$errs = PwtcMileage_DB::handle_db_upgrade();
		if ($errs > 0) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('PWTC Mileage plugin could not update database tables');			
		}
		$errs = PwtcMileage_DB::create_db_tables();
		if ($errs > 0) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('PWTC Mileage plugin could not create database tables');			
		}
		$errs = PwtcMileage_DB::create_db_views();
		if ($errs > 0) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('PWTC Mileage plugin could not create database views');			
		}
		PwtcMileage_DB::set_db_version();
		if (self::get_plugin_options() === false) {
			self::create_default_plugin_options();
		}
		self::add_caps_admin_role();
		pwtc_mileage_create_stat_role();
	}

	public static function plugin_deactivation( ) {
		pwtc_mileage_write_log( 'PWTC Mileage plugin deactivated' );
		self::remove_caps_admin_role();
		pwtc_mileage_remove_stat_role();
	}

	public static function plugin_uninstall() {
		pwtc_mileage_write_log( 'PWTC Mileage plugin uninstall' );	
		$plugin_options = self::get_plugin_options();
		if ($plugin_options['drop_db_on_delete']) {
			PwtcMileage_DB::drop_db_views();	
			PwtcMileage_DB::drop_db_tables();
			PwtcMileage_DB::delete_db_version();				
		}
		self::delete_plugin_options();
	}

}