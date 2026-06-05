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
<html lang="fr" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="LionTech Business Manager — connexion sécurisée."/>
  <meta name="theme-color" content="#0B1F3A"/>
  <title>Connexion — LionTech Business Manager</title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/Image/logo_lionTechhead.jpeg"/>
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
        <div class="lt-feat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
        <span class="lt-feat-text" data-lt="feat1">Real-time inventory tracking</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <span class="lt-feat-text" data-lt="feat2">Employee attendance &amp; payroll</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
        <span class="lt-feat-text" data-lt="feat3">Detailed business reports</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
        <span class="lt-feat-text" data-lt="feat4">Multi-business &amp; multi-branch support</span>
      </li>
      <li class="lt-feature">
        <div class="lt-feat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
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
        <span class="lt-alert-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
        <span>Your session expired. Please sign in again.</span>
      </div>
      <?php elseif ($queryError === 'unauthorized'): ?>
      <div class="lt-alert error visible" role="alert">
        <span class="lt-alert-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
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