jQuery(function() {

	$(':button[data-table-sel="all"]').on('click', function(){
		$('table input[type="checkbox"]:visible:not(:disabled)').each(function(){
			this.checked = true;
		});
	});

	$(':button[data-table-sel="none"]').on('click', function(){
		$('table input[type="checkbox"]:visible:not(:disabled)').each(function(){
			this.checked = false;
		});
	});

	$(':button[data-table-sel="invert"]').on('click', function(){
		$('table input[type="checkbox"]:visible:not(:disabled)').each(function(){
			this.checked = !this.checked;
		});
	});

	$('form[method="post"]').on('submit', function(event) {
		const $form = $(this);
		$('table input[type="checkbox"]:visible:checked:not(:disabled)').each(function(){
			$('<input>').attr({
				'type': 'hidden',
				'name': $(this).attr('name'),
				"value": $(this).attr('value')
			}).appendTo($form);;
		});
	});

	$('form[method="post"]').on('submit', function(ev){
		const $form = $(this);
    const form_name = $form.attr('name');
    const selected_id = form_name + '_selected';
    const selected = [];
    $('table input[type="checkbox"]:checked:not(:disabled)').each(function() {
      selected.push($(this).val());
    });
    $form.find('#' + selected_id).attr('value', selected.join(','));
	});
});
