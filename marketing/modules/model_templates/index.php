<?php
/**
 * Model Templates Module - Master Content Library
 * Cross-references specs and pitches for rapid promotion.
 */

$editTmpl = null;
$action = $_GET['action'] ?? null;
$error = null;

// 1. Add Template (POST, CSRF Protected)
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=model_templates");
        exit;
    }

    $model_name = trim($_POST['model_name'] ?? '');
    $category = trim($_POST['category'] ?? 'Laptop');
    $base_specs = trim($_POST['base_specs'] ?? '');
    $marketing_copy = trim($_POST['marketing_copy'] ?? '');

    if (!empty($model_name)) {
        try {
            $stmt = $marketingDb->prepare("INSERT INTO model_templates (model_name, category, base_specs, marketing_copy) VALUES (?, ?, ?, ?)");
            $stmt->execute([$model_name, $category, $base_specs, $marketing_copy]);

            $newId = $marketingDb->lastInsertId();
            log_marketing_audit($marketingDb, 'Template', $newId, 'CREATED', "Created marketing template for: $model_name");

            $_SESSION['notify'] = ['message' => 'Template created successfully!', 'type' => 'success'];
            header("Location: ?page=model_templates");
            exit;
        } catch (Throwable $e) {
            $error = "Failed to create template: " . $e->getMessage();
        }
    } else {
        $error = "Model Name is required.";
    }
}

// 2. Update Template (POST, CSRF Protected)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=model_templates");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $model_name = trim($_POST['model_name'] ?? '');
    $category = trim($_POST['category'] ?? 'Laptop');
    $base_specs = trim($_POST['base_specs'] ?? '');
    $marketing_copy = trim($_POST['marketing_copy'] ?? '');

    if ($id > 0 && !empty($model_name)) {
        try {
            $stmt = $marketingDb->prepare("UPDATE model_templates SET model_name = ?, category = ?, base_specs = ?, marketing_copy = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$model_name, $category, $base_specs, $marketing_copy, $id]);

            log_marketing_audit($marketingDb, 'Template', $id, 'UPDATED', "Updated marketing template for: $model_name");

            $_SESSION['notify'] = ['message' => 'Template updated successfully!', 'type' => 'success'];
            header("Location: ?page=model_templates");
            exit;
        } catch (Throwable $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    } else {
        $error = "Invalid template ID or missing model name.";
    }
}

// 3. Edit Fetch (Safe GET)
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $marketingDb->prepare("SELECT * FROM model_templates WHERE id = ?");
    $stmt->execute([$id]);
    $editTmpl = $stmt->fetch();
}

// 4. Delete Action (POST only, CSRF Protected)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=model_templates");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $marketingDb->prepare("SELECT model_name FROM model_templates WHERE id = ?");
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();

            $delStmt = $marketingDb->prepare("DELETE FROM model_templates WHERE id = ?");
            $delStmt->execute([$id]);

            log_marketing_audit($marketingDb, 'Template', $id, 'DELETED', "Deleted marketing template for: " . ($name ?: "ID $id"));

            $_SESSION['notify'] = ['message' => 'Template deleted successfully!', 'type' => 'success'];
            header("Location: ?page=model_templates");
            exit;
        } catch (Throwable $e) {
            $error = "Deletion failed: " . $e->getMessage();
        }
    }
}

// 5. Fetch Warehouse Inventory from db/warehouse.db
$warehouseItems = [];
$inventoryData = [];
$totalWarehouseQty = 0;
$sectorCounts = [
    'All' => 0,
    'Laptops' => 0,
    'Desktops' => 0,
    'Gaming' => 0
];

if (!function_exists('parseWarehouseCpuSpecs')) {
    function parseWarehouseCpuSpecs($specs) {
        $rawCpu = trim($specs['cpu'] ?? ($specs['cpu_gen'] ?? ''));
        $rawGen = trim($specs['gen'] ?? '');
        $rawSeries = trim($specs['series'] ?? '');
        $full = strtolower($rawCpu . ' ' . $rawGen . ' ' . $rawSeries);

        // 1. Generation Extraction (1st - 16th Gen)
        $genNum = 0;
        $genDisplay = '';

        if (preg_match_all('/\b(1[0-6]|[1-9])(?:th|st|nd|rd|h)\b/i', $rawGen, $m)) {
            $genNum = max(array_map('intval', $m[1]));
        } elseif (preg_match_all('/\b(1[0-6]|[1-9])(?:th|st|nd|rd|h)\b/i', $rawCpu, $m)) {
            $genNum = max(array_map('intval', $m[1]));
        } elseif (preg_match('/i[3579]-(\d{1,2})\d{3}/i', $rawCpu, $m)) {
            $genNum = (int)$m[1];
        } elseif (preg_match('/ryzen\s*[3579]\s*(\d)\d{3}/i', $full, $m)) {
            $genNum = (int)$m[1];
        } elseif (preg_match('/m([123])/i', $full, $m)) {
            $genNum = (int)$m[1];
        }

        if ($genNum > 0) {
            $genDisplay = $genNum . 'th Gen';
        }

        // 2. CPU Tier Extraction (Core i9/Ryzen 9, Core i7/Ryzen 7, Core i5/Ryzen 5, Core i3/Ryzen 3, Xeon, Entry)
        $tierRank = 0;
        $tierCode = 'other';
        $tierName = 'Other';

        if (preg_match('/(i9|ryzen\s*9|m[123]\s*max|threadripper)/i', $full)) {
            $tierRank = 90;
            $tierCode = 'i9';
            $tierName = 'Core i9';
        } elseif (preg_match('/(i7|ryzen\s*7|m[123]\s*pro)/i', $full)) {
            $tierRank = 70;
            $tierCode = 'i7';
            $tierName = 'Core i7';
        } elseif (preg_match('/(xeon)/i', $full)) {
            $tierRank = 60;
            $tierCode = 'xeon';
            $tierName = 'Xeon';
        } elseif (preg_match('/(i5|ryzen\s*5|apple\s*m\d|\bm[123]\b)/i', $full)) {
            $tierRank = 50;
            $tierCode = 'i5';
            $tierName = 'Core i5';
        } elseif (preg_match('/(i3|ryzen\s*3)/i', $full)) {
            $tierRank = 30;
            $tierCode = 'i3';
            $tierName = 'Core i3';
        } elseif (preg_match('/(celeron|pentium|athlon|atom)/i', $full)) {
            $tierRank = 10;
            $tierCode = 'entry';
            $tierName = 'Entry';
        }

        return [
            'gen_num' => $genNum,
            'gen_display' => $genDisplay,
            'tier_rank' => $tierRank,
            'tier_code' => $tierCode,
            'tier_name' => $tierName
        ];
    }
}

if (isset($warehouseDb) && $warehouseDb instanceof PDO) {
    try {
        $stmt = $warehouseDb->query("SELECT id, user_owner, sector, location_code, brand, model, specs_json, quantity, status, price FROM inventory ORDER BY id DESC");
        $warehouseItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sectorCounts['All'] = count($warehouseItems);
        foreach ($warehouseItems as $wIt) {
            $sec = $wIt['sector'] ?? 'Other';
            if (isset($sectorCounts[$sec])) {
                $sectorCounts[$sec]++;
            }
            $totalWarehouseQty += (int)($wIt['quantity'] ?? 0);

            $specs = json_decode($wIt['specs_json'] ?? '', true) ?: [];
            $cpu = $specs['cpu'] ?? ($specs['cpu_gen'] ?? '');
            if (!empty($specs['gen']) && !str_contains($cpu, $specs['gen'])) {
                $cpu .= (!empty($cpu) ? ' ' : '') . '(' . $specs['gen'] . ')';
            }
            if (empty($cpu) && !empty($specs['series'])) {
                $cpu = $specs['series'];
            }
            if (empty($cpu) && !empty($specs['category'])) {
                $cpu = $specs['category'];
            }
            $ram = $specs['ram'] ?? '';
            $storage = $specs['storage'] ?? '';
            $cond = $specs['condition'] ?? '';
            $notes = $specs['notes'] ?? '';
            $price = number_format((float)($wIt['price'] ?? 0), 2);
            $qty = (int)($wIt['quantity'] ?? 0);

            $parsedCpu = parseWarehouseCpuSpecs($specs);

            $searchStr = strtolower(implode(' ', [
                $sec,
                $wIt['location_code'] ?? '',
                $wIt['brand'] ?? '',
                $wIt['model'] ?? '',
                $cpu,
                $parsedCpu['gen_display'],
                $parsedCpu['tier_name'],
                $ram,
                $storage,
                $cond,
                $notes,
                $price,
                (string)$qty
            ]));

            $inventoryData[] = [
                'id' => (int)$wIt['id'],
                'sector' => $sec,
                'location' => $wIt['location_code'] ?? '',
                'brand' => $wIt['brand'] ?? '',
                'model' => $wIt['model'] ?? '',
                'cpu' => $cpu,
                'cpu_gen' => (int)$parsedCpu['gen_num'],
                'cpu_gen_label' => $parsedCpu['gen_display'],
                'cpu_tier' => (int)$parsedCpu['tier_rank'],
                'cpu_tier_code' => $parsedCpu['tier_code'],
                'cpu_tier_name' => $parsedCpu['tier_name'],
                'ram' => $ram,
                'storage' => $storage,
                'condition' => $cond,
                'notes' => $notes,
                'price' => $price,
                'qty' => $qty,
                'search' => $searchStr
            ];
        }
    } catch (Throwable $e) {
        error_log("Failed to load warehouse inventory: " . $e->getMessage());
    }
}

// Fetch saved templates early so count is available for navigation pills
$templates = [];
try {
    $templates = $marketingDb->query("SELECT * FROM model_templates ORDER BY model_name ASC")->fetchAll();
} catch (Throwable $e) {
    error_log("Failed to load templates: " . $e->getMessage());
}
$templateCount = count($templates);

// Determine initial active view section
$activeView = 'stock';
if ($editTmpl || $error || (isset($_GET['view']) && $_GET['view'] === 'form') || isset($_GET['prefill_model'])) {
    $activeView = 'form';
} elseif (isset($_GET['view']) && $_GET['view'] === 'templates') {
    $activeView = 'templates';
}
?>

<header class="page-header">
    <h1>Model Template Library</h1>
    <p>Create master marketing copy and specs for high-volume inventory.</p>
</header>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error); ?></div>
<?php endif; ?>

<!-- Single-Section Card Switcher (Displays one section at a time) -->
<nav class="section-switcher-nav" id="model-templates-nav" aria-label="Template Workspace Sections">
    <button type="button" class="section-switcher-btn <?= $activeView === 'stock' ? 'active' : ''; ?>" data-target-view="stock" onclick="switchSectionView('stock')">
        <span>📊 Stock Spreadsheet</span>
        <span class="section-badge"><?= number_format(count($inventoryData)); ?> items</span>
    </button>
    <button type="button" class="section-switcher-btn <?= $activeView === 'form' ? 'active' : ''; ?>" data-target-view="form" onclick="switchSectionView('form')">
        <span><?= $editTmpl ? '✏️ Edit Template' : '✍️ Create New Template'; ?></span>
        <?php if ($editTmpl): ?>
            <span class="section-badge badge-pulse">#<?= (int)$editTmpl['id']; ?></span>
        <?php else: ?>
            <span class="section-badge" id="form-mode-badge">Editor</span>
        <?php endif; ?>
    </button>
    <button type="button" class="section-switcher-btn <?= $activeView === 'templates' ? 'active' : ''; ?>" data-target-view="templates" onclick="switchSectionView('templates')">
        <span>📚 Saved Templates</span>
        <span class="section-badge"><?= number_format($templateCount); ?> active</span>
    </button>
</nav>

<div class="dashboard-grid">
    <!-- SECTION 1: WAREHOUSE STOCK SPREADSHEET (Default Primary View) -->
    <div class="template-view-section <?= $activeView === 'stock' ? 'active' : ''; ?>" id="view-section-stock" style="grid-column: span 12;">
        <section class="card spreadsheet-section" id="warehouse-spreadsheet-card" style="width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span>📊 Warehouse Stock Spreadsheet</span>
                        <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: #e0f2fe; color: #0284c7;">db/warehouse.db</span>
                    </h2>
                    <p style="color: var(--text-dim); font-size: 0.85rem; margin: 0;">
                        Live warehouse stock inventory with flexible multi-term search and smart CPU stacked sorting. Click <strong>⚡ Prefill</strong> on any stock item to immediately create a model template.
                    </p>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn-small" onclick="exportWarehouseStockCSV()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                        📥 Export CSV
                    </button>
                    <button type="button" class="btn-small btn-highlight" onclick="switchSectionView('form')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                        ✍️ Create Blank Template
                    </button>
                </div>
            </div>

        <!-- Toolbar & Flexible Search -->
        <div class="spreadsheet-toolbar">
            <div class="spreadsheet-search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" id="wh-search-input" class="spreadsheet-search-input" 
                       placeholder="Flexible search: brand, model, cpu, ram, location (e.g. 'dell 5490', 'i7 16gb')..." 
                       autocomplete="off">
                <button type="button" id="wh-search-clear" class="spreadsheet-search-clear" title="Clear search">✕</button>
            </div>

            <!-- Sector Filters -->
            <div class="spreadsheet-filter-pills" id="wh-sector-pills">
                <button type="button" class="filter-pill active" data-sector="All">
                    All (<?= number_format($sectorCounts['All']); ?>)
                </button>
                <button type="button" class="filter-pill" data-sector="Laptops">
                    💻 Laptops (<?= number_format($sectorCounts['Laptops']); ?>)
                </button>
                <button type="button" class="filter-pill" data-sector="Desktops">
                    🖥️ Desktops (<?= number_format($sectorCounts['Desktops']); ?>)
                </button>
                <button type="button" class="filter-pill" data-sector="Gaming">
                    🎮 Gaming (<?= number_format($sectorCounts['Gaming']); ?>)
                </button>
            </div>
        </div>

        <!-- Metadata & Live Stats Counter -->
        <div class="spreadsheet-meta-bar">
            <div>
                Showing <strong id="wh-visible-count" style="color: var(--accent-primary);"><?= min(50, count($inventoryData)); ?></strong> of 
                <strong id="wh-total-filtered-top"><?= number_format(count($inventoryData)); ?></strong> items 
                (<span id="wh-visible-qty"><?= number_format($totalWarehouseQty); ?></span> total units in stock)
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span>Click <strong style="color: var(--accent-primary);">⚡ Prefill</strong> to auto-populate template</span>
            </div>
        </div>

        <!-- Stacked Sort Hierarchy Bar -->
        <div class="stacked-sort-bar" id="wh-stacked-sort-bar" style="display: none;">
            <span class="stacked-sort-label">Stacked Sort:</span>
            <div class="stacked-sort-chips" id="wh-stacked-sort-chips"></div>
            <button type="button" class="btn-clear-sorts" id="wh-clear-sorts" title="Reset all column sorts">Clear Sorts</button>
        </div>

        <!-- Spreadsheet Table Grid -->
        <div class="spreadsheet-table-wrapper" id="wh-table-wrapper">
            <table class="spreadsheet-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="sector" style="width: 7%;">Sector <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="qty" style="width: 5%; text-align: center;">Qty <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="location" style="width: 9%; text-align: center;" title="Location Code">Loc <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="brand" style="width: 8%;">Brand <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="model" style="width: 12%;">Model <span class="sort-indicator">⇅</span></th>
                        <th class="sortable col-cpu-header" data-sort="cpu" style="width: 13%;" title="Click for Smart CPU Stack: Gen (Newest) ➔ Tier (Fastest)">
                            <div class="cpu-header-content">
                                <span>CPU / Series</span>
                                <span class="cpu-smart-hint" id="wh-cpu-sort-badge" title="Smart CPU Stack">Gen ➔ Tier</span>
                                <span class="sort-indicator" id="wh-cpu-indicator">⇅</span>
                            </div>
                        </th>
                        <th class="sortable" data-sort="ram" style="width: 5%; text-align: center;">RAM <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="storage" style="width: 7%; text-align: center;">Storage <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="condition" style="width: 6.5%; text-align: center;" title="Condition Grade">Cond <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="notes" style="width: 12.5%;">Notes <span class="sort-indicator">⇅</span></th>
                        <th class="sortable" data-sort="price" style="width: 6.5%; text-align: right;">Price <span class="sort-indicator">⇅</span></th>
                        <th style="width: 8.5%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="wh-inventory-tbody">
                    <?php if (empty($inventoryData)): ?>
                        <tr id="wh-empty-row">
                            <td colspan="12" style="text-align: center; padding: 3rem; color: var(--text-dim);">
                                No inventory records found in warehouse database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $initialRows = array_slice($inventoryData, 0, 50);
                        foreach ($initialRows as $item):
                            $secClass = strtolower($item['sector']);
                        ?>
                            <tr class="wh-stock-row"
                                data-id="<?= (int)$item['id']; ?>"
                                data-sector="<?= h($item['sector']); ?>"
                                data-location="<?= h($item['location']); ?>"
                                data-brand="<?= h($item['brand']); ?>"
                                data-model="<?= h($item['model']); ?>"
                                data-cpu="<?= h($item['cpu']); ?>"
                                data-cpu-gen="<?= (int)$item['cpu_gen']; ?>"
                                data-cpu-tier="<?= (int)$item['cpu_tier']; ?>"
                                data-ram="<?= h($item['ram']); ?>"
                                data-storage="<?= h($item['storage']); ?>"
                                data-condition="<?= h($item['condition']); ?>"
                                data-notes="<?= h($item['notes']); ?>"
                                data-price="<?= h($item['price']); ?>"
                                data-qty="<?= (int)$item['qty']; ?>"
                                data-search="<?= h($item['search']); ?>">
                                
                                <td>
                                    <div style="padding: 0 4px;">
                                        <span class="sector-tag <?= h($secClass); ?>" title="Sector: <?= h($item['sector']); ?>"><?= h($item['sector']); ?></span>
                                    </div>
                                </td>
                                <td class="text-center font-bold" style="font-size: 0.88rem;" title="<?= (int)$item['qty']; ?> units">
                                    <?= (int)$item['qty']; ?>
                                </td>
                                <td class="text-center cell-location" style="color: var(--text-dim); font-size: 0.82rem; font-weight: 600; white-space: nowrap;" title="Location: <?= h($item['location']); ?>">
                                    <?= h($item['location']); ?>
                                </td>
                                <td style="font-weight: 600;" title="<?= h($item['brand']); ?>">
                                    <?= h($item['brand']); ?>
                                </td>
                                <td class="font-bold cell-model" style="color: var(--text-main);" title="<?= h($item['model']); ?>">
                                    <?= h($item['model']); ?>
                                </td>
                                <td class="cell-cpu" style="font-size: 0.82rem;" title="<?= h($item['cpu']); ?>">
                                    <?php if (!empty($item['cpu_tier_code']) && $item['cpu_tier_code'] !== 'other'): ?>
                                        <span class="cpu-tier-badge tier-<?= h($item['cpu_tier_code']); ?>"><?= strtoupper(h($item['cpu_tier_code'])); ?></span>
                                    <?php endif; ?>
                                    <span class="cpu-spec-txt"><?= h(!empty($item['cpu_gen_label']) ? $item['cpu_gen_label'] : $item['cpu']); ?></span>
                                </td>
                                <td class="text-center" style="font-size: 0.82rem;" title="RAM: <?= h($item['ram']); ?>">
                                    <?= h($item['ram']); ?>
                                </td>
                                <td class="text-center" style="font-size: 0.82rem;" title="Storage: <?= h($item['storage']); ?>">
                                    <?= h($item['storage']); ?>
                                </td>
                                <td class="text-center" title="Condition: <?= h($item['condition']); ?>">
                                    <span class="condition-tag"><?= h($item['condition']); ?></span>
                                </td>
                                <td style="color: var(--text-dim); font-size: 0.82rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= h($item['notes']); ?>">
                                    <?= h($item['notes']); ?>
                                </td>
                                <td class="text-right font-bold price-tag" title="$<?= number_format((float)$item['price'], 2); ?>">
                                    $<?= number_format((float)$item['price'], 2); ?>
                                </td>
                                <td style="text-align: center; padding: 0 4px;">
                                    <button type="button" class="btn-prefill-template" title="Prefill template form above"
                                            data-sector="<?= h($item['sector']); ?>"
                                            data-brand="<?= h($item['brand']); ?>"
                                            data-model="<?= h($item['model']); ?>"
                                            data-cpu="<?= h($item['cpu']); ?>"
                                            data-ram="<?= h($item['ram']); ?>"
                                            data-storage="<?= h($item['storage']); ?>"
                                            data-condition="<?= h($item['condition']); ?>"
                                            data-notes="<?= h($item['notes']); ?>"
                                            data-price="<?= h($item['price']); ?>">
                                        ⚡ Prefill
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination & Rows Selection Bar -->
        <div class="spreadsheet-pagination">
            <div>
                Showing <span id="wh-page-range" style="font-weight: 700; color: var(--text-main);">1-<?= min(50, count($inventoryData)); ?></span> of 
                <span id="wh-total-filtered" style="font-weight: 700; color: var(--text-main);"><?= number_format(count($inventoryData)); ?></span> items 
                (<span id="wh-total-units" style="font-weight: 700; color: var(--accent-primary);"><?= number_format($totalWarehouseQty); ?></span> units)
            </div>
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label for="wh-page-size" style="font-size: 0.8rem; color: var(--text-dim);">Rows:</label>
                    <select id="wh-page-size" style="height: 32px; padding: 0 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.8rem; background: var(--bg-panel); color: var(--text-main);">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="pagination-controls">
                    <button type="button" class="btn-page" id="wh-prev-page" disabled>◀ Prev</button>
                    <span class="page-info" id="wh-page-info">Page 1 of <?= max(1, (int)ceil(count($inventoryData) / 50)); ?></span>
                    <button type="button" class="btn-page" id="wh-next-page" <?= count($inventoryData) <= 50 ? 'disabled' : ''; ?>>Next ▶</button>
                </div>
            </div>
        </div>
    </section>
</div> <!-- /view-section-stock -->

<!-- SECTION 2: CREATE / EDIT TEMPLATE FORM (Focused Single-Section Mode) -->
<div class="template-view-section <?= $activeView === 'form' ? 'active' : ''; ?>" id="view-section-form" style="grid-column: span 12;">
    <section class="card" id="template-form-card" style="width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span><?= $editTmpl ? '✏️ Edit Template' : '✍️ Create New Template'; ?></span>
                    <?php if ($editTmpl): ?>
                        <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: #fef3c7; color: #b45309;">Editing #<?= (int)$editTmpl['id']; ?></span>
                    <?php endif; ?>
                </h2>
                <p style="color: var(--text-dim); font-size: 0.85rem; margin: 4px 0 0 0;">
                    Define master specifications and promotional copy, or choose <strong>⚡ Prefill</strong> from warehouse stock.
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-small" onclick="switchSectionView('stock')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                    ← Back to Stock Spreadsheet
                </button>
            </div>
        </div>

        <form action="?page=model_templates&action=<?= $editTmpl ? 'update' : 'add'; ?>" method="POST" class="standard-form">
            <?= UI::csrf_field() ?>
            <?php if ($editTmpl): ?>
                <input type="hidden" name="id" value="<?= (int)$editTmpl['id']; ?>">
            <?php endif; ?>

            <div class="form-grid-2col">
                <div class="form-group">
                    <label for="model_name">Model Name</label>
                    <?php
                        $prefill = $_GET['prefill_model'] ?? ($editTmpl['model_name'] ?? '');
                    ?>
                    <input type="text" name="model_name" id="model_name" required value="<?= h($prefill); ?>" placeholder="e.g. Dell Latitude 5490">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <?php
                        $cats = ['Laptop', 'Desktop', 'Server', 'Part', 'Monitor', 'Workstation'];
                        foreach($cats as $cat):
                            $sel = (isset($editTmpl) && ($editTmpl['category'] ?? '') === $cat) ? 'selected' : '';
                            echo "<option value=\"" . h($cat) . "\" $sel>" . h($cat) . "</option>";
                        endforeach;
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2col">
                <div class="form-group">
                    <label for="base_specs">Standard Specifications</label>
                    <?php $preSpecs = $_GET['prefill_specs'] ?? ($editTmpl['base_specs'] ?? ''); ?>
                    <textarea name="base_specs" id="base_specs" rows="6" placeholder="i5-8350U, 8GB RAM, 256GB SSD..."><?= h($preSpecs); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="marketing_copy">Marketing Copy (The Pitch)</label>
                    <?php $preCopy = $_GET['prefill_copy'] ?? ($editTmpl['marketing_copy'] ?? ''); ?>
                    <textarea name="marketing_copy" id="marketing_copy" rows="6" placeholder="Write the promotional ad description here..."><?= h($preCopy); ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-top: 0.5rem;">
                <button type="button" class="btn-small" onclick="switchSectionView('stock')" style="height: 48px; line-height: 48px; padding: 0 20px;">
                    ← Back to Stock
                </button>
                <button type="submit" class="btn-action" style="min-width: 200px;">
                    <?= $editTmpl ? 'Update Template' : 'Save Template'; ?>
                </button>
            </div>
        </form>
    </section>
</div> <!-- /view-section-form -->

<!-- SECTION 3: SAVED TEMPLATES LIBRARY -->
<div class="template-view-section <?= $activeView === 'templates' ? 'active' : ''; ?>" id="view-section-templates" style="grid-column: span 12;">
    <section class="card" id="saved-templates-card" style="width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>📚 Active Templates Library</span>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: rgba(0,0,0,0.06); color: var(--text-dim);"><?= number_format($templateCount); ?> saved</span>
                </h2>
                <p style="color: var(--text-dim); font-size: 0.85rem; margin: 4px 0 0 0;">
                    Master marketing templates ready for immediate ad generation and photo inspection.
                </p>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-small" onclick="switchSectionView('stock')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                    📊 Stock Spreadsheet
                </button>
                <button type="button" class="btn-small btn-highlight" onclick="switchSectionView('form')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                    ✍️ New Template
                </button>
            </div>
        </div>

        <div class="template-list">
            <?php if (empty($templates)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-dim);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                    <p>Your library is empty. Templates help you generate ads instantly from warehouse stock.</p>
                </div>
            <?php else: ?>
                <div class="template-grid">
                    <?php 
                    $photoStmt = $marketingDb->prepare("SELECT category FROM photos WHERE model_name = ?");
                    foreach ($templates as $tmpl):
                        // Photo Bank Check
                        $photoStmt->execute([$tmpl['model_name']]);
                        $foundPhotos = $photoStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                        $hasBulk = in_array('Bulk Stock', $foundPhotos);
                        $hasLaptop = in_array('Laptop', $foundPhotos) || in_array('Workstation', $foundPhotos);
                        $hasOther = count($foundPhotos) > 0;
                        $photoCount = count($foundPhotos);
                    ?>
                        <div class="template-card">
                            <div class="tmpl-header">
                                <h3><?= h($tmpl['model_name']); ?></h3>
                                <span class="tmpl-badge"><?= h($tmpl['category']); ?></span>
                            </div>

                            <!-- PHOTO BANK PREVIEW -->
                            <div class="photo-bank-preview">
                                <div class="photo-slot <?= $hasBulk ? 'filled' : ''; ?>" title="Bulk/Pallet Shot">📦</div>
                                <div class="photo-slot <?= $hasLaptop ? 'filled' : ''; ?>" title="Detail Shot">✨</div>
                                <div class="photo-slot <?= $hasOther ? 'filled' : ''; ?>" title="Other Assets">🖼️</div>
                                <span class="photo-status"><?= (int)$photoCount; ?> Assets</span>
                            </div>

                            <div class="tmpl-body">
                                <div class="tmpl-specs">
                                    <?= UI::format_specs($tmpl['base_specs']); ?>
                                </div>
                            </div>
                            <div class="tmpl-footer" style="display: flex; gap: 8px; align-items: center; justify-content: flex-end;">
                                <a href="?page=model_templates&action=edit&id=<?= (int)$tmpl['id']; ?>#form" class="btn-small">Edit</a>
                                <a href="?page=ad_generator&model=<?= urlencode($tmpl['model_name']); ?>" class="btn-small btn-highlight">Create Ad</a>
                                
                                <form action="?page=model_templates&action=delete" method="POST" style="margin: 0; display: inline;" onsubmit="return confirmAction('Delete template for <?= h(addslashes($tmpl['model_name'])); ?>?', this);">
                                    <?= UI::csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$tmpl['id']; ?>">
                                    <button type="submit" class="btn-small" style="color: #ef4444; border-color: #fee2e2; background: none; cursor: pointer;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div> <!-- /view-section-templates -->
</div> <!-- /dashboard-grid -->

<script>
/**
 * Single-Section Card Switcher (Stock Spreadsheet, Create/Edit Form, Saved Templates)
 */
window.switchSectionView = function(viewName) {
    const validViews = ['stock', 'form', 'templates'];
    if (!validViews.includes(viewName)) viewName = 'stock';

    // 1. Update navigation switcher buttons
    const navButtons = document.querySelectorAll('#model-templates-nav .section-switcher-btn');
    navButtons.forEach(btn => {
        if (btn.getAttribute('data-target-view') === viewName) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 2. Toggle view section cards
    const sections = document.querySelectorAll('.template-view-section');
    sections.forEach(sec => {
        if (sec.id === `view-section-${viewName}`) {
            sec.classList.add('active');
        } else {
            sec.classList.remove('active');
        }
    });

    // 3. Update URL hash without reloading page
    try {
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location.href);
            url.hash = viewName;
            window.history.replaceState(null, '', url.toString());
        }
    } catch (e) {}

    // 4. Scroll smoothly to switcher nav if page was scrolled down
    const nav = document.getElementById('model-templates-nav');
    if (nav && window.scrollY > nav.offsetTop + 100) {
        nav.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Check initial hash on load
if (window.location.hash) {
    const initialHash = window.location.hash.replace('#', '');
    if (['stock', 'form', 'templates'].includes(initialHash)) {
        window.switchSectionView(initialHash);
    }
}

/**
 * Warehouse Stock Spreadsheet & Flexible Search Controller
 * High performance client-side virtualized rendering & multi-term space-separated filter.
 */
(function() {
    'use strict';

    // In-memory data store for all inventory items from db/warehouse.db
    const ALL_INVENTORY = <?= json_encode($inventoryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];

    let filteredItems = ALL_INVENTORY.slice();
    let currentPage = 1;
    let pageSize = 50;
    let activeSector = 'All';

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initWarehouseSpreadsheet() {
        const searchInput = document.getElementById('wh-search-input');
        const clearBtn = document.getElementById('wh-search-clear');
        const sectorPills = document.querySelectorAll('#wh-sector-pills .filter-pill');
        const tbody = document.getElementById('wh-inventory-tbody');
        const tableWrapper = document.getElementById('wh-table-wrapper');
        const prevBtn = document.getElementById('wh-prev-page');
        const nextBtn = document.getElementById('wh-next-page');
        const pageInfo = document.getElementById('wh-page-info');
        const pageSizeSelect = document.getElementById('wh-page-size');
        const pageRange = document.getElementById('wh-page-range');
        const totalFiltered = document.getElementById('wh-total-filtered');
        const totalFilteredTop = document.getElementById('wh-total-filtered-top');
        const visibleCount = document.getElementById('wh-visible-count');
        const visibleUnits = document.getElementById('wh-visible-qty');

        const COLUMN_LABELS = {
            sector: 'Sector',
            qty: 'QTY',
            location: 'Location',
            brand: 'Brand',
            model: 'Model',
            cpu_gen: 'CPU Gen',
            cpu_tier: 'CPU Tier',
            cpu: 'CPU Series',
            ram: 'RAM',
            storage: 'Storage',
            condition: 'Condition',
            notes: 'Notes',
            price: 'Price'
        };

        const SUPERSCRIPT_NUMS = ['¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹', '¹⁰', '¹¹'];

        // Stacked Sort State: array of { key: string, dir: 'asc' | 'desc' }
        let sortStack = [];

        const stackedBar = document.getElementById('wh-stacked-sort-bar');
        const chipsContainer = document.getElementById('wh-stacked-sort-chips');
        const clearSortsBtn = document.getElementById('wh-clear-sorts');
        const sortableHeaders = document.querySelectorAll('.spreadsheet-table th.sortable');

        // Field comparison helper
        function compareField(a, b, key, dir) {
            let valA = a[key];
            let valB = b[key];

            if (key === 'qty') {
                const numA = parseInt(valA, 10) || 0;
                const numB = parseInt(valB, 10) || 0;
                return dir === 'asc' ? numA - numB : numB - numA;
            }

            if (key === 'price') {
                const numA = parseFloat(valA) || 0;
                const numB = parseFloat(valB) || 0;
                return dir === 'asc' ? numA - numB : numB - numA;
            }

            if (key === 'cpu_gen') {
                const genA = parseInt(a.cpu_gen, 10) || 0;
                const genB = parseInt(b.cpu_gen, 10) || 0;
                return dir === 'asc' ? genA - genB : genB - genA;
            }

            if (key === 'cpu_tier') {
                const tierA = parseInt(a.cpu_tier, 10) || 0;
                const tierB = parseInt(b.cpu_tier, 10) || 0;
                return dir === 'asc' ? tierA - tierB : tierB - tierA;
            }

            if (key === 'cpu') {
                const genA = parseInt(a.cpu_gen, 10) || 0;
                const genB = parseInt(b.cpu_gen, 10) || 0;
                if (genA !== genB) return dir === 'asc' ? genA - genB : genB - genA;
                const tierA = parseInt(a.cpu_tier, 10) || 0;
                const tierB = parseInt(b.cpu_tier, 10) || 0;
                if (tierA !== tierB) return dir === 'asc' ? tierA - tierB : tierB - tierA;
                return String(a.cpu || '').localeCompare(String(b.cpu || ''));
            }

            const strA = String(valA || '').trim();
            const strB = String(valB || '').trim();
            const cmp = strA.localeCompare(strB, undefined, { numeric: true, sensitivity: 'base' });
            return dir === 'asc' ? cmp : -cmp;
        }

        // 1. Stacked Sort Engine
        function applySort() {
            if (!sortStack || sortStack.length === 0) return;

            filteredItems.sort((a, b) => {
                for (let i = 0; i < sortStack.length; i++) {
                    const cmp = compareField(a, b, sortStack[i].key, sortStack[i].dir);
                    if (cmp !== 0) return cmp;
                }
                return 0;
            });
        }

        // 1.1 Update Sort UI (Headers + Stacked Sort Chips Bar)
        function updateSortUI() {
            // Update Headers
            sortableHeaders.forEach(th => {
                th.classList.remove('sorted-asc', 'sorted-desc');
                const key = th.getAttribute('data-sort');
                const ind = th.querySelector('.sort-indicator');

                if (key === 'cpu') {
                    // Special smart CPU handling below
                    return;
                }

                const stackIndex = sortStack.findIndex(s => s.key === key);
                if (stackIndex === -1) {
                    if (ind) ind.textContent = '⇅';
                } else {
                    const item = sortStack[stackIndex];
                    th.classList.add(item.dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    const superNum = SUPERSCRIPT_NUMS[stackIndex] || `${stackIndex + 1}`;
                    const arrow = item.dir === 'asc' ? '▲' : '▼';
                    if (ind) {
                        ind.textContent = `${superNum}${arrow}`;
                    }
                }
            });

            // Update CPU header hint & indicators
            const cpuTh = document.querySelector('.col-cpu-header');
            const cpuBadge = document.getElementById('wh-cpu-sort-badge');
            const cpuInd = document.getElementById('wh-cpu-indicator');
            const genIdx = sortStack.findIndex(s => s.key === 'cpu_gen');
            const tierIdx = sortStack.findIndex(s => s.key === 'cpu_tier');

            if (cpuBadge) {
                if (genIdx !== -1 && tierIdx !== -1) {
                    if (genIdx < tierIdx) {
                        cpuBadge.textContent = sortStack[genIdx].dir === 'desc' ? '⚡ Gen ➔ Tier' : 'Gen ▲ ➔ Tier';
                        cpuBadge.classList.add('active');
                    } else {
                        cpuBadge.textContent = sortStack[tierIdx].dir === 'desc' ? '⚡ Tier ➔ Gen' : 'Tier ▲ ➔ Gen';
                        cpuBadge.classList.add('active');
                    }
                } else if (genIdx !== -1) {
                    cpuBadge.textContent = `Gen ${sortStack[genIdx].dir === 'desc' ? '▼' : '▲'}`;
                    cpuBadge.classList.add('active');
                } else if (tierIdx !== -1) {
                    cpuBadge.textContent = `Tier ${sortStack[tierIdx].dir === 'desc' ? '▼' : '▲'}`;
                    cpuBadge.classList.add('active');
                } else {
                    cpuBadge.textContent = 'Gen ➔ Tier';
                    cpuBadge.classList.remove('active');
                }
            }

            if (cpuTh && cpuInd) {
                if (genIdx !== -1 || tierIdx !== -1) {
                    cpuTh.classList.add('sorted-asc');
                    const firstIdx = (genIdx !== -1 && tierIdx !== -1) ? Math.min(genIdx, tierIdx) : Math.max(genIdx, tierIdx);
                    const arrow = (genIdx !== -1) ? (sortStack[genIdx].dir === 'desc' ? '▼' : '▲') : (sortStack[tierIdx].dir === 'desc' ? '▼' : '▲');
                    cpuInd.textContent = `${SUPERSCRIPT_NUMS[firstIdx] || ''}${arrow}`;
                } else {
                    cpuTh.classList.remove('sorted-asc', 'sorted-desc');
                    cpuInd.textContent = '⇅';
                }
            }

            // Update Stacked Sort Bar
            if (!stackedBar || !chipsContainer) return;

            if (sortStack.length === 0) {
                stackedBar.style.display = 'none';
                chipsContainer.innerHTML = '';
                return;
            }

            stackedBar.style.display = 'flex';
            let chipsHtml = '';

            sortStack.forEach((sort, idx) => {
                const label = COLUMN_LABELS[sort.key] || sort.key.toUpperCase();
                const arrow = sort.dir === 'asc' ? '▲' : '▼';

                if (idx > 0) {
                    chipsHtml += `<span class="stacked-sort-arrow">➔</span>`;
                }

                chipsHtml += `
                    <div class="stacked-sort-chip" data-key="${sort.key}" title="Click to toggle direction, or ✖ to remove">
                        <span class="stacked-sort-chip-num">${idx + 1}.</span>
                        <span class="stacked-sort-chip-label">${escapeHtml(label)}</span>
                        <button type="button" class="stacked-sort-chip-dir" data-key="${sort.key}" title="Toggle direction (${sort.dir.toUpperCase()})">${arrow}</button>
                        <button type="button" class="stacked-sort-chip-remove" data-key="${sort.key}" title="Remove from sort stack">✖</button>
                    </div>
                `;
            });

            chipsContainer.innerHTML = chipsHtml;
        }

        // 2. Render Table Rows for Current Page
        function renderTable() {
            if (!tbody) return;

            const total = filteredItems.length;
            const size = (pageSize === 'all') ? total : parseInt(pageSize, 10);
            const totalPages = Math.max(1, Math.ceil(total / (size || 1)));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * size;
            const endIndex = Math.min(startIndex + size, total);
            const pageItems = (pageSize === 'all') ? filteredItems : filteredItems.slice(startIndex, endIndex);

            // Compute total units for filtered items
            let filteredUnitsTotal = 0;
            for (let i = 0; i < total; i++) {
                filteredUnitsTotal += (filteredItems[i].qty || 0);
            }

            // Update stats
            if (totalFiltered) totalFiltered.textContent = total.toLocaleString();
            if (totalFilteredTop) totalFilteredTop.textContent = total.toLocaleString();
            if (visibleCount) visibleCount.textContent = (endIndex - startIndex).toLocaleString();
            if (visibleUnits) visibleUnits.textContent = filteredUnitsTotal.toLocaleString();
            if (pageRange) {
                pageRange.textContent = total === 0 ? '0' : `${(startIndex + 1).toLocaleString()}-${endIndex.toLocaleString()}`;
            }
            if (pageInfo) {
                pageInfo.textContent = `Page ${currentPage.toLocaleString()} of ${totalPages.toLocaleString()}`;
            }

            if (prevBtn) prevBtn.disabled = (currentPage <= 1);
            if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);

            if (total === 0) {
                tbody.innerHTML = `
                    <tr id="wh-no-results-row">
                        <td colspan="12" style="text-align: center; padding: 3rem; color: var(--text-dim);">
                            🔍 No inventory items match your search criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            for (let i = 0; i < pageItems.length; i++) {
                const item = pageItems[i];
                const secClass = (item.sector || 'other').toLowerCase();
                html += `
                    <tr class="wh-stock-row"
                        data-id="${item.id}"
                        data-sector="${escapeHtml(item.sector)}"
                        data-location="${escapeHtml(item.location)}"
                        data-brand="${escapeHtml(item.brand)}"
                        data-model="${escapeHtml(item.model)}"
                        data-cpu="${escapeHtml(item.cpu)}"
                        data-ram="${escapeHtml(item.ram)}"
                        data-storage="${escapeHtml(item.storage)}"
                        data-condition="${escapeHtml(item.condition)}"
                        data-notes="${escapeHtml(item.notes)}"
                        data-price="${escapeHtml(item.price)}"
                        data-qty="${item.qty}">
                        
                        <td>
                            <div style="padding: 0 4px;">
                                <span class="sector-tag ${secClass}" title="Sector: ${escapeHtml(item.sector)}">${escapeHtml(item.sector)}</span>
                            </div>
                        </td>
                        <td class="text-center font-bold" style="font-size: 0.88rem;" title="${item.qty} units">
                            ${item.qty}
                        </td>
                        <td class="text-center cell-location" style="color: var(--text-dim); font-size: 0.82rem; font-weight: 600; white-space: nowrap;" title="Location: ${escapeHtml(item.location)}">
                            ${escapeHtml(item.location)}
                        </td>
                        <td style="font-weight: 600;" title="${escapeHtml(item.brand)}">
                            ${escapeHtml(item.brand)}
                        </td>
                        <td class="font-bold cell-model" style="color: var(--text-main);" title="${escapeHtml(item.model)}">
                            ${escapeHtml(item.model)}
                        </td>
                        <td class="cell-cpu" style="font-size: 0.82rem;" title="${escapeHtml(item.cpu)}">
                            ${item.cpu_tier_code && item.cpu_tier_code !== 'other'
                                ? `<span class="cpu-tier-badge tier-${escapeHtml(item.cpu_tier_code)}">${escapeHtml(item.cpu_tier_code.toUpperCase())}</span>`
                                : ''}
                            <span class="cpu-spec-txt">${escapeHtml(item.cpu_gen_label || item.cpu)}</span>
                        </td>
                        <td class="text-center" style="font-size: 0.82rem;" title="RAM: ${escapeHtml(item.ram)}">
                            ${escapeHtml(item.ram)}
                        </td>
                        <td class="text-center" style="font-size: 0.82rem;" title="Storage: ${escapeHtml(item.storage)}">
                            ${escapeHtml(item.storage)}
                        </td>
                        <td class="text-center" title="Condition: ${escapeHtml(item.condition)}">
                            <span class="condition-tag">${escapeHtml(item.condition)}</span>
                        </td>
                        <td style="color: var(--text-dim); font-size: 0.82rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(item.notes)}">
                            ${escapeHtml(item.notes)}
                        </td>
                        <td class="text-right font-bold price-tag" title="$${parseFloat(item.price || 0).toFixed(2)}">
                            $${parseFloat(item.price || 0).toFixed(2)}
                        </td>
                        <td style="text-align: center; padding: 0 4px;">
                            <button type="button" class="btn-prefill-template" title="Prefill template form above"
                                    data-sector="${escapeHtml(item.sector)}"
                                    data-brand="${escapeHtml(item.brand)}"
                                    data-model="${escapeHtml(item.model)}"
                                    data-cpu="${escapeHtml(item.cpu)}"
                                    data-ram="${escapeHtml(item.ram)}"
                                    data-storage="${escapeHtml(item.storage)}"
                                    data-condition="${escapeHtml(item.condition)}"
                                    data-notes="${escapeHtml(item.notes)}"
                                    data-price="${escapeHtml(item.price)}">
                                ⚡ Prefill
                            </button>
                        </td>
                    </tr>
                `;
            }
            tbody.innerHTML = html;
        }

        // 3. Flexible Multi-Term Search Logic
        function filterWarehouseTable(isSearchTriggered = false) {
            const rawQuery = searchInput ? searchInput.value : '';
            const query = rawQuery.toLowerCase().trim();
            const terms = query.split(/\s+/).filter(t => t.length > 0);
            
            if (clearBtn) {
                clearBtn.style.display = rawQuery.length > 0 ? 'block' : 'none';
            }

            // When a user searches, change the Rows view to all.
            // If the user writes something in the text box and deletes it, keep view rows default to ALL.
            if (isSearchTriggered) {
                pageSize = 'all';
                if (pageSizeSelect) {
                    pageSizeSelect.value = 'all';
                }
            }

            filteredItems = ALL_INVENTORY.filter(item => {
                const itemSector = (item.sector || '').toLowerCase();
                const matchesSector = (activeSector === 'All' || itemSector === activeSector.toLowerCase());
                if (!matchesSector) return false;

                if (terms.length === 0) return true;
                return terms.every(term => item.search.includes(term));
            });

            if (sortStack.length > 0) {
                applySort();
            }

            currentPage = 1;
            renderTable();
        }

        // Live input search listener (debounced slightly for performance)
        let searchTimeout = null;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => filterWarehouseTable(true), 40);
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    filterWarehouseTable(true);
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                filterWarehouseTable(true);
            });
        }

        // 4. Sector Filter Pills
        sectorPills.forEach(pill => {
            pill.addEventListener('click', function() {
                sectorPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                activeSector = this.getAttribute('data-sector') || 'All';
                filterWarehouseTable();
            });
        });

        // 5. Stacked Header Sorting Event Listeners
        sortableHeaders.forEach(th => {
            th.addEventListener('click', function(e) {
                const sortKey = this.getAttribute('data-sort');
                if (!sortKey) return;

                if (sortKey === 'cpu') {
                    // Smart CPU Stack Controller
                    const genIdx = sortStack.findIndex(s => s.key === 'cpu_gen');
                    const tierIdx = sortStack.findIndex(s => s.key === 'cpu_tier');

                    if (genIdx === -1 && tierIdx === -1) {
                        // State 1: Gen (desc) ➔ Tier (desc) - Newest Gen, Fastest Tier within Gen
                        sortStack.push({ key: 'cpu_gen', dir: 'desc' });
                        sortStack.push({ key: 'cpu_tier', dir: 'desc' });
                    } else if (genIdx !== -1 && tierIdx !== -1 && genIdx < tierIdx && sortStack[genIdx].dir === 'desc') {
                        // State 2: Tier (desc) ➔ Gen (desc) - Flagship Tier First (i7/i9 first), then newest to oldest
                        const otherSorts = sortStack.filter(s => s.key !== 'cpu_gen' && s.key !== 'cpu_tier');
                        sortStack = otherSorts;
                        sortStack.push({ key: 'cpu_tier', dir: 'desc' });
                        sortStack.push({ key: 'cpu_gen', dir: 'desc' });
                    } else if (tierIdx !== -1 && genIdx !== -1 && tierIdx < genIdx && sortStack[tierIdx].dir === 'desc') {
                        // State 3: Gen (asc) ➔ Tier (asc) - Budget Mode (Oldest Gen First, Entry Tier First)
                        const otherSorts = sortStack.filter(s => s.key !== 'cpu_gen' && s.key !== 'cpu_tier');
                        sortStack = otherSorts;
                        sortStack.push({ key: 'cpu_gen', dir: 'asc' });
                        sortStack.push({ key: 'cpu_tier', dir: 'asc' });
                    } else {
                        // State 4: Reset CPU from stack
                        sortStack = sortStack.filter(s => s.key !== 'cpu_gen' && s.key !== 'cpu_tier');
                    }
                } else {
                    const existingIndex = sortStack.findIndex(s => s.key === sortKey);
                    if (existingIndex !== -1) {
                        // Already in stack: toggle direction
                        sortStack[existingIndex].dir = (sortStack[existingIndex].dir === 'asc') ? 'desc' : 'asc';
                    } else {
                        // Append to sort stack
                        const defaultDir = (sortKey === 'qty' || sortKey === 'price') ? 'desc' : 'asc';
                        sortStack.push({ key: sortKey, dir: defaultDir });
                    }
                }

                applySort();
                currentPage = 1;
                renderTable();
                updateSortUI();
            });
        });

        // 5.1 Stacked Sort Chips & Clear Sorts Actions
        if (chipsContainer) {
            chipsContainer.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.stacked-sort-chip-remove');
                const dirBtn = e.target.closest('.stacked-sort-chip-dir');
                const chip = e.target.closest('.stacked-sort-chip');

                if (removeBtn) {
                    e.stopPropagation();
                    const key = removeBtn.getAttribute('data-key');
                    sortStack = sortStack.filter(s => s.key !== key);
                    filterWarehouseTable(false);
                    updateSortUI();
                    return;
                }

                if (dirBtn || chip) {
                    e.stopPropagation();
                    const targetEl = dirBtn || chip;
                    const key = targetEl.getAttribute('data-key');
                    const item = sortStack.find(s => s.key === key);
                    if (item) {
                        item.dir = (item.dir === 'asc') ? 'desc' : 'asc';
                        applySort();
                        currentPage = 1;
                        renderTable();
                        updateSortUI();
                    }
                }
            });
        }

        if (clearSortsBtn) {
            clearSortsBtn.addEventListener('click', function() {
                sortStack = [];
                filterWarehouseTable(false);
                updateSortUI();
            });
        }

        // 4. Pagination Controls Handlers
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderTable();
                    if (tableWrapper) tableWrapper.scrollTop = 0;
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const size = (pageSize === 'all') ? filteredItems.length : parseInt(pageSize, 10);
                const totalPages = Math.ceil(filteredItems.length / (size || 1));
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTable();
                    if (tableWrapper) tableWrapper.scrollTop = 0;
                }
            });
        }

        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function() {
                pageSize = this.value;
                currentPage = 1;
                renderTable();
            });
        }

        // 6. Template Prefill Action (Delegated on tbody)
        if (tbody) {
            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.btn-prefill-template');
                if (!btn) return;

                e.preventDefault();
                const brand = (btn.getAttribute('data-brand') || '').trim();
                const model = (btn.getAttribute('data-model') || '').trim();
                const sector = (btn.getAttribute('data-sector') || '').trim();
                const cpu = (btn.getAttribute('data-cpu') || '').trim();
                const ram = (btn.getAttribute('data-ram') || '').trim();
                const storage = (btn.getAttribute('data-storage') || '').trim();
                const condition = (btn.getAttribute('data-condition') || '').trim();
                const notes = (btn.getAttribute('data-notes') || '').trim();

                const fullName = `${brand} ${model}`.trim();

                // 1. Model Name
                const modelInput = document.getElementById('model_name');
                if (modelInput) {
                    modelInput.value = fullName;
                }

                // 2. Category Mapping
                const categorySelect = document.getElementById('category');
                if (categorySelect) {
                    let targetCategory = 'Laptop';
                    const secLow = sector.toLowerCase();
                    const brandLow = brand.toLowerCase();
                    const modelLow = model.toLowerCase();

                    if (secLow.includes('desktop')) {
                        targetCategory = 'Desktop';
                    } else if (secLow.includes('gaming')) {
                        if (brandLow.includes('sony') || modelLow.includes('ps3') || modelLow.includes('ps4') || modelLow.includes('ps5') || modelLow.includes('xbox')) {
                            targetCategory = 'Part';
                        } else {
                            targetCategory = 'Laptop';
                        }
                    } else if (modelLow.includes('server') || modelLow.includes('poweredge')) {
                        targetCategory = 'Server';
                    } else if (modelLow.includes('workstation') || modelLow.includes('precision tower')) {
                        targetCategory = 'Workstation';
                    }

                    for (let opt of categorySelect.options) {
                        if (opt.value.toLowerCase() === targetCategory.toLowerCase()) {
                            categorySelect.value = opt.value;
                            break;
                        }
                    }
                }

                // 3. Base Specifications formatting
                const specList = [];
                if (cpu && cpu !== 'No') specList.push(cpu);
                if (ram && ram !== 'No') specList.push(ram.toUpperCase().includes('RAM') || ram.toUpperCase().includes('GB') ? ram : `${ram} RAM`);
                if (storage && storage !== 'No') specList.push(storage.toUpperCase().includes('SSD') || storage.toUpperCase().includes('HDD') || storage.toUpperCase().includes('GB') ? storage : `${storage} Storage`);
                if (condition) specList.push(condition);
                if (notes) specList.push(notes);

                const baseSpecsText = document.getElementById('base_specs');
                if (baseSpecsText) {
                    baseSpecsText.value = specList.join(', ');
                }

                // 4. Marketing Copy generation
                const copyText = document.getElementById('marketing_copy');
                if (copyText) {
                    const existingCopy = copyText.value.trim();
                    if (!existingCopy || confirm(`Prefill ad description for ${fullName}?`)) {
                        let pitch = `Tested and verified ${fullName}`;
                        if (specList.length > 0) {
                            pitch += ` configured with ${specList.slice(0, 3).join(', ')}`;
                        }
                        pitch += `. Professional tier hardware inspected for reliable everyday performance and productivity. Includes verified ports, tested battery health, and warranty coverage.`;
                        copyText.value = pitch;
                    }
                }

                // 5. Switch to Form Section & Highlight
                if (typeof window.switchSectionView === 'function') {
                    window.switchSectionView('form');
                }
                const formCard = document.getElementById('template-form-card');
                if (formCard) {
                    formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    formCard.classList.remove('form-attention-flash');
                    void formCard.offsetWidth; // trigger CSS reflow
                    formCard.classList.add('form-attention-flash');
                }

                if (window.notify) {
                    window.notify(`Template form prefilled with ${fullName}!`, 'success', '⚡ Prefilled');
                }
            });
        }

        // 7. CSV Export with Active Sort Guaranteed
        window.exportWarehouseStockCSV = function() {
            // Guarantee current filtered records are sorted according to active sortStack
            if (typeof applySort === 'function' && sortStack && sortStack.length > 0) {
                applySort();
            }

            const headers = [
                "Sector",
                "Quantity",
                "Location",
                "Brand",
                "Model",
                "CPU_Gen",
                "CPU_Tier",
                "CPU_Series",
                "RAM",
                "Storage",
                "Condition",
                "Notes",
                "Price"
            ];
            let csv = headers.map(h => `"${h}"`).join(',') + "\n";
            let count = 0;

            filteredItems.forEach(item => {
                const cols = [
                    item.sector || '',
                    item.qty || '0',
                    item.location || '',
                    item.brand || '',
                    item.model || '',
                    item.cpu_gen_label || (item.cpu_gen ? `${item.cpu_gen}th Gen` : ''),
                    item.cpu_tier_name || (item.cpu_tier_code ? item.cpu_tier_code.toUpperCase() : ''),
                    item.cpu || '',
                    item.ram || '',
                    item.storage || '',
                    item.condition || '',
                    item.notes || '',
                    item.price || '0.00'
                ];
                csv += cols.map(val => `"${(String(val) || '').replace(/"/g, '""')}"`).join(',') + "\n";
                count++;
            });

            // Include UTF-8 BOM so Microsoft Excel cleanly renders encoding
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;

            let sortSuffix = '';
            let sortDesc = '';
            if (sortStack && sortStack.length > 0) {
                sortSuffix = '_' + sortStack.map(s => `${s.key}_${s.dir}`).join('_');
                sortDesc = ' (sorted by ' + sortStack.map(s => `${COLUMN_LABELS[s.key] || s.key} ${s.dir === 'asc' ? '▲' : '▼'}`).join(', ') + ')';
            }

            const dateStr = new Date().toISOString().slice(0, 10);
            a.download = `warehouse_stock${sortSuffix}_${dateStr}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            if (window.notify) {
                window.notify(`Exported ${count.toLocaleString()} items to CSV${sortDesc}!`, 'success', '📥 CSV Exported');
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWarehouseSpreadsheet);
    } else {
        initWarehouseSpreadsheet();
    }
})();
</script>

