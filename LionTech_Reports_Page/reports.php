<?php
/* ============================================================
   reports.php — LionTech Business Manager
   Role: owner, manager
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user = currentUser();
$pdo  = getDB();
$businessId = (int)($user['business_id'] ?? 0);

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
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
if (!$business) { header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized'); exit; }

$subscriptionStatus = $business['subscription_status'] ?? 'trial';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = ($subscriptionStatus === 'expired') || ($expiresAt && strtotime($expiresAt) < time());

/* Date range */
$quickRange = $_GET['range'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

if ($quickRange === 'today') {
    $from = $to = date('Y-m-d');
} elseif ($quickRange === 'month') {
    $from = date('Y-m-01');
    $to   = date('Y-m-d');
} elseif ($quickRange === 'year') {
    $from = date('Y-01-01');
    $to   = date('Y-m-d');
} elseif ($quickRange === 'last30') {
    $from = date('Y-m-d', strtotime('-30 days'));
    $to   = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
$params = [':bid'=>$businessId, ':from'=>$from.' 00:00:00', ':to'=>$to.' 23:59:59'];

/* ── Stats ── */
$totalProducts = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status <> 'archived'", [':bid'=>$businessId]);
$totalQty      = safeCount($pdo, "SELECT COALESCE(SUM(quantity),0) FROM products WHERE business_id=:bid AND status <> 'archived'", [':bid'=>$businessId]);
$lowStock      = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status<>'archived' AND quantity <= COALESCE(low_stock_level,5)", [':bid'=>$businessId]);
$outStock      = safeCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id=:bid AND status<>'archived' AND quantity <= 0", [':bid'=>$businessId]);

/* Stock In — try stock_in_requests first, fallback to stock_in */
$stockInQty = safeCount($pdo,
    "SELECT COALESCE(SUM(quantity),0) FROM stock_in_requests WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to",
    $params
);
if ($stockInQty === 0) {
    $stockInQty = safeCount($pdo,
        "SELECT COALESCE(SUM(quantity),0) FROM stock_in WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to",
        $params
    );
}

/* Stock Out — try stock_movements then stock_out */
$stockOutQty = safeCount($pdo,
    "SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE business_id=:bid AND movement_type='stock_out' AND created_at BETWEEN :from AND :to",
    $params
);
if ($stockOutQty === 0) {
    $stockOutQty = safeCount($pdo,
        "SELECT COALESCE(SUM(quantity),0) FROM stock_out WHERE business_id=:bid AND status='approved' AND created_at BETWEEN :from AND :to",
        $params
    );
}

/* Pending approvals */
$pendingApprovals = safeCount($pdo, "SELECT COUNT(*) FROM stock_in_requests WHERE business_id=:bid AND status='pending'", [':bid'=>$businessId]);
if ($pendingApprovals === 0) {
    $pendingApprovals = safeCount($pdo, "SELECT COUNT(*) FROM stock_in WHERE business_id=:bid AND status='pending'", [':bid'=>$businessId]);
}

/* Attendance — use employee_attendance (new table) first */
$attendanceRows = safeRows($pdo,
    "SELECT u.full_name,
            COUNT(ea.attendance_id) AS days_present,
            COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ea.clock_in_at, ea.clock_out_at))/60, 0) AS hours_worked,
            SUM(CASE WHEN ea.status IN('pending_review','rejected') THEN 1 ELSE 0 END) AS late_count
     FROM employee_attendance ea
     JOIN users u ON u.user_id = ea.user_id
     WHERE ea.business_id=:bid AND ea.clock_in_at BETWEEN :from AND :to
     GROUP BY u.user_id, u.full_name
     ORDER BY hours_worked DESC LIMIT 10",
    $params
);
/* Fallback to old attendance table */
if (empty($attendanceRows)) {
    $attendanceRows = safeRows($pdo,
        "SELECT u.full_name,
                COUNT(a.attendance_id) AS days_present,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.clock_in, a.clock_out))/60, 0) AS hours_worked,
                0 AS late_count
         FROM attendance a
         JOIN users u ON u.user_id = a.user_id
         WHERE a.business_id=:bid AND a.clock_in BETWEEN :from AND :to
         GROUP BY u.user_id, u.full_name
         ORDER BY hours_worked DESC LIMIT 10",
        $params
    );
}

/* Low stock rows */
$lowRows = safeRows($pdo,
    "SELECT name, category, quantity, unit, low_stock_level
     FROM products WHERE business_id=:bid AND status<>'archived' AND quantity <= COALESCE(low_stock_level,5)
     ORDER BY quantity ASC LIMIT 12",
    [':bid'=>$businessId]
);

/* Top products */
$topProducts = safeRows($pdo,
    "SELECT p.name, p.category,
            COALESCE(SUM(sm.quantity),0) AS moved_qty
     FROM stock_movements sm
     JOIN products p ON p.product_id = sm.product_id
     WHERE sm.business_id=:bid AND sm.movement_type='stock_out' AND sm.created_at BETWEEN :from AND :to
     GROUP BY p.product_id, p.name, p.category
     ORDER BY moved_qty DESC LIMIT 8",
    $params
);
/* Fallback to old stock_out table */
if (empty($topProducts)) {
    $topProducts = safeRows($pdo,
        "SELECT p.name, p.category, COALESCE(SUM(so.quantity),0) AS moved_qty
         FROM stock_out so JOIN products p ON p.product_id=so.product_id
         WHERE so.business_id=:bid AND so.status='approved' AND so.created_at BETWEEN :from AND :to
         GROUP BY p.product_id, p.name, p.category
         ORDER BY moved_qty DESC LIMIT 8",
        $params
    );
}

/* Recent movements */
$recentMovements = safeRows($pdo,
    "SELECT movement_type AS type, p.name AS product_name, sm.quantity, 'approved' AS status,
            sm.created_at, u.full_name AS user_name
     FROM stock_movements sm
     JOIN products p ON p.product_id = sm.product_id
     LEFT JOIN users u ON u.user_id = sm.created_by
     WHERE sm.business_id=:bid AND sm.created_at BETWEEN :from AND :to
     ORDER BY sm.created_at DESC LIMIT 15",
    $params
);
/* Fallback */
if (empty($recentMovements)) {
    $recentMovements = safeRows($pdo,
        "SELECT 'stock_in' AS type, p.name AS product_name, si.quantity, si.status, si.created_at, u.full_name AS user_name
         FROM stock_in_requests si JOIN products p ON p.product_id=si.product_id
         LEFT JOIN users u ON u.user_id=si.created_by
         WHERE si.business_id=:bid AND si.created_at BETWEEN :from AND :to
         ORDER BY si.created_at DESC LIMIT 15",
        $params
    );
}

/* Placeholders if empty */
if (empty($attendanceRows)) {
    $attendanceRows = [['full_name'=>'Aucun employé','days_present'=>0,'hours_worked'=>0,'late_count'=>0]];
}
if (empty($topProducts)) {
    $topProducts = [['name'=>'Aucun produit','category'=>'—','moved_qty'=>0]];
}

$chartData = [
    'stock' => ['labels'=>['Stock Entrant','Stock Sortant'], 'values'=>[$stockInQty ?: 0, $stockOutQty ?: 0]],
    'top'   => ['labels'=>array_column($topProducts,'name'), 'values'=>array_map('intval', array_column($topProducts,'moved_qty'))]
];

$initials='';
foreach(explode(' ', trim($user['full_name'] ?? 'O')) as $w){ $initials .= strtoupper(substr($w,0,1)); }
$initials = substr($initials ?: 'O', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<meta name="theme-color" content="#0B1F3A"/>
<title>Rapports — LionTech</title>
<link rel="icon" href="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"/>
<link rel="stylesheet" href="reports.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  window.REPORT_CHARTS = <?= json_encode($chartData) ?>;
  window.REPORT_FROM   = '<?= e($from) ?>';
  window.REPORT_TO     = '<?= e($to) ?>';
</script>
</head>
<body>
<div class="rp-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="rp-main">
    <header class="rp-topbar">
      <button class="rp-menu-btn" id="rp-menu-btn" aria-label="Open menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="rp-title">
        <h1 data-i18n="page_title">Rapports</h1>
        <p><?= e($business['business_name'] ?? 'Business') ?> · <span data-i18n="page_subtitle">Analyse inventaire, présence et mouvements de stock</span></p>
      </div>
      <div class="rp-top-actions">
        <button class="rp-lang" id="rp-lang-btn" type="button">FR</button>
        <div class="rp-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($isExpired): ?>
    <section class="rp-warning">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <div>
        <strong data-i18n="expired_title">Abonnement expiré</strong>
        <p data-i18n="expired_text">Vous pouvez consulter les rapports, mais les actions d'inventaire restent limitées jusqu'au renouvellement.</p>
      </div>
    </section>
    <?php endif; ?>

    <!-- Filters -->
    <section class="rp-filter-card">
      <form method="GET" class="rp-filters" id="rp-filter-form">
        <div>
          <label data-i18n="from">Du</label>
          <input type="date" name="from" id="rp-from" value="<?= e($from) ?>">
        </div>
        <div>
          <label data-i18n="to">Au</label>
          <input type="date" name="to" id="rp-to" value="<?= e($to) ?>">
        </div>
        <button type="submit" class="rp-btn primary" data-i18n="apply">Appliquer</button>
        <button type="button" class="rp-btn" id="quick-today" data-i18n="today">Aujourd'hui</button>
        <button type="button" class="rp-btn" id="quick-month" data-i18n="this_month">Ce mois</button>
        <button type="button" class="rp-btn" id="quick-year"  data-i18n="this_year">Cette année</button>
        <button type="button" class="rp-btn rp-btn-icon" id="export-csv" title="Export CSV">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          CSV
        </button>
        <button type="button" class="rp-btn rp-btn-icon" id="print-pdf" title="Imprimer PDF">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          PDF
        </button>
      </form>
      <div class="rp-active-range">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span data-i18n="period">Période</span> : <strong><?= e(date('d/m/Y', strtotime($from))) ?> → <?= e(date('d/m/Y', strtotime($to))) ?></strong>
      </div>
    </section>

    <!-- Stats -->
    <section class="rp-stats">
      <article class="rp-card stat">
        <span class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span>
        <div><small data-i18n="products">Produits</small><strong><?= number_format($totalProducts) ?></strong></div>
      </article>
      <article class="rp-card stat">
        <span class="stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg></span>
        <div><small data-i18n="stock_in">Stock entrant</small><strong><?= number_format($stockInQty) ?></strong></div>
      </article>
      <article class="rp-card stat">
        <span class="stat-icon red"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg></span>
        <div><small data-i18n="stock_out">Stock sortant</small><strong><?= number_format($stockOutQty) ?></strong></div>
      </article>
      <article class="rp-card stat">
        <span class="stat-icon amber"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg></span>
        <div><small data-i18n="low_stock">Stock faible</small><strong><?= number_format($lowStock) ?></strong></div>
      </article>
      <article class="rp-card stat">
        <span class="stat-icon purple"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
        <div><small data-i18n="pending">En attente</small><strong><?= number_format($pendingApprovals) ?></strong></div>
      </article>
      <article class="rp-card stat">
        <span class="stat-icon gray"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></span>
        <div><small data-i18n="out_stock">Rupture</small><strong><?= number_format($outStock) ?></strong></div>
      </article>
    </section>

    <!-- Charts -->
    <section class="rp-grid charts">
      <article class="rp-card">
        <div class="rp-card-head">
          <h2 data-i18n="stock_movement">Mouvement de stock</h2>
          <p data-i18n="stock_movement_sub">Entrées vs sorties</p>
        </div>
        <canvas id="stockChart"></canvas>
      </article>
      <article class="rp-card">
        <div class="rp-card-head">
          <h2 data-i18n="top_products">Produits les plus sortis</h2>
          <p data-i18n="top_products_sub">Selon la période choisie</p>
        </div>
        <canvas id="topChart"></canvas>
      </article>
    </section>

    <!-- Tables -->
    <section class="rp-grid">
      <article class="rp-card">
        <div class="rp-card-head">
          <h2 data-i18n="low_stock_report">Rapport stock faible</h2>
          <p data-i18n="low_stock_sub">Produits à réapprovisionner</p>
        </div>
        <div class="rp-table-wrap">
          <table class="rp-table">
            <thead><tr>
              <th data-i18n="col_product">Produit</th>
              <th data-i18n="col_category">Catégorie</th>
              <th data-i18n="col_qty">Quantité</th>
              <th data-i18n="col_min">Minimum</th>
            </tr></thead>
            <tbody>
              <?php if($lowRows): foreach($lowRows as $r): ?>
              <tr>
                <td><?= e($r['name']) ?></td>
                <td><?= e($r['category']??'—') ?></td>
                <td><span class="badge danger"><?= e($r['quantity']) ?> <?= e($r['unit']??'') ?></span></td>
                <td><?= e($r['low_stock_level'] ?? 5) ?></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="4" class="rp-empty-row" data-i18n="no_low_stock">Aucun produit en stock faible.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </article>

      <article class="rp-card">
        <div class="rp-card-head">
          <h2 data-i18n="attendance_report">Rapport présence employés</h2>
          <p data-i18n="attendance_sub">Heures travaillées et retards</p>
        </div>
        <div class="rp-table-wrap">
          <table class="rp-table">
            <thead><tr>
              <th data-i18n="col_employee">Employé</th>
              <th data-i18n="col_days">Présence</th>
              <th data-i18n="col_hours">Heures</th>
              <th data-i18n="col_late">Retards</th>
            </tr></thead>
            <tbody>
              <?php foreach($attendanceRows as $r): ?>
              <tr>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['days_present']) ?> <span data-i18n="days">jour(s)</span></td>
                <td><?= number_format((float)$r['hours_worked'],1) ?></td>
                <td><?= e($r['late_count']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
    </section>

    <!-- Recent movements -->
    <section class="rp-card movements">
      <div class="rp-card-head">
        <h2 data-i18n="recent_movements">Historique récent</h2>
        <p data-i18n="recent_sub">Dernières entrées et sorties de stock</p>
      </div>
      <div class="rp-table-wrap">
        <table class="rp-table" id="report-table">
          <thead><tr>
            <th data-i18n="col_type">Type</th>
            <th data-i18n="col_product">Produit</th>
            <th data-i18n="col_qty">Quantité</th>
            <th data-i18n="col_status">Statut</th>
            <th data-i18n="col_user">Utilisateur</th>
            <th data-i18n="col_date">Date</th>
          </tr></thead>
          <tbody>
            <?php if($recentMovements): foreach($recentMovements as $m):
              $isIn = in_array($m['type'], ['stock_in','IN'], true);
            ?>
            <tr>
              <td><span class="badge <?= $isIn?'success':'danger' ?>"><?= $isIn?'Entrée':'Sortie' ?></span></td>
              <td><?= e($m['product_name']) ?></td>
              <td><?= e($m['quantity']) ?></td>
              <td><?= e($m['status']) ?></td>
              <td><?= e($m['user_name'] ?? '-') ?></td>
              <td><?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="rp-empty-row" data-i18n="no_movements">Aucun mouvement trouvé pour cette période.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>
<script src="reports.js"></script>
</body>
</html>
