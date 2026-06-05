<?php
/* ============================================================
   Sidebar.php — LionTech Business Manager
   Role-based navigation. Shared by all owner/manager/employee pages.
   ============================================================ */

$currentPage     = basename($_SERVER['PHP_SELF']);
$sidebarRole     = $_SESSION['role'] ?? '';
$isOwner         = ($sidebarRole === 'business_owner');
$isManager       = ($sidebarRole === 'manager');
$isEmployee      = ($sidebarRole === 'employee');
$isOwnerOrMgr    = ($isOwner || $isManager);
$url             = defined('APP_URL') ? APP_URL : '';

$L = [
    'employee_dash' => $url . '/LionTech_Employee_Dashboard/liontech_employee_dashboard/employee_dashboard.php',
    'owner_dash'    => $url . '/LionTech_Owner_Dashboard/liontech_owner_dashboard/owner_dashboard.php',
    'products'      => $url . '/Produit/products.php',
    'stock_in'      => $url . '/LionTech_Stock_In_Page/liontech_stock_in_page/stock_in.php',
    'stock_out'     => $url . '/stockout_stockfinis/stock_out.php',
    'attendance'    => $url . '/Attendance_presenceemployer/clock_attendance.php',
    'notifications' => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/notifications.php',
    'change_pin'    => $url . '/change_pin.php',
    'logout'        => $url . '/Logininventory/logout.php',
    'employees'     => $url . '/LionTech_Employee_Management/liontech_employee_management/employees.php',
    'validations'   => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/approval_center.php',
    'reports'       => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/reports.php',
    'activity'      => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/activity_logs.php',
    'subscription'  => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/subscription_billing.php',
    'settings'      => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/settings.php',
];

$sidebarUser     = function_exists('currentUser') ? currentUser() : [];
$sidebarFullName = $sidebarUser['full_name'] ?? 'User';
$sidebarInitials = '';
foreach (explode(' ', trim($sidebarFullName)) as $w)
    $sidebarInitials .= strtoupper(substr($w, 0, 1));
$sidebarInitials = substr($sidebarInitials ?: 'U', 0, 2);
$sidebarBizName  = $business['business_name'] ?? $sidebarUser['business_name'] ?? ucfirst(str_replace('_', ' ', $sidebarRole));

function sbActive(string $page, string $current): string {
    return $current === $page ? ' active' : '';
}

/* SVG icons helper */
function sbIcon(string $name): string {
    $icons = [
        'home'         => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'package'      => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'arrow-down'   => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg>',
        'arrow-up'     => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg>',
        'clock'        => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'bell'         => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'users'        => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'check'        => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'bar-chart'    => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'file-text'    => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'credit-card'  => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'settings'     => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'lock'         => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'log-out'      => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>

<link rel="stylesheet" href="<?= $url ?>/LionTech_Owner_Dashboard/liontech_owner_dashboard/owner_dashboard.css"/>

<div id="od-overlay" style="display:none;position:fixed;inset:0;z-index:29;background:rgba(11,31,58,.52);backdrop-filter:blur(3px)"></div>

<aside class="od-sidebar" id="od-sidebar">

  <div class="od-sidebar-header">
    <div class="od-logo">
      <img src="<?= $url ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:46px;height:46px;border-radius:50%;object-fit:cover;">
      <div>
        <div class="od-logo-name">LionTech</div>
        <div class="od-logo-tag">Business Manager</div>
      </div>
    </div>
    <button class="od-sidebar-close" id="od-sidebar-close" aria-label="Close">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>

  <nav class="od-nav">

    <?php if ($isEmployee): ?>
    <a class="od-nav-link<?= sbActive('employee_dashboard.php', $currentPage) ?>" href="<?= $L['employee_dash'] ?>">
      <?= sbIcon('home') ?><span data-i18n="nav_dashboard">Dashboard</span>
    </a>
    <?php else: ?>
    <a class="od-nav-link<?= sbActive('owner_dashboard.php', $currentPage) ?>" href="<?= $L['owner_dash'] ?>">
      <?= sbIcon('home') ?><span data-i18n="nav_dashboard">Dashboard</span>
    </a>
    <?php endif; ?>

    <a class="od-nav-link<?= sbActive('products.php', $currentPage) ?>" href="<?= $L['products'] ?>">
      <?= sbIcon('package') ?><span data-i18n="nav_products">Produits<?= $isEmployee ? ' (lecture)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('stock_in.php', $currentPage) ?>" href="<?= $L['stock_in'] ?>">
      <?= sbIcon('arrow-down') ?><span data-i18n="nav_stock_in">Stock entrant<?= $isEmployee ? ' (demande)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('stock_out.php', $currentPage) ?>" href="<?= $L['stock_out'] ?>">
      <?= sbIcon('arrow-up') ?><span data-i18n="nav_stock_out">Stock sortant<?= $isEmployee ? ' (demande)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('clock_attendance.php', $currentPage) ?>" href="<?= $L['attendance'] ?>">
      <?= sbIcon('clock') ?><span data-i18n="nav_attendance">Présence</span>
    </a>

    <a class="od-nav-link<?= sbActive('notifications.php', $currentPage) ?>" href="<?= $L['notifications'] ?>">
      <?= sbIcon('bell') ?><span data-i18n="nav_notifications">Notifications</span>
    </a>

    <?php if ($isOwnerOrMgr): ?>
    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('employees.php', $currentPage) ?>" href="<?= $L['employees'] ?>">
      <?= sbIcon('users') ?><span data-i18n="nav_employees">Employés</span>
    </a>

    <a class="od-nav-link<?= sbActive('approval_center.php', $currentPage) ?>" href="<?= $L['validations'] ?>">
      <?= sbIcon('check') ?><span data-i18n="nav_validations">Validations</span>
    </a>

    <a class="od-nav-link<?= sbActive('reports.php', $currentPage) ?>" href="<?= $L['reports'] ?>">
      <?= sbIcon('bar-chart') ?><span data-i18n="nav_reports">Rapports<?= $isManager ? ' (lecture)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('activity_logs.php', $currentPage) ?>" href="<?= $L['activity'] ?>">
      <?= sbIcon('file-text') ?><span data-i18n="nav_activity">Activité<?= $isManager ? ' (lecture)' : '' ?></span>
    </a>
    <?php endif; ?>

    <?php if ($isOwner): ?>
    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('subscription_billing.php', $currentPage) ?>" href="<?= $L['subscription'] ?>">
      <?= sbIcon('credit-card') ?><span data-i18n="nav_subscription">Abonnement</span>
    </a>

    <a class="od-nav-link<?= sbActive('settings.php', $currentPage) ?>" href="<?= $L['settings'] ?>">
      <?= sbIcon('settings') ?><span data-i18n="nav_settings">Paramètres</span>
    </a>
    <?php endif; ?>

    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('change_pin.php', $currentPage) ?>" href="<?= $L['change_pin'] ?>">
      <?= sbIcon('lock') ?><span data-i18n="nav_change_pin">Changer PIN</span>
    </a>

    <a class="od-nav-link logout" href="<?= $L['logout'] ?>">
      <?= sbIcon('log-out') ?><span data-i18n="nav_logout">Déconnexion</span>
    </a>

  </nav>

  <div class="od-sidebar-footer">
    <div class="od-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
    <div>
      <div class="od-sidebar-name"><?= htmlspecialchars($sidebarFullName) ?></div>
      <div class="od-sidebar-role"><?= htmlspecialchars($sidebarBizName) ?></div>
    </div>
  </div>

</aside>

<script>
(function () {
  if (window._sidebarBound) return;
  window._sidebarBound = true;

  var sidebar  = document.getElementById('od-sidebar');
  var overlay  = document.getElementById('od-overlay');
  var menuBtn  = document.getElementById('od-menu-btn');
  var closeBtn = document.getElementById('od-sidebar-close');

  function openSidebar() {
    sidebar  && sidebar.classList.add('open');
    overlay  && (overlay.style.display = 'block');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar  && sidebar.classList.remove('open');
    overlay  && (overlay.style.display = 'none');
    document.body.style.overflow = '';
  }

  menuBtn  && menuBtn.addEventListener('click', openSidebar);
  closeBtn && closeBtn.addEventListener('click', closeSidebar);
  overlay  && overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });

  /* Apply translations from localStorage */
  var lang = localStorage.getItem('lt_lang') || 'fr';
  if (lang === 'en' && window.LT_SIDEBAR_LANG_EN) {
    document.querySelectorAll('[data-i18n]').forEach(function(el) {
      var k = el.dataset.i18n;
      if (window.LT_SIDEBAR_LANG_EN[k]) el.textContent = window.LT_SIDEBAR_LANG_EN[k];
    });
  }
})();
</script>

<script>
window.LT_SIDEBAR_LANG_EN = {
  nav_dashboard:   'Dashboard',
  nav_products:    'Products',
  nav_stock_in:    'Stock In',
  nav_stock_out:   'Stock Out',
  nav_attendance:  'Attendance',
  nav_notifications: 'Notifications',
  nav_employees:   'Employees',
  nav_validations: 'Approvals',
  nav_reports:     'Reports',
  nav_activity:    'Activity',
  nav_subscription:'Subscription',
  nav_settings:    'Settings',
  nav_change_pin:  'Change PIN',
  nav_logout:      'Logout',
};
</script>
