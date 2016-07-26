$(document).ready(function(){

	$('input[data-max-inputs]').each(function(){

		var max_inputs = $(this).data('max-inputs');

		var value_ary = $(this).val().split(',');

		var name = $(this).prop('name');

		var $li = $(this).parent();

		var $form_group = $(this).next('div.form-group');

		var $add_input = $(this).siblings('div.add-input');

		if (value_ary.length < max_inputs){

			$add_input.removeClass('hidden');
			$add_input.find('span.btn').click(function(){
				
			});
		}

		var $input = $form_group.find('input');

		if (value_ary[0] && value_ary.length > 1){

			$input.attr('value', '');
			$input.val(value_ary[0].trim());

		}

		var $cloned_group = $form_group.clone();

		$cloned_group.find('label').remove();
		$cloned_group.find('input').attr('value','');
		$cloned_group.find('div').addClass('col-sm-offset-3');

		for (var i = 1; i < value_ary.length; i++){

			$cloned_group.find('input').prop('name', name + '_' + i);
			$cloned_group.find('input').prop('id', name + '_' + i);

			if (value_ary[i]){
			
				$cloned_group.find('input').val(value_ary[i].trim());
			}

			$cloned_group.insertBefore($add_input);

			console.log(name + '_' + i);

		}

	});
});


