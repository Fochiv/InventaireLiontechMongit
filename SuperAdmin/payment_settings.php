<?php
/* ============================================================
   payment_settings.php — LionTech Super Admin
   Manage payment collection numbers and bank details.
   Super admin must enter their name before saving changes.
   Path: C:\Xampp\htdocs\InventoryLiontech\SuperAdmin\payment_settings.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireRole([ROLE_SUPER_ADMIN]);

$user = currentUser();
$pdo  = getDB();

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Load current settings */
$settings = [];
try {
    $stmt = $pdo->query('SELECT * FROM payment_settings ORDER BY setting_id ASC LIMIT 1');
    $settings = $stmt->fetch() ?: [];
} catch (Throwable $ex) {}

/* Load audit log */
$logs = [];
try {
    $stmt = $pdo->query('SELECT * FROM payment_settings_log ORDER BY created_at DESC LIMIT 20');
    $logs = $stmt->fetchAll();
} catch (Throwable $ex) {}

$success = '';
$error   = '';

/* ── Save changes ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmedName = trim($_POST['confirmed_name'] ?? '');

    if ($confirmedName === '') {
        $error = 'Veuillez entrer votre nom avant de sauvegarder. / Please enter your name before saving.';
    } else {
        $fields = [
            'orange_money_number' => trim($_POST['orange_money_number'] ?? ''),
            'orange_money_name'   => trim($_POST['orange_money_name']   ?? ''),
            'mtn_momo_number'     => trim($_POST['mtn_momo_number']     ?? ''),
            'mtn_momo_name'       => trim($_POST['mtn_momo_name']       ?? ''),
            'bank_name'           => trim($_POST['bank_name']           ?? ''),
            'bank_account_number' => trim($_POST['bank_account_number'] ?? ''),
            'bank_account_holder' => trim($_POST['bank_account_holder'] ?? ''),
            'bank_branch'         => trim($_POST['bank_branch']         ?? ''),
        ];

        try {
            /* Log each changed field */
            foreach ($fields as $field => $newVal) {
                $oldVal = $settings[$field] ?? '';
                if ($oldVal !== $newVal) {
                    $pdo->prepare('INSERT INTO payment_settings_log
                        (changed_by_name, user_id, field_changed, old_value, new_value, ip_address)
                        VALUES (?,?,?,?,?,?)')
                        ->execute([
                            $confirmedName,
                            $user['user_id'],
                            $field,
                            $oldVal,
                            $newVal,
                            $_SERVER['REMOTE_ADDR'] ?? null
                        ]);
                }
            }

            /* Upsert settings */
            if (empty($settings)) {
                $pdo->prepare('INSERT INTO payment_settings
                    (orange_money_number, orange_money_name, mtn_momo_number, mtn_momo_name,
                     bank_name, bank_account_number, bank_account_holder, bank_branch,
                     updated_by_name, updated_by_user_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([...array_values($fields), $confirmedName, $user['user_id']]);
            } else {
                $pdo->prepare('UPDATE payment_settings SET
                    orange_money_number=?, orange_money_name=?,
                    mtn_momo_number=?, mtn_momo_name=?,
                    bank_name=?, bank_account_number=?, bank_account_holder=?, bank_branch=?,
                    updated_by_name=?, updated_by_user_id=?
                    WHERE setting_id=?')
                    ->execute([...array_values($fields), $confirmedName, $user['user_id'], $settings['setting_id']]);
            }

            /* Reload */
            $stmt = $pdo->query('SELECT * FROM payment_settings ORDER BY setting_id ASC LIMIT 1');
            $settings = $stmt->fetch() ?: [];
            $stmt = $pdo->query('SELECT * FROM payment_settings_log ORDER BY created_at DESC LIMIT 20');
            $logs = $stmt->fetchAll();

            $success = "Paramètres sauvegardés par {$confirmedName}.";
        } catch (Throwable $ex) {
            $error = 'Erreur: ' . $ex->getMessage();
        }
    }
}

$initials = '';
foreach (explode(' ', $user['full_name'] ?: 'Admin') as $w) $initials .= strtoupper($w[0] ?? '');
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Paramètres Paiement — LionTech</title>
  <link rel="stylesheet" href="super_admin.css"/>
<link rel="stylesheet" href="<?= APP_URL ?>/icons.css">
</head>
<body>
<div class="sa-layout">

  <?php $url = APP_URL; include __DIR__ . '/_sidebar.php'; ?>

  <div class="sa-main">
    <header class="sa-topbar">
      <button class="sa-hamburger" id="sa-hamburger"><?= saIcon('menu') ?></button>
      <div style="font-size:16px;font-weight:700;color:#0B1F3A" data-i18n="ps_title">Paramètres de Paiement</div>
      <div class="sa-topbar-right">
        <div class="sa-profile-av"><?= e($initials) ?></div>
      </div>
    </header>

    <main class="sa-content">

      <?php if ($success): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:13.5px;color:#166534">
        <?= e($success) ?>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div style="display:flex;align-items:center;gap:8px;background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:13.5px;color:#991B1B">
        <?= saIcon('warning') ?> <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

          <!-- Orange Money -->
          <div class="sa-card">
            <div class="sa-card-header">
              <div>
                <div class="sa-card-title" data-i18n="ps_om">Orange Money</div>
                <div class="sa-card-sub">Numéro Orange Money de LionTech</div>
              </div>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px">
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Numéro Orange Money</label>
                <input type="text" name="orange_money_number"
                  value="<?= e($settings['orange_money_number'] ?? '') ?>"
                  placeholder="Ex: +237 6XX XXX XXX"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Nom du compte Orange</label>
                <input type="text" name="orange_money_name"
                  value="<?= e($settings['orange_money_name'] ?? '') ?>"
                  placeholder="Ex: LionTech Sarl"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
            </div>
          </div>

          <!-- MTN MoMo -->
          <div class="sa-card">
            <div class="sa-card-header">
              <div>
                <div class="sa-card-title">MTN Mobile Money</div>
                <div class="sa-card-sub">Numéro MTN MoMo de LionTech</div>
              </div>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px">
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Numéro MTN MoMo</label>
                <input type="text" name="mtn_momo_number"
                  value="<?= e($settings['mtn_momo_number'] ?? '') ?>"
                  placeholder="Ex: +237 6XX XXX XXX"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Nom du compte MTN</label>
                <input type="text" name="mtn_momo_name"
                  value="<?= e($settings['mtn_momo_name'] ?? '') ?>"
                  placeholder="Ex: LionTech Sarl"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
            </div>
          </div>

          <!-- Bank -->
          <div class="sa-card" style="grid-column:1/-1">
            <div class="sa-card-header">
              <div>
                <div class="sa-card-title">Virement Bancaire</div>
                <div class="sa-card-sub">Coordonnées bancaires de LionTech</div>
              </div>
            </div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Nom de la banque</label>
                <input type="text" name="bank_name"
                  value="<?= e($settings['bank_name'] ?? '') ?>"
                  placeholder="Ex: Afriland First Bank"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Numéro de compte</label>
                <input type="text" name="bank_account_number"
                  value="<?= e($settings['bank_account_number'] ?? '') ?>"
                  placeholder="Ex: 00123456789"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Titulaire du compte</label>
                <input type="text" name="bank_account_holder"
                  value="<?= e($settings['bank_account_holder'] ?? '') ?>"
                  placeholder="Ex: LionTech Sarl"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
              <div>
                <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Agence / Ville</label>
                <input type="text" name="bank_branch"
                  value="<?= e($settings['bank_branch'] ?? '') ?>"
                  placeholder="Ex: Agence Akwa, Douala"
                  style="width:100%;padding:10px 13px;border:1.5px solid #E5E7EB;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
              </div>
            </div>
          </div>

        </div>

        <!-- Confirmation box -->
        <div class="sa-card" style="margin-top:20px;border:2px solid #D4A017">
          <div class="sa-card-header" style="background:#FFFBEB">
            <div>
              <div class="sa-card-title" style="color:#92400E;display:flex;align-items:center;gap:6px"><?= saIcon('lock') ?> Confirmation requise</div>
              <div class="sa-card-sub">Entrez votre nom complet pour valider les modifications. Ceci sera enregistré dans le journal d'audit.</div>
            </div>
          </div>
          <div style="padding:18px;display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:220px">
              <label style="font-size:12px;font-weight:700;color:#0B1F3A;display:block;margin-bottom:5px">Votre nom complet *</label>
              <input type="text" name="confirmed_name" required
                placeholder="Ex: Jean-Pierre Kamga"
                style="width:100%;padding:11px 14px;border:2px solid #D4A017;border-radius:10px;font-size:14px;font-family:inherit;box-sizing:border-box"/>
            </div>
            <button type="submit"
              style="padding:12px 28px;background:#0B1F3A;color:#fff;border:none;border-radius:11px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">
              Sauvegarder les modifications
            </button>
          </div>
        </div>

      </form>

      <!-- Audit log -->
      <?php if ($logs): ?>
      <div class="sa-card" style="margin-top:20px;padding:0;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #F1F5F9">
          <div class="sa-card-title">Journal des modifications</div>
          <div class="sa-card-sub">Historique des changements de paramètres de paiement</div>
        </div>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#F8FAFC;border-bottom:1.5px solid #E5E7EB">
                <th style="padding:10px 16px;text-align:left;color:#6B7280;font-size:11px;font-weight:700;text-transform:uppercase">Date</th>
                <th style="padding:10px 16px;text-align:left;color:#6B7280;font-size:11px;font-weight:700;text-transform:uppercase">Modifié par</th>
                <th style="padding:10px 16px;text-align:left;color:#6B7280;font-size:11px;font-weight:700;text-transform:uppercase">Champ</th>
                <th style="padding:10px 16px;text-align:left;color:#6B7280;font-size:11px;font-weight:700;text-transform:uppercase">Ancienne valeur</th>
                <th style="padding:10px 16px;text-align:left;color:#6B7280;font-size:11px;font-weight:700;text-transform:uppercase">Nouvelle valeur</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr style="border-bottom:1px solid #F1F5F9">
              <td style="padding:10px 16px;color:#6B7280;font-size:12px"><?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
              <td style="padding:10px 16px;font-weight:600"><?= e($log['changed_by_name']) ?></td>
              <td style="padding:10px 16px"><code style="background:#F1F5F9;padding:2px 6px;border-radius:4px;font-size:11px"><?= e($log['field_changed']) ?></code></td>
              <td style="padding:10px 16px;color:#991B1B"><?= e($log['old_value'] ?: '—') ?></td>
              <td style="padding:10px 16px;color:#166534;font-weight:600"><?= e($log['new_value'] ?: '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>
<script>
const _sa_sidebar   = document.getElementById('sa-sidebar');
const _sa_overlay   = document.getElementById('sa-overlay');
const _sa_hamburger = document.getElementById('sa-hamburger');
const _sa_close     = document.getElementById('sa-sidebar-close');
function _sa_open()     { _sa_sidebar.classList.add('open');    _sa_overlay.classList.add('active'); }
function _sa_close_fn() { _sa_sidebar.classList.remove('open'); _sa_overlay.classList.remove('active'); }
if (_sa_hamburger) _sa_hamburger.addEventListener('click', _sa_open);
if (_sa_close)     _sa_close.addEventListener('click', _sa_close_fn);
if (_sa_overlay)   _sa_overlay.addEventListener('click', _sa_close_fn);
</script>
</body>
</html>