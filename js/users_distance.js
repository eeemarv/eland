$(document).ready(function() {
	var lat = $('table').data('lat');
	var lng = $('table').data('lng');

	if (lat && lng){

		lat = Math.PI * lat / 180;
		lng = Math.PI * lng / 180;

		$('table tr td[data-lng]').each(function() {
			var llat = $(this).data('lat');
			var llng = $(this).data('lng');

			llat = Math.PI * llat / 180;
			llng = Math.PI * llng / 180;

			var th = lng - llng;
			var distance = (Math.sin(lat) * Math.sin(llat)) + (Math.cos(lat) * Math.cos(llat) * Math.cos(th));
			distance = Math.acos(distance);
	//		distance = distance * (180 / Math.PI) * 60 * 1.515 * 1.609344;
			distance = distance * 8381.763465709408;
			$(this).data('value', (Math.round(distance * 100) / 100));
			if (distance < 1){
				distance = Math.round(distance * 10) * 100;
				distance = distance + ' m';
			} else if (distance < 10){
				distance = Math.round(distance * 10) / 10;
				distance = distance + ' km';
			} else {
				distance = Math.round(distance);
				distance = distance + ' km';
			}
			$(this).text(distance);
		});
	}
});
