<?php
/* ============================================================
   settings.php — LionTech Business Manager
   Owner only — business settings: GPS, language, currency, logo
   Path: LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/
   ============================================================ */
require_once __DIR__ . '/../../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

/* Load current settings */
$settings = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM business_settings WHERE business_id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $settings = $stmt->fetch() ?: [];
} catch (Throwable $e) {}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $phone        = trim($_POST['phone']         ?? '');
    $city         = trim($_POST['city']          ?? '');
    $address      = trim($_POST['address']       ?? '');
    $language     = trim($_POST['language']      ?? 'fr');
    $currency     = trim($_POST['currency']      ?? 'XAF');
    $gpsRadius    = (int)($_POST['gps_radius']   ?? 200);
    $requireApproval = isset($_POST['approval']) ? 1 : 0;
    $brandColor   = trim($_POST['brand_color']   ?? '#0B1F3A');

    try {
        /* Update business info */
        if ($businessName !== '') {
            $pdo->prepare('UPDATE businesses SET business_name=?, phone=?, city=?, address=?, updated_at=NOW() WHERE business_id=?')
                ->execute([$businessName, $phone, $city, $address, $businessId]);
        }

        /* Upsert business_settings */
        $pdo->prepare('INSERT INTO business_settings (business_id, brand_color, language, currency, gps_radius_meters, require_stock_approval, updated_at)
            VALUES (?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE brand_color=VALUES(brand_color), language=VALUES(language), currency=VALUES(currency), gps_radius_meters=VALUES(gps_radius_meters), require_stock_approval=VALUES(require_stock_approval), updated_at=NOW()')
            ->execute([$businessId, $brandColor, $language, $currency, $gpsRadius, $requireApproval]);

        /* Upsert attendance_settings GPS radius */
        $pdo->prepare('INSERT INTO attendance_settings (business_id, gps_radius_meters)
            VALUES (?,?)
            ON DUPLICATE KEY UPDATE gps_radius_meters=VALUES(gps_radius_meters), updated_at=NOW()')
            ->execute([$businessId, $gpsRadius]);

        $success = 'Paramètres sauvegardés avec succès. ✅';

        /* Reload */
        $stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
        $stmt->execute([$businessId]);
        $business = $stmt->fetch();
    } catch (Throwable $ex) {
        $error = 'Erreur: ' . $ex->getMessage();
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'O', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Paramètres — LionTech</title>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../../LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Paramètres Business</h1>
        <p>Configuration du business, GPS, langue et préférences</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($success): ?>
    <div style="background:#F0FDF4;border:1px solid #86EFAC;padding:12px 24px;font-size:13px;color:#166534"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 24px;font-size:13px;color:#991B1B">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <div style="padding:24px">
      <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

          <!-- Business Info -->
          <div class="od-card" style="padding:28px">
            <div class="od-card-head"><div><h2>Informations Business</h2><p>Modifier les coordonnées du business</p></div></div>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px">

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Nom du business</label>
                <input name="business_name" value="<?= e($business['business_name'] ?? '') ?>"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Téléphone</label>
                <input name="phone" value="<?= e($business['phone'] ?? '') ?>" placeholder="+237..."
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Ville</label>
                <input name="city" value="<?= e($business['city'] ?? '') ?>" placeholder="Douala"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box"/>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Adresse</label>
                <textarea name="address" rows="2" placeholder="Quartier, rue..."
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;resize:vertical;box-sizing:border-box"><?= e($business['address'] ?? '') ?></textarea>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Couleur principale</label>
                <input type="color" name="brand_color" value="<?= e($settings['brand_color'] ?? '#0B1F3A') ?>"
                  style="width:60px;height:38px;border:1.5px solid #E5E7EB;border-radius:10px;cursor:pointer"/>
              </div>

            </div>
          </div>

          <!-- Preferences -->
          <div class="od-card" style="padding:28px">
            <div class="od-card-head"><div><h2>Préférences</h2><p>Langue, devise, GPS et approbations</p></div></div>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px">

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Langue par défaut</label>
                <select name="language" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
                  <option value="fr" <?= ($settings['language']??'fr')==='fr'?'selected':'' ?>>Français</option>
                  <option value="en" <?= ($settings['language']??'fr')==='en'?'selected':'' ?>>English</option>
                </select>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Devise</label>
                <select name="currency" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
                  <option value="XAF" <?= ($settings['currency']??'XAF')==='XAF'?'selected':'' ?>>XAF / FCFA</option>
                  <option value="USD" <?= ($settings['currency']??'XAF')==='USD'?'selected':'' ?>>USD</option>
                  <option value="EUR" <?= ($settings['currency']??'XAF')==='EUR'?'selected':'' ?>>EUR</option>
                </select>
              </div>

              <div>
                <label style="font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px">Rayon GPS clock-in</label>
                <select name="gps_radius" style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit">
                  <option value="100" <?= ($settings['gps_radius_meters']??200)==100?'selected':'' ?>>100 mètres</option>
                  <option value="200" <?= ($settings['gps_radius_meters']??200)==200?'selected':'' ?>>200 mètres</option>
                  <option value="500" <?= ($settings['gps_radius_meters']??200)==500?'selected':'' ?>>500 mètres</option>
                </select>
              </div>

              <div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13.5px;color:#0B1F3A;font-weight:500">
                  <input type="checkbox" name="approval" value="1"
                    <?= ($settings['require_stock_approval']??1)?'checked':'' ?>
                    style="width:16px;height:16px;cursor:pointer"/>
                  Validation stock employé requise
                </label>
                <p style="font-size:12px;color:#6B7280;margin:4px 0 0 26px">Les demandes d'employés nécessitent une approbation avant de modifier l'inventaire.</p>
              </div>

              <button type="submit" class="od-primary"
                style="padding:13px 24px;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:6px;width:fit-content">
                💾 Sauvegarder
              </button>

            </div>
          </div>

        </div>
      </form>
    </div>
  </main>
</div>
</body>
</html>