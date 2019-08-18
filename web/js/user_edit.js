$(document).ready(function(){
	var $status = $('#status');
	var $accountrole = $('#accountrole');
	var $activate = $('div#activate');
	var $contact_input = $('input[data-access]');
	var $presharedkey_panel = $('#presharedkey_panel');

	$('body').delay(100, function(){
		$('input[type="email"]').prop('disabled', false);
	});

	status_change();
	accountrole_change();
	$status.change(status_change);
	$accountrole.change(accountrole_change);
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

	function accountrole_change(){
		if ($accountrole.val() == 'interlets'){

			$presharedkey_panel.show();
		} else {

			$presharedkey_panel.hide();
		}
	}
});
