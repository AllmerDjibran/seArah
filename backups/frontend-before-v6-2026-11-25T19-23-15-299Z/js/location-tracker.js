/**
 * SeArah — Location Tracker Service
 * Pelacak lokasi real-time untuk pengemudi dan penumpang.
 * Menggunakan Browser Geolocation API tanpa posisi buatan.
 */

const LocationTracker = {
  watchId: null,
  currentPosition: null,
  isTracking: false,
  listeners: [],
  updateInterval: null,

  getStoredPosition() { return this.currentPosition; },
  storePosition(pos) { this.currentPosition = pos; },

  /* Cek dukungan Geolocation */
  isSupported() {
    return "geolocation" in navigator;
  },

  /* Memulai tracking real-time */
  startTracking(options = {}) {
    const { role = "driver", onUpdate = () => {}, onError = () => {}, highAccuracy = true } = options;
    this.isTracking = true;
    this.listeners.push(onUpdate);

    if (this.isSupported()) {
      // Coba dapatkan posisi riil dari GPS browser
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          this.handlePositionUpdate(pos, role);
          onUpdate(this.currentPosition);
        },
        (err) => {
          
          this.stopTracking();
          onError(err);
        },
        { enableHighAccuracy: highAccuracy, timeout: 5000, maximumAge: 0 }
      );

      this.watchId = navigator.geolocation.watchPosition(
        (pos) => {
          this.handlePositionUpdate(pos, role);
          this.listeners.forEach((cb) => cb(this.currentPosition));
        },
        () => {},
        { enableHighAccuracy: highAccuracy, timeout: 8000, maximumAge: 2000 }
      );
    } else {
      this.stopTracking(); onError(new Error("GPS tidak tersedia."));
    }

    return true;
  },

  /* Menghentikan tracking */
  stopTracking() {
    this.isTracking = false;
    if (this.watchId !== null) {
      navigator.geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
    this.listeners = [];
  },

  /* Olah data posisi GPS */
  handlePositionUpdate(pos, role = "driver") {
    this.currentPosition = {
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      accuracy: pos.coords.accuracy || 25,
      speed: pos.coords.speed || 0,
      heading: pos.coords.heading || null,
      timestamp: pos.timestamp || Date.now(),
      source: "gps",
      role: role
    };
    this.storePosition(this.currentPosition, role);
  },

  /* Menghitung jarak 2 titik koordinat (km) */
  distanceBetween(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * (Math.PI / 180);
    const dLng = (lng2 - lng1) * (Math.PI / 180);
    const a = Math.sin(dLat / 2) ** 2 +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
      Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  },

  /* Format kecepatan */
  formatSpeed(speedMps) {
    if (speedMps == null || speedMps <= 0) return "0 km/jam";
    return Math.round(speedMps * 3.6) + " km/jam";
  },

  /* Marker Leaflet untuk Lokasi Driver */
  addDriverMarker(map, pos, label = "Armada Driver") {
    if (typeof L === "undefined" || !map || !pos) return null;

    const icon = L.divIcon({
      className: "loc-driver-pulse-marker",
      html: `
        <div style="position:relative;display:flex;flex-direction:column;align-items:center;pointer-events:none">
          <div style="background:#1e3a8a;color:#ffffff;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:800;white-space:nowrap;border:1.5px solid #ffffff;box-shadow:0 3px 8px rgba(0,0,0,0.3);margin-bottom:3px">
            🚗 ${label}
          </div>
          <div style="width:20px;height:20px;border-radius:50%;background:#1e3a8a;border:3px solid #ffffff;box-shadow:0 0 0 5px rgba(30,58,138,0.35);display:grid;place-items:center;color:#fff;font-size:10px">
            ▲
          </div>
        </div>
      `,
      iconSize: [110, 44],
      iconAnchor: [55, 44]
    });

    return L.marker([pos.lat, pos.lng], { icon, zIndexOffset: 3500 }).addTo(map);
  },

  /* Marker Leaflet untuk Lokasi Penumpang */
  addPassengerMarker(map, pos, label = "Posisi Anda") {
    if (typeof L === "undefined" || !map || !pos) return null;

    const icon = L.divIcon({
      className: "loc-pax-pulse-marker",
      html: `
        <div style="position:relative;display:flex;flex-direction:column;align-items:center;pointer-events:none">
          <div style="background:#0284c7;color:#ffffff;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:800;white-space:nowrap;border:1.5px solid #ffffff;box-shadow:0 3px 8px rgba(0,0,0,0.3);margin-bottom:3px">
            📍 ${label}
          </div>
          <div style="width:16px;height:16px;border-radius:50%;background:#0284c7;border:2.5px solid #ffffff;box-shadow:0 0 0 5px rgba(2,132,199,0.35)"></div>
        </div>
      `,
      iconSize: [100, 40],
      iconAnchor: [50, 40]
    });

    return L.marker([pos.lat, pos.lng], { icon, zIndexOffset: 3400 }).addTo(map);
  }
};

if (typeof window !== "undefined") {
  window.LocationTracker = LocationTracker;
}
