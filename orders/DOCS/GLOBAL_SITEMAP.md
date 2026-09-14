# 🗺️ Global System Sitemap 9/5/2026 10:52 PM

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
- `LocationPhotoProcessor.php`: Resizes, optimizes, generates thumbnails, and saves original raw photos to the archive.
- `Storage.php`: Storage Abstraction layer for Location Photos (SSD and Archive).
- `BackupManager.php`: Handles `.tar` package creation and restoration for photo assets and database metadata.
- `login.php` / `logout.php`: Standard account access endpoints.

### View Fragments (`/prod/pages/`)
These files are buffered and rendered dynamically within `prod/index.php`.
- `calendar.php`: Interactive monthly/weekly event schedulers.
- `customer_registry.php`: Main administration panel for viewing registered billing clients.
- `import_warehouse.php`: Form handling bulk paste copy/paste operations from external Excel spreadsheets.
- `leads.php`: CRM prospects management, outreach pipelines, and quick logging.
- `new_customer.php`: Form to register a new B2B client company.
- `new_order.php`: Interactive order B2B batch builder panel.
- `orders.php`: Overview log of current and finalized orders.
- `settings.php`: Administrative control panel (includes db schema diagnostics, log viewer, and backup manager).
- `trends.php`: BI trends analyzer charting CPU types, buying velocity, and price indexes.
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
- `save_lead.php`: Logs CRM client interactions.
- `search_customers.php`: Retrieves auto-complete lists of billing customers.
- `sync_stream.php`: Server-Sent Events (SSE) database file modification stream.
- `transfer_order.php`: Re-allocates order batches between client profiles.
- `update_order_status.php`: Changes a single order status.

### Static Assets (`/prod/assets/`)
- `exports/`
  - `labels/`: Stores generated Flat ODT labels ready for local retrieval.
- `icon/`: System icons and branding.
- `js/`: Modular javascript loaders matching the views (e.g. `checkout.js`, `warehouse.js`, `customer_registry.js`, `sync.js` [AppSync Engine]).
- `styles/`: View-specific styling sheets (e.g. `style.css`, `components.css`, `dialogs.css`, `warehouse.css`, `leads.css`).
- `ts/`: TypeScript source definitions.
