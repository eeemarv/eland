$(document).ready(function(){

	$('input[data-max-inputs]').each(function(){

		var max_inputs = $(this).data('max-inputs');

		var value_ary = $(this).val().split(',');

		var name = $(this).prop('name');

		var $li = $(this).parent();

		var $form_group = $(this).next('div.form-group');

		var $add_input = $(this).siblings('div.add-input');

		$add_input.removeClass('hidden');

		var $input = $form_group.find('input');

		if (value_ary[0] && value_ary.length > 1){

			$input.attr('value', '');
			$input.val(value_ary[0].trim());

		}

		var $cloned_group = $form_group.clone();

		$cloned_group.find('label').remove();

		for (var i = 1; i < value_ary.length; i++){

			$group_clone = $cloned_group.clone();

			$cloned_group.find('input').prop('name', name + '_' + (i + 1));
			$cloned_group.find('input').prop('id', name + '_' + (i + 1));
			$cloned_group.find('input').attr('value','');
			$cloned_group.find('div').addClass('col-sm-offset-3');

			if (value_ary[i]){
			
				$cloned_group.find('input').val(value_ary[i].trim());

			}

			$cloned_group.insertAfter($form_group);

			console.log(name + '_' + (i + 1));

		}

	});

});


