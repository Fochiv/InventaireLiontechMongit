(function(){
  /* ── Sidebar toggle ── */
  const sidebar = document.getElementById('od-sidebar');
  document.getElementById('rp-menu-btn')?.addEventListener('click', () => {
    if (sidebar) {
      sidebar.classList.add('open');
      const overlay = document.getElementById('od-overlay');
      if (overlay) overlay.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
  });

  /* ── Date range helpers ── */
  function ymd(d) { return d.toISOString().slice(0, 10); }

  function setRange(from, to) {
    const f = document.getElementById('rp-from');
    const t = document.getElementById('rp-to');
    if (f) f.value = from;
    if (t) t.value = to;
    document.getElementById('rp-filter-form')?.submit();
  }

  document.getElementById('quick-today')?.addEventListener('click', () => {
    const d = ymd(new Date());
    setRange(d, d);
  });

  document.getElementById('quick-month')?.addEventListener('click', () => {
    const now  = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    setRange(ymd(from), ymd(now));
  });

  document.getElementById('quick-year')?.addEventListener('click', () => {
    const now  = new Date();
    const from = new Date(now.getFullYear(), 0, 1);
    setRange(ymd(from), ymd(now));
  });

  /* ── Print PDF ── */
  document.getElementById('print-pdf')?.addEventListener('click', () => window.print());

  /* ── Export CSV ── */
  document.getElementById('export-csv')?.addEventListener('click', () => {
    const rows = [...document.querySelectorAll('#report-table tr')].map(tr =>
      [...tr.children].map(td => '"' + td.innerText.replace(/"/g, '""') + '"').join(',')
    );
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'liontech_rapport_' + (window.REPORT_FROM || '') + '_' + (window.REPORT_TO || '') + '.csv';
    a.click();
    URL.revokeObjectURL(a.href);
  });

  /* ── Language system ── */
  const dict = {
    fr: {
      page_title:'Rapports', page_subtitle:'Analyse inventaire, présence et mouvements de stock',
      nav_dashboard:'Dashboard', nav_products:'Produits', nav_stock_in:'Stock entrant',
      nav_stock_out:'Stock sortant', nav_employees:'Employés', nav_attendance:'Présence',
      nav_reports:'Rapports', nav_settings:'Paramètres', nav_logout:'Déconnexion',
      nav_notifications:'Notifications', nav_validations:'Validations',
      nav_activity:'Activité', nav_subscription:'Abonnement', nav_change_pin:'Changer PIN',
      expired_title:'Abonnement expiré',
      expired_text:"Vous pouvez consulter les rapports, mais les actions d'inventaire restent limitées jusqu'au renouvellement.",
      from:'Du', to:'Au', apply:'Appliquer', today:"Aujourd'hui", this_month:'Ce mois',
      this_year:'Cette année', period:'Période',
      products:'Produits', stock_in:'Stock entrant', stock_out:'Stock sortant',
      low_stock:'Stock faible', pending:'En attente', out_stock:'Rupture',
      stock_movement:'Mouvement de stock', stock_movement_sub:'Entrées vs sorties',
      top_products:'Produits les plus sortis', top_products_sub:'Selon la période choisie',
      low_stock_report:'Rapport stock faible', low_stock_sub:'Produits à réapprovisionner',
      attendance_report:'Rapport présence employés', attendance_sub:'Heures travaillées et retards',
      recent_movements:'Historique récent', recent_sub:'Dernières entrées et sorties de stock',
      col_product:'Produit', col_category:'Catégorie', col_qty:'Quantité', col_min:'Minimum',
      col_employee:'Employé', col_days:'Présence', col_hours:'Heures', col_late:'Retards',
      col_type:'Type', col_status:'Statut', col_user:'Utilisateur', col_date:'Date',
      no_low_stock:'Aucun produit en stock faible.', no_movements:'Aucun mouvement trouvé.',
      days:'jour(s)',
    },
    en: {
      page_title:'Reports', page_subtitle:'Inventory, attendance, and stock movement analysis',
      nav_dashboard:'Dashboard', nav_products:'Products', nav_stock_in:'Stock In',
      nav_stock_out:'Stock Out', nav_employees:'Employees', nav_attendance:'Attendance',
      nav_reports:'Reports', nav_settings:'Settings', nav_logout:'Logout',
      nav_notifications:'Notifications', nav_validations:'Approvals',
      nav_activity:'Activity', nav_subscription:'Subscription', nav_change_pin:'Change PIN',
      expired_title:'Subscription expired',
      expired_text:'You can view reports, but inventory actions are limited until renewal.',
      from:'From', to:'To', apply:'Apply', today:'Today', this_month:'This month',
      this_year:'This year', period:'Period',
      products:'Products', stock_in:'Stock In', stock_out:'Stock Out',
      low_stock:'Low Stock', pending:'Pending', out_stock:'Out of Stock',
      stock_movement:'Stock Movement', stock_movement_sub:'Incoming vs outgoing',
      top_products:'Most moved products', top_products_sub:'Based on selected period',
      low_stock_report:'Low Stock Report', low_stock_sub:'Products to restock',
      attendance_report:'Employee Attendance Report', attendance_sub:'Hours worked and late arrivals',
      recent_movements:'Recent History', recent_sub:'Latest stock in and stock out records',
      col_product:'Product', col_category:'Category', col_qty:'Quantity', col_min:'Minimum',
      col_employee:'Employee', col_days:'Attendance', col_hours:'Hours', col_late:'Late',
      col_type:'Type', col_status:'Status', col_user:'User', col_date:'Date',
      no_low_stock:'No low stock products.', no_movements:'No movements found.',
      days:'day(s)',
    },
  };

  let lang = localStorage.getItem('lt_lang') || 'fr';

  function applyLang() {
    document.documentElement.lang = lang;
    const btn = document.getElementById('rp-lang-btn');
    if (btn) btn.textContent = lang === 'fr' ? 'EN' : 'FR';
    const d = dict[lang] || dict.fr;
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const k = el.dataset.i18n;
      if (d[k] !== undefined) el.textContent = d[k];
    });
  }

  document.getElementById('rp-lang-btn')?.addEventListener('click', () => {
    lang = lang === 'fr' ? 'en' : 'fr';
    localStorage.setItem('lt_lang', lang);
    applyLang();
  });

  applyLang();

  /* ── Charts ── */
  const charts = window.REPORT_CHARTS || {};
  if (window.Chart) {
    const stockCanvas = document.getElementById('stockChart');
    if (stockCanvas) {
      new Chart(stockCanvas, {
        type: 'doughnut',
        data: {
          labels: charts.stock?.labels || [],
          datasets: [{
            data: charts.stock?.values || [],
            backgroundColor: ['#1A9E7A', '#DC2626'],
            borderWidth: 0,
          }],
        },
        options: {
          plugins: { legend: { position: 'bottom' } },
          responsive: true,
          cutout: '60%',
        },
      });
    }

    const topCanvas = document.getElementById('topChart');
    if (topCanvas) {
      new Chart(topCanvas, {
        type: 'bar',
        data: {
          labels: charts.top?.labels || [],
          datasets: [{
            label: 'Quantité',
            data: charts.top?.values || [],
            backgroundColor: 'rgba(212,160,23,.75)',
            borderColor: '#D4A017',
            borderWidth: 1,
            borderRadius: 6,
          }],
        },
        options: {
          plugins: { legend: { display: false } },
          responsive: true,
          scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } } },
        },
      });
    }
  }
})();
