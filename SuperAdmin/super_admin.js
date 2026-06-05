/* ============================================================
   super_admin.js — LionTech Super Admin Dashboard
   Panel system, sidebar, chart, search/filter, modals, toasts, lang
   ============================================================ */

const $  = id  => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);
const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

/* ══════════════════════════════════════════
   1. SIDEBAR TOGGLE (mobile drawer)
   ══════════════════════════════════════════ */
function initSidebar() {
  const sidebar   = $('sa-sidebar');
  const overlay   = $('sa-overlay');
  const hamburger = $('sa-hamburger');
  const closeBtn  = $('sa-sidebar-close');

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

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeSidebar(); closeModal(); }
  });
}

/* ══════════════════════════════════════════
   2. PANEL SWITCHING (data-panel nav items)
   ══════════════════════════════════════════ */
const PANELS = ['dashboard', 'businesses', 'users', 'subscriptions'];

function showPanel(name) {
  PANELS.forEach(p => {
    const el = $('panel-' + p);
    if (el) el.style.display = (p === name) ? '' : 'none';
  });

  /* Update nav active state */
  $$('.sa-nav-item[data-panel]').forEach(item => {
    item.classList.toggle('active', item.dataset.panel === name);
  });

  /* Close sidebar on mobile */
  if (window.innerWidth <= 992) {
    $('sa-sidebar')?.classList.remove('open');
    $('sa-overlay')?.classList.remove('active');
    document.body.style.overflow = '';
  }

  /* Re-init chart when showing dashboard */
  if (name === 'dashboard' && !window._chartDone) initChart();

  /* Update topbar search placeholder context */
  const placeholders = {
    dashboard     : 'Rechercher businesses, utilisateurs…',
    businesses    : 'Rechercher businesses…',
    users         : 'Rechercher utilisateurs…',
    subscriptions : 'Rechercher abonnements…',
  };
  const ts = $('topbar-search');
  if (ts) ts.placeholder = placeholders[name] || placeholders.dashboard;
}

function initPanels() {
  $$('.sa-nav-item[data-panel]').forEach(item => {
    on(item, 'click', () => showPanel(item.dataset.panel));
  });

  /* Read ?panel=xxx from URL to navigate directly to a panel */
  const urlPanel = new URLSearchParams(window.location.search).get('panel');
  showPanel(urlPanel && PANELS.includes(urlPanel) ? urlPanel : 'dashboard');
}

/* ══════════════════════════════════════════
   3. DROPDOWNS
   ══════════════════════════════════════════ */
function initDropdowns() {
  $$('[data-dropdown]').forEach(trigger => {
    const targetId = trigger.dataset.dropdown;
    const menu     = $(targetId);
    on(trigger, 'click', e => {
      e.stopPropagation();
      const isOpen = menu?.classList.contains('open');
      $$('.sa-dropdown.open').forEach(d => d.classList.remove('open'));
      if (!isOpen && menu) menu.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    $$('.sa-dropdown.open').forEach(d => d.classList.remove('open'));
  });
}

/* ══════════════════════════════════════════
   4. CHART.JS
   ══════════════════════════════════════════ */
function initChart() {
  const canvas = $('sa-chart');
  if (!canvas || !window.Chart) return;
  if (window._chartDone) return;

  const data = window.SA_CHART_DATA || {
    labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
    revenue: [0,0,0,0,0,0], subscriptions: [0,0,0,0,0,0],
  };

  new Chart(canvas, {
    data: {
      labels: data.labels,
      datasets: [
        {
          type: 'bar', label: 'Revenus (XAF)', data: data.revenue,
          backgroundColor: 'rgba(212,160,23,.75)', borderColor: '#D4A017',
          borderWidth: 1, borderRadius: 6, yAxisID: 'yRev', order: 2,
        },
        {
          type: 'line', label: 'Abonnements actifs', data: data.subscriptions,
          borderColor: '#1A9E7A', backgroundColor: 'rgba(26,158,122,.10)',
          fill: true, tension: 0.42,
          pointBackgroundColor: '#1A9E7A', pointBorderColor: '#fff',
          pointBorderWidth: 2, pointRadius: 5, yAxisID: 'ySub', order: 1,
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0B1F3A', titleColor: '#F0C040',
          bodyColor: 'rgba(255,255,255,.85)', padding: 10,
          callbacks: {
            label: ctx => ctx.dataset.label === 'Revenus (XAF)'
              ? ` Revenus: ${ctx.parsed.y.toLocaleString()} XAF`
              : ` Abonnements: ${ctx.parsed.y}`,
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
        yRev: {
          position: 'left', grid: { color: 'rgba(0,0,0,.05)' },
          ticks: { font: { size: 11 }, color: '#94A3B8', callback: v => v >= 1000 ? (v/1000)+'k' : v },
        },
        ySub: {
          position: 'right', grid: { display: false },
          ticks: { font: { size: 11 }, color: '#1A9E7A', stepSize: 1 },
        },
      },
    },
  });
  window._chartDone = true;
}

/* ══════════════════════════════════════════
   5. TABLE FILTERING (businesses / users / subs)
   ══════════════════════════════════════════ */
function makeFilter(tableId, searchId, filterIds, countId) {
  const table   = $(tableId);
  const search  = $(searchId);
  if (!table) return;

  const filters = filterIds.map(id => $(id)).filter(Boolean);

  function apply() {
    const q = (search?.value || '').toLowerCase().trim();
    const filterVals = filters.map(f => f?.value || 'all');

    const rows = Array.from(table.querySelectorAll('tbody tr[data-name], tbody tr[data-biz]'));
    let visible = 0;

    rows.forEach(row => {
      const d = row.dataset;
      const text = Object.values(d).join(' ').toLowerCase();
      const matchQ = !q || text.includes(q);

      /* Check each filter against its corresponding data attribute */
      let matchFilters = true;
      filters.forEach((f, i) => {
        const val = filterVals[i];
        if (val === 'all') return;
        /* Determine which data attribute to check */
        if (f.id.includes('role'))   { if (d.role   !== val) matchFilters = false; }
        else if (f.id.includes('status')) { if (d.status !== val) matchFilters = false; }
        else { if (d.status !== val) matchFilters = false; }
      });

      const show = matchQ && matchFilters;
      row.classList.toggle('hidden', !show);
      if (show) visible++;
    });

    /* Empty state */
    let empty = table.querySelector('.sa-table-empty-row');
    if (visible === 0 && rows.length > 0) {
      if (!empty) {
        const tr = document.createElement('tr');
        tr.className = 'sa-table-empty-row';
        const cols = table.querySelector('thead tr')?.children?.length || 8;
        tr.innerHTML = `<td colspan="${cols}" class="sa-table-empty">Aucun résultat ne correspond à votre recherche.</td>`;
        table.querySelector('tbody')?.appendChild(tr);
      }
    } else {
      empty?.remove();
    }

    /* Update count */
    const countEl = $(countId);
    if (countEl) countEl.textContent = `Affichage de ${visible} sur ${rows.length} résultat(s)`;
  }

  on(search, 'input', apply);
  filters.forEach(f => on(f, 'change', apply));
}

function initTables() {
  makeFilter('businesses-table', 'biz-search',   ['biz-filter'],                       'biz-table-count');
  makeFilter('users-table',      'users-search',  ['users-role-filter','users-status-filter'], 'users-table-count');
  makeFilter('subs-table',       'subs-search',   ['subs-filter'],                      null);
}

/* ══════════════════════════════════════════
   6. TOPBAR SEARCH (global — syncs to active panel's table)
   ══════════════════════════════════════════ */
function initTopbarSearch() {
  const topSearch = $('topbar-search');
  on(topSearch, 'input', e => {
    const q = e.target.value.trim();
    /* Determine active panel */
    const activePanel = PANELS.find(p => {
      const el = $('panel-' + p);
      return el && el.style.display !== 'none';
    }) || 'dashboard';

    const searchMap = {
      dashboard     : 'biz-search',
      businesses    : 'biz-search',
      users         : 'users-search',
      subscriptions : 'subs-search',
    };
    const targetId = searchMap[activePanel];
    if (targetId) {
      const t = $(targetId);
      if (t) {
        t.value = q;
        t.dispatchEvent(new Event('input'));
      }
    }
  });
}

/* ══════════════════════════════════════════
   7. MODAL SYSTEM
   ══════════════════════════════════════════ */
function openModal(id) {
  const overlay = $(id + '-modal');
  if (!overlay) return;
  $$('.sa-modal-overlay.open').forEach(m => m.classList.remove('open'));
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const target = id ? $(id + '-modal') : document.querySelector('.sa-modal-overlay.open');
  if (!target) return;
  target.classList.remove('open');
  document.body.style.overflow = '';
}
function initModals() {
  $$('.sa-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  });
  $$('[data-close-modal]').forEach(btn => on(btn, 'click', () => closeModal()));
  $$('[data-open-modal]').forEach(btn => on(btn, 'click', () => openModal(btn.dataset.openModal)));
}

/* ══════════════════════════════════════════
   8. BUSINESS ACTIONS
   ══════════════════════════════════════════ */
function viewBusiness(id, name, type, owner, phone, city, status, date, username, pin) {
  $('view-biz-name').textContent    = name || '—';
  $('view-biz-type').textContent    = type || '—';
  $('view-biz-owner').textContent   = owner || '—';
  $('view-biz-phone').textContent   = phone || '—';
  $('view-biz-city').textContent    = city || '—';
  $('view-biz-date').textContent    = date || '—';
  $('view-owner-username').textContent = username || '—';
  const badge = $('view-biz-status');
  badge.textContent = status || '—';
  badge.className   = 'sa-badge sa-badge-' + (status || 'disabled');
  openModal('view-business');
}

function editBusiness(id, name, type, owner, phone, city) {
  const fields = { 'edit-biz-id': id, 'edit-biz-name': name, 'edit-biz-owner': owner, 'edit-biz-phone': phone, 'edit-biz-city': city };
  Object.entries(fields).forEach(([fid, val]) => { const el = $(fid); if (el) el.value = val; });
  /* Set select */
  const typeEl = $('edit-biz-type');
  if (typeEl) { for (let o of typeEl.options) { if (o.value === type || o.text === type) { o.selected = true; break; } } }
  openModal('edit-business');
}

function confirmDisable(id, name) {
  const msg = $('disable-confirm-msg');
  if (msg) msg.innerHTML = `Désactiver <strong>${name}</strong> ? Le propriétaire et les employés perdront l'accès.`;
  const btn = $('disable-confirm-btn');
  if (btn) btn.onclick = () => {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
      const badge = row.querySelector('.sa-badge');
      if (badge) { badge.className = 'sa-badge sa-badge-disabled'; badge.textContent = 'Désactivé'; }
    }
    closeModal();
    showToast(`"${name}" a été désactivé.`, 'warning');
  };
  openModal('disable');
}

function renewSubscription(id, name) {
  $('renew-biz-name') && ($('renew-biz-name').textContent = name);
  $('renew-biz-id')   && ($('renew-biz-id').value = id);
  const today = new Date().toISOString().split('T')[0];
  const in30  = new Date(Date.now() + 30*864e5).toISOString().split('T')[0];
  $('renew-start') && ($('renew-start').value = today);
  $('renew-end')   && ($('renew-end').value = in30);
  openModal('renew');
}

function saveEdit() {
  const name = $('edit-biz-name')?.value || 'Business';
  closeModal();
  showToast(`"${name}" mis à jour avec succès.`, 'success');
}

function saveRenewal() {
  const name = $('renew-biz-name')?.textContent;
  const end  = $('renew-end')?.value;
  closeModal();
  showToast(`Abonnement renouvelé pour "${name}" jusqu'au ${end}.`, 'success');
}

/* ══════════════════════════════════════════
   9. TOAST
   ══════════════════════════════════════════ */
let toastTimer = null;
function showToast(message, type = 'default', duration = 3500) {
  const toast = $('sa-toast');
  if (!toast) return;
  toast.textContent = message;
  toast.className   = `sa-toast show${type !== 'default' ? ' ' + type : ''}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.className = 'sa-toast'; }, duration);
}

/* ══════════════════════════════════════════
   10. ALERT RENEW SHORTCUTS
   ══════════════════════════════════════════ */
function initAlertRenew() {
  $$('.sa-alert-renew[data-id]').forEach(btn => {
    on(btn, 'click', () => renewSubscription(btn.dataset.id, btn.dataset.name));
  });
}

/* ══════════════════════════════════════════
   11. LANGUAGE TOGGLE (FR / EN)
   ══════════════════════════════════════════ */
const SA_LANG = {
  fr: {
    nav_section_main:'Principal', nav_section_payments:'Paiements', nav_section_platform:'Plateforme', nav_section_system:'Système',
    nav_dashboard:'Dashboard', nav_add_business:'Ajouter Business', nav_businesses:'Businesses',
    nav_validate:'Valider Paiements', nav_payment_numbers:'Numéros Paiement',
    nav_subscriptions:'Abonnements', nav_users:'Utilisateurs', nav_reports:'Rapports',
    nav_settings:'Paramètres', nav_logout:'Déconnexion',
    page_title:'Super Admin Dashboard', page_sub:'Gérez les businesses, abonnements, utilisateurs et l\'activité.',
    stat_total:'Total Businesses', stat_active:'Actifs', stat_expired:'Abonnements Expirés',
    stat_users:'Utilisateurs', stat_pending:'En Attente', stat_activity:'Activité Aujourd\'hui',
    chart_title:'Tendances Mensuelles', chart_sub:'Revenus (XAF) & Abonnements actifs',
    view_reports:'Voir Rapports', legend_revenue:'Revenus (XAF)', legend_subs:'Abonnements actifs',
    alerts_title:'Alertes Abonnements', alerts_sub:'Businesses nécessitant attention',
    alerts_label:'alertes', activity_title:'Activité Récente', activity_sub:'Derniers événements',
    no_alerts:'Aucune alerte.', no_activity:'Aucune activité aujourd\'hui.',
    biz_page_title:'Gestion des Businesses', users_page_title:'Gestion des Utilisateurs',
    subs_page_title:'Gestion des Abonnements',
    col_name:'Nom', col_type:'Type', col_owner:'Propriétaire', col_phone:'Téléphone',
    col_city:'Ville', col_status:'Statut', col_created:'Créé le', col_actions:'Actions',
    col_login_id:'Identifiant', col_email:'Email', col_role:'Rôle', col_business:'Business',
    col_plan:'Plan', col_amount:'Montant', col_start:'Début', col_end:'Fin',
    filter_all:'Tous', filter_active:'Actif', filter_expired:'Expiré',
    filter_trial:'Essai', filter_suspended:'Suspendu', filter_inactive:'Inactif',
    role_owner:'Propriétaire', role_manager:'Gérant', role_employee:'Employé',
    btn_view:'Voir', btn_edit:'Modifier', btn_disable:'Désactiver', btn_renew:'Renouveler',
    btn_close:'Fermer', btn_cancel:'Annuler', btn_save:'Enregistrer',
    btn_disable_confirm:'Oui, Désactiver', btn_confirm_renew:'Confirmer Renouvellement',
    modal_view_title:'Détails du Business', modal_edit_title:'Modifier Business',
    modal_disable_title:'Désactiver Business', modal_renew_title:'Renouveler Abonnement',
    notif_title:'Notifications', validate_link:'→ Valider',
    pending_payments:'paiement(s) en attente',
    no_businesses:'Aucun business.', no_users:'Aucun utilisateur.', no_subscriptions:'Aucun abonnement.',
  },
  en: {
    nav_section_main:'Main', nav_section_payments:'Payments', nav_section_platform:'Platform', nav_section_system:'System',
    nav_dashboard:'Dashboard', nav_add_business:'Add Business', nav_businesses:'Businesses',
    nav_validate:'Validate Payments', nav_payment_numbers:'Payment Numbers',
    nav_subscriptions:'Subscriptions', nav_users:'Users', nav_reports:'Reports',
    nav_settings:'Settings', nav_logout:'Logout',
    page_title:'Super Admin Dashboard', page_sub:'Manage businesses, subscriptions, users and platform activity.',
    stat_total:'Total Businesses', stat_active:'Active', stat_expired:'Expired Subscriptions',
    stat_users:'Users', stat_pending:'Pending Payments', stat_activity:'Today\'s Activity',
    chart_title:'Monthly Trends', chart_sub:'Revenue (XAF) & Active Subscriptions',
    view_reports:'View Reports', legend_revenue:'Revenue (XAF)', legend_subs:'Active Subscriptions',
    alerts_title:'Subscription Alerts', alerts_sub:'Businesses needing attention',
    alerts_label:'alerts', activity_title:'Recent Activity', activity_sub:'Latest platform events',
    no_alerts:'No alerts.', no_activity:'No activity logged today.',
    biz_page_title:'Business Management', users_page_title:'User Management',
    subs_page_title:'Subscription Management',
    col_name:'Name', col_type:'Type', col_owner:'Owner', col_phone:'Phone',
    col_city:'City', col_status:'Status', col_created:'Created', col_actions:'Actions',
    col_login_id:'Login ID', col_email:'Email', col_role:'Role', col_business:'Business',
    col_plan:'Plan', col_amount:'Amount', col_start:'Start', col_end:'End',
    filter_all:'All', filter_active:'Active', filter_expired:'Expired',
    filter_trial:'Trial', filter_suspended:'Suspended', filter_inactive:'Inactive',
    role_owner:'Business Owner', role_manager:'Manager', role_employee:'Employee',
    btn_view:'View', btn_edit:'Edit', btn_disable:'Disable', btn_renew:'Renew',
    btn_close:'Close', btn_cancel:'Cancel', btn_save:'Save Changes',
    btn_disable_confirm:'Yes, Disable', btn_confirm_renew:'Confirm Renewal',
    modal_view_title:'Business Details', modal_edit_title:'Edit Business',
    modal_disable_title:'Disable Business', modal_renew_title:'Renew Subscription',
    notif_title:'Notifications', validate_link:'→ Validate',
    pending_payments:'pending payment(s)',
    no_businesses:'No businesses yet.', no_users:'No users found.', no_subscriptions:'No subscriptions found.',
  },
};

let _lang = localStorage.getItem('lt_lang') || 'fr';

function applyLang(lang) {
  _lang = lang;
  localStorage.setItem('lt_lang', lang);
  const dict = SA_LANG[lang] || SA_LANG.fr;

  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n;
    if (dict[k] !== undefined) el.textContent = dict[k];
  });

  const btn = $('sa-lang-btn');
  if (btn) btn.textContent = lang === 'fr' ? 'EN' : 'FR';

  /* Update placeholders */
  const s = $('topbar-search');
  if (s) s.placeholder = lang === 'fr' ? 'Rechercher businesses, utilisateurs…' : 'Search businesses, users…';
  const bs = $('biz-search');
  if (bs) bs.placeholder = lang === 'fr' ? 'Rechercher par nom, type, propriétaire, ville…' : 'Search by name, type, owner, city…';
  const us = $('users-search');
  if (us) us.placeholder = lang === 'fr' ? 'Rechercher par nom, identifiant, email…' : 'Search by name, login ID, email…';
  const ss = $('subs-search');
  if (ss) ss.placeholder = lang === 'fr' ? 'Rechercher business, plan…' : 'Search business, plan…';
}

function initLang() {
  const btn = $('sa-lang-btn');
  on(btn, 'click', () => applyLang(_lang === 'fr' ? 'en' : 'fr'));
  applyLang(_lang);
}

/* ══════════════════════════════════════════
   INIT
   ══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initPanels();
  initDropdowns();
  initModals();
  initTables();
  initTopbarSearch();
  initAlertRenew();
  initLang();

  if (window.Chart) {
    initChart();
  } else {
    const s = document.querySelector('script[src*="chart"]');
    s?.addEventListener('load', initChart);
    setTimeout(initChart, 600);
  }
});
