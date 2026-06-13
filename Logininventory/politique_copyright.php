<?php
/* ============================================================
   policy.php — LionTech Business Manager
   Public page — Privacy Policy + Terms of Service
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\policy.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
$lang = $_GET['lang'] ?? 'fr';
$lang = in_array($lang, ['fr','en']) ? $lang : 'fr';
$year = date('Y');
$date = $lang==='fr' ? date('d/m/Y') : date('F j, Y');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $lang==='fr' ? 'Politique & Conditions — LionTech' : 'Policy & Terms — LionTech' ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,'Segoe UI',sans-serif;background:#F0F4F8;color:#0F172A}
    a{text-decoration:none}

    .nav{background:#0B1F3A;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
    .nav-logo{display:flex;align-items:center;gap:12px}
    .nav-logo img{width:44px;height:44px;border-radius:50%;object-fit:cover}
    .nav-logo-name{font-size:16px;font-weight:800;color:#fff}
    .nav-logo-tag{font-size:10px;color:#D4A017}
    .nav-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .nav-link{color:rgba(255,255,255,.7);font-size:13px;font-weight:500;padding:7px 14px;border-radius:8px}
    .nav-link:hover,.nav-link.active{background:rgba(255,255,255,.12);color:#fff}
    .nav-link.cta{background:#1A9E7A;color:#fff;font-weight:700}

    .hero{background:linear-gradient(135deg,#0B1F3A 0%,#133150 100%);padding:56px 32px 48px;text-align:center}
    .hero-badge{display:inline-block;background:rgba(212,160,23,.15);border:1px solid rgba(212,160,23,.4);color:#D4A017;font-size:11px;font-weight:700;padding:5px 14px;border-radius:50px;margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px}
    .hero h1{font-size:32px;font-weight:900;color:#fff;margin-bottom:10px}
    .hero h1 span{color:#D4A017}
    .hero p{font-size:13px;color:rgba(255,255,255,.55)}

    .wrap{max-width:860px;margin:0 auto;padding:48px 24px}

    .lang-toggle{display:flex;justify-content:center;gap:10px;margin-bottom:36px}
    .lang-btn{padding:9px 22px;border-radius:10px;font-size:13.5px;font-weight:700;border:2px solid #E5E7EB;background:#fff;color:#6B7280}
    .lang-btn.active{background:#0B1F3A;color:#fff;border-color:#0B1F3A}

    .toc{background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:20px 24px;margin-bottom:36px}
    .toc-title{font-size:14px;font-weight:700;color:#0B1F3A;margin-bottom:12px}
    .toc a{display:block;font-size:13px;color:#1A9E7A;padding:4px 0;border-bottom:1px solid #F1F5F9}
    .toc a:last-child{border-bottom:none}
    .toc a:hover{color:#0B1F3A}

    .section{margin-bottom:44px}
    .section h2{font-size:20px;font-weight:800;color:#0B1F3A;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #E5E7EB}
    .section p{font-size:14px;color:#374151;line-height:1.8;margin-bottom:12px}
    .section ul{padding-left:20px;margin:8px 0 12px}
    .section ul li{font-size:14px;color:#374151;line-height:1.8;margin-bottom:4px}
    .section strong{color:#0B1F3A}

    .alert-warn{background:#FEF3C7;border:1.5px solid #FDE68A;border-radius:12px;padding:16px 20px;margin-bottom:16px;font-size:14px;color:#92400E;line-height:1.7}
    .alert-info{background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:12px;padding:14px 18px;margin-bottom:14px;font-size:14px;color:#1E40AF;line-height:1.7}

    .fee-box{background:#0B1F3A;border-radius:14px;padding:20px 24px;display:flex;align-items:center;gap:16px;margin:16px 0}
    .fee-icon{font-size:32px;flex-shrink:0}
    .fee-label{font-size:13px;color:rgba(255,255,255,.7);margin-bottom:4px}
    .fee-amount{font-size:24px;font-weight:900;color:#D4A017}
    .fee-note{font-size:12px;color:rgba(255,255,255,.5);margin-top:4px}

    .wa-btn{display:inline-flex;align-items:center;gap:10px;background:#25D366;color:#fff;padding:13px 22px;border-radius:12px;font-size:14px;font-weight:700;margin-top:6px}
    .update-note{font-size:13px;color:#6B7280;margin-top:16px;line-height:1.6}

    .footer{background:#0B1F3A;padding:24px 32px;text-align:center;color:rgba(255,255,255,.45);font-size:13px}
    .footer a{color:rgba(255,255,255,.55);margin:0 10px}
    .footer a:hover{color:#fff}
    .footer-links{margin-top:10px}

    @media(max-width:600px){.hero h1{font-size:24px}.nav{padding:12px 16px}}
  </style>
</head>
<body>

<nav class="nav">
  <a class="nav-logo" href="<?= APP_URL ?>/Logininventory/login.php">
    <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" onerror="this.style.display='none'"/>
    <div>
      <div class="nav-logo-name">LionTech</div>
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
  <div class="hero-badge">📄 <?= $lang==='fr'?'Documents légaux':'Legal Documents' ?></div>
  <h1><?= $lang==='fr' ? 'Politique & <span>Conditions</span>' : 'Policy & <span>Terms</span>' ?></h1>
  <p><?= $lang==='fr' ? "Dernière mise à jour : {$date}" : "Last updated: {$date}" ?></p>
</div>

<div class="wrap">

  <div class="lang-toggle">
    <a class="lang-btn <?= $lang==='fr'?'active':'' ?>" href="?lang=fr">🇫🇷 Français</a>
    <a class="lang-btn <?= $lang==='en'?'active':'' ?>" href="?lang=en">🇬🇧 English</a>
  </div>

  <?php if($lang==='fr'): ?>

  <div class="toc">
    <div class="toc-title">📋 Table des matières</div>
    <a href="#s1">1. Données collectées</a>
    <a href="#s2">2. Utilisation des données</a>
    <a href="#s3">3. Responsabilités des parties</a>
    <a href="#s4">4. Abonnement et paiement</a>
    <a href="#s5">5. Désactivation et réactivation</a>
    <a href="#s6">6. Sécurité des données</a>
    <a href="#s7">7. Confidentialité</a>
    <a href="#s8">8. Sessions et cookies</a>
    <a href="#s9">9. Résiliation</a>
    <a href="#s10">10. Vos droits</a>
    <a href="#s11">11. Nous contacter</a>
  </div>

  <div class="section" id="s1">
    <h2>📥 1. Données collectées</h2>
    <p>LionTech Business Manager collecte les données suivantes pour faire fonctionner la plateforme :</p>
    <ul>
      <li><strong>Informations du business :</strong> nom, type, ville, adresse, téléphone, statut.</li>
      <li><strong>Informations des utilisateurs :</strong> nom complet, téléphone, identifiant, rôle.</li>
      <li><strong>Données de connexion :</strong> historique des connexions, adresse IP, tentatives échouées.</li>
      <li><strong>Données de présence :</strong> heures de clock in/out, coordonnées GPS au pointage.</li>
      <li><strong>Données d'inventaire :</strong> produits, stocks, mouvements de stock.</li>
      <li><strong>Données de paiement :</strong> montant, méthode, référence de transaction, preuve de paiement.</li>
      <li><strong>Questions de sécurité :</strong> réponses hashées — jamais stockées en clair.</li>
    </ul>
    <div class="alert-info">ℹ️ Nous ne collectons <strong>jamais</strong> de numéro de carte bancaire, de code PIN en clair, ni d'informations financières sensibles.</div>
  </div>

  <div class="section" id="s2">
    <h2>⚙️ 2. Utilisation des données</h2>
    <p>Les données collectées sont utilisées uniquement pour :</p>
    <ul>
      <li>Faire fonctionner et améliorer la plateforme LionTech Business Manager.</li>
      <li>Permettre aux propriétaires de gérer leurs employés, stocks et opérations.</li>
      <li>Envoyer des notifications importantes (expiration d'abonnement, alertes de stock).</li>
      <li>Valider les paiements soumis et mettre à jour les abonnements.</li>
      <li>Assurer la sécurité des comptes contre les tentatives de connexion anormales.</li>
      <li>Générer des rapports et analyses pour le propriétaire du business.</li>
    </ul>
    <p>Nous <strong>ne vendons, ne louons et ne partageons jamais</strong> vos données avec des tiers à des fins commerciales.</p>
  </div>

  <div class="section" id="s3">
    <h2>👥 3. Responsabilités des parties</h2>
    <p><strong>LionTech</strong> est responsable de la disponibilité de la plateforme, de la sécurité des données, de la validation des paiements et du support technique via WhatsApp.</p>
    <p><strong>Le propriétaire du business</strong> est l'unique client contractuel de LionTech. C'est lui qui crée et gère tous ses employés sur la plateforme. Il est responsable de l'utilisation de la plateforme par ses employés, du paiement de l'abonnement en temps voulu et de la confidentialité des identifiants de connexion.</p>
    <div class="alert-warn">⚠️ <strong>Important :</strong> LionTech ne tient pas de contrat direct avec les employés ou managers. Le propriétaire du business est l'unique responsable contractuel vis-à-vis de LionTech.</div>
  </div>

  <div class="section" id="s4">
    <h2>💳 4. Abonnement et paiement</h2>
    <p>LionTech Business Manager propose les formules d'abonnement suivantes :</p>
    <ul>
      <li><strong>Mensuel :</strong> facturation chaque mois, renouvelable chaque mois.</li>
      <li><strong>Trimestriel :</strong> facturation tous les 3 mois avec tarif avantageux.</li>
      <li><strong>Annuel :</strong> facturation une fois par an avec la meilleure réduction.</li>
    </ul>
    <p><strong>Méthodes de paiement acceptées :</strong> 🟠 Orange Money · 🟡 MTN Mobile Money · 🏦 Virement bancaire · 💵 Espèces (auprès d'un agent LionTech).</p>
    <p>Tout paiement soumis est <strong>en attente de validation</strong> par l'équipe LionTech avant activation. LionTech se réserve le droit de rejeter tout paiement dont la preuve semble frauduleuse ou incorrecte.</p>
    <div class="alert-info">ℹ️ Les numéros de transaction sont vérifiés manuellement avant toute activation. Une référence déjà utilisée est automatiquement bloquée par le système.</div>
  </div>

  <div class="section" id="s5">
    <h2>🔒 5. Désactivation et réactivation</h2>
    <div class="alert-warn">
      ⚠️ <strong>Clause importante — Non-paiement :</strong><br/><br/>
      Tout business dont l'abonnement expire et qui ne renouvelle pas dans un délai de <strong>30 jours</strong> sans aucune communication avec LionTech sera automatiquement désactivé.<br/><br/>
      Si vous rencontrez des difficultés de paiement, contactez LionTech <strong>avant</strong> l'expiration via WhatsApp. Un business en communication active ne sera pas désactivé sans accord préalable.
    </div>
    <p>En cas de désactivation : le propriétaire et tous ses employés perdent l'accès. Les données sont conservées <strong>90 jours</strong> après désactivation. Au-delà, LionTech se réserve le droit de supprimer les données.</p>
    <p><strong>Frais de réactivation :</strong></p>
    <div class="fee-box">
      <div class="fee-icon">💰</div>
      <div>
        <div class="fee-label">Frais de réactivation après désactivation pour non-paiement</div>
        <div class="fee-amount">10 000 — 25 000 XAF</div>
        <div class="fee-note">En plus de l'abonnement dû. Montant selon la durée d'inactivité et le dossier.</div>
      </div>
    </div>
  </div>

  <div class="section" id="s6">
    <h2>🔐 6. Sécurité des données</h2>
    <ul>
      <li>Tous les mots de passe et PIN sont hashés avec <strong>bcrypt</strong> — jamais stockés en clair.</li>
      <li>Les réponses aux questions de sécurité sont hashées de manière irréversible.</li>
      <li>Sessions sécurisées avec régénération d'identifiant après connexion.</li>
      <li>Protection contre les tentatives de connexion répétées (brute-force).</li>
      <li>Verrouillage automatique après 3 tentatives incorrectes aux questions de sécurité.</li>
      <li>Données GPS utilisées uniquement pour le pointage de présence.</li>
    </ul>
  </div>

  <div class="section" id="s7">
    <h2>🛡️ 7. Confidentialité</h2>
    <p>LionTech s'engage à ne jamais vendre, louer ou céder vos données à des tiers. Les données des employés sont visibles uniquement par le propriétaire du business et les managers autorisés. LionTech peut accéder aux données à des fins de support technique uniquement, sur demande du propriétaire.</p>
  </div>

  <div class="section" id="s8">
    <h2>🍪 8. Sessions et cookies</h2>
    <p>LionTech utilise des <strong>sessions PHP</strong> pour maintenir votre connexion. Aucun cookie de traçage publicitaire tiers n'est utilisé. Les sessions expirent après <strong>8 heures</strong> d'inactivité. La déconnexion détruit immédiatement la session.</p>
  </div>

  <div class="section" id="s9">
    <h2>🚪 9. Résiliation</h2>
    <p>Le propriétaire peut résilier son abonnement à tout moment via WhatsApp. LionTech se réserve le droit de résilier un compte en cas de non-paiement prolongé, d'utilisation frauduleuse ou de soumission de preuves de paiement falsifiées. En cas de résiliation pour faute, aucun remboursement n'est accordé.</p>
  </div>

  <div class="section" id="s10">
    <h2>✅ 10. Vos droits</h2>
    <p>Vous avez le droit d'accéder à vos données, de les corriger, de demander leur suppression après résiliation et de contacter LionTech pour toute question. Contactez-nous via WhatsApp au <strong>+237 688 20 30 95</strong>.</p>
  </div>

  <div class="section" id="s11">
    <h2>📞 11. Nous contacter</h2>
    <a class="wa-btn" href="https://wa.me/237688203095?text=Bonjour%20LionTech%2C%20j'ai%20une%20question%20concernant%20votre%20politique." target="_blank" rel="noopener">💬 Contacter LionTech sur WhatsApp</a>
    <p class="update-note">Cette politique est effective à compter du <?= $date ?>. LionTech se réserve le droit de la modifier à tout moment.</p>
  </div>

  <?php else: ?>

  <div class="toc">
    <div class="toc-title">📋 Table of Contents</div>
    <a href="#s1">1. Data We Collect</a>
    <a href="#s2">2. How We Use Data</a>
    <a href="#s3">3. Responsibilities</a>
    <a href="#s4">4. Subscription & Payment</a>
    <a href="#s5">5. Deactivation & Reactivation</a>
    <a href="#s6">6. Data Security</a>
    <a href="#s7">7. Privacy</a>
    <a href="#s8">8. Sessions & Cookies</a>
    <a href="#s9">9. Termination</a>
    <a href="#s10">10. Your Rights</a>
    <a href="#s11">11. Contact Us</a>
  </div>

  <div class="section" id="s1">
    <h2>📥 1. Data We Collect</h2>
    <ul>
      <li><strong>Business information:</strong> name, type, city, address, phone number, status.</li>
      <li><strong>User information:</strong> full name, phone number, login ID, role.</li>
      <li><strong>Login data:</strong> login history, IP address, failed login attempts.</li>
      <li><strong>Attendance data:</strong> clock-in/out times, GPS coordinates at check-in.</li>
      <li><strong>Inventory data:</strong> products, stock levels, stock movements.</li>
      <li><strong>Payment data:</strong> amount, method, transaction reference, proof of payment.</li>
      <li><strong>Security questions:</strong> hashed answers only — never stored in plain text.</li>
    </ul>
    <div class="alert-info">ℹ️ We <strong>never</strong> collect bank card numbers, plain-text PINs, or sensitive financial credentials.</div>
  </div>

  <div class="section" id="s2">
    <h2>⚙️ 2. How We Use Data</h2>
    <ul>
      <li>Operate and improve the LionTech Business Manager platform.</li>
      <li>Allow business owners to manage employees, inventory, and operations.</li>
      <li>Send important notifications (subscription expiry, stock alerts).</li>
      <li>Validate submitted payments and update subscription status.</li>
      <li>Ensure account security against abnormal login attempts.</li>
      <li>Generate reports and analytics for business owners.</li>
    </ul>
    <p>We <strong>never sell, rent, or share</strong> your data with third parties for commercial purposes.</p>
  </div>

  <div class="section" id="s3">
    <h2>👥 3. Responsibilities</h2>
    <p><strong>LionTech</strong> is responsible for platform availability, data security, payment validation, and WhatsApp technical support.</p>
    <p><strong>The business owner</strong> is the sole contractual client of LionTech. They create and manage all employees on the platform and are responsible for all platform use by their employees, timely subscription payments, and employee credential confidentiality.</p>
    <div class="alert-warn">⚠️ <strong>Important:</strong> LionTech does not hold a direct contract with employees or managers. The business owner is the sole contractual party with LionTech.</div>
  </div>

  <div class="section" id="s4">
    <h2>💳 4. Subscription & Payment</h2>
    <ul>
      <li><strong>Monthly:</strong> billed each month, renewable monthly.</li>
      <li><strong>Quarterly:</strong> billed every 3 months at a discounted rate.</li>
      <li><strong>Annual:</strong> billed once per year with the best discount.</li>
    </ul>
    <p><strong>Accepted methods:</strong> 🟠 Orange Money · 🟡 MTN Mobile Money · 🏦 Bank transfer · 💵 Cash (LionTech agent).</p>
    <p>All submitted payments are <strong>pending validation</strong> before activation. LionTech reserves the right to reject any payment whose proof appears fraudulent or incorrect.</p>
    <div class="alert-info">ℹ️ Transaction references are manually verified before any activation. A previously used reference is automatically blocked by the system.</div>
  </div>

  <div class="section" id="s5">
    <h2>🔒 5. Deactivation & Reactivation</h2>
    <div class="alert-warn">
      ⚠️ <strong>Important Clause — Non-Payment:</strong><br/><br/>
      Any business whose subscription expires and does not renew within <strong>30 days</strong> without any communication with LionTech will be automatically deactivated.<br/><br/>
      If you are experiencing payment difficulties, contact LionTech <strong>before</strong> expiration via WhatsApp. A business maintaining active communication will not be deactivated without prior agreement.
    </div>
    <p>Upon deactivation: the owner and all employees lose access. Data is retained for <strong>90 days</strong>. Beyond that, LionTech reserves the right to delete the data.</p>
    <div class="fee-box">
      <div class="fee-icon">💰</div>
      <div>
        <div class="fee-label">Reactivation fee after deactivation for non-payment</div>
        <div class="fee-amount">10,000 — 25,000 XAF</div>
        <div class="fee-note">In addition to subscription due. Amount based on inactivity period and history.</div>
      </div>
    </div>
  </div>

  <div class="section" id="s6">
    <h2>🔐 6. Data Security</h2>
    <ul>
      <li>All passwords and PINs are hashed with <strong>bcrypt</strong> — never stored in plain text.</li>
      <li>Security question answers are irreversibly hashed.</li>
      <li>Sessions secured with ID regeneration after login.</li>
      <li>Protection against brute-force login attempts.</li>
      <li>Automatic account lockout after 3 incorrect security question attempts.</li>
      <li>GPS location data used only for attendance check-in.</li>
    </ul>
  </div>

  <div class="section" id="s7">
    <h2>🛡️ 7. Privacy</h2>
    <p>LionTech never sells, rents, or transfers your data to third parties. Employee data is visible only to the business owner and authorized managers. LionTech may access data for technical support purposes only, upon the owner's request.</p>
  </div>

  <div class="section" id="s8">
    <h2>🍪 8. Sessions & Cookies</h2>
    <p>LionTech uses <strong>PHP sessions</strong> to maintain your login. No third-party tracking cookies are used. Sessions expire after <strong>8 hours</strong> of inactivity. Logout immediately destroys the session.</p>
  </div>

  <div class="section" id="s9">
    <h2>🚪 9. Termination</h2>
    <p>The owner may cancel at any time via WhatsApp. LionTech reserves the right to terminate an account for extended non-payment, fraudulent use, or falsified payment proofs. No refund is granted for termination due to breach.</p>
  </div>

  <div class="section" id="s10">
    <h2>✅ 10. Your Rights</h2>
    <p>You have the right to access, correct, and request deletion of your data. Contact us via WhatsApp at <strong>+237 688 20 30 95</strong>.</p>
  </div>

  <div class="section" id="s11">
    <h2>📞 11. Contact Us</h2>
    <a class="wa-btn" href="https://wa.me/237688203095?text=Hello%20LionTech%2C%20I%20have%20a%20question%20about%20your%20policy." target="_blank" rel="noopener">💬 Contact LionTech on WhatsApp</a>
    <p class="update-note">This policy is effective as of <?= $date ?>. LionTech reserves the right to modify it at any time.</p>
  </div>

  <?php endif; ?>

</div>

<footer class="footer">
  <div>© <?= $year ?> LionTech Business Manager. <?= $lang==='fr'?'Tous droits réservés.':'All rights reserved.' ?></div>
  <div class="footer-links">
    <a href="about.php?lang=<?= $lang ?>"><?= $lang==='fr'?'À Propos':'About Us' ?></a>
    <a href="policy.php?lang=<?= $lang ?>"><?= $lang==='fr'?'Politique':'Policy' ?></a>
    <a href="login.php"><?= $lang==='fr'?'Connexion':'Login' ?></a>
    <a href="https://wa.me/237688203095" target="_blank">WhatsApp</a>
  </div>
</footer>

</body>
</html>