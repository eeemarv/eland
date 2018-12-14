$(document).ready(function() {
	var $map = $('#map');
	var lat = $map.data('lat');
	var lng = $map.data('lng');
	var users = $map.data('users');
	var sessionParam = $map.data('session-param');

	var map = L.map('map').setView([lat, lng], 14);

	L.tileLayer('https://api.mapbox.com/v4/mapbox.streets/{z}/{x}/{y}.png?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		maxZoom: 17,
		minZoom: 10,
		accessToken: $map.data('token')
	}).addTo(map);

	$.each(users, function(id, user){
		var m = L.marker([user.lat, user.lng], {
			riseOnHover: true
		}).addTo(map).on('click', function(e){
			window.location.href = './users.php?id=' + id + '&' + sessionParam;
		}).bindTooltip(user.letscode + ' ' + user.name);
	});
});
