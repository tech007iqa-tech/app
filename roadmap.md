# 🗺️ IQA Metal Warehouse Systems — Engineering Roadmap

*Executive multi-phase architecture and evolution plan for physical warehouse logistics, CRM fulfillment, thermal labeling, and technician throughput.*

---

## 📍 System Milestones Overview

| Phase | Milestone | Focus Area | Status |
| :--- | :--- | :--- | :--- |
| **Phase 1** | Security & API Hardening | Dynamic AuthGuard routing, CSRF protection, Inbound API sanitization | ✅ Completed |
| **Phase 2** | Database & Schema Consolidation | Centralized `core/Schema.php`, 10-DB unified pool, `.sqlite` interop | ✅ Completed |
| **Phase 3** | Modularity & Hygiene | Deconstruct monolithic `setup/index.php` (2,402 &rarr; 22 lines), views & controllers | ✅ Completed |
| **Phase 4** | Automated Testing Suite | Zero-dependency CLI regression & health checks (`tests/run.php`, 42 tests) | ✅ Completed |
| **Phase 5** | Site-Wide Real-Time AJAX & Invariants | Universal `AppSync`, `ApiResponse`, eliminate `location.reload()`, pipeline date fix | ✅ Completed |
| **Phase 6** | Edge & Offline Hardware Sync | Offline PWA service worker, thermal print queue resilience | 📅 Planned |

---

## 🛠️ Detailed Phase Breakdown

### Phase 1: Security & API Endpoint Hardening ✅
- [x] Universal dynamic login path detection in `core/Auth.php`.
- [x] Fix CSRF bypass vulnerability in standalone `labels/includes/config.php`.
- [x] Authenticate and secure `sampleWHdata/process.php` (`AuthGuard::check()`).
- [x] Require `POST` and Admin authorization for `clear_committed`.
- [x] Mask Gemini API keys for non-administrative sessions in `get_config`.
- [x] Shield `config.json` and `/db/` directories from direct HTTP downloads via production `.htaccess`.

### Phase 2: Database & Schema Consolidation ✅
- [x] Promote system schema registry to root `core/Schema.php`.
- [x] Provide zero-breaking-change backward compatibility forwarder in `orders/core/Schema.php`.
- [x] Centralize 30 tables across all 10 databases (`customers`, `orders`, `warehouse`, `users`, `calendar`, `tech`, `labels`, `marketing`, `intake`, `audit`).
- [x] Support automatic `.db` and `.sqlite` fallback interop in `core/Database.php`.
- [x] Connect `labels` and `sampleWHdata` to master connection pool.
- [x] Eliminate repetitive inline `ALTER TABLE` and `CREATE TABLE` loops in `leads.php`, `new_customer.php`, and `calendar.php`.

### Phase 3: Modularity & Code Hygiene ✅
- [x] Replace empty `orders/download_archive.php` with working authenticated streaming handler for raw original photos.
- [x] Eliminate duplicate misspelled scratch file `marketing/promtp.txt` and relocate clean mandate to `DOCS/MARKETING_ENGINEERING_MANDATE.md`.
- [x] Deconstruct monolithic wizard in `setup/index.php` (2,402 lines down to 22 lines).
- [x] Created `setup/src/SetupController.php` with isolated business logic for migrations, presets, and challenges.
- [x] Modularized wizard views in `setup/views/` (`step1_env.php`, `step2_presets.php`, `step3_dbs.php`, `step4_users.php`, `challenge.php`, `success.php`).
- [x] Separated standalone setup styles and asynchronous client script in `setup/assets/`.

### Phase 4: Automated Testing & Continuous Verification ✅
- [x] Lightweight zero-dependency CLI test runner (`tests/run.php`) executing 42 tests in <60ms.
- [x] Custom zero-dependency test harness and colorful ANSI reporter (`tests/TestRunner.php`, `tests/Assert.php`).
- [x] Security and CSRF unit test suite (`tests/Unit/SecurityTest.php`).
- [x] Database health and mandatory SQLite PRAGMAs test suite (`tests/Integration/DatabaseHealthTest.php`).
- [x] Schema repair and self-healing regression test suite (`tests/Integration/SchemaRegressionTest.php`).
- [x] GEMINI.md domain invariants test suite (`tests/Integration/InvariantsTest.php`).

### Phase 5: Site-Wide Real-Time AJAX & Invariant Hardening ✅
- [x] Centralized universal `AppSync` client in `assets/js/app_sync.js` with smart DOM diffing and focus protection.
- [x] Eliminated `location.reload()` anti-patterns in `orders/assets/js/leads.js` (`saveLead`, `initLeadsSync`) and `orders/pages/leads.php` (`quickRegisterLead`).
- [x] Eliminated `location.reload()` anti-pattern in `orders/assets/js/orders.js` (`transferOrder`, `updateOrderStatus`).
- [x] Standardized API endpoints on `core/ApiResponse.php` (`save_lead.php`, `transfer_order.php`, `update_order_status.php`, `bulk_update.php`).
- [x] Integrated `labels/includes/header.php` with CSRF auto-discovery and `AppSync`.
- [x] Fixed Pipeline KPI date calculation bug in `customer_registry.php` caused by SQLite left-to-right modifier evaluation (`date('now', 'localtime', 'start of month')`).

### Phase 6: Hardware & Mobile Edge Sync 📅
- [ ] Offline intake queue sync with localStorage fallback when workstation loses Wi-Fi.
- [ ] Direct network thermal printer raw ESC/POS and TSPL socket streaming.
