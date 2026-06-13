<?php
/* ============================================================
   super_admin.php — LionTech Super Admin Dashboard
   Role: super_admin only.
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
    try { $stats['pending'] = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn(); } catch(Throwable $e){}
} catch(Throwable $e){}

/* ── PENDING BUSINESS REQUESTS ── */
$pendingBizReq = 0;
try {
    $pendingBizReq = (int)$pdo->query("SELECT COUNT(*) FROM business_requests WHERE status='pending'")->fetchColumn();
} catch(Throwable $e){}

/* ── BUSINESSES ── */
$businesses = [];
try {
    $businesses = $pdo->query("
        SELECT b.business_id AS id, b.business_name AS name, b.business_type AS type,
               b.phone, b.city, b.subscription_status AS status, b.created_at AS created,
               u.full_name AS owner, u.login_id AS owner_username,
               u.email AS owner_email
        FROM businesses b
        LEFT JOIN users u ON u.business_id=b.business_id AND u.role='business_owner'
        ORDER BY b.created_at DESC")->fetchAll();
} catch(Throwable $e){}

/* ── USERS ── */
$allUsers = [];
try {
    $allUsers = $pdo->query("
        SELECT u.user_id, u.full_name, u.login_id, u.email, u.phone, u.role, u.status, u.created_at,
               b.business_name
        FROM users u
        LEFT JOIN businesses b ON b.business_id = u.business_id
        WHERE u.role IN ('business_owner', 'manager')
        ORDER BY u.created_at DESC
        LIMIT 300")->fetchAll();
} catch(Throwable $e){}

/* ── SUBSCRIPTIONS ── */
$subscriptions = [];
try {
    $subscriptions = $pdo->query("
        SELECT s.subscription_id, s.plan_name, s.amount, s.currency, s.start_date, s.end_date,
               s.status, s.created_at,
               b.business_name, b.subscription_status AS biz_status,
               u.full_name AS owner_name
        FROM subscriptions s
        JOIN businesses b ON b.business_id = s.business_id
        LEFT JOIN users u ON u.business_id = s.business_id AND u.role = 'business_owner'
        ORDER BY s.created_at DESC
        LIMIT 200")->fetchAll();
} catch(Throwable $e){}
if (empty($subscriptions)) {
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM businesses")->fetchAll(), 'Field');
        $expCol = in_array('subscription_expires_at', $cols) ? 'b.subscription_expires_at' : 'NULL';
        $subscriptions = $pdo->query("
            SELECT b.business_id AS subscription_id, 'Standard' AS plan_name,
                   10000 AS amount, 'XAF' AS currency,
                   b.created_at AS start_date, {$expCol} AS end_date,
                   b.subscription_status AS status, b.created_at,
                   b.business_name, b.subscription_status AS biz_status,
                   u.full_name AS owner_name
            FROM businesses b
            LEFT JOIN users u ON u.business_id=b.business_id AND u.role='business_owner'
            ORDER BY b.created_at DESC")->fetchAll();
    } catch(Throwable $e2){}
}

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
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'expired','msg'=>"Expiré il y a {$d} jour(s)",'date'=>$row['date']?date('d M Y',strtotime($row['date'])):'—'];
        } elseif($row['type']==='trial'){
            $d = $exp ? ceil(($exp-$now)/86400) : '?';
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'pending','msg'=>"Essai — expire dans {$d} jour(s)",'date'=>$row['date']?date('d M Y',strtotime($row['date'])):'—'];
        } else {
            $d = $exp ? ceil(($exp-$now)/86400) : '?';
            $alerts[] = ['id'=>$row['id'],'name'=>$row['name'],'type'=>'warning','msg'=>"Expire dans {$d} jour(s)",'date'=>$row['date']?date('d M Y',strtotime($row['date'])):'—'];
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
    LEFT JOIN users u ON u.user_id=al.user_id
    WHERE al.user_id IS NULL 
       OR u.role IN ('super_admin','business_owner','manager')
    ORDER BY al.created_at DESC LIMIT 8")->fetchAll();
    foreach($rows as $row){
        $diff = time()-strtotime($row['created_at']);
        $time = $diff<3600 ? ceil($diff/60).' min' : ($diff<86400 ? ceil($diff/3600).'h' : ceil($diff/86400).'j');
        $activity[] = ['icon'=>$row['icon']?:'info','desc'=>$row['desc']?:$row['action'],'biz'=>$row['biz'],'time'=>$time.' ago'];
    }
} catch(Throwable $e){}
if (empty($activity)) {
    try {
        $rows2 = $pdo->query("
            SELECT p.status, p.created_at, b.business_name AS biz,
                   CONCAT('Paiement ', p.status, ' — ', FORMAT(p.amount,0), ' XAF pour ', b.business_name) AS action
            FROM liontech_payments p
            JOIN businesses b ON b.business_id = p.business_id
            ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
        foreach($rows2 as $row){
            $diff = time()-strtotime($row['created_at']);
            $t    = $diff<3600 ? ceil($diff/60).' min' : ($diff<86400 ? ceil($diff/3600).'h' : ceil($diff/86400).'j');
            $icon = $row['status']==='approved' ? 'check' : ($row['status']==='pending' ? 'clock' : 'x-circle');
            $activity[] = ['icon'=>$icon,'desc'=>$row['action'],'biz'=>$row['biz'],'time'=>$t.' ago'];
        }
    } catch(Throwable $e2){}
}
if (empty($activity)) {
    try {
        $rows3 = $pdo->query("
            SELECT b.business_name, b.created_at,
                   CONCAT('Business enregistré : ', b.business_name) AS action
            FROM businesses b ORDER BY b.created_at DESC LIMIT 5")->fetchAll();
        foreach($rows3 as $row){
            $diff = time()-strtotime($row['created_at']);
            $t    = $diff<3600 ? ceil($diff/60).' min' : ($diff<86400 ? ceil($diff/3600).'h' : ceil($diff/86400).'j');
            $activity[] = ['icon'=>'briefcase','desc'=>$row['action'],'biz'=>$row['business_name'],'time'=>$t.' ago'];
        }
    } catch(Throwable $e3){}
}

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
    $chartData = ['labels'=>['Jan','Fév','Mar','Avr','Mai','Jun'],'revenue'=>[0,0,0,0,0,0],'subscriptions'=>[0,0,0,0,0,0]];
}

$currentPage = basename($_SERVER['PHP_SELF']);
$initials = '';
foreach(explode(' ',$user['full_name']) as $w) $initials .= strtoupper($w[0]??'');
$initials = substr($initials,0,2);
$url = APP_URL;

/* ── SVG Icon helper ── */
function saIcon(string $name, int $size=18): string {
    $s = $size;
    $icons = [
        'dashboard'   => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='7' height='7'/><rect x='14' y='3' width='7' height='7'/><rect x='14' y='14' width='7' height='7'/><rect x='3' y='14' width='7' height='7'/></svg>",
        'plus'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='16'/><line x1='8' y1='12' x2='16' y2='12'/></svg>",
        'building'    => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='2' width='18' height='20' rx='1'/><line x1='9' y1='22' x2='9' y2='12'/><line x1='15' y1='22' x2='15' y2='12'/><rect x='9' y='12' width='6' height='10'/><path d='M7 6h.01M7 10h.01M11 6h.01M11 10h.01M17 6h.01M17 10h.01'/></svg>",
        'check'       => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/><polyline points='22 4 12 14.01 9 11.01'/></svg>",
        'credit-card' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='1' y='4' width='22' height='16' rx='2' ry='2'/><line x1='1' y1='10' x2='23' y2='10'/></svg>",
        'refresh'     => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='23 4 23 10 17 10'/><polyline points='1 20 1 14 7 14'/><path d='M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15'/></svg>",
        'users'       => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
        'bar-chart'   => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>",
        'settings'    => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='3'/><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'/></svg>",
        'logout'      => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'/><polyline points='16 17 21 12 16 7'/><line x1='21' y1='12' x2='9' y2='12'/></svg>",
        'menu'        => "<svg width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='3' y1='12' x2='21' y2='12'/><line x1='3' y1='6' x2='21' y2='6'/><line x1='3' y1='18' x2='21' y2='18'/></svg>",
        'close'       => "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg>",
        'search'      => "<svg width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>",
        'bell'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9'/><path d='M13.73 21a2 2 0 0 1-3.46 0'/></svg>",
        'chevron'     => "<svg width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>",
        'warning'     => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg>",
        'x-circle'    => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='15' y1='9' x2='9' y2='15'/><line x1='9' y1='9' x2='15' y2='15'/></svg>",
        'clock'       => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>",
        'info'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='12' y1='16' x2='12' y2='12'/><line x1='12' y1='8' x2='12.01' y2='8'/></svg>",
        'trend'       => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='23 6 13.5 15.5 8.5 10.5 1 18'/><polyline points='17 6 23 6 23 12'/></svg>",
        'list'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='8' y1='6' x2='21' y2='6'/><line x1='8' y1='12' x2='21' y2='12'/><line x1='8' y1='18' x2='21' y2='18'/><line x1='3' y1='6' x2='3.01' y2='6'/><line x1='3' y1='12' x2='3.01' y2='12'/><line x1='3' y1='18' x2='3.01' y2='18'/></svg>",
        'user'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/><circle cx='12' cy='7' r='4'/></svg>",
    ];
    return $icons[$name] ?? $icons['info'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Super Admin — LionTech</title>
  <link rel="icon" href="<?= $url ?>/Image/logo_lionTechhead.jpeg"/>
  <link rel="stylesheet" href="super_admin.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>window.SA_CHART_DATA=<?= json_encode($chartData) ?>;</script>
  <style>
    @keyframes sa-pulse {
      0%,100% { opacity:1; transform:scale(1); }
      50%      { opacity:.6; transform:scale(1.2); }
    }
  </style>
</head>
<body>
<div class="sa-layout">

<!-- ══ SIDEBAR ══ -->
<aside class="sa-sidebar" id="sa-sidebar">
  <div class="sa-sidebar-header">
    <div class="sa-logo">
      <img src="<?= $url ?>/Image/logo_lionTechhead.jpeg" alt="LionTech"
           style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div><div class="sa-logo-name">LionTech</div><div class="sa-logo-tag">Business Manager</div></div>
    </div>
    <button class="sa-sidebar-close" id="sa-sidebar-close"><?= saIcon('close') ?></button>
  </div>

  <nav class="sa-nav">
    <div class="sa-nav-section">Principal</div>

    <button class="sa-nav-item active" data-panel="dashboard">
      <span class="sa-nav-icon"><?= saIcon('dashboard') ?></span>
      <span>Dashboard</span>
    </button>

    <a class="sa-nav-item" href="<?= $url ?>/LionTech_Add_Business_Page/add_business.php">
      <span class="sa-nav-icon"><?= saIcon('plus') ?></span>
      <span>Ajouter Business</span>
    </a>

    <!-- Confirm Business Requests — only clickable when pending > 0 -->
    <?php if ($pendingBizReq > 0): ?>
    <a class="sa-nav-item" href="<?= $url ?>/SuperAdmin/business_requests.php"
       style="background:rgba(249,115,22,.1)">
      <span class="sa-nav-icon"><?= saIcon('check') ?></span>
      <span>Confirmer Demandes</span>
      <span class="sa-nav-badge" style="background:#F97316;animation:sa-pulse 1.5s infinite">
        <?= $pendingBizReq ?>
      </span>
    </a>
    <?php else: ?>
    <span class="sa-nav-item" style="opacity:.4;cursor:not-allowed;pointer-events:none;">
      <span class="sa-nav-icon"><?= saIcon('check') ?></span>
      <span>Confirmer Demandes</span>
    </span>
    <?php endif; ?>

    <button class="sa-nav-item" data-panel="businesses">
      <span class="sa-nav-icon"><?= saIcon('building') ?></span>
      <span>Businesses</span>
      <span class="sa-nav-badge"><?= $stats['total'] ?></span>
    </button>

    <div class="sa-nav-section">Paiements</div>

    <a class="sa-nav-item" href="<?= $url ?>/SuperAdmin/payment_review.php">
      <span class="sa-nav-icon"><?= saIcon('check') ?></span>
      <span>Valider Paiements</span>
      <?php if($stats['pending']>0): ?>
      <span class="sa-nav-badge"><?= $stats['pending'] ?></span>
      <?php endif; ?>
    </a>

    <a class="sa-nav-item" href="<?= $url ?>/SuperAdmin/payment_settings.php">
      <span class="sa-nav-icon"><?= saIcon('credit-card') ?></span>
      <span>Numéros Paiement</span>
    </a>

    <div class="sa-nav-section">Plateforme</div>

    <button class="sa-nav-item" data-panel="subscriptions">
      <span class="sa-nav-icon"><?= saIcon('refresh') ?></span>
      <span>Abonnements</span>
      <span class="sa-nav-badge"><?= count($subscriptions) ?></span>
    </button>

    <button class="sa-nav-item" data-panel="users">
      <span class="sa-nav-icon"><?= saIcon('users') ?></span>
      <span>Utilisateurs</span>
      <span class="sa-nav-badge"><?= $stats['users'] ?></span>
    </button>

    <a class="sa-nav-item" href="<?= $url ?>/SuperAdmin/super_admin_reports.php">
      <span class="sa-nav-icon"><?= saIcon('bar-chart') ?></span>
      <span>Rapports</span>
    </a>

    <div class="sa-nav-section">Système</div>

    <a class="sa-nav-item" href="<?= $url ?>/SuperAdmin/payment_settings.php">
      <span class="sa-nav-icon"><?= saIcon('settings') ?></span>
      <span>Paramètres</span>
    </a>

    <a class="sa-nav-item sa-nav-logout" href="<?= $url ?>/Logininventory/logout.php">
      <span class="sa-nav-icon"><?= saIcon('logout') ?></span>
      <span>Déconnexion</span>
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
    <button class="sa-hamburger" id="sa-hamburger"><?= saIcon('menu') ?></button>
    <div class="sa-topbar-search">
      <span class="sa-search-icon"><?= saIcon('search') ?></span>
      <input type="search" id="topbar-search" placeholder="Rechercher businesses, utilisateurs…"/>
    </div>
    <div class="sa-topbar-right">
      <button class="sa-lang-btn" id="sa-lang-btn" title="Changer de langue">FR</button>

      <!-- Notifications -->
      <div style="position:relative">
        <button class="sa-icon-btn" data-dropdown="notif-dropdown">
          <?= saIcon('bell') ?>
          <span class="sa-notif-dot"></span>
        </button>
        <div class="sa-dropdown sa-notif-panel" id="notif-dropdown">
          <div class="sa-notif-header">Notifications (<?= count($alerts) + $pendingBizReq ?>)</div>

          <?php if ($pendingBizReq > 0): ?>
          <div class="sa-notif-item">
            <div class="sa-notif-ico" style="background:#FFF7ED">📋</div>
            <div>
              <div class="sa-notif-txt"><?= $pendingBizReq ?> demande(s) business en attente</div>
              <div class="sa-notif-time">
                <a href="<?= $url ?>/SuperAdmin/business_requests.php"
                   style="color:#F97316;font-weight:700">Voir →</a>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php foreach(array_slice($alerts,0,4) as $a): ?>
          <div class="sa-notif-item">
            <div class="sa-notif-ico" style="background:<?= $a['type']==='expired'?'#FEF2F2':($a['type']==='warning'?'#FFFBEB':'#EFF6FF') ?>">
              <?= $a['type']==='expired'?saIcon('x-circle'):($a['type']==='warning'?saIcon('warning'):saIcon('credit-card')) ?>
            </div>
            <div>
              <div class="sa-notif-txt"><?= htmlspecialchars($a['msg']) ?></div>
              <div class="sa-notif-time"><?= htmlspecialchars($a['name']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if(empty($alerts) && $pendingBizReq === 0): ?>
          <div style="padding:16px;text-align:center;font-size:13px;color:#6B7280">Aucune alerte.</div>
          <?php endif; ?>
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
          <span class="sa-chev"><?= saIcon('chevron') ?></span>
        </button>
        <div class="sa-dropdown" id="profile-dropdown">
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/payment_settings.php"><?= saIcon('settings') ?> Paramètres</a>
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/payment_review.php"><?= saIcon('check') ?> Valider Paiements</a>
          <a class="sa-dropdown-item" href="<?= $url ?>/SuperAdmin/super_admin_reports.php"><?= saIcon('bar-chart') ?> Rapports</a>
          <div class="sa-dropdown-divider"></div>
          <a class="sa-dropdown-item danger" href="<?= $url ?>/Logininventory/logout.php"><?= saIcon('logout') ?> Déconnexion</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Content -->
  <main class="sa-content">

    <!-- New business credentials box (after manual creation) -->
    <?php if (isset($_GET['created']) && !empty($_SESSION['new_business_credentials'])):
        $creds = $_SESSION['new_business_credentials'];
        unset($_SESSION['new_business_credentials']);
    ?>
    <div style="background:#D1FAE5;border:2px solid #10B981;border-radius:12px;padding:20px 24px;margin:0 0 24px;">
      <h3 style="color:#065F46;margin:0 0 12px;font-size:16px">
        ✅ Business créé : <?= htmlspecialchars($creds['business_name']) ?>
      </h3>
      <p style="margin:8px 0;font-size:14px">
        <strong>Propriétaire :</strong> <?= htmlspecialchars($creds['owner_name']) ?>
      </p>
      <p style="margin:8px 0;font-size:14px">
        <strong>Identifiant connexion :</strong>
        <span style="background:#fff;padding:4px 12px;border-radius:6px;border:1px solid #10B981;font-size:15px">
          <?= htmlspecialchars($creds['owner_username']) ?>
        </span>
      </p>
      <p style="margin:8px 0;font-size:14px">
        <strong>PIN temporaire :</strong>
        <span style="background:#fff;padding:4px 12px;border-radius:6px;border:1px solid #10B981;font-size:20px;font-weight:bold;letter-spacing:4px">
          <?= htmlspecialchars($creds['temporary_pin']) ?>
        </span>
      </p>
      <p style="margin:14px 0 0;color:#065F46;font-size:13px">
        ⚠️ Copiez ces informations et transmettez-les au propriétaire. Le PIN ne sera plus affiché.
      </p>
    </div>
    <?php endif; ?>

    <!-- ══════════ PANEL: DASHBOARD ══════════ -->
    <div id="panel-dashboard" class="sa-panel">

      <!-- Pending business requests banner -->
      <?php if ($pendingBizReq > 0): ?>
      <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:12px">
          <span style="font-size:24px">📋</span>
          <div>
            <strong style="color:#92400E;font-size:15px">
              <?= $pendingBizReq ?> nouvelle(s) demande(s) de création de business en attente
            </strong>
            <p style="font-size:13px;color:#92400E;margin-top:3px">
              Un ou plusieurs clients attendent votre validation pour activer leur compte.
            </p>
          </div>
        </div>
        <a href="<?= $url ?>/SuperAdmin/business_requests.php"
           style="background:#F59E0B;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;white-space:nowrap">
          Voir les demandes →
        </a>
      </div>
      <?php endif; ?>

      <div class="sa-page-header">
        <div>
          <h1 class="sa-page-title">Super Admin Dashboard</h1>
          <p class="sa-page-sub">Gérez les businesses, abonnements, utilisateurs et l'activité de la plateforme.</p>
        </div>
        <div class="sa-page-actions">
          <?php if($stats['pending']>0): ?>
          <a class="sa-btn sa-btn-outline" href="<?= $url ?>/SuperAdmin/payment_review.php">
            <?= saIcon('clock') ?> <?= $stats['pending'] ?> paiement(s) en attente
          </a>
          <?php endif; ?>
          <a class="sa-btn sa-btn-outline" href="<?= $url ?>/SuperAdmin/payment_settings.php">
            <?= saIcon('credit-card') ?> Numéros Paiement
          </a>
          <a class="sa-btn sa-btn-primary" href="<?= $url ?>/LionTech_Add_Business_Page/add_business.php">
            <?= saIcon('plus') ?> Nouveau Business
          </a>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="sa-cards-grid">
        <div class="sa-stat-card navy">
          <div class="sa-stat-icon navy"><?= saIcon('building',22) ?></div>
          <div><div class="sa-stat-val"><?= $stats['total'] ?></div><div class="sa-stat-label">Total Businesses</div></div>
        </div>
        <div class="sa-stat-card green">
          <div class="sa-stat-icon green"><?= saIcon('check',22) ?></div>
          <div><div class="sa-stat-val"><?= $stats['active'] ?></div><div class="sa-stat-label">Actifs</div></div>
        </div>
        <div class="sa-stat-card red">
          <div class="sa-stat-icon red"><?= saIcon('warning',22) ?></div>
          <div><div class="sa-stat-val"><?= $stats['expired'] ?></div><div class="sa-stat-label">Abonnements Expirés</div></div>
        </div>
        <div class="sa-stat-card teal">
          <div class="sa-stat-icon teal"><?= saIcon('users',22) ?></div>
          <div><div class="sa-stat-val"><?= $stats['users'] ?></div><div class="sa-stat-label">Utilisateurs</div></div>
        </div>
        <div class="sa-stat-card amber">
          <div class="sa-stat-icon amber"><?= saIcon('credit-card',22) ?></div>
          <div>
            <div class="sa-stat-val"><?= $stats['pending'] ?></div>
            <div class="sa-stat-label">Paiements en Attente</div>
            <?php if($stats['pending']>0): ?>
            <a href="<?= $url ?>/SuperAdmin/payment_review.php"
               style="font-size:11px;color:#D4A017;font-weight:700;text-decoration:none">→ Valider</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="sa-stat-card blue">
          <div class="sa-stat-icon blue"><?= saIcon('list',22) ?></div>
          <div><div class="sa-stat-val"><?= $stats['activity'] ?></div><div class="sa-stat-label">Activité Aujourd'hui</div></div>
        </div>
      </div>

      <!-- Chart + Alerts -->
      <div class="sa-row">
        <div class="sa-card">
          <div class="sa-card-header">
            <div><div class="sa-card-title">Tendances Mensuelles</div><div class="sa-card-sub">Revenus (XAF) &amp; Abonnements actifs</div></div>
            <a class="sa-btn sa-btn-sm sa-btn-outline" href="<?= $url ?>/SuperAdmin/super_admin_reports.php">Voir Rapports</a>
          </div>
          <div class="sa-chart-wrap"><canvas id="sa-chart"></canvas></div>
          <div class="sa-chart-legend">
            <div class="sa-legend-item"><div class="sa-legend-dot" style="background:#D4A017"></div><span>Revenus (XAF)</span></div>
            <div class="sa-legend-item"><div class="sa-legend-dot" style="background:#1A9E7A"></div><span>Abonnements actifs</span></div>
          </div>
        </div>

        <div class="sa-card">
          <div class="sa-card-header">
            <div><div class="sa-card-title">Alertes Abonnements</div><div class="sa-card-sub">Businesses nécessitant attention</div></div>
            <span style="background:#FEF2F2;color:#DC2626;font-size:11px;font-weight:700;border-radius:50px;padding:3px 10px">
              <?= count($alerts) ?> alertes
            </span>
          </div>
          <div class="sa-alerts-list">
            <?php foreach($alerts as $a): ?>
            <div class="sa-alert-item <?= htmlspecialchars($a['type']) ?>">
              <span class="sa-alert-icon">
                <?= $a['type']==='expired'?saIcon('x-circle'):($a['type']==='warning'?saIcon('warning'):saIcon('credit-card')) ?>
              </span>
              <div style="flex:1;min-width:0">
                <div class="sa-alert-biz"><?= htmlspecialchars($a['name']) ?></div>
                <div class="sa-alert-msg"><?= htmlspecialchars($a['msg']) ?></div>
                <div class="sa-alert-date"><?= htmlspecialchars($a['date']) ?></div>
              </div>
              <button class="sa-alert-renew"
                      data-id="<?= $a['id'] ?>"
                      data-name="<?= htmlspecialchars($a['name']) ?>">Renouveler</button>
            </div>
            <?php endforeach; ?>
            <?php if(empty($alerts)): ?>
            <div style="padding:28px;text-align:center;color:#6B7280;font-size:13.5px">
              <?= saIcon('check',16) ?> Aucune alerte d'abonnement.
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="sa-card">
        <div class="sa-card-header">
          <div><div class="sa-card-title">Activité Récente</div><div class="sa-card-sub">Derniers événements de la plateforme</div></div>
        </div>
        <div class="sa-activity-list">
          <?php foreach($activity as $act): ?>
          <div class="sa-activity-item">
            <div class="sa-activity-icon sa-act-navy"><?= saIcon('info',15) ?></div>
            <div style="flex:1;min-width:0">
              <div class="sa-activity-desc"><?= htmlspecialchars($act['desc']) ?></div>
              <?php if($act['biz']): ?>
              <div class="sa-activity-biz"><?= htmlspecialchars($act['biz']) ?></div>
              <?php endif; ?>
              <div class="sa-activity-time"><?= htmlspecialchars($act['time']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($activity)): ?>
          <div style="padding:28px;text-align:center;color:#6B7280;font-size:13.5px">
            Aucune activité enregistrée aujourd'hui.
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /#panel-dashboard -->

    <!-- ══════════ PANEL: BUSINESSES ══════════ -->
    <div id="panel-businesses" class="sa-panel" style="display:none">

      <div class="sa-page-header">
        <div>
          <h1 class="sa-page-title">Gestion des Businesses</h1>
          <p class="sa-page-sub" id="biz-table-count">Affichage de <?= count($businesses) ?> business(es)</p>
        </div>
        <div class="sa-page-actions">
          <a class="sa-btn sa-btn-primary" href="<?= $url ?>/LionTech_Add_Business_Page/add_business.php">
            <?= saIcon('plus') ?> Nouveau Business
          </a>
        </div>
      </div>

      <div class="sa-card sa-table-card">
        <div class="sa-table-controls">
          <div class="sa-search-wrap">
            <span class="sa-search-ico"><?= saIcon('search') ?></span>
            <input type="search" class="sa-table-search" id="biz-search"
                   placeholder="Rechercher par nom, type, propriétaire, ville…"/>
          </div>
          <select class="sa-table-filter" id="biz-filter">
            <option value="all">Tous statuts</option>
            <option value="active">Actif</option>
            <option value="expired">Expiré</option>
            <option value="trial">Essai</option>
            <option value="suspended">Suspendu</option>
          </select>
        </div>
        <div class="sa-table-wrap">
          <table class="sa-table" id="businesses-table">
            <thead><tr>
              <th>Nom</th><th>Type</th><th>Propriétaire</th>
              <th>Téléphone</th><th>Ville</th><th>Statut</th>
              <th>Créé le</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($businesses as $b): ?>
            <tr data-id="<?= $b['id'] ?>"
                data-name="<?= htmlspecialchars($b['name']) ?>"
                data-type="<?= htmlspecialchars($b['type']??'') ?>"
                data-owner="<?= htmlspecialchars($b['owner']??'') ?>"
                data-phone="<?= htmlspecialchars($b['phone']??'') ?>"
                data-city="<?= htmlspecialchars($b['city']??'') ?>"
                data-status="<?= htmlspecialchars($b['status']) ?>"
                data-date="<?= htmlspecialchars($b['created']) ?>"
                data-owner-username="<?= htmlspecialchars($b['owner_username']??'') ?>">
              <td><?= htmlspecialchars($b['name']) ?></td>
              <td><?= htmlspecialchars($b['type']??'—') ?></td>
              <td><?= htmlspecialchars($b['owner']??'—') ?></td>
              <td><?= htmlspecialchars($b['phone']??'—') ?></td>
              <td><?= htmlspecialchars($b['city']??'—') ?></td>
              <td><span class="sa-badge sa-badge-<?= htmlspecialchars($b['status']) ?>"><?= ucfirst(htmlspecialchars($b['status'])) ?></span></td>
              <td><?= htmlspecialchars(date('d/m/Y', strtotime($b['created']))) ?></td>
              <td>
                <div class="sa-tbl-actions">
                  <button class="sa-tbl-btn sa-tbl-btn-view" onclick="viewBusiness(
                    <?= $b['id'] ?>,'<?= addslashes($b['name']) ?>',
                    '<?= addslashes($b['type']??'') ?>','<?= addslashes($b['owner']??'') ?>',
                    '<?= addslashes($b['phone']??'') ?>','<?= addslashes($b['city']??'') ?>',
                    '<?= $b['status'] ?>','<?= $b['created'] ?>',
                    '<?= addslashes($b['owner_username']??'') ?>',''
                  )">Voir</button>
                  <button class="sa-tbl-btn sa-tbl-btn-edit" onclick="editBusiness(
                    <?= $b['id'] ?>,'<?= addslashes($b['name']) ?>',
                    '<?= addslashes($b['type']??'') ?>','<?= addslashes($b['owner']??'') ?>',
                    '<?= addslashes($b['phone']??'') ?>','<?= addslashes($b['city']??'') ?>'
                  )">Modifier</button>
                  <button class="sa-tbl-btn sa-tbl-btn-disable"
                          onclick="confirmDisable(<?= $b['id'] ?>,'<?= addslashes($b['name']) ?>')">
                    Désactiver
                  </button>
                  <?php if(in_array($b['status'],['expired','trial','suspended'],true)): ?>
                  <button class="sa-tbl-btn sa-tbl-btn-renew"
                          onclick="renewSubscription(<?= $b['id'] ?>,'<?= addslashes($b['name']) ?>')">
                    Renouveler
                  </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($businesses)): ?>
            <tr><td colspan="8" style="text-align:center;padding:28px;color:#6B7280">
              Aucun business.
              <a href="<?= $url ?>/LionTech_Add_Business_Page/add_business.php">Ajouter le premier →</a>
            </td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /#panel-businesses -->

    <!-- ══════════ PANEL: USERS ══════════ -->
    <div id="panel-users" class="sa-panel" style="display:none">

      <div class="sa-page-header">
        <div>
          <h1 class="sa-page-title">Propriétaires & Managers</h1>
          <p class="sa-page-sub" id="users-table-count"><?= count($allUsers) ?> utilisateur(s) au total</p>
        </div>
      </div>

      <div class="sa-card sa-table-card">
        <div class="sa-table-controls">
          <div class="sa-search-wrap">
            <span class="sa-search-ico"><?= saIcon('search') ?></span>
            <input type="search" class="sa-table-search" id="users-search"
                   placeholder="Rechercher par nom, identifiant, email…"/>
          </div>
          <select class="sa-table-filter" id="users-role-filter">
            <option value="all">Tous rôles</option>
            <option value="business_owner">Propriétaire</option>
            <option value="manager">Gérant</option>
           
          </select>
          <select class="sa-table-filter" id="users-status-filter">
            <option value="all">Tous statuts</option>
            <option value="active">Actif</option>
            <option value="inactive">Inactif</option>
            <option value="suspended">Suspendu</option>
          </select>
        </div>
        <div class="sa-table-wrap">
          <table class="sa-table" id="users-table">
            <thead><tr>
              <th>Nom</th><th>Identifiant</th><th>Email</th>
              <th>Rôle</th><th>Business</th><th>Statut</th><th>Créé le</th>
            </tr></thead>
            <tbody>
            <?php foreach($allUsers as $u): ?>
            <tr data-name="<?= htmlspecialchars(strtolower($u['full_name'])) ?>"
                data-login="<?= htmlspecialchars(strtolower($u['login_id'])) ?>"
                data-email="<?= htmlspecialchars(strtolower($u['email']??'')) ?>"
                data-role="<?= htmlspecialchars($u['role']) ?>"
                data-status="<?= htmlspecialchars($u['status']) ?>">
              <td>
                <div style="display:flex;align-items:center;gap:9px">
                  <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0B1F3A,#1A9E7A);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0">
                    <?= strtoupper(substr($u['full_name'],0,1)) ?>
                  </div>
                  <span><?= htmlspecialchars($u['full_name']) ?></span>
                </div>
              </td>
              <td style="font-family:monospace;font-size:12.5px"><?= htmlspecialchars($u['login_id']) ?></td>
              <td style="color:#6B7280;font-size:12.5px"><?= htmlspecialchars($u['email']??'—') ?></td>
              <td><?php
                $roleMap   = ['business_owner'=>'Propriétaire','manager'=>'Gérant','employee'=>'Employé'];
                $roleClass = ['business_owner'=>'navy','manager'=>'teal','employee'=>'blue'];
                $r = $u['role'];
              ?><span class="sa-badge sa-badge-<?= $roleClass[$r]??'blue' ?>"><?= $roleMap[$r]??$r ?></span></td>
              <td style="font-size:12.5px"><?= htmlspecialchars($u['business_name']??'—') ?></td>
              <td><span class="sa-badge sa-badge-<?= $u['status']==='active'?'active':'disabled' ?>"><?= $u['status']==='active'?'Actif':'Inactif' ?></span></td>
              <td style="font-size:12px;color:#94A3B8"><?= date('d/m/Y',strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($allUsers)): ?>
            <tr><td colspan="7" style="text-align:center;padding:28px;color:#6B7280">Aucun utilisateur trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /#panel-users -->

    <!-- ══════════ PANEL: SUBSCRIPTIONS ══════════ -->
    <div id="panel-subscriptions" class="sa-panel" style="display:none">

      <div class="sa-page-header">
        <div>
          <h1 class="sa-page-title">Gestion des Abonnements</h1>
          <p class="sa-page-sub"><?= count($subscriptions) ?> abonnement(s) au total · <?= $stats['expired'] ?> expiré(s)</p>
        </div>
      </div>

      <div class="sa-cards-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:20px">
        <div class="sa-stat-card green">
          <div class="sa-stat-icon green"><?= saIcon('check',20) ?></div>
          <div><div class="sa-stat-val"><?= $stats['active'] ?></div><div class="sa-stat-label">Actifs</div></div>
        </div>
        <div class="sa-stat-card red">
          <div class="sa-stat-icon red"><?= saIcon('x-circle',20) ?></div>
          <div><div class="sa-stat-val"><?= $stats['expired'] ?></div><div class="sa-stat-label">Expirés</div></div>
        </div>
        <div class="sa-stat-card blue">
          <div class="sa-stat-icon blue"><?= saIcon('clock',20) ?></div>
          <div>
            <div class="sa-stat-val">
              <?= (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE subscription_status='trial'")->fetchColumn() ?>
            </div>
            <div class="sa-stat-label">Essai</div>
          </div>
        </div>
        <div class="sa-stat-card amber">
          <div class="sa-stat-icon amber"><?= saIcon('credit-card',20) ?></div>
          <div><div class="sa-stat-val"><?= $stats['pending'] ?></div><div class="sa-stat-label">En attente</div></div>
        </div>
      </div>

      <div class="sa-card sa-table-card">
        <div class="sa-table-controls">
          <div class="sa-search-wrap">
            <span class="sa-search-ico"><?= saIcon('search') ?></span>
            <input type="search" class="sa-table-search" id="subs-search" placeholder="Rechercher business, plan…"/>
          </div>
          <select class="sa-table-filter" id="subs-filter">
            <option value="all">Tous statuts</option>
            <option value="active">Actif</option>
            <option value="expired">Expiré</option>
            <option value="trial">Essai</option>
          </select>
        </div>
        <div class="sa-table-wrap">
          <table class="sa-table" id="subs-table">
            <thead><tr>
              <th>Business</th><th>Propriétaire</th><th>Plan</th>
              <th>Montant</th><th>Début</th><th>Fin</th><th>Statut</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($subscriptions as $s): ?>
            <tr data-biz="<?= htmlspecialchars(strtolower($s['business_name'])) ?>"
                data-owner="<?= htmlspecialchars(strtolower($s['owner_name']??'')) ?>"
                data-plan="<?= htmlspecialchars(strtolower($s['plan_name']??'')) ?>"
                data-status="<?= htmlspecialchars($s['status']??$s['biz_status']??'') ?>">
              <td style="font-weight:600"><?= htmlspecialchars($s['business_name']) ?></td>
              <td style="color:#6B7280;font-size:12.5px"><?= htmlspecialchars($s['owner_name']??'—') ?></td>
              <td><?= htmlspecialchars($s['plan_name']??'Standard') ?></td>
              <td><?= number_format((float)($s['amount']??10000),0) ?> <?= htmlspecialchars($s['currency']??'XAF') ?></td>
              <td style="font-size:12.5px;color:#6B7280"><?= $s['start_date'] ? date('d/m/Y',strtotime($s['start_date'])) : '—' ?></td>
              <td style="font-size:12.5px"><?= $s['end_date'] ? date('d/m/Y',strtotime($s['end_date'])) : '—' ?></td>
              <td><?php
                $st = $s['status']??$s['biz_status']??'trial';
                $stClass = ['active'=>'active','expired'=>'expired','trial'=>'trial','cancelled'=>'disabled','suspended'=>'disabled'];
                $stLabel = ['active'=>'Actif','expired'=>'Expiré','trial'=>'Essai','cancelled'=>'Annulé','suspended'=>'Suspendu'];
              ?><span class="sa-badge sa-badge-<?= $stClass[$st]??'disabled' ?>"><?= $stLabel[$st]??$st ?></span></td>
              <td>
                <?php if(in_array($st,['expired','trial','suspended'],true)): ?>
                <button class="sa-tbl-btn sa-tbl-btn-renew"
                        onclick="renewSubscription('','<?= addslashes($s['business_name']) ?>')">
                  Renouveler
                </button>
                <?php else: ?>
                <span style="font-size:12px;color:#94A3B8">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($subscriptions)): ?>
            <tr><td colspan="8" style="text-align:center;padding:28px;color:#6B7280">Aucun abonnement trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /#panel-subscriptions -->

  </main>
</div><!-- /.sa-main -->
</div><!-- /.sa-layout -->

<!-- ══ MODALS ══ -->

<!-- View Business -->
<div class="sa-modal-overlay" id="view-business-modal">
  <div class="sa-modal">
    <div class="sa-modal-header">
      <h2 class="sa-modal-title">Détails du Business</h2>
      <button class="sa-modal-close" data-close-modal><?= saIcon('close') ?></button>
    </div>
    <div class="sa-modal-body">
      <div class="sa-detail-grid">
        <div class="sa-detail-item"><div class="sa-detail-label">Nom</div><div class="sa-detail-val" id="view-biz-name">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Type</div><div class="sa-detail-val" id="view-biz-type">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Propriétaire</div><div class="sa-detail-val" id="view-biz-owner">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Identifiant</div><div class="sa-detail-val" id="view-owner-username">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Téléphone</div><div class="sa-detail-val" id="view-biz-phone">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Ville</div><div class="sa-detail-val" id="view-biz-city">—</div></div>
        <div class="sa-detail-item"><div class="sa-detail-label">Date de création</div><div class="sa-detail-val" id="view-biz-date">—</div></div>
        <div class="sa-detail-item" style="grid-column:1/-1">
          <div class="sa-detail-label">Statut</div>
          <div class="sa-detail-val"><span class="sa-badge" id="view-biz-status">—</span></div>
        </div>
      </div>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Fermer</button>
    </div>
  </div>
</div>

<!-- Edit Business -->
<div class="sa-modal-overlay" id="edit-business-modal">
  <div class="sa-modal sa-modal-lg">
    <div class="sa-modal-header">
      <h2 class="sa-modal-title">Modifier Business</h2>
      <button class="sa-modal-close" data-close-modal><?= saIcon('close') ?></button>
    </div>
    <div class="sa-modal-body">
      <input type="hidden" id="edit-biz-id"/>
      <div class="sa-form-grid">
        <div class="sa-form-group">
          <label class="sa-form-label">Nom *</label>
          <input type="text" class="sa-form-input" id="edit-biz-name"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Type *</label>
          <select class="sa-form-select" id="edit-biz-type">
            <option>Restaurant</option><option>Salon</option><option>Boutique</option>
            <option>Snack Bar</option><option>Commerce</option><option>Mode</option><option>Autre</option>
          </select>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Propriétaire</label>
          <input type="text" class="sa-form-input" id="edit-biz-owner"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Téléphone</label>
          <input type="tel" class="sa-form-input" id="edit-biz-phone"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Ville</label>
          <input type="text" class="sa-form-input" id="edit-biz-city"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Statut</label>
          <select class="sa-form-select" id="edit-biz-status-sel">
            <option value="active">Actif</option>
            <option value="expired">Expiré</option>
            <option value="trial">Essai</option>
            <option value="suspended">Suspendu</option>
          </select>
        </div>
      </div>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Annuler</button>
      <button class="sa-btn sa-btn-primary" onclick="saveEdit()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- Disable Confirm -->
<div class="sa-modal-overlay" id="disable-modal">
  <div class="sa-modal" style="max-width:420px">
    <div class="sa-modal-header">
      <h2 class="sa-modal-title">Désactiver Business</h2>
      <button class="sa-modal-close" data-close-modal><?= saIcon('close') ?></button>
    </div>
    <div class="sa-modal-body">
      <div class="sa-confirm-icon"><?= saIcon('x-circle',48) ?></div>
      <p class="sa-confirm-msg" id="disable-confirm-msg">Êtes-vous sûr ?</p>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Annuler</button>
      <button class="sa-btn sa-btn-danger" id="disable-confirm-btn">Oui, Désactiver</button>
    </div>
  </div>
</div>

<!-- Renew Subscription -->
<div class="sa-modal-overlay" id="renew-modal">
  <div class="sa-modal">
    <div class="sa-modal-header">
      <h2 class="sa-modal-title">Renouveler Abonnement</h2>
      <button class="sa-modal-close" data-close-modal><?= saIcon('close') ?></button>
    </div>
    <div class="sa-modal-body">
      <input type="hidden" id="renew-biz-id"/>
      <p style="font-size:13.5px;color:#64748B;margin-bottom:16px">
        Business : <strong id="renew-biz-name" style="color:#0B1F3A">—</strong>
      </p>
      <div class="sa-form-grid">
        <div class="sa-form-group">
          <label class="sa-form-label">Plan</label>
          <select class="sa-form-select" id="renew-plan">
            <option value="standard">Standard — 10 000 XAF/mois</option>
            <option value="premium">Premium — 20 000 XAF/mois</option>
          </select>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Montant (XAF)</label>
          <input type="number" class="sa-form-input" id="renew-amount" value="10000" min="0"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Date début</label>
          <input type="date" class="sa-form-input" id="renew-start"/>
        </div>
        <div class="sa-form-group">
          <label class="sa-form-label">Date fin</label>
          <input type="date" class="sa-form-input" id="renew-end"/>
        </div>
        <div class="sa-form-group full">
          <label class="sa-form-label">Notes</label>
          <textarea class="sa-form-textarea" id="renew-notes" placeholder="ex: Payé via MTN MoMo"></textarea>
        </div>
      </div>
    </div>
    <div class="sa-modal-footer">
      <button class="sa-btn sa-btn-outline" data-close-modal>Annuler</button>
      <button class="sa-btn sa-btn-teal" onclick="saveRenewal()">
        <?= saIcon('check') ?> Confirmer Renouvellement
      </button>
    </div>
  </div>
</div>

<div class="sa-toast" id="sa-toast"></div>
<script src="super_admin.js"></script>
</body>
</html>