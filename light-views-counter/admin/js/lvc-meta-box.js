/**
 * Light Views Counter - Meta Box JavaScript
 *
 * Handles the meta box UI interactions in the post editor.
 *
 * @package LightViewsCounter
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		/**
		 * Show input container when "Change View Count" button is clicked
		 */
		$('#lvc-show-input-btn').on('click', function () {
			$('#lvc-show-input-btn').hide();
			$('#lvc-input-container').slideDown(200);
			$('#lightvc_custom_views').focus();
		});

		/**
		 * Cancel and hide input container
		 */
		$('#lvc-cancel-btn').on('click', function () {
			$('#lvc-input-container').slideUp(200, function () {
				$('#lvc-show-input-btn').show();
			});
			$('#lightvc_custom_views').val(''); // Clear input
		});
	});

})(jQuery);
