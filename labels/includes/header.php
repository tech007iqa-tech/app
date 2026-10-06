<?php
// labels/includes/header.php
// Central Modular Header & Navigation for IQA Labels.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$is_standalone = defined('LABELS_STANDALONE') && LABELS_STANDALONE;
$company_name = Company::getName();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title><?= htmlspecialchars($company_name) ?> · <?= htmlspecialchars(LABELS_APP_NAME) ?></title>

    <!-- Immediate Theme Application (Zero FOUC) -->
    <script>
        (function() {
            const saved = localStorage.getItem('iqa_theme') || localStorage.getItem('iqa_labels_theme') || 
                (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', saved);
        })();

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('iqa_theme', next);
            localStorage.setItem('iqa_labels_theme', next);
            window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme: next } }));
        }
    </script>

    <!-- Modern Typography (Plus Jakarta Sans & Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <!-- Optional Parent Components (if present) -->
    <?php if (file_exists(__DIR__ . '/../../assets/css/components.css')): ?>
        <link rel="stylesheet" href="../assets/css/components.css">
    <?php endif; ?>

    <!-- Master Modular Design System -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">

    <!-- Standalone & Parent Data Catalogs -->
    <script src="assets/js/inventory_catalog.js?v=<?= filemtime(__DIR__ . '/../assets/js/inventory_catalog.js') ?>"></script>
    <?php if (file_exists(__DIR__ . '/../../orders/assets/js/inventory_data.js')): ?>
        <script src="../orders/assets/js/inventory_data.js"></script>
    <?php endif; ?>

    <!-- Modular Action Bridges & Print Engine -->
    <script src="assets/js/hardware_mapping.js"></script>
    <script src="assets/js/actions.js?v=<?= filemtime(__DIR__ . '/../assets/js/actions.js') ?>"></script>
    <script src="assets/js/print_engine.js?v=<?= filemtime(__DIR__ . '/../assets/js/print_engine.js') ?>"></script>

    <!-- Security CSRF Meta & Universal AJAX Client (AppSync) -->
    <meta name="csrf-token" content="<?= htmlspecialchars(Security::getToken()) ?>">
    <script src="../assets/js/app_sync.js?v=<?= file_exists(__DIR__ . '/../../assets/js/app_sync.js') ? filemtime(__DIR__ . '/../../assets/js/app_sync.js') : time() ?>"></script>
</head>
<body class="safe-area-bottom">

    <!-- THE MOBILE DRAWER TOGGLE CHECKBOX -->
    <input type="checkbox" id="nav-toggle" hidden>

    <!-- TOP HEADER BAR -->
    <header class="app-top-header">
        <div class="header-left">
            <label for="nav-toggle" class="menu-toggle-btn" aria-label="Toggle Navigation Menu">
                <span class="hamburger-icon">☰</span>
            </label>

            <a href="index.php" class="brand-link">
                <span class="brand-logo-icon">🏷️</span>
                <div class="brand-text-group">
                    <span class="brand-name"><?= htmlspecialchars($company_name) ?></span>
                    <span class="brand-module">Labels & Logistics</span>
                </div>
            </a>
        </div>

        <nav class="top-nav-links">
            <a href="index.php" class="nav-chip <?= $current_page === 'index.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="new_label.php" class="nav-chip <?= $current_page === 'new_label.php' ? 'active' : '' ?>">
                <span class="nav-icon">➕</span>
                <span class="nav-label">New Label</span>
            </a>
            <a href="labels.php" class="nav-chip <?= $current_page === 'labels.php' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Inventory</span>
            </a>
        </nav>

        <div class="header-right">
            <div class="workstation-status-pill" title="Hardware Workstation Ready">
                <span class="status-pulse-dot"></span>
                <span class="status-pill-text">Station Ready</span>
            </div>

            <!-- Quick Add Shortcut -->
            <a href="new_label.php" class="btn btn-primary btn-header-quick" title="Create New Hardware Label">
                <span>➕</span>
                <span class="btn-text-desktop">Create Label</span>
            </a>

            <!-- Dark / Light Theme Toggle -->
            <button type="button" class="theme-toggle-btn theme-btn" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle Theme" title="Toggle Light/Dark Theme">
                <span class="theme-icon light-icon theme-icon-sun">☀️</span>
                <span class="theme-icon dark-icon theme-icon-moon">🌙</span>
            </button>

            <!-- User Avatar & Profile info -->
            <div class="user-badge" title="Active User">
                <span class="user-avatar">👤</span>
                <span class="user-name-text"><?= htmlspecialchars($_SESSION['username'] ?? 'Operator') ?></span>
            </div>
        </div>
    </header>

    <!-- MOBILE / SIDEBAR DRAWER OVERLAY -->
    <label for="nav-toggle" class="drawer-backdrop"></label>

    <div class="app-layout">
        <!-- PERSISTENT SIDEBAR NAVIGATION (Desktop & Mobile) -->
        <aside class="sidebar-drawer">
            <div class="drawer-header">
                <div class="brand-link">
                    <span class="brand-logo-icon">🏷️</span>
                    <div>
                        <div class="brand-name"><?= htmlspecialchars($company_name) ?></div>
                        <div class="brand-module">v<?= htmlspecialchars(LABELS_VERSION) ?></div>
                    </div>
                </div>
                <label for="nav-toggle" class="drawer-close-btn" title="Close Drawer">✕</label>
            </div>

            <nav class="drawer-nav">
                <div class="nav-category-header">CORE WORKFLOW</div>
                <a href="index.php" class="drawer-item <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <span class="drawer-icon">🏠</span>
                    <span class="drawer-text">Dashboard & Locate</span>
                </a>
                <a href="new_label.php" class="drawer-item <?= $current_page === 'new_label.php' ? 'active' : '' ?>">
                    <span class="drawer-icon">🏷️</span>
                    <span class="drawer-text">Add Item & Print Label</span>
                    <span class="badge badge-accent">Intake</span>
                </a>
                <a href="labels.php" class="drawer-item <?= $current_page === 'labels.php' ? 'active' : '' ?>">
                    <span class="drawer-icon">📦</span>
                    <span class="drawer-text">Warehouse Inventory</span>
                </a>

                <?php if (file_exists(__DIR__ . '/../../index.php')): ?>
                    <div class="nav-category-header">WAREHOUSE PORTAL</div>
                    <a href="../index.php" class="drawer-item">
                        <span class="drawer-icon">🌐</span>
                        <span class="drawer-text">Main Portal</span>
                    </a>
                    <?php if (file_exists(__DIR__ . '/../../orders/index.php')): ?>
                        <a href="../orders/index.php" class="drawer-item">
                            <span class="drawer-icon">📊</span>
                            <span class="drawer-text">Orders & WH</span>
                        </a>
                    <?php endif; ?>
                    <?php if (file_exists(__DIR__ . '/../../tech/index.php')): ?>
                        <a href="../tech/index.php" class="drawer-item">
                            <span class="drawer-icon">🔧</span>
                            <span class="drawer-text">Technician Bench</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="nav-category-header">HARDWARE STATION</div>
                <div class="station-info-card">
                    <div class="station-meta-row">
                        <span>Label Dimensions:</span>
                        <strong>2.0" × 1.0"</strong>
                    </div>
                    <div class="station-meta-row">
                        <span>Print Method:</span>
                        <strong>Thermal Direct / ODT</strong>
                    </div>
                    <div class="station-meta-row">
                        <span>DB Status:</span>
                        <strong style="color:var(--color-success);">Connected (WAL)</strong>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- MAIN VIEW CONTAINER -->
        <main class="main-content-area" id="mainAppContent">

        <!-- GLOBAL TOAST NOTIFICATION CONTAINER -->
        <div id="toastContainer" class="toast-container" aria-live="polite"></div>

        <!-- GLOBAL MODAL: QUICK HARDWARE VIEW -->
        <div id="quickViewModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeQuickViewModal()">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="qvTitle">Hardware Profile</h3>
                    <button type="button" class="modal-close-btn" onclick="closeQuickViewModal()">✕</button>
                </div>
                <div class="modal-body" id="qvContent">
                    <div class="modal-loading-spinner">⏳ Loading details...</div>
                </div>
                <div class="modal-footer" id="qvFooter">
                    <button type="button" class="btn btn-secondary" onclick="closeQuickViewModal()">Close</button>
                </div>
            </div>
        </div>

        <!-- GLOBAL MODAL: PRINT CONFIGURATION -->
        <div id="printModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closePrintModal()">
            <div class="modal-dialog modal-print-dialog">
                <div class="modal-header">
                    <div>
                        <h3 style="margin:0;">🖨️ Print Hardware Label</h3>
                        <p style="margin:2px 0 0 0; font-size:0.85rem; color:var(--text-secondary);">2" × 1" Thermal Format</p>
                    </div>
                    <button type="button" class="modal-close-btn" onclick="closePrintModal()">✕</button>
                </div>

                <div class="modal-body">
                    <div class="print-preview-choice-grid">
                        <!-- Label A Selector -->
                        <div id="prevLabelA" class="print-choice-card active" onclick="toggleLabelPage('a')">
                            <div class="choice-icon">🏷️</div>
                            <div class="choice-title">Sticker A: Brand</div>
                            <p class="choice-desc">Model, Series, CPU & Barcode</p>
                            <span class="choice-badge">Page 1</span>
                        </div>

                        <!-- Label B Selector -->
                        <div id="prevLabelB" class="print-choice-card active" onclick="toggleLabelPage('b')">
                            <div class="choice-icon">📜</div>
                            <div class="choice-title">Sticker B: Specs</div>
                            <p class="choice-desc">CPU, RAM, SSD, Battery, OS</p>
                            <span class="choice-badge">Page 2</span>
                        </div>
                    </div>

                    <div class="print-options-bar">
                        <div class="qty-control-group">
                            <label for="printQty">Copies:</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn" onclick="adjustPrintQty(-1)">−</button>
                                <input type="number" id="printQty" value="1" min="1" max="100" class="qty-input-field">
                                <button type="button" class="qty-btn" onclick="adjustPrintQty(1)">+</button>
                            </div>
                        </div>

                        <div class="format-notes">
                            ⚡ <em>Optimized for standard thermal rolls (Zebra, Rollo, Brother, Dymo).</em>
                        </div>
                    </div>
                </div>

                <div class="modal-footer print-modal-actions">
                    <button type="button" id="btnBrowserDirectPrint" class="btn btn-success btn-large" style="flex:1;">
                        <span>🖨️ Direct Web Print</span>
                    </button>
                    <button type="button" id="confirmPrintBtn" class="btn btn-secondary btn-large" title="Open LibreOffice / ODT on host Windows PC">
                        <span>📄 Windows ODT</span>
                    </button>
                </div>
            </div>
        </div>
