<?php
require_once '../core/database.php';
require_once '../core/auth.php';

$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
$is_admin = (
    strtolower($user_role) === 'admin' || 
    strtolower($_SESSION['username'] ?? '') === 'admin' || 
    strpos(strtolower($user_role), 'admin') !== false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage & Donor Hub | Technician Dashboard</title>
    <!-- Design System Styles -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/storage.css?v=<?= filemtime(__DIR__ . '/../assets/styles/storage.css') ?>">
    <link rel="stylesheet" href="../assets/styles/qol_workbench.css?v=<?= filemtime(__DIR__ . '/../assets/styles/qol_workbench.css') ?>">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme" style="background-color: #f8fafc;">

    <!-- Breadcrumb Banner -->
    <div class="breadcrumb-container" role="banner">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">🗄️</span> Storage &amp; Donor Hub
            </a>
        </nav>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn-sound-toggle" onclick="TechWorkbench.Audio?.toggleMute()" title="Toggle Sound">
                <span>🔊</span> <span>Sound</span>
            </button>
            <a href="logs.php" class="btn-settings" style="background: #eff6ff; color: #1e40af; border-color: #bfdbfe; font-weight: 700;">
                📋 Hardware Logs
            </a>
            <?php if ($is_admin): ?>
            <a href="admin_audit.php" class="btn-settings" style="background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; font-weight: 700;">👥 Admin Audit</a>
            <?php endif; ?>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <!-- Main Storage Workbench Container -->
    <main class="container storage-container">

        <!-- Page Header -->
        <div class="page-header" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 14px;">
                <div>
                    <h1 style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 1.85rem; color: #ffffff;">
                        <span>🗄️</span> Storage &amp; Donor Hub
                    </h1>
                    <p style="margin: 6px 0 0 0; color: #cbd5e1; font-size: 0.95rem;">
                        Triage incomplete units, track donor machines, and harvest screens, batteries &amp; components.
                    </p>
                </div>
                <button type="button" class="btn-storage-primary" onclick="TechWorkbench.Storage.openAddModal()" style="height: 48px; border-radius: 12px; font-weight: 800; box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);">
                    ➕ Ingest Machine into Storage
                </button>
            </div>
        </div>

        <!-- Metric Stat Cards (Clickable Fast Filters) -->
        <div class="storage-stats-grid">
            <div class="storage-stat-card active" data-filter="all_active" title="View all active machines in storage">
                <div class="storage-stat-label"><span>🗄️</span> In Storage</div>
                <div class="storage-stat-value" id="statTotalStorage">-</div>
            </div>
            <div class="storage-stat-card" data-filter="needs_screen" title="Units missing or needing a replacement screen">
                <div class="storage-stat-label"><span>📺</span> Needs Screen</div>
                <div class="storage-stat-value" id="statNeedsScreen" style="color: #dc2626;">-</div>
            </div>
            <div class="storage-stat-card" data-filter="needs_battery" title="Units missing or needing a battery">
                <div class="storage-stat-label"><span>🔋</span> Needs Battery</div>
                <div class="storage-stat-value" id="statNeedsBattery" style="color: #d97706;">-</div>
            </div>
            <div class="storage-stat-card" data-filter="donors_screen" title="Donor machines with a working good screen">
                <div class="storage-stat-label"><span>⚡</span> Good Screens</div>
                <div class="storage-stat-value" id="statDonorsScreen" style="color: #0284c7;">-</div>
            </div>
            <div class="storage-stat-card" data-filter="donors_battery" title="Donor machines with a good battery">
                <div class="storage-stat-label"><span>⚡</span> Good Batteries</div>
                <div class="storage-stat-value" id="statDonorsBattery" style="color: #16a34a;">-</div>
            </div>
            <div class="storage-stat-card" data-filter="promoted" title="Machines promoted back to Good in Hardware Logs">
                <div class="storage-stat-label"><span>✅</span> Promoted</div>
                <div class="storage-stat-value" id="statPromoted" style="color: #15803d;">-</div>
            </div>
            <div class="storage-stat-card" data-filter="harvested" title="Machines harvested for parts / scrapped">
                <div class="storage-stat-label"><span>♻️</span> Harvested / Scrapped</div>
                <div class="storage-stat-value" id="statHarvested" style="color: #64748b;">-</div>
            </div>
        </div>

        <!-- Control & Filter Bar -->
        <div class="storage-controls-card">
            <div class="storage-search-box">
                <span class="storage-search-icon">🔍</span>
                <input type="text" id="storageSearchInput" class="storage-search-input" placeholder="Search SKU (e.g. RM-14, DL-5), Model, Issue, or Part...">
            </div>

            <div class="storage-filter-group">
                <select id="storageShelfSelect" class="storage-select" title="Filter by physical warehouse shelf / family">
                    <option value="">🏢 All Warehouse Shelves</option>
                    <option value="DL">💻 DL — Dell Latitude Shelf</option>
                    <option value="DP">🔬 DP — Dell Precision Shelf</option>
                    <option value="DeX">⚡ DeX — Dell XPS Shelf</option>
                    <option value="HE">💼 HE — HP EliteBook Shelf</option>
                    <option value="HZ">🚀 HZ — HP ZBook Shelf</option>
                    <option value="HpPro">🏢 HpPro — HP ProBook Shelf</option>
                    <option value="LT">🔴 LT — Lenovo ThinkPad Shelf</option>
                    <option value="LO">📱 LO — Lenovo Other Shelf</option>
                    <option value="AP">🍏 AP — Apple MacBook Shelf</option>
                    <option value="MS">🪟 MS — Microsoft Surface Shelf</option>
                    <option value="RM">📦 RM — General Missing 1 Part</option>
                </select>

                <select id="storageBrandSelect" class="storage-select">
                    <option value="">All Brands</option>
                    <option value="Apple">Apple</option>
                    <option value="Dell">Dell</option>
                    <option value="HP">HP</option>
                    <option value="Lenovo">Lenovo</option>
                    <option value="Microsoft">Microsoft</option>
                </select>

                <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.loadStorage()">
                    🔄 Refresh
                </button>
            </div>
        </div>

        <!-- Storage Inventory Table Card -->
        <div class="storage-table-card">
            <div style="overflow-x: auto;">
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Internal SKU</th>
                            <th style="width: 110px;">Date Added</th>
                            <th>Brand & Model #</th>
                            <th style="width: 170px;">Parts Condition</th>
                            <th style="width: 220px;">Issue & Need</th>
                            <th>Inspection Notes / Specs</th>
                            <th style="width: 220px; text-align: right;">Workbench Actions</th>
                        </tr>
                    </thead>
                    <tbody id="storageTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 40px; color: #64748b;">
                                ⏳ Initializing Storage inventory...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: Ingest Machine into Storage                                      -->
    <!-- ========================================================================= -->
    <div id="addStorageModal" class="storage-modal-overlay">
        <div class="storage-modal">
            <div class="storage-modal-header">
                <h3><span>➕</span> Ingest Machine into Storage</h3>
                <button type="button" class="storage-modal-close" onclick="TechWorkbench.Storage.closeAddModal()">&times;</button>
            </div>
            <form id="addStorageForm">
                <div class="storage-modal-body">
                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Brand *</label>
                            <select name="brand" class="storage-form-select" required>
                                <option value="">Select Brand...</option>
                                <option value="Dell">Dell</option>
                                <option value="HP">HP</option>
                                <option value="Lenovo">Lenovo</option>
                                <option value="Apple">Apple</option>
                                <option value="Microsoft">Microsoft</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="storage-form-group">
                            <label>Model Number *</label>
                            <input type="text" name="model_num" class="storage-input" placeholder="e.g. 7420, 650 G10, A2337" required>
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Series / Line</label>
                            <input type="text" name="series" class="storage-input" placeholder="e.g. Latitude, EliteBook, ThinkPad">
                        </div>
                        <div class="storage-form-group">
                            <label>Warehouse Shelf SKU</label>
                            <div style="height: 48px; background: #e0f2fe; border: 1.5px dashed #0284c7; border-radius: 8px; display: flex; align-items: center; padding: 0 14px; color: #0369a1; font-weight: 700; font-size: 0.88rem;">
                                🏷️ Auto-generated by Family (DL, DP, HE, LT, AP)
                            </div>
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Screen Condition</label>
                            <select name="screen" class="storage-form-select">
                                <option value="Good Screen">✔ Good Screen (Donor Viable)</option>
                                <option value="Cracked / Bad">✘ Cracked / Bad</option>
                                <option value="Lines / Defective">✘ Lines / Defective</option>
                                <option value="Missing Screen">✘ Missing Screen</option>
                                <option value="Untested">? Untested</option>
                            </select>
                        </div>
                        <div class="storage-form-group">
                            <label>Battery Condition</label>
                            <select name="battery" class="storage-form-select">
                                <option value="Good">✔ Good (Donor Viable)</option>
                                <option value="No Bat">✘ No Battery</option>
                                <option value="Bad BAT">✘ Bad / Swollen BAT</option>
                                <option value="Missing Cable">✘ Missing Cable</option>
                                <option value="Untested">? Untested</option>
                            </select>
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Primary Issue *</label>
                            <input type="text" name="issue" class="storage-input" placeholder="e.g. Cracked Screen, No Power, BIOs Lock" required>
                        </div>
                        <div class="storage-form-group">
                            <label>Needed Part</label>
                            <input type="text" name="need" class="storage-input" placeholder="e.g. Screen, Battery, Motherboard, SSD">
                        </div>
                    </div>

                    <div class="storage-form-group full-width">
                        <label>Technician Notes & Specs</label>
                        <textarea name="notes" class="storage-textarea" placeholder="RAM, Storage, CPU specs, cosmetic grading, or donor usability notes..."></textarea>
                    </div>

                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-top: 6px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span style="font-size: 0.88rem; font-weight: 800; color: #0f172a; display: inline-flex; align-items: center; gap: 6px;">
                            🏷️ Thermal Label Print Mode:
                        </span>
                        <div style="display: flex; gap: 10px; align-items: center; font-size: 0.86rem;">
                            <label id="lblFormatSkuOnly" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 700; padding: 6px 12px; background: #e0f2fe; border: 1.5px solid #0284c7; border-radius: 6px; color: #0369a1;">
                                <input type="radio" name="print_format_choice" value="sku_only" checked style="accent-color: #0284c7; width: 16px; height: 16px; cursor: pointer;" onchange="document.getElementById('lblFormatSkuOnly').style.background='#e0f2fe'; document.getElementById('lblFormatSkuOnly').style.borderColor='#0284c7'; document.getElementById('lblFormatSkuOnly').style.color='#0369a1'; document.getElementById('lblFormatDetailed').style.background='#fff'; document.getElementById('lblFormatDetailed').style.borderColor='#cbd5e1'; document.getElementById('lblFormatDetailed').style.color='#475569';">
                                <span>SKU &amp; Barcode Only (Storage Shelf)</span>
                            </label>
                            <label id="lblFormatDetailed" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 600; padding: 6px 12px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 6px; color: #475569;">
                                <input type="radio" name="print_format_choice" value="detailed" style="accent-color: #0284c7; width: 16px; height: 16px; cursor: pointer;" onchange="document.getElementById('lblFormatDetailed').style.background='#e0f2fe'; document.getElementById('lblFormatDetailed').style.borderColor='#0284c7'; document.getElementById('lblFormatDetailed').style.color='#0369a1'; document.getElementById('lblFormatSkuOnly').style.background='#fff'; document.getElementById('lblFormatSkuOnly').style.borderColor='#cbd5e1'; document.getElementById('lblFormatSkuOnly').style.color='#475569';">
                                <span>Detailed (Specs &amp; Issues)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="storage-modal-footer">
                    <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.closeAddModal()">Cancel</button>
                    <button type="submit" id="btnIngestOnly" name="print_action" value="save_only" class="btn-storage-secondary" style="background:#f1f5f9; color:#334155;">💾 Ingest Only</button>
                    <button type="submit" id="btnIngestPrint" name="print_action" value="save_and_print" class="btn-storage-primary" style="background:#0284c7;">🖨️ Ingest &amp; Print Barcode Label</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: Harvest Donor Part                                               -->
    <!-- ========================================================================= -->
    <div id="harvestPartModal" class="storage-modal-overlay">
        <div class="storage-modal">
            <div class="storage-modal-header">
                <h3><span>🛠️</span> Harvest Donor Part</h3>
                <button type="button" class="storage-modal-close" onclick="TechWorkbench.Storage.closeHarvestModal()">&times;</button>
            </div>
            <form id="harvestPartForm">
                <input type="hidden" name="id" id="harvestUnitId">
                <div class="storage-modal-body">
                    <p style="margin-top:0; color:#475569;">
                        Harvesting a working component from donor: <strong id="harvestUnitTitle" style="color:#0f172a;"></strong>
                    </p>

                    <div class="storage-form-group">
                        <label>Part to Harvest *</label>
                        <select name="part" class="storage-form-select" required>
                            <option value="Screen">📺 Screen (LCD Display Assembly)</option>
                            <option value="Battery">🔋 Battery</option>
                            <option value="Keyboard">⌨️ Keyboard / Palmrest</option>
                            <option value="Motherboard">💻 Motherboard</option>
                            <option value="RAM">💾 RAM Memory</option>
                            <option value="SSD">💽 Storage SSD / NVMe</option>
                            <option value="Other">🔧 Other Component</option>
                        </select>
                    </div>

                    <div class="storage-form-group">
                        <label>Recipient Machine / Order / Shelf Destination</label>
                        <input type="text" name="recipient" class="storage-input" placeholder="e.g. Laptop S/N 8XYZ123, Order #ORD-1044, or Bench">
                    </div>
                </div>
                <div class="storage-modal-footer">
                    <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.closeHarvestModal()">Cancel</button>
                    <button type="submit" class="btn-action-harvest" style="height: 48px; padding: 0 20px;">
                        🛠️ Confirm Harvest
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: Promote Unit to Good (Transfers to Hardware Logs)                 -->
    <!-- ========================================================================= -->
    <div id="promoteGoodModal" class="storage-modal-overlay">
        <div class="storage-modal">
            <div class="storage-modal-header">
                <h3><span>✅</span> Promote Unit to Good</h3>
                <button type="button" class="storage-modal-close" onclick="TechWorkbench.Storage.closePromoteModal()">&times;</button>
            </div>
            <form id="promoteGoodForm">
                <input type="hidden" name="id" id="promoteUnitId">
                <div class="storage-modal-body">
                    <p style="margin-top:0; color:#475569;">
                        The repair is finished and QC passed! This will register the machine in <strong>Hardware Logs</strong> as <strong>Good</strong> and route it to the warehouse shelf.
                    </p>
                    <p style="color:#0f172a; font-weight:700;" id="promoteUnitTitle"></p>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Hardware Serial / Service Tag</label>
                            <input type="text" name="serial_number" id="promoteSerial" class="storage-input" placeholder="Device Serial Number" required>
                        </div>
                        <div class="storage-form-group">
                            <label>RAM Capacity</label>
                            <input type="text" name="ram" class="storage-input" placeholder="e.g. 16 GB, 8 GB">
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Storage Drive</label>
                            <input type="text" name="storage" class="storage-input" placeholder="e.g. 256 GB SSD, 512 GB NVMe">
                        </div>
                        <div class="storage-form-group">
                            <label>CPU Specification</label>
                            <input type="text" name="cpu" class="storage-input" placeholder="e.g. i5-1135G7, i7-8650U">
                        </div>
                    </div>

                    <div class="storage-form-group full-width">
                        <label>Resolution Summary</label>
                        <input type="text" name="resolution_notes" class="storage-input" placeholder="e.g. Replaced screen with donor RM-09; passed 100% diagnostics." required>
                    </div>
                </div>
                <div class="storage-modal-footer">
                    <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.closePromoteModal()">Cancel</button>
                    <button type="submit" class="btn-action-promote" style="height: 48px; padding: 0 20px;">
                        ✅ Promote to Hardware Logs
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: Edit Unit Details                                                -->
    <!-- ========================================================================= -->
    <div id="editStorageModal" class="storage-modal-overlay">
        <div class="storage-modal">
            <div class="storage-modal-header">
                <h3><span>✏️</span> Edit Unit <span id="editUnitSku" style="color: #4f46e5;"></span></h3>
                <button type="button" class="storage-modal-close" onclick="TechWorkbench.Storage.closeEditModal()">&times;</button>
            </div>
            <form id="editStorageForm">
                <input type="hidden" name="id" id="editUnitId">
                <div class="storage-modal-body">
                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Brand</label>
                            <input type="text" name="brand" id="editBrand" class="storage-input" required>
                        </div>
                        <div class="storage-form-group">
                            <label>Model Number</label>
                            <input type="text" name="model_num" id="editModelNum" class="storage-input" required>
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Series</label>
                            <input type="text" name="series" id="editSeries" class="storage-input">
                        </div>
                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Internal SKU (Editable)</label>
                            <input type="text" name="sku" id="editSku" class="storage-input" required style="font-family: monospace; font-weight: 800; background: #fff;">
                        </div>
                        <div class="storage-form-group">
                            <label>Status</label>
                            <select name="status" id="editStatus" class="storage-form-select">
                                <option value="In Storage">In Storage</option>
                                <option value="Promoted to Good">Promoted to Good</option>
                                <option value="Harvested / Scrapped">Harvested / Scrapped</option>
                            </select>
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Screen Condition</label>
                            <input type="text" name="screen" id="editScreen" class="storage-input">
                        </div>
                        <div class="storage-form-group">
                            <label>Battery Condition</label>
                            <input type="text" name="battery" id="editBattery" class="storage-input">
                        </div>
                    </div>

                    <div class="storage-form-row">
                        <div class="storage-form-group">
                            <label>Issue</label>
                            <input type="text" name="issue" id="editIssue" class="storage-input" required>
                        </div>
                        <div class="storage-form-group">
                            <label>Need</label>
                            <input type="text" name="need" id="editNeed" class="storage-input">
                        </div>
                    </div>

                    <div class="storage-form-group full-width">
                        <label>Notes</label>
                        <textarea name="notes" id="editNotes" class="storage-textarea"></textarea>
                    </div>
                </div>
                <div class="storage-modal-footer">
                    <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.closeEditModal()">Cancel</button>
                    <button type="button" class="btn-storage-secondary" onclick="TechWorkbench.Storage.printCurrentEdit()" style="background:#f1f5f9; color:#0284c7; border:1px solid #bae6fd;">🖨️ Print Label (SKU Only)</button>
                    <button type="submit" class="btn-storage-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/modules/sound_fx.js"></script>
    <script src="../assets/js/modules/clipboard_tools.js"></script>
    <script src="../assets/js/modules/storage_workbench.js?v=<?= filemtime(__DIR__ . '/../assets/js/modules/storage_workbench.js') ?>"></script>
</body>
</html>
