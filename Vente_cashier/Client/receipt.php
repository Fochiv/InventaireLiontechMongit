<?php
/* receipt.php — Tally Client Full Receipt View */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo = getDB(); ensureClientTables($pdo);
$loggedIn = isClientLoggedIn();
$client   = $loggedIn ? currentClient() : [];
$LOGO     = APP_URL . '/Image/TALLYLOGO.png';

$rid   = (int)($_GET['rid']   ?? 0);
$token = trim($_GET['token']  ?? '');
$phone = preg_replace('/[^\d\+]/','',trim($_GET['phone'] ?? ($client['phone'] ?? '')));

$receipt = null; $snapshot = null;
if ($token) {
    $s = $pdo->prepare("SELECT * FROM receipts WHERE public_token=? LIMIT 1");
    $s->execute([$token]); $receipt = $s->fetch(PDO::FETCH_ASSOC);
} elseif ($rid > 0) {
    $s = $pdo->prepare("SELECT * FROM receipts WHERE receipt_id=? LIMIT 1");
    $s->execute([$rid]); $receipt = $s->fetch(PDO::FETCH_ASSOC);
}

/* Security: guest must provide matching phone OR token */
if ($receipt && !$token && $phone && $receipt['client_phone'] && $receipt['client_phone'] !== $phone) {
    if (!$loggedIn || $client['phone'] !== $receipt['client_phone']) {
        http_response_code(403); exit('<h2>Accès refusé / Access denied</h2>');
    }
}

if ($receipt && !empty($receipt['receipt_snapshot'])) {
    $snapshot = json_decode($receipt['receipt_snapshot'], true);
}
if (!$snapshot && $receipt) {
    $snapshot = buildReceiptSnapshot($pdo, (int)$receipt['business_id'], (int)$receipt['transaction_id']);
}
if (!$snapshot) { http_response_code(404); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8"/><link rel="stylesheet" href="client.css"/></head>
<body class="cl-body" style="display:flex;align-items:center;justify-content:center;min-height:100vh">
<div style="text-align:center"><div style="font-size:48px">🔍</div>
<h2 data-i="not_found">Reçu introuvable</h2>
<a href="client.php" class="cl-btn-primary" style="margin-top:16px" data-i="back_home">← Accueil</a>
</div><script src="i18n.js"></script><script src="client.js"></script></body></html>
<?php exit; } ?>
<!DOCTYPE html><html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<title>Reçu <?=h($receipt['receipt_number']??'')?> — Tally</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?=$LOGO?>"/>
<link rel="stylesheet" href="client.css"/>
</head>
<body class="cl-body">

<!-- NAV -->
<nav class="cl-nav">
  <a href="dashboard.php?phone=<?=urlencode($phone)?>" class="cl-nav-link">
    ← <span data-i="back">Retour</span>
  </a>
  <a href="client.php" class="cl-nav-brand">
    <img src="<?=$LOGO?>" alt="Tally" class="cl-nav-logo"/>
    <span class="cl-nav-name">Tally</span>
  </a>
  <div class="cl-nav-right">
    <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    <?php if($loggedIn): ?>
    <a href="profile.php" class="cl-av"><?=strtoupper(substr($client['name']?:'C',0,2))?></a>
    <?php endif; ?>
  </div>
</nav>

<?php
$biz    = $snapshot['business']    ?? [];
$tx     = $snapshot['transaction'] ?? [];
$items  = $snapshot['items']       ?? [];
$pays   = $snapshot['payments']    ?? [];
$color  = $biz['brand_color'] ?? '#0B1F3A';
$bizName= $biz['brand_name'] ?? 'Business';
$typeOp = $tx['type_operation'] ?? 'vente';
$invoice= $tx['numero_facture'] ?? ($receipt['receipt_number'] ?? '');
$payLabels = ['especes'=>'💵 Espèces','mtn_momo'=>'📱 MTN MoMo','orange_money'=>'🟠 Orange Money'];
?>

<div class="rc-wrap">
  <!-- Action bar (non-print) -->
  <div class="rc-actions no-print">
    <button onclick="window.print()" class="rc-btn-print">🖨️ <span data-i="print">Imprimer</span></button>

    <?php if($loggedIn): ?>
    <button class="rc-btn-save" id="saveBtn" onclick="doSave()">☆ <span data-i="save_receipt">Sauvegarder</span></button>
    <?php else: ?>
    <a href="register.php?phone=<?=urlencode($phone)?>" class="rc-btn-save" data-i="save_receipt">☆ Sauvegarder</a>
    <?php endif; ?>

    <button onclick="openReport()" class="rc-btn-report">🚩 <span data-i="report">Signaler</span></button>
  </div>

  <!-- RECEIPT PAPER -->
  <div class="rc-paper" id="rcPaper">

    <!-- Header band -->
    <div class="rc-header" style="background:<?=h($color)?>">
      <?php if(!empty($biz['logo_url'])): ?>
      <img src="<?=h($biz['logo_url'])?>" alt="Logo" class="rc-biz-logo"/>
      <?php endif; ?>
      <div class="rc-biz-info">
        <div class="rc-biz-name"><?=h($bizName)?></div>
        <?php if($biz['city']||$biz['address']): ?>
        <div class="rc-biz-addr"><?=h(trim($biz['city'].' '.$biz['address']))?></div>
        <?php endif; ?>
        <?php if($biz['phone']): ?><div class="rc-biz-phone">📞 <?=h($biz['phone'])?></div><?php endif; ?>
        <?php if($biz['email']): ?><div class="rc-biz-email">✉️ <?=h($biz['email'])?></div><?php endif; ?>
      </div>
    </div>

    <!-- Invoice info -->
    <div class="rc-invoice-band">
      <div>
        <div class="rc-invoice-num">🧾 <?=h($invoice)?></div>
        <div class="rc-invoice-date">📅 <?=date('d/m/Y H:i',strtotime($tx['created_at']??'now'))?></div>
      </div>
      <div class="rc-badge rc-badge-<?=$typeOp==='vente'?'paid':($typeOp==='remboursement'?'remb':'warn')?>">
        <?=$typeOp==='vente'?'✓ PAYÉ':($typeOp==='remboursement'?'↩ REMB':'⚠️')?>
      </div>
    </div>

    <!-- Cashier + Client -->
    <div class="rc-meta-row">
      <?php if(($biz['show_cashier']??1) && !empty($tx['cashier_name']??$tx['caissier_id'])): ?>
      <div class="rc-meta-item">
        <span class="rc-meta-lbl" data-i="cashier">Caissier</span>
        <span><?=h($tx['cashier_name']??'—')?></span>
      </div>
      <?php endif; ?>
      <?php if(!empty($tx['client_nom'])): ?>
      <div class="rc-meta-item">
        <span class="rc-meta-lbl" data-i="client">Client</span>
        <span><?=h($tx['client_nom'])?></span>
      </div>
      <?php endif; ?>
      <?php if(!empty($tx['client_phone']) && ($biz['show_client_phone']??1)): ?>
      <div class="rc-meta-item">
        <span class="rc-meta-lbl">📱</span>
        <span><?=h($tx['client_phone'])?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="rc-divider"></div>

    <!-- Items -->
    <table class="rc-items">
      <thead>
        <tr>
          <th data-i="item_name">Article</th>
          <th data-i="item_qty">Qté</th>
          <th data-i="item_price">Prix U.</th>
          <th data-i="item_total">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($items as $it): ?>
        <tr>
          <td><?=h($it['product_name']??$it['nom_produit']??'—')?></td>
          <td class="rc-center"><?=h($it['quantite']??$it['qty']??1)?></td>
          <td class="rc-right"><?=fmtXAF($it['prix_unitaire']??$it['unit_price']??0)?></td>
          <td class="rc-right"><?=fmtXAF($it['total_ligne']??$it['total']??0)?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="rc-divider"></div>

    <!-- Totals -->
    <div class="rc-totals">
      <div class="rc-tot-row"><span data-i="subtotal">Sous-total</span><span><?=fmtXAF($tx['sous_total']??0)?></span></div>
      <?php if(($tx['remise_montant']??0)>0): ?>
      <div class="rc-tot-row rc-disc"><span data-i="discount">Remise</span><span>−<?=fmtXAF($tx['remise_montant'])?></span></div>
      <?php endif; ?>
      <?php if(($tx['tva_active']??0)): ?>
      <div class="rc-tot-row"><span>TVA <?=h($tx['tva_taux']??19.25)?>%</span><span><?=fmtXAF($tx['tva_montant']??0)?></span></div>
      <?php endif; ?>
      <div class="rc-tot-row rc-grand"><span data-i="total">TOTAL</span><span><?=fmtXAF($tx['total_ttc']??0)?></span></div>
      <?php if(($tx['monnaie_rendue']??0)>0): ?>
      <div class="rc-tot-row"><span data-i="change">Monnaie rendue</span><span><?=fmtXAF($tx['monnaie_rendue'])?></span></div>
      <?php endif; ?>
    </div>

    <div class="rc-divider"></div>

    <!-- Payments -->
    <div class="rc-pays">
      <div class="rc-pays-title" data-i="payment">Paiement</div>
      <?php foreach($pays as $pay): ?>
      <div class="rc-pay-row">
        <span><?=$payLabels[$pay['mode']??'']??h($pay['mode']??'')?></span>
        <span><?=fmtXAF($pay['montant']??0)?></span>
      </div>
      <?php if(!empty($pay['reference'])): ?>
      <div class="rc-pay-ref">Réf: <?=h($pay['reference'])?></div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <!-- Return policy -->
    <?php if(!empty($biz['return_policy'])): ?>
    <div class="rc-policy">📋 <?=h($biz['return_policy'])?></div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="rc-footer-msg">
      <?=h($biz['footer_message']??'Merci pour votre achat.')?>
    </div>
    <div class="rc-powered">
      <img src="<?=$LOGO?>" alt="Tally" style="width:20px;height:20px;object-fit:contain"/>
      Tally · Powered by LionTech
    </div>

  </div><!-- /.rc-paper -->
</div><!-- /.rc-wrap -->

<!-- Report modal -->
<div class="cl-modal-overlay" id="reportModal" style="display:none">
  <div class="cl-modal">
    <h3>🚩 <span data-i="report_title">Signaler ce reçu</span></h3>
    <textarea id="reportText" rows="3" placeholder="Décrivez le problème..."></textarea>
    <div class="cl-modal-btns">
      <button onclick="document.getElementById('reportModal').style.display='none'" data-i="cancel">Annuler</button>
      <button class="cl-modal-submit" onclick="submitReport()" data-i="send">Envoyer</button>
    </div>
  </div>
</div>
<div class="cl-toast" id="clToast"></div>

<script src="i18n.js"></script>
<script src="client.js"></script>
<script>
const CL_PHONE='<?=h($phone)?>', CL_LOGGED=<?=json_encode($loggedIn)?>, CL_RID=<?=json_encode($rid)?>;
function doSave(){
  const btn=document.getElementById('saveBtn');
  clApi({action:'save_receipt',receipt_id:CL_RID}).then(j=>{
    if(j.success){ btn.textContent='★ Sauvegardé'; btn.classList.add('is-saved'); clToast(I18N[CL_LANG].saved_ok); }
    else if(j.code==='login_required') window.location.href='login.php';
  });
}
function openReport(){ document.getElementById('reportModal').style.display='flex'; }
function submitReport(){
  const r=document.getElementById('reportText').value.trim(); if(!r)return;
  clApi({action:'report_receipt',receipt_id:CL_RID,reason:r}).then(j=>{
    if(j.success){ document.getElementById('reportModal').style.display='none'; clToast(I18N[CL_LANG].reported); }
  });
}
</script>
</body></html>