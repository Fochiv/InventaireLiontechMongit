<?php
/* profile.php — Tally Client Profile & QR */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo=getDB(); ensureClientTables($pdo);
requireClientLogin(APP_URL.'/Vente_cashier/Client/profile.php');
$cl   = currentClient();
$LOGO = APP_URL.'/Image/TALLYLOGO.png';
$row  = $pdo->prepare("SELECT * FROM clients WHERE client_id=? LIMIT 1");
$row->execute([$cl['client_id']]); $row=$row->fetch(PDO::FETCH_ASSOC);

/* Stats */
$stats=$pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(r.total_amount),0) total,
    SUM(COALESCE(cra.is_saved,0)) saved
    FROM receipts r LEFT JOIN client_receipt_actions cra
    ON cra.receipt_id=r.receipt_id AND cra.client_phone=?
    WHERE r.client_phone=?");
$stats->execute([$cl['phone'],$cl['phone']]); $stats=$stats->fetch();

/* Favorite businesses */
$favs=$pdo->prepare("SELECT DISTINCT b.business_name, b.phone
    FROM client_receipt_actions cra JOIN businesses b ON b.business_id=cra.business_id
    WHERE cra.client_phone=? AND cra.is_favorite_business=1 LIMIT 10");
$favs->execute([$cl['phone']]); $favs=$favs->fetchAll();

$qrUrl = APP_URL.'/Vente_cashier/Client/client.php?phone='.urlencode($cl['phone']);
$qrToken = $row['qr_token']??'';

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['name'])){
    $newName=trim($_POST['name']??'');
    if($newName){ $pdo->prepare("UPDATE clients SET full_name=? WHERE client_id=?")->execute([$newName,$cl['client_id']]);
        $_SESSION['cl_name']=$newName; $msg='<span class="icon-ok">✓</span> Profil mis à jour'; }
}
?>
<!DOCTYPE html><html lang="fr">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<title>Mon Profil — LionTech</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?=$LOGO?>"/>
<link rel="stylesheet" href="client.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body class="cl-body">

<nav class="cl-nav">
  <a href="dashboard.php" class="cl-nav-link">← <span data-i="my_receipts">Mes reçus</span></a>
  <a href="client.php" class="cl-nav-brand">
    <img src="<?=$LOGO?>" alt="Tally" class="cl-nav-logo"/>
    <span class="cl-nav-name">Tally</span>
  </a>
  <div class="cl-nav-right">
    <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    <a href="logout.php" class="cl-nav-outline-btn" data-i="logout">Déconnexion</a>
  </div>
</nav>

<div class="pf-wrap">

  <!-- Profile card -->
  <div class="pf-card">
    <div class="pf-avatar"><?=strtoupper(substr($cl['name']?:'C',0,2))?></div>
    <div class="pf-name"><?=h($cl['name'])?></div>
    <div class="pf-phone"><span class="icon-phone"><span class="icon-phone">☎</span></span> <?=h($cl['phone'])?></div>
    <?php if($msg): ?><div class="cl-success-msg"><?=h($msg)?></div><?php endif; ?>
    <form method="POST" class="pf-edit-form">
      <input type="text" name="name" value="<?=h($cl['name'])?>" placeholder="Nom complet"/>
      <button type="submit" class="cl-btn-sm" data-i="update">Modifier</button>
    </form>
  </div>

  <!-- Stats -->
  <div class="db-stats pf-stats">
    <div class="db-stat cl-stat-blue"><div class="db-stat-val"><?=(int)$stats['cnt']?></div><div class="db-stat-lbl" data-i="stat_receipts">Reçus totaux</div></div>
    <div class="db-stat cl-stat-green"><div class="db-stat-val"><?=fmtXAF($stats['total'])?></div><div class="db-stat-lbl" data-i="stat_spent">Dépenses totales</div></div>
    <div class="db-stat cl-stat-gold"><div class="db-stat-val"><?=(int)$stats['saved']?></div><div class="db-stat-lbl" data-i="stat_saved">Sauvegardés</div></div>
  </div>

  <!-- QR CODE section -->
  <div class="pf-qr-card">
    <h2 class="pf-qr-title"><span class="icon-sq">▪</span> <span data-i="qr_title">Mon QR Code</span></h2>
    <p class="pf-qr-sub" data-i="qr_sub">Montrez ce QR au caissier pour lier vos reçus automatiquement à votre compte.</p>
    <div id="qrcode" class="pf-qr-box"></div>
    <div class="pf-qr-token">Token: <code><?=h(substr($qrToken,0,12))?>...</code></div>
    <div class="pf-qr-actions">
      <button onclick="downloadQR()" class="cl-btn-sm" data-i="download_qr"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Télécharger</button>
      <button onclick="copyPhone()" class="cl-btn-sm cl-btn-outline-sm" data-i="copy_phone"><span class="icon-list">≡</span> Copier le numéro</button>
    </div>
  </div>

  <!-- Favorite shops -->
  <?php if(!empty($favs)): ?>
  <div class="pf-favs">
    <h3 data-i="fav_shops"><span class="icon-star">★</span> Mes commerces favoris</h3>
    <div class="pf-fav-grid">
      <?php foreach($favs as $f): ?>
      <div class="pf-fav-chip">
        <span>🏪</span>
        <span><?=h($f['business_name'])?></span>
        <?php if($f['phone']): ?><small><?=h($f['phone'])?></small><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="text-align:center;margin-top:24px">
    <a href="dashboard.php" class="cl-btn-primary" data-i="view_receipts"><span class="icon-receipt">▤</span> Voir mes reçus</a>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="i18n.js"></script>
<script src="client.js"></script>
<script>
const QR_DATA = <?=json_encode($qrUrl)?>;
const qr = new QRCode(document.getElementById('qrcode'),{
  text: QR_DATA, width:200, height:200,
  colorDark:'#0B1F3A', colorLight:'#ffffff',
  correctLevel: QRCode.CorrectLevel.H
});
function downloadQR(){
  setTimeout(()=>{
    const img=document.querySelector('#qrcode img');
    if(img){ const a=document.createElement('a'); a.href=img.src; a.download='tally-qr.png'; a.click(); }
  },200);
}
function copyPhone(){
  navigator.clipboard.writeText(<?=json_encode($cl['phone'])?>).then(()=>clToast('<span class="icon-list">≡</span> Copié !'));
}
</script>
</body></html>