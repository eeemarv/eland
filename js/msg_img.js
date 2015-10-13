$(function () {
/*

*/


//	images_con('[u="slides"]').append('<p>hdmkqsdfjmkqsdjmk</p>');


    $('#fileupload').fileupload({
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent)

    }).on('fileuploaddone', function (e, data) {

        $.each(data.result, function (index, file) {
            if (file.filename) {

				imgs.push(file.filename);
				images_con.data('images', imgs.join(','));
				$("#slider1_container").remove();

				images_con.append(html_sl);

				slides_cont = $('#slides_cont');

				$.each(imgs, function(k, v){
					slides_cont.append('<div><img src="' + bucket_url + v + '" u="image"/></div>');
				});

				jssor_slider1 = new $JssorSlider$("slider1_container", options);
				ScaleSlider();

				for (var i = jssor_slider1.$CurrentIndex(); i < jssor_slider1.$SlidesCount(); i++)
				{
					jssor_slider1.$Next();
				}

            } else {
				alert('Fout bij het opladen van de afbeelding.');
            }
        });
     }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');

});

