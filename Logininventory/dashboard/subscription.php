<?php
require_once __DIR__ . '/../../Config.php';
requireRole([ROLE_BUSINESS_OWNER]);
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Renew Subscription — LionTech</title>
<link rel="stylesheet" href="../style.css"/></head>
<body>
<div class="dash-page">
  <div class="dash-badge" style="background:linear-gradient(135deg,#D97706,#F0C040)">⚡ Subscription Expired</div>
  <h1 class="dash-title" style="color:#92400E">Renew Your Subscription</h1>
  <p class="dash-sub">Hi <strong><?= htmlspecialchars($user['full_name']) ?></strong>,<br>
  Your subscription has expired. Please renew to restore access.</p>
  <p style="color:#7A8CA0;font-size:13px;margin-bottom:32px">Contact LionTech to process your renewal.</p>
  <a href="mailto:billing@liontech.com" class="dash-logout" style="display:inline-block;text-decoration:none;background:#D97706">
    Contact Billing
  </a>
  <div style="margin-top:16px">
    <button class="dash-logout" onclick="window.location='../logout.php'">Sign Out</button>
  </div>
</div>
</body></html>
