<?php
/* ============================================================
   employees.php — LionTech Business Manager
   Employee Management page
   Access: business_owner / manager only
   Feature gate: employee_management must be enabled for the business
   ============================================================ */
require_once __DIR__ . '/../Config.php';
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$user = currentUser();
$pdo  = getDB();
$businessId = (int)($user['business_id'] ?? 0);

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/login.php?error=unauthorized');
    exit;
}

/* Helpers */
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
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
        $stmt->execute([$login]);
        if ((int)$stmt->fetchColumn() === 0) return $login;
    }
    return $base . random_int(1000, 9999);
}
function generate_pin(): string { return (string)random_int(100000, 999999); }

/* Business + feature state */
$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    http_response_code(404);
    exit('Business not found.');
}

$featureEnabled = true; // fallback for older DBs before business_features exists
try {
    $stmt = $pdo->prepare('SELECT employee_management, employee_attendance FROM business_features WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$feature = $stmt->fetch();
// Unlock if EITHER employee_management OR employee_attendance is enabled
if ($feature && (int)$feature['employee_management'] !== 1 && (int)$feature['employee_attendance'] !== 1) {
    $featureEnabled = false;
}

} catch (Throwable $ex) {
    // If table does not exist yet, keep page available during development.
    $featureEnabled = true;
}

$subscriptionExpired = in_array(($business['subscription_status'] ?? ''), ['expired','suspended'], true);
$readOnly = $subscriptionExpired;

$success = '';
$error = '';
$newCredentials = null;

/* POST actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $featureEnabled && !$readOnly) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_employee') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $employeeRole = trim($_POST['employee_role'] ?? 'employee');
            $jobTitle  = trim($_POST['job_title'] ?? '');
            $status    = 'active';

            if ($firstName === '' || $lastName === '' || $phone === '') {
                throw new Exception('Please fill first name, last name, and phone number.');
            }

            $allowedEmployeeRoles = ['employee','cashier','stock_manager','manager','other'];
            if (!in_array($employeeRole, $allowedEmployeeRoles, true)) $employeeRole = 'employee';

            $systemRole = ($employeeRole === 'manager') ? ROLE_MANAGER : ROLE_EMPLOYEE;
            $loginId = generate_employee_login($pdo, $firstName, $lastName);
            $tempPin = generate_pin();
            $passwordHash = password_hash($tempPin, PASSWORD_BCRYPT);
            $fullName = trim($firstName . ' ' . $lastName);

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO users (business_id, full_name, login_id, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$businessId, $fullName, $loginId, $phone, $passwordHash, $systemRole, $status]);
            $employeeUserId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO employee_profiles (user_id, business_id, first_name, last_name, employee_role, job_title, profile_photo_url, pin_must_change, created_by) VALUES (?, ?, ?, ?, ?, ?, NULL, 1, ?)');
            $stmt->execute([$employeeUserId, $businessId, $firstName, $lastName, $employeeRole, $jobTitle, $user['user_id']]);

            $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, business_id, action, description, icon, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['user_id'], $businessId, 'employee_created', 'New employee account created: ' . $fullName, 'user-plus', $_SERVER['REMOTE_ADDR'] ?? null]);

            $pdo->commit();
            $success = 'Employee created successfully.';
            $newCredentials = ['name' => $fullName, 'login_id' => $loginId, 'pin' => $tempPin];
        }

        if ($action === 'toggle_status') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ? AND business_id = ? AND role IN (?, ?)');
            $stmt->execute([$newStatus, $employeeId, $businessId, ROLE_EMPLOYEE, ROLE_MANAGER]);
            $success = 'Employee status updated.';
        }

        if ($action === 'reset_pin') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $tempPin = generate_pin();
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ? AND business_id = ? AND role IN (?, ?)');
            $stmt->execute([password_hash($tempPin, PASSWORD_BCRYPT), $employeeId, $businessId, ROLE_EMPLOYEE, ROLE_MANAGER]);
            $stmt = $pdo->prepare('UPDATE employee_profiles SET pin_must_change = 1 WHERE user_id = ? AND business_id = ?');
            $stmt->execute([$employeeId, $businessId]);
            $newCredentials = ['name' => 'Employee', 'login_id' => 'Same login ID', 'pin' => $tempPin];
            $success = 'Temporary PIN generated.';
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $ex->getMessage();
    }
}

/* Employees list */
$employees = [];
try {
    $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, u.login_id, u.phone, u.role, u.status, u.created_at,
                                  ep.first_name, ep.last_name, ep.employee_role, ep.job_title, ep.profile_photo_url, ep.pin_must_change
                           FROM users u
                           LEFT JOIN employee_profiles ep ON ep.user_id = u.user_id
                           WHERE u.business_id = ? AND u.role IN ('employee','manager')
                           ORDER BY u.created_at DESC");
    $stmt->execute([$businessId]);
    $employees = $stmt->fetchAll();
} catch (Throwable $ex) { $employees = []; }

/* Today's attendance */
$todayAttendance = [];
try {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name
                           FROM attendance a
                           JOIN users u ON u.user_id = a.user_id
                           WHERE a.business_id = ? AND a.date = CURDATE()
                           ORDER BY a.clock_in DESC");
    $stmt->execute([$businessId]);
    $todayAttendance = $stmt->fetchAll();
} catch (Throwable $ex) { $todayAttendance = []; }

$totalEmployees = count($employees);
$activeEmployees = count(array_filter($employees, fn($emp) => ($emp['status'] ?? '') === 'active'));
$clockedIn = count(array_filter($todayAttendance, fn($row) => !empty($row['clock_in']) && empty($row['clock_out'])));
$inactiveEmployees = $totalEmployees - $activeEmployees;

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Owner') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="robots" content="noindex,nofollow"/>
<title>Employee Management — LionTech</title>
<link rel="stylesheet" href="employees.css"/>
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
        <?php if($featureEnabled && !$readOnly): ?>
        <button class="em-primary" id="openAddModal">+ <span data-i18n="add_employee">Add Employee</span></button>
        <?php endif; ?>
      </div>
    </header>

    <?php if(!$featureEnabled): ?>
      <section class="em-lock-card">
        <div class="em-lock-icon">🔒</div>
        <h2 data-i18n="feature_locked_title">Employee feature is not active</h2>
        <p data-i18n="feature_locked_text">This business does not have employee management enabled. Please contact LionTech to activate this feature.</p>
      </section>
    <?php else: ?>

      <?php if($subscriptionExpired): ?>
        <div class="em-alert warning">⚠️ <span data-i18n="expired_warning">Subscription expired. You can view employees, but employee actions are disabled until renewal.</span></div>
      <?php endif; ?>
      <?php if($success): ?><div class="em-alert success">✅ <?= e($success) ?></div><?php endif; ?>
      <?php if($error): ?><div class="em-alert error">⚠️ <?= e($error) ?></div><?php endif; ?>
      <?php if($newCredentials): ?>
        <div class="em-credentials">
          <strong data-i18n="new_credentials">New temporary credentials:</strong>
          <span><?= e($newCredentials['name']) ?></span>
          <span>Login: <b><?= e($newCredentials['login_id']) ?></b></span>
          <span>PIN: <b><?= e($newCredentials['pin']) ?></b></span>
          <small data-i18n="pin_notice">Show this PIN to the employee once. They should change it after first login.</small>
        </div>
      <?php endif; ?>

      <section class="em-cards">
        <div class="em-card"><div class="em-card-icon">👥</div><div><span data-i18n="total_employees">Total Employees</span><strong><?= $totalEmployees ?></strong></div></div>
        <div class="em-card"><div class="em-card-icon green">✅</div><div><span data-i18n="active_employees">Active Employees</span><strong><?= $activeEmployees ?></strong></div></div>
        <div class="em-card"><div class="em-card-icon blue">🟢</div><div><span data-i18n="clocked_in">Clocked In Today</span><strong><?= $clockedIn ?></strong></div></div>
        <div class="em-card"><div class="em-card-icon red">⏸️</div><div><span data-i18n="inactive_employees">Inactive Employees</span><strong><?= $inactiveEmployees ?></strong></div></div>
      </section>

      <section class="em-security-note">
        <div>📍</div>
        <div>
          <strong data-i18n="gps_title">Phone clock-in protection</strong>
          <p data-i18n="gps_text">Employees may log in from anywhere, but clock in/out should use GPS verification. If GPS is slightly outside range, manager review is required. Clock times are locked and cannot be edited directly.</p>
        </div>
      </section>

      <section class="em-toolbar">
        <div class="em-search"><span>🔍</span><input type="search" id="employeeSearch" placeholder="Search employees..." data-i18n-placeholder="search_placeholder"/></div>
        <select id="roleFilter">
          <option value="" data-i18n="all_roles">All roles</option>
          <option value="employee">Employee</option>
          <option value="cashier">Cashier</option>
          <option value="stock_manager">Stock Manager</option>
          <option value="manager">Manager</option>
          <option value="other">Other</option>
        </select>
        <select id="statusFilter">
          <option value="" data-i18n="all_statuses">All statuses</option>
          <option value="active" data-i18n="active">Active</option>
          <option value="inactive" data-i18n="inactive">Inactive</option>
        </select>
      </section>

      <section class="em-panel">
        <div class="em-panel-header"><h2 data-i18n="employees_list">Employees List</h2></div>
        <div class="em-table-wrap">
          <table class="em-table" id="employeesTable">
            <thead><tr><th data-i18n="employee">Employee</th><th>Login ID</th><th data-i18n="phone">Phone</th><th data-i18n="role">Role</th><th data-i18n="status">Status</th><th data-i18n="actions">Actions</th></tr></thead>
            <tbody>
              <?php if(empty($employees)): ?>
                <tr><td colspan="6" class="em-empty" data-i18n="no_employees">No employees yet. Add your first employee.</td></tr>
              <?php else: foreach($employees as $emp):
                $photo = $emp['profile_photo_url'] ?: '';
                $role = $emp['employee_role'] ?: $emp['role'];
                $status = $emp['status'];
              ?>
                <tr data-role="<?= e($role) ?>" data-status="<?= e($status) ?>" data-name="<?= e(strtolower($emp['full_name'])) ?>">
                  <td>
                    <div class="em-person">
                      <?php if($photo): ?><img src="<?= e($photo) ?>" alt=""/><?php else: ?><div class="em-photo-placeholder"><?= e(strtoupper(substr($emp['full_name'],0,1))) ?></div><?php endif; ?>
                      <div><strong><?= e($emp['full_name']) ?></strong><small><?= e($emp['job_title'] ?: '—') ?></small></div>
                    </div>
                  </td>
                  <td><code><?= e($emp['login_id']) ?></code></td>
                  <td><?= e($emp['phone'] ?: '—') ?></td>
                  <td><span class="em-chip"><?= e(ucwords(str_replace('_',' ', $role))) ?></span></td>
                  <td><span class="em-status <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
                  <td>
                    <div class="em-actions">
                      <?php if(!$readOnly): ?>
                      <form method="POST"><input type="hidden" name="action" value="reset_pin"/><input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/><button class="em-small-btn" type="submit" data-i18n="reset_pin">Reset PIN</button></form>
                      <form method="POST"><input type="hidden" name="action" value="toggle_status"/><input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>"/><input type="hidden" name="new_status" value="<?= $status === 'active' ? 'inactive' : 'active' ?>"/><button class="em-small-btn danger" type="submit"><?= $status === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
                      <?php else: ?><span class="em-muted" data-i18n="actions_disabled">Actions disabled</span><?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="em-panel attendance-panel">
        <div class="em-panel-header"><h2 data-i18n="today_attendance">Today’s Attendance</h2><span class="em-muted" data-i18n="locked_times">Clock times are locked after submission.</span></div>
        <div class="em-attendance-grid">
          <?php if(empty($todayAttendance)): ?>
            <div class="em-empty-card" data-i18n="no_attendance">No clock-in records for today yet.</div>
          <?php else: foreach($todayAttendance as $row): ?>
            <div class="em-attendance-card">
              <strong><?= e($row['full_name']) ?></strong>
              <span>In: <?= e($row['clock_in'] ?: '—') ?></span>
              <span>Out: <?= e($row['clock_out'] ?: '—') ?></span>
              <small>🔒 Locked record</small>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php endif; ?>
  </main>
</div>

<div class="em-modal" id="addEmployeeModal" aria-hidden="true">
  <div class="em-modal-card">
    <div class="em-modal-header"><h2 data-i18n="add_employee_title">Add Employee</h2><button id="closeAddModal">✕</button></div>
    <form method="POST" class="em-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_employee"/>
      <div class="em-form-grid">
        <label><span data-i18n="first_name">First Name</span><input name="first_name" id="firstName" required/></label>
        <label><span data-i18n="last_name">Last Name</span><input name="last_name" id="lastName" required/></label>
        <label><span data-i18n="phone_required">Phone Number</span><input name="phone" required placeholder="+237 6xx xxx xxx"/></label>
        <label><span data-i18n="employee_role">Employee Role</span><select name="employee_role" required><option value="employee">Employee</option><option value="cashier">Cashier</option><option value="stock_manager">Stock Manager</option><option value="manager">Manager</option><option value="other">Other</option></select></label>
        <label class="em-full"><span data-i18n="job_title">Job Title / Notes</span><input name="job_title" placeholder="Example: Morning cashier, stock assistant..."/></label>
      </div>
      <div class="em-preview"><span data-i18n="username_preview">Username preview:</span> <b id="usernamePreview">firstnameLastname###</b> <small data-i18n="auto_pin">6-digit temporary PIN will be generated automatically.</small></div>
      <div class="em-form-actions"><button type="button" class="em-secondary" id="cancelAdd" data-i18n="cancel">Cancel</button><button type="submit" class="em-primary" data-i18n="create_employee">Create Employee</button></div>
    </form>
  </div>
</div>

<script src="employees.js"></script>
</body>
</html>
