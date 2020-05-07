export default function(){

	var $form = $('form');

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

	var $info_admin_limit = $amount_container.find('#info_admin_limit');

	var $account_info = $form.find('ul#account_info');
	var $account_info_typeahead = $account_info.find('li#info_typeahead');
	var $account_info_no_typeahead = $account_info.find('li#info_no_typeahead');

	var select_change = function(){

		var $option = $select.find('option:selected');

		var currency = $option.data('currency');
		var ratio = $option.data('currencyratio');

		if ($option.data('typeahead')){
			$account_info_typeahead.removeClass('hidden');
			$account_info_typeahead.show();
			$account_info_no_typeahead.hide();
		} else {
			$account_info_typeahead.hide();
			$account_info_no_typeahead.removeClass('hidden');
			$account_info_no_typeahead.show();
		}

		if ($option.attr('id') === 'group_self'){
			$info_admin_limit.show();
		} else {
			$info_admin_limit.hide();
		}

		if (currency && $option.attr('id') !== 'group_self'){

			$remote_amount_input.val(Math.round(($amount_input.val() / ratio_self) * ratio));

			$amount_input.on('keyup change blur keypress', function(){
				$remote_amount_input.val(Math.round(($(this).val() / ratio_self) * ratio));
			});

			$remote_amount_input.on('keyup change blur keypress', function(){
				$amount_input.val(Math.round(($(this).val() / ratio) * ratio_self));
			});

			$info_ratio_span.text(ratio);

			if ($amount_container.hasClass('col-sm-12')){
				$amount_container.removeClass('col-sm-12');
				$amount_container.addClass('col-sm-6');
			}

			$remote_amount_container.find('span.input-group-addon').eq(0).text(currency);

			$remote_amount_container.show();

		} else {

			$remote_amount_container.hide();

			if ($amount_container.hasClass('col-sm-6')){
				$amount_container.removeClass('col-sm-6');
				$amount_container.addClass('col-sm-12');
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
};
