<?php
/* register.php — Tally Client Registration */
require_once __DIR__ . '/config_client.php';
clientSession(); $pdo=getDB(); ensureClientTables($pdo);
if(isClientLoggedIn()){ header('Location: '.clUrl('dashboard.php')); exit; }
$LOGO=$APP_URL = APP_URL.'/Image/TALLYLOGO.png';
$err=''; $prePhone=trim($_GET['phone']??'');

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name  = trim($_POST['name']??'');
    $phone = preg_replace('/[^\d\+]/','',trim($_POST['phone']??''));
    $pass  = trim($_POST['password']??'');
    $pass2 = trim($_POST['password2']??'');
    if(!$name||!$phone||!$pass){ $err='Remplissez tous les champs / Fill all fields'; }
    elseif($pass!==$pass2){ $err='Les mots de passe ne correspondent pas / Passwords do not match'; }
    elseif(strlen($pass)<4){ $err='Minimum 4 caractères / Minimum 4 characters'; }
    else {
        $ck=$pdo->prepare("SELECT client_id FROM clients WHERE phone=? LIMIT 1");
        $ck->execute([$phone]); if($ck->fetch()){ $err='Ce numéro est déjà utilisé / Phone already registered'; }
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $qr   = bin2hex(random_bytes(20));
            $ins  = $pdo->prepare("INSERT INTO clients(full_name,phone,password_hash,qr_token) VALUES(?,?,?,?)");
            $ins->execute([$name,$phone,$hash,$qr]);
            $cid=(int)$pdo->lastInsertId();
            /* Link existing receipts by phone */
            try { $pdo->prepare("UPDATE client_receipt_actions SET client_id=? WHERE client_phone=? AND client_id IS NULL")->execute([$cid,$phone]); } catch(Throwable $e){}
            $_SESSION['cl_id']=$cid; $_SESSION['cl_phone']=$phone; $_SESSION['cl_name']=$name;
            header('Location: '.clUrl('dashboard.php',['phone'=>$phone,'welcome'=>1])); exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="fr">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#28744c"/>
<title>Créer un compte — LionTech</title>
<link rel="manifest" href="manifest.webmanifest"/>
<link rel="icon" type="image/png" href="<?=APP_URL?>/Image/TALLYLOGO.png"/>
<link rel="stylesheet" href="client.css"/><link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body class="cl-body cl-auth-body">

<div class="cl-auth-wrap">
  <div class="cl-auth-left">
    <div class="cl-auth-left-bg"></div>
    <div class="cl-auth-brand">
      <img src="<?=APP_URL?>/Image/TALLYLOGO.png" alt="Tally" class="cl-auth-logo"/>
      <div class="cl-auth-brand-name">Tally</div>
      <div class="cl-auth-brand-sub" data-i="client_portal">Portail Client</div>
    </div>
    <h2 class="cl-auth-tagline" data-i="register_tagline">Sauvegardez tous vos reçus gratuitement.</h2>
    <ul class="cl-auth-feats">
      <li><span class="icon-receipt">▤</span> <span data-i="feat_link">Liez vos achats existants automatiquement</span></li>
      <li><span class="icon-sq">▪</span> <span data-i="feat_qr">Générez votre QR Code personnel</span></li>
      <li><span class="icon-chart">▦</span> <span data-i="feat_stats">Suivez vos dépenses mensuelles</span></li>
      <li><span class="icon-star">★</span> <span data-i="feat_save">Sauvegarde permanente illimitée</span></li>
    </ul>
  </div>

  <div class="cl-auth-right">
    <div class="cl-auth-top">
      <button class="cl-lang-btn" id="langBtn" onclick="toggleLang()">EN</button>
    </div>
    <a href="client.php" class="cl-back-link">← <span data-i="back_home">Retour à l'accueil</span></a>
    <div class="cl-auth-card">
      <div class="cl-auth-logo-mobile">
        <img src="<?=APP_URL?>/Image/TALLYLOGO.png" alt="Tally" style="width:48px;height:48px;object-fit:contain"/>
        <span class="cl-auth-brand-mobile">Tally Client</span>
      </div>
      <h1 class="cl-auth-title" data-i="register_title">Créer un compte</h1>
      <p class="cl-auth-sub" data-i="register_sub">Gratuit · Accès immédiat à vos reçus</p>

      <?php if($err): ?><div class="cl-auth-err"><span class="icon-warn">⚠</span> <?=h($err)?></div><?php endif; ?>

      <form method="POST">
        <div class="cl-field">
          <label data-i="name_label"><span class="icon-user">◉</span> Nom complet</label>
          <input type="text" name="name" placeholder="Jean Dupont" required value="<?=h($_POST['name']??'')?>"/>
        </div>
        <div class="cl-field">
          <label data-i="phone_label"><span class="icon-phone"><span class="icon-phone">☎</span></span> Numéro de téléphone</label>
          <input type="tel" name="phone" placeholder="+237 6XX XXX XXX" inputmode="tel" required
                 value="<?=h($_POST['phone']??$prePhone)?>"/>
        </div>
        <div class="cl-field">
          <label data-i="password_label"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span> Mot de passe / PIN (min. 4 chiffres)</label>
          <div class="cl-input-wrap">
            <input type="password" id="p1" name="password" placeholder="••••••" required/>
            <button type="button" class="cl-eye-btn" onclick="togglePass('p1')">👁</button>
          </div>
        </div>
        <div class="cl-field">
          <label data-i="confirm_label"><span class="icon-lock"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span> Confirmer le mot de passe</label>
          <div class="cl-input-wrap">
            <input type="password" id="p2" name="password2" placeholder="••••••" required/>
            <button type="button" class="cl-eye-btn" onclick="togglePass('p2')">👁</button>
          </div>
        </div>
        <button type="submit" class="cl-submit-btn" data-i="register_btn">Créer mon compte →</button>
      </form>

      <div class="cl-auth-links">
        <a href="login.php" data-i="have_account">Déjà un compte ? Se connecter</a>
      </div>
      <div class="cl-auth-guest">
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