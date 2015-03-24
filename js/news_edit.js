$.datepicker.setDefaults($.datepicker.regional['nl']);
$.datepicker.setDefaults({
	dateFormat: 'yy-mm-dd'
});

$(function(){
	$('#itemdate').datepicker();
});
