<?php

add_action( 'wp_ajax_lp_reports_get_data', 'lp_reports_get_data' );

function lp_reports_get_data() {

    if ( ! check_ajax_referer( 'process_lp_insights' ) ) {
        wp_send_json( array( 'error' => 'There was an error.' ) );
    }

    $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '';

    // get total revenue data
    $revenue = leaky_paywall_reports_get_total_revenue( $period );
    $new_paid_subs = leaky_paywall_reports_get_new_paid_subs( $period );
    $new_free_subs = leaky_paywall_reports_get_new_free_subs( $period );
    $new_gift_subs = leaky_paywall_reports_get_new_gift_subs( $period );
    $paid_content = leaky_paywall_reports_get_paid_content( $period );
    $free_content = leaky_paywall_reports_get_free_content( $period );
    // $active_subs = leaky_paywall_reports_get_active_subs( $period );

    wp_send_json(
        array(
            'total_revenue' => $revenue,
            'new_paid_subs' => $new_paid_subs,
            'new_free_subs' => $new_free_subs,
            'new_gift_subs' => $new_gift_subs,
            'paid_content' => $paid_content,
            'free_content' => $free_content,
            // 'active_subs'   => $active_subs
        )
    );
}

function leaky_paywall_reports_get_total_revenue( $period ) {

    $revenue = 0;

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
			$price   = get_post_meta( $transaction->ID, '_price', true );

            $revenue = $revenue + $price;


		}
	}

    $formatted_revenue = leaky_paywall_get_current_currency_symbol() . number_format( $revenue, 2 );

    return html_entity_decode( $formatted_revenue );

}


function leaky_paywall_reports_get_new_paid_subs( $period ) {

    $new_paid_subs = 0;

    switch ($period) {
        case '4 weeks':
            $args_period = '-4 weeks';
            break;
        case '7 days':
            $args_period = '-7 days';
            break;
        case 'today':
            $args_period = '24 hours ago';
            break;
        case '3 months':
            $args_period = '-3 months';
            break;
        default:
            $args_period = '-1 weeks';
            break;
    }

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
        $new_paid_subs = count( $transactions );
	}

    return $new_paid_subs;

}


function leaky_paywall_reports_get_new_free_subs( $period ) {

    $new_free_subs = 0;

    switch ($period) {
        case '4 weeks':
            $args_period = '-4 weeks';
            break;
        case '7 days':
            $args_period = '-7 days';
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

    return $new_free_subs;

}

function leaky_paywall_reports_get_paid_content( $period ) {

    $paid_content = array();

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
	);

	$transactions = get_posts( $args );

	if ( ! empty( $transactions ) ) {
		foreach ( $transactions as $transaction ) {
			$price   = get_post_meta( $transaction->ID, '_price', true );
			$status   = get_post_meta( $transaction->ID, '_status', true );
            $nag_loc = get_post_meta( $transaction->ID, '_nag_location_id', true );

            if ( $status != 'incomplete' && $price > 0 && $nag_loc ) {
                $paid_content[$nag_loc]['url'] = get_the_permalink( $nag_loc );
                $paid_content[$nag_loc]['count'] = isset( $paid_content[$nag_loc]['count'] ) ? $paid_content[$nag_loc]['count'] + 1 : 1;
            }

		}

        if ( !empty( $paid_content ) ) {

            foreach( $paid_content as $item ) {
                $sorted_paid_content[$item['url']] = $item['count'];

            }

            arsort( $sorted_paid_content );

            $i = 1;

            foreach( $sorted_paid_content as $perm => $num ) {

                if ( $i > 10 ) {
                    break;
                }
                $new_paid_content[] = $perm . ' - (' . $num . ')';

                $i++;
            }



            return $new_paid_content;

        }





	} else {
        $paid_content[] = 'No data found for selected time period.';
    }

    return $paid_content;
}


function leaky_paywall_reports_get_free_content( $period ) {

    $free_content = array();

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
	);

	$transactions = get_posts( $args );

	if ( ! empty( $transactions ) ) {
		foreach ( $transactions as $transaction ) {
			$price   = get_post_meta( $transaction->ID, '_price', true );
			$status   = get_post_meta( $transaction->ID, '_status', true );
            $nag_loc = get_post_meta( $transaction->ID, '_nag_location_id', true );

            if ( $price > 0 ) {
                continue;
            }

            if ( $status != 'incomplete' && $nag_loc ) {
                $free_content[$nag_loc]['url'] = get_the_permalink( $nag_loc );
                $free_content[$nag_loc]['count'] = isset( $free_content[$nag_loc]['count'] ) ? $free_content[$nag_loc]['count'] + 1 : 1;
            }

		}

        if ( !empty( $free_content ) ) {
            foreach( $free_content as $item ) {
                $sorted_free_content[$item['url']] = $item['count'];

            }

            arsort( $sorted_free_content );


            $j = 1;

            foreach( $sorted_free_content as $perm => $num ) {

                if ( $j > 10 ) {
                    break;
                }
                $new_free_content[] = $perm . ' - (' . $num . ')';

                $j++;
            }

            return $new_free_content;
        }

	} else {
        $free_content[] = 'No data found for selected time period.';
    }

    return $free_content;
}


function leaky_paywall_reports_get_active_subs( $period ) {

    $active_subs = array();
    $levels = leaky_paywall_get_levels();

    foreach( $levels as $level_id => $level ) {

        $active_subs[] = array(
            'name' => $level['label'],
            'count' => leaky_paywall_reports_get_active_subs_for_level( $level_id )
        );

    }

    return $active_subs;

}

function leaky_paywall_reports_get_active_subs_for_level( $level_id ) {

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