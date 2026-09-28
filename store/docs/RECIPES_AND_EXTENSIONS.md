# 📖 Storefront Extension Recipes & Developer Cookbook (`/store`)

This guide provides tested, step-by-step implementation blueprints ("recipes") for future developers and AI agents extending the **IQA Metal Storefront (`iqametal.com`)**.

---

## Table of Contents
1. [Recipe 1: Adding a Live Payment Gateway (Stripe Checkout)](#recipe-1-adding-a-live-payment-gateway-stripe-checkout)
2. [Recipe 2: Order Logging & Sales Audit Trail](#recipe-2-order-logging--sales-audit-trail)
3. [Recipe 3: Adding Hardware Condition Grades (Grade A/B/C/Parts)](#recipe-3-adding-hardware-condition-grades-grade-abcparts)
4. [Recipe 4: Automated Low Stock Alerts & Zero-Quantity Handling](#recipe-4-automated-low-stock-alerts--zero-quantity-handling)
5. [Recipe 5: Registering a New Hardware Category / Sector](#recipe-5-registering-a-new-hardware-category--sector)
6. [Recipe 6: Order Receipt & Terms-Compliant Invoice Generator](#recipe-6-order-receipt--terms-compliant-invoice-generator)

---

## Recipe 1: Adding a Live Payment Gateway (Stripe Checkout)

To replace simulated checkout with real credit card processing via **Stripe Checkout**:

### Step 1: Install Stripe PHP SDK (or use direct REST API via cURL)
If avoiding Composer, Stripe's REST API can be invoked cleanly using native PHP `curl_init()`:

```php
// core/StripeGateway.php
class StripeGateway {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function createCheckoutSession(array $items, string $successUrl, string $cancelUrl) {
        $lineItems = [];
        foreach ($items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item['title'],
                        'description' => $item['description'],
                    ],
                    'unit_amount' => (int)round($item['price'] * 100), // in cents
                ],
                'quantity' => (int)$item['quantity'],
            ];
        }

        $payload = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
```

### Step 2: Webhook Listener for Fulfillment (`webhook_stripe.php`)
```php
// webhook_stripe.php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

if ($event && $event['type'] === 'checkout.session.completed') {
    $session = $event['data']['object'];
    $inventory = new Inventory($db);

    // Metadata contains purchased product IDs and quantities
    if (!empty($session['metadata']['order_items'])) {
        $orderItems = json_decode($session['metadata']['order_items'], true);
        foreach ($orderItems as $id => $qty) {
            $inventory->reduceQuantity($id, $qty);
        }
    }
    http_response_code(200);
    echo json_encode(['status' => 'success']);
}
```

---

## Recipe 2: Order Logging & Sales Audit Trail

The warehouse database already contains a `sold_items` table ready for sales recording.

### Implementation:
Hook into the checkout completion sequence in [`store/cart.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/cart.php) to atomically record order lines:

```php
function logStoreSale(PDO $db, int $productId, int $quantityPurchased, float $unitPrice, string $orderRef) {
    $stmt = $db->prepare("
        INSERT INTO sold_items (
            location_code, sector, brand, model, specs_json, 
            quantity, sold_price, sold_by, reason, sold_at
        )
        SELECT 
            location_code, sector, brand, model, specs_json, 
            ?, ?, 'STORE_CHECKOUT', ?, CURRENT_TIMESTAMP
        FROM inventory 
        WHERE id = ?
    ");
    $stmt->execute([$quantityPurchased, $unitPrice, "Store Order #" . $orderRef, $productId]);
}
```

---

## Recipe 3: Adding Hardware Condition Grades (Grade A/B/C/Parts)

Secondary market electronics benefit from standardized condition grading.

### Step 1: Update Schema (Safe Migration)
Run once to add a default grade column:
```sql
ALTER TABLE inventory ADD COLUMN condition_grade TEXT DEFAULT 'Tested Working';
```

### Step 2: Add Grade Pill in `views/product_card.php`
```php
<?php
$grade = $p['condition_grade'] ?? 'Tested';
$gradeColors = [
    'Grade A' => 'background: #10b981; color: #fff;',
    'Grade B' => 'background: #f59e0b; color: #fff;',
    'Grade C' => 'background: #ef4444; color: #fff;',
    'As-Is'   => 'background: #6b7280; color: #fff;',
];
$style = $gradeColors[$grade] ?? 'background: var(--primary-color); color: #fff;';
?>
<span class="badge" style="<?= $style ?> font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; font-weight: bold;">
    <?= htmlspecialchars($grade) ?>
</span>
```

---

## Recipe 4: Automated Low Stock Alerts & Zero-Quantity Handling

When an item hits 0 units, decide whether to auto-unpost or display "Out of Stock":

### Option A: Auto-Unpost Upon Depletion
In [`Inventory::reduceQuantity()`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/core/Inventory.php):
```php
public function reduceQuantity($id, $amount) {
    $stmt = $this->db->prepare("
        UPDATE inventory 
        SET quantity = MAX(0, quantity - ?),
            is_posted = CASE WHEN (quantity - ?) <= 0 THEN 0 ELSE is_posted END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([(int)$amount, (int)$amount, (int)$id]);
}
```

### Option B: Keep Posted with "SOLD OUT" Badge
In `views/product_card.php`:
```php
<?php if ($p['quantity'] <= 0): ?>
    <button type="button" class="buy-btn" style="background: #64748b; cursor: not-allowed;" disabled>
        Sold Out
    </button>
<?php else: ?>
    <button type="submit" class="buy-btn">Acquire</button>
<?php endif; ?>
```

---

## Recipe 5: Registering a New Hardware Category / Sector

To add a new category (e.g. `'Networking'` or `'Audio'`):

1. **Verify in Database**: Check existing sectors in `sectors` table:
   ```sql
   INSERT OR IGNORE INTO sectors (name, description, icon) 
   VALUES ('Networking', 'Routers, switches, access points, and rack gear', 'router');
   ```

2. **Update Global Header Filter (`store/views/header.php`)**:
   Add the link to the navigation bar:
   ```html
   <a href="category.php?sec=Networking" class="nav-item <?= ($activeCategory === 'Networking') ? 'active' : '' ?>">Networking</a>
   ```

3. **Update Category Dropdowns**:
   In `store/views/add_item.php`, `store/views/product_card.php`, and `store/views/warehouse_modal.php`, add:
   ```html
   <option value="Networking">Category: Networking</option>
   ```

---

## Recipe 6: Order Receipt & Terms-Compliant Invoice Generator

To create a printable order confirmation honoring the legal requirements from [`store/terms.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/terms.php):

```php
// views/receipt.php
function renderReceipt($orderNumber, array $items, float $subtotal, $customerInfo) {
    ?>
    <div class="receipt-box" style="max-width: 600px; margin: 2rem auto; font-family: 'Inter', sans-serif; border: 1px solid #ddd; padding: 2rem; border-radius: 8px;">
        <h2 style="margin-top:0;">IQA Metal Hardware Clearance</h2>
        <p><strong>Order #:</strong> <?= htmlspecialchars($orderNumber) ?><br>
           <strong>Date:</strong> <?= date('F j, Y, g:i a') ?></p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 1.5rem 0;">
            <thead>
                <tr style="border-bottom: 2px solid #333;">
                    <th style="text-align: left; padding: 8px;">Item</th>
                    <th style="text-align: center; padding: 8px;">Qty</th>
                    <th style="text-align: right; padding: 8px;">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 8px;"><?= htmlspecialchars($item['title']) ?></td>
                    <td style="text-align: center; padding: 8px;"><?= (int)$item['quantity'] ?></td>
                    <td style="text-align: right; padding: 8px;">$<?= number_format($item['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="text-align: right; font-size: 1.2rem; font-weight: bold; margin-bottom: 1.5rem;">
            Total: $<?= number_format($subtotal, 2) ?> USD
        </div>

        <div style="background: #f8fafc; border-left: 4px solid #ef4444; padding: 12px; font-size: 0.8rem; color: #475569;">
            <strong>ALL SALES ARE FINAL:</strong> Items are sold strictly as-is, where-is, with no warranties, returns, or refunds pursuant to the <a href="terms.php" target="_blank">IQA Metal Terms of Sale</a>.
        </div>
    </div>
    <?php
}
```
