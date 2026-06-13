<?php
/* ============================================================
   payment_review.php — LionTech Super Admin
   Review, approve or reject owner payment submissions.
   Path: C:\Xampp\htdocs\InventoryLiontech\SuperAdmin\payment_review.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_SUPER_ADMIN]);

$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$message = ''; $messageType = '';

$REJECTION_REASONS = [
    'Screenshot invalide ou illisible',
    'Numéro de transaction introuvable',
    'Montant ne correspond pas',
    'Transaction déjà utilisée',
    'Screenshot trop ancien (plus de 48h)',
    'Paiement non reçu sur notre compte',
    'Informations du business incorrectes',
    'Autre (précisez ci-dessous)',
];

/* ── Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $paymentId = (int)($_POST['payment_id'] ?? 0);

    if ($action === 'approve' && $paymentId > 0) {
        try {
            $pdo->beginTransaction();

            /* Get payment */
            $stmt = $pdo->prepare('SELECT * FROM liontech_payments WHERE payment_id = ? AND status = "pending" FOR UPDATE');
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();

            if ($payment) {
                /* Update payment status */
                $pdo->prepare('UPDATE liontech_payments SET status="approved", approved_by=?, approved_at=NOW() WHERE payment_id=?')
                    ->execute([$user['user_id'], $paymentId]);

                /* Extend subscription */
                $stmt = $pdo->prepare('SELECT subscription_expires_at FROM businesses WHERE business_id = ?');
                $stmt->execute([$payment['business_id']]);
                $biz = $stmt->fetch();

                $currentExpiry = $biz['subscription_expires_at'] ?? null;
                $months = (int)$payment['months_paid'];

                if ($currentExpiry && strtotime($currentExpiry) > time()) {
                    /* Still active — add months from current expiry */
                    $newExpiry = date('Y-m-d H:i:s', strtotime("+{$months} months", strtotime($currentExpiry)));
                } else {
                    /* Expired — start from today */
                    $newExpiry = date('Y-m-d H:i:s', strtotime("+{$months} months"));
                }

                $pdo->prepare('UPDATE businesses SET subscription_status="active", subscription_expires_at=? WHERE business_id=?')
                    ->execute([$newExpiry, $payment['business_id']]);

                /* Get owner phone for WhatsApp notification */
                $stmt = $pdo->prepare('SELECT u.phone, u.full_name, b.business_name FROM users u JOIN businesses b ON b.business_id=u.business_id WHERE u.business_id=? AND u.role="business_owner" LIMIT 1');
                $stmt->execute([$payment['business_id']]);
                $owner = $stmt->fetch();

                $pdo->commit();

                $ownerPhone  = preg_replace('/\D/', '', $owner['phone'] ?? '');
                $bizName     = $owner['business_name'] ?? 'votre business';
                $ownerName   = $owner['full_name']     ?? 'Propriétaire';
                $newExpiryFmt= date('d/m/Y', strtotime($newExpiry));
                $waMsg       = urlencode("<span class="icon-ok">✓</span> Bonjour {$ownerName},\n\nVotre paiement LionTech a été *approuvé* pour *{$bizName}*.\n\n<span class="icon-cal">▦</span> Abonnement valide jusqu'au: *{$newExpiryFmt}*\n<span class="icon-card">▬</span> Montant: " . number_format((float)$payment['amount'], 0, '.', ' ') . " XAF\n\nMerci pour votre confiance. <span class="icon-brand">T</span>\n— Tally Business Manager");
                $waUrl = "https://wa.me/{$ownerPhone}?text={$waMsg}";

                $message    = "Paiement approuvé. Abonnement étendu jusqu'au {$newExpiryFmt}.";
                $messageType= 'success';
                $_SESSION['wa_notify_url']  = $waUrl;
                $_SESSION['wa_notify_name'] = $ownerName;
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Erreur: ' . $ex->getMessage(); $messageType = 'error';
        }
    }

    if ($action === 'reject' && $paymentId > 0) {
        $reason       = trim($_POST['rejection_reason'] ?? '');
        $customDetail = trim($_POST['rejection_detail'] ?? '');
        $fullReason   = $reason . ($customDetail ? ' — ' . $customDetail : '');

        try {
            $pdo->prepare('UPDATE liontech_payments SET status="rejected", approved_by=?, approved_at=NOW(), rejection_reason=?, rejection_detail=? WHERE payment_id=? AND status="pending"')
                ->execute([$user['user_id'], $reason, $customDetail ?: null, $paymentId]);

            /* Get owner phone for WhatsApp notification */
            $stmt = $pdo->prepare('SELECT u.phone, u.full_name, b.business_name FROM liontech_payments lp JOIN users u ON u.business_id=lp.business_id AND u.role="business_owner" JOIN businesses b ON b.business_id=lp.business_id WHERE lp.payment_id=? LIMIT 1');
            $stmt->execute([$paymentId]);
            $owner = $stmt->fetch();

            $ownerPhone = preg_replace('/\D/', '', $owner['phone'] ?? '');
            $bizName    = $owner['business_name'] ?? 'votre business';
            $ownerName  = $owner['full_name']     ?? 'Propriétaire';
            $waMsg      = urlencode("<span class="icon-err">✗</span> Bonjour {$ownerName},\n\nVotre paiement LionTech pour *{$bizName}* a été *rejeté*.\n\n<span class="icon-list">≡</span> Raison: {$fullReason}\n\nVeuillez soumettre un nouveau paiement ou nous contacter.\n— Tally Business Manager");
            $waUrl      = "https://wa.me/{$ownerPhone}?text={$waMsg}";

            $message    = 'Paiement rejeté.';
            $messageType= 'success';
            $_SESSION['wa_notify_url']  = $waUrl;
            $_SESSION['wa_notify_name'] = $ownerName;
        } catch (Throwable $ex) {
            $message = 'Erreur: ' . $ex->getMessage(); $messageType = 'error';
        }
    }
}

/* WhatsApp notification link from previous action */
$waUrl  = $_SESSION['wa_notify_url']  ?? null;
$waName = $_SESSION['wa_notify_name'] ?? null;
unset($_SESSION['wa_notify_url'], $_SESSION['wa_notify_name']);

/* Load payments */
$pending  = [];
$reviewed = [];
try {
    $stmt = $pdo->query("SELECT lp.*, b.business_name, b.phone AS biz_phone, u.full_name AS owner_name, u.phone AS owner_phone FROM liontech_payments lp JOIN businesses b ON b.business_id=lp.business_id LEFT JOIN users u ON u.business_id=lp.business_id AND u.role='business_owner' WHERE lp.status='pending' ORDER BY lp.created_at ASC");
    $pending = $stmt->fetchAll();
} catch (Throwable $e) {}
try {
    $stmt = $pdo->query("SELECT lp.*, b.business_name, u.full_name AS owner_name, adm.full_name AS approved_by_name FROM liontech_payments lp JOIN businesses b ON b.business_id=lp.business_id LEFT JOIN users u ON u.business_id=lp.business_id AND u.role='business_owner' LEFT JOIN users adm ON adm.user_id=lp.approved_by WHERE lp.status <> 'pending' ORDER BY lp.approved_at DESC LIMIT 30");
    $reviewed = $stmt->fetchAll();
} catch (Throwable $e) {}

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Admin') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);

$methodLabels = [
    'orange_money'  => 'Orange Money',
    'mtn_momo'      => 'MTN MoMo',
    'bank_transfer' => 'Virement Bancaire',
    'cash'          => 'Espèces',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Valider Paiements — LionTech</title>
  <link rel="stylesheet" href="super_admin.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="sa-layout">

  <?php $url = APP_URL; include __DIR__ . '/_sidebar.php'; ?>

  <div class="sa-main">
    <header class="sa-topbar">
      <button class="sa-hamburger" id="sa-hamburger"><?= saIcon('menu') ?></button>
      <div style="font-size:16px;font-weight:700;color:#0B1F3A" data-i18n="pr_title">Validation des Paiements</div>
      <div class="sa-topbar-right">
        <?php if (count($pending) > 0): ?>
        <span style="background:#FEF3C7;color:#92400E;border-radius:50px;padding:5px 14px;font-size:12px;font-weight:700"><?= count($pending) ?> en attente</span>
        <?php endif; ?>
      </div>
    </header>

    <main class="sa-content">

      <?php if ($message): ?>
      <div style="background:<?= $messageType==='success'?'#F0FDF4':'#FEF2F2' ?>;border:1px solid <?= $messageType==='success'?'#86EFAC':'#FECACA' ?>;border-radius:12px;padding:13px 18px;margin-bottom:16px;font-size:13.5px;color:<?= $messageType==='success'?'#166534':'#991B1B' ?>">
        <?= e($message) ?>
      </div>
      <?php endif; ?>

      <?php if ($waUrl): ?>
      <!-- WhatsApp notification prompt -->
      <div style="background:#ECFDF5;border:2px solid #1A9E7A;border-radius:14px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px">
        <span style="display:flex;align-items:center;color:#1A9E7A"><?= saIcon('message', 28) ?></span>
        <div style="flex:1">
          <div style="font-size:14px;font-weight:700;color:#0B1F3A">Notifier <?= e($waName) ?> sur WhatsApp</div>
          <div style="font-size:13px;color:#6B7280;margin-top:3px">Cliquez pour envoyer la notification au propriétaire.</div>
        </div>
        <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer"
          style="background:#25D366;color:#fff;padding:11px 20px;border-radius:10px;text-decoration:none;font-size:13.5px;font-weight:700;white-space:nowrap">
          Envoyer WhatsApp
        </a>
      </div>
      <?php endif; ?>

      <!-- Pending payments -->
      <div class="sa-card" style="margin-bottom:20px">
        <div class="sa-card-header">
          <div><div class="sa-card-title" data-i18n="pr_pending_title">Paiements en attente de validation</div><div class="sa-card-sub" data-i18n="pr_pending_sub">Vérifiez chaque preuve avant d'approuver</div></div>
          <span style="background:#FEF2F2;color:#DC2626;font-size:11px;font-weight:700;border-radius:50px;padding:3px 10px"><?= count($pending) ?> en attente</span>
        </div>

        <?php if (!$pending): ?>
        <div style="padding:32px;text-align:center;color:#6B7280;font-size:14px">Aucun paiement en attente.</div>
        <?php else: foreach ($pending as $p): ?>
        <div style="border:1.5px solid #E5E7EB;border-radius:14px;margin:16px;padding:20px">

          <!-- Payment header -->
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px">
            <div>
              <div style="font-size:15px;font-weight:700;color:#0B1F3A"><?= e($p['business_name']) ?></div>
              <div style="font-size:12px;color:#6B7280">Propriétaire: <?= e($p['owner_name'] ?? '—') ?> · <?= e($p['owner_phone'] ?? '—') ?></div>
            </div>
            <div style="text-align:right">
              <div style="font-size:20px;font-weight:900;color:#0B1F3A"><?= number_format((float)$p['amount'],0,'.',' ') ?> XAF</div>
              <div style="font-size:12px;color:#6B7280"><?= $p['months_paid'] ?> mois · <?= $methodLabels[$p['payment_method']] ?? $p['payment_method'] ?></div>
            </div>
          </div>

          <!-- Details grid -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div style="background:#F8FAFC;border-radius:10px;padding:12px">
              <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:4px">Réf. Transaction</div>
              <div style="font-size:14px;font-weight:700;color:#0B1F3A;font-family:monospace"><?= e($p['transaction_reference'] ?? '—') ?></div>
            </div>
            <div style="background:#F8FAFC;border-radius:10px;padding:12px">
              <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:4px">Soumis le</div>
              <div style="font-size:14px;font-weight:600;color:#0B1F3A"><?= e(date('d/m/Y H:i', strtotime($p['created_at']))) ?></div>
            </div>
          </div>

          <!-- Screenshot -->
          <?php if ($p['proof_image_url']): ?>
          <div style="margin-bottom:16px">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:8px">Preuve / Screenshot</div>
            <a href="<?= e(APP_URL . '/' . $p['proof_image_url']) ?>" target="_blank">
              <img src="<?= e(APP_URL . '/' . $p['proof_image_url']) ?>" alt="Preuve"
                style="max-width:100%;max-height:300px;border-radius:10px;border:2px solid #E5E7EB;object-fit:contain;display:block"/>
            </a>
            <div style="font-size:11px;color:#6B7280;margin-top:4px">Cliquez sur l'image pour l'agrandir</div>
          </div>
          <?php else: ?>
          <div style="display:flex;align-items:center;gap:8px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:10px 14px;font-size:13px;color:#92400E;margin-bottom:16px">
            <?= saIcon('warning') ?> Aucune preuve uploadée — paiement en espèces ou preuve manquante
          </div>
          <?php endif; ?>

          <!-- Admin checklist -->
          <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:14px;margin-bottom:16px">
            <div style="font-size:12px;font-weight:700;color:#1E40AF;margin-bottom:8px">Checklist de vérification</div>
            <div style="display:flex;flex-direction:column;gap:6px;font-size:12.5px;color:#1E40AF">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox"/> Montant sur screenshot = <?= number_format((float)$p['amount'],0,'.',' ') ?> XAF</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox"/> Numéro de transaction correspond</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox"/> Date du screenshot est récente (moins de 48h)</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox"/> Screenshot paraît authentique (non modifié)</label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox"/> Paiement reçu sur notre compte</label>
            </div>
          </div>

          <!-- Action buttons -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">

            <!-- Approve -->
            <form method="POST">
              <input type="hidden" name="action" value="approve"/>
              <input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>"/>
              <button type="submit"
                onclick="return confirm('Confirmer l\'approbation de ce paiement?')"
                style="width:100%;padding:13px;background:#166534;color:#fff;border:none;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">
                Approuver — Activer abonnement
              </button>
            </form>

            <!-- Reject -->
            <form method="POST" id="reject-form-<?= (int)$p['payment_id'] ?>">
              <input type="hidden" name="action" value="reject"/>
              <input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>"/>
              <div style="display:flex;flex-direction:column;gap:8px">
                <select name="rejection_reason" required
                  style="width:100%;padding:10px 13px;border:1.5px solid #FECACA;border-radius:10px;font-size:13px;font-family:inherit;background:#FEF2F2">
                  <option value="">— Raison du rejet —</option>
                  <?php foreach ($REJECTION_REASONS as $r): ?>
                  <option value="<?= e($r) ?>"><?= e($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="rejection_detail"
                  placeholder="Détail supplémentaire (optionnel)..."
                  style="width:100%;padding:9px 13px;border:1.5px solid #FECACA;border-radius:10px;font-size:13px;font-family:inherit;box-sizing:border-box"/>
                <button type="submit"
                  onclick="return confirm('Rejeter ce paiement?')"
                  style="width:100%;padding:11px;background:#FEF2F2;color:#991B1B;border:1.5px solid #FECACA;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">
                  Rejeter
                </button>
              </div>
            </form>

          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Reviewed payments history -->
      <div class="sa-card" style="padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9">
          <div class="sa-card-title">Historique des validations</div>
        </div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Business</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Montant</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Méthode</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Statut</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Traité par</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase">Date</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($reviewed): foreach ($reviewed as $r): ?>
            <tr style="border-bottom:1px solid #F1F5F9">
              <td style="padding:10px 14px;font-weight:600"><?= e($r['business_name']) ?></td>
              <td style="padding:10px 14px"><?= number_format((float)$r['amount'],0,'.',' ') ?> XAF</td>
              <td style="padding:10px 14px"><?= e($methodLabels[$r['payment_method']] ?? $r['payment_method']) ?></td>
              <td style="padding:10px 14px">
                <span style="padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;background:<?= $r['status']==='approved'?'#DCFCE7':'#FEE2E2' ?>;color:<?= $r['status']==='approved'?'#166534':'#991B1B' ?>">
                  <?= $r['status']==='approved'?'Approuvé':'Rejeté' ?>
                </span>
              </td>
              <td style="padding:10px 14px;color:#6B7280"><?= e($r['approved_by_name'] ?? '—') ?></td>
              <td style="padding:10px 14px;font-size:12px;color:#6B7280"><?= e($r['approved_at'] ? date('d/m/Y H:i', strtotime($r['approved_at'])) : '—') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="padding:28px;text-align:center;color:#6B7280">Aucun paiement traité.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>
<script>
const _sa_sidebar  = document.getElementById('sa-sidebar');
const _sa_overlay  = document.getElementById('sa-overlay');
const _sa_hamburger = document.getElementById('sa-hamburger');
const _sa_close    = document.getElementById('sa-sidebar-close');
function _sa_open()  { _sa_sidebar.classList.add('open');    _sa_overlay.classList.add('active'); }
function _sa_close_fn() { _sa_sidebar.classList.remove('open'); _sa_overlay.classList.remove('active'); }
if (_sa_hamburger) _sa_hamburger.addEventListener('click', _sa_open);
if (_sa_close)     _sa_close.addEventListener('click', _sa_close_fn);
if (_sa_overlay)   _sa_overlay.addEventListener('click', _sa_close_fn);
</script>
</body>
</html>