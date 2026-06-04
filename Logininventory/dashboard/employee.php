<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_EMPLOYEE]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Employee Dashboard — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge">👤 Employee</div>
  <h1 class="dash-title">Employee Dashboard</h1>
  <p class="dash-sub">Welcome, <strong><?= htmlspecialchars($user['full_name']) ?></strong>!<br>
  Clock in, view your schedule, and manage your tasks.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">Business ID: <?= htmlspecialchars((string)$user['business_id']) ?></p>
  <button class="dash-logout" onclick="window.location='../logout.php'">Sign Out</button>
</div>
</body></html>
