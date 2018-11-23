$(document).ready(function(){

	$('body').delay(100, function(){
		$('input').prop('disabled', false);
	});

	var $form = $('form');
	var $input = $form.find('input#value');
	var $select = $form.find('select');
	var contacts_format = $input.data('contacts-format');
	var $addon = $form.find('span#value_addon');
	var $contact_explain = $form.find('p#contact-explain');
	var $fa = $addon.find('i');

	console.log(contacts_format);

	function select_change(){
		var $selected = $select.find('option:selected');
		var abbrev = $selected.data('abbrev');
		var format = contacts_format[abbrev];

		console.log(format);

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

			if (format.hasOwnProperty('explain')){
				$contact_explain.text(format.explain);
			} else {
				$contact_explain.text();
			}
		} else {
			$input.attr('type', 'text');
			$contact_explain.text();
		}
	}

	$select.change(function(){
		select_change();
	});

	select_change();

});
