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
</head>
<body>

<!-- ══ PIN GATE SCREEN (caissier role) ══ -->
<div class="gate-screen lock-hidden" id="pinGateScreen">
  <div class="gate-logo"><img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3)"/></div>
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
   <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"/>
  <div class="gate-title">Accès Caisse</div>
  <div class="gate-sub">Entrez le code caisse pour continuer<br><em>Enter the caisse code to continue</em></div>
  <input class="gate-input" id="caisseCodeInput" type="password" inputmode="numeric" maxlength="6" placeholder="••••" onkeydown="if(event.key==='Enter') tryCode()"/>
  <div class="gate-error" id="caisseCodeErr"></div>
  <button class="gate-btn" id="caisseCodeBtn" type="button" onclick="tryCode()">Ouvrir la caisse</button>
</div>

<!-- ══ SESSION OPENING SCREEN ══ -->
<div class="gate-screen lock-hidden" id="sessionScreen">
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"/>
  <div class="gate-title" id="sesTitle">Ouvrir la caisse</div>
  <div class="gate-sub" id="sesSub">Bonjour <?= h(explode(' ',$cashier)[0]) ?> — entrez le montant en caisse</div>
  <div class="gate-field-label">Fond de caisse (XAF)</div>
  <input class="gate-input" id="fondCaisse" type="number" inputmode="numeric" placeholder="Ex: 5000" min="0" onkeydown="if(event.key==='Enter') tryOpenSession()"/>
  <div class="gate-error" id="sessionErr"></div>
  <button class="gate-btn" id="openSessionBtn" type="button" onclick="tryOpenSession()">Ouvrir la caisse →</button>
</div>

<!-- ══ LOCK SCREEN (inactivity) ══ -->
<div class="gate-screen lock-hidden" id="lockScreen">
  <div class="gate-logo">🔒</div>
  <div class="gate-title" id="lkTitle">Session verrouillée</div>
  <div class="gate-sub"   id="lkSub">Entrez votre PIN pour reprendre</div>
  <input class="gate-input" id="lockPin" type="password" inputmode="numeric" maxlength="10" placeholder="••••••" autocomplete="current-password"/>
  <div class="gate-error" id="lockErr"></div>
  <button class="gate-btn" id="lockBtn" type="button">🔓 Déverrouiller</button>
</div>

<!-- ══ SIDEBAR ══ -->
<div class="sb-overlay" id="sbOverlay"></div>
<aside class="sb-drawer" id="sbDrawer">
  <div class="sb-head">
    <div class="sb-brand">
      <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech"
           style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0"
           onerror="this.style.display='none'"/>
      <div>
        <div class="sb-name">LionTech</div>
        <div class="sb-tag">Business Manager</div>
      </div>
    </div>
    <button class="sb-close" id="sbClose" type="button">✕</button>
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
  <button class="bar-icon-btn" id="hamburger" type="button" aria-label="Menu">☰</button>
  <a class="bar-icon-btn" href="<?= h($dashLink) ?>" title="Retour">←</a>
  <div class="bar-center">
    <div class="bar-biz">🧾 <?= h($bizName) ?></div>
    <div class="bar-sub" id="barSub">👤 <?= h($cashier) ?></div>
  </div>
  <div class="bar-right">
    <div class="online-dot" id="onlineDot"></div>
    <button class="pos-close-btn" id="closeSessionBtn" type="button" title="Fermer la caisse" style="display:none">⏏</button>
    <button class="lang-btn" id="langBtn" type="button">EN</button>
  </div>
</header>

<!-- ══ MAIN LAYOUT ══ -->
<div class="pos-wrap" id="posWrap">

  <!-- ── Search + Products ── -->
  <div class="products-col">
    <div class="pos-search">
      <div class="s-wrap">
        <span class="s-ico">🔍</span>
        <input type="search" id="searchInput" placeholder="Chercher un produit..." autocomplete="off" spellcheck="false"/>
      </div>
      <button class="scan-btn" id="scanBtn" type="button" title="Scanner">📷</button>
    </div>
    <div class="products-wrap">
      <div class="prod-empty-state" id="prodEmptyState">
        <div class="prod-empty-icon">🔍</div>
        <div class="prod-empty-text" id="prodEmptyText">Recherchez ou scannez un produit</div>
        <div class="prod-empty-sub" id="prodEmptySub">Scan or search to add products</div>
      </div>
      <div class="prod-grid" id="prodGrid" style="display:none"></div>
    </div>
  </div>

  <!-- Cart is in cpPanel (handles desktop+mobile via Caisse.css) -->

</div>

<!-- ══ CART BAR (mobile bottom) ══ -->
<div class="cart-bar" id="cartBar">
  <div class="cart-bar-info">
    <div class="cart-bar-count" id="cartCount">0 article(s)</div>
    <div class="cart-bar-total" id="cartBarTotal">0 XAF</div>
  </div>
  <button class="cart-bar-btn" id="cartBarBtn" type="button">Voir le panier →</button>
</div>

<!-- ══ CART PANEL (mobile) ══ -->
<div class="cp-overlay" id="cpOverlay"></div>
<div class="cp-panel" id="cpPanel">
  <div class="cp-handle"></div>
  <div class="cp-head">
    <div class="cp-title">🛒 <span id="cpTtl">Panier</span></div>
    <button class="cp-close" id="cpClose" type="button">✕</button>
  </div>
  <div id="cpBody" class="cp-body-scroll">

    <!-- Cart items -->
    <div id="cartItems" class="cart-items-wrap"></div>

    <!-- Client info -->
    <div class="co-section">
      <div class="co-label">Client (optionnel)</div>
      <div class="co-row-2">
        <input class="co-input" id="cliName" type="text" placeholder="Nom client..."/>
        <input class="co-input" id="cliPhone" type="tel" placeholder="Téléphone..."/>
      </div>
    </div>

    <!-- Discount -->
    <div class="co-section">
      <div class="co-label">Remise</div>
      <div class="disc-btns">
        <button class="disc-btn active" id="discNoneBtn" onclick="applyDisc('none')">Aucune</button>
        <button class="disc-btn" id="discPctBtn"  onclick="applyDisc('pct')">% Remise</button>
        <button class="disc-btn" id="discFixBtn"  onclick="applyDisc('fix')">XAF Fixe</button>
      </div>
      <input class="co-input" id="discInput" type="number" min="0" placeholder="Valeur..."
             style="display:none;margin-top:6px" oninput="applyDiscVal(this.value)"/>
    </div>

    <!-- Payment -->
    <div class="co-section">
      <div class="co-label">Paiement</div>
      <div class="pay-modes">
        <button class="pay-mode-btn" onclick="selectPayMode('especes')">💵<br>Espèces</button>
        <button class="pay-mode-btn" onclick="selectPayMode('mtn_momo')">📱<br>MTN MoMo</button>
        <button class="pay-mode-btn" onclick="selectPayMode('orange_money')">🟠<br>Orange</button>
      </div>
      <div id="payInputRow" class="co-row-2" style="display:none;margin-top:8px">
        <input class="co-input" id="payAmount" type="number" min="0" placeholder="Montant (XAF)"/>
        <input class="co-input" id="payRef"    type="text"   placeholder="Réf MoMo (optionnel)"/>
      </div>
      <button id="addPayBtn" onclick="addPayment()"
        style="display:none;width:100%;margin-top:6px;padding:9px;background:var(--navy,#0B1F3A);
               color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer">
        ➕ Ajouter ce paiement
      </button>
      <div id="paymentList" style="margin-top:6px"></div>
    </div>

    <!-- Totals -->
    <div class="co-section">
      <div class="totals-box">
        <div class="tot-row"><span>Sous-total</span><span id="subTotal">0 XAF</span></div>
        <div class="tot-row"><span>Remise</span><span id="discountAmount">- 0 XAF</span></div>
        <div class="tot-row"><span>TVA</span><span id="tvaAmount">0 XAF</span></div>
        <div class="tot-row grand"><span>TOTAL</span><span id="grandTotal">0 XAF</span></div>
        <div class="tot-row remaining"><span>Reste à payer</span><span id="remainingAmount">0 XAF</span></div>
        <div class="tot-row change"><span>Monnaie rendue</span><span id="changeAmount">0 XAF</span></div>
      </div>
    </div>

    <!-- Note -->
    <div class="co-section">
      <textarea class="co-input" id="saleNote" rows="2"
                placeholder="Note (optionnelle)..." style="resize:none"></textarea>
    </div>

    <!-- Actions -->
    <div class="co-btns">
      <button class="btn-clear" onclick="clearCart()">🗑 Vider</button>
      <button class="btn-sale"  onclick="doSale()">✅ Valider la vente</button>
    </div>

  </div><!-- /#cpBody -->
</div>

<!-- ══ SCANNER ══ -->
<div class="scan-modal" id="scanModal">
  <div class="scan-frame"><div id="reader"></div></div>
  <div class="scan-hint" id="scanHint">📷 Pointez la caméra vers le code-barres</div>
  <button class="scan-close" id="scanClose" type="button">✕ Fermer</button>
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
      <button class="rec-wa"  id="recWa"  type="button">💬 Envoyer sur WhatsApp</button>
      <button class="rec-print" id="recPrint" type="button">🖨️ Imprimer la facture</button>
      <button class="rec-new" id="recNew" type="button">🛒 Nouvelle vente</button>
    </div>
  </div>
</div>

<!-- ══ TOAST ══ -->
<div class="pos-toast" id="posToast"></div>

<script src="caisse.js"></script>
</body>
</html>