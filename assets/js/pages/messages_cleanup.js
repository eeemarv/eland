export default function() {
	var $input_switch = $('[data-expires-at-switch-enabled]');
	var $input_days_default = $('[data-expires-at-days-default]');
	var $input_required = $('[data-expires-at-required]');
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
};
