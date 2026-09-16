# 🗺️ Global System Sitemap 9/16/2026 11:26 AM

This document outlines the file layout and component structure of the **IQA Warehouse Systems** workspace.

---

## 📍 Database Storage (`/db/`)
Stored in the workspace root, one level above the public web root (`/prod/`).
- `calendar.db`: Stores events, meeting logs, colors, and date allocations.
- `customers.db`: Master CRM file containing leads, customer profiles, and callback schedules.
- `orders.db`: Stores batch orders and order details.
- `users.db`: Centralized accounts list and audit logs.
- `warehouse.db`: Stores sectors, stock counts, location parameters, and status listings.
- `.htaccess`: Secures the databases by denying HTTP direct file downloads.

---

## 🏬 Public Web Root (`/prod/`)

### Core Entry Files
- `index.php`: Consolidated router and page layout shell. Dispatches views and manages autolinking of stylesheets/javascript files.
- `checkout.php`: Customer B2B batch order checkout manifest verification, order backdating, and ownership transfer utility.
- `generate_odt.php`: Single-label Flat ODT generation helper. Bypasses ZipArchive using flat string overrides.
- `download_archive.php`: Endpoint for fetching raw archived photographs.
- `.htaccess`: Handles standard URL directory settings.

### Core Libraries (`/prod/core/`)
- `auth.php`: Authentication guard validating session states.
- `database.php`: Singleton PDO connection factory enforcing SQLite WAL modes and foreign key configurations.
- `Schema.php`: Central blueprint holding all SQL table layouts, automatic data seeding, and column migrations.
- `Security.php`: Houses CSRF token generators, password policy checks, and dirty input sanitizers.
- `UI.php`: Dynamic template rendering for CSS styling loaders, custom dialog triggers, and toast notifications. Adds `UI::is_ajax()` to detect background synchronizations.
- `warehouse_db.php`: Database connection mapping helper.
- `MediaManager.php`: Automated GD WebP conversion (1920px web view, 160x160 thumb), EXIF auto-rotation, `YYYY/MM/` date partitioning, and cascading file deletion.
- `LocationPhotoProcessor.php`: Resizes, optimizes, generates thumbnails, and saves original raw photos to the archive.
- `Storage.php`: Storage Abstraction layer for Location Photos (SSD and Archive) with nested subfolder path resolution.
- `BackupManager.php`: Handles `.tar` package creation, monthly partition exports, and restoration for photo assets and database metadata.
- `login.php` / `logout.php`: Standard account access endpoints.

### View Fragments (`/prod/pages/`)
These files are buffered and rendered dynamically within `prod/index.php`.
- `calendar.php`: Interactive monthly/weekly event schedulers.
- `customer_registry.php`: Main administration panel for viewing registered billing clients.
- `import_warehouse.php`: Form handling bulk paste copy/paste operations from external Excel spreadsheets.
- `leads.php`: CRM prospects management, outreach pipelines, 9-column bidirectional sorting, urgency tags, search keyword highlighting, and 1-click CSV exports.
- `new_customer.php`: Form to register a new B2B client company.
- `new_order.php`: Interactive order B2B batch builder panel.
- `orders.php`: Overview log of current and finalized orders.
- `settings.php`: Administrative control panel (includes db schema diagnostics, log viewer, and backup manager).
- `trends.php`: BI trends analyzer with modular multi-tab architecture, accounting graphs, CPU metrics, and demand velocity.
- `partials/`: Modular page components loaded by main views:
  - `camera_modal.php`: Live HTML5 camera viewfinder modal with front/rear lens switcher, shutter snap, freeze-frame preview, and drag & drop file uploader.
  - `inventory_modal.php`: Shelf stock inspector, deduplication, and sync panel.
  - `trends_tab_velocity.php`: Model Demand Velocity table (displays Avg Price before Details) with interactive buyer profile links and CSV export.
  - `trends_tab_pricing.php`: Accounting-grade ASP timeline & Monthly Valuation trend graphs (Split and Dual-Axis Combo views) with reconciliation ledger and CSV export.
  - `trends_tab_cpu.php`: CPU Family dominance metrics and transaction drills.
  - `trends_tab_customers.php`: Customer purchasing frequency, volume analytics, and 1-click Customer Intelligence modal links.
  - `b2b_untested_matrix.php`: Wholesale pricing matrix grid with live save feedback toasts.
  - `tested_market_tab.php`: Retail/tested market price comparisons with live save feedback toasts.
  - `trends_actions.php` / `trends_data.php` / `trends_widgets.php` / `trends_modals.php`: Core Trends BI calculation, card layout, customer profile intelligence dialog (`#customerProfileModal`), and manifest preview modals.
- `warehouse.php`: Main storage registration portal and zone map.

### AJAX Endpoints (`/prod/api/`)
- `calendar/`
  - `save.php`: Saves or updates appointment logs.
  - `delete.php`: Deletes scheduling events.
- `add_order_item.php`: Appends a single line item to an active batch order.
- `bulk_update_inventory.php`: Batch relocates or reprices inventory lines.
- `bulk_update_orders.php`: Bulk marks orders as completed or active.
- `consolidate_inventory.php`: Automates deduplication and quantity merging for identical warehouse items.
- `generate_backup.php`: Generates a zip export containing all SQLite databases.
- `generate_warehouse_label.php`: Generates and exports a 2"x1" Flat XML ODT thermal label for a specific inventory ID.
- `get_cpu_pricing_details.php`: API endpoint returning price metrics and recent transactions for CPU families.
- `get_interaction_logs.php`: Fetches timeline items for a lead.
- `get_order_details.php`: API endpoint returning item batch list and totals for a given order ID.
- `get_vocabulary.php`: Returns autocomplete suggestions for model intake.
- `get_warehouse_stock.php`: Returns active quantities for location slots.
- `media_upload.php`: REST endpoint for multipart image and Base64 live camera snapshot uploads.
- `media_delete.php`: REST endpoint for cascading photo deletion (disk assets and DB metadata).
- `save_lead.php`: Logs CRM client interactions.
- `search_customers.php`: Retrieves auto-complete lists of billing customers.
- `sync_stream.php`: Server-Sent Events (SSE) database file modification stream.
- `transfer_order.php`: Re-allocates order batches between client profiles.
- `update_order_status.php`: Changes a single order status.

### Static Assets (`/prod/assets/`)
- `exports/`
  - `labels/`: Stores generated Flat ODT labels ready for local retrieval.
- `icon/`: System icons and branding.
  - `camera_uploader.js`: Universal live camera viewfinder, lens switcher, snapshot capture, and drag & drop photo upload engine.
  - `checkout.js`: Manifest builder and checkout verification.
  - `warehouse.js`: Storage location management and photo gallery triggers.
  - `customer_registry.js`: Customer account roster and registry editing.
  - `leads.js`: CRM pipeline interactions, 9-column sorting, urgency badges, live keyword search highlighting, and `exportLeadsCSV()`.
  - `sync.js`: AppSync Engine for SSE real-time multi-workstation sync.
  - `trends/`: Modular Trends Engine scripts:
    - `trends_nav.js`: Tab switching with canvas lifecycle timeout, URL/session state persistence, global empty states, `exportFinancialLedgerCSV()`, and `exportDemandVelocityCSV()`.
    - `trends_charts.js`: Accounting-grade ASP timeline, Gross Valuation, & Dual-Axis Combo Chart.js lifecycle management with chronological sorting and dual currency formatting.
    - `trends_matrix.js`: Untested B2B matrix editing and live updates.
    - `trends_details.js`: CPU pricing detail drills and order manifest previews.
    - `trends_modals.js`: Customer Profile intelligence dialog (`openCustomerProfileModal`), CPU pricing dialog, Order Preview manifest popup, and live matrix save toasts (`showMatrixSaveToast`).
- `styles/`: View-specific styling sheets (`style.css`, `components.css`, `dialogs.css`, `warehouse.css`, `leads.css`, `trends.css`).
- `ts/`: TypeScript source definitions.
