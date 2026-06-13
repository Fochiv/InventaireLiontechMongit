<?php
/* ============================================================
   Vente.php — LionTech Business Manager / Sales Control Page
   Owner + Manager only · FR/EN · Mobile responsive tables
   Path: C:\Xampp\htdocs\InventoryLiontech\Vente_cashier\Vente.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user    = currentUser();
$pdo     = getDB();
$bizId   = (int)($user['business_id'] ?? 0);
$role    = $user['role'] ?? '';
$isOwner = $role === ROLE_BUSINESS_OWNER;
$url     = APP_URL;

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function xaf($v){ return number_format((float)$v, 0, ',', ' ') . ' XAF'; }
function dmy($v){ return $v ? date('d/m/Y', strtotime($v)) : '—'; }
function dmyhm($v){ return $v ? date('d/m/Y H:i', strtotime($v)) : '—'; }
function n0($v){ return number_format((float)$v, 0, ',', ' '); }

/* User initials */
$fullName = trim($user['full_name'] ?? 'User');
$initials = '';
foreach (explode(' ', $fullName) as $w) { $initials .= strtoupper(substr($w, 0, 1)); }
$initials = substr($initials ?: 'U', 0, 2);

/* Business */
$bizName = 'LionTech';
try {
    $s = $pdo->prepare('SELECT business_name FROM businesses WHERE business_id = ? LIMIT 1');
    $s->execute([$bizId]);
    $bizName = $s->fetchColumn() ?: $bizName;
} catch (Throwable $e) {}

/* Manager permissions */
$defaultPerms = [
    'cashier_perf' => true,
    'stock_in'     => true,
    'stock_out'    => true,
    'receipts'     => true,
    'fraud'        => true,
    'money'        => false
];
$managerPerms = $defaultPerms;
try {
    $s = $pdo->prepare('SELECT manager_vente_perms FROM business_settings WHERE business_id = ? LIMIT 1');
    $s->execute([$bizId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['manager_vente_perms'])) {
        $saved = json_decode($row['manager_vente_perms'], true);
        if (is_array($saved)) $managerPerms = array_merge($defaultPerms, $saved);
    }
} catch (Throwable $e) {}

$permSaved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_perms']) && $isOwner) {
    $newPerms = [];
    foreach (array_keys($defaultPerms) as $k) $newPerms[$k] = !empty($_POST['perm_'.$k]);
    try {
        $s = $pdo->prepare('UPDATE business_settings SET manager_vente_perms = ? WHERE business_id = ?');
        $s->execute([json_encode($newPerms), $bizId]);
        $managerPerms = $newPerms;
        $permSaved = true;
    } catch (Throwable $e) {}
}
function canSee($key, $isOwner, $perms){ return $isOwner || !empty($perms[$key]); }

/* Period */
$view  = $_GET['view'] ?? 'month';
$month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$year  = max(2020, min((int)date('Y') + 1, (int)($_GET['year'] ?? date('Y'))));
$mNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$dateFrom = $view === 'year' ? "$year-01-01" : date('Y-m-d', mktime(0,0,0,$month,1,$year));
$dateTo   = $view === 'year' ? "$year-12-31" : date('Y-m-t', mktime(0,0,0,$month,1,$year));
$prevM=$month-1; $prevY=$year; if($prevM<1){$prevM=12;$prevY--;}
$nextM=$month+1; $nextY=$year; if($nextM>12){$nextM=1;$nextY++;}
$isCurrent = ($month === (int)date('n') && $year === (int)date('Y'));

/* KPIs */
$kpi = [
    'sales_total' => 0,
    'sales_count' => 0,
    'loss_total' => 0,
    'stock_spent' => 0,
    'profit_est' => 0,
    'stock_value_left' => 0,
    'cash_total' => 0,
    'mtn_total' => 0,
    'orange_total' => 0
];
try {
    $s = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total_ttc),0) t
        FROM transactions_caisse
        WHERE business_id=? AND type_operation='vente' AND statut='validee' AND DATE(created_at) BETWEEN ? AND ?");
    $s->execute([$bizId,$dateFrom,$dateTo]);
    $r=$s->fetch(PDO::FETCH_ASSOC) ?: [];
    $kpi['sales_count']=(int)($r['c']??0); $kpi['sales_total']=(float)($r['t']??0);
} catch(Throwable $e) {}
try {
    $s=$pdo->prepare("SELECT COALESCE(SUM(loss_amount),0) FROM stock_out_requests
        WHERE business_id=? AND movement_type IN('broken','lost') AND DATE(created_at) BETWEEN ? AND ?");
    $s->execute([$bizId,$dateFrom,$dateTo]); $kpi['loss_total']=(float)$s->fetchColumn();
} catch(Throwable $e) {}
try {
    $s=$pdo->prepare("SELECT COALESCE(SUM(quantity*cost_price),0) FROM stock_in_requests
        WHERE business_id=? AND status='approved' AND DATE(created_at) BETWEEN ? AND ?");
    $s->execute([$bizId,$dateFrom,$dateTo]); $kpi['stock_spent']=(float)$s->fetchColumn();
} catch(Throwable $e) {}
try {
    $s=$pdo->prepare("SELECT COALESCE(SUM(quantity*unit_price),0) FROM products WHERE business_id=? AND status='active'");
    $s->execute([$bizId]); $kpi['stock_value_left']=(float)$s->fetchColumn();
} catch(Throwable $e) {}
try {
    $s=$pdo->prepare("SELECT COALESCE(SUM(it.total_ligne - (it.quantite*COALESCE(p.cost_price,0))),0)
        FROM items_transaction it
        JOIN transactions_caisse tc ON it.transaction_id=tc.transaction_id
        LEFT JOIN products p ON p.product_id=it.product_id
        WHERE tc.business_id=? AND tc.type_operation='vente' AND tc.statut='validee' AND DATE(tc.created_at) BETWEEN ? AND ?");
    $s->execute([$bizId,$dateFrom,$dateTo]); $kpi['profit_est']=(float)$s->fetchColumn();
} catch(Throwable $e) {}
try {
    $s=$pdo->prepare("SELECT pm.mode, COALESCE(SUM(pm.montant),0) total
        FROM paiements_mixtes pm
        JOIN transactions_caisse tc ON tc.transaction_id=pm.transaction_id
        WHERE tc.business_id=? AND tc.type_operation='vente' AND tc.statut='validee' AND DATE(tc.created_at) BETWEEN ? AND ?
        GROUP BY pm.mode");
    $s->execute([$bizId,$dateFrom,$dateTo]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
        if($r['mode']==='especes') $kpi['cash_total']=(float)$r['total'];
        if($r['mode']==='mtn_momo') $kpi['mtn_total']=(float)$r['total'];
        if($r['mode']==='orange_money') $kpi['orange_total']=(float)$r['total'];
    }
} catch(Throwable $e) {}

/* Sales chart */
$chartLabels=[]; $chartData=[];
try {
    if($view === 'year'){
        $s=$pdo->prepare("SELECT MONTH(created_at) m, COALESCE(SUM(total_ttc),0) total
            FROM transactions_caisse
            WHERE business_id=? AND type_operation='vente' AND statut='validee' AND YEAR(created_at)=?
            GROUP BY MONTH(created_at)");
        $s->execute([$bizId,$year]);
        $by=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $by[(int)$r['m']] = (float)$r['total'];
        for($i=1;$i<=12;$i++){ $chartLabels[]=$mNames[$i]; $chartData[]=$by[$i]??0; }
    } else {
        $s=$pdo->prepare("SELECT DAY(created_at) d, COALESCE(SUM(total_ttc),0) total
            FROM transactions_caisse
            WHERE business_id=? AND type_operation='vente' AND statut='validee' AND YEAR(created_at)=? AND MONTH(created_at)=?
            GROUP BY DAY(created_at)");
        $s->execute([$bizId,$year,$month]);
        $by=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $by[(int)$r['d']] = (float)$r['total'];
        $days=(int)date('t',mktime(0,0,0,$month,1,$year));
        for($d=1;$d<=$days;$d++){ $chartLabels[]=(string)$d; $chartData[]=$by[$d]??0; }
    }
} catch(Throwable $e) {}

/* Table 1: Cashier performance */
$cashiers=[];
try {
    $s=$pdo->prepare("SELECT u.user_id, u.full_name,
            COUNT(CASE WHEN tc.type_operation='vente' AND tc.statut='validee' THEN 1 END) nb_sales,
            COALESCE(SUM(CASE WHEN tc.type_operation='vente' AND tc.statut='validee' THEN tc.total_ttc END),0) total_sales,
            COUNT(CASE WHEN tc.statut IN('pending_remb','remb_validee','remb_rejetee') THEN 1 END) refunds,
            COUNT(CASE WHEN tc.type_operation='abime' THEN 1 END) damaged,
            MAX(CASE WHEN tc.type_operation='vente' THEN tc.created_at END) last_sale,
            (SELECT pm.mode FROM paiements_mixtes pm
             JOIN transactions_caisse tx ON tx.transaction_id=pm.transaction_id
             WHERE tx.business_id=tc.business_id AND tx.caissier_id=u.user_id AND tx.type_operation='vente'
             GROUP BY pm.mode ORDER BY COUNT(*) DESC LIMIT 1) fav_payment
        FROM transactions_caisse tc
        JOIN users u ON u.user_id=tc.caissier_id
        WHERE tc.business_id=? AND DATE(tc.created_at) BETWEEN ? AND ?
        GROUP BY u.user_id,u.full_name,tc.business_id
        ORDER BY total_sales DESC");
    $s->execute([$bizId,$dateFrom,$dateTo]);
    $cashiers=$s->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Table 2: Stock Entrant */
$stockIn=[]; $sumDelivery=0; $sumPotentialRevenue=0; $sumPotentialProfit=0;
try {
    $s=$pdo->prepare("SELECT p.name product_name,
            COALESCE(sir.cost_price, p.cost_price, 0) cost_price,
            COALESCE(p.unit_price,0) sell_price,
            sir.quantity qty_in,
            COALESCE(u.full_name,'—') entered_by,
            sir.created_at,
            (COALESCE(sir.cost_price, p.cost_price, 0)*sir.quantity) delivery_value,
            (COALESCE(p.unit_price,0)*sir.quantity) potential_revenue,
            ((COALESCE(p.unit_price,0)-COALESCE(sir.cost_price, p.cost_price, 0))*sir.quantity) potential_profit
        FROM stock_in_requests sir
        JOIN products p ON p.product_id=sir.product_id
        LEFT JOIN users u ON u.user_id=sir.created_by
        WHERE sir.business_id=? AND sir.status='approved' AND DATE(sir.created_at) BETWEEN ? AND ?
        ORDER BY sir.created_at DESC LIMIT 200");
    $s->execute([$bizId,$dateFrom,$dateTo]);
    $stockIn=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($stockIn as $r){ $sumDelivery += $r['delivery_value']; $sumPotentialRevenue += $r['potential_revenue']; $sumPotentialProfit += $r['potential_profit']; }
} catch(Throwable $e) {}

/* Table 3: Stock Sortant / Sales / Loss / Fraud check */
$stockOut=[]; $sumSold=0; $sumLost=0; $sumMissing=0;
try {
    $s=$pdo->prepare("SELECT p.product_id, p.name product_name,
            COALESCE(p.cost_price,0) cost_price,
            COALESCE(p.unit_price,0) sell_price,
            COALESCE(si.qty_in,0) qty_in,
            COALESCE(sold.qty_sold,0) qty_sold,
            COALESCE(loss.qty_lost,0) qty_lost,
            COALESCE(loss.loss_amount,0) loss_amount,
            COALESCE(loss.reported_by,'—') reported_by,
            COALESCE(sold.revenue,0) total_sold,
            p.quantity stock_real,
            (COALESCE(si.qty_in,0)-COALESCE(sold.qty_sold,0)-COALESCE(loss.qty_lost,0)) stock_theorique,
            ((COALESCE(si.qty_in,0)-COALESCE(sold.qty_sold,0)-COALESCE(loss.qty_lost,0))-p.quantity) ecart
        FROM products p
        LEFT JOIN (
            SELECT product_id, SUM(quantity) qty_in
            FROM stock_in_requests WHERE business_id=? AND status='approved' GROUP BY product_id
        ) si ON si.product_id=p.product_id
        LEFT JOIN (
            SELECT it.product_id, SUM(it.quantite) qty_sold, SUM(it.total_ligne) revenue
            FROM items_transaction it
            JOIN transactions_caisse tc ON tc.transaction_id=it.transaction_id
            WHERE tc.business_id=? AND tc.type_operation='vente' AND tc.statut='validee'
            GROUP BY it.product_id
        ) sold ON sold.product_id=p.product_id
        LEFT JOIN (
            SELECT sor.product_id, SUM(COALESCE(sor.broken_qty,sor.quantity,0)) qty_lost, SUM(COALESCE(sor.loss_amount,0)) loss_amount,
                   GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') reported_by
            FROM stock_out_requests sor
            LEFT JOIN users u ON u.user_id=sor.created_by
            WHERE sor.business_id=? AND sor.movement_type IN('broken','lost')
            GROUP BY sor.product_id
        ) loss ON loss.product_id=p.product_id
        WHERE p.business_id=? AND p.status='active'
          AND (COALESCE(si.qty_in,0)>0 OR COALESCE(sold.qty_sold,0)>0 OR COALESCE(loss.qty_lost,0)>0)
        ORDER BY total_sold DESC, p.name ASC LIMIT 200");
    $s->execute([$bizId,$bizId,$bizId,$bizId]);
    $stockOut=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($stockOut as $r){ $sumSold += $r['total_sold']; $sumLost += $r['loss_amount']; if($r['ecart']>0) $sumMissing += $r['ecart']; }
} catch(Throwable $e) {}

/* Receipts */
$q       = trim($_GET['q'] ?? '');
$rdate   = trim($_GET['rdate'] ?? '');
$cashier = trim($_GET['cashier'] ?? '');
$receipts=[]; $recToday=0; $recWeek=0; $recMonth=0;
try {
    $s=$pdo->prepare("SELECT COUNT(*) FROM transactions_caisse WHERE business_id=? AND type_operation='vente' AND statut='validee' AND DATE(created_at)=CURDATE()");
    $s->execute([$bizId]); $recToday=(int)$s->fetchColumn();
    $s=$pdo->prepare("SELECT COUNT(*) FROM transactions_caisse WHERE business_id=? AND type_operation='vente' AND statut='validee' AND YEARWEEK(created_at,1)=YEARWEEK(NOW(),1)");
    $s->execute([$bizId]); $recWeek=(int)$s->fetchColumn();
    $s=$pdo->prepare("SELECT COUNT(*) FROM transactions_caisse WHERE business_id=? AND type_operation='vente' AND statut='validee' AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())");
    $s->execute([$bizId]); $recMonth=(int)$s->fetchColumn();
} catch(Throwable $e) {}
try {
    $params=[$bizId,$dateFrom,$dateTo]; $extra='';
    if($q){ $extra .= " AND (tc.numero_facture LIKE ? OR tc.client_nom LIKE ? OR tc.client_phone LIKE ?)"; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
    if($cashier){ $extra .= " AND u.full_name LIKE ?"; $params[]='%'.$cashier.'%'; }
    if($rdate){ $extra .= " AND DATE(tc.created_at)=?"; $params[]=$rdate; }
    $s=$pdo->prepare("SELECT tc.transaction_id, tc.numero_facture, tc.client_nom, tc.client_phone, tc.total_ttc, tc.created_at,
            COALESCE(u.full_name,'—') cashier_name,
            GROUP_CONCAT(pm.mode ORDER BY pm.mode SEPARATOR ', ') payments
        FROM transactions_caisse tc
        LEFT JOIN users u ON u.user_id=tc.caissier_id
        LEFT JOIN paiements_mixtes pm ON pm.transaction_id=tc.transaction_id
        WHERE tc.business_id=? AND tc.type_operation='vente' AND tc.statut='validee' AND DATE(tc.created_at) BETWEEN ? AND ? $extra
        GROUP BY tc.transaction_id
        ORDER BY tc.created_at DESC LIMIT 80");
    $s->execute($params); $receipts=$s->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Fraud table rows */
$fraudRows=[];
foreach($stockOut as $r){
    if((int)$r['ecart'] > 0){
        $fraudRows[] = ['level'=>'red','type'=>'Stock','target'=>$r['product_name'],'desc'=>'Écart de '.(int)$r['ecart'].' unité(s) entre stock théorique et stock réel'];
    }
    if((float)$r['loss_amount'] > 0){
        $fraudRows[] = ['level'=>'amber','type'=>'Perte','target'=>$r['product_name'],'desc'=>'Perte déclarée: '.xaf($r['loss_amount']).' — Signalé par: '.$r['reported_by']];
    }
}
foreach($cashiers as $c){
    if((int)$c['refunds'] >= 2){
        $fraudRows[]=['level'=>'amber','type'=>'Caissier','target'=>$c['full_name'],'desc'=>(int)$c['refunds'].' remboursement(s) sur la période'];
    }
}
try {
    $s=$pdo->prepare("SELECT tc.numero_facture, tc.total_ttc, tc.created_at, pm.mode
        FROM paiements_mixtes pm
        JOIN transactions_caisse tc ON tc.transaction_id=pm.transaction_id
        WHERE tc.business_id=? AND pm.mode!='especes' AND (pm.reference IS NULL OR pm.reference='') AND DATE(tc.created_at) BETWEEN ? AND ?
        LIMIT 20");
    $s->execute([$bizId,$dateFrom,$dateTo]);
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
        $fraudRows[]=['level'=>'red','type'=>'Paiement','target'=>$r['numero_facture'],'desc'=>'Paiement '.$r['mode'].' sans référence de transaction'];
    }
} catch(Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vente — <?= e($bizName) ?></title>
<link rel="stylesheet" href="<?= $url ?>/LionTech_Owner_Dashboard/owner_dashboard.css">
<link rel="stylesheet" href="<?= $url ?>/Vente_cashier/Vente.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<div class="od-layout">
<?php include dirname(__DIR__) . '/LionTech_Owner_Dashboard/Sidebar.php'; ?>
<main class="od-main">

<div class="od-topbar">
    <div>
        <h1 class="page-title">📊 <span data-i18n="title">Contrôle des ventes</span></h1>
        <p class="page-subtitle"><?= e($bizName) ?> · <?= e($dateFrom) ?> → <?= e($dateTo) ?></p>
    </div>
    <div class="top-actions">
        <?php if(count($fraudRows)>0): ?><a class="alert-pill" href="#fraudTable">⚠️ <?= count($fraudRows) ?></a><?php endif; ?>
        <button class="lang-btn" id="salesLangBtn" onclick="toggleSalesLang()">FR</button>
        <div class="od-avatar"><?= e($initials) ?></div>
    </div>
</div>

<div class="vd-wrap">
<?php if($permSaved): ?><div class="vd-alert success">✅ Permissions manager mises à jour.</div><?php endif; ?>

<div class="control-bar">
    <div class="period-box">
        <a class="period-btn <?= $view==='month'?'active':'' ?>" href="?view=month&month=<?= $month ?>&year=<?= $year ?>" data-i18n="month">Mois</a>
        <a class="period-btn <?= $view==='year'?'active':'' ?>" href="?view=year&year=<?= $year ?>" data-i18n="year">Année</a>
        <?php if($view==='month'): ?>
            <a class="period-arrow" href="?view=month&month=<?= $prevM ?>&year=<?= $prevY ?>">‹</a>
            <strong><?= $mNames[$month].' '.$year ?></strong>
            <a class="period-arrow <?= $isCurrent?'disabled':'' ?>" href="?view=month&month=<?= $nextM ?>&year=<?= $nextY ?>">›</a>
        <?php else: ?>
            <a class="period-arrow" href="?view=year&year=<?= $year-1 ?>">‹</a>
            <strong><?= $year ?></strong>
            <a class="period-arrow <?= $year >= (int)date('Y')?'disabled':'' ?>" href="?view=year&year=<?= $year+1 ?>">›</a>
        <?php endif; ?>
    </div>
    <div class="control-actions">
        <button class="btn secondary" onclick="window.print()">🖨️ <span data-i18n="print">Imprimer</span></button>
        <button class="btn secondary" onclick="exportCurrentTable()">⬇️ CSV</button>
        <?php if($isOwner): ?><button class="btn gold" onclick="openPermModal()">⚙️ Manager</button><?php endif; ?>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><span>💰</span><small data-i18n="total_sales">Total vendu</small><strong><?= xaf($kpi['sales_total']) ?></strong></div>
    <div class="kpi-card"><span>🧾</span><small data-i18n="receipts">Reçus</small><strong><?= n0($kpi['sales_count']) ?></strong></div>
    <?php if(canSee('money',$isOwner,$managerPerms)): ?><div class="kpi-card"><span>📦</span><small data-i18n="stock_spent">Dépensé stock</small><strong><?= xaf($kpi['stock_spent']) ?></strong></div><?php endif; ?>
    <div class="kpi-card danger"><span>⚠️</span><small data-i18n="losses">Pertes</small><strong><?= xaf($kpi['loss_total']) ?></strong></div>
    <?php if(canSee('money',$isOwner,$managerPerms)): ?><div class="kpi-card success"><span>📈</span><small data-i18n="profit">Bénéfice estimé</small><strong><?= xaf($kpi['profit_est']) ?></strong></div><?php endif; ?>
</div>

<div class="chart-panel">
    <div class="table-title"><h2 data-i18n="sales_graph">Évolution des ventes</h2><span><?= $view==='year'?$year:($mNames[$month].' '.$year) ?></span></div>
    <canvas id="salesChart" height="95"></canvas>
</div>

<?php if(canSee('cashier_perf',$isOwner,$managerPerms)): ?>
<section class="data-panel">
    <div class="table-title"><h2 data-i18n="cashier_perf">Performance des caissiers</h2><span><?= count($cashiers) ?> ligne(s)</span></div>
    <div class="table-scroll">
        <table class="audit-table mobile-table">
            <thead><tr><th>Caissier</th><th>Nb ventes</th><th>Total vendu</th><th>Paiement favori</th><th>Remb.</th><th>Abîmé</th><th>Dernière vente</th><th>Statut</th></tr></thead>
            <tbody>
            <?php if(empty($cashiers)): ?><tr><td colspan="8" class="empty-cell">Aucune vente pour cette période.</td></tr><?php endif; ?>
            <?php foreach($cashiers as $c):
                $status = ((int)$c['refunds']>=2 || (int)$c['damaged']>=1) ? '<span class="badge amber">À vérifier</span>' : '<span class="badge green">Normal</span>';
            ?>
            <tr>
                <td data-label="Caissier"><strong><?= e($c['full_name']) ?></strong></td>
                <td data-label="Nb ventes"><?= n0($c['nb_sales']) ?></td>
                <td data-label="Total vendu"><strong><?= xaf($c['total_sales']) ?></strong></td>
                <td data-label="Paiement favori"><?= e($c['fav_payment'] ?: '—') ?></td>
                <td data-label="Remb."><?= n0($c['refunds']) ?></td>
                <td data-label="Abîmé"><?= n0($c['damaged']) ?></td>
                <td data-label="Dernière vente"><?= dmyhm($c['last_sale']) ?></td>
                <td data-label="Statut"><?= $status ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if(canSee('stock_in',$isOwner,$managerPerms)): ?>
<section class="data-panel">
    <div class="table-title"><h2 data-i18n="stock_in">Stock entrant</h2><span><?= count($stockIn) ?> entrée(s)</span></div>
    <div class="table-scroll">
        <table class="audit-table mobile-table">
            <thead><tr><th>Produit</th><th>Prix achat</th><th>Date achat</th><th>Qté entrée</th><th>Entré par</th><th>Prix vente</th><th>Valeur livraison</th><th>Revenu potentiel</th><th>Profit potentiel</th></tr></thead>
            <tbody>
            <?php if(empty($stockIn)): ?><tr><td colspan="9" class="empty-cell">Aucun stock entrant pour cette période.</td></tr><?php endif; ?>
            <?php foreach($stockIn as $r): ?>
            <tr>
                <td data-label="Produit"><strong><?= e($r['product_name']) ?></strong></td>
                <td data-label="Prix achat"><?= xaf($r['cost_price']) ?></td>
                <td data-label="Date achat"><?= dmy($r['created_at']) ?></td>
                <td data-label="Qté entrée"><span class="num blue"><?= n0($r['qty_in']) ?></span></td>
                <td data-label="Entré par"><?= e($r['entered_by']) ?></td>
                <td data-label="Prix vente"><?= xaf($r['sell_price']) ?></td>
                <td data-label="Valeur livraison"><?= xaf($r['delivery_value']) ?></td>
                <td data-label="Revenu potentiel"><?= xaf($r['potential_revenue']) ?></td>
                <td data-label="Profit potentiel"><strong class="pos"><?= xaf($r['potential_profit']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="6">TOTAL</td><td><?= xaf($sumDelivery) ?></td><td><?= xaf($sumPotentialRevenue) ?></td><td><?= xaf($sumPotentialProfit) ?></td></tr></tfoot>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="main-grid">
    <?php if(canSee('stock_out',$isOwner,$managerPerms)): ?>
    <section class="data-panel wide">
        <div class="table-title"><h2 data-i18n="stock_out">Stock sortant / ventes / pertes</h2><span><?= count($stockOut) ?> produit(s)</span></div>
        <div class="table-scroll">
            <table class="audit-table mobile-table">
                <thead><tr><th>Produit</th><th>Prix achat</th><th>Prix vente</th><th>Qté entrée</th><th>Qté vendue</th><th>Qté perdue</th><th>Déclaré par</th><th>Total vendu</th><th>Total perdu</th><th>Stock restant</th><th>Écart</th></tr></thead>
                <tbody>
                <?php if(empty($stockOut)): ?><tr><td colspan="11" class="empty-cell">Aucun stock sortant enregistré.</td></tr><?php endif; ?>
                <?php foreach($stockOut as $r): $hasGap = (int)$r['ecart'] > 0; ?>
                <tr class="<?= $hasGap?'row-warning':'' ?>">
                    <td data-label="Produit"><strong><?= e($r['product_name']) ?></strong></td>
                    <td data-label="Prix achat"><?= xaf($r['cost_price']) ?></td>
                    <td data-label="Prix vente"><?= xaf($r['sell_price']) ?></td>
                    <td data-label="Qté entrée"><span class="num blue"><?= n0($r['qty_in']) ?></span></td>
                    <td data-label="Qté vendue"><span class="num green"><?= n0($r['qty_sold']) ?></span></td>
                    <td data-label="Qté perdue"><span class="num <?= $r['qty_lost']>0?'red':'gray' ?>"><?= n0($r['qty_lost']) ?></span></td>
                    <td data-label="Déclaré par"><?= e($r['reported_by']) ?></td>
                    <td data-label="Total vendu"><strong><?= xaf($r['total_sold']) ?></strong></td>
                    <td data-label="Total perdu"><strong class="neg"><?= $r['loss_amount']>0?'-'.xaf($r['loss_amount']):'—' ?></strong></td>
                    <td data-label="Stock restant"><span class="badge <?= $r['stock_real']<=2?'red':($r['stock_real']<=5?'amber':'green') ?>"><?= n0($r['stock_real']) ?></span></td>
                    <td data-label="Écart"><?= $hasGap ? '<span class="badge red">+'.n0($r['ecart']).'</span>' : '<span class="badge green">OK</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="7">TOTAL</td><td><?= xaf($sumSold) ?></td><td>-<?= xaf($sumLost) ?></td><td></td><td><?= $sumMissing>0?'+'.n0($sumMissing):'OK' ?></td></tr></tfoot>
            </table>
        </div>
        <p class="table-note">Un écart positif signifie que le stock théorique est plus grand que le stock réel. Cela peut indiquer une erreur, une perte non déclarée ou une fraude.</p>
    </section>
    <?php endif; ?>

    <aside class="summary-panel">
        <h2 data-i18n="financial_summary">Résumé financier</h2>
        <div class="summary-row"><span>Total vendu</span><strong><?= xaf($kpi['sales_total']) ?></strong></div>
        <div class="summary-row"><span>Total perdu</span><strong class="neg">-<?= xaf($kpi['loss_total']) ?></strong></div>
        <div class="summary-row"><span>Total dépensé</span><strong><?= xaf($kpi['stock_spent']) ?></strong></div>
        <div class="summary-row"><span>Bénéfice estimé</span><strong class="<?= $kpi['profit_est']>=0?'pos':'neg' ?>"><?= xaf($kpi['profit_est']) ?></strong></div>
        <div class="summary-row"><span>Valeur stock restant</span><strong><?= xaf($kpi['stock_value_left']) ?></strong></div>
        <hr>
        <div class="summary-row"><span>Cash</span><strong><?= xaf($kpi['cash_total']) ?></strong></div>
        <div class="summary-row"><span>MTN Money</span><strong><?= xaf($kpi['mtn_total']) ?></strong></div>
        <div class="summary-row"><span>Orange Money</span><strong><?= xaf($kpi['orange_total']) ?></strong></div>
    </aside>
</div>

<?php if(canSee('receipts',$isOwner,$managerPerms)): ?>
<section class="data-panel" id="receiptsTable">
    <div class="table-title"><h2 data-i18n="receipt_table">Reçus / factures</h2><span>Aujourd'hui: <?= $recToday ?> · Semaine: <?= $recWeek ?> · Mois: <?= $recMonth ?></span></div>
    <form class="receipt-search" method="GET">
        <input type="hidden" name="view" value="<?= e($view) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="year" value="<?= e($year) ?>">
        <input name="q" value="<?= e($q) ?>" placeholder="Facture, client, WhatsApp...">
        <input name="cashier" value="<?= e($cashier) ?>" placeholder="Caissier...">
        <input name="rdate" value="<?= e($rdate) ?>" type="date">
        <button class="btn primary" type="submit">Chercher</button>
    </form>
    <div class="table-scroll">
        <table class="audit-table mobile-table">
            <thead><tr><th>Facture</th><th>Client</th><th>Téléphone</th><th>Caissier</th><th>Paiement</th><th>Total</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(empty($receipts)): ?><tr><td colspan="8" class="empty-cell">Aucun reçu trouvé.</td></tr><?php endif; ?>
            <?php foreach($receipts as $r): ?>
            <tr>
                <td data-label="Facture"><strong><?= e($r['numero_facture']) ?></strong></td>
                <td data-label="Client"><?= e($r['client_nom'] ?: 'Walk-in') ?></td>
                <td data-label="Téléphone"><?= e($r['client_phone'] ?: '—') ?></td>
                <td data-label="Caissier"><?= e($r['cashier_name']) ?></td>
                <td data-label="Paiement"><?= e($r['payments'] ?: '—') ?></td>
                <td data-label="Total"><strong><?= xaf($r['total_ttc']) ?></strong></td>
                <td data-label="Date"><?= dmyhm($r['created_at']) ?></td>
                <td data-label="Action"><a class="btn small secondary" href="<?= $url ?>/Vente_cashier/caisse/facture.php?id=<?= (int)$r['transaction_id'] ?>" target="_blank">Voir</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php if(canSee('fraud',$isOwner,$managerPerms)): ?>
<section class="data-panel" id="fraudTable">
    <div class="table-title"><h2 data-i18n="fraud_table">Détection fraude / anomalies</h2><span><?= count($fraudRows) ?> alerte(s)</span></div>
    <div class="table-scroll">
        <table class="audit-table mobile-table">
            <thead><tr><th>Niveau</th><th>Type</th><th>Produit / Caissier / Facture</th><th>Description</th></tr></thead>
            <tbody>
            <?php if(empty($fraudRows)): ?><tr><td colspan="4" class="empty-cell">Aucune anomalie détectée.</td></tr><?php endif; ?>
            <?php foreach($fraudRows as $a): ?>
            <tr>
                <td data-label="Niveau"><span class="badge <?= e($a['level']) ?>"><?= $a['level']==='red'?'🔴 Élevé':'🟠 Moyen' ?></span></td>
                <td data-label="Type"><?= e($a['type']) ?></td>
                <td data-label="Cible"><strong><?= e($a['target']) ?></strong></td>
                <td data-label="Description"><?= e($a['desc']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

</div>
</main>
</div>

<?php if($isOwner): ?>
<div id="permModal" class="modal-backdrop">
    <div class="modal-card">
        <h2>⚙️ Permissions du Manager</h2>
        <p>Choisis les tables visibles pour le manager.</p>
        <form method="POST">
            <input type="hidden" name="save_perms" value="1">
            <?php
            $labels = [
                'cashier_perf'=>'Performance des caissiers', 'stock_in'=>'Stock entrant', 'stock_out'=>'Stock sortant',
                'receipts'=>'Reçus / factures', 'fraud'=>'Détection fraude', 'money'=>'Résumé financier détaillé'
            ];
            foreach($labels as $k=>$label): ?>
            <label class="perm-line"><span><?= e($label) ?></span><input type="checkbox" name="perm_<?= e($k) ?>" <?= !empty($managerPerms[$k])?'checked':'' ?>></label>
            <?php endforeach; ?>
            <div class="modal-actions"><button type="button" class="btn secondary" onclick="closePermModal()">Annuler</button><button class="btn primary" type="submit">Enregistrer</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
window.SALES_DATA = { labels: <?= json_encode($chartLabels) ?>, values: <?= json_encode(array_map('floatval',$chartData)) ?> };
</script>
<script src="<?= $url ?>/Vente_cashier/Vente.js"></script>
</body>
</html>