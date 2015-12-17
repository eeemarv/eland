$(document).ready(function() {
	var $map = $('#map');
	var lat = $map.data('lat');
	var lng = $map.data('lng');
	var users = $map.data('users');

	var map = L.map('map').setView([lat, lng], 14);

	L.tileLayer('https://api.mapbox.com/v4/mapbox.streets/{z}/{x}/{y}.png?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		maxZoom: 17,
		minZoom: 10,
		accessToken: $map.data('token')
	}).addTo(map);

	$.each(users, function(id, user){
		var m = L.marker([user.lat, user.lng], {
			url: './users.php?id=' + id,
			riseOnHover: true
		}).addTo(map);

		m.on('click', function(){ window.location.replace( this.options.url );})
			.bindLabel(user.letscode + ' ' + user.name, {
				direction: 'auto',
			});
	});
});

