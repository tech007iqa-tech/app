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
                Total Qty: <span class="count-value" id="table-total-qty"><?= number_format($total_qty) ?> Units</span>
                <span id="search-match-count" class="search-match-count-badge" style="display: none;"></span>
            </div>
        </div>
        <div class="inventory-actions">
            <div class="search-container" style="flex: 1; max-width: 340px;">
                <i class="search-icon">🔍</i>
                <input type="text" id="wh-search" placeholder="Search items... (Ctrl+K)"
                    aria-label="Search warehouse inventory" oninput="syncSearch(this)"
                    onkeydown="handleSearchKeydown(event, this)" class="search-input" autocomplete="off" spellcheck="false">
                <button type="button" class="search-clear-btn" id="search-clear-btn" onclick="clearWarehouseSearch()" title="Clear Search (Esc)">✕</button>
                <span class="search-kbd-hint">Ctrl K</span>
            </div>
            <button type="button" onclick="openInventoryModal('intake')" class="btn-inventory" title="Open Inventory Intake & Shelf Depletion Dialog">
                ⚡ INVENTORY
            </button>
            <?php if ($selected_loc !== 'GLOBAL'): ?>
                <a href="#wh-main-form" class="btn-export"
                    style="background: var(--text-main); color: white; border: none;">NEW Item</a>
            <?php else: ?>
                <button type="button" onclick="openInventoryModal('intake')" class="btn-export"
                    style="background: var(--text-main); color: white; border: none;" title="Open Quick Inbound Intake">➕ Quick Intake</button>
            <?php endif; ?>
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
                    <th class="col-main">Make/Model</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-price">Price</th>
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
                        <th class="col-specs">Core Specs</th>
                    <?php endif; ?>
                    <th class="col-notes">Notes</th>
                    <th class="col-log">Staff Log</th>
                    <?php if ($selected_sector === 'Master'): ?>
                        <th class="col-sector">Sector</th>
                    <?php endif; ?>
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
                            <div class="no-results-wrapper" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; gap: 10px;">
                                <div style="font-size: 2rem;">🕵️‍♂️</div>
                                <div style="font-size: 1.25rem; font-weight: 900; letter-spacing: -0.02em; color: var(--text-main, #0f172a);">No items matching your search filter</div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary, #64748b);">Try different keywords or press escape to clear.</div>
                                <button type="button" onclick="clearWarehouseSearch()" class="btn-export" style="background: var(--accent-color, #0284c7); color: white; padding: 6px 16px; height: auto; font-size: 0.8rem; margin-top: 5px;">
                                    ✕ Clear Search Filter
                                </button>
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
                            data-sector="<?= htmlspecialchars($item['sector']) ?>"
                            data-location="<?= htmlspecialchars($item['location_code'] ?? '') ?>"
                            data-brand="<?= htmlspecialchars($item['brand']) ?>"
                            data-model="<?= htmlspecialchars($item['model']) ?>"
                            data-qty="<?= (int)$item['quantity'] ?>"
                            data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                            data-created-date="<?= $created_date_only ?>" data-created-time="<?= $created_time_only ?>"
                            data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                            data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . ($item['location_code'] ?? '') . ' ' . ($item['sector'] ?? '') . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['cpu_gen'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['series'] ?? '') . ' ' . ($specs['notes'] ?? '') . ' ' . ($specs['condition'] ?? ''))) ?>">

                            <td style="text-align: center;"><input type="checkbox" class="row-select"></td>
                            <td class="col-type"><span class="location-tag"><?= htmlspecialchars($item['location_code']) ?></span></td>

                            <td class="col-main">
                                <div class="cell-make"><?= htmlspecialchars($item['brand']) ?></div>
                                <div class="cell-model"><?= htmlspecialchars($item['model']) ?></div>
                            </td>

                            <td class="col-qty"><span class="qty-pill"><?= (int) $item['quantity'] ?></span></td>

                            <td class="col-price"><span class="price-pill">$<?= number_format($item['price'] ?? 0, 0) ?></span></td>

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
                            <?php elseif ($selected_sector === 'Master'):
                                $core_primary = '';
                                $core_secondary = '';

                                if ($item['sector'] === 'Laptops') {
                                    $cpu_str = $specs['cpu'] ?? '';
                                    if (!empty($specs['gen']) && $specs['gen'] !== '-') {
                                        $cpu_str .= ($cpu_str ? ' (' . $specs['gen'] . ')' : $specs['gen']);
                                    }
                                    $core_primary = $cpu_str ?: ($specs['series'] ?? '-');

                                    $sec_parts = [];
                                    if (!empty($specs['series']) && $core_primary !== $specs['series']) {
                                        $sec_parts[] = $specs['series'];
                                    }
                                    $ram_val = trim($specs['ram'] ?? '');
                                    $storage_val = trim($specs['storage'] ?? '');
                                    $has_ram = ($ram_val !== '' && $ram_val !== '-');
                                    $has_storage = ($storage_val !== '' && $storage_val !== '-');
                                    if ($has_ram && $has_storage) {
                                        $sec_parts[] = $ram_val . ' / ' . $storage_val;
                                    } elseif ($has_ram) {
                                        $sec_parts[] = $ram_val;
                                    } elseif ($has_storage) {
                                        $sec_parts[] = $storage_val;
                                    }
                                    $core_secondary = implode(' • ', $sec_parts);
                                } elseif ($item['sector'] === 'Gaming') {
                                    $core_primary = $specs['gpu'] ?? ($specs['category'] ?? '-');
                                    $sec_parts = [];
                                    if (!empty($specs['cpu']) && $specs['cpu'] !== '-') $sec_parts[] = $specs['cpu'];
                                    $ram_val = trim($specs['ram'] ?? '');
                                    $storage_val = trim($specs['storage'] ?? '');
                                    $has_ram = ($ram_val !== '' && $ram_val !== '-');
                                    $has_storage = ($storage_val !== '' && $storage_val !== '-');
                                    if ($has_ram && $has_storage) {
                                        $sec_parts[] = $ram_val . ' / ' . $storage_val;
                                    } elseif ($has_ram) {
                                        $sec_parts[] = $ram_val;
                                    } elseif ($has_storage) {
                                        $sec_parts[] = $storage_val;
                                    }
                                    $core_secondary = implode(' • ', $sec_parts);
                                } elseif ($item['sector'] === 'Desktops') {
                                    $core_primary = $specs['cpu_gen'] ?? ($specs['cpu'] ?? '-');
                                    $ram_val = trim($specs['ram'] ?? '');
                                    $storage_val = trim($specs['storage'] ?? '');
                                    $has_ram = ($ram_val !== '' && $ram_val !== '-');
                                    $has_storage = ($storage_val !== '' && $storage_val !== '-');
                                    if ($has_ram && $has_storage) {
                                        $core_secondary = $ram_val . ' / ' . $storage_val;
                                    } elseif ($has_ram) {
                                        $core_secondary = $ram_val;
                                    } elseif ($has_storage) {
                                        $core_secondary = $storage_val;
                                    }
                                } else {
                                    $core_primary = $specs['type'] ?? ($specs['voltage'] ?? '-');
                                    $core_secondary = (!empty($specs['type']) && !empty($specs['voltage']) && $specs['voltage'] !== '-') ? $specs['voltage'] : '';
                                }
                            ?>
                                <td class="col-specs">
                                    <div class="cell-make"><?= htmlspecialchars($core_primary ?: '-') ?></div>
                                    <?php if (!empty($core_secondary)): ?>
                                        <div class="cell-model"><?= htmlspecialchars($core_secondary) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="col-notes">
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

                            <td class="col-log">
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

                            <?php if ($selected_sector === 'Master'):
                                $target_loc = !empty($selected_loc) ? $selected_loc : ($item['location_code'] ?? 'GLOBAL');
                                $sector_href = "index.php?view=warehouse&sector=" . urlencode($item['sector']) . "&loc=" . urlencode($target_loc);
                                if (!empty($active_zone_name)) {
                                    $sector_href .= "&zone=" . urlencode($active_zone_name);
                                }
                            ?>
                                <td class="col-sector">
                                    <a href="<?= $sector_href ?>"
                                        style="text-decoration: none;"
                                        title="Filter <?= htmlspecialchars($item['sector']) ?> in <?= htmlspecialchars($target_loc) ?>">
                                        <span
                                            class="sector-badge sector-<?= strtolower($item['sector']) ?>"><?= htmlspecialchars($item['sector']) ?></span>
                                    </a>
                                </td>
                            <?php endif; ?>

                            <td class="col-actions">
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
                    <td colspan="2" style="padding: 15px;">
                        <div class="search-container footer-search" style="max-width: 300px; margin: 0;">
                            <i class="search-icon">🔍</i>
                            <input type="text" id="wh-search-footer" placeholder="Filter these results..."
                                oninput="syncSearch(this)"
                                onkeydown="handleSearchKeydown(event, this)" class="search-input"
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
                    $remaining_cols = max(1, $total_cols - 4);
                    ?>
                    <td colspan="<?= $remaining_cols ?>"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
