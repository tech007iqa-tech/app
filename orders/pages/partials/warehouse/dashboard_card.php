<?php
/**
 * Warehouse Dashboard Card Partial
 * Displays Master Overview and Sector-Specific drilldown launchpads for Global, All Locations, or Zone contexts.
 * 
 * Variables used:
 * - $active_zone_name: string|null
 * - $selected_sector: string
 */
?>
<div class="gate-card">
    <div style="font-size: 3.5rem; margin-bottom: 25px;">📊</div>
    <?php if (!empty($active_zone_name)): ?>
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
