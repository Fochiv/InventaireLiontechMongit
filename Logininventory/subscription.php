<?php
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER, ROLE_MANAGER]);
$user = currentUser();
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Abonnement expiré — LionTech</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#FFF7ED;color:#0F172A;min-height:100vh;display:grid;place-items:center;padding:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 16px 48px rgba(11,31,58,.1);width:100%;max-width:520px;overflow:hidden}
.card-head{background:linear-gradient(135deg,#0B1F3A,#1A2F4A);padding:24px 28px;display:flex;align-items:center;justify-content:space-between}
.logo-row{display:flex;align-items:center;gap:12px}
.logo-name{font-size:16px;font-weight:800;color:#fff}
.logo-tag{font-size:11px;color:#D4A017}
.lang-btn{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer}
.lang-btn:hover{background:rgba(255,255,255,.25)}
.card-body{padding:32px 28px;text-align:center}
.expired-icon{font-size:56px;margin-bottom:16px}
.badge{display:inline-block;background:linear-gradient(135deg,#D97706,#F0C040);color:#fff;font-size:12px;font-weight:700;padding:5px 14px;border-radius:50px;margin-bottom:16px;letter-spacing:.5px}
.title{font-size:22px;font-weight:800;color:#92400E;margin-bottom:12px}
.sub{font-size:14px;color:#6B7280;line-height:1.7;margin-bottom:8px}
.name{color:#0B1F3A;font-weight:700}
.info-box{background:#FEF3C7;border:1px solid #FDE68A;border-radius:12px;padding:16px;margin:20px 0;font-size:13px;color:#92400E;line-height:1.7;text-align:left}
.info-box strong{display:block;margin-bottom:4px}
.btn{display:block;width:100%;padding:13px;border-radius:12px;font-size:14px;font-weight:800;cursor:pointer;border:none;font-family:inherit;text-decoration:none;text-align:center;margin-bottom:10px;transition:opacity .15s}
.btn:hover{opacity:.9}
.btn-whatsapp{background:#25D366;color:#fff}
.btn-email{background:#D97706;color:#fff}
.btn-logout{background:#fff;color:#0B1F3A;border:1.5px solid #E5E7EB}
.divider{border:none;border-top:1px solid #F1F5F9;margin:18px 0}
</style>
</head>
<body>
<div class="card">

  <div class="card-head">
    <div class="logo-row">
      <img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally"
           style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div>
        <div class="sb-logo-name">LionTech</div>
        <div class="logo-tag">Business Manager</div>
      </div>
    </div>
    <button class="lang-btn" id="langBtn">EN</button>
  </div>

  <div class="card-body">
    <div class="expired-icon">⏰</div>
    <div class="badge" data-i18n="badge">Abonnement expiré</div>
    <h1 class="title" data-i18n="title">Renouveler votre abonnement</h1>
    <p class="sub">
      <span data-i18n="hello">Bonjour</span>
      <strong class="name"><?= e($user['full_name']) ?></strong>,
    </p>
    <p class="sub" data-i18n="sub">
      Votre abonnement a expiré. Veuillez le renouveler pour retrouver l'accès complet à votre espace business.
    </p>

    <div class="info-box">
      <strong data-i18n="what_happens">Qu'est-ce qui se passe ?</strong>
      <span data-i18n="info_text">
        Vos données sont en sécurité et conservées. Dès que le renouvellement est confirmé par LionTech,
        vous retrouvez immédiatement l'accès à votre tableau de bord.
      </span>
    </div>

    <a href="https://wa.me/237688203095?text=Bonjour%20LionTech%20%F0%9F%91%8B%0AJe%20voudrais%20renouveler%20mon%20abonnement.%0A%0ANom%3A%20<?= urlencode($user['full_name']) ?>%0A"
       target="_blank" rel="noopener" class="btn btn-whatsapp">
      <span class="icon-msg">▷</span> <span data-i18n="btn_whatsapp">Renouveler via WhatsApp</span>
    </a>

    <a href="mailto:billing@liontech.cm?subject=Renouvellement%20abonnement%20-%20<?= urlencode($user['full_name']) ?>"
       class="btn btn-email">
      ✉️ <span data-i18n="btn_email">Contacter la facturation</span>
    </a>

    <hr class="divider">

    <a href="<?= APP_URL ?>/Logininventory/logout.php" class="btn btn-logout">
      <span class="icon-door">▭</span> <span data-i18n="btn_logout">Se déconnecter</span>
    </a>
  </div>
</div>

<script>
const T = {
  fr: {
    badge:'Abonnement expiré',
    title:'Renouveler votre abonnement',
    hello:'Bonjour',
    sub:"Votre abonnement a expiré. Veuillez le renouveler pour retrouver l'accès complet à votre espace business.",
    what_happens:'Qu\'est-ce qui se passe ?',
    info_text:"Vos données sont en sécurité et conservées. Dès que le renouvellement est confirmé par LionTech, vous retrouvez immédiatement l'accès à votre tableau de bord.",
    btn_whatsapp:'Renouveler via WhatsApp',
    btn_email:'Contacter la facturation',
    btn_logout:'Se déconnecter',
  },
  en: {
    badge:'Subscription Expired',
    title:'Renew Your Subscription',
    hello:'Hello',
    sub:'Your subscription has expired. Please renew it to regain full access to your business dashboard.',
    what_happens:'What happens now?',
    info_text:'Your data is safe and preserved. Once LionTech confirms your renewal, you immediately regain access to your dashboard.',
    btn_whatsapp:'Renew via WhatsApp',
    btn_email:'Contact Billing',
    btn_logout:'Log Out',
  }
};

let lang = localStorage.getItem('lt_lang') || 'fr';
const btn = document.getElementById('langBtn');

function applyLang() {
  const t = T[lang];
  document.documentElement.lang = lang;
  btn.textContent = lang === 'fr' ? 'EN' : 'FR';
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n;
    if (t[k] !== undefined) el.textContent = t[k];
  });
  localStorage.setItem('lt_lang', lang);
}

btn.addEventListener('click', () => { lang = lang === 'fr' ? 'en' : 'fr'; applyLang(); });
applyLang();
</script>
</body>
</html>
