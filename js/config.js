$(document).ready(function(){

	$('input[data-max-inputs]').each(function(){

		var max_inputs = $(this).data('max-inputs');

		var value_ary = $(this).val().split(',');

		var name = $(this).prop('name');

		var $li = $(this).parent();

		var $form_group = $(this).siblings('div.form-group');

		var $extra_btn_form_group = $(this).find('div.extra-field');

		var $input = $form_group.find('input');

		console.log($input);

		if (value_ary[0]){

			$input.val(value_ary[0].trim());

		}

		var $cloned_form_group = $form_group.clone();

		$cloned_form_group.find('label').remove();

		console.log($cloned_form_group);
		console.log(name);
		console.log(value_ary);

		for (var i = 1; i < value_ary.length; i++){

			

			$group_clone = $cloned_form_group.clone();
			$group_clone.find('input').prop('name', name + '_' + (i + 1));
			$group_clone.find('input').prop('id', name + '_' + (i + 1));
			$group_clone.find('div').addClass('col-sm-offset-3');

			if (value_ary[i]){
			
				$group_clone.find('input').val(value_ary[i].trim());

			}

			$group_clone.insertAfter($form_group);

			console.log(name + '_' + (i + 1));

		}

	});

});


