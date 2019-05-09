<?php 

/**
* Load the base class
*/
class LP_Transaction_Post_Type {
	
	function __construct()	{
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'meta_box_create' ) );
        add_action( 'save_post', array( $this, 'save_meta') );

        add_filter('manage_edit-lp_transaction_columns' , array( $this, 'transaction_columns') );
        add_action('manage_lp_transaction_posts_custom_column' , array( $this, 'transaction_custom_columns' ), 10, 2 );


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
            'public'             => false,
            'publicly_queryable' => false,
            'exclude_fromsearch' 	=> true,
            'show_ui'            => true,
            'show_in_menu'       => false,
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
        
        $level_id = esc_attr( get_post_meta( $post->ID, '_level_id', true ) );
        $level = get_leaky_paywall_subscription_level( $level_id );

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
                        <?php echo $level_id . ' - ' . $level['label']; ?>
					</td>
				</tr>
                <tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Price </label>
					</th>
					<td>
                        <?php echo esc_attr( get_post_meta( $post->ID, '_price', true ) ); ?>
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
                <tr valign="top">
                    <th scope="row">
                        <label for="apc_box1_description">Is Recurring </label>
                    </th>
                    <td>
                        <?php echo get_post_meta( $post->ID, '_is_recurring', true ) ? 'yes' : 'no'; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if ( get_post_meta( $post->ID, '_paypal_request', true ) ) {
            echo '<p><strong>Paypal Response</strong></p>';
            echo '<pre>';
            print_r( json_decode( get_post_meta( $post->ID, '_paypal_request', true ) ) );
            echo '</pre>';
            
           
        } ?>

        <?php do_action( 'leaky_paywall_after_transaction_meta_box', $post ); ?>

        <?php 

    }

    public function save_meta( $post_id )
    {
        
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
     
        // if our nonce isn't there, or we can't verify it, bail 
        if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'lp_transaction_meta_box_nonce' ) ) return; 
        
       
    
        // checkbox save
        // update_post_meta( $post_id, '_en_sold_out', $_POST['en_sold_out'] );

        if ( isset( $_POST['apc_box1_title'] ) ) {
       //     update_post_meta( $post_id, '_apc_box1_title',strip_tags( $_POST['apc_box1_title'] ) );
        }  
        
    }

    public function transaction_columns( $columns ) 
    {

        $columns = array(
            'cb' => '<input type="checkbox" />',
            'email' => __( 'Email' ),
            'level' => __( 'Level' ),
            'price' => __( 'Price' ),
            'created' => __( 'Created' ),
            'status' => __( 'Status' )
        );

        return $columns;

    }

    public function transaction_custom_columns( $column, $post_id ) 
    {

        $level_id = esc_attr( get_post_meta( $post_id, '_level_id', true ) );
        $level = get_leaky_paywall_subscription_level( $level_id );
        
        switch ( $column ) {
        
           case 'email':
               echo '<a href="' . admin_url() . '/post.php?post=' . $post_id . '&action=edit">' . esc_attr( get_post_meta( $post_id, '_email', true ) ) . '</a>';
               break;

           case 'level':
                echo $level['label'];
               break;

            case 'price':
                echo leaky_paywall_get_current_currency_symbol() . number_format( esc_attr( get_post_meta( $post_id, '_price', true ) ), 2 );
               break;

            case 'created':
                echo get_the_date( 'M d, Y h:i:s A', $post_id );
               break;

            case 'status':
                echo  get_post_meta( $post_id, '_is_recurring', true ) ? 'Recurring Payment' : 'Complete'; 
               break;
        }

    }

}

new LP_Transaction_Post_Type();