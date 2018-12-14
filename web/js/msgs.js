$(document).ready(function() {
    $('#cid').change(function() {
		$('#filter_submit').click();
    });

	$('form#bulk').submit(function(){
		$('table#msgs input[type=checkbox]:checked').hide().appendTo(this);
		return true;
	});
});
