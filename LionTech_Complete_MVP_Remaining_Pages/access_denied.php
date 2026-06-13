<?php
/* ============================================================
   access_denied.php — Tally Business Manager
   Shown when a user tries to access a page they don't have
   permission for.
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

/* Figure out where to send the user back */
$role = $_SESSION['role'] ?? '';
$routes = json_decode(DASHBOARD_ROUTES, true);
$dashUrl = APP_URL . '/' . ($routes[$role] ?? 'Logininventory/login.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Accès refusé — Tally</title>
</head>
<body>
<div class="od-layout">
  <?php
  /* Only include sidebar if user is logged in */
  if (isset($_SESSION['user_id'])) {
      include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php';
  }
  ?>

  <main class="od-main" style="display:grid;place-items:center;min-height:100vh">
    <div class="od-card" style="max-width:480px;text-align:center;padding:48px 36px">

      <div style="font-size:60px;margin-bottom:16px"><span class="icon-no">⊘</span></div>

      <h1 style="font-size:24px;font-weight:800;color:#0B1F3A;margin-bottom:10px">
        Accès refusé
      </h1>

      <p style="font-size:14px;color:#6B7280;line-height:1.7;margin-bottom:28px">
        Vous n'avez pas la permission d'accéder à cette page.<br>
        Si vous pensez que c'est une erreur, contactez LionTech.
      </p>

      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="<?= htmlspecialchars($dashUrl) ?>"
          style="background:#0B1F3A;color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:700">
          🏠 Retour au dashboard
        </a>
        <a href="<?= APP_URL ?>/Logininventory/login.php"
          style="background:#fff;color:#0B1F3A;border:1.5px solid #E5E7EB;padding:12px 22px;border-radius:12px;text-decoration:none;font-size:14px;font-weight:600">
          <span class="icon-key">⚿</span> Se reconnecter
        </a>
      </div>

    </div>
  </main>
</div>
</body>
</html>