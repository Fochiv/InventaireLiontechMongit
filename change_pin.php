<?php
/* ============================================================
   change_pin.php — LionTech Business Manager
   FIXED: all roles can change their own PIN
   Path: C:\Xampp\htdocs\InventoryLiontech\change_pin.php
   ============================================================ */
require_once __DIR__ . '/LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/mvp_helpers.php';

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

                $success = 'PIN changé avec succès. ✅';
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
</head>
<body>
<div class="od-layout">

  <?php include __DIR__ . '/LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php'; ?>

  <main class="od-main">

    <div class="od-topbar">
      <div class="od-business-title">
        <h1>Changer PIN</h1>
        <p>Sécurité du compte</p>
      </div>
      <div class="od-top-actions">
        <div class="od-avatar"><?= htmlspecialchars($initials) ?></div>
      </div>
    </div>

    <div style="padding:24px">

      <?php if ($success): ?>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:12px;padding:13px 18px;margin-bottom:20px;color:#166534;font-size:13.5px">
        <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:13px 18px;margin-bottom:20px;color:#991B1B;font-size:13.5px">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="od-card" style="max-width:520px;padding:32px">
        <div class="od-card-head">
          <div>
            <h2>Changer le PIN</h2>
            <p>Entrez votre ancien PIN puis choisissez un nouveau.</p>
          </div>
        </div>

        <form method="POST" style="display:flex;flex-direction:column;gap:18px;margin-top:4px">

          <div>
            <label style="display:block;font-size:12.5px;font-weight:600;color:#0B1F3A;margin-bottom:6px">
              Ancien PIN / mot de passe *
            </label>
            <input type="password" name="old_pin" required
              style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:14px;outline:none;font-family:inherit;box-sizing:border-box"/>
          </div>

          <div>
            <label style="display:block;font-size:12.5px;font-weight:600;color:#0B1F3A;margin-bottom:6px">
              Nouveau PIN *
            </label>
            <input type="password" name="new_pin" minlength="6" required
              style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:14px;outline:none;font-family:inherit;box-sizing:border-box"/>
          </div>

          <div>
            <label style="display:block;font-size:12.5px;font-weight:600;color:#0B1F3A;margin-bottom:6px">
              Confirmer nouveau PIN *
            </label>
            <input type="password" name="confirm_pin" minlength="6" required
              style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:14px;outline:none;font-family:inherit;box-sizing:border-box"/>
          </div>

          <button type="submit"
            style="background:#0B1F3A;color:#fff;border:none;padding:13px 28px;border-radius:13px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;width:fit-content">
            🔐 Changer le PIN
          </button>

        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>