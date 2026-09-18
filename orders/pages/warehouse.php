<?php
/**
 * Warehouse Control Center (Main Orchestrator)
 * Modularized view coordinating stock management, working zones, and density tracking.
 */

include 'core/warehouse_db.php';
include 'core/auth.php'; // Session is already started and checked

$current_user = $_SESSION['username'];
$selected_sector = $_GET['sector'] ?? 'Laptops';
$selected_loc = $_GET['loc'] ?? null;
$active_zone_name = $_GET['zone'] ?? null;

// Determine modes
$is_zone_view = (!empty($active_zone_name) && empty($selected_loc));
$is_spreadsheet = ($selected_loc && $selected_loc !== 'GLOBAL') || $is_zone_view;

// Fetch Location Photos if active in single shelf spreadsheet mode
$location_photos = [];
if ($selected_loc && $selected_loc !== 'GLOBAL') {
    try {
        $stmt_lp = $conn_wh->prepare("SELECT * FROM location_photos WHERE location_code = ? AND sector = ? ORDER BY category ASC, created_at DESC");
        $stmt_lp->execute([$selected_loc, $selected_sector]);
        $location_photos = $stmt_lp->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// 1. Process Actions (CRUD, Zone operations, Photo Uploads)
include __DIR__ . '/partials/warehouse/actions.php';

// 2. Fetch Reference Datasets
$stmt_locs = $conn_wh->query("
    SELECT l.*,
        (SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code) as item_count,
        ls.color as status_color
    FROM locations l
    LEFT JOIN (
        SELECT name, color FROM location_statuses GROUP BY name
    ) ls ON l.status = ls.name
    ORDER BY l.location_code ASC
");
$existing_locs = $stmt_locs->fetchAll(PDO::FETCH_ASSOC);

$all_statuses = $conn_wh->query("
    SELECT MIN(id) AS id, name, color, is_default 
    FROM location_statuses 
    WHERE is_default = 1
    GROUP BY name 
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$sectors = $conn_wh->query("SELECT * FROM sectors")->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Inventory Items
$items = [];
if ($selected_loc) {
    if ($selected_loc === 'GLOBAL') {
        if ($active_zone_name) {
            if ($selected_sector === 'Master') {
                $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE location_code IN (SELECT location_code FROM locations WHERE working_zone_name = ?) ORDER BY sector ASC, id DESC");
                $stmt_i->execute([$active_zone_name]);
            } else {
                $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code IN (SELECT location_code FROM locations WHERE working_zone_name = ?) ORDER BY id DESC");
                $stmt_i->execute([$selected_sector, $active_zone_name]);
            }
        } else {
            if ($selected_sector === 'Master') {
                $stmt_i = $conn_wh->query("SELECT * FROM inventory ORDER BY sector ASC, id DESC");
            } else {
                $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? ORDER BY id DESC");
                $stmt_i->execute([$selected_sector]);
            }
        }
        $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Ensure location entry exists
        $stmt_check = $conn_wh->prepare("INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')");
        $stmt_check->execute([$selected_loc]);

        if ($selected_sector === 'Master') {
            $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE location_code = ? ORDER BY id DESC");
            $stmt_i->execute([$selected_loc]);
        } else {
            $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code = ? ORDER BY id DESC");
            $stmt_i->execute([$selected_sector, $selected_loc]);
        }
        $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($active_zone_name) {
    // Inside a Zone view without single shelf filter: load all items in this working zone
    if ($selected_sector === 'Master') {
        $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE location_code IN (SELECT location_code FROM locations WHERE working_zone_name = ?) ORDER BY sector ASC, id DESC");
        $stmt_i->execute([$active_zone_name]);
    } else {
        $stmt_i = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND location_code IN (SELECT location_code FROM locations WHERE working_zone_name = ?) ORDER BY id DESC");
        $stmt_i->execute([$selected_sector, $active_zone_name]);
    }
    $items = $stmt_i->fetchAll(PDO::FETCH_ASSOC);
}

$highlight_id = $_GET['last_id'] ?? null;

// 4. Livewire-style AJAX Responder
include __DIR__ . '/partials/warehouse/ajax_view.php';
?>

<script id="warehouse-state" type="application/json">
    <?= json_encode(['activeSector' => $selected_sector], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<div class="warehouse-container">
    <header class="warehouse-header">
        <div class="warehouse-header-main">
            <div class="warehouse-title-block">
                <h1><a href="index.php?view=import_warehouse">Warehouse Control Center</a></h1>
                <p class="subtitle">Managing stock and locations across all inventory sectors.</p>
            </div>
            <div class="header-right-side">
                <?php
                $parent_zone = null;
                $active_l_color = '#94a3b8';
                $active_l_status = 'Idle';
                if ($selected_loc) {
                    $active_l_stmt = $conn_wh->prepare("SELECT l.*, ls.color FROM locations l LEFT JOIN (SELECT name, color FROM location_statuses GROUP BY name) ls ON l.status = ls.name WHERE l.location_code = ?");
                    $active_l_stmt->execute([$selected_loc]);
                    $active_l = $active_l_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($active_l) {
                        $active_l_status = $active_l['status'] ?? 'Idle';
                        $active_l_color = $active_l['color'] ?? '#94a3b8';
                        $parent_zone = $active_l['working_zone_name'] ?? 'General';
                    }
                }
                $effective_zone = $active_zone_name ?? $parent_zone;
                ?>
                <div class="warehouse-breadcrumbs">
                    <a href="index.php?view=warehouse">Warehouse</a>
                    <?php if ($selected_loc): ?>
                        <span class="separator">/</span>
                        <?php if ($effective_zone): ?>
                            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&zone=<?= urlencode($effective_zone) ?>"><?= htmlspecialchars($effective_zone) ?></a>
                            <span class="separator">/</span>
                        <?php endif; ?>
                        <span class="current-crumb"><?= htmlspecialchars($selected_loc) ?></span>
                    <?php elseif ($effective_zone): ?>
                        <span class="separator">/</span>
                        <span class="current-crumb"><?= htmlspecialchars($effective_zone) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($selected_loc): ?>
                    <div class="active-loc-display" style="display:flex; align-items:center; gap:15px;">
                        <div style="text-align:right;">
                            <div class="loc-label">Active Location</div>
                            <div
                                style="font-size:0.65rem; font-weight:900; text-transform:uppercase; color:<?= $active_l_color ?>; letter-spacing:0.05em;">
                                <?= htmlspecialchars($active_l_status) ?>
                            </div>
                        </div>
                        <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?><?= $effective_zone ? '&zone=' . urlencode($effective_zone) : '' ?>" class="loc-active-badge">
                            <span class="loc-pin">📍</span>
                            <span class="loc-text"><?= htmlspecialchars($selected_loc) ?></span>
                            <span class="loc-change">Change</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Bulk Action Bar -->
    <div id="bulkActionBar" class="bulk-action-bar" style="display:none;">
        <div class="bulk-info">
            <span id="selectedCount">0</span> items selected
        </div>
        <div class="bulk-actions">
            <input type="text" id="bulkLocation" placeholder="Move to Zone..." list="gate-loc-datalist"
                style="width: 150px; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            <datalist id="gate-loc-datalist">
                <?php foreach ($existing_locs as $l): ?>
                    <option value="<?= htmlspecialchars($l['location_code']) ?>">
                <?php endforeach; ?>
            </datalist>
            <div style="position:relative; display:flex; align-items:center;">
                <span style="position:absolute; left:10px; font-weight:800; color:var(--text-secondary);">$</span>
                <input type="number" id="bulkPrice" placeholder="Price"
                    style="width: 100px; padding: 10px 10px 10px 25px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
            <button id="applyBulkBtn" class="btn btn-success"
                style="background: white; color: var(--text-main); font-weight: 800; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer;">
                Apply Batch Changes
            </button>
            <button id="cancelBulkBtn"
                style="background: none; border: 1px solid rgba(255,255,255,0.3); color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; font-weight: 700;">
                Cancel
            </button>
        </div>
    </div>
    <?= UI::csrf_field() ?>

    <?php if ($is_zone_view): ?>
        <!-- Zone Gate Shelves Navigation & Dashboard at Top -->
        <?php include __DIR__ . '/partials/warehouse/gate_view.php'; ?>
    </div> <!-- Close top .warehouse-container -->
    </div> <!-- Close top .container -->

    <!-- Standalone Data Container for Zone Inventory Table -->
    <div class="container order-view warehouse-data-container" style="margin-top: 30px;">
        <div class="warehouse-container">
            <!-- Sector Navigation Tabs for Zone -->
            <div class="sector-nav" style="margin-top: 5px;">
                <?php foreach ($sectors as $s):
                    $sector_url = "index.php?view=warehouse&sector=" . urlencode($s['name']) . "&zone=" . urlencode($active_zone_name);
                ?>
                    <a href="<?= $sector_url ?>"
                        class="sector-card <?= $selected_sector === $s['name'] ? 'active' : '' ?>"
                        data-sector="<?= htmlspecialchars($s['name']) ?>">
                        <span class="sector-icon"><?= $s['icon'] ?></span>
                        <span class="sector-name"><?= htmlspecialchars($s['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Zone Spreadsheet Inventory Table -->
            <div class="warehouse-layout spreadsheet-mode" style="margin-top: 20px;">
                <?php include __DIR__ . '/partials/warehouse/spreadsheet_view.php'; ?>
            </div>
        </div>
    <?php elseif (!$selected_loc): ?>
        <!-- Root Gate View (Zones / All Locations Toggle) -->
        <?php include __DIR__ . '/partials/warehouse/gate_view.php'; ?>

    <?php else: ?>
        <!-- Single Shelf or Global View -->
        <div class="sector-nav">
            <?php foreach ($sectors as $s):
                $sector_url = "index.php?view=warehouse&sector=" . urlencode($s['name']) . "&loc=" . urlencode($selected_loc);
                if (!empty($effective_zone)) {
                    $sector_url .= "&zone=" . urlencode($effective_zone);
                }
            ?>
                <a href="<?= $sector_url ?>"
                    class="sector-card <?= $selected_sector === $s['name'] ? 'active' : '' ?>"
                    data-sector="<?= htmlspecialchars($s['name']) ?>">
                    <span class="sector-icon"><?= $s['icon'] ?></span>
                    <span class="sector-name"><?= htmlspecialchars($s['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Main Content Layout Grid -->
        <div class="warehouse-layout <?= $is_spreadsheet ? 'spreadsheet-mode' : '' ?>">
            <?php if ($is_spreadsheet): ?>
                <?php include __DIR__ . '/partials/warehouse/spreadsheet_view.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/partials/warehouse/table_view.php'; ?>
                <?php include __DIR__ . '/partials/warehouse/sidebar_form.php'; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 8. Modal Windows -->
<?php include __DIR__ . '/partials/warehouse/modals.php'; ?>