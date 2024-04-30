jQuery(function(){

	var $images_con = $('[data-images-container]');
	var jssor_slider1;

	function jssor_init(data_images)
	{
		var html_sl = '<div id="slider1_container" style="position: relative; top: 0px; left: 0px; width: 800px; height: 600px;">';
		html_sl += '<div u="slides" id="slides_cont" style="cursor: move; position: absolute; overflow: hidden; left: 0px; top: 0px; width: 800px; height: 600px;" id="slides">';
		html_sl += '</div>';
		html_sl += '<div u="navigator" class="jssorb01" style="bottom: 16px; right: 10px;">';
		html_sl += '<div u="prototype"></div>';
		html_sl += '</div>';
		html_sl += '<span u="arrowleft" class="jssora02l" style="top: 123px; left: 8px;"></span>';
		html_sl += '<span u="arrowright" class="jssora02r" style="top: 123px; right: 8px;"></span>';
		html_sl += '</div>';

		$('[data-no-images]').css('display', 'none');

		$images_con.append(html_sl);

		var slides_cont = $('#slides_cont');

		$.each(data_images.files, function(k, v){
			slides_cont.append('<div><img src="' + data_images.base_url + v + '" u="image"/></div>');
		});

		$("#slider1_container").css("display", "block");

		jssor_slider1 = new $JssorSlider$("slider1_container", {
			$FillMode: 3,
			$DragOrientation: 3,
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

		ScaleSlider();

		$(window).on('load', ScaleSlider);
		$(window).on('resize', ScaleSlider);
		$(window).on('orientationchange', ScaleSlider);
	}

	function ScaleSlider() {
		var parentWidth = $('#slider1_container').parent().width();
		if (parentWidth) {
			jssor_slider1.$ScaleWidth(parentWidth);
		}
		else
		{
			window.setTimeout(ScaleSlider, 30);
		}
	}

	var data_images = $images_con.data('images');
	if (data_images.files.length){
		jssor_init(data_images);
	}
});
