<?php
require_once __DIR__ . '/../Config.php';
startSecureSession();
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$selectedPlan = $_GET['plan'] ?? 'Basic';
$allowedPlans = ['Basic', 'Standard', 'Premium'];
if (!in_array($selectedPlan, $allowedPlans, true)) {
    $selectedPlan = 'Basic';
}

/* Show errors if redirected back */
$errors = $_SESSION['business_request_errors'] ?? [];
unset($_SESSION['business_request_errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Business Account — Tally</title>
<link rel="stylesheet" href="add_business.css">
<style>
.lang-switch{max-width:1250px;margin:0 auto 16px;display:flex;justify-content:flex-end;}
.lang-switch button{background:#0B1F3A;color:white;border:none;border-radius:10px;padding:10px 18px;font-weight:800;cursor:pointer;font-size:14px;}
.ab-logo-top{display:flex;justify-content:center;margin-bottom:18px;}
.ab-plan-note{background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:14px;font-size:13px;color:#64748B;margin-bottom:18px;}
.ab-plan-price{font-weight:900;color:#0B1F3A;}
.other-box{display:none;margin-top:8px;}
.other-box input{width:100%;padding:10px 12px;border:1px solid #CBD5E1;border-radius:8px;font-size:14px;outline:none;}
.other-box input:focus{border-color:#1A9E7A;}
.error-list{background:#FEE2E2;border:1px solid #FCA5A5;border-radius:10px;padding:14px 18px;margin-bottom:20px;color:#991B1B;font-size:14px;}
.error-list li{margin-bottom:4px;}
</style>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<main class="ab-content">

    <div class="lang-switch">
        <button type="button" id="langBtn">FR</button>
    </div>

    <div class="ab-logo-top">
        <img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally"
             style="width:60px;height:60px;border-radius:50%;object-fit:cover;"/>
    </div>

    <div class="ab-page-head">
        <div>
            <h1 class="ab-page-title"
                data-en="Create Your Business Account"
                data-fr="Créer votre compte business">
                Create Your Business Account
            </h1>
            <p class="ab-page-sub"
               data-en="Complete this form. LionTech will review your request before activating your account."
               data-fr="Remplissez ce formulaire. LionTech vérifiera votre demande avant d'activer votre compte.">
                Complete this form. LionTech will review your request before activating your account.
            </p>
        </div>
        <div class="ab-actions">
            <a class="ab-btn ab-btn-outline"
               href="<?= APP_URL ?>/LionTech_Complete_MVP_Remaining_Pages/subscription_plans.php"
               data-en="← Back to plans" data-fr="← Retour aux abonnements">
                ← Back to plans
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <ul class="error-list">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <form method="POST" action="<?= APP_URL ?>/LionTech_Add_Business_Page/business_request.php" novalidate>
        <div class="ab-grid">

            <div class="ab-card">
                <div class="ab-card-header">
                    <div>
                        <div class="ab-card-title"
                             data-en="Business Request"
                             data-fr="Demande de création">Business Request</div>
                        <div class="ab-card-sub"
                             data-en="Fields marked with * are required."
                             data-fr="Les champs avec * sont obligatoires.">
                            Fields marked with * are required.
                        </div>
                    </div>
                </div>

                <div class="ab-card-body">

                    <!-- SECTION 1: Business Info -->
                    <section class="ab-section">
                        <div class="ab-section-title"
                             data-en="<span class="icon-biz">⌂</span> Business Information"
                             data-fr="<span class="icon-biz">⌂</span> Informations du business">
                            <span class="icon-biz">⌂</span> Business Information
                        </div>
                        <div class="ab-form-grid">

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Business Name *"
                                       data-fr="Nom du business *">Business Name *</label>
                                <input class="ab-input" name="business_name"
                                       data-ph-en="Example: Simba Restaurant"
                                       data-ph-fr="Exemple: Restaurant Simba"
                                       placeholder="Example: Simba Restaurant" required>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Business Type *"
                                       data-fr="Type de business *">Business Type *</label>
                                <select class="ab-select" name="business_type" id="business_type" required>
                                    <option value="" data-en="Choose..." data-fr="Choisir...">Choose...</option>
                                    <option value="Restaurant">Restaurant</option>
                                    <option value="Snack Bar">Snack Bar</option>
                                    <option value="Boutique" data-en="Boutique" data-fr="Boutique">Boutique</option>
                                    <option value="Salon">Salon</option>
                                    <option value="Pharmacy" data-en="Pharmacy" data-fr="Pharmacie">Pharmacy</option>
                                    <option value="Hardware Store" data-en="Hardware Store" data-fr="Quincaillerie">Hardware Store</option>
                                    <option value="Supermarket" data-en="Supermarket" data-fr="Supermarché">Supermarket</option>
                                    <option value="Other" data-en="Other" data-fr="Autre">Other</option>
                                </select>
                                <div class="other-box" id="otherBusinessTypeBox">
                                    <input name="other_business_type"
                                           data-ph-en="Specify your business type"
                                           data-ph-fr="Précisez votre type de business"
                                           placeholder="Specify your business type">
                                </div>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Country *"
                                       data-fr="Pays *">Country *</label>
                                <select class="ab-select" name="country" id="country" required>
                                    <option value="" data-en="Choose..." data-fr="Choisir...">Choose...</option>
                                    <option value="Cameroon" data-en="Cameroon" data-fr="Cameroun">Cameroon</option>
                                    <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                                    <option value="Senegal" data-en="Senegal" data-fr="Sénégal">Senegal</option>
                                    <option value="Nigeria">Nigeria</option>
                                    <option value="Ghana">Ghana</option>
                                    <option value="Kenya">Kenya</option>
                                    <option value="Other" data-en="Other" data-fr="Autre">Other</option>
                                </select>
                                <div class="other-box" id="otherCountryBox">
                                    <input name="other_country"
                                           data-ph-en="Enter your country"
                                           data-ph-fr="Entrez votre pays"
                                           placeholder="Enter your country">
                                </div>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Preferred Currency *"
                                       data-fr="Devise préférée *">Preferred Currency *</label>
                                <select class="ab-select" name="currency" id="currency" required>
                                    <option value="" data-en="Choose..." data-fr="Choisir...">Choose...</option>
                                    <option value="XAF">XAF — Central African CFA franc</option>
                                    <option value="XOF">XOF — West African CFA franc</option>
                                    <option value="NGN">NGN — Nigerian Naira</option>
                                    <option value="GHS">GHS — Ghana Cedi</option>
                                    <option value="KES">KES — Kenyan Shilling</option>
                                    <option value="USD">USD — US Dollar</option>
                                    <option value="EUR">EUR — Euro</option>
                                    <option value="Other" data-en="Other" data-fr="Autre">Other</option>
                                </select>
                                <div class="other-box" id="otherCurrencyBox">
                                    <input name="other_currency"
                                           data-ph-en="Enter your currency (e.g. RWF)"
                                           data-ph-fr="Entrez votre devise (ex: RWF)"
                                           placeholder="Enter your currency (e.g. RWF)">
                                </div>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="City *"
                                       data-fr="Ville *">City *</label>
                                <input class="ab-input" name="city"
                                       data-ph-en="Example: Douala"
                                       data-ph-fr="Exemple: Douala"
                                       placeholder="Example: Douala" required>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Business Phone *"
                                       data-fr="Téléphone business *">Business Phone *</label>
                                <input class="ab-input" name="phone"
                                       placeholder="+237 6XX XXX XXX" required>
                            </div>

                            <div class="ab-field full">
                                <label class="ab-label"
                                       data-en="Business Email"
                                       data-fr="Email business">Business Email</label>
                                <input class="ab-input" type="email" name="business_email"
                                       data-ph-en="Optional"
                                       data-ph-fr="Optionnel"
                                       placeholder="Optional">
                            </div>

                            <div class="ab-field full">
                                <label class="ab-label"
                                       data-en="Address"
                                       data-fr="Adresse">Address</label>
                                <textarea class="ab-textarea" name="address"
                                          data-ph-en="Neighborhood, street, or location details..."
                                          data-ph-fr="Quartier, rue ou indication..."></textarea>
                            </div>

                        </div>
                    </section>

                    <!-- SECTION 2: Owner Info -->
                    <section class="ab-section">
                        <div class="ab-section-title"
                             data-en="<span class="icon-user">◉</span> Owner Information"
                             data-fr="<span class="icon-user">◉</span> Informations du propriétaire">
                            <span class="icon-user">◉</span> Owner Information
                        </div>
                        <div class="ab-form-grid">

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="First Name *"
                                       data-fr="Prénom *">First Name *</label>
                                <input class="ab-input" name="owner_first_name"
                                       data-ph-en="Example: Martha"
                                       data-ph-fr="Exemple: Martha"
                                       placeholder="Example: Martha" required>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Last Name *"
                                       data-fr="Nom *">Last Name *</label>
                                <input class="ab-input" name="owner_last_name"
                                       data-ph-en="Example: Njoya"
                                       data-ph-fr="Exemple: Njoya"
                                       placeholder="Example: Njoya" required>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Owner Phone *"
                                       data-fr="Téléphone propriétaire *">Owner Phone *</label>
                                <input class="ab-input" name="owner_phone"
                                       placeholder="+237 6XX XXX XXX" required>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Owner Email"
                                       data-fr="Email propriétaire">Owner Email</label>
                                <input class="ab-input" type="email" name="owner_email"
                                       data-ph-en="Optional"
                                       data-ph-fr="Optionnel"
                                       placeholder="Optional">
                            </div>

                        </div>
                    </section>

                    <!-- SECTION 3: Subscription -->
                    <section class="ab-section">
                        <div class="ab-section-title"
                             data-en="<span class="icon-card">▬</span> Subscription & Payment"
                             data-fr="<span class="icon-card">▬</span> Abonnement et paiement">
                            <span class="icon-card">▬</span> Subscription & Payment
                        </div>

                        <div class="ab-plan-note">
                            <span data-en="Selected plan:" data-fr="Plan sélectionné :">Selected plan:</span>
                            <span class="ab-plan-price"><?= e($selectedPlan) ?></span>
                        </div>

                        <div class="ab-form-grid">

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Plan *"
                                       data-fr="Plan *">Plan *</label>
                                <select class="ab-select" name="plan_name" required>
                                    <option value="Basic"    <?= $selectedPlan==='Basic'    ?'selected':'' ?>>Basic — 2,000 XAF</option>
                                    <option value="Standard" <?= $selectedPlan==='Standard' ?'selected':'' ?>>Standard — 5,000 XAF</option>
                                    <option value="Premium"  <?= $selectedPlan==='Premium'  ?'selected':'' ?>>Premium — 10,000 XAF</option>
                                </select>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Payment Frequency *"
                                       data-fr="Fréquence de paiement *">Payment Frequency *</label>
                                <select class="ab-select" name="billing_cycle" required>
                                    <option value="monthly"  data-en="Monthly"  data-fr="Mensuel">Monthly</option>
                                    <option value="3_months" data-en="3 months" data-fr="3 mois">3 months</option>
                                    <option value="6_months" data-en="6 months" data-fr="6 mois">6 months</option>
                                    <option value="yearly"   data-en="Yearly"   data-fr="Annuel">Yearly</option>
                                </select>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Preferred Payment Method *"
                                       data-fr="Méthode de paiement préférée *">Preferred Payment Method *</label>
                                <select class="ab-select" name="preferred_payment_method" id="payment_method" required>
                                    <option value="" data-en="Choose..." data-fr="Choisir...">Choose...</option>
                                    <option value="mtn_orange_money">MTN / Orange Money</option>
                                    <option value="bank_transfer" data-en="Bank Transfer" data-fr="Virement bancaire">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="sendwave">Sendwave</option>
                                    <option value="taptap_send">Taptap Send</option>
                                    <option value="lemfi">LemFi</option>
                                    <option value="other" data-en="Other" data-fr="Autre">Other</option>
                                </select>
                                <div class="other-box" id="otherPaymentBox">
                                    <input name="other_payment_method"
                                           data-ph-en="Specify your payment method"
                                           data-ph-fr="Précisez votre méthode de paiement"
                                           placeholder="Specify your payment method">
                                </div>
                            </div>

                            <div class="ab-field">
                                <label class="ab-label"
                                       data-en="Do you have employees? *"
                                       data-fr="Avez-vous des employés ? *">Do you have employees? *</label>
                                <select class="ab-select" name="has_employees" required>
                                    <option value="0" data-en="No"  data-fr="Non">No</option>
                                    <option value="1" data-en="Yes" data-fr="Oui">Yes</option>
                                </select>
                            </div>

                        </div>
                    </section>

                    <!-- SECTION 4: Modules -->
                    <section class="ab-section">
                        <div class="ab-section-title"
                             data-en="<span class="icon-gear"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Requested Modules"
                             data-fr="<span class="icon-gear"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Modules souhaités">
                            <span class="icon-gear"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Requested Modules
                        </div>
                        <div class="ab-checks">

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="inventory_management" checked>
                                <div>
                                    <strong data-en="Inventory" data-fr="Inventaire">Inventory</strong>
                                    <small data-en="Products, stock in/out" data-fr="Produits, stock entrant/sortant">Products, stock in/out</small>
                                </div>
                            </label>

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="reports" checked>
                                <div>
                                    <strong data-en="Reports" data-fr="Rapports">Reports</strong>
                                    <small data-en="Daily/monthly summaries" data-fr="Résumé journalier/mensuel">Daily/monthly summaries</small>
                                </div>
                            </label>

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="low_stock_alerts" checked>
                                <div>
                                    <strong data-en="Low Stock Alerts" data-fr="Alertes stock faible">Low Stock Alerts</strong>
                                    <small data-en="Restock notifications" data-fr="Notifications de réapprovisionnement">Restock notifications</small>
                                </div>
                            </label>

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="employee_management">
                                <div>
                                    <strong data-en="Employee Management" data-fr="Gestion des employés">Employee Management</strong>
                                    <small data-en="Employee records and roles" data-fr="Fiches employés et rôles">Employee records and roles</small>
                                </div>
                            </label>

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="employee_attendance">
                                <div>
                                    <strong data-en="Clock In / Clock Out" data-fr="Clock in / Clock out">Clock In / Clock Out</strong>
                                    <small data-en="Employee attendance tracking" data-fr="Suivi de présence employés">Employee attendance tracking</small>
                                </div>
                            </label>

                            <label class="ab-check">
                                <input type="checkbox" name="features[]" value="mobile_employee_access">
                                <div>
                                    <strong data-en="Employee Mobile Access" data-fr="Accès mobile employés">Employee Mobile Access</strong>
                                    <small data-en="Employee phone access" data-fr="Utilisation sur téléphone">Employee phone access</small>
                                </div>
                            </label>

                        </div>
                    </section>

                </div>

                <div class="ab-footer-actions">
                    <a class="ab-btn ab-btn-outline"
                       href="<?= APP_URL ?>/LionTech_Complete_MVP_Remaining_Pages/subscription_plans.php"
                       data-en="Cancel" data-fr="Annuler">Cancel</a>
                    <button class="ab-btn ab-btn-primary" type="submit"
                            data-en="Submit Request" data-fr="Soumettre la demande">
                        Submit Request
                    </button>
                </div>
            </div>

            <!-- Sidebar info -->
            <aside class="ab-card">
                <div class="ab-card-header">
                    <div>
                        <div class="ab-card-title" data-en="Important" data-fr="Important">Important</div>
                        <div class="ab-card-sub" data-en="After submission" data-fr="Après soumission">After submission</div>
                    </div>
                </div>
                <div class="ab-card-body">
                    <div class="ab-side-list">

                        <div class="ab-info-box">
                            <strong data-en="LionTech Review" data-fr="Validation LionTech">LionTech Review</strong>
                            <p data-en="Your request will be sent to the Super Admin for review."
                               data-fr="Votre demande sera envoyée au Super Admin pour validation.">
                                Your request will be sent to the Super Admin for review.
                            </p>
                        </div>

                        <div class="ab-info-box">
                            <strong data-en="Login Created by Admin" data-fr="Login créé par l'admin">Login Created by Admin</strong>
                            <p data-en="The Super Admin will create your login ID and temporary PIN after approval."
                               data-fr="Le Super Admin créera votre identifiant et votre PIN temporaire après validation.">
                                The Super Admin will create your login ID and temporary PIN after approval.
                            </p>
                        </div>

                        <div class="ab-info-box">
                            <strong data-en="Within 48 Hours" data-fr="Dans les 48 heures">Within 48 Hours</strong>
                            <p data-en="LionTech will contact you via WhatsApp with your credentials."
                               data-fr="LionTech vous contactera via WhatsApp avec vos identifiants.">
                                LionTech will contact you via WhatsApp with your credentials.
                            </p>
                        </div>

                        <div class="ab-info-box">
                            <strong data-en="Private Business Space" data-fr="Espace business privé">Private Business Space</strong>
                            <p data-en="Each business gets its own private and separated space."
                               data-fr="Chaque business aura son propre espace privé et séparé.">
                                Each business gets its own private and separated space.
                            </p>
                        </div>

                    </div>
                </div>
            </aside>

        </div>
    </form>

</main>

<script>
let currentLang = "en";

/* ── Language toggle ── */
document.getElementById("langBtn").addEventListener("click", function(){
    currentLang = currentLang === "en" ? "fr" : "en";
    document.documentElement.lang = currentLang;
    this.textContent = currentLang === "en" ? "FR" : "EN";

    /* Switch text content */
    document.querySelectorAll("[data-en][data-fr]").forEach(function(el){
        el.textContent = el.getAttribute("data-" + currentLang);
    });

    /* Switch placeholders */
    document.querySelectorAll("[data-ph-en][data-ph-fr]").forEach(function(el){
        el.placeholder = el.getAttribute("data-ph-" + currentLang);
    });
});

/* ── Other box helper ── */
function setupOtherBox(selectId, boxId, inputRequired) {
    const sel = document.getElementById(selectId);
    const box = document.getElementById(boxId);
    if (!sel || !box) return;
    const input = box.querySelector("input");

    sel.addEventListener("change", function(){
        if (this.value === "Other") {
            box.style.display = "block";
            if (inputRequired) input.required = true;
        } else {
            box.style.display = "none";
            if (inputRequired) input.required = false;
            input.value = "";
        }
    });
}

setupOtherBox("business_type", "otherBusinessTypeBox", true);
setupOtherBox("country",       "otherCountryBox",       true);
setupOtherBox("currency",      "otherCurrencyBox",      true);
setupOtherBox("payment_method","otherPaymentBox",       true);
</script>

</body>
</html>