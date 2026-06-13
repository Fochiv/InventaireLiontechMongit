/* products.js — LionTech Products Pages — EN/FR · Preview · Filters */
const translations = {
  en: {
    nav_main:'Menu',nav_dashboard:'Dashboard',nav_employees:'Employees',
    nav_products:'Products',nav_stock_in:'Stock In',nav_stock_out:'Stock Out',
    nav_reports:'Reports',nav_settings:'Settings',nav_logout:'Logout',
    page_title:'Products',page_subtitle:'Add and manage products for your inventory.',
    add_product:'Add Product',add_product_title:'Add Product',
    add_product_page_subtitle:'Create the product first. Stock quantity added later via Stock In.',
    back_to_products:'← Back to products',view_products:'View all products →',
    feature_locked_title:'Inventory feature not active',
    expired_warning:'Subscription expired. View-only mode.',
    total_products:'Total Products',low_stock:'Low Stock',
    out_of_stock:'Out of Stock',archived_products:'Archived',
    search_placeholder:'Search products...',all_categories:'All categories',
    all_stock:'All stock',export:'Export',products_list:'Products List',
    safe_delete_note:'Products are archived instead of permanently deleted.',
    product:'Product',category:'Category',quantity:'Quantity',
    price:'Price',low_limit:'Low Limit',expiration:'Expiration',
    status:'Status',actions:'Actions',no_products:'No products yet.',
    view:'View',archive:'Archive',restore:'Restore',
    product_identity:'Product Identity',
    product_identity_subtitle:'Fill in product details. Quantity comes from Stock In deliveries.',
    product_name:'Product Name *',custom_category:'Custom Category',
    select_option:'Select...',unit:'Unit *',
    selling_price:'Selling Price (XAF)',selling_price_help:'Price used by the cashier during sales.',
    cost_price:'Purchase Price / Cost (XAF)',cost_price_help:'Optional. Used to calculate profit margin on Vente reports.',
    low_stock_level:'Low Stock Alert Level',barcode:'Barcode / UPC',
    barcode_help:'Use existing barcode or generate one later.',
    expiration_date:'Expiration Date',product_image:'Product Image',
    image_note:'Optional. Max 2 MB.',description:'Description / Notes',
    cancel:'Cancel',save_product:'Save Product',margin:'Margin / unit',
    preview:'Preview',preview_subtitle:'Live product summary.',
    stock_note_text:'Use Stock In when products are delivered. This keeps delivery records, cost prices, and profit history clean.',
  },
  fr: {
    nav_main:'Menu',nav_dashboard:'Tableau de bord',nav_employees:'Employés',
    nav_products:'Produits',nav_stock_in:'Stock entrant',nav_stock_out:'Stock sortant',
    nav_reports:'Rapports',nav_settings:'Paramètres',nav_logout:'Déconnexion',
    page_title:'Produits',page_subtitle:'Ajouter et gérer les produits de votre inventaire.',
    add_product:'Ajouter produit',add_product_title:'Ajouter produit',
    add_product_page_subtitle:"Créez d'abord le produit. La quantité sera ajoutée depuis Stock entrant.",
    back_to_products:'← Retour produits',view_products:'Voir tous les produits →',
    feature_locked_title:'Fonction inventaire non active',
    expired_warning:'Abonnement expiré. Mode consultation uniquement.',
    total_products:'Total produits',low_stock:'Stock faible',
    out_of_stock:'Rupture',archived_products:'Archivés',
    search_placeholder:'Rechercher un produit...',all_categories:'Toutes catégories',
    all_stock:'Tous les stocks',export:'Exporter',products_list:'Liste des produits',
    safe_delete_note:"Les produits sont archivés au lieu d'être supprimés.",
    product:'Produit',category:'Catégorie',quantity:'Quantité',
    price:'Prix',low_limit:'Limite faible',expiration:'Expiration',
    status:'Statut',actions:'Actions',no_products:'Aucun produit pour le moment.',
    view:'Voir',archive:'Archiver',restore:'Restaurer',
    product_identity:'Identité du produit',
    product_identity_subtitle:"Créez d'abord le produit. La quantité viendra de Stock entrant.",
    product_name:'Nom du produit *',custom_category:'Catégorie personnalisée',
    select_option:'Sélectionner...',unit:'Unité *',
    selling_price:'Prix de vente (XAF)',selling_price_help:'Prix utilisé par la caisse lors des ventes.',
    cost_price:"Prix d'achat / Coût (XAF)",cost_price_help:'Optionnel. Utilisé pour calculer la marge sur les rapports Vente.',
    low_stock_level:"Niveau d'alerte stock faible",barcode:'Code-barres / UPC',
    barcode_help:"Utilisez le code-barres existant ou générez-en un plus tard.",
    expiration_date:"Date d'expiration",product_image:'Image du produit',
    image_note:'Optionnel. Max 2 Mo.',description:'Description / Notes',
    cancel:'Annuler',save_product:'Enregistrer produit',margin:'Marge / unité',
    preview:'Aperçu',preview_subtitle:'Résumé en direct.',
    stock_note_text:"Utilisez Stock entrant lors des livraisons. Cela garde l'historique des livraisons, prix d'achat et validations propre.",
  }
};

let lang = localStorage.getItem('lt_lang') || 'en';

function applyLang(){
  document.documentElement.lang = lang;
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const k=el.dataset.i18n;
    if(translations[lang]?.[k]) el.textContent=translations[lang][k];
  });
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
    const k=el.dataset.i18nPlaceholder;
    if(translations[lang]?.[k]) el.placeholder=translations[lang][k];
  });
  const btn=document.getElementById('pr-lang');
  if(btn) btn.textContent = lang==='en'?'FR':'EN';
  localStorage.setItem('lt_lang',lang);
  if(typeof window.applySidebarLang==='function') window.applySidebarLang(lang);
}

document.getElementById('pr-lang')?.addEventListener('click',()=>{
  lang = lang==='en'?'fr':'en';
  localStorage.setItem('lt_lang',lang);
  applyLang();
});

/* ── Sidebar handled by Sidebar.php ── */

/* ── Modals ── */
const addModal = document.getElementById('addProductModal');
function openAdd(){ if(addModal){ addModal.style.display='flex'; addModal.setAttribute('aria-hidden','false'); } }
function closeAdd(){ if(addModal){ addModal.style.display='none'; addModal.setAttribute('aria-hidden','true'); } }
document.getElementById('openAddProduct')?.addEventListener('click',openAdd);
document.getElementById('closeAddProduct')?.addEventListener('click',closeAdd);
document.getElementById('cancelAddProduct')?.addEventListener('click',closeAdd);
addModal?.addEventListener('click',e=>{ if(e.target===addModal) closeAdd(); });

/* ── Search / Filter ── */
const search=document.getElementById('productSearch');
const catFilter=document.getElementById('categoryFilter');
const stockFilter=document.getElementById('stockFilter');
function filterRows(){
  const q=(search?.value||'').toLowerCase();
  const c=catFilter?.value||'';
  const s=stockFilter?.value||'';
  document.querySelectorAll('#productsTable tbody tr').forEach(row=>{
    if(row.querySelector('.pr-empty,.od-empty')) return;
    const okQ=!q||(row.dataset.name||'').includes(q)||row.textContent.toLowerCase().includes(q);
    const okC=!c||(row.dataset.category||'').includes(c);
    const okS=!s||row.dataset.stock===s||row.dataset.status===s;
    row.style.display=okQ&&okC&&okS?'':'none';
  });
}
search?.addEventListener('input',filterRows);
catFilter?.addEventListener('change',filterRows);
stockFilter?.addEventListener('change',filterRows);

/* ── CSV Export ── */
document.getElementById('exportCsv')?.addEventListener('click',()=>{
  const rows=[...document.querySelectorAll('#productsTable tr')]
    .filter(r=>r.style.display!=='none')
    .map(r=>[...r.children].map(c=>'"'+c.innerText.replace(/"/g,'""').replace(/\n/g,' ')+'"').join(','));
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([rows.join('\n')],{type:'text/csv'}));
  a.download='products.csv'; a.click(); URL.revokeObjectURL(a.href);
});

/* ── View modal ── */
const viewModal=document.getElementById('viewProductModal');
const viewBody=document.getElementById('viewProductBody');
function openView(){ if(viewModal){viewModal.style.display='flex';viewModal.classList.add('show');} }
function closeView(){ if(viewModal){viewModal.style.display='none';viewModal.classList.remove('show');} }
document.getElementById('closeViewProduct')?.addEventListener('click',closeView);
viewModal?.addEventListener('click',e=>{ if(e.target===viewModal) closeView(); });
document.querySelectorAll('[data-view-product]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    try{
      const p=JSON.parse(btn.dataset.viewProduct);
      const rows=[
        ['Name',p.name],['SKU',p.sku||'-'],['Barcode',p.barcode||'-'],
        ['Category',p.category||'-'],['Unit',p.unit||'-'],
        ['Quantity',p.quantity??0],
        ['Selling Price',p.unit_price?Number(p.unit_price).toLocaleString()+' XAF':'-'],
        ['Cost Price',p.cost_price&&p.cost_price>0?Number(p.cost_price).toLocaleString()+' XAF':'-'],
        ['Low Stock Level',p.low_stock_level||'0'],
        ['Expiration',p.expiration_date||'-'],['Status',p.status||'active']
      ];
      viewBody.innerHTML=rows.map(r=>`<div class="pr-view-row"><span>${r[0]}</span><strong>${r[1]??'-'}</strong></div>`).join('')
        +(p.description?`<div class="pr-view-row"><span>Description</span><strong>${p.description}</strong></div>`:'');
      openView();
    }catch(e){}
  });
});

/* ── Add product form validation ── */
document.getElementById('addProductForm')?.addEventListener('submit',e=>{
  const name=e.target.product_name?.value.trim();
  if(!name){ e.preventDefault(); alert(lang==='en'?'Product name is required.':'Le nom du produit est obligatoire.'); }
});
document.getElementById('addProductPageForm')?.addEventListener('submit',e=>{
  const name=document.getElementById('product_name')?.value.trim();
  const selCat=document.getElementById('category_select')?.value.trim();
  const custCat=document.getElementById('category_custom')?.value.trim();
  if(!name){ e.preventDefault(); alert(lang==='en'?'Product name is required.':'Le nom du produit est obligatoire.'); return; }
  if(!selCat&&!custCat){ e.preventDefault(); alert(lang==='en'?'Category is required.':'La catégorie est obligatoire.'); }
});

/* ── Add product page live preview ── */
(function productPreview(){
  function upd(){
    const get=id=>document.getElementById(id)?.value||'';
    const pv=id=>document.getElementById(id);
    const name=get('product_name'); if(pv('pv-name')) pv('pv-name').textContent=name||'—';
    const unit=get('unit'); if(pv('pv-unit')) pv('pv-unit').textContent=unit||'—';
    const bc=get('barcode'); if(pv('pv-barcode')) pv('pv-barcode').textContent=bc||'—';
    const sell=parseFloat(get('selling_price')||0);
    if(pv('pv-price')) pv('pv-price').textContent=sell?sell.toLocaleString('fr-FR')+' XAF':'0 XAF';
    const cat=(document.querySelector('[name="category_custom"]')?.value.trim())
           ||(document.querySelector('[name="category_select"]')?.value.trim())||'—';
    if(pv('pv-category')) pv('pv-category').textContent=cat;
  }
  ['product_name','unit','barcode','selling_price','category_select','category_custom'].forEach(n=>{
    document.querySelector(`[name="${n}"]`)?.addEventListener('input',upd);
    document.querySelector(`[name="${n}"]`)?.addEventListener('change',upd);
  });
  upd();
})();

/* ── Lang sync from other tabs ── */
window.addEventListener('storage',e=>{ if(e.key==='lt_lang'){ lang=e.newValue||'en'; applyLang(); }});

/* ── Init ── */
applyLang();