# 🗄️ SQLite Database Reference & Query Manual (`/store`)

This document provides a comprehensive reference for the SQLite database powering the **IQA Metal Storefront (`iqametal.com`)** and its interface with the physical warehouse database at [`data/db/warehouse.db`](file:///c:/Users/Laptop/Documents/wh.latinospc/data/db/warehouse.db).

---

## 1. Connection & Pragmas

All database connections in `/store` are initialized in [`store/core/db.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/db.php) using PHP's native `PDO` extension.

```php
$dbPath = __DIR__ . '/../../data/db/warehouse.db';
$db = new PDO("sqlite:" . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### Essential PRAGMA Settings
When performing transactional modifications or building background tasks, ensure the following pragmas are active:

```sql
PRAGMA journal_mode = WAL;        -- Enables concurrent reads during writes
PRAGMA synchronous = NORMAL;      -- Balances durability with write throughput
PRAGMA busy_timeout = 5000;       -- Waits up to 5000ms on locks before failing
PRAGMA foreign_keys = ON;         -- Enforces relational integrity
```

> [!TIP]
> **Write-Ahead Logging (WAL Mode)**:
> In WAL mode, readers do not block writers, and writers do not block readers. This allows guest shoppers to browse products simultaneously while an admin posts or unposts warehouse stock.

---

## 2. Master Table Schemas

### 2.1 Table: `inventory`
The core catalog table storing both physical warehouse hardware and storefront listings.

```sql
CREATE TABLE inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_owner TEXT NOT NULL,          -- 'STORE' for store-created items, or warehouse user
    sector TEXT NOT NULL,              -- Hardware category: 'Laptops', 'Desktops', 'Gaming', 'Servers', 'Parts'
    location_code TEXT DEFAULT 'ZONE-0', -- Shelf bin (e.g. 'G2-L4') or isolated code ('STORE-WH-123')
    brand TEXT NOT NULL,               -- Manufacturer (e.g. 'Dell', 'Lenovo', 'HP', 'Apple')
    model TEXT NOT NULL,               -- Model name/number (e.g. 'Latitude 5400', 'ThinkPad T480')
    specs_json TEXT,                   -- Technical specifications (plain text or JSON metadata)
    quantity INTEGER DEFAULT 0,        -- Current in-stock quantity available
    status TEXT DEFAULT 'stocked',     -- 'stocked', 'reserved', 'out', 'maintenance'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_updated_by TEXT DEFAULT NULL, -- Username or 'STORE'
    price REAL DEFAULT 0.00,           -- Retail selling price (USD)
    is_posted INTEGER DEFAULT 0        -- 1 = Publicly listed in store; 0 = Warehouse reserve
);
```

#### Key Indexes on `inventory`:
- `CREATE INDEX idx_inventory_sector ON inventory(sector);`
- `CREATE INDEX idx_inventory_brand_model ON inventory(brand, model);`

> [!NOTE]
> **Store Visibility Rule**:
> An item appears in the storefront catalog IF AND ONLY IF `(is_posted = 1 OR user_owner = 'STORE')`.
> An item appears in the Warehouse Stock Drawer IF AND ONLY IF `(is_posted = 0 OR is_posted IS NULL) AND quantity > 0`.

---

### 2.2 Table: `location_photos`
Tracks photographic assets linked to inventory locations and products.

```sql
CREATE TABLE location_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    location_code TEXT NOT NULL,       -- Matches inventory.location_code
    original_filename TEXT NOT NULL,   -- Original uploaded file name
    archive_driver TEXT NOT NULL,      -- 'store_local', 'spinning_disk', 'local'
    archive_path TEXT NOT NULL,        -- Raw or preserved original asset path
    optimized_path TEXT NOT NULL,      -- Max 1200px WebP path (e.g. 'images/store/opt_...webp')
    thumbnail_path TEXT NOT NULL,      -- 250x250px square WebP path (e.g. 'images/store/thumb_...webp')
    uploaded_by TEXT NOT NULL,         -- 'STORE' or warehouse username
    category TEXT DEFAULT 'General',
    sector TEXT NOT NULL DEFAULT 'Laptops',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_code) REFERENCES locations(location_code) ON DELETE CASCADE
);
```

---

### 2.3 Table: `sold_items`
Records completed transactions and inventory deductions.

```sql
CREATE TABLE sold_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    location_code TEXT,
    sector TEXT NOT NULL DEFAULT 'Laptops',
    brand TEXT NOT NULL,
    model TEXT NOT NULL,
    specs_json TEXT,
    quantity INTEGER DEFAULT 1,
    sold_price REAL DEFAULT 0.00,
    sold_by TEXT,                      -- 'STORE_CHECKOUT' or sales technician
    reason TEXT DEFAULT 'Reconciliation Sale',
    sold_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

### 2.4 Supporting Warehouse Tables (Read-Only Context)

| Table Name | Primary Key | Description | Store Access |
|---|---|---|---|
| `sectors` | `id` | Standard categories and theme colors (`Laptops`, `Desktops`, etc.) | Read-Only |
| `locations` | `location_code` | Physical warehouse shelf bin coordinates (`G2-L4`, `A1-S2`, etc.) | Read-Only |
| `location_statuses`| `name` | Bin statuses (`Idle`, `Active`, etc.) | Read-Only |
| `pricing_rules` | `id` | Automated market price rules by CPU generation & grade | Reference |
| `settings` | `key` | System key-value configurations | Read-Only |

---

## 3. Practical SQL Query Manual

Here are verified, battle-tested SQL snippets for storefront operations:

### 3.1 Fetch Live Public Catalog (with Photos)
```sql
SELECT 
    i.id,
    i.sector AS category,
    i.brand || ' ' || i.model AS title,
    i.specs_json AS description,
    i.brand,
    i.model,
    i.price,
    i.quantity,
    i.location_code,
    i.user_owner,
    i.is_posted,
    i.updated_at,
    p.optimized_path AS image,
    p.thumbnail_path AS thumb
FROM inventory i
LEFT JOIN location_photos p ON i.location_code = p.location_code
WHERE (i.is_posted = 1 OR i.user_owner = 'STORE')
GROUP BY i.id
ORDER BY i.updated_at DESC, i.id DESC;
```

### 3.2 Search Unposted Warehouse Stock (Modal Drawer)
```sql
SELECT 
    i.id,
    i.sector AS category,
    i.brand || ' ' || i.model AS title,
    i.specs_json AS description,
    i.brand,
    i.model,
    i.price,
    i.quantity,
    i.location_code,
    p.optimized_path AS image,
    p.thumbnail_path AS thumb
FROM inventory i
LEFT JOIN location_photos p ON i.location_code = p.location_code
WHERE (i.is_posted = 0 OR i.is_posted IS NULL)
  AND i.quantity > 0
  AND (i.brand LIKE :term OR i.model LIKE :term OR i.specs_json LIKE :term OR i.location_code LIKE :term)
GROUP BY i.id
ORDER BY i.id DESC
LIMIT 50;
```

### 3.3 Atomic Checkout Inventory Deduction
Always use `MAX(0, quantity - ?)` inside a database transaction to prevent negative stock:

```php
$db->beginTransaction();
try {
    $stmt = $db->prepare("UPDATE inventory SET quantity = MAX(0, quantity - ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([(int)$purchasedQty, (int)$productId]);
    
    // Optional: Record in sold_items
    $recordStmt = $db->prepare("
        INSERT INTO sold_items (location_code, sector, brand, model, specs_json, quantity, sold_price, sold_by, reason)
        SELECT location_code, sector, brand, model, specs_json, ?, price, 'STORE_CHECKOUT', 'Store Online Order'
        FROM inventory WHERE id = ?
    ");
    $recordStmt->execute([(int)$purchasedQty, (int)$productId]);
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

### 3.4 Unposting vs. Deleting
```sql
-- UNPOST: Retains warehouse stock, merely unlists from public storefront
UPDATE inventory 
SET is_posted = 0, updated_at = CURRENT_TIMESTAMP 
WHERE id = :id;

-- DELETE: ONLY for items created manually by the store (user_owner = 'STORE')
DELETE FROM inventory 
WHERE id = :id AND user_owner = 'STORE';
```

---

## 4. Schema Migration Guidelines

When adding columns or modifying database structure:
1. **Never Drop or Recreate Existing Tables**: The warehouse database holds live production data.
2. **Use Safe `ALTER TABLE ADD COLUMN` Statements**: Check if the column exists first via `PRAGMA table_info(table_name)`.
3. **Always Backup Before DDL Operations**: Copy `warehouse.db` to a `.bak` file before running structural alterations.

### Migration Script Example (PHP):
```php
$cols = $db->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('warranty_months', $cols)) {
    $db->exec("ALTER TABLE inventory ADD COLUMN warranty_months INTEGER DEFAULT 0");
}
```
