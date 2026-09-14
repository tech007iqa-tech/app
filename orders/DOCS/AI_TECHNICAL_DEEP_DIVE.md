# 🧠 AI Technical Deep Dive 9/5/2026 10:52 PM

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
