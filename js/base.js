$(document).ready(function() {
	$('[data-toggle=offcanvas]').click(function() {
		$('.row-offcanvas').toggleClass('active');
	});

	$('.footable').footable();

	$('a[data-elas-group-id]').click(function() {

		var group_id = $(this).data('elas-group-id');
		var user_param = $('body').data('user-param');
		var group_url = $(this).data('elas-group-url');

		$.get('ajax/elas_group_token.php?group_id=' + group_id + '&' + user_param, function(data){
			if (data.error) {
				alert(data.error);
			} else if (data.token) {
				window.open(group_url + '/login.php?token=' + data.token);
			} else {
				alert('De pagina kon niet geopend worden.');
			}
		});

	});
});
