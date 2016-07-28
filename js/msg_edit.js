$(document).ready(function () {

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

				var filename_input_html = '<input type="hidden" name="uploaded_images[]" value="';

				filename_input_html += file.filename + '">';

				$('form').append(filename_input_html);


            } else {

				alert('Fout bij het opladen van de afbeelding: ' + file.error);

			}  
        });
        
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

});
