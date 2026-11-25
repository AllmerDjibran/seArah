/* Operational pages use only server records; no mock reservation or payment. */
(async function () {
  const app = document.getElementById('app');
  if (!app) return;
  const page = location.pathname.split('/').pop().replace('.html', '');
  const query = new URLSearchParams(location.search);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = n => new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(n || 0));
  const option = (value, label) => `<option value="${esc(value)}">${esc(label)}</option>`;
  const field = (name, label, type = 'text', extra = '') => `<div class="field"><label for="${name}">${esc(label)}</label><input id="${name}" name="${name}" type="${type}" ${extra}></div>`;
  const select = (name, label, options) => `<div class="field"><label for="${name}">${esc(label)}</label><select id="${name}" name="${name}">${options}</select></div>`;
  const button = text => `<button class="btn btn--primary" type="submit">${text}</button>`;
  const notice = text => `<p class="server-notice">${esc(text)}</p>`;
  const formData = form => Object.fromEntries(new FormData(form));
  function bind(form, action) {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const buttons = [...form.querySelectorAll('button')];
      buttons.forEach(b => b.disabled = true);
      try { await action(formData(form), form); }
      catch (err) { UI.toast(err.message, 'error', 6000); }
      finally { buttons.forEach(b => b.disabled = false); }
    });
  }
  async function act(path, body = {}, method = 'POST') { return api.request(path, method, body); }
  function navigate(target) { location.href = target; }
  function gps() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('GPS tidak tersedia. Isi koordinat manual.'));
      navigator.geolocation.getCurrentPosition(p => resolve({lat:p.coords.latitude,lng:p.coords.longitude,accuracy:p.coords.accuracy}), e => reject(new Error('GPS gagal: ' + e.message)), {enableHighAccuracy:true,timeout:15000,maximumAge:0});
    });
  }
  function coords(prefix, label) {
    return `<fieldset><legend>${esc(label)}</legend>${field(prefix + 'lat','Latitude','number','step="any" min="-90" max="90" required')}${field(prefix + 'lng','Longitude','number','step="any" min="-180" max="180" required')}<button type="button" class="btn btn--ghost" data-gps="${prefix}">Gunakan GPS</button><button type="button" class="btn btn--ghost" data-map="${prefix}">Pilih di peta</button><div data-map-slot="${prefix}"></div></fieldset>`;
  }
  async function loadMapLibrary() {
    if (window.L) return;
    if (!window.searahMapPromise) window.searahMapPromise = new Promise((resolve,reject) => {
      const css = document.createElement('link'); css.rel='stylesheet'; css.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.append(css);
      const script=document.createElement('script'); script.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; script.onload=resolve; script.onerror=() => reject(new Error('Peta gagal dimuat. Koordinat manual tetap tersedia.')); document.head.append(script);
    });
    return window.searahMapPromise;
  }
  function bindCoords(form) {
    form.querySelectorAll('[data-gps]').forEach(b => b.onclick = async () => {
      b.disabled = true;
      try { const p=await gps(); form.elements[b.dataset.gps+'lat'].value=p.lat; form.elements[b.dataset.gps+'lng'].value=p.lng; }
      catch(err) { UI.toast(err.message,'error'); } finally { b.disabled=false; }
    });
    form.querySelectorAll('[data-map]').forEach(b => b.onclick = async () => {
      try {
        await loadMapLibrary();
        const prefix=b.dataset.map, slot=form.querySelector(`[data-map-slot="${prefix}"]`);
        if (slot.dataset.ready) return;
        slot.dataset.ready='true'; slot.style.height='260px';
        const lat=form.elements[prefix+'lat'], lng=form.elements[prefix+'lng'];
        const map=L.map(slot).setView([lat.value ? Number(lat.value) : -7.98,lng.value ? Number(lng.value) : 112.63],12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
        let marker;
        map.on('click',e => {lat.value=e.latlng.lat;lng.value=e.latlng.lng;if(marker) marker.setLatLng(e.latlng);else marker=L.marker(e.latlng).addTo(map);});
      } catch(err) { UI.toast(err.message,'error'); }
    });
  }
  const summary = b => `<h2>${esc(b.code || b.id)}</h2><p>${esc(b.origin_city)} ke ${esc(b.dest_city)} | ${esc(b.package_type)}</p><p>${esc(b.trip_date)} ${esc(b.preferred_time)} | ${esc(b.pax_count)} penumpang</p><p>Jemput: ${esc(b.pickup_address || 'Garasi asal')}<br>Antar: ${esc(b.drop_address || 'Garasi tujuan')}</p><p>Status: <strong>${esc(b.status)}</strong></p><p>Dasar ${money(b.fare_base)} + tol ${money(b.fare_toll)} + perlindungan ${money(b.fare_protection)}</p><h3>Total server: ${money(b.fare_total)}</h3><p>Invoice ${esc(b.invoice_code)} | ${esc(b.invoice_status || '-')}</p>`;
  try {
    await api.ready;
    if (['masuk','daftar-penumpang','daftar-pengemudi'].includes(page)) {
      // Legacy multi-step forms are not used: uploads are not supported by this backend.
      const main=app.closest('main');
      [...main.children].forEach(el => { if(el!==app) el.hidden=true; });
      const login=page==='masuk', role=page==='daftar-pengemudi'?'pengemudi':'penumpang';
      app.innerHTML=`<h1>${login?'Masuk ke SeArah':'Daftar '+role}</h1><form id="auth">${login?'':field('name','Nama','text','required')}${field('email','Email','email','autocomplete="email" required')}${field('password','Password','password','minlength="8" maxlength="72" autocomplete="'+(login?'current-password':'new-password')+'" required')}${login?'':field('phone','Telepon','tel')}${button(login?'Masuk':'Buat akun')}</form>${login?'<p>Akun demo hanya tersedia pada database development yang di-seed backend.</p><div id="demo"></div><a href="daftar.html">Daftar akun baru</a>':notice(role==='pengemudi'?'Akun driver akan menunggu verifikasi. Upload KTP/SIM aman belum disediakan backend. Tidak ada dokumen yang diunggah oleh formulir ini.':'Data akun disimpan di server SQLite.')} `;
      if(login) {
        for(const [label,email] of [['Admin','admin'],['Driver','pengemudi'],['Penumpang','penumpang']]) {
          const b=document.createElement('button'); b.className='btn btn--ghost'; b.textContent=label+' demo'; b.onclick=() => {document.getElementById('email').value=email+'@searah.id';document.getElementById('password').value='12345678';};document.getElementById('demo').append(b);
        }
      }
      bind(document.getElementById('auth'),async data => {
        if(!login) await api.register({...data,role});
        const user=await api.login({email:data.email,password:data.password});
        const next=query.get('next');
        const url=next?new URL(next,location.href):null;
        const safe=url && url.origin===location.origin && /\/(?:pages\/[^/]+\.html|index\.html)$/.test(url.pathname);
        navigate(safe?url.href:user.role==='admin'?'admin-panel.html':user.role==='pengemudi'?'dashboard-pengemudi.html':'profil.html');
      });
      return;
    }
    const admin=page.startsWith('admin-');
    const driver=['dashboard-pengemudi','order-masuk-driver','perjalanan-aktif-driver','status-verifikasi','menunggu-verifikasi','edit-akun-driver'].includes(page);
    const passenger=['checkout','profil','detail-perjalanan','invoice','pembayaran','pembatalan'].includes(page);
    const user=api.getSession();
    if((admin||driver||passenger||['edit-profil','ubah-password','ajukan-unban'].includes(page))&&!user) {
      navigate('masuk.html?next='+encodeURIComponent(location.pathname+location.search)); return;
    }
    if((admin&&user.role!=='admin')||(driver&&user.role!=='pengemudi')||(passenger&&user.role!=='penumpang')) { app.textContent='Akses ditolak: role akun tidak sesuai.';return; }
    if(page==='booking'||page==='checkout') {
      if(user && user.role!=='penumpang') {app.textContent='Pemesanan khusus akun penumpang.';return;}
      const [refs,routes]=await Promise.all([api.request('references'),api.request('routes')]);
      const unique=[...new Set(routes.routes.map(r=>r.origin))];
      app.innerHTML=`<h1>Pesan perjalanan</h1>${notice('Pilihan armada adalah katalog, bukan jaminan ketersediaan. Konfirmasi trip dilakukan admin setelah pembayaran terverifikasi.')}<form id="booking" class="server-form">${select('package','Paket',option('reguler','Reguler')+option('door_to_door','Door to Door')+option('private','Private'))}${select('origin','Kota asal',unique.map(n=>option(n,n)).join(''))}${select('dest','Kota tujuan','')}${field('trip_date','Tanggal','date','required')}${field('time','Jam berangkat','time','required')}${field('pax','Jumlah penumpang','number','min="1" max="7" value="1" required')}${select('road','Jalan',option('biasa','Biasa')+option('tol','Tol'))}${select('insurance','Perlindungan',option('0','Tidak')+option('1','Ya'))}${select('vehicle_id','Mobil (opsional)','')}${select('driver_id','Driver Private (pilihan terpisah)',option('','Ditentukan admin')+refs.drivers.map(d=>option(d.user_id,d.name+' | rating '+(d.rating??'-'))).join(''))}<div id="addresses">${field('pickup_address','Alamat jemput','text','required')}${coords('pickup_','Koordinat jemput')}${field('drop_address','Alamat antar','text','required')}${coords('drop_','Koordinat antar')}</div><div id="quote" aria-live="polite"></div><button class="btn btn--ghost" id="quote-button" type="button">Hitung tarif server</button>${button(user?'Buat pesanan':'Masuk untuk memesan')}</form>`;
      const form=document.getElementById('booking');
      form.elements.package.value=(query.get('paket')||query.get('package')||'reguler').replace(/-/g,'_');
      if(!form.elements.package.value)form.elements.package.value='reguler';
      if(unique.includes(query.get('asal')))form.elements.origin.value=query.get('asal');
      function update() {
        const old=form.elements.dest.value || query.get('tujuan');
        form.elements.dest.innerHTML=[...new Set(routes.routes.filter(r=>r.origin===form.elements.origin.value&&r.package_type===form.elements.package.value).map(r=>r.dest))].map(n=>option(n,n)).join('');
        if([...form.elements.dest.options].some(o=>o.value===old))form.elements.dest.value=old;
        form.elements.vehicle_id.innerHTML=option('','Ditentukan admin')+refs.vehicles.filter(v=>v.serves_package===form.elements.package.value).map(v=>option(v.id,v.model+' | '+v.plate+' | '+v.seat_capacity+' kursi')).join('');
        form.elements.driver_id.disabled=form.elements.package.value!=='private';
        const addresses=document.getElementById('addresses'); addresses.hidden=form.elements.package.value==='reguler'; addresses.querySelectorAll('input,button').forEach(i=>i.disabled=addresses.hidden);
        document.getElementById('quote').textContent='Hitung ulang tarif untuk pilihan saat ini.';
      }
      update(); form.elements.origin.onchange=update;form.elements.package.onchange=update;
      form.addEventListener('input',()=>document.getElementById('quote').textContent='Pilihan berubah. Hitung ulang tarif.');
      if(query.get('tanggal'))form.elements.trip_date.value=query.get('tanggal');
      if(query.get('jam'))form.elements.time.value=query.get('jam');
      bindCoords(form);
      document.getElementById('quote-button').onclick=async () => {
        try {
          const d=formData(form); const result=await api.request('tarif?'+new URLSearchParams({origin:d.origin,dest:d.dest,package:d.package,pax:d.pax,road:d.road,insurance:d.insurance}));
          document.getElementById('quote').textContent='Tarif server: '+money(result.calc.total)+' ('+result.calc.unit+', jumlah '+result.calc.qty+')';
        } catch(err) {UI.toast(err.message,'error');}
      };
      bind(form,async d=>{
        if(!user) {navigate('masuk.html?next='+encodeURIComponent(location.pathname+location.search));return;}
        for(const key of ['pax','vehicle_id','driver_id','pickup_lat','pickup_lng','drop_lat','drop_lng']) {if(d[key]==='')delete d[key];else if(d[key]!==undefined)d[key]=Number(d[key]);}
        d.insurance=d.insurance==='1';
        const result=await act('bookings',d);
        navigate('detail-perjalanan.html?id='+result.booking.id);
      });
      return;
    }
    if(page==='profil') {
      const {bookings}=await api.request('bookings');
      app.innerHTML=`<h1>Profil ${esc(user.name)}</h1><p>${esc(user.email)} | ${esc(user.role)} | ${esc(user.status)}</p><a class="btn btn--primary" href="booking.html">Pesan perjalanan</a><h2>Pesanan saya</h2>${bookings.length?bookings.map(b=>`<article class="server-record">${summary(b)}<a class="btn btn--ghost" href="detail-perjalanan.html?id=${b.id}">Detail / pembayaran</a></article>`).join(''):notice('Belum ada pesanan di server.')}`;return;
    }
    if(['detail-perjalanan','invoice','pembayaran'].includes(page)) {
      const id=Number(query.get('id'));
      if(!id)throw new Error('ID pesanan wajib. Buka dari Profil Saya.');
      let paymentId=Number(query.get('payment_id'))||null;
      async function refresh() {
        const {booking:b}=await api.request('bookings/'+id);
        app.innerHTML=`<h1>${page==='invoice'?'Invoice':'Detail perjalanan'}</h1>${summary(b)}${notice('Status diambil dari server. Pelacakan GPS penumpang belum memiliki endpoint baca pada backend ini.')}<div class="server-actions"><a class="btn btn--ghost" href="profil.html">Pesanan saya</a><a class="btn btn--ghost" href="invoice.html?id=${id}">Invoice</a><button class="btn btn--ghost" id="refresh">Perbarui status</button><button class="btn btn--ghost" id="print">Cetak</button></div><div id="payment"></div>`;
        document.getElementById('refresh').onclick=()=>refresh().catch(err=>UI.toast(err.message,'error'));
        document.getElementById('print').onclick=()=>window.print();
        if(b.status==='menunggu_pembayaran') {
          const div=document.getElementById('payment');
          div.innerHTML='<button class="btn btn--primary" id="pay">'+(paymentId?'Ambil pembayaran':'Buat pembayaran Duitku')+'</button>'+notice('Tidak ada QR atau status lunas buatan. Gateway yang belum dikonfigurasi akan mengembalikan kesalahan server.');
          document.getElementById('pay').onclick=async function(){
            this.disabled=true;
            try {
              const data=paymentId?await api.request('payments/'+paymentId):await act('payments',{booking_id:id});
              paymentId=data.payment.id;
              const url=new URL(location.href);url.searchParams.set('payment_id',paymentId);history.replaceState(null,'',url);
              const p=data.payment;
              div.innerHTML=`<p>Order ${esc(p.merchant_order_id || p.order_id || p.id)} | ${esc(p.status)} | ${money(p.amount)}</p><div id="gateway-link"></div><button class="btn btn--primary" id="check">Cek status pembayaran</button>`;
              const paymentURL=p.redirect_url;
              if(paymentURL) { const url=new URL(paymentURL); if(url.protocol==='https:'){const a=document.createElement('a');a.href=url.href;a.target='_blank';a.rel='noopener noreferrer';a.className='btn btn--ghost';a.textContent='Buka pembayaran resmi';document.getElementById('gateway-link').append(a);} }
              document.getElementById('check').onclick=async function(){this.disabled=true;try{const result=await act('payments/'+paymentId+'/check');UI.toast('Status server: '+result.payment.status);await refresh();}catch(err){UI.toast(err.message,'error');this.disabled=false;}};
            }catch(err){UI.toast(err.message,'error');this.disabled=false;}
          };
        }
        if(b.status==='selesai') {
          const f=document.createElement('form');f.innerHTML='<h2>Rating driver</h2>'+select('score','Nilai',[1,2,3,4,5].map(n=>option(n,n)).join(''))+field('comment','Ulasan')+button('Kirim rating');app.append(f);
          bind(f,async d=>{await act('ratings',{booking_id:id,score:Number(d.score),comment:d.comment});f.replaceWith(document.createTextNode('Rating tersimpan di server.'));});
        }
      }
      await refresh();return;
    }
    if(page==='admin-armada') {
      let refs,garages,vehicles,drivers,relocations;
      async function reload() {
        const results=await Promise.all([api.request('references'),api.request('admin/garasi'),api.request('admin/vehicles'),api.request('admin/accounts/pengemudi'),api.request('admin/relocations')]);
        refs=results[0];garages=results[1].garages;vehicles=results[2].vehicles;drivers=results[3].accounts;relocations=results[4].relocations;
        render();
      }
      function render() {
        app.innerHTML='<h1>Garasi, Mobil & Monitoring Driver</h1><div class="server-actions"><button class="btn btn--primary" id="new-garage">Tambah garasi</button><button class="btn btn--primary" id="new-vehicle">Tambah mobil</button><button class="btn btn--ghost" id="reload">Perbarui</button></div><div id="editor"></div>'+field('search','Cari nama / alamat / pelat / driver','search')+select('region','Wilayah',option('','Semua wilayah')+refs.regions.map(r=>option(r.id,r.name||r.code||r.id)).join(''))+'<div id="records"></div><h2>Relokasi menunggu keputusan</h2><div id="relocations"></div>';
        document.getElementById('new-garage').onclick=()=>editGarage();document.getElementById('new-vehicle').onclick=()=>editVehicle();document.getElementById('reload').onclick=()=>reload().catch(e=>UI.toast(e.message,'error'));
        document.getElementById('search').oninput=records;document.getElementById('region').onchange=records;records();
        const slot=document.getElementById('relocations');
        slot.innerHTML=relocations.length?relocations.map(r=>`<article class="server-record"><p>#${r.id} Driver ${esc(r.driver_name||r.driver_user_id)} ke ${esc(r.requested_garage||r.requested_garage_id)} | ${esc(r.status)}</p><p>${esc(r.note)} | GPS ${esc(r.reported_lat)}, ${esc(r.reported_lng)}</p>${r.status==='await_admin'?`<button class="btn btn--primary" data-decision="approve" data-id="${r.id}">Setujui</button> <button class="btn btn--ghost" data-decision="reject" data-id="${r.id}">Tolak</button>`:''}</article>`).join(''):notice('Tidak ada relokasi menunggu.');
        slot.querySelectorAll('[data-decision]').forEach(b=>b.onclick=async()=>{const note=prompt('Catatan keputusan:','');if(note===null)return;try{await act('admin/relocations/'+b.dataset.id+'/'+b.dataset.decision,{note});await reload();}catch(e){UI.toast(e.message,'error');}});
      }
      function records() {
        const q=document.getElementById('search').value.toLowerCase(), region=document.getElementById('region').value;
        const city=g=>refs.cities.find(c=>Number(c.id)===Number(g.city_id));
        const matches=g=>!region||String(city(g)?.region_id)===region;
        const list=garages.filter(matches).filter(g=>[g.name,g.address,city(g)?.name,...vehicles.filter(v=>Number(v.current_garage_id)===Number(g.id)).map(v=>v.model+' '+v.plate)].join(' ').toLowerCase().includes(q));
        const slot=document.getElementById('records');
        slot.innerHTML=list.map(g=>`<article class="server-record"><h2>${esc(g.name)} | ${esc(city(g)?.name)}</h2><p>${esc(g.address)} | ${esc(g.lat)}, ${esc(g.lng)} | ${Number(g.is_active)?'Aktif':'Nonaktif'}</p><button class="btn btn--ghost" data-garage="${g.id}">Edit garasi</button> <button class="btn btn--ghost" data-disable-g="${g.id}">Nonaktifkan</button>${vehicles.filter(v=>Number(v.current_garage_id)===Number(g.id)).map(v=>`<p>${esc(v.model)} | ${esc(v.plate)} | ${esc(v.serves_package)} | ${esc(v.seat_capacity)} kursi | ${esc(v.status)} <button class="btn btn--ghost" data-vehicle="${v.id}">Edit / pindah</button> <button class="btn btn--ghost" data-disable-v="${v.id}">Nonaktifkan</button></p>`).join('')}</article>`).join('')||notice('Tidak ada garasi yang cocok.');
        const filteredDrivers=drivers.filter(d=>{
          const g=garages.find(g=>Number(g.id)===Number(d.current_garage_id));
          return (!region||(g&&matches(g)))&&[d.name,d.email,g?.name].join(' ').toLowerCase().includes(q);
        });
        slot.innerHTML+='<h2>Monitoring driver</h2>'+filteredDrivers.map(d=>`<p>${esc(d.name)} | ${esc(d.email)} | Akun ${esc(d.status)} | Verifikasi ${esc(d.driver_status||d.drv_status||'-')} | Garasi ${esc(garages.find(g=>Number(g.id)===Number(d.current_garage_id))?.name||'Belum ditetapkan')}</p>`).join('');
        slot.querySelectorAll('[data-garage]').forEach(b=>b.onclick=()=>editGarage(garages.find(g=>String(g.id)===b.dataset.garage)));
        slot.querySelectorAll('[data-vehicle]').forEach(b=>b.onclick=()=>editVehicle(vehicles.find(v=>String(v.id)===b.dataset.vehicle)));
        for(const [attr,path] of [['disable-g','garasi'],['disable-v','vehicles']])slot.querySelectorAll('[data-'+attr+']').forEach(b=>b.onclick=async()=>{if(!confirm('Nonaktifkan record ini di server?'))return;try{await act('admin/'+path+'/'+b.getAttribute('data-'+attr)+'/disable');await reload();}catch(e){UI.toast(e.message,'error');}});
      }
      function editGarage(g={}) {
        const slot=document.getElementById('editor');slot.innerHTML=`<h2>${g.id?'Edit':'Tambah'} garasi</h2><form id="garage-form" class="server-form">${field('name','Nama garasi','text','required')}${select('city_id','Kota',refs.cities.map(c=>option(c.id,c.name)).join(''))}${field('address','Alamat','text','required')}${coords('','Lokasi garasi')}${field('phone','Telepon','tel')}${field('operating_hours','Jam operasi')}${button('Simpan garasi')}<button type="button" class="btn btn--ghost" id="close-editor">Batal</button></form>`;
        const f=document.getElementById('garage-form');for(const key of ['name','city_id','address','lat','lng','phone','operating_hours'])if(g[key]!=null)f.elements[key].value=g[key];bindCoords(f);
        document.getElementById('close-editor').onclick=()=>slot.replaceChildren();
        bind(f,async d=>{await act('admin/garasi'+(g.id?'/'+g.id:''),{...d,city_id:Number(d.city_id),lat:Number(d.lat),lng:Number(d.lng)},g.id?'PATCH':'POST');await reload();});slot.scrollIntoView({block:'center'});
      }
      function editVehicle(v={}) {
        const active=garages.filter(g=>Number(g.is_active));
        if(!active.length){UI.toast('Buat garasi aktif terlebih dahulu.','warning');return;}
        const slot=document.getElementById('editor');slot.innerHTML=`<h2>${v.id?'Edit / pindah':'Tambah'} mobil</h2><form id="vehicle-form" class="server-form">${field('model','Model mobil','text','required')}${field('plate','Pelat nomor','text','required')}${select('serves_package','Paket',['reguler','door_to_door','private'].map(n=>option(n,n)).join(''))}${field('seat_capacity','Kapasitas kursi','number','min="1" max="100" value="7" required')}${select('current_garage_id','Garasi aktif',active.map(g=>option(g.id,g.name)).join(''))}${field('year','Tahun','number','min="1900" max="2100"')}${button('Simpan mobil')}<button type="button" class="btn btn--ghost" id="close-editor">Batal</button></form>`;
        const f=document.getElementById('vehicle-form');for(const key of ['model','plate','serves_package','seat_capacity','current_garage_id','year'])if(v[key]!=null)f.elements[key].value=v[key];
        document.getElementById('close-editor').onclick=()=>slot.replaceChildren();
        bind(f,async d=>{d.seat_capacity=Number(d.seat_capacity);d.current_garage_id=Number(d.current_garage_id);if(d.year)d.year=Number(d.year);else delete d.year;await act('admin/vehicles'+(v.id?'/'+v.id:''),d,v.id?'PATCH':'POST');await reload();});slot.scrollIntoView({block:'center'});
      }
      await reload();return;
    }
    if(page==='admin-panel') {
      const refs=await api.request('references');
      app.innerHTML='<h1>Manajemen Akun & Perjalanan</h1><div class="server-actions" id="tabs"></div><div id="accounts"></div><h2>Operasional trip</h2><div id="trip-editor"></div><div id="trips"></div><h2>Smart Match</h2><button class="btn btn--primary" id="match">Jalankan pencocokan server</button><div id="suggestions"></div>';
      async function accounts(role) {
        const slot=document.getElementById('accounts');
        if(role==='admin'){slot.innerHTML=notice('CRUD dan daftar seluruh akun admin belum tersedia di backend. Akun admin aktif: '+user.name+' ('+user.email+').');return;}
        const {accounts}=await api.request('admin/accounts/'+role);
        slot.innerHTML='<h2>Akun '+(role==='pengemudi'?'Driver':'Penumpang')+'</h2>'+field('account-search','Cari akun','search')+'<div id="account-records"></div>';
        function render(){const q=document.getElementById('account-search').value.toLowerCase();document.getElementById('account-records').innerHTML=accounts.filter(a=>(a.name+' '+a.email).toLowerCase().includes(q)).map(a=>`<article class="server-record"><b>${esc(a.name)}</b><p>${esc(a.email)} | ${esc(a.status)}</p>${select('status-'+a.id,'Status', ['aktif','suspend','ban','dinonaktifkan'].map(s=>option(s,s)).join(''))}<button class="btn btn--ghost" data-account="${a.id}">Simpan status</button></article>`).join('')||notice('Tidak ada akun.');accounts.forEach(a=>{const s=document.getElementById('status-'+a.id);if(s)s.value=a.status;});document.querySelectorAll('[data-account]').forEach(b=>b.onclick=async()=>{const reason=prompt('Alasan perubahan status:','');if(reason===null)return;try{await act('admin/accounts/'+b.dataset.account+'/status',{status:document.getElementById('status-'+b.dataset.account).value,reason});await accountsReload(role);}catch(e){UI.toast(e.message,'error');}});}
        render();document.getElementById('account-search').oninput=render;
      }
      const accountsReload=accounts;
      for(const [role,label]of [['penumpang','Akun Penumpang'],['pengemudi','Akun Driver'],['admin','Akun Admin']]){const b=document.createElement('button');b.className='btn btn--ghost';b.textContent=label;b.onclick=()=>accounts(role).catch(e=>UI.toast(e.message,'error'));document.getElementById('tabs').append(b);}
      await accounts('penumpang');
      const routes=(await api.request('routes')).routes;
      const routeRecords=[...new Map(routes.map(r=>[r.id,r])).values()];
      document.getElementById('trip-editor').innerHTML=`<form id="trip-form" class="server-form">${select('package','Paket',['reguler','door_to_door','private'].map(s=>option(s,s)).join(''))}${select('route_id','Rute',routeRecords.map(r=>option(r.id,r.origin+' ke '+r.dest)).join(''))}${field('trip_date','Tanggal','date','required')}${field('departure_time','Jam','time','required')}${select('road_type','Jalan',option('biasa','Biasa')+option('tol','Tol'))}${select('origin_garage_id','Garasi asal',option('','Opsional')+refs.garages.map(g=>option(g.id,g.name)).join(''))}${select('dest_garage_id','Garasi tujuan',option('','Opsional')+refs.garages.map(g=>option(g.id,g.name)).join(''))}${select('vehicle_id','Mobil',refs.vehicles.map(v=>option(v.id,v.model+' '+v.plate+' '+v.serves_package)).join(''))}${select('driver_id','Driver',refs.drivers.map(d=>option(d.user_id,d.name)).join(''))}${button('Buat draft trip')}</form>`;
      bind(document.getElementById('trip-form'),async d=>{for(const k of ['route_id','vehicle_id','driver_id','origin_garage_id','dest_garage_id'])if(d[k])d[k]=Number(d[k]);else delete d[k];await act('admin/trips',d);await trips();});
      async function trips(){const {trips}=await api.request('admin/trips');document.getElementById('trips').innerHTML=trips.map(t=>`<article class="server-record"><h3>Trip #${t.id} | ${esc(t.package_type)} | ${esc(t.trip_date)} ${esc(t.departure_time)}</h3><p>${esc(t.status)} | Mobil ${esc(t.vehicle_id)} | Driver ${esc(t.driver_id)}</p><button class="btn btn--ghost" data-detail="${t.id}">Manifest / GPS</button>${t.status==='draft'?`<button class="btn btn--ghost" data-attach="${t.id}">Tambah booking lunas</button> <button class="btn btn--primary" data-confirm="${t.id}">Konfirmasi trip</button>`:''}<div id="trip-detail-${t.id}"></div></article>`).join('')||notice('Belum ada trip.');
        document.querySelectorAll('[data-detail]').forEach(b=>b.onclick=async()=>{try{const {trip}=await api.request('admin/trips/'+b.dataset.detail);const pre=document.createElement('pre');pre.className='server-json';pre.textContent=JSON.stringify(trip,null,2);document.getElementById('trip-detail-'+b.dataset.detail).replaceChildren(pre);}catch(e){UI.toast(e.message,'error');}});
        document.querySelectorAll('[data-attach]').forEach(b=>b.onclick=async()=>{const id=prompt('ID numerik booking lunas:');if(!id)return;try{await act('admin/trips/'+b.dataset.attach+'/bookings',{booking_id:Number(id)});await tripsReload();}catch(e){UI.toast(e.message,'error');}});
        document.querySelectorAll('[data-confirm]').forEach(b=>b.onclick=async()=>{try{await act('admin/trips/'+b.dataset.confirm+'/confirm');await tripsReload();}catch(e){UI.toast(e.message,'error');}});
      }
      const tripsReload=trips;
      async function suggestions(){const {suggestions}=await api.request('admin/match/suggestions');const slot=document.getElementById('suggestions');slot.innerHTML=suggestions.map(s=>`<article class="server-record"><p>Usulan #${s.id} Booking ${esc(s.booking_id)} | Trip ${esc(s.target_trip_id||'-')} | Skor ${esc(s.score)}</p><button class="btn btn--ghost" data-accept="${s.id}">Terima usulan</button> <button class="btn btn--ghost" data-reject="${s.id}">Tolak</button></article>`).join('')||notice('Tidak ada usulan. Usulan bukan konfirmasi keberangkatan.');slot.querySelectorAll('[data-accept]').forEach(b=>b.onclick=async()=>{const id=prompt('ID target draft trip (wajib untuk alokasi yang sudah dipilih):');if(!id)return;try{await act('admin/match/'+b.dataset.accept+'/accept',{target_trip_id:Number(id)});await suggestions();await trips();}catch(e){UI.toast(e.message,'error');}});slot.querySelectorAll('[data-reject]').forEach(b=>b.onclick=async()=>{try{await act('admin/match/'+b.dataset.reject+'/reject');await suggestions();}catch(e){UI.toast(e.message,'error');}});}
      document.getElementById('match').onclick=async function(){this.disabled=true;try{await act('admin/match/run');await suggestions();}catch(e){UI.toast(e.message,'error');}finally{this.disabled=false;}};
      await trips();await suggestions();return;
    }
    if(['dashboard-pengemudi','order-masuk-driver','perjalanan-aktif-driver'].includes(page)) {
      const refs=await api.request('references');
      let watch=null,sending=false,lastSent=0;
      window.addEventListener('pagehide',()=>{if(watch!==null)navigator.geolocation.clearWatch(watch);});
      async function refresh() {
        if(watch!==null){navigator.geolocation.clearWatch(watch);watch=null;}
        const [{trips},{relocations}]=await Promise.all([api.request('driver/trips'),api.request('driver/relocation')]);
        app.innerHTML=`<h1>Dashboard Driver</h1><p>${esc(user.name)} | ${esc(user.email)}</p><button class="btn btn--ghost" id="refresh">Perbarui</button><h2>Penugasan server</h2>${trips.map(t=>`<article class="server-record"><h3>Trip #${t.id} | ${esc(t.trip_date)} ${esc(t.departure_time)}</h3><p>${esc(t.package_type)} | Status ${esc(t.status)} | Respons ${esc(t.my_response||'Belum direspons')}</p>${t.my_response!=='terima'?`<button class="btn btn--primary" data-respond="terima" data-id="${t.id}">Terima</button> <button class="btn btn--ghost" data-respond="tolak" data-id="${t.id}">Tolak</button>`:`<form data-trip="${t.id}">${select('event-'+t.id,'Status perjalanan',['berangkat','tiba_jemput','berlangsung','tiba_drop','selesai','dibatalkan'].map(n=>option(n,n)).join(''))}${button('Kirim status')}</form><button class="btn btn--ghost" data-track="${t.id}">Mulai GPS nyata</button>`}</article>`).join('')||notice('Tidak ada penugasan aktif. Admin harus membuat dan mengonfirmasi trip terlebih dahulu.')}<p id="gps-status" aria-live="polite"></p><button class="btn btn--ghost" id="stop-gps">Hentikan GPS</button><h2>Ajukan relokasi garasi</h2><form id="relocation" class="server-form">${select('requested_garage_id','Garasi tujuan',refs.garages.map(g=>option(g.id,g.name)).join(''))}${select('verify_method','Metode',option('confirm','Konfirmasi')+option('gps_scan','GPS')+option('manual_map','Peta manual'))}<div id="relocation-coords">${coords('','Koordinat relokasi')}</div>${field('work_date','Tanggal kerja','date')}${field('start_time','Mulai','time')}${field('end_time','Selesai','time')}${field('note','Catatan')}${button('Ajukan, menunggu admin')}</form><h2>Status relokasi</h2>${relocations.map(r=>`<article class="server-record"><p>#${r.id} | ${esc(r.requested_garage || r.proposed_garage_id)} | ${esc(r.status)} | ${esc(r.review_note||r.note)}</p>${r.status==='await_driver_verify'?`<button class="btn btn--ghost" data-verify="${r.id}">Konfirmasi garasi usulan</button>`:''}</article>`).join('')||notice('Belum ada relokasi.')}`;
        document.getElementById('refresh').onclick=()=>refresh().catch(e=>UI.toast(e.message,'error'));
        document.querySelectorAll('[data-respond]').forEach(b=>b.onclick=async()=>{const reason=b.dataset.respond==='tolak'?prompt('Alasan penolakan (penalti backend berlaku):'):'';if(reason===null)return;try{await act('driver/trips/'+b.dataset.id+'/respond',{response:b.dataset.respond,reason});await refresh();}catch(e){UI.toast(e.message,'error');}});
        document.querySelectorAll('[data-trip]').forEach(f=>bind(f,async()=>{await act('driver/trips/'+f.dataset.trip+'/status',{event:f.elements['event-'+f.dataset.trip].value});await refresh();}));
        document.getElementById('stop-gps').onclick=()=>{if(watch!==null)navigator.geolocation.clearWatch(watch);watch=null;document.getElementById('gps-status').textContent='GPS dihentikan.';};
        document.querySelectorAll('[data-track]').forEach(b=>b.onclick=()=>{
          if(!navigator.geolocation){UI.toast('GPS tidak tersedia.','error');return;}
          if(watch!==null)navigator.geolocation.clearWatch(watch);
          document.getElementById('gps-status').textContent='Meminta izin GPS...';
          watch=navigator.geolocation.watchPosition(async p=>{
            if(sending||Date.now()-lastSent<10000)return;
            sending=true;
            try{await act('driver/trips/'+b.dataset.track+'/location',{lat:p.coords.latitude,lng:p.coords.longitude,accuracy:p.coords.accuracy});lastSent=Date.now();document.getElementById('gps-status').textContent='GPS tersimpan di server: '+p.coords.latitude+', '+p.coords.longitude+' | '+new Date().toLocaleTimeString('id-ID');}
            catch(e){UI.toast(e.message,'error');if(watch!==null)navigator.geolocation.clearWatch(watch);watch=null;}finally{sending=false;}
          },e=>{UI.toast('GPS gagal: '+e.message,'error');if(watch!==null)navigator.geolocation.clearWatch(watch);watch=null;},{enableHighAccuracy:true,timeout:15000,maximumAge:0});
        });
        const f=document.getElementById('relocation');bindCoords(f);
        function method(){const box=document.getElementById('relocation-coords');box.hidden=f.elements.verify_method.value==='confirm';box.querySelectorAll('input,button').forEach(i=>i.disabled=box.hidden);}
        f.elements.verify_method.onchange=method;method();
        bind(f,async d=>{d.requested_garage_id=Number(d.requested_garage_id);if(d.lat!==undefined){d.lat=Number(d.lat);d.lng=Number(d.lng);}for(const key of ['work_date','start_time','end_time'])if(!d[key])delete d[key];await act('driver/relocation',d);await refresh();});
        document.querySelectorAll('[data-verify]').forEach(b=>b.onclick=async()=>{try{await act('driver/relocation/'+b.dataset.verify+'/verify',{action:'confirm'});await refresh();}catch(e){UI.toast(e.message,'error');}});
      }
      await refresh();return;
    }
    if(page==='admin-verifikasi') {
      async function refresh(){const {drivers}=await api.request('admin/verify/pending');app.innerHTML='<h1>Verifikasi Driver</h1>'+notice('Backend memerlukan record KTP dan SIM untuk persetujuan; upload/download dokumen aman belum tersedia.')+drivers.map(d=>`<article class="server-record"><h2>${esc(d.name)}</h2><p>${esc(d.email)} | ${esc(d.status)}</p><p>${esc((d.documents||[]).map(x=>x.jenis||x.type).join(', '))}</p><button class="btn btn--primary" data-review="${d.user_id||d.id}" data-approve="1">Setujui</button> <button class="btn btn--ghost" data-review="${d.user_id||d.id}" data-approve="0">Tolak</button></article>`).join('');document.querySelectorAll('[data-review]').forEach(b=>b.onclick=async()=>{const note=prompt('Catatan verifikasi:','');if(note===null)return;try{await act('admin/verify/'+b.dataset.review,{approve:b.dataset.approve==='1',note});await refresh();}catch(e){UI.toast(e.message,'error');}});}
      await refresh();return;
    }
    if(page==='admin-unban') {
      async function refresh(){const {requests}=await api.request('admin/unban');app.innerHTML='<h1>Review Unban</h1>'+notice('Keputusan hanya diizinkan backend pada hari Minggu Asia/Jakarta dan oleh super admin.')+requests.map(r=>`<article class="server-record"><p>${esc(r.name||r.user_id)} | ${esc(r.reason)} | ${esc(r.status)}</p><button class="btn btn--primary" data-unban="${r.id}" data-approve="1">Setujui</button> <button class="btn btn--ghost" data-unban="${r.id}" data-approve="0">Tolak</button></article>`).join('');document.querySelectorAll('[data-unban]').forEach(b=>b.onclick=async()=>{const note=prompt('Catatan:','');if(note===null)return;try{await act('admin/unban/'+b.dataset.unban+'/review',{approve:b.dataset.approve==='1',note});await refresh();}catch(e){UI.toast(e.message,'error');}});}
      await refresh();return;
    }
    if(page==='admin-setting') {
      const {settings}=await api.request('settings');app.innerHTML='<h1>Pengaturan backend</h1>'+notice('Read-only: endpoint perubahan pengaturan belum disediakan. Tidak ada perubahan lokal yang dianggap tersimpan.');const pre=document.createElement('pre');pre.className='server-json';pre.textContent=JSON.stringify(settings,null,2);app.append(pre);return;
    }
    if(['status-verifikasi','menunggu-verifikasi'].includes(page)) {
      const refs=await api.request('references');const d=refs.drivers.find(d=>Number(d.user_id)===Number(user.id));app.innerHTML='<h1>Status Driver</h1>'+notice(d?'Akun tercantum dalam katalog driver terverifikasi.':'Akun tidak tercantum dalam katalog driver terverifikasi. Hubungi admin untuk pemeriksaan.')+notice('Upload dokumen dan endpoint detail verifikasi pribadi belum tersedia di backend.');return;
    }
    app.innerHTML='<h1>'+esc(page.replace(/-/g,' '))+'</h1>'+notice('Fitur ini belum memiliki endpoint backend: perubahan profil/password, pembatalan penumpang, atau pengajuan banding. Tidak ada data browser yang disimpan dan tidak ada klaim sukses.')+'<a class="btn btn--ghost" href="'+(user?.role==='pengemudi'?'dashboard-pengemudi.html':'profil.html')+'">Kembali</a>';
  } catch(err) {
    app.replaceChildren();const h=document.createElement('h1');h.textContent='Data server tidak dapat dimuat';const p=document.createElement('p');p.textContent=err.message;const b=document.createElement('button');b.className='btn btn--primary';b.textContent='Coba lagi';b.onclick=()=>location.reload();app.append(h,p,b);
  }
})();
