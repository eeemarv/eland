$(document).ready(function(){

	$('input[data-max-inputs]').each(function(){

		var max_inputs = $(this).data('max-inputs');

		var value_ary = $(this).val().split(',');

		var name = $(this).prop('name');

		var $li = $(this).parent();

		var $form_group = $(this).next('div.form-group');

		var $add_input = $(this).siblings('div.add-input');

		var $input = $form_group.find('input');

		if (value_ary[0] && value_ary.length > 1){

			$input.attr('value', '');
			$input.val(value_ary[0].trim());

		}

		if (value_ary.length < max_inputs){

			$add_input.removeClass('hidden');

			$add_input.find('span.btn').click(function(){

				var $cloned_group = $form_group.clone();

				$cloned_group.find('label').remove();
				$cloned_group.find('input').attr('value','');
				$cloned_group.find('div').addClass('col-sm-offset-3');

				$cloned_input = $cloned_group.find('input');

				var inputs_num = $li.find('input').length;
				inputs_num--;

				$cloned_input.prop('name', name + '_' + inputs_num);
				$cloned_input.prop('id', name + '_' + inputs_num);
				$cloned_input.val('');

				$cloned_group.insertBefore($add_input);

				if ((inputs_num + 2) > max_inputs){

					$add_input.addClass('hidden');

				}
			});
		}

		for (var i = 1; i < value_ary.length; i++){

			$cloned_group = $form_group.clone();

			$cloned_group.find('label').remove();
			$cloned_group.find('input').attr('value','');
			$cloned_group.find('div').addClass('col-sm-offset-3');

			$cloned_input = $cloned_group.find('input');

			$cloned_input.prop('name', name + '_' + i);
			$cloned_input.prop('id', name + '_' + i);

			if (value_ary[i]){
			
				$cloned_input.val(value_ary[i].trim());
			}

			$cloned_group.insertBefore($add_input);

			console.log(name + '_' + i);

		}

	});
});


