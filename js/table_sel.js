$(document).ready(function() {
	
	$('#select_all').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = true;
		});
	});

	$('#deselect_all').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = false;
		});
	});

	$('#invert_selection').click(function(){
		$('table input[type="checkbox"]:visible').each(function() {
			this.checked = !this.checked;
		});
	});

	$('form[method="post"]').submit(function(event) {

		var sel_ary = [];

		$('table input[type="checkbox"]:visible:checked').each(function(){
			sel_ary.push($(this).attr('name').split('sel_')[1]);
		});

		$('<input type="hidden">').attr({"name": "sel", "value": sel_ary.join()}).appendTo(this);
	});	
});
