$(document).ready(function() {

	$(':button[data-table-sel="all"]').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = true;
		});
	});

	$(':button[data-table-sel="none"]').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = false;
		});
	});

	$(':button[data-table-sel="invert"]').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = !this.checked;
		});
	});

	$('form[method="post"]').submit(function(event) {
		var $form = $(this);
		$('table input[type="checkbox"]:visible:checked').each(function(){
			$('<input>').attr({
				'type': 'hidden',
				'name': $(this).attr('name'),
				"value": $(this).attr('value')
			}).appendTo($form);;
		});
	});
});
