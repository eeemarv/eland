export default function () {
	var $container = $('[data-fileupload-container]');
	var s3_url = $container.data('s3-url');
    var $btn = $container.find('[data-fileupload-btn]');
    var $btn_icon = $btn.find('[data-fileupload-btn-icon]');
    var $btn_input = $btn.find('[data-fileupload-btn-input]');
	var upload_url = $btn_input.data('fileupload-url');
	var size_600 = $btn_input.data('filupload-600');
	var size_800 = $btn_input.data('filupload-800');
	var size = typeof size_800 === 'undefined' ? 400 : 800;
	var size = typeof size_600 === 'undefined' ? 400 : 600;

	var $model = $container.find('[data-fileupload-model]');
    var $files_input = $container.find('input[data-fileupload-files-input]');
	var $sortable = $container.find('[data-fileupload-sortable]');

	var $jssor = $('[data-jssor]');
	var $logo_viewbox = $container.find('[data-logo-viewbox]');
	var $no_image = $container.find('[data-no-image]');

	function rewrite_image_files_input(){
		var image_files = [];
		$sortable.children('[data-fileupload-file]').each(function(){
			image_files.push($(this).data('fileupload-file'));
		});
		$files_input.val(JSON.stringify(image_files));
	}

    if ($sortable && $.fn.sortable){
		$sortable.sortable({
            onEnd: function(e){
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
		imageMaxWidth: size,
		imageMaxHeight: size,
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
            if ($model.length && file) {
                var $model_clone = $model.clone();
				$model_clone.removeAttr('data-fileupload-model');
				$model_clone.attr('data-fileupload-file', file);
				var $img = $model_clone.find('img');
				$img.attr('src', s3_url + file);
				$model_clone.find('[data-fileupload-btn-delete]').click(function() {
					$model_clone.remove();
					rewrite_image_files_input();
				});
				$model_clone.removeAttr('hidden');
				$model.parent().append($model_clone);
				rewrite_image_files_input();
			} else if ($jssor.length && file){
				var image_files = $container.data('image-files');
				image_files.push(file);
				$container.data('image-files', image_files);
				$jssor.jssor(image_files, true);
            } else if ($logo_viewbox.length && file){
				$logo_viewbox.attr('src', s3_url + file);
				$no_image.attr('hidden', '');
				$logo_viewbox.removeAttr('hidden');
				var $logo = $container.find('[data-logo]');
				$logo.attr('src', s3_url + file);
				var $brand = $('[data-brand]');
				$brand.find('[data-logo]').remove();
				$brand.prepend($logo.clone());
			} else {
				alert('Fout bij het opladen van de afbeelding.');
			}
        });
	 }).prop('disabled', !$.support.fileInput)
	 	.parent().addClass($.support.fileInput ? undefined : 'disabled');;

	 /*

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