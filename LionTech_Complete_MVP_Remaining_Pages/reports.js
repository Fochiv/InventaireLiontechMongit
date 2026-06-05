(function(){
  const sidebar=document.getElementById('od-sidebar');
  const overlay=document.getElementById('od-overlay');
  function sbOpen(){if(sidebar){sidebar.classList.add('open');if(overlay)overlay.style.display='block';document.body.style.overflow='hidden';}}
  function sbClose(){if(sidebar){sidebar.classList.remove('open');if(overlay)overlay.style.display='none';document.body.style.overflow='';}}
  document.getElementById('rp-menu-btn')?.addEventListener('click',sbOpen);
  document.getElementById('od-sidebar-close')?.addEventListener('click',sbClose);
  overlay?.addEventListener('click',sbClose);

  const dict={
    fr:{page_title:'Rapports',page_subtitle:'Analyse inventaire, présence et mouvements de stock',expired_title:'Abonnement expiré',expired_text:"Vous pouvez consulter les rapports, mais les actions d'inventaire restent limitées jusqu'au renouvellement.",from:'Du',to:'Au',apply:'Appliquer',today:"Aujourd'hui",this_month:'Ce mois',this_year:'Cette année',products:'Produits',stock_in:'Stock entrant',stock_out:'Stock sortant',low_stock:'Stock faible',pending:'En attente',out_stock:'Rupture',stock_movement:'Mouvement de stock',stock_movement_sub:'Entrées vs sorties',top_products:'Produits les plus sortis',top_products_sub:'Selon la période choisie',low_stock_report:'Rapport stock faible',low_stock_sub:'Produits à réapprovisionner',attendance_report:'Rapport présence employés',attendance_sub:'Heures travaillées et retards',recent_movements:'Historique récent',recent_sub:'Dernières entrées et sorties de stock'},
    en:{page_title:'Reports',page_subtitle:'Inventory, attendance, and stock movement analysis',expired_title:'Subscription expired',expired_text:'You can view reports, but inventory actions are limited until renewal.',from:'From',to:'To',apply:'Apply',today:'Today',this_month:'This month',this_year:'This year',products:'Products',stock_in:'Stock In',stock_out:'Stock Out',low_stock:'Low Stock',pending:'Pending',out_stock:'Out of Stock',stock_movement:'Stock Movement',stock_movement_sub:'Incoming vs outgoing',top_products:'Most moved products',top_products_sub:'Based on selected period',low_stock_report:'Low Stock Report',low_stock_sub:'Products to restock',attendance_report:'Employee Attendance Report',attendance_sub:'Hours worked and late arrivals',recent_movements:'Recent History',recent_sub:'Latest stock in and stock out records'}
  };
  let lang=localStorage.getItem('lt_lang')||'fr';
  function applyLang(){
    document.documentElement.lang=lang;
    const btn=document.getElementById('rp-lang-btn');
    if(btn)btn.textContent=lang.toUpperCase();
    document.querySelectorAll('[data-i18n]').forEach(el=>{
      const k=el.dataset.i18n;
      if(dict[lang]&&dict[lang][k])el.textContent=dict[lang][k];
    });
  }
  document.getElementById('rp-lang-btn')?.addEventListener('click',()=>{
    lang=lang==='fr'?'en':'fr';
    localStorage.setItem('lt_lang',lang);
    applyLang();
  });
  applyLang();

  const charts=window.REPORT_CHARTS||{};
  if(window.Chart){
    const s=document.getElementById('stockChart');
    if(s){new Chart(s,{type:'doughnut',data:{labels:charts.stock?.labels||[],datasets:[{data:charts.stock?.values||[],backgroundColor:['#0B1F3A','#D9A441','#12B8A6','#8B5CF6','#EF4444','#F59E0B','#22C55E','#6366F1']}]},options:{plugins:{legend:{position:'bottom'}},responsive:true,cutout:'60%'}})}
    const t=document.getElementById('topChart');
    if(t){new Chart(t,{type:'bar',data:{labels:charts.top?.labels||[],datasets:[{label:'Qty',data:charts.top?.values||[],backgroundColor:'#0B1F3A',borderRadius:8}]},options:{plugins:{legend:{display:false}},responsive:true,scales:{y:{beginAtZero:true}}}})}
  }

  function ymd(d){return d.toISOString().slice(0,10)}
  document.getElementById('quick-today')?.addEventListener('click',()=>{const d=ymd(new Date());location.search='?from='+d+'&to='+d;});
  document.getElementById('quick-month')?.addEventListener('click',()=>{const now=new Date();const from=new Date(now.getFullYear(),now.getMonth(),1);location.search='?from='+ymd(from)+'&to='+ymd(now);});
  document.getElementById('quick-year')?.addEventListener('click',()=>{const now=new Date();const from=new Date(now.getFullYear(),0,1);location.search='?from='+ymd(from)+'&to='+ymd(now);});
  document.getElementById('print-pdf')?.addEventListener('click',()=>window.print());
  document.getElementById('export-csv')?.addEventListener('click',()=>{
    const rows=[...document.querySelectorAll('#report-table tr')].map(tr=>[...tr.children].map(td=>'"'+td.innerText.replace(/"/g,'""')+'"').join(','));
    const blob=new Blob([rows.join('\n')],{type:'text/csv'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='liontech_report.csv';a.click();URL.revokeObjectURL(a.href);
  });
})();
