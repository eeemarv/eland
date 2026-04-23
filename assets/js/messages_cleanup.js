jQuery(function() {
	var $input_switch = $('[data-expires-at-switch-enabled]');
	var $input_days_default = $('[data-expires-at-days-default]');
	var $input_required = $('[data-expires-at-required]');
	var $input_cleanup_enabled = $('[data-cleanup-enabled]');
	var $input_cleanup_after_days = $('[data-cleanup-after-days]');
	var $form = $('form[method="post"]');

	function set_switch()
	{
		if (!$input_days_default.val() || $input_required.prop('checked')){
			$input_switch.prop('disabled', true);
			return;
		}
		$input_switch.prop('disabled', false);
	}

	function set_cleanup_after_days()
	{
		$input_cleanup_after_days.prop('disabled', !$input_cleanup_enabled.prop('checked'));
	}

	set_switch();
	set_cleanup_after_days();

	$input_days_default.on('keyup keydown change', function(){
		set_switch();
	});

	$input_required.on('change', function(){
		set_switch();
	});

	$input_cleanup_enabled.on('change', function(){
		set_cleanup_after_days();
	});

	$form.on('submit', function() {
		$input_cleanup_after_days.prop('disabled', false);
	});
});
