<?php
/**
 * Warehouse Spreadsheet Mode View
 * In-cell editable grid layout with location photo gallery, auto-calculating totals, and rapid keyboard intake.
 */

$is_zone_overview = (!empty($active_zone_name) && empty($selected_loc));
$show_location_col = empty($selected_loc) || $selected_loc === 'GLOBAL' || $is_zone_overview;

// Find default shelf for blank intake row
$default_shelf = $selected_loc ?: '';
if (empty($default_shelf) && !empty($active_zone_name)) {
    if (!empty($existing_locs)) {
        foreach ($existing_locs as $el) {
            if (($el['working_zone_name'] ?? 'General') === $active_zone_name) {
                $default_shelf = $el['location_code'];
                break;
            }
        }
    }
    if (empty($default_shelf)) {
        if (preg_match('/Zone\s+([a-zA-Z0-9]+)/i', $active_zone_name, $m)) {
            $default_shelf = strtoupper($m[1]) . '-1';
        } else {
            $default_shelf = $active_zone_name . '-1';
        }
    }
}
?>
<!-- Metadata helper for JS -->
<div id="warehouse-metadata"
     data-csrf="<?= htmlspecialchars(Security::getToken()) ?>"
     data-sector="<?= htmlspecialchars($selected_sector) ?>"
     data-location-code="<?= htmlspecialchars($selected_loc ?: $default_shelf) ?>"
     data-zone="<?= htmlspecialchars($active_zone_name ?? '') ?>"
     style="display:none;"></div>

<!-- Inventory List (Spreadsheet Mode) -->
<section class="inventory-feed" style="width: 100%; max-width: none;">
    <div class="inventory-feed-header">
        <div class="inventory-summary-title">
            <h2>
                <?php if ($is_zone_overview): ?>
                    Zone <?= htmlspecialchars($active_zone_name) ?> &mdash; <?= htmlspecialchars($selected_sector) ?> Inventory
                <?php else: ?>
                    <?= htmlspecialchars($selected_sector) ?> Inventory
                <?php endif; ?>
            </h2>
            <?php
            $total_qty = 0;
            foreach ($items as $it) {
                $total_qty += (int) ($it['quantity'] ?? 0);
            }
            ?>
            <div class="inventory-total-count">
                Total Qty: <span class="count-value" id="sidebar-total-qty"><?= number_format($total_qty) ?> Units</span>
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
                class="btn-export" style="background: #1e293b; color: white; border: none;" title="Bulk import into <?= htmlspecialchars($selected_loc ?: ($active_zone_name ? 'Zone ' . $active_zone_name : 'Warehouse')) ?>">
                📥 Import Bulk
            </button>
        </div>
    </div>

    <div class="scroll-hint">↔️ Swipe horizontally to edit/view all columns</div>

    <?php if ($selected_loc && $selected_loc !== 'GLOBAL'): ?>
        <!-- Collapsible Location Photo Gallery Widget for single shelf -->
        <div style="margin-bottom: 1.5rem; margin-top: 0.5rem; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-card); overflow: hidden;">
            <details style="padding: 1rem; cursor: pointer;">
                <summary style="font-weight: 700; font-size: 0.95rem; color: var(--text-main); display: flex; justify-content: space-between; align-items: center; list-style: none;">
                    <span>📸 Location Photos for <?= htmlspecialchars($selected_loc) ?> (<?= htmlspecialchars($selected_sector) ?>)</span>
                    <span class="photo-count" style="font-size: 0.85rem; background: var(--accent-color); color: white; padding: 2px 8px; border-radius: 12px;"><?= count($location_photos) ?> Photos</span>
                </summary>

                <div style="margin-top: 1rem;">
                    <!-- Gallery Grid -->
                    <div class="photo-grid-horizontal" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem; align-items: center;">
                        <?php if (empty($location_photos)): ?>
                            <div style="color: var(--text-dim); font-size: 0.85rem; padding: 1rem 0;">No photographs uploaded for this shelf yet.</div>
                        <?php else: ?>
                            <?php foreach ($location_photos as $photo): ?>
                                <div class="photo-card-mini" style="flex: 0 0 100px; text-align: center; border: 1px solid var(--border-color); border-radius: 8px; padding: 4px; background: var(--bg-body); position: relative;">
                                    <div class="img-preview-container" style="position: relative; width: 100%; height: 75px; overflow: hidden; border-radius: 6px;">
                                        <img src="<?= htmlspecialchars($photo['thumbnail_path']) ?>" alt="<?= htmlspecialchars($photo['original_filename']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <div class="hover-preview" style="display: none; position: fixed; z-index: 2100; width: 450px; height: 350px; background: rgba(0,0,0,0.95); border: 2px solid var(--accent-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; pointer-events: none;">
                                            <img src="<?= htmlspecialchars($photo['optimized_path']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                        </div>
                                    </div>
                                    <div style="font-size: 0.7rem; font-weight: 700; margin-top: 4px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?= htmlspecialchars($photo['category']) ?>">
                                        <?= htmlspecialchars($photo['category']) ?>
                                    </div>
                                    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 4px;">
                                        <a href="download_archive.php?id=<?= $photo['id'] ?>" class="btn-icon-tiny" title="Download Raw Original" style="font-size: 0.75rem; text-decoration: none;">📥</a>
                                        <button type="button" onclick="deleteLocationPhotoAjax(<?= $photo['id'] ?>, this)" style="background: none; border: none; padding: 0; cursor: pointer; font-size: 0.75rem;" title="Delete Photo">🗑️</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Add Photo trigger -->
                        <button type="button" onclick="CameraUploader.open({ locationCode: '<?= htmlspecialchars($selected_loc) ?>', sector: '<?= htmlspecialchars($selected_sector) ?>', onSuccess: () => window.location.reload() })" style="flex: 0 0 100px; height: 110px; border: 2px dashed var(--border-color); border-radius: 8px; background: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--text-dim); transition: all 0.2s;">
                            <span style="font-size: 1.5rem;">📷</span>
                            <span style="font-size: 0.75rem; font-weight: 600;">Camera / Add</span>
                        </button>
                    </div>
                </div>
            </details>
        </div>
    <?php endif; ?>

    <div class="spreadsheet-table-wrapper">
        <table class="spreadsheet-table">
            <thead>
                <?php if ($selected_sector === 'Laptops'): ?>
                <tr>
                    <?php if ($show_location_col): ?><th style="width: 8%;">Shelf</th><?php endif; ?>
                    <th style="width: 10%;">Brand</th>
                    <th style="width: 10%;">Model</th>
                    <th style="width: 10%;">Series</th>
                    <th style="width: 8%;">CPU</th>
                    <th style="width: 7%;">Gen</th>
                    <th style="width: 7%;">RAM</th>
                    <th style="width: 8%;">Storage</th>
                    <th style="width: 7%;">Battery</th>
                    <th style="width: 8%;">Condition</th>
                    <th style="width: 6%;">Qty</th>
                    <th style="width: 7%;">Price</th>
                    <th style="width: 12%;">Notes</th>
                    <th style="width: 6%;"></th>
                </tr>
                <?php elseif ($selected_sector === 'Gaming'): ?>
                <tr>
                    <?php if ($show_location_col): ?><th style="width: 8%;">Shelf</th><?php endif; ?>
                    <th style="width: 10%;">Brand</th>
                    <th style="width: 10%;">Model</th>
                    <th style="width: 10%;">Category</th>
                    <th style="width: 9%;">Specs/Series</th>
                    <th style="width: 7%;">CPU</th>
                    <th style="width: 7%;">GPU</th>
                    <th style="width: 7%;">RAM</th>
                    <th style="width: 8%;">Storage</th>
                    <th style="width: 8%;">Condition</th>
                    <th style="width: 6%;">Qty</th>
                    <th style="width: 7%;">Price</th>
                    <th style="width: 12%;">Notes</th>
                    <th style="width: 6%;"></th>
                </tr>
                <?php elseif ($selected_sector === 'Desktops'): ?>
                <tr>
                    <?php if ($show_location_col): ?><th style="width: 10%;">Shelf</th><?php endif; ?>
                    <th style="width: 12%;">Brand</th>
                    <th style="width: 15%;">Model</th>
                    <th style="width: 18%;">CPU/Gen/Brand</th>
                    <th style="width: 12%;">Condition</th>
                    <th style="width: 8%;">Qty</th>
                    <th style="width: 10%;">Price</th>
                    <th style="width: 23%;">Notes</th>
                    <th style="width: 6%;"></th>
                </tr>
                <?php else: ?>
                <tr>
                    <?php if ($show_location_col): ?><th style="width: 10%;">Shelf</th><?php endif; ?>
                    <th style="width: 12%;">Brand</th>
                    <th style="width: 15%;">Model</th>
                    <th style="width: 15%;">Device Type</th>
                    <th style="width: 15%;">Voltage/Specs</th>
                    <th style="width: 12%;">Condition</th>
                    <th style="width: 8%;">Qty</th>
                    <th style="width: 10%;">Price</th>
                    <th style="width: 18%;">Notes</th>
                    <th style="width: 6%;"></th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody id="inventory-list">
                <?php foreach ($items as $item):
                    $specs = json_decode($item['specs_json'], true) ?: [];
                    ?>
                    <tr class="inventory-card summary-row" data-id="<?= $item['id'] ?>"
                        data-brand="<?= htmlspecialchars($item['brand']) ?>"
                        data-model="<?= htmlspecialchars($item['model']) ?>"
                        data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>"
                        data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
                        data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . ($item['location_code'] ?? '') . ' ' . ($specs['cpu'] ?? '') . ' ' . ($specs['ram'] ?? '') . ' ' . ($specs['storage'] ?? '') . ' ' . ($specs['notes'] ?? ''))) ?>">

                        <?php if ($show_location_col): ?>
                            <td class="editable-cell" data-field="location_code">
                                <input type="text" class="cell-input text-center" value="<?= htmlspecialchars($item['location_code'] ?? '') ?>" list="zone-shelves-list" style="font-weight: 800; color: #2563eb;" title="Shelf Location">
                            </td>
                        <?php endif; ?>

                        <td class="editable-cell" data-field="brand">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($item['brand']) ?>" list="brand-options" placeholder="...">
                        </td>
                        <td class="editable-cell" data-field="model">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($item['model']) ?>" placeholder="...">
                        </td>

                        <?php if ($selected_sector === 'Laptops'): ?>
                            <td class="editable-cell" data-field="series">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="cpu">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" list="cpu-options-list" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="gen">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gen'] ?? '') ?>" list="gen-options-list" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="ram">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="storage">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="battery">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['battery'] ?? '') ?>" list="battery-options-list" placeholder="...">
                            </td>
                        <?php elseif ($selected_sector === 'Gaming'): ?>
                            <td class="editable-cell" data-field="gaming_category">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['category'] ?? '') ?>" list="gaming-cat-list" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="series">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['series'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="cpu">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="gpu">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['gpu'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="ram">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['ram'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="storage">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['storage'] ?? '') ?>" placeholder="...">
                            </td>
                        <?php elseif ($selected_sector === 'Desktops'): ?>
                            <td class="editable-cell" data-field="cpu_gen">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['cpu_gen'] ?? '') ?>" list="cpu-gen-options-list" placeholder="...">
                            </td>
                        <?php else: // Electronics/Other ?>
                            <td class="editable-cell" data-field="type">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['type'] ?? '') ?>" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="voltage">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['voltage'] ?? '') ?>" placeholder="...">
                            </td>
                        <?php endif; ?>

                        <td class="editable-cell" data-field="condition">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['condition'] ?? 'Used') ?>" list="condition-options-list" placeholder="...">
                        </td>
                        <td class="editable-cell numeric" data-field="quantity">
                            <input type="number" step="1" class="cell-input text-center font-bold" value="<?= (int)$item['quantity'] ?>">
                        </td>
                        <td class="editable-cell numeric" data-field="price">
                            <input type="number" step="any" class="cell-input text-right" value="<?= htmlspecialchars($item['price'] ?? '0.00') ?>">
                        </td>
                        <td class="editable-cell" data-field="notes">
                            <input type="text" class="cell-input" value="<?= htmlspecialchars($specs['notes'] ?? '') ?>" placeholder="...">
                        </td>
                        <td style="text-align:right;">
                            <div class="action-buttons">
                                <button type="button" class="btn-clone-row" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px;" title="Clone Row">➕</button>
                                <button type="button" class="btn-label"
                                    onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                    title="Generate & Download Label" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">🏷️</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item?');">
                                    <input type="hidden" name="action" value="delete_inventory">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                    <input type="hidden" name="location_code" value="<?= htmlspecialchars($item['location_code'] ?? $selected_loc) ?>">
                                    <?= UI::csrf_field() ?>
                                    <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Permanent blank row at the bottom for rapid intake -->
                <tr class="summary-row new-blank-row" data-id="new">
                    <?php if ($show_location_col): ?>
                        <td class="editable-cell" data-field="location_code">
                            <input type="text" class="cell-input text-center" list="zone-shelves-list" placeholder="Shelf..." value="<?= htmlspecialchars($default_shelf) ?>" style="font-weight: 800; color: #2563eb;" title="Intake Shelf">
                        </td>
                    <?php endif; ?>

                    <td class="editable-cell" data-field="brand">
                        <input type="text" class="cell-input" list="brand-options" placeholder="Brand...">
                    </td>
                    <td class="editable-cell" data-field="model">
                        <input type="text" class="cell-input" placeholder="Model...">
                    </td>

                    <?php if ($selected_sector === 'Laptops'): ?>
                        <td class="editable-cell" data-field="series">
                            <input type="text" class="cell-input" placeholder="Series...">
                        </td>
                        <td class="editable-cell" data-field="cpu">
                            <input type="text" class="cell-input" list="cpu-options-list" placeholder="CPU...">
                        </td>
                        <td class="editable-cell" data-field="gen">
                            <input type="text" class="cell-input" list="gen-options-list" placeholder="Gen...">
                        </td>
                        <td class="editable-cell" data-field="ram">
                            <input type="text" class="cell-input" placeholder="RAM...">
                        </td>
                        <td class="editable-cell" data-field="storage">
                            <input type="text" class="cell-input" placeholder="Storage...">
                        </td>
                        <td class="editable-cell" data-field="battery">
                            <input type="text" class="cell-input" list="battery-options-list" placeholder="Battery...">
                        </td>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                        <td class="editable-cell" data-field="gaming_category">
                            <input type="text" class="cell-input" list="gaming-cat-list" placeholder="Category...">
                        </td>
                        <td class="editable-cell" data-field="series">
                            <input type="text" class="cell-input" placeholder="Series...">
                        </td>
                        <td class="editable-cell" data-field="cpu">
                            <input type="text" class="cell-input" placeholder="CPU...">
                        </td>
                        <td class="editable-cell" data-field="gpu">
                            <input type="text" class="cell-input" placeholder="GPU...">
                        </td>
                        <td class="editable-cell" data-field="ram">
                            <input type="text" class="cell-input" placeholder="RAM...">
                        </td>
                        <td class="editable-cell" data-field="storage">
                            <input type="text" class="cell-input" placeholder="Storage...">
                        </td>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                        <td class="editable-cell" data-field="cpu_gen">
                            <input type="text" class="cell-input" list="cpu-gen-options-list" placeholder="CPU/Gen...">
                        </td>
                    <?php else: ?>
                        <td class="editable-cell" data-field="type">
                            <input type="text" class="cell-input" placeholder="Type...">
                        </td>
                        <td class="editable-cell" data-field="voltage">
                            <input type="text" class="cell-input" placeholder="Specs...">
                        </td>
                    <?php endif; ?>

                    <td class="editable-cell" data-field="condition">
                        <input type="text" class="cell-input" list="condition-options-list" placeholder="Condition...">
                    </td>
                    <td class="editable-cell numeric" data-field="quantity">
                        <input type="number" step="1" class="cell-input text-center font-bold" placeholder="Qty...">
                    </td>
                    <td class="editable-cell numeric" data-field="price">
                        <input type="number" step="any" class="cell-input text-right" placeholder="Price...">
                    </td>
                    <td class="editable-cell" data-field="notes">
                        <input type="text" class="cell-input" placeholder="Notes...">
                    </td>
                    <td style="text-align:right;">
                        <div class="action-buttons" style="justify-content: flex-end;">
                            <button type="button" class="btn-add-row-indicator" title="Add this item to inventory (or press Enter)" style="background: #2563eb; color: white; border: none; font-size: 1rem; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform 0.15s, background-color 0.15s;" onmouseover="this.style.transform='scale(1.1)'; this.style.backgroundColor='#1d4ed8';" onmouseout="this.style.transform='scale(1)'; this.style.backgroundColor='#2563eb';">➕</button>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                <tr>
                    <?php
                    $total_cols_sp = 9;
                    if ($selected_sector === 'Laptops') $total_cols_sp = 13;
                    elseif ($selected_sector === 'Gaming') $total_cols_sp = 13;
                    elseif ($selected_sector === 'Desktops') $total_cols_sp = 8;
                    if ($show_location_col) $total_cols_sp += 1;
                    ?>
                    <td colspan="<?= $total_cols_sp - 4 ?>" style="padding: 15px;">
                        <div class="search-container footer-search" style="max-width: 300px; margin: 0;">
                            <i class="search-icon">🔍</i>
                            <input type="text" id="wh-search-footer" placeholder="Filter these results..."
                                onkeyup="syncSearch(this)"
                                onkeydown="if(event.key==='Enter') event.preventDefault()" class="search-input"
                                style="height: 40px; font-size: 0.9rem; border-radius: 10px;">
                        </div>
                    </td>
                    <td style="text-align: right; padding: 15px; font-size: 1.1rem; color: #334155; font-weight: 800;">
                        Inventory Total:
                    </td>
                    <td style="padding: 15px; text-align: center;">
                        <span class="qty-pill" id="table-total-qty" style="background: #1e293b; color: white; font-size: 1.1rem; padding: 6px 12px; display: inline-block;">
                            <?= number_format($total_qty) ?>
                        </span>
                    </td>
                    <td colspan="2" style="text-align: right; padding: 15px;">
                        <button type="button" id="btn-consolidate-spreadsheet" class="btn-consolidate" onclick="consolidateWarehouseRows()" style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 8px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.75rem; color: #475569;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'" title="Consolidate duplicate rows">
                            🔄 Consolidate
                        </button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<!-- Shelves options datalist for auto-fill -->
<datalist id="zone-shelves-list">
    <?php foreach ($existing_locs as $el): ?>
        <option value="<?= htmlspecialchars($el['location_code']) ?>"><?= htmlspecialchars($el['working_zone_name'] ? $el['working_zone_name'] : 'General') ?></option>
    <?php endforeach; ?>
</datalist>

<!-- Hidden datalists for cells -->
<datalist id="cpu-options-list">
    <option value="i3"></option>
    <option value="i5"></option>
    <option value="i7"></option>
    <option value="i9"></option>
    <option value="Ryzen 3"></option>
    <option value="Ryzen 5"></option>
    <option value="Ryzen 7"></option>
    <option value="Ryzen 9"></option>
</datalist>
<datalist id="gen-options-list">
    <option value="-"></option>
    <option value="4th & 5th"></option>
    <option value="6th & 7th"></option>
    <option value="8th"></option>
    <option value="9th"></option>
    <option value="10th"></option>
    <option value="11th"></option>
    <option value="12th"></option>
    <option value="13th"></option>
    <option value="14th"></option>
    <option value="Core 2 Duo"></option>
    <option value="2nd"></option>
    <option value="3rd"></option>
    <option value="AMD"></option>
</datalist>
<datalist id="battery-options-list">
    <option value="Yes"></option>
    <option value="Unknown"></option>
</datalist>
<datalist id="gaming-cat-list">
    <option value="PC"></option>
    <option value="Consoles"></option>
    <option value="Controllers"></option>
    <option value="Games"></option>
</datalist>
<datalist id="cpu-gen-options-list">
    <option value="2nd-3rd Gen"></option>
    <option value="4th-5th Gen"></option>
    <option value="6th-7th Gen"></option>
    <option value="i5-8th Gen"></option>
    <option value="i7-8th Gen"></option>
    <option value="i5-9th Gen"></option>
    <option value="i7-9th Gen"></option>
    <option value="i5-10th Gen"></option>
    <option value="i7-10th Gen"></option>
    <option value="i5-11th Gen"></option>
    <option value="i7-11th Gen"></option>
    <option value="i5-12th Gen"></option>
    <option value="i7-12th Gen"></option>
    <option value="i5-13th Gen"></option>
    <option value="i7-13th Gen"></option>
</datalist>
<datalist id="condition-options-list">
    <option value="A Grade"></option>
    <option value="B Grade"></option>
    <option value="C Grade"></option>
    <option value="No Power"></option>
    <option value="No Post"></option>
</datalist>
