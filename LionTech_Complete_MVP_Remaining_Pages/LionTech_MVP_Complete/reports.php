<?php
/* ============================================================
   reports.php — LionTech Business Manager
   Owner: full access | Manager: view only (no export)
   FIXED: correct table names (stock_in_requests, stock_out_requests)
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
function safeCount(PDO $pdo, string $sql, array $params=[]): int {
    try { $s=$pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
    catch(Throwable $e){ return 0; }
}
function safeRows(PDO $pdo, string $sql, array $params=[]): array {
    try { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch(Throwable $e){ return []; }
}

$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id=? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

$subStatus = $business['subscription_status'] ?? 'trial';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = ($subStatus==='expired') || ($expiresAt && strtotime($expiresAt)<time());

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $to   = date('Y-m-d');
$p = [':bid'=>$businessId,':from'=>$from.' 00:00:00',':to'=>$to.' 23:59:59'];

/* Stats */
$totalProducts = safeCount($pdo,"SELECT COUNT(*) FROM products WHERE business_id=:bid AND status<>'archived'",[':bid'=>$businessId]);
$lowStock      = safeCount($pdo,"SELECT COUNT(*) FROM products WHERE business_id=:bid AND status<>'archived' AND quantity<=COALESCE(low_stock_level,5)",[':bid'=>$businessId]);
$outStock      = safeCount($pdo,"SELECT COUNT(*) FROM products WHERE business_id=:bid AND status<>'archived' AND quantity<=0",[':bid'=>$businessId]);

/* FIXED: use stock_in_requests and stock_out_requests */
$stockInQty       = safeCount($pdo,"SELECT COALESCE(SUM(quantity),0) FROM stock_in_requests WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to",$p);
$stockOutQty      = safeCount($pdo,"SELECT COALESCE(SUM(quantity),0) FROM stock_out_requests WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to",$p);
$pendingApprovals = safeCount($pdo,"SELECT COUNT(*) FROM stock_in_requests WHERE business_id=:bid AND status='pending'",[':bid'=>$businessId])
                  + safeCount($pdo,"SELECT COUNT(*) FROM stock_out_requests WHERE business_id=:bid AND status='pending'",[':bid'=>$businessId]);

$lowRows    = safeRows($pdo,"SELECT name,category,quantity,unit,low_stock_level FROM products WHERE business_id=:bid AND status<>'archived' AND quantity<=COALESCE(low_stock_level,5) ORDER BY quantity ASC LIMIT 12",[':bid'=>$businessId]);
$topProducts= safeRows($pdo,"SELECT p.name,p.category,COALESCE(SUM(so.quantity),0) AS moved_qty FROM stock_out_requests so JOIN products p ON p.product_id=so.product_id WHERE so.business_id=:bid AND so.status='approved' AND so.created_at BETWEEN :from AND :to GROUP BY p.product_id,p.name,p.category ORDER BY moved_qty DESC LIMIT 8",$p);

$attendanceRows = safeRows($pdo,"SELECT u.full_name,COUNT(a.attendance_id) AS days_present,COALESCE(SUM(TIMESTAMPDIFF(MINUTE,a.clock_in_at,a.clock_out_at))/60,0) AS hours_worked FROM employee_attendance a JOIN users u ON u.user_id=a.user_id WHERE a.business_id=:bid AND a.clock_in_at BETWEEN :from AND :to GROUP BY u.user_id,u.full_name ORDER BY hours_worked DESC LIMIT 10",$p);

/* Recent movements from both tables */
$recentMovements = safeRows($pdo,"
    SELECT 'IN' AS type, p.name AS product_name, r.quantity, r.status, r.created_at, u.full_name AS user_name
    FROM stock_in_requests r
    JOIN products p ON p.product_id=r.product_id
    LEFT JOIN users u ON u.user_id=r.created_by
    WHERE r.business_id=:bid AND r.created_at BETWEEN :from AND :to
    UNION ALL
    SELECT 'OUT' AS type, p.name AS product_name, r.quantity, r.status, r.created_at, u.full_name AS user_name
    FROM stock_out_requests r
    JOIN products p ON p.product_id=r.product_id
    LEFT JOIN users u ON u.user_id=r.created_by
    WHERE r.business_id=:bid AND r.created_at BETWEEN :from AND :to
    ORDER BY created_at DESC LIMIT 20",$p);

$chartData = [
    'stock' => ['labels'=>['Stock Entrant','Stock Sortant'],'values'=>[$stockInQty?:0,$stockOutQty?:0]],
    'top'   => ['labels'=>array_column($topProducts,'name'),'values'=>array_map('intval',array_column($topProducts,'moved_qty'))],
];
if (empty($chartData['top']['labels'])) {
    $chartData['top'] = ['labels'=>['Aucune donnée'],'values'=>[0]];
}

$initials='';
foreach(explode(' ',trim($user['full_name']??'O')) as $w) $initials.=strtoupper(substr($w,0,1));
$initials=substr($initials?:'O',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Rapports — LionTech</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>window.REPORT_CHARTS = <?= json_encode($chartData) ?>;</script>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Rapports</h1>
        <p><?= e($business['business_name']??'Business') ?> · Analyse inventaire, présence et mouvements</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($isExpired): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E">
      ⚠️ Abonnement expiré. Vous pouvez consulter les rapports mais les actions sont limitées.
    </div>
    <?php endif; ?>

    <!-- Date filter -->
    <div style="padding:20px 24px 0">
      <div class="od-card" style="padding:18px 20px">
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
          <button type="submit" class="od-primary" style="padding:9px 18px;border:none;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">Appliquer</button>
          <button type="button" onclick="setRange('today')" style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">Aujourd'hui</button>
          <button type="button" onclick="setRange('month')" style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">Ce mois</button>
          <?php if ($isOwner): ?>
          <button type="button" onclick="window.print()" style="padding:9px 14px;background:#fff;border:1.5px solid #E5E7EB;border-radius:9px;font-size:13px;cursor:pointer;font-family:inherit">🖨️ PDF</button>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Stats -->
    <div style="padding:16px 24px 0;display:grid;grid-template-columns:repeat(6,1fr);gap:12px">
      <div class="od-card stat"><span class="stat-icon blue">📦</span><div><small>Produits</small><strong><?= number_format($totalProducts) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon green">📥</span><div><small>Stock entrant</small><strong><?= number_format($stockInQty) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon" style="background:#FEE2E2">📤</span><div><small>Stock sortant</small><strong><?= number_format($stockOutQty) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon amber">⚠️</span><div><small>Stock faible</small><strong><?= number_format($lowStock) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon purple">⏳</span><div><small>En attente</small><strong><?= number_format($pendingApprovals) ?></strong></div></div>
      <div class="od-card stat"><span class="stat-icon" style="background:#F1F5F9">🚫</span><div><small>Rupture</small><strong><?= number_format($outStock) ?></strong></div></div>
    </div>

    <!-- Charts -->
    <div style="padding:16px 24px 0;display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div class="od-card" style="padding:20px">
        <div class="od-card-head"><div><h2>Mouvement de stock</h2><p>Entrées vs sorties sur la période</p></div></div>
        <canvas id="stockChart" height="220"></canvas>
      </div>
      <div class="od-card" style="padding:20px">
        <div class="od-card-head"><div><h2>Produits les plus sortis</h2><p>Selon la période choisie</p></div></div>
        <canvas id="topChart" height="220"></canvas>
      </div>
    </div>

    <!-- Tables -->
    <div style="padding:16px 24px 0;display:grid;grid-template-columns:1fr 1fr;gap:20px">

      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9"><h2 style="font-size:14px;font-weight:700;color:#0B1F3A;margin:0">Rapport stock faible</h2></div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead><tr><th>Produit</th><th>Catégorie</th><th>Quantité</th><th>Minimum</th></tr></thead>
            <tbody>
            <?php if($lowRows): foreach($lowRows as $r): ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['category']??'—') ?></td>
              <td><span class="od-badge danger"><?= e($r['quantity']) ?> <?= e($r['unit']??'') ?></span></td>
              <td><?= e($r['low_stock_level']??5) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="od-empty">Aucun produit en stock faible.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9"><h2 style="font-size:14px;font-weight:700;color:#0B1F3A;margin:0">Rapport présence employés</h2></div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead><tr><th>Employé</th><th>Jours</th><th>Heures</th></tr></thead>
            <tbody>
            <?php if($attendanceRows): foreach($attendanceRows as $r): ?>
            <tr>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['days_present']) ?></td>
              <td><?= number_format((float)$r['hours_worked'],1) ?>h</td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3" class="od-empty">Aucune présence enregistrée.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Recent movements -->
    <div style="padding:16px 24px 40px">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9"><h2 style="font-size:14px;font-weight:700;color:#0B1F3A;margin:0">Historique récent des mouvements</h2></div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead><tr><th>Type</th><th>Produit</th><th>Quantité</th><th>Statut</th><th>Par</th><th>Date</th></tr></thead>
            <tbody>
            <?php if($recentMovements): foreach($recentMovements as $m): ?>
            <tr>
              <td><span class="od-badge <?=$m['type']==='IN'?'success':'danger'?>"><?=$m['type']==='IN'?'Entrée':'Sortie'?></span></td>
              <td><?= e($m['product_name']) ?></td>
              <td><?= e($m['quantity']) ?></td>
              <td><?= e($m['status']) ?></td>
              <td><?= e($m['user_name']??'—') ?></td>
              <td><?= e(date('d/m/Y H:i',strtotime($m['created_at']))) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="od-empty">Aucun mouvement pour cette période.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>
<script>
function setRange(type) {
    const today = new Date();
    const fmt = d => d.toISOString().split('T')[0];
    if (type === 'today') {
        document.querySelector('[name=from]').value = fmt(today);
        document.querySelector('[name=to]').value   = fmt(today);
    } else {
        const first = new Date(today.getFullYear(), today.getMonth(), 1);
        document.querySelector('[name=from]').value = fmt(first);
        document.querySelector('[name=to]').value   = fmt(today);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const d = window.REPORT_CHARTS;
    if (d && window.Chart) {
        new Chart(document.getElementById('stockChart'), {
            type:'doughnut',
            data:{labels:d.stock.labels,datasets:[{data:d.stock.values,borderWidth:0,backgroundColor:['#1A9E7A','#D4A017']}]},
            options:{responsive:true,plugins:{legend:{position:'bottom'}},cutout:'60%'}
        });
        new Chart(document.getElementById('topChart'), {
            type:'bar',
            data:{labels:d.top.labels,datasets:[{data:d.top.values,backgroundColor:'#0B1F3A',borderRadius:6}]},
            options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
        });
    }
});
</script>
</body>
</html>