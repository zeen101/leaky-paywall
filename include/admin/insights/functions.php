<?php

add_action( 'wp_ajax_lp_reports_get_data', 'lp_reports_get_data' );

function lp_reports_get_data() {

    if ( ! check_ajax_referer( 'process_lp_insights' ) ) {
        wp_send_json( array( 'error' => 'There was an error.' ) );
    }

    $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '';

    // get total revenue data
    $revenue = leaky_paywall_insights_get_total_revenue( $period );
    $new_paid_subs = leaky_paywall_insights_get_new_paid_subs( $period );
    $new_free_subs = leaky_paywall_insights_get_new_free_subs( $period );
    $paid_content = leaky_paywall_insights_get_paid_content( $period );
    $free_content = leaky_paywall_insights_get_free_content( $period );

    wp_send_json(
        array(
            'total_revenue' => $revenue,
            'new_paid_subs' => $new_paid_subs,
            'new_free_subs' => $new_free_subs,
            'paid_content' => $paid_content,
            'free_content' => $free_content,
        )
    );
}

function leaky_paywall_insights_get_total_revenue( $period ) {

    global $wpdb;

    $args_period = leaky_paywall_insights_get_formatted_period( $period );
    $after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );

    $revenue = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COALESCE(SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))), 0)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price
                 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             INNER JOIN {$wpdb->postmeta} pm_status
                 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
             WHERE p.post_type = 'lp_transaction'
             AND p.post_date > %s
             AND pm_status.meta_value != 'incomplete'
             AND (CAST(pm_price.meta_value AS DECIMAL(10,2)) > 0 OR pm_status.meta_value = 'refund')",
            $after_date
        )
    );

    $formatted_revenue = leaky_paywall_get_current_currency_symbol() . number_format( (float) $revenue, 2 );

    return html_entity_decode( $formatted_revenue );

}


function leaky_paywall_insights_get_new_paid_subs( $period ) {

    global $wpdb;

    $args_period = leaky_paywall_insights_get_formatted_period( $period );
    $after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price
                 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             INNER JOIN {$wpdb->postmeta} pm_status
                 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
             LEFT JOIN {$wpdb->postmeta} pm_recurring
                 ON p.ID = pm_recurring.post_id AND pm_recurring.meta_key = '_is_recurring'
             LEFT JOIN {$wpdb->postmeta} pm_rcpt
                 ON p.ID = pm_rcpt.post_id AND pm_rcpt.meta_key = '_rcpt_email'
             WHERE p.post_type = 'lp_transaction'
             AND p.post_date > %s
             AND pm_status.meta_value != 'incomplete'
             AND CAST(pm_price.meta_value AS DECIMAL(10,2)) > 0
             AND (pm_recurring.meta_value IS NULL OR pm_recurring.meta_value = '' OR pm_recurring.meta_value = '0')
             AND pm_rcpt.meta_id IS NULL",
            $after_date
        )
    );

    return (int) $count;

}


function leaky_paywall_insights_get_new_free_subs( $period ) {

    global $wpdb;

    $args_period = leaky_paywall_insights_get_formatted_period( $period );
    $after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );

    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price
                 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             INNER JOIN {$wpdb->postmeta} pm_status
                 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
             LEFT JOIN {$wpdb->postmeta} pm_trial
                 ON p.ID = pm_trial.post_id AND pm_trial.meta_key = '_trial_type'
             WHERE p.post_type = 'lp_transaction'
             AND p.post_date > %s
             AND pm_status.meta_value != 'incomplete'
             AND CAST(pm_price.meta_value AS DECIMAL(10,2)) = 0
             AND pm_trial.meta_id IS NULL",
            $after_date
        )
    );

    return (int) $count;

}

function leaky_paywall_insights_get_paid_content( $period ) {

    global $wpdb;

    $args_period = leaky_paywall_insights_get_formatted_period( $period );
    $after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pm_nag.meta_value AS nag_loc, COUNT(*) AS total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price
                 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             INNER JOIN {$wpdb->postmeta} pm_status
                 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
             INNER JOIN {$wpdb->postmeta} pm_nag
                 ON p.ID = pm_nag.post_id AND pm_nag.meta_key = '_nag_location_id'
             WHERE p.post_type = 'lp_transaction'
             AND p.post_date > %s
             AND pm_status.meta_value != 'incomplete'
             AND CAST(pm_price.meta_value AS DECIMAL(10,2)) > 0
             AND pm_nag.meta_value != ''
             GROUP BY pm_nag.meta_value
             ORDER BY total DESC
             LIMIT 10",
            $after_date
        )
    );

    if ( empty( $results ) ) {
        return array( 'No data found for selected time period.' );
    }

    $paid_content = array();
    foreach ( $results as $row ) {
        $url = get_the_permalink( $row->nag_loc );
        $paid_content[] = $url . ' - (' . $row->total . ')';
    }

    return $paid_content;
}


function leaky_paywall_insights_get_free_content( $period ) {

    global $wpdb;

    $args_period = leaky_paywall_insights_get_formatted_period( $period );
    $after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT pm_nag.meta_value AS nag_loc, COUNT(*) AS total
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_price
                 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             INNER JOIN {$wpdb->postmeta} pm_status
                 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
             INNER JOIN {$wpdb->postmeta} pm_nag
                 ON p.ID = pm_nag.post_id AND pm_nag.meta_key = '_nag_location_id'
             WHERE p.post_type = 'lp_transaction'
             AND p.post_date > %s
             AND pm_status.meta_value != 'incomplete'
             AND CAST(pm_price.meta_value AS DECIMAL(10,2)) = 0
             AND pm_nag.meta_value != ''
             GROUP BY pm_nag.meta_value
             ORDER BY total DESC
             LIMIT 10",
            $after_date
        )
    );

    if ( empty( $results ) ) {
        return array( 'No data found for selected time period.' );
    }

    $free_content = array();
    foreach ( $results as $row ) {
        $url = get_the_permalink( $row->nag_loc );
        $free_content[] = $url . ' - (' . $row->total . ')';
    }

    return $free_content;
}


function leaky_paywall_insights_get_active_subs( $period ) {

    $active_subs = array();
    $levels = leaky_paywall_get_levels();

    foreach ( $levels as $level_id => $level ) {

        $active_subs[] = array(
            'name'  => $level['label'],
            'count' => leaky_paywall_insights_get_active_subs_for_level( $level_id ),
        );

    }

    return $active_subs;

}

function leaky_paywall_insights_get_active_subs_for_level( $level_id ) {

    $mode = leaky_paywall_get_current_mode();
    $site = leaky_paywall_get_current_site();

    $args = array(
        'fields'      => 'ID',
        'count_total' => true,
        'number'      => 1,
        'meta_query'  => array(
            'relation' => 'AND',
            array(
                'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
                'value'   => $level_id,
                'compare' => '=',
            ),
            array(
                'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
                'value'   => array( 'active', 'pending_cancel', 'trial' ),
                'compare' => 'IN',
            ),
        ),
    );

    $subscribers = new WP_User_Query( $args );

    return $subscribers->get_total();

}

function leaky_paywall_insights_get_formatted_period($period)
{

    switch ($period) {
        case '4 weeks':
            $args_period = '-4 weeks';
            break;
        case '7 days':
            $args_period = '-7 days';
            break;
        case '30 days':
            $args_period = '-30 days';
            break;
        case 'today':
            $args_period = '24 hours ago';
            break;
        case '3 months':
            $args_period = '-3 months';
            break;
        default:
            $args_period = '-4 weeks';
            break;
    }

    return $args_period;
}
