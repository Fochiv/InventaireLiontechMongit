const translations={
  en:{nav_main:'Menu',nav_dashboard:'Dashboard',nav_employees:'Employees',nav_products:'Products',nav_stock_in:'Stock In',nav_stock_out:'Stock Out',nav_reports:'Reports',nav_settings:'Settings',nav_logout:'Logout',page_title:'Stock In',page_subtitle:'Record products that enter your business inventory.',add_stock_in:'Add Stock In',feature_locked_title:'Inventory feature not active',feature_locked_text:'Contact LionTech to enable inventory management for this business.',expired_warning:'Subscription expired. You can view Stock In history, but new actions are disabled.',no_products_warning:'No products found. Add products first before recording Stock In.',pending_requests:'Pending Requests',approved_today:'Approved Today',active_products:'Active Products',pending_quantity:'Pending Quantity',how_title:'How Stock In works',how_text:'Employees or stock managers submit delivered quantities. The owner or manager verifies and approves. Product quantity updates only after approval.',history_title:'Stock In Requests',history_subtitle:'Review deliveries, approvals, and pending stock entries.',search_ph:'Search product/supplier...',filter_all:'All',filter_pending:'Pending',filter_approved:'Approved',filter_rejected:'Rejected',th_product:'Product',th_quantity:'Quantity',th_supplier:'Supplier',th_date:'Date',th_by:'Entered By',th_status:'Status',th_proof:'Proof',th_actions:'Actions',approve:'Approve',reject:'Reject',waiting:'Waiting',empty:'No Stock In records yet.',modal_title:'Add Stock In',product_label:'Product',choose_product:'Choose product',quantity_label:'Quantity received',supplier_label:'Supplier / Delivered by',delivery_date_label:'Delivery date',proof_label:'Delivery proof / invoice photo',note_label:'Note',approval_note:'This stock entry will wait for owner/manager approval before inventory quantity changes.',cancel:'Cancel',submit_request:'Submit Stock In'},
  fr:{nav_main:'Menu',nav_dashboard:'Tableau de bord',nav_employees:'Employés',nav_products:'Produits',nav_stock_in:'Entrée Stock',nav_stock_out:'Sortie Stock',nav_reports:'Rapports',nav_settings:'Paramètres',nav_logout:'Déconnexion',page_title:'Entrée Stock',page_subtitle:'Enregistrer les produits qui entrent dans l’inventaire.',add_stock_in:'Ajouter une entrée',feature_locked_title:'Fonction inventaire non active',feature_locked_text:'Contactez LionTech pour activer l’inventaire pour ce business.',expired_warning:'Abonnement expiré. Vous pouvez voir l’historique, mais les actions sont désactivées.',no_products_warning:'Aucun produit trouvé. Ajoutez d’abord des produits avant d’enregistrer une entrée de stock.',pending_requests:'Demandes en attente',approved_today:'Approuvées aujourd’hui',active_products:'Produits actifs',pending_quantity:'Quantité en attente',how_title:'Comment fonctionne l’entrée de stock',how_text:'Les employés ou responsables stock soumettent les quantités livrées. Le propriétaire ou manager vérifie et approuve. La quantité du produit change seulement après approbation.',history_title:'Demandes d’entrée de stock',history_subtitle:'Vérifiez les livraisons, validations et entrées en attente.',search_ph:'Rechercher produit/fournisseur...',filter_all:'Tous',filter_pending:'En attente',filter_approved:'Approuvé',filter_rejected:'Rejeté',th_product:'Produit',th_quantity:'Quantité',th_supplier:'Fournisseur',th_date:'Date',th_by:'Entré par',th_status:'Statut',th_proof:'Preuve',th_actions:'Actions',approve:'Approuver',reject:'Rejeter',waiting:'En attente',empty:'Aucune entrée de stock pour le moment.',modal_title:'Ajouter une entrée de stock',product_label:'Produit',choose_product:'Choisir un produit',quantity_label:'Quantité reçue',supplier_label:'Fournisseur / Livré par',delivery_date_label:'Date de livraison',proof_label:'Preuve de livraison / photo facture',note_label:'Note',approval_note:'Cette entrée attendra la validation du propriétaire/manager avant de changer la quantité.',cancel:'Annuler',submit_request:'Soumettre'}
};

let lang=localStorage.getItem('lt_lang')||'fr';

function applyLang(){
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const k=el.dataset.i18n;
    if(translations[lang][k]) el.textContent=translations[lang][k];
  });

  document.querySelectorAll('[data-i18n-ph]').forEach(el=>{
    const k=el.dataset.i18nPh;
    if(translations[lang][k]) el.placeholder=translations[lang][k];
  });

  const btn=document.getElementById('si-lang');
  if(btn) btn.textContent=lang==='en'?'FR':'EN';
}

document.addEventListener('DOMContentLoaded',()=>{
  applyLang();

  const sidebar=document.getElementById('si-sidebar');
  const overlay=document.getElementById('si-overlay');

  function openSide(){
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
  }

  function closeSide(){
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
  }

  document.getElementById('si-hamburger')?.addEventListener('click',openSide);
  document.getElementById('si-sidebar-close')?.addEventListener('click',closeSide);
  overlay?.addEventListener('click',closeSide);

  document.getElementById('si-lang')?.addEventListener('click',()=>{
    lang=lang==='en'?'fr':'en';
    localStorage.setItem('lt_lang',lang);
    applyLang();
  });

  const modal=document.getElementById('stockInModal');

  function openModal(){
    if(!modal) return;
    modal.style.display='flex';
    modal.setAttribute('aria-hidden','false');
  }

  function closeModal(){
    if(!modal) return;
    modal.style.display='none';
    modal.setAttribute('aria-hidden','true');
  }

  document.getElementById('openStockIn')?.addEventListener('click',openModal);
  document.getElementById('closeStockIn')?.addEventListener('click',closeModal);
  document.getElementById('cancelStockIn')?.addEventListener('click',closeModal);

  modal?.addEventListener('click',e=>{
    if(e.target===modal) closeModal();
  });

  document.getElementById('stockInForm')?.addEventListener('submit',e=>{
    const q=e.target.querySelector('input[name="quantity"]');
    if(!q.value||parseFloat(q.value)<=0){
      e.preventDefault();
      alert(lang==='fr'?'La quantité doit être supérieure à zéro.':'Quantity must be greater than zero.');
    }
  });

  const search=document.getElementById('si-search');
  const filter=document.getElementById('si-status-filter');

  function filterRows(){
    const s=(search?.value||'').toLowerCase().trim();
    const f=filter?.value||'all';

    document.querySelectorAll('#si-table tbody tr[data-status]').forEach(row=>{
      const okS=!s||row.dataset.search.includes(s);
      const okF=f==='all'||row.dataset.status===f;
      row.style.display=okS&&okF?'':'none';
    });
  }

  search?.addEventListener('input',filterRows);
  filter?.addEventListener('change',filterRows);

  document.querySelectorAll('.reject-form').forEach(form=>{
    form.addEventListener('submit',e=>{
      const reason=prompt(lang==='fr'?'Raison du rejet?':'Reason for rejection?');
      if(reason===null){
        e.preventDefault();
        return;
      }
      form.querySelector('input[name="rejection_reason"]').value=reason||'Rejected after verification';
    });
  });
});