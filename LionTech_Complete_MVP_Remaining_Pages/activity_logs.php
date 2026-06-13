<?php
/* ============================================================
   activity_logs.php — Tally Business Manager
   Owner: full access | Manager: view only
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$role       = $user['role'] ?? '';
$isOwner    = ($role === ROLE_BUSINESS_OWNER);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Date filter */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

/* Load logs */
$logs = [];
try {
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name AS user_name
        FROM activity_logs al
        LEFT JOIN users u ON u.user_id = al.user_id
        WHERE al.business_id = ?
          AND al.created_at BETWEEN ? AND ?
        ORDER BY al.created_at DESC
        LIMIT 200
    ");
    $stmt->execute([$businessId, $from.' 00:00:00', $to.' 23:59:59']);
    $logs = $stmt->fetchAll();
} catch (Throwable $e) {}

$total = count($logs);

/* Today count */
$todayCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE business_id=? AND DATE(created_at)=CURDATE()");
    $stmt->execute([$businessId]);
    $todayCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'O', 0, 2);

/* Action SVG icon map */
function actionSvgIcon(string $action): string {
    $a = strtolower($action);
    if (str_contains($a, 'clock_in'))         return '<svg style="color:#16A34A" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    if (str_contains($a, 'clock_out'))        return '<svg style="color:#DC2626" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
    if (str_contains($a, 'employee'))        return '<svg style="color:#2563EB" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    if (str_contains($a, 'stock_in'))        return '<svg style="color:#1A9E7A" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg>';
    if (str_contains($a, 'stock_out'))       return '<svg style="color:#DC2626" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg>';
    if (str_contains($a, 'login'))           return '<svg style="color:#8B5CF6" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
    if (str_contains($a, 'logout'))          return '<svg style="color:#6B7280" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
    if (str_contains($a, 'product'))         return '<svg style="color:#0B1F3A" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
    if (str_contains($a, 'approval'))        return '<svg style="color:#16A34A" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    return '<svg style="color:#6B7280" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
}

$business = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id=? LIMIT 1");
    $stmt->execute([$businessId]);
    $business = $stmt->fetch() ?: [];
} catch(Throwable $e){}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Journal d'Activité — LionTech</title>
  <link rel="icon" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
  <style>
    .al-menu-btn{display:none;border:1.5px solid #E5E7EB;background:#fff;border-radius:10px;padding:8px 10px;cursor:pointer;color:#0B1F3A}
    @media(max-width:1050px){.al-menu-btn{display:flex;align-items:center}}
    .al-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:20px 24px 0}
    @media(max-width:700px){.al-stat-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:480px){.al-stat-grid{grid-template-columns:1fr}}
    .al-filter-bar{padding:16px 24px 0}
    .al-filter-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .al-date-group{display:flex;align-items:center;gap:6px}
    .al-date-group label{font-size:12px;font-weight:600;color:#6B7280}
    .al-date-group input{padding:8px 11px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit;outline:none;transition:.15s}
    .al-date-group input:focus{border-color:#0B1F3A}
    .al-btn{padding:9px 16px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;border:1.5px solid transparent;transition:.15s}
    .al-btn-primary{background:#0B1F3A;color:#fff;border-color:#0B1F3A}
    .al-btn-outline{background:#fff;color:#0B1F3A;border-color:#E5E7EB}
    .al-btn-outline:hover{border-color:#0B1F3A}
    .al-search{padding:9px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit;outline:none;margin-left:auto;min-width:180px}
    .al-search:focus{border-color:#0B1F3A}
    .al-table-card{margin:16px 24px 40px}
    .al-card-head{padding:14px 18px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center}
    .al-action-cell{display:flex;align-items:center;gap:7px}
    .action-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:50px;font-size:11.5px;font-weight:600;background:#EEF2F7;color:#334155}
  </style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
<link rel="stylesheet" href="<?= APP_URL ?>/responsive_utils.css">
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <button class="od-menu-btn al-menu-btn" id="od-menu-btn" aria-label="Open menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="od-business-title">
        <h1 data-i18n="page_title">Journal d'Activité</h1>
        <p><span data-i18n="page_sub">Traçabilité complète — historique de toutes les actions</span></p>
      </div>
      <div class="od-top-actions">
        <?php if ($isOwner): ?>
        <button onclick="exportCSV()" class="od-lang" style="display:flex;align-items:center;gap:5px;font-size:13px">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          CSV
        </button>
        <?php endif; ?>
        <button class="od-lang" id="al-lang-btn" type="button">FR</button>
        <div class="od-avatar" title="<?= e($user['full_name']) ?>"><?= e($initials) ?></div>
      </div>
    </header>

    <!-- Stat cards -->
    <div class="al-stat-grid">
      <article class="od-card stat">
        <span class="stat-icon blue">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </span>
        <div><small data-i18n="stat_period">Événements (période)</small><strong><?= $total ?></strong></div>
      </article>
      <article class="od-card stat">
        <span class="stat-icon green">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </span>
        <div><small data-i18n="stat_today">Aujourd'hui</small><strong><?= $todayCount ?></strong></div>
      </article>
      <article class="od-card stat">
        <span class="stat-icon amber">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <div><small data-i18n="stat_users">Utilisateurs actifs</small><strong><?= count(array_unique(array_column($logs, 'user_id'))) ?></strong></div>
      </article>
    </div>

    <!-- Filter bar -->
    <div class="al-filter-bar">
      <div class="od-card" style="padding:14px 18px">
        <form method="GET" class="al-filter-form" id="al-filter-form">
          <div class="al-date-group">
            <label data-i18n="from">Du</label>
            <input type="date" name="from" id="al-from" value="<?= e($from) ?>">
          </div>
          <div class="al-date-group">
            <label data-i18n="to">Au</label>
            <input type="date" name="to" id="al-to" value="<?= e($to) ?>">
          </div>
          <button type="submit" class="al-btn al-btn-primary" data-i18n="filter">Filtrer</button>
          <button type="button" class="al-btn al-btn-outline" id="al-today" data-i18n="today">Aujourd'hui</button>
          <button type="button" class="al-btn al-btn-outline" id="al-month" data-i18n="this_month">Ce mois</button>
          <input type="search" id="logSearch" class="al-search" placeholder="Rechercher action, utilisateur…">
        </form>
      </div>
    </div>

    <!-- Logs table -->
    <div class="al-table-card">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div class="al-card-head">
          <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0" data-i18n="table_title">Journal d'activité</h2>
          <span style="font-size:12px;color:#6B7280"><?= $total ?> <span data-i18n="events">événement(s)</span></span>
        </div>
        <div class="od-table-wrap">
          <table class="od-table" id="logsTable">
            <thead>
              <tr>
                <th data-i18n="col_date">Date</th>
                <th data-i18n="col_user">Utilisateur</th>
                <th data-i18n="col_action">Action</th>
                <th data-i18n="col_detail">Détail</th>
                <th data-i18n="col_ip">IP</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($logs): foreach ($logs as $log): ?>
            <tr data-search="<?= e(strtolower(($log['user_name']??'').($log['action']??'').($log['description']??''))) ?>">
              <td style="font-size:12.5px;color:#6B7280;white-space:nowrap">
                <?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
              </td>
              <td style="font-weight:600;font-size:13px"><?= e($log['user_name'] ?? 'Système') ?></td>
              <td>
                <div class="al-action-cell">
                  <?= actionSvgIcon($log['action'] ?? '') ?>
                  <span class="action-pill"><?= e($log['action'] ?? '—') ?></span>
                </div>
              </td>
              <td style="font-size:13px;color:#6B7280;max-width:280px"><?= e($log['description'] ?? '—') ?></td>
              <td style="font-size:12px;color:#94A3B8"><?= e($log['ip_address'] ?? '—') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="od-empty" data-i18n="no_logs">Aucune activité enregistrée sur cette période.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
/* Sidebar hamburger is handled by Sidebar.php JS */

/* Quick date filters */
document.getElementById('al-today')?.addEventListener('click', () => {
    const t = new Date().toISOString().split('T')[0];
    document.getElementById('al-from').value = t;
    document.getElementById('al-to').value   = t;
    document.getElementById('al-filter-form').submit();
});
document.getElementById('al-month')?.addEventListener('click', () => {
    const now   = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const today = now.toISOString().split('T')[0];
    document.getElementById('al-from').value = first;
    document.getElementById('al-to').value   = today;
    document.getElementById('al-filter-form').submit();
});

/* Search */
document.getElementById('logSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#logsTable tbody tr').forEach(row => {
        row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
    });
});

/* Export CSV */
function exportCSV() {
    const rows = [['Date','Utilisateur','Action','Détail','IP']];
    document.querySelectorAll('#logsTable tbody tr').forEach(row => {
        if (row.style.display === 'none') return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            rows.push([0,1,2,3,4].map(i => '"' + cells[i].textContent.trim().replace(/"/g,'""') + '"'));
        }
    });
    const csv = rows.map(r => r.join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = 'activite_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}

/* FR/EN toggle */
const alDict = {
  fr: {
    page_title:'Journal d\'Activité', page_sub:'Traçabilité complète — historique de toutes les actions',
    stat_period:'Événements (période)', stat_today:'Aujourd\'hui', stat_users:'Utilisateurs actifs',
    from:'Du', to:'Au', filter:'Filtrer', today:'Aujourd\'hui', this_month:'Ce mois',
    table_title:'Journal d\'activité', events:'événement(s)',
    col_date:'Date', col_user:'Utilisateur', col_action:'Action', col_detail:'Détail', col_ip:'IP',
    no_logs:'Aucune activité enregistrée sur cette période.',
    nav_dashboard:'Dashboard', nav_products:'Produits', nav_stock_in:'Stock entrant',
    nav_stock_out:'Stock sortant', nav_attendance:'Présence', nav_notifications:'Notifications',
    nav_employees:'Employés', nav_validations:'Validations', nav_reports:'Rapports',
    nav_activity:'Activité', nav_subscription:'Abonnement', nav_settings:'Paramètres',
    nav_change_pin:'Changer PIN', nav_logout:'Déconnexion',
  },
  en: {
    page_title:'Activity Log', page_sub:'Full audit trail — history of all business actions',
    stat_period:'Events (period)', stat_today:'Today', stat_users:'Active users',
    from:'From', to:'To', filter:'Filter', today:'Today', this_month:'This month',
    table_title:'Activity Log', events:'event(s)',
    col_date:'Date', col_user:'User', col_action:'Action', col_detail:'Details', col_ip:'IP',
    no_logs:'No activity recorded for this period.',
    nav_dashboard:'Dashboard', nav_products:'Products', nav_stock_in:'Stock In',
    nav_stock_out:'Stock Out', nav_attendance:'Attendance', nav_notifications:'Notifications',
    nav_employees:'Employees', nav_validations:'Approvals', nav_reports:'Reports',
    nav_activity:'Activity', nav_subscription:'Subscription', nav_settings:'Settings',
    nav_change_pin:'Change PIN', nav_logout:'Logout',
  },
};
let alLang = localStorage.getItem('lt_lang') || 'fr';
function alApplyLang() {
  const d = alDict[alLang] || alDict.fr;
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n; if(d[k]!==undefined) el.textContent = d[k];
  });
  const btn = document.getElementById('al-lang-btn');
  if(btn) btn.textContent = alLang === 'fr' ? 'EN' : 'FR';
  const s = document.getElementById('logSearch');
  if(s) s.placeholder = alLang === 'fr' ? 'Rechercher action, utilisateur…' : 'Search action, user…';
}
document.getElementById('al-lang-btn')?.addEventListener('click', () => {
  alLang = alLang === 'fr' ? 'en' : 'fr';
  localStorage.setItem('lt_lang', alLang);
  alApplyLang();
});
alApplyLang();
</script>
</body>
</html>
