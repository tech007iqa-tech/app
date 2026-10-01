<?php
/**
 * labels/includes/hardware_form.php
 * Unified, Intuitive Hardware Intake & Specification Form Component.
 *
 * Used by:
 *  - new_label.php (Add Mode)
 *  - hardware_view.php (Edit Mode)
 */
require_once __DIR__ . '/hardware_mapping.php';

$item = $item ?? [];
$isEdit = ($formType ?? 'add') === 'edit';
$condition = $item[HW_FIELDS['DESCRIPTION']] ?? 'Untested';
$current_brand = $item[HW_FIELDS['BRAND']] ?? '';
$current_ram = $item[HW_FIELDS['RAM']] ?? '';
$current_storage = $item[HW_FIELDS['STORAGE']] ?? '';
?>

<!-- ONE-TOUCH PRESETS SHELF (Visible on Add mode) -->
<?php if (!$isEdit): ?>
<div class="preset-shelf-container">
    <div class="preset-shelf-header">
        <div class="preset-shelf-title">
            <span>⚡</span>
            <span>One-Touch Quick Presets (Click to autofill)</span>
        </div>
        <span style="font-size: 0.75rem; color: var(--text-muted);">Saves 90% of typing</span>
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
            <span class="preset-card-badge" style="background:rgba(59,130,246,0.15); color:var(--brand-blue);">Refurbished</span>
        </div>

        <div class="preset-pill-card" onclick="applyPreset('budget-student')">
            <div class="preset-card-title">📦 Budget Student</div>
            <div class="preset-card-subtitle">Lenovo · i5-8th · 8GB · 256GB SSD</div>
            <span class="preset-card-badge" style="background:rgba(245,158,11,0.15); color:var(--color-warning);">Untested</span>
        </div>

        <div class="preset-pill-card" onclick="applyPreset('apple-m1')">
            <div class="preset-card-title">🍎 MacBook M1</div>
            <div class="preset-card-subtitle">Apple Air · M1 · 8GB · 256GB SSD</div>
            <span class="preset-card-badge" style="background:rgba(15,23,42,0.1); color:var(--text-main);">Apple</span>
        </div>

        <div class="preset-pill-card" onclick="applyPreset('for-parts')">
            <div class="preset-card-title">🛠️ For Parts</div>
            <div class="preset-card-subtitle">No RAM · No SSD · Salvage Batch</div>
            <span class="preset-card-badge" style="background:rgba(239,68,68,0.15); color:var(--color-danger);">Salvage</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- INTAKE MODE SELECTOR (Simple vs Advanced) -->
<div class="mode-toggle-bar">
    <button type="button" class="mode-tab-btn" id="btnModeSimple" onclick="switchFormMode('simple')">
        <span>✨</span> Simple Intake Mode
    </button>
    <button type="button" class="mode-tab-btn active" id="btnModeAdvanced" onclick="switchFormMode('advanced')">
        <span>🛠️</span> Full Technical Sheet
    </button>
</div>

<!-- SECTION 1: HARDWARE IDENTITY -->
<div class="form-panel-section" id="sectionHardwareIdentity" style="scroll-margin-top: 85px;">
    <h3 class="form-section-header">
        <span class="icon">🔍</span> Hardware Identity &amp; Model
    </h3>

    <!-- Brand Selection Pills -->
    <div class="form-group">
        <label>Manufacturer Brand *</label>
        <div class="brand-pills-row" id="brandPillsContainer">
            <?php
            $popular_brands = ['Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'Asus', 'Acer', 'MSI', 'Other'];
            foreach ($popular_brands as $pb):
                $active = ($current_brand === $pb) ? 'selected' : '';
            ?>
                <button type="button" class="brand-pill <?= $active ?>" data-brand="<?= $pb ?>" onclick="selectBrandPill('<?= $pb ?>')">
                    <?= $pb ?>
                </button>
            <?php endforeach; ?>
        </div>
        <!-- Hidden/Fallback Select to guarantee standard form submission -->
        <select id="<?= HW_FIELDS['BRAND'] ?>" name="<?= HW_FIELDS['BRAND'] ?>" required style="display:none;">
            <option value="" disabled <?= empty($current_brand) ? 'selected' : '' ?>>Choose Brand...</option>
            <?php foreach ($popular_brands as $pb): ?>
                <option value="<?= $pb ?>" <?= ($current_brand === $pb) ? 'selected' : '' ?>><?= $pb ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Model & Series in 2 Columns -->
    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['MODEL'] ?>">Model Line *</label>
            <input type="text" id="<?= HW_FIELDS['MODEL'] ?>" name="<?= HW_FIELDS['MODEL'] ?>" required value="<?= htmlspecialchars($item[HW_FIELDS['MODEL']] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="e.g. Latitude / EliteBook / ThinkPad" list="model-options">
            <datalist id="model-options"></datalist>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['SERIES'] ?>">Model Number / Series</label>
            <input type="text" id="<?= HW_FIELDS['SERIES'] ?>" name="<?= HW_FIELDS['SERIES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIES']] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="e.g. 5400 / 840 G6 / T480" list="series-options">
            <datalist id="series-options"></datalist>
        </div>
    </div>

    <!-- Serial Number / Asset Tag -->
    <div class="form-group">
        <label for="<?= HW_FIELDS['SERIAL_NUMBER'] ?>">Serial Number (S/N) or Asset Tag</label>
        <input type="text" id="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" name="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIAL_NUMBER']] ?? '') ?>" placeholder="Scan barcode or type S/N..." autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" style="font-family: var(--font-mono); font-weight: 700;">
    </div>
</div>

<!-- SECTION 2: PROCESSOR & CORE SPECS -->
<div class="form-panel-section">
    <h3 class="form-section-header">
        <span class="icon">🧠</span> Processor &amp; Memory Specs
    </h3>

    <!-- CPU Generation & Common Presets -->
    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['CPU_GEN'] ?>">CPU Generation / Family</label>
            <input type="text" id="<?= HW_FIELDS['CPU_GEN'] ?>" name="<?= HW_FIELDS['CPU_GEN'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_GEN']] ?? '') ?>" autocomplete="off" placeholder="e.g. 8th Gen / 10th Gen / Apple M1" list="cpu-gen-options">
            <datalist id="cpu-gen-options">
                <option value="8th Gen">
                <option value="10th Gen">
                <option value="11th Gen">
                <option value="12th Gen">
                <option value="13th Gen">
                <option value="6th Gen">
                <option value="7th Gen">
                <option value="Apple M1">
                <option value="Apple M2">
                <option value="Apple M3">
                <option value="AMD Ryzen 5">
                <option value="AMD Ryzen 7">
            </datalist>
        </div>

        <div class="form-group">
            <label for="cpu_specs_main">Exact Processor Model</label>
            <div style="display:flex; gap:6px; align-items:center;">
                <span id="cpu_prefix_display" style="font-weight:800; color:var(--accent-color); font-size:0.95rem; white-space:nowrap;">
                    <?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[0] . '-' : '' ?>
                </span>
                <input type="text" id="cpu_specs_main" value="<?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[1] : ($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>" placeholder="e.g. 8350U / 10610U / M1 8-Core">
            </div>
            <!-- Hidden field submitted to Database -->
            <input type="hidden" id="<?= HW_FIELDS['CPU_SPECS'] ?>" name="<?= HW_FIELDS['CPU_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>">
        </div>
    </div>

    <!-- Cores & Speed (Compact with Dynamic Suffixes) -->
    <div class="form-grid-2">
        <div class="form-group">
            <label for="cpu_cores_display">Core Count</label>
            <div class="input-suffix-wrapper">
                <input type="text" 
                       id="cpu_cores_display" 
                       inputmode="numeric" 
                       placeholder="e.g. 4" 
                       autocomplete="off"
                       value="<?= htmlspecialchars(preg_replace('/\D/', '', $item[HW_FIELDS['CPU_CORES']] ?? '')) ?>">
                <span id="cpu_cores_suffix" class="input-suffix-badge" style="<?= !empty($item[HW_FIELDS['CPU_CORES']]) ? '' : 'display:none;' ?>">
                    <?= ((int)preg_replace('/\D/', '', $item[HW_FIELDS['CPU_CORES']] ?? '') === 1) ? 'Core' : 'Cores' ?>
                </span>
            </div>
            <!-- Hidden field submitted to Database -->
            <input type="hidden" id="<?= HW_FIELDS['CPU_CORES'] ?>" name="<?= HW_FIELDS['CPU_CORES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_CORES']] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="cpu_speed_display">Clock Frequency</label>
            <div class="input-suffix-wrapper">
                <?php
                    $init_spd = '';
                    if (!empty($item[HW_FIELDS['CPU_SPEED']])) {
                        if (preg_match('/[0-9]+(\.[0-9]+)?/', $item[HW_FIELDS['CPU_SPEED']], $sm)) {
                            $init_spd = $sm[0];
                        }
                    }
                ?>
                <input type="text" 
                       id="cpu_speed_display" 
                       inputmode="decimal" 
                       placeholder="e.g. 1.80" 
                       autocomplete="off"
                       value="<?= htmlspecialchars($init_spd) ?>">
                <span id="cpu_speed_suffix" class="input-suffix-badge" style="<?= !empty($init_spd) ? '' : 'display:none;' ?>">GHz</span>
            </div>
            <!-- Hidden field submitted to Database -->
            <input type="hidden" id="<?= HW_FIELDS['CPU_SPEED'] ?>" name="<?= HW_FIELDS['CPU_SPEED'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPEED']] ?? '') ?>">
        </div>
    </div>

    <!-- RAM Capacity with Quick Pills -->
    <div class="form-group">
        <label for="<?= HW_FIELDS['RAM'] ?>">RAM Memory Capacity</label>
        <div class="spec-pills-row" id="ramPillsContainer">
            <?php
            $ram_pills = ['4 GB', '8 GB', '16 GB', '32 GB', '64 GB', 'No RAM'];
            foreach ($ram_pills as $rp):
                $active = ($current_ram === $rp) ? 'selected' : '';
                $isNone = ($rp === 'No RAM') ? 'pill-none' : '';
            ?>
                <button type="button" class="spec-pill <?= $active ?> <?= $isNone ?>" onclick="selectSpecPill('ram', '<?= $rp ?>')"><?= $rp ?></button>
            <?php endforeach; ?>
        </div>
        <input type="text" id="<?= HW_FIELDS['RAM'] ?>" name="<?= HW_FIELDS['RAM'] ?>" value="<?= htmlspecialchars($current_ram) ?>" placeholder="e.g. 16 GB or No RAM">
    </div>

    <!-- Storage Capacity with Quick Pills -->
    <div class="form-group">
        <label for="<?= HW_FIELDS['STORAGE'] ?>">Storage Drive Capacity</label>
        <div class="spec-pills-row" id="storagePillsContainer">
            <?php
            $storage_pills = ['128 GB SSD', '256 GB SSD', '512 GB SSD', '1 TB SSD', '2 TB SSD', 'No SSD', 'No Drive'];
            foreach ($storage_pills as $sp):
                $active = ($current_storage === $sp) ? 'selected' : '';
                $isNone = ($sp === 'No SSD' || $sp === 'No Drive') ? 'pill-none' : '';
            ?>
                <button type="button" class="spec-pill <?= $active ?> <?= $isNone ?>" onclick="selectSpecPill('storage', '<?= $sp ?>')"><?= $sp ?></button>
            <?php endforeach; ?>
        </div>
        <input type="text" id="<?= HW_FIELDS['STORAGE'] ?>" name="<?= HW_FIELDS['STORAGE'] ?>" value="<?= htmlspecialchars($current_storage) ?>" placeholder="e.g. 256 GB SSD or No SSD">
    </div>
</div>

<!-- SECTION 3: WAREHOUSE LOCATION & CONDITION -->
<div class="form-panel-section">
    <h3 class="form-section-header">
        <span class="icon">📍</span> Location, Condition &amp; Status
    </h3>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['DESCRIPTION'] ?>">Standard Condition *</label>
            <select id="<?= HW_FIELDS['DESCRIPTION'] ?>" name="<?= HW_FIELDS['DESCRIPTION'] ?>" required>
                <option value="Untested"    <?= $condition === 'Untested'    ? 'selected' : '' ?>>⏳ Untested (Standard Intake)</option>
                <option value="Refurbished" <?= $condition === 'Refurbished' ? 'selected' : '' ?>>✅ Refurbished (Ready for Sale)</option>
                <option value="For Parts"   <?= $condition === 'For Parts'   ? 'selected' : '' ?>>🛠️ For Parts (Defects Present)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['STATUS'] ?>">Warehouse Status</label>
            <select id="<?= HW_FIELDS['STATUS'] ?>" name="<?= HW_FIELDS['STATUS'] ?>">
                <option value="In Warehouse" <?= (($item[HW_FIELDS['STATUS']] ?? 'In Warehouse') === 'In Warehouse') ? 'selected' : '' ?>>📦 In Stock / Live</option>
                <option value="Tested"       <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Tested'       ? 'selected' : '' ?>>✅ Tested Working</option>
                <option value="Grade A"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade A'      ? 'selected' : '' ?>>🟢 Grade A (Mint)</option>
                <option value="Grade B"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade B'      ? 'selected' : '' ?>>🔵 Grade B (Clean)</option>
                <option value="Grade C"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade C'      ? 'selected' : '' ?>>🟠 Grade C (Heavy Wear)</option>
                <option value="No Post"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Post'      ? 'selected' : '' ?>>🛑 No Post / Logo Only</option>
                <option value="No Power"     <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Power'     ? 'selected' : '' ?>>🔌 No Power / Dead</option>
                <option value="Sold"         <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Sold'         ? 'selected' : '' ?>>🚚 Archive / Sold</option>
            </select>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <div class="flex-between" style="margin-bottom:8px;">
                <label for="<?= HW_FIELDS['LOCATION'] ?>" style="margin-bottom:0;">Shelf / Bin Location</label>
                <?php if (!$isEdit): ?>
                <label style="font-size:0.75rem; color:var(--accent-color); cursor:pointer; font-weight:800; display:flex; align-items:center; gap:4px;">
                    <input type="checkbox" id="pin_location" style="accent-color:var(--accent-color);">
                    <span>📌 Pin Location</span>
                </label>
                <?php endif; ?>
            </div>
            <input type="text" id="<?= HW_FIELDS['LOCATION'] ?>" name="<?= HW_FIELDS['LOCATION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['LOCATION']] ?? '') ?>" placeholder="e.g. A-1-2 / Rack 4">
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['BIOS_STATE'] ?>">BIOS Security Lock</label>
            <select id="<?= HW_FIELDS['BIOS_STATE'] ?>" name="<?= HW_FIELDS['BIOS_STATE'] ?>">
                <option value="Unknown"  <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unknown'  ? 'selected' : '' ?>>Unknown / Pending</option>
                <option value="Unlocked" <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unlocked' ? 'selected' : '' ?>>🟢 Open / Unlocked</option>
                <option value="Locked"   <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Locked'   ? 'selected' : '' ?>>🔒 Password Locked</option>
            </select>
        </div>
    </div>
</div>

<!-- SECTION 4: DEEP TECHNICAL SHEET (Full Technical Sheet Mode) -->
<div id="advancedTechSection" class="form-panel-section" style="display:block;">
    <h3 class="form-section-header" style="color:var(--brand-blue);">
        <span class="icon">🔬</span> Deep Diagnostic Sheet (Full Technical Mode)
    </h3>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['GPU'] ?>">Graphics Processing (GPU)</label>
            <input type="text" id="<?= HW_FIELDS['GPU'] ?>" name="<?= HW_FIELDS['GPU'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['GPU']] ?? '') ?>" placeholder="e.g. Intel UHD / NVIDIA GTX 1650">
        </div>
        <div class="form-group">
            <label for="<?= HW_FIELDS['SCREEN_RES'] ?>">Screen Resolution &amp; Display</label>
            <input type="text" id="<?= HW_FIELDS['SCREEN_RES'] ?>" name="<?= HW_FIELDS['SCREEN_RES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SCREEN_RES']] ?? '') ?>" placeholder="e.g. 1920x1080 FHD Touch">
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['BATTERY'] ?>">Battery Status</label>
            <select id="<?= HW_FIELDS['BATTERY'] ?>" name="<?= HW_FIELDS['BATTERY'] ?>">
                <option value="" <?= !isset($item[HW_FIELDS['BATTERY']]) ? 'selected' : '' ?>>— Pending / Unknown —</option>
                <option value="1" <?= (isset($item[HW_FIELDS['BATTERY']]) && $item[HW_FIELDS['BATTERY']] == 1) ? 'selected' : '' ?>>Included / Good</option>
                <option value="0" <?= (isset($item[HW_FIELDS['BATTERY']]) && $item[HW_FIELDS['BATTERY']] == '0') ? 'selected' : '' ?>>Missing / Dead</option>
            </select>
        </div>
        <div class="form-group">
            <label for="<?= HW_FIELDS['BATTERY_SPECS'] ?>">Battery Health % / Cycle Count</label>
            <input type="text" id="<?= HW_FIELDS['BATTERY_SPECS'] ?>" name="<?= HW_FIELDS['BATTERY_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['BATTERY_SPECS']] ?? '') ?>" placeholder="e.g. 88% Health / 140 Cycles">
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <label for="<?= HW_FIELDS['OS_VERSION'] ?>">Operating System License</label>
            <input type="text" id="<?= HW_FIELDS['OS_VERSION'] ?>" name="<?= HW_FIELDS['OS_VERSION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['OS_VERSION']] ?? '') ?>" placeholder="e.g. Windows 11 Pro / macOS Sonoma">
        </div>
        <div class="form-group">
            <label for="<?= HW_FIELDS['COSMETIC_GRADE'] ?>">Cosmetic Grading</label>
            <select id="<?= HW_FIELDS['COSMETIC_GRADE'] ?>" name="<?= HW_FIELDS['COSMETIC_GRADE'] ?>">
                <option value="">— Unset —</option>
                <option value="A" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'A' ? 'selected' : '' ?>>Grade A (Mint / Like New)</option>
                <option value="B" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'B' ? 'selected' : '' ?>>Grade B (Minor Scratches)</option>
                <option value="C" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'C' ? 'selected' : '' ?>>Grade C (Heavy Wear / Dents)</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="<?= HW_FIELDS['WORK_NOTES'] ?>">Internal Technician Repair Notes (Hidden from label)</label>
        <textarea id="<?= HW_FIELDS['WORK_NOTES'] ?>" name="<?= HW_FIELDS['WORK_NOTES'] ?>" rows="3" placeholder="Document testing, thermal repaste, ports check, and cosmetic notes..."><?= htmlspecialchars($item[HW_FIELDS['WORK_NOTES']] ?? '') ?></textarea>
    </div>
</div>

<style>
.form-panel-section {
    background: var(--bg-panel);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    padding: 22px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-sm);
}

.form-section-header {
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>
