<?php
/**
 * Leaky Paywall incomplete user
 *
 * @package Leaky Paywall
 */

/**
 * Load the base class
 */
class LP_Incomplete_User {


	/**
	 * The constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box_create' ) );
	}

	/**
	 * Register incomplete user post type
	 */
	public function register_post_type() {

		$labels = array(
			'name'               => 'Incomplete User',
			'singular_name'      => 'Incomplete User',
			'menu_name'          => 'Incomplete Users',
			'name_admin_bar'     => 'Incomplete User',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Incomplete User',
			'new_item'           => 'New Incomplete User',
			'edit_item'          => 'Edit Incomplete User',
			'view_item'          => 'View Incomplete User',
			'all_items'          => 'All Incomplete Users',
			'search_items'       => 'Search Incomplete Users',
			'parent_item_colon'  => 'Parent Incomplete Users:',
			'not_found'          => 'No Incomplete Users found',
			'not_found_in_trash' => 'No Incomplete Users found in trash.',
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Leaky Paywall Incomplete Users', 'leaky-paywall' ),
			'public'             => false,
			'publicly_queryable' => false,
			'exclude_fromsearch' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title' ),
		);

		register_post_type( 'lp_incomplete_user', $args );
	}

	/**
	 * Create meta box for incomplete user post type
	 */
	public function meta_box_create() {
		add_meta_box( 'incomplete_user_details', 'Incomplete User Details', array( $this, 'incomplete_user_details_func' ), 'lp_incomplete_user', 'normal', 'high' );
	}

	/**
	 * Show details for incomplete user
	 *
	 * @param object $post The post object.
	 */
	public function incomplete_user_details_func( $post ) {

		$user_data     = get_post_meta( $post->ID, '_user_data', true );
		$customer_data = get_post_meta( $post->ID, '_customer_data', true );

		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_title">User Data</label>
					</th>
					<td>

						<?php

						if ( !empty( $user_data ) ) {
							foreach ( $user_data as $key => $value ) {
								echo '<p><strong>' . esc_html( $key ) . '</strong>: ' . esc_html( $value ) . '</p>';
							}
						}
						
						?>

					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Customer Data</label>
					</th>
					<td>
						<?php
						echo '<pre>';
						print_r( $customer_data );
						echo '</pre>';
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php

	}
}

new LP_Incomplete_User();
