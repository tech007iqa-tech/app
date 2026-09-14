<?php
// includes/header.php
// This snippet forms the top half of the HTML document and persistent Sidebar menu.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../core/Security.php';
require_once __DIR__ . '/../../core/Company.php';
Security::init();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title>TEST ENVIROMENT</title>

    <!-- Global UI & Components -->
    <?php require_once __DIR__ . '/../../core/UI.php'; ?>
    <link rel="stylesheet" href="../assets/css/components.css">

    <!-- Global CSS Variables & Layout Rules -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Universal Actions Bridge -->
    <script src="assets/js/actions.js"></script>
    <script src="assets/js/print_engine.js"></script>
    <script src="assets/js/hardware_mapping.js"></script>
    <script src="../orders/assets/js/inventory_data.js"></script>

    <!-- Optional: A nice system font hook if desired later. Using system-ui fallbacks in CSS for now -->
</head>
<body class="safe-area-bottom">
    <?= UI::theme_toggle() ?>

<!-- THE CHECKBOX HACK -->
<input type="checkbox" id="nav-toggle" hidden>

<!-- MOBILE HEADER (Visible on phones) -->
<header class="mobile-top-bar">
    <label for="nav-toggle" class="menu-label" aria-label="Toggle Menu">
        <span>☰</span>
    </label>
    <div style="font-weight: 800; color: var(--accent-color); font-size: 1.1rem; letter-spacing: -1px;"><?= htmlspecialchars(strtoupper(Company::getName())) ?></div>
    <div style="width: 44px;"></div> <!-- Spacer for balance -->
</header>

<div class="app-container">

    <!-- GLOBAL PRINT SETTINGS MODAL -->
    <div id="printModal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); z-index:2000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px);">
        <div class="panel" style="width:100%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="flex-between" style="margin-bottom:20px;">
                <h2 style="margin:0; font-size:1.2rem;">🖨️ Print Configuration</h2>
                <button onclick="closePrintModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-secondary);">✕</button>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:25px;">
                <!-- Label A Preview -->
                <div id="prevLabelA" style="border:2px solid var(--accent-color); border-radius:10px; padding:15px; text-align:center; background:var(--bg-page);">
                    <div style="font-size:1.5rem; margin-bottom:5px;">🏷️</div>
                    <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase;">Page 1: Brand</div>
                    <p style="font-size:0.7rem; color:var(--text-secondary); margin-top:5px;">Logo & Model Only</p>
                </div>
                <!-- Label B Preview -->
                <div id="prevLabelB" style="border:2px solid var(--accent-color); border-radius:10px; padding:15px; text-align:center; background:var(--bg-page);">
                    <div style="font-size:1.5rem; margin-bottom:5px;">📜</div>
                    <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase;">Page 2: Specs</div>
                    <p style="font-size:0.7rem; color:var(--text-secondary); margin-top:5px;">Technical Details</p>
                </div>
            </div>

            <label for="printQty" style="display:block; margin-bottom:10px; font-weight:700; font-size:0.85rem;">Quantity of Sets</label>
            <div style="display:flex; gap:10px; align-items:center; margin-bottom:25px;">
                <input type="number" id="printQty" value="1" min="1" max="100" style="width:80px; text-align:center; font-size:1.2rem; font-weight:bold;">
                <p style="font-size:0.8rem; color:var(--text-secondary);">Copies of the document.</p>
            </div>

            <button id="confirmPrintBtn" class="btn btn-success" style="width:100%; height:60px; font-size:1.1rem; gap:10px;">
                <span>🚀 Send to Windows Printer</span>
            </button>

            <p style="margin-top:15px; font-size:0.7rem; text-align:center; color:var(--text-secondary);">
                This will open the file directly in your default Windows application.
            </p>
        </div>
    </div>

    <script>
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
    </script>

    <!--
       SIDEBAR NAVIGATION
       Persists across all views. Active state can be toggled via JS or PHP logic.
    -->
    <aside class="sidebar">
        <!-- Close button for mobile -->
        <label for="nav-toggle" class="mobile-only" style="position: absolute; top: 15px; right: 15px; font-size: 1.5rem; cursor: pointer; display: none;">✕</label>

        <h2 style="margin-top: 10px;"><?= htmlspecialchars(strtoupper(Company::getName())) ?></h2>

        <nav style="flex: 1; overflow-y: auto;">
            <ul class="nav-menu">
                <!-- PRIMARY NAV -->
                <li style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 12px; margin-bottom: 8px;">Workspace</li>
                <li><a href="index.php" class="nav-link" id="nav-dashboard">🏠 Dashboard</a></li>
                <li><a href="labels.php" class="nav-link" id="nav-labels">📦 Inventory Tracker</a></li>
                <li><a href="new_label.php" class="nav-link" id="nav-new-label">🏷️ Print Hardware Label</a></li>
                <li style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 12px; margin-top: 18px; margin-bottom: 8px;">Portal & Modules</li>
                <li><a href="../index.php" class="nav-link">🌐 Main Portal</a></li>
                <li><a href="../orders/index.php" class="nav-link">📊 Orders & WH</a></li>
                <li><a href="../tech/index.php" class="nav-link">🔧 Technician</a></li>
                <li><a href="../marketing/index.php" class="nav-link">📣 Marketing</a></li>
                <li style="color: var(--text-secondary); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 12px; margin-top: 18px; margin-bottom: 8px;">Account</li>
                <li style="padding-left: 12px; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 6px;">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></li>
                <li><a href="../orders/core/logout.php" class="nav-link" style="color: #ef4444;">🚪 Sign Out</a></li>
            </ul>
        </nav>
    </aside>

    <!--
       MAIN CONTENT CONTAINER
       This opens the flex container that individual pages will pour their HTML into.
       Closed by includes/footer.php
    -->
    <main class="main-content">
