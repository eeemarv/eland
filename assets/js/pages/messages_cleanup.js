export default function() {
	var $input_switch_enabled = $('[data-expires-at-switch-enabled]');
	var $input_days_default = $('[data-expires-at-days-default]');
	var $input_required = $('[data-expires-at-required]');
	var $input_cleanup_enabled = $('[data-cleanup-enabled]');
	var $input_cleanup_after_days = $('[data-cleanup-after-days]');
	function set_switch_enabled_prop()
	{
		if (!$input_days_default.val() || $input_required.prop('checked')){
			$input_switch_enabled.prop('disabled', true);
			return;
		}
		$input_switch_enabled.prop('disabled', false);
	}
	function set_cleanup_after_days_prop()
	{
		if (!$input_cleanup_enabled.prop('checked')){
			$input_cleanup_after_days.prop('disabled', true);
			return;
		}
		$input_cleanup_after_days.prop('disabled', false);
	}
	set_switch_enabled_prop();
	set_cleanup_after_days_prop();
	$input_days_default.on('keyup keydown change', function(){
		set_switch_enabled_prop();
	});
	$input_required.on('change', function(){
		set_switch_enabled_prop();
	});
	$input_cleanup_enabled.on('change', function(){
		set_cleanup_after_days_prop();
	});
	$input_cleanup_after_days.closest('form').submit(function(){
		$input_cleanup_after_days.prop('disabled', false);
	});
};
