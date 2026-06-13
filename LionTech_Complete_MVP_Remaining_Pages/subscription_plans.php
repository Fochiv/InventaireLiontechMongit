<?php
require_once __DIR__ . '/../Config.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Choose a Subscription — LionTech</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}

body{
    font-family:Arial,sans-serif;
    background:#F3F6FA;
    color:#0B1F3A;
}

.plans-page{
    min-height:100vh;
    padding:35px 20px;
}

.lang-switch{
    max-width:1100px;
    margin:0 auto 20px;
    display:flex;
    justify-content:flex-end;
}

.lang-switch button{
    background:#0B1F3A;
    color:white;
    border:none;
    border-radius:10px;
    padding:10px 16px;
    font-weight:700;
    cursor:pointer;
}

.plans-header{
    max-width:1100px;
    margin:0 auto 35px;
    text-align:center;
}

.logo{
    margin:0 auto 15px;
    display:flex;
    justify-content:center;
    align-items:center;
}

.plans-header h1{
    font-size:34px;
    margin-bottom:10px;
}

.plans-header p{
    color:#64748B;
    font-size:16px;
}

.plans-grid{
    max-width:1150px;
    margin:0 auto;
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:22px;
}

.plan-card{
    background:white;
    border-radius:20px;
    padding:28px;
    box-shadow:0 10px 28px rgba(11,31,58,.12);
    border-top:8px solid;
    display:flex;
    flex-direction:column;
    min-height:520px;
}

.plan-basic{border-color:#16A34A}
.plan-standard{border-color:#2563EB;transform:scale(1.03)}
.plan-premium{border-color:#D4A017}

.plan-tag{
    width:max-content;
    padding:7px 13px;
    border-radius:999px;
    font-size:13px;
    font-weight:800;
    margin-bottom:18px;
}

.plan-basic .plan-tag{background:#DCFCE7;color:#166534}
.plan-standard .plan-tag{background:#DBEAFE;color:#1E40AF}
.plan-premium .plan-tag{background:#FEF3C7;color:#92400E}

.plan-card h2{
    font-size:25px;
    margin-bottom:8px;
}

.plan-price{
    font-size:32px;
    font-weight:900;
    margin-bottom:6px;
}

.plan-price span{
    font-size:14px;
    color:#64748B;
    font-weight:600;
}

.plan-desc{
    color:#64748B;
    font-size:14px;
    margin-bottom:20px;
    min-height:42px;
}

.plan-list{
    list-style:none;
    display:flex;
    flex-direction:column;
    gap:11px;
    margin-bottom:25px;
    flex:1;
}

.plan-list li{
    font-size:14px;
    line-height:1.4;
}

.yes{color:#166534;font-weight:700}
.no{color:#DC2626;font-weight:700}

.plan-btn{
    display:block;
    text-align:center;
    padding:14px;
    border-radius:12px;
    color:white;
    text-decoration:none;
    font-weight:800;
    transition:.2s;
}

.plan-basic .plan-btn{background:#16A34A}
.plan-standard .plan-btn{background:#2563EB}
.plan-premium .plan-btn{background:#D4A017;color:#0B1F3A}

.plan-btn:hover{
    transform:translateY(-2px);
    opacity:.92;
}

.payment-section{
    max-width:1100px;
    margin:35px auto 0;
    background:white;
    border-radius:20px;
    padding:28px;
    box-shadow:0 10px 28px rgba(11,31,58,.10);
}

.payment-section h2{
    text-align:center;
    margin-bottom:18px;
}

.payment-methods{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:12px;
}

.payment-method{
    background:#F8FAFC;
    border:1px solid #E2E8F0;
    border-radius:14px;
    padding:14px;
    text-align:center;
    font-size:13px;
    font-weight:700;
}

.note{
    max-width:850px;
    margin:25px auto 0;
    text-align:center;
    color:#64748B;
    font-size:14px;
    line-height:1.6;
}

.back-link{
    display:block;
    text-align:center;
    margin-top:25px;
    color:#1A9E7A;
    font-weight:800;
    text-decoration:none;
}

@media(max-width:1000px){
    .plans-grid{
        grid-template-columns:1fr;
        max-width:650px;
    }

    .plan-standard{
        transform:none;
    }

    .payment-methods{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:520px){
    .plans-page{
        padding:25px 14px;
    }

    .plans-header h1{
        font-size:26px;
    }

    .plan-card{
        padding:22px;
        min-height:auto;
    }

    .plan-price{
        font-size:27px;
    }

    .payment-methods{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<div class="plans-page">

    <div class="lang-switch">
        <button type="button" id="langBtn">FR</button>
    </div>

    <header class="plans-header">
        <div class="logo">
            <img
                src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"
                alt="LionTech"
                style="width:60px;height:60px;border-radius:50%;object-fit:cover;"
            />
        </div>

        <h1 data-en="Choose Your Subscription Plan" data-fr="Choisissez votre abonnement">
            Choose Your Subscription Plan
        </h1>

        <p data-en="Select the plan that best fits your business. You can upgrade at any time."
           data-fr="Choisissez le plan qui correspond à votre business. Vous pourrez évoluer plus tard.">
            Select the plan that best fits your business. You can upgrade at any time.
        </p>
    </header>

    <section class="plans-grid">

        <div class="plan-card plan-basic">
            <div class="plan-tag">🟢 BASIC</div>
            <h2>Basic</h2>
            <div class="plan-price">2,000 XAF <span data-en="/ month" data-fr="/ mois">/ month</span></div>

            <p class="plan-desc"
               data-en="For small businesses that want to manage stock simply."
               data-fr="Pour les petits business qui veulent gérer leur stock simplement.">
                For small businesses that want to manage stock simply.
            </p>

            <ul class="plan-list">
                <li><span class="yes">✓</span> <span data-en="Inventory management" data-fr="Gestion d’inventaire">Inventory management</span></li>
                <li><span class="yes">✓</span> <span data-en="Low stock alerts" data-fr="Alertes stock faible">Low stock alerts</span></li>
                <li><span class="yes">✓</span> <span data-en="Simple reports" data-fr="Rapports simples">Simple reports</span></li>
                <li><span class="no">✕</span> <span data-en="Employee creation" data-fr="Création d’employés">Employee creation</span></li>
                <li><span class="no">✕</span> <span data-en="Employee accounts" data-fr="Comptes employés">Employee accounts</span></li>
                <li><span class="no">✕</span> <span data-en="Clock in / Clock out" data-fr="Clock in / Clock out">Clock in / Clock out</span></li>
            </ul>

            <a href="../LionTech_Add_Business_Page/Ajoute_business_login.php?plan=Basic" class="plan-btn"
               data-en="Choose Basic" data-fr="Choisir Basic">Choose Basic</a>
        </div>

        <div class="plan-card plan-standard">
            <div class="plan-tag">🔵 STANDARD</div>
            <h2>Standard</h2>
            <div class="plan-price">5,000 XAF <span data-en="/ month" data-fr="/ mois">/ month</span></div>

            <p class="plan-desc"
               data-en="For businesses that want to manage employee records without employee mobile access."
               data-fr="Pour les business qui veulent gérer les employés sans accès mobile employé.">
                For businesses that want to manage employee records without employee mobile access.
            </p>

            <ul class="plan-list">
                <li><span class="yes">✓</span> <span data-en="Everything in Basic" data-fr="Tout dans Basic">Everything in Basic</span></li>
                <li><span class="yes">✓</span> <span data-en="Create employees" data-fr="Créer des employés">Create employees</span></li>
                <li><span class="yes">✓</span> <span data-en="Employee list" data-fr="Liste des employés">Employee list</span></li>
                <li><span class="yes">✓</span> <span data-en="Employee information and roles" data-fr="Informations et rôles employés">Employee information and roles</span></li>
                <li><span class="no">✕</span> <span data-en="Employee login" data-fr="Connexion employé">Employee login</span></li>
                <li><span class="no">✕</span> <span data-en="Clock in / Clock out" data-fr="Clock in / Clock out">Clock in / Clock out</span></li>
                <li><span class="no">✕</span> <span data-en="Stock changes by employees" data-fr="Modification de stock par employé">Stock changes by employees</span></li>
            </ul>

            <a href="../LionTech_Add_Business_Page/Ajoute_business_login.php?plan=Standard" class="plan-btn"
               data-en="Choose Standard" data-fr="Choisir Standard">Choose Standard</a>
        </div>

        <div class="plan-card plan-premium">
            <div class="plan-tag">🟡 PREMIUM</div>
            <h2>Premium</h2>
            <div class="plan-price">10,000 XAF <span data-en="/ month" data-fr="/ mois">/ month</span></div>

            <p class="plan-desc"
               data-en="For businesses that want to give secure system access to employees."
               data-fr="Pour les business qui veulent donner un accès sécurisé aux employés.">
                For businesses that want to give secure system access to employees.
            </p>

            <ul class="plan-list">
                <li><span class="yes">✓</span> <span data-en="Everything in Basic" data-fr="Tout dans Basic">Everything in Basic</span></li>
                <li><span class="yes">✓</span> <span data-en="Everything in Standard" data-fr="Tout dans Standard">Everything in Standard</span></li>
                <li><span class="yes">✓</span> <span data-en="Employee accounts" data-fr="Comptes employés">Employee accounts</span></li>
                <li><span class="yes">✓</span> <span data-en="Clock in / Clock out" data-fr="Clock in / Clock out">Clock in / Clock out</span></li>
                <li><span class="yes">✓</span> <span data-en="Employee mobile access" data-fr="Accès mobile employé">Employee mobile access</span></li>
                <li><span class="yes">✓</span> <span data-en="Stock changes with approval" data-fr="Changements de stock avec approbation">Stock changes with approval</span></li>
                <li><span class="yes">✓</span> <span data-en="Role-based access" data-fr="Accès selon le rôle">Role-based access</span></li>
                <li><span class="yes">✓</span> <span data-en="Manager can manage employees if authorized" data-fr="Manager peut gérer les employés si autorisé">Manager can manage employees if authorized</span></li>
            </ul>

            <a href="../LionTech_Add_Business_Page/Ajoute_business_login.php?plan=Premium" class="plan-btn"
               data-en="Choose Premium" data-fr="Choisir Premium">Choose Premium</a>
        </div>

    </section>

    <section class="payment-section">
        <h2 data-en="Accepted Payment Methods" data-fr="Méthodes de paiement acceptées">
            Accepted Payment Methods
        </h2>

        <div class="payment-methods">
            <div class="payment-method">MTN / Orange Money</div>
            <div class="payment-method" data-en="Bank Transfer" data-fr="Compte bancaire">Bank Transfer</div>
            <div class="payment-method">PayPal</div>
            <div class="payment-method">Sendwave</div>
            <div class="payment-method">Taptap Send</div>
            <div class="payment-method">LemFi</div>
        </div>

        <p class="note"
           data-en="Prices are displayed in XAF, the base currency in Cameroon. If your business is in another country, LionTech will confirm the equivalent in your local currency before activation."
           data-fr="Les prix sont affichés en XAF, la monnaie de base au Cameroun. Si votre business est dans un autre pays, LionTech confirmera l’équivalent dans votre devise locale avant l’activation.">
            Prices are displayed in XAF, the base currency in Cameroon. If your business is in another country, LionTech will confirm the equivalent in your local currency before activation.
        </p>
    </section>

       <a href="<?= APP_URL ?>/Logininventory/login.php" class="back-link"
   data-en="← Back to login" data-fr="← Retour à la connexion">← Back to login</a>
</div>

<script>
let currentLang = "en";

document.getElementById("langBtn").addEventListener("click", function(){
    currentLang = currentLang === "en" ? "fr" : "en";

    document.documentElement.lang = currentLang;
    this.textContent = currentLang === "en" ? "FR" : "EN";

    document.querySelectorAll("[data-en][data-fr]").forEach(function(el){
        el.textContent = el.getAttribute("data-" + currentLang);
    });
});
</script>

</body>
</html>