jQuery(document).ready(function ($) {
	var options = {
		$FillMode: 3,
		$DragOrientation: 3,
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
	};
	
	$("#slider1_container").css("display", "block");
	var jssor_slider1 = new $JssorSlider$("slider1_container", options);

	//responsive code begin
	//you can remove responsive code if you don't want the slider scales
	//while window resizes
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
	//Scale slider after document ready
	ScaleSlider();
									
	//Scale slider while window load/resize/orientationchange.
	$(window).bind("load", ScaleSlider);
	$(window).bind("resize", ScaleSlider);
	$(window).bind("orientationchange", ScaleSlider);
	//responsive code end
});
