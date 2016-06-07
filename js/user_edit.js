$(document).ready(function(){
	var $status = $('#status');
	var $mail = $('input[type="email"]');
	var $activate = $('div#activate');
	var $contact_input = $('input[data-access]');

	status_change();

	$status.change(status_change);
	
	$contact_input.each(access_required);

	$contact_input.keyup(access_required);

	function access_required(){
		var $access = $('div#' + $(this).data('access') + ' input');

		if ($(this).val() == ''){

			$access.prop('required', false);
		} else {

			$access.prop('required', true);
		}
	}

	function status_change(){
		if ($status.val() == '1' || $status.val() == '2'){

			$activate.show();
			return;
		} else {

			$activate.hide();
		}
	}
});
