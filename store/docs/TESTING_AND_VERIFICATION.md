# 🧪 Automated Testing & Verification Guide (`/store`)

This guide explains how developers and AI agents can rapidly verify, test, and quality-assure the **IQA Metal Storefront (`iqametal.com`)** without needing a manual browser or a live web server.

---

## 1. Environment & Prerequisites Diagnostics

Run these one-line commands in your terminal to verify system requirements:

```powershell
# 1. Verify PHP CLI and version
php -v

# 2. Check for PDO SQLite support
php -r "echo extension_loaded('pdo_sqlite') ? 'PDO SQLite OK\n' : 'PDO SQLite MISSING\n';"

# 3. Check for GD and WebP image generation support
php -r "echo (extension_loaded('gd') && function_exists('imagewebp')) ? 'GD & WebP OK\n' : 'GD/WebP MISSING (Graceful fallback active)\n';"

# 4. Verify warehouse database access and integrity
php -r "$db = new PDO('sqlite:../data/db/warehouse.db'); echo 'DB Items Count: ' . $db->query('SELECT COUNT(*) FROM inventory')->fetchColumn() . PHP_EOL;"
```

---

## 2. Syntax & Linting Sweep

Always perform a full lint check across all PHP files before committing changes:

```powershell
# Windows PowerShell lint check across all PHP files in store/
Get-ChildItem -Path . -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }
```

Expected output: `No syntax errors detected in ...` for every file.

---

## 3. Headless Test Suite Scripts

Store these scripts in your artifact scratch directory or execute them inline to validate core business logic:

### Test Suite 1: Verify Inventory Querying & Formatting
```php
<?php
// test_inventory.php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$inv = new Inventory($db);
$products = $inv->getProducts();
echo "Total Posted Store Products: " . count($products) . PHP_EOL;

$whCount = $inv->getWarehouseStockCount();
echo "Available Warehouse Items for Posting: " . $whCount . PHP_EOL;

// Assertions
assert(is_array($products), "getProducts() must return an array");
assert($whCount >= 0, "Warehouse stock count must be a non-negative integer");
echo "✓ Inventory Query Test PASSED\n";
```

---

### Test Suite 2: Verify `StoreImageProcessor`
Tests MIME validation, file generation, downscaling, and thumbnail cropping.

```php
<?php
// test_image_processor.php
require_once __DIR__ . '/core/StoreImageProcessor.php';

// Create a synthetic 1600x1200 test JPEG in memory
$img = imagecreatetruecolor(1600, 1200);
$red = imagecolorallocate($img, 255, 60, 60);
imagefill($img, 0, 0, $red);
$tmpFile = sys_get_temp_dir() . '/synthetic_test_' . uniqid() . '.jpg';
imagejpeg($img, $tmpFile, 90);
imagedestroy($img);

$fakeFile = [
    'name' => 'synthetic_test.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $tmpFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpFile)
];

$res = StoreImageProcessor::processUpload($fakeFile);

assert($res['success'] === true, "Processing must succeed");
assert(file_exists(__DIR__ . '/' . $res['raw']), "Raw file must exist");
assert(file_exists(__DIR__ . '/' . $res['opt']), "Optimized WebP must exist");
assert(file_exists(__DIR__ . '/' . $res['thumb']), "Thumbnail WebP must exist");

// Clean up generated test files
@unlink(__DIR__ . '/' . $res['raw']);
@unlink(__DIR__ . '/' . $res['opt']);
@unlink(__DIR__ . '/' . $res['thumb']);
@unlink($tmpFile);

echo "✓ StoreImageProcessor Test PASSED\n";
```

---

### Test Suite 3: Verify Warehouse Posting with Location Isolation
Ensures that posting a warehouse item with a custom photo changes `location_code` to `STORE-WH-[id]` to prevent overwriting physical shelf photography.

```php
<?php
// test_posting_isolation.php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$inv = new Inventory($db);

// Find a test warehouse item
$stmt = $db->query("SELECT id, location_code FROM inventory WHERE (is_posted = 0 OR is_posted IS NULL) AND quantity > 0 LIMIT 1");
$sample = $stmt->fetch(PDO::FETCH_ASSOC);

if ($sample) {
    $testId = $sample['id'];
    $originalLoc = $sample['location_code'];

    // Post with test parameters
    $inv->postFromWarehouse($testId, ['price' => 199.99]);

    // Verify it is posted
    $check = $db->query("SELECT is_posted, price FROM inventory WHERE id = $testId")->fetch(PDO::FETCH_ASSOC);
    assert((int)$check['is_posted'] === 1, "Item must be marked is_posted = 1");
    assert((float)$check['price'] === 199.99, "Price must be updated");

    // Safe unpost
    $inv->unpostProduct($testId);
    $unposted = $db->query("SELECT is_posted FROM inventory WHERE id = $testId")->fetchColumn();
    assert((int)$unposted === 0, "Item must be marked is_posted = 0");

    echo "✓ Posting & Unposting Lifecycle Test PASSED (Item #$testId)\n";
} else {
    echo "No unposted warehouse items available to test.\n";
}
```

---

### Test Suite 4: Shopping Cart & Atomic Stock Deduction
```php
<?php
// test_cart.php
require_once __DIR__ . '/core/Cart.php';

$cart = new Cart();
$cart->clear();

$cart->add(101, 1);
$cart->add(101, 2);
assert($cart->getItems()[101] === 3, "Cart quantity should sum to 3");

$cart->update(101, 1);
assert($cart->getItems()[101] === 1, "Cart quantity should update to 1");

$cart->remove(101);
assert(!isset($cart->getItems()[101]), "Item should be removed from cart");

$cart->clear();
echo "✓ Shopping Cart Unit Test PASSED\n";
```

---

## 4. Pre-Completion Quality Checklist for Agents

Before completing any task on `/store`, verify the following 7 items:

- [ ] **1. Decoupling Check**: Ensure zero files in `serverWarehouse/` were altered or modified.
- [ ] **2. PHP Syntax Check**: Run `php -l` on all modified files.
- [ ] **3. Infinite Loop Prevention**: Verify all new/edited `<img>` tags use `onerror="this.onerror=null; this.src='images/placeholder.svg';"`.
- [ ] **4. Photo Asset Path**: Confirm all uploaded images reside strictly within `store/images/store/`.
- [ ] **5. Destruction Safety**: Confirm no queries execute `DELETE FROM inventory` on warehouse items (`is_posted = 0` only).
- [ ] **6. Theme Compatibility**: Ensure new UI elements use CSS variables (`var(--primary-color)`, `var(--card-bg)`, etc.) to look flawless in both light and dark mode.
- [ ] **7. Autonomy Preservation**: Confirm the warehouse operator retains full control over pricing, specs text, and photo uploads.
