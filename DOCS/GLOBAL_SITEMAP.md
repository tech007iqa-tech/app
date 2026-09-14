# 🗺️ Global System Sitemap - IQA Warehouse Systems 9/5/2026 10:52 PM

This sitemap outlines the comprehensive multi-module ecosystem of the IQA Warehouse Systems.

---

## 📍 Root `/WarehouseSystems-main/`
- `index.php`: Master Portal & Navigation Landing Page.
- `core/`: Shared platform utilities:
  - `UI.php`: Server-side UI component engine (`stat_card`, `badge`, `csrf_field`, `modal`, notifications).
  - `Security.php`: Central CSRF token generation, validation, and session security.
- `DOCS/`: System-wide architectural and AI reviewer documentation:
  - `AI_AGENT_INSTRUCTIONS.md`: Core developer rules and system constraints.
  - `AI_TECHNICAL_DEEP_DIVE.md`: Architectural shortcuts, DB sync patterns, and token-saving tips.
  - `GLOBAL_SITEMAP.md`: This comprehensive ecosystem directory.
  - `CODE_REVIEW_CHECKLIST.md`: Zero-trust code review quality checklist.
- `sampleWHdata/`: Standalone offline intake and hardware auditing utility.

---

## 🏷️ Module: Labels (`/labels/`)
*Focus: Individual unit intake, hardware spec editing, and high-fidelity thermal label printing.*

- `index.php`: Dashboard (Stats & Quick Inventory Search).
- `labels.php`: Main Inventory Tracker.
- `new_label.php`: Rapid Unit Intake Form.
- `hardware_view.php`: Technical Spec Editor & Hardware Details.
- `api/`:
  - `add_label.php`: Database insertion and validation.
  - `reprint_label.php`: Flat XML (`.fodt`) generation (portable, zero ZipArchive dependency).
  - `open_windows_file.php`: Native Windows file launcher helper.
- `db/`: SQLite databases (`labels.sqlite`, `audit.sqlite`, `orders.sqlite`, `rolodex.sqlite`).
- `templates/`: Flat XML LibreOffice master templates.
- `exports/`: Destination directory for generated thermal print jobs.

---

## 📊 Module: Orders & CRM (`/orders/`)
*Focus: B2B relationship management, warehouse locations, and batch order fulfillment.*

- `index.php`: Front router (routes views via `?view=` query parameter).
- `pages/`:
  - `warehouse.php`: Stock and multi-tier physical storage location management.
  - `inbound.php`: Embedded AI intake terminal.
  - `customer_registry.php`: Canonical B2B account roster.
  - `leads.php`: CRM interaction hub with real-time SSE sync.
  - `new_order.php`: Batch order and invoice builder.
  - `checkout.php`: B2B manifest builder and standardized CSV export.
  - `trends.php`: Historical BI analytics with CPU pricing modals.
  - `calendar.php`: Outreach scheduler with lead conversion tracking.
  - `settings.php`: Administrative control panel (schema repair, backups, audit logs).
  - `import_warehouse.php`: Batch inventory CSV intake importer.
- `core/`:
  - `database.php`: Cross-DB PDO singleton with self-healing schema migrations.
  - `auth.php`: Role-based security (`Admin`, `Operator`, `Front Desk`).
  - `Schema.php`: Database table blueprints and migration rules.
  - `LocationPhotoProcessor.php`: Location and shelf photo optimization pipeline.
  - `Storage.php`: Storage abstraction layer for SSD and spinning disk archives.
  - `BackupManager.php`: Automated `.tar` archive creation and recovery.

---

## 📢 Module: Marketing Hub (`/marketing/`)
*Focus: Inventory-driven ad generation, outbound campaigns, canonical hardware specs, and photo vaults.*

- `index.php`: Modular front controller and strict `$allowed_modules` dispatcher.
- `config.php`: Multi-database path configuration and RBAC authentication.
- `includes/`:
  - `db.php`: Database connection handles, self-healing migrations, and `log_marketing_audit()`.
  - `header.php` / `footer.php`: Shared navigation, portal topbar, and notifications.
  - `photo_processor.php`: Automated GD-based WebP image optimization and thumbnailing.
- `modules/`:
  - `dashboard/`: Executive KPI command center and smart inventory opportunities.
  - `leads/`: Sales pipeline with bi-directional Master CRM (`customers.db`) sync.
  - `model_templates/`: Canonical hardware catalog with photo coverage badge tracking.
  - `ad_generator/`: Real-time multi-tone copy generator matched against `labels.sqlite` stock.
  - `photo_bucket/`: Hardware photo repository with drag-and-drop uploads and WebP conversion.
  - `campaigns/`: Multi-channel marketing initiatives and goal tracker.
  - `manifest/`: Reusable `ManifestGenerator` service class.
  - `reports/`: Funnel analytics, conversion velocity, and inventory marketing coverage.
  - `docs/`: Built-in interactive documentation viewer and knowledge base.
- `docs/`: Architectural blueprints, feature specifications, SOPs, and roadmap.

---

## 🛠️ Module: Technician Control Center (`/tech/`)
*Focus: Hardware testing, test yields, device audit logs, and component inventory.*

- `index.php`: Technician dashboard (daily test yields, Good vs. Bad unit metrics).
- `pages/`:
  - `logs.php`: Hardware testing logs and historical test lookup.
  - `parts.php`: Parts inventory (RAM, Storage, Batteries) with automated low-stock alerts.
  - `audit.php`: Administrator-only technician throughput audit ledger.
- `api/`:
  - `search_logs.php`: Fast serial, make, and model test history lookup.
  - `log_test.php`: Hardware test submission endpoint.
- `core/`: Shared database handles and auth guards.
