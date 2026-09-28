# 🧠 AI Technical Handover & Agent Guide (`/store`)

> [!NOTE]
> This document is written specifically for AI agents (and developers) tasked with maintaining, extending, or debugging the **IQA Metal Storefront (`iqametal.com`)**. Read this guide before making any changes.

---

## 🧭 The Mental Model: Storefront vs. Warehouse

The application operates as two distinct but coordinated domains:

1. **The Physical Warehouse (`/serverWarehouse`)**:
   - Manages physical hardware intake, shelf bins (`G2-L4`), diagnostic testing, technician repairs, and wholesale inventory.
   - **RULE**: **Do NOT modify `/serverWarehouse` files when working on the store!** Keep the store decoupled.

2. **The Storefront (`/store`)**:
   - A public-facing retail storefront with a shopping cart, legal compliance notices, and an administrative overlay for warehouse operators.
   - Shared database: Both domains read from [`data/db/warehouse.db`](file:///c:/Users/Laptop/Documents/wh.latinospc/data/db/warehouse.db).
   - Visibility flag: Products with `is_posted = 1` or `user_owner = 'STORE'` are visible to the public. Products with `is_posted = 0` are in the warehouse reserve.

---

## ⚡ The 6 Golden Rules for Store Agents

### 1. Store Images Belong in `store/images/store/`
- **Never** write store-uploaded photos to `serverWarehouse/marketing/assets/photo_bucket/`.
- All uploads must go through [`StoreImageProcessor::processUpload($file)`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/StoreImageProcessor.php), which automatically generates `opt_...webp` and `thumb_...webp` in [`store/images/store/`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/images/store/).

### 2. Never Overwrite Warehouse Shelf Codes with Custom Photos
- If an operator posts an item from warehouse shelf `G2-L4` and uploads a custom photo, **do not** update `location_photos WHERE location_code = 'G2-L4'`. That shelf may contain 15 other units!
- Instead, assign an isolated code:
  ```php
  $storeLoc = 'STORE-WH-' . (int)$id;
  $this->db->prepare("UPDATE inventory SET location_code = ? WHERE id = ?")->execute([$storeLoc, (int)$id]);
  $this->handlePhotoUpload($storeLoc, $sector, $file);
  ```

### 3. Never `DELETE` Warehouse Records When Unposting
- When an operator removes a product from the storefront, **do not execute a `DELETE` query** on warehouse items! That would delete physical warehouse stock!
- Always use `unpostProduct($id)`:
  ```php
  UPDATE inventory SET is_posted = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?
  ```
  Only items where `user_owner = 'STORE'` should be eligible for true deletion.

### 4. Always Guard `<img>` Tags Against Infinite Loops
- In HTML/PHP templates, never write a raw `onerror="this.src='images/placeholder.svg';"` without nullifying the handler first! If the fallback image is delayed or blocked, the browser will enter a recursive infinite fetch loop.
- **Correct Pattern**:
  ```html
  <img src="<?= htmlspecialchars($img) ?>" onerror="this.onerror=null; this.src='images/placeholder.svg';" loading="lazy">
  ```

### 5. Always Preserve User Autonomy in Posting Decisions
- Never make arbitrary assumptions about retail pricing, quantity allocation, or title refinement.
- The UI in [`store/views/warehouse_modal.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/views/warehouse_modal.php) is specifically designed to let the human operator choose the retail price, quantity, category, specs text, and custom photo. Keep this workflow intact.

### 6. Respect Vanilla CSS & Dark/Light Themes
- Do not introduce TailwindCSS or bulky CSS frameworks unless explicitly instructed.
- All styles must use CSS variables defined in [`store/assets/css/store.css`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/assets/css/store.css) (`var(--primary-color)`, `var(--bg-color)`, `var(--card-bg)`, etc.).
- The theme toggle in [`store/views/header.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/views/header.php) relies on `document.documentElement.setAttribute('data-theme', '...')`.

---

## 🔍 Key Classes & Methods Cheat Sheet

### [`Inventory.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Inventory.php)

```php
$inventory = new Inventory($db);

// 1. Get live public storefront products (filtered optionally by sector)
$products = $inventory->getProducts('Laptops');

// 2. Get unposted warehouse inventory for the warehouse stock drawer
$available = $inventory->getWarehouseAvailableProducts('Gaming', 'i7');

// 3. Get count of available warehouse items ready to post
$count = $inventory->getWarehouseStockCount();

// 4. Post a warehouse item to the storefront with customized attributes
$inventory->postFromWarehouse($id, [
    'price' => 249.99,
    'quantity' => 3,
    'sector' => 'Laptops',
    'specs_json' => 'Intel Core i5, 16GB RAM, 512GB SSD'
], $_FILES['photo'] ?? null);

// 5. Unpost product safely
$inventory->unpostProduct($id);

// 6. Safe image URL resolution (handles store, marketing, orders, and fallback)
$resolvedUrl = Inventory::resolveImagePath($rawDbPath);
```

### [`StoreImageProcessor.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/StoreImageProcessor.php)

```php
// Processes $_FILES['photo'] -> generates raw, opt, and thumb WebP
$res = StoreImageProcessor::processUpload($_FILES['photo']);
if ($res['success']) {
    $rawPath   = $res['raw'];   // e.g. "images/store/raw_store_8a963...jpg"
    $optPath   = $res['opt'];   // e.g. "images/store/opt_store_8a963...webp"
    $thumbPath = $res['thumb']; // e.g. "images/store/thumb_store_8a963...webp"
}
```

### [`Cart.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Cart.php)

```php
$cart = new Cart();
$cart->add($productId);
$cart->update($productId, 2);
$cart->remove($productId);
$items = $cart->getItems(); // returns array: [productId => quantity]
$count = $cart->getTotalQuantity();
$cart->clear();
```

---

## 🐛 Debugging & Common Pitfalls

1. **`is_uploaded_file()` in CLI / Automated Test Scripts**:
   - In PHP, `move_uploaded_file()` strictly returns `false` if the file was not uploaded via an HTTP POST request.
   - [`StoreImageProcessor`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/StoreImageProcessor.php) has a built-in check: `is_uploaded_file($file['tmp_name']) ? move_uploaded_file(...) : copy(...)`. If creating new upload utilities, always include this fallback to support automated CLI testing.

2. **Session Authentication & Admin Mode**:
   - Authentication is shared with warehouse user logins via `$_SESSION['authenticated'] === true`.
   - If `!$is_auth`, the storefront renders in **Guest Mode** (add to cart, shopping cart view, no edit forms, no warehouse stock button).
   - If `$is_auth`, the storefront renders in **Admin Mode** (inline editable fields, quick update buttons, unpost action, "⚡ Post from Warehouse Stock" shortcut, and the warehouse stock drawer).

3. **Specs JSON Parsing**:
   - Warehouse items store hardware descriptions in `specs_json`.
   - When formatting for guest display, [`Inventory::formatProducts()`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Inventory.php) attempts to `json_decode()` this field. If it contains a JSON object (e.g. `{"cpu":"i5","ram":"16GB"}`), it displays as `Cpu: i5 | Ram: 16GB`.
   - If it is plain text, it displays the raw text as-is. Always handle both strings and JSON gracefully.
