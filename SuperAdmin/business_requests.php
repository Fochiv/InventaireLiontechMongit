<?php
/* ============================================================
   business_requests.php — Super Admin
   Review and approve business requests submitted by users
   ============================================================ */
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function gen_pin(): string { return (string)random_int(100000, 999999); }

$message     = '';
$messageType = '';
$selectedRequest = null;

/* ── Handle APPROVE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_request') {
    $requestId  = (int)($_POST['request_id'] ?? 0);
    $loginId    = trim($_POST['login_id'] ?? '');
    $ownerName  = trim($_POST['owner_name'] ?? '');
    $ownerPhone = trim($_POST['owner_phone'] ?? '');
    $ownerEmail = trim($_POST['owner_email'] ?? '') ?: null;
    $bizName    = trim($_POST['business_name'] ?? '');
    $bizType    = trim($_POST['business_type'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $bizEmail   = trim($_POST['business_email'] ?? '') ?: null;
    $address    = trim($_POST['address'] ?? '') ?: null;
    $country    = trim($_POST['country'] ?? 'Cameroun');
    $planName   = trim($_POST['plan_name'] ?? 'Basic');
    $amount     = (float)($_POST['amount'] ?? 0);
    $endDate    = trim($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
    $features   = $_POST['features'] ?? [];

    if (!$loginId) {
        $message = "L'identifiant de connexion est obligatoire.";
        $messageType = 'error';
    } else {
        /* Check login_id unique */
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login_id = ?");
        $check->execute([$loginId]);
        if ((int)$check->fetchColumn() > 0) {
            $message = "Cet identifiant existe déjà. Choisissez-en un autre.";
            $messageType = 'error';
        }
    }

    if (!$message) {
        try {
            $pdo->beginTransaction();

            /* Create business */
            $pdo->prepare("INSERT INTO businesses 
                (business_name, business_type, address, phone, email, city, country, subscription_status, subscription_expires_at) 
                VALUES (?,?,?,?,?,?,?,'active',?)")
                ->execute([$bizName, $bizType, $address, $phone, $bizEmail, $city, $country, $endDate.' 23:59:59']);
            $businessId = (int)$pdo->lastInsertId();

            /* Create owner */
            $tempPin  = gen_pin();
            $passHash = password_hash($tempPin, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users 
                (business_id, full_name, login_id, email, phone, password_hash, role, status, temporary_pin_plain, pin_must_change) 
                VALUES (?,?,?,?,?,?,?,?,?,1)")
                ->execute([$businessId, $ownerName, $loginId, $ownerEmail, $ownerPhone, $passHash, ROLE_BUSINESS_OWNER, 'active', $tempPin]);

            /* Create subscription */
            $pdo->prepare("INSERT INTO subscriptions 
                (business_id, plan_name, amount, start_date, end_date, status, renewed_by) 
                VALUES (?,?,?,?,?,'active',?)")
                ->execute([$businessId, $planName, $amount, date('Y-m-d'), $endDate, $user['user_id']]);

            /* Create features */
            $f = array_flip($features);
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

            /* Mark request approved */
            $pdo->prepare("UPDATE business_requests 
                SET status='approved', reviewed_by=?, reviewed_at=NOW(),
                    created_business_id=?, created_owner_user_id=LAST_INSERT_ID()
                WHERE request_id=?")
                ->execute([$user['user_id'], $businessId, $requestId]);

            /* Activity log */
            $pdo->prepare("INSERT INTO activity_logs 
                (user_id, business_id, action, description, icon, ip_address) 
                VALUES (?,?,'business_created',?,'building',?)")
                ->execute([$user['user_id'], $businessId, 'Business approuvé : '.$bizName, $_SERVER['REMOTE_ADDR']??null]);

            $pdo->commit();

            /* Build WhatsApp message */
            $waPhone = preg_replace('/[^0-9]/', '', $ownerPhone);
            /* Add Cameroon country code if needed */
            if (strlen($waPhone) === 9) $waPhone = '237' . $waPhone;
            $waText = urlencode(
                "Bonjour {$ownerName} 👋\n\n" .
                "Votre compte business *{$bizName}* sur *LionTech Business Manager* a été activé ! ✅\n\n" .
                "🔐 *Identifiant de connexion :* {$loginId}\n" .
                "🔑 *PIN temporaire :* {$tempPin}\n\n" .
                "👉 Connectez-vous ici :\n" . APP_URL . "/Logininventory/login.php\n\n" .
                "⚠️ Changez votre PIN dès la première connexion.\n\n" .
                "Bienvenue sur LionTech ! 🦁\n" .
                "— LionTech Business Manager"
            );
            $waLink = "https://wa.me/{$waPhone}?text={$waText}";

            /* Store in session then redirect */
            $_SESSION['approved_credentials'] = [
                'biz_name'    => $bizName,
                'owner_name'  => $ownerName,
                'owner_phone' => $ownerPhone,
                'owner_email' => $ownerEmail,
                'login_id'    => $loginId,
                'temp_pin'    => $tempPin,
                'wa_link'     => $waLink,
                'wa_phone'    => $waPhone,
            ];

            header('Location: ' . APP_URL . '/SuperAdmin/business_requests.php?approved=1');
            exit;

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Erreur base de données : ' . $ex->getMessage();
            $messageType = 'error';
        }
    }

    /* If error, reload the selected request */
    if ($message && $requestId) {
        $stmt = $pdo->prepare("SELECT * FROM business_requests WHERE request_id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $selectedRequest = $stmt->fetch();
    }
}

/* ── Handle REJECT ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $pdo->prepare("UPDATE business_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE request_id=?")
        ->execute([$user['user_id'], $requestId]);
    header('Location: ' . APP_URL . '/SuperAdmin/business_requests.php?rejected=1');
    exit;
}

/* ── Load selected request ── */
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId > 0 && !$selectedRequest) {
    $stmt = $pdo->prepare("SELECT * FROM business_requests WHERE request_id = ? LIMIT 1");
    $stmt->execute([$viewId]);
    $selectedRequest = $stmt->fetch();
}

/* ── Pre-generate suggested login ── */
$suggestedLogin = '';
if ($selectedRequest) {
    $fn = strtolower(preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $selectedRequest['owner_first_name']) ?: ''));
    $ln = strtolower(preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $selectedRequest['owner_last_name']) ?: ''));
    $suggestedLogin = ($fn ?: 'user') . ($ln ? '.' . $ln : '') . random_int(10, 99);
}

/* ── Load requests ── */
$requests = $pdo->query("SELECT * FROM business_requests WHERE status='pending' ORDER BY created_at ASC")->fetchAll();
$recentApproved = $pdo->query("SELECT * FROM business_requests WHERE status='approved' ORDER BY reviewed_at DESC LIMIT 10")->fetchAll();

/* ── Show credentials after approval ── */
$approvedCreds = null;
if (isset($_GET['approved']) && !empty($_SESSION['approved_credentials'])) {
    $approvedCreds = $_SESSION['approved_credentials'];
    unset($_SESSION['approved_credentials']);
}

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Admin') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);
$url = APP_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Business Requests — LionTech Super Admin</title>
<link rel="stylesheet" href="super_admin.css"/>
<style>
.br-layout { display:grid; grid-template-columns:320px 1fr; gap:24px; }
.br-list-item { padding:14px 16px; border:1px solid #E2E8F0; border-radius:12px; margin-bottom:10px; cursor:pointer; background:#fff; transition:.15s; }
.br-list-item:hover { border-color:#1A9E7A; box-shadow:0 2px 10px rgba(26,158,122,.1); }
.br-list-item.selected { border-color:#1A9E7A; background:#F0FDF9; }
.br-list-item h4 { font-size:14px; font-weight:700; color:#0B1F3A; margin-bottom:3px; }
.br-list-item p { font-size:12px; color:#64748B; margin-bottom:3px; }
.br-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; }
.br-badge.pending  { background:#FEF3C7; color:#92400E; }
.br-badge.approved { background:#DCFCE7; color:#166534; }
.br-badge.rejected { background:#FEE2E2; color:#991B1B; }
.br-review { background:#fff; border-radius:16px; padding:24px; border:1px solid #E2E8F0; }
.br-sec { margin-bottom:22px; }
.br-sec-title { font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#94A3B8; font-weight:700; margin-bottom:12px; border-bottom:1px solid #F1F5F9; padding-bottom:8px; }
.br-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.br-field label { display:block; font-size:11px; color:#94A3B8; margin-bottom:4px; font-weight:600; }
.br-field input, .br-field select { width:100%; padding:9px 12px; border:1px solid #E2E8F0; border-radius:8px; font-size:13.5px; outline:none; }
.br-field input:focus, .br-field select:focus { border-color:#1A9E7A; }
.br-checks { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.br-check { display:flex; align-items:center; gap:10px; padding:10px 12px; background:#F8FAFC; border-radius:8px; font-size:13px; cursor:pointer; }
.br-check input { width:16px; height:16px; cursor:pointer; }
.creds-box { background:#D1FAE5; border:2px solid #10B981; border-radius:14px; padding:22px; margin-bottom:24px; }
.creds-box h3 { color:#065F46; margin-bottom:14px; font-size:16px; }
.creds-pin { font-size:28px; font-weight:900; letter-spacing:6px; background:#fff; padding:8px 18px; border-radius:8px; border:2px solid #10B981; display:inline-block; color:#065F46; }
.creds-login { font-size:16px; font-weight:700; background:#fff; padding:6px 14px; border-radius:8px; border:1px solid #10B981; display:inline-block; }
.wa-btn { display:inline-flex; align-items:center; gap:10px; background:#25D366; color:#fff; padding:13px 22px; border-radius:12px; text-decoration:none; font-weight:800; font-size:15px; margin-top:14px; }
.wa-btn:hover { background:#1ebe5a; }
.empty-state { text-align:center; padding:50px 20px; color:#94A3B8; }
.btn-approve { width:100%; padding:14px; background:#1A9E7A; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-bottom:10px; }
.btn-approve:hover { background:#178a6a; }
.btn-reject { width:100%; padding:12px; background:#fff; color:#DC2626; border:2px solid #DC2626; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-reject:hover { background:#FEE2E2; }
.info-row { display:flex; gap:8px; align-items:flex-start; padding:8px 0; border-bottom:1px solid #F1F5F9; font-size:13.5px; }
.info-row:last-child { border-bottom:none; }
.info-row .lbl { color:#94A3B8; font-weight:600; min-width:140px; font-size:12px; }
.info-row .val { color:#0B1F3A; font-weight:500; }
.alert-box { padding:14px 18px; border-radius:10px; margin-bottom:20px; font-size:14px; }
.alert-box.error { background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5; }
.alert-box.success { background:#DCFCE7; color:#166534; border:1px solid #86EFAC; }
@media(max-width:900px){ .br-layout{ grid-template-columns:1fr; } .br-row{ grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="sa-layout">

<?php include __DIR__ . '/_sidebar.php'; ?>

<div class="sa-main">
  <header class="sa-topbar">
    <button class="sa-hamburger" id="sa-hamburger">☰</button>
    <div style="font-size:16px;font-weight:700;color:#0B1F3A;flex:1">
      Demandes de création de business
      <?php if (count($requests) > 0): ?>
      <span style="background:#F97316;color:#fff;font-size:12px;padding:2px 10px;border-radius:999px;margin-left:8px"><?= count($requests) ?> en attente</span>
      <?php endif; ?>
    </div>
    <div class="sa-topbar-right">
      <div class="sa-profile-av"><?= e($initials) ?></div>
    </div>
  </header>

  <main class="sa-content">

    <?php if ($approvedCreds): ?>
    <!-- ✅ Credentials box shown after approval -->
    <div class="creds-box">
      <h3>✅ Business activé avec succès — <?= e($approvedCreds['biz_name']) ?></h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <p style="font-size:12px;color:#065F46;font-weight:700;margin-bottom:6px">PROPRIÉTAIRE</p>
          <p style="font-size:14px;color:#0B1F3A"><strong><?= e($approvedCreds['owner_name']) ?></strong></p>
          <p style="font-size:13px;color:#374151"><?= e($approvedCreds['owner_phone']) ?></p>
          <?php if ($approvedCreds['owner_email']): ?>
          <p style="font-size:13px;color:#374151"><?= e($approvedCreds['owner_email']) ?></p>
          <?php endif; ?>
        </div>
        <div>
          <p style="font-size:12px;color:#065F46;font-weight:700;margin-bottom:6px">IDENTIFIANT DE CONNEXION</p>
          <span class="creds-login"><?= e($approvedCreds['login_id']) ?></span>
          <p style="font-size:12px;color:#065F46;font-weight:700;margin:14px 0 6px">PIN TEMPORAIRE</p>
          <span class="creds-pin"><?= e($approvedCreds['temp_pin']) ?></span>
        </div>
      </div>
      <p style="font-size:13px;color:#065F46;margin-bottom:4px">⚠️ Copiez ces informations maintenant, puis envoyez-les via WhatsApp.</p>
      <a class="wa-btn" href="<?= e($approvedCreds['wa_link']) ?>" target="_blank">
        📲 Envoyer les identifiants via WhatsApp
      </a>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['rejected'])): ?>
    <div class="alert-box success">✅ Demande rejetée avec succès.</div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div class="alert-box <?= $messageType ?>">
      <?= $messageType === 'error' ? '⚠️' : '✅' ?> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="br-layout">

      <!-- LEFT: list -->
      <div>
        <p style="font-size:12px;color:#94A3B8;text-transform:uppercase;font-weight:700;margin-bottom:12px">
          En attente (<?= count($requests) ?>)
        </p>

        <?php if (empty($requests)): ?>
        <div class="empty-state">
          <div style="font-size:40px">📭</div>
          <p style="margin-top:10px">Aucune demande en attente.</p>
        </div>
        <?php else: foreach ($requests as $r): ?>
        <div class="br-list-item <?= ($viewId == $r['request_id']) ? 'selected' : '' ?>"
             onclick="window.location='?view=<?= $r['request_id'] ?>'">
          <h4><?= e($r['business_name']) ?></h4>
          <p><?= e($r['owner_full_name']) ?> · <?= e($r['city']) ?>, <?= e($r['country']) ?></p>
          <p>📞 <?= e($r['owner_phone']) ?></p>
          <p style="margin-top:6px">
            <span class="br-badge pending"><?= e($r['plan_name']) ?></span>
            <span style="font-size:11px;color:#94A3B8;margin-left:8px">
              <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
            </span>
          </p>
        </div>
        <?php endforeach; endif; ?>

        <?php if (!empty($recentApproved)): ?>
        <p style="font-size:12px;color:#94A3B8;text-transform:uppercase;font-weight:700;margin:20px 0 12px">
          Récemment approuvés
        </p>
        <?php foreach ($recentApproved as $r): ?>
        <div class="br-list-item" style="opacity:.65">
          <h4><?= e($r['business_name']) ?></h4>
          <p><?= e($r['owner_full_name']) ?></p>
          <p style="margin-top:6px"><span class="br-badge approved">Approuvé</span></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- RIGHT: review form -->
      <div>
        <?php if ($selectedRequest): ?>
        <div class="br-review">
          <h2 style="font-size:17px;color:#0B1F3A;margin-bottom:6px">
            Réviser : <?= e($selectedRequest['business_name']) ?>
          </h2>
          <p style="font-size:13px;color:#64748B;margin-bottom:20px">
            Soumis le <?= date('d/m/Y à H:i', strtotime($selectedRequest['created_at'])) ?>
          </p>

          <!-- Read-only submitted info -->
          <div class="br-sec">
            <div class="br-sec-title">📋 Informations soumises</div>
            <div class="info-row"><span class="lbl">Business</span><span class="val"><?= e($selectedRequest['business_name']) ?></span></div>
            <div class="info-row"><span class="lbl">Type</span><span class="val"><?= e($selectedRequest['business_type'] ?? '—') ?></span></div>
            <div class="info-row"><span class="lbl">Ville / Pays</span><span class="val"><?= e($selectedRequest['city']) ?>, <?= e($selectedRequest['country']) ?></span></div>
            <div class="info-row"><span class="lbl">Téléphone business</span><span class="val"><?= e($selectedRequest['phone'] ?? '—') ?></span></div>
            <div class="info-row"><span class="lbl">Propriétaire</span><span class="val"><?= e($selectedRequest['owner_full_name']) ?></span></div>
            <div class="info-row"><span class="lbl">Téléphone propriétaire</span><span class="val"><?= e($selectedRequest['owner_phone']) ?></span></div>
            <?php if ($selectedRequest['owner_email']): ?>
            <div class="info-row"><span class="lbl">Email propriétaire</span><span class="val"><?= e($selectedRequest['owner_email']) ?></span></div>
            <?php endif; ?>
            <div class="info-row"><span class="lbl">Plan demandé</span><span class="val"><strong><?= e($selectedRequest['plan_name']) ?></strong> — <?= number_format($selectedRequest['amount'],0) ?> XAF</span></div>
            <div class="info-row"><span class="lbl">Fréquence</span><span class="val"><?= e($selectedRequest['billing_cycle']) ?></span></div>
            <div class="info-row"><span class="lbl">Paiement préféré</span><span class="val"><?= e($selectedRequest['preferred_payment_method'] ?? '—') ?></span></div>
            <div class="info-row"><span class="lbl">Employés</span><span class="val"><?= $selectedRequest['has_employees'] ? 'Oui' : 'Non' ?></span></div>
            <?php
            $reqFeatures = json_decode($selectedRequest['requested_features'] ?? '[]', true) ?: [];
            if (!empty($reqFeatures)):
            ?>
            <div class="info-row">
              <span class="lbl">Modules demandés</span>
              <span class="val"><?= e(implode(', ', $reqFeatures)) ?></span>
            </div>
            <?php endif; ?>
          </div>

          <!-- APPROVE FORM -->
          <form method="POST" id="approveForm">
            <input type="hidden" name="action" value="approve_request">
            <input type="hidden" name="request_id" value="<?= (int)$selectedRequest['request_id'] ?>">

            <div class="br-sec">
              <div class="br-sec-title">🏢 Confirmer informations business</div>
              <div class="br-row">
                <div class="br-field">
                  <label>Nom du business</label>
                  <input name="business_name" value="<?= e($selectedRequest['business_name']) ?>" required>
                </div>
                <div class="br-field">
                  <label>Type</label>
                  <input name="business_type" value="<?= e($selectedRequest['business_type'] ?? '') ?>">
                </div>
                <div class="br-field">
                  <label>Ville</label>
                  <input name="city" value="<?= e($selectedRequest['city'] ?? '') ?>">
                </div>
                <div class="br-field">
                  <label>Pays</label>
                  <input name="country" value="<?= e($selectedRequest['country'] ?? 'Cameroun') ?>">
                </div>
                <div class="br-field">
                  <label>Téléphone business</label>
                  <input name="phone" value="<?= e($selectedRequest['phone'] ?? '') ?>">
                </div>
                <div class="br-field">
                  <label>Email business</label>
                  <input name="business_email" value="<?= e($selectedRequest['business_email'] ?? '') ?>">
                </div>
              </div>
            </div>

            <div class="br-sec">
              <div class="br-sec-title">👤 Créer l'identifiant propriétaire</div>
              <div class="br-row">
                <div class="br-field">
                  <label>Nom complet</label>
                  <input name="owner_name" value="<?= e($selectedRequest['owner_full_name']) ?>" required>
                </div>
                <div class="br-field">
                  <label>Identifiant de connexion <span style="color:#DC2626">*</span></label>
                  <input name="login_id" value="<?= e($suggestedLogin) ?>" required
                         placeholder="Ex: simba.njoya42">
                  <small style="color:#94A3B8;font-size:11px">Modifiable — doit être unique</small>
                </div>
                <div class="br-field">
                  <label>Téléphone propriétaire</label>
                  <input name="owner_phone" value="<?= e($selectedRequest['owner_phone']) ?>">
                </div>
                <div class="br-field">
                  <label>Email propriétaire</label>
                  <input name="owner_email" value="<?= e($selectedRequest['owner_email'] ?? '') ?>">
                </div>
              </div>
              <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:12px;font-size:13px;color:#9A3412;margin-top:8px">
                🔑 Un PIN temporaire à 6 chiffres sera généré automatiquement par le système.
              </div>
            </div>

            <div class="br-sec">
              <div class="br-sec-title">💳 Abonnement</div>
              <div class="br-row">
                <div class="br-field">
                  <label>Plan</label>
                  <select name="plan_name">
                    <?php foreach (['Basic','Standard','Premium'] as $p): ?>
                    <option value="<?= $p ?>" <?= $selectedRequest['plan_name']===$p?'selected':'' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="br-field">
                  <label>Montant (XAF)</label>
                  <input name="amount" type="number" value="<?= e($selectedRequest['amount']) ?>">
                </div>
                <div class="br-field">
                  <label>Expiration abonnement</label>
                  <input name="end_date" type="date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
              </div>
            </div>

            <div class="br-sec">
              <div class="br-sec-title">⚙️ Modules à activer</div>
              <?php
              $allFeatures = [
                'inventory_management'   => '📦 Inventaire',
                'employee_management'    => '👥 Gestion employés',
                'employee_attendance'    => '⏱️ Présence (Clock in/out)',
                'sales_tracking'         => '💰 Ventes',
                'reports'                => '📊 Rapports',
                'low_stock_alerts'       => '⚠️ Alertes stock faible',
                'mobile_employee_access' => '📱 Accès mobile employés',
              ];
              ?>
              <div class="br-checks">
                <?php foreach ($allFeatures as $key => $label): ?>
                <label class="br-check">
                  <input type="checkbox" name="features[]" value="<?= $key ?>"
                    <?= in_array($key, $reqFeatures) ? 'checked' : '' ?>>
                  <?= e($label) ?>
                </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit" class="btn-approve">
              ✅ Approuver et créer le compte
            </button>
          </form>

          <!-- REJECT FORM — separate from approve form -->
          <form method="POST" id="rejectForm"
                onsubmit="return confirm('Rejeter définitivement cette demande ?')">
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="request_id" value="<?= (int)$selectedRequest['request_id'] ?>">
            <button type="submit" class="btn-reject">✕ Rejeter la demande</button>
          </form>

        </div>

        <?php else: ?>
        <div class="br-review empty-state">
          <div style="font-size:52px">👈</div>
          <p style="margin-top:14px;font-size:15px">
            Sélectionnez une demande à gauche pour la réviser et l'approuver.
          </p>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>
</div>

<script>
const sidebar   = document.getElementById('sa-sidebar');
const overlay   = document.getElementById('sa-overlay');
const hamburger = document.getElementById('sa-hamburger');
const closeBtn  = document.getElementById('sa-sidebar-close');
if(hamburger) hamburger.addEventListener('click', ()=>{ sidebar.classList.add('open'); overlay.classList.add('active'); });
if(closeBtn)  closeBtn.addEventListener('click',  ()=>{ sidebar.classList.remove('open'); overlay.classList.remove('active'); });
if(overlay)   overlay.addEventListener('click',   ()=>{ sidebar.classList.remove('open'); overlay.classList.remove('active'); });
</script>
</body>
</html>