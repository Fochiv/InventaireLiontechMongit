<?php
/* ============================================================
   owner_dashboard.php — Tally Business Manager
   Role: business_owner / manager
   Landing page after a business owner logs in.
   ============================================================ */

require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user = currentUser();
$pdo  = getDB();
$businessId = (int)($user['business_id'] ?? 0);

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

/* ── Business info ── */
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = :bid LIMIT 1");
$stmt->execute([':bid' => $businessId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

/* ── Subscription status ── */
$subscriptionStatus = $business['subscription_status'] ?? 'trial';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = ($subscriptionStatus === 'expired') || ($expiresAt && strtotime($expiresAt) < time());
$subscriptionMessage = '';

if ($isExpired) {
    $subscriptionMessage = 'Votre abonnement est expiré. Vous pouvez voir le tableau de bord, mais les actions d\'inventaire sont limitées.';
} elseif ($expiresAt) {
    $daysLeft = ceil((strtotime($expiresAt) - time()) / 86400);
    if ($daysLeft <= 7) {
        $subscriptionMessage = "Votre abonnement expire dans {$daysLeft} jour(s). Pensez à renouveler.";
    }
}

/* ── Basic stats ── */
function dbCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$totalProducts = dbCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id = :bid", [':bid'=>$businessId]);
$totalQty      = dbCount($pdo, "SELECT COALESCE(SUM(quantity),0) FROM products WHERE business_id = :bid", [':bid'=>$businessId]);
$employeeCount = dbCount($pdo, "SELECT COUNT(*) FROM users WHERE business_id = :bid AND role IN ('manager','employee') AND status='active'", [':bid'=>$businessId]);

try {
    $lowStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE business_id = :bid AND quantity <= COALESCE(low_stock_level, 5)");
    $lowStmt->execute([':bid'=>$businessId]);
    $lowStockCount = (int)$lowStmt->fetchColumn();
} catch (Throwable $e) {
    $lowStockCount = dbCount($pdo, "SELECT COUNT(*) FROM products WHERE business_id = :bid AND quantity <= 5", [':bid'=>$businessId]);
}

/* ── Products preview ── */
try {
    $stmt = $pdo->prepare("SELECT product_id, name, sku, category, quantity, unit_price, image_url, low_stock_level FROM products WHERE business_id = :bid ORDER BY created_at DESC LIMIT 8");
    $stmt->execute([':bid'=>$businessId]);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $stmt = $pdo->prepare("SELECT product_id, name, sku, category, quantity, unit_price FROM products WHERE business_id = :bid ORDER BY created_at DESC LIMIT 8");
    $stmt->execute([':bid'=>$businessId]);
    $products = $stmt->fetchAll();
}

/* ── Recent activity ── */
try {
    $stmt = $pdo->prepare("
    SELECT action, description, icon, created_at
    FROM activity_logs
    WHERE business_id = :bid
      AND action != 'business_created'
    ORDER BY created_at DESC
    LIMIT 6
");
    $stmt->execute([':bid'=>$businessId]);
    $activities = $stmt->fetchAll();
} catch (Throwable $e) {
    $activities = [];
}

/* ── Chart data ── */
try {
    $stmt = $pdo->prepare("SELECT COALESCE(category, 'Autre') AS category, SUM(quantity) AS total FROM products WHERE business_id = :bid GROUP BY category ORDER BY total DESC LIMIT 6");
    $stmt->execute([':bid'=>$businessId]);
    $catRows = $stmt->fetchAll();
} catch (Throwable $e) {
    $catRows = [];
}

$chartData = [
    'labels' => array_column($catRows, 'category'),
    'values' => array_map('intval', array_column($catRows, 'total')),
];

if (empty($chartData['labels'])) {
    $chartData = [
        'labels' => ['Boissons', 'Nourriture', 'Produits', 'Autres'],
        'values' => [0, 0, 0, 0],
    ];
}

$initials = '';
foreach (explode(' ', trim($user['full_name'])) as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
}
$initials = substr($initials ?: 'O', 0, 2);

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

/* ── URL shortcuts ── */
$url = APP_URL;
$products_url      = $url . '/Produit/products.php';
 $stock_in_url  = $url . '/LionTech_Stock_In_Page/stock_in.php';
$stock_out_url     = $url . '/stockout_stockfinis/stock_out.php';
$employees_url = $url . '/LionTech_Employee_Management/employees.php';
$employee_dash_url = $url . '/LionTech_Employee_Dashboard/employee_dashboard.php';
$reports_url       = $url . '/LionTech_Complete_MVP_Remaining_Pages/reports.php';
$notifications_url = $url . '/LionTech_Complete_MVP_Remaining_Pages/notifications.php';
$settings_url      = $url . '/LionTech_Complete_MVP_Remaining_Pages/settings.php';
$billing_url       = $url . '/LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php';
$logout_url        = $url . '/Logininventory/logout.php';
$dashboard_url     = $url . '/LionTech_Owner_Dashboard/owner_dashboard.php';
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<meta name="theme-color" content="#0B1F3A"/>
<title>Dashboard Propriétaire — Tally</title>
<link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
<link rel="stylesheet" href="owner_dashboard.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>window.OWNER_CHART_DATA = <?= json_encode($chartData) ?>;</script>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">

  <!-- Sidebar -->
 <?php
$sidebarFile = __DIR__ . '/Sidebar.php';
if (file_exists($sidebarFile)) {
    include $sidebarFile;
} else {
    echo '<p style="color:red">Sidebar.php NOT FOUND at: ' . $sidebarFile . '</p>';
}
?>

  <main class="od-main">
    <!-- Topbar -->
    <header class="od-topbar">
      <button class="od-menu-btn" id="od-menu-btn" aria-label="Open menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="od-business-title">
        <h1>Tableau de bord</h1>
        <p>Bienvenue, <?= e($user['full_name']) ?> · <?= e($business['business_name']) ?></p>
      </div>
      <div class="od-top-actions">
        <button class="od-lang" id="od-lang-btn" type="button">FR</button>
        <div class="od-avatar" title="<?= e($user['full_name']) ?>"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($subscriptionMessage): ?>
    <section class="od-sub-warning <?= $isExpired ? 'expired' : 'soon' ?>">
      <div class="od-warning-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
      <div>
        <strong>Attention abonnement</strong>
        <p><?= e($subscriptionMessage) ?></p>
      </div>
      <a class="od-warning-btn" href="<?= $billing_url ?>">Renouveler</a>
    </section>
    <?php endif; ?>

    <!-- Quick actions -->
    <section class="od-quick-actions">
      <a class="od-action <?= $isExpired ? 'disabled' : '' ?>" href="<?= $products_url ?>?action=add">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
        <div><strong data-i18n="add_product">Ajouter produit</strong><small data-i18n="add_product_sub">Créer un nouvel article</small></div>
      </a>
      <a class="od-action <?= $isExpired ? 'disabled' : '' ?>" href="<?= $stock_in_url ?>">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg></span>
        <div><strong data-i18n="stock_in">Stock entrant</strong><small data-i18n="stock_in_sub">Ajouter une livraison</small></div>
      </a>
      <a class="od-action <?= $isExpired ? 'disabled' : '' ?>" href="<?= $stock_out_url ?>">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg></span>
        <div><strong data-i18n="stock_out">Stock sortant</strong><small data-i18n="stock_out_sub">Vente, perte ou usage</small></div>
      </a>
      <a class="od-action" href="<?= $employees_url ?>?action=add">
        <span><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <div><strong data-i18n="add_employee">Ajouter employé</strong><small data-i18n="add_employee_sub">Optionnel si vous travaillez seul</small></div>
      </a>
    </section>

    <!-- Stats -->
    <section class="od-stats">
      <article class="od-card stat"><span class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></span><div><small data-i18n="total_products">Produits</small><strong><?= number_format($totalProducts) ?></strong></div></article>
      <article class="od-card stat"><span class="stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span><div><small data-i18n="total_stock">Quantité totale</small><strong><?= number_format($totalQty) ?></strong></div></article>
      <article class="od-card stat"><span class="stat-icon amber"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg></span><div><small data-i18n="low_stock">Stock faible</small><strong><?= number_format($lowStockCount) ?></strong></div></article>
      <article class="od-card stat"><span class="stat-icon purple"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><div><small data-i18n="employees">Employés</small><strong><?= number_format($employeeCount) ?></strong></div></article>
    </section>

    <section class="od-grid">

      <!-- Products preview -->
      <article class="od-card od-products-card">
        <div class="od-card-head">
          <div>
            <h2>Produits récents</h2>
            <p>Aperçu des derniers articles ajoutés</p>
          </div>
          <a href="<?= $products_url ?>" class="od-link">Voir tout</a>
        </div>

        <?php if (empty($products)): ?>
        <div class="od-empty">
          <div><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
          <strong>Aucun produit pour le moment</strong>
          <p>Commencez par ajouter vos produits pour suivre votre stock.</p>
          <a href="<?= $products_url ?>?action=add" class="od-primary <?= $isExpired ? 'disabled' : '' ?>">
            Ajouter le premier produit
          </a>
        </div>
        <?php else: ?>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Qté</th>
                <th>Prix</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p):
                $lowLevel = (float)($p['low_stock_level'] ?? 5);
                $qty      = (float)($p['quantity'] ?? 0);
                $low      = $qty <= $lowLevel;
                $img      = $p['image_url'] ?? '';
              ?>
              <tr>
                <td>
                  <div class="product-cell">
                    <div class="product-img"><?= $img ? '<img src="'.e($img).'" alt="">' : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>' ?></div>
                    <div><strong><?= e($p['name']) ?></strong><small><?= e($p['sku'] ?? '') ?></small></div>
                  </div>
                </td>
                <td><?= e($p['category'] ?? 'Autre') ?></td>
                <td><strong><?= number_format($qty) ?></strong></td>
                <td><?= number_format((float)($p['unit_price'] ?? 0), 0) ?> XAF</td>
                <td><span class="od-badge <?= $low ? 'danger' : 'success' ?>"><?= $low ? 'Faible' : 'OK' ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </article>

      <!-- Chart -->
      <article class="od-card od-chart-card">
        <div class="od-card-head">
          <div>
            <h2>Stock par catégorie</h2>
            <p>Vue simple de vos quantités</p>
          </div>
        </div>
        <canvas id="odStockChart" height="240"></canvas>
      </article>

    </section>

    <section class="od-bottom-grid">

      <article class="od-card">
        <div class="od-card-head">
          <div>
            <h2>Espace employés</h2>
            <p>Le système fonctionne aussi sans employés.</p>
          </div>
        </div>
        <?php if ($employeeCount === 0): ?>
        <div class="od-info-box">
          <strong>Mode business individuel actif</strong>
          <p>Vous pouvez gérer vos produits et votre stock vous-même.</p>
        </div>
        <?php else: ?>
        <div class="od-info-box success">
          <strong><?= number_format($employeeCount) ?> employé(s) actif(s)</strong>
          <p>Vous pouvez suivre les présences et les activités de votre équipe.</p>
        </div>
        <?php endif; ?>
      </article>

      <article class="od-card">
        <div class="od-card-head">
          <div>
            <h2>Activité récente</h2>
            <p>Dernières actions dans votre business</p>
          </div>
        </div>
        <div class="od-activity-list">
          <?php if (empty($activities)): ?>
          <div class="od-empty mini"><strong>Aucune activité récente</strong></div>
          <?php else: foreach ($activities as $a): ?>
          <div class="od-activity">
            <span class="od-activity-icon"><?php
              $actIco = $a['icon'] ?? $a['action'] ?? '';
              $actI = strtolower($actIco);
              if(str_contains($actI,'stock_in'))         echo '<svg style="color:#16A34A" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="8" x2="12" y2="16"/></svg>';
              elseif(str_contains($actI,'stock_out'))    echo '<svg style="color:#DC2626" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 12 12 8 8 12"/><line x1="12" y1="16" x2="12" y2="8"/></svg>';
              elseif(str_contains($actI,'login'))        echo '<svg style="color:#8B5CF6" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>';
              elseif(str_contains($actI,'product'))      echo '<svg style="color:#0B1F3A" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
              elseif(str_contains($actI,'employee'))     echo '<svg style="color:#2563EB" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
              elseif(str_contains($actI,'approv'))       echo '<svg style="color:#16A34A" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
              else                                        echo '<svg style="color:#6B7280" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
            ?></span>
            <div>
              <strong><?= e($a['action']) ?></strong>
              <p><?= e($a['description']) ?></p>
              <small><?= e(date('d M Y H:i', strtotime($a['created_at']))) ?></small>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </article>

    </section>

  </main>
</div>
<script src="owner_dashboard.js"></script>
</body>
</html>