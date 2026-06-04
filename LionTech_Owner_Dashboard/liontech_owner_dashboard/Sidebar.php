<?php
/* ============================================================
   Sidebar.php — LionTech Business Manager
   Role-based navigation:
     business_owner → full access
     manager        → no Abonnement, no Paramètres, view-only flags passed
     employee        → minimal menu, own dashboard
   ============================================================ */

$currentPage  = basename($_SERVER['PHP_SELF']);
$sidebarRole  = $_SESSION['role'] ?? '';

$isOwner    = ($sidebarRole === 'business_owner');
$isManager  = ($sidebarRole === 'manager');
$isEmployee = ($sidebarRole === 'employee');
$isOwnerOrManager = ($isOwner || $isManager);

$url = defined('APP_URL') ? APP_URL : '';

/* ── All URLs ── */
$L = [
    /* shared */
    'employee_dash' => $url . '/LionTech_Employee_Dashboard/liontech_employee_dashboard/employee_dashboard.php',
    'owner_dash'    => $url . '/LionTech_Owner_Dashboard/liontech_owner_dashboard/owner_dashboard.php',
    'products'      => $url . '/Produit/products.php',
    'stock_in'      => $url . '/LionTech_Stock_In_Page/liontech_stock_in_page/stock_in.php',
    'stock_out'     => $url . '/stockout_stockfinis/stock_out.php',
    'attendance' => $url . '/Attendance_presenceemployer/clock_attendance.php',
    'notifications' => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/notifications.php',
    'change_pin'    => $url . '/change_pin.php',
    'logout'        => $url . '/Logininventory/logout.php',
    /* owner + manager */
    'employees'     => $url . '/LionTech_Employee_Management/liontech_employee_management/employees.php',
    'validations'   => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/approval_center.php',
    'reports'       => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/reports.php',
    'activity'      => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/activity_logs.php',
    /* owner only */
    'subscription'  => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/subscription_billing.php',
    'settings'      => $url . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/settings.php',
];

/* ── User info ── */
$sidebarUser     = function_exists('currentUser') ? currentUser() : [];
$sidebarFullName = $sidebarUser['full_name'] ?? 'User';
$sidebarInitials = '';
foreach (explode(' ', trim($sidebarFullName)) as $w)
    $sidebarInitials .= strtoupper(substr($w, 0, 1));
$sidebarInitials = substr($sidebarInitials ?: 'U', 0, 2);
$sidebarBizName  = $business['business_name']
                ?? $sidebarUser['business_name']
                ?? ucfirst(str_replace('_', ' ', $sidebarRole));

function sbActive(string $page, string $current): string {
    return $current === $page ? ' active' : '';
}
?>

<!-- ① CSS injected once — works on every page -->
<link rel="stylesheet" href="/InventoryLiontech/LionTech_Owner_Dashboard/liontech_owner_dashboard/owner_dashboard.css"/>

<!-- ② Hamburger (mobile) -->
<button id="od-menu-btn" class="od-menu-btn" aria-label="Open menu">☰</button>

<!-- ③ Overlay -->
<div id="od-overlay" style="display:none;position:fixed;inset:0;z-index:29;
  background:rgba(11,31,58,.52);backdrop-filter:blur(3px)"></div>

<!-- ④ Sidebar -->
<aside class="od-sidebar" id="od-sidebar">

  <div class="od-sidebar-header">
    <div class="od-logo">
     <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
      <div>
        <div class="od-logo-name">LionTech</div>
        <div class="od-logo-tag">Business Manager</div>
      </div>
    </div>
    <button class="od-sidebar-close" id="od-sidebar-close" aria-label="Close">✕</button>
  </div>

  <nav class="od-nav">

    <!-- ════ DASHBOARD ════ -->
    <?php if ($isEmployee): ?>
    <a class="od-nav-link<?= sbActive('employee_dashboard.php', $currentPage) ?>"
       href="<?= $L['employee_dash'] ?>">
      <span>🏠</span><span>Dashboard</span>
    </a>
    <?php else: ?>
    <a class="od-nav-link<?= sbActive('owner_dashboard.php', $currentPage) ?>"
       href="<?= $L['owner_dash'] ?>">
      <span>🏠</span><span>Dashboard</span>
    </a>
    <?php endif; ?>

    <!-- ════ STOCK / PRODUCTS — all roles ════ -->
    <a class="od-nav-link<?= sbActive('products.php', $currentPage) ?>"
       href="<?= $L['products'] ?>">
      <span>📦</span>
      <span>Produits<?= $isEmployee ? ' (lecture)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('stock_in.php', $currentPage) ?>"
       href="<?= $L['stock_in'] ?>">
      <span>📥</span>
      <span>Stock entrant<?= $isEmployee ? ' (demande)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('stock_out.php', $currentPage) ?>"
       href="<?= $L['stock_out'] ?>">
      <span>📤</span>
      <span>Stock sortant<?= $isEmployee ? ' (demande)' : '' ?></span>
    </a>

    <!-- ════ ATTENDANCE — all roles ════ -->
    <a class="od-nav-link<?= sbActive('clock_attendance.php', $currentPage) ?>"
       href="<?= $L['attendance'] ?>">
      <span>⏱️</span><span>Présence</span>
    </a>

    <!-- ════ NOTIFICATIONS — all roles ════ -->
    <a class="od-nav-link<?= sbActive('notifications.php', $currentPage) ?>"
       href="<?= $L['notifications'] ?>">
      <span>🔔</span><span>Notifications</span>
    </a>

    <?php if ($isOwnerOrManager): ?>
    <!-- ════ OWNER + MANAGER ONLY ════ -->
    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('employees.php', $currentPage) ?>"
       href="<?= $L['employees'] ?>">
      <span>👥</span><span>Employés</span>
    </a>

    <a class="od-nav-link<?= sbActive('approval_center.php', $currentPage) ?>"
       href="<?= $L['validations'] ?>">
      <span>✅</span><span>Validations</span>
    </a>

    <a class="od-nav-link<?= sbActive('reports.php', $currentPage) ?>"
       href="<?= $L['reports'] ?>">
      <span>📊</span>
      <span>Rapports<?= $isManager ? ' (lecture)' : '' ?></span>
    </a>

    <a class="od-nav-link<?= sbActive('activity_logs.php', $currentPage) ?>"
       href="<?= $L['activity'] ?>">
      <span>🧾</span>
      <span>Activité<?= $isManager ? ' (lecture)' : '' ?></span>
    </a>
    <?php endif; ?>

    <?php if ($isOwner): ?>
    <!-- ════ OWNER ONLY ════ -->
    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('subscription_billing.php', $currentPage) ?>"
       href="<?= $L['subscription'] ?>">
      <span>💳</span><span>Abonnement</span>
    </a>

    <a class="od-nav-link<?= sbActive('settings.php', $currentPage) ?>"
       href="<?= $L['settings'] ?>">
      <span>⚙️</span><span>Paramètres</span>
    </a>
    <?php endif; ?>

    <!-- ════ ACCOUNT — all roles ════ -->
    <div style="height:1px;background:rgba(255,255,255,.08);margin:6px 0"></div>

    <a class="od-nav-link<?= sbActive('change_pin.php', $currentPage) ?>"
       href="<?= $L['change_pin'] ?>">
      <span>🔐</span><span>Changer PIN</span>
    </a>

    <a class="od-nav-link logout" href="<?= $L['logout'] ?>">
      <span>🚪</span><span>Déconnexion</span>
    </a>

  </nav>

  <!-- User info footer -->
  <div class="od-sidebar-footer">
    <div class="od-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
    <div>
      <div class="od-sidebar-name"><?= htmlspecialchars($sidebarFullName) ?></div>
      <div class="od-sidebar-role"><?= htmlspecialchars($sidebarBizName) ?></div>
    </div>
  </div>

</aside>

<!-- ⑤ Toggle JS — self-contained, prevents double-binding with owner_dashboard.js -->
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
})();
</script>