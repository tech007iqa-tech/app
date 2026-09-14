# Project Roadmap: Marketing Hub
*Last Updated: September 5, 2026 10:52 PM*

## 🏁 Phase 1: Foundation & Core Architecture (Complete)
- [x] Scaffolding directory structure and strict module whitelist routing (`marketing/index.php`).
- [x] Responsive layout with dark/light design tokens matching warehouse aesthetics (`core/UI.php`, `main.css`).
- [x] Self-healing SQLite database initialization (`includes/db.php`).
- [x] Standardized architectural blueprints and development guidelines.

---

## 👥 Phase 2: CRM & Hardware Content Library (Complete)
- [x] Leads Intake & Conversion Pipeline with priority scoring.
- [x] Bi-directional integration with Master CRM (`customers.db`) using `CUST-` ID format.
- [x] Model Template Library for canonical specs and hero descriptions (`modules/model_templates/`).
- [x] Live Warehouse Stock Spreadsheet (`db/warehouse.db`, 1,211 items) with multi-header sorting, flexible multi-term search, and template prefill.
- [x] Centralized Photo Bucket storage with drag-and-drop uploads (`modules/photo_bucket/`).
- [x] Automated triple-tier image processing with WebP compression (150x150 thumbs, 1920px display).
- [x] Model Template photo coverage badges (📦 Volume, ✨ Quality, 🖼️ Spec Proof).

---

## 📢 Phase 3: Inventory-to-Ad Generation & Campaigns (Complete)
- [x] Ad Generator engine with live stock filtering from `labels.sqlite` (`modules/ad_generator/`).
- [x] Multi-tone ad generation (B2B Inventory Manifest, Flash Sale Urgency, Social Discussion).
- [x] Reusable programmatic manifest generation class (`modules/manifest/ManifestGenerator.php`).
- [x] One-click clipboard copy utility for generated copy and photo links.
- [x] Campaign initiatives hub with budget, goal tracking, and status metrics (`modules/campaigns/`).

---

## 🛡️ Phase 4: Enterprise Security & System Optimization (Complete)
- [x] Role-Based Access Control (RBAC) across entry points (`Admin`, `Manager`, `Sales`, `Marketing`).
- [x] CSRF protection engine integration on all mutating forms (`core/Security.php`, `UI::csrf_field()`).
- [x] 100% Parameterized PDO queries and strict XSS output escaping (`h()`).
- [x] Central audit logging (`log_marketing_audit()`) in `marketing_audit` ledger.
- [x] Database performance indexing across leads, templates, photos, and campaigns.
- [x] Executive Dashboard with smart stock opportunities and conversion analytics (`modules/dashboard/`).
- [x] Automated reporting engine for lead velocity and inventory marketing coverage (`modules/reports/`).
- [x] Interactive technical documentation viewer with native Markdown parser (`modules/docs/`).

---

## 🚀 Phase 5: Future Enhancements & Automation (Active / Backlog)
- [x] Warehouse stock spreadsheet export to Excel-compatible CSV.
- [x] Dynamic All-rows viewing mode on search and delete.
- [ ] Direct SMTP / Email client dispatch for generated manifests.
- [ ] Automated low-stock alerts and threshold notifications.
- [ ] Bulk CSV lead import and export matching Orders module format.
- [ ] Native AI copywriting assistance for novel hardware models.
