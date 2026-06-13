<?php
require_once __DIR__ . '/../Config.php';
startSecureSession();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = getDB();

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function cleanPhone(string $p): string {
    return preg_replace('/\s+/', '', trim($p));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/LionTech_Complete_MVP_Remaining_Pages/subscription_plans.php');
    exit;
}

$businessName  = trim($_POST['business_name'] ?? '');
$businessType  = trim($_POST['business_type'] ?? '');
$country       = trim($_POST['country'] ?? '');
$otherCountry  = trim($_POST['other_country'] ?? '');
$currency      = trim($_POST['currency'] ?? '');
$otherCurrency = trim($_POST['other_currency'] ?? '');
$city          = trim($_POST['city'] ?? '');
$phone         = cleanPhone($_POST['phone'] ?? '');
$businessEmail = trim($_POST['business_email'] ?? '');
$address       = trim($_POST['address'] ?? '');

$ownerFirstName = trim($_POST['owner_first_name'] ?? '');
$ownerLastName  = trim($_POST['owner_last_name'] ?? '');
$ownerPhone     = cleanPhone($_POST['owner_phone'] ?? '');
$ownerEmail     = trim($_POST['owner_email'] ?? '');

$planName      = trim($_POST['plan_name'] ?? 'Basic');
$billingCycle  = trim($_POST['billing_cycle'] ?? 'monthly');
$paymentMethod = trim($_POST['preferred_payment_method'] ?? '');
$hasEmployees  = (int)($_POST['has_employees'] ?? 0);
$features      = $_POST['features'] ?? [];

if ($country === 'Other') {
    $country = $otherCountry;
}

if ($currency === 'Other') {
    $currency = $otherCurrency;
}
if ($businessType === 'Other') {
    $businessType = trim($_POST['other_business_type'] ?? '');
}

if ($paymentMethod === 'other') {
    $paymentMethod = trim($_POST['other_payment_method'] ?? '');
}

$allowedPlans = ['Basic', 'Standard', 'Premium'];
$allowedBilling = ['monthly', '3_months', '6_months', 'yearly'];

$planPrices = [
    'Basic' => 2000,
    'Standard' => 5000,
    'Premium' => 10000
];

$errors = [];

if (!$businessName) $errors[] = 'Business name is required.';
if (!$businessType) $errors[] = 'Business type is required.';
if (!$country) $errors[] = 'Country is required.';
if (!$currency) $errors[] = 'Currency is required.';
if (!$city) $errors[] = 'City is required.';
if (!$phone) $errors[] = 'Business phone is required.';

if (!$ownerFirstName) $errors[] = 'Owner first name is required.';
if (!$ownerLastName) $errors[] = 'Owner last name is required.';
if (!$ownerPhone) $errors[] = 'Owner phone is required.';

if ($businessEmail && !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Business email is invalid.';
}

if ($ownerEmail && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Owner email is invalid.';
}

if (!in_array($planName, $allowedPlans, true)) {
    $errors[] = 'Invalid subscription plan.';
}

if (!in_array($billingCycle, $allowedBilling, true)) {
    $errors[] = 'Invalid billing cycle.';
}

if (!$paymentMethod) {
    $errors[] = 'Preferred payment method is required.';
}

if ($errors) {
    $_SESSION['business_request_errors'] = $errors;
    header('Location: ' . APP_URL . '/LionTech_Add_Business_Page/Ajoute_business_login.php?plan=' . urlencode($planName));
    exit;
}

$ownerFullName = trim($ownerFirstName . ' ' . $ownerLastName);
$requestedFeatures = json_encode(array_values($features), JSON_UNESCAPED_UNICODE);
$amount = $planPrices[$planName];

try {
    $stmt = $pdo->prepare("
        INSERT INTO business_requests (
            business_name,
            business_type,
            country,
            currency,
            city,
            address,
            phone,
            business_email,
            owner_first_name,
            owner_last_name,
            owner_full_name,
            owner_phone,
            owner_email,
            plan_name,
            amount,
            billing_cycle,
            preferred_payment_method,
            has_employees,
            requested_features,
            status,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
        )
    ");

   
    $stmt->execute([
        $businessName,
        $businessType,
        $country,
        $currency,
        $city,
        $address ?: null,
        $phone,
        $businessEmail ?: null,
        $ownerFirstName,
        $ownerLastName,
        $ownerFullName,
        $ownerPhone,
        $ownerEmail ?: null,
        $planName,
        $amount,
        $billingCycle,
        $paymentMethod,
        $hasEmployees,
        $requestedFeatures
    ]);

    /* Log activity ONLY after successful insert */
    try {
        $pdo->prepare("INSERT INTO activity_logs 
            (user_id, business_id, action, description, icon, ip_address) 
            VALUES (NULL, NULL, 'business_request_submitted', ?, 'building', ?)")
            ->execute([
                'Nouvelle demande business : ' . $businessName . ' — ' . $ownerFullName,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
    } catch (Throwable $ignored) {}

    $_SESSION['business_request_success'] = 'Your business request was submitted successfully.';
$_SESSION['business_request_owner_phone'] = $ownerPhone;

header('Location: ' . APP_URL . '/LionTech_Add_Business_Page/request_success.php');
    exit;

} catch (Throwable $ex) {
    $_SESSION['business_request_errors'] = [
        'Database error: ' . $ex->getMessage()
    ];

    header('Location: ' . APP_URL . '/LionTech_Add_Business_Page/Ajoute_business_login.php?plan=' . urlencode($planName));
    exit;
}