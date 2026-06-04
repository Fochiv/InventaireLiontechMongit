/* ============================================================
   super_admin.js — LionTech Super Admin Dashboard
   Sidebar, Chart, Search/Filter, Modals, Toasts, Actions
   ============================================================ */

/* ─── DOM helpers ─── */
const $  = id  => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);
const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

/* ─── Active page state ─── */
let activeModal = null;

/* ══════════════════════════════════════════════
   1. SIDEBAR TOGGLE (mobile drawer)
   ══════════════════════════════════════════════ */
function initSidebar() {
  const sidebar  = $('sa-sidebar');
  const overlay  = $('sa-overlay');
  const hamburger= $('sa-hamburger');
  const closeBtn = $('sa-sidebar-close');

  const openSidebar  = () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
  };
  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
  };

  on(hamburger, 'click', openSidebar);
  on(closeBtn,  'click', closeSidebar);
  on(overlay,   'click', closeSidebar);

  /* Close on escape */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeSidebar(); closeModal(); }
  });

  /* Nav item active highlight */
  $$('.sa-nav-item[data-page]').forEach(item => {
    on(item, 'click', () => {
      $$('.sa-nav-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      if (window.innerWidth <= 992) closeSidebar();
    });
  });
}

/* ══════════════════════════════════════════════
   2. DROPDOWNS (profile & notifications)
   ══════════════════════════════════════════════ */
function initDropdowns() {
  $$('[data-dropdown]').forEach(trigger => {
    const targetId = trigger.dataset.dropdown;
    const menu     = $(targetId);
    on(trigger, 'click', e => {
      e.stopPropagation();
      const isOpen = menu?.classList.contains('open');
      /* close all first */
      $$('.sa-dropdown.open').forEach(d => d.classList.remove('open'));
      if (!isOpen && menu) menu.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    $$('.sa-dropdown.open').forEach(d => d.classList.remove('open'));
  });
}

/* ══════════════════════════════════════════════
   3. CHART.JS — Monthly Trends
   ══════════════════════════════════════════════ */
function initChart() {
  const canvas = $('sa-chart');
  if (!canvas || !window.Chart) return;

  const data = window.SA_CHART_DATA || {
    labels      : ['Jan','Feb','Mar','Apr','May','Jun'],
    revenue     : [45000, 80000, 65000, 120000, 95000, 110000],
    subscriptions: [2, 3, 4, 6, 7, 8],
  };

  new Chart(canvas, {
    data: {
      labels  : data.labels,
      datasets: [
        {
          type           : 'bar',
          label          : 'Revenue (XAF)',
          data           : data.revenue,
          backgroundColor: 'rgba(212,160,23,.75)',
          borderColor    : '#D4A017',
          borderWidth    : 1,
          borderRadius   : 6,
          yAxisID        : 'yRev',
          order          : 2,
        },
        {
          type            : 'line',
          label           : 'Active Subscriptions',
          data            : data.subscriptions,
          borderColor     : '#1A9E7A',
          backgroundColor : 'rgba(26,158,122,.10)',
          fill            : true,
          tension         : 0.42,
          pointBackgroundColor: '#1A9E7A',
          pointBorderColor    : '#fff',
          pointBorderWidth    : 2,
          pointRadius     : 5,
          yAxisID         : 'ySub',
          order           : 1,
        },
      ],
    },
    options: {
      responsive         : true,
      maintainAspectRatio: true,
      interaction        : { mode: 'index', intersect: false },
      plugins: {
        legend : { display: false },
        tooltip: {
          backgroundColor: '#0B1F3A',
          titleColor     : '#F0C040',
          bodyColor      : 'rgba(255,255,255,.85)',
          padding        : 10,
          callbacks: {
            label: ctx => {
              if (ctx.dataset.label === 'Revenue (XAF)')
                return ` Revenue: ${ctx.parsed.y.toLocaleString()} XAF`;
              return ` Subscriptions: ${ctx.parsed.y}`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 }, color: '#94A3B8' },
        },
        yRev: {
          position: 'left',
          grid: { color: 'rgba(0,0,0,.05)' },
          ticks: {
            font: { size: 11 }, color: '#94A3B8',
            callback: v => v >= 1000 ? (v/1000)+'k' : v,
          },
        },
        ySub: {
          position: 'right',
          grid: { display: false },
          ticks: { font: { size: 11 }, color: '#1A9E7A', stepSize: 1 },
        },
      },
    },
  });
}

/* ══════════════════════════════════════════════
   4. BUSINESS TABLE — Live search + filter
   ══════════════════════════════════════════════ */
function initTable() {
  const searchInput = $('table-search');
  const filterSel   = $('table-filter');
  const table       = $('businesses-table');
  if (!table) return;

  const rows = () => Array.from(table.querySelectorAll('tbody tr[data-name]'));

  function applyFilters() {
    const q      = (searchInput?.value || '').toLowerCase().trim();
    const status = filterSel?.value || 'all';
    let visible  = 0;

    rows().forEach(row => {
      const name = (row.dataset.name || '').toLowerCase();
      const type = (row.dataset.type || '').toLowerCase();
      const owner= (row.dataset.owner|| '').toLowerCase();
      const city = (row.dataset.city || '').toLowerCase();
      const st   = (row.dataset.status|| '');

      const matchQ  = !q || name.includes(q) || type.includes(q) || owner.includes(q) || city.includes(q);
      const matchSt = status === 'all' || st === status;

      const show = matchQ && matchSt;
      row.classList.toggle('hidden', !show);
      if (show) visible++;
    });

    /* Empty state */
    let empty = table.querySelector('.sa-table-empty-row');
    if (visible === 0) {
      if (!empty) {
        const tr = document.createElement('tr');
        tr.className = 'sa-table-empty-row';
        const cols = table.querySelector('thead tr')?.children?.length || 9;
        tr.innerHTML = `<td colspan="${cols}" class="sa-table-empty">🔍 No businesses match your search.</td>`;
        table.querySelector('tbody')?.appendChild(tr);
      }
    } else {
      empty?.remove();
    }

    /* Update count */
    const countEl = $('table-count');
    if (countEl) countEl.textContent = `Showing ${visible} of ${rows().length} businesses`;
  }

  on(searchInput, 'input', applyFilters);
  on(filterSel,   'change', applyFilters);
}

/* ══════════════════════════════════════════════
   5. MODAL SYSTEM
   ══════════════════════════════════════════════ */
function openModal(id) {
  const overlay = $(id + '-modal');
  if (!overlay) return;
  $$('.sa-modal-overlay.open').forEach(m => m.classList.remove('open'));
  overlay.classList.add('open');
  activeModal = id;
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  const target = id ? $(id + '-modal') : document.querySelector('.sa-modal-overlay.open');
  if (!target) return;
  target.classList.remove('open');
  activeModal = null;
  document.body.style.overflow = '';
}

function initModals() {
  /* Close on overlay click */
  $$('.sa-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal();
    });
  });

  /* Close buttons */
  $$('[data-close-modal]').forEach(btn => {
    on(btn, 'click', () => closeModal());
  });

  /* Open triggers */
  $$('[data-open-modal]').forEach(btn => {
    on(btn, 'click', () => openModal(btn.dataset.openModal));
  });
}

/* ══════════════════════════════════════════════
   6. BUSINESS ACTIONS
   ══════════════════════════════════════════════ */
function viewBusiness(
  id,
  name,
  type,
  owner,
  phone,
  city,
  status,
  date,
  username,
  pin
) {

  document.getElementById('view-biz-name').textContent =
    name || '—';

  document.getElementById('view-biz-type').textContent =
    type || '—';

  document.getElementById('view-biz-owner').textContent =
    owner || '—';

  document.getElementById('view-biz-phone').textContent =
    phone || '—';

  document.getElementById('view-biz-city').textContent =
    city || '—';

  document.getElementById('view-biz-date').textContent =
    date || '—';

  document.getElementById('view-owner-username').textContent =
    username || '—';

  document.getElementById('view-temp-pin').textContent =
    pin || '—';

 const statusBadge =
  document.getElementById('view-biz-status');

statusBadge.textContent =
  status || '—';

statusBadge.className =
  'sa-badge sa-badge-' + status;

openModal('view-business');
}

function editBusiness(id, name, type, owner, phone, city) {
  const fields = {
    'edit-biz-name' : name,
    'edit-biz-type' : type,
    'edit-biz-owner': owner,
    'edit-biz-phone': phone,
    'edit-biz-city' : city,
  };
  Object.entries(fields).forEach(([fieldId, val]) => {
    const el = $(fieldId);
    if (el) el.value = val;
  });
  $('edit-biz-id') && ($('edit-biz-id').value = id);
  openModal('edit-business');
}

function confirmDisable(id, name) {
  const msg = $('disable-confirm-msg');
  if (msg) msg.innerHTML = `Are you sure you want to <strong>disable</strong> <em>${name}</em>? The owner and employees will lose access.`;
  const btn = $('disable-confirm-btn');
  if (btn) btn.onclick = () => {
    /* ── DB: UPDATE businesses SET disabled=1 WHERE business_id=id ── */
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
      const badge = row.querySelector('.sa-badge');
      if (badge) { badge.className = 'sa-badge sa-badge-disabled'; badge.textContent = 'Disabled'; }
    }
    closeModal();
    showToast(`Business "${name}" has been disabled.`, 'warning');
  };
  openModal('disable');
}

function renewSubscription(id, name) {
  $('renew-biz-name') && ($('renew-biz-name').textContent = name);
  $('renew-biz-id')   && ($('renew-biz-id').value   = id);
  /* default renewal: today → +30 days */
  const today = new Date().toISOString().split('T')[0];
  const in30  = new Date(Date.now() + 30*864e5).toISOString().split('T')[0];
  $('renew-start') && ($('renew-start').value = today);
  $('renew-end')   && ($('renew-end').value   = in30);
  openModal('renew');
}

/* Save edit */
function saveEdit() {
  /* ── DB: UPDATE businesses SET ... WHERE business_id = $('edit-biz-id').value ── */
  const name = $('edit-biz-name')?.value || 'Business';
  closeModal();
  showToast(`"${name}" updated successfully.`, 'success');
}

/* Save renewal */
function saveRenewal() {
  const id   = $('renew-biz-id')?.value;
  const name = $('renew-biz-name')?.textContent;
  const end  = $('renew-end')?.value;
  /* ── DB: INSERT INTO subscriptions (...) VALUES (...) ── */
  /* ── DB: UPDATE businesses SET subscription_status='active', subscription_expires_at=end WHERE business_id=id ── */
  const row = document.querySelector(`tr[data-id="${id}"]`);
  if (row) {
    const badge = row.querySelector('.sa-badge');
    if (badge) { badge.className = 'sa-badge sa-badge-active'; badge.textContent = 'Active'; }
  }
  closeModal();
  showToast(`Subscription renewed for "${name}" until ${end}.`, 'success');
}

/* ══════════════════════════════════════════════
   7. TOAST NOTIFICATION
   ══════════════════════════════════════════════ */
let toastTimer = null;
function showToast(message, type = 'default', duration = 3500) {
  const toast = $('sa-toast');
  if (!toast) return;
  toast.textContent = message;
  toast.className   = `sa-toast show${type !== 'default' ? ' ' + type : ''}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.classList.remove('show');
    toast.className = 'sa-toast';
  }, duration);
}

/* ══════════════════════════════════════════════
   8. ADD BUSINESS FORM SUBMIT
   ══════════════════════════════════════════════ */
function submitAddBusiness() {
  const name = $('add-biz-name')?.value.trim();
  if (!name) { showToast('Business name is required.', 'error'); return; }

  /* ── DB: INSERT INTO businesses (...) VALUES (...) ── */
  /* ── DB: INSERT INTO users (...) VALUES (...) for owner ── */
  closeModal();
  showToast(`"${name}" added successfully!`, 'success');
  /* In production: reload or AJAX update the table */
}

/* ══════════════════════════════════════════════
   9. TOPBAR SEARCH (global shortcut)
   ══════════════════════════════════════════════ */
function initTopbarSearch() {
  const topSearch = $('topbar-search');
  on(topSearch, 'keyup', e => {
    const q = e.target.value.trim();
    const tableSearch = $('table-search');
    if (tableSearch) {
      tableSearch.value = q;
      tableSearch.dispatchEvent(new Event('input'));
    }
  });
}

/* ══════════════════════════════════════════════
   10. ALERT RENEW SHORTCUTS
   ══════════════════════════════════════════════ */
function initAlertRenew() {
  $$('.sa-alert-renew[data-id]').forEach(btn => {
    on(btn, 'click', () => {
      const id   = btn.dataset.id;
      const name = btn.dataset.name;
      renewSubscription(id, name);
    });
  });
}

/* ══════════════════════════════════════════════
   INIT
   ══════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initDropdowns();
  initModals();
  initTable();
  initTopbarSearch();
  initAlertRenew();

  /* Chart — wait for Chart.js to load */
  if (window.Chart) {
    initChart();
  } else {
    const s = document.querySelector('script[src*="chart"]');
    s?.addEventListener('load', initChart);
    /* Fallback: try after 500ms */
    setTimeout(initChart, 600);
  }

  /* Mark sidebar dashboard item active */
  const dashItem = document.querySelector('.sa-nav-item[data-page="dashboard"]');
  dashItem?.classList.add('active');
});
