<?php
/* dashboard.php — Tally Client Receipt Dashboard */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo = getDB(); ensureClientTables($pdo);
$loggedIn = isClientLoggedIn();
$client   = $loggedIn ? currentClient() : [];
$LOGO     = APP_URL . '/Image/TALLYLOGO.png';

/* Phone resolution */
$phone = '';
if ($loggedIn)            $phone = $client['phone'];
elseif(!empty($_GET['phone'])) $phone = preg_replace('/[^\d\+]/', '', trim($_GET['phone']));
if (!$phone) { header('Location: ' . clUrl('client.php')); exit; }

/* Filters */
$fBiz   = trim($_GET['biz']   ?? '');
$fMonth = (int)($_GET['month'] ?? 0);
$fYear  = (int)($_GET['year']  ?? date('Y'));
$fCat   = trim($_GET['cat']   ?? '');
$fNum   = trim($_GET['num']   ?? '');

/* Query */
$where = ["r.client_phone = ?"]; $params = [$phone];
if($fBiz) { $where[] = "(b.business_name LIKE ? OR b.phone LIKE ?)"; $params[]="%$fBiz%"; $params[]="%$fBiz%"; }
if($fNum) { $where[] = "r.receipt_number LIKE ?"; $params[]="%$fNum%"; }
if($fYear) { $where[] = "YEAR(r.created_at)=?"; $params[]=$fYear; }
if($fMonth){ $where[] = "MONTH(r.created_at)=?"; $params[]=$fMonth; }

$wStr = implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT r.*, b.business_name, b.phone AS biz_phone, b.city,
           COALESCE(cra.is_saved,0) is_saved, COALESCE(cra.is_hidden,0) is_hidden,
           COALESCE(cra.is_reported,0) is_reported,
           COALESCE(cra.category,'other') category,
           COALESCE(cra.is_favorite_business,0) is_fav
    FROM receipts r
    JOIN businesses b ON b.business_id=r.business_id
    LEFT JOIN client_receipt_actions cra ON cra.receipt_id=r.receipt_id AND cra.client_phone=?
    WHERE $wStr AND COALESCE(cra.is_hidden,0)=0
    ORDER BY r.created_at DESC LIMIT 150
");
$stmt->execute(array_merge([$phone], $params));
$rows = $stmt->fetchAll();

/* Filter by category after fetch (can't join well without cra row) */
if($fCat) $rows = array_filter($rows, fn($r)=>($r['category']??'other')===$fCat);

/* Summary */
$sm = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(r.total_amount),0) total,
    COUNT(DISTINCT r.business_id) biz_count, SUM(COALESCE(cra.is_saved,0)) saved_count
    FROM receipts r LEFT JOIN client_receipt_actions cra
    ON cra.receipt_id=r.receipt_id AND cra.client_phone=?
    WHERE r.client_phone=? AND YEAR(r.created_at)=? AND MONTH(r.created_at)=?");
$sm->execute([$phone,$phone,$fYear,$fMonth?:date('m')]);
$sum = $sm->fetch();

/* Expiry warning */
$exp = $pdo->prepare("SELECT COUNT(*) FROM receipts r LEFT JOIN client_receipt_actions cra
    ON cra.receipt_id=r.receipt_id AND cra.client_phone=?
    WHERE r.client_phone=? AND COALESCE(cra.is_saved,0)=0 AND r.created_at < DATE_SUB(NOW(),INTERVAL 6 MONTH)");
$exp->execute([$phone,$phone]); $expCount = (int)$exp->fetchColumn();
$todayCount = count(array_filter($rows, fn($r)=>date('Y-m-d',strtotime($r['created_at']))===date('Y-m-d')));
?>
<!DOCTYPE html><html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<title>Mes Reçus — Tally</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?= $LOGO ?>"/>
<link rel="stylesheet" href="client.css"/>
</head>
<body class="cl-body">

<nav class="cl-nav">
  <a href="client.php" class="cl-nav-back" title="Accueil">←</a>
  <a href="client.php" class="cl-nav-brand">
    <img src="<?= $LOGO ?>" alt="Tally" class="cl-nav-logo"/>
    <span class="cl-nav-name">Tally</span>
  </a>
  <div class="cl-nav-right">
    <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    <?php if($loggedIn): ?>
    <a href="profile.php" class="cl-av"><?= strtoupper(substr($client['name']?:'C',0,2)) ?></a>
    <a href="logout.php" class="cl-nav-icon-btn" title="Déconnexion">⏻</a>
    <?php else: ?>
    <a href="login.php" class="cl-nav-link" data-i="login">Connexion</a>
    <?php endif; ?>
  </div>
</nav>

<div class="db-layout">

  <!-- SIDEBAR FILTERS -->
  <aside class="db-sidebar">
    <div class="db-sidebar-title">🔍 <span data-i="filters">Filtres</span></div>
    <form method="GET" action="dashboard.php">
      <input type="hidden" name="phone" value="<?= h($phone) ?>"/>
      <div class="db-fgroup">
        <label data-i="filter_biz">Enseigne</label>
        <input type="text" name="biz" value="<?= h($fBiz) ?>" placeholder="Nom ou tél du commerce"/>
      </div>
      <div class="db-fgroup">
        <label data-i="filter_invoice">N° Facture</label>
        <input type="text" name="num" value="<?= h($fNum) ?>" placeholder="FAC-2026-..."/>
      </div>
      <div class="db-fgroup">
        <label data-i="filter_cat">Catégorie</label>
        <select name="cat">
          <option value="">— <span data-i="all">Toutes</span></option>
          <?php foreach(CAT_ICONS as $k=>$c): ?>
          <option value="<?=$k?>" <?=$fCat===$k?'selected':''?>><?=$c['icon']?> <?=$c['fr']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="db-fgroup">
        <label data-i="filter_month">Mois</label>
        <select name="month">
          <option value="">— <span data-i="all">Tous</span></option>
          <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?=$m?>" <?=$fMonth===$m?'selected':''?>><?=date('F',mktime(0,0,0,$m,1))?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="db-fgroup">
        <label data-i="filter_year">Année</label>
        <select name="year">
          <?php for($y=date('Y');$y>=2024;$y--): ?>
          <option value="<?=$y?>" <?=$fYear===$y?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="db-filter-btn" data-i="apply">Appliquer</button>
      <a href="dashboard.php?phone=<?=urlencode($phone)?>" class="db-clear-btn" data-i="clear">Effacer</a>
    </form>
  </aside>

  <!-- MAIN -->
  <main class="db-main">

    <!-- Header -->
    <div class="db-header">
      <div class="db-header-left">
        <h1 class="db-title">
          <?php if($loggedIn): ?>👋 <?= h($client['name']) ?>
          <?php else: ?>📱 <?= h($phone) ?><?php endif; ?>
        </h1>
        <p class="db-subtitle">
          <?=count($rows)?> <span data-i="receipts_found">reçu(s) trouvé(s)</span>
          <?php if($todayCount): ?> · <span class="db-today-tag"><?=$todayCount?> <span data-i="today">aujourd'hui</span></span><?php endif; ?>
        </p>
      </div>
      <?php if(!$loggedIn): ?>
      <a href="register.php?phone=<?=urlencode($phone)?>" class="cl-btn-pink" data-i="save_permanently">✨ Sauvegarder mes reçus</a>
      <?php else: ?>
      <a href="profile.php" class="cl-btn-pink" data-i="my_qr">🔲 Mon QR Code</a>
      <?php endif; ?>
    </div>

    <!-- Expiry warning -->
    <?php if($expCount>0): ?>
    <div class="db-warning">
      ⏰ <strong><?=$expCount?> <span data-i="expiry_warn">reçu(s) non-sauvegardé(s) vieux de +6 mois seront bientôt supprimés.</span></strong>
      <?php if(!$loggedIn): ?> <a href="register.php?phone=<?=urlencode($phone)?>" data-i="create_to_save">Créez un compte pour les garder →</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="db-stats">
      <div class="db-stat cl-stat-blue">
        <div class="db-stat-val"><?=fmtXAF($sum['total'])?></div>
        <div class="db-stat-lbl" data-i="stat_spent">Dépensé ce mois</div>
      </div>
      <div class="db-stat cl-stat-green">
        <div class="db-stat-val"><?=(int)$sum['cnt']?></div>
        <div class="db-stat-lbl" data-i="stat_receipts">Reçus</div>
      </div>
      <div class="db-stat cl-stat-pink">
        <div class="db-stat-val"><?=(int)$sum['biz_count']?></div>
        <div class="db-stat-lbl" data-i="stat_shops">Commerces</div>
      </div>
      <div class="db-stat cl-stat-gold">
        <div class="db-stat-val"><?=(int)$sum['saved_count']?></div>
        <div class="db-stat-lbl" data-i="stat_saved">Sauvegardés</div>
      </div>
    </div>

    <!-- Cards -->
    <?php if(empty($rows)): ?>
    <div class="db-empty">
      <div class="db-empty-icon">🧾</div>
      <div class="db-empty-title" data-i="no_receipts">Aucun reçu trouvé</div>
      <div class="db-empty-sub" data-i="no_receipts_sub">Aucun achat enregistré pour ce numéro.</div>
      <a href="client.php" class="cl-btn-primary" style="margin-top:16px" data-i="back_home">← Accueil</a>
    </div>
    <?php else: ?>
    <div class="db-grid">
    <?php
    $palette = ['blue','green','pink','teal','gold'];
    foreach($rows as $i=>$r):
      $cat    = $r['category'] ?? 'other';
      $ci     = CAT_ICONS[$cat] ?? CAT_ICONS['other'];
      $isToday= date('Y-m-d',strtotime($r['created_at']))===date('Y-m-d');
      $isSaved= (bool)$r['is_saved'];
      $isWarn = in_array($cat, warrantyCategories());
      $color  = $palette[$i % count($palette)];
    ?>
    <div class="db-card db-card-<?=$color?>" data-rid="<?=$r['receipt_id']?>">
      <?php if($isToday): ?><span class="db-today-badge" data-i="today">Aujourd'hui</span><?php endif; ?>
      <?php if($isSaved): ?><span class="db-saved-badge">⭐</span><?php endif; ?>

      <div class="db-card-top">
        <div class="db-card-cat-icon"><?=$ci['icon']?></div>
        <div class="db-card-biz">
          <div class="db-card-bizname"><?=h($r['business_name'])?></div>
          <?php if($r['biz_phone']): ?><div class="db-card-bizphone">📞 <?=h($r['biz_phone'])?></div><?php endif; ?>
        </div>
        <div class="db-card-amount"><?=fmtXAF($r['total_amount'])?></div>
      </div>

      <div class="db-card-meta">
        <span>🧾 <?=h($r['receipt_number'])?></span>
        <span>📅 <?=date('d/m/Y H:i',strtotime($r['created_at']))?></span>
      </div>

      <?php if($isWarn): ?>
      <div class="db-warranty-note">🛡️ <span data-i="warranty_note">Conservez ce reçu pour la garantie</span></div>
      <?php endif; ?>

      <div class="db-card-actions">
        <a href="receipt.php?rid=<?=$r['receipt_id']?>&phone=<?=urlencode($phone)?>"
           class="db-act-view">👁 <span data-i="view">Voir</span></a>

        <?php if($loggedIn): ?>
        <button class="db-act-save <?=$isSaved?'is-saved':''?>"
                onclick="toggleSave(<?=$r['receipt_id']?>,this)">
          <?=$isSaved?'★':'☆'?> <span data-i-save="save" data-i-unsave="saved"><?=$isSaved?'Sauvegardé':'Sauvegarder'?></span>
        </button>
        <div class="db-more-wrap">
          <button class="db-act-more-btn" onclick="toggleMore(this)">⋯</button>
          <div class="db-dropdown">
            <button onclick="openCats(<?=$r['receipt_id']?>)">🏷️ <span data-i="categorize">Catégoriser</span></button>
            <button onclick="doHide(<?=$r['receipt_id']?>,this)">🙈 <span data-i="hide">Masquer</span></button>
            <button class="db-dd-danger" onclick="openReport(<?=$r['receipt_id']?>)">🚩 <span data-i="report">Signaler</span></button>
          </div>
        </div>
        <?php else: ?>
        <a href="register.php?phone=<?=urlencode($phone)?>" class="db-act-save-guest">☆ <span data-i="login_to_save">Connexion</span></a>
        <?php endif; ?>
      </div>

      <?php if($loggedIn): ?>
      <div class="db-cat-row" id="cats-<?=$r['receipt_id']?>" style="display:none">
        <?php foreach(CAT_ICONS as $k=>$c): ?>
        <button onclick="doSetCat(<?=$r['receipt_id']?>,'<?=$k?>')"
                class="db-cat-chip <?=$cat===$k?'active':''?>"><?=$c['icon']?> <?=$c['fr']?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>

<!-- Report modal -->
<div class="cl-modal-overlay" id="reportModal" style="display:none">
  <div class="cl-modal">
    <h3>🚩 <span data-i="report_title">Signaler un reçu incorrect</span></h3>
    <textarea id="reportText" rows="3" placeholder="Décrivez le problème..."></textarea>
    <div class="cl-modal-btns">
      <button onclick="document.getElementById('reportModal').style.display='none'" data-i="cancel">Annuler</button>
      <button class="cl-modal-submit" onclick="doReport()" data-i="send">Envoyer</button>
    </div>
  </div>
</div>
<div class="cl-toast" id="clToast"></div>

<script src="i18n.js"></script>
<script src="client.js"></script>
<script>
const CL_PHONE='<?=h($phone)?>', CL_LOGGED=<?=json_encode($loggedIn)?>;
let _repId=null;
function toggleSave(rid,btn){
  const saved=btn.classList.contains('is-saved');
  clApi({action:saved?'unsave_receipt':'save_receipt',receipt_id:rid}).then(j=>{
    if(!j.success)return;
    btn.classList.toggle('is-saved');
    btn.querySelector('span').textContent=saved?I18N[CL_LANG].save:I18N[CL_LANG].saved;
    btn.firstChild.textContent=saved?'☆':'★';
    clToast(saved?I18N[CL_LANG].unsaved:I18N[CL_LANG].saved_ok);
  });
}
function doHide(rid,btn){
  if(!confirm(I18N[CL_LANG].hide_confirm))return;
  clApi({action:'hide_receipt',receipt_id:rid}).then(j=>{
    if(j.success){ const c=btn.closest('.db-card'); c.style.opacity='0'; setTimeout(()=>c.remove(),350); }
  });
}
function openCats(rid){ const el=document.getElementById('cats-'+rid); el.style.display=el.style.display==='none'?'flex':'none'; }
function doSetCat(rid,cat){
  clApi({action:'set_category',receipt_id:rid,category:cat}).then(j=>{
    if(j.success){ document.getElementById('cats-'+rid).style.display='none'; clToast('✅'); }
  });
}
function openReport(rid){ _repId=rid; document.getElementById('reportModal').style.display='flex'; }
function doReport(){
  const r=document.getElementById('reportText').value.trim(); if(!r)return;
  clApi({action:'report_receipt',receipt_id:_repId,reason:r}).then(j=>{
    if(j.success){ document.getElementById('reportModal').style.display='none'; clToast(I18N[CL_LANG].reported); }
  });
}
function toggleMore(btn){ const dd=btn.nextElementSibling; dd.classList.toggle('open'); }
document.addEventListener('click',e=>{ if(!e.target.classList.contains('db-act-more-btn')) document.querySelectorAll('.db-dropdown.open').forEach(d=>d.classList.remove('open')); });
</script>
</body></html>