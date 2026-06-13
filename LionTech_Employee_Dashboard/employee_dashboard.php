<?php
/* ============================================================
   employee_dashboard.php — Tally Business Manager
   Role: employee / cashier / stock_manager / manager
   Landing page after employee logs in.
   ============================================================ */

require_once __DIR__ . '/../Config.php';
if (function_exists('startSecureSession')) startSecureSession();
if (function_exists('requireRole')) {
    requireRole([ROLE_EMPLOYEE, ROLE_MANAGER]);
}

$pdo  = function_exists('getDB') ? getDB() : null;
$user = function_exists('currentUser') ? currentUser() : ($_SESSION['user'] ?? []);
$businessId = (int)($user['business_id'] ?? 0);
$userId = (int)($user['user_id'] ?? 0);

if (!$pdo || $businessId <= 0 || $userId <= 0) {
    header('Location: login.php?error=unauthorized');
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableExists(PDO $pdo, string $table): bool {
    try { $pdo->query("SELECT 1 FROM {$table} LIMIT 1"); return true; }
    catch(Throwable $e) { return false; }
}
function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch(Throwable $e) { return false; }
}
function haversineMeters($lat1, $lng1, $lat2, $lng2): float {
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    return $earth * 2 * atan2(sqrt($a), sqrt(1-$a));
}
function logActivity(PDO $pdo, int $userId, int $businessId, string $action, string $description, string $icon='activity'): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id,business_id,action,description,icon,ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$userId,$businessId,$action,$description,$icon,$_SERVER['REMOTE_ADDR'] ?? null]);
    } catch(Throwable $e) {}
}

/* Business */
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ? LIMIT 1");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* Feature gate */
$employeeFeatureEnabled = true;
try {
    $stmt = $pdo->prepare("SELECT employee_management FROM business_features WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($feature && (int)$feature['employee_management'] !== 1) $employeeFeatureEnabled = false;
} catch(Throwable $e) {}

if (!$employeeFeatureEnabled) {
    http_response_code(403);
    echo "Cette fonctionnalité n'est pas active pour ce business. Contactez LionTech.";
    exit;
}

/* Subscription read-only */
$subscriptionStatus = $business['subscription_status'] ?? 'active';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = in_array($subscriptionStatus, ['expired','suspended'], true) || ($expiresAt && strtotime($expiresAt) < time());

/* Attendance settings */
$gpsRequired = true;
$businessLat = null; $businessLng = null; $gpsRadius = 200;
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $gpsRequired = (int)($settings['gps_required'] ?? 1) === 1;
        $businessLat = $settings['business_latitude'] !== null ? (float)$settings['business_latitude'] : null;
        $businessLng = $settings['business_longitude'] !== null ? (float)$settings['business_longitude'] : null;
        $gpsRadius = (int)($settings['gps_radius_meters'] ?? 200);
    }
} catch(Throwable $e) {}

$success = '';
$error = '';

/* POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($isExpired && in_array($action, ['clock_in','clock_out','change_pin'], true)) {
            throw new Exception('Votre abonnement est expiré. Les actions sont limitées.');
        }

        if ($action === 'clock_in') {
            $lat = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
            $lng = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;
            $accuracy = ($_POST['accuracy'] ?? '') !== '' ? (float)$_POST['accuracy'] : null;

            $openStmt = $pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL LIMIT 1");
            $openStmt->execute([$userId,$businessId]);
            if ($openStmt->fetch()) throw new Exception('Vous êtes déjà clocké(e) in.');

            $gpsStatus = 'pending_review';
            $distance = null;
            $note = 'GPS non vérifié';

            if ($lat !== null && $lng !== null && $businessLat !== null && $businessLng !== null) {
                $distance = haversineMeters($lat,$lng,$businessLat,$businessLng);
                if ($distance <= $gpsRadius) {
                    $gpsStatus = 'on_site';
                    $note = 'Employé proche du business';
                } elseif ($distance <= ($gpsRadius + 300)) {
                    $gpsStatus = 'pending_review';
                    $note = 'Employé légèrement hors zone, validation recommandée';
                } else {
                    $gpsStatus = 'rejected_far';
                    $note = 'Employé trop loin du business';
                    throw new Exception('Clock in refusé: vous semblez trop loin du business.');
                }
            } elseif (!$gpsRequired) {
                $gpsStatus = 'no_gps_allowed';
                $note = 'GPS non disponible mais autorisé par le business';
            }

            $stmt = $pdo->prepare("INSERT INTO employee_attendance (business_id,user_id,clock_in_at,clock_in_latitude,clock_in_longitude,clock_in_accuracy,gps_status,distance_meters,status,note,created_at) VALUES (?,?,NOW(),?,?,?,?,?,'clocked_in',?,NOW())");
            $stmt->execute([$businessId,$userId,$lat,$lng,$accuracy,$gpsStatus,$distance !== null ? round($distance,2) : null,$note]);
            logActivity($pdo,$userId,$businessId,'employee_clock_in','Employé clock in: ' . ($user['full_name'] ?? ''),'clock');
            $success = 'Clock in enregistré avec succès.';
        }

        if ($action === 'clock_out') {
            $lat = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
            $lng = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;
            $accuracy = ($_POST['accuracy'] ?? '') !== '' ? (float)$_POST['accuracy'] : null;

            $openStmt = $pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1");
            $openStmt->execute([$userId,$businessId]);
            $row = $openStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Aucun clock in ouvert trouvé.');

            $stmt = $pdo->prepare("UPDATE employee_attendance SET clock_out_at=NOW(), clock_out_latitude=?, clock_out_longitude=?, clock_out_accuracy=?, status='clocked_out', updated_at=NOW() WHERE attendance_id=? AND user_id=? AND business_id=?");
            $stmt->execute([$lat,$lng,$accuracy,(int)$row['attendance_id'],$userId,$businessId]);
            logActivity($pdo,$userId,$businessId,'employee_clock_out','Employé clock out: ' . ($user['full_name'] ?? ''),'clock');
            $success = 'Clock out enregistré avec succès.';
        }

        if ($action === 'change_pin') {
            $newPin = trim($_POST['new_pin'] ?? '');
            $confirmPin = trim($_POST['confirm_pin'] ?? '');
            if (!preg_match('/^\d{6}$/', $newPin)) throw new Exception('Le PIN doit contenir 6 chiffres.');
            if ($newPin !== $confirmPin) throw new Exception('Les PIN ne correspondent pas.');
            $hash = password_hash($newPin, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE user_id=? AND business_id=?");
            $stmt->execute([$hash,$userId,$businessId]);
            try {
                $stmt = $pdo->prepare("UPDATE employee_profiles SET pin_must_change=0 WHERE user_id=? AND business_id=?");
                $stmt->execute([$userId,$businessId]);
            } catch(Throwable $e) {}
            $success = 'PIN modifié avec succès.';
        }
    } catch(Throwable $e) {
        $error = $e->getMessage();
    }
}

/* Current attendance */
$currentAttendance = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1");
    $stmt->execute([$userId,$businessId]);
    $currentAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Today's attendance */
$todayHistory = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND DATE(clock_in_at)=CURDATE() ORDER BY clock_in_at DESC");
    $stmt->execute([$userId,$businessId]);
    $todayHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Recent attendance history */
$attendanceHistory = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? ORDER BY clock_in_at DESC LIMIT 10");
    $stmt->execute([$userId,$businessId]);
    $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Products read-only */
$products = [];
try {
    $stmt = $pdo->prepare("SELECT product_id,name,category,quantity,unit,low_stock_level,image_url FROM products WHERE business_id=? AND status <> 'archived' ORDER BY name ASC LIMIT 12");
    $stmt->execute([$businessId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
    try {
        $stmt = $pdo->prepare("SELECT product_id,name,category,quantity FROM products WHERE business_id=? ORDER BY name ASC LIMIT 12");
        $stmt->execute([$businessId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e2) {}
}

/* Tasks */
$tasks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_tasks WHERE business_id=? AND assigned_to=? AND status IN ('pending','in_progress') ORDER BY due_date IS NULL, due_date ASC, created_at DESC LIMIT 6");
    $stmt->execute([$businessId,$userId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

/* Pending approvals count */
$pendingStockActions = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE business_id=? AND created_by=? AND approval_status='pending'");
    $stmt->execute([$businessId,$userId]);
    $pendingStockActions = (int)$stmt->fetchColumn();
} catch(Throwable $e) {}

$employeeRole = 'Employé';
$pinMustChange = false;
try {
    $stmt = $pdo->prepare("SELECT employee_role, pin_must_change FROM employee_profiles WHERE user_id=? AND business_id=? LIMIT 1");
    $stmt->execute([$userId,$businessId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $employeeRole = $profile['employee_role'] ?: $employeeRole;
        $pinMustChange = (int)($profile['pin_must_change'] ?? 0) === 1;
    }
} catch(Throwable $e) {}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'Employee')) as $w) { $initials .= strtoupper(substr($w,0,1)); }
$initials = substr($initials ?: 'E',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<meta name="theme-color" content="#0B1F3A"/>
<title>Dashboard Employé — LionTech</title>
<link rel="stylesheet" href="employee_dashboard.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="ed-layout">
 
<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="ed-main">
    <header class="ed-topbar">
      <button class="menu-btn" id="menu-btn">☰</button>
      <div>
        <h1 data-i18n="title">Dashboard Employé</h1>
        <p><?= e($business['business_name'] ?? $business['name'] ?? 'Business') ?> · <?= e($employeeRole) ?></p>
      </div>
      <div class="top-actions">
        <button class="lang-btn" id="lang-btn">EN</button>
        <div class="avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($isExpired): ?>
      <div class="alert warning"><span class="icon-warn">⚠</span> <span data-i18n="sub_expired">L’abonnement du business est expiré. Les actions sont limitées.</span></div>
    <?php endif; ?>
    <?php if ($pinMustChange): ?>
      <div class="alert info"><span class="icon-lock"><span class="icon-lock">🔒</span></span> <span data-i18n="pin_warning">Vous utilisez encore un PIN temporaire. Changez votre PIN dans le profil.</span></div>
    <?php endif; ?>
    <?php if ($success): ?><div class="alert success"><span class="icon-ok">✓</span> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><span class="icon-warn">⚠</span> <?= e($error) ?></div><?php endif; ?>

    <section class="hero-card">
      <div>
        <span class="eyebrow" data-i18n="welcome_back">Bienvenue</span>
        <h2><?= e($user['full_name'] ?? 'Employé') ?></h2>
        <p data-i18n="hero_text">Gérez votre présence, vos tâches et vos actions d’inventaire depuis votre téléphone.</p>
      </div>
      <div class="clock-box">
        <span data-i18n="status">Statut</span>
        <strong><?= $currentAttendance ? 'Clocked In' : 'Clocked Out' ?></strong>
      </div>
    </section>

    <section class="cards-grid">
      <div class="stat-card"><span>🕒</span><strong><?= count($todayHistory) ?></strong><small data-i18n="today_records">Présences aujourd’hui</small></div>
      <div class="stat-card"><span><span class="icon-box">▣</span></span><strong><?= count($products) ?></strong><small data-i18n="visible_products">Produits visibles</small></div>
      <div class="stat-card"><span><span class="icon-ok">✓</span></span><strong><?= count($tasks) ?></strong><small data-i18n="open_tasks">Tâches ouvertes</small></div>
      <div class="stat-card"><span>⏳</span><strong><?= $pendingStockActions ?></strong><small data-i18n="pending_actions">Actions en attente</small></div>
    </section>

    <section class="content-grid">
      <div class="panel clock-panel" id="attendance">
        <div class="panel-head"><h3 data-i18n="clock_title">Clock In / Clock Out</h3><p data-i18n="clock_sub">Le système utilise l’heure du serveur. L’employé ne peut pas modifier l’heure.</p></div>
        <div class="gps-status" id="gps-status"><span class="icon-pin">●</span> GPS: attente de permission</div>
        <div class="clock-actions">
          <?php if (!$currentAttendance): ?>
          <form method="POST" class="geo-form">
            <input type="hidden" name="action" value="clock_in"/>
            <input type="hidden" name="latitude" class="lat"/>
            <input type="hidden" name="longitude" class="lng"/>
            <input type="hidden" name="accuracy" class="acc"/>
            <button class="primary-btn" type="submit" <?= $isExpired ? 'disabled' : '' ?>><span class="dot-green">●</span> <span data-i18n="clock_in">Clock In</span></button>
          </form>
          <?php else: ?>
          <form method="POST" class="geo-form">
            <input type="hidden" name="action" value="clock_out"/>
            <input type="hidden" name="latitude" class="lat"/>
            <input type="hidden" name="longitude" class="lng"/>
            <input type="hidden" name="accuracy" class="acc"/>
            <button class="danger-btn" type="submit" <?= $isExpired ? 'disabled' : '' ?>>🔴 <span data-i18n="clock_out">Clock Out</span></button>
          </form>
          <p class="clocked-since">Clock in: <?= e(date('H:i', strtotime($currentAttendance['clock_in_at']))) ?></p>
          <?php endif; ?>
        </div>
        <p class="small-note" data-i18n="gps_note">Si le GPS est légèrement hors zone, l’action peut être marquée pour validation.</p>
      </div>

      <div class="panel quick-panel">
        <div class="panel-head"><h3 data-i18n="quick_actions">Actions rapides</h3></div>
        <div class="quick-grid">
          <a href="<?= APP_URL ?>/LionTech_Stock_In_Page/stock_in.php" class="quick-action"><span class="icon-add">+</span> <span data-i18n="add_stock_in">Ajouter stock entrant</span></a>
<a href="<?= APP_URL ?>/stockout_stockfinis/stock_out.php" class="quick-action">➖ <span data-i18n="add_stock_out">Ajouter stock sortant</span></a>
<a href="<?= APP_URL ?>/Produit/products.php" class="quick-action"><span class="icon-box">▣</span> <span data-i18n="view_products">Voir produits</span></a>
<a href="<?= APP_URL ?>/change_pin.php" class="quick-action"><span class="icon-lock"><span class="icon-lock">🔒</span></span> <span data-i18n="change_pin">Changer PIN</span></a>
      </div>
      </div>
    </section>

    <section class="panel" id="tasks">
      <div class="panel-head"><h3 data-i18n="tasks_title">Mes tâches</h3><p data-i18n="tasks_sub">Tâches assignées par le manager ou le propriétaire.</p></div>
      <?php if (!$tasks): ?>
        <div class="empty">Aucune tâche assignée pour le moment.</div>
      <?php else: ?>
        <div class="task-list">
          <?php foreach ($tasks as $task): ?>
            <div class="task-item"><span><span class="icon-ok">✓</span></span><div><strong><?= e($task['title'] ?? 'Tâche') ?></strong><p><?= e($task['description'] ?? '') ?></p></div><small><?= e($task['due_date'] ?? '') ?></small></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h3 data-i18n="products_title">Produits visibles</h3><p data-i18n="products_sub">Lecture seule. Les employés ne peuvent pas modifier les produits.</p></div>
      <div class="product-grid">
        <?php if (!$products): ?><div class="empty">Aucun produit trouvé.</div><?php endif; ?>
        <?php foreach ($products as $p): ?>
          <?php $low = isset($p['low_stock_level']) && $p['low_stock_level'] !== null && (float)$p['quantity'] <= (float)$p['low_stock_level']; ?>
          <div class="product-card <?= $low ? 'low' : '' ?>">
            <div class="product-img"><?php if (!empty($p['image_url'])): ?><img src="<?= e($p['image_url']) ?>" alt=""/><?php else: ?><span class="icon-box">▣</span><?php endif; ?></div>
            <div><strong><?= e($p['name']) ?></strong><p><?= e($p['category'] ?? 'Autre') ?></p><span><?= e($p['quantity'] ?? 0) ?> <?= e($p['unit'] ?? '') ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h3 data-i18n="history_title">Historique de présence</h3></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>GPS</th><th>Statut</th></tr></thead>
          <tbody>
          <?php if (!$attendanceHistory): ?><tr><td colspan="5" class="empty-row">Aucun historique.</td></tr><?php endif; ?>
          <?php foreach ($attendanceHistory as $a): ?>
            <tr>
              <td><?= e(date('d/m/Y', strtotime($a['clock_in_at']))) ?></td>
              <td><?= e(date('H:i', strtotime($a['clock_in_at']))) ?></td>
              <td><?= !empty($a['clock_out_at']) ? e(date('H:i', strtotime($a['clock_out_at']))) : '—' ?></td>
              <td><span class="badge <?= e($a['gps_status'] ?? 'pending_review') ?>"><?= e($a['gps_status'] ?? 'pending') ?></span></td>
              <td><?= e($a['status'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="profile">
      <div class="panel-head"><h3 data-i18n="profile_title">Profil & PIN</h3><p data-i18n="profile_sub">L’employé peut changer son PIN, mais ne peut pas modifier ses heures de présence.</p></div>
      <form method="POST" class="pin-form">
        <input type="hidden" name="action" value="change_pin"/>
        <label>Nouveau PIN <input type="password" name="new_pin" maxlength="6" pattern="\d{6}" placeholder="6 chiffres" required></label>
        <label>Confirmer PIN <input type="password" name="confirm_pin" maxlength="6" pattern="\d{6}" placeholder="6 chiffres" required></label>
        <button class="primary-btn" type="submit" <?= $isExpired ? 'disabled' : '' ?>>Changer PIN</button>
      </form>
    </section>
  </main>
</div>
<script>
window.ATTENDANCE_CONFIG = <?= json_encode(['gpsRequired'=>$gpsRequired,'businessLat'=>$businessLat,'businessLng'=>$businessLng,'gpsRadius'=>$gpsRadius]) ?>;
</script>
<script src="employee_dashboard.js"></script>
</body>
</html>
