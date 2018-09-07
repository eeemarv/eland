$(document).ready(function(){

	var now = Math.floor(new Date().getTime() / 1000);

	var $form = $('form');

	var $data_text_inputs = $form.find('input[type="text"][data-typeahead]');
	var $data_options = $form.find('form select option[data-typeahead]');
	var $target_text_inputs = $form.find('form input[type="text"][data-typeahead-source]');

	var session_params = $('body').data('session-params');

	var $remote_amount_container = $form.find('#remote_amount_container');
	var $amount_container = $form.find('#amount_container');

	var $remote_info = $remote_amount_container.find('ul');
	var $info_ratio = $remote_info.find('#info_ratio');
	var $info_ratio_span = $info_ratio.find('span');

	var $select = $('form select');
	var $option_self = $select.find('option#group_self');
	var ratio_self = $option_self.data('currencyratio');

	var $amount_input = $amount_container.find('input');
	var $remote_amount_input = $remote_amount_container.find('input');

	var $info = $amount_container.find('ul');
	var $info_remote_amount_unknown = $info.find('#info_remote_amount_unknown');

	var select_change = function(){

		var $option = $select.find('option:selected');

		var currency = $option.data('currency');
		var ratio = $option.data('currencyratio');

		if (currency && $option.attr('id') !== 'group_self'){

			$remote_amount_input.val(Math.round(($amount_input.val() / ratio_self) * ratio));

			$amount_input.on('keyup change blur keypress', function(){
				$remote_amount_input.val(Math.round(($(this).val() / ratio_self) * ratio));
			});

			$remote_amount_input.on('keyup change blur keypress', function(){
				$amount_input.val(Math.round(($(this).val() / ratio) * ratio_self));
			});

			$info_ratio_span.text(ratio);

			if ($amount_container.hasClass('col-sm-10')){
				$amount_container.removeClass('col-sm-10');
				$amount_container.addClass('col-sm-5');
			}

			$remote_amount_container.find('span.input-group-addon').eq(0).text(currency);

			$remote_amount_container.show();

		} else {

			$remote_amount_container.hide();

			if ($amount_container.hasClass('col-sm-5')){
				$amount_container.removeClass('col-sm-5');
				$amount_container.addClass('col-sm-10');
			}
		}

		if (currency || $option.attr('id') === 'group_self'){
			if (!$info_remote_amount_unknown.hasClass('hidden')){
				$info_remote_amount_unknown.addClass('hidden');
		  	}
		} else {
			$info_remote_amount_unknown.removeClass('hidden');
		}

	};

	if ($select.length){
		select_change();

		$select.change(function(){
			select_change();
		});
	}
});
