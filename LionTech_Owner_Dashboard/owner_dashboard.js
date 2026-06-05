/* owner_dashboard.js — bilingual UI + mobile menu + chart */
(function(){
  const btn = document.getElementById('od-menu-btn');
  const close = document.getElementById('od-sidebar-close');
  const sidebar = document.getElementById('od-sidebar');
  if(btn && sidebar) btn.addEventListener('click', () => sidebar.classList.add('open'));
  if(close && sidebar) close.addEventListener('click', () => sidebar.classList.remove('open'));

  const translations = {
    fr: {
      nav_dashboard:'Dashboard', nav_products:'Produits', nav_stock_in:'Stock entrant', nav_stock_out:'Stock sortant', nav_employees:'Employés', nav_reports:'Rapports', nav_notifications:'Notifications', nav_settings:'Paramètres', nav_logout:'Déconnexion',
      page_title:'Tableau de bord', welcome:'Bienvenue', sub_attention:'Attention abonnement', renew:'Renouveler',
      add_product:'Ajouter produit', add_product_sub:'Créer un nouvel article', stock_in:'Stock entrant', stock_in_sub:'Ajouter une livraison', stock_out:'Stock sortant', stock_out_sub:'Vente, perte ou usage', add_employee:'Ajouter employé', add_employee_sub:'Optionnel si vous travaillez seul',
      total_products:'Produits', total_stock:'Quantité totale', low_stock:'Stock faible', employees:'Employés',
      recent_products:'Produits récents', recent_products_sub:'Aperçu des derniers articles ajoutés', view_all:'Voir tout', product:'Produit', category:'Catégorie', qty:'Qté', price:'Prix', status:'Statut',
      no_products:'Aucun produit pour le moment', no_products_sub:'Commencez par ajouter vos produits pour suivre votre stock.', add_first_product:'Ajouter le premier produit',
      stock_by_category:'Stock par catégorie', stock_by_category_sub:'Vue simple de vos quantités', employee_area:'Espace employés', employee_area_sub:'Le système fonctionne aussi sans employés.', solo_mode:'Mode business individuel actif', solo_mode_text:'Vous pouvez gérer vos produits et votre stock vous-même. Les pages employés resteront optionnelles.', active_employees:'employé(s) actif(s)', active_employees_text:'Vous pouvez suivre les présences et les activités de votre équipe.', recent_activity:'Activité récente', recent_activity_sub:'Dernières actions dans votre business', no_activity:'Aucune activité récente'
    },
    en: {
      nav_dashboard:'Dashboard', nav_products:'Products', nav_stock_in:'Stock In', nav_stock_out:'Stock Out', nav_employees:'Employees', nav_reports:'Reports', nav_notifications:'Notifications', nav_settings:'Settings', nav_logout:'Logout',
      page_title:'Owner Dashboard', welcome:'Welcome', sub_attention:'Subscription warning', renew:'Renew',
      add_product:'Add Product', add_product_sub:'Create a new item', stock_in:'Stock In', stock_in_sub:'Record a delivery', stock_out:'Stock Out', stock_out_sub:'Sale, loss or usage', add_employee:'Add Employee', add_employee_sub:'Optional if you work alone',
      total_products:'Products', total_stock:'Total Quantity', low_stock:'Low Stock', employees:'Employees',
      recent_products:'Recent Products', recent_products_sub:'Preview of latest items added', view_all:'View all', product:'Product', category:'Category', qty:'Qty', price:'Price', status:'Status',
      no_products:'No products yet', no_products_sub:'Start by adding products to track your stock.', add_first_product:'Add first product',
      stock_by_category:'Stock by Category', stock_by_category_sub:'Simple view of your quantities', employee_area:'Employee Area', employee_area_sub:'The system also works without employees.', solo_mode:'Solo business mode active', solo_mode_text:'You can manage your products and stock by yourself. Employee pages stay optional.', active_employees:'active employee(s)', active_employees_text:'You can track attendance and team activity.', recent_activity:'Recent Activity', recent_activity_sub:'Latest actions in your business', no_activity:'No recent activity'
    }
  };

  let lang = localStorage.getItem('lt_lang') || 'fr';
  const langBtn = document.getElementById('od-lang-btn');
  function applyLang(){
    document.documentElement.lang = lang;
    if(langBtn) langBtn.textContent = lang === 'fr' ? 'EN' : 'FR';
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if(translations[lang] && translations[lang][key]) el.textContent = translations[lang][key];
    });
  }
  if(langBtn){ langBtn.addEventListener('click',()=>{ lang = lang === 'fr' ? 'en':'fr'; localStorage.setItem('lt_lang', lang); applyLang(); }); }
  applyLang();

  const chartEl = document.getElementById('odStockChart');
  if(chartEl && window.Chart){
    const data = window.OWNER_CHART_DATA || {labels:[], values:[]};
    new Chart(chartEl, {
      type: 'doughnut',
      data: { labels: data.labels, datasets: [{ data: data.values, borderWidth: 0 }] },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } }, cutout:'62%' }
    });
  }
})();
