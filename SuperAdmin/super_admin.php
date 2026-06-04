<?php
/* ============================================================
   super_admin.php — LionTech Super Admin Dashboard
   Role: super_admin only. All others → redirect.
   ============================================================ */
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
$pdo  = getDB();

/* ── STATS ── */
$stats = ['total'=>0,'active'=>0,'expired'=>0,'users'=>0,'pending'=>0,'activity'=>0];
try {
    $stats['total']    = (int)$pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
    $stats['active']   = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_status='active'")->fetchColumn();
    $stats['expired']  = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_status IN('expired','suspended')")->fetchColumn();
    $stats['users']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role!='super_admin'")->fetchColumn();
    $stats['activity'] = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    try { $stats['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM liontech_payments WHERE status='pending'")->fetchColumn(); } catch(Throwable $e){}
} catch(Throwable $e){}

/* ── BUSINESSES ── */
$businesses = [];
try {
    $businesses = $pdo->query("
        SELECT b.business_id AS id, b.business_name AS name, b.business_type AS type,
               b.phone, b.city, b.subscription_status AS status, b.created_at AS created,
               u.full_name AS owner, u.login_id AS owner_username, u.temporary_pin_plain AS temporary_pin
        FROM businesses b
        LEFT JOIN users u ON u.business_id=b.business_id AND u.role='business_owner'
        ORDER BY b.created_at DESC")->fetchAll();
} catch(Throwable $e){}

/* ── ALERTS ── */
$alerts = [];
try {
    $rows = $pdo->query("
        SELECT business_id AS id, business_name AS name,
               subscription_status AS type, subscription_expires_at AS date
        FROM businesses
        WHERE subscription_status IN('expired','suspended','trial')
           OR (subscription_expires_at IS NOT NULL
               AND subscription_expires_at<=DATE_ADD(NOW(),INTERVAL 14 DAY)
               AND subscription_status='active')
        ORDER BY subscription_expires_at ASC LIMIT 10")->fetchAll();
    foreach($rows as $row){
        $exp = $row['date'] ? strtotime($row['date']) : null;
        $now = time();
        if(in_array($row['type'],['expired','suspended'],true)){
            $d = $exp ? ceil(($now-$exp)/86400) : '?';
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'expired','msg'=>"Expired {$d} day(s) ago",'date'=>$row['date']?date('M d, Y',strtotime($row['date'])):'—'];
        } elseif($row['type']==='trial'){
            $d = $exp ? ceil(($exp-$now)/86400) : '?';
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'pending','msg'=>"Trial — expires in {$d} day(s)",'date'=>$row['date']?date('M d, Y',strtotime($row['date'])):'—'];
        } else {
            $d = $exp ? ceil(($exp-$now)/86400) : '?';
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'warning','msg'=>"Expires in {$d} day(s)",'date'=>$row['date']?date('M d, Y',strtotime($row['date'])):'—'];
        }
    }
} catch(Throwable $e){}

/* ── ACTIVITY ── */
$activity = [];
try {
    $rows = $pdo->query("
        SELECT al.action, al.description AS desc, al.icon, al.created_at, b.business_name AS biz
        FROM activity_logs al
        LEFT JOIN businesses b ON b.business_id=al.business_id
        ORDER BY al.created_at DESC LIMIT 8")->fetchAll();
    foreach($rows as $row){
        $diff = time()-strtotime($row['created_at']);
        $time = $diff<3600 ? ceil($diff/60).' min ago' : ($diff<86400 ? ceil($diff/3600).' hrs ago' : ceil($diff/86400).' days ago');
        $activity[] = ['icon'=>$row['icon']?:'ℹ️','desc'=>$row['desc']?:$row['action'],'biz'=>$row['biz'],'time'=>$time];
    }
} catch(Throwable $e){}

/* ── CHART ── */
$chartData = ['labels'=>[],'revenue'=>[],'subscriptions'=>[]];
try {
    $rows = $pdo->query("SELECT month,total_revenue AS revenue,payment_count AS subs FROM v_monthly_revenue ORDER BY month ASC LIMIT 6")->fetchAll();
    foreach($rows as $row){
        $chartData['labels'][]        = date('M Y',strtotime($row['month'].'-01'));
        $chartData['revenue'][]       = (int)$row['revenue'];
        $chartData['subscriptions'][] = (int)$row['subs'];
    }
} catch(Throwable $e){}
if(empty($chartData['labels'])){
    $chartData = ['labels'=>['Jan','Feb','Mar','Apr','May','Jun'],'revenue'=>[0,0,0,0,0,0],'subscriptions'=>[0,0,0,0,0,0]];
}

$currentPage = basename($_SERVER['PHP_SELF']);
$initials = '';
foreach(explode(' ',$user['full_name']) as $w) $initials .= strtoupper($w[0]??'');
$initials = substr($initials,0,2);

function saNavLink(string $href, string $icon, string $label, string $currentPage, string $badge=''): string {
    $page    = basename($href);
    $active  = $page===$currentPage ? 'active' : '';
    $badgeHtml = $badge ? "<span class='sa-nav-badge'>{$badge}</span>" : '';
    return "<a class='sa-nav-item {$active}' href='{$href}'><span class='sa-nav-icon'>{$icon}</span><span>{$label}</span>{$badgeHtml}</a>";
}
$url = APP_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Super Admin — LionTech</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230B1F3A'/><text y='22' x='5' font-size='20'>🦁</text></svg>"/>
  <link rel="stylesheet" href="super_admin.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>window.SA_CHART_DATA=<?= json_encode($chartData) ?>;</script>
</head>
<body>
<div class="sa-layout">

<!-- ══ SIDEBAR ══ -->
<aside class="sa-sidebar" id="sa-sidebar">
  <div class="sa-sidebar-header">
    <div class="sa-logo">
       <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
      <div><div class="sa-logo-name">LionTech</div><div class="sa-logo-tag">Business Manager</div></div>
    </div>
    <button class="sa-sidebar-close" id="sa-sidebar-close">✕</button>
  </div>

  <nav class="sa-nav">

    <div class="sa-nav-section">Principal</div>

    <?= saNavLink("{$url}/SuperAdmin/super_admin.php",         '📊', 'Dashboard',           $currentPage) ?>
    <?= saNavLink("{$url}/LionTech_Add_Business_Page/liontech_add_business_page/add_business.php", '➕', 'Add Business', $currentPage) ?>

    <!-- Businesses JS tab (same page) -->
    <button class="sa-nav-item" data-page="businesses">
      <span class="sa-nav-icon">🏢</span>
      <span>Businesses</span>
      <span class="sa-nav-badge"><?= $stats['total'] ?></span>
    </button>

    <div class="sa-nav-section">Paiements</div>

    <?= saNavLink("{$url}/SuperAdmin/payment_review.php",   '✅', 'Valider Paiements',      $currentPage, $stats['pending']>0?(string)$stats['pending']:'') ?>
    <?= saNavLink("{$url}/SuperAdmin/payment_settings.php", '💳', 'Numéros de Paiement',    $currentPage) ?>

    <div class="sa-nav-section">Plateforme</div>

    <!-- Subscriptions JS tab -->
    <button class="sa-nav-item" data-page="subscriptions">
      <span class="sa-nav-icon">🔄</span>
      <span>Subscriptions</span>
      <span class="sa-nav-badge"><?= $stats['expired'] ?></span>
    </button>

    <!-- Users JS tab -->
    <button class="sa-nav-item" data-page="users">
      <span class="sa-nav-icon">👥</span>
      <span>Users</span>
      <span class="sa-nav-badge"><?= $stats['users'] ?></span>
    </button>

    <?= saNavLink("{$url}/SuperAdmin/super_admin_reports.php", '📈', 'Reports', $currentPage) ?>

    <div class="sa-nav-section">Système</div>

    <?= saNavLink("{$url}/SuperAdmin/payment_settings.php", '⚙️', 'Settings', $currentPage) ?>

    <a class="sa-nav-item sa-nav-logout" href="<?= $url ?>/Logininventory/logout.php">
      <span class="sa-nav-icon">🚪</span><span>Logout</span>
    </a>

  </nav>

  <div class="sa-sidebar-footer">
    <div class="sa-sidebar-avatar"><?= htmlspecialchars($initials) ?></div>
    <div>
      <div class="sa-sidebar-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="sa-sidebar-role">Super Admin</div>
    </div>
  </div>
</aside>
<div class="sa-overlay" id="sa-overlay"></div>

<!-- ══ MAIN ══ -->
<div class="sa-main">

  <!-- Topbar -->
  <header class="sa-topbar">
    <button class="sa-hamburger" id="sa-hamburger">☰</button>
    <div class="sa-topbar-search">
      <span class="sa-search-icon">🔍</span>
      <input type="search" id="topbar-search" placeholder="Search businesses, users…"/>
    </div>
    <div class="sa-topbar-right">

      <!-- Notifications -->
      <div style="position:relative">
        <button class="sa-icon-btn" data-dropdown="notif-dropdown">🔔<span class="sa-notif-dot"></span></button>
        <div class="sa-dropdown sa-notif-panel" id="notif-dropdown">
          <div class="sa-notif-header">Notifications (<?= count($alerts) ?>)</div>
          <?php foreach(array_slice($alerts,0,3) as $a): ?>
          <div class="sa-notif-item">
            <div class="sa-notif-ico" style="background:<?= $a['type']==='expired'?'#FEF2F2':($a['type']==='warning'?'#FFFBEB':'#EFF6FF') ?>">
              <?= $a['type']==='expired'?'❌':($a['type']==='warning'?'⚠️':'💳') ?>
            </div>
            <div>
              <div class="sa-notif-txt"><?= htmlspecialchars($a['msg']) ?></div>
              <div class="sa-notif-time"><?= htmlspecialchars($a['name']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Profile -->
      <div style="position:relative">
        <button class="sa-profile-btn" data-dropdown="profile-dropdown">
          <div class="sa-profile-av"><?= htmlspecialchars($initials) ?></div>
          <div>
            <div class="sa-profile-name"><?= htmlspecialchars(explode(' ',$user['full_name'])[0]) ?></div>
            <div class="sa-profile-role">Super Admin</div>
          </div>
          <span class="sa-chev">▾</span>
        </button>
        <div class="sa-dropdown" id="profile-dropdown">
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/payment_settings.php">⚙️ Settings</a>
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/payment_review.php">✅ Validate Payments</a>
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/super_admin_reports.php">📈 Reports</a>
          <div class="sa-dropdown-divider"></div>
          <a class="sa-dropdown-item danger" href="<?= $url ?>/Logininventory/logout.php">🚪 Logout</a>
        </div>
      </div>

    </div>
  </header>

  <!-- Content -->
  <main class="sa-content">

    <!-- Page Header -->
    <div class="sa-page-header">
      <div>
        <h1 class="sa-page-title">Super Admin Dashboard</h1>
        <p class="sa-page-sub">Manage businesses, subscriptions, users, and platform activity.</p>
      </div>
      <div class="sa-page-actions">
        <?php if($stats['pending']>0): ?>
        <a class="sa-btn sa-btn-outline" href="<?= $url ?>/SuperAdmin/payment_review.php">
          ⏳ <?= $stats['pending'] ?> paiement(s) en attente
        </a>
        <?php endif; ?>
        <a class="sa-btn sa-btn-outline" href="<?= $url ?>/SuperAdmin/payment_settings.php">💳 Numéros Paiement</a>
        <a class="sa-btn sa-btn-primary" href="<?= $url ?>/LionTech_Add_Business_Page/liontech_add_business_page/add_business.php">➕ Add New Business</a>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="sa-cards-grid">
      <div class="sa-stat-card navy">
        <div class="sa-stat-icon navy">🏢</div>
        <div><div class="sa-stat-val"><?= $stats['total'] ?></div><div class="sa-stat-label">Total Businesses</div></div>
      </div>
      <div class="sa-stat-card green">
        <div class="sa-stat-icon green">✅</div>
        <div><div class="sa-stat-val"><?= $stats['active'] ?></div><div class="sa-stat-label">Active Businesses</div></div>
      </div>
      <div class="sa-stat-card red">
        <div class="sa-stat-icon red">⚠️</div>
        <div><div class="sa-stat-val"><?= $stats['expired'] ?></div><div class="sa-stat-label">Expired Subscriptions</div></div>
      </div>
      <div class="sa-stat-card teal">
        <div class="sa-stat-icon teal">👥</div>
        <div><div class="sa-stat-val"><?= $stats['users'] ?></div><div class="sa-stat-label">Total Users</div></div>
      </div>
      <div class="sa-stat-card amber">
        <div class="sa-stat-icon amber">💳</div>
        <div>
          <div class="sa-stat-val"><?= $stats['pending'] ?></div>
          <div class="sa-stat-label">Pending Payments</div>
          <?php if($stats['pending']>0): ?>
          <a href="<?= $url ?>/SuperAdmin/payment_review.php" style="font-size:11px;color:#D4A017;font-weight:700;text-decoration:none">→ Valider</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="sa-stat-card blue">
        <div class="sa-stat-icon blue">📋</div>
        <div><div class="sa-stat-val"><?= $stats['activity'] ?></div><div class="sa-stat-label">Today's Activity</div></div>
      </div>
    </div>

    <!-- Chart + Alerts -->
    <div class="sa-row">
      <div class="sa-card">
        <div class="sa-card-header">
          <div><div class="sa-card-title">Monthly Trends</div><div class="sa-card-sub">Revenue (XAF) &amp; Active Subscriptions</div></div>
          <a class="sa-btn sa-btn-sm sa-btn-outline" href="<?= $url ?>/SuperAdmin/super_admin_reports.php">View Reports</a>
        </div>
        <div class="sa-chart-wrap"><canvas id="sa-chart"></canvas></div>
        <div class="sa-chart-legend">
          <div class="sa-legend-item"><div class="sa-legend-dot" style="background:#D4A017"></div>Revenue (XAF)</div>
          <div class="sa-legend-item"><div class="sa-legend-dot" style="background:#1A9E7A"></div>Active Subscriptions</div>
        </div>
      </div>

      <div class="sa-card">
        <div class="sa-card-header">
          <div><div class="sa-card-title">Subscription Alerts</div><div class="sa-card-sub">Businesses needing attention</div></div>
          <span style="background:#FEF2F2;color:#DC2626;font-size:11px;font-weight:700;border-radius:50px;padding:3px 10px"><?= count($alerts) ?> alerts</span>
        </div>
        <div class="sa-alerts-list">
          <?php foreach($alerts as $a): ?>
          <div class="sa-alert-item <?= htmlspecialchars($a['type']) ?>">
            <span class="sa-alert-icon"><?= $a['type']==='expired'?'❌':($a['type']==='warning'?'⚠️':'💳') ?></span>
            <div style="flex:1;min-width:0">
              <div class="sa-alert-biz"><?= htmlspecialchars($a['name']) ?></div>
              <div class="sa-alert-msg"><?= htmlspecialchars($a['msg']) ?></div>
              <div class="sa-alert-date"><?= htmlspecialchars($a['date']) ?></div>
            </div>
            <button class="sa-alert-renew" data-id="<?= $a['id'] ?>" data-name="<?= htmlspecialchars($a['name']) ?>">Renew</button>
          </div>
          <?php endforeach; ?>
          <?php if(empty($alerts)): ?>
          <div style="padding:28px;text-align:center;color:#6B7280;font-size:13.5px">✅ No subscription alerts.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Business Table -->
    <div class="sa-card sa-table-card">
      <div class="sa-card-header">
        <div>
          <div class="sa-card-title">Business Overview</div>
          <div class="sa-card-sub" id="table-count">Showing <?= count($businesses) ?> businesses</div>
        </div>
        <a class="sa-btn sa-btn-sm sa-btn-primary"
           href="<?= $url ?>/LionTech_Add_Business_Page/liontech_add_business_page/add_business.php">
          ➕ Add Business
        </a>
      </div>
      <div class="sa-table-controls">
        <div class="sa-search-wrap">
          <span class="sa-search-ico">🔍</span>
          <input type="search" class="sa-table-search" id="table-search" placeholder="Search by name, type, owner, city…"/>
        </div>
        <select class="sa-table-filter" id="table-filter">
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="expired">Expired</option>
          <option value="pending">Pending</option>
          <option value="trial">Trial</option>
        </select>
      </div>
      <div class="sa-table-wrap">
        <table class="sa-table" id="businesses-table">
          <thead><tr>
            <th>Business Name</th><th>Type</th><th>Owner</th><th>Phone</th>
            <th>City</th><th>Status</th><th>Created</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach($businesses as $b): ?>
          <tr data-id="<?= $b['id'] ?>"
              data-name="<?= htmlspecialchars($b['name']) ?>"
              data-type="<?= htmlspecialchars($b['type']) ?>"
              data-owner="<?= htmlspecialchars($b['owner']) ?>"
              data-phone="<?= htmlspecialchars($b['phone']) ?>"
              data-city="<?= htmlspecialchars($b['city']) ?>"
              data-status="<?= htmlspecialchars($b['status']) ?>"
              data-date="<?= htmlspecialchars($b['created']) ?>"
              data-owner-username="<?= htmlspecialchars($b['owner_username']??'') ?>"
              data-temp-pin="<?= htmlspecialchars($b['temporary_pin']??'') ?>">
            <td><?= htmlspecialchars($b['name']) ?></td>
            <td><?= htmlspecialchars($b['type']) ?></td>
            <td><?= htmlspecialchars($b['owner']) ?></td>
            <td><?= htmlspecialchars($b['phone']) ?></td>
            <td><?= htmlspecialchars($b['city']) ?></td>
            <td><span class="sa-badge sa-badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst(htmlspecialchars($b['status'])) ?></span></td>
            <td><?= htmlspecialchars($b['created']) ?></td>
            <td>
              <div class="sa-tbl-actions">
                <button class="sa-tbl-btn sa-tbl-btn-view" onclick="viewBusiness(
                  <?= $b['id'] ?>,
                  '<?= addslashes($b['name']) ?>',
                  '<?= addslashes($b['type']) ?>',
                  '<?= addslashes($b['owner']) ?>',
                  '<?= addslashes($b['phone']) ?>',
                  '<?= addslashes($b['city']) ?>',
                  '<?= $b['status'] ?>',
                  '<?= $b['created'] ?>',
                  '<?= addslashes($b['owner_username']??'') ?>',
                  '<?= addslashes($b['temporary_pin']??'') ?>'
                )">View</button>
                <button class="sa-tbl-btn sa-tbl-btn-edit" onclick="editBusiness(
                  <?= $b['id'] ?>,
                  '<?= addslashes($b['name']) ?>',
                  '<?= addslashes($b['type']) ?>',
                  '<?= addslashes($b['owner']) ?>',
                  '<?= addslashes($b['phone']) ?>',
                  '<?= addslashes($b['city']) ?>'
                )">Edit</button>
                <button class="sa-tbl-btn sa-tbl-btn-disable" onclick="confirmDisable(<?= $b['id'] ?>,'<?= addslashes($b['name']) ?>')">Disable</button>
                <?php if(in_array($b['status'],['expired','pending'],true)): ?>
                <button class="sa-tbl-btn sa-tbl-btn-renew" onclick="renewSubscription(<?= $b['id'] ?>,'<?= addslashes($b['name']) ?>')">Renew</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($businesses)): ?>
          <tr><td colspan="8" style="text-align:center;padding:28px;color:#6B7280">No businesses yet. <a href="<?= $url ?>/LionTech_Add_Business_Page/liontech_add_business_page/add_business.php">Add the first one →</a></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="sa-pagination">
        <span>Page 1 of 1</span>
        <div class="sa-pg-btns">
          <button class="sa-pg-btn">‹</button>
          <button class="sa-pg-btn active">1</button>
          <button class="sa-pg-btn">›</button>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="sa-card">
      <div class="sa-card-header">
        <div><div class="sa-card-title">Recent Activity</div><div class="sa-card-sub">Latest platform events</div></div>
      </div>
      <div class="sa-activity-list">
        <?php foreach($activity as $act): ?>
        <div class="sa-activity-item">
          <div class="sa-activity-icon sa-act-navy"><?= $act['icon'] ?></div>
          <div style="flex:1;min-width:0">
            <div class="sa-activity-desc"><?= htmlspecialchars($act['desc']) ?></div>
            <?php if($act['biz']): ?><div class="sa-activity-biz"><?= htmlspecialchars($act['biz']) ?></div><?php endif; ?>
            <div class="sa-activity-time"><?= htmlspecialchars($act['time']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($activity)): ?>
        <div style="padding:28px;text-align:center;color:#6B7280;font-size:13.5px">No activity logged today.</div>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div><!-- /.sa-main -->
</div><!-- /.sa-layout -->

<!-- ══ MODALS ══ -->

<!-- View Business -->
<div class="sa-modal-overlay" id="view-business-modal">
  <div class="sa-modal">
    <div class="sa-modal-header"><h2 class="sa-modal-title">Business Details</h2><button class="sa-modal-close" data-close-modal>✕</button></div>
    <div class="sa-modal-body">
      <div class="sa-detail-grid">
        <div class="sa-detail-item"><div class="sa-detail-label">Business Name</div><div class="sa-detail-val" id="view-biz-name">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Type</div><div class="sa-detail-val" id="view-biz-type">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Owner</div><div class="sa-detail-val" id="view-biz-owner">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Owner Username</div><div class="sa-detail-val" id="view-owner-username">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Temporary PIN</div><div class="sa-detail-val" id="view-temp-pin">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Phone</div><div class="sa-detail-val" id="view-biz-phone">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">City</div><div class="sa-detail-val" id="view-biz-city">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Date Created</div><div class="sa-detail-val" id="view-biz-date">—</div></div>
        <div class="sa-detail-item" style="grid-column:1/-1"><div class="sa-detail-label">Status</div><div class="sa-detail-val"><span class="sa-badge" id="view-biz-status">—</span></div></div>
      </div>
    </div>
    <div class="sa-modal-footer"><button class="sa-btn sa-btn-outline" data-close-modal>Close</button></div>
  </div>
</div>

<!-- Edit Business -->
<div class="sa-modal-overlay" id="edit-business-modal">
  <div class="sa-modal sa-modal-lg">
    <div class="sa-modal-header"><h2 class="sa-modal-title">Edit Business</h2><button class="sa-modal-close" data-close-modal>✕</button></div>
    <div class="sa-modal-body">
      <input type="hidden" id="edit-biz-id"/>
      <div class="sa-form-grid">
        <div class="sa-form-group"><label class="sa-form-label">Business Name *</label><input type="text" class="sa-form-input" id="edit-biz-name"/></div>
        <div class="sa-form-group"><label class="sa-form-label">Type *</label>
          <select class="sa-form-select" id="edit-biz-type">
            <option>Restaurant</option><option>Salon</option><option>Boutique</option>
            <option>Snack Bar</option><option>Retail</option><option>Fashion</option><option>Other</option>
          </select>
        </div>
        <div class="sa-form-group"><label class="sa-form-label">Owner Name</label><input type="text" class="sa-form-input" id="edit-biz-owner"/></div>
        <div class="sa-form-group"><label class="sa-form-label">Phone</label><input type="tel" class="sa-form-input" id="edit-biz-phone"/></div>
        <div class="sa-form-group"><label class="sa-form-label">City</label><input type="text" class="sa-form-input" id="edit-biz-city"/></div>
        <div class="sa-form-group"><label class="sa-form-label">Status</label>
          <select class="sa-form-select" id="edit-biz-status-sel">
            <option value="active">Active</option><option value="expired">Expired</option>
            <option value="trial">Trial</option><option value="suspended">Suspended</option>
          </select>
        </div>
      </div>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Cancel</button>
      <button class="sa-btn sa-btn-primary" onclick="saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- Disable Confirm -->
<div class="sa-modal-overlay" id="disable-modal">
  <div class="sa-modal" style="max-width:420px">
    <div class="sa-modal-header"><h2 class="sa-modal-title">Disable Business</h2><button class="sa-modal-close" data-close-modal>✕</button></div>
    <div class="sa-modal-body">
      <div class="sa-confirm-icon">⛔</div>
      <p class="sa-confirm-msg" id="disable-confirm-msg">Are you sure?</p>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Cancel</button>
      <button class="sa-btn sa-btn-danger" id="disable-confirm-btn">Yes, Disable</button>
    </div>
  </div>
</div>

<!-- Renew Subscription -->
<div class="sa-modal-overlay" id="renew-modal">
  <div class="sa-modal">
    <div class="sa-modal-header"><h2 class="sa-modal-title">Renew Subscription</h2><button class="sa-modal-close" data-close-modal>✕</button></div>
    <div class="sa-modal-body">
      <input type="hidden" id="renew-biz-id"/>
      <p style="font-size:13.5px;color:#64748B;margin-bottom:16px">
        Renewing for: <strong id="renew-biz-name" style="color:#0B1F3A">—</strong>
      </p>
      <div class="sa-form-grid">
        <div class="sa-form-group"><label class="sa-form-label">Plan</label>
          <select class="sa-form-select" id="renew-plan">
            <option value="standard">Standard — 10,000 XAF/mo</option>
            <option value="premium">Premium — 20,000 XAF/mo</option>
          </select>
        </div>
        <div class="sa-form-group"><label class="sa-form-label">Amount (XAF)</label><input type="number" class="sa-form-input" id="renew-amount" value="10000" min="0"/></div>
        <div class="sa-form-group"><label class="sa-form-label">Start Date</label><input type="date" class="sa-form-input" id="renew-start"/></div>
        <div class="sa-form-group"><label class="sa-form-label">End Date</label><input type="date" class="sa-form-input" id="renew-end"/></div>
        <div class="sa-form-group full"><label class="sa-form-label">Notes</label><textarea class="sa-form-textarea" id="renew-notes" placeholder="e.g. Paid via MTN MoMo"></textarea></div>
      </div>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Cancel</button>
      <button class="sa-btn sa-btn-teal" onclick="saveRenewal()">✅ Confirm Renewal</button>
    </div>
  </div>
</div>

<div class="sa-toast" id="sa-toast"></div>
<script src="super_admin.js"></script>
</body>
</html>