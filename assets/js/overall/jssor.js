export default function(el, jump_to_last_image){
	var $view_box = $(el).find('[data-view-box]');
	var $models = $(el).find('[data-models]');
	var image_files = $(el).data('image-files');
	if (image_files.length === 0){
		$view_box.html($models.find('[data-no-image]').clone());
		return;
	}
	var s3_url = $(el).data('s3-url');
	var $jssor = $models.find('[data-jssor-model]').clone();
	var $slide_model = $models.find('[data-jssor-slide-model');
	var $slides = $jssor.find('[data-u="slides"]');
	$.each(image_files, function(i, img){
		console.log(s3_url + img);
		var $slide = $slide_model.clone();
		$slide.removeAttr('data-jssor-slide-model');
		$slide.find('img').attr('data-src', s3_url + img);
		$slides.append($slide);
	});
	$view_box.html($jssor);
	var jssor_el = $jssor[0];
	var jssor_1 = new $JssorSlider$(jssor_el, {
		$FillMode: 3,
		$DragOrientation: 1,
		$PlayOrientation: 1,
		$ArrowKeyNavigation: 1,
		$BulletNavigatorOptions: {
			$Class: $JssorBulletNavigator$,
			$AutoCenter: 1,
			$ChanceToShow: 2,
			$SpacingX: 10,
			$SpacingY: 10
		},
		$ArrowNavigatorOptions: {
			$Class: $JssorArrowNavigator$,
			$ChanceToShow: 2,
			$AutoCenter: 2
		}
	});

	function ScaleSlider() {
		var parentWidth = $view_box.width();
		if (parentWidth) {
			jssor_1.$ScaleWidth(parentWidth);
		}
		else
		{
			window.setTimeout(ScaleSlider, 30);
		}
	}

	ScaleSlider();

	if (jump_to_last_image){
		jssor_1.$GoTo(jssor_1.$SlidesCount() - 1);
	}

	$(window).bind('load', ScaleSlider);
	$(window).bind('resize', ScaleSlider);
	$(window).bind('orientationchange', ScaleSlider);
};
