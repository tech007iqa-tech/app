# 🏗️ Storefront Technical Architecture (`/store`)

This document outlines the software architecture, database design, image processing pipeline, and API contracts for the **IQA Metal Storefront (`iqametal.com`)**.

---

## 1. High-Level Architectural Overview

The storefront follows a lightweight, decoupled MVC architecture built entirely with native PHP (no bulky framework dependencies), Vanilla CSS, and SQLite.

```
                  ┌───────────────────────────────┐
                  │          HTTP Client          │
                  │  (Guest Shopper / WH Admin)   │
                  └───────────────┬───────────────┘
                                  │
         ┌────────────────────────┼────────────────────────┐
         │ GET Pages              │ POST / AJAX            │
         ▼                        ▼                        ▼
┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│    index.php     │    │   category.php   │    │ admin_action.php │
│ (Store Catalog)  │    │  (Category View) │    │  (API Controller)│
└────────┬─────────┘    └────────┬─────────┘    └────────┬─────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │  core/Inventory.php   │
                     │  (Business Logic &    │
                     │   Image Resolution)   │
                     └───────────┬───────────┘
                                 │
         ┌───────────────────────┴───────────────────────┐
         ▼                                               ▼
┌──────────────────┐                           ┌──────────────────┐
│  data/db/        │                           │  core/           │
│  warehouse.db    │                           │  StoreImage-     │
│  (SQLite Engine) │                           │  Processor.php   │
└──────────────────┘                           └────────┬─────────┘
                                                        │
                                                        ▼
                                               ┌──────────────────┐
                                               │ store/images/    │
                                               │ store/           │
                                               │ (WebP Media Vault│
                                               └──────────────────┘
```

---

## 2. Database Architecture

The storefront connects to the shared physical warehouse database located at [`data/db/warehouse.db`](file:///c:/Users/Laptop/Documents/wh.latinospc/data/db/warehouse.db) via PDO in [`store/core/db.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/db.php).

### Database Pragmas & Concurrency
- `journal_mode = WAL` (Write-Ahead Logging): Allows simultaneous read operations while write transactions (such as posting items or checkout inventory deduction) occur without database locking.
- `synchronous = NORMAL`: Ensures fast transactional writes while maintaining ACID reliability.
- `busy_timeout = 5000`: Waits up to 5 seconds if a write lock is active before failing.

### Table: `inventory`
This table represents the master inventory for both physical warehouse stock and storefront listings:

| Column | Type | Default | Description |
|---|---|---|---|
| `id` | `INTEGER` | `PRIMARY KEY` | Unique product identifier. |
| `user_owner` | `TEXT` | `NULL` | Ownership identifier (`'STORE'` for manual store items, or warehouse user). |
| `sector` | `TEXT` | `NULL` | Category sector (`'Laptops'`, `'Desktops'`, `'Gaming'`, `'Servers'`, `'Parts'`). |
| `location_code` | `TEXT` | `NULL` | Shelf location (e.g. `'G2-L4'`, `'STORE-FRONT-...'`, or `'STORE-WH-[id]'`). |
| `brand` | `TEXT` | `NULL` | Hardware manufacturer (e.g. `'Dell'`, `'Lenovo'`, `'HP'`). |
| `model` | `TEXT` | `NULL` | Model identifier (e.g. `'ThinkPad T480'`, `'OptiPlex 7050'`). |
| `specs_json` | `TEXT` | `NULL` | Free-form technical specifications or JSON-encoded hardware attributes. |
| `quantity` | `INTEGER` | `0` | Active unit count available for sale. |
| `price` | `NUMERIC` | `0.00` | Retail selling price. |
| `is_posted` | `INTEGER` | `0` | **Storefront gate flag**: `1` = Live on public store; `0` = Unposted warehouse stock. |
| `created_at` | `DATETIME` | `CURRENT_TIMESTAMP` | Initial intake timestamp. |
| `updated_at` | `DATETIME` | `CURRENT_TIMESTAMP` | Last modification timestamp. |

### Table: `location_photos`
Tracks photography associated with inventory items and physical warehouse locations:

| Column | Type | Description |
|---|---|---|
| `id` | `INTEGER` | Primary key. |
| `location_code` | `TEXT` | Joined against `inventory.location_code`. |
| `original_filename`| `TEXT` | Original uploaded file name. |
| `archive_driver` | `TEXT` | Driver indicator (`'store_local'`, `'spinning_disk'`, `'local'`). |
| `archive_path` | `TEXT` | Path to the original raw or archived file. |
| `optimized_path` | `TEXT` | Path to the display-optimized WebP image (`opt_...webp`). |
| `thumbnail_path` | `TEXT` | Path to the square thumbnail WebP image (`thumb_...webp`). |
| `uploaded_by` | `TEXT` | Origin identifier (`'STORE'`, `'Warehouse Sync'`, or username). |
| `sector` | `TEXT` | Associated hardware category. |

---

## 3. Location Code Isolation Pattern

> [!IMPORTANT]
> **Why Location Isolation Matters:**
> In the warehouse, a single shelf location code like `G2-L4` may contain 15 units of laptops. If a store operator publishes one of those laptops with a custom photo, updating `location_photos` for `location_code = 'G2-L4'` would overwrite the physical shelf photo for all other 14 laptops in that shelf.

To eliminate photo collisions:
- When a warehouse item is posted **without** a custom photo, it retains its warehouse `location_code` and inherits existing warehouse photography.
- When a warehouse item is posted **with a custom photo**, [`Inventory::postFromWarehouse()`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Inventory.php) assigns an isolated location code:
  ```php
  $storeLoc = 'STORE-WH-' . (int)$id;
  $this->db->prepare("UPDATE inventory SET location_code = ? WHERE id = ?")->execute([$storeLoc, (int)$id]);
  $this->handlePhotoUpload($storeLoc, $item['sector'], $file);
  ```
  This guarantees that custom storefront photos belong **exclusively** to that specific listing and never alter physical warehouse shelf photography.

---

## 4. Media & Image Processing Pipeline (`StoreImageProcessor`)

All store photography is completely self-contained within [`store/images/store/`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/images/store/).

### Processing Flow
1. **Upload Received**: `admin_action.php` receives `$_FILES['photo']`.
2. **Security & MIME Verification**: [`StoreImageProcessor::processUpload()`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/StoreImageProcessor.php) checks extension whitelist (`jpg`, `jpeg`, `png`, `webp`, `gif`) and reads binary MIME type via `finfo(FILEINFO_MIME_TYPE)`.
3. **Dual-Tier WebP Generation**:
   - **Optimized Full View**: Scaled proportionally to max 1200px width/height at 85% WebP quality (`opt_store_[id].webp`).
   - **Square Thumbnail View**: Scaled and center-cropped to 250x250px at 80% WebP quality (`thumb_store_[id].webp`).
   - **Sanitized Raw Storage**: Original file archived as `raw_store_[id].[ext]`.
4. **Graceful Fallback**: If the server does not have the PHP GD extension loaded, the raw file is copied and used across all tiers without throwing fatal errors.

### Safe Cascading Image Resolution
When rendering product cards or the cart, [`Inventory::resolveImagePath($path)`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Inventory.php) applies a 5-step fallback sequence:

```php
// 1. Direct Store-Local Check (images/store/... or images/...)
if (file_exists(__DIR__ . '/../' . $cleanPath)) return $cleanPath;

// 2. Basename Match in store/images/store/
if (file_exists(__DIR__ . '/../images/store/' . basename($cleanPath))) {
    return 'images/store/' . basename($cleanPath);
}

// 3. Fallback: Marketing Asset Bucket (for warehouse items)
if (file_exists(__DIR__ . '/../../serverWarehouse/marketing/' . $cleanPath)) {
    return '../serverWarehouse/marketing/' . $cleanPath;
}

// 4. Fallback: Orders Location Photos (for warehouse items)
if (file_exists(__DIR__ . '/../../serverWarehouse/orders/' . $cleanPath)) {
    return '../serverWarehouse/orders/' . $cleanPath;
}

// 5. Default Fallback
return 'images/placeholder.svg';
```

---

## 5. API & Action Controller (`admin_action.php`)

All operational mutations route through [`store/admin_action.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/admin_action.php). Authentication is guarded via warehouse session cookies (`$_SESSION['authenticated'] === true`).

### Endpoints

| Method | Parameter | Description | Response Type |
|---|---|---|---|
| `GET` | `action=get_warehouse_items&sector=[sec]&search=[term]` | Live debounce search across 1,200+ warehouse items. | `application/json` |
| `POST` | `action=post_warehouse` (`id`, `price`, `quantity`, `sector`, `specs_json`, `photo`) | Publish a warehouse item with custom overrides. | JSON (if AJAX) or Redirect |
| `POST` | `action=unpost` (`id`) | Set `is_posted = 0` to safely remove item from store without deleting warehouse records. | JSON (if AJAX) or Redirect |
| `POST` | `action=add` (`brand`, `model`, `price`, `quantity`, `sector`, `specs_json`, `photo`) | Create a custom store-owned product. | Redirect |
| `POST` | `action=edit` (`id`, `brand`, `model`, `price`, `quantity`, `sector`, `specs_json`, `photo`) | Update product fields and photo inline. | Redirect |
| `POST` | `action=delete` (`id`) | Permanently delete a store custom product. | Redirect |

---

## 6. Related Documentation

- [**`AGENT_HANDOVER.md`**](file:///c:/Users/Laptop/Documents/wh.latinospc/store/docs/AGENT_HANDOVER.md): The essential 6 Golden Rules and survival guide for AI agents.
- [**`COMPONENTS_GUIDE.md`**](file:///c:/Users/Laptop/Documents/wh.latinospc/store/docs/COMPONENTS_GUIDE.md): Design tokens, themes, and reusable frontend views.
- [**`DATABASE_REFERENCE.md`**](file:///c:/Users/Laptop/Documents/wh.latinospc/store/docs/DATABASE_REFERENCE.md): Full schema definitions, PRAGMA concurrency rules, and SQL query book.
- [**`RECIPES_AND_EXTENSIONS.md`**](file:///c:/Users/Laptop/Documents/wh.latinospc/store/docs/RECIPES_AND_EXTENSIONS.md): Step-by-step blueprints for payments, audit logs, condition grading, and receipts.
- [**`TESTING_AND_VERIFICATION.md`**](file:///c:/Users/Laptop/Documents/wh.latinospc/store/docs/TESTING_AND_VERIFICATION.md): Headless test scripts and pre-completion QA checklists.

