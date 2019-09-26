<?php

/*
Returns an array of arrays that contains the membership list. 
The interor array contains a membership record structured thus:
array[0] - member ID (string)
array[1] - first name (string)
array[2] - last name (string)
array[3] - expiration date (string with PHP date format 'Y-m-d')
*/
function pwtc_mileage_fetch_membership() {
    return array();
}

/*
Given the user's email address, this function looks up the CiviCRM contact record 
and returns it's rider ID. Null is returned if no rider ID is set or is valid. Before this
function is used you must first initialize the CiviCRM API by calling civicrm_initialize().
*/
function pwtc_mileage_fetch_civi_member_id($email) {
    $member_id = null;
    if (function_exists('civicrm_api3')) {
        $result = civicrm_api3('contact', 'get', array(
            'sequential' => 1,
            'contact_type' => 'Individual',
            'email' => $email
        ));
        if ($result['values']) {
            $contact_id = $result['values'][0]['contact_id'];
            $result = civicrm_api3('CustomValue', 'get', array(
                'sequential' => 1,
                'entity_id' => $contact_id,
                'return.custom_5' => 1
            ));
            if ($result['values']) {
                $member_id = trim($result['values'][0]['latest']);
                if (strlen($member_id) == 0) {
                    $member_id = null;    
                }
                else {
                    if (PwtcMileage::validate_member_id_str($member_id)) {
                        $result = PwtcMileage_DB::fetch_rider($member_id); 
                        if (count($result) == 0) {
                            $member_id = null;
                        } 
                    } 
                    else {
                        $member_id = null;
                    }   
                }
            }
        }
    }
    return $member_id;
}

function pwtc_mileage_insert_new_rider($lastname, $firstname, $expdate) {
    if (!PwtcMileage::validate_date_str($expdate)) {
        throw new Exception('Cannot create new rider, expiration date is invalid.');
    }
    else if (!PwtcMileage::validate_member_name_str($firstname)) {
        throw new Exception('Cannot create new rider, first name must begin with letter.');
    }
    else if (!PwtcMileage::validate_member_name_str($lastname)) {
        throw new Exception('Cannot create new rider, last name must begin with letter.');
    }
    else {
        $rider_id = PwtcMileage_DB::gen_new_member_id();
        if (PwtcMileage::validate_member_id_str($rider_id)) {
            $status = PwtcMileage_DB::insert_rider(
                $rider_id, $lastname, $firstname, $expdate, true);
            if (false === $status or 0 === $status) {
                throw new Exception('Cannot create new rider, database insert failed.');
            }
        }
        else {
            throw new Exception('Cannot create new rider, generated rider ID is invalid.');
        }
    }
    return $rider_id;
}

function pwtc_mileage_update_rider($rider_id, $lastname, $firstname, $expdate) {
    if (!PwtcMileage::validate_member_id_str($rider_id)) {
        throw new Exception('Cannot update rider, rider ID is invalid.');
    }
    else if (!PwtcMileage::validate_date_str($expdate)) {
        throw new Exception('Cannot update rider, expiration date is invalid.');
    }
    else if (!PwtcMileage::validate_member_name_str($firstname)) {
        throw new Exception('Cannot update rider, first name must begin with letter.');
    }
    else if (!PwtcMileage::validate_member_name_str($lastname)) {
        throw new Exception('Cannot update rider, last name must begin with letter.');
    }
    else {
        if (count(PwtcMileage_DB::fetch_rider($rider_id)) == 0) {
            throw new Exception('Cannot update rider, rider ' . $rider_id . ' not found.');
        }
        else {
            $status = PwtcMileage_DB::insert_rider(
                $rider_id, $lastname, $firstname, $expdate);
            if (false === $status) {
                throw new Exception('Cannot update rider, database update failed.');
            }
        }
    }
}

function pwtc_mileage_delete_rider($rider_id) {
    if (PwtcMileage::validate_member_id_str($rider_id)) {
        if (count(PwtcMileage_DB::fetch_rider($rider_id)) == 0) {
            throw new Exception('Cannot delete rider, rider ' . $rider_id . ' not found.');
        }
        else if (PwtcMileage_DB::fetch_member_has_mileage($rider_id) > 0) {
            throw new Exception('Cannot delete rider, rider has recorded mileage.');
        }
        else if (PwtcMileage_DB::fetch_member_has_leaders($rider_id) > 0) {
            throw new Exception('Cannot delete rider, rider is a recorded ride leader.');            
        }
        else {
            $status = PwtcMileage_DB::delete_rider($rider_id);
            if (false === $status or 0 === $status) {
                throw new Exception('Cannot delete rider, database delete failed.');
            }
        }
    }
    else {
        throw new Exception('Cannot delete rider, rider ID is invalid.');
    }
}

/*
Returns a string that contains the member ID of the logged on user.
(Throws an exception if the user is not logged on or his member ID is not set.)
*/
function pwtc_mileage_get_member_id($check_membership = false) {
    $id = null;
    $current_user = wp_get_current_user();
    if ( 0 == $current_user->ID ) {
        throw new Exception('notloggedin');
    }
    else {
        $plugin_options = PwtcMileage::get_plugin_options();
        $mode = $plugin_options['user_lookup_mode'];
        if ($mode == 'woocommerce') {
            if ($check_membership) {
                if (function_exists('wc_memberships_get_user_memberships')) {
                    $memberships = wc_memberships_get_user_memberships($current_user->ID);
                    if (empty($memberships)) {
                        throw new Exception('notmember');
                    }
                    else if (count($memberships) > 1) {
                        throw new Exception('multimember');
                    }
                }
            }
            $rider_id = get_field('rider_id', 'user_'.$current_user->ID);
            if (!$rider_id) {
                $rider_id = '';
            }
            if (empty($rider_id)) {
                throw new Exception('idnotset');
            }
            if (PwtcMileage::validate_member_id_str($rider_id)) {
                $result = PwtcMileage_DB::fetch_rider($rider_id); 
                if (count($result) == 0) {
                    throw new Exception('idnotfound');
                } 
                $id = $rider_id;
            } 
            else {
                throw new Exception('idnotfound');
            }
        }
        else if ($mode == 'civicrm') {
            if (function_exists('civicrm_initialize')) {
                civicrm_initialize();
                $id = pwtc_mileage_fetch_civi_member_id($current_user->user_email);
                if (!$id) {
                    throw new Exception('idnotfound');
                }
            }
            else {
                throw new Exception('idnotfound');
            }
        }
        else {
            $test_date = PwtcMileage::get_date_for_expir_check();
            $result = PwtcMileage_DB::fetch_riders_by_name(trim($current_user->user_lastname), 
                trim($current_user->user_firstname), $test_date);
            $count = count($result);
            if ($count == 0) {
                throw new Exception('idnotfound');
            }
            else if ($count > 1) {
                throw new Exception('multidfound');
            }
            $id = $result[0]['member_id'];
        }
    }
    return $id;
}

function pwtc_mileage_get_rider_card_info($user_id, $rider_id = '') {
    $plugin_options = PwtcMileage::get_plugin_options();
    $mode = $plugin_options['user_lookup_mode'];
    if ($mode == 'woocommerce') {
        if ($user_id == 0) {
            return false;
        }
        $userdata = get_userdata($user_id);
        if ($userdata === false) {
            return false;
        }
        $lastname = $userdata->last_name;
        $firstname = $userdata->first_name;
        $family_id = '';
        if (function_exists('wc_memberships_get_user_memberships')) {
            $memberships = wc_memberships_get_user_memberships($user_id);
            if (!empty($memberships)) {
                $membership = $memberships[0];
                $exp_date = pwtc_mileage_get_expiration_date($membership);
                if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
                    $team = wc_memberships_for_teams_get_user_membership_team($membership->get_id());
                    if ($team) {
                        $owner_id = $team->get_owner_id();
                        //if ($owner_id != $user_id) {
                            $id = get_field('rider_id', 'user_'.$owner_id);
                            if ($id) {
                                $family_id = $id;
                            }
                        //}
                    }
                }
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }
    else {
        if (empty($rider_id)) {
            return false;
        }
        $result = PwtcMileage_DB::fetch_rider($rider_id);
        if (count($result) == 0) {
            return false;
        }
        $lastname = $result[0]['last_name'];
        $firstname = $result[0]['first_name'];
        $exp_date = $result[0]['expir_date'];
        $family_id = '';
    }
    $result = array(
        'last_name' => $lastname,
        'first_name' => $firstname,
        'expir_date' => $exp_date,
        'family_id' => $family_id
    );
    return $result;
}

function pwtc_mileage_get_expiration_date($membership) {
    $team = false;
    if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
        $team = wc_memberships_for_teams_get_user_membership_team($membership->get_id());
    }
    if ($team) {
        $datetime = $team->get_local_membership_end_date('mysql');
        $pieces = explode(' ', $datetime);
        $exp_date = $pieces[0];
    }
    else {
        if ($membership->has_end_date()) {
            $datetime = $membership->get_local_end_date('mysql', false);
            $pieces = explode(' ', $datetime);
            $exp_date = $pieces[0];
        }
        else {
            $exp_date = '2099-01-01';
        }
    }
    return $exp_date;
}

function pwtc_mileage_lookup_user($rider_id) {
    $query_args = [
        'meta_key' => 'last_name',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ];
    $query_args['meta_query'] = [];
    $query_args['meta_query'][] = [
        'key'     => 'rider_id',
        'value'   => $rider_id,
        'compare' => '='
    ];
    $user_query = new WP_User_Query( $query_args );
    $results = $user_query->get_results();
    return $results;
}

/*
Returns an array of arrays that contains the posted rides without ridesheets. 
The interor array contains a posted ride record structured thus:
array[0] - post ID (string)
array[1] - title (string)
array[2] - start date (string with PHP date format 'Y-m-d')
*/
function pwtc_mileage_fetch_posted_rides($start_date, $end_date, $exclude_sql="") {
    global $wpdb;
    $ride_post_type = 'scheduled_rides';
    $ride_date_metakey = 'date';
    $select_sql = "";
    if ($exclude_sql) {
        $select_sql = " and p.ID not in (" . $exclude_sql . ")";
    }
    $sql_stmt = $wpdb->prepare(
        'select p.ID, p.post_title, date_format(m.meta_value, %s) as start_date' . 
        ' from ' . $wpdb->posts . ' as p inner join ' . $wpdb->postmeta . 
        ' as m on p.ID = m.post_id where p.post_type = %s and p.post_status = \'publish\'' . 
        ' and m.meta_key = %s and (cast(m.meta_value as date) between %s and %s)' . 
        $select_sql . ' order by m.meta_value', 
        '%Y-%m-%d', $ride_post_type, $ride_date_metakey, $start_date, $end_date);
    $results = $wpdb->get_results($sql_stmt, ARRAY_N);
    return $results;
}

/*
Returns an array of arrays that contains the posted ride. 
The interor array contains a posted ride record structured thus:
array[0] - post ID (string)
array[1] - title (string)
array[2] - start date (string with PHP date format 'Y-m-d')
*/
function pwtc_mileage_fetch_posted_ride($post_id) {
    global $wpdb;
    $ride_post_type = 'scheduled_rides';
    $ride_date_metakey = 'date';
    $sql_stmt = $wpdb->prepare(
        'select p.ID, p.post_title, date_format(m.meta_value, %s) as start_date' . 
        ' from ' . $wpdb->posts . ' as p inner join ' . $wpdb->postmeta . 
        ' as m on p.ID = m.post_id where p.ID = %d and p.post_type = %s' . 
        ' and p.post_status = \'publish\' and m.meta_key = %s order by m.meta_value', 
        '%Y-%m-%d', $post_id, $ride_post_type, $ride_date_metakey);
    $results = $wpdb->get_results($sql_stmt, ARRAY_N);
    return $results;
}

function pwtc_mileage_posted_ride_canceled($post_id) {
    $ride_canceled_metakey = 'is_canceled';
    return get_field($ride_canceled_metakey, $post_id);
}

/*
Returns an array that contains the rider ids of the ride leaders of the posted ride. 
*/
function pwtc_mileage_fetch_ride_leader_ids($post_id) {
    $leaders_array = array();
    if (function_exists('get_field')) {

        $leaders = get_field('ride_leaders', $post_id);
        if ($leaders) {
            $plugin_options = PwtcMileage::get_plugin_options();
            $mode = $plugin_options['user_lookup_mode'];
            if ($mode == 'woocommerce') {    
                foreach ($leaders as $leader) {
                    $rider_id = get_field('rider_id', 'user_'.$leader['ID']);
                    if (!$rider_id) {
                        $rider_id = '';
                    }        
                    if (PwtcMileage::validate_member_id_str($rider_id)) {
                        $result = PwtcMileage_DB::fetch_rider($rider_id); 
                        if (count($result) > 0) {
                            array_push($leaders_array, $rider_id);
                        } 
                    } 
                }
            }
            else if ($mode == 'civicrm') {
                if (function_exists('civicrm_initialize')) {
                    civicrm_initialize();
                    foreach ($leaders as $leader) {
                        $id = pwtc_mileage_fetch_civi_member_id($leader['user_email']);
                        if ($id) {
                            array_push($leaders_array, $id);
                        }
                    }
                }
            }
            else {
                $test_date = PwtcMileage::get_date_for_expir_check();
                foreach ($leaders as $leader) {
                    $fname = $leader['user_firstname'];
                    $lname = $leader['user_lastname'];
                    $result = PwtcMileage_DB::fetch_riders_by_name(trim($lname), trim($fname), $test_date);
                    if (count($result) == 1) {
                        $id = $result[0]['member_id'];
                        array_push($leaders_array, $id);
                    }
                }                    
            }
        }
    }
    return $leaders_array;
}

/*
Returns an array that contains the names of the ride leaders of the posted ride. 
*/
function pwtc_mileage_fetch_ride_leader_names($post_id) {
    $leaders_array = array();
    if (function_exists('get_field')) {

        $leaders = get_field('ride_leaders', $post_id);
        if ($leaders) {
            foreach ($leaders as $leader) {
                $fname = $leader['user_firstname'];
                $lname = $leader['user_lastname'];
                $name = $fname . ' ' . $lname;
                array_push($leaders_array, $name);
            }
        }
    }
    return $leaders_array;
}

function pwtc_mileage_create_stat_role() {
    $stat = get_role('statistician');
    if ($stat === null) {
        $stat = add_role('statistician', 'Statistician');
        pwtc_mileage_write_log('PWTC Mileage plugin added statistician role');
    }
    if ($stat !== null) {
        $stat->add_cap(PwtcMileage::VIEW_MILEAGE_CAP);
        $stat->add_cap(PwtcMileage::EDIT_MILEAGE_CAP);
        $stat->add_cap(PwtcMileage::EDIT_RIDERS_CAP);
        $stat->add_cap(PwtcMileage::DB_OPS_CAP);
        pwtc_mileage_write_log('PWTC Mileage plugin added capabilities to statistician role');
    } 
    $captain = get_role('ride_captain'); 
    if ($captain !== null) {
        $captain->add_cap(PwtcMileage::VIEW_MILEAGE_CAP);
        pwtc_mileage_write_log('PWTC Mileage plugin added capabilities to ride_captain role');
    } 
}

function pwtc_mileage_remove_stat_role() {
    $users = get_users(array('role' => 'statistician'));
    if (count($users) > 0) {
        $stat = get_role('statistician');
        $stat->remove_cap(PwtcMileage::VIEW_MILEAGE_CAP);
        $stat->remove_cap(PwtcMileage::EDIT_MILEAGE_CAP);
        $stat->remove_cap(PwtcMileage::EDIT_RIDERS_CAP);
        $stat->remove_cap(PwtcMileage::DB_OPS_CAP);
        pwtc_mileage_write_log('PWTC Mileage plugin removed capabilities from statistician role');
    }
    else {
        $stat = get_role('statistician');
        if ($stat !== null) {
            remove_role('statistician');
            pwtc_mileage_write_log('PWTC Mileage plugin removed statistician role');
        }
    }
    $captain = get_role('ride_captain'); 
    if ($captain !== null) {
        $captain->remove_cap(PwtcMileage::VIEW_MILEAGE_CAP);
        pwtc_mileage_write_log('PWTC Mileage plugin removed capabilities to ride_captain role');
    } 
}

function pwtc_mileage_ridesheet_exists($post_id) {
    if ($post_id > 0) {
        $data = PwtcMileage_DB::fetch_ride_by_post_id($post_id);
        if (count($data) > 0) {
            return true;
        }
    }
    return false;
}

function pwtc_mileage_ridesheet_status($post_id) {
    $msg = false;
    if (current_user_can(PwtcMileage::VIEW_MILEAGE_CAP)) {
        if ($post_id > 0) {
            $data = pwtc_mileage_fetch_posted_ride($post_id);
            if (count($data) > 0) {
                $post_title = $data[0][1];
                $post_date = $data[0][2];
                $data = PwtcMileage_DB::fetch_ride_by_post_id($post_id);
                if (count($data) > 1) {
                    $msg = 'Multiple ride sheets are linked to this ride:<br/>';
                    $type = 'alert';
                    foreach( $data as $row ):
                        $msg .= '"' . $row['title'] . '" on ' . date('D M j Y', strtotime($row['date'])) . '<br/>';
                    endforeach;
                }
                else if (count($data) > 0) {
                    if ($post_date <> $data[0]['date'])	{
                        $msg = 'The date of this ride does not match date of linked ride sheet "' . $data[0]['title'] . '" on ' . date('D M j Y', strtotime($data[0]['date'])) . '.';
                        $type = 'alert';
                    }
                    else if ($post_title <> $data[0]['title'])	{
                        $msg = 'The title of this ride does not match title of linked ride sheet "' . $data[0]['title'] . '" on ' . date('D M j Y', strtotime($data[0]['date'])) . '.';
                        $type = 'warning';
                    }
                    else {
                        $rideid = intval($data[0]['ID']);
                        $mcnt = PwtcMileage_DB::fetch_ride_has_mileage($rideid);
                        $lcnt = PwtcMileage_DB::fetch_ride_has_leaders($rideid);
                        $msg = 'This ride has a linked ride sheet with ' . $lcnt . ' leaders and ' . $mcnt . ' riders.';
                        $type = 'success';
                    }
                }
                else {
                    $msg = 'This ride has no ride sheet.';
                    $type = 'success';
                }
            }
        }
    }
    $result = false;
    if ($msg) {
        $result = array(
            'callout_type' => $type,
            'callout_msg' => $msg);
    }
    return $result;
}

if (!function_exists('pwtc_mileage_write_log')) {
    function pwtc_mileage_write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }
}