<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/Company.php';

// Redirect to Setup Wizard if system has not been initialized yet
if (!Company::isSetupComplete()) {
    header("Location: setup/index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Company::getSystemName()) ?> | Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Global Component Styles -->
    <link rel="stylesheet" href="assets/css/components.css?v=<?= filemtime('assets/css/components.css') ?>">

    <!-- Primary Stylesheet -->
    <link rel="stylesheet" href="assets/css/portal.css?v=<?= filemtime('assets/css/portal.css') ?>">
    <link rel="icon" type="image/png" href="./orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>

<body>

    <!-- User Authentication Status Bar -->
    <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
        <div style="position: absolute; top: 16px; right: 20px; display: flex; align-items: center; gap: 10px; z-index: 100;">
            <span style="font-size: 0.85rem; color: var(--text-dim); background: rgba(8, 45, 69, 0.7); border: 1px solid var(--glass-border); padding: 6px 14px; border-radius: 20px; backdrop-filter: blur(8px);">
                👤 <?= htmlspecialchars($_SESSION['username']) ?> <span style="opacity: 0.7;">(<?= htmlspecialchars($_SESSION['role'] ?? 'User') ?>)</span>
            </span>
            <a href="orders/core/logout.php" style="font-size: 0.85rem; font-weight: 700; color: #ef4444; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); padding: 6px 14px; border-radius: 20px; text-decoration: none;">
                🚪 Sign Out
            </a>
        </div>
    <?php else: ?>
        <div style="position: absolute; top: 16px; right: 20px; z-index: 100;">
            <a href="orders/core/login.php" style="font-size: 0.85rem; font-weight: 700; color: #38bdf8; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); padding: 6px 16px; border-radius: 20px; text-decoration: none;">
                🔒 Sign In
            </a>
        </div>
    <?php endif; ?>

    <div class="background-blob"></div>

    <main class="portal-main">
        <div class="module-grid">
            <!-- TECH MODULE -->
            <a href="tech/index.php" class="module-card">
                <div class="icon-box">🔧</div>
                <h2>Technician Dashboard</h2>
                <p>Hardware testing, computer logs, and parts inventory management.</p>
                <div class="badge badge-tech">Module Active</div>
            </a>

            <!-- ORDERS MODULE -->
            <a href="orders/index.php" class="module-card">
                <div class="icon-box">📊</div>
                <h2>Order Manager</h2>
                <p>Comprehensive CRM, batch fulfillment, and customer registry with advanced warehouse location
                    tracking.
                </p>
                <div class="badge badge-orders">Module Active</div>
            </a>

            <!-- MARKETING MODULE -->
            <a href="marketing/index.php" class="module-card">
                <div class="icon-box">📣</div>
                <h2>Marketing Hub</h2>
                <p>Lead generation, campaign tracking, and outreach automation for B2B expansion.</p>
                <div class="badge badge-marketing">Module Active</div>
            </a>

            <!-- LABELS MODULE -->
            <a href="labels/index.php" class="module-card">
                <div class="icon-box">🏷️</div>
                <h2>Labels & Intake</h2>
                <p>Hardware inventory tracking, thermal printing, and barcode label generator.</p>
                <div class="badge badge-labels">Module Active</div>
            </a>
        </div>

        <header class="portal-header">
            <h1><?= htmlspecialchars(Company::getSystemName()) ?></h1>
            <p class="tagline"><?= htmlspecialchars(Company::getTagline()) ?></p>
            <p class="description">
                Welcome to your all-in-one operations hub! Tailored specifically for the used computer and electronics refurbishing market, this portal
                is here to keep operations smooth, fast, and users synchronized. [Operators, Front Desk, Technicians and Admins]
            </p>
            <p class="description">
                Whether you're operating the <strong>Tech Center</strong> for precise hardware diagnostics, test yield
                auditing, and live parts inventory tracking; leveraging the <strong>Orders Module</strong> for
                AI-powered intake digitization, physical warehouse logistics, and real-time CRM synchronization;
                generating thermal barcodes and hardware tracking tags in the <strong>Labels Module</strong>; or
                driving growth in the <strong>Marketing Hub</strong> via automated lead generation, campaign tracking,
                and performance analytics—this integrated ecosystem unifies every aspect of our workflow.
            </p>
        </header>
    </main>


    <footer class="footer-note">
        <a href="<?= htmlspecialchars(Company::getUrl()) ?>" style="color: white; text-decoration: none;" target="_blank"><?= htmlspecialchars(Company::getName()) ?></a>.
        Inventory System &copy; <?php echo date('Y'); ?> | Powered by <?= htmlspecialchars(Company::getSystemName()) ?>
        <?php if (!Company::isSetupComplete() || (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && ($_SESSION['role'] ?? '') === 'Admin')): ?>
            &nbsp;&bull;&nbsp; <a href="setup/index.php?reconfigure=1" style="color: #38bdf8; text-decoration: underline; font-size: 0.8rem;">⚙️ Reconfigure System</a>
        <?php endif; ?>
    </footer>

    <!-- Global Notifications Engine -->
    <script src="assets/js/notifications.js?v=<?= filemtime('assets/js/notifications.js') ?>"></script>
</body>

</html>