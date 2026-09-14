<?php
/**
 * Warehouse Table View Partial
 * Displays the standard/global inventory overview table with bulk selection, status badges, and staff logs.
 */
?>
<!-- Inventory List -->
<section class="inventory-feed">
    <div class="inventory-feed-header">
        <div class="inventory-summary-title">
            <h2><?= htmlspecialchars($selected_sector) ?> Inventory</h2>
            <?php
            $total_qty = 0;
            foreach ($items as $it) {
                $total_qty += (int) ($it['quantity'] ?? 0);
            }
            ?>
            <div class="inventory-total-count">
                Total Qty: <span class="count-value"><?= number_format($total_qty) ?> Units</span>
            </div>
        </div>
        <div class="inventory-actions">
            <div class="search-container" style="flex: 1; max-width: 300px;">
                <i class="search-icon">🔍</i>
                <input type="text" id="wh-search" placeholder="Search items..."
                    aria-label="Search warehouse inventory" onkeyup="syncSearch(this)"
                    onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input">
            </div>
            <button type="button" onclick="openInventoryModal('intake')" class="btn-inventory" title="Open Inventory Intake & Shelf Depletion Dialog">
                ⚡ INVENTORY
            </button>
            <a href="#wh-main-form" class="btn-export"
                style="background: var(--text-main); color: white; border: none;">NEW Item</a>
            <button type="button" onclick="downloadWarehouseCSV()" class="btn-export">
                📊 Export CSV
            </button>
            <?php
            $import_bulk_url = 'index.php?view=import_warehouse'
                . ($selected_sector ? '&sector=' . urlencode($selected_sector) : '')
                . ($selected_loc && $selected_loc !== 'GLOBAL' ? '&loc=' . urlencode($selected_loc) : '')
                . (!empty($active_zone_name) ? '&zone=' . urlencode($active_zone_name) : '');
            ?>
            <button type="button" onclick="window.location.href='<?= htmlspecialchars($import_bulk_url) ?>'"
                class="btn-export" style="background: #1e293b; color: white; border: none;" title="Bulk import into <?= htmlspecialchars($selected_loc ?: 'Warehouse') ?>">
                📥 Import Bulk
            </button>

        </div>
    </div>

    <div class="scroll-hint">↔️ Swipe horizontally to view all columns</div>
    <div class="inventory-table-container">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                    <th class="col-type">Location</th>
                    <?php if ($selected_sector === 'Master'): ?>
                        <th>Sector</th>
                    <?php endif; ?>
                    <th class="col-main">Make/Model</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-qty">Price</th>
                    <?php if ($selected_sector === 'Laptops'): ?>
                        <th>CPU</th>
                        <th>Ram/Storage</th>
                        <th>Series</th>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                        <th>Category</th>
                        <th>CPU / GPU</th>
                        <th>RAM / Storage</th>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                        <th>CPU / Gen Brand</th>
                    <?php elseif ($selected_sector === 'Master'): ?>
                        <th>Core Specs</th>
                    <?php endif; ?>
                    <th>Notes</th>
                    <th class="col-log">Staff Log</th>
                    <th class="col-actions">Modify</th>
                </tr>
            </thead>
            <tbody id="inventory-list">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="10"
                            style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                            No items found in this sector.
                        </td>
                    </tr>
                <?php else: ?>
                    <!-- Dynamic No Results Placeholder -->
                    <tr id="wh-no-results" class="no-results-row" style="display: none;">
                        <td colspan="12">
                            <div class="no-results-wrapper"
                                style="display: flex; justify-content: center; width: 100%;">
                                <div class="no-results-container">
                                    <div class="no-results-icon">🕵️‍♂️</div>
                                    <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.02em;">No
                                        matches found</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php foreach ($items as $item):
                        $specs = json_decode($item['specs_json'], true) ?: [];

                        // Timezone conversion to America/Los_Angeles
                        $created_date = '';
                        $created_date_only = '';
                        $created_time_only = '';
                        if (!empty($item['created_at'])) {
                            $date_created_obj = new DateTime($item['created_at'], new DateTimeZone('UTC'));
                            $date_created_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                            $created_date = $date_created_obj->format('m/d/y');
                            $created_date_only = $date_created_obj->format('m/d/y');
                            $created_time_only = $date_created_obj->format('h:i A');
                        }

                        $updated_date = '';
                        if (!empty($item['updated_at'])) {
                            $date_updated_obj = new DateTime($item['updated_at'], new DateTimeZone('UTC'));
                            $date_updated_obj->setTimezone(new DateTimeZone('America/Los_Angeles'));
                            $updated_date = $date_updated_obj->format('m/d/y');
                        }
                        ?>
                        <tr class="inventory-card <?= ($highlight_id && $item['id'] == $highlight_id) ? 'highlight-row' : '' ?>"
                            data-id="<?= $item['id'] ?>" data-sector-theme="<?= htmlspecialchars($item['sector']) ?>"
                            data-brand="<?= htmlspecialchars($item['brand']) ?>"
                            data-model="<?= htmlspecialchars($item['model']) ?>"
                            data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                            data-created-date="<?= $created_date_only ?>" data-created-time="<?= $created_time_only ?>"
                            data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                            data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['location_code'] . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['cpu_gen'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['series'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">

                            <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                            <td><span class="location-tag"><?= htmlspecialchars($item['location_code']) ?></span></td>

                            <?php if ($selected_sector === 'Master'): ?>
                                <td>
                                    <a href="index.php?view=warehouse&sector=<?= urlencode($item['sector']) ?>&loc=<?= urlencode($item['location_code']) ?>"
                                        style="text-decoration: none;">
                                        <span
                                            class="sector-badge sector-<?= strtolower($item['sector']) ?>"><?= htmlspecialchars($item['sector']) ?></span>
                                    </a>
                                </td>
                            <?php endif; ?>

                            <td>
                                <div class="cell-make"><?= htmlspecialchars($item['brand']) ?></div>
                                <div class="cell-model"><?= htmlspecialchars($item['model']) ?></div>
                            </td>

                            <td><span class="qty-pill"><?= (int) $item['quantity'] ?></span></td>

                            <td><span class="price-pill">$<?= number_format($item['price'] ?? 0, 0) ?></span></td>

                            <?php if ($selected_sector === 'Laptops'): ?>
                                <td>
                                    <div class="spec-value"><?= htmlspecialchars($specs['cpu'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div class="spec-value">
                                        <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="spec-value">
                                        <?= htmlspecialchars(($specs['series'] ?? '-') . ' (' . ($specs['gen'] ?? '-') . ')') ?>
                                    </div>
                                </td>
                            <?php elseif ($selected_sector === 'Gaming'): ?>
                                <td>
                                    <div class="spec-value"><?= htmlspecialchars($specs['category'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div class="spec-value">
                                        <?= htmlspecialchars(($specs['cpu'] ?? '-') . ' / ' . ($specs['gpu'] ?? '-')) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="spec-value">
                                        <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?>
                                    </div>
                                </td>
                            <?php elseif ($selected_sector === 'Desktops'): ?>
                                <td>
                                    <div class="spec-value"><?= htmlspecialchars($specs['cpu_gen'] ?? '-') ?></div>
                                </td>
                            <?php elseif ($selected_sector === 'Master'): ?>
                                <td>
                                    <div class="master-specs-wrapper">
                                        <?php if ($item['sector'] === 'Laptops'): ?>
                                            <?php if (!empty($specs['cpu'])): ?>
                                                <span class="spec-tag cpu" title="CPU">💻 <?= htmlspecialchars($specs['cpu']) ?><?php if (!empty($specs['gen']) && $specs['gen'] !== '-'): ?> <small>(<?= htmlspecialchars($specs['gen']) ?>)</small><?php endif; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                                <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($specs['series'])): ?>
                                                <span class="spec-tag series" title="Series">🏷️ <?= htmlspecialchars($specs['series']) ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($item['sector'] === 'Gaming'): ?>
                                            <?php if (!empty($specs['category'])): ?>
                                                <span class="spec-tag category" title="Category">🎮 <?= htmlspecialchars($specs['category']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($specs['gpu'])): ?>
                                                <span class="spec-tag gpu" title="GPU">⚡ <?= htmlspecialchars($specs['gpu']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($specs['ram']) || !empty($specs['storage'])): ?>
                                                <span class="spec-tag memory" title="RAM / Storage">💾 <?= htmlspecialchars(($specs['ram'] ?? '-') . ' / ' . ($specs['storage'] ?? '-')) ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($item['sector'] === 'Desktops'): ?>
                                            <?php if (!empty($specs['cpu_gen'])): ?>
                                                <span class="spec-tag cpu" title="CPU/Gen">🖥️ <?= htmlspecialchars($specs['cpu_gen']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="spec-tag empty">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>

                            <td>
                                <div class="notes-cell-wrapper">
                                    <div class="status-row">
                                        <?php if (!empty($item['status'])): ?>
                                            <span
                                                class="status-badge status-<?= htmlspecialchars($item['status']) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                        <?php endif; ?>
                                        <?php
                                        $cond = $specs['condition'] ?? 'Used';
                                        $cond_class = 'cond-' . strtolower(str_replace(' ', '-', $cond));
                                        ?>
                                        <span class="condition-badge <?= $cond_class ?>"><?= htmlspecialchars($cond) ?></span>
                                        <?php if ($item['sector'] === 'Laptops'): ?>
                                            <span class="battery-badge <?= empty($specs['battery']) ? 'missing' : '' ?>"
                                                title="Battery Status">
                                                🔋
                                                <?= !empty($specs['battery']) ? htmlspecialchars($specs['battery']) : 'Missing' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notes-text"><?= htmlspecialchars($specs['notes'] ?? '') ?></div>
                                </div>
                            </td>

                            <td>
                                <div class="staff-log-wrapper">
                                    <div class="log-entry">
                                        <span class="log-user">👤 <?= htmlspecialchars($item['user_owner']) ?></span>
                                        <span class="log-date">Created <?= $created_date ?></span>
                                    </div>
                                    <?php if ($item['last_updated_by']): ?>
                                        <div class="log-entry updated">
                                            <span class="log-user">✏️
                                                <?= htmlspecialchars($item['last_updated_by']) ?></span>
                                            <span class="log-date">Edited <?= $updated_date ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button type="button" class="row-action-btn btn-edit"
                                        onclick='editWarehouseItem(<?= json_encode($item) ?>)'
                                        title="Edit Entry">📝</button>
                                    <button type="button" class="row-action-btn btn-label"
                                        onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                        title="Generate & Download Label">🏷️</button>
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete_inventory">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="sector"
                                            value="<?= htmlspecialchars($selected_sector) ?>">
                                        <input type="hidden" name="location_code"
                                            value="<?= htmlspecialchars($selected_loc) ?>">
                                        <?= UI::csrf_field() ?>
                                        <button type="submit" class="row-action-btn btn-delete"
                                            title="Delete Entry">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                <tr>
                    <td colspan="<?= $selected_sector === 'Master' ? 3 : 2 ?>" style="padding: 15px;">
                        <div class="search-container footer-search" style="max-width: 300px; margin: 0;">
                            <i class="search-icon">🔍</i>
                            <input type="text" id="wh-search-footer" placeholder="Filter these results..."
                                onkeyup="syncSearch(this)"
                                onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input"
                                style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                        </div>
                    </td>
                    <td
                        style="text-align: right; padding: 15px; font-size: 1.1rem; color: #334155; font-weight: 800;">
                        Inventory Total:</td>
                    <td style="padding: 15px;">
                        <span class="qty-pill" id="table-total-qty"
                            style="background: #1e293b; color: white; font-size: 1.1rem; padding: 6px 12px;">
                            <?= number_format($total_qty) ?>
                        </span>
                    </td>
                    <?php
                    $total_cols = 9; // default for Electronics/Other
                    if ($selected_sector === 'Laptops' || $selected_sector === 'Gaming') {
                        $total_cols = 11;
                    } elseif ($selected_sector === 'Desktops') {
                        $total_cols = 9;
                    } elseif ($selected_sector === 'Master') {
                        $total_cols = 10;
                    }
                    $cols_used = ($selected_sector === 'Master' ? 3 : 2) + 2; // first td + Inventory Total td + Qty td
                    $remaining_cols = $total_cols - $cols_used;
                    ?>
                    <td colspan="<?= $remaining_cols ?>"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
