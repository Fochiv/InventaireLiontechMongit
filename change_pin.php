<?php
/* ============================================================
   change_pin.php — Tally Business Manager
   FIXED: all roles can change their own PIN
   Path: C:\Xampp\htdocs\InventoryLiontech\change_pin.php
   ============================================================ */
require_once __DIR__ . '/LionTech_Complete_MVP_Remaining_Pages/mvp_helpers.php';

/* All roles can change their PIN — not just owner */
requireLogin();

$user       = lt_user();
$businessId = (int)($user['business_id'] ?? 0);
$pdo        = getDB();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPin     = trim($_POST['old_pin']     ?? '');
    $newPin     = trim($_POST['new_pin']     ?? '');
    $confirmPin = trim($_POST['confirm_pin'] ?? '');

    if ($oldPin === '' || $newPin === '' || $confirmPin === '') {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (strlen($newPin) < 6) {
        $error = 'Le nouveau PIN doit contenir au moins 6 caractères.';
    } elseif ($newPin !== $confirmPin) {
        $error = 'Les PIN ne correspondent pas.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([$user['user_id']]);
            $row = $stmt->fetch();

            /* Normalize $2b$ → $2y$ for PHP compatibility */
            $hash = $row['password_hash'] ?? '';
            if (str_starts_with($hash, '$2b$')) {
                $hash = '$2y$' . substr($hash, 4);
            }

            if (!password_verify($oldPin, $hash)) {
                $error = 'Ancien PIN incorrect.';
            } else {
                $newHash = password_hash($newPin, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?')
                    ->execute([$newHash, $user['user_id']]);

                /* Clear pin_must_change flag if employee */
                try {
                    $pdo->prepare('UPDATE employee_profiles SET pin_must_change = 0 WHERE user_id = ?')
                        ->execute([$user['user_id']]);
                } catch (Throwable $e) {}

                $success = 'PIN changé avec succès. <span class="icon-ok">✓</span>';
            }
        } catch (Throwable $ex) {
            $error = 'Erreur: ' . $ex->getMessage();
        }
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w)
    $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Changer PIN — LionTech</title>
  <link rel="icon" href="<?= APP_URL ?>/Image/TALLYLOGO.png"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/LionTech_Owner_Dashboard/owner_dashboard.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/icons.css"/>
  <style>
    .cp-wrap{max-width:540px;margin:24px auto 0;padding:0 16px 40px}
    .cp-card{background:#fff;border:1px solid #E5E7EB;border-radius:18px;box-shadow:0 4px 24px rgba(11,31,58,.07);padding:32px}
    .cp-field{display:flex;flex-direction:column;gap:5px;margin-bottom:18px}
    .cp-field label{font-size:12.5px;font-weight:700;color:#0B1F3A}
    .cp-field input{padding:12px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:14px;font-family:inherit;outline:none;transition:.15s;box-sizing:border-box;width:100%}
    .cp-field input:focus{border-color:#0B1F3A}
    .cp-alert{border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:13.5px;display:flex;align-items:flex-start;gap:9px}
    .cp-alert.success{background:#F0FDF4;border:1px solid #86EFAC;color:#166534}
    .cp-alert.error{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}
    .cp-btn{background:#0B1F3A;color:#fff;border:none;padding:13px 28px;border-radius:13px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px}
    .cp-btn:hover{background:#102d52}
    @media(max-width:640px){.cp-card{padding:22px 18px}.cp-wrap{padding:0 12px 40px}}
  </style>
<link rel="stylesheet" href="<?= APP_URL ?>/responsive_utils.css">
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/LionTech_Owner_Dashboard/Sidebar.php'; ?>

  <main class="od-main">

    <header class="od-topbar">
      <button class="od-menu-btn" id="od-menu-btn" aria-label="Menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="od-business-title">
        <h1>Changer PIN</h1>
        <p>Sécurité du compte</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= htmlspecialchars($initials) ?></div>
      </div>
    </header>

    <div class="cp-wrap">

      <?php if ($success): ?>
      <div class="cp-alert success">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="cp-alert error">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="cp-card">
        <h2 style="margin:0 0 4px;font-size:19px;color:#0B1F3A">Changer le PIN</h2>
        <p style="margin:0 0 22px;color:#6B7280;font-size:13.5px">Entrez votre ancien PIN puis choisissez un nouveau.</p>

        <form method="POST">
          <div class="cp-field">
            <label>Ancien PIN / mot de passe *</label>
            <input type="password" name="old_pin" required autocomplete="current-password"/>
          </div>
          <div class="cp-field">
            <label>Nouveau PIN *</label>
            <input type="password" name="new_pin" minlength="6" required autocomplete="new-password"/>
          </div>
          <div class="cp-field">
            <label>Confirmer nouveau PIN *</label>
            <input type="password" name="confirm_pin" minlength="6" required autocomplete="new-password"/>
          </div>
          <button type="submit" class="cp-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Changer le PIN
          </button>
        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>