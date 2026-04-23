jQuery(function(){
	var $form = $('form');
	var $limits = $form.find('#form_limits');
	var $autominlimit = $form.find('#form_autominlimit');

	function set_auto(){
		console.log($limits[0].checked);
		if ($limits[0].checked){
			$autominlimit.prop('disabled', false);
		} else {
			$autominlimit.prop('disabled', true);
		}
	};
	console.log($limits[0].checked);
	set_auto();
	$limits.on('change', set_auto);
});
