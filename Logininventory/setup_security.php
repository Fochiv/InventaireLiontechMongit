<?php
/* ============================================================
   setup_security.php — LionTech Business Manager
   First login: change PIN + set 3 security questions
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\setup_security.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireLogin();

$user       = currentUser();
$pdo        = getDB();
$userId     = (int)$user['user_id'];
$role       = $user['role'] ?? '';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ── Available security questions ── */
$QUESTIONS = [
    1 => ['fr' => 'Quel est le nom de votre première école ?',              'en' => 'What was the name of your first school?'],
    2 => ['fr' => 'Quel est le nom de jeune fille de votre mère ?',         'en' => "What is your mother's maiden name?"],
    3 => ['fr' => 'Quelle est la ville de naissance de votre père ?',       'en' => 'What city was your father born in?'],
    4 => ['fr' => 'Quel était le nom de votre premier animal de compagnie ?','en' => 'What was the name of your first pet?'],
    5 => ['fr' => 'Quel est votre plat préféré ?',                          'en' => 'What is your favourite food?'],
    6 => ['fr' => 'Dans quelle ville avez-vous grandi ?',                   'en' => 'What city did you grow up in?'],
    7 => ['fr' => 'Quel est le surnom que vous aviez enfant ?',             'en' => 'What was your childhood nickname?'],
    8 => ['fr' => 'Quel est le prénom de votre meilleur(e) ami(e) d\'enfance ?', 'en' => 'What is the first name of your childhood best friend?'],
];

/* ── Check if questions already set ── */
$hasQuestions = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM security_questions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $hasQuestions = (int)$stmt->fetchColumn() > 0;
} catch (Throwable $e) {}

/* If already set up and PIN doesn't need changing → redirect to dashboard */
$pinMustChange = false;
try {
    $stmt = $pdo->prepare('SELECT pin_must_change FROM employee_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $pinMustChange = $row ? (bool)$row['pin_must_change'] : false;
} catch (Throwable $e) {}

if ($hasQuestions && !$pinMustChange) {
    $routes  = json_decode(DASHBOARD_ROUTES, true);
    $dashUrl = APP_URL . '/' . ($routes[$role] ?? 'Logininventory/login.php');
    header('Location: ' . $dashUrl);
    exit;
}

$error   = '';
$success = '';
$step    = (int)($_POST['step'] ?? 1);

/* ── PROCESS FORM ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── Step 1: Change PIN ── */
    if ($step === 1) {
        $newPin     = trim($_POST['new_pin']     ?? '');
        $confirmPin = trim($_POST['confirm_pin'] ?? '');

        if (strlen($newPin) < 6) {
            $error = 'Le PIN doit contenir au moins 6 caractères.';
        } elseif ($newPin !== $confirmPin) {
            $error = 'Les PIN ne correspondent pas.';
        } else {
            $hash = password_hash($newPin, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?')
                ->execute([$hash, $userId]);
            /* Clear pin_must_change */
            try {
                $pdo->prepare('UPDATE employee_profiles SET pin_must_change = 0 WHERE user_id = ?')
                    ->execute([$userId]);
            } catch (Throwable $e) {}
            $step = 2; /* Move to security questions */
        }
    }

    /* ── Step 2: Save security questions ── */
    if ($step === 2 && ($_POST['substep'] ?? '') === 'save_questions') {
        $q1 = trim($_POST['question_1'] ?? '');
        $q2 = trim($_POST['question_2'] ?? '');
        $q3 = trim($_POST['question_3'] ?? '');
        $a1 = strtolower(trim($_POST['answer_1'] ?? ''));
        $a2 = strtolower(trim($_POST['answer_2'] ?? ''));
        $a3 = strtolower(trim($_POST['answer_3'] ?? ''));

        if (!$q1 || !$q2 || !$q3) {
            $error = 'Veuillez choisir 3 questions de sécurité.';
            $step  = 2;
        } elseif (!$a1 || !$a2 || !$a3) {
            $error = 'Veuillez répondre aux 3 questions.';
            $step  = 2;
        } elseif ($q1 === $q2 || $q1 === $q3 || $q2 === $q3) {
            $error = 'Veuillez choisir 3 questions différentes.';
            $step  = 2;
        } else {
            $h1 = password_hash($a1, PASSWORD_BCRYPT);
            $h2 = password_hash($a2, PASSWORD_BCRYPT);
            $h3 = password_hash($a3, PASSWORD_BCRYPT);

            try {
                $pdo->prepare('INSERT INTO security_questions
                    (user_id, question_1, answer_1_hash, question_2, answer_2_hash, question_3, answer_3_hash)
                    VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        question_1=VALUES(question_1), answer_1_hash=VALUES(answer_1_hash),
                        question_2=VALUES(question_2), answer_2_hash=VALUES(answer_2_hash),
                        question_3=VALUES(question_3), answer_3_hash=VALUES(answer_3_hash),
                        failed_attempts=0, is_flagged=0, updated_at=NOW()')
                    ->execute([$userId, $q1, $h1, $q2, $h2, $q3, $h3]);

                /* Redirect to dashboard */
                $routes  = json_decode(DASHBOARD_ROUTES, true);
                $dashUrl = APP_URL . '/' . ($routes[$role] ?? 'Logininventory/login.php');
                header('Location: ' . $dashUrl . '?setup=done');
                exit;
            } catch (Throwable $ex) {
                $error = 'Erreur: ' . $ex->getMessage();
                $step  = 2;
            }
        }
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Configuration sécurité — LionTech</title>
  <link rel="stylesheet" href="style.css"/>
  <style>
    body { margin:0; font-family: Inter, 'Segoe UI', sans-serif; background:#F0F4F8; color:#0F172A; }
    .setup-wrap { min-height:100vh; display:grid; place-items:center; padding:24px; }
    .setup-card { background:#fff; border-radius:20px; box-shadow:0 16px 48px rgba(11,31,58,.1); width:100%; max-width:560px; overflow:hidden; }
    .setup-header { background:#0B1F3A; padding:28px 32px; }
    .setup-logo { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .setup-logo-icon { width:44px; height:44px; border-radius:12px; background:rgba(212,160,23,.2); display:flex; align-items:center; justify-content:center; font-size:22px; }
    .setup-logo-name { font-size:17px; font-weight:800; color:#fff; }
    .setup-logo-tag  { font-size:11px; color:#D4A017; }
    .setup-steps { display:flex; gap:8px; }
    .setup-step { flex:1; height:4px; border-radius:2px; background:rgba(255,255,255,.2); }
    .setup-step.done { background:#1A9E7A; }
    .setup-step.active { background:#D4A017; }
    .setup-body { padding:28px 32px; }
    .setup-title { font-size:20px; font-weight:800; color:#0B1F3A; margin-bottom:6px; }
    .setup-sub   { font-size:13.5px; color:#6B7280; margin-bottom:22px; line-height:1.6; }
    .field { margin-bottom:16px; }
    .field label { display:block; font-size:12px; font-weight:700; color:#0B1F3A; margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
    .field input, .field select { width:100%; padding:11px 14px; border:1.5px solid #E5E7EB; border-radius:12px; font-size:14px; font-family:inherit; outline:none; box-sizing:border-box; transition:border-color .15s; }
    .field input:focus, .field select:focus { border-color:#0B1F3A; }
    .question-block { background:#F8FAFC; border:1.5px solid #E5E7EB; border-radius:14px; padding:16px; margin-bottom:14px; }
    .question-block .q-label { font-size:13.5px; font-weight:700; color:#0B1F3A; margin-bottom:2px; }
    .question-block .q-label-en { font-size:12px; color:#6B7280; margin-bottom:10px; }
    .btn-primary { width:100%; padding:13px; background:#0B1F3A; color:#fff; border:none; border-radius:12px; font-size:14px; font-weight:800; cursor:pointer; font-family:inherit; transition:opacity .15s; }
    .btn-primary:hover { opacity:.9; }
    .alert-error { background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:11px 14px; font-size:13px; color:#991B1B; margin-bottom:16px; }
    .avatar-row { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
    .avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#D4A017,#F0C040); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; color:#1f2937; }
  </style>
</head>
<body>
<div class="setup-wrap">
  <div class="setup-card">

    <div class="setup-header">
      <div class="setup-logo">
         <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
        <div>
          <div class="setup-logo-name">LionTech</div>
          <div class="setup-logo-tag">Business Manager</div>
        </div>
      </div>
      <div class="setup-steps">
        <div class="setup-step <?= $step >= 1 ? 'done' : '' ?>"></div>
        <div class="setup-step <?= $step >= 2 ? ($step === 2 ? 'active' : 'done') : '' ?>"></div>
      </div>
    </div>

    <div class="setup-body">

      <div class="avatar-row">
        <div class="avatar"><?= e($initials) ?></div>
        <div>
          <div style="font-size:14px;font-weight:700;color:#0B1F3A"><?= e($user['full_name']) ?></div>
          <div style="font-size:12px;color:#6B7280"><?= e(ucfirst(str_replace('_',' ',$role))) ?></div>
        </div>
      </div>

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
      <!-- STEP 1: Change PIN -->
      <div class="setup-title">Changer votre PIN</div>
      <div class="setup-sub">
        <strong>Étape 1 sur 2</strong> — Définissez un nouveau mot de passe ou PIN sécurisé.<br>
        <em>Step 1 of 2 — Set a new secure password or PIN.</em>
      </div>

      <form method="POST">
        <input type="hidden" name="step" value="1"/>
        <div class="field">
          <label><strong>Nouveau PIN / mot de passe</strong><br><em style="font-weight:400;color:#6B7280">New PIN / password</em></label>
          <input type="password" name="new_pin" minlength="6" required placeholder="Minimum 6 caractères"/>
        </div>
        <div class="field">
          <label><strong>Confirmer le PIN</strong><br><em style="font-weight:400;color:#6B7280">Confirm PIN</em></label>
          <input type="password" name="confirm_pin" minlength="6" required placeholder="Répéter le PIN"/>
        </div>
        <button type="submit" class="btn-primary">Continuer → / Continue →</button>
      </form>

      <?php elseif ($step === 2): ?>
      <!-- STEP 2: Security Questions -->
      <div class="setup-title">Questions de sécurité</div>
      <div class="setup-sub">
        <strong>Étape 2 sur 2</strong> — Choisissez 3 questions et répondez-y. Vos réponses serviront à récupérer votre compte.<br>
        <em>Step 2 of 2 — Choose 3 questions and answer them. Your answers will be used to recover your account.</em>
      </div>

      <form method="POST">
        <input type="hidden" name="step" value="2"/>
        <input type="hidden" name="substep" value="save_questions"/>

        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="question-block">
          <div class="q-label"><strong>Question <?= $i ?></strong></div>
          <div class="q-label-en">Question <?= $i ?></div>
          <div class="field">
            <label><strong>Choisir une question</strong> / <em>Choose a question</em></label>
            <select name="question_<?= $i ?>" required>
              <option value="">— Sélectionner / Select —</option>
              <?php foreach ($QUESTIONS as $qId => $q): ?>
              <option value="<?= e($q['fr']) ?>">
                <?= e($q['fr']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><strong>Votre réponse</strong> / <em>Your answer</em></label>
            <input type="text" name="answer_<?= $i ?>" required placeholder="Réponse (insensible à la casse / case-insensitive)" autocomplete="off"/>
          </div>
        </div>
        <?php endfor; ?>

        <button type="submit" class="btn-primary">✅ Terminer la configuration / Finish Setup</button>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>