$(document).ready(function () {

	$('span.img-delete').click(function() {

		var $thumbnail = $(this).closest('div.thumbnail');

		var src = $thumbnail.find('img').attr('src');

		alert(src);
	});

    $('#fileupload').bind('fileuploadprocessfail', function (e, data) {

		var error = (data.files[data.index].error == 'File type not allowed') ? 'Fout bestandstype' : data.files[data.index].error;

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

        $.each(data.result, function (index, file) {

            if (file.filename) {

				var input_str = '<input type="hidden" name="uploaded_images[]" value="';
				input_str += file.filename + '">'; 

				var $input = $(input_str);

				$('form').append($input);

				var $model = $('#thumbnail_model');

				var $thumbnail = $model.clone();

				$thumbnail.prop('id', file.filename);

				var $img = $thumbnail.find('img');

				var s3_url = $img.data('s3-url');

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
