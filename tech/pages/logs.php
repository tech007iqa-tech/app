<?php
require_once '../core/database.php';
require_once '../core/auth.php';

$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
$is_admin = (
    strtolower($_SESSION['role'] ?? '') === 'admin' || 
    strtolower($_SESSION['username'] ?? '') === 'admin' || 
    strpos(strtolower($_SESSION['role'] ?? ''), 'admin') !== false
);

$tech_list = [];
if ($is_admin) {
    try {
        $conn = Database::tech();
        $stmt_t = $conn->query("SELECT DISTINCT tech_id FROM logs WHERE tech_id != '' ORDER BY tech_id ASC");
        $tech_list = $stmt_t->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}
$selected_tech = htmlspecialchars($_GET['tech_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Logs | Technician Dashboard</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/logs.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme" style="background-color: #f8fafc;">

    <div class="breadcrumb-container" role="banner">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">📋</span> Hardware Logs
            </a>
        </nav>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($is_admin): ?>
            <a href="admin_audit.php" class="btn-settings" style="background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; font-weight: 700;">👥 Admin Audit</a>
            <?php endif; ?>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container">

        <div class="page-header flex-between" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1><span>📋</span> Hardware Logs &amp; Quality Control</h1>
                <p>Log laptop tests, scan serial barcodes, verify technical specs, print 2"×1" thermal labels, and track deliveries.</p>
            </div>
            <div>
                <button type="button" onclick="openIntakeModal()" class="btn-primary" style="padding: 14px 26px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: all 0.2s;">
                    <span style="font-size: 1.2rem;">➕</span>
                    <span>Add New Log (Full Technical Sheet)</span>
                </button>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════
             FULL TECHNICAL SHEET INTAKE MODAL (OVERLAY)
             ═════════════════════════════════════════════════════════════════════ -->
        <div id="techIntakeModal" class="intake-modal-backdrop" onclick="onModalBackdropClick(event)">
            <div class="intake-modal-dialog" id="techIntakeDialog" onclick="event.stopPropagation()">
                
                <!-- STICKY MODAL HEADER -->
                <div class="intake-modal-header">
                    <div class="intake-modal-title">
                        <span>🏷️</span>
                        <span>Hardware Intake &amp; Full Technical Sheet</span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 12px;">
                        <!-- Good / Bad Laptop Status Toggle -->
                        <div class="status-toggle" style="margin-bottom: 0;">
                            <button type="button" class="status-btn active good" id="btnIntakeGood" onclick="setIntakeStatus('Good')">✅ Good Laptop</button>
                            <button type="button" class="status-btn bad" id="btnIntakeBad" onclick="setIntakeStatus('Bad')">❌ Bad Laptop</button>
                        </div>
                        <button type="button" class="modal-fullscreen-btn" id="btnToggleFullscreen" onclick="toggleModalFullscreen()" title="Toggle Fullscreen (Edge to Edge)">⛶</button>
                        <button type="button" class="modal-close-btn" onclick="closeIntakeModal()" title="Close (Esc)">&times;</button>
                    </div>
                </div>

                <!-- FORM WRAPPER -->
                <form id="techIntakeForm" class="intake-form-wrapper" autocomplete="off">
                    <input type="hidden" id="intake_status" name="status" value="Good">
                    <input type="hidden" id="intake_label_id" name="label_id" value="">
                    <input type="hidden" id="intake_sync_labels" name="sync_labels" value="1">

                    <!-- SCROLLABLE MODAL BODY -->
                    <div class="intake-modal-body">
                        <div class="intake-layout-grid">
                            
                            <!-- ── LEFT COLUMN: FULL TECHNICAL INTAKE FORM ── -->
                            <div class="intake-form-column">

                                <!-- INTAKE MODE SWITCHER & CONTINUOUS RAPID INTAKE -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                                    <div class="intake-mode-switcher">
                                        <button type="button" class="mode-switch-btn active" id="btnModeSingle" onclick="switchIntakeMode('single')">
                                            <span>💻 Single Unit</span>
                                        </button>
                                        <button type="button" class="mode-switch-btn" id="btnModeBatch" onclick="switchIntakeMode('batch')">
                                            <span>📦 Batch / Lot Intake (Same Specs)</span>
                                        </button>
                                    </div>

                                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700; color: #475569; cursor: pointer; user-select: none;">
                                        <input type="checkbox" id="intake_continuous" checked style="accent-color: #2563eb; width: 16px; height: 16px;">
                                        <span>⚡ Continuous Rapid Intake (Keep specs ready)</span>
                                    </label>
                                </div>

                                <!-- BATCH / LOT INTAKE CARD (ACTIVE IN BATCH MODE) -->
                                <div id="batchIntakeCard" class="batch-intake-card hidden">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                        <div>
                                            <strong style="color: #166534; font-size: 0.95rem;">📦 Batch / Lot Intake Active</strong>
                                            <p style="margin: 2px 0 0 0; font-size: 0.78rem; color: #15803d;">All units in this batch inherit the specs below. Scan serial numbers one after another.</p>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <label style="font-size: 0.8rem; font-weight: 800; color: #166534;">BATCH / LOT #:</label>
                                            <input type="text" id="intake_batch_number" name="batch_number" placeholder="e.g. LOT-5400-A" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #86efac; font-weight: 800; font-size: 0.85rem; width: 160px; background: white;">
                                            <button type="button" onclick="autoGenerateBatchNumber()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #86efac; background: white; font-size: 0.75rem; font-weight: 800; cursor: pointer; color: #166534;" title="Generate new batch timestamp code">🎲 Auto</button>
                                        </div>
                                    </div>

                                    <div style="background: white; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <label style="font-size: 0.82rem; font-weight: 800; color: #166534; text-transform: uppercase;">Scan Serial Numbers for this Batch:</label>
                                            <span id="batchCountBadge" style="font-size: 0.8rem; font-weight: 800; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: 6px;">0 units queued</span>
                                        </div>
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <input type="text" id="batch_sn_scanner" placeholder="🔍 Scan barcode or type S/N and press Enter..." style="flex: 1; padding: 9px 12px; border-radius: 8px; border: 2px solid #22c55e; font-family: monospace; font-weight: 800; font-size: 0.95rem;" onkeydown="onBatchScannerKeyDown(event)" autocomplete="off">
                                            <button type="button" onclick="addBatchSerialFromInput()" class="btn-primary" style="padding: 9px 16px; border-radius: 8px; background: #16a34a; border: none; font-weight: 800; color: white; cursor: pointer;">➕ Add Unit</button>
                                        </div>
                                        <div id="batchSnChipsContainer" class="batch-sn-chips-container">
                                            <span style="color: #94a3b8; font-size: 0.8rem; font-style: italic; padding: 6px;">Scan barcode or type S/N and press Enter to queue multiple laptops...</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 0.78rem;">
                                            <span style="color: #64748b;">Or set bulk count (no individual serials):</span>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <label style="font-weight: 700; color: #475569;">Bulk Qty:</label>
                                                <input type="number" id="intake_batch_qty" min="1" value="1" style="width: 70px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 700;" oninput="onBatchQtyChange(this.value)">
                                            </div>
                                        </div>
                                        <input type="hidden" id="intake_batch_serials" name="batch_serials" value="">
                                    </div>
                                </div>

                                <!-- ONE-TOUCH QUICK PRESETS -->
                                <div class="preset-shelf-container">
                                    <div class="preset-shelf-header">
                                        <div class="preset-shelf-title">
                                            <span>⚡</span>
                                            <span>One-Touch Quick Presets (Click to autofill)</span>
                                        </div>
                                        <span style="font-size: 0.75rem; color: #64748b;">Saves 90% of typing</span>
                                    </div>
                                    <div class="preset-cards-grid">
                                        <div class="preset-pill-card" onclick="applyPreset('fleet-office')">
                                            <div class="preset-card-title">⚡ Office Fleet</div>
                                            <div class="preset-card-subtitle">Dell · i5-8th · 16GB · 256GB SSD</div>
                                            <span class="preset-card-badge">Most Common</span>
                                        </div>
                                        <div class="preset-pill-card" onclick="applyPreset('executive-pro')">
                                            <div class="preset-card-title">🚀 Executive Pro</div>
                                            <div class="preset-card-subtitle">HP · i7-10th · 16GB · 512GB SSD</div>
                                            <span class="preset-card-badge" style="background:#e0f2fe; color:#0369a1;">Refurbished</span>
                                        </div>
                                        <div class="preset-pill-card" onclick="applyPreset('budget-student')">
                                            <div class="preset-card-title">📦 Budget Student</div>
                                            <div class="preset-card-subtitle">Lenovo · i5-8th · 8GB · 256GB SSD</div>
                                            <span class="preset-card-badge" style="background:#fef3c7; color:#92400e;">Untested</span>
                                        </div>
                                        <div class="preset-pill-card" onclick="applyPreset('apple-m1')">
                                            <div class="preset-card-title">🍎 MacBook M1</div>
                                            <div class="preset-card-subtitle">Apple Air · M1 · 8GB · 256GB SSD</div>
                                            <span class="preset-card-badge" style="background:#f1f5f9; color:#0f172a;">Apple</span>
                                        </div>
                                        <div class="preset-pill-card" onclick="applyPreset('for-parts')">
                                            <div class="preset-card-title">🛠️ For Parts</div>
                                            <div class="preset-card-subtitle">No RAM · No SSD · Salvage Batch</div>
                                            <span class="preset-card-badge" style="background:#fee2e2; color:#b91c1c;">Salvage</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 1: HARDWARE IDENTITY & BARCODE SCANNER -->
                                <div class="form-panel-section">
                                    <h3 class="form-section-header">
                                        <span>🔍</span> Hardware Identity &amp; Model
                                    </h3>

                                    <!-- Manufacturer Brand Pills -->
                                    <div class="form-group" style="margin-bottom: 14px;">
                                        <label>Manufacturer Brand *</label>
                                        <div class="brand-pills-row" id="intakeBrandPills">
                                            <?php
                                            $brands = ['Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'Asus', 'Acer', 'MSI', 'Other'];
                                            foreach ($brands as $b):
                                                $active = ($b === 'Dell') ? 'selected' : '';
                                            ?>
                                                <button type="button" class="brand-pill <?= $active ?>" data-brand="<?= $b ?>" onclick="selectBrandPill('<?= $b ?>')"><?= $b ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" id="intake_brand" name="brand" value="Dell" required>
                                    </div>

                                    <!-- Model Line & Model Number / Series -->
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_model">Model Line *</label>
                                            <input type="text" id="intake_model" name="model" class="form-control" required placeholder="e.g. Latitude / EliteBook / ThinkPad" list="model-options" autocomplete="off">
                                            <datalist id="model-options">
                                                <option value="Latitude">
                                                <option value="Inspiron">
                                                <option value="Precision">
                                                <option value="XPS">
                                                <option value="EliteBook">
                                                <option value="ProBook">
                                                <option value="ZBook">
                                                <option value="ThinkPad">
                                                <option value="IdeaPad">
                                                <option value="MacBook Air">
                                                <option value="MacBook Pro">
                                                <option value="Surface Laptop">
                                                <option value="Surface Pro">
                                            </datalist>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_series">Model Number / Series</label>
                                            <input type="text" id="intake_series" name="series" class="form-control" placeholder="e.g. 5400 / 840 G6 / T480" list="series-options" autocomplete="off">
                                            <datalist id="series-options">
                                                <option value="5400"><option value="5490"><option value="5500"><option value="7490"><option value="7400">
                                                <option value="840 G5"><option value="840 G6"><option value="840 G7"><option value="850 G6">
                                                <option value="T480"><option value="T490"><option value="T14 Gen 1"><option value="X1 Carbon">
                                            </datalist>
                                        </div>
                                    </div>

                                    <!-- Serial Number / Asset Tag Barcode Scanner Input -->
                                    <div id="singleSnGroup" class="form-group" style="position: relative;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <label for="intake_serial_number" style="margin-bottom: 0;">Serial Number (S/N) or Asset Tag</label>
                                            <span id="snLookupStatus" style="font-size: 0.75rem; font-weight: 700; color: #64748b;">Ready for Barcode Scanner</span>
                                        </div>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input type="text" id="intake_serial_number" name="serial_number" class="form-control" placeholder="Scan barcode with 2D scanner or type S/N..." autocomplete="off" style="font-family: monospace; font-weight: 700; font-size: 1.05rem; padding-left: 38px;">
                                            <span style="position: absolute; left: 12px; font-size: 1.1rem; pointer-events: none;">📷</span>
                                        </div>
                                        <div id="snMatchedAlert" style="display: none; margin-top: 8px; padding: 8px 12px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; font-size: 0.82rem; color: #065f46; display: flex; justify-content: space-between; align-items: center;">
                                            <span id="snMatchedText">🏷️ Existing Label Found</span>
                                            <button type="button" onclick="clearMatchedProfile()" style="background: none; border: none; font-size: 0.75rem; font-weight: 800; color: #047857; cursor: pointer; text-decoration: underline;">Clear</button>
                                        </div>
                                        <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                                            <label style="font-size: 0.78rem; font-weight: 700; color: #64748b;">Lot / Batch # (Optional):</label>
                                            <input type="text" id="intake_single_batch" placeholder="e.g. LOT-5400-A" style="flex: 1; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 700;" oninput="document.getElementById('intake_batch_number').value = this.value">
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 2: PROCESSOR & MEMORY SPECS -->
                                <div class="form-panel-section">
                                    <h3 class="form-section-header">
                                        <span>🧠</span> Processor &amp; Memory Specs
                                    </h3>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_cpu_gen">CPU Generation / Family</label>
                                            <input type="text" id="intake_cpu_gen" name="cpu_gen" class="form-control" placeholder="e.g. 8th Gen / 10th Gen / Apple M1" list="cpu-gen-options" autocomplete="off">
                                            <datalist id="cpu-gen-options">
                                                <option value="8th Gen"><option value="10th Gen"><option value="11th Gen"><option value="12th Gen"><option value="13th Gen">
                                                <option value="6th Gen"><option value="7th Gen"><option value="Apple M1"><option value="Apple M2"><option value="AMD Ryzen 5"><option value="AMD Ryzen 7">
                                            </datalist>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_cpu_specs">Exact Processor Model</label>
                                            <input type="text" id="intake_cpu_specs" name="cpu_specs" class="form-control" placeholder="e.g. i5-8350U / i7-10610U / M1 8-Core" autocomplete="off">
                                            <input type="hidden" id="intake_cpu" name="cpu">
                                        </div>
                                    </div>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_cpu_cores_display">Core Count</label>
                                            <div class="input-suffix-wrapper">
                                                <input type="text" id="intake_cpu_cores_display" class="form-control" placeholder="e.g. 4" inputmode="numeric" oninput="onCpuCoresInput(this)" autocomplete="off">
                                                <span id="intake_cpu_cores_suffix" class="input-suffix-badge" style="display:none;">Cores</span>
                                                <input type="hidden" id="intake_cpu_cores" name="cpu_cores">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_cpu_speed_display">Clock Frequency</label>
                                            <div class="input-suffix-wrapper">
                                                <input type="text" id="intake_cpu_speed_display" class="form-control" placeholder="e.g. 1.80" inputmode="decimal" oninput="onCpuSpeedInput(this)" autocomplete="off">
                                                <span id="intake_cpu_speed_suffix" class="input-suffix-badge" style="display:none;">GHz</span>
                                                <input type="hidden" id="intake_cpu_speed" name="cpu_speed">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- RAM Capacity with Pills -->
                                    <div class="form-group" style="margin-bottom: 14px;">
                                        <label for="intake_ram">RAM Memory Capacity</label>
                                        <div class="spec-pills-row" id="intakeRamPills">
                                            <?php foreach (['4 GB', '8 GB', '16 GB', '32 GB', '64 GB', 'No RAM'] as $rp): ?>
                                                <button type="button" class="spec-pill <?= ($rp === '16 GB') ? 'selected' : '' ?> <?= ($rp === 'No RAM') ? 'pill-none' : '' ?>" onclick="selectSpecPill('ram', '<?= $rp ?>')"><?= $rp ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="text" id="intake_ram" name="ram" class="form-control" value="16 GB" placeholder="e.g. 16 GB or No RAM">
                                    </div>

                                    <!-- Storage Capacity with Pills -->
                                    <div class="form-group">
                                        <label for="intake_storage">Storage Drive Capacity</label>
                                        <div class="spec-pills-row" id="intakeStoragePills">
                                            <?php foreach (['128 GB SSD', '256 GB SSD', '512 GB SSD', '1 TB SSD', '2 TB SSD', 'No SSD'] as $sp): ?>
                                                <button type="button" class="spec-pill <?= ($sp === '256 GB SSD') ? 'selected' : '' ?> <?= ($sp === 'No SSD') ? 'pill-none' : '' ?>" onclick="selectSpecPill('storage', '<?= $sp ?>')"><?= $sp ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="text" id="intake_storage" name="storage" class="form-control" value="256 GB SSD" placeholder="e.g. 256 GB SSD or No SSD">
                                    </div>
                                </div>

                                <!-- SECTION 3: WAREHOUSE LOCATION & CONDITION -->
                                <div class="form-panel-section">
                                    <h3 class="form-section-header">
                                        <span>📍</span> Location, Condition &amp; Status
                                    </h3>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_condition">Standard Condition *</label>
                                            <select id="intake_condition" name="condition" class="form-control">
                                                <option value="Refurbished" selected>✅ Refurbished (Ready for Sale)</option>
                                                <option value="Untested">⏳ Untested (Standard Intake)</option>
                                                <option value="For Parts">🛠️ For Parts (Defects Present)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_wh_status">Warehouse Status</label>
                                            <select id="intake_wh_status" name="wh_status" class="form-control">
                                                <option value="In Warehouse" selected>📦 In Warehouse / Live Stock</option>
                                                <option value="Tested">✅ Tested Working</option>
                                                <option value="Grade A">🟢 Grade A (Mint)</option>
                                                <option value="Grade B">🔵 Grade B (Clean)</option>
                                                <option value="Grade C">🟠 Grade C (Heavy Wear)</option>
                                                <option value="No Post">🛑 No Post / Logo Only</option>
                                                <option value="No Power">🔌 No Power / Dead</option>
                                                <option value="Sold">🚚 Archive / Sold</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                <label for="intake_location" style="margin-bottom: 0;">Shelf / Bin Location</label>
                                                <label style="font-size: 0.75rem; color: #2563eb; cursor: pointer; font-weight: 800; display: flex; align-items: center; gap: 4px;">
                                                    <input type="checkbox" id="intake_pin_location" style="accent-color: #2563eb;">
                                                    <span>📌 Pin Location</span>
                                                </label>
                                            </div>
                                            <input type="text" id="intake_location" name="location" class="form-control" placeholder="e.g. Shelf A-1-2 / Rack 4">
                                            <!-- Tracked Locations for this Smart Label -->
                                            <div id="smartLocationChips" class="smart-locations-bar" style="display: none;">
                                                <span style="color: #64748b; font-size: 0.72rem; font-weight: 700;">📍 Tracked Locations:</span>
                                                <div id="smartLocationChipsList" style="display: inline-flex; gap: 4px; flex-wrap: wrap;"></div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_bios_state">BIOS Security Lock</label>
                                            <select id="intake_bios_state" name="bios_state" class="form-control">
                                                <option value="Unlocked" selected>🟢 Open / Unlocked</option>
                                                <option value="Locked">🔒 Password Locked</option>
                                                <option value="Unknown">Unknown / Pending</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 4: HYBRID DELIVERY & LOG TRACKING -->
                                <div class="form-panel-section" style="border-left: 4px solid #2563eb;">
                                    <h3 class="form-section-header" style="color: #1e40af;">
                                        <span>🚚</span> Hybrid Delivery &amp; Destination Tracking
                                    </h3>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Delivery Target Status</label>
                                            <select id="intake_delivery_status" name="delivery_status" class="form-control" onchange="onDeliveryTargetChange(this.value)">
                                                <option value="Pending" selected>⏳ Pending Delivery / Testing In Progress</option>
                                                <option value="Delivered to Shelf">📦 Deliver to Warehouse Shelf Stock</option>
                                                <option value="Delivered to Order">🚚 Deliver to Customer Order / Buyer</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_delivery_destination" id="lblDeliveryDestination">Destination Details</label>
                                            <input type="text" id="intake_delivery_destination" name="delivery_destination" class="form-control" placeholder="e.g. Shelf A-1-2 or Order #1042 (Alex M.)">
                                        </div>
                                    </div>
                                </div>

                                <!-- SECTION 5: DEEP DIAGNOSTIC & SPECIFICATION SHEET -->
                                <div class="form-panel-section">
                                    <h3 class="form-section-header" style="color: #0369a1;">
                                        <span>🔬</span> Deep Diagnostic Sheet (Full Technical Details)
                                    </h3>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_gpu">Graphics Processing (GPU)</label>
                                            <input type="text" id="intake_gpu" name="gpu" class="form-control" placeholder="e.g. Intel UHD / NVIDIA GTX 1650">
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_screen_res">Screen Resolution &amp; Display</label>
                                            <input type="text" id="intake_screen_res" name="screen_res" class="form-control" placeholder="e.g. 1920x1080 FHD Touch">
                                        </div>
                                    </div>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_battery">Battery Status</label>
                                            <select id="intake_battery" name="battery" class="form-control">
                                                <option value="Included / Good" selected>Included / Good</option>
                                                <option value="Missing / Dead">Missing / Dead</option>
                                                <option value="Pending">Pending / Unknown</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_battery_specs">Battery Health % / Cycle Count</label>
                                            <input type="text" id="intake_battery_specs" name="battery_specs" class="form-control" placeholder="e.g. 88% Health / 140 Cycles">
                                        </div>
                                    </div>

                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label for="intake_os">Operating System License</label>
                                            <input type="text" id="intake_os" name="os_version" class="form-control" value="Windows 11 Pro" placeholder="e.g. Windows 11 Pro / macOS Sonoma">
                                        </div>
                                        <div class="form-group">
                                            <label for="intake_cosmetic_grade">Cosmetic Grading</label>
                                            <select id="intake_cosmetic_grade" name="cosmetic_grade" class="form-control">
                                                <option value="A" selected>Grade A (Mint / Like New)</option>
                                                <option value="B">Grade B (Minor Scratches)</option>
                                                <option value="C">Grade C (Heavy Wear / Dents)</option>
                                                <option value="">Unset</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="intake_notes">Internal Technician Repair &amp; Testing Notes</label>
                                        <textarea id="intake_notes" name="work_notes" rows="2" class="form-control" placeholder="Document testing, thermal repaste, ports check, cosmetic notes..."></textarea>
                                    </div>
                                </div>

                            </div>

                            <!-- ── RIGHT COLUMN: LIVE 2" x 1" THERMAL STICKER PREVIEW ── -->
                            <aside class="intake-preview-column">
                                <div class="live-sticker-preview-box">
                                    <div class="preview-box-header">
                                        <div class="preview-box-title">
                                            <span style="color:#10b981;">●</span> Live Thermal Sticker (2" × 1")
                                        </div>
                                        <div class="preview-tab-pills">
                                            <button type="button" class="preview-tab-pill active" id="btnPrevBoth" onclick="switchPreviewTab('both')">Dual</button>
                                            <button type="button" class="preview-tab-pill" id="btnPrevA" onclick="switchPreviewTab('a')">Brand</button>
                                            <button type="button" class="preview-tab-pill" id="btnPrevB" onclick="switchPreviewTab('b')">Specs</button>
                                        </div>
                                    </div>

                                    <!-- STICKER MOCKUPS STAGE -->
                                    <div id="mockupStage" style="display: flex; flex-direction: column; gap: 12px;">
                                        
                                        <!-- STICKER A: BRAND MOCKUP -->
                                        <div class="thermal-sticker-mockup" id="mockupLabelA">
                                            <div class="mockup-branding">
                                                <div class="mockup-brand-model" id="prevBrandModel">DELL LATITUDE</div>
                                                <div class="mockup-series" id="prevSeries">5400</div>
                                                <div class="mockup-cpu" id="prevCpu">i5-8350U (8th Gen)</div>
                                            </div>
                                            <div class="mockup-barcode">
                                                <svg viewBox="0 0 100 20" preserveAspectRatio="none">
                                                    <rect x="0" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="4" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="7" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="12" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="16" y="0" width="4" height="20" fill="#000"/>
                                                    <rect x="22" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="25" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="30" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="34" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="37" y="0" width="4" height="20" fill="#000"/>
                                                    <rect x="43" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="47" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="50" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="55" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="59" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="64" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="67" y="0" width="4" height="20" fill="#000"/>
                                                    <rect x="73" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="77" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="80" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="85" y="0" width="2" height="20" fill="#000"/>
                                                    <rect x="89" y="0" width="1" height="20" fill="#000"/>
                                                    <rect x="92" y="0" width="3" height="20" fill="#000"/>
                                                    <rect x="97" y="0" width="2" height="20" fill="#000"/>
                                                </svg>
                                                <div class="mockup-sn" id="prevSN">S/N: SCANNING...</div>
                                            </div>
                                            <div class="mockup-footer">
                                                <span id="prevLabelId">#PENDING</span>
                                                <span id="prevLoc">LOC: —</span>
                                                <span id="prevCond">REFURBISHED</span>
                                            </div>
                                        </div>

                                        <!-- STICKER B: SPECS MOCKUP -->
                                        <div class="thermal-sticker-mockup" id="mockupLabelB">
                                            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #000; padding-bottom:1px; margin-bottom:2px; font-size:0.65rem; font-weight:800;">
                                                <span id="prevSpecsHeaderBrand">DELL SPECS</span>
                                                <span id="prevSpecsLabelId">#PENDING</span>
                                            </div>
                                            <div class="mockup-specs-grid">
                                                <div class="mockup-spec-line">
                                                    <strong>CPU:</strong> <span id="prevSpecsCpu">i5-8350U 4 Cores</span>
                                                </div>
                                                <div class="mockup-spec-line">
                                                    <strong>RAM:</strong> <span id="prevSpecsRam">16 GB</span> &nbsp;|&nbsp; 
                                                    <strong>SSD:</strong> <span id="prevSpecsStorage">256 GB SSD</span> &nbsp;|&nbsp;
                                                    <strong>BAT:</strong> <span id="prevSpecsBatt">YES</span>
                                                </div>
                                                <div class="mockup-spec-line">
                                                    <strong>GPU:</strong> <span id="prevSpecsGpu">Integrated</span> &nbsp;|&nbsp;
                                                    <strong>OS:</strong> <span id="prevSpecsOs">Win 11 Pro</span>
                                                </div>
                                            </div>
                                            <div class="mockup-footer">
                                                <span>BIOS: <strong id="prevSpecsBios">UNLOCKED</strong></span>
                                                <span id="prevSpecsLoc" style="background:#000; color:#fff; padding:1px 3px; border-radius:2px;">📍 LOC: —</span>
                                                <span id="prevSpecsCond">REFURBISHED</span>
                                            </div>
                                        </div>

                                    </div>

                                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 10px; text-align: center;">
                                        Physical 2.0" × 1.0" thermal roll preview. Margin: 0mm.
                                    </div>
                                </div>

                                <!-- SMART LABELS & SPECS PRESELECT PICKER (Right Column Quick Selection) -->
                                <div class="smart-picker-card">
                                    <div class="smart-picker-header">
                                        <div class="smart-picker-title">
                                            <span>🏷️</span>
                                            <span>Smart Labels (Preselect Specs)</span>
                                        </div>
                                        <span id="smartCatalogCountBadge" style="font-size: 0.72rem; font-weight: 800; color: #2563eb; background: #dbeafe; padding: 2px 7px; border-radius: 6px;">Loading...</span>
                                    </div>
                                    
                                    <div class="smart-search-wrapper">
                                        <span class="smart-search-icon">🔍</span>
                                        <input type="text" id="smart_label_search" class="smart-search-input" placeholder="Search templates (e.g. Latitude 5400, Surface...)" oninput="filterSmartLabels(this.value)" autocomplete="off">
                                        <button type="button" class="smart-search-clear" onclick="clearSmartSearch()" title="Clear search">✕</button>
                                    </div>

                                    <!-- Quick Chips Shelf of Matching Smart Labels -->
                                    <div id="smartChipsShelf" class="smart-chips-shelf">
                                        <span style="color: #94a3b8; font-size: 0.8rem; font-style: italic; padding: 6px;">Loading unique smart templates...</span>
                                    </div>

                                    <!-- Active Connected Smart Label Banner (Visible when selected or matched) -->
                                    <div id="smartMatchedBanner" class="smart-matched-status" style="display: none;">
                                        <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                            <span style="font-size: 1.1rem; flex-shrink: 0;">🏷️</span>
                                            <div style="min-width: 0; overflow: hidden;">
                                                <div id="smartMatchedTitle" style="font-weight: 800; color: #065f46; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Connected to Smart Label</div>
                                                <div id="smartMatchedLocs" style="font-size: 0.7rem; color: #047857; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Tracked Locations: Unassigned</div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="unlinkSmartLabel()" style="background: white; border: 1px solid #6ee7b7; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; color: #065f46; cursor: pointer; flex-shrink: 0;">✕ Custom</button>
                                    </div>
                                </div>
                            </aside>

                        </div>
                    </div>

                    <!-- STICKY ACTION BAR -->
                    <div class="intake-action-bar">
                        <div id="intakeFormMessage" style="flex: 1; font-weight: 800; font-size: 0.9rem;"></div>
                        
                        <button type="button" class="btn-reset-form" onclick="resetIntakeForm()">
                            <span>✨ Start Fresh</span>
                        </button>
                        
                        <button type="button" class="btn-odt" onclick="submitIntake('odt')" title="Save log and generate Windows LibreOffice ODT label">
                            <span>📄 Save &amp; Windows ODT</span>
                        </button>
                        
                        <button type="button" class="btn-thermal" onclick="submitIntake('thermal')" title="Save log and trigger direct 2x1 browser thermal print">
                            <span>🖨️ Print Thermal Label</span>
                        </button>
                        
                        <button type="button" class="btn-save-log" onclick="submitIntake('save')" title="Save test log to database">
                            <span>💾 Save Hardware Log</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════
             DELIVERY STATUS MODAL (QUICK ROW ACTION)
             ═════════════════════════════════════════════════════════════════════ -->
        <div id="deliveryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); z-index: 10001; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
            <div style="background: white; border-radius: 16px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #cbd5e1;">
                <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                    <h3 style="margin: 0; font-size: 1.15rem; color: #1e293b;">🚚 Update Delivery Status</h3>
                    <button type="button" onclick="closeDeliveryModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
                </div>
                <div style="padding: 20px 24px;">
                    <input type="hidden" id="deliveryLogId">
                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 700; font-size: 0.85rem; color: #475569; display: block; margin-bottom: 6px;">SELECT DELIVERY ACTION:</label>
                        <select id="deliveryActionType" class="form-control" onchange="onDeliveryModalTypeChange(this.value)">
                            <option value="shelf">📦 Deliver to Warehouse Shelf Stock</option>
                            <option value="order">🚚 Deliver to Customer Order / Buyer</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label id="deliveryDestLabel" style="font-weight: 700; font-size: 0.85rem; color: #475569; display: block; margin-bottom: 6px;">SHELF LOCATION:</label>
                        <input type="text" id="deliveryDestinationInput" class="form-control" placeholder="e.g. Shelf A-1-2">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" onclick="closeDeliveryModal()" style="padding: 10px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 700; cursor: pointer;">Cancel</button>
                        <button type="button" onclick="confirmDeliveryUpdate()" class="btn-primary" style="padding: 10px 24px; border-radius: 8px; background: #2563eb; color: white; border: none; font-weight: 800; cursor: pointer;">Confirm Delivery</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════
             REAL-TIME TODAY'S TECHNICIAN PRODUCTIVITY RIBBON
             ═════════════════════════════════════════════════════════════════════ -->
        <div id="todayProductivityBar" class="tech-productivity-bar" style="display: none;">
            <div class="title-group">
                <h3>⚡ Today's Technician Activity</h3>
                <span id="prodTotalBadge" style="font-size: 0.82rem; font-weight: 800; color: #1e293b; background: #f1f5f9; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">0 units tested today</span>
            </div>
            <div class="tech-prod-chips" id="techProdChipsContainer">
                <!-- Injected via JavaScript from get_logs.php tech_stats -->
            </div>
        </div>

        <!-- Recent Logs Table (full width, below) -->
        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button id="tab-good" onclick="setActiveTab('Good')" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #22c55e; color: white; font-weight: 800; cursor: pointer; transition: all 0.2s;">✅ Good Laptops</button>
                    <button id="tab-bad" onclick="setActiveTab('Bad')" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 800; cursor: pointer; transition: all 0.2s;">❌ Bad Laptops</button>
                    
                    <!-- Search Input -->
                    <input type="text" id="log_search" placeholder="🔍 Search S/N, Model..." oninput="onSearchFilterChange()" style="padding: 9px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white; width: 200px;">
                </div>

                <!-- Filters -->
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <!-- Delivery Filter -->
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">DELIVERY:</label>
                        <select id="delivery_filter" onchange="fetchLogs()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                            <option value="">All Statuses</option>
                            <option value="Pending">⏳ Pending</option>
                            <option value="Delivered to Shelf">📦 Shelved</option>
                            <option value="Delivered to Order">🚚 Delivered</option>
                        </select>
                    </div>

                    <?php if ($is_admin): ?>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">TECH:</label>
                        <select id="tech_filter" onchange="fetchLogs()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                            <option value="" <?= ($selected_tech === '') ? 'selected' : '' ?>>All Technicians</option>
                            <option value="<?= htmlspecialchars($_SESSION['username']) ?>" <?= ($selected_tech === $_SESSION['username']) ? 'selected' : '' ?>>My Logs Only (Admin)</option>
                            <?php foreach ($tech_list as $t): ?>
                                <?php if ($t !== $_SESSION['username']): ?>
                                    <option value="<?= htmlspecialchars($t) ?>" <?= ($selected_tech === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">START:</label>
                        <input type="date" id="start_date" onchange="onDateFilterChange()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">END:</label>
                        <input type="date" id="end_date" onchange="onDateFilterChange()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                    </div>
                    <button onclick="clearDateFilter()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; background: white; color: #475569; font-size: 0.85rem; font-weight: 700; cursor: pointer;">Reset</button>
                </div>
            </div>

            <div class="log-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status &amp; Delivery</th>
                            <th>Hardware Identity</th>
                            <th>Specifications</th>
                            <th>Location &amp; Notes</th>
                            <th style="text-align: right; width: 340px;">Actions &amp; Fulfillment</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Hidden Iframe for Direct Web Thermal Printing -->
    <iframe id="thermalPrintFrame" style="display:none; width:0; height:0; border:none;"></iframe>

    <!-- Edit Log Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 10002; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div style="background: white; border-radius: 16px; width: 90%; max-width: 750px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #cbd5e1;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;">✏️ Edit Log Entry</h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form id="editForm" style="padding: 24px; margin-bottom: 0;">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="action" value="edit">

                <div class="form-grid">
                    <div class="form-group">
                        <label>QTY</label>
                        <input type="number" class="form-control" name="qty" id="edit-qty" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" class="form-control" name="make" id="edit-make" required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" class="form-control" name="model" id="edit-model" required>
                    </div>
                    <div class="form-group">
                        <label>Series</label>
                        <input type="text" class="form-control" name="series" id="edit-series">
                    </div>
                    <div class="form-group">
                        <label>Serial Number (S/N)</label>
                        <input type="text" class="form-control" name="serial_number" id="edit-serial_number" style="font-family: monospace; font-weight: 700;">
                    </div>
                    <div class="form-group">
                        <label>CPU</label>
                        <input type="text" class="form-control" name="cpu" id="edit-cpu">
                    </div>
                    <div class="form-group">
                        <label>RAM</label>
                        <input type="text" class="form-control" name="ram" id="edit-ram">
                    </div>
                    <div class="form-group">
                        <label>Storage</label>
                        <input type="text" class="form-control" name="storage" id="edit-storage">
                    </div>
                    <div class="form-group">
                        <label>Shelf Location</label>
                        <input type="text" class="form-control" name="location" id="edit-location">
                    </div>
                    <div class="form-group">
                        <label>Delivery Status</label>
                        <select class="form-control" name="delivery_status" id="edit-delivery_status">
                            <option value="Pending">Pending</option>
                            <option value="Delivered to Shelf">Delivered to Shelf</option>
                            <option value="Delivered to Order">Delivered to Order</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Delivery Destination</label>
                        <input type="text" class="form-control" name="delivery_destination" id="edit-delivery_destination">
                    </div>
                    <div class="form-group">
                        <label>Batch / Lot #</label>
                        <input type="text" class="form-control" name="batch_number" id="edit-batch_number" placeholder="e.g. LOT-5400-A">
                    </div>
                    <div class="form-group">
                        <label>GPU</label>
                        <input type="text" class="form-control" name="gpu" id="edit-gpu">
                    </div>
                    <div class="form-group">
                        <label>Battery</label>
                        <input type="text" class="form-control" name="battery" id="edit-battery">
                    </div>
                    <div class="form-group">
                        <label>BIOS State</label>
                        <input type="text" class="form-control" name="bios_state" id="edit-bios_state">
                    </div>
                    <div class="form-group">
                        <label>OS</label>
                        <input type="text" class="form-control" name="os" id="edit-os">
                    </div>
                    <div class="form-group span-2">
                        <label>Notes</label>
                        <input type="text" class="form-control" name="notes" id="edit-notes">
                    </div>
                </div>

                <div id="editFormMessage" style="font-weight: bold; text-align: center; font-size: 0.9rem; margin-bottom: 15px;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="padding: 10px 30px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═════════════════════════════════════════════════════════════════════
         MODULAR INTAKE JAVASCRIPT CONTROLLER
         ═════════════════════════════════════════════════════════════════════ -->
    <script>
        'use strict';

        // ── PACIFIC TIMEZONE UTILITIES ────────────────────────────────────────
        function formatPacificTime(utcStr, options = { hour: 'numeric', minute: '2-digit' }) {
            if (!utcStr) return '';
            let str = String(utcStr).trim();
            if (!str.includes('Z') && !/[+-]\d{2}:?\d{2}$/.test(str)) {
                str = str.replace(' ', 'T') + 'Z';
            }
            const d = new Date(str);
            if (isNaN(d.getTime())) return utcStr;
            return d.toLocaleTimeString('en-US', { ...options, timeZone: 'America/Los_Angeles' });
        }

        function formatPacificDate(utcStr, options = { year: 'numeric', month: '2-digit', day: '2-digit' }) {
            if (!utcStr) return '';
            let str = String(utcStr).trim();
            if (!str.includes('Z') && !/[+-]\d{2}:?\d{2}$/.test(str)) {
                str = str.replace(' ', 'T') + 'Z';
            }
            const d = new Date(str);
            if (isNaN(d.getTime())) return utcStr;
            return d.toLocaleDateString('en-US', { ...options, timeZone: 'America/Los_Angeles' });
        }

        const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
        let currentStatus = 'Good';
        let searchDebounceTimer = null;
        let snLookupDebounceTimer = null;
        let currentIntakeMode = 'single';
        let batchSerialsList = [];
        let smartCatalog = [];
        let specCollisionTimer = null;

        // Built-in Quick Presets Catalog
        const IQA_Presets = [
            { id: 'fleet-office', brand: 'Dell', model: 'Latitude', series: '5400', cpu_gen: '8th Gen', cpu_specs: 'i5-8350U', cpu_cores: '4', cpu_speed: '1.70', ram: '16 GB', storage: '256 GB SSD', condition: 'Refurbished' },
            { id: 'executive-pro', brand: 'HP', model: 'EliteBook', series: '840 G6', cpu_gen: '8th Gen', cpu_specs: 'i7-8665U', cpu_cores: '4', cpu_speed: '1.90', ram: '16 GB', storage: '512 GB SSD', condition: 'Refurbished' },
            { id: 'budget-student', brand: 'Lenovo', model: 'ThinkPad', series: 'T480', cpu_gen: '8th Gen', cpu_specs: 'i5-8250U', cpu_cores: '4', cpu_speed: '1.60', ram: '8 GB', storage: '256 GB SSD', condition: 'Untested' },
            { id: 'apple-m1', brand: 'Apple', model: 'MacBook Air', series: 'A2337', cpu_gen: 'Apple M1', cpu_specs: 'M1 8-Core', cpu_cores: '8', cpu_speed: '3.20', ram: '8 GB', storage: '256 GB SSD', condition: 'Refurbished' },
            { id: 'for-parts', brand: 'Dell', model: 'Latitude', series: '5490', cpu_gen: '8th Gen', cpu_specs: 'i5-8250U', cpu_cores: '4', cpu_speed: '', ram: 'No RAM', storage: 'No SSD', condition: 'For Parts' }
        ];

        // ── 0. SMART LABEL CATALOG & COLLISION CONTROLLER ─────────────────────
        async function fetchSmartCatalog() {
            try {
                const response = await fetch('../api/lookup_label.php?action=catalog');
                const result = await response.json();
                if (result.success && Array.isArray(result.catalog)) {
                    smartCatalog = result.catalog;
                    const countBadge = document.getElementById('smartCatalogCountBadge');
                    if (countBadge) {
                        countBadge.textContent = `${smartCatalog.length} Smart Labels Ready`;
                    }
                    renderSmartChips(smartCatalog);
                }
            } catch (err) {
                console.error('Failed to load smart catalog', err);
            }
        }

        function getBrandColor(brand) {
            const b = (brand || '').toLowerCase();
            if (b.includes('dell')) return { bg: '#eff6ff', text: '#1d4ed8', border: '#2563eb' };
            if (b.includes('hp')) return { bg: '#ecfeff', text: '#0891b2', border: '#06b6d4' };
            if (b.includes('lenovo')) return { bg: '#fff7ed', text: '#c2410c', border: '#ea580c' };
            if (b.includes('apple')) return { bg: '#f8fafc', text: '#0f172a', border: '#334155' };
            if (b.includes('microsoft')) return { bg: '#eef2ff', text: '#4338ca', border: '#6366f1' };
            return { bg: '#f1f5f9', text: '#334155', border: '#94a3b8' };
        }

        function renderSmartChips(list) {
            const container = document.getElementById('smartChipsShelf');
            if (!container) return;

            if (!list || list.length === 0) {
                container.innerHTML = '<span style="color: #94a3b8; font-size: 0.82rem; font-style: italic; padding: 14px; text-align: center; display: block;">No matching smart labels found.</span>';
                return;
            }

            const currentLabelId = parseInt(document.getElementById('intake_label_id')?.value, 10);

            let html = '';
            list.forEach(item => {
                const isActive = (currentLabelId === parseInt(item.id, 10));
                const brandColors = getBrandColor(item.brand);
                
                // Formatted location pills
                const locs = item.locations_list || [];
                const locHtml = locs.length > 0 
                    ? locs.map(l => `<span class="chip-mini-loc">📍 ${escapeHtml(l)}</span>`).join('')
                    : '<span class="chip-mini-loc unassigned">📍 Unassigned</span>';

                // Spec Pills
                const ramPill = item.ram ? `<span class="chip-mini-spec ram">⚡ ${escapeHtml(item.ram)}</span>` : '';
                const ssdPill = item.storage ? `<span class="chip-mini-spec storage">💾 ${escapeHtml(item.storage)}</span>` : '';
                const cpuPill = item.cpu_specs 
                    ? `<span class="chip-mini-spec cpu">🧠 ${escapeHtml(item.cpu_specs)}</span>` 
                    : (item.cpu_gen ? `<span class="chip-mini-spec cpu">${escapeHtml(item.cpu_gen)}</span>` : '');

                html += `
                    <div class="smart-label-chip ${isActive ? 'active' : ''}" style="border-left: 5px solid ${brandColors.border};" onclick="selectSmartLabel(${item.id})">
                        <div class="smart-chip-top">
                            <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1;">
                                <span class="smart-chip-id-badge">#${item.id}</span>
                                <span class="smart-chip-brand-badge" style="background: ${brandColors.bg}; color: ${brandColors.text}; border: 1px solid ${brandColors.border}40;">${escapeHtml(item.brand || 'Other')}</span>
                                <span class="smart-chip-model-name">${escapeHtml(item.model || '')} ${escapeHtml(item.series || '')}</span>
                            </div>
                            <div style="display: flex; gap: 4px; flex-shrink: 0;">
                                ${locHtml}
                            </div>
                        </div>
                        <div class="smart-chip-specs-row">
                            ${ramPill}
                            ${ssdPill}
                            ${cpuPill}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function filterSmartLabels(query) {
            const q = query.toLowerCase().trim();
            if (!q) {
                renderSmartChips(smartCatalog);
                return;
            }
            const filtered = smartCatalog.filter(item => {
                const text = `${item.id} ${item.brand} ${item.model} ${item.series || ''} ${item.cpu_specs || ''} ${item.ram || ''} ${item.storage || ''} ${item.warehouse_location || ''}`.toLowerCase();
                return text.includes(q);
            });
            renderSmartChips(filtered);
        }

        function clearSmartSearch() {
            const input = document.getElementById('smart_label_search');
            if (input) input.value = '';
            renderSmartChips(smartCatalog);
        }

        function selectSmartLabel(labelId) {
            const item = smartCatalog.find(l => parseInt(l.id, 10) === parseInt(labelId, 10));
            if (!item) return;

            // Preselect fields
            if (item.brand) selectBrandPill(item.brand);
            if (item.model) document.getElementById('intake_model').value = item.model;
            if (item.series) document.getElementById('intake_series').value = item.series;
            if (item.cpu_gen) document.getElementById('intake_cpu_gen').value = item.cpu_gen;
            if (item.cpu_specs) document.getElementById('intake_cpu_specs').value = item.cpu_specs;

            onCpuCoresInput(item.cpu_cores || '');
            onCpuSpeedInput(item.cpu_speed || '');

            if (item.ram) selectSpecPill('ram', item.ram);
            if (item.storage) selectSpecPill('storage', item.storage);

            if (item.gpu) document.getElementById('intake_gpu').value = item.gpu;
            if (item.screen_res) document.getElementById('intake_screen_res').value = item.screen_res;
            if (item.os_version) document.getElementById('intake_os').value = item.os_version;
            if (item.bios_state) document.getElementById('intake_bios_state').value = item.bios_state;

            // Set condition
            if (item.description === 'For Parts') {
                document.getElementById('intake_condition').value = 'For Parts';
                setIntakeStatus('Bad');
            } else {
                document.getElementById('intake_condition').value = 'Refurbished';
                setIntakeStatus('Good');
            }

            // Hook label ID
            document.getElementById('intake_label_id').value = item.id;

            // Display active connected banner
            const banner = document.getElementById('smartMatchedBanner');
            const titleEl = document.getElementById('smartMatchedTitle');
            const locsEl = document.getElementById('smartMatchedLocs');
            if (banner && titleEl && locsEl) {
                banner.style.display = 'flex';
                titleEl.textContent = `Connected to Smart Label #${item.id}: ${item.display_title}`;
                const locText = item.locations_list && item.locations_list.length > 0
                    ? item.locations_list.join(', ')
                    : 'Unassigned';
                locsEl.textContent = `Tracked Locations for this Spec: ${locText}`;
            }

            // Render location chips
            renderLocationChips(item.locations_list);

            // Re-render chips to show active selection
            renderSmartChips(smartCatalog);
            updateLivePreview();
        }

        function unlinkSmartLabel() {
            document.getElementById('intake_label_id').value = '';
            const banner = document.getElementById('smartMatchedBanner');
            if (banner) banner.style.display = 'none';

            const locBar = document.getElementById('smartLocationChips');
            if (locBar) locBar.style.display = 'none';

            renderSmartChips(smartCatalog);
            updateLivePreview();
        }

        function renderLocationChips(locList) {
            const bar = document.getElementById('smartLocationChips');
            const listEl = document.getElementById('smartLocationChipsList');
            if (!bar || !listEl) return;

            if (!locList || locList.length === 0) {
                bar.style.display = 'none';
                return;
            }

            bar.style.display = 'flex';
            let html = '';
            locList.forEach(loc => {
                html += `
                    <button type="button" class="location-pill" onclick="setLocationInput('${escapeJs(loc)}')">
                        ${escapeHtml(loc)}
                    </button>
                `;
            });
            listEl.innerHTML = html;
        }

        function setLocationInput(loc) {
            const input = document.getElementById('intake_location');
            if (input) {
                input.value = loc;
                updateLivePreview();
            }
        }

        // Reactive Spec Collision Checker
        function onSpecsInputChange() {
            updateLivePreview();
            clearTimeout(specCollisionTimer);
            specCollisionTimer = setTimeout(checkSpecCollisionReactive, 200);
        }

        function checkSpecCollisionReactive() {
            const brand = document.getElementById('intake_brand')?.value?.trim() || '';
            const model = document.getElementById('intake_model')?.value?.trim() || '';
            const series = document.getElementById('intake_series')?.value?.trim() || '';
            const cpuSpecs = document.getElementById('intake_cpu_specs')?.value?.trim() || '';
            const ram = document.getElementById('intake_ram')?.value?.trim() || '';
            const storage = document.getElementById('intake_storage')?.value?.trim() || '';

            if (!brand || !model) return;

            // Find matching label in smartCatalog
            const match = smartCatalog.find(l => {
                return l.brand.toLowerCase() === brand.toLowerCase() &&
                       l.model.toLowerCase() === model.toLowerCase() &&
                       (l.series || '').toLowerCase() === series.toLowerCase() &&
                       (l.cpu_specs || '').toLowerCase() === cpuSpecs.toLowerCase() &&
                       (l.ram || '').toLowerCase() === ram.toLowerCase() &&
                       (l.storage || '').toLowerCase() === storage.toLowerCase();
            });

            if (match) {
                document.getElementById('intake_label_id').value = match.id;
                const banner = document.getElementById('smartMatchedBanner');
                const titleEl = document.getElementById('smartMatchedTitle');
                const locsEl = document.getElementById('smartMatchedLocs');
                if (banner && titleEl && locsEl) {
                    banner.style.display = 'flex';
                    titleEl.textContent = `✨ Same Specs Matched: Smart Label #${match.id} (${match.display_title})`;
                    const locText = match.locations_list && match.locations_list.length > 0
                        ? match.locations_list.join(', ')
                        : 'Unassigned';
                    locsEl.textContent = `Tracked Locations for this Spec: ${locText}`;
                }
                renderLocationChips(match.locations_list);
            } else {
                // Unique specs
                document.getElementById('intake_label_id').value = '';
                const banner = document.getElementById('smartMatchedBanner');
                if (banner) banner.style.display = 'none';
                const locBar = document.getElementById('smartLocationChips');
                if (locBar) locBar.style.display = 'none';
            }
        }

        // ── 1. MODAL VISIBILITY & FULLSCREEN CONTROLS ───────────────────────
        function openIntakeModal() {
            const modal = document.getElementById('techIntakeModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            fetchSmartCatalog();

            const snInput = (currentIntakeMode === 'batch') 
                ? document.getElementById('batch_sn_scanner') 
                : document.getElementById('intake_serial_number');
            if (snInput) {
                setTimeout(() => { snInput.focus(); }, 150);
            }
            updateLivePreview();
        }

        function closeIntakeModal() {
            const modal = document.getElementById('techIntakeModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function toggleModalFullscreen() {
            const dialog = document.getElementById('techIntakeDialog');
            const btn = document.getElementById('btnToggleFullscreen');
            if (!dialog) return;
            dialog.classList.toggle('fullscreen');
            if (dialog.classList.contains('fullscreen')) {
                if (btn) btn.innerHTML = '🗗';
                if (btn) btn.title = 'Exit Fullscreen';
            } else {
                if (btn) btn.innerHTML = '⛶';
                if (btn) btn.title = 'Toggle Fullscreen';
            }
        }

        function onModalBackdropClick(e) {
            if (e.target.id === 'techIntakeModal') {
                closeIntakeModal();
            }
        }

        // Close on Esc key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeIntakeModal();
                closeDeliveryModal();
                closeEditModal();
            }
        });

        // ── BATCH INTAKE CONTROLLER ──────────────────────────────────────────
        function switchIntakeMode(mode) {
            currentIntakeMode = mode;
            const btnSingle = document.getElementById('btnModeSingle');
            const btnBatch = document.getElementById('btnModeBatch');
            const batchCard = document.getElementById('batchIntakeCard');
            const singleSnGroup = document.getElementById('singleSnGroup');

            if (mode === 'batch') {
                btnBatch.classList.add('active');
                btnSingle.classList.remove('active');
                batchCard.classList.remove('hidden');
                if (singleSnGroup) singleSnGroup.style.display = 'none';

                if (!document.getElementById('intake_batch_number').value.trim()) {
                    autoGenerateBatchNumber();
                }
                setTimeout(() => {
                    document.getElementById('batch_sn_scanner')?.focus();
                }, 100);
            } else {
                btnSingle.classList.add('active');
                btnBatch.classList.remove('active');
                batchCard.classList.add('hidden');
                if (singleSnGroup) singleSnGroup.style.display = 'block';

                setTimeout(() => {
                    document.getElementById('intake_serial_number')?.focus();
                }, 100);
            }
        }

        function autoGenerateBatchNumber() {
            const d = new Date();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const hh = String(d.getHours()).padStart(2, '0');
            const rnd = Math.floor(100 + Math.random() * 900);
            const code = `LOT-${mm}${dd}-${rnd}`;
            const input = document.getElementById('intake_batch_number');
            if (input) input.value = code;
        }

        function onBatchScannerKeyDown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addBatchSerialFromInput();
            }
        }

        function addBatchSerialFromInput() {
            const input = document.getElementById('batch_sn_scanner');
            const sn = input.value.trim();
            if (!sn) return;

            if (batchSerialsList.includes(sn)) {
                alert(`Serial number "${sn}" is already in this batch queue.`);
                input.value = '';
                input.focus();
                return;
            }

            batchSerialsList.push(sn);
            input.value = '';
            renderBatchChips();
            input.focus();
        }

        function removeBatchSerial(index) {
            batchSerialsList.splice(index, 1);
            renderBatchChips();
        }

        function clearBatchSerials() {
            batchSerialsList = [];
            renderBatchChips();
        }

        function renderBatchChips() {
            const container = document.getElementById('batchSnChipsContainer');
            const badge = document.getElementById('batchCountBadge');
            const hidden = document.getElementById('intake_batch_serials');

            hidden.value = JSON.stringify(batchSerialsList);
            badge.textContent = `${batchSerialsList.length} units queued`;

            if (batchSerialsList.length === 0) {
                container.innerHTML = '<span style="color: #94a3b8; font-size: 0.8rem; font-style: italic; padding: 6px;">Scan barcode or type S/N and press Enter to queue multiple laptops...</span>';
                return;
            }

            let html = '';
            batchSerialsList.forEach((sn, idx) => {
                html += `
                    <span class="batch-sn-chip">
                        <span>🏷️ ${escapeHtml(sn)}</span>
                        <span class="batch-sn-chip-remove" onclick="removeBatchSerial(${idx})" title="Remove">&times;</span>
                    </span>
                `;
            });
            container.innerHTML = html;
        }

        function onBatchQtyChange(val) {
            const q = parseInt(val, 10);
            if (batchSerialsList.length === 0) {
                document.getElementById('batchCountBadge').textContent = `${q || 1} units bulk`;
            }
        }

        // ── TODAY'S PRODUCTIVITY BAR RENDERER ────────────────────────────────
        function renderTodayProductivity(stats) {
            const bar = document.getElementById('todayProductivityBar');
            const container = document.getElementById('techProdChipsContainer');
            const totalBadge = document.getElementById('prodTotalBadge');
            if (!bar || !container) return;

            if (!stats || stats.length === 0) {
                bar.style.display = 'none';
                return;
            }

            bar.style.display = 'flex';
            const totalTested = stats.reduce((sum, s) => sum + parseInt(s.total_units || 0), 0);
            const totalGood = stats.reduce((sum, s) => sum + parseInt(s.good_units || 0), 0);
            const totalBad = stats.reduce((sum, s) => sum + parseInt(s.bad_units || 0), 0);
            totalBadge.textContent = `${totalTested} units tested today (${totalGood} Good / ${totalBad} Bad)`;

            const currentSelectedTech = document.getElementById('tech_filter')?.value || '';

            let html = `
                <button type="button" class="tech-prod-chip ${currentSelectedTech === '' ? 'active' : ''}" onclick="selectTechFilter('')">
                    <span>👥 All Technicians</span>
                </button>
            `;

            stats.forEach(s => {
                const isSelected = (currentSelectedTech === s.tech_id);
                const lastTime = s.last_activity ? formatPacificTime(s.last_activity) : '';
                html += `
                    <button type="button" class="tech-prod-chip ${isSelected ? 'active' : ''}" onclick="selectTechFilter('${escapeJs(s.tech_id)}')">
                        <span>👤 <strong>${escapeHtml(s.tech_id)}</strong>:</span>
                        <span style="font-weight: 800;">${s.total_units}</span>
                        <span class="tech-prod-badge good">${s.good_units}G</span>
                        <span class="tech-prod-badge bad">${s.bad_units}B</span>
                        <small style="color: #64748b; font-size: 0.72rem; margin-left: 2px;">${lastTime}</small>
                    </button>
                `;
            });

            container.innerHTML = html;
        }

        function selectTechFilter(techId) {
            const techSelect = document.getElementById('tech_filter');
            if (techSelect) {
                techSelect.value = techId;
            }
            fetchLogs();
        }

        // ── 2. PRESETS & PILL HANDLERS ───────────────────────────────────────
        function applyPreset(presetId) {
            const p = IQA_Presets.find(item => item.id === presetId);
            if (!p) return;

            selectBrandPill(p.brand);
            document.getElementById('intake_model').value = p.model;
            document.getElementById('intake_series').value = p.series;
            document.getElementById('intake_cpu_gen').value = p.cpu_gen;
            document.getElementById('intake_cpu_specs').value = p.cpu_specs;
            
            onCpuCoresInput(p.cpu_cores);
            onCpuSpeedInput(p.cpu_speed);

            selectSpecPill('ram', p.ram);
            selectSpecPill('storage', p.storage);
            document.getElementById('intake_condition').value = p.condition;

            onSpecsInputChange();
        }

        function selectBrandPill(brand) {
            document.getElementById('intake_brand').value = brand;
            document.querySelectorAll('#intakeBrandPills .brand-pill').forEach(btn => {
                btn.classList.toggle('selected', btn.dataset.brand === brand);
            });
            onSpecsInputChange();
        }

        function selectSpecPill(type, val) {
            if (type === 'ram') {
                document.getElementById('intake_ram').value = val;
                document.querySelectorAll('#intakeRamPills .spec-pill').forEach(btn => {
                    btn.classList.toggle('selected', btn.textContent.trim() === val);
                });
            } else if (type === 'storage') {
                document.getElementById('intake_storage').value = val;
                document.querySelectorAll('#intakeStoragePills .spec-pill').forEach(btn => {
                    btn.classList.toggle('selected', btn.textContent.trim() === val);
                });
            }
            onSpecsInputChange();
        }

        function onCpuCoresInput(valOrEl) {
            const display = document.getElementById('intake_cpu_cores_display');
            const suffix = document.getElementById('intake_cpu_cores_suffix');
            const hidden = document.getElementById('intake_cpu_cores');
            if (!display) return;

            let val = (valOrEl && valOrEl.nodeType) ? valOrEl.value : (valOrEl !== undefined && valOrEl !== null ? String(valOrEl) : display.value);
            const num = val.replace(/\D/g, '');
            if (display.value !== num) {
                display.value = num;
            }
            if (num) {
                const sfx = (parseInt(num, 10) === 1 ? 'Core' : 'Cores');
                if (suffix) {
                    suffix.textContent = sfx;
                    suffix.style.display = 'inline-flex';
                }
                if (hidden) hidden.value = num + ' ' + sfx;
            } else {
                if (suffix) suffix.style.display = 'none';
                if (hidden) hidden.value = '';
            }
            updateLivePreview();
        }

        function onCpuSpeedInput(valOrEl) {
            const display = document.getElementById('intake_cpu_speed_display');
            const suffix = document.getElementById('intake_cpu_speed_suffix');
            const hidden = document.getElementById('intake_cpu_speed');
            if (!display) return;

            // Programmatic update (presets, barcode auto-match)
            if (valOrEl !== undefined && valOrEl !== null && !valOrEl.nodeType) {
                const str = String(valOrEl).trim();
                if (str === '') {
                    display.value = '';
                    if (suffix) suffix.style.display = 'none';
                    if (hidden) hidden.value = '';
                    updateLivePreview();
                    return;
                }
                const match = str.match(/[0-9]+(\.[0-9]+)?/);
                if (match) {
                    display.value = match[0];
                    if (suffix) {
                        suffix.textContent = 'GHz';
                        suffix.style.display = 'inline-flex';
                    }
                    if (hidden) hidden.value = match[0] + ' GHz';
                } else {
                    display.value = '';
                    if (suffix) suffix.style.display = 'none';
                    if (hidden) hidden.value = '';
                }
                updateLivePreview();
                return;
            }

            // Interactive typing: preserve decimal point while typing float (e.g. "1.", "1.2", "1.20")
            let raw = display.value;
            let val = raw.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }

            if (display.value !== val) {
                display.value = val;
            }

            if (val.trim() === '' || val.trim() === '.') {
                if (suffix) suffix.style.display = 'none';
                if (hidden) hidden.value = '';
            } else {
                if (suffix) {
                    suffix.textContent = 'GHz';
                    suffix.style.display = 'inline-flex';
                }
                if (hidden) {
                    const cleanNum = val.replace(/\.$/, '');
                    hidden.value = cleanNum + ' GHz';
                }
            }
            updateLivePreview();
        }

        // Keydown filter for Clock Frequency decimal input & Initial Catalog Fetch
        document.addEventListener('DOMContentLoaded', () => {
            fetchSmartCatalog();
            const speedDisplay = document.getElementById('intake_cpu_speed_display');
            if (speedDisplay) {
                speedDisplay.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
                        e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown' ||
                        e.key === 'Home' || e.key === 'End' || e.key === 'Enter' || e.ctrlKey || e.metaKey) {
                        return;
                    }
                    if (e.key === '.') {
                        if (speedDisplay.value.includes('.')) {
                            e.preventDefault();
                        }
                        return;
                    }
                    if (!/^[0-9]$/.test(e.key)) {
                        e.preventDefault();
                    }
                });
            }
        });

        function setIntakeStatus(status) {
            document.getElementById('intake_status').value = status;
            const btnGood = document.getElementById('btnIntakeGood');
            const btnBad = document.getElementById('btnIntakeBad');

            if (status === 'Good') {
                btnGood.classList.add('active');
                btnBad.classList.remove('active');
            } else {
                btnGood.classList.remove('active');
                btnBad.classList.add('active');
            }
            updateLivePreview();
        }

        function onDeliveryTargetChange(target) {
            const destInput = document.getElementById('intake_delivery_destination');
            const lbl = document.getElementById('lblDeliveryDestination');

            if (target === 'Delivered to Shelf') {
                lbl.textContent = 'SHELF LOCATION:';
                destInput.placeholder = 'e.g. Shelf A-1-2';
                const loc = document.getElementById('intake_location').value.trim();
                if (loc && !destInput.value) destInput.value = loc;
            } else if (target === 'Delivered to Order') {
                lbl.textContent = 'ORDER # / BUYER NAME:';
                destInput.placeholder = 'e.g. Order #1042 (Alex M.)';
            } else {
                lbl.textContent = 'DESTINATION DETAILS:';
                destInput.placeholder = 'e.g. Shelf A-1-2 or Order #1042';
            }
        }

        // ── 3. REAL-TIME BARCODE S/N LOOKUP BRIDGE ───────────────────────────
        document.getElementById('intake_serial_number').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const sn = e.target.value.trim();
                const brand = document.getElementById('intake_brand')?.value.trim();
                const model = document.getElementById('intake_model')?.value.trim();
                if (sn && brand && model) {
                    submitIntake('thermal');
                }
            }
        });

        document.getElementById('intake_serial_number').addEventListener('input', (e) => {
            const sn = e.target.value.trim();
            const statusEl = document.getElementById('snLookupStatus');
            const alertBox = document.getElementById('snMatchedAlert');

            updateLivePreview();

            if (snLookupDebounceTimer) clearTimeout(snLookupDebounceTimer);

            if (sn.length < 3) {
                statusEl.textContent = 'Ready for Barcode Scanner';
                statusEl.style.color = '#64748b';
                alertBox.style.display = 'none';
                return;
            }

            statusEl.textContent = '🔍 Checking labels...';
            statusEl.style.color = '#2563eb';

            snLookupDebounceTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`../api/lookup_label.php?sn=${encodeURIComponent(sn)}`);
                    const result = await response.json();

                    if (result.success && result.found && result.data) {
                        const d = result.data;
                        statusEl.textContent = '✅ Profile Matched';
                        statusEl.style.color = '#15803d';

                        document.getElementById('intake_label_id').value = d.id;
                        document.getElementById('snMatchedText').innerHTML = `🏷️ <strong>Matched Label #${d.id}</strong>: ${escapeHtml(d.brand)} ${escapeHtml(d.model)} (${escapeHtml(d.cpu_specs || '-')})`;
                        alertBox.style.display = 'flex';

                        // Autofill specs from existing label
                        if (d.brand) selectBrandPill(d.brand);
                        if (d.model) document.getElementById('intake_model').value = d.model;
                        if (d.series) document.getElementById('intake_series').value = d.series;
                        if (d.cpu_gen) document.getElementById('intake_cpu_gen').value = d.cpu_gen;
                        if (d.cpu_specs) document.getElementById('intake_cpu_specs').value = d.cpu_specs;
                        if (d.cpu_cores) onCpuCoresInput(d.cpu_cores);
                        if (d.cpu_speed) onCpuSpeedInput(d.cpu_speed);
                        if (d.ram) selectSpecPill('ram', d.ram);
                        if (d.storage) selectSpecPill('storage', d.storage);
                        if (d.warehouse_location) document.getElementById('intake_location').value = d.warehouse_location;
                        if (d.gpu) document.getElementById('intake_gpu').value = d.gpu;
                        if (d.screen_res) document.getElementById('intake_screen_res').value = d.screen_res;
                        if (d.os_version) document.getElementById('intake_os').value = d.os_version;
                        if (d.bios_state) document.getElementById('intake_bios_state').value = d.bios_state;

                        updateLivePreview();
                    } else {
                        statusEl.textContent = '✨ New Hardware Profile';
                        statusEl.style.color = '#0284c7';
                        alertBox.style.display = 'none';
                        document.getElementById('intake_label_id').value = '';
                    }
                } catch (err) {
                    statusEl.textContent = 'Ready for Barcode Scanner';
                }
            }, 250);
        });

        function clearMatchedProfile() {
            document.getElementById('intake_label_id').value = '';
            document.getElementById('snMatchedAlert').style.display = 'none';
            document.getElementById('snLookupStatus').textContent = 'Ready for Barcode Scanner';
            document.getElementById('snLookupStatus').style.color = '#64748b';
        }

        // ── 4. LIVE 2" x 1" THERMAL STICKER MOCKUP REACTIVITY ────────────────
        function updateLivePreview() {
            const brand = document.getElementById('intake_brand')?.value || 'BRAND';
            const model = document.getElementById('intake_model')?.value || 'MODEL';
            const series = document.getElementById('intake_series')?.value || '';
            const cpuGen = document.getElementById('intake_cpu_gen')?.value || '';
            const cpuSpecs = document.getElementById('intake_cpu_specs')?.value || 'Processor N/A';
            const ram = document.getElementById('intake_ram')?.value || '16 GB';
            const storage = document.getElementById('intake_storage')?.value || '256 GB SSD';
            const sn = document.getElementById('intake_serial_number')?.value || 'SCANNING...';
            const loc = document.getElementById('intake_location')?.value || '—';
            const cond = document.getElementById('intake_condition')?.value?.toUpperCase() || 'REFURBISHED';
            const bios = document.getElementById('intake_bios_state')?.value?.toUpperCase() || 'UNLOCKED';
            const labelId = document.getElementById('intake_label_id')?.value ? '#' + document.getElementById('intake_label_id').value : '#NEW';
            const gpu = document.getElementById('intake_gpu')?.value || 'Integrated';
            const os = document.getElementById('intake_os')?.value || 'Win 11 Pro';

            const cpuCores = document.getElementById('intake_cpu_cores')?.value?.trim() || '';
            const cpuSpeed = document.getElementById('intake_cpu_speed')?.value?.trim() || '';
            const cpuDetail = [cpuCores, cpuSpeed].filter(Boolean).join(' @ ');

            let fullCpuDisplay = cpuSpecs;
            if (cpuDetail) {
                fullCpuDisplay += ` (${cpuDetail})`;
            } else if (cpuGen) {
                fullCpuDisplay += ` (${cpuGen})`;
            }

            // Label A (Branding)
            const brandModelEl = document.getElementById('prevBrandModel');
            if (brandModelEl) brandModelEl.textContent = `${brand} ${model}`.trim();
            const seriesEl = document.getElementById('prevSeries');
            if (seriesEl) seriesEl.textContent = series ? `Series: ${series}` : 'Standard Series';
            const cpuEl = document.getElementById('prevCpu');
            if (cpuEl) cpuEl.textContent = fullCpuDisplay;
            const snEl = document.getElementById('prevSN');
            if (snEl) snEl.textContent = sn ? `S/N: ${sn}` : 'S/N: SCANNING...';
            const locEl = document.getElementById('prevLoc');
            if (locEl) locEl.textContent = `LOC: ${loc}`;
            const condEl = document.getElementById('prevCond');
            if (condEl) condEl.textContent = cond;
            const lblIdEl = document.getElementById('prevLabelId');
            if (lblIdEl) lblIdEl.textContent = labelId;

            // Label B (Specs)
            const specsHeader = document.getElementById('prevSpecsHeaderBrand');
            if (specsHeader) specsHeader.textContent = `${brand} SPECS`;
            const specsId = document.getElementById('prevSpecsLabelId');
            if (specsId) specsId.textContent = labelId;
            const specsCpu = document.getElementById('prevSpecsCpu');
            if (specsCpu) specsCpu.textContent = fullCpuDisplay;
            const specsRam = document.getElementById('prevSpecsRam');
            if (specsRam) specsRam.textContent = ram;
            const specsStorage = document.getElementById('prevSpecsStorage');
            if (specsStorage) specsStorage.textContent = storage;
            const specsGpu = document.getElementById('prevSpecsGpu');
            if (specsGpu) specsGpu.textContent = gpu;
            const specsOs = document.getElementById('prevSpecsOs');
            if (specsOs) specsOs.textContent = os;
            const specsBios = document.getElementById('prevSpecsBios');
            if (specsBios) specsBios.textContent = bios;
            const specsLoc = document.getElementById('prevSpecsLoc');
            if (specsLoc) specsLoc.textContent = `📍 LOC: ${loc}`;
            const specsCond = document.getElementById('prevSpecsCond');
            if (specsCond) specsCond.textContent = cond;
        }

        // Live input listeners for mockup and reactive spec collision matching
        ['intake_model', 'intake_series', 'intake_cpu_gen', 'intake_cpu_specs', 'intake_ram', 'intake_storage', 'intake_location', 'intake_condition', 'intake_bios_state', 'intake_gpu', 'intake_os'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', onSpecsInputChange);
                el.addEventListener('change', onSpecsInputChange);
            }
        });

        function switchPreviewTab(mode) {
            const btnBoth = document.getElementById('btnPrevBoth');
            const btnA = document.getElementById('btnPrevA');
            const btnB = document.getElementById('btnPrevB');
            const mockupA = document.getElementById('mockupLabelA');
            const mockupB = document.getElementById('mockupLabelB');

            [btnBoth, btnA, btnB].forEach(b => b.classList.remove('active'));

            if (mode === 'both') {
                btnBoth.classList.add('active');
                mockupA.style.display = 'flex';
                mockupB.style.display = 'flex';
            } else if (mode === 'a') {
                btnA.classList.add('active');
                mockupA.style.display = 'flex';
                mockupB.style.display = 'none';
            } else if (mode === 'b') {
                btnB.classList.add('active');
                mockupA.style.display = 'none';
                mockupB.style.display = 'flex';
            }
        }

        function resetIntakeForm() {
            const form = document.getElementById('techIntakeForm');
            const pinLoc = document.getElementById('intake_pin_location');
            const savedLoc = pinLoc && pinLoc.checked ? document.getElementById('intake_location').value : '';

            form.reset();
            if (pinLoc && pinLoc.checked) {
                document.getElementById('intake_location').value = savedLoc;
                pinLoc.checked = true;
            }

            selectBrandPill('Dell');
            selectSpecPill('ram', '16 GB');
            selectSpecPill('storage', '256 GB SSD');
            onCpuCoresInput('');
            onCpuSpeedInput('');
            setIntakeStatus('Good');
            clearMatchedProfile();
            unlinkSmartLabel();
            clearSmartSearch();
            updateLivePreview();

            const snInput = document.getElementById('intake_serial_number');
            if (snInput) snInput.focus();
        }

        // ── 5. FORM SUBMISSION EXECUTION (PRINT / ODT / SAVE) ────────────────
        async function submitIntake(action) {
            const form = document.getElementById('techIntakeForm');
            const msgEl = document.getElementById('intakeFormMessage');
            msgEl.textContent = 'Saving hardware record...';
            msgEl.style.color = '#2563eb';

            const brand = document.getElementById('intake_brand').value.trim();
            const model = document.getElementById('intake_model').value.trim();

            if (!brand || !model) {
                msgEl.textContent = '❌ Brand and Model are required.';
                msgEl.style.color = '#dc2626';
                return;
            }

            const formData = new FormData(form);
            formData.append('print_action', action);

            const isBatch = (currentIntakeMode === 'batch');
            const isContinuous = document.getElementById('intake_continuous')?.checked ?? true;

            if (isBatch) {
                const batchNum = document.getElementById('intake_batch_number').value.trim();
                formData.set('batch_number', batchNum);
                formData.set('batch_serials', JSON.stringify(batchSerialsList));
                formData.set('qty', batchSerialsList.length > 0 ? batchSerialsList.length : (document.getElementById('intake_batch_qty').value || 1));
            } else {
                const singleBatch = document.getElementById('intake_single_batch')?.value.trim() || document.getElementById('intake_batch_number')?.value.trim();
                formData.set('batch_number', singleBatch);
            }

            try {
                const response = await fetch('../api/add_log.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!result.success) {
                    msgEl.textContent = '❌ Error: ' + (result.error || 'Failed to save');
                    msgEl.style.color = '#dc2626';
                    return;
                }

                msgEl.textContent = '✅ ' + (result.message || 'Saved successfully!');
                msgEl.style.color = '#15803d';

                const labelId = result.label_id;
                const labelIds = result.label_ids || (labelId ? [labelId] : []);

                // Handle selected printing action
                if (action === 'thermal') {
                    if (labelIds.length > 0) {
                        labelIds.forEach((lid, i) => {
                            setTimeout(() => printThermalDirect(lid), i * 600);
                        });
                    }
                } else if (action === 'odt') {
                    if (labelId) {
                        printODTDirect(labelId, result.make, result.model);
                    } else if (result.log_id) {
                        printLabel(result.log_id);
                    }
                }

                fetchLogs();

                if (isContinuous) {
                    if (isBatch) {
                        clearBatchSerials();
                        autoGenerateBatchNumber();
                        setTimeout(() => {
                            document.getElementById('batch_sn_scanner')?.focus();
                        }, 100);
                    } else {
                        // Continuous single unit: clear S/N, keep all specs intact!
                        const snInput = document.getElementById('intake_serial_number');
                        if (snInput) {
                            snInput.value = '';
                            clearMatchedProfile();
                            snInput.focus();
                        }
                    }

                    const pinLoc = document.getElementById('intake_pin_location');
                    if (!pinLoc || !pinLoc.checked) {
                        // If not pinned, clear location, or keep if preferred
                    }

                    updateLivePreview();
                    setTimeout(() => { msgEl.textContent = ''; }, 3500);

                    if (action === 'save' && !isContinuous) {
                        closeIntakeModal();
                    }
                } else {
                    setTimeout(() => {
                        closeIntakeModal();
                        msgEl.textContent = '';
                    }, 800);
                }

            } catch (err) {
                msgEl.textContent = '❌ Network Error saving record.';
                msgEl.style.color = '#dc2626';
            }
        }

        // Direct Browser Thermal Printing Bridge (2" x 1")
        function printThermalDirect(labelId) {
            const printUrl = `../../labels/print_label.php?id=${labelId}&mode=both&autoprint=1`;
            const frame = document.getElementById('thermalPrintFrame');
            if (frame) {
                frame.src = printUrl;
            } else {
                window.open(printUrl, '_blank', 'width=420,height=300');
            }
        }

        // Windows LibreOffice Thermal ODT Bridge
        function printODTDirect(labelId, brand, model) {
            const formData = new FormData();
            formData.append('id', labelId);
            formData.append('qty', 1);

            fetch('../../labels/api/reprint_label.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.success && res.file_path) {
                    window.location.href = '../../labels/' + res.file_path;
                }
            }).catch(() => {
                window.location.href = `../../labels/print_label.php?id=${labelId}&mode=both`;
            });
        }

        // ── 6. DELIVERY STATUS QUICK ACTIONS ─────────────────────────────────
        function openDeliveryModal(logId, currentLoc) {
            document.getElementById('deliveryLogId').value = logId;
            document.getElementById('deliveryDestinationInput').value = currentLoc || '';
            document.getElementById('deliveryActionType').value = 'shelf';
            onDeliveryModalTypeChange('shelf');
            document.getElementById('deliveryModal').style.display = 'flex';
        }

        function closeDeliveryModal() {
            document.getElementById('deliveryModal').style.display = 'none';
        }

        function onDeliveryModalTypeChange(type) {
            const lbl = document.getElementById('deliveryDestLabel');
            const input = document.getElementById('deliveryDestinationInput');
            if (type === 'order') {
                lbl.textContent = 'ORDER # / BUYER NAME:';
                input.placeholder = 'e.g. Order #1042 (Alex M.)';
            } else {
                lbl.textContent = 'SHELF LOCATION:';
                input.placeholder = 'e.g. Shelf A-1-2';
            }
        }

        async function confirmDeliveryUpdate() {
            const id = document.getElementById('deliveryLogId').value;
            const type = document.getElementById('deliveryActionType').value;
            const dest = document.getElementById('deliveryDestinationInput').value.trim();

            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('type', type);
                formData.append('destination', dest);

                const response = await fetch('../api/pass_warehouse.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    closeDeliveryModal();
                    fetchLogs();
                } else {
                    alert('Error: ' + (result.error || 'Failed to update delivery status.'));
                }
            } catch (err) {
                alert('Network error updating delivery status.');
            }
        }

        // ── 7. TABLE FETCH & RENDER ──────────────────────────────────────────
        function getLocalDateString() {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
        }

        // Default to today
        const todayStr = getLocalDateString();
        document.getElementById('start_date').value = todayStr;
        document.getElementById('end_date').value = todayStr;

        function setActiveTab(status) {
            currentStatus = status;
            const btnGood = document.getElementById('tab-good');
            const btnBad = document.getElementById('tab-bad');

            if (status === 'Good') {
                btnGood.style.background = '#22c55e';
                btnGood.style.color = 'white';
                btnBad.style.background = 'white';
                btnBad.style.color = '#475569';
            } else {
                btnGood.style.background = 'white';
                btnGood.style.color = '#475569';
                btnBad.style.background = '#ef4444';
                btnBad.style.color = 'white';
            }
            fetchLogs();
        }

        function onDateFilterChange() { fetchLogs(); }
        function onSearchFilterChange() {
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(fetchLogs, 300);
        }

        function clearDateFilter() {
            const todayStr = getLocalDateString();
            document.getElementById('start_date').value = todayStr;
            document.getElementById('end_date').value = todayStr;
            document.getElementById('delivery_filter').value = '';
            document.getElementById('log_search').value = '';
            fetchLogs();
        }

        async function fetchLogs() {
            const tbody = document.getElementById('logsTableBody');
            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;
            const deliveryFilter = document.getElementById('delivery_filter').value;
            const search = document.getElementById('log_search').value.trim();

            let url = `../api/get_logs.php?status=${currentStatus}`;
            if (start && end) url += `&start_date=${start}&end_date=${end}`;
            if (deliveryFilter) url += `&delivery_status=${encodeURIComponent(deliveryFilter)}`;
            if (search) url += `&q=${encodeURIComponent(search)}`;

            const techSelect = document.getElementById('tech_filter');
            if (techSelect && techSelect.value) {
                url += `&tech_id=${encodeURIComponent(techSelect.value)}`;
            }

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    // Update Today's Technician Productivity Ribbon
                    renderTodayProductivity(result.tech_stats);

                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #64748b;">No ${currentStatus.toLowerCase()} laptop logs found matching your filters.</td></tr>`;
                        return;
                    }

                    let html = '';
                    result.data.forEach(log => {
                        const isGood = log.status === 'Good';
                        const badgeClass = isGood ? 'good' : 'bad';
                        const rowStyle = isGood ? 'background: #ffffff;' : 'background: #fff8f8;';

                        // Delivery Badge
                        let deliveryBadge = '';
                        const dStat = log.delivery_status || 'Pending';
                        if (dStat === 'Delivered to Shelf') {
                            deliveryBadge = `<span class="badge badge-shelved">📦 Shelved: ${escapeHtml(log.delivery_destination || log.location || 'Warehouse')}</span>`;
                        } else if (dStat === 'Delivered to Order') {
                            deliveryBadge = `<span class="badge badge-delivered">🚚 Delivered: ${escapeHtml(log.delivery_destination || 'Order')}</span>`;
                        } else {
                            deliveryBadge = `<span class="badge badge-pending">⏳ Pending Delivery</span>`;
                        }

                        // S/N display
                        const snDisplay = log.serial_number ? 
                            `<span style="font-family: monospace; font-weight: 800; color: #0f172a; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; font-size: 0.78rem;">S/N: ${escapeHtml(log.serial_number)}</span>` : '';

                        // Dates (Pacific Time)
                        const dateStr = formatPacificDate(log.created_at);
                        const timeStr = formatPacificTime(log.created_at);

                        // Edited & Rejected Badges
                        const editedBadge = parseInt(log.edited) === 1 ? `<span class="badge" style="background: #fef9c3; color: #854d0e; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Edited</span>` : '';
                        const rejectedBadge = parseInt(log.delete_requested) === 2 ? `<span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Delete Rejected</span>` : '';
                        const techBadge = log.tech_id ? `<small style="color: #4f46e5; font-weight: 700;">👤 Tech: ${escapeHtml(log.tech_id)}</small>` : '';

                        // Controls
                        let controlHtml = '';
                        if (parseInt(log.delete_requested) === 1) {
                            controlHtml = `
                                <div style="display: flex; gap: 4px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                    <span style="color: #ef4444; font-size: 0.75rem; font-weight: bold;">🗑️ Pending Delete</span>
                                    ${isAdmin ? `<button onclick="approveDelete(${log.id})" style="background: #dc2626; color: #ffffff; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 800;" title="Approve deletion as Admin">Approve</button>` : ''}
                                </div>
                            `;
                        } else {
                            const statusText = log.status_change_requested !== '' ?
                                `<span style="color: #f97316; font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px;">⏳ Pending Status -> ${escapeHtml(log.status_change_requested)}</span>` : '';

                            const deliverBtn = dStat !== 'Delivered to Order' ? 
                                `<button onclick="openDeliveryModal(${log.id}, '${escapeJs(log.location || '')}')" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 800;" title="Update warehouse shelf or order delivery">🚚 Deliver</button>` : '';

                            const thermalPrintBtn = log.label_id ?
                                `<button onclick="printThermalDirect(${log.label_id})" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 800;" title="Print 2x1 Thermal Label">🖨️ Thermal</button>` : '';

                            controlHtml = `
                                ${statusText}
                                <div style="display: flex; gap: 4px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                    ${deliverBtn}
                                    ${thermalPrintBtn}
                                    <button onclick="toggleStatus(${log.id})" style="background: #cbd5e1; color: #1e293b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🔄 Status</button>
                                    <button onclick="openEditModal(${JSON.stringify(log).replace(/"/g, '&quot;')})" style="background: #e2e8f0; color: #475569; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">✏️ Edit</button>
                                    <button onclick="requestDelete(${log.id})" style="background: #fee2e2; color: #991b1b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🗑️ Delete</button>
                                    <button onclick="printLabel(${log.id})" style="background: #1e293b; color: #ffffff; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;" title="Windows LibreOffice ODT Label">📄 ODT</button>
                                </div>
                            `;
                        }

                        html += `
                            <tr style="${rowStyle}">
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span class="badge ${badgeClass}" style="align-self: flex-start;">${escapeHtml(log.status)}</span>
                                        ${deliveryBadge}
                                    </div>
                                </td>
                                <td>
                                    <strong>${escapeHtml(log.make)} ${escapeHtml(log.model)}</strong>${editedBadge}${rejectedBadge}
                                    ${log.series ? `<br><small style="color: #64748b;">Series: ${escapeHtml(log.series)}</small>` : ''}
                                    <br><small style="color: #64748b;">Qty: ${log.qty}</small>
                                    <br>${snDisplay}
                                    ${log.batch_number ? `<br><span style="font-size:0.75rem; background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; padding:1px 6px; border-radius:4px; font-weight:800; display:inline-block; margin-top:2px;">📦 Lot: ${escapeHtml(log.batch_number)}</span>` : ''}
                                    ${log.label_id ? `<br><a href="../../labels/labels.php?search=${log.label_id}" target="_blank" style="display:inline-flex; align-items:center; gap:3px; margin-top:3px; font-size:0.75rem; color:#4f46e5; font-weight:800; text-decoration:none; background:#eef2ff; border:1px solid #c7d2fe; padding:1px 6px; border-radius:4px;" title="View & Edit in Labels System">🏷️ Label #${log.label_id} ↗</a>` : ''}
                                    <br>${techBadge}
                                </td>
                                <td>
                                    <small style="line-height: 1.4; display: block;">
                                        <strong>CPU:</strong> ${escapeHtml(log.cpu || log.cpu_specs || '-')}<br>
                                        <strong>RAM:</strong> ${escapeHtml(log.ram || '-')}&nbsp;|&nbsp;<strong>SSD:</strong> ${escapeHtml(log.storage || '-')}<br>
                                        <strong>BAT:</strong> ${escapeHtml(log.battery || 'N/A')} ${log.battery_specs ? `(${escapeHtml(log.battery_specs)})` : ''}
                                        ${log.gpu ? `<br><strong>GPU:</strong> ${escapeHtml(log.gpu)}` : ''}
                                        ${log.screen_res ? `<br><strong>Screen:</strong> ${escapeHtml(log.screen_res)}` : ''}
                                        ${log.os ? `<br><strong>OS:</strong> ${escapeHtml(log.os)}` : ''}
                                        ${log.cosmetic_grade ? `<br><strong>Grade:</strong> ${escapeHtml(log.cosmetic_grade)}` : ''}
                                    </small>
                                </td>
                                <td>
                                    <small style="line-height: 1.4; display: block;">
                                        ${log.location ? `<strong style="color: #0369a1;">📍 ${escapeHtml(log.location)}</strong><br>` : ''}
                                        <strong>Date:</strong> ${dateStr} ${timeStr}
                                        ${log.notes ? `<br><span style="color:#475569; font-style:italic;">"${escapeHtml(log.notes)}"</span>` : ''}
                                    </small>
                                </td>
                                <td style="text-align: right; vertical-align: middle;">
                                    ${controlHtml}
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444;">Failed to load logs.</td></tr>';
            }
        }

        // ── 8. EDIT MODAL LOGIC ──────────────────────────────────────────────
        function openEditModal(log) {
            document.getElementById('edit-id').value = log.id;
            document.getElementById('edit-qty').value = log.qty || 1;
            document.getElementById('edit-make').value = log.make || '';
            document.getElementById('edit-model').value = log.model || '';
            document.getElementById('edit-series').value = log.series || '';
            document.getElementById('edit-serial_number').value = log.serial_number || '';
            document.getElementById('edit-cpu').value = log.cpu || '';
            document.getElementById('edit-ram').value = log.ram || '';
            document.getElementById('edit-storage').value = log.storage || '';
            document.getElementById('edit-location').value = log.location || '';
            document.getElementById('edit-delivery_status').value = log.delivery_status || 'Pending';
            document.getElementById('edit-delivery_destination').value = log.delivery_destination || '';
            document.getElementById('edit-batch_number').value = log.batch_number || '';
            document.getElementById('edit-gpu').value = log.gpu || '';
            document.getElementById('edit-battery').value = log.battery || '';
            document.getElementById('edit-bios_state').value = log.bios_state || '';
            document.getElementById('edit-os').value = log.os || '';
            document.getElementById('edit-notes').value = log.notes || '';

            document.getElementById('editFormMessage').textContent = '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('editFormMessage');
            msg.textContent = 'Saving...';
            msg.style.color = '#475569';

            const formData = new FormData(e.target);
            try {
                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    msg.textContent = '✅ Log updated successfully!';
                    msg.style.color = '#15803d';
                    setTimeout(() => {
                        closeEditModal();
                        fetchLogs();
                    }, 800);
                } else {
                    msg.textContent = '❌ Error: ' + result.error;
                    msg.style.color = '#b91c1c';
                }
            } catch (err) {
                msg.textContent = '❌ Network Error';
                msg.style.color = '#b91c1c';
            }
        });

        // ── 9. ACTION HANDLERS (STATUS, DELETE, ODT) ─────────────────────────
        async function toggleStatus(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', id);

                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    fetchLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network error trying to toggle status.');
            }
        }

        async function requestDelete(id) {
            const confirmMsg = isAdmin ? 
                'Are you sure you want to permanently delete this log entry?' : 
                'Are you sure you want to request deletion of this entry?';
            if (!confirm(confirmMsg)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'request_delete');
                formData.append('id', id);

                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    fetchLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network error trying to request delete.');
            }
        }

        async function approveDelete(id) {
            if (!confirm('Approve and permanently delete this log entry?')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'approve_delete');
                formData.append('id', id);

                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    fetchLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network error trying to approve delete.');
            }
        }

        async function printLabel(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('qty', 1);
                formData.append('print_a', 1);
                formData.append('print_b', 1);

                const response = await fetch('../api/print_label.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = result.file_path;
                } else {
                    alert('Failed to generate label: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }

        function escapeJs(str) {
            if (!str) return '';
            return String(str).replace(/'/g, "\\'");
        }

        // Initial fetch
        fetchLogs();
    </script>
</body>
</html>
