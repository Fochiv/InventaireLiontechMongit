<?php
/* ============================================================
   _sidebar.php — Super Admin Sidebar
   Requires before include: $url, $user, $initials
   ============================================================ */

if (!function_exists('saIcon')) {
    function saIcon(string $name, int $size = 18): string {
        $s = $size;

        $icons = [
            'dashboard' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='7' height='7'/><rect x='14' y='3' width='7' height='7'/><rect x='14' y='14' width='7' height='7'/><rect x='3' y='14' width='7' height='7'/></svg>",

            'plus' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='16'/><line x1='8' y1='12' x2='16' y2='12'/></svg>",

            'check' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M20 6L9 17l-5-5'/></svg>",

            'building' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='2' width='18' height='20' rx='1'/><line x1='9' y1='22' x2='9' y2='12'/><line x1='15' y1='22' x2='15' y2='12'/><rect x='9' y='12' width='6' height='10'/></svg>",

            'credit-card' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='1' y='4' width='22' height='16' rx='2'/><line x1='1' y1='10' x2='23' y2='10'/></svg>",

            'refresh' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='23 4 23 10 17 10'/><polyline points='1 20 1 14 7 14'/><path d='M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15'/></svg>",

            'users' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/></svg>",

            'bar-chart' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>",

            'settings' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='3'/><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.32.33.64.6 1h.09a2 2 0 1 1 0 4H20a1.65 1.65 0 0 0-.6 1z'/></svg>",

            'logout' => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'/><polyline points='16 17 21 12 16 7'/><line x1='21' y1='12' x2='9' y2='12'/></svg>",

            'close' => "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg>",
        ];

        return $icons[$name] ?? $icons['dashboard'];
    }
}

$saCurrentPage = basename($_SERVER['PHP_SELF']);
$saTab = $_GET['tab'] ?? '';
$saUrl = $url ?? APP_URL;

$saPendingCount = 0;
$saBusinessRequestCount = 0;
$saStatTotal = 0;
$saStatSubs = 0;
$saStatUsers = 0;

try {
    $pdoSide = getDB();

    $saPendingCount = (int)$pdoSide
        ->query("SELECT COUNT(*) FROM liontech_payments WHERE status='pending'")
        ->fetchColumn();

    $saBusinessRequestCount = (int)$pdoSide
        ->query("SELECT COUNT(*) FROM business_requests WHERE status='pending'")
        ->fetchColumn();

    $saStatTotal = (int)$pdoSide
        ->query("SELECT COUNT(*) FROM businesses")
        ->fetchColumn();

    $saStatUsers = (int)$pdoSide
        ->query("SELECT COUNT(*) FROM users WHERE role!='super_admin'")
        ->fetchColumn();

    try {
        $saStatSubs = (int)$pdoSide
            ->query("SELECT COUNT(*) FROM subscriptions")
            ->fetchColumn();
    } catch (Throwable $_e) {
        $saStatSubs = $saStatTotal;
    }

} catch (Throwable $_e) {
    // Keep sidebar working even if one table does not exist yet.
}
?>

<aside class="sa-sidebar" id="sa-sidebar">

  <div class="sa-sidebar-header">
    <div class="sa-logo">
      <img
        src="<?= $saUrl ?>/Image/TALLYLOGO.png"
        alt="Tally"
        style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0"
      >
      <div>
        <div class="sb-logo-name">LionTech</div>
        <div class="sa-logo-tag">Business Manager</div>
      </div>
    </div>

    <button class="sa-sidebar-close" id="sa-sidebar-close">
      <?= saIcon('close') ?>
    </button>
  </div>

  <nav class="sa-nav">

    <div class="sa-nav-section" data-i18n="nav_section_main">Principal</div>

    <a class="sa-nav-item <?= $saCurrentPage === 'super_admin.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/super_admin.php">
      <span class="sa-nav-icon"><?= saIcon('dashboard') ?></span>
      <span data-i18n="nav_dashboard">Dashboard</span>
    </a>

    <a class="sa-nav-item <?= $saCurrentPage === 'add_business.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/LionTech_Add_Business_Page/add_business.php">
      <span class="sa-nav-icon"><?= saIcon('plus') ?></span>
      <span data-i18n="nav_add_business">Add Business</span>
    </a>

    <?php if ($saBusinessRequestCount > 0): ?>
<a class="sa-nav-item <?= $saCurrentPage === 'business_requests.php' ? 'active' : '' ?>"
   href="<?= $saUrl ?>/SuperAdmin/business_requests.php">
  <span class="sa-nav-icon"><?= saIcon('check') ?></span>
  <span data-i18n="nav_business_requests">Confirm Business Requests</span>
  <span class="sa-nav-badge"><?= $saBusinessRequestCount ?></span>
</a>
<?php else: ?>
<span class="sa-nav-item" style="opacity:.4;cursor:not-allowed;pointer-events:none;">
  <span class="sa-nav-icon"><?= saIcon('check') ?></span>
  <span data-i18n="nav_business_requests">Confirm Business Requests</span>
</span>
<?php endif; ?>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php?panel=businesses">
      <span class="sa-nav-icon"><?= saIcon('building') ?></span>
      <span data-i18n="nav_businesses">Businesses</span>

      <?php if ($saStatTotal > 0): ?>
        <span class="sa-nav-badge"><?= $saStatTotal ?></span>
      <?php endif; ?>
    </a>

    <div class="sa-nav-section" data-i18n="nav_section_payments">Payments</div>

    <a class="sa-nav-item <?= $saCurrentPage === 'payment_review.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_review.php">
      <span class="sa-nav-icon"><?= saIcon('check') ?></span>
      <span data-i18n="nav_validate">Validate Payments</span>

      <?php if ($saPendingCount > 0): ?>
        <span class="sa-nav-badge"><?= $saPendingCount ?></span>
      <?php endif; ?>
    </a>

    <a class="sa-nav-item <?= ($saCurrentPage === 'payment_settings.php' && $saTab === 'numbers') ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_settings.php?tab=numbers">
      <span class="sa-nav-icon"><?= saIcon('credit-card') ?></span>
      <span data-i18n="nav_payment_numbers">Payment Numbers</span>
    </a>

    <div class="sa-nav-section" data-i18n="nav_section_platform">Platform</div>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php?panel=subscriptions">
      <span class="sa-nav-icon"><?= saIcon('refresh') ?></span>
      <span data-i18n="nav_subscriptions">Subscriptions</span>

      <?php if ($saStatSubs > 0): ?>
        <span class="sa-nav-badge"><?= $saStatSubs ?></span>
      <?php endif; ?>
    </a>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php?panel=users">
      <span class="sa-nav-icon"><?= saIcon('users') ?></span>
      <span data-i18n="nav_users">Users</span>

      <?php if ($saStatUsers > 0): ?>
        <span class="sa-nav-badge"><?= $saStatUsers ?></span>
      <?php endif; ?>
    </a>

    <a class="sa-nav-item <?= $saCurrentPage === 'super_admin_reports.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/super_admin_reports.php">
      <span class="sa-nav-icon"><?= saIcon('bar-chart') ?></span>
      <span data-i18n="nav_reports">Reports</span>
    </a>

    <div class="sa-nav-section" data-i18n="nav_section_system">System</div>

    <a class="sa-nav-item <?= ($saCurrentPage === 'payment_settings.php' && $saTab !== 'numbers') ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_settings.php">
      <span class="sa-nav-icon"><?= saIcon('settings') ?></span>
      <span data-i18n="nav_settings">Settings</span>
    </a>

    <a class="sa-nav-item sa-nav-logout" href="<?= $saUrl ?>/Logininventory/logout.php">
      <span class="sa-nav-icon"><?= saIcon('logout') ?></span>
      <span data-i18n="nav_logout">Sign Out</span>
    </a>

  </nav>

  <div class="sa-sidebar-footer">
    <div class="sa-sidebar-avatar"><?= htmlspecialchars($initials ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <div>
      <div class="sa-sidebar-name"><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
      <div class="sa-sidebar-role">Super Admin</div>
    </div>
  </div>

</aside>

<div class="sa-overlay" id="sa-overlay"></div>

<script>
(function(){
  var KEYS = {
    en:{
      nav_section_main:'Main',
      nav_section_payments:'Payments',
      nav_section_platform:'Platform',
      nav_section_system:'System',
      nav_dashboard:'Dashboard',
      nav_add_business:'Add Business',
      nav_business_requests:'Confirm Business Requests',
      nav_businesses:'Businesses',
      nav_validate:'Validate Payments',
      nav_payment_numbers:'Payment Numbers',
      nav_subscriptions:'Subscriptions',
      nav_users:'Users',
      nav_reports:'Reports',
      nav_settings:'Settings',
      nav_logout:'Sign Out'
    },
    fr:{
      nav_section_main:'Principal',
      nav_section_payments:'Paiements',
      nav_section_platform:'Plateforme',
      nav_section_system:'Système',
      nav_dashboard:'Dashboard',
      nav_add_business:'Ajouter Business',
      nav_business_requests:'Confirmer demandes business',
      nav_businesses:'Businesses',
      nav_validate:'Valider Paiements',
      nav_payment_numbers:'Numéros Paiement',
      nav_subscriptions:'Abonnements',
      nav_users:'Utilisateurs',
      nav_reports:'Rapports',
      nav_settings:'Paramètres',
      nav_logout:'Déconnexion'
    }
  };

  var lang = localStorage.getItem('lt_lang') || 'en';

  function applyLang(l){
    document.querySelectorAll('[data-i18n]').forEach(function(el){
      var key = el.getAttribute('data-i18n');
      if (KEYS[l] && KEYS[l][key]) {
        el.textContent = KEYS[l][key];
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    applyLang(lang);
  });
})();
</script>