<?php
/* ============================================================
   setup_security.php — LionTech Business Manager
   First login: change PIN + set 3 security questions
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\setup_security.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();
requireLogin();

$user   = currentUser();
$pdo    = getDB();
$userId = (int)$user['user_id'];
$role   = $user['role'] ?? '';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Security questions — FR only as values, but shown bilingually */
$QUESTIONS = [
    1 => ['fr'=>'Quel est le nom de votre première école ?',               'en'=>'What was the name of your first school?'],
    2 => ['fr'=>'Quel est le nom de jeune fille de votre mère ?',          'en'=>"What is your mother's maiden name?"],
    3 => ['fr'=>'Quelle est la ville de naissance de votre père ?',        'en'=>'What city was your father born in?'],
    4 => ['fr'=>'Quel était le nom de votre premier animal de compagnie ?', 'en'=>'What was the name of your first pet?'],
    5 => ['fr'=>'Quel est votre plat préféré ?',                           'en'=>'What is your favourite food?'],
    6 => ['fr'=>'Dans quelle ville avez-vous grandi ?',                    'en'=>'What city did you grow up in?'],
    7 => ['fr'=>'Quel est le surnom que vous aviez enfant ?',              'en'=>'What was your childhood nickname?'],
    8 => ['fr'=>"Quel est le prénom de votre meilleur(e) ami(e) d'enfance ?", 'en'=>'What is the first name of your childhood best friend?'],
];

/* Check setup state */
$hasQuestions = false;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM security_questions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $hasQuestions = (int)$stmt->fetchColumn() > 0;
} catch (Throwable $ex) {}

$pinMustChange = false;
try {
    $stmt = $pdo->prepare('SELECT pin_must_change FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $pinMustChange = $row ? (bool)$row['pin_must_change'] : false;
} catch (Throwable $ex) {}

if ($hasQuestions && !$pinMustChange) {
    $routes  = json_decode(DASHBOARD_ROUTES, true);
    $dashUrl = APP_URL . '/' . ($routes[$role] ?? 'Logininventory/login.php');
    header('Location: ' . $dashUrl);
    exit;
}

$error = '';
$step  = (int)($_POST['step'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Step 1: Change PIN */
    if ($step === 1) {
        $newPin     = trim($_POST['new_pin']     ?? '');
        $confirmPin = trim($_POST['confirm_pin'] ?? '');

        if (strlen($newPin) < 6) {
            $error = '__pin_short__';
        } elseif ($newPin !== $confirmPin) {
            $error = '__pin_mismatch__';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, pin_must_change = 0, updated_at = NOW() WHERE user_id = ?')
                ->execute([password_hash($newPin, PASSWORD_BCRYPT), $userId]);
            try {
                $pdo->prepare('UPDATE employee_profiles SET pin_must_change = 0 WHERE user_id = ?')
                    ->execute([$userId]);
            } catch (Throwable $ex) {}
            $step = 2;
        }
    }

    /* Step 2: Save security questions */
    if ($step === 2 && ($_POST['substep'] ?? '') === 'save_questions') {
        $q1 = trim($_POST['question_1'] ?? '');
        $q2 = trim($_POST['question_2'] ?? '');
        $q3 = trim($_POST['question_3'] ?? '');
        $a1 = strtolower(trim($_POST['answer_1'] ?? ''));
        $a2 = strtolower(trim($_POST['answer_2'] ?? ''));
        $a3 = strtolower(trim($_POST['answer_3'] ?? ''));

        if (!$q1 || !$q2 || !$q3) {
            $error = '__choose_3__';
            $step  = 2;
        } elseif (!$a1 || !$a2 || !$a3) {
            $error = '__answer_3__';
            $step  = 2;
        } elseif ($q1 === $q2 || $q1 === $q3 || $q2 === $q3) {
            $error = '__diff_questions__';
            $step  = 2;
        } else {
            try {
                $pdo->prepare('INSERT INTO security_questions
                    (user_id, question_1, answer_1_hash, question_2, answer_2_hash, question_3, answer_3_hash)
                    VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                        question_1=VALUES(question_1), answer_1_hash=VALUES(answer_1_hash),
                        question_2=VALUES(question_2), answer_2_hash=VALUES(answer_2_hash),
                        question_3=VALUES(question_3), answer_3_hash=VALUES(answer_3_hash),
                        failed_attempts=0, is_flagged=0, updated_at=NOW()')
                    ->execute([
                        $userId, $q1, password_hash($a1, PASSWORD_BCRYPT),
                        $q2, password_hash($a2, PASSWORD_BCRYPT),
                        $q3, password_hash($a3, PASSWORD_BCRYPT),
                    ]);
                $routes  = json_decode(DASHBOARD_ROUTES, true);
                $dashUrl = APP_URL . '/' . ($routes[$role] ?? 'Logininventory/login.php');
                header('Location: ' . $dashUrl . '?setup=done');
                exit;
            } catch (Throwable $ex) {
                $error = '__db_error__' . $ex->getMessage();
                $step  = 2;
            }
        }
    }
}

$initials = '';
foreach (explode(' ', trim($user['full_name'] ?? 'U')) as $w) $initials .= strtoupper(substr($w, 0, 1));
$initials = substr($initials ?: 'U', 0, 2);

/* Pass questions JSON to JS */
$questionsJson = json_encode(array_values($QUESTIONS), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Configuration sécurité — LionTech</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Arial,sans-serif;background:#F0F4F8;color:#0F172A;min-height:100vh;display:grid;place-items:center;padding:20px}
    .card{background:#fff;border-radius:20px;box-shadow:0 16px 48px rgba(11,31,58,.1);width:100%;max-width:560px;overflow:hidden}
    .card-head{background:#0B1F3A;padding:24px 28px}
    .head-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .logo-row{display:flex;align-items:center;gap:12px}
    .logo-name{font-size:16px;font-weight:800;color:#fff}
    .logo-tag{font-size:11px;color:#D4A017}
    .lang-btn{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer}
    .lang-btn:hover{background:rgba(255,255,255,.25)}
    .steps{display:flex;gap:8px}
    .step{flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,.2)}
    .step.done{background:#1A9E7A}
    .step.active{background:#D4A017}
    .card-body{padding:26px 28px}
    .avatar-row{display:flex;align-items:center;gap:10px;margin-bottom:18px}
    .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#D4A017,#F0C040);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#1f2937;flex-shrink:0}
    .title{font-size:19px;font-weight:800;color:#0B1F3A;margin-bottom:5px}
    .sub{font-size:13.5px;color:#6B7280;margin-bottom:20px;line-height:1.6}
    .field{margin-bottom:15px}
    .field label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
    .field input,.field select{width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:11px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s}
    .field input:focus,.field select:focus{border-color:#0B1F3A}
    .q-block{background:#F8FAFC;border:1.5px solid #E5E7EB;border-radius:13px;padding:14px 16px;margin-bottom:12px}
    .q-num{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#94A3B8;font-weight:700;margin-bottom:8px}
    .btn{width:100%;padding:13px;background:#0B1F3A;color:#fff;border:none;border-radius:11px;font-size:14px;font-weight:800;cursor:pointer;font-family:inherit;transition:opacity .15s}
    .btn:hover{opacity:.9}
    .alert-error{background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:11px 14px;font-size:13px;color:#991B1B;margin-bottom:14px}
    .note-box{background:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:10px 14px;font-size:12.5px;color:#92400E;margin-bottom:16px}
  </style>
</head>
<body>
<div class="card">

  <div class="card-head">
    <div class="head-top">
      <div class="logo-row">
        <img src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg" alt="LionTech"
             style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
        <div>
          <div class="logo-name">LionTech</div>
          <div class="logo-tag">Business Manager</div>
        </div>
      </div>
      <button class="lang-btn" id="langBtn">EN</button>
    </div>
    <div class="steps">
      <div class="step <?= $step >= 1 ? 'done' : '' ?>"></div>
      <div class="step <?= $step >= 2 ? ($step === 2 ? 'active' : 'done') : '' ?>"></div>
    </div>
  </div>

  <div class="card-body">

    <div class="avatar-row">
      <div class="avatar"><?= e($initials) ?></div>
      <div>
        <div style="font-size:14px;font-weight:700;color:#0B1F3A"><?= e($user['full_name']) ?></div>
        <div style="font-size:12px;color:#6B7280"><?= e(ucfirst(str_replace('_',' ', $role))) ?></div>
      </div>
    </div>

    <?php if ($error && !str_starts_with($error, '__db_error__')): ?>
    <div class="alert-error" id="errorBox">⚠️ <span id="errorMsg"></span></div>
    <?php elseif (str_starts_with($error ?? '', '__db_error__')): ?>
    <div class="alert-error">⚠️ <?= e(substr($error, 11)) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ── STEP 1: Change PIN ── -->
    <div class="title" data-i18n="step1_title">Changer votre PIN</div>
    <div class="sub" data-i18n="step1_sub">Étape 1 sur 2 — Définissez un nouveau PIN ou mot de passe sécurisé.</div>

    <form method="POST">
      <input type="hidden" name="step" value="1"/>
      <div class="field">
        <label data-i18n="new_pin_label">Nouveau PIN / mot de passe</label>
        <input type="password" name="new_pin" minlength="6" required
               id="newPinInput" data-i18n-ph="new_pin_ph" placeholder="Minimum 6 caractères"/>
      </div>
      <div class="field">
        <label data-i18n="confirm_pin_label">Confirmer le PIN</label>
        <input type="password" name="confirm_pin" minlength="6" required
               data-i18n-ph="confirm_pin_ph" placeholder="Répéter le PIN"/>
      </div>
      <button type="submit" class="btn" data-i18n="btn_continue">Continuer →</button>
    </form>

    <?php elseif ($step === 2): ?>
    <!-- ── STEP 2: Security Questions ── -->
    <div class="title" data-i18n="step2_title">Questions de sécurité</div>
    <div class="sub" data-i18n="step2_sub">Étape 2 sur 2 — Choisissez 3 questions et répondez-y pour sécuriser votre compte.</div>

    <div class="note-box" data-i18n="questions_note">
      ⚠️ Les questions sont en français uniquement. Les réponses sont insensibles à la casse.
    </div>

    <form method="POST">
      <input type="hidden" name="step" value="2"/>
      <input type="hidden" name="substep" value="save_questions"/>

      <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="q-block">
        <div class="q-num"><span data-i18n="question_label">Question</span> <?= $i ?></div>
        <div class="field">
          <label data-i18n="choose_question">Choisir une question</label>
          <select name="question_<?= $i ?>" required class="q-select" data-index="<?= $i ?>">
            <option value="" data-i18n="select_ph">— Sélectionner —</option>
            <?php foreach ($QUESTIONS as $qId => $q): ?>
            <option value="<?= e($q['fr']) ?>"
                    data-fr="<?= e($q['fr']) ?>"
                    data-en="<?= e($q['en']) ?>">
              <?= e($q['fr']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin-bottom:0">
          <label data-i18n="your_answer">Votre réponse</label>
          <input type="text" name="answer_<?= $i ?>" required autocomplete="off"
                 data-i18n-ph="answer_ph" placeholder="Réponse..."/>
        </div>
      </div>
      <?php endfor; ?>

      <button type="submit" class="btn" data-i18n="btn_finish">✅ Terminer la configuration</button>
    </form>
    <?php endif; ?>

  </div>
</div>

<script>
const T = {
  fr: {
    step1_title:'Changer votre PIN',
    step1_sub:'Étape 1 sur 2 — Définissez un nouveau PIN ou mot de passe sécurisé.',
    step2_title:'Questions de sécurité',
    step2_sub:'Étape 2 sur 2 — Choisissez 3 questions et répondez-y pour sécuriser votre compte.',
    questions_note:'⚠️ Les questions sont en français uniquement. Les réponses sont insensibles à la casse.',
    new_pin_label:'Nouveau PIN / mot de passe',   new_pin_ph:'Minimum 6 caractères',
    confirm_pin_label:'Confirmer le PIN',          confirm_pin_ph:'Répéter le PIN',
    btn_continue:'Continuer →',
    question_label:'Question', choose_question:'Choisir une question',
    select_ph:'— Sélectionner —', your_answer:'Votre réponse', answer_ph:'Réponse...',
    btn_finish:'✅ Terminer la configuration',
    err_pin_short:'Le PIN doit contenir au moins 6 caractères.',
    err_pin_mismatch:'Les PIN ne correspondent pas.',
    err_choose_3:'Veuillez choisir 3 questions de sécurité.',
    err_answer_3:'Veuillez répondre aux 3 questions.',
    err_diff_questions:'Veuillez choisir 3 questions différentes.',
  },
  en: {
    step1_title:'Change Your PIN',
    step1_sub:'Step 1 of 2 — Set a new secure PIN or password.',
    step2_title:'Security Questions',
    step2_sub:'Step 2 of 2 — Choose 3 questions and answer them to secure your account.',
    questions_note:'⚠️ Questions are in French only. Answers are case-insensitive.',
    new_pin_label:'New PIN / password',           new_pin_ph:'Minimum 6 characters',
    confirm_pin_label:'Confirm PIN',               confirm_pin_ph:'Repeat PIN',
    btn_continue:'Continue →',
    question_label:'Question', choose_question:'Choose a question',
    select_ph:'— Select —', your_answer:'Your answer', answer_ph:'Answer...',
    btn_finish:'✅ Finish Setup',
    err_pin_short:'PIN must be at least 6 characters.',
    err_pin_mismatch:'PINs do not match.',
    err_choose_3:'Please choose 3 security questions.',
    err_answer_3:'Please answer all 3 questions.',
    err_diff_questions:'Please choose 3 different questions.',
  }
};

/* Error code map */
const errorCode = '<?= addslashes($error) ?>';
const errorMap = {
  '__pin_short__'    : {fr:'err_pin_short',    en:'err_pin_short'},
  '__pin_mismatch__' : {fr:'err_pin_mismatch', en:'err_pin_mismatch'},
  '__choose_3__'     : {fr:'err_choose_3',     en:'err_choose_3'},
  '__answer_3__'     : {fr:'err_answer_3',     en:'err_answer_3'},
  '__diff_questions__': {fr:'err_diff_questions',en:'err_diff_questions'},
};

let lang = localStorage.getItem('lt_lang') || 'fr';
const btn = document.getElementById('langBtn');

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

  /* Update question options text based on lang */
  document.querySelectorAll('.q-select option[data-fr]').forEach(opt => {
    opt.textContent = lang === 'fr' ? opt.dataset.fr : opt.dataset.en;
  });

  /* Show translated error */
  const errBox = document.getElementById('errorBox');
  const errMsg = document.getElementById('errorMsg');
  if (errBox && errMsg && errorCode && errorMap[errorCode]) {
    errMsg.textContent = t[errorMap[errorCode][lang]] || errorCode;
  }

  localStorage.setItem('lt_lang', lang);
}

btn.addEventListener('click', () => { lang = lang === 'fr' ? 'en' : 'fr'; applyLang(); });
applyLang();
</script>
</body>
</html>