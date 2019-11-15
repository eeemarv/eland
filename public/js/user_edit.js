$(document).ready(function(){
	var $status = $('#status');
	var $activate = $('div#activate');
	var $contact_input = $('input[data-access]');

	$('body').delay(100, function(){
		$('input[type="email"]').prop('disabled', false);
	});

	status_change();
	$status.change(status_change);
	$contact_input.each(access_required);
	$contact_input.keyup(access_required);

	function access_required(){
		var $access = $('input[name="' + $(this).data('access') + '"]');

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
