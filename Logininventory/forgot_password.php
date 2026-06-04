<?php
/* ============================================================
   forgot_password.php — LionTech Business Manager
   Reset PIN/password via security questions.
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\forgot_password.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

/* Already logged in → go to dashboard */
if (isLoggedIn()) {
    $routes = json_decode(DASHBOARD_ROUTES, true);
    header('Location: ' . APP_URL . '/' . ($routes[$_SESSION['role']] ?? 'Logininventory/login.php'));
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$MAX_ATTEMPTS = 3;

$QUESTIONS_MAP = [
    'Quel est le nom de votre première école ?'
        => 'What was the name of your first school?',
    'Quel est le nom de jeune fille de votre mère ?'
        => "What is your mother's maiden name?",
    'Quelle est la ville de naissance de votre père ?'
        => 'What city was your father born in?',
    'Quel était le nom de votre premier animal de compagnie ?'
        => 'What was the name of your first pet?',
    'Quel est votre plat préféré ?'
        => 'What is your favourite food?',
    'Dans quelle ville avez-vous grandi ?'
        => 'What city did you grow up in?',
    'Quel est le surnom que vous aviez enfant ?'
        => 'What was your childhood nickname?',
    "Quel est le prénom de votre meilleur(e) ami(e) d'enfance ?"
        => 'What is the first name of your childhood best friend?',
];

$pdo      = getDB();
$stage    = $_SESSION['fp_stage']   ?? 'identify';
$fpUserId = $_SESSION['fp_user_id'] ?? null;
$error    = '';

/* ══════════════════════════════════════
   STAGE: identify — find user by login ID
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'identify') {

    $loginId = trim($_POST['login_id'] ?? '');

    if ($loginId === '') {
        $error = 'Veuillez entrer votre identifiant. / Please enter your login ID.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, role, full_name, security_flagged
             FROM users
             WHERE login_id = ? AND status = "active"
             LIMIT 1'
        );
        $stmt->execute([$loginId]);
        $found = $stmt->fetch();

        if (!$found) {
            $error = 'Identifiant introuvable. / Login ID not found.';

        } elseif ($found['security_flagged']) {
            $error = 'Votre compte est verrouillé. Contactez votre responsable ou LionTech. /
                      Your account is locked. Contact your owner or LionTech.';

        } else {
            $foundRole = $found['role'];
            $foundId   = (int)$found['user_id'];

            /* ── Super Admin: auto-generate temp PIN ── */
            if ($foundRole === ROLE_SUPER_ADMIN) {
                $tempPin = (string)random_int(100000, 999999);
                $hash    = password_hash($tempPin, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?')
                    ->execute([$hash, $foundId]);
                try {
                    $pdo->prepare('DELETE FROM security_questions WHERE user_id = ?')
                        ->execute([$foundId]);
                } catch (Throwable $e) {}
                $_SESSION['fp_stage']    = 'done_admin';
                $_SESSION['fp_temp_pin'] = $tempPin;
                $_SESSION['fp_name']     = $found['full_name'];
                header('Location: forgot_password.php');
                exit;
            }

            /* ── Employee: cannot self-reset ── */
            if ($foundRole === ROLE_EMPLOYEE) {
                $_SESSION['fp_stage'] = 'employee_contact';
                header('Location: forgot_password.php');
                exit;
            }

            /* ── Owner / Manager: check security questions ── */
            $stmt = $pdo->prepare('SELECT * FROM security_questions WHERE user_id = ? LIMIT 1');
            $stmt->execute([$foundId]);
            $sq = $stmt->fetch();

            if (!$sq) {
                $error = 'Aucune question de sécurité configurée. Contactez LionTech. /
                          No security questions set up. Contact LionTech.';
            } else {
                $_SESSION['fp_stage']   = 'questions';
                $_SESSION['fp_user_id'] = $foundId;
                $_SESSION['fp_sq']      = $sq;
                header('Location: forgot_password.php');
                exit;
            }
        }
    }
}

/* ══════════════════════════════════════
   STAGE: questions — verify answers
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'questions') {

    $sq  = $_SESSION['fp_sq']      ?? [];
    $uid = (int)($_SESSION['fp_user_id'] ?? 0);

    $a1 = strtolower(trim($_POST['answer_1'] ?? ''));
    $a2 = strtolower(trim($_POST['answer_2'] ?? ''));
    $a3 = strtolower(trim($_POST['answer_3'] ?? ''));

    $ok1 = password_verify($a1, $sq['answer_1_hash'] ?? '');
    $ok2 = password_verify($a2, $sq['answer_2_hash'] ?? '');
    $ok3 = password_verify($a3, $sq['answer_3_hash'] ?? '');

    if ($ok1 && $ok2 && $ok3) {
        $pdo->prepare('UPDATE security_questions SET failed_attempts = 0 WHERE user_id = ?')
            ->execute([$uid]);
        $_SESSION['fp_stage']   = 'reset';
        $_SESSION['fp_user_id'] = $uid;
        header('Location: forgot_password.php');
        exit;
    } else {
        $pdo->prepare('UPDATE security_questions SET failed_attempts = failed_attempts + 1 WHERE user_id = ?')
            ->execute([$uid]);
        $stmt = $pdo->prepare('SELECT failed_attempts FROM security_questions WHERE user_id = ?');
        $stmt->execute([$uid]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= $MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE users SET security_flagged = 1 WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('UPDATE security_questions SET is_flagged = 1 WHERE user_id = ?')->execute([$uid]);
            unset($_SESSION['fp_stage'], $_SESSION['fp_user_id'], $_SESSION['fp_sq']);
            $_SESSION['fp_stage'] = 'locked';
            header('Location: forgot_password.php');
            exit;
        }

        $remaining = $MAX_ATTEMPTS - $attempts;
        $error = "Réponses incorrectes. Il vous reste <strong>{$remaining}</strong> tentative(s). /
                  Wrong answers. <strong>{$remaining}</strong> attempt(s) left.";
    }
}

/* ══════════════════════════════════════
   STAGE: reset — save new PIN
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'reset') {

    $uid        = (int)($_SESSION['fp_user_id'] ?? 0);
    $newPin     = trim($_POST['new_pin']     ?? '');
    $confirmPin = trim($_POST['confirm_pin'] ?? '');

    if (strlen($newPin) < 6) {
        $error = 'Le PIN doit contenir au moins 6 caractères. / PIN must be at least 6 characters.';
    } elseif ($newPin !== $confirmPin) {
        $error = 'Les PIN ne correspondent pas. / PINs do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?')
            ->execute([password_hash($newPin, PASSWORD_BCRYPT), $uid]);
        $pdo->prepare('UPDATE users SET security_flagged = 0 WHERE user_id = ?')
            ->execute([$uid]);
        unset($_SESSION['fp_stage'], $_SESSION['fp_user_id'], $_SESSION['fp_sq']);
        $_SESSION['fp_stage'] = 'done';
        header('Location: forgot_password.php');
        exit;
    }
}

/* Read stage after redirects */
$stage = $_SESSION['fp_stage'] ?? 'identify';
$sq    = $_SESSION['fp_sq']    ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Mot de passe oublié — LionTech</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    body {
      margin: 0;
      font-family: Inter, 'Segoe UI', sans-serif;
      background: #F0F4F8;
      color: #0F172A;
    }
    .fp-wrap {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
    }
    .fp-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 16px 48px rgba(11,31,58,.1);
      width: 100%;
      max-width: 520px;
      overflow: hidden;
    }
    .fp-head {
      background: #0B1F3A;
      padding: 24px 30px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .fp-logo-icon {
      width: 42px;
      height: 42px;
      border-radius: 11px;
      background: rgba(212,160,23,.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 21px;
    }
    .fp-logo-name { font-size: 16px; font-weight: 800; color: #fff; }
    .fp-logo-tag  { font-size: 11px; color: #D4A017; }
    .fp-body { padding: 28px 30px; }
    .fp-title {
      font-size: 19px;
      font-weight: 800;
      color: #0B1F3A;
      margin-bottom: 5px;
    }
    .fp-sub {
      font-size: 13px;
      color: #6B7280;
      margin-bottom: 20px;
      line-height: 1.7;
    }
    .field { margin-bottom: 14px; }
    .field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #0B1F3A;
      margin-bottom: 5px;
    }
    .field label em { font-weight: 400; color: #6B7280; }
    .field input {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #E5E7EB;
      border-radius: 11px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      box-sizing: border-box;
      transition: border-color .15s;
    }
    .field input:focus { border-color: #0B1F3A; }
    .q-block {
      background: #F8FAFC;
      border: 1.5px solid #E5E7EB;
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 12px;
    }
    .q-fr { font-size: 13.5px; font-weight: 700; color: #0B1F3A; margin-bottom: 1px; }
    .q-en { font-size: 12px; color: #6B7280; margin-bottom: 10px; }
    .btn {
      width: 100%;
      padding: 13px;
      border: none;
      border-radius: 11px;
      font-size: 14px;
      font-weight: 800;
      cursor: pointer;
      font-family: inherit;
      text-align: center;
      text-decoration: none;
      display: block;
      box-sizing: border-box;
    }
    .btn-primary { background: #0B1F3A; color: #fff; }
    .btn-primary:hover { opacity: .9; }
    .btn-outline {
      background: #fff;
      color: #0B1F3A;
      border: 1.5px solid #E5E7EB;
      margin-top: 10px;
    }
    .alert-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      border-radius: 10px;
      padding: 11px 14px;
      font-size: 13px;
      color: #991B1B;
      margin-bottom: 14px;
    }
    .alert-success {
      background: #F0FDF4;
      border: 1px solid #86EFAC;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: 13.5px;
      color: #166534;
      margin-bottom: 14px;
    }
    .alert-warning {
      background: #FEF3C7;
      border: 1px solid #FDE68A;
      border-radius: 10px;
      padding: 14px 16px;
      font-size: 13.5px;
      color: #92400E;
      margin-bottom: 14px;
    }
    .pin-display {
      background: #0B1F3A;
      border-radius: 14px;
      padding: 20px;
      text-align: center;
      margin: 16px 0;
    }
    .pin-display .pin-val {
      font-size: 36px;
      font-weight: 900;
      color: #D4A017;
      letter-spacing: 8px;
    }
    .pin-display p {
      font-size: 12.5px;
      color: rgba(255,255,255,.7);
      margin: 6px 0 0;
    }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 12px;
      font-size: 13px;
      color: #6B7280;
      text-decoration: none;
    }
    .back-link:hover { color: #0B1F3A; }
  </style>
</head>
<body>
<div class="fp-wrap">
  <div class="fp-card">

    <!-- Header -->
    <div class="fp-head">
     <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
      <div>
        <div class="fp-logo-name">LionTech</div>
        <div class="fp-logo-tag">Business Manager</div>
      </div>
    </div>

    <!-- Body -->
    <div class="fp-body">

      <?php if ($stage === 'identify'): ?>
      <!-- ── STEP 1: Enter login ID ── -->
      <div class="fp-title">Mot de passe oublié</div>
      <div class="fp-sub">
        <strong>Entrez votre identifiant de connexion.</strong><br>
        <em>Enter your login ID.</em>
      </div>

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= $error ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>
            <strong>Identifiant / Login ID</strong><br>
            <em>Email, téléphone, username ou code employé</em>
          </label>
          <input
            type="text"
            name="login_id"
            required
            autocomplete="username"
            autocapitalize="none"
            placeholder="Votre identifiant..."
          />
        </div>
        <button type="submit" class="btn btn-primary">
          Continuer / Continue →
        </button>
      </form>
      <a class="back-link" href="login.php">← Retour connexion / Back to login</a>

      <?php elseif ($stage === 'questions'): ?>
      <!-- ── STEP 2: Answer security questions ── -->
      <div class="fp-title">Questions de sécurité</div>
      <div class="fp-sub">
        <strong>Répondez à vos 3 questions de sécurité.</strong><br>
        <em>Answer your 3 security questions. Answers are case-insensitive.</em>
      </div>

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= $error ?></div>
      <?php endif; ?>

      <form method="POST">
        <?php
        $qKeys = ['question_1', 'question_2', 'question_3'];
        $aKeys = ['answer_1',   'answer_2',   'answer_3'];
        for ($i = 0; $i < 3; $i++):
            $qFr = $sq[$qKeys[$i]] ?? '';
            $qEn = $QUESTIONS_MAP[$qFr] ?? $qFr;
        ?>
        <div class="q-block">
          <div class="q-fr"><strong><?= e($qFr) ?></strong></div>
          <div class="q-en"><?= e($qEn) ?></div>
          <div class="field" style="margin-bottom:0">
            <label>
              <strong>Votre réponse</strong> /
              <em>Your answer</em>
            </label>
            <input
              type="text"
              name="<?= $aKeys[$i] ?>"
              required
              autocomplete="off"
              placeholder="Réponse..."
            />
          </div>
        </div>
        <?php endfor; ?>
        <button type="submit" class="btn btn-primary">
          Vérifier / Verify →
        </button>
      </form>
      <a class="back-link" href="login.php">← Retour connexion / Back to login</a>

      <?php elseif ($stage === 'reset'): ?>
      <!-- ── STEP 3: Set new PIN ── -->
      <div class="fp-title">Nouveau PIN / mot de passe</div>
      <div class="fp-sub">
        <strong>Identité vérifiée ✅</strong> Définissez un nouveau PIN.<br>
        <em>Identity verified. Set a new PIN.</em>
      </div>

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= $error ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label>
            <strong>Nouveau PIN / mot de passe</strong><br>
            <em>New PIN / password — minimum 6 characters</em>
          </label>
          <input
            type="password"
            name="new_pin"
            minlength="6"
            required
            placeholder="Minimum 6 caractères"
          />
        </div>
        <div class="field">
          <label>
            <strong>Confirmer le PIN</strong><br>
            <em>Confirm PIN</em>
          </label>
          <input
            type="password"
            name="confirm_pin"
            minlength="6"
            required
            placeholder="Répéter le PIN"
          />
        </div>
        <button type="submit" class="btn btn-primary">
          ✅ Sauvegarder / Save
        </button>
      </form>

      <?php elseif ($stage === 'done'): ?>
      <!-- ── SUCCESS ── -->
      <div class="alert-success">
        ✅ <strong>PIN changé avec succès !</strong><br>
        <em>PIN changed successfully!</em>
      </div>
      <p style="font-size:13.5px;color:#6B7280;text-align:center;line-height:1.7">
        Vous pouvez maintenant vous connecter avec votre nouveau PIN.<br>
        <em>You can now login with your new PIN.</em>
      </p>
      <a href="login.php" class="btn btn-primary">🔑 Se connecter / Login</a>
      <?php unset($_SESSION['fp_stage']); ?>

      <?php elseif ($stage === 'done_admin'): ?>
      <!-- ── SUPER ADMIN TEMP PIN ── -->
      <div class="fp-title">PIN temporaire Super Admin</div>
      <div class="fp-sub">
        <strong>Voici votre PIN temporaire.</strong><br>
        <em>Here is your temporary PIN. Change it immediately after login.</em>
      </div>
      <div class="pin-display">
        <div class="pin-val"><?= e($_SESSION['fp_temp_pin'] ?? '——') ?></div>
        <p>PIN temporaire — à changer immédiatement / Change immediately</p>
      </div>
      <a href="login.php" class="btn btn-primary">🔑 Se connecter / Login</a>
      <?php unset($_SESSION['fp_temp_pin'], $_SESSION['fp_name'], $_SESSION['fp_stage']); ?>

      <?php elseif ($stage === 'employee_contact'): ?>
      <!-- ── EMPLOYEE: contact owner ── -->
      <div class="fp-title">Réinitialisation employé</div>
      <div class="alert-warning">
        👤 <strong>Contactez votre propriétaire ou manager</strong>
        pour réinitialiser votre PIN.<br><br>
        <em>Contact your owner or manager to reset your PIN.</em>
      </div>
      <p style="font-size:13px;color:#6B7280;line-height:1.7;margin-bottom:18px">
        Pour des raisons de sécurité, les employés ne peuvent pas
        réinitialiser leur propre PIN sans l'accord du propriétaire.<br>
        <em>For security reasons, employees cannot self-reset their PIN.</em>
      </p>
      <a
        href="https://wa.me/237688203095?text=Bonjour%20LionTech%20%F0%9F%91%8B%0AUn%20employ%C3%A9%20a%20besoin%20d'aide%20pour%20r%C3%A9initialiser%20son%20PIN."
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-primary"
      >
        💬 Contacter LionTech sur WhatsApp
      </a>
      <a href="login.php" class="back-link">← Retour / Back to login</a>
      <?php unset($_SESSION['fp_stage']); ?>

      <?php elseif ($stage === 'locked'): ?>
      <!-- ── ACCOUNT LOCKED ── -->
      <div class="fp-title">Compte verrouillé 🔒</div>
      <div class="alert-error">
        <strong>Votre compte a été verrouillé</strong> après
        <?= $MAX_ATTEMPTS ?> tentatives incorrectes.<br><br>
        <em>Your account has been locked after <?= $MAX_ATTEMPTS ?> incorrect attempts.</em>
      </div>
      <p style="font-size:13px;color:#6B7280;line-height:1.7;margin-bottom:18px">
        Contactez votre propriétaire (employé/manager) ou LionTech (propriétaire)
        pour déverrouiller votre compte.<br>
        <em>Contact your owner (employee/manager) or LionTech (owner) to unlock.</em>
      </p>
      <a
        href="https://wa.me/237688203095?text=Bonjour%20LionTech%20%F0%9F%91%8B%0AMon%20compte%20est%20verrouill%C3%A9.%0A%0ANom%3A%20%0ABusiness%3A%20%0A"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-primary"
      >
        💬 Contacter LionTech sur WhatsApp
      </a>
      <a href="login.php" class="back-link">← Retour / Back to login</a>
      <?php unset($_SESSION['fp_stage']); ?>

      <?php endif; ?>

    </div><!-- /.fp-body -->
  </div><!-- /.fp-card -->
</div><!-- /.fp-wrap -->
</body>
</html>