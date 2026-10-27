# 📦 IQA Metal Warehouse Systems — AI Agent Instructions (`/tech`)
*Last Updated: October 2026 • Version 2.5.1 | Timezone: America/Los_Angeles (Pacific Time)*

Welcome to the **Technician Control Center (`/tech`)** module of the IQA Metal Warehouse Systems ecosystem. This document contains mandatory rules, architectural boundaries, and guidelines for AI agents and human developers maintaining or extending this module.

---

## 🎯 Primary Purpose & Domain
The `/tech` module serves as the primary operational workbench for warehouse hardware technicians and system administrators. Its responsibilities include:
1. **Hardware Testing & Intake**: Recording functional testing results (`Good` vs `Bad`), technical hardware specifications, cosmetic grading, and multi-serial lot ingest.
2. **Smart Spec Reusability**: Connecting to `/labels/` to reuse existing master spec labels ("One label per unique specs; multiple warehouse locations") to prevent duplicate label sprawl.
3. **Warehouse Hand-off & Delivery Tracking**: Routing tested hardware from the tech workbench to physical shelf bins (`Delivered to Shelf` -> `/warehouse/`) or directly to customer order staging (`Delivered to Order` -> `/orders/`).
4. **Thermal Label Generation**: Generating dependency-free Flat XML (`.fodt`) 2x1 thermal labels via LibreOffice for immediate physical attachment to laptops/desktops.
5. **Component Stock Tracking**: Managing warehouse inventory for RAM, SSDs, batteries, screens, and diagnostic tools with automated low-stock warnings.
6. **Administrative Governance & Auditing**: Enforcing dual-control security (technician delete requests requiring admin approvals) and tracking technician daily velocity.
7. **Storage & Donor Triage Hub**: Managing units waiting for parts ("Missing 1 Part") and donor machines holding viable screens/batteries, with debounced cross-module matching from the testing bench (`pages/logs.php`).

---

## ⚠️ Non-Negotiable Core Rules & Constraints

### 1. Strict Zero-Dependency Policy
- **Languages**: **Vanilla PHP 8.1+**, **Vanilla JavaScript (ES6+)**, and **Vanilla CSS3**.
- **Prohibited**: NEVER introduce Node.js build steps, `npm`/`yarn` packages, Vite/Webpack bundlers, Composer packages, or external PHP composer dependencies.
- **Label Generation**: NEVER use `ZipArchive` or third-party PDF generators for label generation. Always use Flat XML (`.fodt`) templates and XML string construction.

### 2. Timezone Standard: `America/Los_Angeles` (Pacific Time)
- **Mandatory Requirement**: All warehouse physical operations run on Pacific Time (`America/Los_Angeles`).
- **PHP Initialization**: Every endpoint and page controller must enforce `date_default_timezone_set('America/Los_Angeles');` (already declared in `tech/core/database.php` and `tech/core/auth.php`).
- **SQLite Timestamps**: SQLite stores `CURRENT_TIMESTAMP` in UTC. Whenever reading or returning timestamps to client views, convert them to Pacific Time (e.g. using `DateTime` with `DateTimeZone('UTC') -> setTimezone('America/Los_Angeles')` in PHP, and `formatPacificTime()` / `formatPacificDate()` with `{ timeZone: 'America/Los_Angeles' }` in JavaScript).
- **Date Boundary Invariant**: Daily metrics (e.g., "Tested Today", technician daily quotas) must bind `$today = date('Y-m-d');` evaluated under Pacific Time to prevent premature day rollovers after 5:00 PM PDT / 4:00 PM PST.

### 3. Database Integrity & Prepared Statements
- **Connection Standard**: Always obtain connections via `core/Database.php` using convenience methods (`Database::tech()`, `Database::labels()`, `Database::warehouse()`) or `Database::getConnection($db_name)`.
- **100% Prepared Statements**: Every dynamic SQL query MUST use PDO prepared statements with bound parameters. NEVER interpolate or concatenate variables directly into SQL statements.
- **Self-Healing Schemas**: Maintain database column additions and migrations inside central schema registries. Never execute arbitrary `ALTER TABLE` in user-facing view templates.

### 4. Cross-Module Harmony
- **UI Design System**: Do NOT duplicate core stylesheets. Import `../../orders/assets/styles/components.css` and `../../orders/assets/styles/style.css`.
- **Touch-First Accessibility**: All buttons, inputs, and clickable chips must maintain a minimum touch target of **48x48px** for warehouse touch monitors and rugged handheld tablets.
- **No Hover-Only Actions**: Essential controls must remain accessible without requiring hover states.

### 5. Role-Based Access Control (RBAC)
- **Authentication Guard**: All files must check `tech/core/auth.php`.
- **Role Evaluation**: Always perform case-insensitive role comparisons:
  ```php
  $user_role = $_SESSION['role'] ?? '';
  $username = $_SESSION['username'] ?? '';
  $is_admin = (
      strtolower($user_role) === 'admin' || 
      strtolower($username) === 'admin' || 
      strpos(strtolower($user_role), 'admin') !== false
  );
  ```
- **Dual-Control Deletion**: Technicians can only request deletions (`delete_requested = 1`). Only Admins can execute permanent deletions or approve deletion requests.

---

## 🧭 Navigation & Documentation Directory
For deep technical architectures and cross-module bridges, consult the following dedicated documents:
- [AI_TECHNICAL_DEEP_DIVE.md](file:///c:/xampp/htdocs/app/tech/DOCS/AI_TECHNICAL_DEEP_DIVE.md) — Comprehensive architecture, data schemas, state machines, and complete API catalog.
- [MODULE_SITEMAP.md](file:///c:/xampp/htdocs/app/tech/DOCS/MODULE_SITEMAP.md) — Exhaustive inventory of every file, endpoint, route, and UI view.
- [CROSS_MODULE_INTEGRATION_GUIDE.md](file:///c:/xampp/htdocs/app/tech/DOCS/CROSS_MODULE_INTEGRATION_GUIDE.md) — Protocols for interacting with `/labels/`, `/warehouse/`, `/orders/`, and scanner hardware.
- [STORAGE_MODULE.md](file:///c:/xampp/htdocs/app/tech/DOCS/STORAGE_MODULE.md) — Architecture and API catalog for the Storage & Donor Matching subsystem.
- [STORAGE_ROADMAP.md](file:///c:/xampp/htdocs/app/tech/STORAGE_ROADMAP.md) — Blueprint and phase milestones for storage implementation.
