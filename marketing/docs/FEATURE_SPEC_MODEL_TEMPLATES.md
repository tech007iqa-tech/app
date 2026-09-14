# Feature Specification: Model Templates & Canonical Hardware Catalog
*Last Updated: September 5, 2026 10:52 PM*

## 🎯 Objective
Establish a centralized, reusable repository of canonical hardware specifications and verified marketing copy for popular hardware lines (e.g., Dell Wyse, HP ProDesk, Lenovo ThinkCentre). This eliminates redundant manual typing and guarantees technical accuracy across all sales channels.

---

## 📂 Implementation Location
- **Module Controller**: [`marketing/modules/model_templates/index.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/modules/model_templates/index.php)
- **Database Table**: `model_templates` in `marketing.db`
- **Warehouse Stock Database**: `inventory` table in `db/warehouse.db` (1,211 live units)
- **Asset Relationship**: Linked to `photos` table in `marketing.db` via `model_name` foreign reference.

---

## 🗄️ Database Blueprint

The `model_templates` table is managed via automated self-healing migrations in `includes/db.php`:

```sql
CREATE TABLE IF NOT EXISTS model_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_name TEXT NOT NULL UNIQUE,
    category TEXT,
    base_specs TEXT,
    marketing_copy TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_model_templates_name ON model_templates(model_name);
```

### Schema Fields
- `id`: Unique numeric primary key.
- `model_name`: Standardized manufacturer and model identifier (e.g., `Dell Wyse 5070 Thin Client`).
- `category`: Hardware classification (`Thin Client`, `Mini PC`, `Workstation`, `Laptop`, `Server`, `Networking`).
- `base_specs`: Standard technical block (CPU family, architecture, max RAM, video outputs, PSU specs).
- `marketing_copy`: The "Hero Description" highlighting value propositions, typical deployment scenarios, and power efficiency.
- `created_at` / `updated_at`: Timestamps for audit tracking.

---

## 📸 Photo Bank Asset Coverage Tracking

The Model Templates module automatically audits photo asset completeness for every registered model:

| Indicator Badge | Requirement | Purpose |
| :--- | :--- | :--- |
| **📦 Volume** | At least 1 photo categorized as `Bulk Stock` | Proves wholesale pallet volume to commercial buyers. |
| **✨ Quality** | At least 1 photo categorized as `Laptop`/`Workstation` | Demonstrates unit condition, ports, and aesthetic cleanliness. |
| **🖼️ Spec Proof** | At least 1 photo categorized as `Other` (BIOS/Specs screen) | Confirms hardware configuration and functionality. |

Models missing any of these assets display an actionable "Needs Photos" indicator that deep-links directly into the Photo Bucket upload interface.

---

## 🔄 Downstream System Integration

1. **Ad Generator**: Supplies the baseline marketing copy and technical specs when generating ad blasts.
2. **Photo Bucket**: Filters and auto-suggests existing model templates during photo upload.
3. **Leads & Campaigns**: Enables sales reps to attach standardized model sheets to prospective customer inquiries.

---

## 📊 Warehouse Stock Spreadsheet Integration
The Model Templates page features an integrated live spreadsheet view of all 1,211 inventory items from `db/warehouse.db`:
- **Editable Cells**: Styled input boxes (`.cell-input`) for Sector, Qty, Location, Brand, Model, CPU, RAM, Storage, Condition, Notes, and Price with keyboard arrow navigation (<kbd>↑</kbd>, <kbd>↓</kbd>, <kbd>Enter</kbd>).
- **Column Order**: `Sector` ➔ `Qty` (adjacent to Sector) ➔ `Location` ➔ `Brand` ➔ `Model` ➔ `CPU/Series` ➔ `RAM` ➔ `Storage` ➔ `Condition` ➔ `Notes` ➔ `Price` ➔ `Action`.
- **All-Header Sorting**: Bidirectional numeric and natural alphanumeric sorting across all 11 column headers with visual state indicators (`▲`/`▼`/`⇅`).
- **Flexible Multi-Term Search**: Matches space-separated query terms in any order. Searching automatically expands the Rows view to `All`, and deleting or clearing the search text keeps the Rows view default to `All`.
- **⚡ Prefill Action**: One-click action button on each row that transfers hardware specs directly into the top Template creation form with smooth scrolling and highlight pulse animation.
- **CSV Export**: Instant browser export of filtered/sorted warehouse stock to `.csv`.
