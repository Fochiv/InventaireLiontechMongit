<?php
/* config_client.php — Tally Client Portal shared config
   Path: Vente_cashier/Client/config_client.php */
require_once __DIR__ . '/../../Config.php';

/* ── Client session helpers (separate namespace from staff) ── */
function clientSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        session_set_cookie_params(['lifetime'=>3600*24*30,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
}
function isClientLoggedIn(): bool {
    clientSession();
    return !empty($_SESSION['cl_id']) && !empty($_SESSION['cl_phone']);
}
function currentClient(): array {
    clientSession();
    return [
        'client_id' => $_SESSION['cl_id']   ?? null,
        'phone'     => $_SESSION['cl_phone'] ?? '',
        'name'      => $_SESSION['cl_name']  ?? '',
    ];
}
function requireClientLogin(string $back = ''): void {
    if (!isClientLoggedIn()) {
        $url = APP_URL . '/Vente_cashier/Client/login.php';
        if ($back) $url .= '?back=' . urlencode($back);
        header('Location: ' . $url); exit;
    }
}
function clientLogout(): void {
    clientSession();
    unset($_SESSION['cl_id'], $_SESSION['cl_phone'], $_SESSION['cl_name']);
    header('Location: ' . APP_URL . '/Vente_cashier/Client/client.php'); exit;
}
function clUrl(string $page, array $p = []): string {
    $base = APP_URL . '/Vente_cashier/Client/' . $page;
    return $p ? $base . '?' . http_build_query($p) : $base;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmtXAF($v): string { return number_format((float)$v,0,',',' ') . ' XAF'; }

/* ── Ensure new tables exist (safe to call on every page load) ── */
function ensureClientTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        client_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name     VARCHAR(255) NOT NULL,
        phone         VARCHAR(30)  NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        qr_token      VARCHAR(100) NOT NULL,
        account_status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id),
        UNIQUE KEY uq_client_phone (phone),
        UNIQUE KEY uq_client_qr   (qr_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_receipt_actions (
        action_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id    INT UNSIGNED DEFAULT NULL,
        client_phone VARCHAR(30)  NOT NULL,
        receipt_id   INT UNSIGNED NOT NULL,
        business_id  INT UNSIGNED NOT NULL,
        is_saved     TINYINT(1)   NOT NULL DEFAULT 0,
        is_hidden    TINYINT(1)   NOT NULL DEFAULT 0,
        is_reported  TINYINT(1)   NOT NULL DEFAULT 0,
        report_reason TEXT        DEFAULT NULL,
        category     ENUM('food','clothes','pharmacy','electronics',
                          'beauty','transport','restaurant','other') DEFAULT 'other',
        is_favorite_business TINYINT(1) NOT NULL DEFAULT 0,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (action_id),
        UNIQUE KEY uq_cl_receipt (client_phone, receipt_id),
        KEY idx_cra_phone   (client_phone),
        KEY idx_cra_receipt (receipt_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* Add public_token to receipts if column not yet present */
    try { $pdo->exec("ALTER TABLE receipts ADD COLUMN public_token VARCHAR(80) DEFAULT NULL"); } catch(Throwable $e){}
    try { $pdo->exec("ALTER TABLE receipts ADD UNIQUE KEY uq_receipt_token (public_token)"); } catch(Throwable $e){}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS receipt_settings (
        setting_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id INT UNSIGNED NOT NULL,
        brand_name  VARCHAR(255) DEFAULT NULL,
        logo_url    VARCHAR(500) DEFAULT NULL,
        brand_color VARCHAR(20)  NOT NULL DEFAULT '#0B1F3A',
        return_policy TEXT DEFAULT NULL,
        footer_message TEXT DEFAULT NULL,
        show_cashier TINYINT(1) NOT NULL DEFAULT 1,
        show_client_phone TINYINT(1) NOT NULL DEFAULT 1,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (setting_id),
        UNIQUE KEY uq_rs_business (business_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e){}
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS receipts (
        receipt_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
        business_id     INT UNSIGNED NOT NULL,
        transaction_id  INT UNSIGNED NOT NULL,
        receipt_number  VARCHAR(80)  NOT NULL,
        public_token    VARCHAR(80)  DEFAULT NULL,
        client_name     VARCHAR(255) DEFAULT NULL,
        client_phone    VARCHAR(30)  DEFAULT NULL,
        cashier_id      INT UNSIGNED DEFAULT NULL,
        cashier_name    VARCHAR(255) DEFAULT NULL,
        total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        receipt_snapshot JSON         DEFAULT NULL,
        created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (receipt_id),
        UNIQUE KEY uq_receipt_transaction (transaction_id),
        UNIQUE KEY uq_receipt_token (public_token),
        KEY idx_receipt_biz_phone (business_id, client_phone),
        KEY idx_receipt_number    (receipt_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e){}
}

/* ── Category metadata ── */
const CAT_ICONS = [
    'food'        => ['icon'=>'🍔','fr'=>'Alimentation','en'=>'Food'],
    'clothes'     => ['icon'=>'👗','fr'=>'Vêtements','en'=>'Clothes'],
    'pharmacy'    => ['icon'=>'💊','fr'=>'Pharmacie','en'=>'Pharmacy'],
    'electronics' => ['icon'=>'📱','fr'=>'Électronique','en'=>'Electronics'],
    'beauty'      => ['icon'=>'💄','fr'=>'Beauté','en'=>'Beauty'],
    'transport'   => ['icon'=>'🚗','fr'=>'Transport','en'=>'Transport'],
    'restaurant'  => ['icon'=>'🍽️','fr'=>'Restaurant','en'=>'Restaurant'],
    'other'       => ['icon'=>'🛍️','fr'=>'Autre','en'=>'Other'],
];
function warrantyCategories(): array { return ['electronics','pharmacy']; }

/* ── Build receipt snapshot from raw tables (fallback) ── */
function buildReceiptSnapshot(PDO $pdo, int $bizId, int $transId): ?array {
    $st = $pdo->prepare("SELECT tc.*, u.full_name AS cashier_name
        FROM transactions_caisse tc LEFT JOIN users u ON u.user_id=tc.caissier_id
        WHERE tc.transaction_id=? AND tc.business_id=? LIMIT 1");
    $st->execute([$transId,$bizId]); $tx = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tx) return null;
    $items = $pdo->prepare("SELECT * FROM items_transaction WHERE transaction_id=? ORDER BY item_id");
    $items->execute([$transId]); $items = $items->fetchAll(PDO::FETCH_ASSOC);
    $pays = $pdo->prepare("SELECT * FROM paiements_mixtes WHERE transaction_id=? ORDER BY paiement_id");
    $pays->execute([$transId]); $pays = $pays->fetchAll(PDO::FETCH_ASSOC);
    $biz = $pdo->prepare("SELECT b.*,rs.brand_name,rs.logo_url AS receipt_logo,rs.brand_color,
        rs.return_policy,rs.footer_message,rs.show_cashier,rs.show_client_phone
        FROM businesses b LEFT JOIN receipt_settings rs ON rs.business_id=b.business_id
        WHERE b.business_id=? LIMIT 1");
    $biz->execute([$bizId]); $b = $biz->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['transaction'=>$tx,'items'=>$items,'payments'=>$pays,
        'business'=>[
            'brand_name'=>$b['brand_name']??$b['business_name']??'Business',
            'phone'=>$b['phone']??'','email'=>$b['email']??'',
            'city'=>$b['city']??'','address'=>$b['address']??'',
            'logo_url'=>$b['receipt_logo']??$b['logo_url']??'',
            'brand_color'=>$b['brand_color']??'#0B1F3A',
            'return_policy'=>$b['return_policy']??'',
            'footer_message'=>$b['footer_message']??'Merci pour votre achat.',
            'show_cashier'=>(int)($b['show_cashier']??1),
            'show_client_phone'=>(int)($b['show_client_phone']??1),
        ]];
}