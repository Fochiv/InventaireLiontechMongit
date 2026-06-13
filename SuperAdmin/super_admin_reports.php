<?php
/* ============================================================
   super_admin_reports.php — LionTech Super Admin
   Platform-wide reports: revenue, payments, businesses, activity
   Path: C:\Xampp\htdocs\InventoryLiontech\SuperAdmin\super_admin_reports.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_SUPER_ADMIN]);
$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function safeQ(PDO $pdo, string $sql, array $p=[]): mixed {
    try { $s=$pdo->prepare($sql); $s->execute($p); return $s; }
    catch(Throwable $e){ return null; }
}

/* ── Date filter ── */
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

/* ── Platform stats ── */
$totalBusinesses = (int)(safeQ($pdo,"SELECT COUNT(*) FROM businesses")?->fetchColumn() ?: 0);
$activeBusinesses= (int)(safeQ($pdo,"SELECT COUNT(*) FROM businesses WHERE subscription_status='active'")?->fetchColumn() ?: 0);
$expiredBusinesses=(int)(safeQ($pdo,"SELECT COUNT(*) FROM businesses WHERE subscription_status IN('expired','suspended')")?->fetchColumn() ?: 0);
$totalUsers      = (int)(safeQ($pdo,"SELECT COUNT(*) FROM users WHERE role!='super_admin'")?->fetchColumn() ?: 0);

/* ── Payment stats (liontech_payments) ── */
$totalRevenue   = 0; $approvedCount = 0; $pendingCount = 0; $rejectedCount = 0;
$payments       = [];
try {
    $totalRevenue  = (float)(safeQ($pdo,"SELECT COALESCE(SUM(amount),0) FROM liontech_payments WHERE status='approved' AND DATE(approved_at) BETWEEN ? AND ?",[$from,$to])?->fetchColumn() ?: 0);
    $approvedCount = (int)(safeQ($pdo,"SELECT COUNT(*) FROM liontech_payments WHERE status='approved' AND DATE(approved_at) BETWEEN ? AND ?",[$from,$to])?->fetchColumn() ?: 0);
    $pendingCount  = (int)(safeQ($pdo,"SELECT COUNT(*) FROM liontech_payments WHERE status='pending'")?->fetchColumn() ?: 0);
    $rejectedCount = (int)(safeQ($pdo,"SELECT COUNT(*) FROM liontech_payments WHERE status='rejected' AND DATE(approved_at) BETWEEN ? AND ?",[$from,$to])?->fetchColumn() ?: 0);

    $s = safeQ($pdo,"SELECT lp.*, b.business_name, u.full_name AS owner_name FROM liontech_payments lp JOIN businesses b ON b.business_id=lp.business_id LEFT JOIN users u ON u.business_id=lp.business_id AND u.role='business_owner' WHERE DATE(lp.created_at) BETWEEN ? AND ? ORDER BY lp.created_at DESC LIMIT 50",[$from,$to]);
    $payments = $s ? $s->fetchAll() : [];
} catch(Throwable $e){}

/* ── Businesses by status ── */
$bizByStatus = [];
try {
    $s = safeQ($pdo,"SELECT subscription_status, COUNT(*) AS cnt FROM businesses GROUP BY subscription_status");
    $bizByStatus = $s ? $s->fetchAll() : [];
} catch(Throwable $e){}

/* ── Expiring soon (next 30 days) ── */
$expiringSoon = [];
try {
    $s = safeQ($pdo,"SELECT b.business_name, b.subscription_expires_at, b.subscription_status, u.full_name AS owner, u.phone FROM businesses b LEFT JOIN users u ON u.business_id=b.business_id AND u.role='business_owner' WHERE b.subscription_expires_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 30 DAY) ORDER BY b.subscription_expires_at ASC");
    $expiringSoon = $s ? $s->fetchAll() : [];
} catch(Throwable $e){}

/* ── Recent activity ── */
$recentActivity = [];
try {
   $s = safeQ($pdo,"SELECT al.*, u.full_name AS user_name, b.business_name 
    FROM activity_logs al 
    LEFT JOIN users u ON u.user_id=al.user_id 
    LEFT JOIN businesses b ON b.business_id=al.business_id 
    WHERE DATE(al.created_at) BETWEEN ? AND ?
      AND (al.user_id IS NULL OR u.role IN ('super_admin','business_owner','manager'))
    ORDER BY al.created_at DESC LIMIT 30",[$from,$to]);
    $recentActivity = $s ? $s->fetchAll() : [];
} catch(Throwable $e){}

$currentPage = basename($_SERVER['PHP_SELF']);
$initials = '';
foreach(explode(' ',$user['full_name']) as $w) $initials .= strtoupper($w[0]??'');
$initials = substr($initials,0,2);

$url = APP_URL;

$methodLabels = [
    'orange_money'  => 'Orange Money',
    'mtn_momo'      => 'MTN MoMo',
    'bank_transfer' => 'Virement Bancaire',
    'cash'          => 'Espèces',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Reports — LionTech Super Admin</title>
  <link rel="stylesheet" href="super_admin.css"/>
</head>
<body>
<div class="sa-layout">

<?php include __DIR__ . '/_sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<div class="sa-main">
  <header class="sa-topbar">
    <button class="sa-hamburger" id="sa-hamburger"><?= saIcon('menu') ?></button>
    <div style="font-size:16px;font-weight:700;color:#0B1F3A">Rapports Plateforme</div>
    <div class="sa-topbar-right">
      <button onclick="window.print()"
        style="display:flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;padding:8px 14px;font-size:13px;cursor:pointer;font-family:inherit">
        <?= saIcon('printer') ?> Imprimer
      </button>
    </div>
  </header>

  <main class="sa-content">

    <!-- Page header -->
    <div class="sa-page-header">
      <div>
        <h1 class="sa-page-title">Rapports Plateforme</h1>
        <p class="sa-page-sub">Vue globale — revenus, paiements, businesses et activité</p>
      </div>
    </div>

    <!-- Date filter -->
    <div class="sa-card" style="margin-bottom:20px;padding:16px 20px">
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
        <button type="submit"
          style="padding:9px 18px;background:#0B1F3A;color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">
          Appliquer
        </button>
        <button type="button" onclick="setRange('month')"
          style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">
          Ce mois
        </button>
        <button type="button" onclick="setRange('year')"
          style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">
          Cette année
        </button>
      </form>
    </div>

    <!-- Stat cards -->
    <div class="sa-cards-grid" style="margin-bottom:20px">
      <div class="sa-stat-card green">
        <div class="sa-stat-icon green"><?= saIcon('money', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= number_format($totalRevenue,0,'.',',') ?></div>
          <div class="sa-stat-label">Revenu XAF (période)</div>
        </div>
      </div>
      <div class="sa-stat-card navy">
        <div class="sa-stat-icon navy"><?= saIcon('check', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= $approvedCount ?></div>
          <div class="sa-stat-label">Paiements approuvés</div>
        </div>
      </div>
      <div class="sa-stat-card amber">
        <div class="sa-stat-icon amber"><?= saIcon('clock', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= $pendingCount ?></div>
          <div class="sa-stat-label">En attente</div>
          <?php if($pendingCount>0): ?>
          <a href="<?= $url ?>/SuperAdmin/payment_review.php" style="font-size:11px;color:#D4A017;font-weight:700;text-decoration:none">→ Valider</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="sa-stat-card red">
        <div class="sa-stat-icon red"><?= saIcon('x-circle', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= $rejectedCount ?></div>
          <div class="sa-stat-label">Paiements rejetés</div>
        </div>
      </div>
      <div class="sa-stat-card teal">
        <div class="sa-stat-icon teal"><?= saIcon('building', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= $activeBusinesses ?> / <?= $totalBusinesses ?></div>
          <div class="sa-stat-label">Businesses actifs</div>
        </div>
      </div>
      <div class="sa-stat-card blue">
        <div class="sa-stat-icon blue"><?= saIcon('users', 22) ?></div>
        <div>
          <div class="sa-stat-val"><?= $totalUsers ?></div>
          <div class="sa-stat-label">Total utilisateurs</div>
        </div>
      </div>
    </div>

    <!-- Two columns -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

      <!-- Businesses by status -->
      <div class="sa-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9">
          <div class="sa-card-title">Businesses par statut</div>
        </div>
        <div style="padding:16px">
          <?php
          $statusColors = ['active'=>'#166534','expired'=>'#991B1B','trial'=>'#92400E','suspended'=>'#6B21A8'];
          $statusBg     = ['active'=>'#F0FDF4','expired'=>'#FEF2F2','trial'=>'#FEF3C7','suspended'=>'#F5F3FF'];
          foreach($bizByStatus as $row):
            $st = $row['subscription_status'];
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-radius:10px;margin-bottom:8px;background:<?= $statusBg[$st]??'#F8FAFC' ?>">
            <span style="font-size:13.5px;font-weight:600;color:<?= $statusColors[$st]??'#374151' ?>"><?= ucfirst(e($st)) ?></span>
            <span style="font-size:18px;font-weight:900;color:<?= $statusColors[$st]??'#374151' ?>"><?= (int)$row['cnt'] ?></span>
          </div>
          <?php endforeach; ?>
          <?php if(empty($bizByStatus)): ?>
          <p style="text-align:center;color:#6B7280;font-size:13px;padding:20px">Aucun business.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Expiring soon -->
      <div class="sa-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center">
          <div class="sa-card-title">Expirent dans 30 jours</div>
          <span style="background:#FEF3C7;color:#92400E;font-size:11px;font-weight:700;border-radius:50px;padding:3px 10px"><?= count($expiringSoon) ?></span>
        </div>
        <div style="overflow-y:auto;max-height:260px">
          <?php if($expiringSoon): foreach($expiringSoon as $biz): ?>
          <div style="padding:12px 18px;border-bottom:1px solid #F9FAFB;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:13.5px;font-weight:600;color:#0B1F3A"><?= e($biz['business_name']) ?></div>
              <div style="font-size:12px;color:#6B7280"><?= e($biz['owner']??'—') ?> · <?= e($biz['phone']??'—') ?></div>
            </div>
            <div style="text-align:right">
              <div style="font-size:12px;font-weight:700;color:#DC2626"><?= e(date('d/m/Y',strtotime($biz['subscription_expires_at']))) ?></div>
              <div style="font-size:11px;color:#6B7280"><?= ceil((strtotime($biz['subscription_expires_at'])-time())/86400) ?> jours</div>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div style="padding:28px;text-align:center;color:#6B7280;font-size:13px">Aucun abonnement n'expire bientôt.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Payment history table -->
    <div class="sa-card" style="padding:0;overflow:hidden;margin-bottom:20px">
      <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center">
        <div>
          <div class="sa-card-title">Historique des paiements</div>
          <div class="sa-card-sub"><?= count($payments) ?> paiement(s) sur la période</div>
        </div>
        <button onclick="exportCSV()"
          style="display:flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;padding:8px 14px;font-size:13px;cursor:pointer;font-family:inherit">
          <?= saIcon('download') ?> Export CSV
        </button>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px" id="paymentsTable">
          <thead>
            <tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Date</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Business</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Propriétaire</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Montant</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Durée</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Méthode</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Référence</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Statut</th>
            </tr>
          </thead>
          <tbody>
          <?php if($payments): foreach($payments as $p): ?>
          <tr style="border-bottom:1px solid #F1F5F9">
            <td style="padding:10px 14px;font-size:12px;color:#6B7280"><?= e(date('d/m/Y H:i',strtotime($p['created_at']))) ?></td>
            <td style="padding:10px 14px;font-weight:600"><?= e($p['business_name']) ?></td>
            <td style="padding:10px 14px"><?= e($p['owner_name']??'—') ?></td>
            <td style="padding:10px 14px;font-weight:700"><?= number_format((float)$p['amount'],0,'.',',') ?> XAF</td>
            <td style="padding:10px 14px"><?= (int)$p['months_paid'] ?> mois</td>
            <td style="padding:10px 14px"><?= e($methodLabels[$p['payment_method']]??$p['payment_method']) ?></td>
            <td style="padding:10px 14px;font-family:monospace;font-size:12px"><?= e($p['transaction_reference']??'—') ?></td>
            <td style="padding:10px 14px">
              <span style="padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;
                background:<?= $p['status']==='approved'?'#DCFCE7':($p['status']==='pending'?'#FEF3C7':'#FEE2E2') ?>;
                color:<?= $p['status']==='approved'?'#166534':($p['status']==='pending'?'#92400E':'#991B1B') ?>">
                <?= $p['status']==='approved'?'Approuvé':($p['status']==='pending'?'En attente':'Rejeté') ?>
              </span>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" style="padding:28px;text-align:center;color:#6B7280">Aucun paiement sur cette période.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent activity -->
    <div class="sa-card" style="padding:0;overflow:hidden">
      <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9">
        <div class="sa-card-title">Activité récente</div>
        <div class="sa-card-sub"><?= count($recentActivity) ?> événement(s) sur la période</div>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Date</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Utilisateur</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Business</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Action</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Description</th>
            </tr>
          </thead>
          <tbody>
          <?php if($recentActivity): foreach($recentActivity as $act): ?>
          <tr style="border-bottom:1px solid #F1F5F9">
            <td style="padding:10px 14px;font-size:12px;color:#6B7280;white-space:nowrap"><?= e(date('d/m/Y H:i',strtotime($act['created_at']))) ?></td>
            <td style="padding:10px 14px;font-weight:600"><?= e($act['user_name']??'Système') ?></td>
            <td style="padding:10px 14px;color:#6B7280"><?= e($act['business_name']??'—') ?></td>
            <td style="padding:10px 14px"><code style="background:#F1F5F9;padding:2px 7px;border-radius:5px;font-size:11px"><?= e($act['action']??'—') ?></code></td>
            <td style="padding:10px 14px;color:#6B7280"><?= e($act['description']??'—') ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" style="padding:28px;text-align:center;color:#6B7280">Aucune activité sur cette période.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
</div>

<script>
function setRange(type) {
    const now   = new Date();
    const fmt   = d => d.toISOString().split('T')[0];
    const from  = document.querySelector('[name=from]');
    const to    = document.querySelector('[name=to]');
    if (type === 'month') {
        from.value = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
        to.value   = fmt(now);
    } else if (type === 'year') {
        from.value = now.getFullYear() + '-01-01';
        to.value   = fmt(now);
    }
}

function exportCSV() {
    const rows = [['Date','Business','Propriétaire','Montant XAF','Durée','Méthode','Référence','Statut']];
    document.querySelectorAll('#paymentsTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8) {
            rows.push([...cells].map(c => c.textContent.trim()));
        }
    });
    const csv = rows.map(r => r.map(c => '"'+c.replace(/"/g,'""')+'"').join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download= 'rapport_paiements_<?= date('Ymd') ?>.csv';
    a.click();
}

/* Sidebar hamburger */
document.getElementById('sa-hamburger')?.addEventListener('click', () => {
    document.getElementById('sa-sidebar').classList.add('open');
    document.getElementById('sa-overlay').classList.add('active');
});
document.getElementById('sa-sidebar-close')?.addEventListener('click', () => {
    document.getElementById('sa-sidebar').classList.remove('open');
    document.getElementById('sa-overlay').classList.remove('active');
});
document.getElementById('sa-overlay')?.addEventListener('click', () => {
    document.getElementById('sa-sidebar').classList.remove('open');
    document.getElementById('sa-overlay').classList.remove('active');
});
</script>
</body>
</html>