<?php

function lp_get_subscriber_meta( $key, $user = null ) {

	if ( !$user ) {
		$user = wp_get_current_user();
	}

	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	switch ($key) {
		case 'level_id':
			$value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true);
			break;
		case 'expires':
			$value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true);
			break;
		case 'subscriber_id':
			$value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true);
			break;
		case 'payment_status':
			$value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true);
			break;
        case 'created':
            $value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_created' . $site, true);
            break;
        case 'plan':
            $value = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true);
            break;
        case 'notes':
            $value = get_user_meta($user->ID, '_leaky_paywall_subscriber_notes', true);
            break;
        case 'payment_gateway':
            $value = str_replace( ' ', '_', strtolower( get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true) ) );
            break;

		default:
			$value = '';
			break;
	}

	return $value;
}

function lp_update_subscriber_meta( $key, $value, $user_id ) {

    $mode = leaky_paywall_get_current_mode();
    $site = leaky_paywall_get_current_site();

    switch ($key) {
        case 'first_name':
            wp_update_user( [ 'ID' => $user_id, 'first_name' => $value ] );
            break;
        case 'last_name':
            wp_update_user(['ID' => $user_id, 'last_name' => $value]);
            break;
        case 'email':
            wp_update_user(['ID' => $user_id, 'user_email' => $value]);
            break;
        case 'subscriber_notes':
            update_user_meta($user_id, '_leaky_paywall_subscriber_notes', $value);
            break;
        case 'level_id':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, $value);
            break;
        case 'expires':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $value);
            break;
        case 'subscriber_id':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, $value);
            break;
        case 'payment_status':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, $value);
            break;
        case 'payment_gateway':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, $value);
            break;
        case 'expires':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $value);
            break;
        case 'plan':
            update_user_meta($user_id, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, $value);
            break;


        default:
            $value = '';
            break;
    }

}

function lp_render_subscription_status_badge($status) {
    $status_map = [
        'active' => [
            'label' => 'Active',
            'class' => 'status-active',
            'desc'  => 'Your subscription is active and billing as scheduled.',
        ],
        'trialing' => [
            'label' => 'Trial Active',
            'class' => 'status-trial',
            'desc'  => 'You’re currently in a trial.',
        ],
		'trial' => [
			'label' => 'Trial Active',
			'class' => 'status-trial',
			'desc'  => 'You’re currently in a trial.',
		],
        'past_due' => [
            'label' => 'Action Needed',
            'class' => 'status-warning',
            'desc'  => 'We couldn’t process your last payment. Please update your billing info.',
        ],
        'unpaid' => [
            'label' => 'Payment Failed',
            'class' => 'status-failed',
            'desc'  => 'Your subscription is paused due to failed payment.',
        ],
        'incomplete' => [
            'label' => 'Setup Incomplete',
            'class' => 'status-warning',
            'desc'  => 'Subscription started but payment not completed.',
        ],
        'incomplete_expired' => [
            'label' => 'Subscription Expired',
            'class' => 'status-failed',
            'desc'  => 'Subscription setup expired without completion.',
        ],
        'paused' => [
            'label' => 'Paused',
            'class' => 'status-paused',
            'desc'  => 'Your subscription is paused. No billing is occurring.',
        ],
        'canceled' => [
            'label' => 'Canceled',
            'class' => 'status-canceled',
            'desc'  => 'Your subscription has been canceled.',
        ],
        'expired' => [
            'label' => 'Ended',
            'class' => 'status-ended',
            'desc'  => 'Your subscription has ended.',
        ],
        'pending_activation' => [
            'label' => 'Pending Activation',
            'class' => 'status-pending',
            'desc'  => 'Awaiting activation or approval.',
        ],
        'renewal_due' => [
            'label' => 'Renewal Due Soon',
            'class' => 'status-warning',
            'desc'  => 'Your subscription renews soon.',
        ],
        'on_hold' => [
            'label' => 'On Hold',
            'class' => 'status-paused',
            'desc'  => 'Temporarily paused — contact support to resume.',
        ],
        'grace_period' => [
            'label' => 'Grace Period',
            'class' => 'status-warning',
            'desc'  => 'Your payment failed, but your subscription is still active for now.',
        ],
    ];

    $status_data = $status_map[$status] ?? [
        'label' => ucfirst($status),
        'class' => 'status-unknown',
        'desc'  => 'Unknown subscription status.',
    ];

    echo '<div class="subscription-status">';
    echo '<span class="subscription-badge ' . esc_attr($status_data['class']) . '">' . esc_html($status_data['label']) . '</span>';
    echo '<p class="description">' . esc_html($status_data['desc']) . '</p>';
    echo '</div>';
}

function leaky_paywall_get_all_transactions_by_email( $email )
{

    $args = array(
        'post_type'       => 'lp_transaction',
        'number_of_posts' => 99,
        'meta_query'      => array(
            array(
                'key'     => '_email',
                'value'   => $email,
                'compare' => '=',
            ),
        ),
    );

    $transactions = get_posts($args);

    if (!empty($transactions)) {
        return $transactions;
    }

    return false;
}
