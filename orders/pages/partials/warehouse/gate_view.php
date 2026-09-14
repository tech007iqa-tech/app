<?php
/**
 * Warehouse Gate Navigation View
 * Displays Working Zones and Sub-Location (Shelf) grids, with search, sorting, and dashboard links.
 */
?>
<div class="location-gate">
    <div class="gate-options-container">
        <!-- OPTION 1: REGISTRATION / WORKING ZONE -->
        <div class="gate-card main-gate">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h2 style="font-weight:900; margin-bottom:4px;">Select Working Zone</h2>
                    <p style="color:var(--text-secondary); font-size: 0.9rem;">Choose a shelf to register or edit stock.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div class="search-container" style="max-width: 200px; margin: 0;">
                        <i class="search-icon">🔍</i>
                        <input type="text" id="gate-loc-search" placeholder="Find zone..."
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
                    </select>
                </div>
            </div>

            <?php
            // Get current zone selection from GET parameter
            $active_zone_name = $_GET['zone'] ?? null;

            // Fetch working zones for the grid
            $working_zones = $conn_wh->query("
                SELECT wz.*,
                    (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name) as location_count,
                    (SELECT SUM((SELECT COUNT(*) FROM inventory i WHERE i.location_code = l.location_code)) FROM locations l WHERE l.working_zone_name = wz.name) as total_items,
                    (SELECT COUNT(*) FROM locations l WHERE l.working_zone_name = wz.name AND l.status IN ('Audit', 'Idle')) as alert_count
                FROM working_zones wz
                ORDER BY wz.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (!$active_zone_name): ?>
                <div class="loc-grid" id="gate-loc-grid">
                    <div class="loc-item new-loc" style="padding: 10px;">
                        <form method="POST" action="" style="width:100%;">
                            <input type="hidden" name="action" value="add_working_zone">
                            <?= UI::csrf_field() ?>
                            <input type="text" name="zone_name" placeholder="+ New Working Zone" required
                                style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem;">
                        </form>
                    </div>

                    <?php foreach ($working_zones as $wz):
                        $wz_name = $wz['name'];
                        $wz_locations = (int) $wz['location_count'];
                        $wz_items = (int) $wz['total_items'];
                        $has_alerts = (int) ($wz['alert_count'] ?? 0) > 0;
                        ?>
                        <div class="loc-item-wrapper" style="position:relative;">
                            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&zone=<?= urlencode($wz_name) ?>"
                                class="loc-item gate-loc-item" data-loc-name="<?= htmlspecialchars(strtolower($wz_name)) ?>"
                                data-status="<?= $has_alerts ? 'audit' : 'working' ?>" data-count="<?= $wz_locations ?>">
                                <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:#3b82f6; letter-spacing:0.05em;">
                                    <small><?= $wz_locations ?></small> <?= $wz_locations == 1 ? "<small>Shelf</small>" : "<small>Locations</small>" ?>
                                </div>
                                <span class="loc-icon"><small>☷</small></span>
                                <span class="loc-name"><?= htmlspecialchars($wz_name) ?></span>
                                <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= $wz_items ?> Items</div>
                            </a>
                            <button type="button" onclick='openRenameWorkingZoneModal(<?= json_encode($wz) ?>)'
                                class="btn-rename-zone" title="Edit Working Zone">✏️</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else:
                // Show shelves belonging to the active parent zone
                $filtered_locs = [];
                foreach ($existing_locs as $loc) {
                    if (($loc['working_zone_name'] ?? 'General') === $active_zone_name) {
                        $filtered_locs[] = $loc;
                    }
                }

                $zone_locs = [];
                foreach ($filtered_locs as $loc) {
                    $zone_locs[] = $loc['location_code'];
                }

                $zone_photos = [];
                if (!empty($zone_locs)) {
                    $placeholders = implode(',', array_fill(0, count($zone_locs), '?'));
                    $stmt_zp = $conn_wh->prepare("SELECT * FROM location_photos WHERE location_code IN ($placeholders) ORDER BY location_code ASC, category ASC, created_at DESC");
                    $stmt_zp->execute($zone_locs);
                    $zone_photos = $stmt_zp->fetchAll(PDO::FETCH_ASSOC);
                }
                ?>
                <div style="margin-bottom: 20px;">
                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>" class="btn-export" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; box-shadow:none; display:inline-flex; width:auto; height:36px; padding:0 14px; border-radius:10px;">
                        🔙 Back to Zones
                    </a>
                    <span style="margin-left: 15px; font-weight: 800; color: var(--text-main); font-size: 1.1rem; vertical-align: middle;">
                        Zone: <?= htmlspecialchars($active_zone_name) ?>
                    </span>
                    <button type="button" onclick="document.getElementById('zone-photos-modal').style.display='flex'" class="btn-export" style="background: var(--accent-secondary); color: var(--text-main); border: 1px solid var(--border-color); display: inline-flex; width: auto; height: 36px; padding: 0 14px; border-radius: 10px; margin-left: 15px; font-weight: 600; cursor: pointer; align-items: center; justify-content: center; gap: 6px; vertical-align: middle;">
                        📸 View Zone Photos (<?= count($zone_photos) ?>)
                    </button>
                </div>

                <div class="loc-grid" id="gate-loc-grid">
                    <div class="loc-item new-loc" style="padding: 10px;">
                        <form method="POST" action="" style="width:100%;">
                            <input type="hidden" name="action" value="add_sub_zone">
                            <input type="hidden" name="parent_zone" value="<?= htmlspecialchars($active_zone_name) ?>">
                            <?= UI::csrf_field() ?>
                            <?php
                                $prefix_placeholder = '';
                                if (preg_match('/Zone\s+([a-zA-Z0-9]+)/i', $active_zone_name, $m)) {
                                    $prefix_placeholder = strtoupper($m[1]) . '-';
                                }
                            ?>
                            <input type="text" name="shelf_name" placeholder="+ New Location (e.g. <?= $prefix_placeholder ?>1)" required
                                value="<?= htmlspecialchars($prefix_placeholder) ?>"
                                style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem;">
                        </form>
                    </div>

                    <?php foreach ($filtered_locs as $loc):
                        $l_name = $loc['location_code'];
                        $l_status = $loc['status'];
                        $l_color = $loc['status_color'] ?: '#94a3b8';
                        $l_count = (int) $loc['item_count'];
                        ?>
                        <div class="loc-item-wrapper" style="position:relative;">
                            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=<?= urlencode($l_name) ?>"
                                class="loc-item gate-loc-item" data-loc-name="<?= htmlspecialchars(strtolower($l_name)) ?>"
                                data-status="<?= htmlspecialchars(strtolower($l_status)) ?>" data-count="<?= $l_count ?>">
                                <div
                                    style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:<?= $l_color ?>; letter-spacing:0.05em;">
                                    <?= htmlspecialchars($l_status) ?>
                                </div>
                                <span class="loc-icon">📦</span>
                                <span class="loc-name"><?= htmlspecialchars($l_name) ?></span>
                                <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= $l_count ?> Items</div>
                            </a>
                            <button type="button" onclick='openRenameModal(<?= json_encode($loc) ?>)'
                                class="btn-rename-zone" title="Edit Location & Status">✏️</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div id="gate-no-results"
                style="display:none; text-align:center; padding: 40px; color: #94a3b8; font-weight: 600;">
                No matching zones found.
            </div>
        </div>

        <!-- OPTION 2: GLOBAL OR ZONE DASHBOARD -->
        <div class="gate-card">
            <div style="font-size: 3.5rem; margin-bottom: 25px;">📊</div>
            <?php if ($active_zone_name): ?>
                <h2 style="font-weight:900; margin-bottom:10px;">Zone <?= htmlspecialchars($active_zone_name) ?> Dashboard</h2>
                <p style="color:var(--text-secondary); margin-bottom:30px;">Managing stock and locations within Zone <?= htmlspecialchars($active_zone_name) ?> in one easy view.</p>

                <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                    <a href="index.php?view=warehouse&sector=Master&loc=GLOBAL&zone=<?= urlencode($active_zone_name) ?>"
                        style="display: block; width: 100%; padding: 18px; background: var(--text-main); color: white; border-radius: 14px; font-weight: 800; text-decoration: none; transition: 0.2s; font-size: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        🏢 Master Overview (Zone <?= htmlspecialchars($active_zone_name) ?> Stock)
                    </a>

                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=GLOBAL&zone=<?= urlencode($active_zone_name) ?>"
                        style="display: block; width: 100%; padding: 15px; border: 2px solid #e2e8f0; color: #64748b; border-radius: 14px; font-weight: 700; text-decoration: none; transition: 0.2s; font-size: 0.9rem;">
                        🌐 View Only <?= htmlspecialchars($selected_sector) ?> in Zone <?= htmlspecialchars($active_zone_name) ?>
                    </a>
                </div>
            <?php else: ?>
                <h2 style="font-weight:900; margin-bottom:10px;">Global Dashboard</h2>
                <p style="color:var(--text-secondary); margin-bottom:30px;">Managing stock and locations across all inventory sectors in one easy view.</p>

                <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
                    <a href="index.php?view=warehouse&sector=Master&loc=GLOBAL"
                        style="display: block; width: 100%; padding: 18px; background: var(--text-main); color: white; border-radius: 14px; font-weight: 800; text-decoration: none; transition: 0.2s; font-size: 1rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        🏢 Master Overview (All Stock)
                    </a>

                    <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=GLOBAL"
                        style="display: block; width: 100%; padding: 15px; border: 2px solid #e2e8f0; color: #64748b; border-radius: 14px; font-weight: 700; text-decoration: none; transition: 0.2s; font-size: 0.9rem;">
                        🌐 View Only <?= htmlspecialchars($selected_sector) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
