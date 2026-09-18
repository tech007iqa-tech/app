<?php
/**
 * Warehouse Locations / Shelves Grid Partial
 * Renders sub-location (shelf) cards with status indicators, parent zone tags, item counts, and quick actions.
 * 
 * Variables used:
 * - $display_locs: array of location records
 * - $is_all_locations: boolean (true when viewing all locations across zones)
 * - $active_zone_name: string|null (active parent zone if filtered)
 * - $selected_sector: string
 */

$is_all_locations = $is_all_locations ?? false;
$parent_zone_for_new = $active_zone_name ?? 'General';
$grid_id = $grid_id ?? 'gate-loc-grid';
?>
<div class="loc-grid" id="<?= htmlspecialchars($grid_id) ?>">
    <!-- Quick Add Shelf Card -->
    <div class="loc-item new-loc" style="padding: 12px 10px; cursor: pointer;" onclick="const inp = this.querySelector('input[name=\'shelf_name\']'); if(inp && document.activeElement !== inp) { inp.focus(); }">
        <form method="POST" action="" style="width:100%; display:flex; flex-direction:column; align-items:center; gap:4px; margin:0;">
            <input type="hidden" name="action" value="add_sub_zone">
            <input type="hidden" name="parent_zone" value="<?= htmlspecialchars($parent_zone_for_new) ?>">
            <?= UI::csrf_field() ?>
            <?php
                $prefix_placeholder = '';
                if (!empty($active_zone_name)) {
                    if (preg_match('/(?:Zone|Row|zFloor)?\s*([a-zA-Z0-9]+)$/i', $active_zone_name, $m)) {
                        $prefix_placeholder = strtoupper($m[1]) . '-';
                    }
                }
            ?>
            <span style="font-size:1.1rem; line-height:1; color:var(--accent-color); font-weight:900;">＋</span>
            <input type="text" name="shelf_name" placeholder="+ Add Shelf" required
                value="<?= htmlspecialchars($prefix_placeholder) ?>"
                title="Enter location code (e.g. <?= htmlspecialchars($prefix_placeholder ?: 'A-') ?>1) and press Enter"
                style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem; color:var(--text-main); padding:2px 0;">
            <span style="font-size:0.58rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Press ↵ Enter</span>
        </form>
    </div>

    <!-- Location Cards -->
    <?php foreach ($display_locs as $loc):
        $l_name = $loc['location_code'];
        $l_status = $loc['status'] ?? 'Idle';
        $l_color = $loc['status_color'] ?: '#94a3b8';
        $l_count = (int) ($loc['item_count'] ?? 0);
        $l_zone = $loc['working_zone_name'] ?? 'General';
        ?>
        <div class="loc-item-wrapper" style="position:relative;">
            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&loc=<?= urlencode($l_name) ?><?= !empty($active_zone_name) ? '&zone=' . urlencode($active_zone_name) : '' ?>"
                class="loc-item gate-loc-item"
                data-loc-name="<?= htmlspecialchars(strtolower($l_name)) ?>"
                data-zone-name="<?= htmlspecialchars(strtolower($l_zone)) ?>"
                data-status="<?= htmlspecialchars(strtolower($l_status)) ?>"
                data-count="<?= $l_count ?>"
                title="<?= htmlspecialchars($l_name) ?> (<?= htmlspecialchars($l_zone) ?>) - <?= $l_count ?> Items">
                
                <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:<?= $l_color ?>; letter-spacing:0.05em;">
                    <?= htmlspecialchars($l_status) ?>
                </div>

                <?php if ($is_all_locations && !empty($l_zone)): ?>
                    <div class="loc-zone-badge" style="position:absolute; top:8px; right:12px; font-size:0.55rem; font-weight:800; background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:6px; letter-spacing:0.02em;">
                        <?= htmlspecialchars($l_zone) ?>
                    </div>
                <?php endif; ?>

                <span class="loc-icon">📦</span>
                <span class="loc-name"><?= htmlspecialchars($l_name) ?></span>
                <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= number_format($l_count) ?> Items</div>
            </a>
            <button type="button" onclick='openRenameModal(<?= json_encode($loc) ?>)'
                class="btn-rename-zone" title="Edit Location & Status">✏️</button>
        </div>
    <?php endforeach; ?>
</div>
