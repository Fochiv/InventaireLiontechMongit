<?php
/* ============================================================
   _sidebar.php — Barre latérale partagée Super Admin
   À inclure dans toutes les pages Super Admin.
   Nécessite : $url, $user, $initials (définis avant l'include)
   ============================================================ */
if (!function_exists('saIcon')) {
    function saIcon(string $name, int $size = 18): string {
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
            'printer'     => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 6 2 18 2 18 9'/><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'/><rect x='6' y='14' width='12' height='8'/></svg>",
            'download'    => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='7 10 12 15 17 10'/><line x1='12' y1='15' x2='12' y2='3'/></svg>",
            'lock'        => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'/><path d='M7 11V7a5 5 0 0 1 10 0v4'/></svg>",
            'message'     => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'/></svg>",
            'money'       => "<svg width='$s' height='$s' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='12' y1='1' x2='12' y2='23'/><path d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/></svg>",
        ];
        return $icons[$name] ?? $icons['info'];
    }
}

/* Nombre de paiements en attente */
if (!isset($saPendingCount)) {
    $saPendingCount = 0;
    try {
        $saPendingCount = (int)getDB()->query("SELECT COUNT(*) FROM liontech_payments WHERE status='pending'")->fetchColumn();
    } catch (Throwable $_e) {}
}

$saCurrentPage = basename($_SERVER['PHP_SELF']);
$saTab = $_GET['tab'] ?? '';
$saUrl = $url ?? APP_URL;
?>
<aside class="sa-sidebar" id="sa-sidebar">
  <div class="sa-sidebar-header">
    <div class="sa-logo">
      <img src="<?= $saUrl ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div><div class="sa-logo-name">LionTech</div><div class="sa-logo-tag">Business Manager</div></div>
    </div>
    <button class="sa-sidebar-close" id="sa-sidebar-close"><?= saIcon('close') ?></button>
  </div>

  <nav class="sa-nav">
    <div class="sa-nav-section">Principal</div>

    <a class="sa-nav-item <?= $saCurrentPage === 'super_admin.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/super_admin.php">
      <span class="sa-nav-icon"><?= saIcon('dashboard') ?></span>
      <span>Dashboard</span>
    </a>

    <a class="sa-nav-item <?= $saCurrentPage === 'add_business.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/LionTech_Add_Business_Page/add_business.php">
      <span class="sa-nav-icon"><?= saIcon('plus') ?></span>
      <span>Ajouter Business</span>
    </a>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php">
      <span class="sa-nav-icon"><?= saIcon('building') ?></span>
      <span>Businesses</span>
    </a>

    <div class="sa-nav-section">Paiements</div>

    <a class="sa-nav-item <?= $saCurrentPage === 'payment_review.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_review.php">
      <span class="sa-nav-icon"><?= saIcon('check') ?></span>
      <span>Valider Paiements</span>
      <?php if ($saPendingCount > 0): ?>
      <span class="sa-nav-badge"><?= $saPendingCount ?></span>
      <?php endif; ?>
    </a>

    <a class="sa-nav-item <?= ($saCurrentPage === 'payment_settings.php' && $saTab === 'numbers') ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_settings.php?tab=numbers">
      <span class="sa-nav-icon"><?= saIcon('credit-card') ?></span>
      <span>Numéros Paiement</span>
    </a>

    <div class="sa-nav-section">Plateforme</div>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php">
      <span class="sa-nav-icon"><?= saIcon('refresh') ?></span>
      <span>Abonnements</span>
    </a>

    <a class="sa-nav-item" href="<?= $saUrl ?>/SuperAdmin/super_admin.php">
      <span class="sa-nav-icon"><?= saIcon('users') ?></span>
      <span>Utilisateurs</span>
    </a>

    <a class="sa-nav-item <?= $saCurrentPage === 'super_admin_reports.php' ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/super_admin_reports.php">
      <span class="sa-nav-icon"><?= saIcon('bar-chart') ?></span>
      <span>Rapports</span>
    </a>

    <div class="sa-nav-section">Système</div>

    <a class="sa-nav-item <?= ($saCurrentPage === 'payment_settings.php' && $saTab !== 'numbers') ? 'active' : '' ?>"
       href="<?= $saUrl ?>/SuperAdmin/payment_settings.php">
      <span class="sa-nav-icon"><?= saIcon('settings') ?></span>
      <span>Paramètres</span>
    </a>

    <a class="sa-nav-item sa-nav-logout" href="<?= $saUrl ?>/Logininventory/logout.php">
      <span class="sa-nav-icon"><?= saIcon('logout') ?></span>
      <span>Déconnexion</span>
    </a>
  </nav>

  <div class="sa-sidebar-footer">
    <div class="sa-sidebar-avatar"><?= htmlspecialchars($initials ?? '') ?></div>
    <div>
      <div class="sa-sidebar-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
      <div class="sa-sidebar-role">Super Admin</div>
    </div>
  </div>
</aside>
<div class="sa-overlay" id="sa-overlay"></div>
<script>
/* Lang toggle — injected on all SA pages that include _sidebar.php */
(function(){
  if (document.getElementById('lang-toggle')) return; /* super_admin.php has its own */
  var KEYS = {
    fr:{
      nav_dashboard:'Tableau de bord', nav_add_business:'Ajouter Business', nav_businesses:'Businesses',
      nav_payments:'Valider Paiements', nav_numbers:'Numéros Paiement', nav_subscriptions:'Abonnements',
      nav_users:'Utilisateurs', nav_reports:'Rapports', nav_settings:'Paramètres', nav_logout:'Déconnexion',
      pr_title:'Validation des Paiements', pr_pending_title:'Paiements en attente de validation',
      pr_pending_sub:'Vérifiez chaque preuve avant d\'approuver', pr_none:'Aucun paiement en attente.',
      pr_history:'Historique des validations',
      ps_title:'Paramètres de Paiement', ps_om:'Orange Money', ps_mtn:'MTN Mobile Money',
      ps_bank:'Coordonnées Bancaires', ps_save:'💾 Sauvegarder', ps_name_label:'Votre nom complet (confirmation)',
      sa_reports_title:'Rapports Plateforme', sa_reports_sub:'Vue globale — revenus, paiements, businesses et activité'
    },
    en:{
      nav_dashboard:'Dashboard', nav_add_business:'Add Business', nav_businesses:'Businesses',
      nav_payments:'Validate Payments', nav_numbers:'Payment Numbers', nav_subscriptions:'Subscriptions',
      nav_users:'Users', nav_reports:'Reports', nav_settings:'Settings', nav_logout:'Sign Out',
      pr_title:'Payment Validation', pr_pending_title:'Payments awaiting validation',
      pr_pending_sub:'Verify each proof before approving', pr_none:'No pending payments.',
      pr_history:'Validation History',
      ps_title:'Payment Settings', ps_om:'Orange Money', ps_mtn:'MTN Mobile Money',
      ps_bank:'Bank Details', ps_save:'💾 Save', ps_name_label:'Your full name (confirmation)',
      sa_reports_title:'Platform Reports', sa_reports_sub:'Global overview — revenue, payments, businesses and activity'
    }
  };
  var lang = localStorage.getItem('lt_lang') || 'fr';
  function applyLang(l){
    document.querySelectorAll('[data-i18n]').forEach(function(el){
      var k = el.getAttribute('data-i18n');
      if (KEYS[l] && KEYS[l][k]) el.textContent = KEYS[l][k];
    });
    var btn = document.getElementById('_lt_lang_btn');
    if (btn) btn.textContent = l === 'fr' ? 'EN' : 'FR';
  }
  document.addEventListener('DOMContentLoaded', function(){
    var tr = document.querySelector('.sa-topbar-right');
    if (!tr) return;
    var btn = document.createElement('button');
    btn.id = '_lt_lang_btn';
    btn.style.cssText = 'font-size:11px;padding:5px 11px;border:1.5px solid #CBD5E1;border-radius:6px;background:#fff;cursor:pointer;font-weight:700;color:#0B1F3A;letter-spacing:.5px';
    btn.textContent = lang === 'fr' ? 'EN' : 'FR';
    btn.addEventListener('click', function(){
      lang = lang === 'fr' ? 'en' : 'fr';
      localStorage.setItem('lt_lang', lang);
      applyLang(lang);
    });
    tr.insertBefore(btn, tr.firstChild);
    applyLang(lang);
  });
})();
</script>
