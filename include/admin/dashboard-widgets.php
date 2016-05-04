<?php 

/**
 * @package zeen101's Leaky Paywall
 * @since 3.8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Register the recent subscribers dashboard widget
 * @since  3.8.0
 */
function leaky_paywall_register_recent_subscribers_dashboard_widget() {
	wp_add_dashboard_widget('dashboard_widget', 'Leaky Paywall Dashboard', 'leaky_paywall_load_recent_subscribers_dashboard_widget');
}
add_action('wp_dashboard_setup', 'leaky_paywall_register_recent_subscribers_dashboard_widget' );

/**
 * Output the contents of the recent subscribers dashboard widget
 * @since  3.8.0
 */
function leaky_paywall_load_recent_subscribers_dashboard_widget( $post, $callback_args ) {

	$settings = get_leaky_paywall_settings();
	$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

	global $blog_id;
	if ( is_multisite_premium() ){
		$site = '_' . $blog_id;
	} else {
		$site = '';
	}

	?>

	<h3>Recent Subscribers</h3>

	<?php 

		$args = array(
			'order'	=> 'DESC',
			'orderby'	=> 'ID',
			'number'	=> 10,
			'meta_query'	=> array(
				array(
					'key'	=> '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
					'comare'	=> 'EXISTS'
				)
			)
		);

		$users = get_users( $args ); 

		if ( $users ) {
			?>	
			<table class="leaky-paywall-dashboard-table">
			<tr>
				<th>Date</th><th>Name</th><th>Level</th>
			</tr>
			<?php 	

			foreach ( $users as $user ) {

				$date = $user->user_registered;
				$name = $user->first_name . ' ' . $user->last_name;

				if ( !trim($name) ) {
					$name = $user->user_email;
				}
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', true ); 
				$level_name = stripcslashes( $settings['levels'][$level_id]['label'] );

				echo '<tr><td>' . date( 'M d, Y', strtotime($date) ) . '</td><td> <a href="' . admin_url() . '/user-edit.php?user_id=' . $user->ID . '">' . $name . '</a></td><td>' . $level_name . '</td>';
			}

			echo '</table>';
		} else {
			echo '<p>No subscribers found for <strong>' . $mode . '</strong> mode.</p>';
		}

		echo '<p><a href="' . admin_url() . '/admin.php?page=leaky-paywall-subscribers">See all Leaky Paywall Subscribers »</a></p>';
		
	?>

	
	<?php 
}
