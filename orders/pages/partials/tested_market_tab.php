<?php
/**
 * Tested Market Pricing Reference Partial Component
 * Dynamically rendered inside orders/pages/trends.php under tab-tested.
 */
require_once __DIR__ . '/../../core/TestedMarketManager.php';

$tested_categories = TestedMarketManager::getCategories();
$active_cat_id = isset($_GET['tested_cat']) ? (int)$_GET['tested_cat'] : (!empty($tested_categories) ? $tested_categories[0]['id'] : 0);

$active_category = null;
foreach ($tested_categories as $cat) {
    if ($cat['id'] == $active_cat_id) {
        $active_category = $cat;
        break;
    }
}
if (!$active_category && !empty($tested_categories)) {
    $active_category = $tested_categories[0];
    $active_cat_id = $active_category['id'];
}

$rules = $active_category ? TestedMarketManager::getRulesByCategory($active_cat_id) : [];

// Compute metrics
$total_models = count($rules);
$total_price_sum = 0.0;
$total_st_sum = 0.0;
$max_price = 0.0;
$max_model = 'N/A';

foreach ($rules as $r) {
    $p = (float)$r['price'];
    $st = (float)$r['sale_through'];
    $total_price_sum += $p;
    $total_st_sum += $st;
    if ($p > $max_price) {
        $max_price = $p;
        $max_model = trim(($r['brand_series'] ?? '') . ' ' . ($r['model_number'] ?? ''));
    }
}

$avg_price = $total_models > 0 ? ($total_price_sum / $total_models) : 0.00;
$avg_st = $total_models > 0 ? (($total_st_sum / $total_models) * 100) : 0.0;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
?>

<div class="trend-card" style="margin-bottom: 24px;">
    <!-- Header Title & Description -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="font-weight: 800; font-size: 1.25rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                🎯 Tested Market Pricing Reference
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; margin-bottom: 0;">
                Live reference pricing based on market sales data. Tier formulas auto-calculate dynamically as base prices & sell-through rates change.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($is_admin): ?>
                <button type="button" class="btn-main" onclick="openAddCategoryModal()" style="padding: 8px 14px; font-size: 0.82rem; height: auto; border-radius: 20px; background: #8b5cf6; box-shadow: none;">
                    + Add Category Tab
                </button>
            <?php endif; ?>
            <button type="button" class="btn-main" onclick="openAddRuleModal()" style="padding: 8px 14px; font-size: 0.82rem; height: auto; border-radius: 20px; background: #3b82f6; box-shadow: none;">
                + Add Model Row
            </button>
        </div>
    </div>

    <!-- Category Sub-Navigation Pills -->
    <div class="tested-cat-pill-nav" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
        <?php foreach ($tested_categories as $cat): ?>
            <?php $is_active = ($cat['id'] == $active_cat_id); ?>
            <div style="display: inline-flex; align-items: center; gap: 4px;">
                <button type="button"
                        class="tab-btn <?= $is_active ? 'active' : '' ?>"
                        onclick="switchTestedCategory(<?= $cat['id'] ?>)"
                        style="padding: 8px 16px; font-size: 0.85rem; border-radius: 20px; border: 1px solid <?= $is_active ? 'var(--accent-color, #3b82f6)' : 'var(--border-color)' ?>; background: <?= $is_active ? 'var(--accent-color, #3b82f6)' : 'var(--bg-surface)' ?>; color: <?= $is_active ? '#ffffff' : 'var(--text-main)' ?>; font-weight: 700; cursor: pointer; transition: all 0.2s ease;">
                    <?= htmlspecialchars($cat['name']) ?>
                </button>
                <?php if ($is_admin && count($tested_categories) > 1 && $is_active): ?>
                    <button type="button" onclick="deleteTestedCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')" title="Delete Category Tab" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.9rem; padding: 2px 4px;">
                        🗑️
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tracked Models Summary Badge -->
    <div style="margin-bottom: 20px; display: inline-block;">
        <div style="background: var(--bg-surface-2); padding: 10px 18px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Tracked Models</span>
            <span style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);"><?= $total_models ?></span>
        </div>
    </div>

    <!-- Category Table -->
    <?php if ($active_category['layout_type'] === 'ram'): ?>
        <!-- RAM Layout Table -->
        <div class="trends-table-container">
            <table class="trends-table" id="table-tested-ram">
                <thead>
                    <tr style="background: #0f172a; color: white;">
                        <th style="width: 150px;">Category</th>
                        <th style="width: 220px;">Specification & QTY</th>
                        <th style="width: 140px;">Total Price</th>
                        <th style="width: 140px;">Sell-Through %</th>
                        <th style="width: 140px;">Per Unit Price</th>
                        <th style="width: 110px;">Date</th>
                        <th style="width: 70px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rules)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 25px; color: var(--text-secondary);">No RAM pricing rules found in this category.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rules as $r): ?>
                            <?php
                            $price = (float)$r['price'];
                            $st = (float)$r['sale_through'] * 100;
                            $qty = max(1, (int)$r['sold_count']);
                            $per_unit = $qty > 0 ? ($price / $qty) : 0.00;
                            $search_blob = strtolower(($r['brand_series'] ?? '') . ' ' . ($r['model_number'] ?? ''));
                            ?>
                            <tr data-rule-id="<?= $r['id'] ?>" data-search="<?= htmlspecialchars($search_blob) ?>">
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['brand_series'] ?? 'Memory') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'brand_series', this.value)">
                                </td>
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['model_number'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'model_number', this.value)">
                                </td>
                                <td>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <span style="position: absolute; left: 8px; font-weight: 800; color: var(--text-secondary);">$</span>
                                        <input type="number" step="any" class="matrix-cell-input input-price" value="<?= number_format($price, 2, '.', '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'price', this.value)" style="padding-left: 20px; font-weight: 700;">
                                    </div>
                                </td>
                                <td>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <input type="number" step="any" class="matrix-cell-input input-st" value="<?= number_format($st, 2, '.', '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'sale_through', this.value / 100)" style="padding-right: 20px; font-weight: 700;">
                                        <span style="position: absolute; right: 8px; font-weight: 800; color: var(--text-secondary);">%</span>
                                    </div>
                                </td>
                                <td style="font-weight: 800; color: #10b981;">
                                    $<span class="calc-per-unit"><?= number_format($per_unit, 2) ?></span>
                                </td>
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['effective_date'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'effective_date', this.value)" style="text-align: center;">
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_admin): ?>
                                        <button type="button" onclick="deleteTestedRule(<?= $r['id'] ?>)" title="Delete Row" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 1rem;">✕</button>
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
    <?php else: ?>
        <!-- Standard Laptop Model Layout Table -->
        <div class="trends-table-container">
            <table class="trends-table" id="table-tested-laptop">
                <thead>
                    <tr style="background: #0f172a; color: white;">
                        <th style="min-width: 130px;">Series</th>
                        <th style="min-width: 140px;">Model Number</th>
                        <th style="width: 70px; text-align: center;">2-in-1</th>
                        <th style="min-width: 110px;">CPU</th>
                        <th style="width: 125px;">Price ($)</th>
                        <th style="width: 135px;">Sale Through (%)</th>
                        <th style="width: 95px; text-align: center;"># Sold</th>
                        <th style="width: 120px; text-align: right;">Full Specs ($)</th>
                        <th style="width: 130px; text-align: right;">Opp. Full Specs</th>
                        <th style="width: 120px; text-align: right;">Boot2BIOS ($)</th>
                        <th style="width: 130px; text-align: right;">Opp. Boot2BIOS</th>
                        <th style="width: 95px; text-align: center;">Date</th>
                        <th style="width: 60px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rules)): ?>
                        <tr><td colspan="13" style="text-align: center; padding: 25px; color: var(--text-secondary);">No laptop pricing rules found in this category. Click "+ Add Model Row" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rules as $r): ?>
                            <?php
                            $price = (float)$r['price'];
                            $st_dec = (float)$r['sale_through'];
                            $st_pct = $st_dec * 100;
                            $tiers = TestedMarketManager::calculateTiers($price, $st_dec);
                            $search_blob = strtolower(($r['brand_series'] ?? '') . ' ' . ($r['model_number'] ?? '') . ' ' . ($r['cpu'] ?? ''));
                            ?>
                            <tr data-rule-id="<?= $r['id'] ?>" data-search="<?= htmlspecialchars($search_blob) ?>">
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['brand_series'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'brand_series', this.value)">
                                </td>
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['model_number'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'model_number', this.value)">
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_admin): ?>
                                        <input type="checkbox" <?= !empty($r['is_2in1']) ? 'checked' : '' ?> onchange="updateTestedCell(<?= $r['id'] ?>, 'is_2in1', this.checked ? 1 : 0)">
                                    <?php else: ?>
                                        <span style="font-size: 1.05rem; font-weight: 800; color: <?= !empty($r['is_2in1']) ? '#10b981' : 'var(--text-secondary)' ?>;">
                                            <?= !empty($r['is_2in1']) ? '✔' : '—' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['cpu'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'cpu', this.value)">
                                </td>
                                <td>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <span style="position: absolute; left: 8px; font-weight: 800; color: var(--text-secondary);">$</span>
                                        <input type="number" step="any" class="matrix-cell-input input-price" value="<?= number_format($price, 2, '.', '') ?>" onchange="onTestedPriceOrStChange(<?= $r['id'] ?>)" style="padding-left: 20px; font-weight: 700;">
                                    </div>
                                </td>
                                <td>
                                    <div style="position: relative; display: flex; align-items: center;">
                                        <input type="number" step="any" class="matrix-cell-input input-st" value="<?= number_format($st_pct, 2, '.', '') ?>" onchange="onTestedPriceOrStChange(<?= $r['id'] ?>)" style="padding-right: 20px; font-weight: 700;">
                                        <span style="position: absolute; right: 8px; font-weight: 800; color: var(--text-secondary);">%</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="matrix-cell-input inline-text" value="<?= (int)$r['sold_count'] ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'sold_count', this.value)" style="text-align: center;">
                                </td>
                                <!-- Derived Formula Columns -->
                                <td style="font-weight: 700; color: var(--text-main); text-align: right;">
                                    $<span class="calc-full-specs"><?= number_format($tiers['full_specs'], 2) ?></span>
                                </td>
                                <td style="font-weight: 700; color: #3b82f6; text-align: right;">
                                    $<span class="calc-opp-full-specs"><?= number_format($tiers['opp_full_specs'], 2) ?></span>
                                </td>
                                <td style="font-weight: 700; color: var(--text-main); text-align: right;">
                                    $<span class="calc-boot2bios"><?= number_format($tiers['boot2bios'], 2) ?></span>
                                </td>
                                <td style="font-weight: 700; color: #8b5cf6; text-align: right;">
                                    $<span class="calc-opp-boot2bios"><?= number_format($tiers['opp_boot2bios'], 2) ?></span>
                                </td>
                                <td>
                                    <input type="text" class="matrix-cell-input inline-text" value="<?= htmlspecialchars($r['effective_date'] ?? '') ?>" onchange="updateTestedCell(<?= $r['id'] ?>, 'effective_date', this.value)" style="text-align: center;">
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($is_admin): ?>
                                        <button type="button" onclick="deleteTestedRule(<?= $r['id'] ?>)" title="Delete Row" style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 1rem;">✕</button>
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
    <?php endif; ?>
</div>

<!-- Modal: Add Category Tab -->
<div id="add-tested-cat-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-panel, #1e293b); color: var(--text-main, #fff); padding: 24px; border-radius: 12px; max-width: 420px; width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 800; margin-bottom: 15px;">➕ Add Tested Market Tab</h3>
        <div style="margin-bottom: 15px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">Category Name</label>
            <input type="text" id="new-cat-name" placeholder="e.g. 12th Gen, AMD Ryzen, Workstations" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.85rem; font-weight: 700; display: block; margin-bottom: 6px;">Layout Type</label>
            <select id="new-cat-layout" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main); font-weight: 600;">
                <option value="laptop">Standard Laptop Layout</option>
                <option value="ram">RAM / Memory Layout</option>
            </select>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" onclick="closeAddCategoryModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="button" onclick="submitAddCategory()" style="padding: 8px 16px; border-radius: 8px; background: #8b5cf6; border: none; color: white; cursor: pointer; font-weight: 700;">Create Tab</button>
        </div>
    </div>
</div>

<!-- Modal: Add Rule Row -->
<div id="add-tested-rule-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: var(--bg-panel, #1e293b); color: var(--text-main, #fff); padding: 24px; border-radius: 12px; max-width: 480px; width: 90%; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
        <h3 style="margin-top: 0; font-size: 1.1rem; font-weight: 800; margin-bottom: 15px;">➕ Add Row to <?= htmlspecialchars($active_category['name'] ?? '') ?></h3>
        <form id="add-tested-rule-form" onsubmit="submitAddRule(event)">
            <input type="hidden" name="category_id" value="<?= $active_cat_id ?>">
            <?php if ($active_category['layout_type'] === 'ram'): ?>
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Category (Desktop/Laptop)</label>
                    <input type="text" name="brand_series" placeholder="Desktop" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Specification (e.g. 8 GB (x10))</label>
                    <input type="text" name="model_number" placeholder="8 GB (x10)" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Series</label>
                        <input type="text" name="brand_series" placeholder="Latitude / Precision / XPS" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Model Number</label>
                        <input type="text" name="model_number" placeholder="5540" required style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">CPU</label>
                        <input type="text" name="cpu" placeholder="i7 / i5 / Xeon" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 20px;">
                        <input type="checkbox" name="is_2in1" id="add_is_2in1" value="1">
                        <label for="add_is_2in1" style="font-size: 0.85rem; font-weight: 700; cursor: pointer;">2-in-1 Device</label>
                    </div>
                </div>
            <?php endif; ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Price ($)</label>
                    <input type="number" step="any" name="price" placeholder="0.00" value="0.00" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Sell-Through %</label>
                    <input type="number" step="any" name="sale_through" placeholder="45.00" value="0.00" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                </div>
                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; display: block; margin-bottom: 4px;">Units Sold</label>
                    <input type="number" name="sold_count" placeholder="0" value="0" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-surface-2); color: var(--text-main);">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeAddRuleModal()" style="padding: 8px 16px; border-radius: 8px; background: transparent; border: 1px solid var(--border-color); color: var(--text-main); cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; border-radius: 8px; background: #3b82f6; border: none; color: white; cursor: pointer; font-weight: 700;">Save Row</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTestedCategory(catId) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'trends');
    url.searchParams.set('tested_cat', catId);
    window.location.href = url.toString();
}

function onTestedPriceOrStChange(ruleId) {
    const tr = document.querySelector(`tr[data-rule-id="${ruleId}"]`);
    if (!tr) return;

    const priceInput = tr.querySelector('.input-price');
    const stInput = tr.querySelector('.input-st');

    const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
    const stPct = parseFloat(stInput ? stInput.value : 0) || 0;
    const stDec = stPct / 100.0;

    // Send AJAX updates
    updateTestedCell(ruleId, 'price', price);
    updateTestedCell(ruleId, 'sale_through', stDec);

    // Dynamic Live Formula Calculations
    const fullSpecs = Math.max(0, (price * 0.92) - 95.00);
    const oppFullSpecs = stDec * fullSpecs;
    const boot2bios = Math.max(0, (price * 0.92) - 55.00);
    const oppBoot2bios = stDec * boot2bios;

    const elFull = tr.querySelector('.calc-full-specs');
    const elOppFull = tr.querySelector('.calc-opp-full-specs');
    const elBoot = tr.querySelector('.calc-boot2bios');
    const elOppBoot = tr.querySelector('.calc-opp-boot2bios');

    if (elFull) elFull.textContent = fullSpecs.toFixed(2);
    if (elOppFull) elOppFull.textContent = oppFullSpecs.toFixed(2);
    if (elBoot) elBoot.textContent = boot2bios.toFixed(2);
    if (elOppBoot) elOppBoot.textContent = oppBoot2bios.toFixed(2);
}

function updateTestedCell(ruleId, field, value) {
    fetch('?view=trends&action=update_tested_market_cell', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rule_id: ruleId, field: field, value: value })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('Error updating cell: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => console.error('Tested cell update failed:', err));
}

function openAddCategoryModal() {
    const modal = document.getElementById('add-tested-cat-modal');
    if (typeof bringModalToFront === 'function') bringModalToFront(modal);
    modal.style.display = 'flex';
}
function closeAddCategoryModal() {
    document.getElementById('add-tested-cat-modal').style.display = 'none';
}

function submitAddCategory() {
    const name = document.getElementById('new-cat-name').value.trim();
    const layout = document.getElementById('new-cat-layout').value;
    if (!name) {
        alert('Please enter a category name.');
        return;
    }
    fetch('?view=trends&action=add_tested_market_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, layout_type: layout })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            switchTestedCategory(data.category_id);
        } else {
            alert('Error creating tab: ' + (data.error || 'Failed'));
        }
    });
}

function deleteTestedCategory(catId, catName) {
    if (!confirm(`Are you sure you want to delete category tab "${catName}" and all its rows?`)) return;
    fetch('?view=trends&action=delete_tested_market_category', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ category_id: catId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error deleting category: ' + (data.error || 'Failed'));
        }
    });
}

function openAddRuleModal() {
    const modal = document.getElementById('add-tested-rule-modal');
    if (typeof bringModalToFront === 'function') bringModalToFront(modal);
    modal.style.display = 'flex';
}
function closeAddRuleModal() {
    document.getElementById('add-tested-rule-modal').style.display = 'none';
}

function submitAddRule(e) {
    e.preventDefault();
    const form = document.getElementById('add-tested-rule-form');
    const formData = new FormData(form);
    const payload = {};
    formData.forEach((val, key) => payload[key] = val);

    // convert sale_through percentage input to decimal
    if (payload.sale_through) {
        payload.sale_through = (parseFloat(payload.sale_through) || 0) / 100.0;
    }

    fetch('?view=trends&action=add_tested_market_rule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
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

function deleteTestedRule(ruleId) {
    if (!confirm('Are you sure you want to delete this pricing row?')) return;
    fetch('?view=trends&action=delete_tested_market_rule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rule_id: ruleId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tr = document.querySelector(`tr[data-rule-id="${ruleId}"]`);
            if (tr) tr.remove();
        } else {
            alert('Error deleting row: ' + (data.error || 'Failed'));
        }
    });
}
</script>
