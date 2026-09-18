<?php
/**
 * Warehouse Working Zones Grid Partial
 * Displays parent working zones with item aggregations and shelf counts.
 */
?>
<div class="loc-grid" id="gate-zones-grid">
    <div class="loc-item new-loc" style="padding: 12px 10px; cursor: pointer;" onclick="const inp = this.querySelector('input[name=\'zone_name\']'); if(inp && document.activeElement !== inp) { inp.focus(); }">
        <form method="POST" action="" style="width:100%; display:flex; flex-direction:column; align-items:center; gap:4px; margin:0;">
            <input type="hidden" name="action" value="add_working_zone">
            <?= UI::csrf_field() ?>
            <span style="font-size:1.1rem; line-height:1; color:var(--accent-color); font-weight:900;">＋</span>
            <input type="text" name="zone_name" placeholder="+ Add Zone" required
                style="width:100%; border:none; background:transparent; text-align:center; font-weight:800; outline:none; font-size:0.85rem; color:var(--text-main); padding:2px 0;">
            <span style="font-size:0.58rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">Press ↵ Enter</span>
        </form>
    </div>

    <?php foreach ($working_zones as $wz):
        $wz_name = $wz['name'];
        $wz_locations = (int) ($wz['location_count'] ?? 0);
        $wz_items = (int) ($wz['total_items'] ?? 0);
        $has_alerts = (int) ($wz['alert_count'] ?? 0) > 0;
        ?>
        <div class="loc-item-wrapper" style="position:relative;">
            <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>&zone=<?= urlencode($wz_name) ?>"
                class="loc-item gate-loc-item" data-loc-name="<?= htmlspecialchars(strtolower($wz_name)) ?>"
                data-status="<?= $has_alerts ? 'audit' : 'working' ?>" data-count="<?= $wz_locations ?>"
                title="Open Zone <?= htmlspecialchars($wz_name) ?>">
                <div style="position:absolute; top:8px; left:12px; font-size:0.6rem; font-weight:900; text-transform:uppercase; color:#3b82f6; letter-spacing:0.05em;">
                    <small><?= $wz_locations ?></small> <?= $wz_locations == 1 ? "<small>Shelf</small>" : "<small>Locations</small>" ?>
                </div>
                <span class="loc-icon"><small>☷</small></span>
                <span class="loc-name"><?= htmlspecialchars($wz_name) ?></span>
                <div style="font-size:0.7rem; color:#94a3b8; font-weight:700;"><?= number_format($wz_items) ?> Items</div>
            </a>
            <button type="button" onclick='openRenameWorkingZoneModal(<?= json_encode($wz) ?>)'
                class="btn-rename-zone" title="Edit Working Zone">✏️</button>
        </div>
    <?php endforeach; ?>
</div>
