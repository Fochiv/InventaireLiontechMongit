<?php
/* ============================================================
   employee_overview.php — LionTech Business Manager
   Owner / Manager: view all employees, clock status, profile
   Path: C:\Xampp\htdocs\InventoryLiontech\LionTech_Complete_MVP_Remaining_Pages\employee_overview.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);

$pdo        = getDB();
$user       = currentUser();
$businessId = (int)($user['business_id'] ?? 0);
$userId     = (int)($user['user_id']     ?? 0);
$role       = $user['role'] ?? '';
$isOwner    = ($role === ROLE_BUSINESS_OWNER);

if ($businessId <= 0) {
    header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ── Business info ── */
$business = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ex) {}

/* ── AJAX: get employee full profile ── */
if (isset($_GET['get_employee']) && is_numeric($_GET['get_employee'])) {
    $empId = (int)$_GET['get_employee'];
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, u.login_id, u.email, u.phone, u.role, u.status, u.created_at,
                   ep.first_name, ep.last_name, ep.employee_role, ep.job_title,
                   ep.profile_photo_url, ep.pin_must_change,
                   ws.monday, ws.tuesday, ws.wednesday, ws.thursday,
                   ws.friday, ws.saturday, ws.sunday,
                   ws.start_time, ws.end_time, ws.notes AS schedule_notes,
                   ea.clock_in_at, ea.gps_status
            FROM users u
            LEFT JOIN employee_profiles ep ON ep.user_id = u.user_id AND ep.business_id = u.business_id
            LEFT JOIN work_schedules ws    ON ws.user_id  = u.user_id AND ws.business_id  = u.business_id
            LEFT JOIN employee_attendance ea ON ea.user_id = u.user_id AND ea.business_id = u.business_id AND ea.clock_out_at IS NULL
            WHERE u.user_id = ? AND u.business_id = ?
            LIMIT 1
        ");
        $stmt->execute([$empId, $businessId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($emp ?: ['error' => 'Not found']);
    } catch (Throwable $ex) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $ex->getMessage()]);
    }
    exit;
}

/* ── AJAX: reset PIN ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_pin_ajax') {
    $empId = (int)($_POST['employee_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND business_id = ? AND role IN ('employee','manager') LIMIT 1");
        $stmt->execute([$empId, $businessId]);
        if (!$stmt->fetch()) throw new Exception('Employee not found.');
        $newPin  = (string)random_int(100000, 999999);
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ? AND business_id = ?")
            ->execute([password_hash($newPin, PASSWORD_BCRYPT), $empId, $businessId]);
        $pdo->prepare("UPDATE employee_profiles SET pin_must_change = 1 WHERE user_id = ? AND business_id = ?")
            ->execute([$empId, $businessId]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'pin' => $newPin]);
    } catch (Throwable $ex) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    }
    exit;
}

/* ── POST: status change ── */
$success = '';
$error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    $action   = $_POST['action']  ?? '';
    $targetId = (int)($_POST['employee_id'] ?? 0);
    if ($targetId > 0 && $targetId !== $userId) {
        try {
            $newStatus = match ($action) {
                'activate'   => 'active',
                'deactivate' => 'inactive',
                'leave'      => 'suspended',
                default      => null
            };
            if ($newStatus) {
                $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ? AND business_id = ?")
                    ->execute([$newStatus, $targetId, $businessId]);
                $success = 'Statut mis à jour.';
            }
        } catch (Throwable $ex) { $error = $ex->getMessage(); }
    }
}

/* ── Load all employees ── */
$employees = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.login_id, u.phone, u.role, u.status, u.created_at,
               ep.first_name, ep.last_name, ep.employee_role, ep.job_title, ep.profile_photo_url, ep.pin_must_change,
               ea.clock_in_at, ea.gps_status
        FROM users u
        LEFT JOIN employee_profiles ep ON ep.user_id = u.user_id AND ep.business_id = u.business_id
        LEFT JOIN employee_attendance ea ON ea.user_id = u.user_id AND ea.business_id = u.business_id AND ea.clock_out_at IS NULL
        WHERE u.business_id = ? AND u.role IN ('employee','manager')
        ORDER BY FIELD(u.role,'manager','employee'), u.full_name ASC
    ");
    $stmt->execute([$businessId]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) { $error = $error ?: $ex->getMessage(); }

$totalEmp    = count($employees);
$clockedInN  = count(array_filter($employees, fn($e) => !empty($e['clock_in_at'])));
$activeN     = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'active'));
$onLeaveN    = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'suspended'));

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);

$dayNames   = ['monday'=>'Lun','tuesday'=>'Mar','wednesday'=>'Mer','thursday'=>'Jeu','friday'=>'Ven','saturday'=>'Sam','sunday'=>'Dim'];
$dayNamesEn = ['monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri','saturday'=>'Sat','sunday'=>'Sun'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Employés — LionTech</title>
<link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
<style>
*{box-sizing:border-box}
.eo-wrap{max-width:1200px;margin:0 auto;padding:20px 24px 40px}
.eo-alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.eo-alert.success{background:#F0FDF4;border:1px solid #86EFAC;color:#166534}
.eo-alert.error{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}

/* Stats */
.eo-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.eo-stat{background:#fff;border-radius:14px;padding:16px;box-shadow:0 2px 12px rgba(11,31,58,.07);display:flex;gap:12px;align-items:center}
.eo-stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.eo-stat-icon.blue{background:#EFF6FF}
.eo-stat-icon.green{background:#F0FDF4}
.eo-stat-icon.amber{background:#FEF3C7}
.eo-stat-icon.purple{background:#F5F3FF}
.eo-stat small{display:block;color:#94A3B8;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.eo-stat strong{font-size:22px;font-weight:800;color:#0B1F3A}

/* Table card */
.eo-card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(11,31,58,.07);overflow:hidden}
.eo-card-head{padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.eo-card-head h2{margin:0;font-size:16px;color:#0B1F3A;font-weight:700}
.eo-search{padding:9px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-family:inherit;font-size:13px;min-width:220px;outline:none}
.eo-search:focus{border-color:#1A9E7A}
.eo-table{width:100%;border-collapse:collapse;font-size:13px}
.eo-table th{padding:10px 14px;text-align:left;background:#F8FAFC;border-bottom:1.5px solid #E5E7EB;color:#64748B;font-size:11px;font-weight:700;text-transform:uppercase;white-space:nowrap}
.eo-table td{padding:12px 14px;border-bottom:1px solid #F1F5F9;vertical-align:middle}
.eo-table tr:hover td{background:#FAFAFA}
.eo-table tr:last-child td{border-bottom:none}

/* Employee cell */
.eo-person{display:flex;align-items:center;gap:10px}
.eo-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #E2E8F0}
.eo-avatar-placeholder{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0B1F3A,#1A9E7A);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:700;flex-shrink:0}
.eo-name{font-weight:700;color:#0B1F3A;font-size:13.5px}
.eo-sub{font-size:11.5px;color:#94A3B8}

/* Badges */
.badge{display:inline-block;padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;white-space:nowrap}
.badge.manager{background:#DBEAFE;color:#1E40AF}
.badge.employee{background:#F1F5F9;color:#475569}
.badge.cashier{background:#FEF3C7;color:#92400E}
.badge.stock_manager{background:#EDE9FE;color:#5B21B6}
.badge.team_lead{background:#FCE7F3;color:#9D174D}
.badge.active{background:#DCFCE7;color:#166534}
.badge.inactive{background:#FEE2E2;color:#991B1B}
.badge.suspended{background:#FEF3C7;color:#92400E}
.badge.clocked-in{background:#DCFCE7;color:#166534}
.badge.clocked-out{background:#F1F5F9;color:#64748B}

/* Action buttons */
.eo-actions{display:flex;gap:6px;flex-wrap:wrap}
.eo-btn{border:none;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
.eo-btn.view{background:#EFF6FF;color:#1E40AF}
.eo-btn.leave{background:#FEF3C7;color:#92400E}
.eo-btn.deactivate{background:#FEE2E2;color:#991B1B}
.eo-btn.activate{background:#DCFCE7;color:#166534}
.eo-btn:hover{opacity:.85}

/* Modal */
.eo-modal{display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.5);overflow-y:auto;padding:20px}
.eo-modal.show{display:flex;align-items:flex-start;justify-content:center}
.eo-modal-card{background:#fff;border-radius:20px;width:100%;max-width:700px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden}
.eo-modal-head{background:#0B1F3A;padding:20px 24px;display:flex;align-items:center;gap:14px}
.eo-modal-head img{width:60px;height:60px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3);flex-shrink:0}
.eo-modal-head .placeholder{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#D4A017,#F0C040);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#1f2937;flex-shrink:0}
.eo-modal-name{font-size:18px;font-weight:800;color:#fff}
.eo-modal-role{font-size:12px;color:rgba(255,255,255,.6);margin-top:2px}
.eo-modal-close{margin-left:auto;background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.eo-modal-body{padding:24px}
.eo-section{margin-bottom:20px}
.eo-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94A3B8;border-bottom:1px solid #F1F5F9;padding-bottom:6px;margin-bottom:12px}
.eo-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.eo-info-item{padding:10px 12px;background:#F8FAFC;border-radius:10px}
.eo-info-label{font-size:11px;color:#94A3B8;font-weight:600;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px}
.eo-info-val{font-size:13.5px;color:#0B1F3A;font-weight:600}
.eo-day-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.eo-day-pill{padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700}
.eo-day-pill.on{background:#0B1F3A;color:#fff}
.eo-day-pill.off{background:#F1F5F9;color:#94A3B8}
.eo-pin-box{background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:12px;padding:14px 16px;text-align:center;margin-top:8px;display:none}
.eo-pin-val{font-size:28px;font-weight:900;color:#166534;font-family:monospace;letter-spacing:6px}
.eo-modal-footer{padding:16px 24px;border-top:1px solid #F1F5F9;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.eo-modal-btn{padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:700;cursor:pointer;border:none;font-family:inherit}
.eo-modal-btn.close{background:#F1F5F9;color:#374151}
.eo-modal-btn.pin{background:#0B1F3A;color:#fff}
.eo-modal-btn.pin:hover{background:#1A9E7A}

/* Add employee link */
.eo-add-btn{background:#0B1F3A;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px}
.eo-add-btn:hover{background:#1A9E7A}

@media(max-width:768px){
  .eo-stats{grid-template-columns:1fr 1fr}
  .eo-info-grid{grid-template-columns:1fr}
  .eo-modal-card{border-radius:14px}
}
@media(max-width:480px){
  .eo-stats{grid-template-columns:1fr}
  .eo-wrap{padding:14px 16px 30px}
}
</style>
</head>
<body>
<div class="od-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

<main class="od-main">
  <header class="od-topbar">
    <button class="od-menu-btn" id="od-menu-btn">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="od-business-title">
      <h1 data-i18n="page_title">Vue des employés</h1>
      <p><?= e($business['business_name'] ?? 'Business') ?> — <span data-i18n="page_sub">Statut, horaires et gestion du personnel</span></p>
    </div>
    <div class="od-top-actions">
      <button class="od-lang" id="langBtn">EN</button>
      <div class="od-avatar"><?= e($initials) ?></div>
    </div>
  </header>

  <div class="eo-wrap">

    <?php if ($success): ?><div class="eo-alert success">✅ <?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="eo-alert error">⚠️ <?= e($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="eo-stats">
      <div class="eo-stat">
        <div class="eo-stat-icon blue">👥</div>
        <div><small data-i18n="stat_total">Total employés</small><strong><?= $totalEmp ?></strong></div>
      </div>
      <div class="eo-stat">
        <div class="eo-stat-icon green">🟢</div>
        <div><small data-i18n="stat_clocked">Au travail</small><strong><?= $clockedInN ?></strong></div>
      </div>
      <div class="eo-stat">
        <div class="eo-stat-icon purple">✅</div>
        <div><small data-i18n="stat_active">Actifs</small><strong><?= $activeN ?></strong></div>
      </div>
      <div class="eo-stat">
        <div class="eo-stat-icon amber">🌙</div>
        <div><small data-i18n="stat_leave">En congé</small><strong><?= $onLeaveN ?></strong></div>
      </div>
    </div>

    <!-- Table -->
    <div class="eo-card">
      <div class="eo-card-head">
        <div>
          <h2 data-i18n="list_title">Liste des employés</h2>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <input type="search" id="eoSearch" class="eo-search"
                 placeholder="Rechercher..." data-i18n-ph="search_ph"/>
          <a class="eo-add-btn"
             href="<?= APP_URL ?>/LionTech_Employee_Management/employees.php">
            ➕ <span data-i18n="btn_add">Ajouter employé</span>
          </a>
        </div>
      </div>

      <div style="overflow-x:auto">
        <table class="eo-table" id="eoTable">
          <thead>
            <tr>
              <th data-i18n="col_employee">Employé</th>
              <th data-i18n="col_role">Rôle</th>
              <th data-i18n="col_phone">Téléphone</th>
              <th data-i18n="col_clock">Présence</th>
              <th data-i18n="col_account">Compte</th>
              <th data-i18n="col_actions">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($employees)): ?>
          <tr><td colspan="6" style="padding:28px;text-align:center;color:#94A3B8" data-i18n="no_employees">
            Aucun employé. <a href="<?= APP_URL ?>/LionTech_Employee_Management/employees.php" style="color:#1A9E7A;font-weight:700">Ajouter →</a>
          </td></tr>
          <?php else: foreach ($employees as $emp):
            $clockedIn = !empty($emp['clock_in_at']);
            $status    = $emp['status'] ?? 'active';
            $empRole   = $emp['employee_role'] ?: $emp['role'];
            $searchStr = strtolower(($emp['full_name']??'').' '.($emp['login_id']??'').' '.($emp['phone']??'').' '.($empRole));
          ?>
          <tr data-search="<?= e($searchStr) ?>">
            <td>
              <div class="eo-person">
                <?php if (!empty($emp['profile_photo_url'])): ?>
                <img class="eo-avatar" src="<?= e($emp['profile_photo_url']) ?>" alt="">
                <?php else: ?>
                <div class="eo-avatar-placeholder"><?= strtoupper(substr($emp['full_name'] ?? 'E', 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                  <div class="eo-name"><?= e($emp['full_name'] ?? '—') ?></div>
                  <div class="eo-sub"><?= e($emp['job_title'] ?: $emp['login_id']) ?></div>
                  <?php if ($emp['pin_must_change']): ?>
                  <div style="font-size:11px;color:#F97316;font-weight:700">⚠️ PIN à changer</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="badge <?= e(str_replace('_','-',$empRole)) ?>">
                <?= e(ucwords(str_replace('_',' ', $empRole))) ?>
              </span>
            </td>
            <td style="color:#374151"><?= e($emp['phone'] ?: '—') ?></td>
            <td>
              <?php if ($clockedIn): ?>
              <span class="badge clocked-in">🟢 <span data-i18n="on_clock">Au travail</span></span>
              <div style="font-size:11px;color:#6B7280;margin-top:3px">
                <?= e(date('H:i', strtotime($emp['clock_in_at']))) ?>
              </div>
              <?php else: ?>
              <span class="badge clocked-out">⚪ <span data-i18n="off_clock">Absent</span></span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
            <td>
              <div class="eo-actions">
                <!-- View full profile -->
                <button class="eo-btn view" type="button"
                        onclick="viewEmployee(<?= (int)$emp['user_id'] ?>)">
                  👁 <span data-i18n="btn_view">Voir</span>
                </button>

                <?php if ($isOwner && (int)$emp['user_id'] !== $userId): ?>
                  <?php if ($status === 'active'): ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="leave">
                    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>">
                    <button class="eo-btn leave" type="submit" data-i18n="btn_leave">Congé</button>
                  </form>
                  <form method="POST" style="display:inline"
                        onsubmit="return confirm('Désactiver cet employé ?')">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>">
                    <button class="eo-btn deactivate" type="submit" data-i18n="btn_deactivate">Désactiver</button>
                  </form>
                  <?php else: ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="employee_id" value="<?= (int)$emp['user_id'] ?>">
                    <button class="eo-btn activate" type="submit" data-i18n="btn_activate">Activer</button>
                  </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>
</div>

<!-- ══ EMPLOYEE PROFILE MODAL ══ -->
<div class="eo-modal" id="empModal" aria-hidden="true">
  <div class="eo-modal-card">
    <div class="eo-modal-head">
      <div class="placeholder" id="modalAvatarPh">?</div>
      <img id="modalAvatar" src="" alt="" style="display:none">
      <div>
        <div class="eo-modal-name" id="modalName">—</div>
        <div class="eo-modal-role" id="modalRole">—</div>
      </div>
      <button class="eo-modal-close" onclick="closeModal()">✕</button>
    </div>

    <div class="eo-modal-body" id="modalBody">
      <p style="text-align:center;color:#94A3B8">Chargement...</p>
    </div>

    <div class="eo-modal-footer">
      <!-- PIN reset -->
      <?php if ($isOwner): ?>
      <button class="eo-modal-btn pin" id="resetPinBtn" onclick="resetPin()">
        🔑 <span data-i18n="btn_reset_pin">Nouveau PIN temporaire</span>
      </button>
      <?php endif; ?>
      <button class="eo-modal-btn close" onclick="closeModal()" data-i18n="btn_close">Fermer</button>
    </div>

    <!-- PIN display box -->
    <div class="eo-pin-box" id="pinBox">
      <div style="font-size:12px;color:#6B7280;margin-bottom:6px" data-i18n="new_pin_label">Nouveau PIN temporaire :</div>
      <div class="eo-pin-val" id="pinDisplay">——</div>
      <p style="font-size:12px;color:#6B7280;margin-top:8px" data-i18n="pin_notice">
        Montrez ce PIN à l'employé une seule fois. Il devra le changer après connexion.
      </p>
    </div>
  </div>
</div>

<script>
/* ── Translations ── */
const T = {
  fr: {
    page_title:'Vue des employés', page_sub:'Statut, horaires et gestion du personnel',
    stat_total:'Total employés', stat_clocked:'Au travail', stat_active:'Actifs', stat_leave:'En congé',
    list_title:'Liste des employés', search_ph:'Rechercher...',
    btn_add:'Ajouter employé', col_employee:'Employé', col_role:'Rôle',
    col_phone:'Téléphone', col_clock:'Présence', col_account:'Compte', col_actions:'Actions',
    on_clock:'Au travail', off_clock:'Absent',
    btn_view:'Voir', btn_leave:'Congé', btn_deactivate:'Désactiver', btn_activate:'Activer',
    no_employees:'Aucun employé.',
    sec_identity:'👤 Identité', sec_contact:'📞 Contact',
    sec_role:'💼 Rôle & Poste', sec_schedule:'📅 Horaire de travail',
    sec_account:'🔐 Compte',
    label_login:'Identifiant', label_status:'Statut', label_pin:'PIN à changer',
    label_dob:'Date de naissance', label_gender:'Genre', label_phone:'Téléphone',
    label_emergency:'Téléphone d\'urgence', label_address:'Adresse',
    label_role:'Rôle', label_title:'Titre du poste',
    label_start:'Heure début', label_end:'Heure fin', label_schedule_notes:'Notes',
    label_clock:'Statut présence', clocked_in:'🟢 Au travail depuis',
    clocked_out:'⚪ Absent', no_schedule:'Aucun horaire défini',
    yes:'Oui', no:'Non', not_set:'Non renseigné',
    btn_reset_pin:'Nouveau PIN temporaire', btn_close:'Fermer',
    new_pin_label:'Nouveau PIN temporaire :', pin_notice:'Montrez ce PIN à l\'employé une seule fois.',
  },
  en: {
    page_title:'Employee Overview', page_sub:'Status, schedules, and staff management',
    stat_total:'Total employees', stat_clocked:'At work', stat_active:'Active', stat_leave:'On leave',
    list_title:'Employee List', search_ph:'Search...',
    btn_add:'Add employee', col_employee:'Employee', col_role:'Role',
    col_phone:'Phone', col_clock:'Attendance', col_account:'Account', col_actions:'Actions',
    on_clock:'At work', off_clock:'Absent',
    btn_view:'View', btn_leave:'Leave', btn_deactivate:'Deactivate', btn_activate:'Activate',
    no_employees:'No employees.',
    sec_identity:'👤 Identity', sec_contact:'📞 Contact',
    sec_role:'💼 Role & Position', sec_schedule:'📅 Work Schedule',
    sec_account:'🔐 Account',
    label_login:'Login ID', label_status:'Status', label_pin:'PIN change required',
    label_dob:'Date of birth', label_gender:'Gender', label_phone:'Phone',
    label_emergency:'Emergency phone', label_address:'Address',
    label_role:'Role', label_title:'Job title',
    label_start:'Start time', label_end:'End time', label_schedule_notes:'Notes',
    label_clock:'Attendance status', clocked_in:'🟢 At work since',
    clocked_out:'⚪ Absent', no_schedule:'No schedule set',
    yes:'Yes', no:'No', not_set:'Not provided',
    btn_reset_pin:'New temporary PIN', btn_close:'Close',
    new_pin_label:'New temporary PIN:', pin_notice:'Show this PIN to the employee once. They must change it after login.',
  }
};

let lang = localStorage.getItem('lt_lang') || 'fr';
const langBtn = document.getElementById('langBtn');

function applyLang() {
  const t = T[lang];
  document.documentElement.lang = lang;
  langBtn.textContent = lang === 'fr' ? 'EN' : 'FR';
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n; if (t[k]) el.textContent = t[k];
  });
  document.querySelectorAll('[data-i18n-ph]').forEach(el => {
    const k = el.dataset.i18nPh; if (t[k]) el.placeholder = t[k];
  });
  localStorage.setItem('lt_lang', lang);
}
langBtn.addEventListener('click', () => { lang = lang === 'fr' ? 'en' : 'fr'; applyLang(); });
applyLang();

/* ── Sidebar ── */
document.getElementById('od-menu-btn')?.addEventListener('click', () => {
  const sb = document.getElementById('od-sidebar');
  const ov = document.getElementById('od-overlay');
  sb?.classList.toggle('open');
  if (ov) ov.style.display = sb?.classList.contains('open') ? 'block' : 'none';
});

/* ── Search ── */
document.getElementById('eoSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#eoTable tbody tr').forEach(row => {
    row.style.display = (row.dataset.search || row.textContent.toLowerCase()).includes(q) ? '' : 'none';
  });
});

/* ── Modal ── */
const modal    = document.getElementById('empModal');
const pinBox   = document.getElementById('pinBox');
let currentEmpId = null;

const dayKeys   = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
const dayNamesFr = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
const dayNamesEn = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function viewEmployee(userId) {
  currentEmpId = userId;
  pinBox.style.display = 'none';
  document.getElementById('pinDisplay').textContent = '——';
  document.getElementById('modalName').textContent = '...';
  document.getElementById('modalRole').textContent = '';
  document.getElementById('modalBody').innerHTML = '<p style="text-align:center;color:#94A3B8;padding:20px">Chargement...</p>';
  modal.classList.add('show');

  fetch('?get_employee=' + userId)
    .then(r => r.json())
    .then(emp => {
      if (emp.error) {
        document.getElementById('modalBody').innerHTML = '<p style="color:#991B1B;text-align:center">Erreur: ' + emp.error + '</p>';
        return;
      }

      const t = T[lang];
      const names = lang === 'fr' ? dayNamesFr : dayNamesEn;

      /* Header */
      const ph  = document.getElementById('modalAvatarPh');
      const img = document.getElementById('modalAvatar');
      if (emp.profile_photo_url) {
        img.src = emp.profile_photo_url; img.style.display = 'block'; ph.style.display = 'none';
      } else {
        ph.textContent = (emp.full_name || 'E')[0].toUpperCase(); ph.style.display = 'flex'; img.style.display = 'none';
      }
      document.getElementById('modalName').textContent = emp.full_name || '—';
      document.getElementById('modalRole').textContent = emp.job_title || emp.employee_role || emp.role || '—';

      /* Body */
      const statusMap = { active: lang==='fr'?'Actif':'Active', inactive: lang==='fr'?'Inactif':'Inactive', suspended: lang==='fr'?'En congé':'On Leave' };

      let html = '';

      /* Account section */
      html += `<div class="eo-section">
        <div class="eo-section-title">${t.sec_account}</div>
        <div class="eo-info-grid">
          <div class="eo-info-item"><div class="eo-info-label">${t.label_login}</div><div class="eo-info-val" style="font-family:monospace">${emp.login_id||'—'}</div></div>
          <div class="eo-info-item"><div class="eo-info-label">${t.label_status}</div><div class="eo-info-val">${statusMap[emp.status]||emp.status||'—'}</div></div>
          <div class="eo-info-item"><div class="eo-info-label">${t.label_clock}</div><div class="eo-info-val">${emp.clock_in_at ? t.clocked_in + ' ' + emp.clock_in_at.slice(11,16) : t.clocked_out}</div></div>
          <div class="eo-info-item"><div class="eo-info-label">${t.label_pin}</div><div class="eo-info-val">${emp.pin_must_change ? '⚠️ ' + t.yes : t.no}</div></div>
        </div>
      </div>`;

      /* Role section */
      html += `<div class="eo-section">
        <div class="eo-section-title">${t.sec_role}</div>
        <div class="eo-info-grid">
          <div class="eo-info-item"><div class="eo-info-label">${t.label_role}</div><div class="eo-info-val">${emp.employee_role||emp.role||'—'}</div></div>
          <div class="eo-info-item"><div class="eo-info-label">${t.label_title}</div><div class="eo-info-val">${emp.job_title||t.not_set}</div></div>
        </div>
      </div>`;

      /* Contact section */
      html += `<div class="eo-section">
        <div class="eo-section-title">${t.sec_contact}</div>
        <div class="eo-info-grid">
          <div class="eo-info-item"><div class="eo-info-label">${t.label_phone}</div><div class="eo-info-val">${emp.phone||t.not_set}</div></div>
          <div class="eo-info-item"><div class="eo-info-label">Email</div><div class="eo-info-val">${emp.email||t.not_set}</div></div>
        </div>
      </div>`;

      /* Schedule section */
      html += `<div class="eo-section"><div class="eo-section-title">${t.sec_schedule}</div>`;
      const hasSched = dayKeys.some(d => emp[d] == 1);
      if (hasSched) {
        html += '<div class="eo-day-pills">';
        dayKeys.forEach((d, i) => {
          html += `<span class="eo-day-pill ${emp[d]?'on':'off'}">${names[i]}</span>`;
        });
        html += '</div>';
        if (emp.start_time && emp.end_time) {
          html += `<div style="font-size:13.5px;margin-bottom:8px">🕐 ${emp.start_time.slice(0,5)} → ${emp.end_time.slice(0,5)}</div>`;
        }
        if (emp.schedule_notes) {
          html += `<div style="font-size:13px;color:#6B7280">📝 ${emp.schedule_notes}</div>`;
        }
      } else {
        html += `<p style="color:#94A3B8;font-size:13px">${t.no_schedule}</p>`;
      }
      html += '</div>';

      document.getElementById('modalBody').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('modalBody').innerHTML = '<p style="color:#991B1B;text-align:center">Erreur de chargement.</p>';
    });
}

function closeModal() {
  modal.classList.remove('show');
  pinBox.style.display = 'none';
  currentEmpId = null;
}

modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

/* ── Reset PIN ── */
function resetPin() {
  if (!currentEmpId) return;
  if (!confirm(lang === 'fr' ? 'Générer un nouveau PIN temporaire pour cet employé ?' : 'Generate a new temporary PIN for this employee?')) return;

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=reset_pin_ajax&employee_id=' + currentEmpId
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('pinDisplay').textContent = data.pin;
      pinBox.style.display = 'block';
      pinBox.scrollIntoView({ behavior: 'smooth' });
    } else {
      alert('Erreur: ' + (data.error || 'Unknown error'));
    }
  })
  .catch(() => alert('Erreur réseau.'));
}
</script>
</body>
</html>