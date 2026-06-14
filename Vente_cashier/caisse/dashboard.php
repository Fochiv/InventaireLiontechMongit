<?php
/* ============================================================
   Vente.php — LionTech Caisse POS (Combined)
   Mobile + Tablet + Desktop · Offline · Sessions · Mixed Pay
   Path: C:\Xampp\htdocs\InventoryLiontech\Vente_cashier\Vente.php
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE, 'caissier']);

$user    = currentUser();
$pdo     = getDB();
$bizId   = (int)($user['business_id'] ?? 0);
$userId  = (int)($user['user_id']     ?? 0);
$role    = $user['role']              ?? '';
$cashier = $user['full_name']         ?? '';
$url     = APP_URL;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Business */
$biz = [];
try {
    $s = $pdo->prepare('SELECT * FROM businesses WHERE business_id=? LIMIT 1');
    $s->execute([$bizId]); $biz = $s->fetch() ?: [];
} catch(Throwable $e){}
$bizName = $biz['business_name'] ?? 'LionTech';

/* TVA settings */
$tvaEnabled = false; $tvaRate = 19.25;
try {
    $s = $pdo->prepare('SELECT tva_enabled,tva_rate FROM business_settings WHERE business_id=? LIMIT 1');
    $s->execute([$bizId]); $set = $s->fetch();
    if($set){ $tvaEnabled=(bool)($set['tva_enabled']??false); $tvaRate=(float)($set['tva_rate']??19.25); }
} catch(Throwable $e){}

/* Initials */
$initials = '';
foreach(explode(' ',trim($cashier)) as $w) $initials.=strtoupper(substr($w,0,1));
$initials = substr($initials?:'U',0,2);

/* Dashboard link by role */
$dashLink = match($role){
    ROLE_BUSINESS_OWNER => $url.'/LionTech_Owner_Dashboard/owner_dashboard.php',
    ROLE_MANAGER        => $url.'/LionTech_Owner_Dashboard/owner_dashboard.php',
    default             => $url.'/LionTech_Employee_Dashboard/employee_dashboard.php',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
  <meta name="theme-color" content="#0B1F3A"/>
  <meta name="mobile-web-app-capable" content="yes"/>
  <meta name="apple-mobile-web-app-capable" content="yes"/>
  <title>Caisse — <?= h($bizName) ?></title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
  <link rel="stylesheet" href="Caisse.css"/>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
  <script>
    window.POS = {
      cashier:  <?= json_encode($cashier)   ?>,
      bizId:    <?= json_encode($bizId)     ?>,
      bizName:  <?= json_encode($bizName)   ?>,
      tvaOn:    <?= json_encode($tvaEnabled)?>,
      tvaRate:  <?= json_encode($tvaRate)   ?>,
      dash:     <?= json_encode($dashLink)  ?>,
      url:      <?= json_encode($url)       ?>,
      apiUrl:   '<?= APP_URL ?>/Vente_cashier/caisse/CaisseApi.php',
    };
    window.POS_ROLE = <?= json_encode($role) ?>;
  </script>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
<link rel="stylesheet" href="<?= APP_URL ?>/responsive_utils.css">
</head>
<body>

<!-- ══ PIN GATE SCREEN (caissier role) ══ -->
<div class="gate-screen lock-hidden" id="pinGateScreen">
  <div class="gate-logo"><img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3)"/></div>
  <div class="gate-title">Accès Caisse</div>
  <div class="gate-sub">
    Bonjour <?= h(explode(' ',$cashier)[0]) ?> — entrez votre PIN caisse<br>
    <em>Enter your cashier PIN to continue</em>
  </div>
  <input class="gate-input" id="pinGateInput" type="password"
         inputmode="numeric" maxlength="10" placeholder="••••••"
         autocomplete="current-password"
         onkeydown="if(event.key==='Enter') tryPinGate()"/>
  <div class="gate-error" id="pinGateErr"></div>
  <button class="gate-btn" id="pinGateBtn" type="button"
          onclick="tryPinGate()">Accéder à la caisse</button>
</div>

<!-- ══ CAISSE CODE SCREEN (employees) ══ -->
<div class="gate-screen lock-hidden" id="caisseCodeScreen">
  <div class="gate-title">Accès Caisse</div>
  <div class="gate-sub">Entrez le code caisse pour continuer<br><em>Enter the caisse code to continue</em></div>
  <input class="gate-input" id="caisseCodeInput" type="password" inputmode="numeric" maxlength="6" placeholder="••••" onkeydown="if(event.key==='Enter') tryCode()"/>
  <div class="gate-error" id="caisseCodeErr"></div>
  <button class="gate-btn" id="caisseCodeBtn" type="button" onclick="tryCode()">Ouvrir la caisse</button>
</div>

<!-- ══ SESSION OPENING SCREEN ══ -->
<div class="gate-screen lock-hidden" id="sessionScreen">
  <div class="gate-title" id="sesTitle">Ouvrir la caisse</div>
  <div class="gate-sub" id="sesSub">Bonjour <?= h(explode(' ',$cashier)[0]) ?> — entrez le montant en caisse</div>
  <div class="gate-field-group">
    <div class="gate-field-label">Fond de caisse (XAF)</div>
    <input class="gate-input" id="fondCaisse" type="number" inputmode="numeric" placeholder="Ex: 5000" min="0" onkeydown="if(event.key==='Enter') tryOpenSession()"/>
  </div>
  <div class="gate-error" id="sessionErr"></div>
  <button class="gate-btn" id="openSessionBtn" type="button" onclick="tryOpenSession()">Ouvrir la caisse →</button>
</div>

<!-- ══ LOCK SCREEN (inactivity) ══ -->
<div class="gate-screen lock-hidden" id="lockScreen">
  <div class="gate-logo"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
  <div class="gate-title" id="lkTitle">Session verrouillée</div>
  <div class="gate-sub"   id="lkSub">Entrez votre PIN pour reprendre</div>
  <input class="gate-input" id="lockPin" type="password" inputmode="numeric" maxlength="10" placeholder="••••••" autocomplete="current-password"/>
  <div class="gate-error" id="lockErr"></div>
  <button class="gate-btn" id="lockBtn" type="button"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg> Déverrouiller</button>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sb-overlay" id="sbOverlay"></div>
<aside class="sb-drawer" id="sbDrawer">
  <div class="sb-head">
    <div class="sb-brand">
      <img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally"
           style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0"
           onerror="this.style.display='none'"/>
      <div>
        <div class="sb-name">LionTech</div>
        <div class="sb-tag">Business Manager</div>
      </div>
    </div>
    <button class="sb-close" id="sbClose" type="button"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
  </div>
  <nav class="sb-nav">
    <a class="sb-link" href="<?= h($dashLink) ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Dashboard</span>
    </a>
    <a class="sb-link here" href="<?= h($url) ?>/Vente_cashier/caisse/dashboard.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      <span>Caisse</span>
    </a>
    <a class="sb-link" href="<?= h($url) ?>/Attendance_presenceemployer/clock_attendance.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span>Présence</span>
    </a>
    <a class="sb-link" href="<?= h($url) ?>/stockout_stockfinis/stock_out.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg>
      <span>Stock sortant</span>
    </a>
    <?php if(in_array($role,[ROLE_BUSINESS_OWNER,ROLE_MANAGER],true)): ?>
    <a class="sb-link" href="<?= h($url) ?>/Vente_cashier/caisse/validations.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      <span>Validations</span>
    </a>
    <a class="sb-link" href="<?= h($url) ?>/Vente_cashier/Vente.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Rapports Vente</span>
    </a>
    <?php endif; ?>
    <a class="sb-link" href="<?= h($url) ?>/Logininventory/logout.php">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Déconnexion</span>
    </a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av" style="background:#1A9E7A;color:#fff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?= h($initials) ?></div>
    <div>
      <div class="sb-uname"><?= h($cashier) ?></div>
      <div class="sb-urole"><?= ucfirst(h($role)) ?></div>
    </div>
  </div>
</aside>

<!-- ══ OFFLINE BANNER ══ -->
<div class="off-banner" id="offBanner"></div>

<!-- ══ TOP BAR ══ -->
<header class="pos-bar">
  <button class="bar-icon-btn" id="hamburger" type="button" aria-label="Menu"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
  <a class="bar-icon-btn" href="<?= h($dashLink) ?>" title="Retour">←</a>
  <div class="bar-center">
    <div class="bar-biz"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> <?= h($bizName) ?></div>
    <div class="bar-sub" id="barSub"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= h($cashier) ?></div>
  </div>
  <div class="bar-right">
    <div class="online-dot" id="onlineDot"></div>
    <button class="pos-close-btn" id="closeSessionBtn" type="button" title="Fermer la caisse" style="display:none"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <button class="lang-btn" id="langBtn" type="button">EN</button>
  </div>
</header>

<!-- ══ MAIN LAYOUT ══ -->
<div class="pos-wrap" id="posWrap">

  <!-- ── Search + Products ── -->
  <div class="products-col">
    <div class="pos-search">
      <div class="s-wrap">
        <span class="s-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <input type="search" id="searchInput" placeholder="Chercher un produit..." autocomplete="off" spellcheck="false"/>
      </div>
      <button class="scan-btn" id="scanBtn" type="button" title="Scanner"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></button>
    </div>
    <div class="products-wrap">
      <div class="prod-empty-state" id="prodEmptyState">
        <div class="prod-empty-icon"><svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <div class="prod-empty-text" id="prodEmptyText">Recherchez ou scannez un produit</div>
        <div class="prod-empty-sub" id="prodEmptySub">Scan or search to add products</div>
      </div>
      <div class="prod-grid" id="prodGrid" style="display:none"></div>
    </div>
  </div>

  <!-- ── Cart Panel : mobile=fixed slide-up | desktop=right column ── -->
  <div class="cp-panel" id="cpPanel">
    <div class="cp-handle"></div>
    <div class="cp-head">
      <div class="cp-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <span id="cpTtl">Panier</span>
      </div>
      <button class="cp-close" id="cpClose" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div id="cpBody" class="cp-body-scroll">
      <!-- Populated by renderAll() in caisse.js -->
    </div><!-- /#cpBody -->
  </div><!-- /#cpPanel -->

</div><!-- /#posWrap -->

<!-- ══ CART BAR (mobile bottom) ══ -->
<div class="cart-bar" id="cartBar">
  <div class="cart-bar-info">
    <div class="cart-bar-count" id="cartCount">0 article(s)</div>
    <div class="cart-bar-total" id="cartBarTotal">0 XAF</div>
  </div>
  <button class="cart-bar-btn" id="cartBarBtn" type="button">Voir le panier →</button>
</div>
<div class="cp-overlay" id="cpOverlay"></div>

<!-- ══ SCANNER ══ -->
<div class="scan-modal" id="scanModal">
  <div class="scan-frame"><div id="reader"></div></div>
  <div class="scan-hint" id="scanHint"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Pointez la caméra vers le code-barres</div>
  <button class="scan-close" id="scanClose" type="button"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Fermer</button>
</div>

<!-- ══ CLOSE SESSION MODAL ══ -->
<div class="modal-overlay" id="closeSessionModal">
  <div class="modal-box">
    <div class="modal-title">⏏ Fermer la caisse</div>
    <div class="modal-sub" id="closeSessionSummary"></div>
    <div class="gate-error" id="closeSessionErr"></div>
    <div class="modal-actions">
      <button class="modal-btn modal-btn-outline" onclick="closeModal('closeSessionModal')" type="button">Annuler</button>
      <button class="modal-btn modal-btn-danger" id="confirmCloseSession" type="button">Fermer la caisse</button>
    </div>
  </div>
</div>

<!-- ══ RECEIPT MODAL ══ -->
<div class="rec-overlay" id="recOverlay">
  <div class="rec-sheet">
    <div class="rec-handle"></div>
    <div id="recContent"></div>
    <div class="rec-actions">
      <button class="rec-wa" id="recWa" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Envoyer sur WhatsApp
      </button>
      <button class="rec-print" id="recPrint" type="button">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimer la facture
      </button>
      <button class="rec-new" id="recNew" type="button">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        Nouvelle vente
      </button>
    </div>
  </div>
</div>

<!-- ══ TOAST ══ -->
<div class="pos-toast" id="posToast"></div>

<script src="caisse.js"></script>
</body>
</html>