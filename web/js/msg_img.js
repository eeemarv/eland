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

		var imgs = $images_con.data('imgs');

        $.each(data.result, function (index, file) {

            if (file.filename) {

				if (imgs[0] == '')
				{

					imgs = new Array(file.filename);

				} else {

					imgs.push(file.filename);
				}

				$("#slider1_container").remove();

				jssor_init(imgs);

				jssor_slider1.$GoTo(jssor_slider1.$SlidesCount() - 1);

				$('#btn_remove').css('display', 'inline');

            } else {

				alert('Fout bij het opladen van de afbeelding: ' + file.error);

            }

            $images_con.data('imgs', imgs);
            
        });

		$images_con.data('imgs', imgs);
        
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

});

