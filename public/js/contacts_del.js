jQuery(function(){
	var $form = $('form');
	var $input = $form.find('[data-fa]');
	var fa = $input.data('fa');
	var $input_div = $input.parent();
	var $addon = $input_div.find('span');
	var $contact_help = $input_div.parent().find('span#contacts_value_help');
	var $fa = $addon.find('i');

	$contact_help.text('');
	$fa.removeClass();
	$fa.addClass('fa fa-' + fa);
});
