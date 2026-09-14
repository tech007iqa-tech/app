<?php
/**
 * Spreadsheet Editable Table Partial
 * Main interactive spreadsheet view with in-place cell editing, keyboard navigation, sorting, and row consolidation.
 */
?>
<!-- Main Content: Spreadsheet Editable Table -->
<main class="order-main">
    <section class="summary-section card spreadsheet-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h3>Current Batch Summary</h3>
            <div style="display:flex; gap:10px; align-items:center;">
                <select id="summary-sort" onchange="sortSummary()"
                    style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; border: 1px solid var(--border-color); outline: none;">
                    <option value="default">Default Sort (Untested > Tested > Parts)</option>
                    <option value="original">Original Import Order</option>
                    <option value="oldest">Older First</option>
                    <option value="newest">Newest Added</option>
                    <option value="qty_desc">Quantity (High-Low)</option>
                    <option value="price_desc">Price (High-Low)</option>
                </select>
                <div class="search-box" style="max-width: 240px; width: 100%;">
                    <input type="text" id="summary-search" placeholder="Filter items..." onkeyup="filterSummary()"
                        style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; width: 100%;">
                </div>
                <button type="button" id="btn-consolidate-spreadsheet" class="btn-consolidate" onclick="consolidateOrderRows()" style="background: rgb(241, 245, 249); border: 1px solid rgb(203, 213, 225); padding: 4px 8px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 0.75rem; color: rgb(71, 85, 105); height: 34px;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'" title="Consolidate duplicate rows">
                    🔄 Consolidate
                </button>
            </div>
        </div>

        <!-- Injected csrf token and batch details for JS use -->
        <div id="batch-metadata" data-csrf="<?= htmlspecialchars(Security::getToken()) ?>"
            data-customer-id="<?= htmlspecialchars($current_customer) ?>"
            data-order-id="<?= htmlspecialchars($current_order) ?>" style="display:none;"></div>

        <div class="summary-table-wrapper spreadsheet-table-wrapper">
            <table class="summary-table spreadsheet-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Brand</th>
                        <th style="width: 12%;">Model</th>
                        <th style="width: 12%;">Series</th>
                        <th style="width: 12%;">CPU</th>
                        <th style="width: 18%;">Description</th>
                        <th style="width: 11%; text-align:center;">Qty</th>
                        <th style="width: 15%; text-align:right;">Price</th>
                        <th style="width: 15%;">Notes</th>
                        <th style="width: 8%; text-align:right;"></th>
                    </tr>
                </thead>
                <tbody id="summary-list">
                    <?php foreach ($items as $item): ?>
                        <tr class="summary-row" data-id="<?= $item['id'] ?>"
                            data-desc="<?= htmlspecialchars($item['description']) ?>"
                            data-qty="<?= $item['quantity'] ?>" data-price="<?= $item['unit_price'] ?>"
                            data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['series'])) ?>">
                            <td class="editable-cell" data-field="brand">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($item['brand']) ?>"
                                    list="brand-options" placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="model">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($item['model']) ?>"
                                    placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="series">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($item['series']) ?>"
                                    placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="cpu">
                                <input type="text" class="cell-input" value="<?= htmlspecialchars($item['cpu']) ?>"
                                    placeholder="...">
                            </td>
                            <td class="editable-cell" data-field="description">
                                <input type="text" class="cell-input"
                                    value="<?= htmlspecialchars($item['description']) ?>" placeholder="...">
                            </td>
                            <td class="editable-cell numeric" data-field="quantity" style="text-align:center;">
                                <input type="number" step="any" min="0" class="cell-input text-center font-bold"
                                    value="<?= $item['quantity'] ?>">
                            </td>
                            <td class="editable-cell numeric" data-field="unit_price" style="text-align:right;">
                                <input type="number" step="0.01" class="cell-input text-right"
                                    value="<?= number_format($item['unit_price'], 2, '.', '') ?>">
                            </td>
                            <td class="editable-cell" data-field="notes">
                                <input type="text" class="cell-input"
                                    value="<?= htmlspecialchars($item['notes'] ?? '') ?>" placeholder="...">
                            </td>
                            <td style="text-align:right;">
                                <div class="action-buttons">
                                    <button type="button" class="btn-clone-row" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px;" title="Clone Row">➕</button>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Remove this item?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                        <?= UI::csrf_field() ?>
                                        <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Permanent Blank entry row at the bottom for quick appending -->
                    <tr class="summary-row new-blank-row" data-id="new">
                        <td class="editable-cell" data-field="brand">
                            <input type="text" class="cell-input" list="brand-options" placeholder="Brand...">
                        </td>
                        <td class="editable-cell" data-field="model">
                            <input type="text" class="cell-input" placeholder="Model...">
                        </td>
                        <td class="editable-cell" data-field="series">
                            <input type="text" class="cell-input" placeholder="Series...">
                        </td>
                        <td class="editable-cell" data-field="cpu">
                            <input type="text" class="cell-input" placeholder="CPU...">
                        </td>
                        <td class="editable-cell" data-field="description">
                            <input type="text" class="cell-input" placeholder="Desc...">
                        </td>
                        <td class="editable-cell numeric" data-field="quantity" style="text-align:center;">
                            <input type="number" step="any" min="0" class="cell-input text-center font-bold"
                                placeholder="Qty">
                        </td>
                        <td class="editable-cell numeric" data-field="unit_price" style="text-align:right;">
                            <input type="number" step="0.01" class="cell-input text-right" placeholder="Price">
                        </td>
                        <td class="editable-cell" data-field="notes">
                            <input type="text" class="cell-input" placeholder="Notes...">
                        </td>
                        <td style="text-align:right;">
                            <div class="action-buttons">
                                <button type="button" class="btn-add-row-indicator" style="background: none; border: none; font-size: 1rem; opacity: 0.3;">➕</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
