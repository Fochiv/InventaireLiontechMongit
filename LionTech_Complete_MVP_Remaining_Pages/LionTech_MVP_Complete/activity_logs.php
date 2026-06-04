<?php
/* ============================================================
   activity_logs.php — LionTech Business Manager
   Owner: full access | Manager: view only
   Path: LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$role       = $user['role'] ?? '';
$isOwner    = ($role === ROLE_BUSINESS_OWNER);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ── Date filter ── */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

/* ── Load logs ── */
$logs = [];
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name AS user_name
        FROM activity_logs al
        LEFT JOIN users u ON u.user_id = al.user_id
        WHERE al.business_id = ?
          AND al.created_at BETWEEN ? AND ?
        ORDER BY al.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$businessId, $from.' 00:00:00', $to.' 23:59:59']);
    $logs = $stmt->fetchAll();
    $total = count($logs);
} catch (Throwable $e) {}

/* ── Today's count ── */
$todayCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE business_id=? AND DATE(created_at)=CURDATE()");
    $stmt->execute([$businessId]);
    $todayCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'O', 0, 2);

/* Action icon map */
function actionIcon(string $action): string {
    $map = [
        'clock_in'         => '🟢',
        'clock_out'        => '🔴',
        'business_created' => '🏢',
        'employee_created' => '👤',
        'stock_in'         => '📥',
        'stock_out'        => '📤',
        'login'            => '🔑',
        'logout'           => '🚪',
        'product_added'    => '📦',
        'approval'         => '✅',
    ];
    foreach ($map as $key => $icon) {
        if (str_contains(strtolower($action), $key)) return $icon;
    }
    return 'ℹ️';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Journal d'Activité — LionTech</title>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Journal d'Activité</h1>
        <p>Traçabilité et sécurité — historique de ce qui s'est passé dans le business</p>
      </div>
      <div class="od-top-actions">
        <?php if ($isOwner): ?>
        <button onclick="exportCSV()" style="background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;padding:8px 14px;font-size:13px;cursor:pointer;font-family:inherit">📄 Export CSV</button>
        <?php endif; ?>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <!-- Stat cards -->
    <div style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
      <div class="od-card stat"><span class="stat-icon blue">📋</span><div><small>Événements (période)</small><strong><?= $total ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon green">📅</span><div><small>Aujourd'hui</small><strong><?= $todayCount ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon amber">👥</span><div><small>Utilisateurs actifs</small><strong><?= count(array_unique(array_column($logs, 'user_id'))) ?></strong></div></div>
    </div>

    <!-- Date filter -->
    <div style="padding:16px 24px 0">
      <div class="od-card" style="padding:16px 18px">
        <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:8px">
            <label style="font-size:12px;font-weight:600;color:#6B7280">Du</label>
            <input type="date" name="from" value="<?= e($from) ?>"
              style="padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit"/>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <label style="font-size:12px;font-weight:600;color:#6B7280">Au</label>
            <input type="date" name="to" value="<?= e($to) ?>"
              style="padding:8px 12px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit"/>
          </div>
          <button type="submit" class="od-primary" style="padding:9px 18px;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">Filtrer</button>
          <button type="button" onclick="setToday()" style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">Aujourd'hui</button>
          <button type="button" onclick="setMonth()" style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">Ce mois</button>
          <input type="search" id="logSearch" placeholder="Rechercher..."
            style="padding:9px 13px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;font-family:inherit;margin-left:auto"/>
        </form>
      </div>
    </div>

    <!-- Logs table -->
    <div style="padding:16px 24px 40px">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center">
          <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Journal d'activité</h2>
          <span style="font-size:12px;color:#6B7280"><?= $total ?> événement(s) sur la période</span>
        </div>
        <div class="od-table-wrap">
          <table class="od-table" id="logsTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>Utilisateur</th>
                <th>Action</th>
                <th>Détail</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($logs): foreach ($logs as $log): ?>
            <tr data-search="<?= e(strtolower(($log['user_name']??'').($log['action']??'').($log['description']??''))) ?>">
              <td style="font-size:12.5px;color:#6B7280;white-space:nowrap">
                <?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?>
              </td>
              <td>
                <strong style="font-size:13px"><?= e($log['user_name'] ?? 'Système') ?></strong>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:7px">
                  <span><?= actionIcon($log['action'] ?? '') ?></span>
                  <span style="font-size:12.5px;font-weight:600;color:#374151"><?= e($log['action'] ?? '—') ?></span>
                </div>
              </td>
              <td style="font-size:13px;color:#6B7280;max-width:280px">
                <?= e($log['description'] ?? '—') ?>
              </td>
              <td style="font-size:12px;color:#94A3B8">
                <?= e($log['ip_address'] ?? '—') ?>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
              <td colspan="5" class="od-empty">Aucune activité enregistrée sur cette période.</td>
            </tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
function setToday() {
    const t = new Date().toISOString().split('T')[0];
    document.querySelector('[name=from]').value = t;
    document.querySelector('[name=to]').value   = t;
}
function setMonth() {
    const now  = new Date();
    const first= new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    const today= now.toISOString().split('T')[0];
    document.querySelector('[name=from]').value = first;
    document.querySelector('[name=to]').value   = today;
}
document.getElementById('logSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#logsTable tbody tr').forEach(row => {
        row.style.display = (row.dataset.search || '').includes(q) ? '' : 'none';
    });
});
function exportCSV() {
    const rows = [['Date','Utilisateur','Action','Détail','IP']];
    document.querySelectorAll('#logsTable tbody tr').forEach(row => {
        if (row.style.display === 'none') return;
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            rows.push([cells[0].textContent.trim(), cells[1].textContent.trim(), cells[2].textContent.trim(), cells[3].textContent.trim(), cells[4].textContent.trim()]);
        }
    });
    const csv = rows.map(r => r.map(c => '"'+c.replace(/"/g,'""')+'"').join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = 'activite_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}
</script>
</body>
</html>