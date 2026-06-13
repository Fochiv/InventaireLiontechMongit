<?php
/* ============================================================
   auth.php — LionTech Business Manager
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\auth.php
   ============================================================ */

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../Config.php';

try {
    startSecureSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'code' => 'method_not_allowed',
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        $data = $_POST;
    }

    $loginId = trim($data['login_id'] ?? '');
    $password = trim($data['password'] ?? '');

    if ($loginId === '' || $password === '') {
        echo json_encode([
            'success' => false,
            'code' => 'empty_fields',
            'message' => 'Please fill in all fields.'
        ]);
        exit;
    }

    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Check failed attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE login_id = :login_id 
          AND ip_address = :ip_address 
          AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([
        ':login_id' => $loginId,
        ':ip_address' => $ip
    ]);

    if ((int)$stmt->fetchColumn() >= 10) {
        echo json_encode([
            'success' => false,
            'code' => 'too_many_attempts',
            'message' => 'Too many login attempts. Try again in 15 minutes.'
        ]);
        exit;
    }

    // Find user
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            b.subscription_status,
            b.subscription_expires_at
        FROM users u
        LEFT JOIN businesses b ON b.business_id = u.business_id
        WHERE u.login_id = :login_id
        LIMIT 1
    ");
    $stmt->execute([':login_id' => $loginId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $logFail = function () use ($pdo, $loginId, $ip) {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (login_id, ip_address, attempted_at)
            VALUES (:login_id, :ip_address, NOW())
        ");
        $stmt->execute([
            ':login_id' => $loginId,
            ':ip_address' => $ip
        ]);
    };

    if (!$user) {
        $logFail();
        echo json_encode([
            'success' => false,
            'code' => 'invalid_credentials',
            'message' => 'Invalid login ID or password.'
        ]);
        exit;
    }

    // Account locked
    if ((int)($user['security_flagged'] ?? 0) === 1) {
        echo json_encode([
            'success' => false,
            'code' => 'account_locked',
            'message' => 'Your account is locked. Contact LionTech support.'
        ]);
        exit;
    }

    // Verify password
    $hash = $user['password_hash'] ?? '';

    if (str_starts_with($hash, '$2b$')) {
        $hash = '$2y$' . substr($hash, 4);
    }

    if ($hash === '' || !password_verify($password, $hash)) {
        $logFail();
        echo json_encode([
            'success' => false,
            'code' => 'invalid_credentials',
            'message' => 'Invalid login ID or password.'
        ]);
        exit;
    }

    // Account status
    if (in_array($user['status'] ?? '', ['inactive', 'suspended'], true)) {
        echo json_encode([
            'success' => false,
            'code' => 'account_inactive',
            'message' => 'Your account is inactive. Contact LionTech support.'
        ]);
        exit;
    }

    // Subscription check
    $subscriptionWarning = null;

    if (($user['role'] ?? '') !== ROLE_SUPER_ADMIN) {
        $subStatus = $user['subscription_status'] ?? null;

        if ($subStatus === 'expired') {
            if (($user['role'] ?? '') === ROLE_BUSINESS_OWNER) {
                echo json_encode([
                    'success' => false,
                    'code' => 'subscription_expired',
                    'message' => 'Your subscription has expired. Please renew.',
                    'redirect' => APP_URL . '/LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php'
                ]);
                exit;
            }

            echo json_encode([
                'success' => false,
                'code' => 'subscription_expired',
                'message' => 'This business subscription has expired. Contact the owner.'
            ]);
            exit;
        }

        if ($subStatus === 'trial') {
            $subscriptionWarning = 'You are currently in trial mode.';
        }
    }

    // Start login session
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['business_id'] = !empty($user['business_id']) ? (int)$user['business_id'] : null;
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['role'] = $user['role'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['login_time'] = time();

    // Update last login
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_login = NOW() 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user['user_id']]);

    // Clear failed attempts
    $stmt = $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE login_id = :login_id 
          AND ip_address = :ip_address
    ");
    $stmt->execute([
        ':login_id' => $loginId,
        ':ip_address' => $ip
    ]);

    // Security setup check
    $hasQuestions = true;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM security_questions 
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $user['user_id']]);
        $hasQuestions = (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        $hasQuestions = true;
    }

    // PIN change check
    $pinMustChange = false;

    try {
        $stmt = $pdo->prepare("
            SELECT pin_must_change 
            FROM employee_profiles 
            WHERE user_id = :user_id 
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $pinMustChange = (bool)$row['pin_must_change'];
        }
    } catch (Throwable $e) {
        $pinMustChange = false;
    }

    if (($user['role'] ?? '') !== ROLE_SUPER_ADMIN && (!$hasQuestions || $pinMustChange)) {
        echo json_encode([
            'success' => true,
            'code' => 'setup_required',
            'role' => $user['role'],
            'redirect' => APP_URL . '/Logininventory/setup_security.php'
        ]);
        exit;
    }

    // Dashboard redirect
    $routes = json_decode(DASHBOARD_ROUTES, true);

    if (!is_array($routes)) {
        $routes = [];
    }

    $role = $user['role'] ?? '';
    $rolePath = $routes[$role] ?? 'Logininventory/login.php';
    $redirect = APP_URL . '/' . $rolePath;

    echo json_encode([
        'success' => true,
        'code' => 'ok',
        'role' => $role,
        'full_name' => $user['full_name'] ?? '',
        'redirect' => $redirect,
        'subscription_warning' => $subscriptionWarning
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'code' => 'server_error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}