<?php
/**
 * Bulk Warehouse Import (Main Orchestrator)
 * Modularized view coordinating Parser Engine, Upload Dropzone, Verification Spreadsheet Table, and Zone Migrations.
 */
include 'core/warehouse_db.php';
include 'core/auth.php';

// 1. Load Parsing Engine & Pricing Matrix Lookup
require_once __DIR__ . '/partials/import_warehouse/parser_engine.php';

// 2. Process Actions (AJAX cell updates, file uploads, import confirmation, cancels)
require_once __DIR__ . '/partials/import_warehouse/actions.php';
?>

<div class="orders-container" style="animation: fadeInDown 0.4s ease-out; width: 100%; max-width: 1400px; margin: 0 auto; padding: 20px;">
    <header class="orders-header" style="margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-main); margin-bottom: 5px;">Migrate CSV Manifest to Working Zones</h1>
            <p style="color: var(--text-secondary); font-size: 1rem;">Import inventory sheets, dynamically register new shelves, and sanitize tech specs.</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <a href="../sampleWHdata/intake.pdf" target="_blank" class="btn-main" style="background: white; color: #166534; border: 1px solid #bbf7d0; box-shadow: var(--shadow-sm); display: inline-flex; align-items: center; gap: 8px; font-weight: 800; text-decoration: none; padding: 10px 18px; border-radius: 12px;">
                🖨️ Print Intake Form
            </a>
            <a href="index.php?view=warehouse&sector=<?= urlencode($active_param_sector) ?><?= $active_param_loc ? '&loc=' . urlencode($active_param_loc) : '' ?><?= $active_param_zone ? '&zone=' . urlencode($active_param_zone) : '' ?>" class="btn-main" style="background: #f1f5f9; color: #475569; box-shadow: none; border: 1px solid #e2e8f0;">
                ← Back to Warehouse
            </a>
        </div>
    </header>

    <!-- State data hydration for JavaScript -->
    <script id="import-warehouse-state" type="application/json">
    <?= json_encode([
        'zone_locations_map' => $zone_locations_map,
        'working_zones' => $working_zones,
        'active_param_sector' => $active_param_sector,
        'active_param_loc' => $active_param_loc,
        'active_param_zone' => $active_param_zone
    ]) ?>
    </script>

    <?php if ($message): ?>
        <div style="background: #ecfdf5; color: #065f46; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #d1fae5; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">✅</span> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fef2f2; color: #991b1b; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">⚠️</span> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$preview_mode && empty($_SESSION['import_rows'])): ?>
        <!-- 3. Upload Screen & Guidelines Card -->
        <?php include __DIR__ . '/partials/import_warehouse/upload_card.php'; ?>
    <?php else: ?>
        <!-- 4. Preview & Sanitization Verification Report -->
        <?php include __DIR__ . '/partials/import_warehouse/preview_table.php'; ?>
    <?php endif; ?>
</div>
