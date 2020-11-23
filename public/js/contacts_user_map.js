jQuery(function(){
	var $map = $('#map');
	var data = $map.data('map');
	var lat = 0;
	var lng = 0;

	if (typeof data === 'undefined'){
		return;
	}

	if (!data.markers){
		return;
	}

	$.each(data.markers, function(index, marker){
		lat = lat + marker.lat;
		lng = lng + marker.lng;
	});

	lat = lat / data.markers.length;
	lng = lng / data.markers.length;

	var map = L.map('map').setView([lat, lng], 15);

	L.tileLayer('https://' + data.tiles_url + '/{z}/{x}/{y}?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
		maxZoom: 18,
		minZoom: 9,
		accessToken: data.token
	}).addTo(map);

	$.each(data.markers, function(id, m){
		L.marker([m.lat, m.lng], {
			riseOnHover: true
		}).addTo(map);
	});
});
