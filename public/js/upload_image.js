jQuery(function(){
	var btn_remove = $('#btn_remove');
	var img = $('#img');
	var no_img = $('#no_img');
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
        acceptFileFypes: /(\.|\/)(jpg|jpeg|png|gif|svg)$/i,
		maxFileSize: 999000,
		minFileSize: 100,
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		imageMaxWidth: 400,
		imageMaxHeight: 400,
		loadImageFileTypes: /^image\/(gif|jpeg|jpg|png)$/,
		imageOrientation: true,
		messages: messages
	}).on('fileuploadadd', function (e, data) {
		$('#img_plus').removeClass('fa-plus').addClass('fa-spinner fa-spin');
	}).on('fileuploaddone', function (e, data) {
		$('#img_plus').removeClass('fa-spin fa-spinner').addClass('fa-plus');
		if (data.result.hasOwnProperty('error')){
			alert(data.result.error);
		} else {
			var img_filename = img.data('base-url') + data.result.filename;
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
	});
});
