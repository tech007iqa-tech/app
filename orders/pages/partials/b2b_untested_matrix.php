<?php
/**
 * Dynamic B2B Untested Pricing Matrix Reference Partial Component
 * Dynamically rendered inside orders/pages/trends.php under tab-matrix.
 */

// Category metadata maps default titles and column labels
$category_metadata = [
    'Regular'    => ['title' => '💻 Regular Laptops Pricing', 'col' => 'CPU Generation', 'grades' => ['Untested', 'Parts', 'C Grade']],
    'Apple'      => ['title' => '🍏 Apple Devices Pricing', 'col' => 'Model', 'grades' => ['Tested', 'Untested', 'For Parts']],
    'Rugged'     => ['title' => '🏔️ Rugged Devices Pricing', 'col' => 'CPU Generation', 'grades' => ['Untested Complete', 'Untested Parts', 'Tested Complete', 'Tested No Battery']],
    'Microsoft'  => ['title' => '💻 Microsoft Surface Devices', 'col' => 'Model / SKU Specification', 'grades' => ['Tested', 'Untested', 'For Parts']],
    'Chromebook' => ['title' => '🔌 Chromebooks Pricing', 'col' => 'Brand / Model', 'grades' => ['Untested Lot', 'Tested - Clean (A/B)']],
    'Gaming'     => ['title' => '🎮 Gaming Laptops & PCs', 'col' => 'Category / Spec', 'grades' => ['Untested', 'Parts', 'C Grade']],
    'RAM'        => ['title' => '🧠 Memory (RAM) Pricing', 'col' => 'Specification', 'grades' => ['Untested', 'Tested', 'C Grade']],
    'Storage'    => ['title' => '💾 Storage (SSD) Pricing', 'col' => 'Capacity', 'grades' => ['Untested', 'Tested', 'C Grade']]
];

// Fallback ordering for categories
$category_order = ['Regular', 'Apple', 'Rugged', 'Microsoft', 'Chromebook', 'Gaming', 'RAM', 'Storage'];

// Append any dynamically created categories from database
if (isset($matrix_category_items) && is_array($matrix_category_items)) {
    foreach (array_keys($matrix_category_items) as $cat_key) {
        if (!in_array($cat_key, $category_order)) {
            $category_order[] = $cat_key;
        }
    }
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
?>

<div class="trend-card matrix-container" id="b2b-matrix-root" data-matrix-mode="view" style="margin-bottom: 24px;">
    <!-- Section Header Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; font-size: 1.25rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                💵 <?= $is_admin ? 'Live Pricing Matrix Reference (B2B Untested)' : 'Official B2B Untested Pricing Matrix' ?>
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; margin-bottom: 0;">
                <?= $is_admin 
                    ? 'Manage baseline pricing rules for untested lots and intake. Click any price to quick-edit or toggle Bulk Edit Mode.' 
                    : 'Official reference pricing for untested B2B intake and inventory valuation guidelines.' ?>
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if ($is_admin): ?>
                <!-- Admin Mode Switcher -->
                <div class="matrix-mode-toggle-group" title="Toggle editing view mode">
                    <button type="button" id="btn-matrix-mode-view" class="matrix-mode-btn active" onclick="setMatrixMode('view')">
                        🔒 Reference Mode
                    </button>
                    <button type="button" id="btn-matrix-mode-edit" class="matrix-mode-btn" onclick="setMatrixMode('edit')">
                        ✏️ Bulk Edit Mode
                    </button>
                </div>

                <button type="button" class="btn-main" onclick="openAddMatrixCategoryModal()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #8b5cf6; box-shadow: none;">
                    + Add Category Table
                </button>
            <?php else: ?>
                <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    🔒 Official Reference (Read-Only)
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
        <!-- Admin Bulk Edit Mode Active Info Banner -->
        <div id="matrix-edit-alert" style="display: none; background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 10px; padding: 10px 16px; margin-bottom: 22px; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: #a78bfa; font-weight: 600;">
                <span style="font-size: 1.15rem;">✏️</span>
                <span>
                    <strong>Bulk Edit Mode Active:</strong> Edit prices directly in cells. Press <kbd style="background: var(--bg-surface-2); border: 1px solid var(--border-color); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.8rem; color: var(--text-main);">Enter</kbd> to save & advance down the column. Press <kbd style="background: var(--bg-surface-2); border: 1px solid var(--border-color); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.8rem; color: var(--text-main);">Tab</kbd> for next cell.
                </span>
            </div>
            <button type="button" onclick="setMatrixMode('view')" style="background: #8b5cf6; color: white; border: none; padding: 5px 14px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
                ✓ Done Editing
            </button>
        </div>
    <?php endif; ?>

    <!-- Render Dynamic Pricing Tables for Each Category -->
    <?php foreach ($category_order as $cat): ?>
        <?php
        $items = $matrix_category_items[$cat] ?? [];
        if (empty($items) && !$is_admin) continue; // Skip empty categories for non-admins if no items

        $meta = $category_metadata[$cat] ?? [
            'title' => '🏷️ ' . htmlspecialchars($cat) . ' Pricing',
            'col'   => 'Specification / Model',
            'grades' => ['Untested', 'Tested', 'C Grade']
        ];
        $grades = $meta['grades'];
        ?>
        <div class="matrix-category-block" data-category="<?= htmlspecialchars($cat) ?>" style="margin-bottom: 35px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="font-weight: 800; font-size: 1rem; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                    <?= $meta['title'] ?>
                </h3>
                <?php if ($is_admin): ?>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" onclick="openAddMatrixRowModal('<?= htmlspecialchars(addslashes($cat)) ?>')" style="padding: 4px 10px; font-size: 0.78rem; border-radius: 14px; background: var(--bg-surface-2); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; cursor: pointer;">
                            + Add Row
                        </button>
                        <?php if (!in_array($cat, ['Regular', 'Apple', 'Rugged', 'Microsoft', 'Chromebook', 'RAM', 'Storage'])): ?>
                            <button type="button" onclick="deleteMatrixCategory('<?= htmlspecialchars(addslashes($cat)) ?>')" title="Delete Table" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;">
                                🗑️ Delete Table
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="trends-table-container">
                <table class="trends-table matrix-table">
                    <thead>
                        <tr style="background: #0f172a; color: white;">
                            <th style="min-width: 200px;"><?= htmlspecialchars($meta['col']) ?></th>
                            <?php foreach ($grades as $g): ?>
                                <th style="min-width: 140px; text-align: center;"><?= htmlspecialchars($g) ?></th>
                            <?php endforeach; ?>
                            <?php if ($is_admin): ?>
                                <th style="width: 70px; text-align: center;">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="<?= count($grades) + ($is_admin ? 2 : 1) ?>" style="text-align: center; padding: 20px; color: var(--text-secondary);">
                                    No items defined in this table yet.<?= $is_admin ? ' Click "+ Add Row" to create one.' : '' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $rowIndex => $gen): ?>
                                <?php $search_blob = strtolower($cat . ' ' . $gen); ?>
                                <tr data-search="<?= htmlspecialchars($search_blob) ?>" data-row-index="<?= $rowIndex ?>" data-category="<?= htmlspecialchars($cat) ?>">
                                    <td><strong><?= htmlspecialchars($gen) ?></strong></td>
                                    <?php foreach ($grades as $colIndex => $g): ?>
                                        <?php 
                                            $num_val = isset($pricing_matrix[$cat][$gen][$g]) ? (float)$pricing_matrix[$cat][$gen][$g] : 0.0;
                                            $price_val = number_format($num_val, 2, '.', '');
                                            $has_price = $num_val > 0;
                                        ?>
                                        <td style="text-align: center;" 
                                            class="matrix-cell-td"
                                            data-category="<?= htmlspecialchars($cat) ?>"
                                            data-cpu-gen="<?= htmlspecialchars($gen) ?>"
                                            data-grade="<?= htmlspecialchars($g) ?>"
                                            data-col-index="<?= $colIndex ?>">
                                            
                                            <?php if (!$is_admin): ?>
                                                <!-- Regular Non-Admin User: Clean, Formatted Read-Only Pill -->
                                                <div class="matrix-price-pill <?= $has_price ? 'has-price' : 'zero-price' ?>" title="<?= htmlspecialchars($gen . ' • ' . $g . ': $' . $price_val) ?>">
                                                    <span class="matrix-currency">$</span>
                                                    <span class="matrix-val"><?= $price_val ?></span>
                                                </div>
                                            <?php else: ?>
                                                <!-- Elevated Admin: Interactive Cell with Click-to-Edit & Bulk Edit Input -->
                                                <div class="matrix-admin-cell-wrapper">
                                                    <!-- 1. View / Click-to-Edit Pill -->
                                                    <div class="matrix-price-pill admin-clickable <?= $has_price ? 'has-price' : 'zero-price' ?>" 
                                                         onclick="startCellInlineEdit(this)"
                                                         title="Click to edit price ($<?= $price_val ?>)">
                                                        <span class="matrix-currency">$</span>
                                                        <span class="matrix-val"><?= $price_val ?></span>
                                                        <span class="matrix-edit-hint" style="opacity: 0; font-size: 0.72rem; margin-left: 4px; transition: opacity 0.15s ease;">✏️</span>
                                                    </div>

                                                    <!-- 2. Active Input (Visible in Bulk Edit Mode or during Inline Edit) -->
                                                    <div class="matrix-input-container">
                                                        <span style="position: absolute; left: 8px; font-weight: 800; color: var(--text-secondary); pointer-events: none; z-index: 1;">$</span>
                                                        <input type="number"
                                                               step="any"
                                                               class="matrix-cell-input input-price admin-matrix-input"
                                                               value="<?= $price_val ?>"
                                                               data-original="<?= $price_val ?>"
                                                               
                                                               onkeydown="handleMatrixKeyNav(event, this)"
                                                               onblur="handleMatrixInputBlur(this)"
                                                               data-category="<?= htmlspecialchars($cat) ?>"
                                                               data-cpu-gen="<?= htmlspecialchars($gen) ?>"
                                                               data-grade="<?= htmlspecialchars($g) ?>"
                                                               data-col-index="<?= $colIndex ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <?php if ($is_admin): ?>
                                        <td style="text-align: center;">
                                            <button type="button" onclick="deleteMatrixRow('<?= htmlspecialchars(addslashes($cat)) ?>', '<?= htmlspecialchars(addslashes($gen)) ?>')" title="Delete Row" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 1rem; padding: 4px 8px; border-radius: 4px;">✕</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($is_admin): ?>
<!-- Modal: Add Matrix Row (Admin Only) -->
<div id="add-matrix-row-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-panel, #1e293b); color: var(--text-main, #fff); padding: 24px; border-radius: 12px; max-width: 420px; width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 800; margin-bottom: 15px;">➕ Add Row to B2B Pricing Matrix</h3>
        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">Category Table</label>
            <select id="new-matrix-row-cat" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
                <?php foreach ($category_order as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">CPU Gen / Model / Specification Name</label>
            <input type="text" id="new-matrix-row-name" placeholder="e.g. i9-13th, A2485, 64GB DDR5, 4TB M.2" onkeydown="if(event.key==='Enter') submitAddMatrixRow();" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeAddMatrixRowModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="button" onclick="submitAddMatrixRow()" style="padding: 8px 16px; border-radius: 8px; background: #3b82f6; border: none; color: white; cursor: pointer; font-weight: 700;">Add Row</button>
        </div>
    </div>
</div>

<!-- Modal: Add Matrix Category Table (Admin Only) -->
<div id="add-matrix-cat-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-panel, #1e293b); color: var(--text-main, #fff); padding: 24px; border-radius: 12px; max-width: 420px; width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 800; margin-bottom: 15px;">➕ Add New Category Table</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">New Category Table Name</label>
            <input type="text" id="new-matrix-cat-name" placeholder="e.g. Workstations, Displays, GPUs" onkeydown="if(event.key==='Enter') submitAddMatrixCategory();" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">First Model / Row Name</label>
            <input type="text" id="new-matrix-cat-first-row" placeholder="e.g. Default / High-End" value="Default" onkeydown="if(event.key==='Enter') submitAddMatrixCategory();" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeAddMatrixCategoryModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="button" onclick="submitAddMatrixCategory()" style="padding: 8px 16px; border-radius: 8px; background: #8b5cf6; border: none; color: white; cursor: pointer; font-weight: 700;">Create Table</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Prevent accidental mouse wheel value altering on focused number inputs while scrolling (passive & non-blocking)
if (!window.__matrixWheelListenerAttached) {
    window.__matrixWheelListenerAttached = true;
    document.addEventListener('wheel', function() {
        if (document.activeElement && (document.activeElement.classList.contains('matrix-cell-input') || document.activeElement.type === 'number')) {
            document.activeElement.blur();
        }
    }, { passive: true });
}

/**
 * Toggle Matrix Display Mode (view vs edit) for Admin
 */
function setMatrixMode(mode) {
    const root = document.getElementById('b2b-matrix-root');
    const btnView = document.getElementById('btn-matrix-mode-view');
    const btnEdit = document.getElementById('btn-matrix-mode-edit');
    const alertBanner = document.getElementById('matrix-edit-alert');

    if (!root) return;

    if (mode === 'edit') {
        root.setAttribute('data-matrix-mode', 'edit');
        if (btnEdit) btnEdit.classList.add('active');
        if (btnView) btnView.classList.remove('active');
        if (alertBanner) alertBanner.style.display = 'flex';

        // Focus first visible input for convenience
        const firstVisibleInput = root.querySelector('tr:not([style*="display: none"]) .admin-matrix-input');
        if (firstVisibleInput) {
            setTimeout(() => {
                firstVisibleInput.focus();
                firstVisibleInput.select();
            }, 50);
        }
    } else {
        root.setAttribute('data-matrix-mode', 'view');
        if (btnView) btnView.classList.add('active');
        if (btnEdit) btnEdit.classList.remove('active');
        if (alertBanner) alertBanner.style.display = 'none';

        // Clean up any remaining single-cell active states
        document.querySelectorAll('.cell-editing-active').forEach(el => el.classList.remove('cell-editing-active'));
    }
}

/**
 * Start Single-Cell Inline Edit in Reference Mode
 */
function startCellInlineEdit(pillElement) {
    const root = document.getElementById('b2b-matrix-root');
    if (!root || root.getAttribute('data-matrix-mode') === 'edit') return;

    const wrapper = pillElement.closest('.matrix-admin-cell-wrapper');
    if (!wrapper) return;

    // Remove active editing from other cells first
    document.querySelectorAll('.cell-editing-active').forEach(el => {
        if (el !== wrapper) {
            const input = el.querySelector('.admin-matrix-input');
            if (input) finishCellInlineEdit(input, true);
        }
    });

    wrapper.classList.add('cell-editing-active');
    const input = wrapper.querySelector('.admin-matrix-input');
    if (input) {
        input.focus();
        input.select();
    }
}

/**
 * Finish Single-Cell Inline Edit
 */
function finishCellInlineEdit(input, shouldSave) {
    const wrapper = input.closest('.matrix-admin-cell-wrapper');
    if (!wrapper) return;

    wrapper.classList.remove('cell-editing-active');

    if (shouldSave) {
        commitMatrixInput(input);
    } else {
        // Revert to original
        input.value = input.dataset.original || '0.00';
    }
}

/**
 * Commit value, trigger save if changed, and update pill display
 */
function commitMatrixInput(input) {
    const rawVal = parseFloat(input.value);
    const sanitizedVal = isNaN(rawVal) ? 0.00 : Math.max(0, rawVal);
    const formattedVal = sanitizedVal.toFixed(2);
    const originalVal = (parseFloat(input.dataset.original) || 0.00).toFixed(2);

    input.value = formattedVal;

    // Update pill text & styling in the wrapper
    const wrapper = input.closest('.matrix-admin-cell-wrapper');
    if (wrapper) {
        const pill = wrapper.querySelector('.matrix-price-pill');
        if (pill) {
            const valSpan = pill.querySelector('.matrix-val');
            if (valSpan) valSpan.textContent = formattedVal;

            if (sanitizedVal > 0) {
                pill.classList.add('has-price');
                pill.classList.remove('zero-price');
            } else {
                pill.classList.remove('has-price');
                pill.classList.add('zero-price');
            }
            pill.title = `Click to edit price ($${formattedVal})`;
        }
    }

    if (formattedVal !== originalVal) {
        input.dataset.original = formattedVal;
        const category = input.dataset.category;
        const cpuGen = input.dataset.cpuGen;
        const grade = input.dataset.grade;
        const pulseTarget = wrapper ? wrapper.querySelector('.matrix-price-pill') || input : input;

        if (typeof updateMatrixCell === 'function') {
            updateMatrixCell(category, cpuGen, grade, sanitizedVal, pulseTarget);
        }
    }
}

/**
 * Input blur handler
 */
function handleMatrixInputBlur(input) {
    const root = document.getElementById('b2b-matrix-root');
    const isBulkEdit = root && root.getAttribute('data-matrix-mode') === 'edit';

    if (isBulkEdit) {
        commitMatrixInput(input);
    } else {
        finishCellInlineEdit(input, true);
    }
}

/**
 * Keyboard Navigation & Shortcuts for Matrix Inputs
 */
function handleMatrixKeyNav(e, input) {
    const root = document.getElementById('b2b-matrix-root');
    const isBulkEdit = root && root.getAttribute('data-matrix-mode') === 'edit';

    if (e.key === 'Enter') {
        e.preventDefault();
        commitMatrixInput(input);

        if (!isBulkEdit) {
            finishCellInlineEdit(input, true);
            return;
        }

        // In Bulk Edit Mode: Advance focus to the same column on the NEXT visible row
        const currentTd = input.closest('td');
        const currentTr = input.closest('tr');
        if (!currentTd || !currentTr) return;

        const colIndex = input.dataset.colIndex;
        let nextTr = currentTr.nextElementSibling;

        // Skip non-data or hidden search rows
        while (nextTr && (nextTr.style.display === 'none' || !nextTr.hasAttribute('data-row-index'))) {
            nextTr = nextTr.nextElementSibling;
        }

        if (nextTr) {
            const nextInput = nextTr.querySelector(`.admin-matrix-input[data-col-index="${colIndex}"]`);
            if (nextInput) {
                nextInput.focus();
                nextInput.select();
            }
        } else {
            // Jump to the first visible row of the NEXT category table
            const currentBlock = currentTr.closest('.matrix-category-block');
            let nextBlock = currentBlock ? currentBlock.nextElementSibling : null;
            while (nextBlock && !nextBlock.classList.contains('matrix-category-block')) {
                nextBlock = nextBlock.nextElementSibling;
            }
            if (nextBlock) {
                const nextBlockFirstInput = nextBlock.querySelector(`tr:not([style*="display: none"]) .admin-matrix-input[data-col-index="${colIndex}"]`);
                if (nextBlockFirstInput) {
                    nextBlockFirstInput.focus();
                    nextBlockFirstInput.select();
                }
            }
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        if (!isBulkEdit) {
            finishCellInlineEdit(input, false);
        } else {
            input.value = input.dataset.original || '0.00';
            input.blur();
        }
    } else if (e.key === 'ArrowDown' && isBulkEdit) {
        // Navigate to the cell directly below
        const currentTr = input.closest('tr');
        const colIndex = input.dataset.colIndex;
        let nextTr = currentTr ? currentTr.nextElementSibling : null;
        while (nextTr && (nextTr.style.display === 'none' || !nextTr.hasAttribute('data-row-index'))) {
            nextTr = nextTr.nextElementSibling;
        }
        if (nextTr) {
            const target = nextTr.querySelector(`.admin-matrix-input[data-col-index="${colIndex}"]`);
            if (target) {
                e.preventDefault();
                commitMatrixInput(input);
                target.focus();
                target.select();
            }
        }
    } else if (e.key === 'ArrowUp' && isBulkEdit) {
        // Navigate to the cell directly above
        const currentTr = input.closest('tr');
        const colIndex = input.dataset.colIndex;
        let prevTr = currentTr ? currentTr.previousElementSibling : null;
        while (prevTr && (prevTr.style.display === 'none' || !prevTr.hasAttribute('data-row-index'))) {
            prevTr = prevTr.previousElementSibling;
        }
        if (prevTr) {
            const target = prevTr.querySelector(`.admin-matrix-input[data-col-index="${colIndex}"]`);
            if (target) {
                e.preventDefault();
                commitMatrixInput(input);
                target.focus();
                target.select();
            }
        }
    }
}

// Modal & Table Mutation Actions (Admin Only)
function openAddMatrixRowModal(preselectCategory) {
    const sel = document.getElementById('new-matrix-row-cat');
    if (preselectCategory && sel) {
        sel.value = preselectCategory;
    }
    const modal = document.getElementById('add-matrix-row-modal');
    if (modal) {
        if (typeof bringModalToFront === 'function') bringModalToFront(modal);
        modal.style.display = 'flex';
        const nameInput = document.getElementById('new-matrix-row-name');
        if (nameInput) {
            setTimeout(() => { nameInput.value = ''; nameInput.focus(); }, 50);
        }
    }
}

function closeAddMatrixRowModal() {
    const modal = document.getElementById('add-matrix-row-modal');
    if (modal) modal.style.display = 'none';
}

function submitAddMatrixRow() {
    const category = document.getElementById('new-matrix-row-cat').value;
    const cpuGen = document.getElementById('new-matrix-row-name').value.trim();

    if (!category || !cpuGen) {
        alert('Please enter a valid row name.');
        return;
    }

    fetch('index.php?view=trends&action=add_matrix_row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category: category, cpu_gen: cpuGen })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error adding row: ' + (data.error || 'Failed'));
        }
    });
}

function deleteMatrixRow(category, cpuGen) {
    if (!confirm(`Are you sure you want to delete row "${cpuGen}" from "${category}"?`)) return;

    fetch('index.php?view=trends&action=delete_matrix_row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category: category, cpu_gen: cpuGen })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error deleting row: ' + (data.error || 'Failed'));
        }
    });
}

function openAddMatrixCategoryModal() {
    const modal = document.getElementById('add-matrix-cat-modal');
    if (modal) {
        if (typeof bringModalToFront === 'function') bringModalToFront(modal);
        modal.style.display = 'flex';
        const catInput = document.getElementById('new-matrix-cat-name');
        if (catInput) {
            setTimeout(() => { catInput.value = ''; catInput.focus(); }, 50);
        }
    }
}

function closeAddMatrixCategoryModal() {
    const modal = document.getElementById('add-matrix-cat-modal');
    if (modal) modal.style.display = 'none';
}

function submitAddMatrixCategory() {
    const category = document.getElementById('new-matrix-cat-name').value.trim();
    const firstRow = document.getElementById('new-matrix-cat-first-row').value.trim() || 'Default';

    if (!category) {
        alert('Please enter a category table name.');
        return;
    }

    fetch('index.php?view=trends&action=add_matrix_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category: category, cpu_gen: firstRow })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error creating table: ' + (data.error || 'Failed'));
        }
    });
}

function deleteMatrixCategory(category) {
    if (!confirm(`Are you sure you want to delete category table "${category}" and all its rows?`)) return;

    fetch('index.php?view=trends&action=delete_matrix_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category: category })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error deleting category table: ' + (data.error || 'Failed'));
        }
    });
}
</script>
