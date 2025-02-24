jQuery(function () {
	var $form = $('form');
	var $img_add_btn = $form.find('#img_plus');
	var $model = $form.find('#thumbnail_model');
	var s3_url = $model.data('s3-url');
	var $input_image_files = $form.find('input[name="image_files"]');
	var $sortable_div = $form.find('div.sortable');

	function rewrite_image_files_input(){
		var image_files = [];
		$sortable_div.children('[data-file]:visible').each(function(){
			image_files.push($(this).data('file'));
		});
		$input_image_files.val(JSON.stringify(image_files));
	}

	Sortable.create($sortable_div.get(0), {
		onEnd: function(evt){
			rewrite_image_files_input();
		}
	});

	$form.find('span.img-delete').on('touchstart click', function() {
		$(this).closest('div.thumbnail-col').remove();
		rewrite_image_files_input();
	});

	$fileupload = $('[data-fileupload]');
	var messages = {
		acceptFileTypes: $fileupload.data('message-file-type-not-allowed'),
		maxFileSize: $fileupload.data('message-max-file-size'),
		minFileSize: $fileupload.data('message-min-file-size'),
		uploadedBytes : $fileupload.data('message-uploaded-bytes')
	};

    $fileupload.bind('fileuploadprocessfail', function (e, data) {
		$img_add_btn.removeClass('fa-spin fa-spinner').addClass('fa-plus');
		var error = data.files[data.index].error;
		alert(error);
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
		$img_add_btn.removeClass('fa-plus').addClass('fa-spinner fa-spin');
	}).on('fileuploaddone', function (e, data) {
		$img_add_btn.removeClass('fa-spin fa-spinner').addClass('fa-plus');

		if (data.result.hasOwnProperty('error')){
			alert(data.result.error);
		} else {
			$.each(data.result.filenames, function (index, file) {
				var $thumbnail = $model.clone();
				$thumbnail.removeAttr('id');
				$thumbnail.attr('data-file', file);
				var $img = $thumbnail.find('img');
				$img.attr('src', s3_url + file);

				var $del_btn = $thumbnail.find('span.img-delete');
				$del_btn.on('mouseup', function(e){
					e.stopPropagation();
				});
				$del_btn.on('click', function(){
					$thumbnail.remove();
					rewrite_image_files_input();
				});

				$thumbnail.removeClass('hidden');
				$model.parent().append($thumbnail);
				rewrite_image_files_input();
			});
		}
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
});
