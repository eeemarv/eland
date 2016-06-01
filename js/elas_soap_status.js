$(document).ready(function() {
	$('span[data-elas-soap-status]').each(function() {

		var url = $(this).data('elas-soap-status');
		var elas_group_login = $('body').data('elas-group-login');
		var span = $(this);

		$.get(url, function(data){
			console.log('hu');
			console.log(data);
			span.text(data);
		});

	});
});
