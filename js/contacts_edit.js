$(document).ready(function(){

	$('body').delay(100, function(){
		$('input').prop('disabled', false);
	});

	var $form = $('form');
	var $input = $form.find('input#value');
	var $select = $form.find('select');
	var contacts_format = $input.data('contacts-format');
	var $addon = $form.find('span#value_addon');
	var $fa = $addon.find('i');

	console.log(contacts_format);

	function select_change(){
		var $selected = $select.find('option:selected');
		var abbrev = $selected.data('abbrev');
		var format = contacts_format[abbrev];

		if (!format.fa){
			$addon.hide();
		} else {
			$fa.removeClass();
			$fa.addClass('fa fa-' + format.fa);
			$addon.show();
		}
	}

/*
	$select.change(function(){
		select_change();
	});

	select_change();
*/

});
