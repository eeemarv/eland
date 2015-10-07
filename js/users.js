$(document).ready(function() {
	
	$('#select_all').click(function(){
		change_select(true);
	});

	$('#deselect_all').click(function(){
		change_select(false);
	});

	function change_select(checked)
	{
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = checked;
		});
	}

	$('form[method="post"]').submit(function(event) {
		$('table > tbody > tr > td > input[type="checkbox"]:hidden').each(function(){
			$(this).prop('checked', false);
		});
	});	
});
