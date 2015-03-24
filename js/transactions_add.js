$.datepicker.setDefaults($.datepicker.regional['nl']);
$.datepicker.setDefaults({
	dateFormat: 'yy-mm-dd'
});

$(function(){
	$('#date').datepicker();
});
