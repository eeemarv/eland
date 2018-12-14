$(document).ready(function () {

	var $form = $('form');

	var $img_add_btn = $form.find('#img_plus');

	var $model = $form.find('#thumbnail_model');

	var s3_url = $model.data('s3-url');

	$form.find('span.img-delete').click(function() {

		var $thumbnail_col = $(this).closest('div.thumbnail-col');

		var src = $thumbnail_col.find('img').attr('src');

		var filename = src.replace(s3_url, '').trim();

		var input_str = '<input type="hidden" name="deleted_images[]" value="';

		input_str += filename + '">';

		var $input = $(input_str);

		$form.append($input);

		$form.find('input[type="hidden"][name="uploaded_images[]"][value="' + filename + '"]').remove();

		$thumbnail_col.remove();
	});

    $('#fileupload').bind('fileuploadprocessfail', function (e, data) {

		var error = (data.files[data.index].error == 'File type not allowed') ? 'Fout bestandstype' : data.files[data.index].error;

		alert(error);

		$img_add_btn.removeClass('fa-spin fa-spinner').addClass('fa-plus');

	}).fileupload({

		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		imageMaxWidth: 400,
		imageMaxHeight: 400,
		imageOrientation: true

	}).on('fileuploadadd', function (e, data) {

		$img_add_btn.removeClass('fa-plus').addClass('fa-spinner fa-spin');

	}).on('fileuploaddone', function (e, data) {

		$img_add_btn.removeClass('fa-spin fa-spinner').addClass('fa-plus');

        $.each(data.result, function (index, file) {

            if (file.filename) {

				var input_str = '<input type="hidden" name="uploaded_images[]" value="';
				input_str += file.filename + '">'; 

				var $input = $(input_str);

				$form.append($input);

				var $thumbnail = $model.clone();

				$thumbnail.prop('id', file.filename);

				var $img = $thumbnail.find('img');

				$img.attr('src', s3_url + file.filename);

				$thumbnail.find('span.img-delete').click(function(){

					$input.remove();
					$thumbnail.remove();
				});

				$thumbnail.removeClass('hidden');

				$model.parent().append($thumbnail);

            } else {

				alert('Fout bij het opladen van de afbeelding: ' + file.error);

			}  
        });
        
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

});
