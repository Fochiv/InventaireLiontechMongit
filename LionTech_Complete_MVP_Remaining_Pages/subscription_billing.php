<?php
/* ============================================================
   subscription_billing.php — Tally Business Manager
   Owner submits payment — pending until super admin approves.
   Path: LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_BUSINESS_OWNER]);

$user       = currentUser();
$pdo        = getDB();
$businessId = (int)($user['business_id'] ?? 0);

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Load business */
$stmt = $pdo->prepare('SELECT * FROM businesses WHERE business_id = ? LIMIT 1');
$stmt->execute([$businessId]);
$business = $stmt->fetch();

/* Load payment settings (LionTech numbers) */
$ps = [];
try {
    $ps = $pdo->query('SELECT * FROM payment_settings ORDER BY setting_id ASC LIMIT 1')->fetch() ?: [];
} catch (Throwable $e) {}

/* Load subscription */
$subscription = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE business_id = ? ORDER BY subscription_id DESC LIMIT 1');
    $stmt->execute([$businessId]);
    $subscription = $stmt->fetch() ?: [];
} catch (Throwable $e) {}

/* Load payment history */
$payments = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM liontech_payments WHERE business_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$businessId]);
    $payments = $stmt->fetchAll();
} catch (Throwable $e) {}

/* Check for existing pending payment */
$hasPending = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM liontech_payments WHERE business_id = ? AND status = "pending"');
    $stmt->execute([$businessId]);
    $hasPending = (int)$stmt->fetchColumn() > 0;
} catch (Throwable $e) {}

$subStatus = $business['subscription_status'] ?? 'trial';
$expiresAt = $business['subscription_expires_at'] ?? null;
$isExpired = in_array($subStatus, ['expired','suspended'], true) || ($expiresAt && strtotime($expiresAt) < time());
$daysLeft  = $expiresAt ? ceil((strtotime($expiresAt) - time()) / 86400) : null;

/* Duration options */
$DURATIONS = [
    1  => ['label' => '1 mois',  'months' => 1],
    2  => ['label' => '2 mois',  'months' => 2],
    3  => ['label' => '3 mois',  'months' => 3],
    6  => ['label' => '6 mois',  'months' => 6],
    12 => ['label' => '1 an',    'months' => 12],
];

$message = ''; $messageType = '';
$waUrl   = '';

/* ── Submit payment ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_payment') {

    if ($hasPending) {
        $message = 'Vous avez déjà un paiement en attente. Attendez qu\'il soit traité avant d\'en soumettre un nouveau.';
        $messageType = 'error';
    } else {
        $method    = trim($_POST['payment_method']        ?? '');
        $amount    = (float)($_POST['amount']              ?? 0);
        $months    = (int)($_POST['months_paid']           ?? 1);
        $reference = trim($_POST['transaction_reference']  ?? '');
        $proofUrl  = null;

        $allowedMethods = ['orange_money','mtn_momo','bank_transfer','cash'];
        if (!in_array($method, $allowedMethods, true)) {
            $message = 'Veuillez sélectionner une méthode de paiement.'; $messageType = 'error';
        } elseif ($amount <= 0) {
            $message = 'Veuillez entrer un montant valide.'; $messageType = 'error';
        } elseif ($method !== 'cash' && $reference === '') {
            $message = 'Veuillez entrer la référence / numéro de transaction.'; $messageType = 'error';
        } else {

            /* Check duplicate transaction reference */
            if ($reference !== '') {
                try {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM liontech_payments WHERE transaction_reference = ?');
                    $stmt->execute([$reference]);
                    if ((int)$stmt->fetchColumn() > 0) {
                        $message = 'Ce numéro de transaction a déjà été utilisé. Vérifiez votre référence.';
                        $messageType = 'error';
                        goto renderPage;
                    }
                } catch (Throwable $e) {}
            }

            /* Upload proof image */
            if (!empty($_FILES['proof_image']['name']) && is_uploaded_file($_FILES['proof_image']['tmp_name'])) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $mime = mime_content_type($_FILES['proof_image']['tmp_name']);
                if (isset($allowed[$mime]) && $_FILES['proof_image']['size'] <= 5*1024*1024) {
                    $dir = __DIR__ . '/uploads/payments/';
                    if (!is_dir($dir)) @mkdir($dir, 0775, true);
                    $fname = 'pay_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
                    if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $dir.$fname)) {
                        $proofUrl = 'LionTech_Complete_MVP_Remaining_Pages/uploads/payments/'.$fname;
                    }
                }
            }

            /* Insert payment */
            try {
                $pdo->prepare('INSERT INTO liontech_payments
                    (business_id, amount, months_paid, payment_method, transaction_reference, proof_image_url, status, submitted_by, created_at)
                    VALUES (?,?,?,?,?,?,"pending",?,NOW())')
                    ->execute([$businessId, $amount, $months, $method, $reference ?: null, $proofUrl, $user['user_id']]);

                $hasPending  = true;
                $message     = 'Paiement soumis avec succès. En attente de validation par LionTech.';
                $messageType = 'success';

                /* Build WhatsApp notification for LionTech */
                $bizName = $business['business_name'] ?? 'Business';
                $methodLabels = [
                    'orange_money'  => 'Orange Money',
                    'mtn_momo'      => 'MTN MoMo',
                    'bank_transfer' => 'Virement Bancaire',
                    'cash'          => 'Espèces',
                ];
                $waText = "<span class="icon-brand">T</span> *Nouveau paiement LionTech*\n\nBusiness: *{$bizName}*\nMontant: *" . number_format($amount,0,'.',' ') . " XAF*\nDurée: *{$months} mois*\nMéthode: *" . ($methodLabels[$method] ?? $method) . "*\nRéf: *" . ($reference ?: 'Espèces') . "*\n\nVeuillez valider sur le tableau de bord Super Admin.";
                $waUrl = 'https://wa.me/237688203095?text=' . urlencode($waText);

                /* Reload payments */
                $stmt = $pdo->prepare('SELECT * FROM liontech_payments WHERE business_id = ? ORDER BY created_at DESC LIMIT 20');
                $stmt->execute([$businessId]);
                $payments = $stmt->fetchAll();

            } catch (Throwable $ex) {
                $message = 'Erreur: ' . $ex->getMessage(); $messageType = 'error';
            }
        }
    }
}

renderPage:

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'O')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'O', 0, 2);

$methodLabels = [
    'orange_money'  => '🟠 Orange Money',
    'mtn_momo'      => '🟡 MTN MoMo',
    'bank_transfer' => '🏦 Virement Bancaire',
    'cash'          => '<span class="icon-money">&#36;</span> Espèces',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Abonnement — LionTech</title>
</head>
<body>
<div class="od-layout">
  <?php include __DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">
    <header class="od-topbar">
      <div class="od-business-title">
        <h1>Abonnement & Paiement</h1>
        <p><?= e($business['business_name'] ?? '') ?> — Plan actuel, paiements et renouvellement</p>
      </div>
      <div class="od-top-actions">
        <button class="od-lang" id="sb-lang-btn" type="button" style="border:1px solid #E5E7EB;background:#fff;border-radius:999px;padding:9px 13px;font-weight:800;cursor:pointer;font-family:inherit">FR</button>
        <div class="od-avatar"><?= e($initials) ?></div>
      </div>
    </header>

    <?php if ($isExpired): ?>
    <div style="background:#FEF2F2;border:1px solid #FECACA;padding:14px 24px;font-size:13px;color:#991B1B;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span style="font-size:20px"><span class="icon-warn">⚠</span></span>
      <div style="flex:1"><strong>Abonnement expiré</strong><p style="margin:2px 0 0">Les actions d'inventaire sont limitées. Soumettez un paiement ci-dessous pour renouveler.</p></div>
    </div>
    <?php elseif ($daysLeft !== null && $daysLeft <= 14): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 24px;font-size:13px;color:#92400E">
      <span class="icon-warn">⚠</span> Votre abonnement expire dans <strong><?= (int)$daysLeft ?> jour(s)</strong>. Pensez à renouveler.
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div style="background:<?=$messageType==='success'?'#F0FDF4':'#FEF2F2'?>;border:1px solid <?=$messageType==='success'?'#86EFAC':'#FECACA'?>;padding:12px 24px;font-size:13px;color:<?=$messageType==='success'?'#166534':'#991B1B'?>">
      <?=$messageType==='success'?'<span class="icon-ok">✓</span>':'<span class="icon-warn">⚠</span>'?> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($waUrl): ?>
    <!-- WhatsApp notify LionTech -->
    <div style="background:#ECFDF5;border:2px solid #1A9E7A;border-radius:14px;padding:16px 20px;margin:16px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <span style="font-size:24px"><span class="icon-msg">▷</span></span>
      <div style="flex:1">
        <div style="font-size:14px;font-weight:700;color:#0B1F3A">Notifier LionTech sur WhatsApp</div>
        <div style="font-size:12.5px;color:#6B7280;margin-top:3px">Cliquez pour envoyer les détails de votre paiement à LionTech pour accélérer la validation.</div>
      </div>
      <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer"
        style="background:#25D366;color:#fff;padding:11px 20px;border-radius:10px;text-decoration:none;font-size:13.5px;font-weight:700;white-space:nowrap">
        📲 Envoyer WhatsApp
      </a>
    </div>
    <?php endif; ?>

    <?php if ($hasPending): ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:12px;padding:14px 20px;margin:16px 24px;font-size:13.5px;color:#92400E;display:flex;align-items:center;gap:12px">
      <span style="font-size:20px">⏳</span>
      <div>
        <strong>Paiement en attente de validation</strong>
        <p style="margin:3px 0 0;font-size:13px">LionTech est en train de vérifier votre paiement. Vous serez notifié(e) sur WhatsApp dès validation.</p>
      </div>
    </div>
    <?php endif; ?>

    <div style="padding:20px 24px 0;display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

      <!-- Current plan card -->
      <div class="od-card" style="padding:24px">
        <div class="od-card-head"><div><h2>Plan actuel</h2><p>Statut de votre abonnement</p></div>
          <span class="od-badge <?= $subStatus==='active'?'success':'danger' ?>"><?= ucfirst(e($subStatus)) ?></span>
        </div>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:10px;font-size:13.5px">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9">
            <span style="color:#6B7280">Plan</span>
            <strong><?= e($subscription['plan_name'] ?? 'Standard') ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9">
            <span style="color:#6B7280">Expiration</span>
            <strong><?= $expiresAt ? e(date('d/m/Y', strtotime($expiresAt))) : '—' ?></strong>
          </div>
          <?php if ($daysLeft !== null): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0">
            <span style="color:#6B7280">Jours restants</span>
            <strong style="color:<?= $daysLeft<=14?'#DC2626':'#166534' ?>"><?= (int)$daysLeft ?> jours</strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Features card -->
      <div class="od-card" style="padding:24px">
        <div class="od-card-head"><div><h2>Fonctionnalités incluses</h2></div></div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;font-size:13.5px">
          <?php foreach(['Gestion inventaire','Stock entrant / sortant','Gestion des employés','Clock in / Clock out GPS','Rapports et analyses','Validations et approbations'] as $feat): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #F9FAFB">
            <span style="color:#16A34A"><span class="icon-ok">✓</span></span><span><?= $feat ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Payment form -->
    <?php if (!$hasPending): ?>
    <div style="padding:20px 24px 0">
      <div class="od-card" style="padding:28px">
        <div class="od-card-head"><div><h2>Soumettre un paiement</h2><p>Choisissez votre méthode et entrez les détails. LionTech validera sous 24–48h.</p></div></div>

        <form method="POST" enctype="multipart/form-data" id="paymentForm" style="margin-top:20px">
          <input type="hidden" name="action" value="submit_payment"/>

          <!-- Duration selector -->
          <div style="margin-bottom:20px">
            <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px">Durée de l'abonnement</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <?php foreach ($DURATIONS as $m => $d): ?>
              <label style="cursor:pointer">
                <input type="radio" name="months_paid" value="<?= $m ?>" <?= $m===1?'checked':'' ?> style="display:none" class="dur-radio"/>
                <div class="dur-btn" style="padding:10px 18px;border:2px solid #E5E7EB;border-radius:10px;font-size:13.5px;font-weight:700;color:#6B7280;transition:all .15s;user-select:none">
                  <?= e($d['label']) ?>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Amount -->
          <div style="margin-bottom:18px">
            <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px">Montant payé (XAF) *</label>
            <input type="number" name="amount" min="1" step="1" required placeholder="Ex: 25000"
              style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:11px;font-size:15px;font-family:inherit;outline:none;box-sizing:border-box"/>
            <p style="font-size:12px;color:#6B7280;margin:5px 0 0">Entrez le montant exact que vous avez envoyé.</p>
          </div>

          <!-- Payment method -->
          <div style="margin-bottom:18px">
            <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px">Méthode de paiement *</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">

              <?php
              $methods = [
                'orange_money'  => ['icon'=>'🟠','label'=>'Orange Money',   'color'=>'#FF6600'],
                'mtn_momo'      => ['icon'=>'🟡','label'=>'MTN MoMo',       'color'=>'#FFCC00'],
                'bank_transfer' => ['icon'=>'🏦','label'=>'Virement Bancaire','color'=>'#1E40AF'],
                'cash'          => ['icon'=>'<span class="icon-money">&#36;</span>','label'=>'Espèces',         'color'=>'#166534'],
              ];
              foreach ($methods as $val => $m):
              ?>
              <label style="cursor:pointer">
                <input type="radio" name="payment_method" value="<?= $val ?>" style="display:none" class="method-radio" data-method="<?= $val ?>"/>
                <div class="method-btn" data-method="<?= $val ?>"
                  style="border:2px solid #E5E7EB;border-radius:12px;padding:14px;text-align:center;transition:all .15s">
                  <div style="font-size:24px"><?= $m['icon'] ?></div>
                  <div style="font-size:13px;font-weight:700;color:#0B1F3A;margin-top:4px"><?= $m['label'] ?></div>
                </div>
              </label>
              <?php endforeach; ?>

            </div>
          </div>

          <!-- Dynamic info per method -->
          <div id="method-info" style="margin-bottom:18px;display:none">

            <!-- Orange Money info -->
            <div class="method-detail" id="info-orange_money"
              style="display:none;background:#FFF4EE;border:1.5px solid #FF6600;border-radius:12px;padding:16px">
              <div style="font-size:12px;font-weight:700;color:#FF6600;margin-bottom:8px;text-transform:uppercase">Envoyez à ce numéro Orange Money</div>
              <div style="font-size:20px;font-weight:900;color:#0B1F3A;letter-spacing:2px"><?= e($ps['orange_money_number'] ?? 'Non configuré') ?></div>
              <div style="font-size:13px;color:#6B7280;margin-top:4px"><?= e($ps['orange_money_name'] ?? '') ?></div>
              <div style="font-size:12px;color:#FF6600;margin-top:10px">Après envoi, notez le numéro de transaction et uploadez le screenshot ci-dessous.</div>
            </div>

            <!-- MTN MoMo info -->
            <div class="method-detail" id="info-mtn_momo"
              style="display:none;background:#FFFBEA;border:1.5px solid #FFCC00;border-radius:12px;padding:16px">
              <div style="font-size:12px;font-weight:700;color:#B45309;margin-bottom:8px;text-transform:uppercase">Envoyez à ce numéro MTN MoMo</div>
              <div style="font-size:20px;font-weight:900;color:#0B1F3A;letter-spacing:2px"><?= e($ps['mtn_momo_number'] ?? 'Non configuré') ?></div>
              <div style="font-size:13px;color:#6B7280;margin-top:4px"><?= e($ps['mtn_momo_name'] ?? '') ?></div>
              <div style="font-size:12px;color:#B45309;margin-top:10px">Après envoi, notez le numéro de transaction et uploadez le screenshot ci-dessous.</div>
            </div>

            <!-- Bank info -->
            <div class="method-detail" id="info-bank_transfer"
              style="display:none;background:#EFF6FF;border:1.5px solid #1E40AF;border-radius:12px;padding:16px">
              <div style="font-size:12px;font-weight:700;color:#1E40AF;margin-bottom:8px;text-transform:uppercase">Coordonnées bancaires</div>
              <div style="display:flex;flex-direction:column;gap:6px;font-size:13.5px">
                <div><span style="color:#6B7280">Banque:</span> <strong><?= e($ps['bank_name'] ?? 'Non configuré') ?></strong></div>
                <div><span style="color:#6B7280">N° compte:</span> <strong><?= e($ps['bank_account_number'] ?? '—') ?></strong></div>
                <div><span style="color:#6B7280">Titulaire:</span> <strong><?= e($ps['bank_account_holder'] ?? '—') ?></strong></div>
                <div><span style="color:#6B7280">Agence:</span> <strong><?= e($ps['bank_branch'] ?? '—') ?></strong></div>
              </div>
              <div style="font-size:12px;color:#1E40AF;margin-top:10px">Conservez le reçu de dépôt et uploadez-le ci-dessous.</div>
            </div>

            <!-- Cash info -->
            <div class="method-detail" id="info-cash"
              style="display:none;background:#F0FDF4;border:1.5px solid #166534;border-radius:12px;padding:16px">
              <div style="font-size:13.5px;color:#166534;font-weight:600"><span class="icon-money">&#36;</span> Payez directement à un agent ou administrateur LionTech.</div>
              <div style="font-size:13px;color:#6B7280;margin-top:8px">L'administrateur confirmera manuellement votre paiement dans le tableau de bord.</div>
              <a href="https://wa.me/237688203095?text=Bonjour%20LionTech%2C%20je%20souhaite%20payer%20mon%20abonnement%20en%20esp%C3%A8ces%20pour%20<?= urlencode($business['business_name'] ?? '') ?>."
                target="_blank" rel="noopener noreferrer"
                style="display:inline-block;margin-top:12px;background:#25D366;color:#fff;padding:9px 16px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:700">
                <span class="icon-msg">▷</span> Contacter LionTech sur WhatsApp
              </a>
            </div>

          </div>

          <!-- Transaction reference -->
          <div id="ref-field" style="margin-bottom:18px;display:none">
            <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px">Numéro de transaction / Référence *</label>
            <input type="text" name="transaction_reference" id="transaction_reference"
              placeholder="Ex: CI241115.1234.123456"
              style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:11px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;font-family:monospace"/>
            <p style="font-size:12px;color:#6B7280;margin:5px 0 0">Copiez exactement le numéro de transaction depuis votre message de confirmation.</p>
          </div>

          <!-- Proof upload -->
          <div id="proof-field" style="margin-bottom:22px;display:none">
            <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px">Screenshot / Reçu *</label>
            <input type="file" name="proof_image" id="proof_image" accept="image/jpeg,image/png,image/webp"
              style="width:100%;padding:10px;border:1.5px dashed #E5E7EB;border-radius:11px;font-family:inherit;font-size:13px"/>
            <p style="font-size:12px;color:#6B7280;margin:5px 0 0">JPG, PNG ou WEBP — max 5MB. L'image doit montrer le montant, le numéro de transaction et la date.</p>
          </div>

          <button type="submit" id="submitBtn"
            style="width:100%;padding:14px;background:#0B1F3A;color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;display:none">
            📤 Soumettre le paiement
          </button>

        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Payment history -->
    <div style="padding:20px 24px 40px">
      <div class="od-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9">
          <h2 style="font-size:15px;font-weight:700;color:#0B1F3A;margin:0">Historique des paiements</h2>
        </div>
        <div class="od-table-wrap">
          <table class="od-table">
            <thead>
              <tr><th>Date</th><th>Montant</th><th>Durée</th><th>Méthode</th><th>Référence</th><th>Statut</th><th>Détail</th></tr>
            </thead>
            <tbody>
            <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
              <td style="font-size:12px"><?= e(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
              <td><strong><?= number_format((float)$p['amount'],0,'.',' ') ?> XAF</strong></td>
              <td><?= (int)$p['months_paid'] ?> mois</td>
              <td><?= e($methodLabels[$p['payment_method']] ?? $p['payment_method']) ?></td>
              <td style="font-family:monospace;font-size:12px"><?= e($p['transaction_reference'] ?? '—') ?></td>
              <td>
                <span class="od-badge <?= $p['status']==='approved'?'success':($p['status']==='pending'?'':'danger') ?>"
                  style="<?= $p['status']==='pending'?'background:#FEF3C7;color:#92400E':'' ?>">
                  <?= $p['status']==='approved'?'<span class="icon-ok">✓</span> Approuvé':($p['status']==='pending'?'⏳ En attente':'<span class="icon-err">✗</span> Rejeté') ?>
                </span>
              </td>
              <td style="font-size:12px;color:#6B7280"><?= e($p['rejection_reason'] ?? '') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="od-empty">Aucun paiement soumis.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>
</div>

<style>
  .dur-radio:checked + .dur-btn { border-color:#0B1F3A; background:#0B1F3A; color:#fff; }
  .method-radio:checked + .method-btn { border-color:#0B1F3A; background:#F0F4FF; }
</style>
<script>
/* Duration buttons */
document.querySelectorAll('.dur-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.dur-btn').forEach(b => {
            b.style.borderColor = '#E5E7EB';
            b.style.background  = '#fff';
            b.style.color       = '#6B7280';
        });
        const btn = r.nextElementSibling;
        btn.style.borderColor = '#0B1F3A';
        btn.style.background  = '#0B1F3A';
        btn.style.color       = '#fff';
    });
});

/* Method selection */
document.querySelectorAll('.method-radio').forEach(r => {
    r.addEventListener('change', () => {
        const method = r.dataset.method;

        /* Style buttons */
        document.querySelectorAll('.method-btn').forEach(b => {
            b.style.borderColor = '#E5E7EB';
            b.style.background  = '#fff';
        });
        r.nextElementSibling.style.borderColor = '#0B1F3A';
        r.nextElementSibling.style.background  = '#F0F4FF';

        /* Show/hide info panel */
        document.getElementById('method-info').style.display = 'block';
        document.querySelectorAll('.method-detail').forEach(d => d.style.display = 'none');
        const info = document.getElementById('info-' + method);
        if (info) info.style.display = 'block';

        /* Show/hide ref & proof fields */
        const isCash = method === 'cash';
        document.getElementById('ref-field').style.display   = isCash ? 'none' : 'block';
        document.getElementById('proof-field').style.display = isCash ? 'none' : 'block';
        document.getElementById('transaction_reference').required = !isCash;

        /* Show submit button */
        document.getElementById('submitBtn').style.display = 'block';
    });
});

/* Trigger first duration selection */
document.querySelector('.dur-radio:checked')?.dispatchEvent(new Event('change'));

/* Lang button */
(function(){
  var btn = document.getElementById('sb-lang-btn');
  if (btn) btn.addEventListener('click', function(){
    var cur = localStorage.getItem('lt_lang') || 'fr';
    localStorage.setItem('lt_lang', cur === 'fr' ? 'en' : 'fr');
  });
  var saved = localStorage.getItem('lt_lang') || 'fr';
  if (btn) btn.textContent = saved === 'fr' ? 'EN' : 'FR';
})();
</script>
</body>
</html>