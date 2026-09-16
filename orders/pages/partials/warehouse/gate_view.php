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
        (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name) as location_count,
        (SELECT SUM((SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code)) FROM locations l WHERE l.working_zone_name = wz.name) as total_items,
        (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name AND l.status IN ('Audit', 'Idle')) as alert_count
    FROM working_zones wz
    ORDER BY wz.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
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
                        </div>
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
