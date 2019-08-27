$(document).ready(function(){
	var $map = $('#map');
	var markers = $map.data('markers');
	var lat = 0;
	var lng = 0;

	if (!markers){
		return;
	}

	$.each(markers, function(index, marker){
		lat = lat + marker.lat;
		lng = lng + marker.lng;
	});

	lat = lat / markers.length;
	lng = lng / markers.length;

	var map = L.map('map').setView([lat, lng], 15);

	L.tileLayer('https://api.mapbox.com/v4/mapbox.streets/{z}/{x}/{y}.png?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		maxZoom: 17,
		minZoom: 10,
		accessToken: $map.data('token')
	}).addTo(map);

	$.each(markers, function(id, m){
		L.marker([m.lat, m.lng], {
			riseOnHover: true
		}).addTo(map);
	});
});
