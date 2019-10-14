$(document).ready(function() {
	$('span[data-elas-soap-status]').each(function() {

		var path = $(this).data('elas-soap-status');
		var span = $(this);

		$.get(path, function(data){
			span.text(data);
		});
	});
});
