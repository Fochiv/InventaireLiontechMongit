<?php
/* ============================================================
   clock_attendance.php — LionTech Business Manager
   FIXED: Config path, requireRole, sidebar, od-layout
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

if (!$pdo || $businessId <= 0 || $userId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function haversineMeters($lat1,$lng1,$lat2,$lng2): float {
    $earth = 6371000;
    $dLat  = deg2rad($lat2-$lat1); $dLng = deg2rad($lng2-$lng1);
    $a = sin($dLat/2)*sin($dLat/2)+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
    return $earth*2*atan2(sqrt($a),sqrt(1-$a));
}

function logActivity(PDO $pdo,int $userId,int $businessId,string $action,string $desc): void {
    try { $pdo->prepare("INSERT INTO activity_logs (user_id,business_id,action,description,icon,ip_address,created_at) VALUES (?,?,?,?,'clock',?,NOW())")->execute([$userId,$businessId,$action,$desc,$_SERVER['REMOTE_ADDR']??null]); } catch(Throwable $e) {}
}

function durationText($in,$out): string {
    if (!$in||!$out) return '-';
    $mins=max(0,(int)((strtotime($out)-strtotime($in))/60));
    return intdiv($mins,60).'h '.str_pad((string)($mins%60),2,'0',STR_PAD_LEFT).'m';
}

/* Business */
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id=? LIMIT 1");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* Feature gate */
$employeeFeatureEnabled = true;
try {
    $stmt = $pdo->prepare("SELECT employee_management, employee_attendance FROM business_features WHERE business_id=? LIMIT 1");
    $stmt->execute([$businessId]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($feature && (int)$feature['employee_management']!==1 && (int)$feature['employee_attendance']!==1)
        $employeeFeatureEnabled = false;
} catch(Throwable $e) {}

if (!$employeeFeatureEnabled) {
    http_response_code(403);
    echo '<div style="font-family:Inter,sans-serif;padding:40px;text-align:center"><h2>Fonctionnalité non activée</h2><p>Contactez LionTech pour activer la gestion des employés.</p></div>';
    exit;
}

$subStatus = $business['subscription_status'] ?? 'active';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = in_array($subStatus,['expired','suspended'],true) || ($expiresAt && strtotime($expiresAt)<time());

/* Attendance settings */
$gpsRequired=true; $businessLat=null; $businessLng=null; $gpsRadius=200; $reviewBuffer=300;
try {
    $stmt=$pdo->prepare("SELECT * FROM attendance_settings WHERE business_id=? LIMIT 1");
    $stmt->execute([$businessId]);
    $settings=$stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $gpsRequired  = (int)($settings['gps_required']??1)===1;
        $businessLat  = $settings['business_latitude']  !== null ? (float)$settings['business_latitude']  : null;
        $businessLng  = $settings['business_longitude'] !== null ? (float)$settings['business_longitude'] : null;
        $gpsRadius    = (int)($settings['gps_radius_meters']??200);
        $reviewBuffer = (int)($settings['review_buffer_meters']??300);
    }
} catch(Throwable $e) {}

$success=''; $error='';

/* POST actions */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($isExpired && in_array($action,['clock_in','clock_out'],true))
            throw new Exception('Votre abonnement est expiré. Le clock in/out est temporairement désactivé.');

        $lat      = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
        $lng      = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
        $accuracy = isset($_POST['accuracy'])  && $_POST['accuracy']  !== '' ? (float)$_POST['accuracy']  : null;

        $gpsStatus='pending_review'; $distance=null; $gpsNote='GPS non vérifié';

        if ($lat!==null && $lng!==null && $businessLat!==null && $businessLng!==null) {
            $distance=haversineMeters($lat,$lng,$businessLat,$businessLng);
            if      ($distance<=$gpsRadius)                     { $gpsStatus='on_site';       $gpsNote='Employé proche du business'; }
            elseif  ($distance<=($gpsRadius+$reviewBuffer))     { $gpsStatus='pending_review';$gpsNote='Légèrement hors zone, validation recommandée'; }
            else                                                { $gpsStatus='rejected_far';  $gpsNote='Employé trop loin du business'; }
        } elseif (!$gpsRequired) {
            $gpsStatus='no_gps_allowed'; $gpsNote='GPS non disponible mais autorisé';
        }

        if ($gpsStatus==='rejected_far') throw new Exception('Action refusée: vous semblez trop loin du business.');

        if ($action==='clock_in') {
            $stmt=$pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL LIMIT 1");
            $stmt->execute([$userId,$businessId]);
            if ($stmt->fetch()) throw new Exception('Vous êtes déjà clocké(e) in.');
            $pdo->prepare("INSERT INTO employee_attendance (business_id,user_id,clock_in_at,clock_in_latitude,clock_in_longitude,clock_in_accuracy,gps_status,distance_meters,status,note,created_at) VALUES (?,?,NOW(),?,?,?,?,?,'clocked_in',?,NOW())")
                ->execute([$businessId,$userId,$lat,$lng,$accuracy,$gpsStatus,$distance!==null?round($distance,2):null,$gpsNote]);
            logActivity($pdo,$userId,$businessId,'clock_in','Clock in: '.($user['full_name']??'Employé'));
            $success='Clock in enregistré avec succès.';
        }

        if ($action==='clock_out') {
            $stmt=$pdo->prepare("SELECT attendance_id FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1");
            $stmt->execute([$userId,$businessId]);
            $open=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$open) throw new Exception('Aucun clock in ouvert trouvé.');
            $pdo->prepare("UPDATE employee_attendance SET clock_out_at=NOW(),clock_out_latitude=?,clock_out_longitude=?,clock_out_accuracy=?,status='clocked_out',updated_at=NOW() WHERE attendance_id=? AND user_id=? AND business_id=?")
                ->execute([$lat,$lng,$accuracy,(int)$open['attendance_id'],$userId,$businessId]);
            logActivity($pdo,$userId,$businessId,'clock_out','Clock out: '.($user['full_name']??'Employé'));
            $success='Clock out enregistré avec succès.';
        }
    } catch(Throwable $e) { $error=$e->getMessage(); }
}

/* Data */
$currentAttendance=null;
try { $stmt=$pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND clock_out_at IS NULL ORDER BY clock_in_at DESC LIMIT 1"); $stmt->execute([$userId,$businessId]); $currentAttendance=$stmt->fetch(PDO::FETCH_ASSOC); } catch(Throwable $e) {}

$attendanceHistory=[];
try { $stmt=$pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? ORDER BY clock_in_at DESC LIMIT 15"); $stmt->execute([$userId,$businessId]); $attendanceHistory=$stmt->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) {}

$todayCount=0; $totalMinutes=0;
try {
    $stmt=$pdo->prepare("SELECT * FROM employee_attendance WHERE user_id=? AND business_id=? AND DATE(clock_in_at)=CURDATE()");
    $stmt->execute([$userId,$businessId]);
    $todayRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $todayCount=count($todayRows);
    foreach($todayRows as $r) if(!empty($r['clock_out_at'])) $totalMinutes+=max(0,(strtotime($r['clock_out_at'])-strtotime($r['clock_in_at']))/60);
} catch(Throwable $e) {}

$initials='';
foreach(explode(' ',trim($user['full_name']??'E')) as $w) $initials.=strtoupper(substr($w,0,1));
$initials=substr($initials?:'E',0,2);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Clock In / Clock Out — LionTech</title>
<link rel="stylesheet" href="clock_attendance.css"/>
</head>
<body>
<div class="od-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

<main class="od-main">
  <header class="od-topbar">
    <div class="od-business-title">
      <h1>Clock In / Clock Out</h1>
      <p><?=e($business['business_name']??'Business')?> — Enregistrez votre présence</p>
    </div>
    <div class="od-top-actions">
      <button class="od-lang" id="langBtn">EN</button>
      <div class="od-avatar"><?=e($initials)?></div>
    </div>
  </header>

  <?php if($isExpired): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 24px;font-size:13px;color:#991B1B">⚠️ Votre abonnement est expiré. Les actions sont désactivées.</div>
  <?php endif; ?>
  <?php if($success): ?><div style="background:#F0FDF4;border:1px solid #86EFAC;padding:12px 24px;font-size:13px;color:#166534">✅ <?=e($success)?></div><?php endif; ?>
  <?php if($error):   ?><div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 24px;font-size:13px;color:#991B1B">⚠️ <?=e($error)?></div><?php endif; ?>

  <!-- Stat cards -->
  <div style="padding:20px 24px 0;display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
    <div class="od-card stat"><span class="stat-icon blue">📌</span><div><small>Statut actuel</small><strong><?=$currentAttendance?'In':'Out'?></strong></div></div>
    <div class="od-card stat"><span class="stat-icon green">🗓️</span><div><small>Sessions aujourd'hui</small><strong><?=(int)$todayCount?></strong></div></div>
    <div class="od-card stat"><span class="stat-icon amber">⏳</span><div><small>Heures complétées</small><strong><?=floor($totalMinutes/60)?>h<?=str_pad((string)($totalMinutes%60),2,'0',STR_PAD_LEFT)?>m</strong></div></div>
    <div class="od-card stat"><span class="stat-icon purple">📍</span><div><small>Rayon GPS</small><strong><?=$gpsRadius?>m</strong></div></div>
  </div>

  <div style="padding:20px 24px 0;display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

    <!-- Clock action panel -->
    <div class="od-card" style="padding:28px">
      <h2 style="font-size:17px;font-weight:700;color:#0B1F3A;margin-bottom:6px">Action de présence</h2>
      <p style="font-size:13px;color:#6B7280;margin-bottom:18px">Autorisez la localisation avant de cliquer.</p>

      <div id="gpsStatus" style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:10px;padding:10px 14px;font-size:13px;color:#6B7280;margin-bottom:16px">
        📍 GPS en attente...
      </div>

      <?php if($currentAttendance): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:14px 16px;margin-bottom:16px;text-align:center">
        <div style="font-size:12px;color:#6B7280">Clock in depuis</div>
        <div style="font-size:26px;font-weight:900;color:#166534"><?=e(date('H:i',strtotime($currentAttendance['clock_in_at'])))?></div>
        <div style="font-size:12px;color:#6B7280"><?=e(date('d/m/Y',strtotime($currentAttendance['clock_in_at'])))?></div>
      </div>
      <form method="POST" class="clock-form">
        <input type="hidden" name="action" value="clock_out">
        <input type="hidden" name="latitude" class="gps-lat">
        <input type="hidden" name="longitude" class="gps-lng">
        <input type="hidden" name="accuracy" class="gps-accuracy">
        <button type="submit" <?=$isExpired?'disabled':''?>
          style="width:100%;padding:14px;background:#DC2626;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">
          🔴 Clock Out
        </button>
      </form>
      <?php else: ?>
      <form method="POST" class="clock-form">
        <input type="hidden" name="action" value="clock_in">
        <input type="hidden" name="latitude" class="gps-lat">
        <input type="hidden" name="longitude" class="gps-lng">
        <input type="hidden" name="accuracy" class="gps-accuracy">
        <button type="submit" <?=$isExpired?'disabled':''?>
          style="width:100%;padding:14px;background:#16A34A;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit">
          🟢 Clock In
        </button>
      </form>
      <?php endif; ?>

      <p style="font-size:11.5px;color:#94A3B8;margin-top:12px;text-align:center">Les heures enregistrées ne peuvent pas être modifiées directement.</p>
    </div>

    <!-- How it works -->
    <div class="od-card" style="padding:24px">
      <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin-bottom:14px">Comment ça marche</h2>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach(['L\'employé se connecte sur son téléphone.','Il autorise la localisation GPS.','Il clique Clock In en arrivant et Clock Out en partant.','Le patron voit la présence et les heures travaillées.'] as $i=>$step): ?>
        <div style="display:flex;gap:12px;align-items:flex-start">
          <div style="width:24px;height:24px;border-radius:50%;background:#0B1F3A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?=$i+1?></div>
          <p style="font-size:13px;color:#6B7280;margin:3px 0 0"><?=$step?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- History -->
  <div style="padding:20px 24px 40px">
    <div class="od-card" style="padding:0;overflow:hidden">
      <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9">
        <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Historique de présence</h2>
      </div>
      <div class="od-table-wrap">
        <table class="od-table">
          <thead>
            <tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Durée</th><th>GPS</th><th>Statut</th></tr>
          </thead>
          <tbody>
          <?php if(!$attendanceHistory): ?>
          <tr><td colspan="6" class="od-empty">Aucun historique pour le moment.</td></tr>
          <?php else: foreach($attendanceHistory as $row): ?>
          <tr>
            <td><?=e(date('d/m/Y',strtotime($row['clock_in_at'])))?></td>
            <td><?=e(date('H:i',strtotime($row['clock_in_at'])))?></td>
            <td><?=!empty($row['clock_out_at'])?e(date('H:i',strtotime($row['clock_out_at']))):'—'?></td>
            <td><?=e(durationText($row['clock_in_at'],$row['clock_out_at']??null))?></td>
            <td><span class="od-badge <?=$row['gps_status']==='on_site'?'success':'danger'?>"><?=e($row['gps_status']??'—')?></span></td>
            <td><?=e($row['status']??'—')?></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>
</div>
<script>
window.LT_ATTENDANCE_SETTINGS = {
    businessLat: <?=json_encode($businessLat)?>,
    businessLng: <?=json_encode($businessLng)?>,
    gpsRadius: <?=json_encode($gpsRadius)?>,
    reviewBuffer: <?=json_encode($reviewBuffer)?>
};
</script>
<script src="clock_attendance.js"></script>
</body>
</html>