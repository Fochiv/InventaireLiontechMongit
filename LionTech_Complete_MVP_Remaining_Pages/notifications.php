<?php
/* ============================================================
   notifications.php — Tally Business Manager
   Access: ALL roles (owner, manager, employee)
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_EMPLOYEE]);

$user       = currentUser();
$businessId = (int)($user['business_id'] ?? 0);
$role       = $_SESSION['role'] ?? '';

$isOwner          = ($role === 'business_owner');
$isManager        = ($role === 'manager');
$isEmployee       = ($role === 'employee');
$isOwnerOrManager = ($isOwner || $isManager);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w)
    $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications — LionTech</title>
  <link rel="icon" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/icons.css"/>
  <style>
    .notif-content{padding:20px 24px 40px}
    .notif-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:22px}
    .notif-grid{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start}
    @media(max-width:900px){.notif-grid{grid-template-columns:1fr}}
    @media(max-width:600px){.notif-content{padding:14px 14px 40px}.notif-header{flex-direction:column;align-items:flex-start}}
    .notif-item{display:flex;gap:14px;padding:16px 14px;border-bottom:1px solid #F1F5F9}
    .notif-item:last-child{border-bottom:none}
    .notif-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:6px}
    .notif-dot-warn{background:#F59E0B}
    .notif-dot-danger{background:#DC2626}
    .notif-dot-info{background:#2563EB}
    .notif-dot-gold{background:#D4A017}
    .notif-summary-item{display:flex;align-items:center;gap:12px;padding:12px;background:#F8FAFC;border-radius:12px}
    .notif-summary-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex-shrink:0}
    .notif-mark-btn{background:#0B1F3A;color:#fff;border:none;padding:10px 16px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px}
  </style>
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">

    <!-- Topbar -->
    <header class="od-topbar">
      <button class="od-menu-btn" id="od-menu-btn" aria-label="Menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="od-business-title">
        <h1>Notifications</h1>
        <p>Alertes et messages importants</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <div class="notif-content">

      <div class="notif-header">
        <div>
          <h2 style="font-size:20px;font-weight:700;color:#0B1F3A;margin:0">Notifications</h2>
          <p style="color:#6B7280;font-size:13px;margin:4px 0 0">Alertes importantes du business.</p>
        </div>
        <button class="notif-mark-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Tout marquer lu
        </button>
      </div>

      <div class="notif-grid">

        <!-- Alerts timeline -->
        <div class="od-card" style="padding:0;overflow:hidden">
          <div class="od-card-head" style="padding:16px 18px"><div><h2>Alertes récentes</h2></div></div>

          <!-- Low stock — all roles see this -->
          <div class="notif-item">
            <div class="notif-dot notif-dot-warn"></div>
            <div>
              <strong style="font-size:14px;color:#0B1F3A">Stock faible détecté</strong>
              <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Un ou plusieurs produits sont proches du seuil minimum. Vérifiez le stock.</p>
              <span style="background:#FEF3C7;color:#92400E;border-radius:50px;padding:3px 10px;font-size:11px;font-weight:700">Stock faible</span>
            </div>
          </div>

          <!-- Stock validation — owner + manager only -->
          <?php if ($isOwnerOrManager): ?>
          <div class="notif-item">
            <div class="notif-dot notif-dot-gold"></div>
            <div>
              <strong style="font-size:14px;color:#0B1F3A">Stock entrant en attente</strong>
              <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Une livraison doit être validée avant d'augmenter l'inventaire.</p>
              <a class="od-primary" href="approval_center.php" style="font-size:12px;padding:7px 12px;display:inline-flex;align-items:center;gap:6px">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Voir validation
              </a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Subscription — owner only -->
          <?php if ($isOwner): ?>
          <div class="notif-item">
            <div class="notif-dot notif-dot-danger"></div>
            <div>
              <strong style="font-size:14px;color:#0B1F3A">Abonnement bientôt expiré</strong>
              <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Certaines actions seront limitées si l'abonnement expire.</p>
              <a href="subscription_billing.php" style="font-size:12px;padding:7px 12px;display:inline-flex;align-items:center;gap:6px;border:1.5px solid #E5E7EB;border-radius:10px;font-weight:700;color:#0B1F3A">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                Voir abonnement
              </a>
            </div>
          </div>
          <?php endif; ?>

          <!-- Employee late — owner + manager only -->
          <?php if ($isOwnerOrManager): ?>
          <div class="notif-item">
            <div class="notif-dot notif-dot-info"></div>
            <div>
              <strong style="font-size:14px;color:#0B1F3A">Employé en retard</strong>
              <p style="color:#6B7280;font-size:13px;margin:4px 0">Un employé a pointé après l'heure prévue.</p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($isEmployee): ?>
          <div class="notif-item">
            <div class="notif-dot notif-dot-info"></div>
            <div>
              <strong style="font-size:14px;color:#0B1F3A">Votre présence</strong>
              <p style="color:#6B7280;font-size:13px;margin:4px 0">N'oubliez pas de faire votre clock in aujourd'hui.</p>
            </div>
          </div>
          <?php endif; ?>

        </div>

        <!-- Summary panel — owner + manager only -->
        <?php if ($isOwnerOrManager): ?>
        <div class="od-card">
          <div class="od-card-head"><div><h2>Résumé</h2></div></div>
          <div style="display:flex;flex-direction:column;gap:10px">
            <div class="notif-summary-item">
              <div class="notif-summary-icon" style="background:#FEF3C7">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div><small style="color:#6B7280;font-size:12px">Stock faible</small><strong style="display:block;font-size:20px;color:#0B1F3A">—</strong></div>
            </div>
            <div class="notif-summary-item">
              <div class="notif-summary-icon" style="background:#DCFCE7">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div><small style="color:#6B7280;font-size:12px">Validations en attente</small><strong style="display:block;font-size:20px;color:#0B1F3A">—</strong></div>
            </div>
            <div class="notif-summary-item">
              <div class="notif-summary-icon" style="background:#DBEAFE">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div><small style="color:#6B7280;font-size:12px">Employés actifs</small><strong style="display:block;font-size:20px;color:#0B1F3A">—</strong></div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </main>
</div>
</body>
</html>