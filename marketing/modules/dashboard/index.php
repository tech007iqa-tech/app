<?php
/**
 * Dashboard Module - Marketing Hub Command Center
 * Displays high-level stats, smart inventory opportunities, quick actions, and audit feed.
 */

// Ensure dependencies
$marketingDb = get_marketing_db();
$warehouseDb = get_warehouse_db();
$labelsDb = get_labels_db();
$crmDb = get_master_crm_db();

// 1. Fetch Key Metric Counters Resiliently
$leadCount = 0;
$campaignCount = 0;
$photoCount = 0;
$inventoryCount = 0;

try {
    if ($crmDb) {
        $leadCount = (int)($crmDb->query("SELECT COUNT(*) FROM customers WHERE account_status = 'Lead'")->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    error_log("Dashboard leadCount query failed: " . $e->getMessage());
}

try {
    if ($marketingDb) {
        $campaignCount = (int)($marketingDb->query("SELECT COUNT(*) FROM campaigns WHERE status = 'Active'")->fetchColumn() ?: 0);
        $photoCount = (int)($marketingDb->query("SELECT COUNT(*) FROM photos")->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    error_log("Dashboard marketing counters query failed: " . $e->getMessage());
}

try {
    if ($warehouseDb) {
        $inventoryCount = (int)($warehouseDb->query("SELECT COUNT(DISTINCT model) FROM inventory WHERE quantity > 0 AND (status IS NULL OR status != 'Pending Delete') AND LOWER(brand) NOT LIKE '%mix%' AND LOWER(model) NOT LIKE '%mix%'")->fetchColumn() ?: 0);
    } elseif ($labelsDb) {
        $inventoryCount = (int)($labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    error_log("Dashboard inventoryCount query failed: " . $e->getMessage());
}

// 2. Calculate Smart Opportunities (Based on live inventory from orders/pages/warehouse.php)
$opportunities = [];
if ($marketingDb && ($warehouseDb || $labelsDb)) {
    try {
        $tmplCheckStmt = $marketingDb->prepare("SELECT id, model_name FROM model_templates WHERE LOWER(TRIM(model_name)) = LOWER(TRIM(?))");
        $photoCheckStmt = $marketingDb->prepare("SELECT COUNT(*) FROM photos WHERE LOWER(TRIM(model_name)) = LOWER(TRIM(?))");

        if ($warehouseDb) {
            // Query top stock from Warehouse Control Center (warehouse.db inventory table)
            $topStockStmt = $warehouseDb->query("
                SELECT brand, model, specs_json, location_code, sector, SUM(quantity) as qty 
                FROM inventory 
                WHERE quantity > 0 
                  AND (status IS NULL OR status != 'Pending Delete')
                  AND LOWER(brand) NOT LIKE '%mix%' AND LOWER(model) NOT LIKE '%mix%'
                GROUP BY brand, model, specs_json
                HAVING qty > 0 
                ORDER BY qty DESC 
                LIMIT 6
            ");
            $topStock = $topStockStmt ? $topStockStmt->fetchAll() : [];

            foreach ($topStock as $item) {
                $specs = json_decode($item['specs_json'] ?? '', true) ?: [];
                $series = trim($specs['series'] ?? '');
                $cpu = trim($specs['cpu'] ?? '') ?: trim($specs['gen'] ?? '');
                $ram = trim($specs['ram'] ?? '');
                $storage = trim($specs['storage'] ?? '');
                $condition = trim($specs['condition'] ?? 'A Grade');
                $sector = trim($item['sector'] ?? 'Laptops');
                $location = !empty($item['location_code']) ? $item['location_code'] : 'UNSPECIFIED';

                // Compose clean full model title
                $brand = trim($item['brand'] ?? '');
                $model = trim($item['model'] ?? '');
                $full_model = $brand;
                if ($model && stripos($full_model, $model) === false) {
                    $full_model .= " " . $model;
                }
                if ($series && stripos($full_model, $series) === false) {
                    $full_model .= " " . $series;
                }
                $full_model = trim($full_model) ?: ($model ?: 'Hardware Asset');
                $qty = (int)($item['qty'] ?? 0);

                // Build spec summary chips
                $specList = array_filter([$cpu, $ram, $storage, $condition]);
                $specString = !empty($specList) ? "(" . implode(" | ", array_map('h', $specList)) . ")" : "(" . h($sector) . ")";

                // Cross-reference with model templates
                $tmplCheckStmt->execute([$full_model]);
                $template = $tmplCheckStmt->fetch();
                if (!$template && $model !== $full_model) {
                    $tmplCheckStmt->execute([$model]);
                    $template = $tmplCheckStmt->fetch();
                }

                $meta_payload = [
                    'model'    => $full_model,
                    'qty'      => $qty,
                    'location' => $location,
                    'sector'   => $sector,
                    'specs'    => [
                        'CPU'     => $cpu,
                        'RAM'     => $ram,
                        'Storage' => $storage,
                        'Grade'   => $condition
                    ]
                ];

                if (!$template) {
                    $prefill_specs = "BRAND: {$brand}\nMODEL: {$full_model}\nSECTOR: {$sector}\nCPU: {$cpu}\nRAM: {$ram}\nSTORAGE: {$storage}\nCONDITION: {$condition}";
                    $isWorkstation = (stripos($full_model, 'Precision') !== false || stripos($full_model, 'ZBook') !== false || stripos($full_model, 'Toughbook') !== false);
                    $pitch = "Looking for high-volume, reliable inventory? The {$full_model} delivers robust performance tailored for " . ($isWorkstation ? "demanding engineering, CAD, and heavy-duty workstation workloads." : "efficient corporate deployment.") . "\n\n✅ Fully Audited & Tested in Warehouse\n✅ Bulk Pallet Quantities Available ({$qty} units ready to ship)\n✅ Palletized & Ready for Immediate Fulfillment";

                    $opportunities[] = [
                        'type'   => 'NEED_TEMPLATE',
                        'title'  => 'Missing Template',
                        'desc'   => "You have <strong>{$qty}x " . h($full_model) . "</strong> at " . UI::badge("📍 " . h($location), "customer") . " <br><span style='font-size:0.8rem; color:var(--text-secondary);'>{$specString}</span>",
                        'action' => '?page=model_templates&prefill_model=' . urlencode($full_model) . '&prefill_specs=' . urlencode($prefill_specs) . '&prefill_copy=' . urlencode($pitch),
                        'btn'    => 'Create Template',
                        'meta'   => $meta_payload
                    ];
                } else {
                    $photoCheckStmt->execute([$full_model]);
                    $hasPhoto = ((int)$photoCheckStmt->fetchColumn()) > 0;
                    if (!$hasPhoto && $model !== $full_model) {
                        $photoCheckStmt->execute([$model]);
                        $hasPhoto = ((int)$photoCheckStmt->fetchColumn()) > 0;
                    }

                    if (!$hasPhoto) {
                        $opportunities[] = [
                            'type'   => 'NEED_PHOTO',
                            'title'  => 'Photo Needed',
                            'desc'   => "Template exists for <strong>" . h($full_model) . "</strong> ({$qty} units at 📍 " . h($location) . "), but no photos are in the bucket. Photos increase conversion!",
                            'action' => '?page=photo_bucket&model_name=' . urlencode($full_model),
                            'btn'    => 'Upload Photo',
                            'meta'   => $meta_payload
                        ];
                    } else {
                        $opportunities[] = [
                            'type'   => 'READY',
                            'title'  => 'Ready to Promote',
                            'desc'   => "Content and Photos are READY for <strong>" . h($full_model) . "</strong> ({$qty} units in stock at 📍 " . h($location) . "). Generate an ad now!",
                            'action' => '?page=ad_generator&model=' . urlencode($full_model),
                            'btn'    => 'Generate Ad',
                            'meta'   => $meta_payload
                        ];
                    }
                }
            }
        } elseif ($labelsDb) {
            // Legacy fallback to labelsDb
            $topStockStmt = $labelsDb->query("
                SELECT brand, model, cpu_gen, ram, storage, warehouse_location, COUNT(*) as qty 
                FROM items 
                WHERE status = 'In Warehouse' 
                GROUP BY brand, model 
                HAVING qty > 0 
                ORDER BY qty DESC 
                LIMIT 6
            ");
            $topStock = $topStockStmt ? $topStockStmt->fetchAll() : [];

            foreach ($topStock as $item) {
                $specString = "(" . h($item['cpu_gen'] ?? 'N/A') . " | " . h($item['ram'] ?? 'N/A') . " | " . h($item['storage'] ?? 'N/A') . ")";
                $location = !empty($item['warehouse_location']) ? $item['warehouse_location'] : 'UNSPECIFIED';
                $model = $item['model'] ?? 'Unknown Model';
                $qty = (int)($item['qty'] ?? 0);

                $tmplCheckStmt->execute([$model]);
                $template = $tmplCheckStmt->fetch();

                if (!$template) {
                    $prefill_specs = "CPU: " . ($item['cpu_gen'] ?? '') . "\nRAM: " . ($item['ram'] ?? '') . "\nSTORAGE: " . ($item['storage'] ?? '') . "\nOS: Windows 10/11 Pro Ready";
                    $opportunities[] = [
                        'type'   => 'NEED_TEMPLATE',
                        'title'  => 'Missing Content',
                        'desc'   => "You have <strong>{$qty}x " . h($model) . "</strong> at " . UI::badge("📍 " . h($location), "customer") . " <br><span style='font-size:0.8rem; color:var(--text-secondary);'>{$specString}</span>",
                        'action' => '?page=model_templates&prefill_model=' . urlencode($model) . '&prefill_specs=' . urlencode($prefill_specs),
                        'btn'    => 'Create Template'
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        error_log("Error loading smart opportunities: " . $e->getMessage());
    }
}
?>

<header class="page-header">
    <div>
        <h1>Welcome to <?= h(APP_NAME); ?></h1>
        <p>Your modular marketing command center & inventory promotion hub.</p>
    </div>
</header>

<div class="dashboard-grid">
    <?php
    echo UI::stat_card("Lead Statistics", "$leadCount Leads Tracked", "lead-summary");
    echo UI::stat_card("Active Campaigns", "$campaignCount Active", "campaign-summary");
    echo UI::stat_card("Marketable Stock", "$inventoryCount Models in Bulk", "inventory-summary");
    echo UI::stat_card("Photo Assets", "$photoCount In Bucket", "photo-summary");
    ?>

    <!-- SMART OPPORTUNITIES (IDEAS ENGINE) -->
    <section class="card smart-opportunities">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="color: var(--accent-primary); margin: 0;">💡 Smart Opportunities</h2>
            <span style="font-size: 0.85rem; color: var(--text-secondary);">Cross-referenced with warehouse live inventory</span>
        </div>
        <div class="opportunities-grid">
            <?php
            if (empty($opportunities)) {
                echo "<p style='color: var(--text-dim); padding: 1.5rem 0;'>No immediate opportunities found. All top models have templates and assets!</p>";
            } else {
                foreach ($opportunities as $opp) {
                    echo UI::opportunity_card($opp['title'], $opp['desc'], $opp['action'], $opp['btn'], $opp['type']);
                }
            }
            ?>
        </div>
    </section>

    <!-- QUICK ACTIONS HUB -->
    <section class="card quick-actions">
        <h2>⚡ Quick Actions</h2>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
            <?php
            echo UI::action_button("Add New Lead", "?page=leads&action=add", "➕");
            echo UI::action_button("Create Ad", "?page=ad_generator", "📢", "background: var(--accent-primary);");
            echo UI::action_button("Photo Bucket", "?page=photo_bucket", "🖼️", "background: var(--accent-gradient);");
            echo UI::action_button("Update Specs", "?page=model_templates", "📚", "background: var(--text-main);");
            ?>
        </div>
    </section>

    <!-- RECENT ACTIVITY FEED -->
    <section class="card activity-feed">
        <h2>🕒 Recent Marketing Activity</h2>
        <div class="activity-list" style="margin-top: 1rem;">
            <?php
            try {
                $logs = $marketingDb->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 7")->fetchAll();
                if (empty($logs)) {
                    echo "<p style='color: var(--text-dim); text-align: center; padding: 2rem;'>No recent activity recorded.</p>";
                } else {
                    echo "<div style='display: flex; flex-direction: column; gap: 1rem;'>";
                    foreach ($logs as $log) {
                        $icon = '📝';
                        if ($log['action'] === 'CREATED') $icon = '✨';
                        if ($log['action'] === 'SYNCED') $icon = '🔄';
                        if ($log['action'] === 'GENERATED') $icon = '🔥';
                        if ($log['action'] === 'DELETED') $icon = '🗑️';
                        if ($log['action'] === 'UPLOADED') $icon = '📷';

                        $actor = !empty($log['user_name']) ? " (" . h($log['user_name']) . ")" : "";
                        $meta = h($log['entity_type']) . $actor . " • " . date('M j, g:i a', strtotime($log['timestamp']));
                        echo UI::activity_item($icon, h($log['summary'] ?? ''), $meta);
                    }
                    echo "</div>";
                }
            } catch (Throwable $e) {
                echo "<p style='color: var(--text-dim);'>Activity feed temporarily unavailable.</p>";
            }
            ?>
        </div>
    </section>
</div>
