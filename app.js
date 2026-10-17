/* ── App State ────────────────────────────────────────────────────────────── */
const State = {
  user: null,
  currentPage: 'home',
  currentProfilePanel: 'account-info',
};

/* ── Currency System (USD ⇄ LKR) ─────────────────────────────────────────── */
const Currency = {
  current: localStorage.getItem('tourguide_currency') || 'USD',
  rate: 300, // Exchange Rate: 1 USD = 300 LKR

  toggle() {
    this.current = this.current === 'USD' ? 'LKR' : 'USD';
    localStorage.setItem('tourguide_currency', this.current);
    this.updateLabels();
    this.refreshCurrentView();
    Toast.info(`Currency switched to ${this.current} (${this.current === 'USD' ? '$' : 'Rs.'})`);
  },

  format(amountInUSD) {
    const num = parseFloat(amountInUSD) || 0;
    if (this.current === 'LKR') {
      const lkr = Math.round(num * this.rate);
      return `Rs. ${lkr.toLocaleString()}`;
    }
    return `$${num.toFixed(2)}`;
  },

  formatPlain(amountInUSD) {
    const num = parseFloat(amountInUSD) || 0;
    if (this.current === 'LKR') {
      const lkr = Math.round(num * this.rate);
      return `Rs. ${lkr.toLocaleString()}`;
    }
    return `$${num.toFixed(2)}`;
  },

  updateLabels() {
    const isUSD = this.current === 'USD';
    const labelText = isUSD ? 'USD ($)' : 'LKR (Rs.)';

    const navBtn = document.getElementById('curr-label');
    if (navBtn) navBtn.textContent = labelText;
    const mobBtn = document.getElementById('mobile-curr-label');
    if (mobBtn) mobBtn.textContent = labelText;
    const heroBtn = document.getElementById('hero-curr-label');
    if (heroBtn) heroBtn.textContent = labelText;
    const searchBtn = document.getElementById('search-curr-label');
    if (searchBtn) searchBtn.textContent = labelText;
  },

  refreshCurrentView() {
    if (State.currentPage === 'home') {
      loadHomeData();
    } else if (State.currentPage === 'profile') {
      if (State.user) {
        if (document.getElementById('panel-bookings')?.classList.contains('active')) {
          loadGuestBookings();
        } else if (document.getElementById('panel-received')?.classList.contains('active')) {
          applyReceivedFilters();
        } else if (document.getElementById('panel-my-items')?.classList.contains('active')) {
          loadMyItems();
        } else if (document.getElementById('panel-wishlist')?.classList.contains('active')) {
          loadGuestWishlist();
        }
      }
    } else {
      loadListings(State.currentPage);
    }
  }
};

function toggleCurrency() {
  Currency.toggle();
}

function formatCurrency(val) {
  return Currency.format(val);
}

const Theme = {
  current: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',

  update() {
    const dark = this.current === 'dark';
    const nextTheme = dark ? 'light' : 'dark';
    document.documentElement.dataset.theme = this.current;

    const button = document.getElementById('theme-toggle-btn');
    const icon = document.getElementById('theme-toggle-icon');
    const label = document.getElementById('theme-toggle-label');
    const mobileButton = document.getElementById('mobile-theme-toggle');
    if (button) {
      button.title = `Switch to ${nextTheme} theme`;
      button.setAttribute('aria-label', `Switch to ${nextTheme} theme`);
    }
    if (icon) icon.textContent = dark ? '☀️' : '🌙';
    if (label) label.textContent = dark ? 'Light' : 'Dark';
    if (mobileButton) mobileButton.textContent = `${dark ? '☀️' : '🌙'} Switch to ${nextTheme} theme`;
  },

  toggle() {
    this.current = this.current === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('tourguide_theme', this.current); } catch (error) {}
    this.update();
  },
};

function toggleTheme() {
  Theme.toggle();
  return false;
}

/* ── API Helper ───────────────────────────────────────────────────────────── */
const API = {
  baseUrl: '/New/api.php',

  async get(action, params = {}) {
    const url = new URL(this.baseUrl, location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        url.searchParams.set(k, v);
      }
    });
    try {
      const res = await fetch(url, { credentials: 'include' });
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || `Server error (${res.status})`);
      }
      return await res.json();
    } catch (e) {
      console.warn(`API GET error on [${action}]:`, e);
      throw e;
    }
  },

  async post(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    if (data instanceof FormData) {
      data.forEach((v, k) => formData.append(k, v));
    } else {
      Object.entries(data).forEach(([k, v]) => {
        if (v !== undefined && v !== null) {
          formData.append(k, v);
        }
      });
    }
    try {
      const res = await fetch(this.baseUrl, {
        method: 'POST',
        body: formData,
        credentials: 'include',
      });
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || `Server error (${res.status})`);
      }
      return await res.json();
    } catch (e) {
      console.warn(`API POST error on [${action}]:`, e);
      throw e;
    }
  },

  async postForm(action, formEl) {
    const formData = new FormData(formEl);
    formData.append('action', action);
    try {
      const res = await fetch(this.baseUrl, {
        method: 'POST',
        body: formData,
        credentials: 'include',
      });
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || `Server error (${res.status})`);
      }
      return await res.json();
    } catch (e) {
      console.warn(`API postForm error on [${action}]:`, e);
      throw e;
    }
  },

  async delete(action, params = {}) {
    const url = new URL(this.baseUrl, location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    try {
      const res = await fetch(url, {
        method: 'DELETE',
        credentials: 'include',
      });
      if (!res.ok) {
        const errJson = await res.json().catch(() => ({}));
        throw new Error(errJson.message || `Server error (${res.status})`);
      }
      return await res.json();
    } catch (e) {
      console.warn(`API DELETE error on [${action}]:`, e);
      throw e;
    }
  },
};

/* ── Toast System ─────────────────────────────────────────────────────────── */
const Toast = {
  show(msg, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const icons = { success: '✅', error: '❌', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${msg}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  },
  success(msg) { this.show(msg, 'success'); },
  error(msg)   { this.show(msg, 'error'); },
  info(msg)    { this.show(msg, 'info'); },
};

/* ── Page Navigation ──────────────────────────────────────────────────────── */
function navigateTo(pageId) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const page = document.getElementById('page-' + pageId);
  if (page) {
    page.classList.add('active');
    State.currentPage = pageId;
    window.scrollTo(0, 0);
  }

  // Update nav links
  document.querySelectorAll('.nav-link').forEach(a => {
    a.classList.toggle('active', a.dataset.page === pageId);
  });

  // Load page data
  if (pageId === 'home') loadHomeData();
  if (pageId === 'accommodation') loadListings('accommodation');
  if (pageId === 'bikes') loadListings('bikes');
  if (pageId === 'equipment') loadListings('equipment');
  if (pageId === 'guides') loadListings('guides');
  if (pageId === 'profile') loadProfile();

  // Close mobile nav
  closeMobileNav();
}

/* ── Mobile Nav ───────────────────────────────────────────────────────────── */
function toggleMobileNav() {
  const nav = document.getElementById('mobile-nav');
  const btn = document.getElementById('hamburger-btn');
  if (nav) nav.classList.toggle('open');
  if (btn) btn.classList.toggle('open');
}

function closeMobileNav() {
  const nav = document.getElementById('mobile-nav');
  const btn = document.getElementById('hamburger-btn');
  if (nav) nav.classList.remove('open');
  if (btn) btn.classList.remove('open');
}

/* ── Auth ─────────────────────────────────────────────────────────────────── */
async function checkAuth() {
  try {
    const res = await API.get('me');
    if (res.success && res.loggedIn) {
      State.user = res.user;
      updateNavUser();
    } else {
      State.user = null;
      updateNavUser();
    }
  } catch (e) {
    State.user = null;
    updateNavUser();
  }
}

function updateNavUser() {
  const btn = document.getElementById('nav-profile-btn');
  if (!btn) return;
  if (State.user) {
    const initial = (State.user.full_name || State.user.username || 'U').charAt(0).toUpperCase();
    btn.innerHTML = `<span class="nav-avatar">${initial}</span>${escHtml(State.user.username)}`;
  } else {
    btn.innerHTML = `<span class="nav-avatar">👤</span>Sign In`;
  }
}

/* ── Login / Register Modal ───────────────────────────────────────────────── */
function openAuthModal() {
  if (State.user) {
    navigateTo('profile');
    return;
  }
  document.getElementById('auth-modal')?.classList.add('open');
}

function closeAuthModal() {
  document.getElementById('auth-modal')?.classList.remove('open');
}

function switchAuthTab(tab) {
  document.querySelectorAll('#auth-modal .modal-tab').forEach(t =>
    t.classList.toggle('active', t.dataset.tab === tab)
  );
  document.querySelectorAll('#auth-modal .modal-tab-content').forEach(c =>
    c.classList.toggle('active', c.id === `tab-${tab}`)
  );
  document.querySelectorAll('#auth-modal .alert').forEach(a => a.remove());
}

async function handleLogin(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Signing in…';

  clearAlerts('login-alerts');

  const username = form.querySelector('#login-username').value.trim();
  const password = form.querySelector('#login-password').value;

  try {
    const res = await API.post('login', { username, password });
    if (res.success) {
      State.user = res.user;
      updateNavUser();
      showAlert('login-alerts', res.message, 'success');
      form.reset();
      setTimeout(() => {
        closeAuthModal();
        loadProfile();
        navigateTo('profile');
      }, 900);
    } else {
      showAlert('login-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('login-alerts', err.message || 'Connection error. Please try again.', 'error');
  }

  btn.disabled = false;
  btn.textContent = 'Sign In';
}

async function handleRegister(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Creating account…';

  clearAlerts('register-alerts');

  const data = {
    full_name: form.querySelector('#reg-name').value.trim(),
    username: form.querySelector('#reg-username').value.trim(),
    email: form.querySelector('#reg-email').value.trim(),
    phone: form.querySelector('#reg-phone').value.trim(),
    password: form.querySelector('#reg-password').value,
    confirm_password: form.querySelector('#reg-confirm').value,
  };

  if (data.password !== data.confirm_password) {
    showAlert('register-alerts', 'Passwords do not match.', 'error');
    btn.disabled = false;
    btn.textContent = 'Create Guest Account';
    return;
  }

  try {
    const res = await API.post('register', data);
    if (res.success) {
      State.user = res.user;
      updateNavUser();
      showAlert('register-alerts', res.message, 'success');
      form.reset();
      setTimeout(() => {
        closeAuthModal();
        loadProfile();
        navigateTo('profile');
      }, 1000);
    } else {
      showAlert('register-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('register-alerts', err.message || 'Connection error. Please try again.', 'error');
  }

  btn.disabled = false;
  btn.textContent = 'Create Guest Account';
}

async function handleLogout() {
  try {
    await API.get('logout');
  } catch(e) {}
  State.user = null;
  updateNavUser();
  Toast.info('Logged out successfully.');
  navigateTo('home');
}

/* ── Alert Helpers ────────────────────────────────────────────────────────── */
function showAlert(containerId, message, type = 'error') {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = `<div class="alert alert-${type}"><span>${message}</span></div>`;
}

function clearAlerts(containerId) {
  const container = document.getElementById(containerId);
  if (container) container.innerHTML = '';
}

/* ── Image Helpers ────────────────────────────────────────────────────────── */
function getImageUrl(path) {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  return '/New/' + path.replace(/^\//, '');
}

function imgOrPlaceholder(path, category = '') {
  const url = getImageUrl(path);
  if (url) return `<img src="${url}" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`;
  const icons = { accommodation: '🏠', bikes: '🚲', equipment: '🏄', guides: '🧭', default: '📷' };
  const icon = icons[category] || icons.default;
  return `<div class="card-img-placeholder" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;background:rgba(20,20,26,0.9)">${icon}</div>`;
}

/* ── Home Data ────────────────────────────────────────────────────────────── */
async function loadHomeData() {
  Currency.updateLabels();
  try {
    const [acc, bikes, equip, guides] = await Promise.all([
      API.get('get_accommodations', {}).catch(() => ({ success: false, data: [] })),
      API.get('get_bikes', {}).catch(() => ({ success: false, data: [] })),
      API.get('get_equipment', {}).catch(() => ({ success: false, data: [] })),
      API.get('get_guides', {}).catch(() => ({ success: false, data: [] })),
    ]);

    renderHomeSection('home-accommodations', acc.data?.slice(0, 4) || [], 'accommodation');
    renderHomeSection('home-bikes', bikes.data?.slice(0, 4) || [], 'bikes');
    renderHomeSection('home-equipment', equip.data?.slice(0, 4) || [], 'equipment');
    renderHomeGuides('home-guides', guides.data?.slice(0, 4) || []);
  } catch (e) {
    console.warn('Failed to load home data', e);
  }
}

function renderHomeSection(containerId, items, type) {
  const el = document.getElementById(containerId);
  if (!el) return;
  if (!items || !items.length) {
    el.innerHTML = `<div class="empty-state"><div class="empty-state-icon">${typeIcon(type)}</div><p class="empty-state-text">No ${type} listings available right now.</p></div>`;
    return;
  }
  el.innerHTML = items.map(item => buildListingCard(item, type)).join('');
}

function renderHomeGuides(containerId, guides) {
  const el = document.getElementById(containerId);
  if (!el) return;
  if (!guides || !guides.length) {
    el.innerHTML = `<div class="empty-state"><div class="empty-state-icon">🧭</div><p class="empty-state-text">No guides available right now.</p></div>`;
    return;
  }
  el.innerHTML = guides.map(g => buildGuideCard(g)).join('');
}

/* ── Listings ─────────────────────────────────────────────────────────────── */
async function loadListings(type, filters = {}) {
  const actionMap = {
    accommodation: 'get_accommodations',
    bikes: 'get_bikes',
    equipment: 'get_equipment',
    guides: 'get_guides',
  };

  const container = document.getElementById(`listings-${type}`);
  const countEl = document.getElementById(`listings-count-${type}`);
  if (!container) return;

  container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading listings…</p></div>';

  try {
    const res = await API.get(actionMap[type], filters);
    const items = res.data || [];

    if (countEl) countEl.innerHTML = `Showing <strong>${items.length}</strong> result${items.length !== 1 ? 's' : ''}`;

    if (!items.length) {
      container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">${typeIcon(type)}</div><div class="empty-state-title">No results found</div><p class="empty-state-text">Try adjusting your search filters or clear inputs.</p></div>`;
      return;
    }

    if (type === 'guides') {
      container.innerHTML = `<div class="grid-4">${items.map(g => buildGuideCard(g)).join('')}</div>`;
    } else {
      container.innerHTML = `<div class="grid-4">${items.map(i => buildListingCard(i, type)).join('')}</div>`;
    }
  } catch (e) {
    container.innerHTML = `<div class="alert alert-error">Failed to load ${type} listings. ${e.message || ''}</div>`;
  }
}

function typeIcon(type) {
  const icons = { accommodation: '🏠', bikes: '🚲', equipment: '🏄', guides: '🧭' };
  return icons[type] || '📋';
}

function buildListingCard(item, type) {
  const imgUrl = getImageUrl(item.image);
  const price = parseFloat(item.price || 0);
  const priceLabel = type === 'accommodation'
    ? `${formatCurrency(price)}<span>/night</span>`
    : `${formatCurrency(price)}<span>/${item.time_period || 'day'}</span>`;

  const badge = type === 'accommodation' ? item.type : (item.model || item.type || '');
  const isWish = !!item.is_wishlisted;
  const rating = parseFloat(item.rating || 4.9).toFixed(1);

  return `
    <div class="card" onclick="openDetailModal('${type}', '${item.id}')">
      <div class="card-img-wrap" style="position:relative">
        ${imgUrl ? `<img src="${imgUrl}" alt="${escHtml(item.name)}" onerror="this.src='';this.style.display='none'">` : `<div style="width:100%;height:100%;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;font-size:3rem">${typeIcon(type)}</div>`}
        ${badge ? `<span class="card-badge">${escHtml(badge)}</span>` : ''}
        <span class="card-rating-badge">⭐ ${rating}</span>
        <button class="wishlist-btn ${isWish ? 'active' : ''}" 
          id="wishlist-btn-${item.id}"
          onclick="event.stopPropagation(); toggleWishlist('${type}', '${item.id}')"
          title="${isWish ? 'Remove from wishlist' : 'Add to wishlist'}"
          aria-label="Wishlist">
          <span class="heart-icon">${isWish ? '❤️' : '🤍'}</span>
        </button>
      </div>
      <div class="card-body">
        <div class="card-title">${escHtml(item.name)}</div>
        <div class="card-meta">
          <span class="card-meta-item">📍 ${escHtml(item.location)}</span>
          ${type === 'accommodation' && item.units ? `<span class="card-meta-item">🚪 ${item.units} unit${item.units !== 1 ? 's' : ''}</span>` : ''}
        </div>
        ${item.description ? `<p class="card-desc">${escHtml(item.description)}</p>` : ''}
        <div class="card-footer">
          <div class="card-price">${priceLabel}</div>
          <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); openDetailModal('${type}', '${item.id}')">Book Now</button>
        </div>
      </div>
    </div>`;
}

function buildGuideCard(g) {
  const imgUrl = getImageUrl(g.image || g.avatar);
  const stars = buildStars(parseFloat(g.rating || 5));
  const pricePerDay = g.price ? ` · ${formatCurrency(g.price)}/day` : '';
  const isWish = !!g.is_wishlisted;
  return `
    <div class="guide-card" onclick="openDetailModal('guides', '${g.id}')">
      <div class="guide-card-img-wrap" style="position:relative">
        ${imgUrl ? `<img src="${imgUrl}" alt="${escHtml(g.name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">` : ''}
        <div class="guide-avatar-fallback" style="${imgUrl ? 'display:none' : ''}">🧭</div>
        <button class="wishlist-btn ${isWish ? 'active' : ''}" 
          id="wishlist-btn-${g.id}"
          onclick="event.stopPropagation(); toggleWishlist('guide', '${g.id}')"
          title="${isWish ? 'Remove from wishlist' : 'Add to wishlist'}"
          aria-label="Wishlist" style="top:8px;right:8px">
          <span class="heart-icon">${isWish ? '❤️' : '🤍'}</span>
        </button>
      </div>
      <div class="guide-card-body">
        <div class="guide-name">${escHtml(g.name)}</div>
        ${g.experience ? `<div class="guide-exp">⭐ ${escHtml(g.experience)}${pricePerDay}</div>` : ''}
        ${g.description ? `<p class="guide-desc">${escHtml(g.description)}</p>` : ''}
        <div class="guide-rating">
          <span class="stars">${stars}</span>
          <span class="rating-num">${parseFloat(g.rating || 5).toFixed(1)}</span>
        </div>
        <div class="card-meta-item mb-2">📍 ${escHtml(g.location)}</div>
        <button class="btn btn-primary btn-sm btn-full" onclick="event.stopPropagation(); openDetailModal('guides', '${g.id}')">💳 Book Guide</button>
      </div>
    </div>`;
}

function buildStars(rating) {
  let s = '';
  for (let i = 1; i <= 5; i++) {
    s += i <= Math.round(rating) ? '★' : '☆';
  }
  return s;
}

/* ── Search Handlers ──────────────────────────────────────────────────────── */
function handleAccommodationSearch(e) {
  if (e) e.preventDefault();
  const form = e ? e.target : document.getElementById('search-accommodation');
  const filters = {
    type: form.querySelector('[name="type"]')?.value || '',
    location: form.querySelector('[name="location"]')?.value || '',
    check_in: form.querySelector('[name="check_in"]')?.value || '',
    check_out: form.querySelector('[name="check_out"]')?.value || '',
    start_date: form.querySelector('[name="start_date"]')?.value || form.querySelector('[name="check_in"]')?.value || '',
    end_date: form.querySelector('[name="end_date"]')?.value || form.querySelector('[name="check_out"]')?.value || '',
    min_price: form.querySelector('[name="min_price"]')?.value || '',
    max_price: form.querySelector('[name="max_price"]')?.value || '',
    units: form.querySelector('[name="units"]')?.value || '',
  };
  navigateTo('accommodation');
  setTimeout(() => loadListings('accommodation', filters), 0);
}

function handleBikesSearch(e) {
  if (e) e.preventDefault();
  const form = e ? e.target : document.getElementById('search-bikes');
  const filters = {
    model: form.querySelector('[name="model"]')?.value || '',
    location: form.querySelector('[name="location"]')?.value || '',
    start_date: form.querySelector('[name="start_date"]')?.value || '',
    end_date: form.querySelector('[name="end_date"]')?.value || '',
    min_price: form.querySelector('[name="min_price"]')?.value || '',
    max_price: form.querySelector('[name="max_price"]')?.value || '',
  };
  navigateTo('bikes');
  setTimeout(() => loadListings('bikes', filters), 0);
}

function handleEquipmentSearch(e) {
  if (e) e.preventDefault();
  const form = e ? e.target : document.getElementById('search-equipment');
  const filters = {
    type: form.querySelector('[name="type"]')?.value || '',
    location: form.querySelector('[name="location"]')?.value || '',
    start_date: form.querySelector('[name="start_date"]')?.value || '',
    end_date: form.querySelector('[name="end_date"]')?.value || '',
    min_price: form.querySelector('[name="min_price"]')?.value || '',
    max_price: form.querySelector('[name="max_price"]')?.value || '',
  };
  navigateTo('equipment');
  setTimeout(() => loadListings('equipment', filters), 0);
}

function handleGuidesSearch(e) {
  if (e) e.preventDefault();
  const form = e ? e.target : document.getElementById('search-guides');
  const filters = {
    location: form.querySelector('[name="location"]')?.value || '',
    start_date: form.querySelector('[name="start_date"]')?.value || '',
    end_date: form.querySelector('[name="end_date"]')?.value || '',
    min_rating: form.querySelector('[name="min_rating"]')?.value || '',
  };
  navigateTo('guides');
  setTimeout(() => loadListings('guides', filters), 0);
}

/* ── Detail Modal & Multi-Image Gallery ───────────────────────────────────── */
let currentActiveDetailItem = null;

async function openDetailModal(type, id) {
  const modal = document.getElementById('detail-modal');
  modal?.classList.add('open');
  const body = document.getElementById('detail-modal-body');
  if (body) body.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading details…</p></div>';

  const actionMap = {
    accommodation: 'get_accommodation',
    bikes: 'get_bike',
    equipment: 'get_equipment_item',
    guides: 'get_guide',
  };

  try {
    const res = await API.get(actionMap[type], { id });
    if (!res.success) throw new Error(res.message);
    currentActiveDetailItem = { type, item: res.data };
    renderDetailModal(type, res.data);
  } catch (e) {
    if (body) body.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

function renderDetailModal(type, item) {
  const primaryImg = getImageUrl(item.image || item.avatar);
  
  // Parse multi-image gallery
  let allImages = [];
  try {
    if (item.images) {
      allImages = typeof item.images === 'string' ? JSON.parse(item.images) : item.images;
    }
  } catch(e) {
    allImages = [];
  }
  if (!Array.isArray(allImages)) allImages = [];
  if (primaryImg && !allImages.some(img => getImageUrl(img) === primaryImg)) {
    allImages.unshift(item.image || item.avatar);
  }
  allImages = Array.from(new Set(allImages.filter(Boolean)));

  const isLoggedIn = !!State.user;
  const isGuest = isLoggedIn && State.user.role === 'Guest';

  let bookingForm = '';
  if (!isLoggedIn) {
    bookingForm = `<div class="alert alert-info">Please <button class="btn btn-primary btn-sm" onclick="closeDetailModal();openAuthModal()">Sign In</button> to book.</div>`;
  } else if (!isGuest) {
    bookingForm = `<div class="alert alert-info">Admins manage listings and cannot make bookings. Use a Guest account to book.</div>`;
  } else {
    bookingForm = buildBookingForm(type, item);
  }

  // Booked date ranges badge / availability indicator
  let availBoxHtml = '';
  if (item.booked_ranges && item.booked_ranges.length > 0) {
    availBoxHtml = `
      <div class="availability-status-box" style="margin-bottom:14px;padding:12px 14px;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.25);border-radius:var(--radius-md)">
        <div style="font-size:0.86rem;font-weight:700;color:#f87171;margin-bottom:6px;display:flex;align-items:center;gap:6px">
          <span>⚠️</span> <span>Currently Reserved Dates (Unavailable):</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px">
          ${item.booked_ranges.map(r => `
            <span style="font-size:0.78rem;background:rgba(239,68,68,0.18);border:1px solid rgba(239,68,68,0.35);color:#fca5a5;padding:3px 8px;border-radius:4px;font-weight:600">
              🚫 ${r.start_date} ${r.end_date !== r.start_date ? 'to ' + r.end_date : ''}
            </span>
          `).join('')}
        </div>
        <div style="font-size:0.82rem;color:var(--accent-emerald);font-weight:600">
          ✨ All other dates are completely available! Pick your dates below:
        </div>
      </div>`;
  } else {
    availBoxHtml = `
      <div class="availability-status-box" style="margin-bottom:14px;padding:10px 14px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.25);border-radius:var(--radius-md)">
        <div style="font-size:0.85rem;color:var(--accent-emerald);font-weight:600;display:flex;align-items:center;gap:6px">
          <span>✅</span> <span>Available on all dates! Instant booking &amp; confirmation.</span>
        </div>
      </div>`;
  }

  let metaHtml = '';
  if (type === 'accommodation') {
    metaHtml = `
      <div class="detail-meta-grid">
        <div class="detail-meta-item"><div class="detail-meta-label">Type</div><div class="detail-meta-value">${escHtml(item.type)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Price / Night</div><div class="detail-meta-value" style="color:var(--accent-gold);font-weight:700">${formatCurrency(item.price)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Location</div><div class="detail-meta-value">📍 ${escHtml(item.location)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Available Units</div><div class="detail-meta-value">🚪 ${item.units || 1}</div></div>
        ${item.available_from ? `<div class="detail-meta-item"><div class="detail-meta-label">Available From</div><div class="detail-meta-value">${item.available_from}</div></div>` : ''}
        ${item.available_to ? `<div class="detail-meta-item"><div class="detail-meta-label">Available To</div><div class="detail-meta-value">${item.available_to}</div></div>` : ''}
      </div>`;
  } else if (type === 'bikes') {
    metaHtml = `
      <div class="detail-meta-grid">
        <div class="detail-meta-item"><div class="detail-meta-label">Bike Model</div><div class="detail-meta-value">${escHtml(item.model || item.type)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Price</div><div class="detail-meta-value" style="color:var(--accent-gold);font-weight:700">${formatCurrency(item.price)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Location</div><div class="detail-meta-value">📍 ${escHtml(item.location)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Rental Period</div><div class="detail-meta-value">⏱ ${escHtml(item.time_period || '1 day')}</div></div>
      </div>`;
  } else if (type === 'equipment') {
    metaHtml = `
      <div class="detail-meta-grid">
        <div class="detail-meta-item"><div class="detail-meta-label">Category</div><div class="detail-meta-value">${escHtml(item.type || item.category)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Price</div><div class="detail-meta-value" style="color:var(--accent-gold);font-weight:700">${formatCurrency(item.price)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Location</div><div class="detail-meta-value">📍 ${escHtml(item.location)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Rental Period</div><div class="detail-meta-value">⏱ ${escHtml(item.time_period || '1 day')}</div></div>
      </div>`;
  } else if (type === 'guides') {
    const stars = buildStars(parseFloat(item.rating || 5));
    metaHtml = `
      <div class="detail-meta-grid">
        <div class="detail-meta-item"><div class="detail-meta-label">Location</div><div class="detail-meta-value">📍 ${escHtml(item.location)}</div></div>
        <div class="detail-meta-item"><div class="detail-meta-label">Rating</div><div class="detail-meta-value"><span style="color:var(--accent-yellow)">${stars} ${parseFloat(item.rating || 5).toFixed(1)}</span> (${item.reviews || 10} reviews)</div></div>
        ${item.price ? `<div class="detail-meta-item"><div class="detail-meta-label">Rate / Day</div><div class="detail-meta-value" style="color:var(--accent-gold);font-weight:700">${formatCurrency(item.price)}</div></div>` : ''}
        ${item.languages ? `<div class="detail-meta-item"><div class="detail-meta-label">Languages</div><div class="detail-meta-value">${escHtml(item.languages)}</div></div>` : ''}
        ${item.experience ? `<div class="detail-meta-item detail-meta-grid-span"><div class="detail-meta-label">Experience &amp; Bio</div><div class="detail-meta-value">${escHtml(item.experience)}</div></div>` : ''}
      </div>`;
  }

  // Gallery HTML
  let galleryHtml = '';
  if (allImages.length > 0) {
    const heroSrc = getImageUrl(allImages[0]);
    galleryHtml = `
      <div class="detail-gallery">
        <img src="${heroSrc}" alt="${escHtml(item.name)}" class="detail-gallery-hero" id="detail-hero-img">
        ${allImages.length > 1 ? `
          <div class="detail-gallery-thumbs">
            ${allImages.map((img, idx) => `
              <img src="${getImageUrl(img)}" class="detail-gallery-thumb ${idx === 0 ? 'active' : ''}" 
                onclick="switchGalleryImage(this, '${getImageUrl(img)}')" 
                alt="Thumbnail ${idx + 1}">
            `).join('')}
          </div>` : ''}
      </div>`;
  } else if (primaryImg) {
    galleryHtml = `<img src="${primaryImg}" alt="${escHtml(item.name)}" class="detail-img">`;
  }

  document.getElementById('detail-modal-body').innerHTML = `
    ${galleryHtml}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:8px">
      <h2 style="font-family:'Outfit',sans-serif;font-size:1.6rem;font-weight:800;margin-bottom:0">${escHtml(item.name)}</h2>
      <button class="wishlist-btn ${item.is_wishlisted ? 'active' : ''}" 
        id="wishlist-btn-${item.id}"
        style="position:static;flex-shrink:0"
        onclick="toggleWishlist('${type === 'guides' ? 'guide' : type}', '${item.id}')"
        title="${item.is_wishlisted ? 'Remove from wishlist' : 'Add to wishlist'}"
        aria-label="Wishlist">
        <span class="heart-icon">${item.is_wishlisted ? '❤️' : '🤍'}</span>
      </button>
    </div>
    ${item.description ? `<p style="color:var(--text-secondary);font-size:0.92rem;line-height:1.7;margin-bottom:16px">${escHtml(item.description)}</p>` : ''}
    ${metaHtml}
    <section class="public-reviews" id="public-reviews-list" aria-live="polite">
      <div class="loading"><div class="spinner"></div><p>Loading guest reviews…</p></div>
    </section>
    <div class="booking-section">
      <div class="booking-section-title">📅 Reserve &amp; Pay (Instant Confirmation)</div>
      ${availBoxHtml}
      ${bookingForm}
    </div>`;

  loadItemReviews(type, item.id);
}

async function loadItemReviews(type, itemId) {
  const section = document.getElementById('public-reviews-list');
  if (!section) return;

  const itemType = { accommodation: 'accommodation', bikes: 'bike', bike: 'bike', equipment: 'equipment', guides: 'guide', guide: 'guide' }[type];
  if (!itemType) return;

  try {
    const res = await API.get('get_reviews', { item_type: itemType, item_id: itemId });
    if (!res.success) throw new Error(res.message || 'Could not load reviews.');
    if (section !== document.getElementById('public-reviews-list') || currentActiveDetailItem?.item?.id != itemId) return;

    const reviews = Array.isArray(res.data) ? res.data : [];
    const average = reviews.length
      ? (reviews.reduce((sum, review) => sum + (parseFloat(review.rating) || 0), 0) / reviews.length).toFixed(1)
      : null;
    const summary = reviews.length
      ? `<span class="public-reviews-summary">${reviews.length} review${reviews.length === 1 ? '' : 's'} · ⭐ ${average} / 5</span>`
      : '<span class="public-reviews-summary">No written reviews yet</span>';
    const cards = reviews.length ? reviews.map(review => {
      const reviewer = review.reviewer_name || 'Guest';
      const date = review.created_at ? new Date(String(review.created_at).replace(' ', 'T')).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
      const ratingValue = Math.min(5, Math.max(1, parseFloat(review.rating) || 0));
      const rating = Math.round(ratingValue);
      return `
        <article class="public-review-card">
          <div class="public-review-topline">
            <div class="public-review-author">
              <span class="public-review-avatar" aria-hidden="true">${escHtml(reviewer.charAt(0).toUpperCase())}</span>
              <div class="public-review-byline">
                <strong>${escHtml(reviewer)}</strong>
                ${date ? `<time>${escHtml(date)}</time>` : ''}
              </div>
            </div>
            <span class="public-review-stars" aria-label="${ratingValue.toFixed(1)} out of 5 stars">${buildStars(rating)}</span>
          </div>
          <p class="public-review-text">${escHtml(review.review_text || '')}</p>
        </article>`;
    }).join('') : '<p class="public-reviews-empty">Reviews from verified bookings will appear here.</p>';

    section.innerHTML = `
      <div class="public-reviews-heading">
        <div>
          <h3>Guest reviews</h3>
          ${summary}
        </div>
      </div>
      ${reviews.length ? `<div class="public-review-list">${cards}</div>` : cards}`;
  } catch (error) {
    if (section === document.getElementById('public-reviews-list')) {
      section.innerHTML = '<p class="public-reviews-empty">Guest reviews are temporarily unavailable.</p>';
    }
  }
}

function switchGalleryImage(thumbEl, src) {
  const hero = document.getElementById('detail-hero-img');
  if (hero) hero.src = src;
  document.querySelectorAll('.detail-gallery-thumb').forEach(t => t.classList.remove('active'));
  if (thumbEl) thumbEl.classList.add('active');
}

function buildBookingForm(type, item) {
  const today = new Date().toISOString().split('T')[0];
  const price = parseFloat(item.price) || 0;

  if (type === 'accommodation') {
    return `
      <form onsubmit="submitBooking(event, 'accommodation', '${item.id}', ${price})">
        <div class="form-row mb-2">
          <div class="form-group mb-0">
            <label>Check-in Date *</label>
            <input type="date" name="start_date" class="form-control" required min="${today}">
          </div>
          <div class="form-group mb-0">
            <label>Check-out Date *</label>
            <input type="date" name="end_date" class="form-control" required min="${today}">
          </div>
        </div>
        <div class="form-group">
          <label>Units Required</label>
          <input type="number" name="units" class="form-control" value="1" min="1" max="${item.units || 10}">
        </div>
        <div id="booking-msg-${item.id}"></div>
        <button type="submit" class="btn btn-primary btn-full mt-2">💳 Proceed to Payment Gateway</button>
      </form>`;
  } else if (type === 'bikes') {
    return `
      <form onsubmit="submitBooking(event, 'bike', '${item.id}', ${price})">
        <div class="form-row mb-2">
          <div class="form-group mb-0">
            <label>Start Date *</label>
            <input type="date" name="start_date" id="bike-start-${item.id}" class="form-control" required min="${today}" onchange="updateBikeLiveCalculation('${item.id}', ${price})">
          </div>
          <div class="form-group mb-0">
            <label>End Date *</label>
            <input type="date" name="end_date" id="bike-end-${item.id}" class="form-control" required min="${today}" onchange="updateBikeLiveCalculation('${item.id}', ${price})">
          </div>
        </div>
        <input type="hidden" name="time_period" id="bike-period-${item.id}" value="1 day">
        <div class="calc-summary-card" id="bike-calc-box-${item.id}">
          <div class="calc-summary-row">
            <span>Daily Rate:</span>
            <strong>${formatCurrency(price)}/day</strong>
          </div>
          <div class="calc-summary-row">
            <span>Calculated Duration:</span>
            <strong id="bike-days-label-${item.id}" class="calc-badge">1 Day</strong>
          </div>
          <div class="calc-summary-row total">
            <span>Calculated Total:</span>
            <strong id="bike-total-label-${item.id}" class="calc-price">${formatCurrency(price)}</strong>
          </div>
        </div>
        <div id="booking-msg-${item.id}"></div>
        <button type="submit" class="btn btn-primary btn-full mt-2">💳 Proceed to Payment Gateway</button>
      </form>`;
  } else if (type === 'equipment') {
    return `
      <form onsubmit="submitBooking(event, 'equipment', '${item.id}', ${price})">
        <div class="form-row mb-2">
          <div class="form-group mb-0">
            <label>Start Date *</label>
            <input type="date" name="start_date" id="equip-start-${item.id}" class="form-control" required min="${today}" onchange="updateEquipmentLiveCalculation('${item.id}', ${price})">
          </div>
          <div class="form-group mb-0">
            <label>End Date *</label>
            <input type="date" name="end_date" id="equip-end-${item.id}" class="form-control" required min="${today}" onchange="updateEquipmentLiveCalculation('${item.id}', ${price})">
          </div>
        </div>
        <input type="hidden" name="time_period" id="equip-period-${item.id}" value="1 day">
        <div class="calc-summary-card" id="equip-calc-box-${item.id}">
          <div class="calc-summary-row">
            <span>Daily Rate:</span>
            <strong>${formatCurrency(price)}/day</strong>
          </div>
          <div class="calc-summary-row">
            <span>Calculated Duration:</span>
            <strong id="equip-days-label-${item.id}" class="calc-badge">Select dates above</strong>
          </div>
          <div class="calc-summary-row total">
            <span>Calculated Total:</span>
            <strong id="equip-total-label-${item.id}" class="calc-price">${formatCurrency(price)}</strong>
          </div>
        </div>
        <div id="booking-msg-${item.id}"></div>
        <button type="submit" class="btn btn-primary btn-full mt-2">💳 Proceed to Payment Gateway</button>
      </form>`;
  } else if (type === 'guides') {
    return `
      <form onsubmit="submitBooking(event, 'guide', '${item.id}', ${price})">
        <div class="form-row mb-2">
          <div class="form-group mb-0">
            <label>Tour Start Date *</label>
            <input type="date" name="start_date" class="form-control" required min="${today}">
          </div>
          <div class="form-group mb-0">
            <label>Tour End Date *</label>
            <input type="date" name="end_date" class="form-control" required min="${today}">
          </div>
        </div>
        <div style="margin-bottom:12px;padding:10px 14px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-md);font-size:0.88rem;color:var(--accent-yellow)">
          💰 Daily Rate: <strong>${formatCurrency(price)}/day</strong> — Payment gateway will securely process your booking.
        </div>
        <div id="booking-msg-${item.id}"></div>
        <button type="submit" class="btn btn-primary btn-full mt-2">💳 Book &amp; Pay Guide with PayHere</button>
      </form>`;
  }
  return '';
}

function updateBikeLiveCalculation(itemId, basePrice) {
  const startEl = document.getElementById(`bike-start-${itemId}`);
  const endEl = document.getElementById(`bike-end-${itemId}`);
  const periodEl = document.getElementById(`bike-period-${itemId}`);
  const daysLabel = document.getElementById(`bike-days-label-${itemId}`);
  const totalLabel = document.getElementById(`bike-total-label-${itemId}`);

  if (!startEl) return;
  const s = startEl.value;
  const e = (endEl && endEl.value) ? endEl.value : s;

  let days = 1;
  if (s && e) {
    const diff = (new Date(e) - new Date(s)) / (1000 * 60 * 60 * 24);
    days = Math.max(1, Math.round(diff) + 1);
  }
  const total = basePrice * days;
  const periodStr = `${days} day${days > 1 ? 's' : ''}`;

  if (periodEl) periodEl.value = periodStr;
  if (daysLabel) daysLabel.textContent = `${days} Day${days > 1 ? 's' : ''}`;
  if (totalLabel) totalLabel.textContent = formatCurrency(total);
}

function updateEquipmentLiveCalculation(itemId, basePrice) {
  const startEl = document.getElementById(`equip-start-${itemId}`);
  const endEl = document.getElementById(`equip-end-${itemId}`);
  const periodEl = document.getElementById(`equip-period-${itemId}`);
  const daysLabel = document.getElementById(`equip-days-label-${itemId}`);
  const totalLabel = document.getElementById(`equip-total-label-${itemId}`);

  if (!startEl) return;
  const s = startEl.value;
  const e = (endEl && endEl.value) ? endEl.value : s;

  let days = 1;
  if (s && e) {
    const diff = (new Date(e) - new Date(s)) / (1000 * 60 * 60 * 24);
    days = Math.max(1, Math.round(diff) + 1);
  }
  const total = basePrice * days;
  const periodStr = `${days} day${days > 1 ? 's' : ''}`;

  if (periodEl) periodEl.value = periodStr;
  if (daysLabel) daysLabel.textContent = `${days} Day${days > 1 ? 's' : ''}`;
  if (totalLabel) totalLabel.textContent = formatCurrency(total);
}

async function submitBooking(e, itemType, itemId, basePrice) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Processing reservation…';

  const msgEl = document.getElementById(`booking-msg-${itemId}`);
  if (msgEl) msgEl.innerHTML = '';

  const startDate = form.querySelector('[name="start_date"]')?.value;
  const endDate = form.querySelector('[name="end_date"]')?.value;
  const timePeriod = form.querySelector('[name="time_period"]')?.value || '';
  const units = parseInt(form.querySelector('[name="units"]')?.value || 1);

  if (startDate && endDate && endDate < startDate) {
    if (msgEl) msgEl.innerHTML = '<div class="alert alert-error">End date must be on or after start date.</div>';
    btn.disabled = false;
    btn.textContent = '💳 Proceed to Payment';
    return;
  }

  // Real-time date overlap check
  if (currentActiveDetailItem && currentActiveDetailItem.item && currentActiveDetailItem.item.booked_ranges && startDate) {
    const s = startDate;
    const e = endDate || startDate;
    const conflict = currentActiveDetailItem.item.booked_ranges.find(r => {
      const rs = r.start_date;
      const re = r.end_date || r.start_date;
      return s <= re && e >= rs;
    });
    if (conflict) {
      if (msgEl) msgEl.innerHTML = `<div class="alert alert-error">⚠️ This item is already reserved from <strong>${conflict.start_date} ${conflict.end_date !== conflict.start_date ? 'to ' + conflict.end_date : ''}</strong>. All other dates are available!</div>`;
      btn.disabled = false;
      btn.textContent = '💳 Proceed to Payment';
      return;
    }
  }

  // Calculate total price
  let days = 1;
  if (startDate && endDate) {
    const diff = (new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24);
    days = Math.max(1, Math.round(diff) + (itemType === 'guide' || itemType === 'bike' ? 1 : 0));
  } else if (startDate) {
    days = 1;
  }
  const calcPeriod = (itemType === 'bike') ? `${days} day${days > 1 ? 's' : ''}` : (timePeriod || `${days} day${days > 1 ? 's' : ''}`);
  const totalPrice = basePrice * days * units;

  try {
    const res = await API.post('create_booking', {
      item_type: itemType,
      item_id: itemId,
      start_date: startDate,
      end_date: endDate,
      time_period: calcPeriod,
      units,
      price: totalPrice.toFixed(2),
    });

    if (res.success) {
      if (msgEl) msgEl.innerHTML = `<div class="alert alert-success">${res.message} Total: ${formatCurrency(totalPrice)}</div>`;
      form.reset();
      Toast.success('Reservation created! Opening payment gateway…');
      
      // Refresh catalog views
      if (State.currentPage === 'home') loadHomeData();
      else loadListings(State.currentPage);

      setTimeout(() => {
        closeDetailModal();
        if (res.payhere_data) {
          openPayHereCheckout(res.booking_id, res.payhere_data);
        } else {
          navigateTo('profile');
          switchProfilePanel('bookings');
        }
      }, 700);
    } else {
      if (msgEl) msgEl.innerHTML = `<div class="alert alert-error">${res.message}</div>`;
    }
  } catch (err) {
    if (msgEl) msgEl.innerHTML = `<div class="alert alert-error">${err.message || 'Connection error. Please try again.'}</div>`;
  }

  btn.disabled = false;
  btn.textContent = '💳 Proceed to Payment';
}

function closeDetailModal() {
  document.getElementById('detail-modal')?.classList.remove('open');
}

/* ── Profile System ───────────────────────────────────────────────────────── */
async function loadProfile() {
  Currency.updateLabels();
  if (!State.user) {
    renderGuestPromptProfile();
    return;
  }

  try {
    const res = await API.get('me');
    if (!res.success || !res.loggedIn) {
      State.user = null;
      updateNavUser();
      renderGuestPromptProfile();
      return;
    }
    State.user = res.user;
    updateNavUser();

    if (State.user.role === 'Admin') {
      renderAdminProfile();
    } else {
      renderGuestProfile();
    }
  } catch (e) {
    renderGuestPromptProfile();
  }
}

function renderGuestPromptProfile() {
  document.getElementById('profile-content').innerHTML = `
    <div style="text-align:center;padding:80px 24px">
      <div style="font-size:4rem;margin-bottom:24px">🔐</div>
      <h2 style="font-family:'Outfit',sans-serif;font-size:1.8rem;font-weight:800;margin-bottom:12px">Sign In to Continue</h2>
      <p style="color:var(--text-secondary);margin-bottom:28px">Access your bookings, saved wishlists, and manage your account.</p>
      <button class="btn btn-primary btn-lg" onclick="openAuthModal()">Sign In / Register</button>
    </div>`;
}

function renderGuestProfile() {
  const u = State.user;
  const initial = (u.full_name || u.username || 'G').charAt(0).toUpperCase();

  document.getElementById('profile-content').innerHTML = `
    <div class="profile-hero">
      <div class="container">
        <div class="profile-hero-inner">
          <div class="profile-avatar">${initial}</div>
          <div>
            <div class="profile-info-name">${escHtml(u.full_name || u.username)}</div>
            <div class="profile-info-username">@${escHtml(u.username)}</div>
            <div class="role-badge role-guest">👤 Guest Traveler</div>
          </div>
        </div>
      </div>
    </div>
    <div class="profile-content">
      <div class="container">
        <div class="profile-grid">
          <div class="profile-sidebar">
            <button class="profile-nav-btn active" id="pnav-account" onclick="switchProfilePanel('account')">👤 Account Information</button>
            <button class="profile-nav-btn" id="pnav-bookings" onclick="switchProfilePanel('bookings')">📅 My Bookings</button>
            <button class="profile-nav-btn" id="pnav-wishlist" onclick="switchProfilePanel('wishlist')">❤️ My Wishlist</button>
            <hr style="border-color:var(--border);margin:8px 0">
            <button class="profile-nav-btn" onclick="openAuthModal(); switchAuthTab('login')">🔄 Switch Account</button>
            <button class="profile-nav-btn danger" onclick="handleLogout()">🚪 Sign Out</button>
          </div>
          <div class="profile-main">
            <div class="profile-panel active" id="panel-account">
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)">
                <div class="panel-title" style="margin:0;border:0;padding:0">Account Information</div>
                <button class="btn btn-primary btn-sm" onclick="openEditProfileModal()">✏️ Edit Profile</button>
              </div>
              <div class="info-card">
                <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">${escHtml(u.full_name)}</span></div>
                <div class="info-row"><span class="info-label">Username</span><span class="info-value">@${escHtml(u.username)}</span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value">${escHtml(u.email)}</span></div>
                <div class="info-row"><span class="info-label">Phone</span><span class="info-value">${escHtml(u.phone || 'Not provided')}</span></div>
                <div class="info-row"><span class="info-label">Account Role</span><span class="info-value"><span class="role-badge role-guest">Guest</span></span></div>
              </div>
            </div>
            <div class="profile-panel" id="panel-bookings">
              <div class="panel-title">📅 My Bookings &amp; Reservations (Divided by Month)</div>
              <div id="guest-bookings-container"><div class="loading"><div class="spinner"></div><p>Loading bookings…</p></div></div>
            </div>
            <div class="profile-panel" id="panel-wishlist">
              <div class="panel-title" style="display:flex;align-items:center;justify-content:space-between">
                <span>❤️ My Saved Items</span>
                <span style="font-size:0.85rem;color:var(--text-secondary);font-weight:normal" id="wishlist-count"></span>
              </div>
              <div id="guest-wishlist-container"><div class="loading"><div class="spinner"></div><p>Loading wishlist…</p></div></div>
            </div>
          </div>
        </div>
      </div>
    </div>`;
}

function renderAdminProfile() {
  const u = State.user;
  const initial = (u.full_name || u.username || 'A').charAt(0).toUpperCase();

  document.getElementById('profile-content').innerHTML = `
    <div class="profile-hero">
      <div class="container">
        <div class="profile-hero-inner">
          <div class="profile-avatar" style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#0a0b0e">${initial}</div>
          <div>
            <div class="profile-info-name">${escHtml(u.full_name || u.username)}</div>
            <div class="profile-info-username">@${escHtml(u.username)}</div>
            <div class="role-badge role-admin">👑 Administrator</div>
          </div>
        </div>
      </div>
    </div>
    <div class="profile-content">
      <div class="container">
        <div class="profile-grid">
          <div class="profile-sidebar">
            <button class="profile-nav-btn active" id="pnav-account" onclick="switchProfilePanel('account')">👤 Account Info</button>
            <button class="profile-nav-btn" id="pnav-add-item" onclick="switchProfilePanel('add-item')">➕ Insert New Item</button>
            <button class="profile-nav-btn" id="pnav-my-items" onclick="switchProfilePanel('my-items')">📦 Manage My Items</button>
            <button class="profile-nav-btn" id="pnav-received" onclick="switchProfilePanel('received')">📨 Received Bookings</button>
            <button class="profile-nav-btn" id="pnav-create-admin" onclick="switchProfilePanel('create-admin')">👑 Create Another Admin</button>
            <hr style="border-color:var(--border);margin:8px 0">
            <button class="profile-nav-btn danger" onclick="handleLogout()">🚪 Sign Out</button>
          </div>
          <div class="profile-main">
            ${buildAdminAccountPanel(u)}
            ${buildAdminAddItemPanel()}
            ${buildAdminMyItemsPanel()}
            ${buildAdminReceivedPanel()}
            ${buildAdminCreateAdminPanel()}
          </div>
        </div>
      </div>
    </div>`;
}

function buildAdminAccountPanel(u) {
  return `
    <div class="profile-panel active" id="panel-account">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)">
        <div class="panel-title" style="margin:0;border:0;padding:0">Account Information</div>
        <button class="btn btn-primary btn-sm" onclick="openEditProfileModal()">✏️ Edit Profile Details</button>
      </div>
      <div class="info-card">
        <div class="info-row"><span class="info-label">Full Name</span><span class="info-value">${escHtml(u.full_name)}</span></div>
        <div class="info-row"><span class="info-label">Username</span><span class="info-value">@${escHtml(u.username)}</span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-value">${escHtml(u.email)}</span></div>
        <div class="info-row"><span class="info-label">Phone</span><span class="info-value">${escHtml(u.phone || 'Not provided')} <span style="font-size:0.75rem;color:var(--accent-yellow)">(shown to booked guests)</span></span></div>
        <div class="info-row"><span class="info-label">Bank Details</span><span class="info-value">${escHtml(u.bank_details || 'Not provided')}</span></div>
        <div class="info-row"><span class="info-label">Role</span><span class="info-value"><span class="role-badge role-admin">👑 Admin</span></span></div>
      </div>
    </div>`;
}

function buildAdminAddItemPanel() {
  return `
    <div class="profile-panel" id="panel-add-item">
      <div class="panel-title">➕ Insert New Item (Multi-Image Support)</div>
      <div class="admin-form-tabs">
        <button class="admin-form-tab active" onclick="switchAdminForm('accommodation')">🏠 Accommodation</button>
        <button class="admin-form-tab" onclick="switchAdminForm('bike')">🚲 Bike</button>
        <button class="admin-form-tab" onclick="switchAdminForm('equipment')">🏄 Equipment</button>
        <button class="admin-form-tab" onclick="switchAdminForm('guide')">🧭 Guide</button>
      </div>
      <div id="add-item-alerts"></div>

      <!-- Accommodation Form -->
      <form id="admin-form-accommodation" class="admin-form active" onsubmit="submitAddItem(event, 'add_accommodation')">
        <div class="form-row">
          <div class="form-group"><label>Name *</label><input name="name" class="form-control" required placeholder="e.g. Royal Oceanfront Luxury Villa"></div>
          <div class="form-group"><label>Type *</label>
            <select name="type" class="form-control">
              <option value="Villa">Villa</option>
              <option value="Apartment">Apartment</option>
              <option value="Room">Room</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Detailed description of the stay…"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Location *</label><input name="location" class="form-control" required placeholder="Mirissa, Sri Lanka"></div>
          <div class="form-group"><label>Price per Night (USD $) *</label><input type="number" name="price" class="form-control" required min="1" step="0.01" placeholder="250.00"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Available Units</label><input type="number" name="units" class="form-control" value="1" min="1"></div>
          <div class="form-group"><label>Available From</label><input type="date" name="available_from" class="form-control"></div>
        </div>
        <div class="form-group">
          <label>Photos (Select several images) *</label>
          <div class="img-upload-area">
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" onchange="previewMultipleImages(this, 'preview-acc-multi')">
            <div class="img-upload-icon">🖼️</div>
            <div class="img-upload-text">Click or drag multiple photos (JPG, PNG, WEBP — up to 8MB each)</div>
            <div id="preview-acc-multi" class="multi-img-preview-row"></div>
          </div>
        </div>
        <div class="form-group">
          <label>Or Image URLs / Paths (optional, one per line)</label>
          <textarea name="image_urls" class="form-control" rows="2" placeholder="image/accommodation/acc-villa-1.jpg&#10;https://example.com/photo2.jpg"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">➕ Publish Accommodation</button>
      </form>

      <!-- Bike Form -->
      <form id="admin-form-bike" class="admin-form" onsubmit="submitAddItem(event, 'add_bike')">
        <div class="form-row">
          <div class="form-group"><label>Bike Name *</label><input name="name" class="form-control" required placeholder="e.g. Trek Marlin 8 Mountain Bike"></div>
          <div class="form-group"><label>Bike Model / Category *</label>
            <select name="model" class="form-control">
              <option value="Mountain Bike">Mountain Bike</option>
              <option value="Road Bike">Road Bike</option>
              <option value="City Bike">City Bike</option>
              <option value="Electric Bike">Electric Bike</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Describe the bike specs, gear, suspension…"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Location *</label><input name="location" class="form-control" required placeholder="Ella Highlands"></div>
          <div class="form-group"><label>Price (USD $) *</label><input type="number" name="price" class="form-control" required min="1" step="0.01" placeholder="25.00"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Rental Period</label>
            <select name="time_period" class="form-control">
              <option value="1 hour">1 Hour</option>
              <option value="4 hours">4 Hours</option>
              <option value="1 day" selected>1 Day</option>
              <option value="2 days">2 Days</option>
              <option value="1 week">1 Week</option>
            </select>
          </div>
          <div class="form-group"><label>Available From</label><input type="date" name="available_from" class="form-control"></div>
        </div>
        <div class="form-group">
          <label>Photos (Select several images) *</label>
          <div class="img-upload-area">
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" onchange="previewMultipleImages(this, 'preview-bike-multi')">
            <div class="img-upload-icon">🚲</div>
            <div class="img-upload-text">Click to upload multiple bike photos (JPG, PNG, WEBP)</div>
            <div id="preview-bike-multi" class="multi-img-preview-row"></div>
          </div>
        </div>
        <div class="form-group">
          <label>Or Image URLs / Paths (optional, one per line)</label>
          <textarea name="image_urls" class="form-control" rows="2" placeholder="image/bikes/bike-mtn-1.jpg"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">➕ Publish Bike</button>
      </form>

      <!-- Equipment Form -->
      <form id="admin-form-equipment" class="admin-form" onsubmit="submitAddItem(event, 'add_equipment')">
        <div class="form-row">
          <div class="form-group"><label>Equipment Name *</label><input name="name" class="form-control" required placeholder="e.g. Pro Epoxy 7ft Surfboard"></div>
          <div class="form-group"><label>Category *</label>
            <select name="type" class="form-control">
              <option value="Surfboard">Surfboard</option>
              <option value="Skateboard">Skateboard</option>
              <option value="Boat">Boat</option>
              <option value="Kayak">Kayak</option>
              <option value="Snorkeling Gear">Snorkeling Gear</option>
              <option value="Camping Gear">Camping Gear</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Describe this equipment, size, included accessories…"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Location *</label><input name="location" class="form-control" required placeholder="Weligama Bay"></div>
          <div class="form-group"><label>Rental Price (USD $) *</label><input type="number" name="price" class="form-control" required min="1" step="0.01" placeholder="15.00"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Rental Period</label>
            <select name="time_period" class="form-control">
              <option value="1 hour">1 Hour</option>
              <option value="4 hours">4 Hours</option>
              <option value="1 day" selected>1 Day</option>
              <option value="2 days">2 Days</option>
              <option value="1 week">1 Week</option>
            </select>
          </div>
          <div class="form-group"><label>Available From</label><input type="date" name="available_from" class="form-control"></div>
        </div>
        <div class="form-group">
          <label>Photos (Select several images) *</label>
          <div class="img-upload-area">
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" onchange="previewMultipleImages(this, 'preview-eq-multi')">
            <div class="img-upload-icon">🏄</div>
            <div class="img-upload-text">Click to upload multiple equipment photos (JPG, PNG, WEBP)</div>
            <div id="preview-eq-multi" class="multi-img-preview-row"></div>
          </div>
        </div>
        <div class="form-group">
          <label>Or Image URLs / Paths (optional, one per line)</label>
          <textarea name="image_urls" class="form-control" rows="2" placeholder="image/equipment/eq-surf-1.jpg"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">➕ Publish Equipment</button>
      </form>

      <!-- Guide Form -->
      <form id="admin-form-guide" class="admin-form" onsubmit="submitAddItem(event, 'add_guide')">
        <div class="form-row">
          <div class="form-group"><label>Guide Name *</label><input name="name" class="form-control" required placeholder="Full name of guide"></div>
          <div class="form-group"><label>Location *</label><input name="location" class="form-control" required placeholder="Yala & Central Highlands"></div>
        </div>
        <div class="form-group"><label>Professional Bio / Specialties</label><textarea name="description" class="form-control" rows="3" placeholder="Describe the guide's expertise, tours offered…"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Experience / Credentials</label><input name="experience" class="form-control" placeholder="10+ years certified wildlife naturalist"></div>
          <div class="form-group"><label>Rate per Day (USD $) *</label><input type="number" name="price" class="form-control" value="45.00" min="1" step="0.01"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Languages</label><input name="languages" class="form-control" value="English, Sinhala" placeholder="English, French, German"></div>
          <div class="form-group"><label>Rating (1–5)</label><input type="number" name="rating" class="form-control" value="5.0" min="1" max="5" step="0.1"></div>
        </div>
        <div class="form-group">
          <label>Photos (Select several images) *</label>
          <div class="img-upload-area">
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" onchange="previewMultipleImages(this, 'preview-guide-multi')">
            <div class="img-upload-icon">🧭</div>
            <div class="img-upload-text">Click to upload guide portraits & tour action photos</div>
            <div id="preview-guide-multi" class="multi-img-preview-row"></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">➕ Register Tour Guide</button>
      </form>
    </div>`;
}

function previewMultipleImages(input, targetContainerId) {
  const container = document.getElementById(targetContainerId);
  if (!container) return;
  container.innerHTML = '';
  if (input.files && input.files.length > 0) {
    Array.from(input.files).forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const item = document.createElement('div');
        item.className = 'multi-img-preview-item';
        item.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        container.appendChild(item);
      };
      reader.readAsDataURL(file);
    });
  }
}

function buildAdminMyItemsPanel() {
  return `
    <div class="profile-panel" id="panel-my-items">
      <div class="panel-title">📦 Manage My Items &amp; Photos</div>
      <div class="tabs mb-3">
        <button class="tab-btn active" onclick="switchMyItemsTab('my-accommodations')">🏠 Accommodations</button>
        <button class="tab-btn" onclick="switchMyItemsTab('my-bikes')">🚲 Bikes</button>
        <button class="tab-btn" onclick="switchMyItemsTab('my-equipment')">🏄 Equipment</button>
        <button class="tab-btn" onclick="switchMyItemsTab('my-guides')">🧭 Guides</button>
      </div>
      <div id="my-items-container">
        <div class="loading"><div class="spinner"></div><p>Loading your catalog items…</p></div>
      </div>
    </div>`;
}

function buildAdminReceivedPanel() {
  return `
    <div class="profile-panel" id="panel-received">
      <div class="panel-title">📨 Received Bookings &amp; Earnings Summary (Divided by Month)</div>
      
      <!-- Earnings Summary Cards -->
      <div id="received-summary"></div>

      <!-- Filters -->
      <div class="received-filters">
        <div class="received-filter-field">
          <label>From Date</label>
          <input type="date" id="rf-date-from" class="form-control" style="padding:8px 12px;font-size:0.85rem">
        </div>
        <div class="received-filter-field">
          <label>To Date</label>
          <input type="date" id="rf-date-to" class="form-control" style="padding:8px 12px;font-size:0.85rem">
        </div>
        <div class="received-filter-field">
          <label>Item Type</label>
          <select id="rf-item-type" class="form-control" style="padding:8px 12px;font-size:0.85rem">
            <option value="">All Types</option>
            <option value="accommodation">Accommodation</option>
            <option value="bike">Bike</option>
            <option value="equipment">Equipment</option>
            <option value="guide">Guide</option>
          </select>
        </div>
        <div class="received-filter-field">
          <label>Payment / Booking Status</label>
          <select id="rf-status" class="form-control" style="padding:8px 12px;font-size:0.85rem">
            <option value="">All Statuses</option>
            <option value="paid">Paid (PayHere)</option>
            <option value="unpaid">Unpaid / Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div style="flex:0;min-width:auto;align-self:flex-end">
          <button class="btn btn-primary btn-sm" onclick="applyReceivedFilters()">🔍 Filter</button>
          <button class="btn btn-sm" style="background:rgba(255,255,255,0.06);border:1px solid var(--border);margin-left:4px" onclick="clearReceivedFilters()">↺ Reset</button>
        </div>
      </div>

      <div id="received-bookings-container">
        <div class="loading"><div class="spinner"></div><p>Loading received bookings…</p></div>
      </div>
    </div>`;
}

function applyReceivedFilters() {
  const filters = {
    date_from: document.getElementById('rf-date-from')?.value || '',
    date_to: document.getElementById('rf-date-to')?.value || '',
    item_type: document.getElementById('rf-item-type')?.value || '',
    status: document.getElementById('rf-status')?.value || '',
  };
  loadReceivedBookings(filters);
}

function clearReceivedFilters() {
  ['rf-date-from','rf-date-to'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  ['rf-item-type','rf-status'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  loadReceivedBookings();
}

function buildAdminCreateAdminPanel() {
  return `
    <div class="profile-panel" id="panel-create-admin">
      <div class="panel-title">👑 Create Another Administrator</div>
      <div id="create-admin-alerts"></div>
      <form id="create-admin-form" onsubmit="handleCreateAdmin(event)">
        <div class="form-row">
          <div class="form-group"><label>Full Name *</label><input name="full_name" class="form-control" required placeholder="Administrator Name"></div>
          <div class="form-group"><label>Username *</label><input name="username" class="form-control" required placeholder="admin2"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required placeholder="admin2@tourguide.lk"></div>
          <div class="form-group"><label>Bank Details *</label><input name="bank_details" class="form-control" required placeholder="Bank Name &amp; Acc Number"></div>
        </div>
        <div class="form-group"><label>Phone Number (shown to guests)</label><input name="phone" class="form-control" placeholder="+94 77 000 0000"></div>
        <div class="form-row">
          <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required placeholder="min. 6 characters"></div>
          <div class="form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password"></div>
        </div>
        <button type="submit" class="btn btn-primary">Create Admin Account</button>
      </form>
    </div>`;
}

/* ── Profile Panel Switching ──────────────────────────────────────────────── */
function switchProfilePanel(panel) {
  document.querySelectorAll('.profile-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.profile-nav-btn').forEach(b => b.classList.remove('active'));

  const panelEl = document.getElementById(`panel-${panel}`);
  const navEl = document.getElementById(`pnav-${panel}`);
  if (panelEl) panelEl.classList.add('active');
  if (navEl) navEl.classList.add('active');

  // Load panel data
  if (panel === 'bookings') loadGuestBookings();
  if (panel === 'wishlist') loadGuestWishlist();
  if (panel === 'my-items') loadMyItems();
  if (panel === 'received') loadReceivedBookings();
}

function switchAdminForm(type) {
  document.querySelectorAll('.admin-form').forEach(f => f.classList.remove('active'));
  document.querySelectorAll('.admin-form-tab').forEach(t => t.classList.remove('active'));

  const form = document.getElementById(`admin-form-${type}`);
  if (form) form.classList.add('active');

  const tabs = document.querySelectorAll('.admin-form-tab');
  const typeMap = { accommodation: 0, bike: 1, equipment: 2, guide: 3 };
  if (tabs[typeMap[type]]) tabs[typeMap[type]].classList.add('active');

  clearAlerts('add-item-alerts');
}

async function submitAddItem(e, action) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  const origText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Saving…';

  clearAlerts('add-item-alerts');

  try {
    const res = await API.postForm(action, form);
    if (res.success) {
      showAlert('add-item-alerts', res.message, 'success');
      form.reset();
      form.querySelectorAll('.multi-img-preview-row').forEach(p => { p.innerHTML = ''; });
      Toast.success(res.message);
    } else {
      showAlert('add-item-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('add-item-alerts', err.message || 'Connection error.', 'error');
  }

  btn.disabled = false;
  btn.textContent = origText;
}

/* ── My Items Tab Switching & Management ──────────────────────────────────── */
let myItemsData = null;
let currentMyItemsTab = 'my-accommodations';

function switchMyItemsTab(tab) {
  currentMyItemsTab = tab;
  document.querySelectorAll('#panel-my-items .tab-btn').forEach((b, i) => {
    const tabs = ['my-accommodations', 'my-bikes', 'my-equipment', 'my-guides'];
    b.classList.toggle('active', tabs[i] === tab);
  });
  if (myItemsData) renderMyItemsTab(tab);
}

async function loadMyItems() {
  const container = document.getElementById('my-items-container');
  if (!container) return;
  container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading your catalog items…</p></div>';

  try {
    const res = await API.get('my_items');
    if (!res.success) throw new Error(res.message || 'Failed to load items');
    myItemsData = res.data || { accommodations: [], bikes: [], equipment: [], guides: [] };
    renderMyItemsTab(currentMyItemsTab);
  } catch (e) {
    container.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

function renderMyItemsTab(tab) {
  const container = document.getElementById('my-items-container');
  if (!container || !myItemsData) return;

  const tabData = {
    'my-accommodations': { items: myItemsData.accommodations || [], type: 'accommodation', icon: '🏠' },
    'my-bikes': { items: myItemsData.bikes || [], type: 'bike', icon: '🚲' },
    'my-equipment': { items: myItemsData.equipment || [], type: 'equipment', icon: '🏄' },
    'my-guides': { items: myItemsData.guides || [], type: 'guide', icon: '🧭' },
  }[tab];

  if (!tabData) return;
  const { items, type, icon } = tabData;

  if (!items || !items.length) {
    container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">${icon}</div><div class="empty-state-title">No ${type} items yet</div><p class="empty-state-text">Add your first item using the "Insert New Item" tab above.</p></div>`;
    return;
  }

  container.innerHTML = items.map(item => buildAdminItemRow(item, type)).join('');
  // Attach edit button click handlers (registry-safe — no JSON in onclick attrs)
  items.forEach(item => {
    const key = `${type}-${item.id}`;
    _editItemRegistry[key] = item;
    const btn = document.getElementById(`edit-btn-${type}-${item.id}`);
    if (btn) btn.addEventListener('click', () => openEditItemModal(type, _editItemRegistry[key]));
  });
}

// Registry: maps "type-id" -> item object (avoids passing JSON through onclick attrs)
const _editItemRegistry = {};

function buildAdminItemRow(item, type) {
  const imgUrl = getImageUrl(item.image);
  const deleteAction = {
    accommodation: 'delete_accommodation',
    bike: 'delete_bike',
    equipment: 'delete_equipment',
    guide: 'delete_guide',
  }[type];

  // Count photos
  let imgCount = 1;
  try {
    if (item.images) {
      const arr = typeof item.images === 'string' ? JSON.parse(item.images) : item.images;
      if (Array.isArray(arr)) imgCount = arr.length;
    }
  } catch(e) {}

  const meta = type === 'accommodation'
    ? `${item.type} · ${formatCurrency(item.price)}/night · ${item.units} unit${item.units !== 1 ? 's' : ''}`
    : type === 'bike'
    ? `${item.model} · ${formatCurrency(item.price)} · ${item.time_period}`
    : type === 'equipment'
    ? `${item.type} · ${formatCurrency(item.price)} · ${item.time_period}`
    : `Rating: ${parseFloat(item.rating || 5).toFixed(1)} · ${item.location}${item.price ? ` · ${formatCurrency(item.price)}/day` : ''}`;

  return `
    <div class="admin-item-card" id="item-row-${type}-${item.id}">
      ${imgUrl ? `<img src="${imgUrl}" class="admin-item-thumb" alt="">` : `<div class="admin-item-thumb" style="display:flex;align-items:center;justify-content:center;font-size:2rem;background:var(--bg-secondary)">📷</div>`}
      <div class="admin-item-info">
        <div class="admin-item-name">${escHtml(item.name)} <span class="photo-badge">📷 ${imgCount} photo${imgCount > 1 ? 's' : ''}</span></div>
        <div class="admin-item-meta">${meta}</div>
        <div class="admin-item-meta" style="color:var(--text-muted)">📍 ${escHtml(item.location)} · Owner: @${escHtml(item.created_by)}</div>
      </div>
      <div class="admin-item-actions">
        <button class="btn btn-primary btn-sm" id="edit-btn-${type}-${item.id}">✏️ Edit Details &amp; Photos</button>
        <button class="btn btn-danger btn-sm" onclick="deleteItem('${deleteAction}', '${item.id}', '${type}')">🗑 Delete</button>
      </div>
    </div>`;
}

async function deleteItem(action, id, type) {
  if (!confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) return;

  try {
    const res = await API.delete(action, { id });
    if (res.success) {
      Toast.success(res.message);
      const row = document.getElementById(`item-row-${type}-${id}`);
      if (row) row.remove();
      if (myItemsData) {
        const key = type === 'guide' ? 'guides' : type + 's';
        if (myItemsData[key]) {
          myItemsData[key] = myItemsData[key].filter(i => i.id != id);
        }
      }
    } else {
      Toast.error(res.message);
    }
  } catch (e) {
    Toast.error('Delete failed.');
  }
}

/* ── Guest Bookings (Divided by Month) ────────────────────────────────────── */
async function loadGuestBookings() {
  const container = document.getElementById('guest-bookings-container');
  if (!container) return;
  container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading your reservations…</p></div>';

  try {
    const res = await API.get('my_bookings');
    if (!res.success) throw new Error(res.message);

    const bookings = res.data || [];
    if (!bookings.length) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">📅</div>
          <div class="empty-state-title">No bookings yet</div>
          <p class="empty-state-text">Browse and book accommodations, bikes, equipment, or tour guides for your adventure!</p>
          <button class="btn btn-primary mt-3" onclick="navigateTo('accommodation')">Explore Stays</button>
        </div>`;
      return;
    }

    // Sort bookings by newest checking date first (start_date DESC)
    bookings.sort((a, b) => {
      const dateA = a.start_date || (a.created_at ? a.created_at.split(' ')[0] : '');
      const dateB = b.start_date || (b.created_at ? b.created_at.split(' ')[0] : '');
      return dateB.localeCompare(dateA);
    });

    const monthOrder = [];
    const groups = {};
    bookings.forEach(b => {
      let dateObj = null;
      if (b.start_date) {
        const parts = b.start_date.split('-');
        if (parts.length === 3) {
          dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        }
      }
      if (!dateObj || isNaN(dateObj.getTime())) {
        dateObj = new Date(b.created_at);
      }
      const monthKey = !isNaN(dateObj.getTime())
        ? dateObj.toLocaleString('en', { month: 'long', year: 'numeric' })
        : 'Other Dates';

      if (!groups[monthKey]) {
        groups[monthKey] = [];
        monthOrder.push(monthKey);
      }
      groups[monthKey].push(b);
    });

    let html = '';
    for (const month of monthOrder) {
      const items = groups[month];
      const totalMonthCost = items.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0);
      html += `
        <div class="month-group-card">
          <div class="month-group-header">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-size:1.2rem">📅</span>
              <span class="month-title">${month}</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="month-count">${items.length} reservation${items.length !== 1 ? 's' : ''}</span>
              <span class="month-cost-badge">${formatCurrency(totalMonthCost)}</span>
            </div>
          </div>
          <div class="month-group-items">
            ${items.map(b => buildGuestBookingCard(b)).join('')}
          </div>
        </div>`;
    }

    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

function buildGuestBookingCard(b) {
  const typeClass = `type-${b.item_type}`;
  const statusClass = `status-${b.status}`;
  const isPaid = (b.payment_status || '').toLowerCase() === 'paid';
  const canCancel = b.status === 'pending' && !isPaid;
  const imgUrl = getImageUrl(b.item_image);
  const pluralType = b.item_type === 'accommodation' ? 'accommodation' : (b.item_type === 'bike' ? 'bikes' : (b.item_type === 'equipment' ? 'equipment' : 'guides'));

  const dates = b.start_date && b.end_date
    ? `${b.start_date} → ${b.end_date}`
    : b.time_period || 'N/A';

  // Owner contact details (no bank details for guest)
  const ownerHtml = (b.owner_name || b.owner_email || b.owner_phone) ? `
    <div class="owner-contact-card">
      <div class="owner-contact-title">🏠 Owner / Host Contact Details</div>
      <div class="owner-contact-grid">
        ${b.owner_name ? `<div class="owner-contact-row"><span>Host Name:</span> <strong>${escHtml(b.owner_name)}</strong> (@${escHtml(b.created_by)})</div>` : ''}
        ${b.owner_phone ? `
          <div class="owner-contact-row">
            <span>Phone:</span>
            <a href="tel:${escHtml(b.owner_phone)}" class="contact-link">📞 ${escHtml(b.owner_phone)}</a>
            <a href="https://wa.me/${escHtml(b.owner_phone.replace(/[^0-9]/g, ''))}" target="_blank" class="btn btn-sm btn-whatsapp">💬 WhatsApp</a>
          </div>` : ''}
        ${b.owner_email ? `
          <div class="owner-contact-row">
            <span>Email:</span>
            <a href="mailto:${escHtml(b.owner_email)}" class="contact-link">✉️ ${escHtml(b.owner_email)}</a>
          </div>` : ''}
      </div>
    </div>` : '';

  // Review section
  const reviewHtml = isPaid ? (() => {
    const itemNameEsc = escHtml(b.item_name || 'Booked Item');
    if (b.review_id) {
      const reviewTextEsc = (b.review_text || '').replace(/'/g, "\\'");
      return `
        <div class="booking-review-box">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <span class="review-badge">⭐ ${b.review_rating || 5}/5 — Your Review Submitted</span>
            <button class="btn btn-sm btn-outline" onclick="openReviewModal(${b.id}, '${b.item_type}', '${b.item_id}', '${itemNameEsc}', ${b.review_rating || 5}, '${reviewTextEsc}')">
              ✏️ Edit Review
            </button>
          </div>
          <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:6px;font-style:italic">"${escHtml(b.review_text || '')}"</p>
        </div>`;
    } else {
      return `
        <div class="booking-review-box">
          <button class="btn btn-sm btn-review" onclick="openReviewModal(${b.id}, '${b.item_type}', '${b.item_id}', '${itemNameEsc}')">
            ⭐ Write a Review for this Experience
          </button>
        </div>`;
    }
  })() : '';

  return `
    <div class="booking-card" id="booking-${b.id}">
      <div class="booking-media-header">
        <div class="booking-thumb-wrap">
          <img src="${imgUrl}" alt="${escHtml(b.item_name || 'Booked Item')}" class="booking-thumb-img" onerror="this.src='image/icon.jpg'">
          <span class="booking-thumb-badge ${typeClass}">${typeIcon(b.item_type)} ${b.item_type}</span>
        </div>
        <div class="booking-header-main">
          <div class="booking-title-row">
            <div class="booking-title">${escHtml(b.item_name || 'Booked Item')}</div>
            <div class="booking-badge-group">
              ${isPaid ? `<span class="badge-paid">✅ Paid & Confirmed</span>` : `<span class="badge-unpaid">⏳ Unpaid</span>`}
            </div>
          </div>
          ${b.item_location ? `<div class="booking-location">📍 ${escHtml(b.item_location)}</div>` : ''}
          ${b.order_id ? `<div class="booking-order-ref">Order Ref: <code>${escHtml(b.order_id)}</code> ${b.payment_id ? `· Txn: <code>${escHtml(b.payment_id)}</code>` : ''}</div>` : ''}
          ${b.item_description ? `<div class="booking-desc-snippet">${escHtml(b.item_description.length > 120 ? b.item_description.substring(0, 120) + '…' : b.item_description)}</div>` : ''}
        </div>
      </div>

      <div class="booking-details-grid">
        <div class="booking-detail-pill date-pill">
          <span class="pill-label">Check-in / Start</span>
          <span class="pill-value">🗓️ ${b.start_date || 'N/A'}</span>
        </div>
        ${b.end_date ? `
          <div class="booking-detail-pill date-pill">
            <span class="pill-label">Check-out / End</span>
            <span class="pill-value">🏁 ${b.end_date}</span>
          </div>` : ''}
        ${b.time_period ? `
          <div class="booking-detail-pill">
            <span class="pill-label">Duration</span>
            <span class="pill-value">⏱️ ${b.time_period}</span>
          </div>` : ''}
        ${b.units && b.units > 1 ? `
          <div class="booking-detail-pill">
            <span class="pill-label">Quantity</span>
            <span class="pill-value">🚪 ${b.units} units</span>
          </div>` : ''}
        <div class="booking-detail-pill price-pill">
          <span class="pill-label">Total Amount</span>
          <span class="pill-value price">💵 ${formatCurrency(b.price)}</span>
        </div>
      </div>

      ${ownerHtml}
      ${reviewHtml}

      <div class="booking-footer">
        <div class="booking-footer-left">
          <span class="status-badge ${statusClass}">${b.status}</span>
          <button class="btn btn-sm btn-outline" onclick="openDetailModal('${pluralType}', '${b.item_id}')" title="View Full Listing Details">
            🔍 View Listing Details
          </button>
        </div>
        <div class="booking-footer-right">
          ${isPaid ? `
            <button class="btn btn-outline-cyan btn-sm" onclick="openReceipt(${b.id})">
              🧾 Download Receipt
            </button>
          ` : `
            <button class="btn btn-primary btn-sm" onclick="payBookingNow(${b.id})">
              💳 Pay with PayHere (${formatCurrency(b.price)})
            </button>
          `}
          ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="cancelMyBooking(${b.id})">Cancel</button>` : ''}
        </div>
      </div>
    </div>`;
}

async function cancelMyBooking(id) {
  if (!confirm('Are you sure you want to cancel this booking? The reserved dates will be released.')) return;
  try {
    const res = await API.get('cancel_booking', { id });
    if (res.success) {
      Toast.success(res.message);
      loadGuestBookings();
      if (State.currentPage === 'home') loadHomeData();
      else loadListings(State.currentPage);
    } else {
      Toast.error(res.message);
    }
  } catch (e) {
    Toast.error('Failed to cancel booking.');
  }
}

/* ── Admin: Received Bookings & Earnings Summary ──────────────────────────── */
async function loadReceivedBookings(filters = {}) {
  const container = document.getElementById('received-bookings-container');
  if (!container) return;
  container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading received bookings…</p></div>';

  try {
    const res = await API.get('received_bookings', filters);
    if (!res.success) throw new Error(res.message);

    // Render summary cards
    const summaryEl = document.getElementById('received-summary');
    if (summaryEl && res.summary) {
      const s = res.summary;
      summaryEl.innerHTML = `
        <div class="earnings-summary">
          <div class="earnings-card">
            <div class="earnings-card-icon">📋</div>
            <div class="earnings-card-value">${s.total_count}</div>
            <div class="earnings-card-label">Total Filtered Bookings</div>
          </div>
          <div class="earnings-card">
            <div class="earnings-card-icon">💰</div>
            <div class="earnings-card-value">${formatCurrency(s.total_earnings)}</div>
            <div class="earnings-card-label">Gross Value</div>
          </div>
          <div class="earnings-card highlight-green">
            <div class="earnings-card-icon">✅</div>
            <div class="earnings-card-value">${formatCurrency(s.paid_earnings)}</div>
            <div class="earnings-card-label">Completed Revenue (Paid)</div>
          </div>
          <div class="earnings-card highlight-yellow">
            <div class="earnings-card-icon">⏳</div>
            <div class="earnings-card-value">${formatCurrency(s.pending_earnings)}</div>
            <div class="earnings-card-label">Pending / Unpaid</div>
          </div>
        </div>`;
    }

    const bookings = res.data || [];
    if (!bookings.length) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">📨</div>
          <div class="empty-state-title">No bookings match your filters</div>
          <p class="empty-state-text">Customer bookings matching the selected date range and item type will appear here.</p>
        </div>`;
      return;
    }

    // Sort received bookings by newest checking date first (start_date DESC)
    bookings.sort((a, b) => {
      const dateA = a.start_date || (a.created_at ? a.created_at.split(' ')[0] : '');
      const dateB = b.start_date || (b.created_at ? b.created_at.split(' ')[0] : '');
      return dateB.localeCompare(dateA);
    });

    // Group by month maintaining strict newest checking date first
    const monthOrder = [];
    const groups = {};
    bookings.forEach(b => {
      let dateObj = null;
      if (b.start_date) {
        const parts = b.start_date.split('-');
        if (parts.length === 3) {
          dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        }
      }
      if (!dateObj || isNaN(dateObj.getTime())) {
        dateObj = new Date(b.created_at);
      }
      const monthKey = !isNaN(dateObj.getTime())
        ? dateObj.toLocaleString('en', { month: 'long', year: 'numeric' })
        : 'Other Dates';

      if (!groups[monthKey]) {
        groups[monthKey] = [];
        monthOrder.push(monthKey);
      }
      groups[monthKey].push(b);
    });

    let html = '';
    for (const month of monthOrder) {
      const items = groups[month];
      const monthTotal = items.reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0);
      const paidTotal = items.filter(i => (i.payment_status || '').toLowerCase() === 'paid')
                             .reduce((sum, item) => sum + (parseFloat(item.price) || 0), 0);

      html += `
        <div class="month-group-card">
          <div class="month-group-header">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-size:1.2rem">📅</span>
              <span class="month-title">${month}</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="month-count">${items.length} booking${items.length !== 1 ? 's' : ''}</span>
              <span class="month-cost-badge">Paid: ${formatCurrency(paidTotal)} / Total: ${formatCurrency(monthTotal)}</span>
            </div>
          </div>
          <div class="month-group-items">
            ${items.map(b => buildAdminBookingCard(b)).join('')}
          </div>
        </div>`;
    }

    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

function buildAdminBookingCard(b) {
  const typeClass = `type-${b.item_type}`;
  const statusClass = `status-${b.status}`;
  const isPaid = (b.payment_status || '').toLowerCase() === 'paid';
  const imgUrl = getImageUrl(b.item_image);
  const pluralType = b.item_type === 'accommodation' ? 'accommodation' : (b.item_type === 'bike' ? 'bikes' : (b.item_type === 'equipment' ? 'equipment' : 'guides'));

  return `
    <div class="booking-card">
      <div class="booking-media-header">
        <div class="booking-thumb-wrap">
          <img src="${imgUrl}" alt="${escHtml(b.item_name || 'Item')}" class="booking-thumb-img" onerror="this.src='image/icon.jpg'">
          <span class="booking-thumb-badge ${typeClass}">${typeIcon(b.item_type)} ${b.item_type}</span>
        </div>
        <div class="booking-header-main">
          <div class="booking-title-row">
            <div class="booking-title">${escHtml(b.item_name || 'Item')}</div>
            <div class="booking-badge-group">
              ${isPaid ? `<span class="badge-paid">✅ Paid (PayHere)</span>` : `<span class="badge-unpaid">⏳ Unpaid</span>`}
            </div>
          </div>
          ${b.item_location ? `<div class="booking-location">📍 ${escHtml(b.item_location)}</div>` : ''}
          <div class="booking-cust-info">
            Customer: <strong>${escHtml(b.customer_name)}</strong> (@${escHtml(b.customer_username)}) · <a href="mailto:${escHtml(b.customer_email)}">${escHtml(b.customer_email)}</a>
            ${b.customer_phone ? ` · <a href="tel:${escHtml(b.customer_phone)}">📞 ${escHtml(b.customer_phone)}</a>` : ''}
          </div>
          ${b.order_id ? `<div class="booking-order-ref">Order: <code>${escHtml(b.order_id)}</code> ${b.payment_id ? `· Txn: <code>${escHtml(b.payment_id)}</code>` : ''}</div>` : ''}
        </div>
      </div>

      <div class="booking-details-grid">
        <div class="booking-detail-pill date-pill">
          <span class="pill-label">Check-in / Start</span>
          <span class="pill-value">🗓️ ${b.start_date || 'N/A'}</span>
        </div>
        ${b.end_date ? `
          <div class="booking-detail-pill date-pill">
            <span class="pill-label">Check-out / End</span>
            <span class="pill-value">🏁 ${b.end_date}</span>
          </div>` : ''}
        ${b.time_period ? `
          <div class="booking-detail-pill">
            <span class="pill-label">Duration</span>
            <span class="pill-value">⏱️ ${b.time_period}</span>
          </div>` : ''}
        ${b.units && b.units > 1 ? `
          <div class="booking-detail-pill">
            <span class="pill-label">Quantity</span>
            <span class="pill-value">🚪 ${b.units} units</span>
          </div>` : ''}
        <div class="booking-detail-pill price-pill">
          <span class="pill-label">Booking Value</span>
          <span class="pill-value price">💵 ${formatCurrency(b.price)}</span>
        </div>
      </div>

      <div class="booking-footer">
        <div class="booking-footer-left">
          <span class="status-badge ${statusClass}">${b.status}</span>
          <button class="btn btn-sm btn-outline" onclick="openDetailModal('${pluralType}', '${b.item_id}')" title="View Full Listing Details">
            🔍 View Listing Details
          </button>
        </div>
        <div class="booking-footer-right">
          ${isPaid ? `<button class="btn btn-outline-cyan btn-sm" onclick="openReceipt(${b.id})">🧾 View Receipt</button>` : ''}
          <span style="font-size:0.8rem;color:var(--text-muted)">Booked: ${new Date(b.created_at).toLocaleDateString()}</span>
        </div>
      </div>
    </div>`;
}

async function handleCreateAdmin(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Creating…';

  clearAlerts('create-admin-alerts');

  const data = {
    full_name: form.querySelector('[name="full_name"]').value.trim(),
    username: form.querySelector('[name="username"]').value.trim(),
    email: form.querySelector('[name="email"]').value.trim(),
    bank_details: form.querySelector('[name="bank_details"]').value.trim(),
    phone: form.querySelector('[name="phone"]').value.trim(),
    password: form.querySelector('[name="password"]').value,
    confirm_password: form.querySelector('[name="confirm_password"]').value,
  };

  try {
    const res = await API.post('create_admin', data);
    if (res.success) {
      showAlert('create-admin-alerts', res.message, 'success');
      form.reset();
      Toast.success('Admin account created!');
    } else {
      showAlert('create-admin-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('create-admin-alerts', err.message || 'Connection error.', 'error');
  }

  btn.disabled = false;
  btn.textContent = 'Create Admin Account';
}

/* ── Home Search Tab Switching ────────────────────────────────────────────── */
function switchHomeSearchTab(tab) {
  document.querySelectorAll('.search-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  document.querySelectorAll('.search-form-wrap').forEach(f => {
    f.style.display = f.id === `sf-${tab}` ? 'block' : 'none';
  });
}

/* ── HTML Escape ──────────────────────────────────────────────────────────── */
function escHtml(str) {
  if (str == null) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ── Wishlist System (❤️ Heart Icon for All Item Types) ─────────────────── */
const _typeLabels = { accommodation: 'Accommodation', bike: 'Bike', equipment: 'Equipment', guide: 'Guide' };

async function toggleWishlist(itemType, itemId) {
  if (!State.user) {
    Toast.info('Please sign in as a Guest to save items to your wishlist.');
    openAuthModal();
    return;
  }
  if (State.user.role !== 'Guest') {
    Toast.info('Wishlist is only available for Guest accounts.');
    return;
  }

  try {
    const res = await API.post('toggle_wishlist', {
      item_type: itemType,
      item_id: itemId
    });

    if (res.success) {
      const inWishlist = !!res.in_wishlist;
      const btns = document.querySelectorAll(`[id="wishlist-btn-${itemId}"]`);
      btns.forEach(btn => {
        btn.classList.toggle('active', inWishlist);
        const icon = btn.querySelector('.heart-icon');
        if (icon) icon.textContent = inWishlist ? '❤️' : '🤍';
        btn.title = inWishlist ? 'Remove from wishlist' : 'Add to wishlist';
      });

      if (inWishlist) Toast.success(`${_typeLabels[itemType] || 'Item'} added to your Wishlist! ❤️`);
      else Toast.info(`${_typeLabels[itemType] || 'Item'} removed from your Wishlist.`);

      if (document.getElementById('panel-wishlist')?.classList.contains('active')) {
        loadGuestWishlist();
      }
    } else {
      Toast.error(res.message);
    }
  } catch (e) {
    Toast.error('Could not update wishlist.');
  }
}

async function loadGuestWishlist() {
  const container = document.getElementById('guest-wishlist-container');
  const countEl = document.getElementById('wishlist-count');
  if (!container) return;
  container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Loading your saved items…</p></div>';

  try {
    const res = await API.get('get_wishlist');
    if (!res.success) throw new Error(res.message || 'Failed to load wishlist');
    const items = res.data || [];

    if (countEl) countEl.textContent = `${items.length} saved item${items.length !== 1 ? 's' : ''}`;

    if (!items.length) {
      container.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">🤍</div>
          <div class="empty-state-title">Your wishlist is empty</div>
          <p class="empty-state-text">Browse accommodations, bikes, equipment, or guides and click the ❤️ heart icon on any listing to save it here!</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:16px">
            <button class="btn btn-primary" onclick="navigateTo('accommodation')">🏠 Stays</button>
            <button class="btn btn-primary" onclick="navigateTo('bikes')">🚲 Bikes</button>
            <button class="btn btn-primary" onclick="navigateTo('equipment')">🏄 Equipment</button>
            <button class="btn btn-primary" onclick="navigateTo('guides')">🧭 Guides</button>
          </div>
        </div>`;
      return;
    }

    // Group by item_type for a nicer display
    const groups = {};
    const typeOrder = ['accommodation', 'bike', 'equipment', 'guide'];
    const typeEmojis = { accommodation: '🏠', bike: '🚲', equipment: '🏄', guide: '🧭' };
    items.forEach(item => {
      const t = item.item_type || 'equipment';
      if (!groups[t]) groups[t] = [];
      groups[t].push(item);
    });

    let html = '';
    typeOrder.forEach(t => {
      if (!groups[t] || !groups[t].length) return;
      const label = _typeLabels[t] + 's';
      html += `<div class="wishlist-type-group">
        <div class="wishlist-group-header">${typeEmojis[t]} ${label} <span class="month-count">${groups[t].length}</span></div>
        <div class="grid-3">${groups[t].map(item => {
          item.is_wishlisted = true;
          const cardType = t === 'guide' ? 'guides' : t;
          return t === 'guide' ? buildGuideCard(item) : buildListingCard(item, cardType);
        }).join('')}</div>
      </div>`;
    });
    container.innerHTML = html;
  } catch (e) {
    container.innerHTML = `<div class="alert alert-error">${e.message}</div>`;
  }
}

/* ── Review System (Guest Reviews & Ratings) ──────────────────────────────── */
let currentPickerRating = 5;

function openReviewModal(bookingId, itemType, itemId, itemName, currentRating = 5, currentText = '') {
  const modal = document.getElementById('review-modal');
  if (!modal) return;

  const idInput = document.getElementById('review-booking-id');
  const typeInput = document.getElementById('review-item-type');
  const itemIdInput = document.getElementById('review-item-id');
  const nameInput = document.getElementById('review-item-name');
  const textInput = document.getElementById('review-text');

  if (idInput) idInput.value = bookingId;
  if (typeInput) typeInput.value = itemType;
  if (itemIdInput) itemIdInput.value = itemId;
  if (nameInput) nameInput.value = itemName || 'Booked Item';
  if (textInput) textInput.value = currentText || '';

  clearAlerts('review-alerts');
  setPickerRating(currentRating || 5);
  modal.classList.add('open');
}

function closeReviewModal() {
  document.getElementById('review-modal')?.classList.remove('open');
}

function setPickerRating(val) {
  currentPickerRating = val;
  const ratingInput = document.getElementById('review-rating-val');
  if (ratingInput) ratingInput.value = val;

  const stars = document.querySelectorAll('#star-picker .star-pick');
  stars.forEach((s, idx) => {
    s.classList.toggle('active', (idx + 1) <= val);
  });

  const labels = {
    1: '1.0 / 5.0 (Poor)',
    2: '2.0 / 5.0 (Fair)',
    3: '3.0 / 5.0 (Good)',
    4: '4.0 / 5.0 (Very Good)',
    5: '5.0 / 5.0 (Excellent)'
  };
  const labelEl = document.getElementById('star-picker-label');
  if (labelEl) labelEl.textContent = labels[val] || `${val}.0 / 5.0`;
}

async function handleReviewSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('review-submit-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Posting review…';
  }

  clearAlerts('review-alerts');

  const bookingId = form.querySelector('#review-booking-id').value;
  const itemType  = form.querySelector('#review-item-type').value;
  const itemId    = form.querySelector('#review-item-id').value;
  const rating    = form.querySelector('#review-rating-val').value;
  const text      = form.querySelector('#review-text').value.trim();

  try {
    const res = await API.post('submit_review', {
      booking_id: bookingId,
      item_type: itemType,
      item_id: itemId,
      rating,
      review_text: text,
    });

    if (res.success) {
      Toast.success(res.message || 'Review submitted successfully!');
      closeReviewModal();
      loadGuestBookings();
      if (currentActiveDetailItem?.item?.id == itemId) {
        loadItemReviews(currentActiveDetailItem.type, itemId);
      }
    } else {
      showAlert('review-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('review-alerts', err.message || 'Connection error while saving review.', 'error');
  }

  if (btn) {
    btn.disabled = false;
    btn.textContent = '⭐ Post Review';
  }
}

/* ── Edit Profile Modal ───────────────────────────────────────────────────── */
function openEditProfileModal() {
  if (!State.user) return;
  const modal = document.getElementById('edit-profile-modal');
  if (!modal) return;

  const nameInput = document.getElementById('edit-profile-name');
  const emailInput = document.getElementById('edit-profile-email');
  const phoneInput = document.getElementById('edit-profile-phone');
  const bankInput = document.getElementById('edit-profile-bank');
  const bankGroup = document.getElementById('edit-profile-bank-group');
  const passInput = document.getElementById('edit-profile-password');

  if (nameInput) nameInput.value = State.user.full_name || '';
  if (emailInput) emailInput.value = State.user.email || '';
  if (phoneInput) phoneInput.value = State.user.phone || '';
  if (passInput) passInput.value = '';

  // Only show bank details in profile edit if Admin
  if (bankGroup) {
    if (State.user.role === 'Admin') {
      bankGroup.style.display = 'block';
      if (bankInput) bankInput.value = State.user.bank_details || '';
    } else {
      bankGroup.style.display = 'none';
      if (bankInput) bankInput.value = '';
    }
  }

  clearAlerts('edit-profile-alerts');
  modal.classList.add('open');
}

function closeEditProfileModal() {
  document.getElementById('edit-profile-modal')?.classList.remove('open');
}

async function handleEditProfileSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('edit-profile-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Saving changes…';
  }

  clearAlerts('edit-profile-alerts');

  const fullName = document.getElementById('edit-profile-name').value.trim();
  const email    = document.getElementById('edit-profile-email').value.trim();
  const phone    = document.getElementById('edit-profile-phone').value.trim();
  const bank     = document.getElementById('edit-profile-bank')?.value.trim() || '';
  const password = document.getElementById('edit-profile-password').value;

  try {
    const data = {
      full_name: fullName,
      email,
      phone,
    };
    if (State.user.role === 'Admin') {
      data.bank_details = bank;
    }
    if (password) {
      data.new_password = password;
      data.confirm_new_password = password;
    }

    const res = await API.post('update_profile', data);
    if (res.success) {
      State.user = res.user;
      updateNavUser();
      Toast.success('Profile updated successfully!');
      closeEditProfileModal();
      loadProfile();
    } else {
      showAlert('edit-profile-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('edit-profile-alerts', err.message || 'Connection error while saving profile.', 'error');
  }

  if (btn) {
    btn.disabled = false;
    btn.textContent = 'Save Profile Changes';
  }
}

/* ── Edit Item Modal (Admin Details & Multiple Photos) ────────────────────── */
let currentEditKeptImages = [];

function openEditItemModal(type, item) {
  const modal = document.getElementById('edit-item-modal');
  const body = document.getElementById('edit-item-modal-body');
  const title = document.getElementById('edit-item-modal-title');
  if (!modal || !body) return;

  if (title) title.textContent = `Edit ${escHtml(item.name || 'Item')}`;

  // Parse existing images
  let images = [];
  try {
    if (item.images) {
      images = typeof item.images === 'string' ? JSON.parse(item.images) : item.images;
    }
  } catch(e) { images = []; }
  if (!Array.isArray(images)) images = [];
  if (item.image && !images.includes(item.image)) images.unshift(item.image);
  images = Array.from(new Set(images.filter(Boolean)));
  currentEditKeptImages = [...images];

  let typeFields = '';
  if (type === 'accommodation') {
    typeFields = `
      <div class="form-row">
        <div class="form-group">
          <label>Accommodation Type *</label>
          <select name="type" class="form-control">
            <option value="Villa" ${item.type === 'Villa' ? 'selected' : ''}>Villa</option>
            <option value="Apartment" ${item.type === 'Apartment' ? 'selected' : ''}>Apartment</option>
            <option value="Room" ${item.type === 'Room' ? 'selected' : ''}>Room</option>
          </select>
        </div>
        <div class="form-group">
          <label>Available Units</label>
          <input type="number" name="units" class="form-control" value="${item.units || 1}" min="1">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Available From</label><input type="date" name="available_from" class="form-control" value="${item.available_from || ''}"></div>
        <div class="form-group"><label>Available To</label><input type="date" name="available_to" class="form-control" value="${item.available_to || ''}"></div>
      </div>`;
  } else if (type === 'bike') {
    typeFields = `
      <div class="form-row">
        <div class="form-group">
          <label>Bike Model *</label>
          <select name="model" class="form-control">
            <option value="Mountain Bike" ${(item.model||item.type) === 'Mountain Bike' ? 'selected' : ''}>Mountain Bike</option>
            <option value="Road Bike" ${(item.model||item.type) === 'Road Bike' ? 'selected' : ''}>Road Bike</option>
            <option value="City Bike" ${(item.model||item.type) === 'City Bike' ? 'selected' : ''}>City Bike</option>
            <option value="Electric Bike" ${(item.model||item.type) === 'Electric Bike' ? 'selected' : ''}>Electric Bike</option>
          </select>
        </div>
        <div class="form-group">
          <label>Rental Period</label>
          <input name="time_period" class="form-control" value="${escHtml(item.time_period || '1 day')}">
        </div>
      </div>`;
  } else if (type === 'equipment') {
    typeFields = `
      <div class="form-row">
        <div class="form-group">
          <label>Equipment Category *</label>
          <select name="type" class="form-control">
            <option value="Surfboard" ${(item.type||item.category) === 'Surfboard' ? 'selected' : ''}>Surfboard</option>
            <option value="Skateboard" ${(item.type||item.category) === 'Skateboard' ? 'selected' : ''}>Skateboard</option>
            <option value="Boat" ${(item.type||item.category) === 'Boat' ? 'selected' : ''}>Boat</option>
            <option value="Kayak" ${(item.type||item.category) === 'Kayak' ? 'selected' : ''}>Kayak</option>
            <option value="Snorkeling Gear" ${(item.type||item.category) === 'Snorkeling Gear' ? 'selected' : ''}>Snorkeling Gear</option>
            <option value="Camping Gear" ${(item.type||item.category) === 'Camping Gear' ? 'selected' : ''}>Camping Gear</option>
            <option value="Other" ${(item.type||item.category) === 'Other' ? 'selected' : ''}>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Rental Period</label>
          <input name="time_period" class="form-control" value="${escHtml(item.time_period || '1 day')}">
        </div>
      </div>`;
  } else if (type === 'guide') {
    typeFields = `
      <div class="form-row">
        <div class="form-group">
          <label>Languages</label>
          <input name="languages" class="form-control" value="${escHtml(item.languages || 'English, Sinhala')}">
        </div>
        <div class="form-group">
          <label>Experience / Credentials</label>
          <input name="experience" class="form-control" value="${escHtml(item.experience || '')}">
        </div>
      </div>`;
  }

  body.innerHTML = `
    <div id="edit-item-alerts"></div>
    <form id="edit-item-form" onsubmit="handleEditItemSubmit(event, '${type}', '${item.id}')">
      <input type="hidden" name="item_type" value="${type}">
      <input type="hidden" name="id" value="${item.id}">
      <input type="hidden" name="keep_images" id="edit-keep-images" value='${JSON.stringify(currentEditKeptImages)}'>

      <div class="form-row">
        <div class="form-group">
          <label>Item Name *</label>
          <input name="name" class="form-control" required value="${escHtml(item.name || item.title)}">
        </div>
        <div class="form-group">
          <label>Location *</label>
          <input name="location" class="form-control" required value="${escHtml(item.location)}">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Price (USD $) *</label>
          <input type="number" name="price" class="form-control" required min="1" step="0.01" value="${parseFloat(item.price || 0).toFixed(2)}">
        </div>
        <div class="form-group">
          <label>Rating (1 - 5)</label>
          <input type="number" name="rating" class="form-control" value="${parseFloat(item.rating || 5).toFixed(1)}" min="1" max="5" step="0.1">
        </div>
      </div>

      ${typeFields}

      <div class="form-group">
        <label>Description *</label>
        <textarea name="description" class="form-control" rows="3" required>${escHtml(item.description || item.bio || '')}</textarea>
      </div>

      <!-- Current Photos Management -->
      <div class="form-group">
        <label>Current Photos (Click ✕ on any photo to remove it)</label>
        <div class="edit-gallery-grid" id="edit-current-photos-grid">
          ${images.map((img, idx) => `
            <div class="edit-img-tile" id="edit-img-tile-${idx}">
              <img src="${getImageUrl(img)}" alt="Item Photo ${idx + 1}">
              <button type="button" class="edit-img-remove-btn" onclick="removeExistingImageInEdit(${idx})" title="Remove photo">✕</button>
            </div>
          `).join('')}
        </div>
      </div>

      <!-- Upload Additional Photos -->
      <div class="form-group">
        <label>Upload New Photos (Select several files)</label>
        <div class="img-upload-area">
          <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" onchange="previewEditMultipleImages(this)">
          <div class="img-upload-icon">📷</div>
          <div class="img-upload-text">Click to add more photos (JPG, PNG, WEBP)</div>
          <div id="preview-edit-multi" class="multi-img-preview-row"></div>
        </div>
      </div>

      <div class="form-group">
        <label>Or Add Photo URLs / Paths (optional, one per line)</label>
        <textarea name="image_urls" class="form-control" rows="2" placeholder="image/accommodation/acc-room-1.jpg&#10;https://example.com/new-photo.jpg"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="edit-item-submit-btn">
        💾 Save Changes &amp; Update Photos
      </button>
    </form>
  `;

  modal.classList.add('open');
}

function removeExistingImageInEdit(idx) {
  if (currentEditKeptImages[idx] !== undefined) {
    currentEditKeptImages.splice(idx, 1);
    const tile = document.getElementById(`edit-img-tile-${idx}`);
    if (tile) tile.remove();
    const hidden = document.getElementById('edit-keep-images');
    if (hidden) hidden.value = JSON.stringify(currentEditKeptImages);
    Toast.info('Photo marked for removal. Click Save to apply.');
  }
}

function previewEditMultipleImages(input) {
  const container = document.getElementById('preview-edit-multi');
  if (!container) return;
  container.innerHTML = '';
  if (input.files && input.files.length > 0) {
    Array.from(input.files).forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const item = document.createElement('div');
        item.className = 'multi-img-preview-item';
        item.innerHTML = `<img src="${e.target.result}" alt="Preview"><span class="edit-img-new-badge">NEW</span>`;
        container.appendChild(item);
      };
      reader.readAsDataURL(file);
    });
  }
}

function closeEditItemModal() {
  document.getElementById('edit-item-modal')?.classList.remove('open');
}

async function handleEditItemSubmit(e, type, id) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('edit-item-submit-btn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Updating item & photos…';
  }

  clearAlerts('edit-item-alerts');

  try {
    const res = await API.postForm('edit_item', form);
    if (res.success) {
      Toast.success(res.message || 'Item updated successfully!');
      closeEditItemModal();
      loadMyItems();
    } else {
      showAlert('edit-item-alerts', res.message, 'error');
    }
  } catch (err) {
    showAlert('edit-item-alerts', err.message || 'Connection error while saving item.', 'error');
  }

  if (btn) {
    btn.disabled = false;
    btn.textContent = '💾 Save Changes & Update Photos';
  }
}

/* ── PayHere Checkout Flow ────────────────────────────────────────────────── */
let activePaymentSession = null;

function openPayHereCheckout(bookingId, payhereData) {
  payhereData.return_url = window.location.href;
  payhereData.cancel_url = window.location.href;
  payhereData.notify_url = `${window.location.origin}/New/api.php?action=payhere_notify`;
  activePaymentSession = { bookingId, payhereData };
  const modal = document.getElementById('payment-modal');
  const body = document.getElementById('payment-modal-body');
  if (!modal || !body) return;

  const amt = parseFloat(payhereData.amount).toFixed(2);
  const formattedPrice = formatCurrency(amt);

  body.innerHTML = `
    <div class="payhere-checkout-card">
      <div class="payhere-header-banner">
        <div class="payhere-logo-badge">
          <span>💳</span> PayHere <span class="pill">${payhereData.sandbox ? 'SANDBOX' : 'LIVE'}</span>
        </div>
        <div class="payhere-live-tag">🔒 ${payhereData.sandbox ? 'Sandbox payment' : 'Secure live payment'}</div>
      </div>

      <div class="payhere-order-summary">
        <div class="payhere-summary-row">
          <span>Merchant ID:</span>
          <span><code>${escHtml(payhereData.merchant_id)}</code></span>
        </div>
        <div class="payhere-summary-row">
          <span>Order Reference:</span>
          <span><code>${escHtml(payhereData.order_id)}</code></span>
        </div>
        <div class="payhere-summary-row">
          <span>Item / Service:</span>
          <span>${escHtml(payhereData.items)}</span>
        </div>
        <div class="payhere-summary-row">
          <span>Customer:</span>
          <span>${escHtml(payhereData.first_name + ' ' + (payhereData.last_name || ''))}</span>
        </div>
        <div class="payhere-summary-row total">
          <span>Total to Pay:</span>
          <span class="price">${formattedPrice} (${escHtml(payhereData.currency)})</span>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px">
        <button class="btn btn-primary btn-full btn-lg" id="btn-payhere-start" onclick="triggerPayHerePopup()">
          🔒 Pay with PayHere (${formattedPrice})
        </button>
      </div>
      <p style="text-align:center;font-size:0.78rem;color:var(--text-muted);margin-top:14px">
        PayHere Merchant ID: <strong>${escHtml(payhereData.merchant_id)}</strong> · ${payhereData.sandbox ? 'Sandbox mode' : 'Live gateway'} · Signed checkout
      </p>
    </div>
  `;

  modal.classList.add('open');
}

function closePaymentModal() {
  document.getElementById('payment-modal')?.classList.remove('open');
}

function triggerPayHerePopup() {
  if (!activePaymentSession || !activePaymentSession.payhereData) return;
  const payment = activePaymentSession.payhereData;

  if (typeof payhere !== 'undefined') {
    payhere.onCompleted = function onCompleted(orderId) {
      Toast.success('PayHere Payment Completed! Order: ' + orderId);
      confirmPaymentOnServer(activePaymentSession.bookingId, orderId, 'PH-TXN-' + Date.now());
    };

    payhere.onDismissed = function onDismissed() {
      Toast.info('Payment window was closed. You can complete payment anytime in My Bookings.');
    };

    payhere.onError = function onError(error) {
      Toast.error('PayHere Error: ' + error);
    };

    try {
      payhere.startPayment(payment);
    } catch (e) {
      console.warn('PayHere startPayment error:', e);
      Toast.error('Failed to launch PayHere payment gateway: ' + e);
    }
  } else {
    Toast.error('PayHere SDK is loading. Please check your connection and try again.');
  }
}

async function confirmPaymentOnServer(bookingId, orderId, paymentId) {
  try {
    const res = await API.post('confirm_payment', {
      booking_id: bookingId,
      order_id: orderId,
      payment_id: paymentId,
      payment_method: 'PayHere'
    });

    if (res.success) {
      closePaymentModal();
      Toast.success('Payment successfully completed! Official receipt generated.');
      showReceiptModal(res.receipt);
      if (State.currentPage === 'home') loadHomeData();
      else if (State.currentPage === 'profile') loadGuestBookings();
      else loadListings(State.currentPage);
    } else {
      Toast.error(res.message);
    }
  } catch (e) {
    Toast.error('Error confirming payment.');
  }
}

async function payBookingNow(bookingId) {
  try {
    const res = await API.get('get_booking_payment_data', { booking_id: bookingId });
    if (!res.success || !res.payhere_data) {
      Toast.error(res.message || 'Could not prepare this payment.');
      return;
    }
    openPayHereCheckout(res.booking_id, res.payhere_data);
  } catch (e) {
    Toast.error(e.message || 'Could not initiate payment.');
  }
}

/* ── Receipt System (Print & Download) ────────────────────────────────────── */
let currentReceiptData = null;

async function openReceipt(bookingId) {
  try {
    const res = await API.get('get_receipt', { booking_id: bookingId });
    if (res.success && res.receipt) {
      showReceiptModal(res.receipt);
    } else {
      Toast.error(res.message || 'Failed to load receipt.');
    }
  } catch (e) {
    Toast.error('Could not retrieve receipt.');
  }
}

function showReceiptModal(receipt) {
  currentReceiptData = receipt;
  const modal = document.getElementById('receipt-modal');
  const body = document.getElementById('receipt-modal-body');
  if (!modal || !body) return;

  const isPaid = (receipt.payment_status || '').toLowerCase() === 'paid';
  const dates = receipt.start_date && receipt.end_date 
    ? `${receipt.start_date} to ${receipt.end_date}` 
    : (receipt.time_period || 'N/A');

  body.innerHTML = `
    <div class="receipt-card" id="printable-receipt">
      <div class="receipt-watermark">TOURGUIDE PAID</div>
      
      <div class="receipt-top">
        <div>
          <div class="receipt-brand-title">
            <span>🧭</span> TourGuide Platform
          </div>
          <div class="receipt-brand-sub">Official Travel &amp; Equipment Rental Booking Receipt</div>
          <div style="font-size:0.8rem;color:#64748b;margin-top:2px">PayHere Merchant ID: <strong>${escHtml(receipt.merchant_id || '1238511')}</strong> (Verified)</div>
        </div>
        <div style="text-align:right">
          <div class="receipt-status-stamp ${isPaid ? 'paid' : 'unpaid'}">
            ${isPaid ? '✓ PAID IN FULL' : '⏳ PAYMENT PENDING'}
          </div>
          <div style="font-size:0.85rem;font-weight:700;color:#0f172a;margin-top:8px">Receipt #${escHtml(receipt.receipt_no)}</div>
          <div style="font-size:0.75rem;color:#64748b">Date: ${escHtml(receipt.paid_at || new Date().toISOString())}</div>
        </div>
      </div>

      <div class="receipt-grid-meta">
        <div class="receipt-meta-block">
          <h4>Customer Information</h4>
          <p>${escHtml(receipt.customer_name)}</p>
          <div style="font-size:0.82rem;color:#64748b;margin-top:4px">
            <div>Email: ${escHtml(receipt.customer_email || 'N/A')}</div>
            <div>Phone: ${escHtml(receipt.customer_phone || 'N/A')}</div>
            <div>Username: @${escHtml(receipt.customer_username)}</div>
          </div>
        </div>
        <div class="receipt-meta-block">
          <h4>Payment Gateway Details</h4>
          <p>${escHtml(receipt.payment_method)}</p>
          <div style="font-size:0.82rem;color:#64748b;margin-top:4px">
            <div>PayHere Order ID: <code>${escHtml(receipt.order_id)}</code></div>
            <div>Transaction Ref: <code>${escHtml(receipt.payment_id)}</code></div>
            <div>Gateway Status: <span style="color:#059669;font-weight:700">${escHtml(receipt.payment_status)}</span></div>
          </div>
        </div>
      </div>

      <table class="receipt-table">
        <thead>
          <tr>
            <th>Item &amp; Description</th>
            <th>Type</th>
            <th>Booking Period</th>
            <th>Units</th>
            <th>Amount (${escHtml(receipt.currency)})</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong style="color:#0f172a;font-size:0.95rem">${escHtml(receipt.item_name)}</strong>
              <div style="font-size:0.78rem;color:#64748b;margin-top:2px">Booking Ref: #${receipt.booking_id}</div>
            </td>
            <td style="text-transform:capitalize">${escHtml(receipt.item_type)}</td>
            <td>${escHtml(dates)}</td>
            <td>${receipt.units || 1}</td>
            <td>$${parseFloat(receipt.amount).toFixed(2)}</td>
          </tr>
        </tbody>
      </table>

      <div class="receipt-totals">
        <div class="receipt-totals-table">
          <div class="receipt-totals-row">
            <span>Subtotal:</span>
            <span>$${parseFloat(receipt.amount).toFixed(2)}</span>
          </div>
          <div class="receipt-totals-row">
            <span>Taxes &amp; Fees:</span>
            <span>$0.00</span>
          </div>
          <div class="receipt-totals-row grand-total">
            <span>Total Paid:</span>
            <span>$${parseFloat(receipt.amount).toFixed(2)} ${escHtml(receipt.currency)}</span>
          </div>
        </div>
      </div>

      <div class="receipt-footer-note">
        <div>
          <div>Thank you for choosing TourGuide! Please retain this official receipt for your records.</div>
          <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px">For support: support@tourguide.lk | Verified Merchant ID: 1238511</div>
        </div>
        <div style="text-align:right;font-size:0.8rem;color:#059669;font-weight:600">
          🔒 Secure PayHere Transaction
        </div>
      </div>
    </div>
  `;

  modal.classList.add('open');
}

function closeReceiptModal() {
  document.getElementById('receipt-modal')?.classList.remove('open');
}

function printReceipt() {
  window.print();
}

function downloadReceiptFile() {
  if (!currentReceiptData) {
    Toast.error('No receipt loaded.');
    return;
  }
  const r = currentReceiptData;
  const content = `
================================================================================
                           TOURGUIDE PAYMENT RECEIPT
================================================================================
Receipt No        : ${r.receipt_no}
Booking ID        : #${r.booking_id}
PayHere Order ID  : ${r.order_id}
PayHere Trans ID  : ${r.payment_id}
Merchant ID       : ${r.merchant_id || '1238511'}
Payment Method    : ${r.payment_method}
Payment Status    : ${r.payment_status}
Payment Date      : ${r.paid_at}

CUSTOMER INFORMATION
--------------------------------------------------------------------------------
Name              : ${r.customer_name}
Username          : @${r.customer_username}
Email             : ${r.customer_email || 'N/A'}
Phone             : ${r.customer_phone || 'N/A'}

BOOKING DETAILS
--------------------------------------------------------------------------------
Item Name         : ${r.item_name}
Category          : ${r.item_type}
Booking Period    : ${r.start_date && r.end_date ? r.start_date + ' to ' + r.end_date : r.time_period || 'N/A'}
Units             : ${r.units || 1}
--------------------------------------------------------------------------------
TOTAL PAID        : $${parseFloat(r.amount).toFixed(2)} ${r.currency}
================================================================================
            Thank you for exploring with TourGuide! (Merchant: 1238511)
================================================================================
`;
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `Receipt-${r.receipt_no}.txt`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
  Toast.success('Receipt downloaded successfully!');
}

/* ── Init ─────────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  Theme.update();
  Currency.updateLabels();
  await checkAuth();
  loadHomeData();

  // Close modals on Escape key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeAuthModal();
      closeDetailModal();
      closePaymentModal();
      closeReceiptModal();
      closeReviewModal();
      closeEditProfileModal();
      closeEditItemModal();
    }
  });
});
