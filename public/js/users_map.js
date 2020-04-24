$(document).ready(function() {
	var $map = $('#map');
	var data = $map.data('map');

	var map = L.map('map').setView([data.lat, data.lng], 14);

	L.tileLayer('https://' + data.tiles_url + '/{z}/{x}/{y}?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		maxZoom: 18,
		minZoom: 9,
		accessToken: data.token
	}).addTo(map);

	$.each(data.users, function(id, user){
		L.marker([user.lat, user.lng], {
			riseOnHover: true
		}).addTo(map).on('click', function(){
			window.location.href = user.link;
		}).bindTooltip(user.code + ' ' + user.name);
	});
});
