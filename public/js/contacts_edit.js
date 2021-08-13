jQuery(function(){
	$('body').delay(100, function(){
		$('input').prop('disabled', false);
	});

	var $form = $('form');
	var $input = $form.find('[data-contacts-format]');
	var $select = $form.find('select[data-contact-type]');
	var contacts_format = $input.data('contacts-format');
	var $input_div = $input.parent();
	var $addon = $input_div.find('span');
	var $contact_help = $input_div.parent().find('span#contacts_value_help');
	var $fa = $addon.find('i');

	$contact_help.text('');

	function select_change(){
		var $selected = $select.find('option:selected');
		var abbrev = $selected.data('abbrev');
		var format = contacts_format[abbrev];

		$fa.removeClass();

		if (typeof(format) != 'undefined' && format !== null){
			if (format.hasOwnProperty('fa')){
				$fa.addClass('fa fa-' + format.fa);
			}

			if (format.hasOwnProperty('type')){
				$input.attr('type', format.type);
			} else {
				$input.attr('type', 'text');
			}

			if (format.hasOwnProperty('help')){
				$contact_help.text(format.help);
			} else {
				$contact_help.text('');
			}
		} else {
			$input.attr('type', 'text');
			$contact_help.text('');
		}
	}

	$select.on('change', function(){
		select_change();
	});

	select_change();
});
