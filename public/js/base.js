$(document).ready(function() {

	$('[data-toggle=offcanvas]').click(function() {
		$('.row-offcanvas').toggleClass('active');
	});

	$('.footable').footable();

	$('a[data-elas-group-login]').click(function() {

		var ajax_loader = $('img.ajax-loader');
		ajax_loader.css('display', 'inherit');

		var path = $(this).data('elas-group-login');

		$.get(path, function(data){

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

	$('form[method="get"]').submit(function(){
		$(this).find(':input').each(function() {
			var inp = $(this);
			if (!inp.val()) {
				inp.prop('disabled', true);
			}
		});
	});
});
