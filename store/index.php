<?php
require_once __DIR__ . '/core/Cart.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$cart = new Cart();
$inventory = new Inventory($db);
$products = $inventory->getProducts();

$pageTitle = 'Home';
$activeCategory = 'all';

require __DIR__ . '/views/header.php';
?>

<div class="hero">
    <div class="hero-badge">⚡ Direct Warehouse Clearance</div>
    <h1>AS-IS WAREHOUSE CLEARANCE</h1>
    <p>Discounted secondary-market laptops, desktop PCs, and enterprise hardware. Curated directly from physical warehouse inventory.</p>
</div>

<div class="store-container">
    <div class="grid">
        <?php require __DIR__ . '/views/add_item.php'; ?>
        
        <?php foreach ($products as $p): ?>
            <?php require __DIR__ . '/views/product_card.php'; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>