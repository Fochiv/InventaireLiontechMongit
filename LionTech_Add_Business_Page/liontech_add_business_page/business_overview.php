<?php
/* ============================================================
   business_overview.php — LionTech Business Manager
   Shows business details after creation
   Role: super_admin only
   ============================================================ */
require_once __DIR__ . '/../../Config.php';

requireRole([ROLE_SUPER_ADMIN]);
$pdo  = getDB();
$user = currentUser();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare('SHOW TABLES LIKE ?');
    $s->execute([$table]);
    return (bool)$s->fetchColumn();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . APP_URL . '/SuperAdmin/super_admin.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ?");
$stmt->execute([$id]);
$business = $stmt->fetch();
if (!$business) { http_response_code(404); exit('Business not found.'); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE business_id = ? AND role = ? ORDER BY user_id ASC LIMIT 1");
$stmt->execute([$id, ROLE_BUSINESS_OWNER]);
$owner = $stmt->fetch();

$subscription = null;
if (tableExists($pdo, 'subscriptions')) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE business_id = ? ORDER BY subscription_id DESC LIMIT 1");
    $stmt->execute([$id]);
    $subscription = $stmt->fetch();
}

$features = null;
if (tableExists($pdo, 'business_features')) {
    $stmt = $pdo->prepare("SELECT * FROM business_features WHERE business_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $features = $stmt->fetch();
}

$createdCreds = $_SESSION['new_business_credentials'] ?? null;
$showCreds    = $createdCreds && (int)$createdCreds['business_id'] === $id;

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Admin') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);

$status = $business['subscription_status'] ?? 'trial';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Business cree — LionTech</title>
<link rel="stylesheet" href="add_business.css"/>
</head>
<body>
<div class="ab-layout">

  <aside class="ab-sidebar" id="ab-sidebar">
    <div class="ab-sidebar-header">
      <div class="ab-logo">
        <div class="ab-logo-icon">🦁</div>
        <div><div class="ab-logo-name">LionTech</div><div class="ab-logo-tag">Business Manager</div></div>
      </div>
    </div>
    <nav class="ab-nav">
      <div class="ab-nav-section">Principal</div>
      <a class="ab-nav-item" href="<?= APP_URL ?>/SuperAdmin/super_admin.php"><span class="ab-nav-icon">📊</span><span>Dashboard</span></a>
      <a class="ab-nav-item active" href="business_overview.php?id=<?= e($id) ?>"><span class="ab-nav-icon">🏢</span><span>Apercu business</span></a>
      <a class="ab-nav-item" href="add_business.php"><span class="ab-nav-icon">➕</span><span>Ajouter business</span></a>
      <div class="ab-nav-section">Systeme</div>
      <a class="ab-nav-item" href="<?= APP_URL ?>/Logininventory/logout.php"><span class="ab-nav-icon">🚪</span><span>Deconnexion</span></a>
    </nav>
    <div class="ab-sidebar-footer">
      <div class="ab-avatar"><?= e($initials) ?></div>
      <div>
        <strong><?= e($user['full_name']) ?></strong>
        <div style="font-size:12px;color:#CBD5E1">Super Admin</div>
      </div>
    </div>
  </aside>
  <div class="ab-overlay" id="ab-overlay"></div>

  <div class="ab-main">
    <header class="ab-topbar">
      <button class="ab-hamburger" id="ab-hamburger">☰</button>
      <div class="ab-title-sm">Apercu du business</div>
      <div class="ab-user">
        <div class="ab-avatar" style="width:34px;height:34px"><?= e($initials) ?></div>
        <span><?= e($user['full_name']) ?></span>
      </div>
    </header>

    <main class="ab-content">

      <?php if (isset($_GET['created'])): ?>
      <div class="ab-alert success">
        <span>✅</span>
        <div>Business cree avec succes. Verifiez les informations ci-dessous.</div>
      </div>
      <?php endif; ?>

      <div class="ab-page-head">
        <div>
          <h1 class="ab-page-title"><?= e($business['business_name']) ?></h1>
          <p class="ab-page-sub">
            Business ID #<?= e($business['business_id']) ?> •
            <?= e($business['business_type'] ?? 'Type non defini') ?> •
            <?= e($business['city'] ?? '') ?>
          </p>
        </div>
        <div class="ab-actions">
          <a class="ab-btn ab-btn-outline" href="<?= APP_URL ?>/SuperAdmin/super_admin.php">← Dashboard</a>
          <a class="ab-btn ab-btn-primary" href="add_business.php">➕ Ajouter un autre</a>
        </div>
      </div>

      <div class="ab-grid">
        <div class="ab-card">
          <div class="ab-card-header">
            <div>
              <div class="ab-card-title">Details du business</div>
              <div class="ab-card-sub">Resume des informations enregistrees</div>
            </div>
            <span class="ab-pill <?= e($status) ?>"><?= e($status) ?></span>
          </div>
          <div class="ab-card-body">
            <div class="ab-preview">
              <div class="ab-preview-row"><span>Nom</span><span><?= e($business['business_name']) ?></span></div>
              <div class="ab-preview-row"><span>Type</span><span><?= e($business['business_type'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Ville</span><span><?= e($business['city'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Telephone</span><span><?= e($business['phone'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Email</span><span><?= e($business['email'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Adresse</span><span><?= e($business['address'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Cree le</span><span><?= e($business['created_at'] ?? '—') ?></span></div>
            </div>
          </div>
        </div>

        <aside class="ab-card">
          <div class="ab-card-header">
            <div>
              <div class="ab-card-title">Compte proprietaire</div>
              <div class="ab-card-sub">Login du client</div>
            </div>
          </div>
          <div class="ab-card-body">
            <div class="ab-preview">
              <div class="ab-preview-row"><span>Nom</span><span><?= e($owner['full_name'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Identifiant</span><span><?= e($owner['login_id'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Telephone</span><span><?= e($owner['phone'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Statut</span><span><?= e($owner['status'] ?? '—') ?></span></div>
            </div>
            <?php if ($showCreds): ?>
            <div class="ab-credential">
              <strong>A copier pour le client</strong>
              <code>Identifiant: <?= e($createdCreds['owner_username']) ?></code>
              <code>PIN temporaire: <?= e($createdCreds['temporary_pin']) ?></code>
              <small style="display:block;margin-top:10px;color:#CBD5E1">
                Important: ce PIN est affiche ici pour la premiere configuration.
                Le client devra le changer plus tard.
              </small>
            </div>
            <?php endif; ?>
          </div>
        </aside>
      </div>

      <div class="ab-grid" style="margin-top:22px">
        <div class="ab-card">
          <div class="ab-card-header">
            <div>
              <div class="ab-card-title">Abonnement</div>
              <div class="ab-card-sub">Plan et expiration</div>
            </div>
          </div>
          <div class="ab-card-body">
            <div class="ab-preview">
              <div class="ab-preview-row"><span>Plan</span><span><?= e($subscription['plan_name'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Prix</span><span><?= isset($subscription['amount']) ? number_format((float)$subscription['amount'], 0, '.', ' ') . ' XAF' : '—' ?></span></div>
              <div class="ab-preview-row"><span>Debut</span><span><?= e($subscription['start_date'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Fin</span><span><?= e($subscription['end_date'] ?? '—') ?></span></div>
              <div class="ab-preview-row"><span>Statut</span><span><?= e($subscription['status'] ?? $status) ?></span></div>
            </div>
          </div>
        </div>

        <div class="ab-card">
          <div class="ab-card-header">
            <div>
              <div class="ab-card-title">Actions rapides</div>
              <div class="ab-card-sub">Prochaines etapes</div>
            </div>
          </div>
          <div class="ab-card-body ab-side-list">
            <a class="ab-btn ab-btn-outline" href="#">👥 Ajouter employes</a>
            <a class="ab-btn ab-btn-outline" href="#">📦 Ajouter produits</a>
            <a class="ab-btn ab-btn-outline" href="#">🔄 Gerer abonnement</a>
            <a class="ab-btn ab-btn-outline" href="#">✏️ Modifier business</a>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>
<script src="add_business.js"></script>
</body>
</html>