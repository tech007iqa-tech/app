<?php
/**
 * Warehouse Gate Navigation View
 * Modular orchestrator for Working Zones, All Locations, and Control Dashboards.
 */

// Get current zone selection from GET parameter
$active_zone_name = $_GET['zone'] ?? null;

// Fetch working zones dataset
$working_zones = $conn_wh->query("
    SELECT wz.*,
        (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name AND COALESCE(l.is_archived, 0) = 0) as location_count,
        (SELECT SUM((SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code)) FROM locations l WHERE l.working_zone_name = wz.name AND COALESCE(l.is_archived, 0) = 0) as total_items,
        (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name AND l.status IN ('Audit', 'Idle') AND COALESCE(l.is_archived, 0) = 0) as alert_count
    FROM working_zones wz
    ORDER BY wz.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch archived locations
try {
    $stmt_arch = $conn_wh->query("
        SELECT l.*,
            (SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code) as item_count,
            ls.color as status_color
        FROM locations l
        LEFT JOIN (
            SELECT name, color FROM location_statuses GROUP BY name
        ) ls ON l.status = ls.name
        WHERE l.is_archived = 1
        ORDER BY l.archived_at DESC, l.location_code ASC
    ");
    $archived_locs = $stmt_arch ? $stmt_arch->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {
    $archived_locs = [];
}
?>
<div class="location-gate">
    <div class="gate-options-container">
        <!-- OPTION 1: REGISTRATION / WORKING ZONE / ALL LOCATIONS -->
        <div class="gate-card main-gate">
            <div class="gate-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <?php if ($active_zone_name): ?>
                        <h2 style="font-weight:900; margin-bottom:4px;">Zone <?= htmlspecialchars($active_zone_name) ?> Shelves</h2>
                        <p style="color:var(--text-secondary); font-size: 0.9rem;">Choose a shelf to register or edit stock in this zone.</p>
                    <?php else: ?>
                        <h2 style="font-weight:900; margin-bottom:4px;">Select Working Zone</h2>
                        <p style="color:var(--text-secondary); font-size: 0.9rem;">Choose a zone or view all warehouse storage locations.</p>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <?php if (!$active_zone_name): ?>
                        <!-- Segmented Mode Switcher: By Working Zone vs All Locations -->
                        <div class="gate-view-toggle" style="display: inline-flex; background: #f1f5f9; padding: 3px; border-radius: 10px; border: 1px solid #e2e8f0;">
                            <button type="button" id="btn-gate-view-zones" onclick="switchGateViewMode('zones')"
                                class="gate-toggle-btn active"
                                style="border:none; background:white; color:var(--text-main); font-weight:800; font-size:0.8rem; padding:6px 14px; border-radius:8px; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,0.1); transition:all 0.2s;">
                                🏢 By Working Zone
                            </button>
                            <button type="button" id="btn-gate-view-all" onclick="switchGateViewMode('all_locations')"
                                class="gate-toggle-btn"
                                style="border:none; background:transparent; color:#64748b; font-weight:700; font-size:0.8rem; padding:6px 14px; border-radius:8px; cursor:pointer; transition:all 0.2s;">
                                📍 All Locations (<?= count($existing_locs) ?>)
                            </button>
                            <?php if (!empty($archived_locs)): ?>
                                <button type="button" id="btn-gate-view-archived" onclick="switchGateViewMode('archived')"
                                    class="gate-toggle-btn"
                                    style="border:none; background:transparent; color:#64748b; font-weight:700; font-size:0.8rem; padding:6px 14px; border-radius:8px; cursor:pointer; transition:all 0.2s;">
                                    🗄️ Archived (<?= count($archived_locs) ?>)
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Multi-Zone Compare Toggle Button -->
                        <button type="button" id="btn-gate-toggle-compare" onclick="toggleZoneCompareMode()"
                            class="btn-gate-compare"
                            style="border:1px solid #bae6fd; background:#e0f2fe; color:#0284c7; font-weight:800; font-size:0.8rem; padding:6px 14px; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; box-shadow:0 1px 3px rgba(2,132,199,0.12);"
                            title="Compare multiple working zones (or hold-click any zone card)">
                            <span>⚖️ Compare Zones</span>
                            <span class="hold-hint" style="font-size:0.62rem; background:rgba(2,132,199,0.18); padding:1px 5px; border-radius:4px; font-weight:800; letter-spacing:0.02em;">Hold Click</span>
                        </button>
                    <?php endif; ?>

                    <div class="search-container" style="max-width: 200px; margin: 0;">
                        <i class="search-icon">🔍</i>
                        <input type="text" id="gate-loc-search" placeholder="<?= $active_zone_name ? 'Find shelf...' : 'Find zone / shelf...' ?>"
                            onkeyup="filterGateLocations()" class="search-input"
                            style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                    </div>
                    <select id="gate-loc-sort" onchange="sortGateLocations()"
                        style="width: auto; height: 40px; font-size: 0.8rem; border-radius: 10px; padding: 0 12px; font-weight: 700; cursor: pointer; border: 1px solid var(--border-color); background: white; outline: none;">
                        <option value="asc">Sort: A-Z</option>
                        <option value="desc">Sort: Z-A</option>
                        <option value="status">Sort: Status Group</option>
                        <option value="count-desc">Sort: Most Items</option>
                        <option value="count-asc">Sort: Emptiest</option>
                        <?php if (!$active_zone_name): ?>
                            <option value="zone">Sort: Parent Zone</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <?php if ($active_zone_name):
                // Filter shelves belonging to the active parent zone
                $filtered_locs = [];
                $zone_locs = [];
                foreach ($existing_locs as $loc) {
                    if (($loc['working_zone_name'] ?? 'General') === $active_zone_name) {
                        $filtered_locs[] = $loc;
                        $zone_locs[] = $loc['location_code'];
                    }
                }

                // Fetch photos for shelves in this zone
                $zone_photos = [];
                if (!empty($zone_locs)) {
                    $placeholders = implode(',', array_fill(0, count($zone_locs), '?'));
                    $stmt_zp = $conn_wh->prepare("SELECT * FROM location_photos WHERE location_code IN ($placeholders) ORDER BY location_code ASC, category ASC, created_at DESC");
                    $stmt_zp->execute($zone_locs);
                    $zone_photos = $stmt_zp->fetchAll(PDO::FETCH_ASSOC);
                }
                ?>
                <div style="margin-bottom: 20px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>" class="btn-export" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; box-shadow:none; display:inline-flex; width:auto; height:36px; padding:0 14px; border-radius:10px; text-decoration:none; align-items:center; font-weight:700;">
                        🔙 Back to Zones
                    </a>
                    <span style="font-weight: 800; color: var(--text-main); font-size: 1.1rem;">
                        Zone: <?= htmlspecialchars($active_zone_name) ?>
                    </span>
                    <button type="button" onclick="document.getElementById('zone-photos-modal').style.display='flex'" class="btn-export" style="background: var(--accent-secondary); color: var(--text-main); border: 1px solid var(--border-color); display: inline-flex; width: auto; height: 36px; padding: 0 14px; border-radius: 10px; font-weight: 600; cursor: pointer; align-items: center; justify-content: center; gap: 6px;">
                        📸 View Zone Photos (<?= count($zone_photos) ?>)
                    </button>
                    <button type="button" onclick="openZoneComparisonModal(['<?= htmlspecialchars($active_zone_name) ?>'])" class="btn-export" style="background:#e0f2fe; color:#0284c7; border:1px solid #bae6fd; font-weight:800; display:inline-flex; width:auto; height:36px; padding:0 14px; border-radius:10px; align-items:center; gap:6px; cursor:pointer;" title="Compare this zone with other zones">
                        ⚖️ Compare with other Zones...
                    </button>
                </div>

                <!-- Zone-Specific Shelves Grid -->
                <?php
                $display_locs = $filtered_locs;
                $is_all_locations = false;
                $grid_id = 'gate-loc-grid';
                include __DIR__ . '/locations_grid.php';
                ?>

            <?php else: ?>
                <!-- Mode 1: Parent Working Zones Grid -->
                <div id="gate-view-zones-container">
                    <?php include __DIR__ . '/zone_cards_grid.php'; ?>
                </div>

                <!-- Mode 2: All Warehouse Locations Grid (Cross-Zone) -->
                <div id="gate-view-all-locs-container" style="display: none;">
                    <?php
                    $display_locs = $existing_locs;
                    $is_all_locations = true;
                    $grid_id = 'gate-all-locs-grid';
                    include __DIR__ . '/locations_grid.php';
                    ?>
                </div>

                <!-- Mode 3: Archived Shelves Grid -->
                <div id="gate-view-archived-container" style="display: none;">
                    <div style="margin-bottom:15px; color:#64748b; font-size:0.85rem; display:flex; align-items:center; gap:8px;">
                        <span>🗄️</span>
                        <span>These shelves are archived. You can click <strong>Restore</strong> on any shelf to return it to active inventory.</span>
                    </div>
                    <div class="loc-grid" id="gate-archived-locs-grid">
                        <?php foreach ($archived_locs as $al): ?>
                            <div class="loc-item-wrapper" style="position:relative;">
                                <div class="loc-item gate-loc-item" style="border: 2px dashed #cbd5e1; background: #f8fafc; padding: 18px 12px; display: flex; flex-direction: column; align-items: center; justify-content: center;"
                                    data-loc-name="<?= htmlspecialchars(strtolower($al['location_code'])) ?>"
                                    data-zone-name="<?= htmlspecialchars(strtolower($al['working_zone_name'] ?? 'General')) ?>"
                                    data-status="archived"
                                    data-count="0">
                                    <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:#ef4444; letter-spacing:0.05em;">
                                        Archived
                                    </div>
                                    <?php if (!empty($al['working_zone_name'])): ?>
                                        <div class="loc-zone-badge" style="position:absolute; top:8px; right:12px; font-size:0.55rem; font-weight:800; background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:6px;">
                                            <?= htmlspecialchars($al['working_zone_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="loc-icon" style="font-size:1.6rem; margin-top:10px;">🗄️</span>
                                    <span class="loc-name" style="font-size:1.05rem; font-weight:800; color:#64748b; margin-top:4px; text-decoration:line-through;">
                                        <?= htmlspecialchars($al['location_code']) ?>
                                    </span>
                                    <div style="font-size:0.7rem; color:#94a3b8; font-weight:600; margin:4px 0 10px 0;">
                                        <?= htmlspecialchars($al['archived_reason'] ?: 'Empty') ?>
                                    </div>
                                    <form method="POST" action="" style="margin:0;">
                                        <?= UI::csrf_field() ?>
                                        <input type="hidden" name="action" value="restore_location">
                                        <input type="hidden" name="location_code" value="<?= htmlspecialchars($al['location_code']) ?>">
                                        <button type="submit" class="btn-export" style="background:#10b981; color:white; border:none; padding:5px 12px; font-size:0.75rem; font-weight:800; border-radius:8px; cursor:pointer;" title="Restore this shelf to active status">
                                            ♻️ Restore Shelf
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div id="gate-no-results"
                style="display:none; text-align:center; padding: 40px; color: #94a3b8; font-weight: 600;">
                No matching zones or locations found.
            </div>
        </div>

        <!-- OPTION 2: DASHBOARD CARD (GLOBAL / ZONE) -->
        <?php include __DIR__ . '/dashboard_card.php'; ?>
    </div>
</div>
