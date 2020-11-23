jQuery(function() {
	var $input_switch = $('input[name="expires_at_switch_enabled"]');
	var $input_days_default = $('input[name="expires_at_days_default"]');
	var $input_required = $('input[name="expires_at_required"]');
	function set()
	{
		if (!$input_days_default.val() || $input_required.prop('checked')){
			$input_switch.prop('disabled', true);
			return;
		}
		$input_switch.prop('disabled', false);
	}
	set();
	$input_days_default.on('keyup keydown change', function(){
		set();
	});
	$input_required.on('change', function(){
		set();
	});
});
