$(document).ready(function(){
	var $status = $('#status');
	var $mail = $('input[type="email"]');
	var $activate = $('div#activate');

	status_change();

	$status.change(status_change);

	function status_change(){
		if ($status.val() == '1' || $status.val() == '2'){

//			$mail.eq(0).prop('required', true);

			$activate.show();
			return;
		} else {
//			$mail.eq(0).prop('required', false);

			$activate.hide();
		}
	}
});
