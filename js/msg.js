//jQuery(document).ready(function ($) {

	var html_sl = '<div id="slider1_container" style="position: relative; top: 0px; left: 0px; width: 800px; height: 600px;">';
	html_sl = html_sl + '<div u="slides" id="slides_cont" style="cursor: move; position: absolute; overflow: hidden; left: 0px; top: 0px; width: 800px; height: 600px;" id="slides">';
	html_sl = html_sl + '</div>';
	html_sl = html_sl + '<div u="navigator" class="jssorb01" style="bottom: 16px; right: 10px;">';
	html_sl = html_sl + '<div u="prototype"></div>';
	html_sl = html_sl + '</div>';
	html_sl = html_sl + '<span u="arrowleft" class="jssora02l" style="top: 123px; left: 8px;"></span>';
	html_sl = html_sl + '<span u="arrowright" class="jssora02r" style="top: 123px; right: 8px;"></span>';
	html_sl = html_sl + '</div>';

	var images_con = $('#images_con');
	var bucket_url = images_con.attr('data-bucket-url');

	images_con.append(html_sl);

	var imgs = images_con.data('images');
	imgs = imgs.split(',');

	var slides_cont = $('#slides_cont');

	$.each(imgs, function(k, v){
		slides_cont.append('<div><img src="' + bucket_url + v + '" u="image"/></div>');
	});

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

	ScaleSlider();

	$(window).bind('load', ScaleSlider);
	$(window).bind('resize', ScaleSlider);
	$(window).bind('orientationchange', ScaleSlider);
//});

var contacts_div = $('#contacts');
var uid = contacts_div.attr('data-uid');

$.get('contacts.php?inline=1&uid=' + uid, function(data){
	contacts_div.html(data);
});
