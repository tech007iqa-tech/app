<?php
// views/header.php
require_once __DIR__ . '/../core/Tender.php';
require_once __DIR__ . '/../core/StoreAuth.php';

// Handle customer preview toggle for logged-in tenders
if (isset($_GET['preview']) && Tender::isLoggedIn()) {
    Tender::setCustomerPreview($_GET['preview'] == '1');
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$cartCount = isset($cart) ? $cart->getCount() : 0;
$activeCategory = strtolower($activeCategory ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="IQA Metal Hardware Storefront - Certified Refurbished Computers, Workstations & Warehouse Clearance.">
    <title><?= htmlspecialchars($pageTitle ?? 'Warehouse Clearance') ?> | IQA Metal</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link rel="stylesheet" href="assets/css/components.css">

    <script src="assets/js/store.js"></script>
</head>

<body>
    <?php
    // Privileged Store Tender top bar (only visible to authenticated staff)
    require __DIR__ . '/tender/tender_bar.php';
    ?>

    <header>
        <a href='index.php' class='logo-brand' title='IQA Metal - Secondary Market Hardware & Clearance'>
            <div class='logos'>
                <span class='logos-title'><span>IQA</span> Metal</span>
                <small class='logos-tagline'>Warehouse Hardware &amp; Electronics</small>
            </div>
        </a>

        <nav class='nav-links'>
            <a href='category.php?cat=laptops' class='<?= $activeCategory === 'laptops' ? 'active' : '' ?>'>Laptops</a>
            <a href='category.php?cat=desktops'
                class='<?= $activeCategory === 'desktops' ? 'active' : '' ?>'>Desktops</a>
            <a href='category.php?cat=servers' class='<?= $activeCategory === 'servers' ? 'active' : '' ?>'>Servers</a>
            <a href='category.php?cat=parts' class='<?= $activeCategory === 'parts' ? 'active' : '' ?>'>Parts</a>
            <a href='cart.php' class='<?= $activeCategory === 'cart' ? 'active' : '' ?>'>🛒 Cart <span
                    class='cart-pill'><?= $cartCount ?></span></a>

            <?php if (StoreAuth::isCustomer()): ?>
                <?php $currUser = StoreAuth::current(); ?>
                <a href='account.php' class='nav-user-btn <?= $activeCategory === 'account' ? 'active' : '' ?>'
                    title='My Customer Account'>
                    👤 <?= htmlspecialchars($currUser['display_name']) ?>
                </a>
            <?php elseif (StoreAuth::isTender()): ?>
                <?php $currUser = StoreAuth::current(); ?>
                <a href='account.php'
                    class='nav-user-btn nav-tender-user <?= $activeCategory === 'account' ? 'active' : '' ?>'
                    title='Staff Account'>
                    🏪 <?= htmlspecialchars($currUser['display_name']) ?>
                </a>
            <?php else: ?>
                <a href='login.php' class='nav-signin-btn' title='Sign into your Customer Account'>
                    🔑 Sign In
                </a>
            <?php endif; ?>

            <button type='button' class='theme-toggle-btn' onclick='toggleTheme()' title='Toggle Light / Dark Theme'>
                ◑
            </button>
        </nav>
    </header>

    <?php
    // Physical warehouse stock drawer modal for active Tenders
    require __DIR__ . '/warehouse_modal.php';
    ?>