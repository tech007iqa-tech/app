<?php
require_once __DIR__ . '/core/Cart.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

$category = $_GET['cat'] ?? 'laptops';
$cart = new Cart();
$inventory = new Inventory($db);
$products = $inventory->getProducts($category);

$pageTitle = ucfirst($category);
$activeCategory = strtolower($category);

require __DIR__ . '/views/header.php';
?>

<div class="hero">
    <div class="hero-badge">📦 <?= htmlspecialchars(ucfirst($category)) ?> Inventory</div>
    <h1><?= htmlspecialchars(strtoupper($category)) ?></h1>
    <p>Available certified hardware in this category ready for immediate dispatch.</p>
</div>

<div class="store-container">
    <div class="grid">
        <?php require __DIR__ . '/views/add_item.php'; ?>
        
        <?php if (empty($products)): ?>
            <div style="text-align: center; grid-column: 1/-1; padding: 4rem 1rem; color: var(--light-text); background: var(--card-bg); border: 1px dashed var(--card-border); border-radius: var(--border-radius);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📦</div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-color); margin-bottom: 0.5rem;">No items currently posted in this category.</h3>
                <p style="font-size: 0.9rem;">Check back soon or open Warehouse Stock to post units from inventory.</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <?php require __DIR__ . '/views/product_card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>
