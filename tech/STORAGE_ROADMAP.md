# 🗺️ Storage & Donor Module — Implementation Roadmap (`/tech`)
*IQA Metal Warehouse Systems | Engineering & Modular Implementation Blueprint*

---

## 🎯 Domain Purpose & Objective
In addition to the operational `Good` (ready for shelf/sale) and `Bad` (scrapped) workflows in Hardware Logs, warehouse bench technicians routinely handle laptops that are incomplete or waiting for replacement parts (e.g., "Missing 1 Part" — cracked screen, missing battery, locked BIOS).

The **Storage Module** tracks:
1. **Incomplete Machines**: Units waiting for donor parts before they can be promoted to `Good`.
2. **Donor Machines**: Units with defective motherboards or power issues that hold valuable harvestable components (`Good Screen`, `Good Battery`, `Good Palmrest/Keyboard`).
3. **Smart Donor Matching**: Instant cross-referencing between incoming bench intake and storage inventory to identify available donor components in seconds.

---

## 📅 Implementation Phases Overview

| Phase | Milestone | Deliverables | Target Files | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Phase 1** | Database & Migration | `storage_units` SQLite schema + seed importer for 127 units | `core/Schema.php`, `core/storage_seeder.php` | ✅ Completed |
| **Phase 2** | Modular REST APIs | 4 decoupled JSON endpoints (Get, Add, Update, Promote) | `api/get_storage.php`, `api/add_storage.php`, `api/update_storage.php`, `api/promote_storage.php` | ✅ Completed |
| **Phase 3** | Storage Workbench UI | Touch-first workbench, filter chips, search, action modals | `pages/storage.php`, `assets/styles/storage.css`, `storage_workbench.js` | ✅ Completed |
| **Phase 4** | Smart Donor Matcher | Cross-module donor alert during bench intake | `assets/js/modules/storage_donor_match.js`, `pages/logs.php` | ✅ Completed |
| **Phase 5** | Regression & Docs | Header navigation, automated CLI test assertions, AI docs | `tests/run.php`, `tests/Unit/StorageTest.php`, `DOCS/STORAGE_MODULE.md` | ✅ Completed |
| **Phase 6** | SKU Barcode & Thermal Labels | Pure PHP Code 128 SVG barcode engine, 2"x1" thermal stickers, SKU-only & Detailed modes, Ingest autoprint | `core/barcode.php`, `pages/print_storage_label.php`, `storage_workbench.js` | ✅ Completed |

---

## 🛠️ Step-by-Step Blueprint

### Phase 1: Database Foundation & Seed Data Migration
1. **Table Schema (`storage_units` in `data/db/tech.db`)**:
   ```sql
   CREATE TABLE IF NOT EXISTS storage_units (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       sku TEXT UNIQUE NOT NULL,                  -- e.g. 'RM-1', 'DL-1', 'DP-1'
       date_added DATE NOT NULL,                  -- YYYY-MM-DD Pacific Time
       brand TEXT NOT NULL,                       -- 'Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft'
       model_num TEXT NOT NULL,                   -- '7420', '650 G10', 'A2179', 'T480'
       series TEXT DEFAULT '',                    -- 'Latitude', 'EliteBook', 'ThinkPad'
       battery TEXT DEFAULT '',                   -- 'Good', 'No Bat', 'Bad BAT', 'Taken'
       screen TEXT DEFAULT '',                    -- 'Good Screen', 'Bad Screen', 'Cracked'
       issue TEXT DEFAULT '',                     -- 'No Power', 'BIOs Lock', 'MB Bad'
       need TEXT DEFAULT '',                      -- 'Screen', 'Battery', 'SSD', 'Back Plate'
       notes TEXT DEFAULT '',                     -- Inspection/action notes
       status TEXT DEFAULT 'In Storage',          -- 'In Storage', 'Promoted to Good', 'Harvested / Scrapped'
       tech_id TEXT DEFAULT 'tech',               -- Technician username
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   );
   CREATE INDEX IF NOT EXISTS idx_storage_brand_model ON storage_units (brand, model_num);
   CREATE INDEX IF NOT EXISTS idx_storage_status ON storage_units (status);
   ```
2. **Seed Data Importer**:
   - Parses the 35 active units from `tech/trash/Technician Tasks.xlsx` (`Missing 1 Part` and brand sheets).
   - Inserts records safely using PDO prepared statements.

---

### Phase 2: Modular Backend APIs (`tech/api/`)
1. **`api/get_storage.php`**:
   - Filter by `status` (default `In Storage`).
   - Filter by component need (`need=Screen` or `need=Battery`).
   - Donor match query: `?donor_model=Dell+Latitude+7420` returns units matching the model that have `screen LIKE '%Good%'` or `battery LIKE '%Good%'`.
2. **`api/add_storage.php`**:
   - Ingests unit with auto-generated SKU (`RM-xx` or brand prefix) or custom barcode.
3. **`api/update_storage.php`**:
   - Updates battery, screen, issue, or marks a component as harvested.
4. **`api/promote_storage.php`**:
   - Promotes unit to `Good` (transfers to `logs` table) or retires unit to `Harvested / Scrapped`.

---

### Phase 3: Dedicated Storage Workbench UI (`pages/storage.php`)
1. **Touch-First Ergonomics**: Minimum 48px touch targets for rugged monitors and tablets.
2. **Filter Chips**:
   - `All In Storage`
   - `Needs Screen`
   - `Needs Battery`
   - `Has Good Donors`
   - `Harvested / History`
3. **Live Search**: Debounced instant search across SKU, Brand, Model #, Issue, and Notes.
4. **Action Buttons**:
   - 🛠️ *Harvest Part*
   - ✏️ *Edit*
   - ✅ *Promote to Good*

---

### Phase 4: Smart Cross-Module Donor Matcher
1. **Client Module**: `assets/js/modules/storage_donor_match.js` registered on `window.TechWorkbench.DonorMatch`.
2. **Intake Hook**:
   - During intake in `pages/logs.php`, when model is entered and screen/battery is marked bad:
   - Query `api/get_storage.php?donor_model=...`.
   - Displays real-time badge: `⚡ Donor Match in Storage: RM-09 has a Good Screen!`.

---

### Phase 5: Navigation, Regression Suite & Documentation
1. Add `📦 Storage` to top navigation bar.
2. Add automated regression assertions to `tests/run.php`.
3. Create `tech/DOCS/STORAGE_MODULE.md` for AI agent context preservation.

---

### Phase 6: Zero-Dependency SKU Barcode Engine & Thermal Label Printing
1. **Barcode Engine (`tech/core/barcode.php`)**:
   - Zero-dependency vector SVG Code 128 (Subset B) barcode generator.
   - Generates crisp vector bars with checksum validation, readable by any 1D/2D optical or laser barcode gun.
2. **Thermal Label Generator (`tech/pages/print_storage_label.php`)**:
   - Formatted for standard 2.0" x 1.0" thermal sticker printers (Rollo, Zebra, Dymo).
   - **SKU-Only Mode**: Clean, high-contrast label with large bold SKU (e.g. `DL-22`) and tall scannable barcode for warehouse shelf racks and fast locating.
   - **Detailed Mode**: Full specs including Brand, Model, Date, and Need/Issue.
   - Dynamic 1-click format switcher and auto-print trigger (`?autoprint=1`).
3. **Ingest Workflow Integration**:
   - Ingest modal offers default **SKU & Barcode Only** mode.
   - Clicking **🖨️ Ingest & Print Barcode Label** saves the unit with family SKU and automatically launches the label print window.
   - Table rows and Edit modal include 🖨️ quick print buttons.
