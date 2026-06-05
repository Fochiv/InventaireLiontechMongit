<?php
/* ============================================================
   settings.php — LionTech Business Manager
   Owner only — business settings: GPS, language, currency, logo
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

$settings = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM business_settings WHERE business_id = ? LIMIT 1');
    $stmt->execute([$businessId]);
    $settings = $stmt->fetch() ?: [];
} catch (Throwable $e) {}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName    = trim($_POST['business_name'] ?? '');
    $phone           = trim($_POST['phone']         ?? '');
    $city            = trim($_POST['city']           ?? '');
    $address         = trim($_POST['address']        ?? '');
    $language        = trim($_POST['language']       ?? 'fr');
    $currency        = trim($_POST['currency']       ?? 'XAF');
    $gpsRadius       = (int)($_POST['gps_radius']    ?? 200);
    $requireApproval = isset($_POST['approval']) ? 1 : 0;
    $brandColor      = trim($_POST['brand_color']    ?? '#0B1F3A');

    try {
        if ($businessName !== '') {
            $pdo->prepare('UPDATE businesses SET business_name=?, phone=?, city=?, address=?, updated_at=NOW() WHERE business_id=?')
                ->execute([$businessName, $phone, $city, $address, $businessId]);
        }

        $pdo->prepare('INSERT INTO business_settings (business_id, brand_color, language, currency, gps_radius_meters, require_stock_approval, updated_at)
            VALUES (?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE brand_color=VALUES(brand_color), language=VALUES(language), currency=VALUES(currency), gps_radius_meters=VALUES(gps_radius_meters), require_stock_approval=VALUES(require_stock_approval), updated_at=NOW()')
            ->execute([$businessId, $brandColor, $language, $currency, $gpsRadius, $requireApproval]);

        $pdo->prepare('INSERT INTO attendance_settings (business_id, gps_radius_meters)
            VALUES (?,?)
            ON DUPLICATE KEY UPDATE gps_radius_meters=VALUES(gps_radius_meters), updated_at=NOW()')
            ->execute([$businessId, $gpsRadius]);

        $success = 'Paramètres sauvegardés avec succès. ✅';

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
  <link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
  <style>
    .st-label { font-size:12px;font-weight:600;color:#0B1F3A;display:block;margin-bottom:5px }
    .st-input  { width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-family:inherit;box-sizing:border-box }
    .st-input:focus { outline:none;border-color:#0B1F3A }
  </style>
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <button class="od-hamburger" id="od-menu-btn" aria-label="Menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="od-business-title">
        <h1 data-i18n="st_title">Paramètres Business</h1>
        <p data-i18n="st_sub">Configuration, GPS, langue et préférences</p>
      </div>
      <div class="od-top-actions">
        <button id="st-lang-btn" onclick="stToggleLang()"
          style="font-size:11px;padding:5px 12px;border:1.5px solid #CBD5E1;border-radius:6px;background:#fff;cursor:pointer;font-weight:700;color:#0B1F3A;letter-spacing:.5px">EN</button>
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
            <div class="od-card-head">
              <div>
                <h2 data-i18n="st_info_title">Informations Business</h2>
                <p data-i18n="st_info_sub">Modifier les coordonnées du business</p>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px">

              <div>
                <label class="st-label" data-i18n="st_biz_name">Nom du business</label>
                <input class="st-input" name="business_name" value="<?= e($business['business_name'] ?? '') ?>"/>
              </div>

              <div>
                <label class="st-label" data-i18n="st_phone">Téléphone</label>
                <input class="st-input" name="phone" value="<?= e($business['phone'] ?? '') ?>" placeholder="+237..."/>
              </div>

              <div>
                <label class="st-label" data-i18n="st_city">Ville</label>
                <input class="st-input" name="city" value="<?= e($business['city'] ?? '') ?>" placeholder="Douala"/>
              </div>

              <div>
                <label class="st-label" data-i18n="st_address">Adresse</label>
                <textarea name="address" rows="2" class="st-input" placeholder="Quartier, rue..."><?= e($business['address'] ?? '') ?></textarea>
              </div>

              <div>
                <label class="st-label" data-i18n="st_color">Couleur principale</label>
                <input type="color" name="brand_color" value="<?= e($settings['brand_color'] ?? '#0B1F3A') ?>"
                  style="width:60px;height:38px;border:1.5px solid #E5E7EB;border-radius:10px;cursor:pointer"/>
              </div>

            </div>
          </div>

          <!-- Preferences -->
          <div class="od-card" style="padding:28px">
            <div class="od-card-head">
              <div>
                <h2 data-i18n="st_pref_title">Préférences</h2>
                <p data-i18n="st_pref_sub">Langue, devise, GPS et approbations</p>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px">

              <div>
                <label class="st-label" data-i18n="st_lang_label">Langue par défaut</label>
                <select name="language" class="st-input">
                  <option value="fr" <?= ($settings['language']??'fr')==='fr'?'selected':'' ?>>Français</option>
                  <option value="en" <?= ($settings['language']??'fr')==='en'?'selected':'' ?>>English</option>
                </select>
              </div>

              <div>
                <label class="st-label" data-i18n="st_currency">Devise</label>
                <select name="currency" class="st-input">
                  <option value="XAF" <?= ($settings['currency']??'XAF')==='XAF'?'selected':'' ?>>XAF / FCFA</option>
                  <option value="USD" <?= ($settings['currency']??'XAF')==='USD'?'selected':'' ?>>USD</option>
                  <option value="EUR" <?= ($settings['currency']??'XAF')==='EUR'?'selected':'' ?>>EUR</option>
                </select>
              </div>

              <div>
                <label class="st-label" data-i18n="st_gps">Rayon GPS clock-in</label>
                <select name="gps_radius" class="st-input">
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
                  <span data-i18n="st_approval">Validation stock employé requise</span>
                </label>
                <p style="font-size:12px;color:#6B7280;margin:4px 0 0 26px" data-i18n="st_approval_sub">Les demandes d'employés nécessitent une approbation.</p>
              </div>

              <button type="submit" class="od-primary"
                style="padding:13px 24px;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:6px;width:fit-content">
                <span data-i18n="st_save">💾 Sauvegarder</span>
              </button>

            </div>
          </div>

        </div>
      </form>
    </div>
  </main>
</div>

<script>
var ST_LANG = {
  fr: {
    st_title:'Paramètres Business', st_sub:'Configuration, GPS, langue et préférences',
    st_info_title:'Informations Business', st_info_sub:'Modifier les coordonnées du business',
    st_biz_name:'Nom du business', st_phone:'Téléphone', st_city:'Ville', st_address:'Adresse', st_color:'Couleur principale',
    st_pref_title:'Préférences', st_pref_sub:'Langue, devise, GPS et approbations',
    st_lang_label:'Langue par défaut', st_currency:'Devise', st_gps:'Rayon GPS clock-in',
    st_approval:'Validation stock employé requise',
    st_approval_sub:'Les demandes d\'employés nécessitent une approbation.',
    st_save:'💾 Sauvegarder'
  },
  en: {
    st_title:'Business Settings', st_sub:'Configuration, GPS, language and preferences',
    st_info_title:'Business Information', st_info_sub:'Update your business details',
    st_biz_name:'Business Name', st_phone:'Phone', st_city:'City', st_address:'Address', st_color:'Brand Color',
    st_pref_title:'Preferences', st_pref_sub:'Language, currency, GPS and approvals',
    st_lang_label:'Default Language', st_currency:'Currency', st_gps:'GPS clock-in radius',
    st_approval:'Employee stock validation required',
    st_approval_sub:'Employee requests require approval before modifying inventory.',
    st_save:'💾 Save'
  }
};

var _stLang = localStorage.getItem('lt_lang') || 'fr';
function stApplyLang(l) {
  document.querySelectorAll('[data-i18n]').forEach(function(el) {
    var k = el.getAttribute('data-i18n');
    if (ST_LANG[l] && ST_LANG[l][k]) el.textContent = ST_LANG[l][k];
  });
  var btn = document.getElementById('st-lang-btn');
  if (btn) btn.textContent = l === 'fr' ? 'EN' : 'FR';
}
function stToggleLang() {
  _stLang = _stLang === 'fr' ? 'en' : 'fr';
  localStorage.setItem('lt_lang', _stLang);
  stApplyLang(_stLang);
}
stApplyLang(_stLang);
</script>
</body>
</html>
