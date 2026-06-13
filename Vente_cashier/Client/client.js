/* client.js — Tally Client Portal shared JS */
'use strict';

/* ── Language ── */
let CL_LANG = localStorage.getItem('cl_lang') || 'fr';

function toggleLang(){
  CL_LANG = CL_LANG === 'fr' ? 'en' : 'fr';
  localStorage.setItem('cl_lang', CL_LANG);
  applyLang();
}

function applyLang(){
  const T = I18N[CL_LANG] || I18N.fr;
  const btn = document.getElementById('langBtn');
  if(btn) btn.textContent = CL_LANG === 'fr' ? 'EN' : 'FR';

  /* data-i = key for textContent */
  document.querySelectorAll('[data-i]').forEach(el => {
    const k = el.dataset.i;
    if(T[k] !== undefined) el.textContent = T[k];
  });
  /* data-i-ph = key for placeholder */
  document.querySelectorAll('[data-i-ph]').forEach(el => {
    const k = el.dataset.iPh;
    if(T[k] !== undefined) el.placeholder = T[k];
  });
  /* Save/unsave dual labels */
  document.querySelectorAll('[data-i-save]').forEach(el => {
    const btn = el.closest('.db-act-save');
    if(!btn) return;
    const k = btn.classList.contains('is-saved') ? (el.dataset.iUnsave||'saved') : (el.dataset.iSave||'save');
    if(T[k]) el.textContent = T[k];
  });
  /* <html lang> */
  document.documentElement.lang = CL_LANG;
}

document.addEventListener('DOMContentLoaded', applyLang);

/* ── API helper ── */
function clApi(data){
  const base = (window.CL_API_URL || '') || _findApiUrl();
  return fetch(base, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  }).then(r => r.json()).catch(() => ({success:false,message:'Network error'}));
}

function _findApiUrl(){
  /* Auto-detect: same folder as current page */
  const parts = window.location.pathname.split('/');
  parts[parts.length - 1] = 'client_api.php';
  return parts.join('/');
}

/* ── Toast ── */
let _toastTimer;
function clToast(msg, dur=2600){
  const el = document.getElementById('clToast');
  if(!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => el.classList.remove('show'), dur);
}

/* ── Phone formatter ── */
function formatPhone(input){
  let v = input.value.replace(/[^\d\+]/g,'');
  if(v && !v.startsWith('+') && !v.startsWith('00') && v.length >= 9) v = '+237' + v;
  input.value = v;
}

/* ── Service Worker ── */
if('serviceWorker' in navigator){
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js')
      .catch(() => {});
  });
}