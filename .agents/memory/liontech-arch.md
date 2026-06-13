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

## Sidebar
- Shared sidebar is at `LionTech_Owner_Dashboard/Sidebar.php`.
- It generates `<aside class="od-sidebar" id="od-sidebar">` — NOT `rp-sidebar`.
- Sidebar.php does NOT output `od-menu-btn`; each page must add its own hamburger `id="rp-menu-btn"` (reports) or `id="od-menu-btn"` (other pages).
- Sidebar.php JS binds to `od-menu-btn` for open and `od-sidebar-close` for close.
- Reports page uses `id="rp-menu-btn"` and reports.js manually calls `sidebar.classList.add('open')`.
- CSS for sidebar is in `owner_dashboard.css` at 1050px breakpoint; reports.php must load `owner_dashboard.css`.

**Why:** Sidebar.php was rewritten to use shared od-* classes; pages that had their own rp-sidebar must reference od-sidebar.

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
