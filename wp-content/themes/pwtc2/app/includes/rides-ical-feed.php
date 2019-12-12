<?php

function echo_ical_longline($tag, $line) {
    // Wordwrap function deletes the spaces it wraps on - to protect,
    // replace spaces with ASCII non-printing character SUB.
    $output = str_replace(' ', chr(26), $tag.':'.$line);

    // Break long strings at 70 characters.
    $output = wordwrap($output, 70, "\r\n ", true);

    // Restore protected spaces.
    $output = str_replace(chr(26), ' ', $output);

    echo $output;
    echo "\r\n";
}

function echo_ical_shortline($tag, $line) {
    echo $tag;
    echo ":";
    echo $line;
    echo "\r\n";
}

function echo_ical_rideevent() {
    echo_ical_shortline('BEGIN', 'VEVENT');

    $tstamp = get_gmt_from_date(get_the_modified_date('Y-m-d H:i:s'));
    $timestamp = date('Ymd\THis\Z', strtotime($tstamp));
    $datetime = get_gmt_from_date(get_field('date'));
    $icaltime = date('Ymd\THis\Z', strtotime($datetime));
    echo_ical_shortline('UID', 'post_'.get_the_id().'@pwtc.com');
    echo_ical_shortline('DTSTAMP', $timestamp);
    echo_ical_shortline('DTSTART', $icaltime);

    $status = 'CONFIRMED';
    if (get_field('is_canceled')) {
        $status = 'CANCELLED';
    }
    echo_ical_shortline('STATUS', $status);

    $location = get_field('start_location');
    echo_ical_longline('LOCATION', $location['address']);
    echo_ical_shortline('GEO', $location['lat'].';'.$location['lng']);

    echo_ical_longline('SUMMARY', html_entity_decode(get_the_title()));

    $desc = 'Portland Bicycling Club\n';
    $desc .= date('l, F j \a\t g:i A', strtotime(get_field('date'))) . '\n\n';
    $desc .= 'Please join us for a bike ride, all are welcome!\n';
    $desc .= 'For details see:\n';
    $desc .= get_the_permalink();
    echo_ical_longline('DESCRIPTION', $desc);
        
    echo_ical_longline('URL', get_the_permalink());

    echo_ical_shortline('END', 'VEVENT');    
}

function query_ical_upcoming_rides($days) {
    $today = new DateTime(null, new DateTimeZone(pwtc_get_timezone_string()));
    $later = clone $today;
    $later->add(new DateInterval('P'.$days.'D'));
    $query = [
        'posts_per_page' => -1,
        'post_type' => 'scheduled_rides',
        'meta_query' => [
            [
                'key' => 'date',
                'value' =>  [$today->format('Y-m-d 00:00:00'), $later->format('Y-m-d 23:59:59')],
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            ],
        ],
        'orderby' => ['date' => 'ASC'],
    ];  
    return $query;
}

function query_ical_monthly_rides($month) {
    $timezone = new DateTimeZone(pwtc_get_timezone_string());
    $first_day = new DateTime($month.'-01', $timezone);
    $last_day = new DateTime($first_day->format('Y-m-t'), $timezone);
    $query = [
        'posts_per_page' => -1,
        'post_type' => 'scheduled_rides',
        'meta_query' => [
            [
                'key' => 'date',
                'value' =>  [$first_day->format('Y-m-d 00:00:00'), $last_day->format('Y-m-d 23:59:59')],
                'compare' => 'BETWEEN',
                'type' => 'DATETIME'
            ],
        ],
        'orderby' => ['date' => 'ASC'],
    ];  
    return $query;
}

add_action('init', function() {
    add_feed('ical-rides', function() {

        header('Content-type: text/calendar');
        header('Content-Disposition: attachment; filename="pwtc-rides.ics"');

        echo_ical_shortline('BEGIN', 'VCALENDAR');
        echo_ical_shortline('VERSION', '2.0');
        echo_ical_shortline('PRODID', '-//PWTC//PWTC Ride Calendar//EN');
        echo_ical_shortline('CALSCALE', 'GREGORIAN');

        $query_args = false;
        if(isset($_GET['month']) && $_GET['month']) {
            $month = $_GET['month'];
            if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
                $query_args = query_ical_monthly_rides($month);
            }
        }
        else {
            if(isset($_GET['days']) && $_GET['days']) {
                $days = $_GET['days'];
                if (is_numeric($days) and intval($days) > 0) {
                    $query_args = query_ical_upcoming_rides($days);
                }
            }
            else {
                $query_args = query_ical_upcoming_rides('7');
            }
        }

        if ($query_args) {
            $rides_query = new WP_Query($query_args);
            while($rides_query->have_posts()) {
                $rides_query->the_post();
                echo_ical_rideevent();       
            }
            wp_reset_query();
        }

        echo_ical_shortline('END', 'VCALENDAR');

    });

    add_feed('ical-ride', function() {

        header('Content-type: text/calendar');
        header('Content-Disposition: attachment; filename="pwtc-rides.ics"');

        echo_ical_shortline('BEGIN', 'VCALENDAR');
        echo_ical_shortline('VERSION', '2.0');
        echo_ical_shortline('PRODID', '-//PWTC//PWTC Ride Calendar//EN');
        echo_ical_shortline('CALSCALE', 'GREGORIAN');

        $post_type = get_post_type();
        if ($post_type == 'scheduled_rides') {
            echo_ical_rideevent();
        }

        echo_ical_shortline('END', 'VCALENDAR');

    });
});
