<?php
/**
 * Warehouse Import Verification & Sanitization Report Partial
 * Renders target area / location override controls, stats counters, and interactive preview table.
 */
?>
<!-- PREVIEW MODE & SANITIZATION REVIEW -->
<div style="background: white; border-radius: 24px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: var(--shadow-sm); margin-bottom: 40px;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 900; font-size: 1.4rem; color: var(--text-main);">Verification & Sanitization Report</h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">Please review the parsed results and validation status before importing.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="../sampleWHdata/intake.pdf" target="_blank" class="btn-main" style="background: white; color: #166534; border: 1px solid #bbf7d0; box-shadow: var(--shadow-sm); font-size: 0.9rem; padding: 10px 18px; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 800;">
                🖨️ Print Intake Form
            </a>
            <form action="index.php?view=import_warehouse" method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="cancel_import">
                <button type="submit" class="btn-main" style="background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; box-shadow: none; font-size: 0.9rem; padding: 10px 20px; border-radius: 12px;">
                    ❌ Cancel Import
                </button>
            </form>
        </div>
    </div>

    <div id="confirm-import-container" style="display: <?= $accepted > 0 ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 25px; margin-bottom: 30px;">
        <form action="index.php?view=import_warehouse" method="POST" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 25px; align-items: end;">
            <input type="hidden" name="action" value="confirm_import">

            <!-- Select Target Area / Working Zone -->
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label for="override_zone_select" style="font-weight: 800; font-size: 0.9rem; color: #475569;">1. Target Area (Zone Override)</label>
                <div style="display: flex; gap: 10px; width: 100%;">
                    <select name="override_zone_select" id="override_zone_select" style="flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; background: white; font-size: 0.95rem; outline: none;" onchange="onZoneChange()">
                        <option value="" selected>-- Auto-Detect Zone per Location (Default) --</option>
                        <option value="__NEW_ZONE__">+ Create New Zone...</option>
                        <?php foreach ($working_zones as $wz): ?>
                            <option value="<?= htmlspecialchars($wz) ?>">
                                <?= htmlspecialchars($wz) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="override_zone_custom" id="override_zone_custom" placeholder="New Zone Name" style="display: none; width: 140px; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; font-size: 0.95rem; text-transform: uppercase;">
                </div>
            </div>

            <!-- Select Location (Filtered by chosen Working Zone) -->
            <div id="override-location-wrapper" style="display: flex; flex-direction: column; gap: 8px;">
                <label for="override_location_select" style="font-weight: 800; font-size: 0.9rem; color: #475569;">2. Shelf / Layer Code</label>
                <div style="display: flex; gap: 10px; width: 100%;">
                    <select name="override_location_select" id="override_location_select" style="flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; background: white; font-size: 0.95rem; outline: none;" onchange="toggleCustomLocationInput()">
                        <option value="">📄 Keep Row-Level Locations (Default)</option>
                    </select>
                    <input type="text" name="override_location_custom" id="override_location_custom" placeholder="New Shelf/Box" style="display: none; width: 140px; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; font-size: 0.95rem; text-transform: uppercase;">
                </div>
            </div>

            <!-- Submit Import Button -->
            <div>
                <button type="submit" class="btn-main" style="background: var(--accent-color); color: white; height: 48px; padding: 0 30px; font-size: 1rem; border-radius: 12px; font-weight: 900; box-shadow: 0 4px 12px rgba(140, 198, 63, 0.25);">
                    🚀 Confirm Import (<?= $accepted ?> items)
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; text-align: center;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total Rows</div>
            <div id="stats-total" style="font-size: 2rem; font-weight: 900; color: #1e293b;"><?= $total ?></div>
        </div>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 16px; text-align: center;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #065f46; text-transform: uppercase;">Passed / Accepted</div>
            <div id="stats-accepted" style="font-size: 2rem; font-weight: 900; color: #059669;"><?= $accepted ?></div>
        </div>
        <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 20px; border-radius: 16px; text-align: center;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #991b1b; text-transform: uppercase;">Rejected / Invalid</div>
            <div id="stats-rejected" style="font-size: 2rem; font-weight: 900; color: #dc2626;"><?= $rejected ?></div>
        </div>
    </div>

    <!-- Style overrides for warning highlights -->
    <style>
        .cell-input.warning-empty {
            background-color: #fffbeb !important;
            border: 1px dashed #f59e0b !important;
            color: #b45309 !important;
        }
        .cell-input.warning-empty::placeholder {
            color: #d97706 !important;
            opacity: 0.6;
        }
        .spreadsheet-table td input.cell-input {
            background: transparent;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .spreadsheet-table td input.cell-input:hover {
            border-color: #cbd5e1;
            background: #fff;
        }
        .spreadsheet-table td input.cell-input:focus {
            outline: none;
            background: #fff;
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
    </style>

    <!-- Preview Table -->
    <div class="spreadsheet-table-wrapper" style="max-height: 600px; overflow-y: auto;">
        <table class="spreadsheet-table" style="table-layout: auto;">
            <thead>
                <tr>
                    <th style="width: 100px;">Status</th>
                    <th>Original Item Info</th>
                    <th style="width: 90px;">Location</th>
                    <th style="width: 100px;">Brand</th>
                    <th style="width: 120px;">Model</th>
                    <th style="width: 90px;">Series</th>
                    <th style="width: 80px; position: relative; cursor: pointer; user-select: none;" onclick="toggleCpuBulkMenu(event)">
                        CPU <span style="font-size: 0.65rem; opacity: 0.6;">▼</span>
                        <div id="cpu-bulk-menu" style="text-transform: none; display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background: white; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); z-index: 100; min-width: 140px; padding: 6px 0; margin-top: 5px;">
                            <div style="padding: 6px 12px; font-size: 0.7rem; color: #64748b; font-weight: 800; border-bottom: 1px solid #f1f5f9; text-align: center;">Bulk Default CPU</div>
                            <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i3')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i3</a>
                            <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i5')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i5</a>
                            <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i7')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i7</a>
                            <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i9')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i9</a>
                        </div>
                    </th>
                    <th style="width: 80px;">Gen</th>
                    <th style="width: 80px;">RAM</th>
                    <th style="width: 90px;">Storage</th>
                    <th style="width: 80px;">Battery</th>
                    <th style="width: 100px;">Condition</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 70px;">Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($display_rows as $rowIndex => $row):
                    $isAccepted = $row['status'] === 'Accept';
                ?>
                    <tr style="background-color: <?= $isAccepted ? 'rgba(236, 253, 245, 0.4)' : 'rgba(254, 242, 242, 0.6)' ?>;">
                        <td style="padding: 10px; font-weight: 800; text-align: center;">
                            <?php if ($isAccepted): ?>
                                <span style="color: #059669; background: #d1fae5; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;">Accept</span>
                            <?php else: ?>
                                <span style="color: #dc2626; background: #fee2e2; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;" title="<?= htmlspecialchars(implode(', ', $row['errors'])) ?>">Reject ⚠️</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; font-size: 0.8rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <strong><?= htmlspecialchars($row['item']) ?></strong>
                            <?php if (!empty($row['errors'])): ?>
                                <div class="row-error-list" style="color: #b91c1c; font-size: 0.7rem; font-weight: 700; margin-top: 4px;">
                                    <?= htmlspecialchars(implode(', ', $row['errors'])) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="location" style="padding: 5px; text-align: center;">
                            <input type="text" class="cell-input <?= empty(trim($row['location'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['location']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; text-align: center; text-transform: uppercase;">
                            <?php
                            $rowLoc = strtoupper(trim($row['location']));
                            $rowZone = 'General';
                            if (preg_match('/^(?:Zone\s*[-_]?)?([a-zA-Z]+)/iu', $rowLoc, $m)) {
                                $rowZone = 'Zone ' . strtoupper($m[1]);
                            }
                            ?>
                            <?php if (!empty($rowLoc)): ?>
                                <div style="margin-top: 3px;">
                                    <span style="display: inline-block; font-size: 0.68rem; font-weight: 800; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 6px; border-radius: 6px;" title="Auto-mapped working zone">
                                        📍 <?= htmlspecialchars($rowZone) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="editable-cell" data-field="brand" style="padding: 5px;">
                            <input type="text" class="cell-input <?= (empty(trim($row['parsed']['brand'])) || $row['parsed']['brand'] === 'Unknown') ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['brand']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                        </td>
                        <td class="editable-cell" data-field="model" style="padding: 5px;">
                            <input type="text" class="cell-input <?= (empty(trim($row['parsed']['model'])) || $row['parsed']['model'] === 'Unknown') ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['model']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                        </td>
                        <td class="editable-cell" data-field="series" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['series'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['series']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                        </td>
                        <td class="editable-cell" data-field="cpu" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['cpu'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['cpu']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="gen" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['gen'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['gen']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="ram" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['ram'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['ram']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="storage" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['storage'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['storage']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="battery" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['battery'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['battery']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="condition" style="padding: 5px;">
                            <input type="text" class="cell-input <?= empty(trim($row['parsed']['condition'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['condition']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                        </td>
                        <td class="editable-cell" data-field="price" style="padding: 5px;">
                            <input type="number" step="any" class="cell-input" value="<?= htmlspecialchars($row['parsed']['price']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: right; font-weight: 700;">
                        </td>
                        <td class="editable-cell" data-field="qty" style="padding: 5px;">
                            <input type="number" step="1" class="cell-input <?= ((int)$row['qty'] <= 0) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['qty']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; text-align: center;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- State data hydration for JavaScript -->
<script id="import-warehouse-state" type="application/json">
<?= json_encode([
    'zone_locations_map' => $zone_locations_map
]) ?>
</script>
