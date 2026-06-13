<?php
/* ============================================================
   mvp_helpers.php — Tally Business Manager
   Path: LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

/* ── Escape output ── */
function lt_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ── Get current user from session ── */
function lt_user(): array {
    $u = currentUser();
    if (!$u['user_id']) {
        $u = [
            'user_id'     => 0,
            'business_id' => 0,
            'full_name'   => 'Demo Owner',
            'role'        => 'business_owner',
            'email'       => '',
        ];
    }
    return $u;
}

/* ── Safe DB call wrapper ── */
function lt_try(callable $cb, $fallback = []) {
    try { return $cb(); } catch (Throwable $e) { return $fallback; }
}

/* ── Auth guard: owner / manager / super_admin only ── */
function lt_guard_owner(): void {
    requireLogin();
    if (!in_array($_SESSION['role'], [ROLE_BUSINESS_OWNER, ROLE_MANAGER, ROLE_SUPER_ADMIN], true)) {
        header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
        exit;
    }
}

/* ── Employee guard ── */
function lt_guard_employee(): void {
    requireLogin();
    if (!in_array($_SESSION['role'], [ROLE_EMPLOYEE, ROLE_MANAGER, ROLE_BUSINESS_OWNER, ROLE_SUPER_ADMIN], true)) {
        header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
        exit;
    }
}

/* ── Subscription check ── */
function lt_subscription_expired(): bool {
    return false;
}

/* ── Sidebar ── */
function lt_sidebar(string $active = 'dashboard'): void {
    $base  = APP_URL;
    $links = [
        [$base . '/LionTech_Owner_Dashboard/owner_dashboard.php',               '<span class="icon-receipt">&#9962;</span>', 'Dashboard',     'dashboard'],
        [$base . '/LionTech_Employee_Management/liontech_employee_management/employees.php',              '<span class="icon-users">◎</span>', 'Employés',      'employees'],
        [$base . '/Produit/products.php',                                                                 '<span class="icon-box">▣</span>', 'Produits',      'products'],
        [$base . '/LionTech_Stock_In_Page/liontech_stock_in_page/stock_in.php',                          '📥', 'Stock entrant', 'stock_in'],
        [$base . '/stockout_stockfinis/stock_out.php',                                                    '📤', 'Stock sortant', 'stock_out'],
        [$base . '/Attendance_presenceemployer/clock_attendance.php',                                     '⏱️', 'Présence',      'attendance'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/approval_center.php',      '<span class="icon-ok">✓</span>', 'Validations',   'approval'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/reports.php',              '<span class="icon-chart">▦</span>', 'Rapports',      'reports'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/notifications.php',        '🔔', 'Notifications', 'notifications'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/activity_logs.php',        '<span class="icon-receipt">▤</span>', 'Activité',      'logs'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php', '<span class="icon-card">▬</span>', 'Abonnement',    'subscription'],
        [$base . '/LionTech_Complete_MVP_Remaining_Pages/settings.php',             '<span class="icon-gear"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>', 'Paramètres',    'settings'],
    ];

    $u        = lt_user();
    $fullName = $u['full_name'] ?: 'Utilisateur';
    $initials = '';
    foreach (explode(' ', trim($fullName)) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    $initials = substr($initials ?: 'U', 0, 2);

    echo '<aside class="lt-sidebar" id="lt-sidebar">';
    echo   '<div class="lt-brand">';
    echo     '<div class="lt-brand-icon"><span class="icon-brand">T</span></div>';
    echo     '<div><strong>LionTech</strong><small>Business Manager</small></div>';
    echo   '</div>';
    echo   '<nav class="lt-nav">';
    echo     '<div class="lt-nav-title">Business</div>';

    foreach ($links as $l) {
        $cls = $active === $l[3] ? ' active' : '';
        echo '<a class="lt-nav-item' . $cls . '" href="' . $l[0] . '">';
        echo   '<span class="lt-nav-icon">' . $l[1] . '</span>';
        echo   '<span>' . lt_e($l[2]) . '</span>';
        echo '</a>';
    }

    echo '<div class="lt-nav-title">Compte</div>';
    echo '<a class="lt-nav-item" href="' . $base . '/change_pin.php">';
    echo   '<span class="lt-nav-icon"><span class="icon-lock"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span></span></span><span>Changer PIN</span>';
    echo '</a>';
    echo '<a class="lt-nav-item" href="' . $base . '/Logininventory/logout.php">';
    echo   '<span class="lt-nav-icon"><span class="icon-door">▭</span></span><span>Déconnexion</span>';
    echo '</a>';

    echo   '</nav>';
    echo   '<div class="lt-sidebar-footer">';
    echo     '<div class="lt-avatar">' . lt_e($initials) . '</div>';
    echo     '<div>';
    echo       '<div class="lt-user-name">' . lt_e($fullName) . '</div>';
    echo       '<div class="lt-user-role">Business Manager</div>';
    echo     '</div>';
    echo   '</div>';
    echo '</aside>';
    echo '<div class="lt-overlay" id="lt-overlay"></div>';
}

/* ── Top bar ── */
function lt_header(string $title, string $subtitle = ''): void {
    $u = lt_user();
    echo '<header class="lt-topbar">';
    echo   '<div style="display:flex;gap:12px;align-items:center">';
    echo     '<button class="lt-menu" id="lt-menu" aria-label="Open menu"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>';
    echo     '<div>';
    echo       '<strong>' . lt_e($title) . '</strong>';
    if ($subtitle) {
        echo   '<div class="muted" style="font-size:13px">' . lt_e($subtitle) . '</div>';
    }
    echo     '</div>';
    echo   '</div>';
    echo   '<div style="display:flex;gap:10px;align-items:center">';
    echo     '<button id="langToggle" class="lang">FR</button>';
    echo     '<span class="badge info">' . lt_e($u['full_name'] ?: 'Utilisateur') . '</span>';
    echo   '</div>';
    echo '</header>';
}