<?php
/* ============================================================
   clock_attendance.php — Tally Business Manager
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Complete_MVP_Remaining_Pages\
         LionTech_MVP_Complete\clock_attendance.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_EMPLOYEE, ROLE_MANAGER, ROLE_BUSINESS_OWNER]);

$pdo        = getDB();
$user       = currentUser();
$userId     = (int)($user['user_id']     ?? 0);
$businessId = (int)($user['business_id'] ?? 0);
$role       = $user['role'] ?? 'employee';
$isEmployee = $role === ROLE_EMPLOYEE;

if (!$pdo || $businessId <= 0 || $userId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function haversineMeters($lat1, $lng1, $lat2, $lng2): float {
    $earth = 6371000;
    $dLat  = deg2rad($lat2 - $lat1);
    $dLng  = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
    return $earth * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function logActivity(PDO $pdo, int $userId, int $businessId, string $action, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id,business_id,action,description,icon,ip_address,created_at) VALUES (?,?,?,?,'clock',?,NOW())")
            ->execute([$userId, $businessId, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}

function durationText($in, $out): string {
    if (!$in || !$out) return '—';
    $mins = max(0, (int)((strtotime($out) - strtotime($in)) / 60));
    return intdiv($mins, 60) . 'h ' . str_pad((string)($mins % 60), 2, '0', STR_PAD_LEFT) . 'm';
}

/* ── Business ── */
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ? LIMIT 1");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* ── Feature gate ── */
$employeeFeatureEnabled = true;
try {
    $stmt = $pdo->prepare("SELECT employee_management, employee_attendance FROM business_features WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($feature && (int)$feature['employee_management'] !== 1 && (int)$feature['employee_attendance'] !== 1)
        $employeeFeatureEnabled = false;
} catch (Throwable $e) {}

if (!$employeeFeatureEnabled) {
    http_response_code(403);
    echo '<div style="font-family:Inter,sans-serif;padding:40px;text-align:center"><h2>Fonctionnalité non activée</h2><p>Contactez LionTech pour activer la gestion des employés.</p></div>';
    exit;
}

$subStatus = $business['subscription_status'] ?? 'active';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = in_array($subStatus, ['expired','suspended'], true) || ($expiresAt && strtotime($expiresAt) < time());

/* ── Attendance settings ── */
$gpsRequired  = true;
$businessLat  = null;
$businessLng  = null;
$gpsRadius    = 200;
$reviewBuffer = 300;
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $gpsRequired  = (int)($settings['gps_required']   ?? 1) === 1;
        $businessLat  = $settings['business_latitude']  !== null ? (float)$settings['business_latitude']  : null;
        $businessLng  = $settings['business_longitude'] !== null ? (float)$settings['business_longitude'] : null;
        $gpsRadius    = (int)($settings['gps_radius_meters'] ?? 200);
        $reviewBuffer = (int)($settings['review_buffer_meters'] ?? 300);
    }
} catch (Throwable $e) {}

$success = '';
$error   = '';

/* ── POST actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        /* Save work schedule (employee only) */
        if ($action === 'save_schedule' && $isEmployee) {
            $days = [
                'monday'    => isset($_POST['monday'])    ? 1 : 0,
                'tuesday'   => isset($_POST['tuesday'])   ? 1 : 0,
                'wednesday' => isset($_POST['wednesday']) ? 1 : 0,
                'thursday'  => isset($_POST['thursday'])  ? 1 : 0,
                'friday'    => isset($_POST['friday'])    ? 1 : 0,
                'saturday'  => isset($_POST['saturday'])  ? 1 : 0,
                'sunday'    => isset($_POST['sunday'])    ? 1 : 0,
            ];
            $startTime = trim($_POST['start_time']     ?? '') ?: null;
            $endTime   = trim($_POST['end_time']       ?? '') ?: null;
            $notes     = trim($_POST['schedule_notes'] ?? '') ?: null;

            $pdo->prepare("INSERT INTO work_schedules
                (user_id, business_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday, start_time, end_time, notes)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    monday=VALUES(monday), tuesday=VALUES(tuesday), wednesday=VALUES(wednesday),
                    thursday=VALUES(thursday), friday=VALUES(friday), saturday=VALUES(saturday),
                    sunday=VALUES(sunday), start_time=VALUES(start_time), end_time=VALUES(end_time),
                    notes=VALUES(notes), updated_at=NOW()")
                ->execute([
                    $userId, $businessId,
                    $days['monday'], $days['tuesday'], $days['wednesday'],
                    $days['thursday'], $days['friday'], $days['saturday'], $days['sunday'],
                    $startTime, $endTime, $notes
                ]);
            $success = 'Horaire sauvegardé avec succès.';
        }

        /* Clock In / Out */
        if (in_array($action, ['clock_in','clock_out'], true)) {
            if ($isExpired)
                throw new Exception('Votre abonnement est expiré. Le clock in/out est désactivé.');

            $lat      = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
            $lng      = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
            $accuracy = isset($_POST['accuracy'])  && $_POST['accuracy']  !== '' ? (float)$_POST['accuracy']  : null;

            $gpsStatus = 'pending_review';
            $distance  = null;
            $gpsNote   = 'GPS non vérifié';

            if ($lat !== null && $lng !== null && $businessLat !== null && $businessLng !== null) {
                $distance = haversineMeters($lat, $lng, $businessLat, $businessLng);
                if ($distance <= $gpsRadius) {
                    $gpsStatus = 'on_site';
                    $gpsNote   = 'Employé sur place';
                } elseif ($distance <= ($gpsRadius + $reviewBuffer)) {
                    $gpsStatus = 'pending_review';
                    $gpsNote   = 'Légèrement hors zone — validation recommandée';
                } else {
                    $gpsStatus = 'rejected_far';
                    $gpsNote   = 'Trop loin du business (' . round($distance) . 'm)';
                }
            } elseif (!$gpsRequired) {
                $gpsStatus = 'no_gps_allowed';
                $gpsNote   = 'GPS non disponible mais autorisé';
            }

            if ($gpsStatus === 'rejected_far')
                throw new Exception('Action refusée : vous semblez trop loin du business (' . round($distance) . 'm). Rayon autorisé : ' . $gpsRadius . 'm.');

            if ($action === 'clock_in') {
                $stmt = $pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL LIMIT 1");
                $stmt->execute([$userId, $businessId]);
                if ($stmt->fetch()) throw new Exception('Vous êtes déjà clocké(e) in. Faites un clock out d\'abord.');

                $pdo->prepare("INSERT INTO employee_attendance
                    (business_id, user_id, clock_in_at, clock_in_latitude, clock_in_longitude, clock_in_accuracy, gps_status, distance_meters, status, note, created_at)
                    VALUES (?,?,NOW(),?,?,?,?,?,'clocked_in',?,NOW())")
                    ->execute([$businessId, $userId, $lat, $lng, $accuracy, $gpsStatus, $distance !== null ? round($distance, 2) : null, $gpsNote]);

                logActivity($pdo, $userId, $businessId, 'clock_in', 'Clock in: ' . ($user['full_name'] ?? 'Employé'));
                $success = '<span class="icon-ok">✓</span> Clock in enregistré avec succès !';
            }

            if ($action === 'clock_out') {
                $stmt = $pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1");
                $stmt->execute([$userId, $businessId]);
                $open = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$open) throw new Exception('Aucun clock in ouvert trouvé. Faites d\'abord un clock in.');

                $pdo->prepare("UPDATE employee_attendance
                    SET clock_out_at=NOW(), clock_out_latitude=?, clock_out_longitude=?, clock_out_accuracy=?,
                        gps_status=?, status='clocked_out', updated_at=NOW()
                    WHERE attendance_id=? AND user_id=? AND business_id=?")
                    ->execute([$lat, $lng, $accuracy, $gpsStatus, (int)$open['attendance_id'], $userId, $businessId]);

                logActivity($pdo, $userId, $businessId, 'clock_out', 'Clock out: ' . ($user['full_name'] ?? 'Employé'));
                $success = '<span class="icon-ok">✓</span> Clock out enregistré avec succès !';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* ── Load current attendance ── */
$currentAttendance = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1");
    $stmt->execute([$userId, $businessId]);
    $currentAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* ── Load attendance history ── */
$attendanceHistory = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? ORDER BY clock_in_at DESC LIMIT 15");
    $stmt->execute([$userId, $businessId]);
    $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* ── Today stats ── */
$todayCount   = 0;
$totalMinutes = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND DATE(clock_in_at)=CURDATE()");
    $stmt->execute([$userId, $businessId]);
    $todayRows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $todayCount   = count($todayRows);
    foreach ($todayRows as $r) {
        if (!empty($r['clock_out_at']))
            $totalMinutes += max(0, (strtotime($r['clock_out_at']) - strtotime($r['clock_in_at'])) / 60);
    }
} catch (Throwable $e) {}

/* ── Work schedule (employee only) ── */
$mySchedule = null;
if ($isEmployee) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM work_schedules WHERE user_id=? AND business_id=? LIMIT 1");
        $stmt->execute([$userId, $businessId]);
        $mySchedule = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

/* ── Who is clocked in NOW (only if current user is clocked in) ── */
$teamClockedIn = [];
if ($currentAttendance) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.role,
                   ep.employee_role, ep.job_title, ep.profile_photo_url,
                   ea.clock_in_at, ea.gps_status
            FROM employee_attendance ea
            JOIN users u ON u.user_id = ea.user_id
            LEFT JOIN employee_profiles ep
                ON ep.user_id = ea.user_id AND ep.business_id = ea.business_id
            WHERE ea.business_id = ?
              AND ea.clock_out_at IS NULL
              AND ea.user_id != ?
            ORDER BY ea.clock_in_at ASC
        ");
        $stmt->execute([$businessId, $userId]);
        $teamClockedIn = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'E')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'E', 0, 2);

$dayNames   = ['monday'=>'Lundi','tuesday'=>'Mardi','wednesday'=>'Mercredi','thursday'=>'Jeudi','friday'=>'Vendredi','saturday'=>'Samedi','sunday'=>'Dimanche'];
$dayNamesEn = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Clock In / Clock Out — LionTech</title>
<link rel="icon" type="image/png" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
<link rel="manifest" href="<?= APP_URL ?>/manifest.json"/>
<link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
<style>
*{box-sizing:border-box}
.ca-wrap{max-width:1100px;margin:0 auto;padding:20px 24px 40px}
.ca-alert{padding:12px 18px;border-radius:10px;font-size:13.5px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.ca-alert.success{background:#F0FDF4;border:1px solid #86EFAC;color:#166534}
.ca-alert.error{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}
.ca-alert.warning{background:#FEF3C7;border:1px solid #FDE68A;color:#92400E}
.ca-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.ca-card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(11,31,58,.07);overflow:hidden;margin-bottom:20px}
.ca-card:last-child{margin-bottom:0}
.ca-card-head{padding:16px 20px;border-bottom:1px solid #F1F5F9}
.ca-card-head h2{font-size:15px;font-weight:700;color:#0B1F3A;margin:0}
.ca-card-head p{font-size:12.5px;color:#94A3B8;margin:3px 0 0}
.ca-card-body{padding:20px}
.ca-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.ca-stat{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 8px rgba(11,31,58,.06);display:flex;align-items:center;gap:12px}
.ca-stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.ca-stat-icon.blue{background:#EFF6FF}
.ca-stat-icon.green{background:#F0FDF4}
.ca-stat-icon.amber{background:#FEF3C7}
.ca-stat-icon.purple{background:#F5F3FF}
.ca-stat small{font-size:11px;color:#94A3B8;display:block}
.ca-stat strong{font-size:18px;font-weight:800;color:#0B1F3A}

/* GPS */
.gps-box{border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px;transition:.3s}
.gps-box.waiting{background:#F8FAFC;border:1.5px solid #E5E7EB;color:#64748B}
.gps-box.loading{background:#FEF3C7;border:1.5px solid #FDE68A;color:#92400E}
.gps-box.ok{background:#F0FDF4;border:1.5px solid #86EFAC;color:#166534}
.gps-box.review{background:#FEF3C7;border:1.5px solid #FDE68A;color:#92400E}
.gps-box.denied{background:#FEF2F2;border:1.5px solid #FECACA;color:#991B1B}
.gps-box.far{background:#FEF2F2;border:1.5px solid #FECACA;color:#991B1B}

/* Clock button */
.clock-btn{width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit;transition:.15s;margin-top:4px}
.clock-btn:disabled{opacity:.4;cursor:not-allowed}
.clock-btn.in{background:linear-gradient(135deg,#16A34A,#22C55E);color:#fff}
.clock-btn.out{background:linear-gradient(135deg,#DC2626,#EF4444);color:#fff}
.clock-btn:hover:not(:disabled){opacity:.9;transform:translateY(-1px)}

/* Session box */
.session-box{background:linear-gradient(135deg,#F0FDF4,#DCFCE7);border:1.5px solid #86EFAC;border-radius:12px;padding:16px;text-align:center;margin-bottom:16px}
.session-time{font-size:32px;font-weight:900;color:#166534;font-family:monospace;letter-spacing:2px}

/* Team grid */
.team-grid{padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.team-card{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px}
.team-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #16A34A}
.team-avatar-ph{width:40px;height:40px;border-radius:50%;background:#0B1F3A;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;font-weight:700;flex-shrink:0}
.team-name{font-size:13px;font-weight:700;color:#0B1F3A}
.team-role{font-size:11px;color:#6B7280}
.team-time{font-size:11px;color:#16A34A;font-weight:600}

/* Schedule */
.day-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:16px}
.day-check{text-align:center}
.day-check input{display:none}
.day-label{display:block;padding:8px 4px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:11px;font-weight:600;color:#64748B;cursor:pointer;transition:.15s;text-align:center}
.day-check input:checked+.day-label{background:#0B1F3A;color:#fff;border-color:#0B1F3A}
.day-label:hover{border-color:#1A9E7A;color:#1A9E7A}
.time-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.ca-field label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.ca-field input,.ca-field textarea{width:100%;padding:10px 12px;border:1.5px solid #E5E7EB;border-radius:9px;font-size:14px;font-family:inherit;outline:none}
.ca-field input:focus,.ca-field textarea:focus{border-color:#1A9E7A}
.save-btn{background:#0B1F3A;color:#fff;border:none;border-radius:10px;padding:11px 22px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit}
.save-btn:hover{background:#1A9E7A}

/* Schedule display pills */
.schedule-display{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.day-pill{padding:5px 12px;border-radius:50px;font-size:12px;font-weight:700}
.day-pill.active{background:#0B1F3A;color:#fff}
.day-pill.inactive{background:#F1F5F9;color:#94A3B8}

/* History table */
.ca-table{width:100%;border-collapse:collapse;font-size:13px}
.ca-table th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;background:#F8FAFC;border-bottom:1.5px solid #E5E7EB}
.ca-table td{padding:11px 14px;border-bottom:1px solid #F1F5F9;color:#374151}
.ca-table tr:last-child td{border-bottom:none}
.ca-badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700}
.ca-badge.on_site{background:#DCFCE7;color:#166534}
.ca-badge.pending_review{background:#FEF3C7;color:#92400E}
.ca-badge.rejected_far{background:#FEE2E2;color:#991B1B}
.ca-badge.no_gps_allowed{background:#EFF6FF;color:#1E40AF}
.ca-badge.clocked_in{background:#DCFCE7;color:#166534}
.ca-badge.clocked_out{background:#F1F5F9;color:#64748B}

@media(max-width:700px){
  .ca-grid{grid-template-columns:1fr}
  .ca-stats{grid-template-columns:1fr 1fr}
  .day-grid{grid-template-columns:repeat(4,1fr)}
  .team-grid{grid-template-columns:1fr}
}
</style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="od-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

<main class="od-main">
  <header class="od-topbar">
    <button class="od-menu-btn" id="od-menu-btn">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <div class="od-business-title">
      <h1 data-i18n="page_title">Clock In / Clock Out</h1>
      <p><?= e($business['business_name'] ?? 'Business') ?> — <span data-i18n="page_sub">Enregistrez votre présence</span></p>
    </div>
    <div class="od-top-actions">
      <button class="od-lang" id="langBtn">EN</button>
      <div class="od-avatar"><?= e($initials) ?></div>
    </div>
  </header>

  <div class="ca-wrap">

    <?php if ($isExpired): ?>
    <div class="ca-alert warning"><span class="icon-warn">⚠</span> <span data-i18n="expired_warning">Votre abonnement est expiré. Les actions sont désactivées.</span></div>
    <?php endif; ?>
    <?php if ($success): ?><div class="ca-alert success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="ca-alert error"><span class="icon-warn">⚠</span> <?= e($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="ca-stats">
      <div class="ca-stat">
        <div class="ca-stat-icon <?= $currentAttendance ? 'green' : 'blue' ?>">
          <?= $currentAttendance ? '<span class="dot-green">●</span>' : '<span class="dot-gray">○</span>' ?>
        </div>
        <div>
          <small data-i18n="stat_status">Statut actuel</small>
          <strong data-i18n="<?= $currentAttendance ? 'status_in' : 'status_out' ?>">
            <?= $currentAttendance ? 'Clocké In' : 'Clocké Out' ?>
          </strong>
        </div>
      </div>
      <div class="ca-stat">
        <div class="ca-stat-icon green">🗓️</div>
        <div>
          <small data-i18n="stat_sessions">Sessions aujourd'hui</small>
          <strong><?= (int)$todayCount ?></strong>
        </div>
      </div>
      <div class="ca-stat">
        <div class="ca-stat-icon amber">⏳</div>
        <div>
          <small data-i18n="stat_hours">Heures complétées</small>
          <strong><?= floor($totalMinutes/60) ?>h<?= str_pad((string)((int)$totalMinutes%60), 2, '0', STR_PAD_LEFT) ?>m</strong>
        </div>
      </div>
      <div class="ca-stat">
        <div class="ca-stat-icon purple"><span class="icon-pin">●</span></div>
        <div>
          <small data-i18n="stat_radius">Rayon GPS</small>
          <strong><?= $gpsRadius ?>m</strong>
        </div>
      </div>
    </div>

    <div class="ca-grid">

      <!-- ── CLOCK ACTION ── -->
      <div class="ca-card" style="margin-bottom:0">
        <div class="ca-card-head">
          <h2 data-i18n="clock_title">Action de présence</h2>
          <p data-i18n="clock_sub">Autorisez la localisation GPS avant de pointer.</p>
        </div>
        <div class="ca-card-body">

          <div class="gps-box waiting" id="gpsBox">
            <span id="gpsIcon"><span class="icon-pin">●</span></span>
            <span id="gpsText" data-i18n="gps_waiting">En attente du GPS — cliquez sur "Activer GPS"</span>
          </div>

          <button type="button" id="activateGps"
            style="width:100%;padding:11px;background:#0B1F3A;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-bottom:8px;font-family:inherit">
            <span class="icon-pin">●</span> <span data-i18n="btn_activate_gps">Activer la localisation GPS</span>
          </button>

          <button type="button" id="skipGps"
            style="width:100%;padding:9px;background:#fff;color:#64748B;border:1.5px solid #E5E7EB;border-radius:10px;font-size:12.5px;cursor:pointer;margin-bottom:14px;font-family:inherit;display:none">
            <span class="icon-warn">⚠</span> <span data-i18n="btn_skip_gps">Continuer sans GPS (sera validé par le manager)</span>
          </button>

          <?php if ($currentAttendance): ?>
          <div class="session-box">
            <div style="font-size:12px;color:#6B7280;margin-bottom:4px" data-i18n="clocked_since">Clocké in depuis</div>
            <div class="session-time" id="sessionTimer">
              <?= e(date('H:i', strtotime($currentAttendance['clock_in_at']))) ?>
            </div>
            <div style="font-size:12px;color:#6B7280;margin-top:4px">
              <?= e(date('d/m/Y', strtotime($currentAttendance['clock_in_at']))) ?>
            </div>
          </div>
          <form method="POST" id="clockForm">
            <input type="hidden" name="action" value="clock_out">
            <input type="hidden" name="latitude"  id="gpsLat">
            <input type="hidden" name="longitude" id="gpsLng">
            <input type="hidden" name="accuracy"  id="gpsAcc">
            <button type="submit" class="clock-btn out" id="clockBtn" disabled <?= $isExpired ? 'disabled' : '' ?>>
              🔴 <span data-i18n="btn_clock_out">Clock Out</span>
            </button>
          </form>
          <?php else: ?>
          <div style="background:#F8FAFC;border:1.5px dashed #E2E8F0;border-radius:12px;padding:20px;text-align:center;margin-bottom:16px">
            <div style="font-size:36px;margin-bottom:8px">⏰</div>
            <p style="font-size:13px;color:#94A3B8;margin:0" data-i18n="not_clocked_in">Vous n'êtes pas encore clocké in aujourd'hui.</p>
          </div>
          <form method="POST" id="clockForm">
            <input type="hidden" name="action" value="clock_in">
            <input type="hidden" name="latitude"  id="gpsLat">
            <input type="hidden" name="longitude" id="gpsLng">
            <input type="hidden" name="accuracy"  id="gpsAcc">
            <button type="submit" class="clock-btn in" id="clockBtn" disabled <?= $isExpired ? 'disabled' : '' ?>>
              <span class="dot-green">●</span> <span data-i18n="btn_clock_in">Clock In</span>
            </button>
          </form>
          <?php endif; ?>

          <p style="font-size:11.5px;color:#94A3B8;margin-top:12px;text-align:center" data-i18n="locked_note">
            Les heures enregistrées sont verrouillées et ne peuvent pas être modifiées.
          </p>
        </div>
      </div>

      <!-- ── HOW IT WORKS ── -->
      <div class="ca-card" style="margin-bottom:0">
        <div class="ca-card-head">
          <h2 data-i18n="how_title">Comment ça marche</h2>
          <p data-i18n="how_sub">Processus de pointage GPS</p>
        </div>
        <div class="ca-card-body">
          <?php
          $steps = [
            ['icon'=>'<span class="icon-phone"><span class="icon-phone">☎</span></span>','fr'=>'Connectez-vous sur votre téléphone ou ordinateur.',      'en'=>'Log in on your phone or computer.'],
            ['icon'=>'<span class="icon-pin">●</span>','fr'=>'Cliquez sur "Activer GPS" et autorisez la localisation.', 'en'=>'Click "Activate GPS" and allow location access.'],
            ['icon'=>'<span class="dot-green">●</span>','fr'=>'Cliquez Clock In en arrivant au travail.',                'en'=>'Click Clock In when you arrive at work.'],
            ['icon'=>'🔴','fr'=>'Cliquez Clock Out en quittant.',                          'en'=>'Click Clock Out when you leave.'],
            ['icon'=>'👀','fr'=>'Le patron voit vos présences en temps réel.',             'en'=>'The owner sees your attendance in real time.'],
          ];
          ?>
          <div style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($steps as $i => $step): ?>
            <div style="display:flex;gap:12px;align-items:flex-start">
              <div style="width:30px;height:30px;border-radius:50%;background:#0B1F3A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0"><?= $i+1 ?></div>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:18px"><?= $step['icon'] ?></span>
                <p style="font-size:13px;color:#6B7280;margin:0" data-fr="<?= e($step['fr']) ?>" data-en="<?= e($step['en']) ?>"><?= e($step['fr']) ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($businessLat && $businessLng): ?>
          <div style="margin-top:16px;padding:12px;background:#F0FDF4;border-radius:10px;font-size:12.5px;color:#166534">
            <span class="icon-ok">✓</span> <strong data-i18n="gps_configured">GPS configuré</strong> —
            <span data-i18n="radius_text">Rayon autorisé :</span> <strong><?= $gpsRadius ?>m</strong>
          </div>
          <?php else: ?>
          <div style="margin-top:16px;padding:12px;background:#FEF3C7;border-radius:10px;font-size:12.5px;color:#92400E">
            <span class="icon-warn">⚠</span> <span data-i18n="gps_not_configured">GPS business non configuré — pointage marqué "en révision".</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /.ca-grid -->

    <!-- ══ WHO IS AT WORK NOW (only when clocked in) ══ -->
    <?php if ($currentAttendance): ?>
    <div class="ca-card">
      <div class="ca-card-head">
        <h2 data-i18n="team_title"><span class="icon-users">◎</span> Qui est au travail maintenant ?</h2>
        <p data-i18n="team_sub">Collègues actuellement clockés in</p>
      </div>
      <?php if (!empty($teamClockedIn)): ?>
      <div class="team-grid">
        <?php foreach ($teamClockedIn as $col): ?>
        <div class="team-card">
          <?php if (!empty($col['profile_photo_url'])): ?>
          <img class="team-avatar" src="<?= e($col['profile_photo_url']) ?>" alt="">
          <?php else: ?>
          <div class="team-avatar-ph"><?= strtoupper(substr($col['full_name'] ?? 'E', 0, 1)) ?></div>
          <?php endif; ?>
          <div>
            <div class="team-name"><?= e($col['full_name']) ?></div>
            <div class="team-role"><?= e($col['job_title'] ?: ucwords(str_replace('_',' ', $col['employee_role'] ?: $col['role']))) ?></div>
            <div class="team-time"><span class="dot-green">●</span> <?= e(date('H:i', strtotime($col['clock_in_at']))) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="padding:24px;text-align:center;color:#94A3B8;font-size:13.5px" data-i18n="team_alone">
        Vous êtes le seul au travail pour le moment.
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ WORK SCHEDULE (employee only) ══ -->
    <?php if ($isEmployee): ?>
    <div class="ca-card">
      <div class="ca-card-head">
        <h2 data-i18n="schedule_title"><span class="icon-cal">▦</span> Mon horaire de travail</h2>
        <p data-i18n="schedule_sub">Votre patron et manager peuvent consulter cet horaire.</p>
      </div>
      <div class="ca-card-body">
        <?php if ($mySchedule): ?>
        <p style="font-size:12px;font-weight:700;color:#374151;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px" data-i18n="current_schedule">Horaire actuel</p>
        <div class="schedule-display">
          <?php foreach ($dayNames as $key => $dayFr): ?>
          <span class="day-pill <?= $mySchedule[$key] ? 'active' : 'inactive' ?>"
                data-fr="<?= $dayFr ?>" data-en="<?= $dayNamesEn[$key] ?>"><?= $dayFr ?></span>
          <?php endforeach; ?>
        </div>
        <?php if ($mySchedule['start_time'] && $mySchedule['end_time']): ?>
        <div style="display:flex;gap:16px;font-size:13.5px;margin-bottom:8px">
          <span>🕐 <strong data-i18n="start_time">Début :</strong> <?= e(date('H:i', strtotime($mySchedule['start_time']))) ?></span>
          <span>🕔 <strong data-i18n="end_time">Fin :</strong> <?= e(date('H:i', strtotime($mySchedule['end_time']))) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($mySchedule['notes']): ?>
        <p style="font-size:13px;color:#6B7280;margin-bottom:14px">📝 <?= e($mySchedule['notes']) ?></p>
        <?php endif; ?>
        <hr style="border:none;border-top:1px solid #F1F5F9;margin-bottom:14px">
        <p style="font-size:12.5px;color:#94A3B8;margin-bottom:14px" data-i18n="update_schedule">Modifier votre horaire :</p>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="action" value="save_schedule">
          <p style="font-size:12px;font-weight:700;color:#374151;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px" data-i18n="days_label">Jours de travail</p>
          <div class="day-grid">
            <?php foreach ($dayNames as $key => $dayFr): ?>
            <div class="day-check">
              <input type="checkbox" name="<?= $key ?>" id="day_<?= $key ?>" <?= ($mySchedule[$key] ?? 0) ? 'checked' : '' ?>>
              <label for="day_<?= $key ?>" class="day-label" data-fr="<?= substr($dayFr,0,3) ?>" data-en="<?= substr($dayNamesEn[$key],0,3) ?>">
                <?= substr($dayFr, 0, 3) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="time-row">
            <div class="ca-field">
              <label data-i18n="start_time_label">Heure de début</label>
              <input type="time" name="start_time" value="<?= e($mySchedule['start_time'] ?? '08:00') ?>"/>
            </div>
            <div class="ca-field">
              <label data-i18n="end_time_label">Heure de fin</label>
              <input type="time" name="end_time" value="<?= e($mySchedule['end_time'] ?? '17:00') ?>"/>
            </div>
          </div>
          <div class="ca-field" style="margin-bottom:16px">
            <label data-i18n="schedule_notes_label">Notes (optionnel)</label>
            <textarea name="schedule_notes" rows="2" style="resize:none"
                      placeholder="Ex: Je travaille le matin seulement le samedi..."><?= e($mySchedule['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="save-btn" data-i18n="btn_save_schedule"><span class="icon-save">▣</span> Sauvegarder l'horaire</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ HISTORY TABLE ══ -->
    <div class="ca-card">
      <div class="ca-card-head">
        <h2 data-i18n="history_title">Historique de présence</h2>
        <p data-i18n="history_sub">15 derniers enregistrements</p>
      </div>
      <div style="overflow-x:auto">
        <table class="ca-table">
          <thead>
            <tr>
              <th data-i18n="col_date">Date</th>
              <th data-i18n="col_in">Clock In</th>
              <th data-i18n="col_out">Clock Out</th>
              <th data-i18n="col_duration">Durée</th>
              <th data-i18n="col_gps">GPS</th>
              <th data-i18n="col_status">Statut</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$attendanceHistory): ?>
          <tr><td colspan="6" style="padding:28px;text-align:center;color:#94A3B8" data-i18n="no_history">Aucun historique pour le moment.</td></tr>
          <?php else: foreach ($attendanceHistory as $row): ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime($row['clock_in_at']))) ?></td>
            <td><strong><?= e(date('H:i', strtotime($row['clock_in_at']))) ?></strong></td>
            <td><?= !empty($row['clock_out_at']) ? e(date('H:i', strtotime($row['clock_out_at']))) : '—' ?></td>
            <td><?= e(durationText($row['clock_in_at'], $row['clock_out_at'] ?? null)) ?></td>
            <td>
              <?php
              $gpsLabels = ['on_site'=>'Sur place','pending_review'=>'À vérifier','rejected_far'=>'Trop loin','no_gps_allowed'=>'Sans GPS'];
              ?>
              <span class="ca-badge <?= e($row['gps_status'] ?? 'pending_review') ?>">
                <?= e($gpsLabels[$row['gps_status'] ?? ''] ?? ($row['gps_status'] ?? '—')) ?>
              </span>
            </td>
            <td>
              <span class="ca-badge <?= e($row['status'] ?? '') ?>">
                <?= $row['status'] === 'clocked_in' ? 'En cours' : 'Terminé' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /.ca-wrap -->
</main>
</div>

<script>
window.LT_GPS = {
    businessLat  : <?= json_encode($businessLat) ?>,
    businessLng  : <?= json_encode($businessLng) ?>,
    gpsRadius    : <?= json_encode($gpsRadius) ?>,
    reviewBuffer : <?= json_encode($reviewBuffer) ?>,
    gpsRequired  : <?= json_encode($gpsRequired) ?>
};

const T = {
  fr: {
    page_title:'Clock In / Clock Out', page_sub:'Enregistrez votre présence',
    expired_warning:'Votre abonnement est expiré. Les actions sont désactivées.',
    stat_status:'Statut actuel', status_in:'Clocké In', status_out:'Clocké Out',
    stat_sessions:"Sessions aujourd'hui", stat_hours:'Heures complétées', stat_radius:'Rayon GPS',
    clock_title:'Action de présence', clock_sub:'Autorisez la localisation GPS avant de pointer.',
    gps_waiting:'En attente du GPS — cliquez sur "Activer GPS"',
    gps_loading:'Localisation en cours...',
    gps_denied:'<span class="icon-err">✗</span> GPS refusé — autorisez la localisation dans votre navigateur',
    gps_unavailable:'<span class="icon-warn">⚠</span> GPS indisponible — pointage sera marqué "à vérifier"',
    btn_activate_gps:'Activer la localisation GPS',
    btn_skip_gps:'Continuer sans GPS (sera validé par le manager)',
    clocked_since:'Clocké in depuis',
    not_clocked_in:"Vous n'êtes pas encore clocké in aujourd'hui.",
    btn_clock_in:'Clock In', btn_clock_out:'Clock Out',
    locked_note:'Les heures enregistrées sont verrouillées.',
    how_title:'Comment ça marche', how_sub:'Processus de pointage GPS',
    gps_configured:'GPS configuré', radius_text:'Rayon autorisé :',
    gps_not_configured:'GPS business non configuré — pointage marqué "en révision".',
    team_title:'<span class="icon-users">◎</span> Qui est au travail maintenant ?',
    team_sub:'Collègues actuellement clockés in',
    team_alone:'Vous êtes le seul au travail pour le moment.',
    schedule_title:'<span class="icon-cal">▦</span> Mon horaire de travail',
    schedule_sub:'Votre patron et manager peuvent consulter cet horaire.',
    current_schedule:'Horaire actuel', update_schedule:'Modifier votre horaire :',
    days_label:'Jours de travail', start_time:'Début :', end_time:'Fin :',
    start_time_label:'Heure de début', end_time_label:'Heure de fin',
    schedule_notes_label:'Notes (optionnel)',
    btn_save_schedule:"<span class="icon-save">▣</span> Sauvegarder l'horaire",
    history_title:'Historique de présence', history_sub:'15 derniers enregistrements',
    col_date:'Date', col_in:'Clock In', col_out:'Clock Out',
    col_duration:'Durée', col_gps:'GPS', col_status:'Statut',
    no_history:'Aucun historique pour le moment.',
  },
  en: {
    page_title:'Clock In / Clock Out', page_sub:'Record your attendance',
    expired_warning:'Your subscription has expired. Actions are disabled.',
    stat_status:'Current status', status_in:'Clocked In', status_out:'Clocked Out',
    stat_sessions:'Sessions today', stat_hours:'Hours completed', stat_radius:'GPS radius',
    clock_title:'Attendance action', clock_sub:'Allow GPS location before clocking.',
    gps_waiting:'Waiting for GPS — click "Activate GPS"',
    gps_loading:'Getting location...',
    gps_denied:'<span class="icon-err">✗</span> GPS denied — allow location in browser settings',
    gps_unavailable:'<span class="icon-warn">⚠</span> GPS unavailable — attendance will be marked "pending review"',
    btn_activate_gps:'Activate GPS location',
    btn_skip_gps:'Continue without GPS (needs manager approval)',
    clocked_since:'Clocked in since',
    not_clocked_in:"You haven't clocked in today yet.",
    btn_clock_in:'Clock In', btn_clock_out:'Clock Out',
    locked_note:'Recorded times are locked and cannot be modified.',
    how_title:'How it works', how_sub:'GPS clock-in process',
    gps_configured:'GPS configured', radius_text:'Allowed radius:',
    gps_not_configured:'Business GPS not configured — attendance will be marked "pending review".',
    team_title:'<span class="icon-users">◎</span> Who is at work right now?',
    team_sub:'Colleagues currently clocked in',
    team_alone:'You are the only one at work right now.',
    schedule_title:'<span class="icon-cal">▦</span> My work schedule',
    schedule_sub:'Your owner and manager can view this schedule.',
    current_schedule:'Current schedule', update_schedule:'Update your schedule:',
    days_label:'Working days', start_time:'Start:', end_time:'End:',
    start_time_label:'Start time', end_time_label:'End time',
    schedule_notes_label:'Notes (optional)',
    btn_save_schedule:'<span class="icon-save">▣</span> Save schedule',
    history_title:'Attendance history', history_sub:'Last 15 records',
    col_date:'Date', col_in:'Clock In', col_out:'Clock Out',
    col_duration:'Duration', col_gps:'GPS', col_status:'Status',
    no_history:'No history yet.',
  }
};

let lang = localStorage.getItem('lt_lang') || 'fr';
const langBtn = document.getElementById('langBtn');

function applyLang() {
  const t = T[lang];
  document.documentElement.lang = lang;
  langBtn.textContent = lang === 'fr' ? 'EN' : 'FR';
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n; if (t[k] !== undefined) el.textContent = t[k];
  });
  document.querySelectorAll('[data-fr][data-en]').forEach(el => {
    el.textContent = el.dataset[lang] || el.dataset.fr;
  });
  document.querySelectorAll('.day-pill[data-fr][data-en]').forEach(el => {
    el.textContent = el.dataset[lang];
  });
  localStorage.setItem('lt_lang', lang);
}
langBtn.addEventListener('click', () => { lang = lang === 'fr' ? 'en' : 'fr'; applyLang(); });
applyLang();

/* Sidebar */
document.getElementById('od-menu-btn')?.addEventListener('click', () => {
  const sb = document.getElementById('od-sidebar');
  const ov = document.getElementById('od-overlay');
  sb?.classList.toggle('open');
  if (ov) ov.style.display = sb?.classList.contains('open') ? 'block' : 'none';
});

/* GPS */
const gpsBox    = document.getElementById('gpsBox');
const gpsText   = document.getElementById('gpsText');
const gpsIcon   = document.getElementById('gpsIcon');
const clockBtn  = document.getElementById('clockBtn');
const gpsLatEl  = document.getElementById('gpsLat');
const gpsLngEl  = document.getElementById('gpsLng');
const gpsAccEl  = document.getElementById('gpsAcc');
const activateBtn = document.getElementById('activateGps');
const skipBtn     = document.getElementById('skipGps');
const GPS = window.LT_GPS;

function setGpsBox(state, text) {
  gpsBox.className = 'gps-box ' + state;
  gpsText.textContent = text;
  const icons = { waiting:'<span class="icon-pin">●</span>', loading:'🔄', ok:'<span class="icon-ok">✓</span>', denied:'<span class="icon-err">✗</span>', far:'<span class="icon-no">⊘</span>', review:'<span class="icon-warn">⚠</span>' };
  gpsIcon.textContent = icons[state] || '<span class="icon-pin">●</span>';
}

function enableClockBtn() {
  if (clockBtn && !<?= json_encode($isExpired) ?>) clockBtn.disabled = false;
}

function haversine(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const dLat = (lat2-lat1)*Math.PI/180;
  const dLng = (lng2-lng1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function onGpsSuccess(pos) {
  const lat = pos.coords.latitude;
  const lng = pos.coords.longitude;
  const acc = pos.coords.accuracy;
  if (gpsLatEl) gpsLatEl.value = lat;
  if (gpsLngEl) gpsLngEl.value = lng;
  if (gpsAccEl) gpsAccEl.value = acc;

  if (GPS.businessLat && GPS.businessLng) {
    const dist = haversine(lat, lng, GPS.businessLat, GPS.businessLng);
    const d = Math.round(dist);
    if (dist <= GPS.gpsRadius) {
      setGpsBox('ok', (lang==='fr'?'<span class="icon-ok">✓</span> Sur place — ':'<span class="icon-ok">✓</span> On site — ') + d + 'm');
      enableClockBtn();
    } else if (dist <= GPS.gpsRadius + GPS.reviewBuffer) {
      setGpsBox('review', (lang==='fr'?'<span class="icon-warn">⚠</span> Légèrement hors zone (':'<span class="icon-warn">⚠</span> Slightly outside zone (') + d + 'm)');
      enableClockBtn();
    } else {
      setGpsBox('far', (lang==='fr'?'<span class="icon-no">⊘</span> Trop loin : ':'<span class="icon-no">⊘</span> Too far: ') + d + 'm');
      if (skipBtn) skipBtn.style.display = 'block';
    }
  } else {
    setGpsBox('ok', lang==='fr'?'<span class="icon-pin">●</span> Position obtenue':'<span class="icon-pin">●</span> Position obtained');
    enableClockBtn();
  }
}

function onGpsError(err) {
  let msg = '';
  if (err.code === 1) {
    msg = T[lang].gps_denied;
    setGpsBox('denied', msg);
  } else {
    msg = T[lang].gps_unavailable;
    setGpsBox('review', msg);
    enableClockBtn(); /* allow with pending_review status */
  }
  if (skipBtn) skipBtn.style.display = 'block';
  if (!GPS.gpsRequired) enableClockBtn();
}

activateBtn?.addEventListener('click', () => {
  if (!navigator.geolocation) {
    setGpsBox('denied', lang==='fr'?'GPS non supporté.':'GPS not supported.');
    enableClockBtn();
    return;
  }
  setGpsBox('loading', T[lang].gps_loading);
  activateBtn.style.display = 'none';
  setTimeout(() => { if (skipBtn && clockBtn?.disabled) skipBtn.style.display = 'block'; }, 8000);
  navigator.geolocation.getCurrentPosition(onGpsSuccess, onGpsError, {
    enableHighAccuracy: true, timeout: 15000, maximumAge: 0
  });
});

skipBtn?.addEventListener('click', () => {
  setGpsBox('review', lang==='fr'
    ? '<span class="icon-warn">⚠</span> Sans GPS — sera soumis pour validation manager'
    : '<span class="icon-warn">⚠</span> No GPS — will need manager approval');
  enableClockBtn();
  skipBtn.style.display = 'none';
});

/* Live timer */
<?php if ($currentAttendance): ?>
(function() {
  const t0 = new Date('<?= str_replace(' ', 'T', $currentAttendance['clock_in_at']) ?>');
  const el = document.getElementById('sessionTimer');
  if (!el) return;
  function tick() {
    const d = Math.floor((new Date() - t0) / 1000);
    const h = Math.floor(d/3600), m = Math.floor((d%3600)/60), s = d%60;
    el.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  }
  tick(); setInterval(tick, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>