# 🧠 AI Technical Deep Dive & Handover 9/5/2026 10:52 PM

This document serves as a "shortcut" for AI agents to understand the underlying logic of the IQA Warehouse Systems without reading every single file.

## 🏗️ Core Module Differences
1.  **Labels Module (`/labels`)**:
    *   **Logic**: Procedural PHP.
    *   **DB**: Uses `includes/db.php` which initializes 4 global PDO objects (`$pdo_labels`, `$pdo_orders`, `$pdo_rolodex`, `$pdo_audit`).
    *   **Auditing**: Uses `log_audit_event()` helper defined in `includes/audit.php`.
2.  **Orders Module (`/orders`)**:
    *   **Logic**: Semi-Object Oriented.
    *   **DB**: Uses a `Database` singleton class (`core/database.php`).
    *   **Routing**: View-based routing via `index.php?view=...`. Pages are located in `pages/`.
3.  **Marketing Module (`/marketing`)**:
    *   **Logic**: Modular Procedural.
    *   **DB**: Uses `get_marketing_db()`, `get_labels_db()`, and **`get_master_crm_db()`**.
    *   **Sync**: Directly synced with the Master CRM (`customers.db`).
    *   **Design**: Modern **Teal/Lime** design palette (`#007268`).

## 🛠️ Key Technical Patterns
### 1. Smart Sync (Master CRM) & Real-Time SSE Sync
The system uses a **Single Source of Truth** for people (Leads/Customers).
*   **Database**: `orders/assets/db/customers.db`.
*   **Format**: All new accounts must use the `CUST-XXXXXXXX` ID format (randomized string).
*   **Behavior**: A lead captured in Marketing is instantly visible in the Order Manager.
*   **Real-time Reactivity (SSE)**: Rather than running heavy client-side polling timers, we use **Server-Sent Events (SSE)**.
    - **Watcher Endpoint (`api/sync_stream.php`)**: Establishes a persistent `text/event-stream` connection. Every 500ms, it checks `filemtime` on `customers.db` and `customers.db-wal` (WAL write cache). When a modification time change is detected, it broadcasts `database-change` to all open clients.
    - **AppSync Client (`assets/js/sync.js`)**: An EventSource listener that triggers an instant AJAX fetch when notified. It uses a lightweight virtual DOM diffing algorithm to perform row-by-row replacements on the registered container (`#leads-list`) and standard innerHTML swaps for other containers (`#priority-section-container`). Input active states are automatically guarded during swaps.

### 2. Self-Healing Schemas
Every module has a `schema_guard.php` or `Schema::runMigrations()` setup.
*   **Trigger**: Executed on every database connection initialization.
*   **Logic**: Uses `CREATE TABLE IF NOT EXISTS` and `PRAGMA table_info()` to check for missing columns and run `ALTER TABLE` migrations automatically.

### 3. Flat XML ODT Generation
*   **Location**: `labels/api/reprint_label.php`.
*   **Method**: Uses `str_replace()` on a `.fodt` (Flat XML) template.
*   **Benefit**: No `ZipArchive` dependency. Files are portable and work immediately with LibreOffice.

### 4. iOS / Warehouse Optimization
*   **Touch Targets**: Buttons are strictly `48px` minimum height.
*   **Colors**: High-contrast light themes for operational modules; vibrant Teal/Lime for Marketing.

## ⚠️ Recent Critical Fixes & Features (September 2026)
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

## ⚠️ Recent Critical Fixes & Features (June 2026)
*   **Warehouse Working Zones**: Refactored locations into a nested working zones grid (`working_zones` table). Click to drill-down into specific locations, automatically pre-filled with prefixes (e.g. `A-` inside Zone A) when creating sub-locations. Added inline renaming functionality.
*   **CPU Pricing Analytics**: Added an interactive pricing modal in Trends that queries `api/get_cpu_pricing_details.php` to analyze price statistics (min/max/average) and transaction logs for CPU models. Supports drilling down further with `api/get_order_details.php` to preview the transaction manifest.
*   **Dark Mode Visual Contrast**: Upgraded styling variables in `style.css` to cover settings cards, gate cards, location badges, and inputs, ensuring excellent readability for night/warehouse shifts.
*   **CRM Sync**: Integrated Marketing Hub with the Master CRM (`customers.db`) using a dual-database singleton pattern.
*   **ID Integrity**: Fixed `NOT NULL` constraint issues by implementing custom `CUST-` ID generation in the Marketing leads module.

## 🔍 Token-Saving Tips
*   Don't read `hardware_form.php` unless editing labels; it's a massive UI component.
*   Check `GLOBAL_SITEMAP.md` first to find the correct `api/` or `core/` file.
*   Always use `__DIR__` for includes to maintain XAMPP portability.
