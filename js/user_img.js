$(function () {
	var btn_remove = $('#btn_remove');
	var user_img = $('#user_img');
	var no_user_img = $('#no_user_img');

	$('#fileupload').fileupload({
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),

	}).on('fileuploadadd', function (e, data) {

		$('#img_plus').removeClass('fa-plus').addClass('fa-spinner fa-spin');

	}).on('fileuploaddone', function (e, data) {

		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');

		if (data.result.filename) {

			user_img.attr('src', user_img.data('bucket-url') + data.result.filename);
			user_img.css('display', 'inherit');
			no_user_img.css('display', 'none');
			btn_remove.css('display', 'inline');

		} else {
			alert('Fout bij het opladen van de afbeelding: ' + data.result.error);
		}
	}).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

	btn_remove.click(function(){

		btn_remove.find('i').removeClass('fa-times').addClass('fa-spin fa-spinner');

		$.post(btn_remove.data('url'), {}, function(data){

			if (data.success)
			{

				no_user_img.css('display', 'inherit');
				user_img.css('display', 'none');
				btn_remove.css('display', 'none');

			} else {

				alert('Fout bij het verwijderen: ' + data.error);

			}

			btn_remove.find('i').removeClass('fa-spin fa-spinner').addClass('fa-times');			

		}, 'json').fail(function() {

			alert('Fout bij het verwijderen foto');
			btn_remove.find('i').removeClass('fa-spin fa-spinner').addClass('fa-times');

		});
	});

});

