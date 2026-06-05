<?php
ob_start();
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cleanPhone(string $p): string { return preg_replace('/\s+/', '', trim($p)); }
function slugText(string $t): string {
    $t = strtolower(trim($t));
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = preg_replace('/[^a-z0-9]+/', '.', $t);
    return trim($t, '.') ?: 'business';
}
function generateOwnerUsername(PDO $pdo, string $name): string {
    $base = slugText($name) . '.proprietaire';
    $login = $base; $i = 2;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
    while (true) { $stmt->execute([$login]); if (!(int)$stmt->fetchColumn()) return $login; $login = $base . $i++; }
}
function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");

    $stmt->execute([$tableName]);

    return (int)$stmt->fetchColumn() > 0;
}
function checkedFeature(array $old, string $k): string { return in_array($k, $old['features']??[], true)?'checked':''; }

$errors = [];
$old = [
    'business_name'=>'','business_type'=>'','city'=>'','address'=>'','phone'=>'','business_email'=>'',
    'owner_name'=>'','owner_phone'=>'','owner_email'=>'','owner_username'=>'',
    'plan_name'=>'Basic','amount'=>'15000',
    'start_date'=>date('Y-m-d'),'end_date'=>date('Y-m-d',strtotime('+30 days')),
    'status'=>'active','features'=>['inventory_management','reports','low_stock_alerts'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['business_name']  = trim($_POST['business_name']  ?? '');
    $old['business_type']  = trim($_POST['business_type']  ?? '');
    $old['city']           = trim($_POST['city']           ?? '');
    $old['address']        = trim($_POST['address']        ?? '');
    $old['phone']          = cleanPhone($_POST['phone']    ?? '');
    $old['business_email'] = trim($_POST['business_email'] ?? '');
    $old['owner_name']     = trim($_POST['owner_name']     ?? '');
    $old['owner_phone']    = cleanPhone($_POST['owner_phone'] ?? '');
    $old['owner_email']    = trim($_POST['owner_email']    ?? '');
    $old['owner_username'] = trim($_POST['owner_username'] ?? '');
    $old['plan_name']      = trim($_POST['plan_name']      ?? 'Basic');
    $old['amount']         = trim($_POST['amount']         ?? '0');
    $old['start_date']     = trim($_POST['start_date']     ?? '');
    $old['end_date']       = trim($_POST['end_date']       ?? '');
    $old['status']         = trim($_POST['status']         ?? 'active');
    $old['features']       = $_POST['features']            ?? [];

    if (!$old['business_name'])  $errors[] = 'Le nom du business est obligatoire.';
    if (!$old['business_type'])  $errors[] = 'Le type de business est obligatoire.';
    if (!$old['city'])           $errors[] = 'La ville est obligatoire.';
    if (!$old['phone'])          $errors[] = 'Le téléphone du business est obligatoire.';
    if (!$old['owner_name'])     $errors[] = 'Le nom du propriétaire est obligatoire.';
    if (!$old['owner_username']) $errors[] = "L'identifiant propriétaire est obligatoire.";
    if ($old['owner_username'] && !preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $old['owner_username']))
        $errors[] = "L'identifiant propriétaire doit contenir seulement lettres, chiffres, point, tiret ou underscore.";
    if (!$old['owner_phone'])    $errors[] = 'Le téléphone du propriétaire est obligatoire.';
    if (!$old['start_date'])     $errors[] = 'La date de début est obligatoire.';
    if (!$old['end_date'])       $errors[] = "La date d'expiration est obligatoire.";
    if ($old['start_date'] && $old['end_date'] && $old['end_date'] < $old['start_date'])
        $errors[] = "La date d'expiration doit être après la date de début.";
    if (!is_numeric($old['amount']) || (float)$old['amount'] < 0)
        $errors[] = 'Le prix mensuel doit être un nombre valide.';
    if ($old['business_email'] && !filter_var($old['business_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = "L'email du business est invalide.";
    if ($old['owner_email'] && !filter_var($old['owner_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = "L'email du propriétaire est invalide.";

    if (empty($errors)) {
        $checkLogin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login_id = ?");
        $checkLogin->execute([$old['owner_username']]);
        if ((int)$checkLogin->fetchColumn() > 0) {
            $errors[] = "Cet identifiant propriétaire existe déjà. Choisissez un autre.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $subStatus  = in_array($old['status'],['trial','active','expired','suspended'],true) ? $old['status'] : 'active';
            $bizEmail   = $old['business_email'] ?: null;
            $ownerEmail = $old['owner_email']    ?: null;

            $stmt = $pdo->prepare("INSERT INTO businesses (business_name,business_type,address,phone,email,city,subscription_status,subscription_expires_at) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$old['business_name'],$old['business_type'],$old['address'],$old['phone'],$bizEmail,$old['city'],$subStatus,$old['end_date'].' 23:59:59']);
            $businessId = (int)$pdo->lastInsertId();

            $ownerLogin = $old['owner_username'];
            $tempPin    = (string)random_int(100000, 999999);
            $passHash   = password_hash($tempPin, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
    INSERT INTO users 
    (business_id, full_name, login_id, email, phone, password_hash, role, status, temporary_pin_plain, pin_must_change) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, 1)
");

$stmt->execute([
    $businessId,
    $old['owner_name'],
    $ownerLogin,
    $ownerEmail,
    $old['owner_phone'],
    $passHash,
    ROLE_BUSINESS_OWNER,
    $tempPin
]);

            if (tableExists($pdo,'subscriptions')) {
                $sps = in_array($old['status'],['trial','active','expired','cancelled'],true)?$old['status']:'active';
                $stmt = $pdo->prepare("INSERT INTO subscriptions (business_id,plan_name,amount,start_date,end_date,status,renewed_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$businessId,$old['plan_name'],(float)$old['amount'],$old['start_date'],$old['end_date'],$sps,$user['user_id']]);
            }

            if (tableExists($pdo,'business_features')) {
                $f = array_flip($old['features']);
    $stmt = $pdo->prepare("INSERT INTO business_features 
    (business_id, inventory_management, employee_management, employee_attendance, 
     sales_tracking, reports, low_stock_alerts, mobile_employee_access) 
    VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([
    $businessId,
    isset($f['inventory_management'])   ? 1 : 0,
    isset($f['employee_attendance'])    ? 1 : 0,  // ← employee_management mirrors attendance
    isset($f['employee_attendance'])    ? 1 : 0,
    isset($f['sales_tracking'])         ? 1 : 0,
    isset($f['reports'])                ? 1 : 0,
    isset($f['low_stock_alerts'])       ? 1 : 0,
    isset($f['mobile_employee_access']) ? 1 : 0,
]);
                
                }

            if (tableExists($pdo,'activity_logs')) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id,business_id,action,description,icon,ip_address) VALUES (?,?,'business_created',?,'building',?)");
                $stmt->execute([$user['user_id'],$businessId,'Nouveau business créé : '.$old['business_name'],$_SERVER['REMOTE_ADDR']??null]);
            }

            $_SESSION['new_business_credentials'] = [
                'business_id'    => $businessId,
                'business_name'  => $old['business_name'],
                'owner_name'     => $old['owner_name'],
                'owner_username' => $ownerLogin,
                'temporary_pin'  => $tempPin,
            ];

            $pdo->commit();
            ob_end_clean();
            header('Location: '.APP_URL.'/SuperAdmin/super_admin.php?created=1&biz='.urlencode($old['business_name']));
            exit;

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Erreur base de données : ' . $ex->getMessage();
        }
    }
}

$initials='';
foreach(explode(' ',$user['full_name']?:'Admin') as $w) $initials.=strtoupper($w[0]??'');
$initials=substr($initials,0,2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Ajouter un business — LionTech</title>
<link rel="stylesheet" href="../../SuperAdmin/super_admin.css"/>
<link rel="stylesheet" href="add_business.css"/>
</head>
<body>
<div class="sa-layout">

<?php $url = APP_URL; include __DIR__ . '/../../SuperAdmin/_sidebar.php'; ?>

<div class="sa-main">
<header class="sa-topbar">
  <button class="sa-hamburger" id="sa-hamburger"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
  <div style="font-size:16px;font-weight:700;color:#0B1F3A;flex:1">Ajouter un nouveau business</div>
  <div class="sa-topbar-right">
    <div class="sa-profile-av"><?= e($initials) ?></div>
  </div>
</header>

<main class="sa-content">
  <div class="ab-page-head">
    <div>
      <h1 class="ab-page-title">Créer un business client</h1>
      <p class="ab-page-sub">Ajoutez un espace privé pour un client et générez automatiquement son identifiant propriétaire.</p>
    </div>
    <div class="ab-actions">
      <a class="ab-btn ab-btn-outline" href="<?=APP_URL?>/SuperAdmin/super_admin.php">← Retour dashboard</a>
    </div>
  </div>

  <?php if ($errors): ?>
  <div class="ab-alert error" style="margin-bottom:20px">
    <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
    <ul style="margin:0;padding-left:18px">
      <?php foreach($errors as $err): ?><li><?=e($err)?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form id="add-business-form" method="POST" action="add_business.php" novalidate>
    <div class="ab-grid">

      <div class="ab-card">
        <div class="ab-card-header">
          <div><div class="ab-card-title">Informations du business</div><div class="ab-card-sub">Ces informations identifient l'entreprise dans le système.</div></div>
        </div>
        <div class="ab-card-body">

          <section class="ab-section">
            <div class="ab-section-title"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="2" width="18" height="20" rx="1"/><line x1="9" y1="22" x2="9" y2="12"/><line x1="15" y1="22" x2="15" y2="12"/><rect x="9" y="12" width="6" height="10"/></svg></span> Business</div>
            <div class="ab-form-grid">
              <div class="ab-field">
                <label class="ab-label">Nom du business <b class="ab-required">*</b></label>
                <input class="ab-input" id="business_name" name="business_name" value="<?=e($old['business_name'])?>" placeholder="Ex: Simba Restaurant" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Type de business <b class="ab-required">*</b></label>
                <select class="ab-select" id="business_type" name="business_type" required>
                  <option value="">Choisir...</option>
                  <?php foreach(['Restaurant','Snack Bar','Boutique','Salon','Pharmacie','Quincaillerie','Supermarché','Autre'] as $t): ?>
                  <option value="<?=e($t)?>" <?=$old['business_type']===$t?'selected':''?>><?=e($t)?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ab-field">
                <label class="ab-label">Ville <b class="ab-required">*</b></label>
                <input class="ab-input" id="city" name="city" value="<?=e($old['city'])?>" placeholder="Ex: Douala" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Téléphone business <b class="ab-required">*</b></label>
                <input class="ab-input" id="phone" name="phone" value="<?=e($old['phone'])?>" placeholder="+237 6XX XXX XXX" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Email business</label>
                <input class="ab-input" name="business_email" value="<?=e($old['business_email'])?>" placeholder="Optionnel">
              </div>
              <div class="ab-field full">
                <label class="ab-label">Adresse</label>
                <textarea class="ab-textarea" name="address" placeholder="Quartier, rue ou indication..."><?=e($old['address'])?></textarea>
              </div>
            </div>
          </section>

          <section class="ab-section">
            <div class="ab-section-title"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Propriétaire</div>
            <div class="ab-form-grid">
              <div class="ab-field">
                <label class="ab-label">Nom complet <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_name" name="owner_name" value="<?=e($old['owner_name'])?>" placeholder="Ex: Mr Simba" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Identifiant propriétaire <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_username" name="owner_username" value="<?=e($old['owner_username'])?>" placeholder="Ex: simba.proprietaire" required>
                <div class="ab-help">Cet identifiant servira pour la connexion du propriétaire.</div>
              </div>

              <div class="ab-field">
                <label class="ab-label">Téléphone propriétaire <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_phone" name="owner_phone" value="<?=e($old['owner_phone'])?>" placeholder="+237 6XX XXX XXX" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Email propriétaire</label>
                <input class="ab-input" name="owner_email" value="<?=e($old['owner_email'])?>" placeholder="Optionnel">
              </div>
            </div>
          </section>

          <section class="ab-section">
            <div class="ab-section-title"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg></span> Abonnement</div>
            <div class="ab-form-grid">
              <div class="ab-field">
                <label class="ab-label">Plan <b class="ab-required">*</b></label>
                <select class="ab-select" id="plan_name" name="plan_name" required>
                  <?php foreach(['Trial','Basic','Standard','Premium'] as $p): ?>
                  <option value="<?=e($p)?>" <?=$old['plan_name']===$p?'selected':''?>><?=e($p)?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ab-field">
                <label class="ab-label">Prix mensuel (XAF) <b class="ab-required">*</b></label>
                <input class="ab-input" id="amount" name="amount" type="number" min="0" step="500" value="<?=e($old['amount'])?>" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Date début <b class="ab-required">*</b></label>
                <input class="ab-input" id="start_date" name="start_date" type="date" value="<?=e($old['start_date'])?>" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Date expiration <b class="ab-required">*</b></label>
                <input class="ab-input" id="end_date" name="end_date" type="date" value="<?=e($old['end_date'])?>" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Statut</label>
                <select class="ab-select" name="status">
                  <option value="active"  <?=$old['status']==='active' ?'selected':''?>>Actif</option>
                  <option value="trial"   <?=$old['status']==='trial'  ?'selected':''?>>Essai</option>
                  <option value="expired" <?=$old['status']==='expired'?'selected':''?>>Expiré</option>
                </select>
              </div>
            </div>
          </section>

          <section class="ab-section">
            <div class="ab-section-title"><span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Modules activés</div>
            <div class="ab-checks">
              <label class="ab-check"><input type="checkbox" name="features[]" value="inventory_management" <?=checkedFeature($old,'inventory_management')?>><div><strong>Inventaire</strong><small>Produits, stock entrant/sortant</small></div></label>
              <label class="ab-check"><input type="checkbox" name="features[]" value="employee_attendance" <?=checkedFeature($old,'employee_attendance')?>><div><strong>Présence employés</strong><small>Clock in / clock out</small></div></label>
              <label class="ab-check"><input type="checkbox" name="features[]" value="sales_tracking" <?=checkedFeature($old,'sales_tracking')?>><div><strong>Ventes</strong><small>Suivi des ventes et sorties</small></div></label>
              <label class="ab-check"><input type="checkbox" name="features[]" value="reports" <?=checkedFeature($old,'reports')?>><div><strong>Rapports</strong><small>Résumé journalier/mensuel</small></div></label>
              <label class="ab-check"><input type="checkbox" name="features[]" value="low_stock_alerts" <?=checkedFeature($old,'low_stock_alerts')?>><div><strong>Alertes stock faible</strong><small>Notifications de réapprovisionnement</small></div></label>
              <label class="ab-check"><input type="checkbox" name="features[]" value="mobile_employee_access" <?=checkedFeature($old,'mobile_employee_access')?>><div><strong>Accès mobile employés</strong><small>Utilisation sur téléphone</small></div></label>
            </div>
          </section>

        </div>
        <div class="ab-footer-actions">
          <a class="ab-btn ab-btn-outline" href="<?=APP_URL?>/SuperAdmin/super_admin.php">Annuler</a>
          <button class="ab-btn ab-btn-primary" id="btn-create-business" type="submit">Créer le business</button>
        </div>
      </div>

      <aside class="ab-card">
        <div class="ab-card-header">
          <div><div class="ab-card-title">Aperçu</div><div class="ab-card-sub">Résumé avant création</div></div>
        </div>
        <div class="ab-card-body">
          <div class="ab-preview">
            <div class="ab-preview-row"><span>Business</span>     <span id="pv-business">—</span></div>
            <div class="ab-preview-row"><span>Type</span>         <span id="pv-type">—</span></div>
            <div class="ab-preview-row"><span>Ville</span>        <span id="pv-city">—</span></div>
            <div class="ab-preview-row"><span>Propriétaire</span> <span id="pv-owner">—</span></div>
            <div class="ab-preview-row"><span>Téléphone</span>    <span id="pv-owner-phone">—</span></div>
            <div class="ab-preview-row"><span>Plan</span>         <span id="pv-plan">—</span></div>
            <div class="ab-preview-row"><span>Prix</span>         <span id="pv-price">—</span></div>
            <div class="ab-preview-row"><span>Expire</span>       <span id="pv-expire">—</span></div>
          </div>
          <div class="ab-side-list" style="margin-top:18px">
            <div class="ab-info-box"><strong>Identifiant propriétaire</strong><p>Vous choisissez le login du propriétaire manuellement.</p></div>
            <div class="ab-info-box"><strong>PIN temporaire</strong><p>Un PIN de 6 chiffres sera généré et affiché une seule fois après la création.</p></div>
            <div class="ab-info-box"><strong>Séparation des données</strong><p>Ce business ne verra jamais les données des autres business.</p></div>
          </div>
        </div>
      </aside>

    </div>
  </form>
</main>
</div>
</div>
<script src="add_business.js"></script>
<script>
const _ab_sidebar   = document.getElementById('sa-sidebar');
const _ab_overlay   = document.getElementById('sa-overlay');
const _ab_hamburger = document.getElementById('sa-hamburger');
const _ab_close     = document.getElementById('sa-sidebar-close');
function _ab_open()  { _ab_sidebar.classList.add('open');    _ab_overlay.classList.add('active'); }
function _ab_shut()  { _ab_sidebar.classList.remove('open'); _ab_overlay.classList.remove('active'); }
if (_ab_hamburger) _ab_hamburger.addEventListener('click', _ab_open);
if (_ab_close)     _ab_close.addEventListener('click', _ab_shut);
if (_ab_overlay)   _ab_overlay.addEventListener('click', _ab_shut);
</script>
</body>
</html>