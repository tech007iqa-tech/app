# Prompt: Item-Level Inventory Migration, Renaming, and Location Management

## Objective
Implement a robust inventory migration workflow that allows users to move individual items (SKUs/units) between locations, create new locations across different zones, rename existing locations, and automatically delete or archive source locations once they become completely empty after migration.

---

## 🔍 Context: Existing System vs. Target View Analysis

### Existing System (Screenshot 2: Global Table View)
- **Current Route**: `orders/index.php?view=warehouse&sector=Master&loc=GLOBAL` (and table mode when `$is_spreadsheet` is false).
- **Current Components**:
  - **Bulk Action Bar** (`#bulkActionBar` in [`orders/pages/warehouse.php`](file:///c:/xampp/htdocs/app/orders/pages/warehouse.php#L204)): Appears when checkboxes are selected.
  - **Selection Mechanism**: Checkbox column (`#selectAll` in `<thead>`, `.row-select` in each `<tr>`) in [`table_view.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/table_view.php#L64).
  - **Client Controller**: [`warehouse_bulk.js`](file:///c:/xampp/htdocs/app/orders/assets/js/warehouse/warehouse_bulk.js) manages `selectedIds`, displays the selected item count, and handles `applyBulkBtn`.
  - **Backend Endpoint**: [`orders/api/bulk_update_inventory.php`](file:///c:/xampp/htdocs/app/orders/api/bulk_update_inventory.php) accepts `ids`, `location`, `price`, `status`, and updates rows via `UPDATE inventory SET location_code = ? WHERE id IN (...)`.
- **Existing Limitations**:
  1. **Unavailable in Spreadsheet Mode**: It is strictly limited to `table_view.php`. The primary single-shelf and zone views (e.g., `loc=W1-L1` in Screenshot 1) render `spreadsheet_view.php`, which completely lacks row checkboxes, selection bindings, and relocation triggers.
  2. **Coarse "All-or-Nothing" Move**: The existing endpoint only moves entire records. It cannot split quantities (e.g., cannot move 50 out of 200 units of a SKU).
  3. **No Zone-Aware Provisioning**: The `Move to Zone...` input is a simple text input with a datalist of existing location codes. If a new location code is typed, it creates a location with `working_zone_name = NULL` (defaulting to 'General') without allowing zone assignment or metadata capture.
  4. **No Inventory Deduplication / Merge**: Moving an item to a shelf that already contains identical stock results in duplicated rows rather than merging quantities.
  5. **No Empty Location Cleanup**: When all items are moved off a shelf, the empty location remains active indefinitely with 0 items.
  6. **No Relocation Audit Trail**: Relocations are not logged to an audit ledger.

### Target System (Screenshot 1: Shelf & Zone Spreadsheet View)
- **Target Route**: `orders/index.php?view=warehouse&sector=<Sector>&loc=<Shelf>` (e.g., `sector=Laptops&loc=W1-L1`) and zone-wide spreadsheet views (`?view=warehouse&sector=<Sector>&zone=<Zone>`).
- **Target Experience**:
  - Direct row-level migration button (⇄ / 🚚) on each spreadsheet row (marked in Screenshot 1) for rapid single-item migration with quantity splitting.
  - Multi-row selection checkboxes integrated into `spreadsheet_view.php` and `ajax_view.php` that leverage and extend the `#bulkActionBar` from Screenshot 2.
  - Interactive **Migration Modal / Drawer** that supports granular quantity transfers, zone selection, instant shelf provisioning, deduplication, and empty-source cleanup prompts.

---

## Core Requirements

### 1. Item-Level & Multi-Row Migration
- **Granular Quantity Control**: Support moving a subset of an item's stock (e.g., move 50 out of 200 units of Lenovo ThinkPad E560 from `W1-L1` to `W1-L2`) or the entire stock.
- **Stock Splitting & Deduplication**:
  - *Partial Move*: Decrement source item quantity; at destination, increment quantity if an identical item exists (matching `sector`, `brand`, `model`, `specs_json`), or create a new inventory record with the transferred quantity.
  - *Full Move*: Reassign location; merge with matching destination item if present.
- **Validation**: Ensure source location and item row have sufficient quantity available before executing transfer (`quantity_to_move <= source_quantity`).
- **Bulk Migration**: Allow selecting multiple rows via checkboxes (in both spreadsheet and table views) to migrate multiple items in batch to a target location/zone.

### 2. Location Creation & Zone Management
- **Zone-Aware Destination Picker**: Allow operators to select a target **Working Zone** (e.g., `W1`, `A`, `z General`, `Row F`, `zFloor G1`) and then pick a specific shelf within that zone, or create a new shelf on the fly.
- **On-the-Fly Creation**: Enable creating new storage locations directly within the migration workflow (capturing Location Code, Parent Zone, Status, and optional Aisle/Shelf metadata) without navigating away.
- **Automatic Zone Linking**: Newly created locations are strictly mapped to their selected `working_zone_name`.

### 3. Location Renaming
- **Atomic Reference Updates**: Provide a clean renaming function for existing locations that cascades across `locations.location_code`, `inventory.location_code`, `location_photos.location_code`, and historical migration logs.
- **Audit Logging**: Log renames to `audit_log` with user, timestamp, previous name, and new name.

### 4. Automatic Cleanup of Empty Locations
- **Depletion Trigger**: Immediately after a migration completes, calculate the source location's remaining inventory balance across all sectors (`SELECT COUNT(*), SUM(quantity) FROM inventory WHERE location_code = ?`).
- **Cleanup Action**:
  - If source location quantity reaches zero (0):
    - **Prompt Mode**: Prompt the user (*"Shelf W1-L1 is now empty. Would you like to archive or delete this shelf?"*).
    - **Auto-Archive Mode**: Automatically soft-delete/archive the shelf (`is_archived = 1, archived_at = CURRENT_TIMESTAMP`).
- **Safety Checks**: Strictly block deletion or archival if any active inventory or pending order fulfillments remain associated with that location. Preserve photo assets in the archive.

---

## Expected Output / Deliverables

### 1. Database Schema Adjustments
- **`locations` table** extensions in [`orders/core/Schema.php`](file:///c:/xampp/htdocs/app/orders/core/Schema.php):
  - `aisle_shelf TEXT DEFAULT NULL`
  - `is_archived INTEGER DEFAULT 0`
  - `archived_at DATETIME DEFAULT NULL`
  - `archived_reason TEXT DEFAULT NULL`
- **`inventory_move_logs` table**:
  - Tracking `id`, `inventory_id`, `source_location`, `target_location`, `source_zone`, `target_zone`, `quantity_moved`, `moved_by`, `timestamp`.

### 2. API & Backend Logic
- **`POST api/migrate_inventory.php`**:
  - Handles item-level transfers (single item partial/full or multi-item bulk).
  - Handles stock splitting, target deduplication merging, and transactional rollback.
  - Runs empty-location detection and returns `{ success, source_depleted, source_location, moved_count }`.
- **`POST api/manage_location.php`** (or action controllers in [`actions.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/actions.php)):
  - `action=create_location`: Create new shelf with assigned zone and metadata.
  - `action=rename_location`: Atomic cascading rename.
  - `action=cleanup_empty_location`: Archive or delete verified empty shelf.

### 3. UI/UX Workflow & Components
- **Spreadsheet Mode Integration** ([`spreadsheet_view.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/spreadsheet_view.php) & [`ajax_view.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/ajax_view.php)):
  - Checkbox column on the left of each row + `#selectAll` in header.
  - Row-level direct migration trigger button (⇄) next to each item row.
- **Enhanced Bulk Action Bar** ([`warehouse.php`](file:///c:/xampp/htdocs/app/orders/pages/warehouse.php#L204)):
  - Upgraded to support zone selection, destination shelf picker, and quick "Migrate Selected" trigger across both spreadsheet and table modes.
- **Granular Item Migration Modal (`#itemMigrationModal`)**:
  - Item spec header (Brand, Model, Current Shelf, Available Units).
  - Quantity input with quick buttons (`[1]`, `[5]`, `[10]`, `[All]`).
  - Target Zone + Shelf selectors with `+ New Shelf` inline drawer.
  - Empty shelf cleanup confirmation dialog.

---

## Implementation Status (Completed)
- **Phase 1 (Schema & DB Migrations)**: Completed in [`orders/core/Schema.php`](file:///c:/xampp/htdocs/app/orders/core/Schema.php).
- **Phase 2 (Core Migration API & Actions)**: Completed in [`orders/api/migrate_inventory.php`](file:///c:/xampp/htdocs/app/orders/api/migrate_inventory.php) and [`actions.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/actions.php).
- **Phase 3 (Spreadsheet Row Selection & Trigger)**: Completed in [`spreadsheet_view.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/spreadsheet_view.php) and [`ajax_view.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/ajax_view.php).
- **Phase 4 (Modal & Bulk Bar UX)**: Completed in [`migration_modal.php`](file:///c:/xampp/htdocs/app/orders/pages/partials/warehouse/migration_modal.php), [`warehouse.php`](file:///c:/xampp/htdocs/app/orders/pages/warehouse.php), and [`warehouse_modals.js`](file:///c:/xampp/htdocs/app/orders/assets/js/warehouse/warehouse_modals.js).
- **Phase 5 (Depleted Shelf Cleanup & Archival Guard)**: Completed with automated prompt and soft-archival safeguard.
- **Phase 6 (AppSync Integration)**: Wired to instant multi-workstation refresh upon migration.

