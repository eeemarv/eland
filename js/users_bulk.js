$(document).ready(function() {

	(function ($, undefined) {
		$.fn.getCursorPosition = function () {
			var el = $(this).get(0);
			var pos = 0;
			if ('selectionStart' in el) {
				pos = el.selectionStart;
			} else if ('selection' in document) {
				el.focus();
				var Sel = document.selection.createRange();
				var SelLength = document.selection.createRange().text.length;
				Sel.moveStart('character', -el.value.length);
				pos = Sel.text.length - SelLength;
			}
			return pos;
		}
	})(jQuery);

	var $textarea = $('textarea#bulk_mail_content');

	$('#insert_vars span.btn').click(function(){

		var position = $textarea.getCursorPosition();

		var content = $textarea.val();
		var new_content = content.substr(0, position) + $(this).text() + content.substr(position);
		$textarea.val(new_content);
		$textarea.focus();

	});
});
