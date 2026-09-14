<?php
require_once 'core/database.php';
require_once 'core/auth.php';

// Prepare basic dashboard data
$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);

// Calculate Daily Impact Summary
$good_count = 0;
$bad_count = 0;
try {
    $conn = Database::tech();
    if ($_SESSION['role'] !== 'Admin') {
        $stmt = $conn->prepare("SELECT status, SUM(qty) as total FROM logs WHERE date(created_at, 'localtime') = date('now', 'localtime') AND tech_id = ? GROUP BY status");
        $stmt->execute([$_SESSION['username']]);
    } else {
        $stmt = $conn->prepare("SELECT status, SUM(qty) as total FROM logs WHERE date(created_at, 'localtime') = date('now', 'localtime') GROUP BY status");
        $stmt->execute();
    }
    $daily_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $good_count = (int)($daily_stats['Good'] ?? 0);
    $bad_count = (int)($daily_stats['Bad'] ?? 0);

    // Fetch Low Stock Alerts
    $stmt = $conn->prepare("SELECT part_name, quantity FROM parts_inventory WHERE quantity <= low_stock_threshold ORDER BY quantity ASC");
    $stmt->execute();
    $low_stock_parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
$total_tested = $good_count + $bad_count;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard | System</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../orders/assets/styles/style.css">
    <link rel="stylesheet" href="assets/styles/dashboard.css">
    <link rel="icon" type="image/png" href="../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme">

    <div class="breadcrumb-container" role="banner" style="max-width: 1200px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="index.php" class="crumb active">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
        </nav>
        <div>
            <a href="pages/settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container">

        <div class="tech-dashboard-header">
            <div>
                <h1><span>🛠️</span> Technician Control Center</h1>
                <p>Hardware testing, logs, and inventory management.</p>
            </div>

            <!-- Daily Impact Summary Widget -->
            <div class="stats-widget">
                <div class="stat-item">
                    <span class="stat-value"><?= $total_tested ?></span>
                    <span class="stat-label">Tested Today</span>
                </div>
                <div class="divider"></div>
                <div class="stat-details">
                    <div class="stat-row">
                        <span class="indicator indicator-good"></span>
                        <?= $good_count ?> Good
                    </div>
                    <div class="stat-row">
                        <span class="indicator indicator-bad"></span>
                        <?= $bad_count ?> Bad
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($low_stock_parts)): ?>
        <div class="alert-box">
            <span class="alert-icon">⚠️</span>
            <div>
                <h3 class="alert-title">Action Required: Low Stock Detected</h3>
                <p class="alert-desc">
                    The following items are running critically low and need to be reordered:
                    <strong>
                        <?php
                            $alerts = array_map(function($p) { return $p['part_name'] . ' (' . $p['quantity'] . ' left)'; }, $low_stock_parts);
                            echo implode(', ', $alerts);
                        ?>
                    </strong>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Global Search Widget -->
        <div class="search-container">
            <input type="text" id="globalSearch" placeholder="🔍 Quick Search by Serial, Make, or Model..." class="search-input">
            <div id="searchResults" class="search-results">
            </div>
        </div>

        <div class="module-grid">
            <?php if ($_SESSION['role'] !== 'Admin'): ?>
            <a href="pages/logs.php" class="module-card">
                <div class="icon-box">📋</div>
                <h2>Hardware Logs</h2>
                <p>Record hardware tests, track Good/Bad units, and view your daily impact summary.</p>
                <div class="badge badge-active">Module Active</div>
            </a>
            <?php else: ?>
            <a href="pages/admin_audit.php" class="module-card" style="border-color: #4f46e5;">
                <div class="icon-box" style="background: #e0e7ff; color: #4f46e5;">👥</div>
                <h2>Admin Audit Logs</h2>
                <p>Monitor technician log history, search and filter entries by status, dates, and technician name.</p>
                <div class="badge badge-info" style="background: #e0e7ff; color: #3730a3;">Admin Only</div>
            </a>
            <?php endif; ?>

            <!-- Inventory Module -->
            <a href="pages/inventory.php" class="module-card">
                <div class="icon-box">📦</div>
                <h2>Parts Inventory</h2>
                <p>Manage RAM, SSDs, tools, and view critical low-stock alerts before they run out.</p>
                <div class="badge badge-active">Module Active</div>
            </a>

            <a href="pages/labels.php" class="module-card">
                <div class="icon-box">🏷️</div>
                <h2>Label Generation</h2>
                <p>Print professional .odt thermal labels directly from hardware test logs.</p>
                <div class="badge badge-info">Module Active</div>
            </a>
        </div>

    </main>

    <script>
        // Global Search Logic
        document.getElementById('globalSearch').addEventListener('input', async (e) => {
            const q = e.target.value.trim();
            const resultsDiv = document.getElementById('searchResults');

            if (q.length < 2) {
                resultsDiv.style.display = 'none';
                return;
            }

            try {
                const response = await fetch('api/search_logs.php?q=' + encodeURIComponent(q));
                const data = await response.json();

                if (data.success && data.data.length > 0) {
                    let html = '<ul style="list-style: none; margin: 0; padding: 0;">';
                    data.data.forEach(log => {
                        const badge = log.status === 'Good' ? '<span style="color: #166534; background: #dcfce7; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">Good</span>' : '<span style="color: #991b1b; background: #fee2e2; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">Bad</span>';
                        html += `<li style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color:#1e293b;">${log.make} ${log.model}</strong> <small style="color: #64748b; font-weight:700;">(Series: ${log.series || 'N/A'})</small><br>
                                        <small style="color: #94a3b8;">Logged by ${log.tech_id} on ${new Date(log.created_at).toLocaleDateString()}</small>
                                    </div>
                                    <div>${badge}</div>
                                 </li>`;
                    });
                    html += '</ul>';
                    resultsDiv.innerHTML = html;
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div style="padding: 15px; color: #64748b; text-align: center;">No results found.</div>';
                    resultsDiv.style.display = 'block';
                }
            } catch(err) {
                console.error(err);
            }
        });

        // Hide results on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#globalSearch') && !e.target.closest('#searchResults')) {
                document.getElementById('searchResults').style.display = 'none';
            }
        });
    </script>
</body>
</html>
