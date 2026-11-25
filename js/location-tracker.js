/**
 * SeArah — Location Tracker Service
 * Pelacak lokasi real-time untuk pengemudi dan penumpang.
 * Mendukung Browser Geolocation API + Simulasi posisi demo interaktif.
 */

const LocationTracker = {
  watchId: null,
  currentPosition: null,
  isTracking: false,
  listeners: [],
  updateInterval: null,

  /* Mengambil posisi tersimpan */
  getStoredPosition(role = "driver") {
    try {
      const key = role === "driver" ? "searah_driver_loc" : "searah_passenger_loc";
      const stored = sessionStorage.getItem(key);
      return stored ? JSON.parse(stored) : null;
    } catch (e) {
      return null;
    }
  },

  /* Menyimpan posisi */
  storePosition(pos, role = "driver") {
    try {
      const key = role === "driver" ? "searah_driver_loc" : "searah_passenger_loc";
      sessionStorage.setItem(key, JSON.stringify(pos));
    } catch (e) {}
  },

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
          console.log("LocationTracker: GPS riil belum diizinkan, beralih ke simulasi akurat.");
          this.useSimulation(role, options);
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
      this.useSimulation(role, options);
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

  /* Simulasi posisi demo realistis */
  useSimulation(role = "driver", options = {}) {
    const stored = this.getStoredPosition(role);
    // Koordinat dasar: Kepanjen / Malang
    const baseLat = role === "driver" ? -8.1332 : -8.1305;
    const baseLng = role === "driver" ? 112.5710 : 112.5732;

    this.currentPosition = stored || {
      lat: baseLat,
      lng: baseLng,
      accuracy: 15,
      speed: 11.2, // ~40 km/h
      heading: 15,
      timestamp: Date.now(),
      source: "simulated",
      role: role
    };
    this.storePosition(this.currentPosition, role);

    const { onUpdate = () => {} } = options;
    onUpdate(this.currentPosition);

    if (this.updateInterval) clearInterval(this.updateInterval);

    // Update bertahap setiap 2.5 detik
    this.updateInterval = setInterval(() => {
      if (!this.isTracking) return;
      
      // Bergerak perlahan ke arah utara (arah Malang / Surabaya)
      const latDelta = (Math.random() * 0.0003);
      const lngDelta = ((Math.random() - 0.45) * 0.0002);

      this.currentPosition = {
        ...this.currentPosition,
        lat: this.currentPosition.lat + latDelta,
        lng: this.currentPosition.lng + lngDelta,
        speed: 8 + Math.random() * 8, // 30-58 km/h
        heading: 10 + Math.random() * 15,
        timestamp: Date.now(),
        source: "simulated"
      };

      this.storePosition(this.currentPosition, role);
      this.listeners.forEach((cb) => cb(this.currentPosition));
    }, 2500);
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
