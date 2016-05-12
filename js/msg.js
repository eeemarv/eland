$(document).ready(function(){

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

	var imgs = images_con.data('images');
	imgs = imgs.split(',');

	if (imgs[0] != ''){
		jssor_init();
		$('#btn_remove').css('display', 'inline');
	} else {
		$('#no_images').css('display', 'inherit');
	}

	var jssor_slider1;

	function jssor_init()
	{
		$('#no_images').css('display', 'none');

		images_con.append(html_sl);

		var slides_cont = $('#slides_cont');

		$.each(imgs, function(k, v){
			slides_cont.append('<div><img src="' + bucket_url + v + '" u="image"/></div>');
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

		$(window).bind('load', ScaleSlider);
		$(window).bind('resize', ScaleSlider);
		$(window).bind('orientationchange', ScaleSlider);
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

	var contacts_div = $('#contacts');

	$.get(contacts_div.data('url'), function(data){
		contacts_div.html(data);
	}).done(function(){
		var $map = $('#map');
		var lat = $map.data('lat');
		var lng = $map.data('lng');

		var map = L.map('map').setView([lat, lng], 15);

		L.tileLayer('https://api.mapbox.com/v4/mapbox.streets/{z}/{x}/{y}.png?access_token={accessToken}', {
			attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
			maxZoom: 17,
			minZoom: 10,
			accessToken: $map.data('token')
		}).addTo(map);

		L.marker([lat, lng]).addTo(map);
	});
});
