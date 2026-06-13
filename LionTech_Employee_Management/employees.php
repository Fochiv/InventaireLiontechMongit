<?php
/* ============================================================
   employees.php — Tally Business Manager
   Employee Management — Add employees
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Employee_Management\employees.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);
$isOwner    = ($user['role'] ?? '') === ROLE_BUSINESS_OWNER;

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function clean_username_part(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value));
    return $value ?: 'user';
}
function generate_employee_login(PDO $pdo, string $first, string $last): string {
    $base = clean_username_part($first) . clean_username_part($last);
    for ($i = 0; $i < 20; $i++) {
        $login = $base . random_int(100, 999);
        $stmt  = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
        $stmt->execute([$login]);
        if ((int)$stmt->fetchColumn() === 0) return $login;
    }
    return $base . random_int(1000, 9999);
}
function generate_pin(): string { return (string)random_int(100000, 999999); }

/* ── Business + feature state ── */
$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();
if (!$business) { http_response_code(404); exit('Business not found.'); }

$featureEnabled = true;
try {
    $stmt = $pdo->prepare('SELECT employee_management, employee_attendance FROM business_features WHERE business_id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $feature = $stmt->fetch();
    if ($feature && (int)$feature['employee_management'] !== 1 && (int)$feature['employee_attendance'] !== 1)
        $featureEnabled = false;
} catch (Throwable $ex) { $featureEnabled = true; }

$subscriptionExpired = in_array(($business['subscription_status'] ?? ''), ['expired','suspended'], true);
$readOnly = $subscriptionExpired;

/* ══════════════════════════════════════════════
   AJAX: GET employee schedule
   Must be BEFORE any POST handling
══════════════════════════════════════════════ */
if (isset($_GET['get_schedule']) && is_numeric($_GET['get_schedule'])) {
    $empId = (int)$_GET['get_schedule'];
    try {
        $stmt = $pdo->prepare("
            SELECT ws.*, u.full_name
            FROM work_schedules ws
            JOIN users u ON u.user_id = ws.user_id
            WHERE ws.user_id = ? AND ws.business_id = ?
            LIMIT 1
        ");
        $stmt->execute([$empId, $businessId]);
        $sched = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($sched ?: ['empty' => true]);
    } catch (Throwable $ex) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

$success        = '';
$error          = '';
$newCredentials = null;

/* ── POST actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $featureEnabled && !$readOnly) {
    $action = $_POST['action'] ?? '';

    try {
        /* ── Add employee ── */
        if ($action === 'add_employee') {
            $firstName    = trim($_POST['first_name']      ?? '');
            $lastName     = trim($_POST['last_name']       ?? '');
            $dob          = trim($_POST['date_of_birth']   ?? '') ?: null;
            $gender       = trim($_POST['gender']          ?? '') ?: null;
            $idCard       = trim($_POST['id_card']         ?? '') ?: null;
            $address      = trim($_POST['address']         ?? '') ?: null;
            $phone        = trim($_POST['phone']           ?? '');
            $emergPhone   = trim($_POST['emergency_phone'] ?? '') ?: null;
            $employeeRole = trim($_POST['employee_role']   ?? 'employee');
            $customRole   = trim($_POST['custom_role']     ?? '');
            $jobTitle     = trim($_POST['job_title']       ?? '') ?: null;
            $payType      = trim($_POST['pay_type']        ?? '') ?: null;
            $payAmount    = (isset($_POST['pay_amount']) && $_POST['pay_amount'] !== '') ? (float)$_POST['pay_amount'] : null;

            if ($employeeRole === 'other' && $customRole !== '') $jobTitle = $customRole;
            if ($firstName === '' || $lastName === '' || $phone === '')
                throw new Exception('First name, last name and phone are required.');

            $allowedRoles = ['employee','cashier','stock_manager','team_lead','manager','other'];
            if (!in_array($employeeRole, $allowedRoles, true)) $employeeRole = 'employee';

            $systemRole = in_array($employeeRole, ['manager','team_lead']) ? ROLE_MANAGER : ROLE_EMPLOYEE;
            $loginId    = generate_employee_login($pdo, $firstName, $lastName);
            $tempPin    = generate_pin();
            $passHash   = password_hash($tempPin, PASSWORD_BCRYPT);
            $fullName   = trim($firstName . ' ' . $lastName);

            /* Profile photo */
            $uploadDir = __DIR__ . '/uploads/profiles';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
            $photoUrl = null;

            $base64Data = trim($_POST['photo_base64'] ?? '');
            if ($base64Data && str_starts_with($base64Data, 'data:image/')) {
                $parts = explode(',', $base64Data, 2);
                if (count($parts) === 2) {
                    $imageData = base64_decode($parts[1]);
                    if ($imageData) {
                        $fn = time() . '_' . random_int(1000,9999) . '.jpg';
                        if (file_put_contents($uploadDir . '/' . $fn, $imageData))
                            $photoUrl = 'uploads/profiles/' . $fn;
                    }
                }
            }
            if (!$photoUrl && !empty($_FILES['profile_photo']['name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime    = mime_content_type($_FILES['profile_photo']['tmp_name']);
                if (isset($allowed[$mime]) && $_FILES['profile_photo']['size'] <= 5 * 1024 * 1024) {
                    $fn = time() . '_' . random_int(1000,9999) . '.' . $allowed[$mime];
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . '/' . $fn))
                        $photoUrl = 'uploads/profiles/' . $fn;
                }
            }

            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (business_id, full_name, login_id, phone, password_hash, role, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$businessId, $fullName, $loginId, $phone, $passHash, $systemRole, 'active']);
            $employeeUserId = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO employee_profiles (user_id, business_id, first_name, last_name, employee_role, job_title, profile_photo_url, pin_must_change, created_by) VALUES (?,?,?,?,?,?,?,1,?)')
                ->execute([$employeeUserId, $businessId, $firstName, $lastName, $employeeRole, $jobTitle, $photoUrl, $user['user_id']]);

            $pdo->prepare('INSERT INTO activity_logs (user_id, business_id, action, description, icon, ip_address) VALUES (?,?,?,?,?,?)')
                ->execute([$user['user_id'], $businessId, 'employee_created', 'New employee: ' . $fullName, 'user-plus', $_SERVER['REMOTE_ADDR'] ?? null]);

            $pdo->commit();
            $success        = 'Employee created successfully.';
            $newCredentials = ['name'=>$fullName,'login_id'=>$loginId,'pin'=>$tempPin,'photo'=>$photoUrl];
        }

        /* ── Toggle active / inactive ── */
        if ($action === 'toggle_status') {
            $empId     = (int)($_POST['employee_id'] ?? 0);
            $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
            $pdo->prepare('UPDATE users SET status=? WHERE user_id=? AND business_id=? AND role IN (?,?)')
                ->execute([$newStatus, $empId, $businessId, ROLE_EMPLOYEE, ROLE_MANAGER]);
            $success = 'Status updated.';
        }

        /* ── Put on leave (owner only) ── */
        if ($action === 'put_on_leave' && $isOwner) {
            $empId = (int)($_POST['employee_id'] ?? 0);
            $pdo->prepare('UPDATE users SET status=? WHERE user_id=? AND business_id=? AND role IN (?,?)')
                ->execute(['suspended', $empId, $businessId, ROLE_EMPLOYEE, ROLE_MANAGER]);
            $success = 'Employee placed on temporary leave.';
        }

        /* ── Reset PIN ── */
        if ($action === 'reset_pin') {
            $empId   = (int)($_POST['employee_id'] ?? 0);
            $tempPin = generate_pin();
            $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=? AND business_id=? AND role IN (?,?)')
                ->execute([password_hash($tempPin, PASSWORD_BCRYPT), $empId, $businessId, ROLE_EMPLOYEE, ROLE_MANAGER]);
            $pdo->prepare('UPDATE employee_profiles SET pin_must_change=1 WHERE user_id=? AND business_id=?')
                ->execute([$empId, $businessId]);
            $newCredentials = ['name'=>'Employee','login_id'=>'Same login ID','pin'=>$tempPin,'photo'=>null];
            $success = 'New temporary PIN generated.';
        }

    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $ex->getMessage();
    }
}

/* ── Employees list ── */
$employees = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.login_id, u.phone, u.role, u.status, u.created_at,
               ep.first_name, ep.last_name, ep.employee_role, ep.job_title,
               ep.profile_photo_url, ep.pin_must_change,
               ea.clock_in_at
        FROM users u
        LEFT JOIN employee_profiles ep ON ep.user_id = u.user_id
        LEFT JOIN employee_attendance ea
               ON ea.user_id = u.user_id
              AND ea.business_id = u.business_id
              AND ea.clock_out_at IS NULL
        WHERE u.business_id = ? AND u.role IN ('employee','manager')
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$businessId]);
    $employees = $stmt->fetchAll();
} catch (Throwable $ex) { $employees = []; }

/* ── Today attendance ── */
$todayAttendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT ea.*, u.full_name
        FROM employee_attendance ea
        JOIN users u ON u.user_id = ea.user_id
        WHERE ea.business_id = ? AND DATE(ea.clock_in_at) = CURDATE()
        ORDER BY ea.clock_in_at DESC
    ");
    $stmt->execute([$businessId]);
    $todayAttendance = $stmt->fetchAll();
} catch (Throwable $ex) { $todayAttendance = []; }

$totalEmployees    = count($employees);
$activeEmployees   = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'active'));
$clockedInToday    = count(array_filter($todayAttendance, fn($r) => empty($r['clock_out_at'])));
$inactiveEmployees = $totalEmployees - $activeEmployees;

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Owner') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Employees — Tally</title>
<link rel="stylesheet" href="employees.css"/>
<style>
/* ══ MODAL ══ */
.em-modal{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);overflow-y:auto;padding:20px}
.em-modal.show{display:flex;align-items:flex-start;justify-content:center}
.em-modal-card{background:#fff;border-radius:16px;width:100%;max-width:740px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.em-modal-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #F1F5F9}
.em-modal-header h2{font-size:17px;color:#0B1F3A;font-weight:800;margin:0}
.em-modal-header button{background:none;border:none;font-size:22px;cursor:pointer;color:#94A3B8;line-height:1;padding:0}
.em-form{padding:24px}
.em-section-title{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#94A3B8;font-weight:700;margin:22px 0 12px;padding-bottom:6px;border-bottom:1px solid #F1F5F9}
.em-section-title:first-child{margin-top:0}
.em-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.em-form-grid .full{grid-column:1/-1}
.em-field label{display:block;font-size:12px;color:#64748B;font-weight:600;margin-bottom:5px}
.em-field input,.em-field select,.em-field textarea{width:100%;padding:10px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13.5px;outline:none;font-family:inherit;box-sizing:border-box}
.em-field input:focus,.em-field select:focus,.em-field textarea:focus{border-color:#1A9E7A;box-shadow:0 0 0 3px rgba(26,158,122,.08)}
.em-field textarea{resize:vertical;min-height:70px}
.em-field small{display:block;font-size:11px;color:#94A3B8;margin-top:4px}
.photo-upload-area{border:2px dashed #E2E8F0;border-radius:12px;padding:22px;text-align:center;cursor:pointer;transition:.2s;position:relative;overflow:hidden}
.photo-upload-area:hover{border-color:#1A9E7A;background:#F0FDF9}
.photo-preview{width:90px;height:90px;border-radius:50%;object-fit:cover;display:none;margin:0 auto 10px;border:3px solid #1A9E7A}
.photo-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;z-index:1;width:100%;height:100%}
.camera-btn{background:#0B1F3A;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer;margin-top:10px;position:relative;z-index:2}
.camera-btn:hover{background:#1A9E7A}
.em-permissions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px}
.em-perm{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#F8FAFC;border-radius:8px;font-size:13px;border:1px solid #F1F5F9;cursor:pointer;transition:.15s}
.em-perm:hover{border-color:#1A9E7A;background:#F0FDF9}
.em-perm input{width:16px;height:16px;cursor:pointer;flex-shrink:0;accent-color:#1A9E7A}
.pay-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.other-role-box{display:none;margin-top:8px}
.other-role-box input{width:100%;padding:10px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13.5px;outline:none;box-sizing:border-box}
.other-role-box input:focus{border-color:#1A9E7A}
.em-preview-bar{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px 16px;margin-top:16px;font-size:13px;color:#64748B}
.em-form-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:22px;padding-top:16px;border-top:1px solid #F1F5F9}
.camera-overlay{display:none;position:fixed;inset:0;z-index:1100;background:rgba(0,0,0,.85);align-items:center;justify-content:center;flex-direction:column;gap:16px}
.camera-overlay.show{display:flex}
.camera-overlay video{border-radius:12px;max-width:90vw;max-height:60vh;background:#000}
.camera-overlay canvas{display:none}
.cam-actions{display:flex;gap:12px}
.cam-btn{padding:12px 24px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;border:none}
.cam-snap{background:#1A9E7A;color:#fff}
.cam-cancel{background:#fff;color:#0B1F3A}

/* ══ ACTION BUTTONS ══ */
.em-actions{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.em-btn{border:none;border-radius:7px;padding:6px 10px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.em-btn.view{background:#EFF6FF;color:#1E40AF}
.em-btn.schedule{background:#F5F3FF;color:#6B21A8}
.em-btn.pin{background:#F0FDF4;color:#166534}
.em-btn.leave{background:#FEF3C7;color:#92400E}
.em-btn.deactivate{background:#FEE2E2;color:#991B1B}
.em-btn.activate{background:#DCFCE7;color:#166534}
.em-btn:hover{opacity:.85}

/* Schedule modal */
.sched-day-pills{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.sched-pill{padding:5px 12px;border-radius:50px;font-size:12px;font-weight:700}
.sched-pill.on{background:#0B1F3A;color:#fff}
.sched-pill.off{background:#F1F5F9;color:#94A3B8}

@media(max-width:600px){
  .em-form-grid{grid-template-columns:1fr}
  .em-permissions{grid-template-columns:1fr}
  .pay-row{grid-template-columns:1fr}
  .em-modal-card{border-radius:12px}
}
</style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="em-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

<div class="em-overlay" id="em-overlay"></div>

<main class="em-main">
  <header class="em-topbar">
    <button class="em-hamburger" id="em-hamburger">☰</button>
    <div>
      <h1 data-i18n="page_title">Employee Management</h1>
      <p><?= e($business['business_name'] ?? 'Business') ?> • <span data-i18n="page_subtitle">Manage employees, roles, attendance, and access.</span></p>
    </div>
    <div class="em-topbar-actions">
      <button class="em-lang" id="em-lang">FR</button>
      <?php if ($featureEnabled && !$readOnly): ?>
      <button class="em-primary" id="openAddModal">+ <span data-i18n="add_employee">Add Employee</span></button>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$featureEnabled): ?>
  <section class="em-lock-card">
    <div class="em-lock-icon"><span class="icon-lock">🔒</span></div>
    <h2 data-i18n="feature_locked_title">Employee feature not active</h2>
    <p data-i18n="feature_locked_text">This business does not have employee management enabled. Contact LionTech.</p>
  </section>
  <?php else: ?>

  <?php if ($subscriptionExpired): ?>
  <div class="em-alert warning"><span class="icon-warn">⚠</span> <span data-i18n="expired_warning">Subscription expired. Actions disabled.</span></div>
  <?php endif; ?>
  <?php if ($success): ?><div class="em-alert success"><span class="icon-ok">✓</span> <?= e($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="em-alert error"><span class="icon-warn">⚠</span> <?= e($error) ?></div><?php endif; ?>

  <?php if ($newCredentials): ?>
  <div class="em-credentials">
    <?php if (!empty($newCredentials['photo'])): ?>
    <img src="<?= e($newCredentials['photo']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #10B981;flex-shrink:0" alt="">
    <?php endif; ?>
    <strong data-i18n="new_credentials">New credentials:</strong>
    <span><?= e($newCredentials['name']) ?></span>
    <span>Login: <b style="font-family:monospace"><?= e($newCredentials['login_id']) ?></b></span>
    <span>PIN: <b style="font-size:20px;letter-spacing:4px;font-family:monospace"><?= e($newCredentials['pin']) ?></b></span>
    <small data-i18n="pin_notice">Show this PIN to the employee once. They must change it after first login.</small>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <section class="em-cards">
    <div class="em-card"><div class="em-card-icon"><span class="icon-users">◎</span></div><div><span data-i18n="total_employees">Total employees</span><strong><?= $totalEmployees ?></strong></div></div>
    <div class="em-card"><div class="em-card-icon green"><span class="icon-ok">✓</span></div><div><span data-i18n="active_employees">Active</span><strong><?= $activeEmployees ?></strong></div></div>
    <div class="em-card"><div class="em-card-icon blue"><span class="dot-green">●</span></div><div><span data-i18n="clocked_in">Clocked in today</span><strong><?= $clockedInToday ?></strong></div></div>
    <div class="em-card"><div class="em-card-icon red">⏸️</div><div><span data-i18n="inactive_employees">Inactive</span><strong><?= $inactiveEmployees ?></strong></div></div>
  </section>

  <!-- Toolbar -->
  <section class="em-toolbar">
    <div class="em-search"><span><span class="icon-search">⌕</span></span><input type="search" id="employeeSearch" placeholder="Search employees..." data-i18n-placeholder="search_placeholder"/></div>
    <select id="roleFilter">
      <option value="" data-i18n="all_roles">All roles</option>
      <option value="employee">Employee</option>
      <option value="cashier">Cashier</option>
      <option value="stock_manager">Stock Manager</option>
      <option value="team_lead">Team Lead</option>
      <option value="manager">Manager</option>
    </select>
    <select id="statusFilter">
      <option value="" data-i18n="all_statuses">All statuses</option>
      <option value="active" data-i18n="active">Active</option>
      <option value="inactive" data-i18n="inactive">Inactive</option>
      <option value="suspended" data-i18n="on_leave">On Leave</option>
    </select>
  </section>

  <!-- Employees table -->
  <section class="em-panel">
    <div class="em-panel-header"><h2 data-i18n="employees_list">Employees List</h2></div>
    <div class="em-table-wrap">
      <table class="em-table" id="employeesTable">
        <thead><tr>
          <th data-i18n="col_employee">Employee</th>
          <th>Login ID</th>
          <th data-i18n="col_phone">Phone</th>
          <th data-i18n="col_role">Role</th>
          <th data-i18n="col_clock">Clock</th>
          <th data-i18n="col_status">Status</th>
          <th data-i18n="col_actions">Actions</th>
        </tr></thead>
        <tbody>
        <?php if (empty($employees)): ?>
        <tr><td colspan="7" class="em-empty" data-i18n="no_employees">No employees yet. Add your first employee.</td></tr>
        <?php else: foreach ($employees as $emp):
          $photo     = $emp['profile_photo_url'] ?: '';
          $empRole   = $emp['employee_role'] ?: $emp['role'];
          $status    = $emp['status'];
          $clockedIn = !empty($emp['clock_in_at']);
        ?>
        <tr data-role="<?= e($empRole) ?>" data-status="<?= e($status) ?>" data-name="<?= e(strtolower($emp['full_name'])) ?>">
          <td>
            <div class="em-person">
              <?php if ($photo): ?>
              <img src="<?= e($photo) ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #E2E8F0"/>
              <?php else: ?>
              <div class="em-photo-placeholder"><?= e(strtoupper(substr($emp['full_name'],0,1))) ?></div>
              <?php endif; ?>
              <div>
                <strong><?= e($emp['full_name']) ?></strong>
                <small><?= e($emp['job_title'] ?: '—') ?></small>
                <?php if ($emp['pin_must_change']): ?>
                <br><small style="color:#F97316;font-weight:700;font-size:11px"><span class="icon-warn">⚠</span> PIN change required</small>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><code><?= e($emp['login_id']) ?></code></td>
          <td><?= e($emp['phone'] ?: '—') ?></td>
          <td><span class="em-chip"><?= e(ucwords(str_replace('_',' ', $empRole))) ?></span></td>
          <td>
            <?php if ($clockedIn): ?>
            <span style="background:#DCFCE7;color:#166534;padding:3px 8px;border-radius:50px;font-size:11px;font-weight:700">
              <span class="dot-green">●</span> <span data-i18n="at_work">At work</span>
            </span>
            <?php else: ?>
            <span style="background:#F1F5F9;color:#64748B;padding:3px 8px;border-radius:50px;font-size:11px;font-weight:700">
              <span class="dot-gray">○</span> <span data-i18n="off_work">Off</span>
            </span>
            <?php endif; ?>
          </td>
          <td>
            <span class="em-status <?= e($status) ?>">
              <?= $status === 'suspended' ? '🌙 On Leave' : e(ucfirst($status)) ?>
            </span>
          </td>
          <td>
            <div class="em-actions">
  <?php if (!$readOnly): ?>

  <!-- View -->
  <a class="em-btn view"
     href="<?= APP_URL ?>/LionTech_Employee_Management/employee_overview.php">
    👁 <span data-i18n="btn_view">View</span>
  </a>

  <!-- Schedule -->
  <button class="em-btn schedule" type="button"
          onclick="viewSchedule(<?= (int)$emp['user_id'] ?>,'<?= addslashes($emp['full_name']) ?>')">
    <span class="icon-cal">▦</span> <span data-i18n="btn_schedule">Schedule</span>
  </button>

  <!-- Reset PIN -->
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="reset_pin"/>
    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/>
    <button class="em-btn pin" type="submit">
      <span class="icon-key">⚿</span> <span data-i18n="btn_reset_pin">Reset PIN</span>
    </button>
  </form>

  <?php if ($isOwner): ?>
  <?php if ($status === 'active'): ?>
  <!-- Leave -->
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="put_on_leave"/>
    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/>
    <button class="em-btn leave" type="submit">
      🌙 <span data-i18n="btn_leave">Leave</span>
    </button>
  </form>
  <!-- Deactivate -->
  <form method="POST" style="display:inline"
        onsubmit="return confirm('Deactivate this employee?')">
    <input type="hidden" name="action" value="toggle_status"/>
    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/>
    <input type="hidden" name="new_status" value="inactive"/>
    <button class="em-btn deactivate" type="submit">
      <span class="icon-no">⊘</span> <span data-i18n="btn_deactivate">Deactivate</span>
    </button>
  </form>
  <?php else: ?>
  <!-- Activate -->
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="toggle_status"/>
    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/>
    <input type="hidden" name="new_status" value="active"/>
    <button class="em-btn activate" type="submit">
      <span class="icon-ok">✓</span> <span data-i18n="btn_activate">Activate</span>
    </button>
  </form>
  <?php endif; ?>
  <?php endif; ?>

  <?php else: ?>
  <span class="em-muted" data-i18n="actions_disabled">Actions disabled</span>
  <?php endif; ?>
</div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Today attendance -->
  <section class="em-panel attendance-panel">
    <div class="em-panel-header">
      <h2 data-i18n="today_attendance">Today's Attendance</h2>
      <span class="em-muted" data-i18n="locked_times">Times are locked after submission.</span>
    </div>
    <div class="em-attendance-grid">
      <?php if (empty($todayAttendance)): ?>
      <div class="em-empty-card" data-i18n="no_attendance">No clock-in records for today.</div>
      <?php else: foreach ($todayAttendance as $row): ?>
      <div class="em-attendance-card">
        <strong><?= e($row['full_name']) ?></strong>
        <span>In: <?= e(date('H:i', strtotime($row['clock_in_at']))) ?></span>
        <span>Out: <?= !empty($row['clock_out_at']) ? e(date('H:i', strtotime($row['clock_out_at']))) : '—' ?></span>
        <small><span class="icon-lock">🔒</span> Locked</small>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <?php endif; ?>
</main>
</div>

<!-- ══ ADD EMPLOYEE MODAL ══ -->
<div class="em-modal" id="addEmployeeModal" aria-hidden="true">
  <div class="em-modal-card">
    <div class="em-modal-header">
      <h2 data-i18n="add_employee_title">Add Employee</h2>
      <button id="closeAddModal" type="button">✗</button>
    </div>
    <form method="POST" class="em-form" enctype="multipart/form-data" id="addEmployeeForm">
      <input type="hidden" name="action" value="add_employee"/>
      <input type="hidden" name="photo_base64" id="photoBase64"/>

      <!-- PHOTO -->
      <div class="em-section-title" data-i18n="section_photo">📷 Profile Photo</div>
      <div class="photo-upload-area" id="photoArea">
        <img id="photoPreview" class="photo-preview" src="" alt=""/>
        <div id="photoPlaceholder">
          <div style="font-size:40px"><span class="icon-user">◉</span></div>
          <p style="font-size:13px;color:#94A3B8;margin:6px 0 0" data-i18n="photo_hint">Click to upload a photo</p>
        </div>
        <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp"
               style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:1;width:100%;height:100%">
      </div>
      <div style="text-align:center;margin-top:10px">
        <button type="button" class="camera-btn" id="openCamera" style="position:relative;z-index:2">
          📸 <span data-i18n="use_camera">Take a photo</span>
        </button>
      </div>

      <!-- IDENTITY -->
      <div class="em-section-title" data-i18n="section_identity"><span class="icon-user">◉</span> Identity</div>
      <div class="em-form-grid">
        <div class="em-field">
          <label data-i18n="first_name">First Name <span style="color:#DC2626">*</span></label>
          <input name="first_name" id="firstName" required placeholder="Martha"/>
        </div>
        <div class="em-field">
          <label data-i18n="last_name">Last Name <span style="color:#DC2626">*</span></label>
          <input name="last_name" id="lastName" required placeholder="Njoya"/>
        </div>
        <div class="em-field">
          <label data-i18n="date_of_birth">Date of Birth</label>
          <input type="date" name="date_of_birth"/>
        </div>
        <div class="em-field">
          <label data-i18n="gender">Gender</label>
          <select name="gender">
            <option value="">— Select —</option>
            <option value="male"   data-i18n="male">Male</option>
            <option value="female" data-i18n="female">Female</option>
            <option value="other"  data-i18n="other_gender">Other</option>
          </select>
        </div>
        <div class="em-field full">
          <label data-i18n="id_card">National ID / ID Card Number</label>
          <input name="id_card" placeholder="Ex: 12345678A"/>
          <small data-i18n="id_card_hint">Optional — internal verification only</small>
        </div>
      </div>

      <!-- CONTACT -->
      <div class="em-section-title" data-i18n="section_contact"><span class="icon-phone">☎</span> Contact & Address</div>
      <div class="em-form-grid">
        <div class="em-field">
          <label data-i18n="phone_required">Phone <span style="color:#DC2626">*</span></label>
          <input name="phone" required placeholder="+237 6XX XXX XXX"/>
        </div>
        <div class="em-field">
          <label data-i18n="emergency_phone">Emergency Phone</label>
          <input name="emergency_phone" placeholder="+237 6XX XXX XXX"/>
        </div>
        <div class="em-field full">
          <label data-i18n="address">Home Address</label>
          <textarea name="address" placeholder="Neighborhood, city, street..."></textarea>
        </div>
      </div>

      <!-- ROLE -->
      <div class="em-section-title" data-i18n="section_role">💼 Role & Position</div>
      <div class="em-form-grid">
        <div class="em-field">
          <label data-i18n="employee_role">Role <span style="color:#DC2626">*</span></label>
          <select name="employee_role" id="employeeRoleSelect" required>
            <option value="employee"      data-i18n="role_employee">Employee</option>
            <option value="cashier"       data-i18n="role_cashier">Cashier</option>
            <option value="stock_manager" data-i18n="role_stock">Stock Manager</option>
            <option value="team_lead"     data-i18n="role_team_lead">Team Lead</option>
            <option value="manager"       data-i18n="role_manager">Manager</option>
            <option value="other"         data-i18n="role_other">Other</option>
          </select>
          <div class="other-role-box" id="otherRoleBox">
            <input name="custom_role" placeholder="Specify role..." data-i18n-placeholder="custom_role_ph"/>
          </div>
        </div>
        <div class="em-field">
          <label data-i18n="job_title">Job Title</label>
          <input name="job_title" placeholder="Ex: Morning cashier..."/>
        </div>
      </div>

      <!-- PERMISSIONS -->
      <div class="em-section-title" data-i18n="section_permissions"><span class="icon-lock"><span class="icon-lock">🔒</span></span> Access & Permissions</div>
      <p style="font-size:13px;color:#64748B;margin-bottom:10px" data-i18n="permissions_hint">
        Choose what this employee can do in the system.
      </p>
      <div class="em-permissions">
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="view_products" checked><span data-i18n="perm_view_products">View products</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="stock_in"><span data-i18n="perm_stock_in">Add stock in</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="stock_out"><span data-i18n="perm_stock_out">Add stock out</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="view_reports"><span data-i18n="perm_view_reports">View reports</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="manage_employees"><span data-i18n="perm_manage_employees">Manage employees</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="approve_stock" checked><span data-i18n="perm_approve_stock">Approve stock movements</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="clock_inout" checked><span data-i18n="perm_clock">Clock in / Clock out</span></label>
        <label class="em-perm"><input type="checkbox" name="permissions[]" value="view_notifications"><span data-i18n="perm_notifications">View notifications</span></label>
      </div>

      <!-- PAY -->
      <div class="em-section-title" data-i18n="section_pay"><span class="icon-money">&#36;</span> Pay (optional)</div>
      <div class="pay-row">
        <div class="em-field">
          <label data-i18n="pay_type">Pay Type</label>
          <select name="pay_type" id="payTypeSelect">
            <option value="">— Not specified —</option>
            <option value="monthly" data-i18n="pay_monthly">Monthly salary</option>
            <option value="hourly"  data-i18n="pay_hourly">Hourly rate</option>
            <option value="daily"   data-i18n="pay_daily">Daily rate</option>
          </select>
        </div>
        <div class="em-field" id="payAmountField" style="display:none">
          <label data-i18n="pay_amount">Amount (XAF)</label>
          <input type="number" name="pay_amount" min="0" step="500" placeholder="Ex: 75000"/>
        </div>
      </div>

      <div class="em-preview-bar">
        <span data-i18n="username_preview">Username preview:</span>
        <strong id="usernamePreview" style="color:#0B1F3A;font-family:monospace">firstnamelastname###</strong>
        &nbsp;·&nbsp;
        <span data-i18n="auto_pin">A 6-digit temporary PIN will be generated automatically.</span>
      </div>

      <div class="em-form-actions">
        <button type="button" class="em-secondary" id="cancelAdd" data-i18n="cancel">Cancel</button>
        <button type="submit" class="em-primary" data-i18n="create_employee">Create Employee</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ CAMERA OVERLAY ══ -->
<div class="camera-overlay" id="cameraOverlay">
  <video id="cameraVideo" autoplay playsinline></video>
  <canvas id="cameraCanvas"></canvas>
  <div class="cam-actions">
    <button class="cam-btn cam-snap" id="snapPhoto" type="button" data-i18n="snap_photo">📸 Take photo</button>
    <button class="cam-btn cam-cancel" id="closeCamera" type="button" data-i18n="close_camera">✗ Close</button>
  </div>
</div>

<!-- ══ SCHEDULE MODAL ══ -->
<div class="em-modal" id="scheduleModal" aria-hidden="true">
  <div class="em-modal-card" style="max-width:500px">
    <div class="em-modal-header">
      <h2 id="scheduleModalTitle">Work Schedule</h2>
      <button type="button" onclick="closeScheduleModal()">✗</button>
    </div>
    <div style="padding:24px" id="scheduleModalBody">
      <p style="color:#94A3B8;text-align:center">Loading...</p>
    </div>
  </div>
</div>

<script src="employees.js"></script>
<script>
/* ── Schedule modal ── */
const scheduleModal = document.getElementById('scheduleModal');
const dayNamesLang  = {
  en: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
  fr: ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim']
};
const dayKeys = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

function viewSchedule(userId, name) {
  const lang = localStorage.getItem('lt_lang') || 'en';
  document.getElementById('scheduleModalTitle').textContent = (lang === 'fr' ? 'Horaire de ' : 'Schedule for ') + name;
  document.getElementById('scheduleModalBody').innerHTML = '<p style="color:#94A3B8;text-align:center;padding:20px">Loading...</p>';
  scheduleModal?.classList.add('show');

  fetch('?get_schedule=' + userId)
    .then(r => r.json())
    .then(data => {
      const body = document.getElementById('scheduleModalBody');
      if (!data || data.error) {
        body.innerHTML = '<p style="color:#991B1B;text-align:center;padding:20px">Error: ' + (data?.error || 'Unknown') + '</p>';
        return;
      }
      if (data.empty) {
        body.innerHTML = '<div style="text-align:center;padding:24px;color:#94A3B8">' +
          (lang==='fr'?'Aucun horaire défini par cet employé.':'No schedule set by this employee.') + '</div>';
        return;
      }
      const names = dayNamesLang[lang] || dayNamesLang.en;
      let html = '<div style="margin-bottom:16px"><p style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:10px">' +
        (lang==='fr'?'Jours de travail':'Working days') + '</p>';
      html += '<div class="sched-day-pills">';
      dayKeys.forEach((d,i) => {
        html += `<span class="sched-pill ${data[d]?'on':'off'}">${names[i]}</span>`;
      });
      html += '</div>';
      if (data.start_time && data.end_time) {
        html += `<p style="font-size:13.5px;margin-bottom:8px">🕐 <strong>${lang==='fr'?'Début':'Start'}:</strong> ${data.start_time.slice(0,5)} &nbsp; 🕔 <strong>${lang==='fr'?'Fin':'End'}:</strong> ${data.end_time.slice(0,5)}</p>`;
      }
      if (data.schedule_notes || data.notes) {
        html += `<p style="font-size:13px;color:#6B7280">📝 ${data.schedule_notes || data.notes}</p>`;
      }
      html += '</div>';
      body.innerHTML = html;
    })
    .catch(err => {
      document.getElementById('scheduleModalBody').innerHTML =
        '<p style="color:#991B1B;text-align:center;padding:20px">Network error. Please try again.</p>';
    });
}

function closeScheduleModal() {
  scheduleModal?.classList.remove('show');
}
scheduleModal?.addEventListener('click', e => { if (e.target === scheduleModal) closeScheduleModal(); });
</script>
</body>
</html>