<?php
/* ============================================================
   notifications.php — LionTech Business Manager
   Access: ALL roles (owner, manager, employee)
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
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
  <link rel="stylesheet" href="remaining_pages.css"/>
  <script src="remaining_pages.js" defer></script>
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/../../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">

    <!-- Topbar -->
    <div class="od-topbar">
      <div class="od-business-title">
        <h1>Notifications</h1>
        <p>Alertes et messages importants</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </div>

    <div style="padding:0 24px 40px">

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <div>
          <h2 style="font-size:20px;font-weight:700;color:#0B1F3A;margin:0">Notifications</h2>
          <p style="color:#6B7280;font-size:13px;margin:4px 0 0">Alertes importantes du business.</p>
        </div>
        <button class="od-primary" style="font-size:13px;padding:10px 16px;border:none;cursor:pointer">
          ✓ Tout marquer lu
        </button>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

        <!-- Alerts timeline -->
        <div class="od-card">
          <div class="od-card-head"><div><h2>Alertes récentes</h2></div></div>
          <div style="display:flex;flex-direction:column;gap:0;padding:0 4px 8px">

            <!-- Low stock — all roles see this -->
            <div style="display:flex;gap:14px;padding:16px 12px;border-bottom:1px solid #F1F5F9">
              <div style="width:10px;height:10px;border-radius:50%;background:#F59E0B;margin-top:5px;flex-shrink:0"></div>
              <div>
                <strong style="font-size:14px;color:#0B1F3A">Stock faible : Coca-Cola</strong>
                <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Le produit est proche du seuil minimum. Vérifiez le stock.</p>
                <span style="background:#FEF3C7;color:#92400E;border-radius:50px;padding:3px 10px;font-size:11px;font-weight:700">Stock faible</span>
              </div>
            </div>

            <!-- Stock validation — owner + manager only -->
            <?php if ($isOwnerOrManager): ?>
            <div style="display:flex;gap:14px;padding:16px 12px;border-bottom:1px solid #F1F5F9">
              <div style="width:10px;height:10px;border-radius:50%;background:#D4A017;margin-top:5px;flex-shrink:0"></div>
              <div>
                <strong style="font-size:14px;color:#0B1F3A">Stock entrant en attente</strong>
                <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Une livraison doit être validée avant d'augmenter l'inventaire.</p>
                <a class="od-primary" href="approval_center.php"
                   style="font-size:12px;padding:7px 12px;display:inline-flex">
                  Voir validation
                </a>
              </div>
            </div>
            <?php endif; ?>

            <!-- Subscription — owner only -->
            <?php if ($isOwner): ?>
            <div style="display:flex;gap:14px;padding:16px 12px;border-bottom:1px solid #F1F5F9">
              <div style="width:10px;height:10px;border-radius:50%;background:#DC2626;margin-top:5px;flex-shrink:0"></div>
              <div>
                <strong style="font-size:14px;color:#0B1F3A">Abonnement bientôt expiré</strong>
                <p style="color:#6B7280;font-size:13px;margin:4px 0 8px">Certaines actions seront limitées si l'abonnement expire.</p>
                <a href="subscription_billing.php" class="od-primary"
                   style="font-size:12px;padding:7px 12px;display:inline-flex;background:transparent;color:#0B1F3A;border:1px solid #CBD5E1">
                  Voir abonnement
                </a>
              </div>
            </div>
            <?php endif; ?>

            <!-- Employee late — owner + manager only -->
            <?php if ($isOwnerOrManager): ?>
            <div style="display:flex;gap:14px;padding:16px 12px">
              <div style="width:10px;height:10px;border-radius:50%;background:#2563EB;margin-top:5px;flex-shrink:0"></div>
              <div>
                <strong style="font-size:14px;color:#0B1F3A">Employé en retard</strong>
                <p style="color:#6B7280;font-size:13px;margin:4px 0">Un employé a pointé après l'heure prévue.</p>
              </div>
            </div>
            <?php endif; ?>

            <!-- Employee: only their own alerts -->
            <?php if ($isEmployee): ?>
            <div style="display:flex;gap:14px;padding:16px 12px">
              <div style="width:10px;height:10px;border-radius:50%;background:#2563EB;margin-top:5px;flex-shrink:0"></div>
              <div>
                <strong style="font-size:14px;color:#0B1F3A">Votre présence</strong>
                <p style="color:#6B7280;font-size:13px;margin:4px 0">N'oubliez pas de faire votre clock in aujourd'hui.</p>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Summary — owner + manager only -->
        <?php if ($isOwnerOrManager): ?>
        <div class="od-card">
          <div class="od-card-head"><div><h2>Résumé</h2></div></div>
          <div style="display:flex;flex-direction:column;gap:12px;padding:0 4px 12px">
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#F8FAFC;border-radius:12px">
              <span style="font-size:22px">⚠️</span>
              <div><small style="color:#6B7280;font-size:12px">Stock faible</small><strong style="display:block;font-size:20px;color:#0B1F3A">3</strong></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#F8FAFC;border-radius:12px">
              <span style="font-size:22px">✅</span>
              <div><small style="color:#6B7280;font-size:12px">Validations</small><strong style="display:block;font-size:20px;color:#0B1F3A">5</strong></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#F8FAFC;border-radius:12px">
              <span style="font-size:22px">👥</span>
              <div><small style="color:#6B7280;font-size:12px">Employés</small><strong style="display:block;font-size:20px;color:#0B1F3A">2</strong></div>
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