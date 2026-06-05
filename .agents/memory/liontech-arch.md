---
name: LionTech Business Manager — architecture decisions
description: Stack, conventions, and non-obvious fixes for this PHP/MySQL inventory app
---

## Stack
PHP 8.2, MySQL/MariaDB, vanilla JS — no Composer, no build tools.
DB: `InventaireLiontech_db`. Super admin login: `InvenAdmin26`.

## Unified language key
All JS files use `localStorage.getItem('lt_lang')` (was `ownerLang` in older code). Always use `lt_lang`.

**Why:** Sidebar.php, owner_dashboard.js, super_admin.js, reports.js must share one key or lang toggle breaks across pages.

## Sidebar
- Shared sidebar is at `LionTech_Owner_Dashboard/liontech_owner_dashboard/Sidebar.php`.
- It generates `<aside class="od-sidebar" id="od-sidebar">` — NOT `rp-sidebar`.
- Sidebar.php does NOT output `od-menu-btn`; each page must add its own hamburger `id="rp-menu-btn"` (reports) or `id="od-menu-btn"` (other pages).
- Sidebar.php JS binds to `od-menu-btn` for open and `od-sidebar-close` for close.
- Reports page uses `id="rp-menu-btn"` and reports.js manually calls `sidebar.classList.add('open')`.
- CSS for sidebar is in `owner_dashboard.css` at 1050px breakpoint; reports.php must load `owner_dashboard.css`.

**Why:** Sidebar.php was rewritten to use shared od-* classes; pages that had their own rp-sidebar must reference od-sidebar.

## Reports page
- Canonical file used by Sidebar.php links: `LionTech_Complete_MVP_Remaining_Pages/LionTech_MVP_Complete/reports.php`
- Other copy at `LionTech_Reports_Page/liontech_reports_page/reports.php` is legacy.
- reports.php loads `owner_dashboard.css` (not reports.css) since it uses od-* classes from Sidebar.php.
- Quick filter buttons: `id="quick-today"`, `id="quick-month"`, `id="quick-year"` — all three required.
- `setRange('year')` sets from = Jan 1 of current year.

## DB schema quirks
- Attendance: `employee_attendance` has `clock_in_at`/`clock_out_at`; `attendance` has `clock_in`/`clock_out` (fallback).
- Stock requests: `stock_in_requests` (not `stock_in`); `stock_movements.movement_type` for stock_out.
- Payments: `payments` table (not `liontech_payments`).
