<?php

class PwtcMileage_DB {

	const MEMBER_TABLE = 'pwtc_membership';					// club membership list table
	const RIDE_TABLE = 'pwtc_club_rides';					// club ride list table
	const MILEAGE_TABLE = 'pwtc_ride_mileage';				// club ride mileage table
	const LEADER_TABLE = 'pwtc_ride_leaders';				// club ride leader table
	const JOBS_TABLE = 'pwtc_running_jobs';					// currently running jobs table

	const LT_MILES_VIEW = 'pwtc_lt_miles_vw';				// lifetime mileage view
	const YTD_MILES_VIEW = 'pwtc_ytd_miles_vw';				// year-to-date mileage view
	const LY_MILES_VIEW = 'pwtc_ly_miles_vw';				// last year's mileage view
	const LY_LT_MILES_VIEW = 'pwtc_ly_lt_miles_vw';			// last year's lifetime mileage view
	const YBL_LT_MILES_VIEW = 'pwtc_ybl_lt_miles_vw';		// year before last's lifetime mileage view
	const LY_LT_ACHVMNT_VIEW = 'pwtc_ly_lt_achvmnt_vw';		// last year's lifetime achiviement view
	const YTD_RIDES_LED_VIEW = 'pwtc_ytd_rides_led_vw';		// year-to-date rides led list view
	const LY_RIDES_LED_VIEW = 'pwtc_ly_rides_led_vw';		// last year's rides led list view
	const YTD_LED_VIEW = 'pwtc_ytd_led_vw';					// year-to-date number of rides led view 
	const LY_LED_VIEW = 'pwtc_ly_led_vw';					// last year's number of rides led view
	const PRE_LY_LED_VIEW = 'pwtc_pre_ly_led_vw';			// pre-last year's number of rides led view
	const YTD_RIDES_VIEW = 'pwtc_ytd_rides_vw';				// year-to-date rides ridden list view
	const LY_RIDES_VIEW = 'pwtc_ly_rides_vw';				// last year's rides ridden list view

	const TRIGGERED_STATUS = 'triggered';
	const STARTED_STATUS = 'started';
	const FAILED_STATUS = 'failed';
	const SUCCESS_STATUS = 'success';

	public static function get_new_ride_id() {
    	global $wpdb;
		return $wpdb->insert_id;
	}

	public static function get_num_rides_before_date($maxdate) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$num_rides = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $ride_table . 
			' where date <= %s', $maxdate));
		return $num_rides;
	}

	public static function get_ytd_rider_mileage($memberid) {
    	global $wpdb;
		$mileage = $wpdb->get_var($wpdb->prepare('select mileage from ' . self::YTD_MILES_VIEW . 
			' where member_id = %s', $memberid));
		if ($mileage == null) {
			$mileage = 0;
		}
		return $mileage;
	}

	public static function get_ly_rider_mileage($memberid) {
    	global $wpdb;
		$mileage = $wpdb->get_var($wpdb->prepare('select mileage from ' . self::LY_MILES_VIEW . 
			' where member_id = %s', $memberid));
		if ($mileage == null) {
			$mileage = 0;
		}
		return $mileage;
	}

	public static function get_lt_rider_mileage($memberid) {
    	global $wpdb;
		$mileage = $wpdb->get_var($wpdb->prepare('select mileage from ' . self::LT_MILES_VIEW . 
			' where member_id = %s', $memberid));
		if ($mileage == null) {
			$mileage = 0;
		}
		return $mileage;
	}

	public static function get_ytd_rider_led($memberid) {
    	global $wpdb;
		$num_led = $wpdb->get_var($wpdb->prepare('select rides_led from ' . self::YTD_LED_VIEW . 
			' where member_id = %s', $memberid));
		if ($num_led == null) {
			$num_led = 0;
		}
		return $num_led;
	}

	public static function get_ly_rider_led($memberid) {
    	global $wpdb;
		$num_led = $wpdb->get_var($wpdb->prepare('select rides_led from ' . self::LY_LED_VIEW . 
			' where member_id = %s', $memberid));
		if ($num_led == null) {
			$num_led = 0;
		}
		return $num_led;
	}

	public static function fetch_member_duplicates() {
		$dup_array = array();
		$results = self::fetch_member_dups(ARRAY_N);
		foreach ($results as $item) {
			$ids = '';
			$riders = self::fetch_riders_by_name($item[1], $item[0]);
			foreach ($riders as $rider) {
				$ids .= $rider['member_id'] . ' ';
			}
			array_push($dup_array, array($item[0], $item[1], $ids));
		}
		return $dup_array;
	}

	public static function fetch_member_dups($outtype) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
    	$results = $wpdb->get_results(
			'select distinct a.first_name as first_name, a.last_name as last_name' . 
			' from ' . $member_table . ' as a inner join ' . $member_table . ' as b' . 
			' where a.first_name like b.first_name and a.last_name like b.last_name' . 
			' and a.member_id <> b.member_id order by a.last_name, a.first_name', $outtype);
		return $results;
	}

	public static function meta_member_duplicates() {
		$meta = array(
			'header' => array('First Name', 'Last Name', 'IDs'),
			'width' => array(30, 30, 40),
			'align' => array('L', 'L', 'L'),
			'title' => 'Riders With Duplicate Names',
			'date_idx' => -1,
			'id_idx' => -1
		);
		return $meta;		
	}

	public static function fetch_member_list($outtype, $mode) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		if ($mode == 2) {
			$clause = 'member_id in (select distinct member_id from ' . $mileage_table . ')';
		}
		else if ($mode == 1) {
			$clause = 'member_id in (select distinct member_id from ' . $leader_table . ')';
		}
		else {
			$clause = 'member_id not in (select distinct member_id from ' . $leader_table . ') and' . ' member_id not in (select distinct member_id from ' . $mileage_table . ')';
		}
		$results = $wpdb->get_results(
			'select member_id, first_name, last_name, expir_date' . 
			' from ' . $member_table . ' where ' . $clause . 
			' order by last_name, first_name', $outtype);

		return $results;
	}

	public static function meta_member_list($title) {
		$meta = array(
			'header' => array('ID', 'First Name', 'Last Name', 'Expiration'),
			'width' => array(15, 30, 30, 25),
			'align' => array('C', 'L', 'L', 'R'),
			'title' => $title,
			'date_idx' => 3,
			'id_idx' => 0
		);
		return $meta;		
	}

	public static function fetch_annual_accum_miles($outtype, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = 'where a.mileage > 0';
		if ($min > 1) {
			$where = 'where a.mileage >= ' . $min;
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(a.last_name, \', \', a.first_name) as name,' . 
				' a.mileage as annual, b.mileage as accum' .
				' from ' . self::LY_MILES_VIEW . ' as a inner join ' . self::LY_LT_MILES_VIEW . 
				' as b on a.member_id = b.member_id ' . $where .
				' order by a.last_name, a.first_name', $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select a.member_id as member_id,' . 
				' concat(a.last_name, \', \', a.first_name) as name,' . 
				' a.mileage as annual, b.mileage as accum' .
				' from ' . self::LY_MILES_VIEW . ' as a inner join ' . self::LY_LT_MILES_VIEW . 
				' as b on a.member_id = b.member_id ' . $where .
				' order by a.last_name, a.first_name', $outtype);
		}
		return $results;
	}

	public static function meta_annual_accum_miles($min = 0, $no_id = false) {
		$thisyear = date('Y', current_time('timestamp'));
    	$lastyear = intval($thisyear) - 1;
		$title = $lastyear . ' Annual & Accumulative Mileage';
		if ($min > 1) {
			$title .= ' (' . $min . ' miles or more)';
		}
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Annual', 'Accum'),
				'width' => array(70, 15, 15),
				'align' => array('L', 'R', 'R'),
				'title' => $title,
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Annual', 'Accum'),
				'width' => array(15, 55, 15, 15),
				'align' => array('C', 'L', 'R', 'R'),
				'title' => $title,
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ly_lt_achvmnt($outtype, $sort, $no_id = false) {
		global $wpdb;
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), mileage, achievement from ' . 
				self::LY_LT_ACHVMNT_VIEW . ' order by ' . $sort, $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), mileage, achievement from ' . 
				self::LY_LT_ACHVMNT_VIEW . ' order by ' . $sort, $outtype);
		}
		return $results;
	}

	public static function meta_ly_lt_achvmnt($no_id = false) {
		$thisyear = date('Y', current_time('timestamp'));
		$lastyear = intval($thisyear) - 1;
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Mileage', 'Award'),
				'width' => array(70, 15, 15),
				'align' => array('L', 'R', 'R'),
				'title' => '' . $lastyear . ' Lifetime Mileage Achievement',
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Mileage', 'Award'),
				'width' => array(15, 55, 15, 15),
				'align' => array('C', 'L', 'R', 'R'),
				'title' => '' . $lastyear . ' Lifetime Mileage Achievement',
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ytd_attendence($outtype, $sort, $min = 0) {
    	global $wpdb;
		$where = '';
		/*
		if ($min > 0) {
			$where = ' where riders >= ' . $min . ' ';
		}
		*/
		$results = $wpdb->get_results(
			'select title, date, COUNT(member_id) as riders from ' . 
			self::YTD_RIDES_VIEW . $where . ' group by ride_id order by ' . $sort , $outtype);
		return $results;
	}

	public static function meta_ytd_attendence() {
		$timestamp = date('M j Y', current_time('timestamp'));
		$meta = array(
			'header' => array('Title', 'Date', 'Riders'),
			'width' => array(60, 25, 15),
			'align' => array('L', 'R', 'R'),
			'title' => 'Year-to-date Ride Attendence as of ' . $timestamp,
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_ly_attendence($outtype, $sort, $min = 0) {
    	global $wpdb;
		$where = '';
		/*
		if ($min > 0) {
			$where = ' where riders >= ' . $min . ' ';
		}
		*/
		$results = $wpdb->get_results(
			'select title, date, COUNT(member_id) as riders from ' . 
			self::LY_RIDES_VIEW . $where . ' group by ride_id order by ' . $sort , $outtype);
		return $results;
	}

	public static function meta_ly_attendence() {
		$thisyear = date('Y', current_time('timestamp'));
    	$lastyear = intval($thisyear) - 1;
		$meta = array(
			'header' => array('Title', 'Date', 'Riders'),
			'width' => array(60, 25, 15),
			'align' => array('L', 'R', 'R'),
			'title' => $lastyear . ' Ride Attendence',
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_ytd_miles($outtype, $sort, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where mileage >= ' . $min . ' ';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), rides, mileage from ' . 
				self::YTD_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), rides, mileage from ' . 
				self::YTD_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_ytd_miles($no_id = false) {
		$timestamp = date('M j Y', current_time('timestamp'));
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Rides', 'Mileage'),
				'width' => array(60, 20, 20),
				'align' => array('L', 'R', 'R'),
				'title' => 'Year-to-date Rider Mileage as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => -1
			);	
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Rides', 'Mileage'),
				'width' => array(20, 40, 20, 20),
				'align' => array('C', 'L', 'R', 'R'),
				'title' => 'Year-to-date Rider Mileage as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ly_miles($outtype, $sort, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where mileage >= ' . $min . ' ';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), rides, mileage from ' . 
				self::LY_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), rides, mileage from ' . 
				self::LY_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_ly_miles($no_id = false) {
		$thisyear = date('Y', current_time('timestamp'));
		$lastyear = intval($thisyear) - 1;
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Rides', 'Mileage'),
				'width' => array(60, 20, 20),
				'align' => array('L', 'R', 'R'),
				'title' => $lastyear . ' Rider Mileage',
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Rides', 'Mileage'),
				'width' => array(20, 40, 20, 20),
				'align' => array('C', 'L', 'R', 'R'),
				'title' => $lastyear . ' Rider Mileage',
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_pre_ly_miles($outtype, $sort, $min = 0) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where mileage >= ' . $min . ' ';
		}
    	$results = $wpdb->get_results(
			'select member_id, concat(first_name, \' \', last_name), mileage from ' . 
			self::YBL_LT_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		return $results;
	}

	public static function meta_pre_ly_miles() {
		$thisyear = date('Y', current_time('timestamp'));
    	$lastyear = intval($thisyear) - 1;
		$meta = array(
			'header' => array('ID', 'Name', 'Mileage'),
			'width' => array(20, 60, 20),
			'align' => array('C', 'L', 'R'),
			'title' => 'Pre-' . $lastyear . ' Rider Mileage',
			'date_idx' => -1,
			'id_idx' => 0
		);
		return $meta;
	}

	public static function fetch_lt_miles($outtype, $sort, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where mileage >= ' . $min . ' ';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), mileage from ' . 
				self::LT_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), mileage from ' . 
				self::LT_MILES_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_lt_miles($no_id = false) {
		$timestamp = date('M j Y', current_time('timestamp'));
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Mileage'),
				'width' => array(80, 20),
				'align' => array('L', 'R'),
				'title' => 'Lifetime Rider Mileage as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Mileage'),
				'width' => array(20, 60, 20),
				'align' => array('C', 'L', 'R'),
				'title' => 'Lifetime Rider Mileage as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ytd_led($outtype, $sort, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where rides_led >= ' . $min . ' ';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), rides_led from ' . 
				self::YTD_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), rides_led from ' . 
				self::YTD_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_ytd_led($no_id = false) {
		$timestamp = date('M j Y', current_time('timestamp'));
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Rides Led'),
				'width' => array(80, 20),
				'align' => array('L', 'R'),
				'title' => 'Year-to-date Ride Leaders as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => -1
			);	
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Rides Led'),
				'width' => array(20, 60, 20),
				'align' => array('C', 'L', 'R'),
				'title' => 'Year-to-date Ride Leaders as of ' . $timestamp,
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ly_led($outtype, $sort, $min = 0, $lastname_first = false, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where rides_led >= ' . $min . ' ';
		}
		$name = 'concat(first_name, \' \', last_name)';
		if ($lastname_first) {
			$name = 'concat(last_name, \', \', first_name)';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select ' . $name . ', rides_led from ' . 
				self::LY_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, ' . $name . ', rides_led from ' . 
				self::LY_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_ly_led($min = 0, $no_id = false) {
		$thisyear = date('Y', current_time('timestamp'));
		$lastyear = intval($thisyear) - 1;
		$title = $lastyear . ' Ride Leaders';
		if ($min > 1) {
			$title .= ' (' . $min . ' rides or more)';
		}
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Rides Led'),
				'width' => array(80, 20),
				'align' => array('L', 'R'),
				'title' => $title,
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Rides Led'),
				'width' => array(20, 60, 20),
				'align' => array('C', 'L', 'R'),
				'title' => $title,
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_pre_ly_led($outtype, $sort, $min = 0, $no_id = false) {
    	global $wpdb;
		$where = '';
		if ($min > 0) {
			$where = ' where rides_led >= ' . $min . ' ';
		}
		if ($no_id) {
			$results = $wpdb->get_results(
				'select concat(first_name, \' \', last_name), rides_led from ' . 
				self::PRE_LY_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		else {
			$results = $wpdb->get_results(
				'select member_id, concat(first_name, \' \', last_name), rides_led from ' . 
				self::PRE_LY_LED_VIEW . $where . ' order by ' . $sort , $outtype);
		}
		return $results;
	}

	public static function meta_pre_ly_led($no_id = false) {
		$thisyear = date('Y', current_time('timestamp'));
		$lastyear = intval($thisyear) - 1;
		if ($no_id) {
			$meta = array(
				'header' => array('Name', 'Rides Led'),
				'width' => array(80, 20),
				'align' => array('L', 'R'),
				'title' => 'Pre-' . $lastyear . ' Ride Leaders',
				'date_idx' => -1,
				'id_idx' => -1
			);
		}
		else {
			$meta = array(
				'header' => array('ID', 'Name', 'Rides Led'),
				'width' => array(20, 60, 20),
				'align' => array('C', 'L', 'R'),
				'title' => 'Pre-' . $lastyear . ' Ride Leaders',
				'date_idx' => -1,
				'id_idx' => 0
			);
		}
		return $meta;
	}

	public static function fetch_ytd_rides($outtype, $memberid) {
    	global $wpdb;
    	$results = $wpdb->get_results($wpdb->prepare(
			'select title, date, mileage from ' . 
			self::YTD_RIDES_VIEW . ' where member_id = %s', $memberid), $outtype);
		return $results;
	}

	public static function meta_ytd_rides($name = '') {
		$timestamp = date('M j Y', current_time('timestamp'));
		$meta = array(
			'header' => array('Title', 'Date', 'Mileage'),
			'width' => array(60, 25, 15),
			'align' => array('L', 'R', 'R'),
			'title' => 'Year-to-date Rides Ridden by ' . $name . ' as of ' . $timestamp,
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_ly_rides($outtype, $memberid) {
    	global $wpdb;
    	$results = $wpdb->get_results($wpdb->prepare(
			'select title, date, mileage from ' . 
			self::LY_RIDES_VIEW . ' where member_id = %s', $memberid), $outtype);
		return $results;
	}

	public static function meta_ly_rides($name = '') {
		$thisyear = date('Y', current_time('timestamp'));
    	$lastyear = intval($thisyear) - 1;
		$meta = array(
			'header' => array('Title', 'Date', 'Mileage'),
			'width' => array(60, 25, 15),
			'align' => array('L', 'R', 'R'),
			'title' => $lastyear . ' Rides Ridden by ' . $name,
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_ytd_rides_led($outtype, $memberid) {
    	global $wpdb;
    	$results = $wpdb->get_results($wpdb->prepare(
			'select title, date from ' . 
			self::YTD_RIDES_LED_VIEW . ' where member_id = %s', $memberid), $outtype);
		return $results;
	}

	public static function meta_ytd_rides_led($name = '') {
		$timestamp = date('M j Y', current_time('timestamp'));
		$meta = array(
			'header' => array('Title', 'Date'),
			'width' => array(70, 30),
			'align' => array('L', 'R'),
			'title' => 'Year-to-date Rides Led by ' . $name . ' as of ' . $timestamp,
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_ly_rides_led($outtype, $memberid) {
    	global $wpdb;
    	$results = $wpdb->get_results($wpdb->prepare(
			'select title, date from ' . 
			self::LY_RIDES_LED_VIEW . ' where member_id = %s', $memberid), $outtype);
		return $results;
	}

	public static function meta_ly_rides_led($name = '') {
		$thisyear = date('Y', current_time('timestamp'));
    	$lastyear = intval($thisyear) - 1;
		$meta = array(
			'header' => array('Title', 'Date'),
			'width' => array(70, 30),
			'align' => array('L', 'R'),
			'title' => $lastyear . ' Rides Led by ' . $name,
			'date_idx' => 1,
			'id_idx' => -1
		);
		return $meta;
	}

	public static function fetch_club_rides($title, $fromdate, $todate) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
    	$results = $wpdb->get_results($wpdb->prepare('select * from ' . $ride_table . 
			' where title like %s and date between cast(%s as date) and cast(%s as date) order by date', 
			$title . '%', $fromdate, $todate), ARRAY_A);
		return $results;
	}

	public static function fetch_posts_without_rides($start="", $end="") {
		global $wpdb;
		if ($start) {
			if (!$end) {
				$end = $start;
			}
		}
		else {
			$plugin_options = PwtcMileage::get_plugin_options();
			$thisyear = date('Y', current_time('timestamp'));
			$lastyear = intval($thisyear) - 1;
			$start = '' . $lastyear . '-01-01';
			if ($plugin_options['ride_lookback_date'] != '') {
				$option_date = $plugin_options['ride_lookback_date'];
				if (strtotime($option_date) > strtotime($start)) {
					$start = $option_date;
				}
			}
			$end = date('Y-m-d', current_time('timestamp'));
		}
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$sql_stmt = $wpdb->prepare(
			'select post_id from ' . $ride_table . 
			' where post_id <> 0 and date between %s and %s',
			$start, $end);
		$rides = pwtc_mileage_fetch_posted_rides($start, $end, $sql_stmt);
    	$results = array();
		foreach ($rides as $ride) {
			$postid = $ride[0];
			if (!pwtc_mileage_posted_ride_canceled(intval($postid))) {
				$url = get_permalink(intval($postid));
				if (!$url) {
					$url = "";
				}
				array_push($results, array($ride[0], $ride[1], $ride[2], $url));
			}
		}
		return $results;
	}

	public static function meta_posts_without_rides() {
		$meta = array(
			'header' => array('ID', 'Title', 'Date', 'Actions'),
			'width' => array(),
			'align' => array(),
			'title' => 'Posted Rides without Ride Sheets',
			'date_idx' => 2,
			'id_idx' => 0
		);
		return $meta;
	}

	public static function fetch_posts_without_rides2() {
		$rides = self::fetch_posts_without_rides();
    	$rides_array = array();
		foreach ($rides as $ride) {
			$postid = $ride[0];
			$larray = pwtc_mileage_fetch_ride_leader_names(intval($postid));
			$leaders = '';
			$count = 0;
			foreach ($larray as $item) {
				if ($count > 0) {
					$leaders .= ', ';
				}
				$leaders .= $item;
				$count++;
			}
			array_push($rides_array, array($ride[0], $ride[1], $ride[2], $leaders, $ride[3]));
		}
		return $rides_array;
	}

	public static function meta_posts_without_rides2() {
		$meta = array(
			'header' => array('ID', 'Title', 'Date', 'Leaders', 'Actions'),
			'width' => array(),
			'align' => array(),
			'title' => 'Posted Rides without Ride Sheets',
			'date_idx' => 2,
			'id_idx' => 0
		);
		return $meta;
	}

	public static function fetch_ride($rideid) {
   		global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$results = $wpdb->get_results($wpdb->prepare('select ID, title, date, post_id' . 
			' from ' . $ride_table . ' where ID = %d', $rideid), ARRAY_A);
		return $results;
	}

	public static function fetch_ride_by_post_id($postid) {
   		global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$results = $wpdb->get_results($wpdb->prepare('select ID, title, date, post_id' . 
			' from ' . $ride_table . ' where post_id = %d', $postid), ARRAY_A);
		return $results;
	}

	public static function transfer_mileage_ownership($from_memberid, $to_memberid) {
		global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
	 	$status = $wpdb->query($wpdb->prepare('update ' . $mileage_table . 
			 ' set member_id = %s where member_id = %s', $to_memberid, $from_memberid));
	 	return $status;
	}
	
	public static function purge_mileage($memberid) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $mileage_table . 
			' where member_id = %s', $memberid));
		return $status;
	}

	public static function transfer_leader_ownership($from_memberid, $to_memberid) {
		global $wpdb;
		$show_flag = $wpdb->show_errors;
		if ($show_flag) {
			$wpdb->hide_errors();
		}
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
	 	$status = $wpdb->query($wpdb->prepare('update ' . $leader_table . 
			 ' set member_id = %s where member_id = %s', $to_memberid, $from_memberid));
		if ($show_flag) {
			$wpdb->show_errors();
		}
	 	return $status;
	}
	
	public static function purge_leaders($memberid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $leader_table . 
			' where member_id = %s', $memberid));
		return $status;
	}

	public static function update_ride($rideid, $title, $date, $postid=0) {
		global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
	 	$status = $wpdb->query($wpdb->prepare('update ' . $ride_table . 
			 ' set title = %s, date = %s, post_id = %d where ID = %d', 
			 $title, $date, $postid, $rideid));
	 	return $status;
 	}

	public static function update_ride_post_id($rideid, $postid) {
		global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
	 	$status = $wpdb->query($wpdb->prepare('update ' . $ride_table . 
			 ' set post_id = %d where ID = %d', $postid, $rideid));
	 	return $status;
 	}

	public static function fetch_ride_mileage($rideid) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
    	$results = $wpdb->get_results($wpdb->prepare('select' . 
			' c.member_id, c.first_name, c.last_name, m.mileage, m.line_no' . 
			' from ' . $member_table . ' as c inner join ' . $mileage_table . ' as m' . 
			' on c.member_id = m.member_id where m.ride_id = %d order by m.line_no', 
			$rideid), ARRAY_A);
		return $results;
	}

	public static function fetch_ride_member_mileage($memberid, $rideid) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$results = $wpdb->get_results($wpdb->prepare('select' . 
			' member_id, ride_id, mileage, line_no' . 
			' from ' . $mileage_table . ' where ride_id = %d and member_id = %s', 
			$rideid, $memberid), ARRAY_A);
		return $results;
	}		

	public static function fetch_ride_leaders($rideid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$results = $wpdb->get_results($wpdb->prepare('select' . 
			' c.member_id, c.first_name, c.last_name' . 
			' from ' . $member_table . ' as c inner join ' . $leader_table . ' as l' . 
			' on c.member_id = l.member_id where l.ride_id = %d order by c.last_name, c.first_name', 
			$rideid), ARRAY_A);
		return $results;
	}

	public static function fetch_ride_member_leaders($memberid, $rideid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
    	$results = $wpdb->get_results($wpdb->prepare('select member_id, ride_id, rides_led' . 
			' from ' . $leader_table . ' where ride_id = %d and member_id = %s', 
			$rideid, $memberid), ARRAY_A);
		return $results;
	}

	public static function fetch_ride_has_mileage($rideid) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $mileage_table . 
			' where ride_id = %d', $rideid));
		return $results;
	}

	public static function fetch_ride_has_leaders($rideid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $leader_table . 
			' where ride_id = %d', $rideid));
		return $results;
	}

	public static function fetch_member_has_mileage($memberid) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $mileage_table . 
			' where member_id = %s', $memberid));
		return $results;
	}

	public static function fetch_member_has_leaders($memberid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $leader_table . 
			' where member_id = %s', $memberid));
		return $results;
	}

	public static function fetch_current_time() {
    	global $wpdb;
		$results = $wpdb->get_results('select NOW()', ARRAY_N);
		if (count($results) > 0) {
			return $results[0][0];
		}
		else {
			return '';
		}
	}

	public static function delete_all_nonriders() {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query('delete from ' . $member_table . 
			' where member_id not in (select distinct member_id from ' . $leader_table . ') and' .
			' member_id not in (select distinct member_id from ' . $mileage_table . ')');
		return $status;
	}

	public static function delete_ride($rideid) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $ride_table . 
			' where ID = %d', $rideid));
		return $status;
	}

	public static function delete_ride_leader($rideid, $memberid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $leader_table . 
			' where member_id = %s and ride_id = %d', $memberid, $rideid));
		return $status;
	}

	public static function delete_ride_mileage($rideid, $memberid, $lineno) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $mileage_table . 
			' where member_id = %s and ride_id = %d and line_no = %d',
			$memberid, $rideid, $lineno));
		return $status;
	}

	public static function insert_ride($title, $startdate) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$status = $wpdb->query($wpdb->prepare('insert into ' . $ride_table .
			' (title, date) values (%s, %s)', $title, $startdate));
		return $status;
	}

	public static function insert_ride_with_postid($title, $startdate, $postid) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		/*
		$status = $wpdb->query($wpdb->prepare('insert into ' . $ride_table .
			' (title, date, post_id) values (%s, %s, %d)', $title, $startdate, $postid));
		*/
		$status = $wpdb->insert($ride_table,
			array( 
				'title' => $title, 
				'date' => $startdate,
				'post_id' => $postid
			), 
			array( 
				'%s', 
				'%s',
				'%d' 
			)
		);			
		return $status;
	}

	public static function insert_ride_leader($rideid, $memberid) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$status = $wpdb->query($wpdb->prepare('insert into ' . $leader_table . 
			' (member_id, ride_id, rides_led) values (%s, %d, 1)' . 
			' on duplicate key update rides_led = 1', $memberid, $rideid));
		return $status;
	}

	public static function insert_ride_mileage($rideid, $memberid, $mileage) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query($wpdb->prepare('insert into ' . $mileage_table . 
			' (member_id, ride_id, mileage) values (%s, %d, %d)', 
			$memberid, $rideid, $mileage));
		return $status;
	}

	public static function update_ride_mileage($rideid, $memberid, $lineno, $mileage) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query($wpdb->prepare('update ' . $mileage_table . 
			' set mileage = %d where ride_id = %d and member_id = %s and line_no = %d', 
			$mileage, $rideid, $memberid, $lineno));
		return $status;
	}	

	public static function fetch_riders($lastname, $firstname, $memberid = '', $date = '') {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$sql = $wpdb->prepare('select * from ' . $member_table . 
			' where first_name like %s and last_name like %s and member_id like %s' . 
			' order by last_name, first_name', 
            $firstname . "%", $lastname . "%", $memberid . "%");
		if ($date != '') {
			$sql = $wpdb->prepare('select * from ' . $member_table . 
				' where first_name like %s and last_name like %s and member_id like %s' . 
				' and expir_date > %s order by last_name, first_name', 
				$firstname . "%", $lastname . "%", $memberid . "%", $date);			
		}
    	$results = $wpdb->get_results($sql, ARRAY_A);
		return $results;
	}

	public static function fetch_riders_by_name($lastname, $firstname, $date = '') {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$sql = $wpdb->prepare('select * from ' . $member_table . 
			' where first_name like %s and last_name like %s' . 
			' order by expir_date', $firstname, $lastname);
		if ($date != '') {
			$sql = $wpdb->prepare('select * from ' . $member_table . 
				' where first_name like %s and last_name like %s' . 
				' and expir_date > %s', $firstname, $lastname, $date);
		}
    	$results = $wpdb->get_results($sql, ARRAY_A);
		return $results;
	}

	public static function fetch_rider($memberid) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
    	$results = $wpdb->get_results($wpdb->prepare('select * from ' . $member_table . 
			' where member_id = %s', $memberid), ARRAY_A);
		return $results;
	}

	public static function insert_rider($memberid, $lastname, $firstname, $expdate, $no_update = false) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		if ($no_update) {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $member_table .
				' (member_id, last_name, first_name, expir_date) values (%s, %s, %s, %s)',
				$memberid, $lastname, $firstname, $expdate));
		}
		else {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $member_table .
				' (member_id, last_name, first_name, expir_date) values (%s, %s, %s, %s)' . 
				' on duplicate key update last_name = %s, first_name = %s, expir_date = %s',
				$memberid, $lastname, $firstname, $expdate, $lastname, $firstname, $expdate));
		}
		return $status;
	}

	public static function delete_rider($memberid) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $member_table . 
			' where member_id = %s', $memberid));
		return $status;
	}

	public static function rollup_ridesheets($rideid, $maxdate) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;

		$status1 = $wpdb->query($wpdb->prepare('insert into ' . $mileage_table .
				' (member_id, ride_id, mileage) select c.member_id, %d, SUM(m.mileage)' . 
				' from ((' . $mileage_table . ' as m inner join ' . $member_table . 
				' as c on c.member_id = m.member_id) inner join ' . $ride_table . 
				' as r on m.ride_id = r.ID) where r.ID <> %d and r.date <= %s' . 
				' group by m.member_id', $rideid, $rideid, $maxdate));

		$status2 = $wpdb->query($wpdb->prepare('insert into ' . $leader_table .
				'  (member_id, ride_id, rides_led) select c.member_id, %d, SUM(l.rides_led)' . 
				' from ((' . $leader_table . ' as l inner join ' . $member_table . 
				' as c on c.member_id = l.member_id) inner join ' . $ride_table . 
				' as r on l.ride_id = r.ID) where r.ID <> %d and r.date <= %s' . 
				' group by l.member_id', $rideid, $rideid, $maxdate));

		if (false === $status1) {
			$status = array(
				'error' => 'could not insert new mileages'
			);
		}
		else if (false === $status2) {
			$status = array(
				'error' => 'could not insert new leaders'
			);
		}
		else {
			$status3 = $wpdb->query($wpdb->prepare('delete from ' . $mileage_table . 
					' where ride_id in (select ID from ' . $ride_table . 
					' where ID <> %d and date <= %s)', $rideid, $maxdate));

			$status4 = $wpdb->query($wpdb->prepare('delete from ' . $leader_table . 
					' where ride_id in (select ID from ' . $ride_table . 
					' where ID <> %d and date <= %s)', $rideid, $maxdate));

			$status5 = $wpdb->query($wpdb->prepare('delete from ' . $ride_table . 
					' where ID <> %d and date <= %s', $rideid, $maxdate));

			if (false === $status3) {
				$status = array(
					'error' => 'could not delete old mileages'
				);
			}
			else if (false === $status4) {
				$status = array(
					'error' => 'could not delete old leaders'
				);
			}
			else if (false === $status5 or 0 === $status5) {
				$status = array(
					'error' => 'could not delete old ridesheets'
				);
			}
			else {
				$status = array(
					'm_inserts' => $status1,
					'l_inserts' => $status2,
					'm_deletes' => $status3,
					'l_deletes' => $status4,
					'r_deletes' => $status5
				);
			}
		}
		return $status;
	}

	public static function fetch_members_for_export() {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
    	$results = $wpdb->get_results(
			'select member_id, first_name, last_name, expir_date from ' . $member_table . 
			' order by last_name, first_name', ARRAY_N);
		return $results;
	}

	public static function insert_members_for_restore($data) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$errcnt = 0;
		foreach ( $data as $item ) {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $member_table .
				' (member_id, first_name, last_name, expir_date) values (%s, %s, %s, %s)',
				$item[0], $item[1], $item[2], $item[3]));
			if (false === $status or 0 === $status) {
				$errcnt++;
			}
		}
		return $errcnt;
	}

	public static function delete_members_for_restore() {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$status = $wpdb->query('delete from ' . $member_table);
		return $status;
	}

	public static function load_members_for_restore($filename) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$status = $wpdb->query("LOAD DATA LOCAL INFILE '" . $filename . "'" . 
			" INTO TABLE " . $member_table . " FIELDS TERMINATED BY ','" . 
			" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'" . 
			" (member_id, first_name, last_name, expir_date)");
		return $status;
	}

	public static function load_members_for_update($filename) {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$status = $wpdb->query("LOAD DATA LOCAL INFILE '" . $filename . "'" . 
			" REPLACE INTO TABLE " . $member_table . " FIELDS TERMINATED BY ','" . 
			" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'" . 
			" (member_id, first_name, last_name, expir_date)");
		return $status;
	}

	public static function fetch_rides_for_export() {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
    	$results = $wpdb->get_results(
			'select ID, title, date, post_id from ' . $ride_table . 
			' order by date', ARRAY_N);
		return $results;
	}

	public static function insert_rides_for_restore($data) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$errcnt = 0;
		foreach ( $data as $item ) {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $ride_table .
				' (ID, title, date, post_id) values (%d, %s, %s, %d)', 
				intval($item[0]), $item[1], $item[2], intval($item[3])));
			if (false === $status or 0 === $status) {
				$errcnt++;
			}
		}
		return $errcnt;
	}

	public static function delete_rides_for_restore() {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$status = $wpdb->query('delete from ' . $ride_table);
		return $status;
	}

	public static function load_rides_for_restore($filename) {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$status = $wpdb->query("LOAD DATA LOCAL INFILE '" . $filename . "'" . 
			" INTO TABLE " . $ride_table . " FIELDS TERMINATED BY ','" . 
			" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'" . 
			" (ID, title, date, post_id)");
		return $status;
	}

	public static function fetch_mileage_for_export() {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
    	$results = $wpdb->get_results(
			'select ride_id, member_id, mileage, line_no from ' . $mileage_table . 
			' order by ride_id', ARRAY_N);
		return $results;
	}

	public static function insert_mileage_for_restore($data) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$errcnt = 0;
		foreach ( $data as $item ) {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $mileage_table . 
				' (ride_id, member_id, mileage, line_no) values (%d, %s, %d, %d)', 
				intval($item[0]), $item[1], intval($item[2]), intval($item[3])));
			if (false === $status or 0 === $status) {
				$errcnt++;
			}
		}
		return $errcnt;
	}

	public static function delete_mileage_for_restore() {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query('delete from ' . $mileage_table);
		return $status;
	}

	public static function load_mileage_for_restore($filename) {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$status = $wpdb->query("LOAD DATA LOCAL INFILE '" . $filename . "'" . 
			" INTO TABLE " . $mileage_table . " FIELDS TERMINATED BY ','" . 
			" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'" . 
			" (ride_id, member_id, mileage, line_no)");
		return $status;
	}

	public static function fetch_leaders_for_export() {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
    	$results = $wpdb->get_results(
			'select ride_id, member_id, rides_led from ' . $leader_table . 
			' order by ride_id', ARRAY_N);
		return $results;
	}

	public static function insert_leaders_for_restore($data) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$errcnt = 0;
		foreach ( $data as $item ) {
			$status = $wpdb->query($wpdb->prepare('insert into ' . $leader_table . 
				' (ride_id, member_id, rides_led) values (%d, %s, %d)', 
				intval($item[0]), $item[1], intval($item[2])));
			if (false === $status or 0 === $status) {
				$errcnt++;
			}
		}
		return $errcnt;
	}

	public static function delete_leaders_for_restore() {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$status = $wpdb->query('delete from ' . $leader_table);
		return $status;
	}

	public static function load_leaders_for_restore($filename) {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$status = $wpdb->query("LOAD DATA LOCAL INFILE '" . $filename . "'" . 
			" INTO TABLE " . $leader_table . " FIELDS TERMINATED BY ','" . 
			" OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\n'" . 
			" (ride_id, member_id, rides_led)");
		return $status;
	}

	public static function job_get_all_status() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$result = $wpdb->get_results('select * from ' . $jobs_table, ARRAY_A);
		return $result;
	}

	public static function job_get_status($jobid) {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$result = $wpdb->get_row($wpdb->prepare('select * from ' . $jobs_table . 
			' where job_id = %s', $jobid), ARRAY_A);
		return $result;
	}

	public static function job_set_status($jobid, $status, $errmsg = '') {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$t = time();
		$status = $wpdb->query($wpdb->prepare('insert into ' . $jobs_table .
			' (job_id, status, timestamp, error_msg) values (%s, %s, %d, %s)' . 
			' on duplicate key update status = %s, timestamp = %d, error_msg = %s',
			$jobid, $status, $t, $errmsg, $status, $t, $errmsg));
		return $status;
	}

	public static function num_running_jobs() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select count(*) from ' . $jobs_table . 
			' where status = %s or status = %s',
			self::TRIGGERED_STATUS, self::STARTED_STATUS));
		return $results;
	}

	public static function max_job_timestamp() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$results = $wpdb->get_var($wpdb->prepare('select max(timestamp) from ' . $jobs_table . 
			' where status = %s or status = %s',
			self::TRIGGERED_STATUS, self::STARTED_STATUS));
		return $results;
	}

	public static function job_remove($jobid) {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $jobs_table . 
			' where job_id = %s', $jobid));
		return $status;
	}

	public static function job_remove_failed() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $jobs_table . 
			' where status = %s', self::FAILED_STATUS));
		return $status;
	} 

	public static function job_remove_success() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $jobs_table . 
			' where status = %s', self::SUCCESS_STATUS));
		return $status;
	} 

	public static function job_remove_running() {
    	global $wpdb;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		$status = $wpdb->query($wpdb->prepare('delete from ' . $jobs_table . 
			' where status = %s or status = %s', 
			self::TRIGGERED_STATUS, self::STARTED_STATUS));
		return $status;
	} 

	public static function count_members() {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$results = $wpdb->get_var('select count(member_id) from ' . $member_table);
		return $results;
	}

	public static function count_rides() {
    	global $wpdb;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$results = $wpdb->get_var('select count(ID) from ' . $ride_table);
		return $results;
	}

	public static function count_mileage() {
    	global $wpdb;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$results = $wpdb->get_var('select count(ride_id) from ' . $mileage_table);
		return $results;
	}

	public static function count_leaders() {
    	global $wpdb;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$results = $wpdb->get_var('select count(ride_id) from ' . $leader_table);
		return $results;
	}

	public static function gen_new_member_id() {
    	global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$thisyear = date('y', current_time('timestamp'));
		$results = $wpdb->get_var('select max(convert(substring(member_id, 3), unsigned)) from ' . 			$member_table . ' where member_id like \'' . $thisyear . '%\'');
		$member_id = "";
		if ($results == null) {
			$member_id = $thisyear . "001";
		}
		else if ($results < 999) {
			$member_id = sprintf('%s%03d', $thisyear, ($results + 1));
		}
		return $member_id;
	}

	public static function create_db_tables( ) {
		global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;
		
		$err_cnt = 0;
		$result = $wpdb->query('create table if not exists ' . $member_table . 
			' (member_id VARCHAR(5) NOT NULL,' .
			' last_name TEXT NOT NULL,' . 
			' first_name TEXT NOT NULL,' . 
			' expir_date DATE NOT NULL,' . 
			' constraint pk_' . $member_table . ' PRIMARY KEY (member_id))');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create table ' . $member_table . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create table if not exists ' . $ride_table .
			' (ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,' .
			' title TEXT NOT NULL,' .
			' date DATE NOT NULL,' . 
			' post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,' . 
			' constraint pk_' . $ride_table . ' PRIMARY KEY (ID))');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create table ' . $ride_table . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create table if not exists ' . $mileage_table . 
			' (member_id VARCHAR(5) NOT NULL,' . 
			' ride_id BIGINT UNSIGNED NOT NULL,' . 
			' mileage INT UNSIGNED NOT NULL,' . 
			' line_no BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'.
			' constraint pk_' . $mileage_table . ' PRIMARY KEY (line_no, member_id, ride_id)' . 
			//', constraint fk_' . $mileage_table . '_member_id FOREIGN KEY (member_id) REFERENCES ' . $member_table . ' (member_id)' . 
			//', constraint fk_' . $mileage_table . '_ride_id FOREIGN KEY (ride_id) REFERENCES ' . $ride_table . ' (ID)' . 
			')');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create table ' . $mileage_table . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create table if not exists ' . $leader_table . 
			' (member_id VARCHAR(5) NOT NULL,' . 
			' ride_id BIGINT UNSIGNED NOT NULL,' . 
			' rides_led INT UNSIGNED NOT NULL,' . 
			' constraint pk_' . $leader_table . ' PRIMARY KEY (member_id, ride_id)' . 
			//', constraint fk_' . $leader_table . '_member_id FOREIGN KEY (member_id) REFERENCES ' . $member_table . ' (member_id)' . 
			//', constraint fk_' . $leader_table . '_ride_id FOREIGN KEY (ride_id) REFERENCES ' . $ride_table . ' (ID)' . 
			')');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create table ' . $leader_table . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create table if not exists ' . $jobs_table . 
			' (job_id VARCHAR(20) NOT NULL,' .
			' status TEXT NOT NULL,' . 
			' timestamp BIGINT UNSIGNED NOT NULL,' . 
			' error_msg TEXT,' . 
			' constraint pk_' . $jobs_table . ' PRIMARY KEY (job_id))');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create table ' . $jobs_table . ': ' . $wpdb->last_error);
			$err_cnt++;
		}
		
		return $err_cnt;
	}

	public static function create_db_views( ) {
		global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;

		$err_cnt = 0;
		$result = $wpdb->query('create or replace view ' . self::LT_MILES_VIEW . 
			' (member_id, first_name, last_name, mileage)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(m.mileage)' . 
			' from ' . $member_table . ' as c inner join ' . $mileage_table . ' as m on c.member_id = m.member_id' . 
			' group by m.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LT_MILES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::YTD_MILES_VIEW . 
			' (member_id, first_name, last_name, mileage, rides)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(m.mileage), COUNT(m.mileage)' . 
			' from ((' . $mileage_table . ' as m inner join ' . $member_table . ' as c on c.member_id = m.member_id)' . 
			' inner join ' . $ride_table . ' as r on m.ride_id = r.ID)' . 
			' where r.date >= DATE_FORMAT(CURDATE(), \'%Y-01-01\')' . 
			' group by m.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::YTD_MILES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_MILES_VIEW . 
			' (member_id, first_name, last_name, mileage, rides)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(m.mileage), COUNT(m.mileage)' . 
			' from ((' . $mileage_table . ' as m inner join ' . $member_table . ' as c on c.member_id = m.member_id)' . 
			' inner join ' . $ride_table . ' as r on m.ride_id = r.ID)' . 
			' where r.date between DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' and DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-12-31\')' . 
			' group by m.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_MILES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_LT_MILES_VIEW . 
			' (member_id, first_name, last_name, mileage)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(m.mileage)' . 
			' from ((' . $mileage_table . ' as m inner join ' . $member_table . ' as c on c.member_id = m.member_id)' . 
			' inner join ' . $ride_table . ' as r on m.ride_id = r.ID)' . 
			' where r.date < DATE_FORMAT(CURDATE(), \'%Y-01-01\')' . 
			' group by m.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_LT_MILES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::YBL_LT_MILES_VIEW . 
			' (member_id, first_name, last_name, mileage)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(m.mileage)' . 
			' from ((' . $mileage_table . ' as m inner join ' . $member_table . ' as c on c.member_id = m.member_id)' . 
			' inner join ' . $ride_table . ' as r on m.ride_id = r.ID)' . 
			' where r.date < DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' group by m.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::YBL_LT_MILES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_LT_ACHVMNT_VIEW . 
			' (member_id, first_name, last_name, mileage, achievement, nachievement)' . 
			' as select a.member_id, a.first_name, a.last_name, a.mileage,' .
			' concat(floor(a.mileage/10000),\'0K\'), floor(a.mileage/10000)' . 
			' from ' . self::LY_LT_MILES_VIEW . ' as a inner join ' . self::YBL_LT_MILES_VIEW . ' as b on a.member_id = b.member_id' . 
			' where floor(a.mileage/10000) > floor(b.mileage/10000)');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_LT_ACHVMNT_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::YTD_RIDES_LED_VIEW . 
			' (title, date, member_id)' . 
			' as select r.title, r.date, l.member_id' . 
			' from ' . $ride_table . ' as r inner join ' . $leader_table . ' as l on r.ID = l.ride_id' . 
			' where r.date >= DATE_FORMAT(CURDATE(), \'%Y-01-01\')' . 
			' order by r.date');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::YTD_RIDES_LED_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_RIDES_LED_VIEW . 
			' (title, date, member_id)' . 
			' as select r.title, r.date, l.member_id' .
			' from ' . $ride_table . ' as r inner join ' . $leader_table . ' as l on r.ID = l.ride_id' . 
			' where r.date between DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' and DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-12-31\')' . 
			' order by r.date');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_RIDES_LED_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::YTD_LED_VIEW . 
			' (member_id, first_name, last_name, rides_led)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(l.rides_led)' . 
			' from ((' . $leader_table . ' as l inner join ' . $member_table . ' as c on c.member_id = l.member_id)' . 
			' inner join ' . $ride_table . ' as r on l.ride_id = r.ID)' . 
			' where r.date >= DATE_FORMAT(CURDATE(), \'%Y-01-01\')' . 
			' group by l.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::YTD_LED_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_LED_VIEW . 
			'(member_id, first_name, last_name, rides_led)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(l.rides_led)' . 
			' from ((' . $leader_table . ' as l inner join ' . $member_table . ' as c on c.member_id = l.member_id)' . 
			' inner join ' . $ride_table . ' as r on l.ride_id = r.ID)' . 
			' where r.date between DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' and DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-12-31\')' . 
			' group by l.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_LED_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::PRE_LY_LED_VIEW . 
			'(member_id, first_name, last_name, rides_led)' . 
			' as select c.member_id, c.first_name, c.last_name, SUM(l.rides_led)' . 
			' from ((' . $leader_table . ' as l inner join ' . $member_table . ' as c on c.member_id = l.member_id)' . 
			' inner join ' . $ride_table . ' as r on l.ride_id = r.ID)' . 
			' where r.date < DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' group by l.member_id');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::PRE_LY_LED_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::YTD_RIDES_VIEW . 
			' (title, date, mileage, member_id, ride_id)' . 
			' as select r.title, r.date, m.mileage, m.member_id, r.ID' . 
			' from ' . $ride_table . ' as r inner join ' . $mileage_table . ' as m on r.ID = m.ride_id' . 
			' where r.date >= DATE_FORMAT(CURDATE(), \'%Y-01-01\')' . 
			' order by r.date'); 
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::YTD_RIDES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		$result = $wpdb->query('create or replace view ' . self::LY_RIDES_VIEW . 
			' (title, date, mileage, member_id, ride_id)' . 
			' as select r.title, r.date, m.mileage, m.member_id, r.ID' . 
			' from ' . $ride_table . ' as r inner join ' . $mileage_table . ' as m on r.ID = m.ride_id' . 
			' where r.date between DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-01-01\')' . 
			' and DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR), \'%Y-12-31\')' . 
			' order by r.date');
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not create view ' . self::LY_RIDES_VIEW . ': ' . $wpdb->last_error);
			$err_cnt++;
		}

		return $err_cnt;
	}

	public static function drop_db_tables( ) {
		global $wpdb;
		$member_table = $wpdb->prefix . self::MEMBER_TABLE;
		$ride_table = $wpdb->prefix . self::RIDE_TABLE;
		$mileage_table = $wpdb->prefix . self::MILEAGE_TABLE;
		$leader_table = $wpdb->prefix . self::LEADER_TABLE;
		$jobs_table = $wpdb->prefix . self::JOBS_TABLE;

		$result = $wpdb->query('drop table if exists ' . $leader_table . ', ' . $mileage_table . ', ' . $ride_table . ', ' . $member_table . ', ' . $jobs_table);
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not drop tables: ' . $wpdb->last_error);
		}
	}

	public static function drop_db_views( ) {
		global $wpdb;
		$result = $wpdb->query('drop view if exists ' . self::LY_RIDES_VIEW . ', ' . self::YTD_RIDES_VIEW . ', ' . self::LY_LED_VIEW . ', ' . self::PRE_LY_LED_VIEW .
			', ' . self::YTD_LED_VIEW . ', ' . self::LY_RIDES_LED_VIEW . ', ' . self::YTD_RIDES_LED_VIEW . ', ' . self::LY_LT_ACHVMNT_VIEW . 
			', ' . self::YBL_LT_MILES_VIEW . ', ' . self::LY_LT_MILES_VIEW . ', ' . self::LY_MILES_VIEW . ', ' . self::YTD_MILES_VIEW . 
			', ' . self::LT_MILES_VIEW);
		if (false === $result) {
			pwtc_mileage_write_log( 'Could not drop views: ' . $wpdb->last_error);
		}
	}

	public static function get_db_version() {
		return get_option('pwtc_mileage_db_version');
	}

	public static function set_db_version() {
		$installed_ver = get_option('pwtc_mileage_db_version');
		if ($installed_ver == false) {
			add_option('pwtc_mileage_db_version', PWTC_MILEAGE__DB_VERSION);
		}
		else {
			update_option('pwtc_mileage_db_version', PWTC_MILEAGE__DB_VERSION);
		}
	}

	public static function delete_db_version() {
		delete_option('pwtc_mileage_db_version');
	}

	public static function handle_db_upgrade() {
		global $wpdb;
		$err_cnt = 0;
		$installed_ver = self::get_db_version();
		if ($installed_ver == false) {
			pwtc_mileage_write_log( 'Upgrading PWTC Mileage DB version from 1.1 to 1.2');
			$result = $wpdb->query('drop view if exists ' . self::LY_RIDES_VIEW . ', ' .
				self::YTD_RIDES_VIEW . ', ' . self::LY_MILES_VIEW . ', ' . 
				self::YTD_MILES_VIEW);
			if (false === $result) {
				pwtc_mileage_write_log( 'Could not drop views ' . self::LY_RIDES_VIEW . ', ' .
				self::YTD_RIDES_VIEW . ', ' . self::LY_MILES_VIEW . ', ' . 
				self::YTD_MILES_VIEW . ': ' . $wpdb->last_error);
				$err_cnt++;
			}
		}
		return $err_cnt;
	}
   
}
