<?php
/* ============================================================
   auth.php — LionTech Business Manager
   FIXED: redirects to setup_security.php on first login
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\auth.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success'=>false,'message'=>'Method not allowed.']));
}

header('Content-Type: application/json');

$raw      = file_get_contents('php://input');
$data     = json_decode($raw, true) ?: $_POST;
$loginId  = trim($data['login_id'] ?? '');
$password = trim($data['password'] ?? '');

if ($loginId === '' || $password === '') {
    exit(json_encode(['success'=>false,'message'=>'Veuillez remplir tous les champs.','code'=>'empty_fields']));
}

$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$pdo = getDB();

/* ── Brute-force protection ── */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE login_id=:lid AND ip_address=:ip AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$stmt->execute([':lid'=>$loginId,':ip'=>$ip]);
if ((int)$stmt->fetchColumn() >= 10) {
    exit(json_encode(['success'=>false,'message'=>'Trop de tentatives. Réessayez dans 15 minutes.','code'=>'too_many_attempts']));
}

/* ── Find user ── */
$stmt = $pdo->prepare("SELECT u.*,b.subscription_status,b.subscription_expires_at FROM users u LEFT JOIN businesses b ON b.business_id=u.business_id WHERE u.login_id=:lid LIMIT 1");
$stmt->execute([':lid'=>$loginId]);
$user = $stmt->fetch();

$logFail = function() use ($pdo,$loginId,$ip) {
    $pdo->prepare("INSERT INTO login_attempts (login_id,ip_address) VALUES (:lid,:ip)")->execute([':lid'=>$loginId,':ip'=>$ip]);
};

if (!$user) { $logFail(); exit(json_encode(['success'=>false,'message'=>'Identifiant ou mot de passe incorrect.','code'=>'invalid_credentials'])); }

/* ── Check account not security-flagged ── */
if ((int)($user['security_flagged'] ?? 0) === 1) {
    exit(json_encode(['success'=>false,'message'=>'Votre compte est verrouillé. Contactez votre responsable ou LionTech.','code'=>'account_locked']));
}

/* ── Verify password — normalize $2b$ → $2y$ ── */
$hash = $user['password_hash'];
if (str_starts_with($hash, '$2b$')) $hash = '$2y$' . substr($hash, 4);

if (!password_verify($password, $hash)) {
    $logFail();
    exit(json_encode(['success'=>false,'message'=>'Identifiant ou mot de passe incorrect.','code'=>'invalid_credentials']));
}

/* ── Account status ── */
if (in_array($user['status'], ['inactive','suspended'], true)) {
    exit(json_encode(['success'=>false,'message'=>'Votre compte est inactif. Contactez LionTech.','code'=>'account_inactive']));
}

/* ── Subscription check ── */
$subscriptionWarning = null;
if ($user['role'] !== ROLE_SUPER_ADMIN) {
    $subStatus = $user['subscription_status'] ?? null;
    if ($subStatus === 'expired') {
        if ($user['role'] === ROLE_BUSINESS_OWNER) {
            exit(json_encode(['success'=>false,'message'=>'Votre abonnement est expiré. Veuillez renouveler.','code'=>'subscription_expired','redirect'=>APP_URL.'/LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php']));
        } else {
            exit(json_encode(['success'=>false,'message'=>'L\'abonnement de votre business est expiré. Contactez votre propriétaire.','code'=>'subscription_expired']));
        }
    }
    if ($subStatus === 'trial') $subscriptionWarning = 'Vous êtes en mode essai. Pensez à souscrire un abonnement.';
}

/* ── Set session ── */
session_regenerate_id(true);
$_SESSION['user_id']     = (int)$user['user_id'];
$_SESSION['business_id'] = $user['business_id'] ? (int)$user['business_id'] : null;
$_SESSION['full_name']   = $user['full_name'];
$_SESSION['role']        = $user['role'];
$_SESSION['email']       = $user['email'] ?? '';
$_SESSION['login_time']  = time();

/* ── Update last_login ── */
$pdo->prepare("UPDATE users SET last_login=NOW() WHERE user_id=:uid")->execute([':uid'=>$user['user_id']]);

/* ── Clear brute-force log ── */
$pdo->prepare("DELETE FROM login_attempts WHERE login_id=:lid AND ip_address=:ip")->execute([':lid'=>$loginId,':ip'=>$ip]);

/* ── Check if security questions are set up ── */
$hasQuestions = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM security_questions WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);
    $hasQuestions = (int)$stmt->fetchColumn() > 0;
} catch (Throwable $e) {}

/* ── Check pin_must_change ── */
$pinMustChange = false;
try {
    $stmt = $pdo->prepare('SELECT pin_must_change FROM employee_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['user_id']]);
    $row = $stmt->fetch();
    $pinMustChange = $row ? (bool)$row['pin_must_change'] : false;
} catch (Throwable $e) {}

/* ── Redirect to setup if first login or no security questions (skip pour super_admin) ── */
if ($user['role'] !== ROLE_SUPER_ADMIN && (!$hasQuestions || $pinMustChange)) {
    exit(json_encode([
        'success'  => true,
        'redirect' => APP_URL . '/Logininventory/setup_security.php',
        'code'     => 'setup_required',
    ]));
}

/* ── Normal dashboard redirect ── */
$routes   = json_decode(DASHBOARD_ROUTES, true);
$rolePath = $routes[$user['role']] ?? 'Logininventory/login.php';
$redirect = APP_URL . '/' . $rolePath;

exit(json_encode([
    'success'              => true,
    'role'                 => $user['role'],
    'full_name'            => $user['full_name'],
    'redirect'             => $redirect,
    'subscription_warning' => $subscriptionWarning,
    'code'                 => 'ok',
]));