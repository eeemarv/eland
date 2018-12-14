$(document).ready(function(){

	$('input[data-max-inputs]').each(function(){

		var max_inputs = $(this).data('max-inputs');
		var value_ary = $(this).val().split(',');
		var name = $(this).prop('name');
		var $li = $(this).parent();
		var data_input = $(this);

		$(this).closest('form').submit(function(){

			var out = [];

			$li.find('div input').each(function(){

				var val = $(this).val();

				if (val){
					out.push(val);
				}
			});

			data_input.val(out.join(','));

		});

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

	var list_active = document.getElementById('list_active');
	var list_inactive = document.getElementById('list_inactive');
	var sortable_active = Sortable.create(list_active, {
		group: "periodic_mail",
		onAdd: function (evt){
			evt.item.classList.remove('bg-danger');
			evt.item.classList.add('bg-success');
		}
	});
	var sortable_inactive = Sortable.create(list_inactive, {
		group: "periodic_mail",
		onAdd: function (evt){
			evt.item.classList.remove('bg-success');
			evt.item.classList.add('bg-danger');
		}
	});

	$('a[data-o]').click(function(e){
		e.preventDefault();
		var option = $(this).data('o');
		console.log(option);
		var li_el = $(this).parent().parent().parent();
		li_el.find('span.lbl').text($(this).text());
		li_el.data('option', option);
		li_el.attr('data-option', option);
	});

	$('form[method="post"]').submit(function(event) {

		var l = $('ul#list_active');
		var p_string = '';
		$('ul#list_active > li[data-block]').each(function(){
			var ul_el = $(this);
			p_string += ul_el.attr('data-block') + '.';
			p_string += ul_el.attr('data-option') + ',';
		});
		p_string = p_string.slice(0, -1);
		p_string = '+' + p_string;
		$('#periodic_mail_block_ary').val(p_string);
	});
});
