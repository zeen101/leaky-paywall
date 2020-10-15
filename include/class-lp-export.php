<?php 

add_action('init', 'maybe_export_data');

function maybe_export_data() {

	return;

	$export = new LP_Export();
	$export->generate();
	
}

/**
* Load the base class
*/
class LP_Export {

	// string
	private $type;
	private $site;
	private $mode;

	// array
	private $data;

	// array
	private $headers;
	
	function __construct( $type = 'csv' )	{
		
		$this->type = 'csv'; // hard code for now

		$settings = get_leaky_paywall_settings();
		
		$blog_id = get_current_blog_id();
		if ( is_multisite_premium() && ! is_main_site( $blog_id ) ) {
			$this->site = '_' . $blog_id;
		} else {
			$this->site = '';
		}

		$this->mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		$this->data    = $this->getData();
		$this->headers = $this->getHeaders();

	}

	public function generate() 
	{
			
		if ( 'csv' == $this->type ) {
			$this->buildCsv();
		}

	}

	private function buildCsv() 
	{
		
		$file_name = date( 'Ymd_His' ) . '-export.csv';

		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( 'Content-Description: File Transfer' );
		header( "Content-type: text/csv" );
		header( "Content-Disposition: attachment; filename={$file_name}" );
		header( "Expires: 0" );
		header( "Pragma: public" );

		$out = fopen( 'php://output', 'w' );

		fputcsv( $out, $this->headers, ',' );

		// item is an array
		foreach( $this->data as $item ) {

			fputcsv( $out, $item, ',' );

		}

		fclose( $out );

		exit;

	}

	private function getData() 
	{
		$data = array();

		// need to get real data at some point
		$args = array(
			'role' => 'Subscriber',
		);

		$users = new WP_User_Query( $args );

		if ( empty( $users->results ) ) {
			return $data;
		}
		
		foreach( $users->results as $user ) {

			$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_level_id' . $this->site, true );

			if ( ! $level_id ) {
				continue;
			}
			
			$level   = get_leaky_paywall_subscription_level( $level_id );
			$expires = esc_attr( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_expires' . $this->site, true ) );

			if ( ! $expires ) {
				$expires = 'never';
			} else {
				$expires = date( 'Y-m-d', strtotime( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_expires' . $this->site, true ) ) );
			}

			$data[] = array(
				'email'           => $user->user_email,
				'first_name'      => $user->first_name,
				'last_name'       => $user->last_name,
				'username'        => $user->user_login,
				'level'           => $level['label'],
				'subscriber_id'	  => get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_subscriber_id' . $this->site, true ),
				'plan_id'         => get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_plan' . $this->site, true ),
				'payment_gateway' => get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_payment_gateway' . $this->site, true ),
				'status'          => get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_payment_status' . $this->site, true ),
				'created'         => date( 'Y-m-d', strtotime( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $this->mode . '_created' . $this->site, true ) ) ),
				'expires'         => $expires
			);
		}

		return $data;


		/*
		// stub data
		$data = array(
			array(
				'greenhornet79@gmail.com',
				'Jeremy Green',
				'jeremygreen',
				'Monthly',
				'293829',
				'Non-Recurring',
				'Stripe',
				'Active',
				'June 30, 2017',
				'August 31, 2017'
			),
			array(
				'greenhornet792@gmail.com',
				'Jeremy Green2',
				'jeremygreen2',
				'Monthly',
				'293829',
				'Non-Recurring',
				'Stripe',
				'Active',
				'June 30, 2017',
				'August 31, 2017'
			)  
		);
		*/
	
	}

	private function getHeaders() 
	{
		
		$headers = array( 'Email', 'First Name', 'Last Name', 'Username', 'Level', 'Subscriber ID', 'Plan', 'Gateway', 'Payment Status', 'Created', 'Expires' );
		return $headers;

	}

}