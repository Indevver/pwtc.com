<?php

function pwtc_members_strip_phone_number($phoneNumber) {
    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);
    return $phoneNumber;
}

function pwtc_members_format_phone_number($phoneNumber) {
    $phoneNumber = pwtc_members_strip_phone_number($phoneNumber);
    if (strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);
        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if (strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);
        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if (strlen($phoneNumber) == 7) {
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);
        $phoneNumber = $nextThree.'-'.$lastFour;
    }
    return $phoneNumber;
}

function pwtc_members_is_expired($membership) {
    $is_expired = false;
    $team = false;
    if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
        $team = wc_memberships_for_teams_get_user_membership_team($membership->get_id());
    }
    if ($team) {
        if ($team->is_membership_expired()) {
            $is_expired = true;
        }
    }
    else {
        if ($membership->is_expired()) {
            $is_expired = true;
        }
    }
    return $is_expired;
}

function pwtc_members_get_expiration_date($membership) {
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

function pwtc_members_lookup_user($rider_id, $lastname = '', $firstname = '', $email = '', $exact = true) {
    $compare = 'LIKE';
    if ($exact) {
        $compare = '=';
    }
    $query_args = [
        'meta_key' => 'last_name',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ];
    $query_args['meta_query'] = [];
    if (!empty($lastname)) {
        $query_args['meta_query'][] = [
            'key'     => 'last_name',
            'value'   => $lastname,
            'compare' => $compare   
        ];
    }
    if (!empty($firstname)) {
        $query_args['meta_query'][] = [
            'key'     => 'first_name',
            'value'   => $firstname,
            'compare' => $compare 
        ];
    }
    if (!empty($email)) {
        $str = $email;
        if (!$exact) {
            $str = '*' . $str . '*';
        }
        $query_args['search'] = $str;
        $query_args['search_columns'] = array( 'user_email' );
    }
    if (!empty($rider_id)) {
        $query_args['meta_query'][] = [
            'key'     => 'rider_id',
            'value'   => $rider_id,
            'compare' => $compare 
        ];
    }
    /*
    else if (empty($lastname) and empty($firstname)) {
        $query_args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => 'rider_id',
                'compare' => 'NOT EXISTS' 
            ],
            [
                'key'     => 'rider_id',
                'value'   => ''    
            ] 
        ];
    }
    */
    $user_query = new WP_User_Query( $query_args );
    $results = $user_query->get_results();
    return $results;
}
