<?php
ob_start();
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cleanPhone(string $p): string { return preg_replace('/\s+/', '', trim($p)); }

function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function checkedFeature(array $old, string $k): string {
    return in_array($k, $old['features'] ?? [], true) ? 'checked' : '';
}

$planPrices = ['Basic' => 2000, 'Standard' => 5000, 'Premium' => 10000];

$errors = [];
$old = [
    'business_name'  => '',
    'business_type'  => '',
    'city'           => '',
    'country'        => 'Cameroon',
    'currency'       => 'XAF',
    'address'        => '',
    'phone'          => '',
    'business_email' => '',
    'owner_name'     => '',
    'owner_phone'    => '',
    'owner_email'    => '',
    'owner_username' => '',
    'plan_name'      => 'Basic',
    'amount'         => '2000',
    'billing_cycle'  => 'monthly',
    'has_employees'  => 0,
    'start_date'     => date('Y-m-d'),
    'end_date'       => date('Y-m-d', strtotime('+30 days')),
    'status'         => 'active',
    'features'       => ['inventory_management', 'reports', 'low_stock_alerts'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['business_name']  = trim($_POST['business_name']  ?? '');
    $old['business_type']  = trim($_POST['business_type']  ?? '');
    $old['city']           = trim($_POST['city']           ?? '');
    $old['country']        = trim($_POST['country']        ?? 'Cameroon');
    $old['currency']       = trim($_POST['currency']       ?? 'XAF');
    $old['address']        = trim($_POST['address']        ?? '');
    $old['phone']          = cleanPhone($_POST['phone']    ?? '');
    $old['business_email'] = trim($_POST['business_email'] ?? '');
    $old['owner_name']     = trim($_POST['owner_name']     ?? '');
    $old['owner_phone']    = cleanPhone($_POST['owner_phone'] ?? '');
    $old['owner_email']    = trim($_POST['owner_email']    ?? '');
    $old['owner_username'] = trim($_POST['owner_username'] ?? '');
    $old['plan_name']      = trim($_POST['plan_name']      ?? 'Basic');
    $old['billing_cycle']  = trim($_POST['billing_cycle']  ?? 'monthly');
    $old['has_employees']  = (int)($_POST['has_employees'] ?? 0);
    $old['start_date']     = trim($_POST['start_date']     ?? '');
    $old['end_date']       = trim($_POST['end_date']       ?? '');
    $old['status']         = trim($_POST['status']         ?? 'active');
    $old['features']       = $_POST['features']            ?? [];

    /* Auto-set amount from plan */
    $old['amount'] = (string)($planPrices[$old['plan_name']] ?? 2000);

    /* Validations */
    if (!$old['business_name'])  $errors[] = 'Le nom du business est obligatoire.';
    if (!$old['business_type'])  $errors[] = 'Le type de business est obligatoire.';
    if (!$old['city'])           $errors[] = 'La ville est obligatoire.';
    if (!$old['country'])        $errors[] = 'Le pays est obligatoire.';
    if (!$old['phone'])          $errors[] = 'Le téléphone du business est obligatoire.';
    if (!$old['owner_name'])     $errors[] = 'Le nom du propriétaire est obligatoire.';
    if (!$old['owner_username']) $errors[] = "L'identifiant propriétaire est obligatoire.";
    if ($old['owner_username'] && !preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $old['owner_username']))
        $errors[] = "L'identifiant doit contenir seulement lettres, chiffres, point, tiret ou underscore.";
    if (!$old['owner_phone'])    $errors[] = 'Le téléphone du propriétaire est obligatoire.';
    if (!$old['start_date'])     $errors[] = 'La date de début est obligatoire.';
    if (!$old['end_date'])       $errors[] = "La date d'expiration est obligatoire.";
    if ($old['start_date'] && $old['end_date'] && $old['end_date'] < $old['start_date'])
        $errors[] = "La date d'expiration doit être après la date de début.";
    if ($old['business_email'] && !filter_var($old['business_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = "L'email du business est invalide.";
    if ($old['owner_email'] && !filter_var($old['owner_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = "L'email du propriétaire est invalide.";

    if (empty($errors)) {
        $checkLogin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login_id = ?");
        $checkLogin->execute([$old['owner_username']]);
        if ((int)$checkLogin->fetchColumn() > 0)
            $errors[] = "Cet identifiant propriétaire existe déjà. Choisissez un autre.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $subStatus  = in_array($old['status'], ['trial','active','expired','suspended'], true) ? $old['status'] : 'active';
            $bizEmail   = $old['business_email'] ?: null;
            $ownerEmail = $old['owner_email']    ?: null;

            $pdo->prepare("INSERT INTO businesses 
                (business_name, business_type, address, phone, email, city, country, subscription_status, subscription_expires_at)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $old['business_name'], $old['business_type'], $old['address'],
                    $old['phone'], $bizEmail, $old['city'], $old['country'],
                    $subStatus, $old['end_date'].' 23:59:59'
                ]);
            $businessId = (int)$pdo->lastInsertId();

            $ownerLogin = $old['owner_username'];
            $tempPin    = (string)random_int(100000, 999999);
            $passHash   = password_hash($tempPin, PASSWORD_BCRYPT);

            $pdo->prepare("INSERT INTO users 
                (business_id, full_name, login_id, email, phone, password_hash, role, status, temporary_pin_plain, pin_must_change)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, 1)")
                ->execute([
                    $businessId, $old['owner_name'], $ownerLogin,
                    $ownerEmail, $old['owner_phone'], $passHash,
                    ROLE_BUSINESS_OWNER, $tempPin
                ]);

            if (tableExists($pdo, 'subscriptions')) {
                $sps = in_array($old['status'], ['trial','active','expired','cancelled'], true) ? $old['status'] : 'active';
                $pdo->prepare("INSERT INTO subscriptions (business_id, plan_name, amount, start_date, end_date, status, renewed_by)
                    VALUES (?,?,?,?,?,?,?)")
                    ->execute([
                        $businessId, $old['plan_name'], (float)$old['amount'],
                        $old['start_date'], $old['end_date'], $sps, $user['user_id']
                    ]);
            }

            if (tableExists($pdo, 'business_features')) {
                $f = array_flip($old['features']);
                $empMgmt = isset($f['employee_management']) || isset($f['employee_attendance']) ? 1 : 0;
                $pdo->prepare("INSERT INTO business_features 
                    (business_id, inventory_management, employee_management, employee_attendance,
                     sales_tracking, reports, low_stock_alerts, mobile_employee_access)
                    VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([
                        $businessId,
                        isset($f['inventory_management'])   ? 1 : 0,
                        $empMgmt,
                        isset($f['employee_attendance'])    ? 1 : 0,
                        isset($f['sales_tracking'])         ? 1 : 0,
                        isset($f['reports'])                ? 1 : 0,
                        isset($f['low_stock_alerts'])       ? 1 : 0,
                        isset($f['mobile_employee_access']) ? 1 : 0,
                    ]);
            }

            if (tableExists($pdo, 'activity_logs')) {
                $pdo->prepare("INSERT INTO activity_logs (user_id, business_id, action, description, icon, ip_address)
                    VALUES (?,?,'business_created',?,'building',?)")
                    ->execute([
                        $user['user_id'], $businessId,
                        'Nouveau business créé : '.$old['business_name'],
                        $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
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

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Admin') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Ajouter un business — Tally</title>
<link rel="stylesheet" href="../SuperAdmin/super_admin.css"/>
<link rel="stylesheet" href="add_business.css"/>
<style>
.plan-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
.plan-card-sel { border:2px solid #E2E8F0; border-radius:12px; padding:16px; cursor:pointer; transition:.15s; }
.plan-card-sel:hover { border-color:#1A9E7A; }
.plan-card-sel.selected { border-color:#1A9E7A; background:#F0FDF9; }
.plan-card-sel h4 { font-size:15px; font-weight:800; margin-bottom:4px; }
.plan-card-sel .price { font-size:20px; font-weight:900; color:#0B1F3A; }
.plan-card-sel .price span { font-size:12px; font-weight:500; color:#94A3B8; }
.plan-card-sel small { font-size:12px; color:#64748B; display:block; margin-top:4px; }
.plan-basic .plan-card-sel h4    { color:#16A34A; }
.plan-standard .plan-card-sel h4 { color:#2563EB; }
.plan-premium .plan-card-sel h4  { color:#D4A017; }
</style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="sa-layout">

<?php $url = APP_URL; include __DIR__ . '/../SuperAdmin/_sidebar.php'; ?>

<div class="sa-main">
<header class="sa-topbar">
  <button class="sa-hamburger" id="sa-hamburger">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div style="font-size:16px;font-weight:700;color:#0B1F3A;flex:1">Ajouter un nouveau business</div>
  <div class="sa-topbar-right">
    <div class="sa-profile-av"><?= e($initials) ?></div>
  </div>
</header>

<main class="sa-content">
  <div class="ab-page-head">
    <div>
      <h1 class="ab-page-title">Créer un business client</h1>
      <p class="ab-page-sub">Ajoutez un espace privé pour un client. Le PIN temporaire sera généré automatiquement.</p>
    </div>
    <div class="ab-actions">
      <a class="ab-btn ab-btn-outline" href="<?= APP_URL ?>/SuperAdmin/super_admin.php">← Retour dashboard</a>
    </div>
  </div>

  <?php if ($errors): ?>
  <div class="ab-alert error" style="margin-bottom:20px">
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form id="add-business-form" method="POST" action="add_business.php" novalidate>
    <div class="ab-grid">

      <div class="ab-card">
        <div class="ab-card-header">
          <div>
            <div class="ab-card-title">Informations du business</div>
            <div class="ab-card-sub">Tous les champs marqués * sont obligatoires.</div>
          </div>
        </div>
        <div class="ab-card-body">

          <!-- SECTION: Business -->
          <section class="ab-section">
            <div class="ab-section-title"><span class="icon-biz">⌂</span> Business</div>
            <div class="ab-form-grid">

              <div class="ab-field">
                <label class="ab-label">Nom du business <b class="ab-required">*</b></label>
                <input class="ab-input" id="business_name" name="business_name"
                       value="<?= e($old['business_name']) ?>"
                       placeholder="Ex: Restaurant Simba" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Type de business <b class="ab-required">*</b></label>
                <select class="ab-select" name="business_type" required>
                  <option value="">Choisir...</option>
                  <?php foreach (['Restaurant','Snack Bar','Boutique','Salon','Pharmacie','Quincaillerie','Supermarché','Autre'] as $t): ?>
                  <option value="<?= e($t) ?>" <?= $old['business_type']===$t?'selected':'' ?>><?= e($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ab-field">
                <label class="ab-label">Pays <b class="ab-required">*</b></label>
                <select class="ab-select" name="country" required>
                  <?php foreach (['Cameroon'=>'Cameroun','Côte d\'Ivoire'=>'Côte d\'Ivoire','Senegal'=>'Sénégal','Nigeria'=>'Nigeria','Ghana'=>'Ghana','Kenya'=>'Kenya','Autre'=>'Autre'] as $val=>$label): ?>
                  <option value="<?= e($val) ?>" <?= $old['country']===$val?'selected':'' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ab-field">
                <label class="ab-label">Devise <b class="ab-required">*</b></label>
                <select class="ab-select" name="currency" required>
                  <?php foreach (['XAF'=>'XAF — Franc CFA','XOF'=>'XOF — Franc CFA Ouest','NGN'=>'NGN — Naira','GHS'=>'GHS — Cedi','KES'=>'KES — Shilling','USD'=>'USD','EUR'=>'EUR'] as $val=>$label): ?>
                  <option value="<?= e($val) ?>" <?= $old['currency']===$val?'selected':'' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="ab-field">
                <label class="ab-label">Ville <b class="ab-required">*</b></label>
                <input class="ab-input" name="city" value="<?= e($old['city']) ?>" placeholder="Ex: Douala" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Téléphone business <b class="ab-required">*</b></label>
                <input class="ab-input" name="phone" value="<?= e($old['phone']) ?>" placeholder="+237 6XX XXX XXX" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Email business</label>
                <input class="ab-input" type="email" name="business_email"
                       value="<?= e($old['business_email']) ?>" placeholder="Optionnel">
              </div>

              <div class="ab-field full">
                <label class="ab-label">Adresse</label>
                <textarea class="ab-textarea" name="address" placeholder="Quartier, rue ou indication..."><?= e($old['address']) ?></textarea>
              </div>

            </div>
          </section>

          <!-- SECTION: Owner -->
          <section class="ab-section">
            <div class="ab-section-title"><span class="icon-user">◉</span> Propriétaire</div>
            <div class="ab-form-grid">

              <div class="ab-field">
                <label class="ab-label">Nom complet <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_name" name="owner_name"
                       value="<?= e($old['owner_name']) ?>" placeholder="Ex: Mr Simba" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Identifiant de connexion <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_username" name="owner_username"
                       value="<?= e($old['owner_username']) ?>"
                       placeholder="Ex: simba.proprietaire" required>
                <div class="ab-help">Identifiant unique utilisé pour la connexion.</div>
              </div>

              <div class="ab-field">
                <label class="ab-label">Téléphone propriétaire <b class="ab-required">*</b></label>
                <input class="ab-input" id="owner_phone" name="owner_phone"
                       value="<?= e($old['owner_phone']) ?>" placeholder="+237 6XX XXX XXX" required>
              </div>

              <div class="ab-field">
                <label class="ab-label">Email propriétaire</label>
                <input class="ab-input" type="email" name="owner_email"
                       value="<?= e($old['owner_email']) ?>" placeholder="Optionnel">
              </div>

              <div class="ab-field">
                <label class="ab-label">Employés ?</label>
                <select class="ab-select" name="has_employees">
                  <option value="0" <?= $old['has_employees']==0?'selected':'' ?>>Non</option>
                  <option value="1" <?= $old['has_employees']==1?'selected':'' ?>>Oui</option>
                </select>
              </div>

            </div>
          </section>

          <!-- SECTION: Plan -->
          <section class="ab-section">
            <div class="ab-section-title"><span class="icon-card">▬</span> Plan d'abonnement</div>

            <div class="plan-cards">
              <?php
              $plans = [
                'Basic'    => ['price'=>2000,  'color'=>'#16A34A','desc'=>'Inventaire, alertes, rapports simples'],
                'Standard' => ['price'=>5000,  'color'=>'#2563EB','desc'=>'+ Gestion employés (sans accès mobile)'],
                'Premium'  => ['price'=>10000, 'color'=>'#D4A017','desc'=>'+ Accès mobile employés, clock in/out'],
              ];
              foreach ($plans as $pName => $pInfo):
                $selected = $old['plan_name'] === $pName;
              ?>
              <div class="plan-<?= strtolower($pName) ?>">
                <div class="plan-card-sel <?= $selected?'selected':'' ?>"
                     onclick="selectPlan('<?= $pName ?>',<?= $pInfo['price'] ?>)">
                  <h4><?= $pName ?></h4>
                  <div class="price"><?= number_format($pInfo['price']) ?> XAF <span>/ mois</span></div>
                  <small><?= $pInfo['desc'] ?></small>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <input type="hidden" name="plan_name" id="plan_name" value="<?= e($old['plan_name']) ?>">
            <input type="hidden" name="amount"    id="amount"    value="<?= e($old['amount']) ?>">

            <div class="ab-form-grid">
              <div class="ab-field">
                <label class="ab-label">Fréquence de paiement</label>
                <select class="ab-select" name="billing_cycle">
                  <?php foreach (['monthly'=>'Mensuel','3_months'=>'3 mois','6_months'=>'6 mois','yearly'=>'Annuel'] as $val=>$label): ?>
                  <option value="<?= $val ?>" <?= $old['billing_cycle']===$val?'selected':'' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ab-field">
                <label class="ab-label">Statut</label>
                <select class="ab-select" name="status">
                  <option value="active"  <?= $old['status']==='active' ?'selected':'' ?>>Actif</option>
                  <option value="trial"   <?= $old['status']==='trial'  ?'selected':'' ?>>Essai</option>
                  <option value="expired" <?= $old['status']==='expired'?'selected':'' ?>>Expiré</option>
                </select>
              </div>
              <div class="ab-field">
                <label class="ab-label">Date début <b class="ab-required">*</b></label>
                <input class="ab-input" type="date" name="start_date" value="<?= e($old['start_date']) ?>" required>
              </div>
              <div class="ab-field">
                <label class="ab-label">Date expiration <b class="ab-required">*</b></label>
                <input class="ab-input" type="date" name="end_date" value="<?= e($old['end_date']) ?>" required>
              </div>
            </div>
          </section>

          <!-- SECTION: Modules -->
          <section class="ab-section">
            <div class="ab-section-title"><span class="icon-gear">⚙</span> Modules activés</div>
            <div class="ab-checks">
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="inventory_management" <?= checkedFeature($old,'inventory_management') ?>>
                <div><strong>Inventaire</strong><small>Produits, stock entrant/sortant</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="employee_management" <?= checkedFeature($old,'employee_management') ?>>
                <div><strong>Gestion employés</strong><small>Créer et gérer les employés</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="employee_attendance" <?= checkedFeature($old,'employee_attendance') ?>>
                <div><strong>Présence employés</strong><small>Clock in / clock out</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="sales_tracking" <?= checkedFeature($old,'sales_tracking') ?>>
                <div><strong>Ventes</strong><small>Suivi des ventes et sorties</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="reports" <?= checkedFeature($old,'reports') ?>>
                <div><strong>Rapports</strong><small>Résumé journalier/mensuel</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="low_stock_alerts" <?= checkedFeature($old,'low_stock_alerts') ?>>
                <div><strong>Alertes stock faible</strong><small>Notifications de réapprovisionnement</small></div>
              </label>
              <label class="ab-check">
                <input type="checkbox" name="features[]" value="mobile_employee_access" <?= checkedFeature($old,'mobile_employee_access') ?>>
                <div><strong>Accès mobile employés</strong><small>Utilisation sur téléphone</small></div>
              </label>
            </div>
          </section>

        </div>

        <div class="ab-footer-actions">
          <a class="ab-btn ab-btn-outline" href="<?= APP_URL ?>/SuperAdmin/super_admin.php">Annuler</a>
          <button class="ab-btn ab-btn-primary" id="btn-create-business" type="submit">
            <span class="icon-ok">✓</span> Créer le business
          </button>
        </div>
      </div>

      <!-- Sidebar preview -->
      <aside class="ab-card">
        <div class="ab-card-header">
          <div><div class="ab-card-title">Aperçu</div><div class="ab-card-sub">Résumé avant création</div></div>
        </div>
        <div class="ab-card-body">
          <div class="ab-preview">
            <div class="ab-preview-row"><span>Business</span>     <span id="pv-business">—</span></div>
            <div class="ab-preview-row"><span>Type</span>         <span id="pv-type">—</span></div>
            <div class="ab-preview-row"><span>Pays</span>         <span id="pv-country">—</span></div>
            <div class="ab-preview-row"><span>Ville</span>        <span id="pv-city">—</span></div>
            <div class="ab-preview-row"><span>Propriétaire</span> <span id="pv-owner">—</span></div>
            <div class="ab-preview-row"><span>Téléphone</span>    <span id="pv-owner-phone">—</span></div>
            <div class="ab-preview-row"><span>Plan</span>         <span id="pv-plan">—</span></div>
            <div class="ab-preview-row"><span>Prix/mois</span>    <span id="pv-price">—</span></div>
            <div class="ab-preview-row"><span>Expire</span>       <span id="pv-expire">—</span></div>
          </div>

          <div class="ab-side-list" style="margin-top:18px">
            <div class="ab-info-box" style="background:#FFF7ED;border-color:#FED7AA">
              <strong><span class="icon-card">▬</span> Plans & Prix</strong>
              <p>Basic: 2,000 XAF/mois</p>
              <p>Standard: 5,000 XAF/mois</p>
              <p>Premium: 10,000 XAF/mois</p>
            </div>
            <div class="ab-info-box">
              <strong><span class="icon-key">⚿</span> PIN temporaire</strong>
              <p>Un PIN de 6 chiffres sera généré automatiquement et affiché une seule fois.</p>
            </div>
            <div class="ab-info-box">
              <strong><span class="icon-lock">🔒</span> Séparation des données</strong>
              <p>Ce business ne verra jamais les données des autres business.</p>
            </div>
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
/* Sidebar toggle */
const _ab_sidebar   = document.getElementById('sa-sidebar');
const _ab_overlay   = document.getElementById('sa-overlay');
const _ab_hamburger = document.getElementById('sa-hamburger');
const _ab_close     = document.getElementById('sa-sidebar-close');
function _ab_open() { _ab_sidebar.classList.add('open');    _ab_overlay.classList.add('active'); }
function _ab_shut() { _ab_sidebar.classList.remove('open'); _ab_overlay.classList.remove('active'); }
if (_ab_hamburger) _ab_hamburger.addEventListener('click', _ab_open);
if (_ab_close)     _ab_close.addEventListener('click', _ab_shut);
if (_ab_overlay)   _ab_overlay.addEventListener('click', _ab_shut);

/* Plan selection */
const planPrices = { Basic: 2000, Standard: 5000, Premium: 10000 };

function selectPlan(name, price) {
    document.getElementById('plan_name').value = name;
    document.getElementById('amount').value    = price;
    document.querySelectorAll('.plan-card-sel').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('pv-plan').textContent  = name;
    document.getElementById('pv-price').textContent = price.toLocaleString() + ' XAF';
}

/* Live preview */
function updatePreview() {
    const get = id => document.getElementById(id)?.value || '';
    const getEl = id => document.getElementById(id);

    const biz     = get('business_name');
    const type    = document.querySelector('[name="business_type"]')?.value || '';
    const country = document.querySelector('[name="country"]')?.value || '';
    const city    = get('city');
    const owner   = get('owner_name');
    const phone   = get('owner_phone');
    const plan    = get('plan_name');
    const amount  = get('amount');
    const expire  = document.querySelector('[name="end_date"]')?.value || '';

    if (getEl('pv-business'))   getEl('pv-business').textContent  = biz     || '—';
    if (getEl('pv-type'))       getEl('pv-type').textContent      = type    || '—';
    if (getEl('pv-country'))    getEl('pv-country').textContent   = country || '—';
    if (getEl('pv-city'))       getEl('pv-city').textContent      = city    || '—';
    if (getEl('pv-owner'))      getEl('pv-owner').textContent     = owner   || '—';
    if (getEl('pv-owner-phone'))getEl('pv-owner-phone').textContent = phone || '—';
    if (getEl('pv-plan'))       getEl('pv-plan').textContent      = plan    || '—';
    if (getEl('pv-price'))      getEl('pv-price').textContent     = amount  ? parseInt(amount).toLocaleString()+' XAF' : '—';
    if (getEl('pv-expire'))     getEl('pv-expire').textContent    = expire  || '—';
}

document.querySelectorAll('input,select,textarea').forEach(el => {
    el.addEventListener('input',  updatePreview);
    el.addEventListener('change', updatePreview);
});

/* Init preview */
updatePreview();
</script>
</body>
</html>