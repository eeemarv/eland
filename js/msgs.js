$(document).ready(function() {
    $('#cid').change(function() {
        this.form.submit();
    });

	$('form#bulk').submit(function(){
		$('table#msgs input[type=checkbox]:checked').hide().appendTo(this);
		return true;
	});
});

