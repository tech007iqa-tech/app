<?php
require_once 'core/database.php';
require_once __DIR__ . '/core/UI.php';
include 'core/auth.php';

// --- ROUTING & LOGIC PHASE (Pre-Output) ---
$view = $_GET['view'] ?? 'default';
$is_new_order = isset($_GET['customer_id']);

$routes = [
    'register'          => ['page' =>'pages/new_customer.php',     'css' => 'customer_registry.css'],
    'orders'            => ['page' =>'pages/orders.php',           'css' => 'orders.css'],
    'leads'             => ['page' =>'pages/leads.php',            'css' => 'leads.css'],
    'warehouse'         => ['page' =>'pages/warehouse.php',        'css' => 'warehouse.css'],
    'import_warehouse'  => ['page' =>'pages/import_warehouse.php', 'css' => 'warehouse.css'],
    'inbound'           => ['page' =>'pages/inbound.php',          'css' => 'inbound.css'],
    'settings'          => ['page' =>'pages/settings.php',         'css' => 'style.css'],
    'calendar'          => ['page' =>'pages/calendar.php',         'css' => 'calendar.css'],
    'default'           => ['page' =>'pages/customer_registry.php','css' => 'customer_registry.css'],
    'new_order'         => ['page' =>'pages/new_order.php',        'css' => 'new_order.css'],
    'trends'            => ['page' =>'pages/trends.php',           'css' => 'trends.css'],
    'import_sales'      => ['page' =>'pages/import_sales.php',      'css' => 'orders.css']
];

$active_key = $is_new_order ? 'new_order' : (isset($routes[$view]) ? $view : 'default');

// --- ROLE BASED ACCESS CONTROL ---
$user_role = $_SESSION['role'] ?? 'Operator';

// Technicians cannot access Orders — redirect to Tech Dashboard
if ($user_role === 'Technician') {
    header("Location: ../tech/index.php");
    exit();
}

if ($user_role === 'Operator') {
    $allowed_operator_keys = ['warehouse', 'import_warehouse', 'inbound', 'settings'];
    if (!in_array($active_key, $allowed_operator_keys)) {
        $active_key = 'warehouse';
    }
} elseif ($user_role === 'Front Desk') {
    $allowed_front_desk_keys = ['trends', 'calendar', 'settings'];
    if (!in_array($active_key, $allowed_front_desk_keys)) {
        $active_key = 'calendar';
    }
} elseif ($user_role !== 'Admin') {
    $active_key = 'warehouse';
}

$active_route = $routes[$active_key];

// Global State Initialization
$selected_sector = $_GET['sector'] ?? 'Laptops';
$selected_loc = $_GET['loc'] ?? null;

// Order Creation Logic (Move here to prevent headers already sent)
if (isset($_GET['action']) && $_GET['action'] === 'create_new_order' && isset($_GET['customer_id'])) {
    $conn_o = Database::orders();
    $new_order_id = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $stmt = $conn_o->prepare("INSERT INTO orders (order_id, customer_id, status) VALUES (?, ?, 'active')");
    $stmt->execute([$new_order_id, $_GET['customer_id']]);
    header("Location: index.php?customer_id=" . urlencode($_GET['customer_id']) . "&order_id=" . $new_order_id);
    exit();
}

if ($is_new_order) {
    $current_customer = $_GET['customer_id'];
    $current_order = $_GET['order_id'] ?? null;
}

// BUFFERS the page content so sub-pages can still do redirects or set headers if needed (though top-level is better)
ob_start();
include $active_route['page'];
$page_content = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Entry | System</title>
    <meta name="description" content="System Order Management and Warehouse Control System. Efficiently manage batches, and customer fulfillments.">

    <!-- Optimize Third-Party Connections (Non-blocking Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap">
    </noscript>

    <!-- Global Component Styles -->
    <link rel="stylesheet" href="assets/styles/components.css">
    <link rel="stylesheet" href="assets/styles/dialogs.css?v=<?= filemtime('assets/styles/dialogs.css') ?>">

    <!-- Primary Stylesheet (LCP Priority) -->
    <link rel="stylesheet" href="assets/styles/style.css?v=<?= filemtime('assets/styles/style.css') ?>">

    <!-- Conditional Style Discovery -->
    <?php
        if ($active_route['css'] !== 'style.css') {
            $css_path = 'assets/styles/' . $active_route['css'];
            echo '<link rel="stylesheet" href="'.$css_path.'?v='.filemtime($css_path).'">';
        }
    ?>

    <link rel="icon" type="image/png" href="assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">

    <!-- Security & CSRF Context -->
    <meta name="csrf-token" content="<?= htmlspecialchars(Security::getToken()) ?>">

    <!-- Global API & Sync Engine (AppSync) -->
    <script src="assets/js/app_sync.js?v=<?= filemtime('assets/js/app_sync.js') ?>"></script>

    <!-- Logic Initialization (Deferred) -->
    <script src="assets/js/sync.js?v=<?= filemtime('assets/js/sync.js') ?>" defer></script>
</head>

<body class="modern-theme">
    <?= UI::theme_init_script() ?>
    <?= UI::render_notifications() ?>
    <div class="breadcrumb-container" role="banner" style="max-width: 800px; margin: 0 auto 20px auto; width: 100%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <?php if ($user_role === 'Admin'): ?>
                <a href="index.php"
                    class="crumb <?= !isset($_GET['customer_id']) && !isset($_GET['view']) ? 'active' : '' ?>">
                    <span class="step-num">1</span> Customers
                </a>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'register'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">2</span> Register
                </a>
                <?php endif; ?>

                <?php if (isset($_GET['customer_id'])): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">2</span> Order Entry
                </a>
                <?php endif; ?>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'inbound'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">📥</span> Inbound
                </a>
                <?php endif; ?>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">⚙️</span> Settings
                </a>
                <?php endif; ?>
            <?php elseif ($user_role === 'Front Desk'): ?>
                <a href="index.php?view=calendar" class="crumb <?= !isset($_GET['view']) || $_GET['view'] === 'calendar' ? 'active' : '' ?>">
                    <span class="step-num">📅</span> Calendar Portal
                </a>
                <?php if (isset($_GET['view']) && $_GET['view'] === 'trends'): ?>
                <span class="separator">/</span>
                <a href="index.php?view=trends" class="crumb active">
                    <span class="step-num">📈</span> Trends Analysis
                </a>
                <?php endif; ?>
                <?php if (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">⚙️</span> Personal Settings
                </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="index.php?view=warehouse" class="crumb <?= !isset($_GET['view']) || $_GET['view'] === 'warehouse' ? 'active' : '' ?>">
                    <span class="step-num">🏬</span> Warehouse Portal
                </a>
                <?php if (isset($_GET['view']) && $_GET['view'] === 'inbound'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">📥</span> Inbound Portal
                </a>
                <?php endif; ?>
                <?php if (isset($_GET['view']) && $_GET['view'] === 'settings'): ?>
                <span class="separator">/</span>
                <a href="#" class="crumb active">
                    <span class="step-num">⚙️</span> Personal Settings
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- Consolidated Hamburger Navigation Menu -->
        <div class="nav-hamburger-container" style="position: relative; z-index: 100;">
            <button type="button" class="btn-hamburger" onclick="toggleNavDropdown(event)" aria-label="Toggle navigation menu" style="width: 44px; height: 40px; border-radius: 12px; background: white; border: 1px solid var(--border-color); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: all 0.2s; box-shadow: var(--shadow-sm);">
                ☰
            </button>
            <div id="nav-dropdown-menu" class="nav-dropdown" style="display: none; position: absolute; top: calc(100% + 8px); right: 0; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); box-shadow: var(--shadow-lg); width: 220px; padding: 8px; flex-direction: column; gap: 4px; animation: fadeInDown 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                <?php if ($user_role === 'Admin' || $user_role === 'Front Desk'): ?>
                    <a href="index.php?view=calendar" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'calendar' ? 'active' : '' ?>">
                        <span>📅</span> Calendar
                    </a>
                <?php endif; ?>

                <?php if ($user_role === 'Admin'): ?>
                    <a href="index.php?view=leads" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'leads' ? 'active' : '' ?>">
                        <span>🎯</span> Leads
                    </a>
                <?php endif; ?>

                <?php if ($user_role === 'Admin' || $user_role === 'Operator'): ?>
                    <a href="index.php?view=warehouse" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'warehouse' ? 'active' : '' ?>">
                        <span>🏬</span> Warehouse
                    </a>
                <?php endif; ?>

                <?php if ($user_role === 'Admin'): ?>
                    <a href="index.php?view=orders" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'orders' ? 'active' : '' ?>">
                        <span>📦</span> All Orders
                    </a>
                <?php endif; ?>

                <?php if ($user_role === 'Admin' || $user_role === 'Front Desk'): ?>
                    <a href="index.php?view=trends" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'trends' ? 'active' : '' ?>">
                        <span>📈</span> Trends Analysis
                    </a>
                <?php endif; ?>

                <?php if ($user_role === 'Admin' || $user_role === 'Operator'): ?>
                    <a href="index.php?view=inbound" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'inbound' ? 'active' : '' ?>">
                        <span>📥</span> Inbound
                    </a>
                <?php endif; ?>

                <a href="index.php?view=settings" class="dropdown-item <?= isset($_GET['view']) && $_GET['view'] === 'settings' ? 'active' : '' ?>">
                    <span>⚙️</span> Settings
                </a>
            </div>
        </div>

        <script>
        function toggleNavDropdown(event) {
            event.stopPropagation();
            const menu = document.getElementById('nav-dropdown-menu');
            if (menu) {
                const isOpen = menu.style.display === 'flex';
                menu.style.display = isOpen ? 'none' : 'flex';

                if (!isOpen) {
                    const closeMenu = (e) => {
                        if (!menu.contains(e.target)) {
                            menu.style.display = 'none';
                            document.removeEventListener('click', closeMenu);
                        }
                    };
                    document.addEventListener('click', closeMenu);
                }
            }
        }
        </script>
    </div>

    <div class="container <?= in_array($active_key, ['new_order', 'orders', 'warehouse', 'leads', 'import_warehouse', 'calendar', 'inbound', 'trends']) ? 'order-view' : '' ?>" role="main">
        <?= $page_content ?>
    </div>
    <?php if ($active_key !== 'calendar'): ?>
    <footer class="footer" role="contentinfo">
        <p style="text-align: center;" ><a href="#">&copy; <?= date('M Y') ?> System</a> | Managed Inventory & Order Fulfillments</p>
    </footer>
    <?php endif; ?>
    <!-- Load view-specific JavaScript -->
    <?php if (in_array($active_key, ['new_order', 'warehouse', 'import_warehouse'])): ?>
        <script src="assets/js/inventory_data.js?v=<?= filemtime('assets/js/inventory_data.js') ?>" defer></script>
        <script src="assets/js/vocabulary.js?v=<?= filemtime('assets/js/vocabulary.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($active_key === 'new_order'): ?>
        <script src="assets/js/new_order.js?v=<?= filemtime('assets/js/new_order.js') ?>" defer></script>
        <script src="assets/js/import_work_order.js?v=<?= filemtime('assets/js/import_work_order.js') ?>" defer></script>
    <?php elseif ($active_key === 'warehouse'): ?>
        <script src="assets/js/camera_uploader.js?v=<?= filemtime('assets/js/camera_uploader.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_gate.js?v=<?= filemtime('assets/js/warehouse/warehouse_gate.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_form.js?v=<?= filemtime('assets/js/warehouse/warehouse_form.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_spreadsheet.js?v=<?= filemtime('assets/js/warehouse/warehouse_spreadsheet.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_bulk.js?v=<?= filemtime('assets/js/warehouse/warehouse_bulk.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_modals.js?v=<?= filemtime('assets/js/warehouse/warehouse_modals.js') ?>" defer></script>
        <script src="assets/js/warehouse/warehouse_inventory.js?v=<?= filemtime('assets/js/warehouse/warehouse_inventory.js') ?>" defer></script>
        <script src="assets/js/warehouse.js?v=<?= filemtime('assets/js/warehouse.js') ?>" defer></script>
    <?php elseif ($active_key === 'import_warehouse'): ?>
        <script src="assets/js/import_warehouse.js?v=<?= filemtime('assets/js/import_warehouse.js') ?>" defer></script>
    <?php elseif ($active_key === 'default' || $active_key === 'register'): ?>
        <script src="assets/js/customer_registry.js?v=<?= filemtime('assets/js/customer_registry.js') ?>" defer></script>
        <script src="assets/js/pipeline.js?v=<?= filemtime('assets/js/pipeline.js') ?>" defer></script>
    <?php elseif ($active_key === 'leads' && file_exists('assets/js/leads.js')): ?>
        <script src="assets/js/leads.js?v=<?= filemtime('assets/js/leads.js') ?>" defer></script>
    <?php elseif ($active_key === 'orders'): ?>
        <script src="assets/js/orders.js?v=<?= filemtime('assets/js/orders.js') ?>" defer></script>
    <?php elseif ($active_key === 'inbound'): ?>
        <script src="assets/js/inbound.js?v=<?= filemtime('assets/js/inbound.js') ?>" defer></script>
    <?php elseif ($active_key === 'trends'): ?>
        <script src="assets/js/trends/trends_nav.js?v=<?= filemtime('assets/js/trends/trends_nav.js') ?>" defer></script>
        <script src="assets/js/trends/trends_charts.js?v=<?= filemtime('assets/js/trends/trends_charts.js') ?>" defer></script>
        <script src="assets/js/trends/trends_widgets.js?v=<?= filemtime('assets/js/trends/trends_widgets.js') ?>" defer></script>
        <script src="assets/js/trends/trends_modals.js?v=<?= filemtime('assets/js/trends/trends_modals.js') ?>" defer></script>
        <script src="assets/js/trends.js?v=<?= filemtime('assets/js/trends.js') ?>" defer></script>
    <?php elseif ($active_key === 'settings'): ?>
        <script src="assets/js/settings.js?v=<?= filemtime('assets/js/settings.js') ?>" defer></script>
    <?php endif; ?>
    <!-- Global Dialog Engine -->
    <script src="assets/js/dialogEngine.js?v=<?= filemtime('assets/js/dialogEngine.js') ?>"></script>
    <!-- Global Notifications Engine -->
    <script src="assets/js/notifications.js"></script>
</body>

</html>