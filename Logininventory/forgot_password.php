<?php
/* ============================================================
   forgot_password.php — Tally Business Manager
   Reset PIN/password via security questions.
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\forgot_password.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

if (isLoggedIn()) {
    $routes = json_decode(DASHBOARD_ROUTES, true);
    header('Location: ' . APP_URL . '/' . ($routes[$_SESSION['role']] ?? 'Logininventory/login.php'));
    exit;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$MAX_ATTEMPTS = 3;

/* FR question → EN translation map */
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
$errorCode = '';

/* ── IDENTIFY ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'identify') {
    $loginId = trim($_POST['login_id'] ?? '');
    if ($loginId === '') {
        $errorCode = 'err_empty';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, role, full_name, security_flagged FROM users WHERE login_id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$loginId]);
        $found = $stmt->fetch();

        if (!$found) {
            $errorCode = 'err_not_found';
        } elseif ($found['security_flagged']) {
            $errorCode = 'err_locked';
        } else {
            $foundRole = $found['role'];
            $foundId   = (int)$found['user_id'];

            if ($foundRole === ROLE_SUPER_ADMIN) {
                $tempPin = (string)random_int(100000, 999999);
                $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?')
                    ->execute([password_hash($tempPin, PASSWORD_BCRYPT), $foundId]);
                try { $pdo->prepare('DELETE FROM security_questions WHERE user_id = ?')->execute([$foundId]); } catch (Throwable $e) {}
                $_SESSION['fp_stage']    = 'done_admin';
                $_SESSION['fp_temp_pin'] = $tempPin;
                $_SESSION['fp_name']     = $found['full_name'];
                header('Location: forgot_password.php'); exit;
            }

            if ($foundRole === ROLE_EMPLOYEE) {
                $_SESSION['fp_stage'] = 'employee_contact';
                header('Location: forgot_password.php'); exit;
            }

            $stmt = $pdo->prepare('SELECT * FROM security_questions WHERE user_id = ? LIMIT 1');
            $stmt->execute([$foundId]);
            $sq = $stmt->fetch();

            if (!$sq) {
                $errorCode = 'err_no_questions';
            } else {
                $_SESSION['fp_stage']   = 'questions';
                $_SESSION['fp_user_id'] = $foundId;
                $_SESSION['fp_sq']      = $sq;
                header('Location: forgot_password.php'); exit;
            }
        }
    }
}

/* ── QUESTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'questions') {
    $sq  = $_SESSION['fp_sq']      ?? [];
    $uid = (int)($_SESSION['fp_user_id'] ?? 0);
    $a1  = strtolower(trim($_POST['answer_1'] ?? ''));
    $a2  = strtolower(trim($_POST['answer_2'] ?? ''));
    $a3  = strtolower(trim($_POST['answer_3'] ?? ''));

    $ok = password_verify($a1, $sq['answer_1_hash'] ?? '')
       && password_verify($a2, $sq['answer_2_hash'] ?? '')
       && password_verify($a3, $sq['answer_3_hash'] ?? '');

    if ($ok) {
        $pdo->prepare('UPDATE security_questions SET failed_attempts = 0 WHERE user_id = ?')->execute([$uid]);
        $_SESSION['fp_stage'] = 'reset';
        header('Location: forgot_password.php'); exit;
    } else {
        $pdo->prepare('UPDATE security_questions SET failed_attempts = failed_attempts + 1 WHERE user_id = ?')->execute([$uid]);
        $stmt = $pdo->prepare('SELECT failed_attempts FROM security_questions WHERE user_id = ?');
        $stmt->execute([$uid]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= $MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE users SET security_flagged = 1 WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('UPDATE security_questions SET is_flagged = 1 WHERE user_id = ?')->execute([$uid]);
            unset($_SESSION['fp_stage'], $_SESSION['fp_user_id'], $_SESSION['fp_sq']);
            $_SESSION['fp_stage'] = 'locked';
            header('Location: forgot_password.php'); exit;
        }
        $remaining = $MAX_ATTEMPTS - $attempts;
        $errorCode = 'err_wrong_answers:' . $remaining;
    }
}

/* ── RESET ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'reset') {
    $uid        = (int)($_SESSION['fp_user_id'] ?? 0);
    $newPin     = trim($_POST['new_pin']     ?? '');
    $confirmPin = trim($_POST['confirm_pin'] ?? '');

    if (strlen($newPin) < 6) {
        $errorCode = 'err_pin_short';
    } elseif ($newPin !== $confirmPin) {
        $errorCode = 'err_pin_mismatch';
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ?, security_flagged = 0, updated_at = NOW() WHERE user_id = ?')
            ->execute([password_hash($newPin, PASSWORD_BCRYPT), $uid]);
        unset($_SESSION['fp_stage'], $_SESSION['fp_user_id'], $_SESSION['fp_sq']);
        $_SESSION['fp_stage'] = 'done';
        header('Location: forgot_password.php'); exit;
    }
}

$stage = $_SESSION['fp_stage'] ?? 'identify';
$sq    = $_SESSION['fp_sq']    ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Mot de passe oublié — LionTech</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Arial,sans-serif;background:#F0F4F8;color:#0F172A;min-height:100vh;display:grid;place-items:center;padding:20px}
    .card{background:#fff;border-radius:20px;box-shadow:0 16px 48px rgba(11,31,58,.1);width:100%;max-width:520px;overflow:hidden}
    .card-head{background:#0B1F3A;padding:22px 28px;display:flex;align-items:center;justify-content:space-between}
    .logo-row{display:flex;align-items:center;gap:12px}
    .logo-name{font-size:16px;font-weight:800;color:#fff}
    .logo-tag{font-size:11px;color:#D4A017}
    .lang-btn{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer}
    .lang-btn:hover{background:rgba(255,255,255,.25)}
    .card-body{padding:26px 28px}
    .title{font-size:19px;font-weight:800;color:#0B1F3A;margin-bottom:5px}
    .sub{font-size:13.5px;color:#6B7280;margin-bottom:20px;line-height:1.6}
    .field{margin-bottom:14px}
    .field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
    .field input{width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:11px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s}
    .field input:focus{border-color:#0B1F3A}
    .q-block{background:#F8FAFC;border:1.5px solid #E5E7EB;border-radius:12px;padding:14px 16px;margin-bottom:12px}
    .q-fr{font-size:13.5px;font-weight:700;color:#0B1F3A;margin-bottom:2px}
    .q-en{font-size:12px;color:#94A3B8;margin-bottom:10px;font-style:italic}
    .btn{width:100%;padding:13px;border:none;border-radius:11px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;display:block;box-sizing:border-box;margin-bottom:10px;transition:opacity .15s}
    .btn:hover{opacity:.9}
    .btn-primary{background:#0B1F3A;color:#fff}
    .btn-green{background:#25D366;color:#fff}
    .btn-outline{background:#fff;color:#0B1F3A;border:1.5px solid #E5E7EB}
    .alert-error{background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:11px 14px;font-size:13px;color:#991B1B;margin-bottom:14px}
    .alert-success{background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:14px 16px;font-size:13.5px;color:#166534;margin-bottom:14px}
    .alert-warning{background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;font-size:13.5px;color:#92400E;margin-bottom:14px}
    .pin-box{background:#0B1F3A;border-radius:14px;padding:20px;text-align:center;margin:16px 0}
    .pin-val{font-size:36px;font-weight:900;color:#D4A017;letter-spacing:8px}
    .pin-sub{font-size:12.5px;color:rgba(255,255,255,.7);margin-top:6px}
    .back-link{display:block;text-align:center;margin-top:12px;font-size:13px;color:#6B7280;text-decoration:none}
    .back-link:hover{color:#0B1F3A}
  </style>
</head>
<body>
<div class="card">

  <div class="card-head">
    <div class="logo-row">
      <img src="<?= APP_URL ?>/Image/TALLYLOGO.png" alt="Tally"
           style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div>
        <div class="sb-logo-name">LionTech</div>
        <div class="logo-tag">Business Manager</div>
      </div>
    </div>
    <button class="lang-btn" id="langBtn">EN</button>
  </div>

  <div class="card-body">

    <?php if ($stage === 'identify'): ?>
    <div class="title" data-i18n="identify_title">Mot de passe oublié</div>
    <div class="sub" data-i18n="identify_sub">Entrez votre identifiant de connexion pour continuer.</div>

    <?php if ($errorCode): ?>
    <div class="alert-error" id="errorBox"><span class="icon-warn">⚠</span> <span id="errorMsg"></span></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label data-i18n="login_id_label">Identifiant de connexion</label>
        <input type="text" name="login_id" required autocomplete="username" autocapitalize="none"
               data-i18n-ph="login_id_ph" placeholder="Votre identifiant..."/>
      </div>
      <button type="submit" class="btn btn-primary" data-i18n="btn_continue">Continuer →</button>
    </form>
    <a class="back-link" href="login.php" data-i18n="back_login">← Retour à la connexion</a>

    <?php elseif ($stage === 'questions'): ?>
    <div class="title" data-i18n="questions_title">Questions de sécurité</div>
    <div class="sub" data-i18n="questions_sub">Répondez à vos 3 questions. Les réponses sont insensibles à la casse.</div>

    <?php if ($errorCode): ?>
    <div class="alert-error" id="errorBox"><span class="icon-warn">⚠</span> <span id="errorMsg"></span></div>
    <?php endif; ?>

    <form method="POST">
      <?php
      $qKeys = ['question_1','question_2','question_3'];
      $aKeys = ['answer_1','answer_2','answer_3'];
      for ($i = 0; $i < 3; $i++):
          $qFr = $sq[$qKeys[$i]] ?? '';
          $qEn = $QUESTIONS_MAP[$qFr] ?? $qFr;
      ?>
      <div class="q-block">
        <div class="q-fr"><?= e($qFr) ?></div>
        <div class="q-en"><?= e($qEn) ?></div>
        <div class="field" style="margin-bottom:0">
          <label data-i18n="your_answer">Votre réponse</label>
          <input type="text" name="<?= $aKeys[$i] ?>" required autocomplete="off"
                 data-i18n-ph="answer_ph" placeholder="Réponse..."/>
        </div>
      </div>
      <?php endfor; ?>
      <button type="submit" class="btn btn-primary" data-i18n="btn_verify">Vérifier →</button>
    </form>
    <a class="back-link" href="login.php" data-i18n="back_login">← Retour à la connexion</a>

    <?php elseif ($stage === 'reset'): ?>
    <div class="title" data-i18n="reset_title">Nouveau PIN</div>
    <div class="sub" data-i18n="reset_sub">Identité vérifiée <span class="icon-ok">✓</span> — Définissez votre nouveau PIN.</div>

    <?php if ($errorCode): ?>
    <div class="alert-error" id="errorBox"><span class="icon-warn">⚠</span> <span id="errorMsg"></span></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label data-i18n="new_pin_label">Nouveau PIN / mot de passe</label>
        <input type="password" name="new_pin" minlength="6" required
               data-i18n-ph="new_pin_ph" placeholder="Minimum 6 caractères"/>
      </div>
      <div class="field">
        <label data-i18n="confirm_pin_label">Confirmer le PIN</label>
        <input type="password" name="confirm_pin" minlength="6" required
               data-i18n-ph="confirm_pin_ph" placeholder="Répéter le PIN"/>
      </div>
      <button type="submit" class="btn btn-primary" data-i18n="btn_save"><span class="icon-ok">✓</span> Sauvegarder</button>
    </form>

    <?php elseif ($stage === 'done'): ?>
    <div class="alert-success" data-i18n="done_msg"><span class="icon-ok">✓</span> PIN changé avec succès ! Vous pouvez maintenant vous connecter.</div>
    <a href="login.php" class="btn btn-primary" data-i18n="btn_login"><span class="icon-key">⚿</span> Se connecter</a>
    <?php unset($_SESSION['fp_stage']); ?>

    <?php elseif ($stage === 'done_admin'): ?>
    <div class="title" data-i18n="admin_pin_title">PIN temporaire Super Admin</div>
    <div class="sub" data-i18n="admin_pin_sub">Voici votre PIN temporaire. Changez-le immédiatement après connexion.</div>
    <div class="pin-box">
      <div class="pin-val"><?= e($_SESSION['fp_temp_pin'] ?? '——') ?></div>
      <div class="pin-sub" data-i18n="admin_pin_note">PIN temporaire — à changer immédiatement</div>
    </div>
    <a href="login.php" class="btn btn-primary" data-i18n="btn_login"><span class="icon-key">⚿</span> Se connecter</a>
    <?php unset($_SESSION['fp_temp_pin'], $_SESSION['fp_name'], $_SESSION['fp_stage']); ?>

    <?php elseif ($stage === 'employee_contact'): ?>
    <div class="title" data-i18n="employee_title">Réinitialisation employé</div>
    <div class="alert-warning" data-i18n="employee_msg">
      <span class="icon-user">◉</span> Contactez votre propriétaire ou manager pour réinitialiser votre PIN.
    </div>
    <p style="font-size:13px;color:#6B7280;line-height:1.7;margin-bottom:18px" data-i18n="employee_reason">
      Pour des raisons de sécurité, les employés ne peuvent pas réinitialiser leur propre PIN.
    </p>
    <a href="https://wa.me/237688203095?text=Bonjour%20LionTech%20%F0%9F%91%8B%0AUn%20employ%C3%A9%20a%20besoin%20d%27aide%20pour%20r%C3%A9initialiser%20son%20PIN."
       target="_blank" rel="noopener" class="btn btn-green" data-i18n="btn_whatsapp">
      <span class="icon-msg">▷</span> Contacter LionTech sur WhatsApp
    </a>
    <a class="back-link" href="login.php" data-i18n="back_login">← Retour à la connexion</a>
    <?php unset($_SESSION['fp_stage']); ?>

    <?php elseif ($stage === 'locked'): ?>
    <div class="title" data-i18n="locked_title">Compte verrouillé <span class="icon-lock">🔒</span></div>
    <div class="alert-error" data-i18n="locked_msg">
      Votre compte a été verrouillé après <?= $MAX_ATTEMPTS ?> tentatives incorrectes. Contactez votre propriétaire ou LionTech.
    </div>
    <a href="https://wa.me/237688203095?text=Bonjour%20LionTech%20%F0%9F%91%8B%0AMon%20compte%20est%20verrouill%C3%A9."
       target="_blank" rel="noopener" class="btn btn-green" data-i18n="btn_whatsapp">
      <span class="icon-msg">▷</span> Contacter LionTech sur WhatsApp
    </a>
    <a class="back-link" href="login.php" data-i18n="back_login">← Retour à la connexion</a>
    <?php unset($_SESSION['fp_stage']); ?>

    <?php endif; ?>

  </div>
</div>

<script>
const T = {
  fr: {
    identify_title:'Mot de passe oublié',
    identify_sub:'Entrez votre identifiant de connexion pour continuer.',
    login_id_label:'Identifiant de connexion', login_id_ph:'Votre identifiant...',
    btn_continue:'Continuer →',
    questions_title:'Questions de sécurité',
    questions_sub:'Répondez à vos 3 questions. Les réponses sont insensibles à la casse.',
    your_answer:'Votre réponse', answer_ph:'Réponse...',
    btn_verify:'Vérifier →',
    reset_title:'Nouveau PIN',
    reset_sub:'Identité vérifiée <span class="icon-ok">✓</span> — Définissez votre nouveau PIN.',
    new_pin_label:'Nouveau PIN / mot de passe', new_pin_ph:'Minimum 6 caractères',
    confirm_pin_label:'Confirmer le PIN',       confirm_pin_ph:'Répéter le PIN',
    btn_save:'<span class="icon-ok">✓</span> Sauvegarder',
    done_msg:'<span class="icon-ok">✓</span> PIN changé avec succès ! Vous pouvez maintenant vous connecter.',
    btn_login:'<span class="icon-key">⚿</span> Se connecter',
    admin_pin_title:'PIN temporaire Super Admin',
    admin_pin_sub:'Voici votre PIN temporaire. Changez-le immédiatement après connexion.',
    admin_pin_note:'PIN temporaire — à changer immédiatement',
    employee_title:'Réinitialisation employé',
    employee_msg:'<span class="icon-user">◉</span> Contactez votre propriétaire ou manager pour réinitialiser votre PIN.',
    employee_reason:'Pour des raisons de sécurité, les employés ne peuvent pas réinitialiser leur propre PIN.',
    locked_title:'Compte verrouillé <span class="icon-lock">🔒</span>',
    locked_msg:'Votre compte a été verrouillé après plusieurs tentatives incorrectes. Contactez votre propriétaire ou LionTech.',
    btn_whatsapp:'<span class="icon-msg">▷</span> Contacter LionTech sur WhatsApp',
    back_login:'← Retour à la connexion',
    /* Errors */
    err_empty:'Veuillez entrer votre identifiant.',
    err_not_found:'Identifiant introuvable ou compte inactif.',
    err_locked:'Votre compte est verrouillé. Contactez votre responsable ou LionTech.',
    err_no_questions:'Aucune question de sécurité configurée. Contactez LionTech.',
    err_wrong_answers:'Réponses incorrectes.',
    err_pin_short:'Le PIN doit contenir au moins 6 caractères.',
    err_pin_mismatch:'Les PIN ne correspondent pas.',
  },
  en: {
    identify_title:'Forgot Password',
    identify_sub:'Enter your login ID to continue.',
    login_id_label:'Login ID', login_id_ph:'Your login ID...',
    btn_continue:'Continue →',
    questions_title:'Security Questions',
    questions_sub:'Answer your 3 security questions. Answers are case-insensitive.',
    your_answer:'Your answer', answer_ph:'Answer...',
    btn_verify:'Verify →',
    reset_title:'New PIN',
    reset_sub:'Identity verified <span class="icon-ok">✓</span> — Set your new PIN.',
    new_pin_label:'New PIN / password', new_pin_ph:'Minimum 6 characters',
    confirm_pin_label:'Confirm PIN',     confirm_pin_ph:'Repeat PIN',
    btn_save:'<span class="icon-ok">✓</span> Save',
    done_msg:'<span class="icon-ok">✓</span> PIN changed successfully! You can now log in.',
    btn_login:'<span class="icon-key">⚿</span> Log In',
    admin_pin_title:'Super Admin Temporary PIN',
    admin_pin_sub:'Here is your temporary PIN. Change it immediately after login.',
    admin_pin_note:'Temporary PIN — change immediately',
    employee_title:'Employee PIN Reset',
    employee_msg:'<span class="icon-user">◉</span> Contact your owner or manager to reset your PIN.',
    employee_reason:'For security reasons, employees cannot self-reset their PIN.',
    locked_title:'Account Locked <span class="icon-lock">🔒</span>',
    locked_msg:'Your account has been locked after too many incorrect attempts. Contact your owner or LionTech.',
    btn_whatsapp:'<span class="icon-msg">▷</span> Contact LionTech on WhatsApp',
    back_login:'← Back to Login',
    /* Errors */
    err_empty:'Please enter your login ID.',
    err_not_found:'Login ID not found or account inactive.',
    err_locked:'Your account is locked. Contact your owner or LionTech.',
    err_no_questions:'No security questions set up. Contact LionTech.',
    err_wrong_answers:'Wrong answers.',
    err_pin_short:'PIN must be at least 6 characters.',
    err_pin_mismatch:'PINs do not match.',
  }
};

let lang = localStorage.getItem('lt_lang') || 'fr';
const btn = document.getElementById('langBtn');
const errorCode = '<?= addslashes($errorCode) ?>';
const remaining = errorCode.startsWith('err_wrong_answers:') ? errorCode.split(':')[1] : null;

function applyLang() {
  const t = T[lang];
  document.documentElement.lang = lang;
  btn.textContent = lang === 'fr' ? 'EN' : 'FR';

  document.querySelectorAll('[data-i18n]').forEach(el => {
    const k = el.dataset.i18n;
    if (t[k] !== undefined) el.textContent = t[k];
  });
  document.querySelectorAll('[data-i18n-ph]').forEach(el => {
    const k = el.dataset.i18nPh;
    if (t[k] !== undefined) el.placeholder = t[k];
  });

  /* Show translated error */
  const errBox = document.getElementById('errorBox');
  const errMsg = document.getElementById('errorMsg');
  if (errBox && errMsg && errorCode) {
    const baseCode = errorCode.startsWith('err_wrong_answers') ? 'err_wrong_answers' : errorCode;
    let msg = t[baseCode] || errorCode;
    if (remaining) {
      msg += ' ' + (lang === 'fr'
        ? `Il vous reste <strong>${remaining}</strong> tentative(s).`
        : `<strong>${remaining}</strong> attempt(s) left.`);
    }
    errMsg.innerHTML = msg;
  }

  localStorage.setItem('lt_lang', lang);
}

btn.addEventListener('click', () => { lang = lang === 'fr' ? 'en' : 'fr'; applyLang(); });
applyLang();
</script>
</body>
</html>