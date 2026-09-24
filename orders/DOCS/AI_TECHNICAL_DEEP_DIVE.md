# 🧠 AI Technical Deep Dive 9/16/2026 11:26 AM

This document details the database schemas, query abstractions, concurrency controls, document generation formulas, and security patterns implemented in the **IQA Warehouse Systems**.

---

## 💾 Database Schema Reference

The system contains five SQLite databases situated in the `/db/` directory.

### 1. `customers.db`
- **`customers`** (Tracks B2B accounts & outreach details):
  - `customer_id` (TEXT, PRIMARY KEY): Formatted string (e.g. `CUST-XXXXXXXX`).
  - `company_name` (TEXT, NOT NULL)
  - `contact_person` (TEXT)
  - `website` (TEXT)
  - `email` (TEXT)
  - `phone` (TEXT)
  - `address` (TEXT)
  - `shipping_address` (TEXT)
  - `internal_notes` (TEXT)
  - `callback_date` (TEXT): Date representation for callback prompts.
  - `message_date` (TEXT): Date representation for outreach actions.
  - `created_at` (DATETIME): Default `CURRENT_TIMESTAMP`.

### 2. `orders.db`
- **`orders`** (Client purchase orders/batches):
  - `order_id` (TEXT, PRIMARY KEY): Formatted string (e.g. `ORD-XXXXXXXX`).
  - `customer_id` (TEXT): Foreign key linking to `customers.customer_id`.
  - `status` (TEXT): Values are `'active'` or `'finalized'`.
  - `created_at` (DATETIME)
  - `updated_at` (DATETIME)
- **`items`** (Individual hardware items added to orders):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `order_id` (TEXT, NOT NULL)
  - `customer_id` (TEXT, NOT NULL)
  - `brand` (TEXT, NOT NULL)
  - `model` (TEXT, NOT NULL)
  - `series` (TEXT, NOT NULL): Project/Series identifier.
  - `cpu` (TEXT)
  - `ram` (TEXT): Memory specification (e.g. 8GB).
  - `storage` (TEXT): Storage specification (e.g. 256GB).
  - `battery` (TEXT): Battery status (e.g. Yes/No).
  - `description` (TEXT, NOT NULL): Quality/spec details.
  - `notes` (TEXT): Freeform notes and specs parsed from AI or Bulk Import.
  - `quantity` (INTEGER, NOT NULL)
  - `unit_price` (REAL, DEFAULT `0.00`)
  - `created_at` (DATETIME)

### 3. `warehouse.db`
- **`sectors`** (Main inventory sectors):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `name` (TEXT, UNIQUE): E.g., `'Laptops'`, `'Gaming'`, `'Desktops'`, `'Electronics'`.
  - `description` (TEXT)
  - `icon` (TEXT)
  - `color_theme` (TEXT): Hex color values.
- **`inventory`** (Intaken stock items):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `user_owner` (TEXT, NOT NULL)
  - `sector` (TEXT, NOT NULL)
  - `location_code` (TEXT)
  - `brand` (TEXT, NOT NULL)
  - `model` (TEXT, NOT NULL)
  - `specs_json` (TEXT): JSON string holding specific features (e.g. RAM, GPU, OS, Battery status, BIOS configuration).
  - `quantity` (INTEGER, DEFAULT `0`)
  - `status` (TEXT)
  - `last_updated_by` (TEXT): Username of last editor.
  - `price` (REAL, DEFAULT `0.00`)
  - `created_at` (DATETIME)
  - `updated_at` (DATETIME)
- **`locations`** (Physical storage positions):
  - `location_code` (TEXT, PRIMARY KEY): E.g. `'Shelf-A'`.
  - `status` (TEXT): E.g. `'Working'`, `'Audit'`, `'Shipping'`, `'Idle'`.
  - `working_zone_name` (TEXT, DEFAULT NULL): Parent working zone (e.g. `'Zone A'`).
  - `updated_at` (DATETIME)
- **`location_statuses`** (Configurable zone states):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `name` (TEXT, UNIQUE)
  - `color` (TEXT)
- **`working_zones`** (Configurable parent physical zones):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `name` (TEXT, UNIQUE): E.g. `'Zone A'`, `'Zone B'`, `'Inbound'`, `'General'`.
  - `created_at` (DATETIME)
- **`location_photos`** (Tracks uploaded photographs of storage locations/shelves):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `location_code` (TEXT, FOREIGN KEY): Reference to `locations.location_code`.
  - `original_filename` (TEXT, NOT NULL)
  - `archive_driver` (TEXT, NOT NULL)
  - `archive_path` (TEXT, NOT NULL)
  - `optimized_path` (TEXT, NOT NULL)
  - `thumbnail_path` (TEXT, NOT NULL)
  - `uploaded_by` (TEXT, NOT NULL)
  - `category` (TEXT, DEFAULT `'General'`): Layer category (e.g. `Layer 1 (Bottom)`, `Row View`, etc.).
  - `sector` (TEXT, DEFAULT `'Laptops'`)
  - `created_at` (DATETIME, DEFAULT `CURRENT_TIMESTAMP`)
- **`settings`** (Application-wide parameters):
  - `key` (TEXT, PRIMARY KEY)
  - `value` (TEXT)

### 4. `users.db`
- **`users`** (Operator and Administrator credentials):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `username` (TEXT, UNIQUE)
  - `password` (TEXT): Hashed passwords.
  - `role` (TEXT): `'Admin'` or `'Operator'`.
  - `display_name` (TEXT)
  - `ppp_sequence_key` (TEXT)
  - `ppp_row_index` (INTEGER)
- **`audit_log`** (Audit trail table):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `timestamp` (DATETIME)
  - `user_id` (TEXT)
  - `user_name` (TEXT)
  - `module` (TEXT)
  - `action` (TEXT)
  - `target_id` (TEXT)
  - `details` (TEXT)
  - `ip_address` (TEXT)

### 5. `calendar.db`
- **`events`** (Schedule log):
  - `id` (INTEGER, PRIMARY KEY AUTOINCREMENT)
  - `title` (TEXT, NOT NULL)
  - `description` (TEXT)
  - `event_date` (DATE, NOT NULL)
  - `start_time` (TIME, NOT NULL)
  - `end_time` (TIME, NOT NULL)
  - `color` (TEXT)
  - `customer_id` (TEXT)
  - `created_at` (TIMESTAMP)

---

## 🔗 Integrated Query Join Engine
To prevent manual SQLite `ATTACH` sequences, use:
```php
Database::queryIntegrated($primary_db, $attachments, $sql, $params);
```
- **$primary_db**: Primary connection database key name (e.g. `'orders'`).
- **$attachments**: Associative mapping array `['alias' => 'database_name']` (e.g., `['cust' => 'customers']`).
- **$sql**: SQL statement containing joined aliases (e.g., `SELECT * FROM items i LEFT JOIN cust.customers c ...`).
- **$params**: SQL parameters list.

The helper maps connection resources, binds local attachments, runs execution, and detaches the secondary databases safely.

---

## 🔒 Concurrency & Real-Time Sync Controls

### 1. Optimistic Locking (Inventory)
In physical environments, multiple workers may edit stock in the same location.
- **Mechanism**: The `inventory` table contains an `updated_at` timestamp.
- **Save Check**: When the edit form is loaded in `warehouse.php`, the original `updated_at` timestamp is written inside a hidden field `last_updated_at`.
- **Validation**: When submitting changes, the update query compares the hidden `last_updated_at` against the current database value. If they do not match, the query redirects with a `CONCURRENCY_ERROR` code.
- **Client Handling**: The frontend JS monitors response alerts and warns the operator that the record was modified by another user.

### 2. Event-Driven Real-Time Sync (Leads & CRM)
To keep layouts updated in real-time across multiple workstations without spamming the server with timer requests, the Leads module implements **Server-Sent Events (SSE)**.
- **Reactivity Model**:
  1. **EventSource Connection**: The client script (`assets/js/sync.js`) opens a persistent connection to `api/sync_stream.php`.
  2. **File Watcher Loop (Server-side)**: `api/sync_stream.php` monitors both `customers.db` and its Write-Ahead Log cache `customers.db-wal` every 500ms. If the modification timestamp (`filemtime`) of either file changes, the server pushes a `database-change` event.
  3. **Diff Swap (Client-side)**: Upon receiving the event, the client fetches the updated fragment JSON (`index.php?view=leads&ajax=1`). It swaps other static nodes (like follow-up card containers) directly, and performs a smart row-by-row virtual DOM diff on the table (`#leads-list`) to insert, update, or delete elements cleanly.
  4. **Active Input Protection**: The sync engine automatically skips swaps if the client currently has focus in inputs/textareas inside the targeted container.
  5. **Flash Animation**: Modified/new rows are highlighted with a 1.5s fading green background (`row-pulse-highlight` class).

### 3. CPU Pricing details Dialog & Order Preview Modal (Trends Page)
- **CPU Details**: In `trends.php`, clicking a row in the CPU dominance table calls `api/get_cpu_pricing_details.php?cpu=[Name]`. This endpoint computes statistics (min, max, avg unit prices) and fetches recent sales containing this CPU category from `orders.db` and `customers.db`.
- **Order Preview**: Clicking a transaction Order ID inside the CPU details modal calls `api/get_order_details.php?order_id=[ID]`. This queries `orders.db` and `customers.db` to load full B2B client details, line items, and grand totals, displaying them in a manifest preview modal.

### 4. Inventory Consolidation & Maintenance
- **Deduplication**: In physical environments, identical hardware is frequently ingested multiple times. `api/consolidate_inventory.php` normalizes specifications and merges duplicate rows within the same location/sector by summing quantities and cleaning up redundant entries.

### 5. Location & Zone Photo Storage & Backup
- **GD Optimization & WebP Conversion**: `LocationPhotoProcessor` uses the GD graphics library (if available) to convert and scale uploaded images. It generates optimized full-screen preview WebP images (max width 1920px, 85% quality) and square thumbnails (150px, 75% quality). If GD is unavailable, it gracefully falls back to copying the raw files.
- **Storage Abstraction (Local vs. Archive)**: The system implements `StorageManager` with a `StorageDriver` interface.
  - `ssd_local` driver saves optimized previews and thumbnails to `assets/location_photos/` for high-performance rendering.
  - `spinning_disk` driver archives the original high-resolution raw uploads. The target path is configurable under the settings database (`settings` table inside `warehouse.db`), allowing admins to target high-capacity secondary storage drives.
- **Backup & Conflict Resolution**: The `BackupManager` packages all photographs and SQLite `location_photos` metadata database rows into a `.tar` archive. During restore/import, if an uploaded filename already exists on the archive disk, it applies a Windows-style auto-rename resolution (e.g. `filename (1).jpg`) to prevent data loss.

---

## 🏷️ Flat XML Label Generation (FODT)
To enable printing on 2"×1" labels using standard thermal label printers, the system avoids zip dependencies by utilizing the Flat XML OpenDocument format (.fodt).

### Generation Workflow (`prod/api/generate_warehouse_label.php`)
1. Fetches item specification variables (CPU specs, RAM, Storage, GPU, BIOS, notes) from the `inventory` table.
2. Escapes variables utilizing `htmlspecialchars($val, ENT_XML1, 'UTF-8')`.
3. Constructs standard FODT elements containing:
   - Page dimensions: `2in` × `1in`.
   - Fonts: Swiss/Arial family.
   - Flow contents using style paragraphs (`P1`, `P2`, `P4`).
4. Saves files to `/prod/assets/exports/labels/[Brand]_[Model]_[Gen]_ID[ID].odt`.
5. Returns a JSON URL so operators can initiate immediate local download/print actions.

---

## 🛡️ Audit Logger Resilience
The audit manager `Audit::log()` commits operational logs to the `users.db` `audit_log` database.
- **DB Conflict Fallback**: SQLite locks databases when writing from multiple concurrent client processes. If the SQLite database triggers a locking exception, `Audit::log()` catches the error, formats the log properties, and appends the details to a local log file: `/prod/logs/audit_fallback.log`.

---

## ⚠️ Recent Critical Fixes & Features (September 2026)
*   **Warehouse High-Performance Search & Speed Engine (September 24, 2026)**:
    *   **In-Memory Search Indexing (`assets/js/warehouse/warehouse_bulk.js`)**: Eliminated DOM layout thrashing and repetitive cell queries during typing by creating a compiled in-memory index (`window.__whInventoryIndex`). Re-indexed in $O(1)$ on cell edits and $O(N)$ on bulk loads/AppSync updates.
    *   **Hyper-Flexible Multi-Criteria Search & Syntax**:
        *   **Field-Scoped Filters**: `brand:dell`, `model:t480`, `loc:a-1`, `shelf:a-1`, `sec:laptops`, `cpu:i7`, `ram:16`, `storage:512`, `cond:tested`, `status:working`.
        *   **Numeric Range Operators**: `qty:>5`, `qty:<=10`, `qty:0`, `price:>100`, `price:<50`.
        *   **Hardware Acronyms & Synonyms**: Automatic token expansion for RAM (`16g` $\leftrightarrow$ `16gb`), Storage (`512` $\leftrightarrow$ `512gb ssd`), CPU Generations (`8th` $\leftrightarrow$ `gen 8`), and punctuation normalization (`T-480` $\leftrightarrow$ `T480`).
        *   **Negations & Exact Phrases**: Exclusions (`-sold`, `-broken`, `!parts`) and quoted terms (`"ThinkPad T480"`).
    *   **60FPS RAF Debounced Rendering**: Smooth batch display toggle via `requestAnimationFrame` debouncing (20ms) and `.wh-row-hidden` class optimization.
    *   **Quality of Life UX Enhancements**:
        *   **Global Hotkeys**: <kbd>Ctrl</kbd>+<kbd>K</kbd>, <kbd>Cmd</kbd>+<kbd>K</kbd>, and <kbd>/</kbd> instantly focus search; <kbd>Escape</kbd> clears query.
        *   **Embedded Clear Button & Match Counter**: Quick ✕ reset button in search input and real-time match pill badge (`Showing 42 of 1,211 items`).
        *   **Master Sector Navigation Tab**: Embedded `🌐 Master` (all inventory sectors) into the primary `.sector-nav` bar for single-click catalog overview.
    *   **Database Composite Indexing & PRAGMA Optimizations (`core/Schema.php` & `core/Database.php`)**: Added high-speed indexes (`idx_inv_sector_loc`, `idx_inv_loc_sector`, `idx_inv_brand_model`, `idx_inv_updated`, `idx_inv_price`, `idx_locations_zone`, `idx_sold_loc_sec`) alongside SQLite memory cache (`PRAGMA cache_size = -64000`, `PRAGMA temp_store = MEMORY`).
*   **Warehouse Gate Modularization & Zone Spreadsheet View (September 16, 2026)**:
    *   **Zone Integrated Spreadsheet Engine (`pages/partials/warehouse/spreadsheet_view.php`)**: Embedded full in-cell Excel-style spreadsheet directly inside parent Zone views (`?view=warehouse&zone=[Name]`), rendering all stock across all shelves in the active zone with sector navigation tabs.
    *   **Dynamic Shelf Location Column & Auto-fill**: Added real-time editable `Shelf` column with datalist autocomplete (`#zone-shelves-list`), inline shelf reassignment via `api/update_inventory_field.php`, and smart default shelf pre-fill on the bottom blank intake row.
    *   **Modular Component Architecture (`pages/partials/warehouse/`)**: Decomposed monolithic gate view into focused partials: `zone_cards_grid.php` (parent zones with aggregated shelf/item statistics and alert chips), `locations_grid.php` (reusable physical shelf grid supporting single-zone and warehouse-wide cross-zone modes with parent zone tags), and `dashboard_card.php` (modular action launchpad for Global, Zone, and All-Location contexts).
    *   **Segmented Gate Mode Switcher**: Added responsive toggle (`switchGateViewMode('zones' | 'all_locations')`) in `warehouse_gate.js` and `warehouse.css` persisting preference in `sessionStorage` (`wh_gate_view_mode`), allowing instant switching between hierarchical working zones and a flat searchable grid of all warehouse storage shelves.
    *   **Cross-Zone Search & Multi-Criteria Sorting**: Upgraded `warehouse_gate.js` with instant multi-attribute filtering (shelf code + parent zone name) and 6 sorting modes (A-Z, Z-A, Status Group, Most Items, Emptiest, and Parent Zone).
*   **Modular Media & Photography System (September 16, 2026)**:
    *   **Core Engine (`core/MediaManager.php`)**: Built GD-based WebP converter (`1920px` max, ~200-300KB web view; `160x160px` thumbnail crop, ~8-15KB), EXIF auto-orientation, `YYYY/MM/` date-partitioned storage hierarchy, and cascading file + DB deletions.
    *   **Live Camera Viewfinder (`assets/js/camera_uploader.js` & `pages/partials/warehouse/camera_modal.php`)**: HTML5 `navigator.mediaDevices.getUserMedia` live viewfinder, front/rear camera switcher, shutter snap with flash effect, freeze-frame preview/retake workflow, and drag-and-drop file upload zone.
    *   **Universal Upload/Delete APIs (`api/media_upload.php` & `api/media_delete.php`)**: Secure endpoints supporting multipart files and Base64 canvas snapshots, foreign key pre-validation on `locations` table to avoid SQLite constraint violations, and AJAX UI deletion with card fade-out.
    *   **Monthly Partition Archiving (`core/BackupManager.php`)**: Added `exportMonthlyArchive()` and `getMonthlyArchiveBreakdown()` for date-partitioned `.tar` backups.
*   **Warehouse Location Status Deduplication (September 16, 2026)**:
    *   **Global Status Isolation**: Fixed `$all_statuses` query in `pages/warehouse.php` to strictly query global statuses (`location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL'`) with `GROUP BY name`.
    *   **Grouped Color Subquery**: Replaced raw join with `(SELECT name, color FROM location_statuses GROUP BY name)` to prevent duplicate location cards.
    *   **Dynamic Shelf Custom Status Handling**: Updated `warehouse_modals.js` (`openRenameModal()`) to dynamically inject the shelf's custom status into the dropdown if missing, and clean it up upon modal closure.
*   **Shelf Audit & Sync UX Overhaul (September 16, 2026)**:
    *   Removed redundant bulk "Purge Selected (Record as Sold)" button from the "Shelf Audit & Sync" modal (`inventory_modal.php`).
    *   Replaced text "Sold / Purge" button with compact trash icon (`🗑️`).
    *   Changed tab icon from `🗑️` to `🔄` to emphasize reconciliation over deletion.
*   **Trends Center & Financial Analytics Engine (`/orders/index.php?view=trends`)**:
    *   **Model Demand Velocity Table Ordering**: Enforced column order: `Rank/Customer` (0, `num/str`), `Brand` (1, `str`), `Model` (2, `str`), `Avg Price` (3, `num`), `Details` (4, `str`), `Latest Sold/Order` (5, `date/str`), `Units Sold` (6, `num`).
    *   **Financial Graphs (Tab 2 Pricing Curves)**: Restored Chart.js rendering for **Average Selling Price (ASP) Timeline** and **Monthly Gross Realized Valuation**. Time-series points sort chronologically (left-to-right) with financial tooltips showing Realized ASP, Invoiced Units, Gross Revenue, and MoM variance.
    *   **Valuation & ASP Dual-Axis Combo Chart Mode (Phase 3)**: Added interactive view mode switcher between `🔀 Split View` (side-by-side ASP and Gross Valuation cards) and `📊 Dual-Axis Combo` (correlating gross valuation on the left axis against weighted ASP on the right axis with synchronized multi-metric hover tooltips). Mode preference persists in `sessionStorage` (`pricing_chart_view_mode`).
    *   **Live Matrix Micro-Feedback & Cell Glow (Phase 3)**: Added `showMatrixSaveToast()` and `.cell-saved-pulse` in `trends_modals.js` and `trends.css` providing immediate visual feedback upon inline edits to B2B Untested Matrix and Tested Market Reference tables.
    *   **Safe Chart.js Lifecycle**: Added chart destruction guards (`Chart.getChart()`, `aspChartInstance`, `valuationChartInstance`, `comboPricingChartInstance`) to eliminate canvas collision errors when switching tabs or toggling dark/light themes.
    *   **Tab State & Filter Persistence**: Preserved active tab selection in `sessionStorage` (`trends_active_tab`) and the URL (`?view=trends&tab=...`). Changing the date filter triggers `applyTrendsFilter()` to retain the active tab without resetting.
    *   **Executive Accounting KPIs & Ledger**: Added financial summary cards (Gross Valuation, Weighted ASP, Volume Realized, Peak Month, MoM Velocity) and a settlement ledger with MoM growth badges, period revenue share %, and reconciliation totals footer (`<tfoot>`).
    *   **1-Click CSV Exports**: Added UTF-8 BOM formatted exports for Financial Ledger (`exportFinancialLedgerCSV()`), Inventory Demand Velocity (`exportDemandVelocityCSV()`), and Leads CRM (`exportLeadsCSV()`).
    *   **Customer CRM Profile & Order History Intelligence Dialog (Phase 4)**: Added `#customerProfileModal` in `trends_modals.php` and `openCustomerProfileModal()` in `trends_modals.js`. Querying `index.php?view=trends&action=get_customer_profile` in `trends_actions.php`, it dynamically bridges `customers.db` and `orders.db` to calculate lifetime spend, liquidated units, completed orders, tenure dates, CRM contact information, and recent transaction manifests with instant 1-click manifest modal preview links and direct CRM jump actions (`View in CRM` and `New Order Batch`).
    *   **Cross-Module Intelligence Links (Phase 4)**: Interactive company name anchors (`.customer-profile-link`) integrated into Tab 4 (*Top B2B Clients by Volume*), Tab 1 (*Model Demand Buyer Names*), and CPU Pricing modal recent sales lists.
    *   **Global Empty-State Polish (Phase 4)**: Added formatted zero-result placeholders across table-level (`.no-results-row`) and tab-level (`.global-no-results`) search filters with custom iconography and single-click filter reset (`clearSearchInput()`).
*   **Leads & CRM Follow-Up Optimization (`/orders/index.php?view=leads`)**:
    *   **Universal Column Sorting**: Enabled sorting across all 9 data columns (`sortLeadsTable(colIndex, type)`) with raw sort values embedded in `data-sort-val`.
    *   **Follow-Up Urgency Tagging**: Added automated urgency status badges in the `Next Call` column: 🔴 **Overdue** (past date), 🟡 **Due Today** (scheduled today), 🟢 **Upcoming** (future date).
    *   **Real-Time Search Keyword Highlighting**: Enabled multi-term search highlighting across company names, internal notes, contact channels, and status badges via `highlightLeadNodeWords()`.
*   **Warehouse Stock Spreadsheet & Multi-Header Sorting (`/marketing/?page=model_templates`)**:
    *   **Live Database Integration**: Embedded live records from `db/warehouse.db` (`inventory` table, 1,211 items) into an interactive spreadsheet view using text box cells (`<input type="text" class="cell-input">`) with Excel-style keyboard navigation (<kbd>↑</kbd>, <kbd>↓</kbd>, <kbd>Enter</kbd>).
    *   **Column Sequencing**: Structured column layout with **QTY** placed immediately adjacent to **Sector** (`Sector` ➔ `Qty` ➔ `Location` ➔ `Brand` ➔ `Model` ➔ `CPU/Series` ➔ `RAM` ➔ `Storage` ➔ `Condition` ➔ `Notes` ➔ `Price` ➔ `Action`).
    *   **Interactive Multi-Header Sorting**: Implemented bidirectional sorting across all 11 headers (numeric for QTY & Price, natural alphanumeric `localeCompare` for text columns, visual indicators `▲`/`▼`/`⇅`).
    *   **Flexible Search & Rows View Persistence**: Multi-term space-separated search matching tokens in any order. Searching automatically changes the Rows view to `All`; clearing or deleting the search text retains the Rows view default to `All`, displaying all 1,211 items.
    *   **One-Click Template Prefill**: `⚡ Prefill` action button on each row auto-populates Model Name, Category, Base Specs, and Marketing Copy in the top creation form with smooth scrolling and attention flash animation.
    *   **CSV Export**: Instant client-side export of filtered and sorted warehouse stock to `.csv`.
*   **Docs Module & Card Layout Fixes (`/marketing/?page=docs`)**: Fixed `.docs-sidebar` structure and card hierarchy in `marketing/modules/docs/index.php` for seamless side-by-side reading and maximized technical document navigation.
*   **Marketing JS & UI Alignment**: Resolved identifier collision errors (`notify` declaration in `app.js`) and aligned cards, buttons, badges, and filters with the master Aqua Teal (`#007268`) design standard.

## ⚠️ Recent Critical Fixes & Features (July 2026)
*   **Location & Zone Photography (Photos for Zone)**: Added physical shelf/location photo upload functionality to the Warehouse module. Photos are organized by layer category (Layer 1 Bottom to Layer 5 Top, or Row/Overall View) and sector. Features include a floating hover zoom preview for photo thumbnails, and an aggregated gallery view ("View Zone Photos") for parent storage zones.
*   **Photo Storage & Backup Architecture**: Implemented `LocationPhotoProcessor`, `StorageManager` (with local SSD and spinning disk drivers), and `BackupManager` allowing admins to pick the archive path, and create/download or restore `.tar` backup archives containing both photo assets and database metadata.
*   **Inventory Consolidation**: Added `api/consolidate_inventory.php` to automate the merging of identical inventory items within the same warehouse location.
*   **Checkout & Warehouse CSV Standardization**: Added `ram`, `storage`, and `battery` columns directly to the `items` schema. Unified the frontend CSV exports for both Checkout and Warehouse modules so their layout and column sequencing ("Price", "QTY", "Total", plus auto-generated "Notes" and "Battery" descriptions) match perfectly.
*   **Iframe Escaping**: Improved UX in the inbound module (`orders/index.php?view=inbound`) by ensuring navigation actions escape the iframe and target the parent window/tab.
