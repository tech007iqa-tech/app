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

<div class="trend-card" style="margin-bottom: 24px;">
    <!-- Section Header Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; font-size: 1.25rem; margin: 0;">💵 Live Pricing Matrix Reference (B2B Untested)</h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; margin-bottom: 0;">
                Directly edit base prices below. Updates dynamically apply to incoming inventory CSV imports.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn-main" onclick="openAddMatrixCategoryModal()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #8b5cf6; box-shadow: none;">
                + Add Category Table
            </button>
        </div>
    </div>

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
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" onclick="openAddMatrixRowModal('<?= htmlspecialchars(addslashes($cat)) ?>')" style="padding: 4px 10px; font-size: 0.78rem; border-radius: 14px; background: var(--bg-surface-2); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; cursor: pointer;">
                        + Add Row
                    </button>
                    <?php if ($is_admin && !in_array($cat, ['Regular', 'Apple', 'Rugged', 'Microsoft', 'Chromebook', 'RAM', 'Storage'])): ?>
                        <button type="button" onclick="deleteMatrixCategory('<?= htmlspecialchars(addslashes($cat)) ?>')" title="Delete Table" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem;">
                            🗑️ Delete Table
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="trends-table-container">
                <table class="trends-table">
                    <thead>
                        <tr style="background: #0f172a; color: white;">
                            <th style="min-width: 200px;"><?= htmlspecialchars($meta['col']) ?></th>
                            <?php foreach ($grades as $g): ?>
                                <th style="min-width: 150px; text-align: center;"><?= htmlspecialchars($g) ?></th>
                            <?php endforeach; ?>
                            <th style="width: 70px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="<?= count($grades) + 2 ?>" style="text-align: center; padding: 20px; color: var(--text-secondary);">No items defined in this table yet. Click "+ Add Row" to create one.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $gen): ?>
                                <?php $search_blob = strtolower($cat . ' ' . $gen); ?>
                                <tr data-search="<?= htmlspecialchars($search_blob) ?>">
                                    <td><strong><?= htmlspecialchars($gen) ?></strong></td>
                                    <?php foreach ($grades as $g): ?>
                                        <?php $price_val = isset($pricing_matrix[$cat][$gen][$g]) ? number_format($pricing_matrix[$cat][$gen][$g], 2, '.', '') : '0.00'; ?>
                                        <td>
                                            <div style="position: relative; display: flex; align-items: center;">
                                                <span style="position: absolute; left: 8px; font-weight: 800; color: var(--text-secondary);">$</span>
                                                <input type="number"
                                                       step="any"
                                                       class="matrix-cell-input input-price"
                                                       value="<?= $price_val ?>"
                                                       onchange="updateMatrixCell('<?= htmlspecialchars(addslashes($cat)) ?>', '<?= htmlspecialchars(addslashes($gen)) ?>', '<?= htmlspecialchars(addslashes($g)) ?>', this.value)">
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    <td style="text-align: center;">
                                        <?php if ($is_admin): ?>
                                            <button type="button" onclick="deleteMatrixRow('<?= htmlspecialchars(addslashes($cat)) ?>', '<?= htmlspecialchars(addslashes($gen)) ?>')" title="Delete Row" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 1rem;">✕</button>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 0.85rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add Matrix Row -->
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
            <input type="text" id="new-matrix-row-name" placeholder="e.g. i9-13th, A2485, 64GB DDR5, 4TB M.2" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeAddMatrixRowModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="button" onclick="submitAddMatrixRow()" style="padding: 8px 16px; border-radius: 8px; background: #3b82f6; border: none; color: white; cursor: pointer; font-weight: 700;">Add Row</button>
        </div>
    </div>
</div>

<!-- Modal: Add Matrix Category Table -->
<div id="add-matrix-cat-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-panel, #1e293b); color: var(--text-main, #fff); padding: 24px; border-radius: 12px; max-width: 420px; width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 800; margin-bottom: 15px;">➕ Add New Category Table</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">New Category Table Name</label>
            <input type="text" id="new-matrix-cat-name" placeholder="e.g. Workstations, Displays, GPUs" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">First Model / Row Name</label>
            <input type="text" id="new-matrix-cat-first-row" placeholder="e.g. Default / High-End" value="Default" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeAddMatrixCategoryModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="button" onclick="submitAddMatrixCategory()" style="padding: 8px 16px; border-radius: 8px; background: #8b5cf6; border: none; color: white; cursor: pointer; font-weight: 700;">Create Table</button>
        </div>
    </div>
</div>

<script>
function openAddMatrixRowModal(preselectCategory) {
    const sel = document.getElementById('new-matrix-row-cat');
    if (preselectCategory && sel) {
        sel.value = preselectCategory;
    }
    const modal = document.getElementById('add-matrix-row-modal');
    if (typeof bringModalToFront === 'function') bringModalToFront(modal);
    modal.style.display = 'flex';
}

function closeAddMatrixRowModal() {
    document.getElementById('add-matrix-row-modal').style.display = 'none';
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
    if (typeof bringModalToFront === 'function') bringModalToFront(modal);
    modal.style.display = 'flex';
}

function closeAddMatrixCategoryModal() {
    document.getElementById('add-matrix-cat-modal').style.display = 'none';
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
