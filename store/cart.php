<?php
require_once __DIR__ . '/core/Cart.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$cart = new Cart();

// Handle POST actions (add to cart, update qty, remove)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if ($action === 'add' && $product_id > 0) {
        $cart->add($product_id);
    } elseif ($action === 'update' && $product_id > 0) {
        $qty = (int)($_POST['quantity'] ?? 1);
        $cart->update($product_id, $qty);
    } elseif ($action === 'remove' && $product_id > 0) {
        $cart->remove($product_id);
    } elseif ($action === 'checkout') {
        require_once __DIR__ . '/core/Inventory.php';
        $inventory = new Inventory($db);
        $cartItems = $cart->getItems();
        foreach ($cartItems as $pid => $qty) {
            $inventory->reduceQuantity($pid, $qty);
        }
        $cart->clear();
        $_SESSION['checkout_success'] = true;
    }
    
    header('Location: cart.php');
    exit;
}

$cartItems = $cart->getItems();

// Hydrate cart items from DB
$hydratedItems = [];
$total = 0;

if (!empty($cartItems)) {
    $ids = array_keys($cartItems);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT 
            i.id,
            i.brand || ' ' || i.model as title,
            i.price,
            p.optimized_path as image
        FROM inventory i
        LEFT JOIN location_photos p ON i.location_code = p.location_code
        WHERE i.id IN ($placeholders)
        GROUP BY i.id
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $p) {
        $qty = $cartItems[$p['id']];
        $subtotal = $p['price'] * $qty;
        $total += $subtotal;
        
        $imagePath = Inventory::resolveImagePath($p['image'] ?? '');
        
        $hydratedItems[] = [
            'id' => $p['id'],
            'title' => $p['title'] ?: 'Unknown Product',
            'price' => $p['price'],
            'qty' => $qty,
            'subtotal' => $subtotal,
            'image' => $imagePath
        ];
    }
}

$pageTitle = 'Your Cart';
require __DIR__ . '/views/header.php';
?>

<div class="cart-container">
    <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 2rem; font-family: 'Orbitron', sans-serif;">Your Cart</h2>
    
    <?php if (isset($_SESSION['checkout_success'])): ?>
        <?php unset($_SESSION['checkout_success']); ?>
        <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #c3e6cb; text-align: center;">
            <h3 style="margin-bottom: 0.5rem;">Order Successful!</h3>
            <p>Thank you for your purchase. The inventory has been updated.</p>
        </div>
    <?php endif; ?>

    <?php if (empty($hydratedItems)): ?>
        <p style="text-align: center; color: var(--light-text);">Your cart is empty.</p>
    <?php else: ?>
        <?php foreach ($hydratedItems as $item): ?>
            <div class="cart-item">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading='lazy' onerror="this.onerror=null; this.src='images/placeholder.svg';">
                <div class="cart-item-details">
                    <div class="cart-item-title"><?= htmlspecialchars($item['title']) ?></div>
                    <div class="cart-item-price">$<?= number_format($item['price'], 2) ?></div>
                </div>
                
                <div class="qty-controls">
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="quantity" value="<?= max(1, $item['qty'] - 1) ?>">
                        <button type="submit" class="qty-btn" <?= $item['qty'] <= 1 ? 'disabled style="opacity:0.5"' : '' ?>>-</button>
                    </form>
                    <span style="font-weight: bold; width:20px; text-align:center; color: var(--text-color);"><?= $item['qty'] ?></span>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="quantity" value="<?= $item['qty'] + 1 ?>">
                        <button type="submit" class="qty-btn">+</button>
                    </form>
                </div>
                
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                    <button type="submit" style="background:transparent; border:none; color:#ff003c; font-size:1.5rem; cursor:pointer;" title="Remove Item">&times;</button>
                </form>
            </div>
        <?php endforeach; ?>
        
        <div class="cart-total">
            Total: $<?= number_format($total, 2) ?>
        </div>
        
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="checkout">
            <button type="submit" class="checkout-btn">Proceed to Checkout</button>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>
