jQuery(function() {
  let $td_self = $('table tbody tr td[data-distance-self]');

	if ($td_self){
    let $table = $td_self.closest('table');
    let lat_1 = $td_self.data('lat');
    let lng_1 = $td_self.data('lng');

		lat_1 = Math.PI * lat_1 / 180;
		lng_1 = Math.PI * lng_1 / 180;
		$table.find('tbody tr td[data-lng]').each(function() {

			let lat_2 = $(this).data('lat');
			let lng_2 = $(this).data('lng');

			lat_2 = Math.PI * lat_2 / 180;
			lng_2 = Math.PI * lng_2 / 180;

			let lat_d = lat_2 - lat_1;
			let lng_d = lng_2 - lng_1;

			let angle = Math.sin(lat_d / 2) * Math.sin(lat_d / 2) +
				Math.cos(lat_1) * Math.cos(lat_2) *
				Math.sin(lng_d / 2) * Math.sin(lng_d / 2);
			let distance = 2 * Math.atan2(Math.sqrt(angle), Math.sqrt(1 - angle)) * 6371;

			$(this).attr('data-value', Math.round(distance * 1000));

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
