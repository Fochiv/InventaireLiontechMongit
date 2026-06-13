<?php
/* ============================================================
   Config.php — Tally Business Manager
   ============================================================ */

/* ── Auto-detect APP_URL (works with Replit proxy, WAMP subfolder, or root) ── */
if (!defined('APP_URL')) {
    $docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $configDir = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
    $subPath   = ($docRoot !== '' && strpos($configDir, $docRoot) === 0)
                 ? substr($configDir, strlen($docRoot))
                 : '';

    // On Replit: use REPLIT_DOMAINS env var (https, no port)
    $replitDomain = getenv('REPLIT_DOMAINS');
    if ($replitDomain) {
        $host   = explode(',', $replitDomain)[0];
        $scheme = 'https';
    } else {
        // WAMP / local: use HTTP_HOST with scheme detection
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    }
    define('APP_URL', $scheme . '://' . $host . rtrim($subPath, '/'));
    define('IS_REPLIT', (bool)$replitDomain);
}

/* ── Database credentials ── */
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'InventaireLiontech_db');
define('DB_CHARSET', 'utf8mb4');
/* ── MySQL socket (Replit uses Unix socket, WAMP uses TCP) ── */
define('DB_SOCKET',  file_exists('/tmp/mysql.sock') ? '/tmp/mysql.sock' : '');

/* ── App constants ── */
define('APP_NAME',    'Tally Business Manager');
define('APP_VERSION', '1.0.0');

/* ── Session lifetime ── */
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

/* ── Role constants ── */
define('ROLE_SUPER_ADMIN',    'super_admin');
define('ROLE_BUSINESS_OWNER', 'business_owner');
define('ROLE_MANAGER',        'manager');
define('ROLE_EMPLOYEE',       'employee');

/* ── Status constants ── */
define('STATUS_ACTIVE',   'active');
define('STATUS_INACTIVE', 'inactive');

/* ── Subscription status ── */
define('SUB_ACTIVE',  'active');
define('SUB_EXPIRED', 'expired');
define('SUB_TRIAL',   'trial');

/* ── Dashboard redirect map ── */
define('DASHBOARD_ROUTES', json_encode([
    ROLE_SUPER_ADMIN    => 'SuperAdmin/super_admin.php',
    ROLE_BUSINESS_OWNER => 'LionTech_Owner_Dashboard/owner_dashboard.php',
    ROLE_MANAGER        => 'LionTech_Owner_Dashboard/owner_dashboard.php',
    ROLE_EMPLOYEE       => 'LionTech_Employee_Dashboard/employee_dashboard.php',
]));

/* ============================================================
   Database connection (PDO — singleton)
   ============================================================ */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_SOCKET !== '') {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            DB_SOCKET, DB_NAME, DB_CHARSET
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit(json_encode([
            'success' => false,
            'message' => 'Database connection failed. Contact LionTech support.'
        ]));
    }
    return $pdo;
}

/* ============================================================
   Session helpers
   ============================================================ */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        // On Replit (iframe), SameSite=None+Secure required; locally Lax is fine
        $isReplit = defined('IS_REPLIT') ? IS_REPLIT : (bool)getenv('REPLIT_DOMAINS');
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'httponly' => true,
            'secure'   => $isReplit,
            'samesite' => $isReplit ? 'None' : 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/Logininventory/login.php?error=session_expired');
        exit;
    }
}

function requireRole(array $allowedRoles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        header('Location: ' . APP_URL . '/Logininventory/login.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    startSecureSession();
    return [
        'user_id'     => $_SESSION['user_id']     ?? null,
        'business_id' => $_SESSION['business_id'] ?? null,
        'full_name'   => $_SESSION['full_name']   ?? '',
        'role'        => $_SESSION['role']         ?? '',
        'email'       => $_SESSION['email']        ?? '',
    ];
}

function logout(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/Logininventory/login.php');
    exit;
}
