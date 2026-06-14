/* ============================================================
   Vente.js — LionTech Caisse POS (Combined)
   Sessions · Mixed payments · Discounts · Offline · Barcode
   ============================================================ */
'use strict';

/* ── i18n ── */
const i18n = {
  fr:{
    search_ph:'Chercher un produit...', scan_hint:'Pointez la caméra vers le code-barres',
    empty_title:'Recherchez ou scannez un produit', empty_sub:'Scan or search to add products',
    no_prod:'Aucun produit trouvé', out:'Rupture', low:'Stock faible',
    cart:'Panier', cart_empty:'Panier vide',
    sub:'Sous-total', discount:'Remise', tva:'TVA', total:'TOTAL TTC',
    pay_ttl:'PAIEMENT', pay_remaining:'Reste à payer',
    cash:'Espèces', mtn:'MTN MoMo', orange:'Orange Money',
    given:'Montant donné', change:'Monnaie à rendre',
    ref_ph:'Référence transaction',
    client:'CLIENT', opt:'Optionnel',
    name_ph:'Nom du client', phone_ph:'+237 6XX XXX XXX',
    recognized:'Client reconnu',
    note_ph:'Note (optionnel)...',
    validate:'Valider la vente',
    invoice:'N° Facture', cashier:'Caissier',
    wa_btn:'Envoyer sur WhatsApp',
    print_btn:'Imprimer la facture',
    new_sale:'Nouvelle vente',
    offline:'Hors ligne — ventes en attente',
    syncing:'Synchronisation...', synced:'Synchronisé',
    lock_title:'Session verrouillée', lock_sub:'Entrez votre PIN',
    lock_btn:'Déverrouiller', lock_err:'PIN incorrect',
    clear:'Vider le panier', thanks:'Merci pour votre achat !',
    powered:'LionTech Business Manager',
    saved:'Vente enregistrée', sale_err:'Erreur vente',
    no_stock:'Stock insuffisant', sel_pay:'Sélectionnez un mode de paiement',
    pay_short:'Montant insuffisant', empty_warn:'Panier vide',
    articles:'article(s)', session_label:'Session',
    fond:'Fond de caisse', open_session:'Ouvrir la caisse →',
    close_session:'Fermer la caisse',
    session_summary:'Résumé de la session',
    disc_pct:'%', disc_fix:'XAF fixe',
  },
  en:{
    search_ph:'Search product...', scan_hint:'Point camera at barcode',
    empty_title:'Search or scan a product', empty_sub:'Scan or search to add products',
    no_prod:'No products found', out:'Out of stock', low:'Low stock',
    cart:'Cart', cart_empty:'Cart empty',
    sub:'Subtotal', discount:'Discount', tva:'VAT', total:'TOTAL',
    pay_ttl:'PAYMENT', pay_remaining:'Remaining',
    cash:'Cash', mtn:'MTN MoMo', orange:'Orange Money',
    given:'Amount given', change:'Change',
    ref_ph:'Transaction reference',
    client:'CLIENT', opt:'Optional',
    name_ph:'Client name', phone_ph:'+237 6XX XXX XXX',
    recognized:'Client recognized',
    note_ph:'Note (optional)...',
    validate:'Complete sale',
    invoice:'Invoice', cashier:'Cashier',
    wa_btn:'Send receipt on WhatsApp',
    print_btn:'Print invoice',
    new_sale:'New sale',
    offline:'Offline — pending sales',
    syncing:'Syncing...', synced:'Synced',
    lock_title:'Session locked', lock_sub:'Enter your PIN',
    lock_btn:'Unlock', lock_err:'Wrong PIN',
    clear:'Clear cart', thanks:'Thank you for your purchase!',
    powered:'LionTech Business Manager',
    saved:'Sale saved', sale_err:'Sale error',
    no_stock:'Insufficient stock', sel_pay:'Select a payment method',
    pay_short:'Insufficient amount', empty_warn:'Cart is empty',
    articles:'item(s)', session_label:'Session',
    fond:'Opening cash', open_session:'Open register →',
    close_session:'Close register',
    session_summary:'Session summary',
    disc_pct:'%', disc_fix:'Fixed XAF',
  }
};

/* ── State ── */
let lang       = 'fr';
let products   = [];
let cart       = [];
let lastSale   = null;
let sessionId  = null;
let sessionFond= 0;
let discType   = 'none';  /* none | pct | fix */
let discValue  = 0;
let activePays = {};      /* { especes:{amount,ref}, mtn_momo:{amount,ref}, orange_money:{amount,ref} } */
let isOnline   = navigator.onLine;
let lockTimer  = null;
let scanner    = null;
let db         = null;
const POS      = window.POS || {};
const LOCK_MS  = 5 * 60 * 1000;

const t   = k  => i18n[lang][k] || k;
const $   = id => document.getElementById(id);
const fmt = n  => Number(n).toLocaleString('fr-FR') + ' XAF';

/* ── IndexedDB ── */
function openDB(){
  return new Promise((res,rej)=>{
    const r = indexedDB.open('LionTechPOS',1);
    r.onupgradeneeded = e => {
      const d = e.target.result;
      if(!d.objectStoreNames.contains('q'))
        d.createObjectStore('q',{keyPath:'offline_id'});
    };
    r.onsuccess = e => { db=e.target.result; res(); };
    r.onerror   = () => rej(r.error);
  });
}
function qSave(s){ if(!db)return; db.transaction('q','readwrite').objectStore('q').put(s); }
function qGet(){ return new Promise(res=>{ if(!db)return res([]); const r=db.transaction('q','readonly').objectStore('q').getAll(); r.onsuccess=()=>res(r.result||[]); r.onerror=()=>res([]); }); }
function qDel(id){ if(!db)return; db.transaction('q','readwrite').objectStore('q').delete(id); }

/* ── Online/Offline ── */
function setOnline(){
  isOnline = navigator.onLine;
  const d = $('onlineDot');
  if(d) d.className = 'online-dot'+(isOnline?' on':'');
  if(isOnline) syncQ(); else showBanner(t('offline'),'err');
}
async function syncQ(){
  const q = await qGet();
  if(!q.length){ hideBanner(); return; }
  showBanner(t('syncing'),'sync');
  for(const s of q){
    try{
      const r = await fetch(POS.apiUrl+'?action=save_sale',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(s)});
      const j = await r.json();
      if(j.success||j.duplicate) qDel(s.offline_id);
    }catch(e){}
  }
  const left = await qGet();
  if(!left.length){ showBanner(t('synced'),'synced'); setTimeout(hideBanner,3000); }
}
function showBanner(m,type){ const b=$('offBanner'); if(!b)return; b.textContent=m; b.className='off-banner show '+type; }
function hideBanner(){ const b=$('offBanner'); if(b) b.className='off-banner'; }

/* ── Lock screen ── */
function resetLock(){ clearTimeout(lockTimer); lockTimer=setTimeout(doLock,LOCK_MS); }
function doLock(){ $('lockScreen').classList.remove('lock-hidden'); $('lockPin').value=''; $('lockErr').textContent=''; $('lockPin').focus(); }
function doUnlock(){
  const pin = $('lockPin').value;
  fetch(POS.apiUrl+'?action=verify_pin',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pin})})
  .then(r=>r.json()).then(j=>{
    if(j.success){ $('lockScreen').classList.add('lock-hidden'); resetLock(); }
    else{ $('lockErr').textContent=t('lock_err'); $('lockPin').value=''; $('lockPin').focus(); }
  }).catch(()=>{ $('lockScreen').classList.add('lock-hidden'); resetLock(); });
}
['click','keydown','touchstart'].forEach(ev=>document.addEventListener(ev,resetLock,{passive:true}));

/* ── Caisse code screen ── */
function showCodeScreen(){
  $('caisseCodeScreen').classList.remove('lock-hidden');
  $('caisseCodeInput').focus();
  /* Bind button now — finishInit() hasn't run yet */
  const btn=$('caisseCodeBtn');
  if(btn&&!btn.dataset.b){ btn.dataset.b='1'; btn.addEventListener('click',tryCode); }
  const inp=$('caisseCodeInput');
  if(inp&&!inp.dataset.b){ inp.dataset.b='1'; inp.addEventListener('keydown',e=>{ if(e.key==='Enter') tryCode(); }); }
}
async function tryCode(){
  const code = $('caisseCodeInput').value.trim();
  const btn  = $('caisseCodeBtn');
  if(!code){ $('caisseCodeErr').textContent='Code requis'; return; }
  btn.disabled=true; btn.textContent='Vérification...';
  try{
    const r = await fetch(POS.apiUrl+'?action=verify_caisse_code',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({code})});
    const j = await r.json();
    if(j.success){ $('caisseCodeScreen').classList.add('lock-hidden'); showSessionScreen(); }
    else{ $('caisseCodeErr').textContent=j.message||'Code incorrect'; $('caisseCodeInput').value=''; $('caisseCodeInput').focus(); btn.disabled=false; btn.textContent='Ouvrir la caisse'; }
  }catch(e){ $('caisseCodeScreen').classList.add('lock-hidden'); showSessionScreen(); }
}

/* ── Session screen ── */
function showSessionScreen(){
  $('sessionScreen').classList.remove('lock-hidden');
  $('fondCaisse').focus();
  /* Bind button now — finishInit() hasn't run yet */
  const btn=$('openSessionBtn');
  if(btn&&!btn.dataset.b){ btn.dataset.b='1'; btn.addEventListener('click',tryOpenSession); }
  const inp=$('fondCaisse');
  if(inp&&!inp.dataset.b){ inp.dataset.b='1'; inp.addEventListener('keydown',e=>{ if(e.key==='Enter') tryOpenSession(); }); }
}
async function tryOpenSession(){
  const fond = parseFloat($('fondCaisse').value||0);
  const btn  = $('openSessionBtn');
  btn.disabled=true; btn.textContent='Ouverture...';
  try{
    const r = await fetch(POS.apiUrl+'?action=open_session',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({fond_caisse:fond})});
    const j = await r.json();
    if(j.success){
      sessionId  = j.session_id;
      sessionFond= j.fond;
      $('sessionScreen').classList.add('lock-hidden');
      $('closeSessionBtn').style.display='flex';
      updateBarSub();
      await finishInit();
    }else{ $('sessionErr').textContent=j.message||'Erreur'; btn.disabled=false; btn.textContent=t('open_session'); }
  }catch(e){
    /* Offline — continue without session */
    sessionId=null;
    $('sessionScreen').classList.add('lock-hidden');
    await finishInit();
  }
}

function updateBarSub(){
  const sub = $('barSub');
  if(!sub) return;
  const ico = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
  sub.innerHTML = sessionId
    ? `${ico} ${POS.cashier} · Session ouverte`
    : `${ico} ${POS.cashier}`;
}

/* ── Close session ── */
async function requestCloseSession(){
  if(!sessionId){ toast('Aucune session ouverte','err'); return; }
  /* Show summary */
  try{
    const r = await fetch(POS.apiUrl+'?action=get_session');
    const j = await r.json();
    const s = j.session || {};
    $('closeSessionSummary').innerHTML =
      `<div style="font-size:13px;line-height:2">
        <b>Ventes totales:</b> ${fmt(s.total_ventes||0)}<br>
        <b>Espèces:</b> ${fmt(s.total_especes||0)}<br>
        <b>MTN MoMo:</b> ${fmt(s.total_mtn||0)}<br>
        <b>Orange Money:</b> ${fmt(s.total_orange||0)}<br>
        <b>Fond de caisse:</b> ${fmt(s.fond_ouverture||0)}
      </div>`;
  }catch(e){}
  $('closeSessionModal').classList.add('open');
}
async function confirmCloseSession(){
  const btn = $('confirmCloseSession');
  btn.disabled=true; btn.textContent='Fermeture...';
  try{
    const r = await fetch(POS.apiUrl+'?action=close_session',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({session_id:sessionId})});
    const j = await r.json();
    if(j.success){
      closeModal('closeSessionModal');
      sessionId=null;
      $('closeSessionBtn').style.display='none';
      toast('Caisse fermée','ok');
      updateBarSub();
    }else{ $('closeSessionErr').textContent=j.message||'Erreur'; btn.disabled=false; btn.textContent='Fermer la caisse'; }
  }catch(e){ $('closeSessionErr').textContent='Erreur réseau'; btn.disabled=false; btn.textContent='Fermer la caisse'; }
}

/* ── Product search (only on demand) ── */
let searchTimer = null;
function onSearch(q){
  clearTimeout(searchTimer);
  const term = q.trim();
  if(!term){ showEmptyState(); return; }
  searchTimer = setTimeout(()=>searchProducts(term), 300);
}
async function searchProducts(q){
  try{
    const r = await fetch(POS.apiUrl+'?action=get_products&q='+encodeURIComponent(q));
    const j = await r.json();
    if(j.success) renderProducts(j.products, q);
  }catch(e){}
}

function showEmptyState(){
  $('prodEmptyState').style.display='flex';
  $('prodGrid').style.display='none';
}
function hideEmptyState(){
  $('prodEmptyState').style.display='none';
  $('prodGrid').style.display='grid';
}

/* ── Render products ── */
function renderProducts(list, query=''){
  const g = $('prodGrid');
  if(!g) return;
  if(!list.length){
    hideEmptyState();
    g.style.display='block';
    g.innerHTML=`<div class="prod-none">${t('no_prod')}${query?' pour "'+query+'"':''}</div>`;
    return;
  }
  hideEmptyState();
  g.innerHTML = list.map(p=>{
    const ic  = cart.find(c=>c.product_id==p.product_id);
    const qty = parseFloat(p.quantity);
    const low = parseFloat(p.low_stock_level||2);
    const out = qty<=0, lo=!out&&qty<=low;
    const img = p.image_url
      ? `<img src="../${p.image_url}" alt="${p.name}" loading="lazy"/>`
      : `<div class="prod-emoji"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>`;
    return `<div class="prod-card${out?' no-stock':''}${ic?' in-cart':''}"
      data-id="${p.product_id}" onclick="addToCart(${p.product_id},${JSON.stringify(p).replace(/"/g,'&quot;')})"
      role="button" tabindex="0">
      <div class="prod-badge">${ic?ic.quantity:''}</div>
      <div class="prod-img-box">${img}</div>
      <div class="prod-name">${p.name}</div>
      <div class="prod-price">${fmt(p.unit_price)}</div>
      <div class="prod-stock ${out?'out':lo?'low':'ok'}">${out?t('out'):lo?'! '+t('low'):qty+' '+(p.unit||'')}</div>
    </div>`;
  }).join('');
}

/* ── Cart ── */
function addToCart(id, p){
  if(parseFloat(p.quantity)<=0){ toast(t('no_stock'),'err'); return; }
  const ex = cart.find(c=>c.product_id==id);
  if(ex){
    if(ex.quantity>=parseFloat(p.quantity)){ toast(t('no_stock'),'err'); return; }
    ex.quantity++; ex.total=ex.quantity*ex.unit_price;
  } else {
    cart.push({product_id:p.product_id,product_name:p.name,sku:p.sku||'',unit_price:parseFloat(p.unit_price),quantity:1,total:parseFloat(p.unit_price),unit:p.unit||''});
  }
  renderAll(); openPanel();
}
function chgQty(id,d){
  const it=cart.find(c=>c.product_id==id); if(!it)return;
  it.quantity+=d;
  if(it.quantity<=0) cart=cart.filter(c=>c.product_id!=id);
  else it.total=it.quantity*it.unit_price;
  renderAll();
}
function delItem(id){ cart=cart.filter(c=>c.product_id!=id); renderAll(); }
function clearCart(){ cart=[]; discType='none'; discValue=0; activePays={}; renderAll(); closePanel(); }

function totals(){
  const rawSub = cart.reduce((s,i)=>s+i.total,0);
  let remiseMontant = 0;
  if(discType==='pct' && discValue>0) remiseMontant = rawSub*(discValue/100);
  if(discType==='fix' && discValue>0) remiseMontant = Math.min(discValue,rawSub);
  const sub      = rawSub - remiseMontant;
  const tvaAmt   = POS.tvaOn ? sub*(POS.tvaRate/100) : 0;
  const total    = sub + tvaAmt;
  const paid     = Object.values(activePays).reduce((s,p)=>s+(parseFloat(p.amount)||0),0);
  const remaining= Math.max(0, total-paid);
  const change   = activePays.especes ? Math.max(0,(parseFloat(activePays.especes.amount)||0)-(total-paidExcept('especes'))) : 0;
  return {rawSub, remiseMontant, sub, tvaAmt, total, paid, remaining, change};
}
function paidExcept(mode){
  return Object.entries(activePays).filter(([k])=>k!==mode).reduce((s,[,p])=>s+(parseFloat(p.amount)||0),0);
}

/* ── Discount ── */
function setDisc(type){
  discType=type;
  document.querySelectorAll('.disc-btn').forEach(b=>b.classList.remove('active'));
  if(type!=='none'){
    const btn=document.querySelector('.disc-btn[data-type="'+type+'"]');
    if(btn) btn.classList.add('active');
  }
  renderAll();
}
function onDiscInput(val){ discValue=parseFloat(val)||0; renderAll(); }

/* ── Payment methods ── */
function togglePay(mode){
  if(activePays[mode]) delete activePays[mode];
  else activePays[mode]={amount:'',ref:''};
  renderAll();
}
function onPayAmount(mode,val){
  if(!activePays[mode]) activePays[mode]={amount:'',ref:''};
  activePays[mode].amount=val;
  renderAll();
}
function onPayRef(mode,val){
  if(!activePays[mode]) activePays[mode]={amount:'',ref:''};
  activePays[mode].ref=val;
}

/* ── Build cart HTML ── */
function cartItemsHTML(){
  if(!cart.length) return `<div class="cart-empty-msg">${t('cart_empty')}</div>`;
  return `<div class="cart-items-wrap">`+cart.map(it=>`
    <div class="ci">
      <div class="ci-info">
        <div class="ci-name">${it.product_name}</div>
        <div class="ci-up">${fmt(it.unit_price)}</div>
      </div>
      <div class="ci-qty">
        <button class="ci-qbtn" onclick="chgQty(${it.product_id},-1)">−</button>
        <span class="ci-qnum">${it.quantity}</span>
        <button class="ci-qbtn" onclick="chgQty(${it.product_id},1)">+</button>
      </div>
      <div class="ci-total">${fmt(it.total)}</div>
      <button class="ci-del" onclick="delItem(${it.product_id})"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
    </div>`).join('')+`</div>`;
}

function discountHTML(){
  const {rawSub,remiseMontant}=totals();
  return `<div class="disc-section">
    <div class="disc-label">${t('discount')}</div>
    <div class="disc-btns">
      <button class="disc-btn${discType==='none'?' active':''}" data-type="none" onclick="setDisc('none')" type="button">Aucune</button>
      <button class="disc-btn${discType==='pct'?' active':''}"  data-type="pct"  onclick="setDisc('pct')"  type="button">${t('disc_pct')}</button>
      <button class="disc-btn${discType==='fix'?' active':''}"  data-type="fix"  onclick="setDisc('fix')"  type="button">${t('disc_fix')}</button>
    </div>
    ${discType!=='none'?`<div class="disc-input-wrap">
      <input type="number" class="disc-input" value="${discValue||''}"
        placeholder="${discType==='pct'?'Ex: 10':'Ex: 500'}"
        oninput="onDiscInput(this.value)" min="0"
        ${discType==='pct'?'max="100"':''}/>
      <span class="disc-unit">${discType==='pct'?'%':'XAF'}</span>
    </div>`:``}
    ${remiseMontant>0?`<div class="disc-saved">-${fmt(remiseMontant)}</div>`:''}
  </div>`;
}

function totalsHTML(){
  const {rawSub,remiseMontant,sub,tvaAmt,total}=totals();
  return `<div class="cart-tots">
    <div class="tot-row"><span>${t('sub')}</span><span>${fmt(rawSub)}</span></div>
    ${remiseMontant>0?`<div class="tot-row disc-row"><span>- ${t('discount')}</span><span>-${fmt(remiseMontant)}</span></div>`:''}
    ${POS.tvaOn?`<div class="tot-row"><span>${t('tva')} ${POS.tvaRate}%</span><span>${fmt(tvaAmt)}</span></div>`:''}
    <div class="tot-row grand"><span>${t('total')}</span><span>${fmt(total)}</span></div>
  </div>
  <button class="cart-clear" onclick="clearCart()" type="button">${t('clear')}</button>`;
}

function paymentHTML(){
  const {total,paid,remaining,change}=totals();
  const modes=[
    {id:'especes',
     icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1A9E7A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
     label:t('cash')},
    {id:'mtn_momo',
     icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1" fill="#B45309"/><line x1="9" y1="6" x2="15" y2="6"/></svg>',
     label:t('mtn')},
    {id:'orange_money',
     icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF6600" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1" fill="#FF6600"/><line x1="9" y1="6" x2="15" y2="6"/></svg>',
     label:t('orange')},
  ];
  const canValidate = cart.length>0 && Object.keys(activePays).length>0 && remaining<=0;

  return `<div class="pay-section">
    <div class="pay-ttl">${t('pay_ttl')}</div>
    <div class="pay-btns">
      ${modes.map(m=>`
        <button class="pay-mth${activePays[m.id]?' sel '+m.id:''}" onclick="togglePay('${m.id}')" type="button">
          <span class="pm-ico">${m.icon}</span>
          <span class="pm-lbl">${m.label}</span>
        </button>`).join('')}
    </div>
    ${modes.filter(m=>activePays[m.id]).map(m=>`
      <div class="pay-detail show">
        <div class="pf">
          <label>${m.label} — Montant (XAF)</label>
          <input type="number" inputmode="numeric" placeholder="Montant..."
            value="${activePays[m.id].amount||''}"
            oninput="onPayAmount('${m.id}',this.value)"/>
        </div>
        ${m.id!=='especes'?`<div class="pf">
          <label>${t('ref_ph')}</label>
          <input type="text" placeholder="${t('ref_ph')}" value="${activePays[m.id].ref||''}"
            oninput="onPayRef('${m.id}',this.value)"/>
        </div>`:''}
        ${m.id==='especes'&&(parseFloat(activePays.especes?.amount)||0)>0?`
          <div class="change-box">
            <div class="change-lbl">${t('change')}</div>
            <div class="change-val">${fmt(Math.max(0,(parseFloat(activePays.especes.amount)||0)-(total-paidExcept('especes'))))}</div>
          </div>`:''}
      </div>`).join('')}
    ${Object.keys(activePays).length>0?`
      <div class="pay-remaining ${remaining<=0?'ok':''}">
        <span>${remaining<=0?'Complet':t('pay_remaining')}</span>
        <span>${remaining<=0?'':fmt(remaining)}</span>
      </div>`:''}
    <div class="client-box">
      <div class="client-ttl"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ${t('client')} <span class="client-opt">${t('opt')}</span></div>
      <input class="client-inp" type="text" id="cliName" placeholder="${t('name_ph')}"/>
      <input class="client-inp" type="tel"  id="cliPhone" placeholder="${t('phone_ph')}" oninput="chkClient(this.value)"/>
      <div class="client-found" id="clFound"></div>
    </div>
    <div class="note-box">
      <textarea class="note-inp" id="saleNote" placeholder="${t('note_ph')}" rows="2"></textarea>
    </div>
    <button class="validate-btn" ${canValidate?'':'disabled'} onclick="doSale()" type="button">
      ${t('validate')} — ${fmt(total)}
    </button>
  </div>`;
}

/* ── Render all ── */
function renderAll(){
  const {total,remaining}=totals();
  const cnt = cart.reduce((s,i)=>s+i.quantity,0);
  const bb  = $('cartBarCount'); if(bb) bb.textContent=cnt+' '+t('articles');
  const bt  = $('cartBarTotal'); if(bt) bt.textContent=fmt(total);
  const sc  = $('cartSideCnt');  if(sc) sc.textContent=cnt;

  const cartHTML  = cartItemsHTML()+discountHTML()+totalsHTML()+paymentHTML();
  const cpb=$('cpBody');     if(cpb) cpb.innerHTML=cartHTML;
  const csb=$('cartSideBody'); if(csb) csb.innerHTML=cartItemsHTML()+discountHTML();
  const csf=$('cartSideFoot'); if(csf) csf.innerHTML=totalsHTML()+paymentHTML();

  /* Refresh product badges */
  document.querySelectorAll('.prod-card').forEach(c=>{
    const id=parseInt(c.dataset.id);
    const inCart=cart.find(x=>x.product_id===id);
    c.classList.toggle('in-cart',!!inCart);
    const badge=c.querySelector('.prod-badge');
    if(badge) badge.textContent=inCart?inCart.quantity:'';
  });
}

/* ── Client check ── */
let cliTimer=null;
function chkClient(phone){
  clearTimeout(cliTimer);
  const els=document.querySelectorAll('.client-found');
  els.forEach(e=>e.classList.remove('show'));
  if(!phone||phone.length<8) return;
  cliTimer=setTimeout(async()=>{
    try{
      const r=await fetch(POS.apiUrl+'?action=check_client&phone='+encodeURIComponent(phone));
      const j=await r.json();
      if(j.success&&j.found){
        document.querySelectorAll('.client-found').forEach(e=>{e.textContent=t('recognized')+': '+j.name+' ('+j.visits+' visite'+(j.visits>1?'s':'')+')';;e.classList.add('show');});
        document.querySelectorAll('#cliName').forEach(n=>{if(!n.value)n.value=j.name;});
      }
    }catch(e){}
  },600);
}

/* ── Do sale ── */
async function doSale(){
  if(!cart.length){toast(t('empty_warn'),'err');return;}
  if(!Object.keys(activePays).length){toast(t('sel_pay'),'err');return;}
  const {total,remaining}=totals();
  if(remaining>0){toast(t('pay_short'),'err');return;}

  /* Invoice */
  let inv='FAC-'+Date.now();
  try{const r=await fetch(POS.apiUrl+'?action=next_invoice');const j=await r.json();if(j.success)inv=j.invoice;}catch(e){}

  const {rawSub,remiseMontant,sub,tvaAmt}=totals();
  const cliName  = document.querySelector('#cliName')?.value.trim()||null;
  const cliPhone = document.querySelector('#cliPhone')?.value.trim()||null;
  const note     = document.querySelector('#saleNote')?.value.trim()||null;

  const paiements=Object.entries(activePays)
    .filter(([,p])=>parseFloat(p.amount||0)>0)
    .map(([mode,p])=>({mode,montant:parseFloat(p.amount||0),reference:p.ref||null}));

  const totalPaid=paiements.reduce((s,p)=>s+p.montant,0);
  const espPay=activePays.especes;
  const monnaieRendue=espPay?Math.max(0,(parseFloat(espPay.amount)||0)-(total-paidExcept('especes'))):0;

  const sale={
    facture_numero: inv,
    session_id:     sessionId,
    business_id:    POS.bizId,
    client_name:    cliName,
    client_phone:   cliPhone,
    subtotal:       rawSub,
    remise_type:    discType==='pct'?'pourcentage':discType==='fix'?'fixe':'aucune',
    remise_valeur:  discValue,
    remise_montant: remiseMontant,
    tva_rate:       POS.tvaOn?POS.tvaRate:0,
    tva_amount:     POS.tvaOn?tvaAmt:0,
    total_ttc:      total,
    montant_recu:   totalPaid,
    monnaie_rendue: monnaieRendue,
    note,
    paiements,
    items: cart.map(i=>({product_id:i.product_id,product_name:i.product_name,sku:i.sku||'',unit_price:i.unit_price,quantity:i.quantity,total:i.total})),
    offline_id: 'off_'+Date.now()+'_'+Math.random().toString(36).slice(2),
    cashier_name: POS.cashier,
    business_name: POS.bizName,
    tva_enabled:  POS.tvaOn,
  };
  lastSale=sale;

  let ok=false, savedId=null;
  if(isOnline){
    try{
      const r=await fetch(POS.apiUrl+'?action=save_sale',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(sale)});
      const j=await r.json();
      if(j.success){ok=true;savedId=j.transaction_id;}
    }catch(e){}
  }
  if(!ok) qSave(sale);

  lastSale.transaction_id=savedId;
  closePanel();
  showReceipt(sale,savedId);
  toast(t('saved'),'ok');
}

/* ── Receipt ── */
function showReceipt(s,transId){
  const payLbl={especes:'Espèces',mtn_momo:'MTN MoMo',orange_money:'Orange Money'};
  const now=new Date();
  const ds=now.toLocaleDateString('fr-FR')+' '+now.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  const items=s.items.map(i=>`
    <div class="rec-item">
      <span class="rec-iname">${i.product_name}</span>
      <span class="rec-iqty">×${i.quantity}</span>
      <span class="rec-iamt">${fmt(i.total)}</span>
    </div>`).join('');
  const paysHtml=(s.paiements||[]).map(p=>`
    <div class="rec-pay-row">
      <span>${payLbl[p.mode]||p.mode}</span>
      <span>${fmt(p.montant)}</span>
    </div>`).join('');
  const monnaieH=s.monnaie_rendue>0?`<div class="rec-tot-row"><span>Monnaie rendue</span><span>${fmt(s.monnaie_rendue)}</span></div>`:'';
  const remiseH=s.remise_montant>0?`<div class="rec-tot-row disc-row"><span>-${t('discount')}</span><span>-${fmt(s.remise_montant)}</span></div>`:'';
  const tvaH=s.tva_enabled&&s.tva_amount>0?`<div class="rec-tot-row"><span>${t('tva')} ${s.tva_rate}%</span><span>${fmt(s.tva_amount)}</span></div>`:'';

  $('recContent').innerHTML=`
    <div class="rec-top">
      <div class="rec-top-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
      <div class="rec-biz">${s.business_name||POS.bizName}</div>
      <div class="rec-sub">LionTech Business Manager</div>
      <div class="rec-num">${t('invoice')}: ${s.facture_numero}</div>
    </div>
    <div class="rec-meta"><span><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> ${ds}</span><span>${t('cashier')}: ${s.cashier_name}</span></div>
    <hr class="rec-dash"/>
    ${items}
    <hr class="rec-dash"/>
    <div class="rec-tot-row"><span>${t('sub')}</span><span>${fmt(s.subtotal)}</span></div>
    ${remiseH}${tvaH}
    <div class="rec-grand"><span>${t('total')}</span><span>${fmt(s.total_ttc)}</span></div>
    ${monnaieH}
    <hr class="rec-dash"/>
    ${paysHtml}
    <div class="rec-footer">${t('thanks')}<br/><strong>${t('powered')}</strong></div>`;

  /* Set print link */
  const printBtn=$('recPrint');
  if(printBtn && transId){
    printBtn.onclick=()=>window.open(POS.url+'/Vente_cashier/caisse/facture.php?id='+transId,'_blank');
  } else if(printBtn){ printBtn.style.display='none'; }

  $('recOverlay').classList.add('open');
}

function sendWA(){
  if(!lastSale)return;
  const items=lastSale.items.map(i=>`• ${i.product_name} ×${i.quantity} = ${i.total} XAF`).join('\n');
  const pays=(lastSale.paiements||[]).map(p=>`${p.mode}: ${p.montant} XAF`).join(' + ');
  const msg=`*${lastSale.business_name||POS.bizName}*\nFacture: ${lastSale.facture_numero}\n\n${items}\n\n`
    +(lastSale.remise_montant>0?`Remise: -${lastSale.remise_montant} XAF\n`:'')
    +(lastSale.tva_enabled&&lastSale.tva_amount>0?`TVA: ${lastSale.tva_amount} XAF\n`:'')
    +`*TOTAL: ${lastSale.total_ttc} XAF*\n${pays}\n\n${t('thanks')}\n_${t('powered')}_`;
  const ph=lastSale.client_phone;
  window.open(ph?`https://wa.me/${ph.replace(/\D/g,'')}?text=${encodeURIComponent(msg)}`:`https://wa.me/?text=${encodeURIComponent(msg)}`,'_blank');
}

function newSale(){
  cart=[]; discType='none'; discValue=0; activePays={}; lastSale=null;
  $('recOverlay').classList.remove('open');
  const si=$('searchInput'); if(si) si.value='';
  showEmptyState();
  renderAll();
}

/* ── Panel ── */
function openPanel(){ $('cpOverlay').classList.add('open'); $('cpPanel').classList.add('open'); }
function closePanel(){ $('cpOverlay').classList.remove('open'); $('cpPanel').classList.remove('open'); }
function closeModal(id){ const m=$(id); if(m) m.classList.remove('open'); }

/* ── Sidebar ── */
function openSB(){ $('sbDrawer').classList.add('open'); $('sbOverlay').classList.add('open'); }
function closeSB(){ $('sbDrawer').classList.remove('open'); $('sbOverlay').classList.remove('open'); }

/* ── Scanner ── */
function openScan(){
  $('scanModal').classList.add('open');
  if(typeof Html5Qrcode==='undefined'){ toast('Scanner non disponible','err'); closeScan(); return; }
  scanner=new Html5Qrcode('reader');
  scanner.start({facingMode:'environment'},{fps:10,qrbox:{width:250,height:150}},async code=>{
    closeScan();
    try{
      const r=await fetch(POS.apiUrl+'?action=get_by_barcode&code='+encodeURIComponent(code));
      const j=await r.json();
      if(j.success&&j.product){
        addToCart(j.product.product_id,j.product);
        toast(j.product.name+' ajouté','ok');
      }else{
        const si=$('searchInput'); if(si){si.value=code;onSearch(code);}
        toast('Produit non trouvé: '+code,'err');
      }
    }catch(e){ const si=$('searchInput'); if(si){si.value=code;onSearch(code);} }
  },()=>{}).catch(()=>{ toast('Caméra non accessible','err'); closeScan(); });
}
function closeScan(){
  $('scanModal').classList.remove('open');
  if(scanner){ scanner.stop().catch(()=>{}); scanner=null; }
}

/* ── Lang ── */
function toggleLang(){
  lang=lang==='fr'?'en':'fr';
  const b=$('langBtn'); if(b) b.textContent=lang==='fr'?'EN':'FR';
  const si=$('searchInput'); if(si) si.placeholder=t('search_ph');
  $('prodEmptyText').textContent=t('empty_title');
  $('prodEmptySub').textContent=t('empty_sub');
  renderAll();
}

/* ── Toast ── */
function toast(m,type=''){
  const el=$('posToast'); if(!el)return;
  el.textContent=m; el.className='pos-toast show'+(type?' '+type:'');
  clearTimeout(el._t); el._t=setTimeout(()=>el.classList.remove('show'),2800);
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded',async()=>{
  await openDB();
  window.addEventListener('online',setOnline);
  window.addEventListener('offline',setOnline);
  setOnline();
  resetLock();

  /* Load settings */
  try{
    const r=await fetch(POS.apiUrl+'?action=get_settings');
    const j=await r.json();
    if(j.success){ POS.bizName=j.business_name||POS.bizName; POS.tvaOn=j.tva_enabled; POS.tvaRate=j.tva_rate;
      const privileged=['business_owner','manager','caissier'].includes(window.POS_ROLE||'');
      if(j.requires_code&&!privileged){ showCodeScreen(); return; }
    }
  }catch(e){}

  /* ── Show PIN gate for any user who has a PIN set (role-agnostic) ── */
  try{
    const pr=await fetch(POS.apiUrl+'?action=has_pin');
    const pj=await pr.json();
    if(pj.has_pin){
      const ps=$('pinGateScreen');
      if(ps){ ps.classList.remove('lock-hidden'); $('pinGateInput')?.focus(); return; }
    }
  }catch(pe){}

  showSessionScreen();
});

/* tryPinGate — called by pinGateBtn onclick in dashboard.php */
window.tryPinGate = async function(){
  const pin=($('pinGateInput')?.value||'').trim();
  const btn=$('pinGateBtn');
  const err=$('pinGateErr');
  if(!pin){ if(err) err.textContent='PIN requis / PIN required'; return; }
  if(btn){ btn.disabled=true; btn.textContent='Vérification...'; }
  try{
    const r=await fetch(POS.apiUrl+'?action=verify_pin',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({pin})
    });
    const j=await r.json();
    if(j.success){
      $('pinGateScreen')?.classList.add('lock-hidden');
      showSessionScreen();
    }else{
      if(err) err.textContent=j.message||'PIN incorrect';
      const inp=$('pinGateInput');
      if(inp){ inp.value=''; inp.focus(); }
      if(btn){ btn.disabled=false; btn.textContent='Accéder à la caisse'; }
    }
  }catch(e){
    if(err) err.textContent='Erreur réseau';
    if(btn){ btn.disabled=false; btn.textContent='Accéder à la caisse'; }
  }
};

async function finishInit(){
  renderAll();
  updateBarSub();

  /* Events */
  $('hamburger')  ?.addEventListener('click',openSB);
  $('sbClose')    ?.addEventListener('click',closeSB);
  $('sbOverlay')  ?.addEventListener('click',closeSB);
  $('scanBtn')    ?.addEventListener('click',openScan);
  $('scanClose')  ?.addEventListener('click',closeScan);
  $('searchInput')?.addEventListener('input',e=>onSearch(e.target.value));
  $('cartBarBtn') ?.addEventListener('click',openPanel);
  $('cpClose')    ?.addEventListener('click',closePanel);
  $('cpOverlay')  ?.addEventListener('click',closePanel);
  $('langBtn')    ?.addEventListener('click',toggleLang);
  $('lockBtn')    ?.addEventListener('click',doUnlock);
  $('lockPin')    ?.addEventListener('keydown',e=>{if(e.key==='Enter')doUnlock();});
  $('recWa')      ?.addEventListener('click',sendWA);
  $('recNew')     ?.addEventListener('click',newSale);
  $('closeSessionBtn')    ?.addEventListener('click',requestCloseSession);
  $('confirmCloseSession')?.addEventListener('click',confirmCloseSession);
  $('caisseCodeBtn')?.addEventListener('click',tryCode);
  $('caisseCodeInput')?.addEventListener('keydown',e=>{if(e.key==='Enter')tryCode();});
  $('openSessionBtn')?.addEventListener('click',tryOpenSession);
  $('fondCaisse')?.addEventListener('keydown',e=>{if(e.key==='Enter')tryOpenSession();});
}
/* Checkout helpers for dashboard.php onclick attributes */
window.applyDisc = function(type){
  discType=type;
  const inp=$('discInput');
  ['discNoneBtn','discPctBtn','discFixBtn'].forEach(id=>$(id)?.classList.remove('active'));
  if(type==='none'){ discValue=0; if(inp)inp.style.display='none'; $('discNoneBtn')?.classList.add('active'); }
  else{ if(inp)inp.style.display=''; $(type==='pct'?'discPctBtn':'discFixBtn')?.classList.add('active'); discValue=parseFloat(inp?.value||0); }
  renderAll();
};
window.applyDiscVal = function(val){ discValue=parseFloat(val||0); renderAll(); };

let _pendMode=null;
window.selectPayMode = function(mode){
  _pendMode=mode;
  const row=$('payInputRow'),btn=$('addPayBtn');
  if(row)row.style.display='';
  if(btn)btn.style.display='';
  $$('.pay-mode-btn').forEach(b=>b.style.outline='none');
  const idx={especes:0,mtn_momo:1,orange_money:2}[mode];
  const btns=document.querySelectorAll('.pay-mode-btn');
  if(btns[idx])btns[idx].style.outline='2.5px solid #1A9E7A';
};
window.addPayment = function(){
  if(!_pendMode){toast('Choisissez un mode.','err');return;}
  const amount=parseFloat($('payAmount')?.value||0);
  const ref=($('payRef')?.value||'').trim();
  if(amount<=0){toast('Entrez un montant.','err');return;}
  activePays[_pendMode]={amount,ref};
  renderPaymentList();renderTotals();
  if($('payAmount'))$('payAmount').value='';
  if($('payRef'))$('payRef').value='';
  const row=$('payInputRow'),btn=$('addPayBtn');
  if(row)row.style.display='none';
  if(btn)btn.style.display='none';
  $$('.pay-mode-btn').forEach(b=>b.style.outline='none');
  _pendMode=null;
};