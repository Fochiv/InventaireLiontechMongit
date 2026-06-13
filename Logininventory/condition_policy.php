
<?php
/* ============================================================
   about.php — Tally Business Manager
   Public page — no login required
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\about.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
$lang = $_GET['lang'] ?? 'fr';
$lang = in_array($lang, ['fr','en']) ? $lang : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $lang==='fr' ? 'À Propos — Tally Business Manager' : 'About Us — Tally Business Manager' ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,'Segoe UI',sans-serif;background:#F0F4F8;color:#0F172A}
    a{text-decoration:none}

    /* NAV */
    .nav{background:#0B1F3A;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
    .nav-logo{display:flex;align-items:center;gap:12px}
    .nav-logo img{width:44px;height:44px;border-radius:50%;object-fit:cover}
    .nav-logo-name{font-size:16px;font-weight:800;color:#fff}
    .nav-logo-tag{font-size:10px;color:#D4A017}
    .nav-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .nav-link{color:rgba(255,255,255,.7);font-size:13px;font-weight:500;padding:7px 14px;border-radius:8px;transition:background .15s}
    .nav-link:hover,.nav-link.active{background:rgba(255,255,255,.12);color:#fff}
    .nav-link.cta{background:#1A9E7A;color:#fff;font-weight:700}

    /* HERO */
    .hero{background:linear-gradient(135deg,#0B1F3A 0%,#133150 100%);padding:64px 32px;text-align:center}
    .hero-badge{display:inline-block;background:rgba(212,160,23,.15);border:1px solid rgba(212,160,23,.4);color:#D4A017;font-size:11px;font-weight:700;padding:5px 14px;border-radius:50px;margin-bottom:18px;text-transform:uppercase;letter-spacing:.5px}
    .hero h1{font-size:36px;font-weight:900;color:#fff;margin-bottom:14px;line-height:1.2}
    .hero h1 span{color:#D4A017}
    .hero p{font-size:15px;color:rgba(255,255,255,.7);max-width:580px;margin:0 auto;line-height:1.7}

    /* BODY */
    .wrap{max-width:820px;margin:0 auto;padding:48px 24px}
    .lang-toggle{display:flex;justify-content:center;gap:10px;margin-bottom:36px}
    .lang-btn{padding:9px 22px;border-radius:10px;font-size:13.5px;font-weight:700;border:2px solid #E5E7EB;background:#fff;color:#6B7280;cursor:pointer}
    .lang-btn.active{background:#0B1F3A;color:#fff;border-color:#0B1F3A}

    .section{margin-bottom:44px}
    .section h2{font-size:21px;font-weight:800;color:#0B1F3A;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #E5E7EB}
    .section p{font-size:14.5px;color:#374151;line-height:1.8;margin-bottom:12px}

    .mv-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:44px}
    .mv-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 12px rgba(11,31,58,.06);border:1px solid #E5E7EB}
    .mv-icon{font-size:32px;margin-bottom:12px}
    .mv-title{font-size:16px;font-weight:800;color:#0B1F3A;margin-bottom:8px}
    .mv-text{font-size:13.5px;color:#6B7280;line-height:1.7}

    .feat-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
    .feat{background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;font-size:14px;color:#0B1F3A;font-weight:500}
    .feat span{font-size:18px}

    .wa-btn{display:inline-flex;align-items:center;gap:10px;background:#25D366;color:#fff;padding:13px 24px;border-radius:12px;font-size:14px;font-weight:700;margin-top:8px}

    /* FOOTER */
    .footer{background:#0B1F3A;padding:24px 32px;text-align:center;color:rgba(255,255,255,.45);font-size:13px}
    .footer a{color:rgba(255,255,255,.55);margin:0 10px}
    .footer a:hover{color:#fff}
    .footer-links{margin-top:10px}

    @media(max-width:600px){.mv-grid,.feat-grid{grid-template-columns:1fr}.hero h1{font-size:26px}.nav{padding:12px 16px}}
  </style>
</head>
<body>

<nav class="nav">
  <a class="nav-logo" href="<?= APP_URL ?>/Logininventory/login.php">
    <img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally" onerror="this.style.display='none'"/>
    <div>
      <div class="sb-logo-name">Tally</div>
      <div class="nav-logo-tag">Business Manager</div>
    </div>
  </a>
  <div class="nav-links">
    <a class="nav-link" href="condition_policy.php?lang=<?= $lang ?>"><?= $lang==='fr'?'À Propos':'About Us' ?></a>
    <a class="nav-link" href="politique_copyright.php?lang=<?= $lang ?>"><?= $lang==='fr'?'Politique':'Policy' ?></a>
    <a class="nav-link cta" href="login.php"><?= $lang==='fr'?'Se connecter':'Login' ?></a>
</div>
</nav>

<div class="hero">
  <div class="hero-badge"><span class="icon-brand">T</span> <?= $lang==='fr'?'Gestion d\'entreprise africaine':'African Business Management' ?></div>
  <h1><?= $lang==='fr' ? 'À Propos de <span>LionTech</span>' : 'About <span>LionTech</span>' ?></h1>
  <p><?= $lang==='fr'
    ? 'Une plateforme complète de gestion d\'entreprise conçue pour les réalités des entreprises africaines.'
    : 'A complete business management platform built for the realities of African businesses.' ?></p>
</div>

<div class="wrap">

  <div class="lang-toggle">
    <a class="lang-btn <?= $lang==='fr'?'active':'' ?>" href="?lang=fr">🇫🇷 Français</a>
    <a class="lang-btn <?= $lang==='en'?'active':'' ?>" href="?lang=en">🇬🇧 English</a>
  </div>

  <?php if($lang==='fr'): ?>

  <div class="section">
    <h2>LionTech Business Management</h2>
    <p>LionTech Business Management est une plateforme complète de gestion d'entreprise conçue pour aider les entreprises africaines à organiser, gérer et développer leurs activités avec confiance.</p>
    <p>Notre solution regroupe la gestion des stocks, la gestion des employés, le suivi des entrées et sorties de stock, les rapports, le suivi des présences et l'administration de l'entreprise dans une seule plateforme facile à utiliser. Nous aidons les entrepreneurs à réduire le travail manuel, améliorer l'organisation et prendre de meilleures décisions grâce à des outils numériques adaptés aux réalités des entreprises africaines.</p>
    <p>Que vous possédiez une boutique, un salon de beauté, un restaurant, une pharmacie, un entrepôt ou une entreprise de services, LionTech Business Management vous offre les outils nécessaires pour gérer efficacement votre activité et vous concentrer sur votre croissance.</p>
  </div>

  <div class="mv-grid">
    <div class="mv-card"><div class="mv-icon">🎯</div><div class="mv-title">Notre Mission</div><div class="mv-text">Aider les entreprises africaines à moderniser leurs opérations grâce à des solutions numériques simples, accessibles et fiables.</div></div>
    <div class="mv-card"><div class="mv-icon">🌍</div><div class="mv-title">Notre Vision</div><div class="mv-text">Devenir un partenaire technologique de confiance qui accompagne les entreprises africaines vers la réussite dans l'économie numérique.</div></div>
  </div>

  <div class="section">
    <h2>Pourquoi choisir LionTech Business Management ?</h2>
    <div class="feat-grid">
      <div class="feat"><span><span class="icon-ok">✓</span></span> Facile à utiliser</div>
      <div class="feat"><span><span class="icon-lock"><span class="icon-lock">🔒</span></span></span> Sécurisé et fiable</div>
      <div class="feat"><span><span class="icon-box">▣</span></span> Gestion des stocks et inventaires</div>
      <div class="feat"><span><span class="icon-users">◎</span></span> Gestion des employés</div>
      <div class="feat"><span><span class="icon-chart">▦</span></span> Rapports et analyses</div>
      <div class="feat"><span><span class="icon-phone"><span class="icon-phone">☎</span></span></span> Compatible mobile</div>
      <div class="feat"><span>⏰</span> Suivi des présences GPS</div>
      <div class="feat"><span>🌍</span> Conçu pour les entreprises africaines</div>
    </div>
  </div>

  <div class="section">
    <h2>Nous contacter</h2>
    <p>Pour toute question ou demande de démonstration, contactez-nous directement sur WhatsApp :</p>
    <a class="wa-btn" href="https://wa.me/237688203095?text=Bonjour%20LionTech%2C%20je%20voudrais%20en%20savoir%20plus%20sur%20votre%20plateforme." target="_blank" rel="noopener"><span class="icon-msg">▷</span> Contacter LionTech sur WhatsApp</a>
  </div>

  <?php else: ?>

  <div class="section">
    <h2>LionTech Business Management</h2>
    <p>LionTech Business Management is a complete business management platform designed to help African businesses organize, manage, and grow their operations with confidence.</p>
    <p>Our solution brings inventory management, employee management, stock tracking, reporting, attendance monitoring, and business administration into one easy-to-use platform. We help business owners reduce manual work, improve organization, and make better decisions through digital tools built for the realities of African businesses.</p>
    <p>Whether you own a shop, salon, restaurant, pharmacy, warehouse, boutique, or service company, LionTech Business Management gives you the tools needed to manage your business efficiently and focus on growth.</p>
  </div>

  <div class="mv-grid">
    <div class="mv-card"><div class="mv-icon">🎯</div><div class="mv-title">Our Mission</div><div class="mv-text">To help African businesses modernize their operations through simple, affordable, and reliable digital solutions.</div></div>
    <div class="mv-card"><div class="mv-icon">🌍</div><div class="mv-title">Our Vision</div><div class="mv-text">To become a trusted technology partner that empowers businesses across Africa to thrive in the digital economy.</div></div>
  </div>

  <div class="section">
    <h2>Why Choose LionTech Business Management?</h2>
    <div class="feat-grid">
      <div class="feat"><span><span class="icon-ok">✓</span></span> Easy to use</div>
      <div class="feat"><span><span class="icon-lock"><span class="icon-lock">🔒</span></span></span> Secure and reliable</div>
      <div class="feat"><span><span class="icon-box">▣</span></span> Inventory and stock management</div>
      <div class="feat"><span><span class="icon-users">◎</span></span> Employee management</div>
      <div class="feat"><span><span class="icon-chart">▦</span></span> Business reports and analytics</div>
      <div class="feat"><span><span class="icon-phone"><span class="icon-phone">☎</span></span></span> Mobile-friendly design</div>
      <div class="feat"><span>⏰</span> GPS attendance tracking</div>
      <div class="feat"><span>🌍</span> Built for African businesses</div>
    </div>
  </div>

  <div class="section">
    <h2>Contact Us</h2>
    <p>For any questions or to request a demo, contact us directly on WhatsApp:</p>
    <a class="wa-btn" href="https://wa.me/237688203095?text=Hello%20LionTech%2C%20I%20would%20like%20to%20know%20more%20about%20your%20platform." target="_blank" rel="noopener"><span class="icon-msg">▷</span> Contact LionTech on WhatsApp</a>
  </div>

  <?php endif; ?>

</div>

<footer class="footer">
  <div>© <?= date('Y') ?> Tally Business Manager. <?= $lang==='fr'?'Tous droits réservés.':'All rights reserved.' ?></div>
  <div class="footer-links">
    <a href="about.php?lang=<?= $lang ?>"><?= $lang==='fr'?'À Propos':'About Us' ?></a>
    <a href="policy.php?lang=<?= $lang ?>"><?= $lang==='fr'?'Politique':'Policy & Terms' ?></a>
    <a href="login.php"><?= $lang==='fr'?'Connexion':'Login' ?></a>
    <a href="https://wa.me/237688203095" target="_blank">WhatsApp</a>
  </div>
</footer>

</body>
</html>