export default function () {

    // message_edit

    var $container = $('[data-fileupload-container]');
    var $btn = $container.find('[data-fileupload-btn]');
    var $btn_icon = $btn.find('[data-fileupload-btn-icon]');
    var $btn_input = $btn.find('[data-fileupload-btn-input]');
    var base_url = $container.data('base-url');
	var upload_url = $btn_input.data('fileupload-url');
	var max_width = $btn_input.data('fileupload-max-width');
	var max_height = $btn_input.data('fileupload-max-height');

	max_width = typeof max_width === 'undefined' ? 400 : max_width;
	max_height = typeof max_height === 'undefined' ? 400 : max_height;

	var $model = $container.find('[data-fileupload-model]');
    var $files_input = $container.find('input[data-fileupload-files-input]');

	var $sortable = $container.find('[data-fileupload-sortable]');

	function rewrite_image_files_input(){
		var image_files = [];
		$sortable.children('[data-fileupload-file]').each(function(){
			image_files.push($(this).data('fileupload-file'));
		});
		$files_input.val(JSON.stringify(image_files));
	}

    if (typeof Sortable === "function"){
        Sortable.create($sortable.get(0), {
            onEnd: function(evt){
                rewrite_image_files_input();
            }
        });
    }

	$container.find('[data-fileupload-btn-delete]').click(function() {
		$(this).closest('[data-fileupload-file]').remove();
		rewrite_image_files_input();
	});

    $btn.fileupload({
        url: upload_url,
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		imageMaxWidth: max_width,
		imageMaxHeight: max_height,
        imageOrientation: true,
        dataType: 'json'

    })
    .on('fileuploadprocessfail', function (e, data) {

		// fix me
        var error = (data.files[data.index].error == 'File type not allowed') ? 'Fout bestandstype' : data.files[data.index].error;
		alert(error);

		$btn_icon.removeClass('fa-spin fa-spinner').addClass('fa-plus');

	}).on('fileuploadadd', function (e, data) {

        $btn_icon.removeClass('fa-plus').addClass('fa-spinner fa-spin');

	}).on('fileuploaddone', function (e, data) {

		$btn_icon.removeClass('fa-spin fa-spinner').addClass('fa-plus');

        $.each(data.result, function (index, file) {

            if (file) {

                var $model_clone = $model.clone();

				$model_clone.removeAttr('data-fileupload-model');
				$model_clone.attr('data-fileupload-file', file);
				var $img = $model_clone.find('img');
				$img.attr('src', base_url + file);

                $model_clone.find('[data-fileupload-btn-delete]').click(function() {
                    $model_clone.remove();
                    rewrite_image_files_input();
                });

                $model_clone.removeAttr('hidden');
                $model.parent().append($model_clone);

				rewrite_image_files_input();
            } else {
				alert('Fout bij het opladen van de afbeelding.');
			}
        });

	 });

	 /*
     .prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
     */
     // messages show
/*
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

		var data_images = $images_con.data('images');

        $.each(data.result, function (index, file) {

            if (file) {
				data_images.files.push(file);
				$("#slider1_container").remove();
				jssor_init(data_images);
				jssor_slider1.$GoTo(jssor_slider1.$SlidesCount() - 1);
				$('#btn_remove').css('display', 'inherit');
            } else {
				alert('Fout bij het opladen van de afbeelding.');
            }

            $images_con.data('images', data_images);
        });
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

     // user show upload

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
*/
};