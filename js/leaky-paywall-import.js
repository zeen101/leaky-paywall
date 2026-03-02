( function( $ ) {

	$(document).ready( function() {

		$('#leaky_paywall_upload_user_csv_button').click( function(e) {

			var user_custom_uploader;

			e.preventDefault();

			if ( user_custom_uploader ) {
				user_custom_uploader.open();
				return;
			}

			user_custom_uploader = wp.media.frames.file_frame = wp.media({
				title: 'Choose CSV File',
				button: {
					text: 'Choose CSV File'
				},
				multiple: false
			});

			user_custom_uploader.on( 'select', function() {
				var attachment = user_custom_uploader.state().get('selection').first().toJSON();
				$('#leaky_paywall_import_user_csv_file').val( attachment.url );
				$('#leaky_paywall_import_user_csv_file_id').val( attachment.id );
			});

			user_custom_uploader.open();

		});

	});

})( jQuery );
