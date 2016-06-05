$(document).ready(function(){
	var $status = $('#status');
	var $mail = $('input[type="email"]');

	update_required();

	$status.change(update_required);

	function update_required(){
		if ($status.val() == '1' || $status.val() == '2'){
			$mail.eq(0).prop('required', true);
			return;
		}

		$mail.eq(0).prop('required', false);
	}
});
