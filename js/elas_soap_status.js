$(document).ready(function() {
	$('span[data-elas-soap-status]').each(function() {

		var group_id = $(this).data('elas-soap-status');
		var session_params = $('body').data('session-params');
		var params = {"group_id": group_id};
		$.extend(params, session_params);

		var url = './ajax/elas_soap_status.php?' + $.param(params);
		var span = $(this);

		$.get(url, function(data){
			span.text(data);
		});

	});
});
