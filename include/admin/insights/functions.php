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

    global $leaky_paywall;

    $revenue = 0;
    $args_period = leaky_paywall_insights_get_formatted_period( $period );

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

        $args = array(
            'post_type'      => 'lp_transaction',
            'order'          => 'DESC',
            'posts_per_page' => 9999,
            'date_query'     => array(
                array(
                    'after'  => $args_period,
                    'column' => 'post_date',
                ),
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_status',
                    'value'   => 'incomplete',
                    'compare' => 'NOT LIKE',
                ),
                array(
                    'key'     => '_price',
                    'value'   => '0',
                    'compare' => '>',
                ),
            ),
        );

        $transactions = get_posts( $args );

        if ( ! empty( $transactions ) ) {
            foreach ( $transactions as $transaction ) {
                $price   = LP_Transaction::get_meta( $transaction->ID, '_price', true );
                $revenue = $revenue + $price;
            }
        }

    } else {

        $mysql_date_format = 'Y-m-d H:i:s';
		$timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( $args_period, $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'select' => 'SUM(`price`) AS revenue',
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` > 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $revenue = LP_Transaction::get_var( $args );

    }

    $formatted_revenue = leaky_paywall_get_current_currency_symbol() . number_format( $revenue, 2 );

    return html_entity_decode( $formatted_revenue );

}


function leaky_paywall_insights_get_new_paid_subs( $period ) {

    global $leaky_paywall;

    $new_paid_subs = 0;
    $args_period = leaky_paywall_insights_get_formatted_period($period);

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

        $args = array(
            'post_type'      => 'lp_transaction',
            'order'          => 'DESC',
            'posts_per_page' => 9999,
            'date_query'     => array(
                array(
                    'after'  => $args_period,
                    'column' => 'post_date',
                ),
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_status',
                    'value'   => 'incomplete',
                    'compare' => 'NOT LIKE',
                ),
                array(
                    'key'     => '_price',
                    'value'   => '0',
                    'compare' => '>',
                ),
                array(
                    'key'     => '_is_recurring',
                    'value'   => false,
                    'compare' => '=',
                ),
                array(
                    'key'     => '_rcpt_email',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $transactions = get_posts( $args );

        if ( ! empty( $transactions ) ) {

            foreach( $transactions as $transaction ) {

                $new_paid_subs = $new_paid_subs + 1;
                
            }

        }

    } else {

        $mysql_date_format = 'Y-m-d H:i:s';
		$timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( $args_period, $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'select' => 'COUNT(`ID`) AS new_paid_subs',
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` > 0
            AND `is_recurring` = 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $new_paid_subs = LP_Transaction::get_var( $args );
        
    }

    return $new_paid_subs;

}


function leaky_paywall_insights_get_new_free_subs( $period ) {

    global $leaky_paywall;

    $new_free_subs = 0;
    $args_period = leaky_paywall_insights_get_formatted_period($period);

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

        $args = array(
            'post_type'      => 'lp_transaction',
            'order'          => 'DESC',
            'posts_per_page' => 9999,
            'date_query'     => array(
                array(
                    'after'  => $args_period,
                    'column' => 'post_date',
                ),
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_status',
                    'value'   => 'incomplete',
                    'compare' => 'NOT LIKE',
                ),
                array(
                    'key'     => '_price',
                    'value'   => '0',
                    'compare' => '=',
                ),
            ),
        );

        $transactions = get_posts( $args );

        if ( ! empty( $transactions ) ) {

            $new_free_subs = count( $transactions );

        }

    } else {

        $mysql_date_format = 'Y-m-d H:i:s';
		$timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( $args_period, $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'select' => 'COUNT(`ID`) AS new_free_subs',
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` = 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $new_free_subs = LP_Transaction::get_var( $args );

    }

    return $new_free_subs;

}

function leaky_paywall_insights_get_paid_content( $period ) {

    global $leaky_paywall;

    $paid_content = array();
    $args_period = leaky_paywall_insights_get_formatted_period($period);

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

        $args = array(
            'post_type'      => 'lp_transaction',
            'order'          => 'DESC',
            'posts_per_page' => 999,
            'date_query'     => array(
                array(
                    'after'  => $args_period,
                    'column' => 'post_date',
                ),
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_status',
                    'value'   => 'incomplete',
                    'compare' => 'NOT LIKE',
                ),
                array(
                    'key'     => '_price',
                    'value'   => '0',
                    'compare' => '>',
                ),
            ),
        );

        $transactions = get_posts( $args );

    } else {

        $mysql_date_format = 'Y-m-d H:i:s';
        $timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( $args_period, $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` > 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $transactions = LP_Transaction::query( $args );
        
    }

    if ( ! empty( $transactions ) ) {

        foreach ( $transactions as $transaction ) {

            $nag_loc = LP_Transaction::get_meta( $transaction->ID, '_nag_location_id', true );

            if ( $nag_loc ) {

                $paid_content[$nag_loc]['url'] = get_the_permalink( $nag_loc );
                $paid_content[$nag_loc]['count'] = isset( $paid_content[$nag_loc]['count'] ) ? $paid_content[$nag_loc]['count'] + 1 : 1;
            
            }

        }

        foreach( $paid_content as $item ) {

            $sorted_paid_content[$item['url']] = $item['count'];

        }

        arsort( $sorted_paid_content );

        $sorted_paid_content = array_slice( $sorted_paid_content, 0, 10 );

        foreach( $sorted_paid_content as $perm => $num ) {

            $new_paid_content[] = $perm . ' - (' . $num . ')';

        }

        $paid_content = $new_paid_content;

    } else {

        $paid_content[] = 'No data found for selected time period.';
        
    }

    return $paid_content;

}


function leaky_paywall_insights_get_free_content( $period ) {

    global $leaky_paywall;

    $free_content = array();
    $args_period = leaky_paywall_insights_get_formatted_period( $period );

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

        $args = array(
            'post_type'      => 'lp_transaction',
            'order'          => 'DESC',
            'posts_per_page' => 999,
            'date_query'     => array(
                array(
                    'after'  => $args_period,
                    'column' => 'post_date',
                ),
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_status',
                    'value'   => 'incomplete',
                    'compare' => 'NOT LIKE',
                ),
                array(
                    'key'     => '_price',
                    'value'   => '0',
                    'compare' => '=',
                ),
            ),
        );

        $transactions = get_posts( $args );

    } else {

        $mysql_date_format = 'Y-m-d H:i:s';
        $timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( $args_period, $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` = 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $transactions = LP_Transaction::query( $args );
        
    }

	if ( ! empty( $transactions ) ) {

		foreach ( $transactions as $transaction ) {

            $nag_loc = LP_Transaction::get_meta( $transaction->ID, '_nag_location_id', true );

            if ( $nag_loc ) {

                $free_content[$nag_loc]['url'] = get_the_permalink( $nag_loc );
                $free_content[$nag_loc]['count'] = isset( $free_content[$nag_loc]['count'] ) ? $free_content[$nag_loc]['count'] + 1 : 1;
            
            }

		}

        if ( !empty( $free_content ) ) {

            foreach( $free_content as $item ) {

                $sorted_free_content[$item['url']] = $item['count'];

            }

            arsort( $sorted_free_content );

            $sorted_free_content = array_slice( $sorted_free_content, 0, 10 );

            foreach( $sorted_free_content as $perm => $num ) {

                $new_free_content[] = $perm . ' - (' . $num . ')';

            }

            $free_content = $new_free_content;
        }

	} else {

        $free_content[] = 'No data found for selected time period.';

    }

    return $free_content;
}


function leaky_paywall_insights_get_active_subs( $period ) {

    $active_subs = array();
    $levels = leaky_paywall_get_levels();

    foreach( $levels as $level_id => $level ) {

        $active_subs[] = array(
            'name' => $level['label'],
            'count' => leaky_paywall_insights_get_active_subs_for_level( $level_id )
        );

    }

    return $active_subs;

}

function leaky_paywall_insights_get_active_subs_for_level( $level_id ) {

    $mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();

    $users = get_users(
        array(
            // 'number' => 2999,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
                    'value'   => $level_id,
                     'compare' => '='
                ),
                array(
                    'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
                    'value'   => 'active',
                     'compare' => '='
                ),
            )
        )

    );

    if ( empty( $users ) ) {
        return 0;
    }

    return count( $users );

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