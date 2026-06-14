const I18N = {
  fr: {
    nav_dashboard:'Dashboard',nav_stock_in:'Stock entrant',nav_stock_out:'Stock sortant',nav_products:'Produits',nav_attendance:'Présence',nav_tasks:'Tâches',nav_profile:'Profil',nav_logout:'Déconnexion',
    title:'Dashboard Employé',sub_expired:'L’abonnement du business est expiré. Les actions sont limitées.',pin_warning:'Vous utilisez encore un PIN temporaire. Changez votre PIN dans le profil.',welcome_back:'Bienvenue',hero_text:'Gérez votre présence, vos tâches et vos actions d’inventaire depuis votre téléphone.',status:'Statut',today_records:'Présences aujourd’hui',visible_products:'Produits visibles',open_tasks:'Tâches ouvertes',pending_actions:'Actions en attente',clock_title:'Clock In / Clock Out',clock_sub:'Le système utilise l’heure du serveur. L’employé ne peut pas modifier l’heure.',clock_in:'Clock In',clock_out:'Clock Out',gps_note:'Si le GPS est légèrement hors zone, l’action peut être marquée pour validation.',quick_actions:'Actions rapides',add_stock_in:'Ajouter stock entrant',add_stock_out:'Ajouter stock sortant',view_products:'Voir produits',change_pin:'Changer PIN',tasks_title:'Mes tâches',tasks_sub:'Tâches assignées par le manager ou le propriétaire.',products_title:'Produits visibles',products_sub:'Lecture seule. Les employés ne peuvent pas modifier les produits.',history_title:'Historique de présence',profile_title:'Profil & PIN',profile_sub:'L’employé peut changer son PIN, mais ne peut pas modifier ses heures de présence.'
  },
  en: {
    nav_dashboard:'Dashboard',nav_stock_in:'Stock In',nav_stock_out:'Stock Out',nav_products:'Products',nav_attendance:'Attendance',nav_tasks:'Tasks',nav_profile:'Profile',nav_logout:'Logout',
    title:'Employee Dashboard',sub_expired:'The business subscription is expired. Actions are limited.',pin_warning:'You are still using a temporary PIN. Change your PIN in your profile.',welcome_back:'Welcome back',hero_text:'Manage your attendance, tasks, and inventory actions from your phone.',status:'Status',today_records:'Today attendance records',visible_products:'Visible products',open_tasks:'Open tasks',pending_actions:'Pending actions',clock_title:'Clock In / Clock Out',clock_sub:'The system uses server time. Employees cannot edit the time.',clock_in:'Clock In',clock_out:'Clock Out',gps_note:'If GPS is slightly outside the zone, the action may be marked for approval.',quick_actions:'Quick actions',add_stock_in:'Add stock in',add_stock_out:'Add stock out',view_products:'View products',change_pin:'Change PIN',tasks_title:'My tasks',tasks_sub:'Tasks assigned by the manager or owner.',products_title:'Visible products',products_sub:'Read-only. Employees cannot edit products.',history_title:'Attendance history',profile_title:'Profile & PIN',profile_sub:'Employees can change their PIN, but cannot edit attendance times.'
  }
};
let currentLang = localStorage.getItem('lt_lang') || 'fr';
function applyLang(){
  document.querySelectorAll('[data-i18n]').forEach(el=>{ const k=el.dataset.i18n; if(I18N[currentLang][k]) el.textContent=I18N[currentLang][k]; });
  const btn=document.getElementById('lang-btn'); if(btn) btn.textContent=currentLang==='fr'?'EN':'FR';
  document.documentElement.lang=currentLang;
}
document.getElementById('lang-btn')?.addEventListener('click',()=>{ currentLang=currentLang==='fr'?'en':'fr'; localStorage.setItem('lt_lang',currentLang); applyLang(); });
applyLang();

document.getElementById('menu-btn')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));
document.addEventListener('click',e=>{ if(window.innerWidth<850 && !e.target.closest('.ed-sidebar') && !e.target.closest('#menu-btn')) document.body.classList.remove('sidebar-open'); });

const gpsStatus = document.getElementById('gps-status');
function setGPSStatus(text, ok=false){ if(gpsStatus){ gpsStatus.textContent = text; gpsStatus.style.color = ok ? '#166534' : '#92400e'; } }
function captureGeo(){
  if(!navigator.geolocation){ setGPSStatus('GPS non supporté sur cet appareil'); return; }
  navigator.geolocation.getCurrentPosition(pos=>{
    document.querySelectorAll('.lat').forEach(i=>i.value=pos.coords.latitude);
    document.querySelectorAll('.lng').forEach(i=>i.value=pos.coords.longitude);
    document.querySelectorAll('.acc').forEach(i=>i.value=pos.coords.accuracy);
    setGPSStatus(`GPS prêt · précision ${Math.round(pos.coords.accuracy)}m`, true);
  }, err=>{
    setGPSStatus('GPS refusé ou indisponible. L\'action peut nécessiter une validation.');
  }, {enableHighAccuracy:true, timeout:12000, maximumAge:60000});
}
captureGeo();

document.querySelectorAll('.geo-form').forEach(form=>{
  form.addEventListener('submit', e=>{
    const lat = form.querySelector('.lat')?.value;
    const lng = form.querySelector('.lng')?.value;
    if(!lat || !lng){
      const proceed = confirm('GPS is not ready. Continue anyway? This may require manager approval.');
      if(!proceed) e.preventDefault();
    }
  });
});
