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
    <title><?= htmlspecialchars(Company::getSystemName()) ?> | Operations Portal</title>
    <meta name="description" content="Unified enterprise operations hub for used computer refurbishing, hardware intake, warehouse logistics, barcode labeling, and storefront clearance.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Global Component Styles -->
    <link rel="stylesheet" href="assets/css/components.css?v=<?= filemtime('assets/css/components.css') ?>">

    <!-- Primary Stylesheet -->
    <link rel="stylesheet" href="assets/css/portal.css?v=<?= filemtime('assets/css/portal.css') ?>">
    <link rel="icon" type="image/png" href="./orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>

<body>

    <!-- Ambient Glowing Blobs -->
    <div class="background-blob-primary" aria-hidden="true"></div>
    <div class="background-blob-secondary" aria-hidden="true"></div>

    <!-- Top Navigation Bar -->
    <header class="portal-topbar">
        <a href="index.php" class="brand-wrapper" title="<?= htmlspecialchars(Company::getSystemName()) ?>">
            <div class="brand-logo-badge">
                <svg viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div class="brand-text">
                <div class="brand-title"><?= htmlspecialchars(Company::getName()) ?> <span>Systems</span></div>
                <div class="brand-tag">Operations Portal</div>
            </div>
        </a>

        <!-- User Authentication Status Bar -->
        <nav class="auth-controls" aria-label="User Account">
            <?php if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true): ?>
                <div class="user-status-pill">
                    <span>👤</span>
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'User') ?></span>
                </div>
                <a href="orders/core/logout.php" class="btn-signout" title="End active session">
                    <span>🚪</span> Sign Out
                </a>
            <?php else: ?>
                <a href="orders/core/login.php" class="btn-signin" title="Access authorized modules">
                    <span>🔒</span> Sign In
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="portal-main">
        <!-- Hero Header -->
        <section class="portal-header">
            <div class="system-badge">
                <span class="badge-dot"></span>
                <span>Unified Enterprise Operations Ecosystem</span>
            </div>
            <h1><?= htmlspecialchars(Company::getSystemName()) ?></h1>
            <p class="tagline"><?= htmlspecialchars(Company::getTagline()) ?></p>
            <p class="description">
                Centralized command hub tailored for secondary-market computer refurbishing, hardware intake, physical warehouse logistics, multi-user CRM fulfillment, and direct clearance commerce.
            </p>
        </section>

        <!-- 5-Card Module Grid -->
        <section class="module-grid" aria-label="System Modules">

            <!-- 1. TECHNICIAN DASHBOARD -->
            <a href="tech/index.php" class="module-card theme-tech" id="card-tech">
                <div class="corner-glow"></div>
                <div class="card-header-bar">
                    <div class="icon-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                        </svg>
                    </div>
                    <span class="card-status-badge badge-tech">
                        <span class="status-dot"></span> Active
                    </span>
                </div>
                <h2>Technician Center</h2>
                <p class="card-summary">Hardware diagnostics, computer testing logs, spec auditing, and technical parts inventory management.</p>
                <div class="feature-tags">
                    <span class="feature-pill">Hardware Diagnostics</span>
                    <span class="feature-pill">Parts Inventory</span>
                    <span class="feature-pill">Testing Yields</span>
                </div>
                <div class="card-action-row">
                    <span class="module-launch-link">Launch Module</span>
                    <span class="launch-arrow">→</span>
                </div>
            </a>

            <!-- 2. ORDER MANAGER & WAREHOUSE -->
            <a href="orders/index.php" class="module-card theme-orders" id="card-orders">
                <div class="corner-glow"></div>
                <div class="card-header-bar">
                    <div class="icon-box">
                        <svg viewBox="0 0 24 24">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <span class="card-status-badge badge-orders">
                        <span class="status-dot"></span> Active
                    </span>
                </div>
                <h2>Order Manager</h2>
                <p class="card-summary">Comprehensive CRM, batch fulfillment, warehouse location logistics, and real-time customer tracking.</p>
                <div class="feature-tags">
                    <span class="feature-pill">Batch Fulfillment</span>
                    <span class="feature-pill">Zone Logistics</span>
                    <span class="feature-pill">Real-time CRM</span>
                </div>
                <div class="card-action-row">
                    <span class="module-launch-link">Launch Module</span>
                    <span class="launch-arrow">→</span>
                </div>
            </a>

            <!-- 3. HARDWARE STOREFRONT -->
            <a href="store/index.php" class="module-card theme-store" id="card-store">
                <div class="corner-glow"></div>
                <div class="card-header-bar">
                    <div class="icon-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <span class="card-status-badge badge-store">
                        <span class="status-dot"></span> Storefront Online
                    </span>
                </div>
                <h2>The Store</h2>
                <p class="card-summary">Warehouse clearance catalog, certified refurbished PCs, shopping cart, and staff tender pricing controls.</p>
                <div class="feature-tags">
                    <span class="feature-pill">Direct Clearance</span>
                    <span class="feature-pill">Tender Mode</span>
                    <span class="feature-pill">Cart &amp; Checkout</span>
                </div>
                <div class="card-action-row">
                    <span class="module-launch-link">Enter Storefront</span>
                    <span class="launch-arrow">→</span>
                </div>
            </a>

            <!-- 4. LABELS & INTAKE -->
            <a href="labels/index.php" class="module-card theme-labels" id="card-labels">
                <div class="corner-glow"></div>
                <div class="card-header-bar">
                    <div class="icon-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                    </div>
                    <span class="card-status-badge badge-labels">
                        <span class="status-dot"></span> Active
                    </span>
                </div>
                <h2>Labels &amp; Intake</h2>
                <p class="card-summary">Hardware inventory tracking, thermal printing, barcode tag generation, and rapid device locate.</p>
                <div class="feature-tags">
                    <span class="feature-pill">Thermal 4x6" Labels</span>
                    <span class="feature-pill">Rapid Intake</span>
                    <span class="feature-pill">Quick Locate</span>
                </div>
                <div class="card-action-row">
                    <span class="module-launch-link">Launch Module</span>
                    <span class="launch-arrow">→</span>
                </div>
            </a>

            <!-- 5. MARKETING HUB -->
            <a href="marketing/index.php" class="module-card theme-marketing" id="card-marketing">
                <div class="corner-glow"></div>
                <div class="card-header-bar">
                    <div class="icon-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path>
                            <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path>
                            <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path>
                            <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path>
                        </svg>
                    </div>
                    <span class="card-status-badge badge-marketing">
                        <span class="status-dot"></span> Active
                    </span>
                </div>
                <h2>Marketing Hub</h2>
                <p class="card-summary">Automated B2B lead generation, campaign tracking, customer outreach pipelines, and growth analytics.</p>
                <div class="feature-tags">
                    <span class="feature-pill">B2B Outreach</span>
                    <span class="feature-pill">Campaign Tracking</span>
                    <span class="feature-pill">Growth Analytics</span>
                </div>
                <div class="card-action-row">
                    <span class="module-launch-link">Launch Module</span>
                    <span class="launch-arrow">→</span>
                </div>
            </a>

        </section>

        <!-- Ecosystem Architecture Banner -->
        <aside class="ecosystem-banner" aria-label="Ecosystem Status">
            <div class="eco-left">
                <div class="eco-icon">⚡</div>
                <div class="eco-text">
                    <h3>Synchronized Multi-User Infrastructure</h3>
                    <p>Technicians, Operators, Front Desk &amp; Administrators operate on a live SQLite WAL engine with smart real-time diffing.</p>
                </div>
            </div>
            <div class="eco-metrics">
                <div class="metric-item">
                    <div class="metric-val">5</div>
                    <div class="metric-label">Active Modules</div>
                </div>
                <div class="metric-item">
                    <div class="metric-val">&lt; 3ms</div>
                    <div class="metric-label">Sync Latency</div>
                </div>
                <div class="metric-item">
                    <div class="metric-val">100%</div>
                    <div class="metric-label">Operational</div>
                </div>
            </div>
        </aside>
    </main>

    <footer class="footer-note" role="contentinfo">
        <a href="<?= htmlspecialchars(Company::getUrl()) ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars(Company::getName()) ?>
        </a>
        &nbsp;&bull;&nbsp; Inventory System &copy; <?php echo date('Y'); ?> | Powered by <?= htmlspecialchars(Company::getSystemName()) ?>
        <?php if (!Company::isSetupComplete() || (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && ($_SESSION['role'] ?? '') === 'Admin')): ?>
            &nbsp;&bull;&nbsp; <a href="setup/index.php?reconfigure=1" style="color: #38bdf8; text-decoration: underline; font-size: 0.8rem;">⚙️ Reconfigure System</a>
        <?php endif; ?>
    </footer>

    <!-- Global Notifications Engine -->
    <script src="assets/js/notifications.js?v=<?= filemtime('assets/js/notifications.js') ?>"></script>
</body>

</html>