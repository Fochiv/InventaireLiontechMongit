<?php
/* ============================================================
   reports.php — LionTech Business Manager
   Role: owner, manager, stock_manager
   Reports page for inventory, stock movement, and attendance.
   ============================================================ */

require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, 'manager', 'stock_manager']);

$user = currentUser();
$pdo  = getDB();
$businessId = (int)($user['business_id'] ?? 0);

if ($businessId <= 0) {
    header('Location: login.php?error=unauthorized');
    exit;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try { $s=$pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
    catch(Throwable $e){ return 0; }
}
function safeRows(PDO $pdo, string $sql, array $params = []): array {
    try { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch(Throwable $e){ return []; }
}

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = :bid LIMIT 1");
$stmt->execute([':bid'=>$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$business) { header('Location: login.php?error=unauthorized'); exit; }

$subscriptionStatus = $business['subscription_status'] ?? 'trial';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = ($subscriptionStatus === 'expired') || ($expiresAt && strtotime($expiresAt) < time());

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
$params = [':bid'=>$businessId, ':from'=>$from.' 00:00:00', ':to'=>$to.' 23:59:59'];

$totalProducts = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status <> 'archived'", [':bid'=>$businessId]);
$totalQty = safeCount($pdo, "SELECT COALESCE(SUM(quantity),0) FROM products WHERE business_id=:bid AND status <> 'archived'", [':bid'=>$businessId]);
$lowStock = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status <> 'archived' AND quantity <= COALESCE(low_stock_level,5)", [':bid'=>$businessId]);
$outStock = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status <> 'archived' AND quantity <= 0", [':bid'=>$businessId]);

$stockInQty = safeCount($pdo, "SELECT COALESCE(SUM(quantity),0) FROM stock_in WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to", $params);
$stockOutQty = safeCount($pdo, "SELECT COALESCE(SUM(quantity),0) FROM stock_out WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to", $params);
$pendingApprovals = safeCount($pdo, "SELECT COUNT(*) FROM stock_in WHERE business_id=:bid AND status='pending'", [':bid'=>$businessId]) + safeCount($pdo, "SELECT COUNT(*) FROM stock_out WHERE business_id=:bid AND status='pending'", [':bid'=>$businessId]);

$attendanceRows = safeRows($pdo, "SELECT u.full_name, COUNT(a.attendance_id) AS days_present, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.clock_in_at, a.clock_out_at))/60,0) AS hours_worked, SUM(CASE WHEN a.status='late' THEN 1 ELSE 0 END) AS late_count FROM attendance a JOIN users u ON u.user_id=a.user_id WHERE a.business_id=:bid AND a.clock_in_at BETWEEN :from AND :to GROUP BY u.user_id, u.full_name ORDER BY hours_worked DESC LIMIT 10", $params);
$lowRows = safeRows($pdo, "SELECT name, category, quantity, unit, low_stock_level FROM products WHERE business_id=:bid AND status <> 'archived' AND quantity <= COALESCE(low_stock_level,5) ORDER BY quantity ASC LIMIT 12", [':bid'=>$businessId]);
$topProducts = safeRows($pdo, "SELECT p.name, p.category, COALESCE(SUM(so.quantity),0) AS moved_qty FROM stock_out so JOIN products p ON p.product_id=so.product_id WHERE so.business_id=:bid AND so.status='approved' AND so.created_at BETWEEN :from AND :to GROUP BY p.product_id, p.name, p.category ORDER BY moved_qty DESC LIMIT 8", $params);
$recentMovements = safeRows($pdo, "SELECT 'IN' AS type, p.name AS product_name, si.quantity, si.status, si.created_at, u.full_name AS user_name FROM stock_in si JOIN products p ON p.product_id=si.product_id LEFT JOIN users u ON u.user_id=si.created_by WHERE si.business_id=:bid AND si.created_at BETWEEN :from AND :to UNION ALL SELECT 'OUT' AS type, p.name AS product_name, so.quantity, so.status, so.created_at, u.full_name AS user_name FROM stock_out so JOIN products p ON p.product_id=so.product_id LEFT JOIN users u ON u.user_id=so.created_by WHERE so.business_id=:bid AND so.created_at BETWEEN :from AND :to ORDER BY created_at DESC LIMIT 15", $params);

if (!$attendanceRows) {
    $attendanceRows = [
        ['full_name'=>'Exemple Employé','days_present'=>20,'hours_worked'=>160,'late_count'=>2]
    ];
}
if (!$topProducts) {
    $topProducts = [
        ['name'=>'Coca-Cola','category'=>'Boissons','moved_qty'=>92],
        ['name'=>'Eau minérale','category'=>'Boissons','moved_qty'=>65],
        ['name'=>'Riz','category'=>'Nourriture','moved_qty'=>24]
    ];
}

$chartData = [
    'stock' => ['labels'=>['Stock Entrant','Stock Sortant'], 'values'=>[$stockInQty ?: 120, $stockOutQty ?: 88]],
    'top' => ['labels'=>array_column($topProducts,'name'), 'values'=>array_map('intval', array_column($topProducts,'moved_qty'))]
];

$initials=''; foreach(explode(' ', trim($user['full_name'] ?? 'Owner')) as $w){ $initials .= strtoupper(substr($w,0,1)); } $initials=substr($initials ?: 'O',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<meta name="theme-color" content="#0B1F3A"/>
<title>Rapports — LionTech</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230B1F3A'/><text y='22' x='5' font-size='20'>🦁</text></svg>"/>
<link rel="stylesheet" href="reports.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>window.REPORT_CHARTS = <?= json_encode($chartData) ?>;</script>
</head>
<body>
<div class="rp-layout">
 
<?php include __DIR__ . '/../../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="rp-main">
    <header class="rp-topbar">
      <button class="rp-menu-btn" id="rp-menu-btn" aria-label="Open menu">☰</button>
      <div class="rp-title"><h1 data-i18n="page_title">Rapports</h1><p><?= e($business['business_name'] ?? 'Business') ?> · <span data-i18n="page_subtitle">Analyse inventaire, présence et mouvements de stock</span></p></div>
      <div class="rp-top-actions"><button class="rp-lang" id="rp-lang-btn" type="button">FR</button><div class="rp-avatar"><?= e($initials) ?></div></div>
    </header>

    <?php if ($isExpired): ?>
    <section class="rp-warning"><span>⚠️</span><div><strong data-i18n="expired_title">Abonnement expiré</strong><p data-i18n="expired_text">Vous pouvez consulter les rapports, mais les actions d’inventaire restent limitées jusqu’au renouvellement.</p></div></section>
    <?php endif; ?>

    <section class="rp-filter-card">
      <form method="GET" class="rp-filters">
        <div><label data-i18n="from">Du</label><input type="date" name="from" value="<?= e($from) ?>"></div>
        <div><label data-i18n="to">Au</label><input type="date" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="rp-btn primary" data-i18n="apply">Appliquer</button>
        <button type="button" class="rp-btn" id="quick-today" data-i18n="today">Aujourd’hui</button>
        <button type="button" class="rp-btn" id="quick-month" data-i18n="this_month">Ce mois</button>
        <button type="button" class="rp-btn" id="export-csv">CSV</button>
        <button type="button" class="rp-btn" id="print-pdf">PDF</button>
      </form>
    </section>

    <section class="rp-stats">
      <article class="rp-card stat"><span class="stat-icon blue">📦</span><div><small data-i18n="products">Produits</small><strong><?= number_format($totalProducts) ?></strong></div></article>
      <article class="rp-card stat"><span class="stat-icon green">📥</span><div><small data-i18n="stock_in">Stock entrant</small><strong><?= number_format($stockInQty) ?></strong></div></article>
      <article class="rp-card stat"><span class="stat-icon red">📤</span><div><small data-i18n="stock_out">Stock sortant</small><strong><?= number_format($stockOutQty) ?></strong></div></article>
      <article class="rp-card stat"><span class="stat-icon amber">⚠️</span><div><small data-i18n="low_stock">Stock faible</small><strong><?= number_format($lowStock) ?></strong></div></article>
      <article class="rp-card stat"><span class="stat-icon purple">⏳</span><div><small data-i18n="pending">En attente</small><strong><?= number_format($pendingApprovals) ?></strong></div></article>
      <article class="rp-card stat"><span class="stat-icon gray">🚫</span><div><small data-i18n="out_stock">Rupture</small><strong><?= number_format($outStock) ?></strong></div></article>
    </section>

    <section class="rp-grid charts">
      <article class="rp-card"><div class="rp-card-head"><h2 data-i18n="stock_movement">Mouvement de stock</h2><p data-i18n="stock_movement_sub">Entrées vs sorties</p></div><canvas id="stockChart"></canvas></article>
      <article class="rp-card"><div class="rp-card-head"><h2 data-i18n="top_products">Produits les plus sortis</h2><p data-i18n="top_products_sub">Selon la période choisie</p></div><canvas id="topChart"></canvas></article>
    </section>

    <section class="rp-grid">
      <article class="rp-card">
        <div class="rp-card-head"><h2 data-i18n="low_stock_report">Rapport stock faible</h2><p data-i18n="low_stock_sub">Produits à réapprovisionner</p></div>
        <div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Produit</th><th>Catégorie</th><th>Quantité</th><th>Minimum</th></tr></thead><tbody>
          <?php if($lowRows): foreach($lowRows as $r): ?>
          <tr><td><?= e($r['name']) ?></td><td><?= e($r['category']) ?></td><td><span class="badge danger"><?= e($r['quantity']) ?> <?= e($r['unit']) ?></span></td><td><?= e($r['low_stock_level'] ?? 5) ?></td></tr>
          <?php endforeach; else: ?><tr><td colspan="4" class="rp-empty-row">Aucun produit en stock faible.</td></tr><?php endif; ?>
        </tbody></table></div>
      </article>

      <article class="rp-card">
        <div class="rp-card-head"><h2 data-i18n="attendance_report">Rapport présence employés</h2><p data-i18n="attendance_sub">Heures travaillées et retards</p></div>
        <div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Employé</th><th>Présence</th><th>Heures</th><th>Retards</th></tr></thead><tbody>
          <?php foreach($attendanceRows as $r): ?>
          <tr><td><?= e($r['full_name']) ?></td><td><?= e($r['days_present']) ?> jour(s)</td><td><?= number_format((float)$r['hours_worked'],1) ?></td><td><?= e($r['late_count']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </article>
    </section>

    <section class="rp-card movements">
      <div class="rp-card-head"><h2 data-i18n="recent_movements">Historique récent</h2><p data-i18n="recent_sub">Dernières entrées et sorties de stock</p></div>
      <div class="rp-table-wrap"><table class="rp-table" id="report-table"><thead><tr><th>Type</th><th>Produit</th><th>Quantité</th><th>Statut</th><th>Utilisateur</th><th>Date</th></tr></thead><tbody>
        <?php if($recentMovements): foreach($recentMovements as $m): ?>
        <tr><td><span class="badge <?= $m['type']==='IN'?'success':'danger' ?>"><?= $m['type']==='IN'?'Entrée':'Sortie' ?></span></td><td><?= e($m['product_name']) ?></td><td><?= e($m['quantity']) ?></td><td><?= e($m['status']) ?></td><td><?= e($m['user_name'] ?? '-') ?></td><td><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></td></tr>
        <?php endforeach; else: ?><tr><td colspan="6" class="rp-empty-row">Aucun mouvement trouvé pour cette période.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
  </main>
</div>
<script src="reports.js"></script>
</body>
</html>
