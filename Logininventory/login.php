<?php
/* ============================================================
   login.php — LionTech Business Manager
   Path: C:\Xampp\htdocs\InventoryLiontech\Logininventory\login.php
   ============================================================ */
require_once __DIR__ . '/../Config.php';
startSecureSession();

if (isLoggedIn()) {
    $routes = json_decode(DASHBOARD_ROUTES, true);
    $dest   = APP_URL . '/' . ($routes[$_SESSION['role']] ?? 'Logininventory/login.php');
    header('Location: ' . $dest);
    exit;
}

$queryError = htmlspecialchars($_GET['error'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="LionTech Business Manager — secure login."/>
  <meta name="theme-color" content="#0B1F3A"/>
  <title>Login — LionTech Business Manager</title>
  <link rel="icon" type="image/jpeg" href="/InventoryLiontech/Image/logo%20lionTechhead.jpeg"/>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

<div class="lt-page">

  <!-- ── LEFT PANEL ── -->
  <aside class="lt-left" aria-hidden="true">
    <div class="lt-left-bg"></div>
    <div class="lt-blob lt-blob-1"></div>
    <div class="lt-blob lt-blob-2"></div>
    <div class="lt-blob lt-blob-3"></div>

    <div class="lt-logo">
      <img
        src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"
        alt="LionTech"
        style="width:15rem;height:15rem;border-radius:50%;object-fit:cover;"
      />
      <div class="lt-logo-text">
        <div class="lt-logo-name">LionTech</div>
        <div class="lt-logo-tag">Business Manager</div>
      </div>
    </div>

    <h1 class="lt-left-title" data-lt="welcome">
      Welcome to <span>LionTech Business Manager</span>
    </h1>
    <p class="lt-left-sub" data-lt="welcome_sub">
      The all-in-one platform designed for African businesses of every size.
    </p>

    <ul class="lt-features">
      <li class="lt-feature">
        <div class="lt-feat-icon">📦</div>
        <span class="lt-feat-text" data-lt="feat1">Real-time inventory tracking</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon">👥</div>
        <span class="lt-feat-text" data-lt="feat2">Employee attendance &amp; payroll</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon">📊</div>
        <span class="lt-feat-text" data-lt="feat3">Detailed business reports</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon">🏢</div>
        <span class="lt-feat-text" data-lt="feat4">Multi-business &amp; multi-branch support</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon">🔐</div>
        <span class="lt-feat-text" data-lt="feat5">Role-based secure access</span>
      </li>
    </ul>

    <div class="lt-left-footer">© 2026 LionTech. All rights reserved.</div>
  </aside>

  <!-- ── RIGHT PANEL ── -->
  <main class="lt-right" role="main">

    <div class="lt-top-bar">
      <button id="lt-lang-btn" class="lt-lang-btn" aria-label="Switch language">FR</button>
    </div>

    <!-- Mobile logo -->
    <div class="lt-mobile-logo">
      <img
        src="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"
        alt="LionTech"
        style="width:60px;height:60px;border-radius:50%;object-fit:cover;"
      />
      <div class="lt-mobile-logo-name">LionTech Business Manager</div>
      <p class="lt-mobile-logo-sub" data-lt="app_subtitle">
        Manage your business, inventory, employees, and reports — all in one place.
      </p>
    </div>

    <!-- Login card -->
    <div class="lt-card">

      <h2 class="lt-card-title" data-lt="login_title">Sign In to Your Account</h2>
      <p class="lt-card-sub">LionTech Business Manager</p>

      <!-- PHP session errors -->
      <?php if ($queryError === 'session_expired'): ?>
      <div class="lt-alert warning visible" role="alert">
        <span class="lt-alert-icon">⚡</span>
        <span>Your session expired. Please sign in again.</span>
      </div>
      <?php elseif ($queryError === 'unauthorized'): ?>
      <div class="lt-alert error visible" role="alert">
        <span class="lt-alert-icon">⚠️</span>
        <span>Access denied. You do not have permission to view that page.</span>
      </div>
      <?php endif; ?>

      <!-- JS alert box -->
      <div id="lt-alert" class="lt-alert" role="alert" aria-live="polite"></div>

      <!-- Login form -->
      <form id="lt-form" method="POST" action="auth.php" novalidate autocomplete="on">

        <div class="lt-form-group">
          <label class="lt-label" for="lt-login-id" data-lt="login_id_label">Login ID</label>
          <div class="lt-input-wrap">
            <input
              type="text"
              id="lt-login-id"
              name="login_id"
              class="lt-input"
              placeholder="Email, phone number, username, or employee code"
              autocomplete="username"
              autocapitalize="none"
              autocorrect="off"
              spellcheck="false"
              required
              aria-required="true"
            />
          </div>
        </div>

        <div class="lt-form-group">
          <label class="lt-label" for="lt-password" data-lt="password_label">Password / PIN</label>
          <div class="lt-input-wrap">
            <input
              type="password"
              id="lt-password"
              name="password"
              class="lt-input has-icon"
              placeholder="Enter your password or PIN"
              autocomplete="current-password"
              required
              aria-required="true"
            />
            <button
              type="button"
              id="lt-eye-btn"
              class="lt-eye-btn"
              aria-label="Show password"
            ></button>
          </div>
        </div>

        <button type="submit" id="lt-btn-login" class="lt-btn-login">
          <div class="lt-spinner" aria-hidden="true"></div>
          <span data-lt="btn_login">Login</span>
        </button>

        <!-- Links inside the form -->
        <div class="lt-links">
          <a class="lt-link" href="forgot_password.php" data-lt="forgot">
            Forgot password?
          </a>
          <a
            class="lt-link"
            href="https://wa.me/237688203095?text=Bonjour%20LionTech%20Support%20%F0%9F%91%8B%0A%0ANom%20du%20business%3A%20%0AMon%20nom%3A%20%0AMon%20r%C3%B4le%3A%20(Propri%C3%A9taire%20%2F%20Manager%20%2F%20Employ%C3%A9)%0AMa%20demande%3A%20%0A%0A---%0AHello%20LionTech%20Support%20%F0%9F%91%8B%0A%0ABusiness%20name%3A%20%0AMy%20name%3A%20%0AMy%20role%3A%20(Owner%20%2F%20Manager%20%2F%20Employee)%0AMy%20request%3A"
            target="_blank"
            rel="noopener noreferrer"
            data-lt="support"
          >
            💬 Contact LionTech on WhatsApp
          </a>
        </div>

      </form><!-- /.lt-form -->



    </div><!-- /.lt-card -->

    <!-- Footer -->
    <div class="lt-right-footer">
      <span data-lt="footer_copy">© 2026 LionTech. All rights reserved.</span>
      <div class="lt-footer-links">
        <a href="#" data-lt="footer_privacy">Privacy Policy</a>
        <a href="#" data-lt="footer_terms">Terms of Service</a>
      </div>
    </div>

  </main><!-- /.lt-right -->

</div><!-- /.lt-page -->

<script src="lang.js"></script>
<script src="app.js"></script>

</body>
</html>