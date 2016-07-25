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

		var sel_ary = [];

		$('table input[type="checkbox"]:visible:checked').each(function(){
			sel_ary.push($(this).attr('name').split('sel_')[1]);
		});

		$('<input type="hidden">').attr({"name": "sel", "value": sel_ary.join()}).appendTo(this);
	});	
});
