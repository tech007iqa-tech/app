<?php
/**
 * Warehouse AJAX Partial Responder
 * Renders inventory list rows (both spreadsheet and standard table format) for AppSync / live updates.
 */

if (UI::is_ajax()) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    ob_start();
    if (empty($items)): ?>
        <tr>
            <td colspan="10" style="padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;">
                No items found in this sector.
            </td>
        </tr>
    <?php else: ?>
        <tr id="wh-no-results" class="no-results-row" style="display: none;">
            <td colspan="12">
                <div class="no-results-wrapper" style="display: flex; justify-content: center; width: 100%;">
                    <div class="no-results-container">
                        <div class="no-results-icon">🕵️‍♂️</div>
                        <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.02em;">No matches found</div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
        $is_zone_overview = (!empty($active_zone_name) && empty($selected_loc));
        $show_location_col = empty($selected_loc) || $selected_loc === 'GLOBAL' || $is_zone_overview;

        foreach ($items as $item):
            $specs = json_decode($item['specs_json'], true) ?: [];

            if ($is_spreadsheet): ?>
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
                                <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                <?= UI::csrf_field() ?>
                                <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php else:
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
                    data-brand="<?= htmlspecialchars($item['brand']) ?>" data-model="<?= htmlspecialchars($item['model']) ?>"
                    data-price="<?= htmlspecialchars($item['price'] ?? '0.00') ?>" data-created-date="<?= $created_date_only ?>"
                    data-created-time="<?= $created_time_only ?>" data-specs='<?= htmlspecialchars($item['specs_json'], ENT_QUOTES) ?>'
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
                                    <span class="battery-badge <?= empty($specs['battery']) ? 'missing' : '' ?>" title="Battery Status">
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
                            <button type="button" class="row-action-btn btn-edit" onclick='editWarehouseItem(<?= json_encode($item) ?>)'
                                title="Edit Entry">📝</button>
                            <button type="button" class="row-action-btn btn-label"
                                onclick="downloadWarehouseLabel(<?= (int) $item['id'] ?>, this)"
                                title="Generate & Download Label">🏷️</button>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="action" value="delete_inventory">
                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
                                <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
                                <?= UI::csrf_field() ?>
                                <button type="submit" class="row-action-btn btn-delete" title="Delete Entry">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($is_spreadsheet): ?>
            <!-- Permanent blank row at the bottom in spreadsheet AJAX response -->
            <tr class="summary-row new-blank-row" data-id="new">
                <?php if ($show_location_col): ?>
                    <td class="editable-cell" data-field="location_code">
                        <input type="text" class="cell-input text-center" list="zone-shelves-list" placeholder="Shelf..." value="<?= htmlspecialchars($default_shelf ?? '') ?>" style="font-weight: 800; color: #2563eb;" title="Intake Shelf">
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
        <?php endif; ?>
    <?php endif;
    $table_html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'inventory-list' => $table_html
    ]);
    exit();
}
