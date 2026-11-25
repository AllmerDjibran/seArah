/* Same-origin PHP API. Only the bearer credential is kept per tab. */
const api = {
  user: null,
  token: sessionStorage.getItem('searah_bearer') || '',
  async request(endpoint, method = 'GET', body) {
    const base = new URL(location.pathname.includes('/pages/') ? '../' : './', location.href);
    const response = await fetch(new URL('api/' + endpoint, base), {
      method, headers: {Accept:'application/json', ...(body === undefined ? {} : {'Content-Type':'application/json'}), ...(this.token ? {Authorization:'Bearer ' + this.token} : {})},
      ...(body === undefined ? {} : {body:JSON.stringify(body)})
    });
    let data;
    try { data = await response.json(); } catch { throw new Error('Respons API bukan JSON. Jalankan aplikasi melalui PHP, bukan file://.'); }
    if (!response.ok || data.ok !== true) {
      if (response.status === 401) { this.user = null; this.token = ''; sessionStorage.removeItem('searah_bearer'); }
      throw new Error(data.error || 'Permintaan gagal (' + response.status + ').');
    }
    return data;
  },
  async login(payload) { const data = await this.request('auth/login','POST',payload); this.token = data.token; sessionStorage.setItem('searah_bearer',data.token); this.user = data.user; return data.user; },
  async register(payload) { return this.request('auth/register','POST',payload); },
  async me() { this.user = (await this.request('auth/me')).user; return this.user; },
  async logout() { try { if(this.token) await this.request('auth/logout','POST',{}); } finally { this.user=null; this.token=''; sessionStorage.removeItem('searah_bearer'); location.href = new URL(location.pathname.includes('/pages/') ? '../index.html' : 'index.html',location.href); } },
  getSession() { return this.user; },
  getContact() { return {wa:'0812-3456-7890',ig:'@searah.id',tiktok:'@searah.id',cs:'Customer Service SeArah'}; }
};
api.ready = api.token ? api.me() : Promise.resolve(null);
api.ready.catch(() => {});
