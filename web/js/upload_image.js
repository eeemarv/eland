$(document).ready(function () {
	var btn_remove = $('#btn_remove');
	var img = $('#img');
	var no_img = $('#no_img');

	$('#fileupload').bind('fileuploadprocessfail', function (e, data) {

		var error = (data.files[data.index].error === 'File type not allowed') ? 'Fout bestandstype' : data.files[data.index].error;
		alert(error);
		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');

	}).fileupload({
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		imageMaxWidth: 400,
		imageMaxHeight: 400,
		imageOrientation: true
	}).on('fileuploadadd', function (e, data) {
		$('#img_plus').removeClass('fa-plus').addClass('fa-spinner fa-spin');
	}).on('fileuploaddone', function (e, data) {
		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');

		if (data.result[0]) {

			var img_filename = img.data('base-url') + data.result[0];
			img.attr('src', img_filename);
			img.css('display', 'inherit');
			no_img.css('display', 'none');
			btn_remove.css('display', 'inherit');

			if (img.data('replace-logo')){
				$('a.logo').remove();
				var html_logo = '<a href="#" class="navbar-left hidden-xs logo">';
				html_logo += '<img height="50" src="';
				html_logo += img_filename;
				html_logo += '"></a>';
				$('div.navbar-header').prepend(html_logo);
			}
		}
	}).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
});
