<?php 

/**
* Load the base class
*/
class LP_Transaction_Post_Type {
	
	function __construct()	{
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'meta_box_create' ) );
        add_action( 'save_post', array( $this, 'save_meta') );
	}

	public function register_post_type()
    {
        $labels = array(
            'name'               => 'Transaction',
            'singular_name'      => 'Transaction',
            'menu_name'          => 'Transactions',
            'name_admin_bar'     => 'Transaction',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Transaction',
            'new_item'           => 'New Transaction',
            'edit_item'          => 'Edit Transaction',
            'view_item'          => 'View Transaction',
            'all_items'          => 'All Transactions',
            'search_items'       => 'Search Transactions',
            'parent_item_colon'  => 'Parent Transactions:',
            'not_found'          => 'No Transactions found',
            'not_found_in_trash' => 'No Transactions found in trash.'
        );
    
        $args = array(
            'labels'             => $labels,
            'description'        => __( 'Leaky Paywall Transactions', 'leaky-paywall' ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title' )
        );
    
        register_post_type( 'lp_transaction', $args );
    }

    public function meta_box_create()
    {
        add_meta_box( 'transaction_details', 'Transaction Details', array( $this, 'transaction_details_func' ), 'lp_transaction', 'normal', 'high' );
    }

    public function transaction_details_func( $post )
    {
        
        $box1_title = get_post_meta( $post->ID, '_apc_box1_title', true );

        wp_nonce_field( 'lp_transaction_meta_box_nonce', 'meta_box_nonce' ); 
        ?>
        <table class="form-table">
			<tbody>

				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_title">First Name </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_first_name', true ) ); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Last Name </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_last_name', true ) ); ?>
					</td>
				</tr>
                <tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Email </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_email', true ) ); ?>
					</td>
				</tr>
                <tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Gateway </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_gateway', true ) ); ?>
					</td>
				</tr>
                <tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Level ID </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_level_id', true ) ); ?>
					</td>
				</tr>
                <tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Currency </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_currency', true ) ); ?>
					</td>
				</tr>
            </tbody>
        </table>

        <?php 

    }

    public function save_meta( $post_id )
    {
        
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
     
        // if our nonce isn't there, or we can't verify it, bail 
        if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'lp_transaction_meta_box_nonce' ) ) return; 
        
        // if our current user can't edit this post, bail  
        if( !current_user_can( 'edit_post' ) ) return;

    
        // checkbox save
        // update_post_meta( $post_id, '_en_sold_out', $_POST['en_sold_out'] );

        if ( isset( $_POST['apc_box1_title'] ) ) {
       //     update_post_meta( $post_id, '_apc_box1_title',strip_tags( $_POST['apc_box1_title'] ) );
        }  
        
    }

}

new LP_Transaction_Post_Type();