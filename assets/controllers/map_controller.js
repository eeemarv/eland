import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';

export default class extends Controller {
  static values = {
    markers: Array,
    token: String,
    tilesUrl: String,
    lat: Number,
    lng: Number,
    zoom: Number,
    icon: String,
    bgIcon: String,
  }

  connect() {

    const combinedHtml = `
      <div class="map-icon-container">
        <div class="map-icon-bg">
          ${this.bgIconValue}
        </div>
        <div class="map-icon">
          ${this.iconValue}
        </div>
      </div>
    `;

    const customIcon = L.divIcon({
      html: combinedHtml, //this.iconValue,
      className: 'custom-map-icon', // to remove white background from leaflet
      iconSize: [40, 40],
      iconAnchor: [20, 40],
      popupAnchor: [0, -40],
    });

    // 1. Initialize the map
    this.map = L.map(this.element);

    console.log('tilesUrlValue', this.tilesUrlValue);
    console.log('tokenValue', this.tokenValue);

    L.tileLayer('https://' + this.tilesUrlValue + '/{z}/{x}/{y}?access_token={accessToken}', {
      accessToken: this.tokenValue,
      attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery © <a href="http://mapbox.com">Mapbox</a>',
      tileSize: 256,
      zoomOffset: 0,
      maxZoom: 18,
      minZoom: 0,
    }).addTo(this.map);

    // 2. Add markers and determine reach
    const bounds = L.latLngBounds();

    this.markersValue.forEach((marker) => {
      let distanceLabel = '';
      if (marker.distance != null) {
        if (marker.distance < 1000) {
          distanceLabel = `<div>${Math.round(marker.distance)} m</div>`;
        } else {
          const km = (marker.distance / 1000).toFixed(1).replace('.', ',');
          distanceLabel = `<div>${km} km<div>`;
        }
      }

      let userLabel = '';
      if (marker.username) {
        userLabel = marker.url
          ? `<a href="${marker.url}"><strong>${marker.username}</strong></a><br>`
          : `<strong>${marker.username}</strong><br>`;
      }

      const popupContent = `<span class="map-popup">${userLabel}${marker.address}${distanceLabel}</span>`;

      const m = L.marker([marker.lat, marker.lng], {
        icon: customIcon,
        riseOnHover: true,
      })
        .addTo(this.map)
        .bindPopup(popupContent);
      bounds.extend(m.getLatLng());
    });

    // 3. determine position: manual or automatic
    if (this.hasLatValue && this.hasLngValue) {
      this.map.setView(
        [this.latValue, this.lngValue],
        this.hasZoomValue ? this.zoomValue : 13
      );
    } else if (this.markersValue.length > 0) {
      console.log('bounds', bounds);
      this.map.fitBounds(bounds, { padding: [100, 100] });
    } else {
      // Fallback if nothing given (middle of Belgium)
      this.map.setView([50.8503, 4.3517], 8);
    }
  }
}
