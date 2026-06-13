<?php
require_once __DIR__ . '/../Config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Submitted - Tally</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#F3F6FA;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.success-card{
    background:white;
    max-width:650px;
    width:100%;
    border-radius:20px;
    padding:40px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.logo{
    margin-bottom:20px;
}

.logo img{
    width:80px;
    height:80px;
    border-radius:50%;
    object-fit:cover;
}

.check{
    font-size:60px;
    margin-bottom:15px;
}

h1{
    color:#0B1F3A;
    margin-bottom:15px;
}

.message{
    color:#64748B;
    line-height:1.8;
    margin-bottom:30px;
}

.login-btn{
    display:inline-block;
    background:#0B1F3A;
    color:white;
    text-decoration:none;
    padding:14px 28px;
    border-radius:12px;
    font-weight:700;
}

.login-btn:hover{
    opacity:.9;
}
</style>
</head>
<body>

<div class="success-card">

    <div class="logo">
        <img
            src="<?= APP_URL ?>/Image/TALLYLOGO.png"
            alt="Tally">
    </div>

    <div class="check"><span class="icon-ok">✓</span></div>

    <h1>Business Request Submitted</h1>

   <div class="message">
    Thank you for choosing LionTech.<br>
    Your business request has been received and is currently under review by our team.<br>
    We will verify your information, review your subscription plan, and confirm the modules for your business.<br>
    <strong>Within 48 hours</strong>, LionTech will contact you via WhatsApp
    <?php if (!empty($_SESSION['business_request_owner_phone'])): ?>
    at <?= htmlspecialchars($_SESSION['business_request_owner_phone']) ?>
    <?php endif; ?>
    with your Login ID and temporary PIN to access your account.

    <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">

    Merci d'avoir choisi LionTech.<br>
    Votre demande de création de business a bien été reçue et est en cours d'examen par notre équipe.<br>
    Nous vérifierons vos informations, examinerons votre plan d'abonnement et confirmerons les modules pour votre business.<br>
    <strong>Dans les 48 heures</strong>, LionTech vous contactera via WhatsApp
    <?php if (!empty($_SESSION['business_request_owner_phone'])): ?>
    au <?= htmlspecialchars($_SESSION['business_request_owner_phone']) ?>
    <?php endif; ?>
    avec votre identifiant de connexion et votre PIN temporaire pour accéder à votre compte.
</div>
    <a class="login-btn"
       href="<?= APP_URL ?>/Logininventory/login.php">
        Back to Login
    </a>

</div>

</body>
</html>