var $leaky_paywall_subscribers = jQuery.noConflict();

$leaky_paywall_subscribers(document).ready(function($) {

	$( '#leaky-paywall-subscriber-expires' ).datepicker({
		prevText: '',
		nextText: '',
		minDate: 0,
		dateFormat: $( 'input[name=date_format]' ).val()
	});

	$('.lp-notice-link').click(function (e) {
		e.preventDefault();
		$(this).closest('.notice').hide();
		$.ajax({
			url     : leaky_paywall_notice_ajax.ajaxurl,
			type    : 'POST',
			dataType: 'text',
			cache   : false,
			data    : {
				action  : 'leaky_paywall_process_notice_link',
				nonce   : leaky_paywall_notice_ajax.lpNoticeNonce,
				notice  : $(this).data('notice'),
				type    : $(this).data('type')
			}
		});
	});

	/* ========================================
	   Add Subscriber Modal
	   ======================================== */

	var $modal   = $('#lp-add-subscriber-modal');
	var $form    = $('#lp-add-subscriber-form');
	var $body    = $modal.find('.lp-modal-body');
	var $footer  = $modal.find('.lp-modal-footer');
	var $success = $modal.find('.lp-modal-success');
	var $error   = $modal.find('.lp-modal-error');
	var $submit  = $('#lp-modal-submit');

	function openModal() {
		$modal.fadeIn(200);
		$('body').css('overflow', 'hidden');
		// Init datepicker on modal field
		$('#lp-modal-expires').datepicker({
			prevText: '',
			nextText: '',
			minDate: 0,
			dateFormat: $modal.find('input[name=date_format]').val()
		});
	}

	function closeModal() {
		$modal.fadeOut(200, function() {
			resetModal();
		});
		$('body').css('overflow', '');
	}

	function resetModal() {
		$form[0].reset();
		$error.hide().text('');
		$body.show();
		$footer.show();
		$success.hide();
		$submit.prop('disabled', false).text($submit.data('original-text') || 'Add Subscriber');
	}

	// Store original button text
	$submit.data('original-text', $submit.text());

	// Open
	$('#lp-open-add-subscriber-modal').on('click', function(e) {
		e.preventDefault();
		openModal();
	});

	// Close — X button, Cancel button
	$modal.on('click', '.lp-modal-close, .lp-modal-cancel', function(e) {
		e.preventDefault();
		closeModal();
	});

	// Close — Escape key
	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && $modal.is(':visible')) {
			closeModal();
		}
	});

	// Submit
	$form.on('submit', function(e) {
		e.preventDefault();

		$error.hide();
		$submit.prop('disabled', true).text('Adding...');

		var data = {
			action:          'leaky_paywall_add_subscriber',
			nonce:           leaky_paywall_notice_ajax.addSubscriberNonce,
			login:           $form.find('[name="leaky-paywall-subscriber-login"]').val(),
			email:           $form.find('[name="leaky-paywall-subscriber-email"]').val(),
			first_name:      $form.find('[name="leaky-paywall-subscriber-first-name"]').val(),
			last_name:       $form.find('[name="leaky-paywall-subscriber-last-name"]').val(),
			price:           $form.find('[name="leaky-paywall-subscriber-price"]').val(),
			expires:         $form.find('[name="leaky-paywall-subscriber-expires"]').val(),
			level_id:        $form.find('[name="leaky-paywall-subscriber-level-id"]').val(),
			payment_status:  $form.find('[name="leaky-paywall-subscriber-status"]').val(),
			payment_gateway: $form.find('[name="leaky-paywall-subscriber-payment-gateway"]').val(),
			subscriber_id:   $form.find('[name="leaky-paywall-subscriber-id"]').val()
		};

		$.post(leaky_paywall_notice_ajax.ajaxurl, data, function(response) {
			if (response.success) {
				$body.hide();
				$footer.hide();
				$success.find('.lp-modal-success-message').text(response.data.message);
				$success.find('.lp-modal-success-link').html(
					'<a href="' + response.data.url + '">View Subscriber</a>'
				);
				$success.show();

				setTimeout(function() {
					window.location.reload();
				}, 1500);
			} else {
				$error.text(response.data.message).show();
				$submit.prop('disabled', false).text($submit.data('original-text'));
			}
		}).fail(function() {
			$error.text('An unexpected error occurred. Please try again.').show();
			$submit.prop('disabled', false).text($submit.data('original-text'));
		});
	});

});
