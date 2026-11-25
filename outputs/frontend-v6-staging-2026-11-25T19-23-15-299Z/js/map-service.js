/**
 * SeArah Travel Shuttle — Leaflet Map & Routing Service
 * Mendukung Interactive Map Picker ala Gojek, Garage Pool Map,
 * serta SEARAH TripSync™ Multi-Stop Live Route Tracker.
 */

const MapService = {
  /* Koordinat Geografis Kota-Kota di Jawa Timur */
  CITY_COORDS: {
    "Kepanjen": { lat: -8.1333, lng: 112.5714, zoom: 14 },
    "Malang": { lat: -7.9826, lng: 112.6308, zoom: 13 },
    "Batu": { lat: -7.8671, lng: 112.5239, zoom: 14 },
    "Blitar": { lat: -8.0983, lng: 112.1681, zoom: 14 },
    "Kediri": { lat: -7.8166, lng: 112.0119, zoom: 14 },
    "Tulungagung": { lat: -8.0658, lng: 111.9015, zoom: 14 },
    "Madiun": { lat: -7.6298, lng: 111.5239, zoom: 14 },
    "Ponorogo": { lat: -7.8712, lng: 111.4627, zoom: 14 },
    "Nganjuk": { lat: -7.6045, lng: 111.9044, zoom: 14 },
    "Trenggalek": { lat: -8.0577, lng: 111.7167, zoom: 14 },
    "Magetan": { lat: -7.6534, lng: 111.3283, zoom: 14 },
    "Ngawi": { lat: -7.4039, lng: 111.4455, zoom: 14 },
    "Surabaya": { lat: -7.2575, lng: 112.7521, zoom: 13 },
    "Sidoarjo": { lat: -7.4478, lng: 112.7183, zoom: 13 },
    "Pasuruan": { lat: -7.6449, lng: 112.9033, zoom: 14 },
    "Mojokerto": { lat: -7.4726, lng: 112.4381, zoom: 14 },
    "Gresik": { lat: -7.1593, lng: 112.6525, zoom: 14 },
    "Madura": { lat: -7.0315, lng: 112.7483, zoom: 13 },
    "Bangkalan": { lat: -7.0315, lng: 112.7483, zoom: 14 },
    "Sampang": { lat: -7.1873, lng: 113.2394, zoom: 14 },
    "Pamekasan": { lat: -7.1590, lng: 113.4738, zoom: 14 },
    "Sumenep": { lat: -7.0084, lng: 113.8617, zoom: 14 },
    "Probolinggo": { lat: -7.7569, lng: 113.2115, zoom: 14 },
    "Lumajang": { lat: -8.1331, lng: 113.2246, zoom: 14 },
    "Jember": { lat: -8.1724, lng: 113.7007, zoom: 14 },
    "Situbondo": { lat: -7.7063, lng: 114.0044, zoom: 14 },
    "Bondowoso": { lat: -7.9135, lng: 113.8214, zoom: 14 },
    "Banyuwangi": { lat: -8.2192, lng: 114.3692, zoom: 13 },
    "Jombang": { lat: -7.5459, lng: 112.2331, zoom: 14 },
    "Lamongan": { lat: -7.1206, lng: 112.4158, zoom: 14 },
    "Bojonegoro": { lat: -7.1502, lng: 111.8818, zoom: 14 },
    "Tuban": { lat: -6.8976, lng: 112.0649, zoom: 14 }
  },

  /* Landmark Populer per Kota Lengkap dengan Titik Lat/Lng */
  LANDMARKS: {
    "Kepanjen": [
      { name: "Stasiun Kepanjen", addr: "Jl. Stasiun No. 1, Kepanjen, Malang", lat: -8.1332, lng: 112.5710 },
      { name: "Alun-Alun Kepanjen", addr: "Jl. Merdeka No. 1, Kepanjen, Malang", lat: -8.1305, lng: 112.5732 },
      { name: "Stadion Kanjuruhan", addr: "Jl. Trunojoyo, Kedungpedaringan, Kepanjen", lat: -8.1492, lng: 112.5746 },
      { name: "RSUD Kanjuruhan", addr: "Jl. Panji No. 100, Panggungrejo, Kepanjen", lat: -8.1257, lng: 112.5695 }
    ],
    "Malang": [
      { name: "Stasiun Malang Kotabaru", addr: "Jl. Trunojoyo No. 10, Klojen, Malang", lat: -7.9778, lng: 112.6372 },
      { name: "Terminal Arjosari", addr: "Jl. Terusan Raden Intan No. 1, Blimbing, Malang", lat: -7.9306, lng: 112.6587 },
      { name: "Alun-Alun Tugu Malang", addr: "Jl. Tugu No. 1, Kiduldalem, Klojen, Malang", lat: -7.9772, lng: 112.6341 },
      { name: "Malang Town Square (Matos)", addr: "Jl. Veteran No. 2, Penanggungan, Klojen, Malang", lat: -7.9572, lng: 112.6186 }
    ],
    "Surabaya": [
      { name: "Terminal Purabaya (Bungurasih)", addr: "Jl. Letjend Sutoyo, Bungurasih, Waru", lat: -7.3512, lng: 112.7248 },
      { name: "Bandara Internasional Juanda (T1)", addr: "Jl. Ir. H. Juanda, Sedati, Sidoarjo", lat: -7.3797, lng: 112.7874 },
      { name: "Stasiun Surabaya Gubeng", addr: "Jl. Gubeng Masjid No. 1, Pacar Keling, Surabaya", lat: -7.2655, lng: 112.7522 },
      { name: "Tunjungan Plaza", addr: "Jl. Jenderal Basuki Rachmat No. 8-12, Surabaya", lat: -7.2625, lng: 112.7388 }
    ],
    "Sidoarjo": [
      { name: "Alun-Alun Sidoarjo", addr: "Jl. Ahmad Yani, Sidokumpul, Sidoarjo", lat: -7.4478, lng: 112.7183 },
      { name: "Stasiun Sidoarjo", addr: "Jl. Pahlawan, Sidokumpul, Sidoarjo", lat: -7.4566, lng: 112.7169 },
      { name: "Terminal Larangan", addr: "Jl. Sunandar Priyo Sudarmo, Larangan, Sidoarjo", lat: -7.4705, lng: 112.7180 }
    ],
    "Blitar": [
      { name: "Stasiun Blitar", addr: "Jl. Mastrip No. 75, Kepanjenkidul, Blitar", lat: -8.0983, lng: 112.1681 },
      { name: "Makam Bung Karno", addr: "Jl. Ir. Soekarno No. 152, Bendogerit, Blitar", lat: -8.0844, lng: 112.1764 },
      { name: "Alun-Alun Kota Blitar", addr: "Jl. Merdeka, Kepanjenlor, Blitar", lat: -8.1008, lng: 112.1643 }
    ],
    "Batu": [
      { name: "Alun-Alun Kota Wisata Batu", addr: "Jl. Diponegoro, Sisir, Batu", lat: -7.8671, lng: 112.5239 },
      { name: "Terminal Kota Batu", addr: "Jl. Dewi Sartika, Temas, Batu", lat: -7.8821, lng: 112.5327 },
      { name: "Jatim Park 1 & 2", addr: "Jl. Kartika No. 2, Sisir, Batu", lat: -7.8837, lng: 112.5248 }
    ]
  },

  /* Mendapatkan koordinat utama suatu kota */
  getCityCoord(cityName) {
    return this.CITY_COORDS[cityName] || { lat: -7.9826, lng: 112.6308, zoom: 13 };
  },

  /* Mendapatkan daftar landmark suatu kota */
  getLandmarks(cityName) {
    if (this.LANDMARKS[cityName]) return this.LANDMARKS[cityName];
    const center = this.getCityCoord(cityName);
    return [
      { name: `Stasiun / Terminal ${cityName}`, addr: `Jl. Stasiun Utama No. 1, Pusat ${cityName}`, lat: center.lat + 0.005, lng: center.lng - 0.004 },
      { name: `Alun-Alun Kota ${cityName}`, addr: `Jl. Merdeka / Alun-Alun ${cityName}`, lat: center.lat, lng: center.lng },
      { name: `RSUD / Pusat Kesehatan ${cityName}`, addr: `Jl. Pahlawan No. 45, ${cityName}`, lat: center.lat - 0.006, lng: center.lng + 0.005 },
      { name: `Pusat Bisnis & Transit ${cityName}`, addr: `Jl. Gajah Mada No. 88, ${cityName}`, lat: center.lat + 0.008, lng: center.lng + 0.007 }
    ];
  },

  /* Informasi Garasi Pool Resmi SeArah */
  getGarageInfo(cityName) {
    const coord = this.getCityCoord(cityName);
    return {
      nama: `Pool Garasi SeArah ${cityName}`,
      alamat: `Jl. Raya Utama Shuttle No. 45, Area Transit ${cityName}`,
      lat: coord.lat + 0.003,
      lng: coord.lng + 0.003,
      buka: "Operasional 24 Jam Setiap Hari",
      telepon: "+62 812-3456-7890"
    };
  },

  /* Membuat Tile Layer Leaflet (Esri World Street Map — Bebas Blokir 403, Tanpa API Key & Tanpa Watermark) */
  createTileLayer() {
    if (typeof L === "undefined") return null;
    return L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}", {
      attribution: 'Tiles &copy; Esri &mdash; Sumber: Esri, DeLorme, NAVTEQ, TomTom',
      maxZoom: 19
    });
  },

  /* =========================================================
     1. INITIALIZER: GOJEK-STYLE MAP PICKER DENGAN LEAFLET
     ========================================================= */
  initPickerMap(containerId, options = {}) {
    if (typeof L === "undefined") {
      console.warn("Leaflet library belum dimuat.");
      return null;
    }

    const { city = "Kepanjen", initialLocation = null, onLocationChange = () => {} } = options;
    const center = initialLocation && initialLocation.lat 
      ? { lat: initialLocation.lat, lng: initialLocation.lng, zoom: 15 } 
      : this.getCityCoord(city);

    const map = L.map(containerId, {
      center: [center.lat, center.lng],
      zoom: center.zoom || 14,
      zoomControl: true,
      scrollWheelZoom: true,
      wheelDebounceTime: 40,
      wheelPxPerZoomLevel: 60,
      touchZoom: true,
      doubleClickZoom: true,
      boxZoom: true,
      dragging: true
    });

    this.createTileLayer().addTo(map);

    // Custom Gojek Pin Marker Icon (Calibrated: needle tip at bottom center [18, 50])
    const pinIcon = L.divIcon({
      className: "leaflet-gojek-pin",
      html: `
        <div class="gojek-pin-wrap" id="gojek-pin-visual">
          <div class="gojek-pin-bubble" id="leaflet-gojek-pin-bubble">📍 ${initialLocation && initialLocation.name ? initialLocation.name.split(',')[0].slice(0, 24) : 'Titik Dipilih'}</div>
          <svg class="gojek-pin-svg" width="36" height="50" viewBox="0 0 36 50" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 50 C18 50 36 31 36 18 C36 8.05887 27.9411 0 18 0 C8.05887 0 0 8.05887 0 18 C0 31 18 50 18 50 Z" fill="#dc2626"/>
            <circle cx="18" cy="18" r="8" fill="#ffffff"/>
            <circle cx="18" cy="18" r="4.5" fill="#991b1b"/>
          </svg>
          <div class="gojek-pin-pulse"></div>
        </div>
      `,
      iconSize: [36, 50],
      iconAnchor: [18, 50] // Presisi pixel: ujung jarum pin tepat di koordinat klik
    });

    // Marker penunjuk yang bisa digeser (draggable)
    const marker = L.marker([center.lat, center.lng], {
      icon: pinIcon,
      draggable: true,
      zIndexOffset: 1000
    }).addTo(map);

    // Update location handler
    const updateLocation = (lat, lng, explicitName = null, explicitAddr = null) => {
      // Temukan landmark terdekat
      const landmarks = MapService.getLandmarks(city);
      let nearest = null;
      let minDistance = 0.008; // ~800m threshold

      landmarks.forEach((lm) => {
        const d = Math.hypot(lm.lat - lat, lm.lng - lng);
        if (d < minDistance) {
          minDistance = d;
          nearest = lm;
        }
      });

      const result = {
        name: explicitName || (nearest ? nearest.name : `Titik Peta ${city}`),
        addr: explicitAddr || (nearest ? nearest.addr : `Area Koordinat (${lat.toFixed(5)}, ${lng.toFixed(5)}), ${city}`),
        lat: lat,
        lng: lng
      };

      const bubbleEl = document.getElementById("leaflet-gojek-pin-bubble");
      if (bubbleEl) {
        bubbleEl.textContent = `📍 ${result.name.split(',')[0].slice(0, 24)}`;
      }

      onLocationChange(result);
    };

    // Event listener: Klik di mana saja pada peta
    map.on("click", (e) => {
      marker.setLatLng(e.latlng);
      updateLocation(e.latlng.lat, e.latlng.lng);
    });

    // Visual feedback saat drag
    marker.on("dragstart", () => {
      const el = document.getElementById("gojek-pin-visual");
      if (el) el.classList.add("is-dragging");
    });

    // Event listener: Selesai geser marker
    marker.on("dragend", () => {
      const el = document.getElementById("gojek-pin-visual");
      if (el) el.classList.remove("is-dragging");
      const pos = marker.getLatLng();
      updateLocation(pos.lat, pos.lng);
    });

    // Render landmark dots di sekeliling peta
    const landmarks = this.getLandmarks(city);
    landmarks.forEach((lm) => {
      const dotIcon = L.divIcon({
        className: "leaflet-landmark-dot",
        html: `<div class="landmark-dot-inner" title="${lm.name}">📍</div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
      });

      const lmMarker = L.marker([lm.lat, lm.lng], { icon: dotIcon }).addTo(map);
      lmMarker.bindTooltip(`<b>${lm.name}</b><br><small>${lm.addr}</small>`, { direction: "top", offset: [0, -10] });
      lmMarker.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
        marker.setLatLng([lm.lat, lm.lng]);
        map.flyTo([lm.lat, lm.lng], 16, { duration: 0.8 });
        updateLocation(lm.lat, lm.lng, lm.name, lm.addr);
      });
    });

    // Invalidate size agar tile merender penuh
    setTimeout(() => {
      map.invalidateSize();
    }, 150);

    return {
      map,
      marker,
      flyTo(lat, lng, name, addr) {
        marker.setLatLng([lat, lng]);
        map.flyTo([lat, lng], 16, { duration: 1.0 });
        updateLocation(lat, lng, name, addr);
      }
    };
  },

  /* =========================================================
     2. INITIALIZER: GARAGE POOL MAP DENGAN LEAFLET
     ========================================================= */
  initGarageMap(containerId, city) {
    if (typeof L === "undefined") return null;

    const garage = this.getGarageInfo(city);
    const map = L.map(containerId, {
      center: [garage.lat, garage.lng],
      zoom: 15,
      zoomControl: true,
      scrollWheelZoom: true,
      wheelDebounceTime: 40,
      wheelPxPerZoomLevel: 60,
      touchZoom: true,
      doubleClickZoom: true,
      boxZoom: true,
      dragging: true
    });

    this.createTileLayer().addTo(map);

    const garageIcon = L.divIcon({
      className: "leaflet-garage-pin",
      html: `
        <div class="garage-pin-container">
          <div class="garage-pin-icon">🏢</div>
          <div class="garage-pin-badge">Pool SeArah</div>
        </div>
      `,
      iconSize: [48, 54],
      iconAnchor: [24, 54]
    });

    const marker = L.marker([garage.lat, garage.lng], { icon: garageIcon }).addTo(map);
    marker.bindPopup(`
      <div style="font-family:Montserrat,sans-serif;padding:4px">
        <b style="color:#0f2042;font-size:13px">${garage.nama}</b><br>
        <span style="font-size:11px;color:#64748b">${garage.alamat}</span><br>
        <div style="margin-top:6px;font-size:11px;color:#16a34a">● ${garage.buka}</div>
      </div>
    `).openPopup();

    setTimeout(() => {
      map.invalidateSize();
    }, 150);

    return map;
  },

  /* =========================================================
     3. INITIALIZER: SEARAH TRIPSYNC™ MULTI-STOP LIVE ROUTE MAP
     ========================================================= */
  /* Database Koridor Jalan Tol & Jalan Raya Utama Jawa Timur */
  HIGHWAY_CORRIDORS: {
    "Kepanjen_Surabaya": [
      [-8.1332, 112.5710], // Stasiun Kepanjen
      [-8.1120, 112.5830], // Curungrejo
      [-8.0680, 112.5975], // Pakisaji (Jl. Raya Malang - Kepanjen)
      [-8.0260, 112.6170], // Kebonagung
      [-8.0060, 112.6250], // Gadang (Jl. Kolonel Sugiono)
      [-7.9826, 112.6308], // Alun-Alun Malang / Klojen
      [-7.9570, 112.6410], // Rampal / Blimbing
      [-7.9306, 112.6587], // Terminal Arjosari (Jl. A. Yani Malang)
      [-7.9042, 112.6618], // Simpang Karanglo / Gerbang Tol Singosari
      [-7.8720, 112.6780], // Singosari Kota
      [-7.8385, 112.6980], // Tol Singosari - Lawang KM 80
      [-7.7850, 112.7210], // Tol Purwodadi KM 66 (Rest Area)
      [-7.7320, 112.7260], // Purwosari Tol
      [-7.6850, 112.7180], // Tol Sukorejo / Pandaan KM 55
      [-7.6520, 112.7150], // Simpang Tol Pandaan
      [-7.5850, 112.7180], // Simpang Susun Tol Gempol
      [-7.5520, 112.7120], // Tol Gempol - Porong
      [-7.5350, 112.7050], // Tol Porong Arteri Baru
      [-7.4980, 112.7090], // Tol Tanggulangin
      [-7.4478, 112.7080], // Gerbang Tol Sidoarjo KM 26
      [-7.3950, 112.7180], // Tol Waru Utama Sidoarjo KM 18
      [-7.3550, 112.7250], // Bundaran Waru / Simpang Susun Waru
      [-7.3180, 112.7320], // Surabaya Ahmad Yani / DBL Arena
      [-7.2980, 112.7380], // Wonokromo / Joyoboyo
      [-7.2750, 112.7410], // Jl. Raya Darmo
      [-7.2625, 112.7522]  // Surabaya Pusat / Gubeng
    ],
    "Surabaya_Kepanjen": [
      [-7.2625, 112.7522],
      [-7.2750, 112.7410],
      [-7.2980, 112.7380],
      [-7.3180, 112.7320],
      [-7.3550, 112.7250],
      [-7.3950, 112.7180],
      [-7.4478, 112.7080],
      [-7.4980, 112.7090],
      [-7.5350, 112.7050],
      [-7.5520, 112.7120],
      [-7.5850, 112.7180],
      [-7.6520, 112.7150],
      [-7.6850, 112.7180],
      [-7.7320, 112.7260],
      [-7.7850, 112.7210],
      [-7.8385, 112.6980],
      [-7.8720, 112.6780],
      [-7.9042, 112.6618],
      [-7.9306, 112.6587],
      [-7.9570, 112.6410],
      [-7.9826, 112.6308],
      [-8.0060, 112.6250],
      [-8.0260, 112.6170],
      [-8.0680, 112.5975],
      [-8.1120, 112.5830],
      [-8.1332, 112.5710]
    ]
  },

  /* Menghasilkan Rute Halus yang Mengikuti Alur Fisik Jalan Raya & Tol */
  buildRealisticRoadGeometry(stops, asal, tujuan) {
    const corridorKey = `${asal}_${tujuan}`;
    const reverseKey = `${tujuan}_${asal}`;
    let baseCorridor = this.HIGHWAY_CORRIDORS[corridorKey];

    if (!baseCorridor && this.HIGHWAY_CORRIDORS[reverseKey]) {
      baseCorridor = [...this.HIGHWAY_CORRIDORS[reverseKey]].reverse();
    }

    // Jika koridor tol terdaftar, jahit titik penjemputan/pengantaran dengan koridor jalan tol
    if (baseCorridor && baseCorridor.length > 5) {
      const roadPath = [];
      const firstStop = stops[0];
      const lastStop = stops[stops.length - 1];

      // Awal dari titik jemput pertama
      roadPath.push([firstStop.lat, firstStop.lng]);

      // Sisipkan titik koridor jalan arteri & tol yang relevan
      baseCorridor.forEach((pt) => {
        roadPath.push(pt);
      });

      // Akhir ke titik drop off terakhir
      roadPath.push([lastStop.lat, lastStop.lng]);

      // Interpolasi kurva Catmull-Rom agar lekukan jalan raya halus dan menempel
      return this.interpolateSpline(roadPath, 3);
    }

    // Fallback umum: buat kurva lengkung realistis antar stop
    const rawPoints = stops.map((s) => [s.lat, s.lng]);
    return this.interpolateSpline(rawPoints, 5);
  },

  /* Algoritma Interpolasi Spline Geometris untuk Lekukan Rute Jalan */
  interpolateSpline(points, subdivisions = 4) {
    if (!points || points.length < 2) return points;
    const result = [];

    for (let i = 0; i < points.length - 1; i++) {
      const p0 = points[Math.max(i - 1, 0)];
      const p1 = points[i];
      const p2 = points[i + 1];
      const p3 = points[Math.min(i + 2, points.length - 1)];

      for (let step = 0; step < subdivisions; step++) {
        const t = step / subdivisions;
        const t2 = t * t;
        const t3 = t2 * t;

        // Catmull-Rom spline formula
        const lat = 0.5 * (
          (2 * p1[0]) +
          (-p0[0] + p2[0]) * t +
          (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 +
          (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3
        );

        const lng = 0.5 * (
          (2 * p1[1]) +
          (-p0[1] + p2[1]) * t +
          (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 +
          (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3
        );

        result.push([lat, lng]);
      }
    }
    result.push(points[points.length - 1]);
    return result;
  },

  /* Mengambil Geometri Jalan Riil Mengikuti Pola Jalan OpenStreetMap via OSRM Driving Engine */
  async getAccurateRoadGeometry(stops) {
    if (!stops || stops.length < 2) return null;
    try {
      // Ambil titik-titik utama untuk query routing
      const keyStops = stops.length > 5 
        ? [stops[0], stops[1], stops[Math.floor(stops.length / 2)], stops[stops.length - 2], stops[stops.length - 1]] 
        : stops;

      const coordString = keyStops.map(s => `${s.lng.toFixed(5)},${s.lat.toFixed(5)}`).join(";");
      const url = `https://router.project-osrm.org/route/v1/driving/${coordString}?overview=full&geometries=geojson`;

      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 3500); // 3.5s timeout

      const res = await fetch(url, { signal: controller.signal });
      clearTimeout(timeoutId);

      if (!res.ok) return null;
      const data = await res.json();

      if (data && data.code === "Ok" && data.routes && data.routes[0]) {
        const geoCoords = data.routes[0].geometry.coordinates; // [lng, lat]
        // Konversi ke format Leaflet [lat, lng]
        return geoCoords.map(coord => [coord[1], coord[0]]);
      }
    } catch (e) {
      // Offline atau timeout — fallback koridor halus tetap aktif
      console.log("TripSync: Menggunakan koridor geometri lokal presisi tinggi.");
    }
    return null;
  },

  /* Inisialisasi Peta TripSync */
  initTripSyncMap(containerId, b, options = {}) {
    if (typeof L === "undefined") return null;

    const q = b.query || {};
    const asal = q.asal || "Kepanjen";
    const tujuan = q.tujuan || "Surabaya";
    const coordAsal = this.getCityCoord(asal);
    const coordTujuan = this.getCityCoord(tujuan);
    const curStep = options.curStep || 0;

    // Hitung stops multi-stop dengan koordinat riil
    const stops = this.buildTripSyncStops(b, coordAsal, coordTujuan);
    const validStep = Math.min(Math.max(0, curStep), stops.length - 1);
    const currentStop = stops[validStep];

    const map = L.map(containerId, {
      zoomControl: true,
      scrollWheelZoom: true,
      wheelDebounceTime: 40,
      wheelPxPerZoomLevel: 60,
      touchZoom: true,
      doubleClickZoom: true,
      boxZoom: true,
      dragging: true
    });

    this.createTileLayer().addTo(map);

    // Dapatkan geometri rute awal yang meliuk mengikuti jalan raya & tol riil
    const initialRoadGeometry = this.buildRealisticRoadGeometry(stops, asal, tujuan);

    // Background glow line
    const glowLine = L.polyline(initialRoadGeometry, {
      color: "#1d4faa",
      weight: 7,
      opacity: 0.3,
      lineCap: "round",
      lineJoin: "round"
    }).addTo(map);

    // Active route line (mengikuti lekukan jalan)
    const routeLine = L.polyline(initialRoadGeometry, {
      color: "#2563eb",
      weight: 4.5,
      opacity: 0.95,
      dashArray: "10, 8",
      lineCap: "round",
      lineJoin: "round"
    }).addTo(map);

    // Panggil OSRM secara asinkron untuk upgrade geometri ke 100% jalan OpenStreetMap jika online
    this.getAccurateRoadGeometry(stops).then((osrmPoints) => {
      if (osrmPoints && osrmPoints.length > 5) {
        routeLine.setLatLngs(osrmPoints);
        glowLine.setLatLngs(osrmPoints);
      }
    });

    // Pasang Marker Tiap Stop (Stop Pin yang presisi)
    stops.forEach((s, idx) => {
      const isPast = idx < validStep;
      const isCurrent = idx === validStep;
      const isUser = s.isUser;
      
      const pinColor = isUser 
        ? (s.type === "pick" ? "#0284c7" : "#16a34a") 
        : (s.type === "pick" ? "#3b82f6" : s.type === "drop" ? "#ea580c" : "#9333ea");

      const badgeLabel = isUser 
        ? (s.type === "pick" ? "⭐ JEMPUT ANDA" : "🎯 DROP ANDA") 
        : (s.type === "pick" ? `🧍 ${s.passenger.split(' ')[0]}` : s.type === "drop" ? `🏁 ${s.passenger.split(' ')[0]}` : "🛣️ Tol");

      const stopIcon = L.divIcon({
        className: "tripsync-leaflet-stop",
        html: `
          <div class="tripsync-stop-pin ${isCurrent ? 'is-active-pulse' : ''}" style="border-color:${pinColor}">
            <div class="tripsync-stop-tag" style="background:${pinColor}">${badgeLabel}</div>
            <div class="tripsync-stop-circle" style="background:${isPast ? '#64748b' : pinColor}">
              ${isPast ? "✓" : idx + 1}
            </div>
          </div>
        `,
        iconSize: [80, 42],
        iconAnchor: [40, 42] // Anchored tepat di dasar lingkaran nomor stop
      });

      const m = L.marker([s.lat, s.lng], { icon: stopIcon }).addTo(map);
      m.bindPopup(`
        <div style="font-family:Montserrat,sans-serif;min-width:180px">
          <div style="font-size:11px;font-weight:700;color:${pinColor}">PEMBERHENTIAN #${idx + 1} · ${s.role.toUpperCase()}</div>
          <b style="font-size:13px;color:#0f2042">${s.passenger}</b>
          <div style="font-size:11px;color:#475569;margin-top:3px">${s.address}</div>
          <div style="margin-top:6px;font-size:11px;display:flex;justify-content:space-between">
            <span>Estimasi Tiba:</span> <b>${s.eta}</b>
          </div>
          <div style="margin-top:4px;font-size:11px">
            Status: <b>${isPast ? "✓ Selesai Terlewati" : isCurrent ? "🚗 Sedang Dituju Sekarang" : "⏳ Menunggu Antrean"}</b>
          </div>
        </div>
      `);
    });

    // Pasang Marker Mobil Pengemudi (Live Driver Car Marker)
    const carIcon = L.divIcon({
      className: "tripsync-leaflet-driver",
      html: `
        <div class="tripsync-driver-container">
          <div class="tripsync-driver-pulsing-ring"></div>
          <div class="tripsync-driver-bubble">
            <span class="car-emoji">🚗</span>
          </div>
          <div class="tripsync-driver-label">${b.driver || 'Mitra Driver'} · ${b.plat || 'N 7421 KP'}</div>
        </div>
      `,
      iconSize: [50, 50],
      iconAnchor: [25, 25] // Tepat di pusat lingkaran mobil
    });

    const driverMarker = L.marker([currentStop.lat, currentStop.lng], {
      icon: carIcon,
      zIndexOffset: 2500
    }).addTo(map);

    // Zoom agar seluruh rute terlihat dengan ruang pandang yang lega
    map.fitBounds(routeLine.getBounds(), { padding: [50, 50], maxZoom: 12 });

    setTimeout(() => {
      map.invalidateSize();
    }, 200);

    return {
      map,
      driverMarker,
      stops,
      moveToStep(stepIndex) {
        const targetStop = stops[stepIndex];
        if (!targetStop) return;
        driverMarker.setLatLng([targetStop.lat, targetStop.lng]);
        map.panTo([targetStop.lat, targetStop.lng], { animate: true, duration: 1.0 });
      }
    };
  },

  /* Membangun Daftar Pemberhentian TripSync dengan Koordinat Riil */
  buildTripSyncStops(b, coordAsal, coordTujuan) {
    const q = b.query || {};
    const asal = q.asal || "Kepanjen";
    const tujuan = q.tujuan || "Surabaya";
    const userName = b.penumpang || "Anda";
    const userPickup = b.jemput || `Titik Penjemputan Anda di ${asal}`;
    const userDrop = b.antar || `Titik Drop Off Anda di ${tujuan}`;

    const lerp = (a, b, t) => a + (b - a) * t;

    // Rute Privat (Direct Point-to-Point)
    if (b.paket === "private") {
      return [
        {
          id: 1,
          type: "pick",
          isUser: true,
          passenger: `${userName} (Rombongan Private)`,
          role: "Penjemputan Privat Eksklusif",
          address: userPickup,
          lat: coordAsal.lat,
          lng: coordAsal.lng,
          eta: "08:00 WIB"
        },
        {
          id: 2,
          type: "transit",
          isUser: false,
          passenger: "Koridor Tol Trans-Jawa",
          role: "Jalur Tol Bebas Hambatan (Langsung)",
          address: `Tol Trans Jawa (${asal} → ${tujuan})`,
          lat: lerp(coordAsal.lat, coordTujuan.lat, 0.5),
          lng: lerp(coordAsal.lng, coordTujuan.lng, 0.5),
          eta: "08:45 WIB"
        },
        {
          id: 3,
          type: "drop",
          isUser: true,
          passenger: `${userName} (Rombongan Private)`,
          role: "Titik Tujuan Langsung",
          address: userDrop,
          lat: coordTujuan.lat,
          lng: coordTujuan.lng,
          eta: "09:30 WIB"
        }
      ];
    }

    // Rute Reguler (Pool Garasi ke Pool Garasi)
    if (b.paket === "reguler") {
      return [
        {
          id: 1,
          type: "pick",
          isUser: true,
          passenger: `${userName} (Anda - Kursi 1)`,
          role: "Garasi Keberangkatan Asal",
          address: userPickup.includes("Garasi") ? userPickup : `Garasi Utama SeArah ${asal}`,
          lat: coordAsal.lat,
          lng: coordAsal.lng,
          eta: "08:00 WIB"
        },
        {
          id: 2,
          type: "pick",
          isUser: false,
          passenger: "Rizky Ramadhan (Kursi 2)",
          role: "Pool Garasi Transit",
          address: "Pool Transit SeArah Singosari / Karanglo",
          lat: lerp(coordAsal.lat, coordTujuan.lat, 0.28),
          lng: lerp(coordAsal.lng, coordTujuan.lng, 0.28) + 0.015,
          eta: "08:25 WIB"
        },
        {
          id: 3,
          type: "transit",
          isUser: false,
          passenger: "Koridor Tol Pandaan - Malang",
          role: "Jalur Tol Cepat Antar-Pool",
          address: "Gerbang Tol Singosari KM 88",
          lat: lerp(coordAsal.lat, coordTujuan.lat, 0.55),
          lng: lerp(coordAsal.lng, coordTujuan.lng, 0.55),
          eta: "08:50 WIB"
        },
        {
          id: 4,
          type: "drop",
          isUser: false,
          passenger: "Rizky Ramadhan (Kursi 2)",
          role: "Drop Off Garasi Transit Waru",
          address: "Garasi SeArah Pool Waru, Sidoarjo",
          lat: lerp(coordAsal.lat, coordTujuan.lat, 0.82),
          lng: lerp(coordAsal.lng, coordTujuan.lng, 0.82) - 0.015,
          eta: "09:20 WIB"
        },
        {
          id: 5,
          type: "drop",
          isUser: true,
          passenger: `${userName} (Anda - Kursi 1)`,
          role: "Garasi Kedatangan Tujuan",
          address: userDrop.includes("Garasi") ? userDrop : `Garasi Utama SeArah ${tujuan}`,
          lat: coordTujuan.lat,
          lng: coordTujuan.lng,
          eta: "09:45 WIB"
        }
      ];
    }

    // Default: Door to Door Sharing (Multi-Stop Real Coordinates)
    return [
      {
        id: 1,
        type: "pick",
        isUser: true,
        passenger: `${userName} (Anda - Kursi 1)`,
        role: "Titik Penjemputan Anda",
        address: userPickup,
        lat: coordAsal.lat,
        lng: coordAsal.lng,
        eta: "08:00 WIB"
      },
      {
        id: 2,
        type: "pick",
        isUser: false,
        passenger: "Dina Rahmawati (Kursi 2)",
        role: "Titik Jemput Penumpang 2",
        address: `Jl. Ijen / Jl. Kawi Area ${asal}`,
        lat: lerp(coordAsal.lat, coordTujuan.lat, 0.18),
        lng: lerp(coordAsal.lng, coordTujuan.lng, 0.18) + 0.018,
        eta: "08:20 WIB"
      },
      {
        id: 3,
        type: "pick",
        isUser: false,
        passenger: "Bambang Haryanto (Kursi 3)",
        role: "Titik Jemput Penumpang 3",
        address: `Area Perumahan & Transit Dekat Pintu Tol`,
        lat: lerp(coordAsal.lat, coordTujuan.lat, 0.36),
        lng: lerp(coordAsal.lng, coordTujuan.lng, 0.36) - 0.012,
        eta: "08:40 WIB"
      },
      {
        id: 4,
        type: "transit",
        isUser: false,
        passenger: "Koridor Tol Trans-Jawa",
        role: "Jalur Tol Bersama Bebas Hambatan",
        address: "Rest Area KM 66 / Jalur Tol Trans Jawa",
        lat: lerp(coordAsal.lat, coordTujuan.lat, 0.55),
        lng: lerp(coordAsal.lng, coordTujuan.lng, 0.55),
        eta: "09:15 WIB"
      },
      {
        id: 5,
        type: "drop",
        isUser: false,
        passenger: "Dina Rahmawati (Kursi 2)",
        role: "Titik Drop Off Penumpang 2",
        address: "Terminal 1 Domestik Bandara Juanda",
        lat: lerp(coordAsal.lat, coordTujuan.lat, 0.76),
        lng: lerp(coordAsal.lng, coordTujuan.lng, 0.76) + 0.022,
        eta: "09:45 WIB"
      },
      {
        id: 6,
        type: "drop",
        isUser: true,
        passenger: `${userName} (Anda - Kursi 1)`,
        role: "Titik Drop Off Anda",
        address: userDrop,
        lat: lerp(coordAsal.lat, coordTujuan.lat, 0.90),
        lng: lerp(coordAsal.lng, coordTujuan.lng, 0.90) - 0.010,
        eta: "10:10 WIB"
      },
      {
        id: 7,
        type: "drop",
        isUser: false,
        passenger: "Bambang Haryanto (Kursi 3)",
        role: "Titik Drop Off Penumpang 3",
        address: "Stasiun Kereta Api / Pusat Kota Baru",
        lat: coordTujuan.lat,
        lng: coordTujuan.lng,
        eta: "10:35 WIB"
      }
    ];
  }
};

if (typeof window !== "undefined") {
  window.MapService = MapService;
}
