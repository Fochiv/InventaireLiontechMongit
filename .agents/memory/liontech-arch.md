---
name: LionTech Business Manager — architecture decisions
description: Stack, conventions, and non-obvious fixes for this PHP/MySQL inventory app
---

## Stack
PHP 8.2, MySQL/MariaDB, vanilla JS — no Composer, no build tools.
DB: `InventaireLiontech_db`. Super admin login: `InvenAdmin26`.
PHP dev server: `php -S 0.0.0.0:5000 -t .` (workflow "Start application").
Entry point: `index.php` at root → redirects to `/Logininventory/login.php`.

## Unified language key
All JS files use `localStorage.getItem('lt_lang')` (was `ownerLang`, `em_lang`, `pr_lang`, `si_lang`, `lt_emp_lang` in older code). Always use `lt_lang`.

**Why:** Sidebar.php, owner_dashboard.js, super_admin.js, reports.js, employees.js, products.js, stock_in.js, employee_dashboard.js must share one key or lang toggle breaks across pages. All files fixed to use `lt_lang` and default to `'fr'`.

## Flat file structure (no nested subdirectories)
Files are directly in their module folder — no subdirectory inside a module folder.

**Canonical paths (no nesting):**
- Sidebar: `LionTech_Owner_Dashboard/Sidebar.php`
- Owner dashboard: `LionTech_Owner_Dashboard/owner_dashboard.php`
- Employee dashboard: `LionTech_Employee_Dashboard/employee_dashboard.php`
- Add business: `LionTech_Add_Business_Page/add_business.php`
- Reports: `LionTech_Complete_MVP_Remaining_Pages/reports.php`
- Notifications: `LionTech_Complete_MVP_Remaining_Pages/notifications.php`
- Settings: `LionTech_Complete_MVP_Remaining_Pages/settings.php`
- Subscription billing: `LionTech_Complete_MVP_Remaining_Pages/subscription_billing.php`
- Approval center: `LionTech_Complete_MVP_Remaining_Pages/approval_center.php`
- Activity logs: `LionTech_Complete_MVP_Remaining_Pages/activity_logs.php`
- mvp_helpers: `LionTech_Complete_MVP_Remaining_Pages/mvp_helpers.php`
- Uploads: `LionTech_Complete_MVP_Remaining_Pages/uploads/`

**Why:** Old code had nested subdirs (liontech_owner_dashboard, LionTech_MVP_Complete, etc.) that have been removed. Any new file or include must follow flat structure above.

**Include path from a module folder (e.g. Produit/):**
`__DIR__ . '/../LionTech_Owner_Dashboard/Sidebar.php'`  (one `../` to root, then into module)

**Include path from root-level file (e.g. change_pin.php):**
`__DIR__ . '/LionTech_Owner_Dashboard/Sidebar.php'`

## BUGS CRITIQUES RÉSOLUS (à ne pas recasser)

### 1. Double hamburger — RÉSOLU dans Sidebar.php JS
- Cause : `sbHamburger` (od-hamburger) restait dans le DOM et visible via CSS `@media(max-width:768px){.od-hamburger{display:inline-flex}}` même quand la page avait son propre `od-menu-btn`.
- Fix : Quand `existingMenuBtn` existe → `ham.hidden=true; ham.style.setProperty('display','none','important')`.
- Ce fix couvre : clock_attendance, notifications, change_pin, products, employee_dashboard, toutes les pages avec od-menu-btn.

### 2. Sidebar invisible à 769-1050px — RÉSOLU dans Sidebar.php CSS
- Cause : `owner_dashboard.css` ajoute `transform:translateX(-105%)` à max-width:1050px. Sidebar.php écrit `left:0!important` mais pas `transform:none`. CSS = left:0 ET transform(-105%) → sidebar hors écran.
- Fix : Ajout de `transform:none !important` dans Sidebar.php `@media(min-width:769px){ .od-sidebar{left:0!important;transform:none!important} }`.
- Affecte toutes les pages qui chargent owner_dashboard.css : owner_dashboard, notifications, validations, reports, change_pin, Vente, activity_logs, approval_center, subscription_billing, settings.

### 3. employee_dashboard.php — breakpoint mismatch
- Cause : employee_dashboard.css utilisait ed-main (pas od-main) avec margin-left:280px et breakpoint 850px. Sidebar.php forçait sidebar visible à 769px+. Gap 769-850px = sidebar visible + ed-main{margin-left:0} → overlap.
- Fix : `<style>` injecté APRÈS l'include Sidebar.php dans le body → override de Sidebar.php pour ed-main.
- Règle : les pages avec classes CSS personnalisées (ed-main, ca-main) doivent avoir un `<style>` APRÈS l'include Sidebar.php pour synchroniser les breakpoints.

### 4. Caisse.css — cart-bar visible sur desktop
- Cause : `@media(min-width:600px)` (ligne 535) venait APRÈS `@media(min-width:768px)` (ligne 412) qui cachait .cart-bar. Le rule 600px réaffichait cp-panel MAIS n'cachait pas .cart-bar → deux UI panier visibles.
- Fix : Ajout de `.cart-bar,.cp-overlay{display:none!important}` dans le bloc min-width:600px.

## Sidebar — architecture (CRITICAL)
- Shared sidebar is at `LionTech_Owner_Dashboard/Sidebar.php`.
- It generates `<aside class="od-sidebar" id="od-sidebar">` with INLINE CSS in a `<style>` block.
- Sidebar.php inline CSS: `.od-sidebar{position:fixed;top:0;left:-280px;width:260px;height:100vh}` / `.od-sidebar.open{left:0}`.
- Desktop (min-width:769px): `.od-sidebar{left:0!important}` + `.od-main{margin-left:260px}` — sidebar always visible.
- Mobile (<768px): `.od-main{margin-left:0!important}` — sidebar hidden, toggled via `.open` class (JS).
- DO NOT add `transform:translateX()` CSS for od-sidebar — conflicts with the `left:-280px/left:0` system.
- `responsive_utils.css` must NOT override sidebar positioning — Sidebar.php inline CSS handles it entirely.
- Reports page uses `id="rp-menu-btn"` and reports.js manually calls `sidebar.classList.add('open')`.
- All other pages use `id="od-menu-btn"` which Sidebar.php JS auto-binds.

**Why:** Sidebar.php uses `left` property not `transform`. Adding `transform` globally breaks the animation.

## Responsive utilities (responsive_utils.css)
- File at project root: `responsive_utils.css`
- Linked on all 17 admin pages via `<link rel="stylesheet" href="<?= APP_URL ?>/responsive_utils.css">` before `</head>`.
- Handles: inline grid attribute selectors (`1fr 320px`, `1fr 340px`, `repeat(6,1fr)`, `1fr 1fr;gap:20px`), class-based grids (`kpi-grid`, `ac-stat-grid`, `st-grid`, `notif-grid`), table overflow safety nets (`.od-table-wrap`, `.val-table-wrap`), topbar/hero wrap.
- Does NOT touch od-sidebar positioning (handled by Sidebar.php inline styles).
- stock_out.css also targets `div[style*="grid-template-columns:1fr 340px,1fr 320px,1fr 300px"]` — keep in sync.

**Why:** Central responsive supplement; attribute selectors on inline style strings work when the exact substring matches.

## Reports page
- Canonical file used by Sidebar.php links: `LionTech_Complete_MVP_Remaining_Pages/reports.php`
- Other copy at `LionTech_Reports_Page/reports.php` is legacy.
- reports.php loads `owner_dashboard.css` (not reports.css) since it uses od-* classes from Sidebar.php.
- Quick filter buttons: `id="quick-today"`, `id="quick-month"`, `id="quick-year"` — all three required.
- `setRange('year')` sets from = Jan 1 of current year.

## Super Admin — subscriptions badge
- The sidebar badge for "Abonnements" shows `count($subscriptions)` (total count).
- `$stats['expired']` is used on the dashboard stat card (red card) only.
- `$stats['active']` = businesses with subscription_status='active'.

**Why:** Badge showed $stats['expired'] which was 0 for a new active business, confusing the user.

## MariaDB startup on Replit (critical)
Bootstrap mode (`mysqld --bootstrap`) is blocked by Replit's seccomp sandbox — it tries to access `/proc/pid/fd` which is forbidden. `mariadb-install-db` and `--initialize-insecure` also fail for the same reason.

**Working solution (in `start.sh`):**
1. Start mysqld with `--skip-grant-tables --performance-schema=0 --skip-log-bin --skip-slave-start` on a fresh/existing datadir
2. Poll for socket ready in a tight loop (250ms intervals, up to 30s)
3. Once alive, import `db-config.sql` via `mysql --socket=/tmp/mysql.sock -u root`
4. Touch `$MYSQL_DATA/.lt_initialized` so subsequent boots skip the import
5. `exec php -S 0.0.0.0:5000 -t /home/runner/workspace`

**Why:** `--performance-schema=0` prevents background thread crashes due to missing mysql system tables on a fresh datadir. The window to connect is ~1.5s after startup. Socket: `/tmp/mysql.sock`. Datadir: `/home/runner/mysql-data`.

**Config.php** already detects the socket: `define('DB_SOCKET', file_exists('/tmp/mysql.sock') ? '/tmp/mysql.sock' : '');`

## DB schema quirks
- Attendance: `employee_attendance` has `clock_in_at`/`clock_out_at`; `attendance` has `clock_in`/`clock_out` (fallback).
- Stock requests: `stock_in_requests` (not `stock_in`); `stock_movements.movement_type` for stock_out.
- Payments: `payments` table (not `liontech_payments`).
