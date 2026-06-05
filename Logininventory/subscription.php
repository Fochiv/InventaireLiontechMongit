<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_BUSINESS_OWNER]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Renouveler l'abonnement — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge" style="background:linear-gradient(135deg,#D97706,#F0C040)">Abonnement expiré</div>
  <h1 class="dash-title" style="color:#92400E">Renouveler votre abonnement</h1>
  <p class="dash-sub">Bonjour <strong><?= htmlspecialchars($user['full_name']) ?></strong>,<br>
  Votre abonnement a expiré. Veuillez le renouveler pour retrouver l'accès.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">Contactez LionTech pour traiter votre renouvellement.</p>
  <a href="mailto:billing@liontech.com" class="dash-logout" style="display:inline-block;text-decoration:none;background:#D97706">
    Contacter la facturation
  </a>
  <div style="margin-top:16px">
    <button class="dash-logout" onclick="window.location='../logout.php'">Se déconnecter</button>
  </div>
</div>
</body></html>
