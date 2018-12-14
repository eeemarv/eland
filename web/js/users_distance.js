$(document).ready(function() {
	var lat_1 = $('table').data('lat');
	var lng_1 = $('table').data('lng');

	if (lat_1 && lng_1){

		lat_1 = Math.PI * lat_1 / 180;
		lng_1 = Math.PI * lng_1 / 180;

		$('table tr td[data-lng]').each(function() {

			var lat_2 = $(this).data('lat');
			var lng_2 = $(this).data('lng');

			lat_2 = Math.PI * lat_2 / 180;
			lng_2 = Math.PI * lng_2 / 180;

			var lat_d = lat_2 - lat_1;
			var lng_d = lng_2 - lng_1;

			var angle = Math.sin(lat_d / 2) * Math.sin(lat_d / 2) +
				Math.cos(lat_1) * Math.cos(lat_2) *
				Math.sin(lng_d / 2) * Math.sin(lng_d / 2);
			var distance = 2 * Math.atan2(Math.sqrt(angle), Math.sqrt(1 - angle)) * 6371;

			$(this).attr('data-value', (Math.round(distance * 100) / 100));

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
