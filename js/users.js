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
});
