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

			img.attr('src', img.data('base-url') + data.result[0]['filename']);
			img.css('display', 'inherit');
			no_img.css('display', 'none');
			btn_remove.css('display', 'inherit');

		}
	}).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
});
