<?php
/* client.php — Tally Client Portal Landing
   Path: Vente_cashier/Client/client.php */
require_once __DIR__ . '/config_client.php';
clientSession();
$pdo = getDB(); ensureClientTables($pdo);
$loggedIn = isClientLoggedIn();
$client   = $loggedIn ? currentClient() : [];
$LOGO     = APP_URL . '/Image/TALLYLOGO.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<meta name="description" content="Tally Client — Consultez vos reçus et factures."/>
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-title" content="Tally Client"/>
<title>Tally Client — Mes Reçus</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?= $LOGO ?>"/>
<link rel="apple-touch-icon" href="<?= $LOGO ?>"/>
<link rel="stylesheet" href="client.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body class="cl-body">

<!-- NAV -->
<nav class="cl-nav">
  <a href="client.php" class="cl-nav-brand">
    <img src="<?= $LOGO ?>" alt="Tally" class="cl-nav-logo"/>
    <div>
      <div class="cl-nav-name">Tally</div>
      <div class="cl-nav-sub" data-i="client_portal">Portail Client</div>
    </div>
  </a>
  <div class="cl-nav-right">
    <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    <?php if($loggedIn): ?>
      <a href="dashboard.php" class="cl-nav-link" data-i="my_receipts">Mes reçus</a>
      <a href="profile.php" class="cl-nav-link" data-i="profile">Profil</a>
      <a href="logout.php" class="cl-nav-outline-btn" data-i="logout">Déconnexion</a>
    <?php else: ?>
      <a href="login.php" class="cl-nav-link" data-i="login">Connexion</a>
      <a href="register.php" class="cl-nav-fill-btn" data-i="create_account">Créer un compte</a>
    <?php endif; ?>
  </div>
</nav>

<!-- HERO -->
<section class="cl-hero">
  <div class="cl-hero-bg"></div>
  <div class="cl-blob cl-blob-1"></div>
  <div class="cl-blob cl-blob-2"></div>

  <div class="cl-hero-inner">
    <div class="cl-hero-badge">
      <span><span class="icon-receipt">▤</span></span><span data-i="hero_badge">Portail Clients · Tally Business Manager</span>
    </div>
    <h1 class="cl-hero-title" data-i="hero_title">
      Retrouvez tous vos reçus en un instant
    </h1>
    <p class="cl-hero-sub" data-i="hero_sub">
      Entrez votre numéro et accédez immédiatement à vos factures. Aucun compte requis.
    </p>

    <!-- Phone lookup -->
    <div class="cl-lookup-card">
      <div class="cl-lookup-label">
        <span><span class="icon-phone"><span class="icon-phone">☎</span></span></span>
        <span data-i="phone_label">Mon numéro de téléphone</span>
      </div>
      <div class="cl-lookup-row">
        <input type="tel" id="phoneInput" class="cl-lookup-input"
               placeholder="+237 6XX XXX XXX" inputmode="tel"
               value="<?= h($_GET['phone'] ?? '') ?>"
               onkeydown="if(event.key==='Enter') goReceipts()"/>
        <button class="cl-lookup-btn" onclick="goReceipts()">
          <span data-i="view_btn">Voir mes reçus</span> →
        </button>
      </div>
      <div class="cl-lookup-hint" data-i="no_account_needed">
        Aucun compte requis pour consulter · No account required to view
      </div>
    </div>

    <!-- CTA buttons -->
    <div class="cl-hero-btns">
      <?php if(!$loggedIn): ?>
      <a href="register.php" class="cl-btn-primary" data-i="create_account">✨ Créer un compte</a>
      <a href="login.php"    class="cl-btn-pink"     data-i="login">Se connecter</a>
      <?php else: ?>
      <a href="dashboard.php" class="cl-btn-primary" data-i="my_dashboard"><span class="icon-chart">▦</span> Mon tableau de bord</a>
      <a href="profile.php"   class="cl-btn-outline"  data-i="my_qr"><span class="icon-sq">▪</span> Mon QR Code</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Feature chips -->
  <div class="cl-chips">
    <div class="cl-chip"><span><span class="icon-receipt">▤</span></span><span data-i="chip_receipts">Tous vos reçus</span></div>
    <div class="cl-chip"><span><span class="icon-chart">▦</span></span><span data-i="chip_stats">Dépenses mensuelles</span></div>
    <div class="cl-chip"><span><span class="icon-shield">⛉</span></span><span data-i="chip_warranty">Rappels garantie</span></div>
    <div class="cl-chip"><span><span class="icon-star">★</span></span><span data-i="chip_save">Sauvegarder</span></div>
    <div class="cl-chip cl-chip-install"><span>📲</span><span data-i="chip_pwa">Installer l'app</span></div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="cl-how">
  <h2 class="cl-how-title" data-i="how_title">Comment ça marche ?</h2>
  <div class="cl-steps">
    <div class="cl-step">
      <div class="cl-step-num">1</div>
      <div class="cl-step-icon"><span class="icon-phone"><span class="icon-phone">☎</span></span></div>
      <h3 data-i="step1_title">Entrez votre numéro</h3>
      <p data-i="step1_sub">Le numéro utilisé lors de votre achat en boutique.</p>
    </div>
    <div class="cl-step-arrow">→</div>
    <div class="cl-step">
      <div class="cl-step-num">2</div>
      <div class="cl-step-icon"><span class="icon-receipt">▤</span></div>
      <h3 data-i="step2_title">Accédez à vos reçus</h3>
      <p data-i="step2_sub">Les achats du jour apparaissent en premier.</p>
    </div>
    <div class="cl-step-arrow">→</div>
    <div class="cl-step">
      <div class="cl-step-num">3</div>
      <div class="cl-step-icon"><span class="icon-star">★</span></div>
      <h3 data-i="step3_title">Sauvegardez</h3>
      <p data-i="step3_sub">Créez un compte pour sauvegarder définitivement.</p>
    </div>
  </div>
</section>

<!-- ACCOUNT BENEFITS (if not logged in) -->
<?php if(!$loggedIn): ?>
<section class="cl-benefits">
  <h2 data-i="benefits_title">Pourquoi créer un compte ?</h2>
  <div class="cl-benefits-grid">
    <div class="cl-benefit"><div class="cl-benefit-icon"><span class="icon-star">★</span></div><h3 data-i="b1_title">Sauvegarde permanente</h3><p data-i="b1_sub">Vos reçus sont conservés indéfiniment.</p></div>
    <div class="cl-benefit"><div class="cl-benefit-icon"><span class="icon-chart">▦</span></div><h3 data-i="b2_title">Historique complet</h3><p data-i="b2_sub">Analysez vos dépenses par mois et par catégorie.</p></div>
    <div class="cl-benefit"><div class="cl-benefit-icon"><span class="icon-sq">▪</span></div><h3 data-i="b3_title">QR Code personnel</h3><p data-i="b3_sub">Le caissier scan votre QR pour lier vos reçus automatiquement.</p></div>
    <div class="cl-benefit"><div class="cl-benefit-icon">🔔</div><h3 data-i="b4_title">Rappels garantie</h3><p data-i="b4_sub">Soyez averti pour les reçus d'électronique et pharmacie.</p></div>
  </div>
  <a href="register.php" class="cl-btn-primary cl-cta-center" data-i="create_free">✨ Créer mon compte gratuit</a>
</section>
<?php endif; ?>

<footer class="cl-footer">
  <img src="<?= $LOGO ?>" alt="Tally" style="width:32px;height:32px;object-fit:contain;opacity:.6"/>
  <span>© 2026 Tally · Powered by LionTech</span>
  <a href="<?= APP_URL ?>/Logininventory/login.php" data-i="staff_login">Espace staff</a>
</footer>

<script src="i18n.js"></script>
<script src="client.js"></script>
<script>
function goReceipts(){
  const p = document.getElementById('phoneInput').value.trim();
  if(!p){ clToast(I18N[CL_LANG].phone_required); return; }
  window.location.href = 'dashboard.php?phone=' + encodeURIComponent(p);
}
/* PWA install prompt */
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault(); deferredPrompt = e;
  const chip = document.querySelector('[data-i="chip_pwa"]')?.parentElement;
  if(chip){ chip.style.cursor='pointer'; chip.onclick=()=>{ deferredPrompt.prompt(); }; }
});
</script>
</body>
</html>