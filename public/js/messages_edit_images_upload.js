$(document).ready(function () {
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

	$form.find('span.img-delete').click(function() {
		$(this).closest('div.thumbnail-col').remove();
		rewrite_image_files_input();
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

            if (file) {
				var $thumbnail = $model.clone();
				$thumbnail.removeAttr('id');
				$thumbnail.attr('data-file', file);
				var $img = $thumbnail.find('img');
				$img.attr('src', s3_url + file);

				$thumbnail.find('span.img-delete').click(function(){
					$thumbnail.remove();
					rewrite_image_files_input();
				});

				$thumbnail.removeClass('hidden');
				$model.parent().append($thumbnail);
				rewrite_image_files_input();
            } else {
				alert('Fout bij het opladen van de afbeelding.');
			}
        });

     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
});
