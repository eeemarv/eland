jQuery(function () {
	var $fileupload = $('[data-fileupload]');
	var messages = {
		acceptFileTypes: $fileupload.data('message-file-type-not-allowed'),
		maxFileSize: $fileupload.data('message-max-file-size'),
		minFileSize: $fileupload.data('message-min-file-size'),
		uploadedBytes : $fileupload.data('message-uploaded-bytes')
	};
    $fileupload.on('fileuploadprocessfail', function (e, data) {
		var error = data.files[data.index].error;
		alert(error);
		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');
	}).fileupload({
		dataType: 'json',
		autoUpload: true,
        acceptFileFypes: /(\.|\/)(jpg|jpeg|png|gif|webp|svg)$/i,
		maxFileSize: 999000,
		minFileSize: 100,
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		imageMaxWidth: 400,
		imageMaxHeight: 400,
		loadImageFileTypes: /^image\/(gif|jpeg|jpg|webp|png|svg)$/,
		imageOrientation: true,
		messages: messages
	}).on('fileuploadadd', function (e, data) {
		$('#img_plus').removeClass('fa-plus').addClass('fa-spinner fa-spin');
	}).on('fileuploaddone', function (e, data) {
		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');
		var data_images = $images_con.data('images');
		if (data.result.hasOwnProperty('error')){
			alert(data.result.error);
		} else {
			$.each(data.result.filenames, function (index, file) {
				data_images.files.push(file);
				$("#slider1_container").remove();
				jssor_init(data_images);
				jssor_slider1.$GoTo(jssor_slider1.$SlidesCount() - 1);
				$('#btn_remove').css('display', 'inherit');
			});
			$images_con.data('images', data_images);
		}
     });
});
