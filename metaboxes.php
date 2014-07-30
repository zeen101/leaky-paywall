<?php
/**
 * Random metaboxes
 *
 * @package zeen101's Leaky Paywall
 * @since 2.0.0
 */

if ( !function_exists( 'leaky_paywall_general_metaboxes' ) ) {

	function leaky_paywall_general_metaboxes() {
	
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		$post_types = get_post_types( array(), 'objects' );
	
		foreach ( $post_types as $post_type ) {
		
			if ( in_array( $post_type->name, $hidden_post_types ) ) 
				continue;
				
			add_meta_box( 'leaky_paywall_content_visibility', __( 'Leaky Paywall Visibility', 'issuem-leaky-paywall' ), 'leaky_paywall_content_visibility', $post_type->name, 'side' );
		
		}
		
		do_action( 'leaky_paywall_general_metaboxes' );
		
	}
	add_action( 'add_meta_boxes', 'leaky_paywall_general_metaboxes' );

}

if ( !function_exists( 'leaky_paywall_content_visibility' ) ) {

	function leaky_paywall_content_visibility( $post ) {
	
		$settings = get_leaky_paywall_settings();
		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
		if ( empty( $visibility ) ) {
			$visibility = array(
				'visibility_type' 		=> 'default',
				'only_visible' 			=> array(),
				'only_always_visible' 	=> array(),
				'always_visible' 		=> array(),
			);
		}
		
		echo '<label for="leaky-paywall-visibility">' . sprintf( __( 'This %s should...', 'issuem-leaky-paywall' ), $post->post_type ) . '</label> ';

		echo '<select id="issuem-leaky-paywall-visibility-type" name="leaky_paywall_visibility_type">';
		echo '  <option value="default" ' . selected( $visibility['visibility_type'], 'default', true ) . '>' . __( "obey Leaky Paywall's defaults.", 'issuem-leaky-paywall' ) . '</option>';
		echo '  <option value="only" ' . selected( $visibility['visibility_type'], 'only', true ) . '>' . __( 'only be visible to...', 'issuem-leaky-paywall' ) . '</option>';
		echo '  <option value="always" ' . selected( $visibility['visibility_type'], 'always', true ) . '>' . __( 'always be visible to...', 'issuem-leaky-paywall' ) . '</option>';
		echo '  <option value="onlyalways" ' . selected( $visibility['visibility_type'], 'onlyalways', true ) . '>' . __( 'only and always be visible to...', 'issuem-leaky-paywall' ) . '</option>';
		echo '</select>';
		
		if ( 'only' !== $visibility['visibility_type'] )
			$only_visible = 'style="display: none;"';
		else
			$only_visible = '';

		if ( !empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-only-visible" name="leaky_paywall_only_visible[]" ' . $only_visible . ' multiple="multiple">';
	    	foreach( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . $key . '" ' . selected( in_array( $key, $visibility['only_visible'] ), true, false ) . '>' . $level['label'] . '</option>';
			}
			echo '</select>';
		}
		
		if ( 'always' !== $visibility['visibility_type'] )
			$always_visible = 'style="display: none;"';
		else
			$always_visible = '';
		
		if ( !empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-always-visible" name="leaky_paywall_always_visible[]" ' . $always_visible . ' multiple="multiple">';
			echo '  <option value="-1" ' . selected( in_array( '-1', $visibility['always_visible'] ), true, false ) . '>' . __( 'Everyone', 'issuem-leaky-paywall' ) . '</option>';
	    	foreach( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . $key . '" ' . selected( in_array( $key, $visibility['always_visible'] ), true, false ) . '>' . $level['label'] . '</option>';
			}
			echo '</select>';
		}
		
		if ( 'onlyalways' !== $visibility['visibility_type'] )
			$only_always_visible = 'style="display: none;"';
		else
			$only_always_visible = '';

		if ( !empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-only-always-visible" name="leaky_paywall_only_always_visible[]" ' . $only_always_visible . ' multiple="multiple">';
	    	foreach( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . $key . '" ' . selected( in_array( $key, $visibility['only_always_visible'] ), true, false ) . '>' . $level['label'] . '</option>';
			}
			echo '</select>';
		}
		
		echo '<p class="description">' . __( 'Hint:', 'issuem-leaky-paywall' ) . '</p>';
		echo '<p class="description">' . sprintf( __( '"Only" means that only the selected subscription levels can see this %s, if they have not reached their %s limit.', 'issuem-leaky-paywall' ), $post->post_type, $post->post_type ) . '</p>';
		 echo '<p class="description">' . sprintf( __( '"Always" means that the selected subscription levels can see this %s, even if they have reached their %s limit.', 'issuem-leaky-paywall' ), $post->post_type, $post->post_type ) . '</p>';
		 echo '<p class="description">' . sprintf( __( '"Only and Always" means that only the selected subscription levels can see this %s, even if they have reached their %s limit.', 'issuem-leaky-paywall' ), $post->post_type, $post->post_type ) . '</p>';

		wp_nonce_field( 'leaky_paywall_content_visibility_meta_box', 'leaky_paywall_content_visibility_meta_box_nonce' );
	
	}

}

if ( !function_exists( 'save_leaky_paywall_content_visibility' ) ) {

	function save_leaky_paywall_content_visibility( $post_id ) {
	
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
	
		// Check if our nonce is set.
		if ( ! isset( $_POST['leaky_paywall_content_visibility_meta_box_nonce'] ) ) {
			return;
		}
	
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['leaky_paywall_content_visibility_meta_box_nonce'], 'leaky_paywall_content_visibility_meta_box' ) ) {
			return;
		}
	
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
			
		// Check the user's permissions.
		if ( ! ( current_user_can( 'edit_page', $post_id ) || current_user_can( 'edit_post', $post_id ) ) ) {
			return;
		}
	
		/* OK, it's safe for us to save the data now. */	
		
		if ( !empty( $_POST['leaky_paywall_visibility_type'] ) ) {
			$visibility['visibility_type'] = $_POST['leaky_paywall_visibility_type'];
			
			switch ( $visibility['visibility_type'] ) {
			
				case 'only':
					if ( !empty( $_POST['leaky_paywall_only_visible'] ) ) {
						$visibility['only_visible'] = $_POST['leaky_paywall_only_visible'];
						$visibility['only_always_visible'] = array();
						$visibility['always_visible'] = array();
					} else {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible'] = array();
					}
					break;
			
				case 'onlyalways':
					if ( !empty( $_POST['leaky_paywall_only_always_visible'] ) ) {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = $_POST['leaky_paywall_only_always_visible'];
						$visibility['always_visible'] = array();
					} else {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible'] = array();
					}
					break;
					
				case 'always':
					if ( !empty( $_POST['leaky_paywall_always_visible'] ) ) {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = array();
						
						if ( in_array( '-1', $_POST['leaky_paywall_always_visible'] ) )
							$visibility['always_visible'] = array( '-1' );
						else
							$visibility['always_visible'] = $_POST['leaky_paywall_always_visible'];
					} else {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible'] = array();
					}
					break;
					
				default:
					if ( !empty( $_POST['leaky_paywall_always_visible'] ) ) {
						$visibility['only_visible'] = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible'] = array();
					}
					break;
			
			}
			
			update_post_meta( $post_id, '_issuem_leaky_paywall_visibility', $visibility );
			
		} else {
			
			delete_post_meta( $post_id, '_issuem_leaky_paywall_visibility' );
			
		}

	}
	add_action( 'save_post', 'save_leaky_paywall_content_visibility' );
	
}