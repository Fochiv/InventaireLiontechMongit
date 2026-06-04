<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_MANAGER]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manager Dashboard — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge">👔 Manager</div>
  <h1 class="dash-title">Manager Dashboard</h1>
  <p class="dash-sub">Welcome, <strong><?= htmlspecialchars($user['full_name']) ?></strong>!<br>
  You can manage inventory, attendance, and daily operations.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">Business ID: <?= htmlspecialchars((string)$user['business_id']) ?></p>
  <button class="dash-logout" onclick="window.location='../logout.php'">Sign Out</button>
</div>
</body></html>
