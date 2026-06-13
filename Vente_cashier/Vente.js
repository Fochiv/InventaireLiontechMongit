/* Vente.js — chart, language, modal, CSV */
(function(){
  const data = window.SALES_DATA || {labels:[], values:[]};
  const canvas = document.getElementById('salesChart');
  if (canvas && window.Chart) {
    new Chart(canvas, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Ventes',
          data: data.values,
          tension: .35,
          fill: true,
          borderWidth: 3,
          pointRadius: 3,
          borderColor: '#0F3FAE',
          backgroundColor: 'rgba(15,63,174,.12)',
          pointBackgroundColor: '#D4A017'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display:false } },
        scales: {
          y: { beginAtZero:true, ticks:{ callback:v => Number(v).toLocaleString('fr-FR') + ' XAF' } },
          x: { grid:{ display:false } }
        }
      }
    });
  }
})();

function openPermModal(){
  const m = document.getElementById('permModal');
  if(m) m.style.display = 'flex';
}
function closePermModal(){
  const m = document.getElementById('permModal');
  if(m) m.style.display = 'none';
}

const translations = {
  fr:{
    title:'Contrôle des ventes', month:'Mois', year:'Année', print:'Imprimer', total_sales:'Total vendu', receipts:'Reçus', stock_spent:'Dépensé stock', losses:'Pertes', profit:'Bénéfice estimé', sales_graph:'Évolution des ventes', cashier_perf:'Performance des caissiers', stock_in:'Stock entrant', stock_out:'Stock sortant / ventes / pertes', financial_summary:'Résumé financier', receipt_table:'Reçus / factures', fraud_table:'Détection fraude / anomalies'
  },
  en:{
    title:'Sales Control', month:'Month', year:'Year', print:'Print', total_sales:'Total Sales', receipts:'Receipts', stock_spent:'Stock Spent', losses:'Losses', profit:'Estimated Profit', sales_graph:'Sales Trend', cashier_perf:'Cashier Performance', stock_in:'Stock In', stock_out:'Stock Out / Sales / Losses', financial_summary:'Financial Summary', receipt_table:'Receipts / Invoices', fraud_table:'Fraud / Anomaly Detection'
  }
};
let currentLang = localStorage.getItem('lt_lang') || 'en';
function applyLang(lang){
  currentLang = lang;
  localStorage.setItem('lt_lang', lang);
  document.documentElement.lang = lang;
  document.querySelectorAll('[data-i18n]').forEach(el=>{
    const key = el.getAttribute('data-i18n');
    if(translations[lang] && translations[lang][key]) el.textContent = translations[lang][key];
  });
  const b = document.getElementById('salesLangBtn');
  if(b) b.textContent = lang === 'fr' ? 'EN' : 'FR';
}
function toggleSalesLang(){ applyLang(currentLang === 'fr' ? 'en' : 'fr'); }
applyLang(currentLang);

function exportCurrentTable(){
  const tables = document.querySelectorAll('.audit-table');
  if(!tables.length){ alert('No table found'); return; }
  // Export the first visible audit table on screen. User can print all from browser.
  const table = tables[0];
  let csv = [];
  table.querySelectorAll('tr').forEach(row=>{
    const cols = [...row.querySelectorAll('th,td')].map(cell => '"' + cell.innerText.replace(/"/g,'""').replace(/\n/g,' ').trim() + '"');
    csv.push(cols.join(','));
  });
  const blob = new Blob([csv.join('\n')], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'vente-export.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}