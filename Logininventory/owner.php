<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_BUSINESS_OWNER]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Tableau de bord Propriétaire — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge">Propriétaire</div>
  <h1 class="dash-title">Tableau de bord Propriétaire</h1>
  <p class="dash-sub">Bienvenue, <strong><?= htmlspecialchars($user['full_name']) ?></strong> !<br>
  Gérez votre inventaire, vos employés et les rapports de votre établissement.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">Identifiant business : <?= htmlspecialchars((string)$user['business_id']) ?></p>
  <button class="dash-logout" onclick="window.location='../logout.php'">Se déconnecter</button>
</div>
</body></html>
