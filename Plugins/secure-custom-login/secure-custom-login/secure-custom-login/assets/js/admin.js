jQuery(document).ready(function ($) {
	$('.scl-upload-button').on('click', function (e) {
		e.preventDefault();

		var targetInput = $($(this).data('target'));
		var frame = wp.media({
			title: 'Select Image',
			button: {
				text: 'Use this image'
			},
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			targetInput.val(attachment.url);
		});

		frame.open();
	});
});
