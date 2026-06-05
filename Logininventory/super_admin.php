<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Super Admin Dashboard — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge">🦁 Super Admin</div>
  <h1 class="dash-title">Super Admin Dashboard</h1>
  <p class="dash-sub">Welcome, <strong><?= htmlspecialchars($user['full_name']) ?></strong>!<br>
  You have full access to all businesses and system settings.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">
    Full dashboard coming soon. This is your role-specific placeholder.
  </p>
  <button class="dash-logout" onclick="window.location='../logout.php'">Sign Out</button>
</div>
</body></html>
