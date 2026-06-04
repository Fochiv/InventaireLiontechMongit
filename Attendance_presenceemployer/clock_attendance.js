const translations = {
  fr: {
    nav_dashboard:'Dashboard', nav_clock:'Clock In/Out', nav_stock_in:'Stock In', nav_stock_out:'Stock Out', nav_products:'Produits', nav_profile:'Profil', nav_logout:'Déconnexion',
    title:'Clock In / Clock Out', subtitle:'Enregistrez votre présence au travail', expired_warning:'Votre abonnement est expiré. Vous pouvez consulter cette page, mais les actions sont désactivées.',
    presence_system:'Système de présence', currently_in:'Vous êtes clocké(e) in', ready:'Prêt(e) à commencer?', hero_text:'Le système enregistre l’heure réelle, la localisation GPS et garde un historique non modifiable.', current_time:'Heure actuelle', current_status:'Statut actuel', today_sessions:'Sessions aujourd’hui', completed_hours:'Heures complétées', gps_radius:'Rayon GPS', clock_action:'Action de présence', clock_action_sub:'Autorisez la localisation avant de cliquer.', gps_waiting:'GPS en attente...', clocked_since:'Clock in depuis', clock_out:'Clock Out', clock_in:'Clock In', locked_note:'Les heures enregistrées ne peuvent pas être modifiées directement par l’employé ou l’employeur.', how_it_works:'Comment ça marche', step1:'L’employé se connecte sur son téléphone.', step2:'Il autorise la localisation GPS.', step3:'Il clique Clock In en arrivant et Clock Out en partant.', step4:'Le patron voit la présence et les heures travaillées.', history:'Historique de présence', history_sub:'Vos dernières entrées de présence.', date:'Date', clock_in_col:'Clock In', clock_out_col:'Clock Out', duration:'Durée', gps_status_col:'GPS', status:'Statut', no_history:'Aucun historique pour le moment.'
  },
  en: {
    nav_dashboard:'Dashboard', nav_clock:'Clock In/Out', nav_stock_in:'Stock In', nav_stock_out:'Stock Out', nav_products:'Products', nav_profile:'Profile', nav_logout:'Logout',
    title:'Clock In / Clock Out', subtitle:'Record your work attendance', expired_warning:'Your subscription is expired. You can view this page, but actions are disabled.',
    presence_system:'Attendance system', currently_in:'You are clocked in', ready:'Ready to start?', hero_text:'The system records the real time, GPS location, and keeps a locked history.', current_time:'Current time', current_status:'Current status', today_sessions:'Today sessions', completed_hours:'Completed hours', gps_radius:'GPS radius', clock_action:'Attendance action', clock_action_sub:'Allow location before clicking.', gps_waiting:'Waiting for GPS...', clocked_since:'Clocked in since', clock_out:'Clock Out', clock_in:'Clock In', locked_note:'Recorded times cannot be directly changed by the employee or employer.', how_it_works:'How it works', step1:'Employee logs in on their phone.', step2:'They allow GPS location.', step3:'They click Clock In when arriving and Clock Out when leaving.', step4:'The owner sees attendance and worked hours.', history:'Attendance history', history_sub:'Your latest attendance records.', date:'Date', clock_in_col:'Clock In', clock_out_col:'Clock Out', duration:'Duration', gps_status_col:'GPS', status:'Status', no_history:'No history yet.'
  }
};
let currentLang = localStorage.getItem('lt_lang') || 'fr';
function applyLang(){
  document.documentElement.lang = currentLang;
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const key = el.dataset.i18n;
    if(translations[currentLang][key]) el.textContent = translations[currentLang][key];
  });
  const btn = document.getElementById('langBtn');
  if(btn) btn.textContent = currentLang === 'fr' ? 'EN' : 'FR';
}
document.getElementById('langBtn')?.addEventListener('click',()=>{currentLang=currentLang==='fr'?'en':'fr';localStorage.setItem('lt_lang',currentLang);applyLang();});
applyLang();

document.getElementById('menuBtn')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));

function updateClock(){
  const now = new Date();
  const el = document.getElementById('liveClock');
  if(el) el.textContent = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
}
setInterval(updateClock,1000); updateClock();

function setGpsStatus(message, status='warn'){
  const box = document.getElementById('gpsStatus');
  if(!box) return;
  box.className = 'gps-status ' + status;
  box.innerHTML = '📍 ' + message;
}

function fillGPS(pos){
  const lat = pos.coords.latitude;
  const lng = pos.coords.longitude;
  const acc = pos.coords.accuracy;
  document.querySelectorAll('.gps-lat').forEach(i=>i.value=lat);
  document.querySelectorAll('.gps-lng').forEach(i=>i.value=lng);
  document.querySelectorAll('.gps-accuracy').forEach(i=>i.value=acc);

  const s = window.LT_ATTENDANCE_SETTINGS || {};
  if(s.businessLat && s.businessLng){
    const distance = estimateDistance(lat,lng,s.businessLat,s.businessLng);
    if(distance <= s.gpsRadius){
      setGpsStatus(`On site — about ${Math.round(distance)}m from business`, 'good');
    } else if(distance <= (s.gpsRadius + (s.reviewBuffer || 300))){
      setGpsStatus(`Slightly outside — about ${Math.round(distance)}m. May need review.`, 'warn');
    } else {
      setGpsStatus(`Too far — about ${Math.round(distance)}m from business`, 'bad');
    }
  } else {
    setGpsStatus(`GPS captured. Accuracy: ±${Math.round(acc)}m`, 'good');
  }
}
function gpsError(err){
  setGpsStatus('GPS not available. Please allow location on your phone/browser.', 'warn');
}
function estimateDistance(lat1,lng1,lat2,lng2){
  const R=6371000, toRad=x=>x*Math.PI/180;
  const dLat=toRad(lat2-lat1), dLng=toRad(lng2-lng1);
  const a=Math.sin(dLat/2)**2+Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)**2;
  return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
}
if(navigator.geolocation){
  navigator.geolocation.getCurrentPosition(fillGPS,gpsError,{enableHighAccuracy:true,timeout:12000,maximumAge:30000});
  navigator.geolocation.watchPosition(fillGPS,gpsError,{enableHighAccuracy:true,timeout:12000,maximumAge:30000});
} else gpsError();
