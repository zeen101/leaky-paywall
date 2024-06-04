<?php
/**
 * Leaky Paywall dashboard widgets
 *
 * @package zeen101's Leaky Paywall
 * @since 3.8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the recent subscribers dashboard widget
 *
 * @since  3.8.0
 */
function leaky_paywall_register_recent_subscribers_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'dashboard_widget', 'Leaky Paywall Dashboard', 'leaky_paywall_load_recent_subscribers_dashboard_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'leaky_paywall_register_recent_subscribers_dashboard_widget' );

/**
 * Output the contents of the recent subscribers dashboard widget
 *
 * @since  3.8.0
 *
 * @param object $post The post object.
 * @param array  $callback_args The callback args.
 */
function leaky_paywall_load_recent_subscribers_dashboard_widget( $post, $callback_args ) {

	global $leaky_paywall;

	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();
	$revenue  = 0;

    if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {

		$args = array(
			'post_type'      => 'lp_transaction',
			'order'          => 'DESC',
			'posts_per_page' => 9999,
			'date_query'     => array(
				array(
					'after'  => '-30 days',
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

		$revenue = 0;

		if ( ! empty( $transactions ) ) {

			foreach ( $transactions as $transaction ) {

				$revenue  += (float)LP_Transaction::get_meta( $transaction->ID, '_price', true );
			
			}

		}

	} else {

        $mysql_date_format = 'Y-m-d H:i:s';
		$timezone = new DateTimeZone( 'UTC' );
        $datetime = new DateTime( '-30 days', $timezone );
        $date_created = $datetime->format( $mysql_date_format );

        $args = [
            'where'  => '
                `payment_status` NOT LIKE "incomplete"
            AND `price` > 0
            AND `date_created` > "' . $date_created . '"'
        ];

        $transactions = LP_Transaction::query( $args );

		$revenue = 0;

		if ( ! empty( $transactions ) ) {

			foreach( $transactions as $transaction ) {

				$revenue  += (float)$transaction->price;

			}

		}

	}

	$args = array(
		'order'      => 'DESC',
		'orderby'    => 'ID',
		'number'     => 5,
		'meta_query' => array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'compare' => 'EXISTS',
			),
		),
	);

	$users = get_users( $args );

	?>

	<h3><strong>Revenue Last 30 Days</strong></h3>
	<p style="font-size: 24px; margin-top: 5px;"><a style="text-decoration: none;" href="<?php echo esc_url( admin_url() ); ?>edit.php?post_type=lp_transaction"><?php echo esc_html( leaky_paywall_get_current_currency_symbol() ) . number_format( $revenue, 2 ); ?></a></p>

	<h3><strong>Recent Subscribers</strong></h3>

	<?php

	if ( $users ) {
		?>
		<table class="leaky-paywall-dashboard-table">
			<tr>
				<th>Date</th>
				<th>Name</th>
				<th>Level</th>
			</tr>
		<?php

		foreach ( $users as $user ) {

			$date = $user->user_registered;
			$name = $user->first_name . ' ' . $user->last_name;

			if ( ! trim( $name ) ) {
				$name = $user->user_email;
			}
			$level_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

			if ( !is_numeric( $level_id ) ) {
				continue;
			}

			$level_name = stripcslashes( $settings['levels'][ $level_id ]['label'] );

			echo '<tr><td>' . esc_html( gmdate( 'M d, Y', strtotime( $date ) ) ) . '</td><td> <a href="' . esc_url( admin_url() ) . '/user-edit.php?user_id=' . absint( $user->ID ) . '">' . esc_html( $name ) . '</a></td><td>' . esc_html( $level_name ) . '</td>';
		}

		echo '</table>';
	} else {
		echo '<p>No subscribers found for <strong>' . esc_html( $mode ) . '</strong> mode.</p>';
	}

	echo '<p><a href="' . esc_url( admin_url() ) . '/admin.php?page=leaky-paywall-subscribers">See all Leaky Paywall Subscribers »</a></p>';

	?>


	<?php
}
