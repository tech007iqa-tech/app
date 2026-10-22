<?php
require_once 'core/database.php';
require_once 'core/auth.php';

date_default_timezone_set('America/Los_Angeles');

// Prepare basic dashboard data
$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
$is_admin = (
    strtolower($_SESSION['role'] ?? '') === 'admin' || 
    strtolower($_SESSION['username'] ?? '') === 'admin' || 
    strpos(strtolower($_SESSION['role'] ?? ''), 'admin') !== false
);

// Calculate Daily Impact Summary
$good_count = 0;
$bad_count = 0;
$today = date('Y-m-d');
try {
    $conn = Database::tech();
    if (!$is_admin) {
        $stmt = $conn->prepare("SELECT status, SUM(qty) as total FROM logs WHERE date(created_at, 'localtime') = ? AND tech_id = ? GROUP BY status");
        $stmt->execute([$today, $_SESSION['username']]);
    } else {
        $stmt = $conn->prepare("SELECT status, SUM(qty) as total FROM logs WHERE date(created_at, 'localtime') = ? GROUP BY status");
        $stmt->execute([$today]);
    }
    $daily_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $good_count = (int)($daily_stats['Good'] ?? 0);
    $bad_count = (int)($daily_stats['Bad'] ?? 0);

    $total_tested = $good_count + $bad_count;

    // Fetch Today's Per-Technician Activity Breakdown
    $stmt_team = $conn->prepare("
        SELECT 
            tech_id,
            COUNT(*) as log_count,
            SUM(qty) as total_units,
            SUM(CASE WHEN status = 'Good' THEN qty ELSE 0 END) as good_units,
            SUM(CASE WHEN status = 'Bad' THEN qty ELSE 0 END) as bad_units,
            MAX(created_at) as last_activity
        FROM logs
        WHERE date(created_at, 'localtime') = ?
        GROUP BY tech_id
        ORDER BY total_units DESC
    ");
    $stmt_team->execute([$today]);
    $team_stats = $stmt_team->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $team_stats = [];
}
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

    <div class="breadcrumb-container" role="banner">
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

        <!-- Today's Technician Daily Productivity Tracking Section -->
        <div class="form-card" style="margin-bottom: 25px; padding: 20px 24px; background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.4rem;">👥</span>
                    <div>
                        <h2 style="font-size: 1.15rem; font-weight: 800; color: #1e293b; margin: 0;">Today's Technician Activity</h2>
                        <p style="font-size: 0.85rem; color: #64748b; margin: 2px 0 0 0;">Real-time output and hardware test logs recorded throughout today.</p>
                    </div>
                </div>
                <div>
                    <a href="pages/logs.php" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; color: white; font-weight: 700;">
                        <span>➕ Rapid Intake</span>
                    </a>
                </div>
            </div>

            <?php if (!empty($team_stats)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px;">
                    <?php foreach ($team_stats as $ts): 
                        $is_self = ($ts['tech_id'] === $_SESSION['username']);
                        $last_time = 'N/A';
                        if (!empty($ts['last_activity'])) {
                            try {
                                $dt_act = new DateTime($ts['last_activity'], new DateTimeZone('UTC'));
                                $dt_act->setTimezone(new DateTimeZone('America/Los_Angeles'));
                                $last_time = $dt_act->format('g:i A');
                            } catch (Exception $e) {
                                $last_time = 'N/A';
                            }
                        }
                    ?>
                        <div style="background: #ffffff; border: 1px solid <?= $is_self ? '#93c5fd' : '#e2e8f0' ?>; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 32px; height: 32px; border-radius: 8px; background: <?= $is_self ? '#dbeafe' : '#f1f5f9' ?>; color: <?= $is_self ? '#1d4ed8' : '#475569' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                                            <?= strtoupper(substr($ts['tech_id'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong style="color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($ts['tech_id']) ?></strong>
                                            <?php if ($is_self): ?>
                                                <span style="font-size: 0.68rem; background: #dbeafe; color: #1e40af; font-weight: 800; padding: 1px 5px; border-radius: 4px; margin-left: 4px;">YOU</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span style="font-size: 1.25rem; font-weight: 900; color: #0f172a;"><?= (int)$ts['total_units'] ?> <small style="font-size: 0.75rem; font-weight: 700; color: #64748b;">units</small></span>
                                </div>

                                <div style="display: flex; gap: 6px; margin-bottom: 10px;">
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 6px;">✅ <?= (int)$ts['good_units'] ?> Good</span>
                                    <span style="background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 6px;">❌ <?= (int)$ts['bad_units'] ?> Bad</span>
                                </div>
                            </div>

                            <div style="border-top: 1px solid #f1f5f9; padding-top: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem;">
                                <span style="color: #64748b;">Last: <strong><?= $last_time ?></strong></span>
                                <a href="pages/logs.php?tech_id=<?= urlencode($ts['tech_id']) ?>" style="color: #2563eb; font-weight: 800; text-decoration: none;">View Logs &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding: 24px; text-align: center; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b; font-weight: 600;">
                    No hardware logs recorded yet today. Click <strong>Rapid Intake</strong> to start testing.
                </div>
            <?php endif; ?>
        </div>

        <div class="module-grid">
            <!-- Hardware Logs (Always available so Technicians and Admins can log/add items) -->
            <a href="pages/logs.php" class="module-card">
                <div class="icon-box">📋</div>
                <h2>Hardware Logs</h2>
                <p>Record hardware tests, add new unit logs, track Good/Bad units, and print thermal labels.</p>
                <div class="badge badge-active">Module Active</div>
            </a>

            <?php if ($_SESSION['role'] === 'Admin'): ?>
            <!-- Admin Audit Logs (Exclusive for Admin oversight) -->
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
                        let logDateStr = '';
                        if (log.created_at) {
                            let str = String(log.created_at).trim();
                            if (!str.includes('Z') && !/[+-]\d{2}:?\d{2}$/.test(str)) str = str.replace(' ', 'T') + 'Z';
                            const dt = new Date(str);
                            logDateStr = !isNaN(dt.getTime()) ? dt.toLocaleDateString('en-US', { timeZone: 'America/Los_Angeles' }) : log.created_at;
                        }
                        html += `<li style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color:#1e293b;">${log.make} ${log.model}</strong> <small style="color: #64748b; font-weight:700;">(Series: ${log.series || 'N/A'})</small><br>
                                        <small style="color: #94a3b8;">Logged by ${log.tech_id} on ${logDateStr}</small>
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
