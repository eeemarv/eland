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
});

var fi = $('#fileupload');

/*
fi.fileupload({
    dataType: 'json',
    acceptFileTypes: /(\.|\/)(jpe?g)$/i,
    maxFileSize: 5000000, 
    disableImageResize: /Android(?!.*Chrome)|Opera/
        .test(window.navigator && navigator.userAgent),
    imageMaxWidth: 800,
    imageMaxHeight: 600,
    imageCrop: true,
    dropZone: $('body')
});
*/

/*
$('#file_upload').fileupload({
    url: 'messages.php',
    done: function (e, data) {
        $.each(data.result, function (index, file) {
            $('<p/>').text(file.name).appendTo('body');
        });
    }
});
*/

var contacts_div = $('#contacts');
var uid = contacts_div.attr('data-uid');

$.get('contacts.php?inline=1&uid=' + uid, function(data){
	contacts_div.html(data);
});
