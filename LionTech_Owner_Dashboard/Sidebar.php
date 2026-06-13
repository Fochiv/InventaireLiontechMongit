<?php
/* ============================================================
   Sidebar.php — Tally Business Manager
   Mobile hamburger · EN/FR i18n · All roles
   ============================================================ */
$currentPage  = basename($_SERVER['PHP_SELF']);
$sidebarRole  = $_SESSION['role'] ?? '';
$isOwner      = ($sidebarRole === 'business_owner')
             || (defined('ROLE_BUSINESS_OWNER') && $sidebarRole === ROLE_BUSINESS_OWNER);
$isManager    = $sidebarRole === 'manager';
$isEmployee   = $sidebarRole === 'employee';
$isCaissier   = $sidebarRole === 'caissier';
$isOwnerOrMgr = $isOwner || $isManager;
$url          = defined('APP_URL') ? APP_URL : '';

$L = [
    'owner_dash'    => $url.'/LionTech_Owner_Dashboard/owner_dashboard.php',
    'employee_dash' => $url.'/LionTech_Employee_Dashboard/employee_dashboard.php',
    'attendance'    => $url.'/Attendance_presenceemployer/clock_attendance.php',
    'notifications' => $url.'/LionTech_Complete_MVP_Remaining_Pages/notifications.php',
    'caisse'        => $url.'/Vente_cashier/caisse/dashboard.php',
    'caisse_valid'  => $url.'/Vente_cashier/caisse/validations.php',
    'vente'         => $url.'/Vente_cashier/Vente.php',
    'products'      => $url.'/Produit/products.php',
    'stock_in'      => $url.'/LionTech_Stock_In_Page/stock_in.php',
    'stock_out'     => $url.'/stockout_stockfinis/stock_out.php',
    'employees'     => $url.'/LionTech_Employee_Management/employees.php',
    'pin_manager'   => $url.'/LionTech_Owner_Dashboard\pin_manager.php',
    'validations'   => $url.'/LionTech_Complete_MVP_Remaining_Pages/approval_center.php',
    'reports'       => $url.'/LionTech_Complete_MVP_Remaining_Pages/reports.php',
    'activity'      => $url.'/LionTech_Complete_MVP_Remaining_Pages/activity_logs.php',
    'subscription'  => $url.'/LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php',
    'settings'      => $url.'/LionTech_Complete_MVP_Remaining_Pages/settings.php',
    'change_pin'    => $url.'/change_pin.php',
    'logout'        => $url.'/Logininventory/logout.php',
];

$sidebarUser     = function_exists('currentUser') ? currentUser() : [];
$sidebarFullName = $sidebarUser['full_name'] ?? 'User';
$sidebarInitials = '';
foreach (explode(' ', trim($sidebarFullName)) as $w)
    $sidebarInitials .= strtoupper(substr($w, 0, 1));
$sidebarInitials = substr($sidebarInitials ?: 'U', 0, 2);
$sidebarBizName  = $business['business_name'] ?? $sidebarUser['business_name'] ?? 'LionTech';

if (!function_exists('sbA')) {
    function sbA(string $p, string $c): string { return $c === $p ? ' active' : ''; }
}
?>

<!-- ── Sidebar CSS (mobile-first) ── -->
<style>
.od-sidebar{position:fixed;top:0;left:-280px;width:260px;height:100vh;
  background:#0B1F3A;z-index:40;overflow-y:auto;display:flex;flex-direction:column;
  transition:left .28s ease;box-shadow:4px 0 20px rgba(0,0,0,.25)}
.od-sidebar.open{left:0}
.sb-overlay{display:none;position:fixed;inset:0;z-index:39;background:rgba(0,0,0,.45)}
.sb-overlay.open{display:block}
.od-sidebar-header{display:flex;align-items:center;justify-content:space-between;
  padding:18px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-logo{display:flex;align-items:center;gap:10px}
.sb-logo img{width:38px;height:38px;border-radius:50%;object-fit:cover}
.sb-logo-name{color:#D4A017;font-size:15px;font-weight:800;line-height:1;letter-spacing:.3px}
.sb-logo-tag{color:rgba(255,255,255,.45);font-size:10px;margin-top:2px}
.od-sidebar-close{background:none;border:none;color:rgba(255,255,255,.5);
  font-size:22px;cursor:pointer;padding:4px 8px;line-height:1}
.od-sidebar-close:hover{color:#fff}
.od-nav{flex:1;padding:10px 0}
.od-nav-section{padding:10px 16px 4px;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.3)}
.od-nav-link{display:flex;align-items:center;gap:10px;padding:10px 16px;
  color:rgba(255,255,255,.65);text-decoration:none;font-size:13px;font-weight:500;
  border-left:3px solid transparent;transition:all .15s}
.od-nav-link:hover{background:rgba(255,255,255,.07);color:#fff}
.od-nav-link.active{background:rgba(255,255,255,.1);color:#fff;border-left-color:#1A9E7A}
.od-nav-link.logout{color:rgba(255,100,100,.7)}
.od-nav-link.logout:hover{color:#ff6464;background:rgba(255,0,0,.08)}
.od-nav-link svg{flex-shrink:0;opacity:.7}
.od-nav-link:hover svg,.od-nav-link.active svg{opacity:1}
.sb-badge{font-size:10px;color:rgba(255,255,255,.4);margin-left:auto}
.od-sidebar-footer{padding:14px 16px;border-top:1px solid rgba(255,255,255,.08);
  display:flex;align-items:center;gap:10px}
.od-avatar-sb{width:34px;height:34px;border-radius:50%;background:#1A9E7A;
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:700;flex-shrink:0}
.sb-foot-name{color:#fff;font-size:13px;font-weight:600}
.sb-foot-role{color:rgba(255,255,255,.4);font-size:11px;text-transform:capitalize}
/* Desktop: sidebar always visible */
@media(min-width:769px){
  .od-sidebar{left:0 !important}
  .sb-overlay{display:none !important}
  .od-main{margin-left:260px}
  .od-sidebar-close{display:none}
}
/* Mobile hamburger button */
.od-hamburger{display:none;background:none;border:none;font-size:22px;
  cursor:pointer;padding:6px 10px;color:#0B1F3A;line-height:1}
@media(max-width:768px){
  .od-hamburger{display:inline-flex}
  .od-main{margin-left:0 !important}
}
</style>

<!-- Global i18n engine (covers all pages) -->
<script src="<?= $url ?>/global_i18n.js"></script>

<!-- Overlay -->
<div class="sb-overlay" id="sbOverlay"></div>

<!-- Hamburger (inject into topbar via JS) -->
<button class="od-hamburger" id="sbHamburger" aria-label="Menu">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<aside class="od-sidebar" id="od-sidebar">
  <div class="od-sidebar-header">
    <div class="sb-logo">
      <img src="<?= $url ?>/Image/TALLYLOGO.png" alt="Tally"
           onerror="this.style.display='none'">
      <div>
        <div class="sb-logo-name">LionTech</div>
        <div class="sb-logo-tag">Business Manager</div>
      </div>
    </div>
    <button class="od-sidebar-close" id="sbClose">×</button>
  </div>

  <nav class="od-nav">

    <!-- MAIN -->
    <div class="od-nav-section" data-i18n="sec_main">Main</div>

    <?php if($isEmployee||$isCaissier): ?>
    <a class="od-nav-link<?= sbA('employee_dashboard.php',$currentPage) ?>" href="<?= $L['employee_dash'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span data-i18n="nav_dashboard">Dashboard</span>
    </a>
    <?php else: ?>
    <a class="od-nav-link<?= sbA('owner_dashboard.php',$currentPage) ?>" href="<?= $L['owner_dash'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span data-i18n="nav_dashboard">Dashboard</span>
    </a>
    <?php endif; ?>

    <a class="od-nav-link<?= sbA('clock_attendance.php',$currentPage) ?>" href="<?= $L['attendance'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span data-i18n="nav_attendance">Attendance</span>
    </a>

    <a class="od-nav-link<?= sbA('notifications.php',$currentPage) ?>" href="<?= $L['notifications'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span data-i18n="nav_notifications">Notifications</span>
      <span id="lt-notif-badge" style="display:none;background:#DC2626;color:#fff;font-size:10px;font-weight:700;border-radius:50px;padding:1px 6px;margin-left:auto;min-width:18px;text-align:center"></span>
    </a>

    <!-- REGISTER -->
    <div class="od-nav-section" data-i18n="sec_register">Register</div>

    <a class="od-nav-link<?= sbA('dashboard.php',$currentPage) ?>" href="<?= $L['caisse'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      <span data-i18n="nav_caisse">Register</span>
      <?php if($isCaissier): ?><span class="sb-badge">PIN</span>
      <?php elseif($isEmployee): ?><span class="sb-badge">Code</span><?php endif; ?>
    </a>
    <a class="od-nav-link<?= sbA('pin_manager.php',$currentPage) ?>" href="<?= $L['pin_manager'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span data-i18n="nav_pins">PINs</span>
    </a>

    <?php if($isOwnerOrMgr): ?>
    <a class="od-nav-link<?= sbA('validations.php',$currentPage) ?>" href="<?= $L['caisse_valid'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <span data-i18n="nav_caisse_valid">POS Validations</span>
    </a>
    <?php endif; ?>

    <!-- SALES -->
    <?php if($isOwnerOrMgr): ?>
    <div class="od-nav-section" data-i18n="sec_sales">Sales</div>
    <a class="od-nav-link<?= sbA('Vente.php',$currentPage) ?>" href="<?= $L['vente'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span data-i18n="nav_vente">Sales Reports</span>
    </a>
    <?php endif; ?>

    <!-- INVENTORY -->
    <?php if(!$isCaissier): ?>
    <div class="od-nav-section" data-i18n="sec_inventory">Inventory</div>

    <a class="od-nav-link<?= sbA('products.php',$currentPage) ?>" href="<?= $L['products'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      <span data-i18n="nav_products">Products</span>
    </a>
    <a class="od-nav-link<?= sbA('stock_in.php',$currentPage) ?>" href="<?= $L['stock_in'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg>
      <span data-i18n="nav_stock_in">Stock In</span>
    </a>
    <?php endif; ?>

    <a class="od-nav-link<?= sbA('stock_out.php',$currentPage) ?>" href="<?= $L['stock_out'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg>
      <span data-i18n="nav_stock_out">Stock Out</span>
    </a>

    <!-- MANAGEMENT -->
    <?php if($isOwnerOrMgr): ?>
    <div class="od-nav-section" data-i18n="sec_management">Management</div>

    <a class="od-nav-link<?= sbA('employees.php',$currentPage) ?>" href="<?= $L['employees'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
      <span data-i18n="nav_employees">Employees</span>
    </a>
    
    <a class="od-nav-link<?= sbA('approval_center.php',$currentPage) ?>" href="<?= $L['validations'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <span data-i18n="nav_validations">Validations</span>
    </a>
    <a class="od-nav-link<?= sbA('reports.php',$currentPage) ?>" href="<?= $L['reports'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span data-i18n="nav_reports">Reports</span>
    </a>
    <a class="od-nav-link<?= sbA('activity_logs.php',$currentPage) ?>" href="<?= $L['activity'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span data-i18n="nav_activity">Activity</span>
    </a>
    <?php endif; ?>

    <!-- BUSINESS -->
    <?php if($isOwner): ?>
    <div class="od-nav-section" data-i18n="sec_business">Business</div>
    <a class="od-nav-link<?= sbA('subscription_billing.php',$currentPage) ?>" href="<?= $L['subscription'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      <span data-i18n="nav_subscription">Subscription</span>
    </a>
    <a class="od-nav-link<?= sbA('settings.php',$currentPage) ?>" href="<?= $L['settings'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4"/></svg>
      <span data-i18n="nav_settings">Settings</span>
    </a>
    <?php endif; ?>

    <!-- ACCOUNT -->
    <div class="od-nav-section" data-i18n="sec_account">Account</div>
    <a class="od-nav-link<?= sbA('change_pin.php',$currentPage) ?>" href="<?= $L['change_pin'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <span data-i18n="nav_change_pin">Change PIN</span>
    </a>
    <a class="od-nav-link logout" href="<?= $L['logout'] ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span data-i18n="nav_logout">Sign Out</span>
    </a>

  </nav>

  <div class="od-sidebar-footer">
    <div class="od-avatar-sb"><?= htmlspecialchars($sidebarInitials,ENT_QUOTES,'UTF-8') ?></div>
    <div>
      <div class="sb-foot-name"><?= htmlspecialchars($sidebarFullName,ENT_QUOTES,'UTF-8') ?></div>
      <div class="sb-foot-role"><?= htmlspecialchars($sidebarBizName,ENT_QUOTES,'UTF-8') ?></div>
    </div>
  </div>
</aside>

<script>
(function(){
  /* ── Translations ── */
  const T = {
    en:{
      sec_main:'Main', sec_register:'Register', sec_sales:'Sales',
      sec_inventory:'Inventory', sec_management:'Management',
      sec_business:'Business', sec_account:'Account',
      nav_dashboard:'Dashboard', nav_attendance:'Attendance',
      nav_notifications:'Notifications', nav_caisse:'Register',
      nav_caisse_valid:'POS Validations', nav_vente:'Sales Reports',
      nav_products:'Products', nav_stock_in:'Stock In',
      nav_stock_out:'Stock Out', nav_employees:'Employees',
      nav_pins:'PINs', nav_validations:'Validations',
      nav_reports:'Reports', nav_activity:'Activity',
      nav_subscription:'Subscription', nav_settings:'Settings',
      nav_change_pin:'Change PIN', nav_logout:'Sign Out'
    },
    fr:{
      sec_main:'Principal', sec_register:'Caisse', sec_sales:'Vente',
      sec_inventory:'Inventaire', sec_management:'Gestion',
      sec_business:'Business', sec_account:'Compte',
      nav_dashboard:'Tableau de bord', nav_attendance:'Présence',
      nav_notifications:'Notifications', nav_caisse:'Caisse',
      nav_caisse_valid:'Validations caisse', nav_vente:'Tableau des ventes',
      nav_products:'Produits', nav_stock_in:'Stock entrant',
      nav_stock_out:'Stock sortant', nav_employees:'Employés',
      nav_pins:'PINs caisse', nav_validations:'Validations',
      nav_reports:'Rapports', nav_activity:'Activité',
      nav_subscription:'Abonnement', nav_settings:'Paramètres',
      nav_change_pin:'Changer PIN', nav_logout:'Déconnexion'
    }
  };

  window.applySidebarLang = function(lang){
    lang = lang || localStorage.getItem('lt_lang') || 'en';
    const tr = T[lang] || T.en;
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const k = el.dataset.i18n;
      if (tr[k]) el.textContent = tr[k];
    });
  };

  /* ── Hamburger toggle ── */
  const sb  = document.getElementById('od-sidebar');
  const ov  = document.getElementById('sbOverlay');
  const ham = document.getElementById('sbHamburger');
  const cls = document.getElementById('sbClose');

  function openSb(){ sb?.classList.add('open'); ov?.classList.add('open'); document.body.style.overflow='hidden'; }
  function closeSb(){ sb?.classList.remove('open'); ov?.classList.remove('open'); document.body.style.overflow=''; }

  ham?.addEventListener('click', openSb);
  cls?.addEventListener('click', closeSb);
  ov?.addEventListener('click',  closeSb);
  document.addEventListener('keydown', e => { if(e.key==='Escape') closeSb(); });

  /* Move hamburger into existing od-topbar if present */
  const topbar = document.querySelector('.od-topbar');
  const existingMenuBtn = document.getElementById('od-menu-btn');
  if (topbar && ham) {
    if (!existingMenuBtn) {
      /* No page-specific button: inject the sidebar hamburger */
      topbar.insertBefore(ham, topbar.firstChild);
      ham.style.display = '';
    }
    /* If page already has od-menu-btn, hide our extra hamburger */
  }

  /* Hook od-menu-btn if the page has its own */
  existingMenuBtn?.addEventListener('click', openSb);

  /* ── Notification badge ── */
  const badge = document.getElementById('lt-notif-badge');
  if (badge) {
    const api = '<?= $url ?>/LionTech_Complete_MVP_Remaining_Pages/notifications_api.php';
    function pollNotif(){
      fetch(api,{credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if(d.unread>0){badge.textContent=d.unread>99?'99+':d.unread;badge.style.display='inline-block';}
        else badge.style.display='none';
      }).catch(()=>{});
    }
    pollNotif(); setInterval(pollNotif,30000);
  }

  /* ── Listen for lang changes ── */
  window.addEventListener('storage', e => {
    if(e.key==='lt_lang') window.applySidebarLang(e.newValue);
  });

  /* ── Init ── */
  window.applySidebarLang();
})();
</script>