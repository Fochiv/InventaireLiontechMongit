<?php
/* ============================================================
   pin_manager.php — LionTech PIN Manager
   Owner sees all employees + can generate/reset PINs
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Employee_Management\pin_manager.php
   ============================================================ */
require_once dirname(__DIR__) . '/Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user   = currentUser();
$bizId  = (int)($user['business_id'] ?? 0);
$url    = APP_URL;
$initials = '';
foreach(explode(' ', trim($user['full_name']??'U')) as $w) $initials.=strtoupper(substr($w,0,1));
$initials = substr($initials?:'U',0,2);
$biz = [];
try{ $s=getDB()->prepare('SELECT * FROM businesses WHERE business_id=? LIMIT 1'); $s->execute([$bizId]); $biz=$s->fetch()?:[]; }catch(Throwable $e){}
$bizName = $biz['business_name'] ?? 'LionTech';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Gestion des PINs — <?= htmlspecialchars($bizName) ?></title>
<link rel="stylesheet" href="<?= $url ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
<style>
.pm-wrap{padding:0 24px 60px;max-width:900px}
.pm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-top:16px}
.pm-card{background:#fff;border:0.5px solid #E5E7EB;border-radius:14px;padding:16px}
.pm-card-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.pm-avatar{width:38px;height:38px;border-radius:50%;background:#0B1F3A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.pm-name{font-size:14px;font-weight:700;color:#0B1F3A}
.pm-role{font-size:11px;color:#9CA3AF;text-transform:capitalize}
.pm-status{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;font-size:12px}
.badge-ok{background:#DCFCE7;color:#166534;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700}
.badge-no{background:#FEE2E2;color:#991B1B;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700}
.pm-btn{width:100%;padding:9px;background:#0B1F3A;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s}
.pm-btn:hover{background:#1E3A5F}
.pm-btn.secondary{background:#F3F4F6;color:#374151}
.pm-btn.secondary:hover{background:#E5E7EB}
/* PIN reveal modal */
.pin-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px}
.pin-modal{background:#fff;border-radius:20px;padding:28px;max-width:360px;width:100%;text-align:center}
.pin-display{font-size:52px;font-weight:900;color:#0B1F3A;letter-spacing:12px;margin:16px 0;font-family:monospace}
.pin-warning{background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:10px 14px;font-size:12px;color:#92400E;margin-bottom:16px}
.pm-role-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700}
.role-caissier{background:#DBEAFE;color:#1E40AF}
.role-manager{background:#F3E8FF;color:#6B21A8}
.role-employee{background:#F3F4F6;color:#374151}
</style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">
<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>
<main class="od-main">
  <div class="od-topbar">
    <div>
      <h1 style="font-size:20px;font-weight:800;color:#0B1F3A;margin:0"><span class="icon-lock"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span></span> Gestion des PINs</h1>
      <p style="font-size:13px;color:#6B7280;margin:2px 0 0">Générez les PINs d'accès à la caisse pour vos employés</p>
    </div>
    <div class="od-avatar"><?= htmlspecialchars($initials) ?></div>
  </div>

  <div class="pm-wrap">
    <div style="background:#EFF6FF;border:0.5px solid #BFDBFE;border-radius:12px;padding:12px 16px;font-size:13px;color:#1E40AF;margin-bottom:20px">
      💡 <strong>Comment ça marche :</strong>
      Cliquez <em>Générer PIN</em> pour créer un code 4 chiffres pour un employé.
      Notez bien ce code et donnez-le à l'employé — il ne sera affiché qu'une seule fois.
      Les <strong>caissiers</strong> utilisent leur PIN pour accéder à la caisse.
      Les <strong>employés ordinaires</strong> peuvent aussi avoir un PIN si vous souhaitez leur donner accès.
    </div>

    <div class="pm-grid" id="pmGrid">
      <div style="text-align:center;padding:40px;color:#9CA3AF">Chargement...</div>
    </div>
  </div>
</main>
</div>

<!-- PIN Modal -->
<div class="pin-modal-bg" id="pinModal">
  <div class="pin-modal">
    <div style="font-size:24px;margin-bottom:8px"><span class="icon-lock"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span></span></div>
    <div style="font-size:16px;font-weight:800;color:#0B1F3A" id="pinModalTitle">PIN généré</div>
    <div style="font-size:13px;color:#6B7280;margin:4px 0 12px" id="pinModalSub"></div>
    <div class="pin-display" id="pinDisplay">----</div>
    <div class="pin-warning">
      <span class="icon-warn">⚠</span> <strong>Notez ce PIN maintenant.</strong><br>
      Il ne sera plus affiché après fermeture de cette fenêtre.<br>
      Donnez ce code à l'employé.
    </div>
    <div style="display:flex;gap:8px">
      <button onclick="copyPin()" class="pm-btn secondary" style="flex:1"><span class="icon-list">≡</span> Copier</button>
      <button onclick="closePinModal()" class="pm-btn" style="flex:1"><span class="icon-ok">✓</span> Compris</button>
    </div>
  </div>
</div>

<script>
const API = '<?= $url ?>/LionTech_Owner_Dashboard/pin_api.php';
let lastPin = '';

function roleLabel(role){
  const m={caissier:'Caissier',manager:'Manager',employee:'Employé'};
  return m[role]||role;
}
function roleClass(role){
  return 'role-'+(role==='caissier'?'caissier':role==='manager'?'manager':'employee');
}
function initials(name){
  return name.split(' ').map(w=>w[0]||'').join('').toUpperCase().slice(0,2);
}

async function loadEmployees(){
  const r=await fetch(API+'?action=list');
  const j=await r.json();
  const grid=document.getElementById('pmGrid');
  if(!j.success||!j.employees.length){grid.innerHTML='<div style="color:#9CA3AF;padding:40px;text-align:center">Aucun employé trouvé.</div>';return;}
  grid.innerHTML=j.employees.map(e=>`
    <div class="pm-card" id="card-${e.user_id}">
      <div class="pm-card-head">
        <div class="pm-avatar">${initials(e.full_name)}</div>
        <div>
          <div class="pm-name">${e.full_name}</div>
          <span class="pm-role-badge ${roleClass(e.role)}">${roleLabel(e.role)}</span>
        </div>
      </div>
      <div class="pm-status">
        <span>PIN caisse</span>
        ${e.has_pin
          ? `<span class="badge-ok">✓ Configuré</span>`
          : `<span class="badge-no">✗ Non défini</span>`}
      </div>
      ${e.has_pin?`<div style="font-size:11px;color:#9CA3AF;margin-bottom:10px">Mis à jour: ${e.pin_updated_at?e.pin_updated_at.slice(0,10):'—'}</div>`:''}
      <button class="pm-btn" onclick="generatePin(${e.user_id},'${e.full_name.replace(/'/g,"\\'")}',${e.has_pin?1:0})">
        ${e.has_pin?'🔄 Réinitialiser PIN':'<span class="icon-lock"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span></span> Générer PIN'}
      </button>
    </div>`).join('');
}

async function generatePin(userId, name, alreadyHas){
  if(alreadyHas && !confirm(`Réinitialiser le PIN de ${name} ? L'ancien PIN sera inutilisable.`)) return;
  const r=await fetch(API+'?action=generate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:userId})});
  const j=await r.json();
  if(!j.success){alert(j.message||'Erreur');return;}
  lastPin = j.pin;
  document.getElementById('pinModalTitle').textContent = 'PIN généré pour '+j.user_name;
  document.getElementById('pinModalSub').textContent = 'Rôle: '+roleLabel(j.role);
  document.getElementById('pinDisplay').textContent = j.pin;
  document.getElementById('pinModal').style.display='flex';
  /* Refresh card */
  loadEmployees();
}

function copyPin(){
  navigator.clipboard.writeText(lastPin).then(()=>{
    const btn=event.target;btn.textContent='<span class="icon-ok">✓</span> Copié!';setTimeout(()=>btn.textContent='<span class="icon-list">≡</span> Copier',2000);
  });
}
function closePinModal(){
  document.getElementById('pinModal').style.display='none';
  lastPin='';
  document.getElementById('pinDisplay').textContent='----';
}
document.getElementById('pinModal').addEventListener('click',e=>{if(e.target===document.getElementById('pinModal'))closePinModal();});

loadEmployees();
</script>
</body>
</html>