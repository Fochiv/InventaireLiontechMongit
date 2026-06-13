<?php
/* login.php — Tally Client Login */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo=getDB(); ensureClientTables($pdo);
if(isClientLoggedIn()){ header('Location: '.clUrl('dashboard.php')); exit; }
$LOGO = APP_URL.'/Image/TALLYLOGO.png';
$err  = ''; $back = trim($_GET['back'] ?? '');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $phone = preg_replace('/[^\d\+]/','',trim($_POST['phone']??''));
    $pass  = trim($_POST['password']??'');
    if(!$phone||!$pass){ $err='Remplissez tous les champs / Fill all fields'; }
    else {
        $s=$pdo->prepare("SELECT * FROM clients WHERE phone=? AND account_status='active' LIMIT 1");
        $s->execute([$phone]); $cl=$s->fetch(PDO::FETCH_ASSOC);
        if($cl && password_verify($pass,$cl['password_hash'])){
            $_SESSION['cl_id']=$cl['client_id']; $_SESSION['cl_phone']=$cl['phone']; $_SESSION['cl_name']=$cl['full_name'];
            header('Location: '.($back?:clUrl('dashboard.php'))); exit;
        } else { $err='Numéro ou mot de passe incorrect / Wrong phone or password'; }
    }
}
?>
<!DOCTYPE html><html lang="fr">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<title>Connexion Client — Tally</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?=$LOGO?>"/>
<link rel="stylesheet" href="client.css"/></head>
<body class="cl-body cl-auth-body">

<div class="cl-auth-wrap">
  <!-- Left brand panel -->
  <div class="cl-auth-left">
    <div class="cl-auth-left-bg"></div>
    <div class="cl-auth-brand">
      <img src="<?=$LOGO?>" alt="Tally" class="cl-auth-logo"/>
      <div class="cl-auth-brand-name">Tally</div>
      <div class="cl-auth-brand-sub" data-i="client_portal">Portail Client</div>
    </div>
    <h2 class="cl-auth-tagline" data-i="auth_tagline">Tous vos reçus, en un seul endroit.</h2>
    <ul class="cl-auth-feats">
      <li>🧾 <span data-i="feat_receipts">Historique de vos achats</span></li>
      <li>⭐ <span data-i="feat_save">Sauvegarde permanente</span></li>
      <li>🔲 <span data-i="feat_qr">QR Code personnel</span></li>
      <li>📊 <span data-i="feat_stats">Analyse des dépenses</span></li>
    </ul>
  </div>

  <!-- Right form panel -->
  <div class="cl-auth-right">
    <div class="cl-auth-top">
      <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    </div>

    <a href="client.php" class="cl-back-link">← <span data-i="back_home">Retour à l'accueil</span></a>
    <div class="cl-auth-card">
      <div class="cl-auth-logo-mobile">
        <img src="<?=$LOGO?>" alt="Tally" style="width:48px;height:48px;object-fit:contain"/>
        <span class="cl-auth-brand-mobile">Tally Client</span>
      </div>
      <h1 class="cl-auth-title" data-i="login_title">Connexion</h1>
      <p class="cl-auth-sub" data-i="login_sub">Accédez à vos reçus et votre historique</p>

      <?php if($err): ?>
      <div class="cl-auth-err">⚠️ <?=h($err)?></div>
      <?php endif; ?>

      <form method="POST">
        <?php if($back): ?><input type="hidden" name="back" value="<?=h($back)?>"/><?php endif; ?>
        <div class="cl-field">
          <label data-i="phone_label">📱 Numéro de téléphone</label>
          <input type="tel" name="phone" placeholder="+237 6XX XXX XXX" inputmode="tel" required
                 value="<?=h($_POST['phone']??'')?>"/>
        </div>
        <div class="cl-field">
          <label data-i="password_label">🔒 Mot de passe / PIN</label>
          <div class="cl-input-wrap">
            <input type="password" id="passInput" name="password" placeholder="••••••" required/>
            <button type="button" class="cl-eye-btn" onclick="togglePass('passInput')">👁</button>
          </div>
        </div>
        <button type="submit" class="cl-submit-btn" data-i="login_btn">Se connecter →</button>
      </form>

      <div class="cl-auth-links">
        <a href="../../Logininventory/forgot_password.php" data-i="forgot">Mot de passe oublié ?</a>
        <a href="register.php" data-i="no_account">Pas de compte ? Créer un compte</a>
      </div>
      <div class="cl-auth-guest">
        <span data-i="guest_label">Pas encore de compte ?</span>
        <a href="client.php" data-i="view_as_guest">Consulter sans compte →</a>
      </div>
    </div>
  </div>
</div>

<script src="i18n.js"></script>
<script src="client.js"></script>
<script>
function togglePass(id){ const i=document.getElementById(id); i.type=i.type==='password'?'text':'password'; }
</script>
</body></html>