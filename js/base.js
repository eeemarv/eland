$(document).ready(function() {
	$('[data-toggle=offcanvas]').click(function() {
		$('.row-offcanvas').toggleClass('active');
	});

	$('.footable').footable();

	$('a[data-elas-group-id]').click(function() {

		var ajax_loader = $('img.ajax-loader');
		ajax_loader.css('display', 'inherit');

		var group_id = $(this).data('elas-group-id');
		var elas_group_login = $('body').data('elas-group-login');

		$.get(elas_group_login + '&group_id=' + group_id, function(data){

			ajax_loader.css('display', 'none');

			if (data.error) {
				alert(data.error);
			} else if (data.login_url) {
				window.open(data.login_url);
			} else {
				alert('De pagina kon niet geopend worden.');
			}
		});

	});
});
