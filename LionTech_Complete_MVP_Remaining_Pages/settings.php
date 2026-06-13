<?php
require_once __DIR__ . '/../Config.php';

startSecureSession();
requireRole([ROLE_BUSINESS_OWNER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS receipt_settings (
            setting_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            business_id INT UNSIGNED NOT NULL,
            brand_name VARCHAR(255) DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            brand_color VARCHAR(20) NOT NULL DEFAULT '#0B1F3A',
            return_policy TEXT DEFAULT NULL,
            footer_message TEXT DEFAULT NULL,
            show_cashier TINYINT(1) NOT NULL DEFAULT 1,
            show_client_phone TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_id),
            UNIQUE KEY uq_receipt_settings_business (business_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {}

$stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ? LIMIT 1");
$stmt->execute([$businessId]);
$business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$settings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$receiptSettings = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM receipt_settings WHERE business_id = ? LIMIT 1");
    $stmt->execute([$businessId]);
    $receiptSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName    = trim($_POST['business_name'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $address         = trim($_POST['address'] ?? '');

    $language        = trim($_POST['language'] ?? 'fr');
    $currency        = trim($_POST['currency'] ?? 'XAF');
    $gpsRadius       = (int)($_POST['gps_radius'] ?? 200);
    $requireApproval = isset($_POST['approval']) ? 1 : 0;
    $brandColor      = trim($_POST['brand_color'] ?? '#0B1F3A');

    $receiptBrandName = trim($_POST['receipt_brand_name'] ?? '');
    $receiptColor     = trim($_POST['receipt_brand_color'] ?? '#0B1F3A');
    $returnPolicy     = trim($_POST['return_policy'] ?? '');
    $footerMessage    = trim($_POST['footer_message'] ?? '');
    $showCashier      = isset($_POST['show_cashier']) ? 1 : 0;
    $showClientPhone  = isset($_POST['show_client_phone']) ? 1 : 0;

    $tvaEnabled       = isset($_POST['tva_enabled']) ? 1 : 0;
    $tvaRate          = max(0.0, min(99.99, (float)($_POST['tva_rate'] ?? 19.25)));

    $receiptLogoUrl = $receiptSettings['logo_url'] ?? ($business['logo_url'] ?? '');

    try {
        if (
            isset($_FILES['receipt_logo_file']) &&
            $_FILES['receipt_logo_file']['error'] === UPLOAD_ERR_OK
        ) {
            $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            $originalName = $_FILES['receipt_logo_file']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Format logo invalide. Utilisez PNG, JPG, WEBP ou SVG.');
            }

            if ($_FILES['receipt_logo_file']['size'] > 2 * 1024 * 1024) {
                throw new Exception('Le logo est trop lourd. Maximum 2 MB.');
            }

            $uploadDir = dirname(__DIR__) . '/uploads/receipt_logos/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newName = 'receipt_' . $businessId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newName;

            if (!move_uploaded_file($_FILES['receipt_logo_file']['tmp_name'], $targetPath)) {
                throw new Exception('Impossible de téléverser le logo.');
            }

            $receiptLogoUrl = APP_URL . '/uploads/receipt_logos/' . $newName;
        }

        if ($businessName !== '') {
            $pdo->prepare("
                UPDATE businesses
                SET business_name = ?,
                    phone = ?,
                    city = ?,
                    address = ?,
                    updated_at = NOW()
                WHERE business_id = ?
            ")->execute([
                $businessName,
                $phone,
                $city,
                $address,
                $businessId
            ]);
        }

        $pdo->prepare("
            INSERT INTO business_settings (
                business_id,
                brand_color,
                language,
                currency,
                gps_radius_meters,
                require_stock_approval,
                tva_enabled,
                tva_rate,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                brand_color = VALUES(brand_color),
                language = VALUES(language),
                currency = VALUES(currency),
                gps_radius_meters = VALUES(gps_radius_meters),
                require_stock_approval = VALUES(require_stock_approval),
                tva_enabled = VALUES(tva_enabled),
                tva_rate = VALUES(tva_rate),
                updated_at = NOW()
        ")->execute([
            $businessId,
            $brandColor,
            $language,
            $currency,
            $gpsRadius,
            $requireApproval,
            $tvaEnabled,
            $tvaRate
        ]);

        try {
            $pdo->prepare("
                INSERT INTO attendance_settings (business_id, gps_radius_meters)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    gps_radius_meters = VALUES(gps_radius_meters),
                    updated_at = NOW()
            ")->execute([$businessId, $gpsRadius]);
        } catch (Throwable $e) {}

        $pdo->prepare("
            INSERT INTO receipt_settings (
                business_id,
                brand_name,
                logo_url,
                brand_color,
                return_policy,
                footer_message,
                show_cashier,
                show_client_phone,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                brand_name = VALUES(brand_name),
                logo_url = VALUES(logo_url),
                brand_color = VALUES(brand_color),
                return_policy = VALUES(return_policy),
                footer_message = VALUES(footer_message),
                show_cashier = VALUES(show_cashier),
                show_client_phone = VALUES(show_client_phone),
                updated_at = NOW()
        ")->execute([
            $businessId,
            $receiptBrandName,
            $receiptLogoUrl,
            $receiptColor,
            $returnPolicy,
            $footerMessage,
            $showCashier,
            $showClientPhone
        ]);

        $success = 'Paramètres sauvegardés avec succès. ✅';

        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE business_id = ? LIMIT 1");
        $stmt->execute([$businessId]);
        $business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE business_id = ? LIMIT 1");
        $stmt->execute([$businessId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("SELECT * FROM receipt_settings WHERE business_id = ? LIMIT 1");
        $stmt->execute([$businessId]);
        $receiptSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    } catch (Throwable $ex) {
        $error = 'Erreur: ' . $ex->getMessage();
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) {
    $initials .= strtoupper(substr($w, 0, 1));
}
$initials = substr($initials ?: 'O', 0, 2);

$businessNameValue = $business['business_name'] ?? '';
$receiptBrandValue = $receiptSettings['brand_name'] ?? $businessNameValue;
$receiptLogoValue  = $receiptSettings['logo_url'] ?? ($business['logo_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Paramètres — LionTech</title>
<link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>

<style>
:root {
    --navy:#0B1F3A;
    --gold:#D6A437;
    --line:#E5E7EB;
    --muted:#6B7280;
    --shadow:0 16px 40px rgba(11,31,58,.08);
    --radius:18px;
}

.st-wrap { padding:24px; }

.st-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
    align-items:start;
}

.st-card {
    background:#fff;
    border:1px solid var(--line);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:26px;
}

.st-card.full { grid-column:1 / -1; }

.st-card-head {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    margin-bottom:18px;
}

.st-card-head h2 {
    margin:0;
    font-size:18px;
    color:var(--navy);
    font-weight:800;
}

.st-card-head p {
    margin:5px 0 0;
    color:var(--muted);
    font-size:13px;
    line-height:1.5;
}

.st-fields {
    display:flex;
    flex-direction:column;
    gap:14px;
}

.st-two {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

.st-label {
    font-size:12px;
    font-weight:700;
    color:var(--navy);
    display:block;
    margin-bottom:6px;
}

.st-input {
    width:100%;
    padding:11px 13px;
    border:1.5px solid var(--line);
    border-radius:11px;
    font-size:13.5px;
    font-family:inherit;
    box-sizing:border-box;
    background:#fff;
}

.st-input:focus {
    outline:none;
    border-color:var(--navy);
    box-shadow:0 0 0 3px rgba(11,31,58,.08);
}

.st-help {
    font-size:12px;
    color:var(--muted);
    margin-top:5px;
    line-height:1.5;
}

.st-check {
    display:flex;
    align-items:flex-start;
    gap:10px;
    cursor:pointer;
    font-size:13.5px;
    color:var(--navy);
    font-weight:600;
}

.st-check input {
    width:17px;
    height:17px;
    margin-top:2px;
    cursor:pointer;
}

.st-color-row {
    display:flex;
    align-items:center;
    gap:12px;
}

.st-color {
    width:68px;
    height:40px;
    border:1.5px solid var(--line);
    border-radius:11px;
    cursor:pointer;
    background:#fff;
}

.st-current-logo {
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:12px;
}

.st-current-logo img {
    max-width:120px;
    max-height:80px;
    border:1px solid #ddd;
    border-radius:10px;
    padding:5px;
    background:#fff;
}

.st-preview {
    border:1.5px dashed #CBD5E1;
    border-radius:16px;
    overflow:hidden;
    background:#fff;
    margin-top:10px;
}

.st-preview-head {
    padding:18px;
    color:#fff;
    background:var(--navy);
    display:flex;
    justify-content:space-between;
    gap:14px;
}

.st-preview-logo {
    width:44px;
    height:44px;
    border-radius:10px;
    background:#fff;
    display:grid;
    place-items:center;
    overflow:hidden;
    color:var(--navy);
    font-weight:900;
}

.st-preview-logo img {
    width:100%;
    height:100%;
    object-fit:cover;
}

.st-preview-brand {
    font-size:17px;
    font-weight:900;
}

.st-preview-sub {
    font-size:11px;
    opacity:.75;
    margin-top:3px;
}

.st-preview-num {
    text-align:right;
    font-size:12px;
    font-weight:800;
    color:#F59E0B;
}

.st-preview-body { padding:16px 18px; }

.st-preview-row {
    display:flex;
    justify-content:space-between;
    padding:8px 0;
    border-bottom:1px solid #F1F5F9;
    font-size:13px;
}

.st-preview-total {
    display:flex;
    justify-content:space-between;
    margin-top:12px;
    font-weight:900;
    color:var(--navy);
}

.st-preview-footer {
    padding:12px 18px;
    background:#F9FAFB;
    color:#6B7280;
    font-size:11px;
}

.st-alert {
    padding:13px 24px;
    font-size:13.5px;
    border-bottom:1px solid;
}

.st-alert.success {
    background:#F0FDF4;
    border-color:#86EFAC;
    color:#166534;
}

.st-alert.error {
    background:#FEF2F2;
    border-color:#FECACA;
    color:#991B1B;
}

.st-actions {
    margin-top:22px;
    display:flex;
    justify-content:flex-end;
}

.st-save {
    background:linear-gradient(135deg,var(--gold),#F3C85B);
    color:#241700;
    border:none;
    border-radius:13px;
    padding:13px 24px;
    font-size:14px;
    font-weight:800;
    cursor:pointer;
    font-family:inherit;
    box-shadow:0 10px 24px rgba(214,164,55,.24);
}

.st-lang {
    font-size:11px;
    padding:6px 13px;
    border:1.5px solid #CBD5E1;
    border-radius:8px;
    background:#fff;
    cursor:pointer;
    font-weight:800;
    color:var(--navy);
    letter-spacing:.5px;
}

@media(max-width:900px) {
    .st-grid,
    .st-two { grid-template-columns:1fr; }

    .st-wrap { padding:16px; }

    .st-card { padding:20px; }
}
</style>
</head>

<body>
<div class="od-layout">

<?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

<main class="od-main">

<header class="od-topbar">
    <button class="od-hamburger" id="od-menu-btn" aria-label="Menu">
        ☰
    </button>

    <div class="od-business-title">
        <h1 data-i18n="st_title">Paramètres Business</h1>
        <p data-i18n="st_sub">Configuration, préférences et personnalisation des reçus</p>
    </div>

    <div class="od-top-actions">
        <button class="st-lang" id="st-lang-btn" type="button" onclick="stToggleLang()">EN</button>
        <div class="od-avatar"><?= e($initials) ?></div>
    </div>
</header>

<?php if ($success): ?>
<div class="st-alert success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="st-alert error">⚠️ <?= e($error) ?></div>
<?php endif; ?>

<div class="st-wrap">
<form method="POST" enctype="multipart/form-data">

<div class="st-grid">

    <section class="st-card">
        <div class="st-card-head">
            <div>
                <h2 data-i18n="st_info_title">Informations Business</h2>
                <p data-i18n="st_info_sub">Modifier les coordonnées générales du business.</p>
            </div>
        </div>

        <div class="st-fields">
            <div>
                <label class="st-label" data-i18n="st_biz_name">Nom du business</label>
                <input class="st-input" name="business_name" value="<?= e($businessNameValue) ?>"/>
            </div>

            <div>
                <label class="st-label" data-i18n="st_phone">Téléphone</label>
                <input class="st-input" name="phone" value="<?= e($business['phone'] ?? '') ?>" placeholder="+237 6XX XXX XXX"/>
            </div>

            <div>
                <label class="st-label" data-i18n="st_city">Ville</label>
                <input class="st-input" name="city" value="<?= e($business['city'] ?? '') ?>" placeholder="Douala"/>
            </div>

            <div>
                <label class="st-label" data-i18n="st_address">Adresse</label>
                <textarea class="st-input" name="address" rows="2" placeholder="Quartier, rue..."><?= e($business['address'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="st-label" data-i18n="st_color">Couleur principale du système</label>
                <div class="st-color-row">
                    <input class="st-color" type="color" name="brand_color" value="<?= e($settings['brand_color'] ?? '#0B1F3A') ?>"/>
                    <span class="st-help" data-i18n="st_color_help">Couleur utilisée dans certaines pages du business.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="st-card">
        <div class="st-card-head">
            <div>
                <h2 data-i18n="st_pref_title">Préférences</h2>
                <p data-i18n="st_pref_sub">Langue, devise, GPS et validation stock.</p>
            </div>
        </div>

        <div class="st-fields">
            <div>
                <label class="st-label" data-i18n="st_lang_label">Langue par défaut</label>
                <select name="language" class="st-input">
                    <option value="fr" <?= ($settings['language'] ?? 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="en" <?= ($settings['language'] ?? 'fr') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>

            <div>
                <label class="st-label" data-i18n="st_currency">Devise</label>
                <select name="currency" class="st-input">
                    <option value="XAF" <?= ($settings['currency'] ?? 'XAF') === 'XAF' ? 'selected' : '' ?>>XAF / FCFA</option>
                    <option value="USD" <?= ($settings['currency'] ?? 'XAF') === 'USD' ? 'selected' : '' ?>>USD</option>
                    <option value="EUR" <?= ($settings['currency'] ?? 'XAF') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                </select>
            </div>

            <div>
                <label class="st-label" data-i18n="st_gps">Rayon GPS clock-in</label>
                <select name="gps_radius" class="st-input">
                    <option value="100" <?= ($settings['gps_radius_meters'] ?? 200) == 100 ? 'selected' : '' ?>>100 mètres</option>
                    <option value="200" <?= ($settings['gps_radius_meters'] ?? 200) == 200 ? 'selected' : '' ?>>200 mètres</option>
                    <option value="500" <?= ($settings['gps_radius_meters'] ?? 200) == 500 ? 'selected' : '' ?>>500 mètres</option>
                </select>
            </div>

            <label class="st-check">
                <input type="checkbox" name="approval" value="1" <?= ($settings['require_stock_approval'] ?? 1) ? 'checked' : '' ?>/>
                <span>
                    <span data-i18n="st_approval">Validation stock employé requise</span>
                    <div class="st-help" data-i18n="st_approval_sub">Les demandes des employés nécessitent une approbation.</div>
                </span>
            </label>
        </div>
    </section>

    <!-- ══ TVA / CAISSE SECTION ══ -->
    <section class="st-card full">
        <div class="st-card-head">
            <div>
                <h2 data-i18n="st_tva_title">🧾 TVA &amp; Caisse</h2>
                <p data-i18n="st_tva_sub">Activez la TVA pour qu'elle apparaisse automatiquement sur la caisse et les factures.</p>
            </div>
        </div>

        <div class="st-fields">

            <!-- TVA toggle -->
            <label class="st-check">
                <input type="checkbox" name="tva_enabled" id="tvaToggle" value="1"
                    <?= ($settings['tva_enabled'] ?? 0) ? 'checked' : '' ?>
                    onchange="updateTvaPreview()"/>
                <span>
                    <span data-i18n="st_tva_enabled">Activer la TVA sur les ventes</span>
                    <div class="st-help" data-i18n="st_tva_enabled_sub">
                        Une fois activée, la TVA sera calculée sur chaque vente à la caisse et affichée sur les factures.
                    </div>
                </span>
            </label>

            <!-- TVA rate -->
            <div id="tvaRateRow" style="<?= ($settings['tva_enabled'] ?? 0) ? '' : 'display:none' ?>">
                <label class="st-label" data-i18n="st_tva_rate">Taux de TVA (%)</label>
                <div style="display:flex;align-items:center;gap:10px">
                    <input class="st-input" type="number" name="tva_rate" id="tvaRateInput"
                        value="<?= htmlspecialchars((string)($settings['tva_rate'] ?? 19.25)) ?>"
                        min="0" max="99.99" step="0.01" style="max-width:140px"
                        oninput="updateTvaPreview()"/>
                    <span style="font-size:13px;color:#6B7280">%</span>
                    <span class="st-help" data-i18n="st_tva_rate_help" style="margin-left:4px">
                        Cameroun : TVA officielle = 19,25 %
                    </span>
                </div>
            </div>

            <!-- Live preview of TVA impact -->
            <div id="tvaPreviewBox" style="margin-top:8px;<?= ($settings['tva_enabled'] ?? 0) ? '' : 'display:none' ?>">
                <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:14px 16px;font-size:13px">
                    <div style="font-weight:700;color:#166534;margin-bottom:8px">✅ <span data-i18n="st_tva_preview_title">Aperçu TVA sur une vente de 10 000 XAF</span></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #D1FAE5">
                        <span data-i18n="st_tva_subtotal">Sous-total HT</span><span>10 000 XAF</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #D1FAE5">
                        <span>TVA <span id="tvaPreviewRate"><?= htmlspecialchars((string)($settings['tva_rate'] ?? 19.25)) ?></span>%</span>
                        <span id="tvaPreviewAmt">1 925 XAF</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:800;color:#166534">
                        <span data-i18n="st_tva_total">Total TTC</span>
                        <span id="tvaPreviewTotal">11 925 XAF</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ══ RECEIPT SECTION ══ -->
    <section class="st-card full">
        <div class="st-card-head">
            <div>
                <h2 data-i18n="st_receipt_title">🎨 Personnalisation du reçu</h2>
                <p data-i18n="st_receipt_sub">Configurez le reçu que les clients impriment, reçoivent ou consultent depuis l’application.</p>
            </div>
        </div>

        <div class="st-two">
            <div class="st-fields">
                <div>
                    <label class="st-label" data-i18n="st_receipt_brand">Nom affiché sur le reçu</label>
                    <input class="st-input" id="receiptBrandInput" name="receipt_brand_name"
                        value="<?= e($receiptBrandValue) ?>"
                        placeholder="Ex: Nora Beauty Shop"/>
                </div>

                <div>
                    <label class="st-label" data-i18n="st_receipt_logo">Logo du reçu</label>

                    <?php if (!empty($receiptLogoValue)): ?>
                    <div class="st-current-logo">
                        <img src="<?= e($receiptLogoValue) ?>" alt="Logo actuel">
                        <span class="st-help">Logo actuel</span>
                    </div>
                    <?php endif; ?>

                    <input
                        type="file"
                        id="receiptLogoInput"
                        name="receipt_logo_file"
                        accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml"
                        class="st-input"
                    />

                    <div class="st-help" data-i18n="st_receipt_logo_help">
                        PNG, JPG, WEBP ou SVG recommandé. Maximum 2 MB.
                    </div>
                </div>

                <div>
                    <label class="st-label" data-i18n="st_receipt_color">Couleur du reçu</label>
                    <div class="st-color-row">
                        <input class="st-color" id="receiptColorInput" type="color" name="receipt_brand_color"
                            value="<?= e($receiptSettings['brand_color'] ?? '#0B1F3A') ?>"/>
                        <span class="st-help" data-i18n="st_receipt_color_help">Une seule couleur suffit pour garder le reçu propre et professionnel.</span>
                    </div>
                </div>

                <div>
                    <label class="st-label" data-i18n="st_footer">Message en bas du reçu</label>
                    <input class="st-input" id="footerInput" name="footer_message"
                        value="<?= e($receiptSettings['footer_message'] ?? 'Merci pour votre achat.') ?>"
                        placeholder="Merci pour votre achat."/>
                </div>

                <div>
                    <label class="st-label" data-i18n="st_return">Politique de retour</label>
                    <textarea class="st-input" name="return_policy" rows="4"
                        placeholder="Ex: Retour accepté sous 7 jours avec reçu."><?= e($receiptSettings['return_policy'] ?? '') ?></textarea>
                </div>

                <label class="st-check">
                    <input type="checkbox" name="show_cashier" value="1" <?= ($receiptSettings['show_cashier'] ?? 1) ? 'checked' : '' ?>/>
                    <span data-i18n="st_show_cashier">Afficher le nom du caissier sur le reçu</span>
                </label>

                <label class="st-check">
                    <input type="checkbox" name="show_client_phone" value="1" <?= ($receiptSettings['show_client_phone'] ?? 1) ? 'checked' : '' ?>/>
                    <span data-i18n="st_show_phone">Afficher le téléphone du client sur le reçu</span>
                </label>
            </div>

            <div>
                <label class="st-label" data-i18n="st_preview">Aperçu rapide</label>
                <div class="st-preview">
                    <div class="st-preview-head" id="receiptPreviewHead">
                        <div style="display:flex;gap:12px;align-items:center">
                            <div class="st-preview-logo" id="receiptPreviewLogo">
                                <?php if (!empty($receiptLogoValue)): ?>
                                    <img src="<?= e($receiptLogoValue) ?>" alt="logo">
                                <?php else: ?>
                                    🦁
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="st-preview-brand" id="receiptPreviewBrand"><?= e($receiptBrandValue ?: 'Mon Business') ?></div>
                                <div class="st-preview-sub">LionTech Business Manager</div>
                            </div>
                        </div>
                        <div class="st-preview-num">FACTURE<br>FAC-2026-00001</div>
                    </div>
                    <div class="st-preview-body">
                        <div class="st-preview-row"><span>Produit test</span><strong>2 000 XAF</strong></div>
                        <div class="st-preview-row" id="previewTvaRow" style="<?= ($settings['tva_enabled']??0)?'':'display:none' ?>">
                            <span>TVA <?= htmlspecialchars((string)($settings['tva_rate'] ?? 19.25)) ?>%</span>
                            <strong id="previewTvaAmt"><?= ($settings['tva_enabled']??0) ? number_format(2000*($settings['tva_rate']??19.25)/100,0,',',' ').' XAF' : '0 XAF' ?></strong>
                        </div>
                        <div class="st-preview-total"><span>TOTAL</span><span>2 000 XAF</span></div>
                    </div>
                    <div class="st-preview-footer" id="receiptPreviewFooter">
                        <?= e($receiptSettings['footer_message'] ?? 'Merci pour votre achat.') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<div class="st-actions">
    <button type="submit" class="st-save" data-i18n="st_save">💾 Sauvegarder</button>
</div>

</form>
</div>

</main>
</div>

<script>
var ST_LANG = {
    fr: {
        st_title:'Paramètres Business',
        st_sub:'Configuration, préférences et personnalisation des reçus',
        st_info_title:'Informations Business',
        st_info_sub:'Modifier les coordonnées générales du business.',
        st_biz_name:'Nom du business',
        st_phone:'Téléphone',
        st_city:'Ville',
        st_address:'Adresse',
        st_color:'Couleur principale du système',
        st_color_help:'Couleur utilisée dans certaines pages du business.',
        st_pref_title:'Préférences',
        st_pref_sub:'Langue, devise, GPS et validation stock.',
        st_lang_label:'Langue par défaut',
        st_currency:'Devise',
        st_gps:'Rayon GPS clock-in',
        st_approval:'Validation stock employé requise',
        st_approval_sub:'Les demandes des employés nécessitent une approbation.',
        st_receipt_title:'🧾 Personnalisation du reçu',
        st_receipt_sub:'Configurez le reçu que les clients impriment, reçoivent ou consultent depuis l’application.',
        st_receipt_brand:'Nom affiché sur le reçu',
        st_receipt_logo:'Logo du reçu',
        st_receipt_logo_help:'PNG, JPG, WEBP ou SVG recommandé. Maximum 2 MB.',
        st_receipt_color:'Couleur du reçu',
        st_receipt_color_help:'Une seule couleur suffit pour garder le reçu propre et professionnel.',
        st_footer:'Message en bas du reçu',
        st_return:'Politique de retour',
        st_show_cashier:'Afficher le nom du caissier sur le reçu',
        st_show_phone:'Afficher le téléphone du client sur le reçu',
        st_preview:'Aperçu rapide',
        st_save:'💾 Sauvegarder',
        st_tva_title:'🧾 TVA & Caisse',
        st_tva_sub:'Activez la TVA pour qu\'elle apparaisse automatiquement sur la caisse et les factures.',
        st_tva_enabled:'Activer la TVA sur les ventes',
        st_tva_enabled_sub:'Une fois activée, la TVA sera calculée sur chaque vente à la caisse et affichée sur les factures.',
        st_tva_rate:'Taux de TVA (%)',
        st_tva_rate_help:'Cameroun : TVA officielle = 19,25 %',
        st_tva_preview_title:'Aperçu TVA sur une vente de 10 000 XAF',
        st_tva_subtotal:'Sous-total HT',
        st_tva_total:'Total TTC'
    },
    en: {
        st_title:'Business Settings',
        st_sub:'Configuration, preferences and receipt customization',
        st_info_title:'Business Information',
        st_info_sub:'Update general business details.',
        st_biz_name:'Business name',
        st_phone:'Phone',
        st_city:'City',
        st_address:'Address',
        st_color:'Main system color',
        st_color_help:'Color used on some business pages.',
        st_pref_title:'Preferences',
        st_pref_sub:'Language, currency, GPS and stock validation.',
        st_lang_label:'Default language',
        st_currency:'Currency',
        st_gps:'GPS clock-in radius',
        st_approval:'Employee stock validation required',
        st_approval_sub:'Employee requests require approval.',
        st_receipt_title:'🧾 Receipt Customization',
        st_receipt_sub:'Configure the receipt customers print, receive, or view from the app.',
        st_receipt_brand:'Name shown on receipt',
        st_receipt_logo:'Receipt logo',
        st_receipt_logo_help:'PNG, JPG, WEBP or SVG recommended. Max 2 MB.',
        st_receipt_color:'Receipt color',
        st_receipt_color_help:'One color is enough to keep the receipt clean and professional.',
        st_footer:'Footer message',
        st_return:'Return policy',
        st_show_cashier:'Show cashier name on receipt',
        st_show_phone:'Show customer phone on receipt',
        st_preview:'Quick preview',
        st_save:'💾 Save',
        st_tva_title:'🧾 TVA & Register',
        st_tva_sub:'Enable VAT so it automatically appears on the register and invoices.',
        st_tva_enabled:'Enable VAT on sales',
        st_tva_enabled_sub:'Once enabled, VAT will be calculated on each sale and displayed on invoices.',
        st_tva_rate:'VAT Rate (%)',
        st_tva_rate_help:'Cameroon: Official VAT = 19.25%',
        st_tva_preview_title:'VAT preview on a 10,000 XAF sale',
        st_tva_subtotal:'Subtotal (excl. VAT)',
        st_tva_total:'Total incl. VAT'
    }
};

var _stLang = localStorage.getItem('lt_lang') || 'fr';

function stApplyLang(lang) {
    document.querySelectorAll('[data-i18n]').forEach(function(el) {
        var key = el.getAttribute('data-i18n');
        if (ST_LANG[lang] && ST_LANG[lang][key]) {
            el.textContent = ST_LANG[lang][key];
        }
    });

    var btn = document.getElementById('st-lang-btn');
    if (btn) btn.textContent = lang === 'fr' ? 'EN' : 'FR';
}

function stToggleLang() {
    _stLang = _stLang === 'fr' ? 'en' : 'fr';
    localStorage.setItem('lt_lang', _stLang);
    stApplyLang(_stLang);
}

function updateReceiptPreview() {
    var brandInput = document.getElementById('receiptBrandInput');
    var colorInput = document.getElementById('receiptColorInput');
    var footerInput = document.getElementById('footerInput');

    var brand = brandInput ? brandInput.value.trim() : '';
    var color = colorInput ? colorInput.value : '#0B1F3A';
    var footer = footerInput ? footerInput.value.trim() : '';

    var head = document.getElementById('receiptPreviewHead');
    var brandBox = document.getElementById('receiptPreviewBrand');
    var footerBox = document.getElementById('receiptPreviewFooter');

    if (head) head.style.background = color;
    if (brandBox) brandBox.textContent = brand || 'Mon Business';
    if (footerBox) footerBox.textContent = footer || 'Merci pour votre achat.';
}

['receiptBrandInput','receiptColorInput','footerInput'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', updateReceiptPreview);
});

stApplyLang(_stLang);
updateReceiptPreview();

/* ── TVA toggle ── */
function updateTvaPreview() {
    var toggle   = document.getElementById('tvaToggle');
    var rateRow  = document.getElementById('tvaRateRow');
    var previewBox = document.getElementById('tvaPreviewBox');
    var rateInput  = document.getElementById('tvaRateInput');
    var previewRate  = document.getElementById('tvaPreviewRate');
    var previewAmt   = document.getElementById('tvaPreviewAmt');
    var previewTotal = document.getElementById('tvaPreviewTotal');
    var previewTvaRow = document.getElementById('previewTvaRow');

    var enabled = toggle && toggle.checked;
    var rate    = rateInput ? parseFloat(rateInput.value) || 19.25 : 19.25;

    /* Show/hide rate row and preview box */
    if (rateRow)   rateRow.style.display   = enabled ? '' : 'none';
    if (previewBox) previewBox.style.display = enabled ? '' : 'none';
    if (previewTvaRow) previewTvaRow.style.display = enabled ? '' : 'none';

    /* Update live preview numbers */
    if (enabled) {
        var base     = 10000;
        var tvaAmt   = Math.round(base * rate / 100);
        var total    = base + tvaAmt;
        var fmt      = function(n){ return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ') + ' XAF'; };
        if (previewRate)  previewRate.textContent  = rate;
        if (previewAmt)   previewAmt.textContent   = fmt(tvaAmt);
        if (previewTotal) previewTotal.textContent  = fmt(total);
        /* Update receipt mini-preview */
        if (document.getElementById('previewTvaRow')) {
            var tvaStr = document.querySelector('#previewTvaRow span');
            if (tvaStr) tvaStr.textContent = 'TVA ' + rate + '%';
            var tvaAmtEl = document.getElementById('previewTvaAmt');
            if (tvaAmtEl) tvaAmtEl.textContent = fmt(Math.round(2000 * rate / 100));
        }
    }
}

/* Run on page load to set initial state */
document.addEventListener('DOMContentLoaded', updateTvaPreview);
</script>

</body>
</html>