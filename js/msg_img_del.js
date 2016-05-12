$(document).ready(function () {

	$('span[data-img-del]').click(function(){

		var btn = $(this);

		btn.find('i').removeClass('fa-times').addClass('fa-spin fa-spinner');

		$.post(btn.data('url'), {}, function(data) {

			if (data.success)
			{
				btn.parent().parent().parent().remove();
			} else {
				alert('Fout bij het verwijderen: ' + data.error);
				btn.find('i').removeClass('fa-spin fa-spinner').addClass('fa-times');
			}
		}, 'json').fail(function() {
			alert('Fout bij het verwijderen afbeelding');
			btn.find('i').removeClass('fa-spin fa-spinner').addClass('fa-times');
		});
	});
});

