<?php
/**
 * Random metaboxes
 *
 * @package zeen101's Leaky Paywall
 * @since 2.0.0
 */

if ( ! function_exists( 'leaky_paywall_general_metaboxes' ) ) {
	/**
	 * Display Leaky Paywall metaboxes
	 */
	function leaky_paywall_general_metaboxes() {

		$hidden_post_types = apply_filters( 'leaky_paywall_hidden_post_types_metaboxes', array( 'attachment', 'revision', 'nav_menu_item', 'lp_transaction', 'lp-coupons', 'ad_dropper', 'lp_group_account' ) );
		$post_types        = get_post_types( array(), 'objects' );

		foreach ( $post_types as $post_type ) {

			if ( in_array( $post_type->name, $hidden_post_types, true ) ) {
				continue;
			}

			add_meta_box( 'leaky_paywall_content_visibility', __( 'Leaky Paywall Visibility', 'leaky-paywall' ), 'leaky_paywall_content_visibility', $post_type->name, 'side' );

		}

		do_action( 'leaky_paywall_general_metaboxes' );

	}
	add_action( 'add_meta_boxes', 'leaky_paywall_general_metaboxes' );

}

if ( ! function_exists( 'leaky_paywall_content_visibility' ) ) {
	/**
	 * Display Leaky Paywall metaboxe for content visibility
	 *
	 * @param object $post The post object.
	 */
	function leaky_paywall_content_visibility( $post ) {

		$settings   = get_leaky_paywall_settings();
		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
		$show_upgrade_message = get_post_meta( $post->ID, '_issuem_leaky_paywall_show_upgrade_message', true );
		$defaults   = array(
			'visibility_type'     => 'default',
			'only_visible'        => array(),
			'only_always_visible' => array(),
			'always_visible'      => array(),
		);
		$visibility = wp_parse_args( $visibility, $defaults );

		/* Translators: %s - post type */
		echo '<label for="leaky-paywall-visibility">' . esc_attr( sprintf( __( 'This %s should...', 'leaky-paywall' ), $post->post_type ) ) . '</label> ';

		echo '<select id="issuem-leaky-paywall-visibility-type" name="leaky_paywall_visibility_type">';
		echo '  <option value="default" ' . selected( $visibility['visibility_type'], 'default', true ) . '>' . esc_attr__( "obey Leaky Paywall's defaults.", 'leaky-paywall' ) . '</option>';
		echo '  <option value="only" ' . selected( $visibility['visibility_type'], 'only', true ) . '>' . esc_attr__( 'only be visible to...', 'leaky-paywall' ) . '</option>';
		echo '  <option value="always" ' . selected( $visibility['visibility_type'], 'always', true ) . '>' . esc_attr__( 'always be visible to...', 'leaky-paywall' ) . '</option>';
		echo '  <option value="onlyalways" ' . selected( $visibility['visibility_type'], 'onlyalways', true ) . '>' . esc_attr__( 'only and always be visible to...', 'leaky-paywall' ) . '</option>';
		echo '</select>';

		if ( 'only' !== $visibility['visibility_type'] ) {
			$only_visible = 'display: none;';
		} else {
			$only_visible = '';
		}

		if ( ! empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-only-visible" name="leaky_paywall_only_visible[]" style="' . esc_attr( $only_visible ) . '"  multiple="multiple">';
			foreach ( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $visibility['only_visible'] ), true, false ) . '>' . esc_attr( $level['label'] ) . '</option>';
			}
			echo '</select>';
		}

		if ( 'always' !== $visibility['visibility_type'] ) {
			$always_visible = 'display: none;';
		} else {
			$always_visible = '';
		}

		if ( ! empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-always-visible" name="leaky_paywall_always_visible[]" style="' . esc_attr( $always_visible ) . '" multiple="multiple">';
			echo '  <option value="-1" ' . selected( in_array( '-1', $visibility['always_visible'], true ), true, false ) . '>' . esc_attr__( 'Everyone', 'leaky-paywall' ) . '</option>';
			foreach ( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $visibility['always_visible'] ), true, false ) . '>' . esc_attr( $level['label'] ) . '</option>';
			}
			echo '</select>';
		}

		if ( 'onlyalways' !== $visibility['visibility_type'] ) {
			$only_always_visible = 'display: none;';
		} else {
			$only_always_visible = '';
		}

		if ( ! empty( $settings['levels'] ) ) {
			echo '<select id="issuem-leaky-paywall-only-always-visible" name="leaky_paywall_only_always_visible[]" style="' . esc_attr( $only_always_visible ) . '" multiple="multiple">';
			foreach ( $settings['levels'] as $key => $level ) {
				echo '  <option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $visibility['only_always_visible'] ), true, false ) . '>' . esc_attr( $level['label'] ) . '</option>';
			}
			echo '</select>';
		}

		echo '<p class="description">' . esc_attr__( 'Hint:', 'leaky-paywall' ) . '</p>';
		/* Translators: %1$s - post type, %2$s - post type */
		echo '<p class="description">' . esc_attr( sprintf( __( '"Only" means that only the selected subscription levels can see this %1$s, if they have not reached their %2$s limit.', 'leaky-paywall' ), $post->post_type, $post->post_type ) ) . '</p>';
		/* Translators: %1$s - post type, %2$s - post type */
		echo '<p class="description">' . esc_attr( sprintf( __( '"Always" means that the selected subscription levels can see this %1$s, even if they have reached their %2$s limit.', 'leaky-paywall' ), $post->post_type, $post->post_type ) ) . '</p>';
		/* Translators: %1$s - post type, %2$s - post type */
		echo '<p class="description">' . esc_attr( sprintf( __( '"Only and Always" means that only the selected subscription levels can see this %1$s, even if they have reached their %2$s limit.', 'leaky-paywall' ), $post->post_type, $post->post_type ) ) . '</p>';

		?>

		<hr>
		
		<p><input type="checkbox" id="show_upgrade_message" name="show_upgrade_message" <?php checked( 'on', $show_upgrade_message ); ?> />
		<?php esc_attr_e( 'Always show upgrade message if the nag is triggered on this content.', 'leaky-paywall' ); ?></p>
			

		<?php 
		wp_nonce_field( 'leaky_paywall_content_visibility_meta_box', 'leaky_paywall_content_visibility_meta_box_nonce' );

	}
}

if ( ! function_exists( 'save_leaky_paywall_content_visibility' ) ) {

	/**
	 * Save content visibility
	 *
	 * @param int $post_id The post id.
	 */
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
		if ( ! wp_verify_nonce( sanitize_key( $_POST['leaky_paywall_content_visibility_meta_box_nonce'] ), 'leaky_paywall_content_visibility_meta_box' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( ! ( current_user_can( 'edit_pages', $post_id ) || current_user_can( 'edit_posts', $post_id ) ) ) {
			return;
		}

		if ( ! empty( $_POST['show_upgrade_message'] ) ) {
			update_post_meta( $post_id, '_issuem_leaky_paywall_show_upgrade_message', 'on' );
		} else {
			update_post_meta( $post_id, '_issuem_leaky_paywall_show_upgrade_message', 'off' );
		}

		if ( ! empty( $_POST['leaky_paywall_visibility_type'] ) ) {
			$visibility['visibility_type'] = sanitize_text_field( wp_unslash( $_POST['leaky_paywall_visibility_type'] ) );

			switch ( $visibility['visibility_type'] ) {

				case 'only':
					if ( ! empty( $_POST['leaky_paywall_only_visible'] ) ) {
						$visibility['only_visible']        = array_map( 'sanitize_text_field', wp_unslash( $_POST['leaky_paywall_only_visible'] ) );
						$visibility['only_always_visible'] = array();
						$visibility['always_visible']      = array();
					} else {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible']      = array();
					}
					break;

				case 'onlyalways':
					if ( ! empty( $_POST['leaky_paywall_only_always_visible'] ) ) {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['leaky_paywall_only_always_visible'] ) );
						$visibility['always_visible']      = array();
					} else {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible']      = array();
					}
					break;

				case 'always':
					if ( ! empty( $_POST['leaky_paywall_always_visible'] ) ) {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array();

						if ( in_array( '-1', $_POST['leaky_paywall_always_visible'], true ) ) {
							$visibility['always_visible'] = array( '-1' );
						} else {
							$visibility['always_visible'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['leaky_paywall_always_visible'] ) );
						}
					} else {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible']      = array();
					}
					break;

				default:
					if ( ! empty( $_POST['leaky_paywall_always_visible'] ) ) {
						$visibility['only_visible']        = array();
						$visibility['only_always_visible'] = array();
						$visibility['always_visible']      = array();
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
