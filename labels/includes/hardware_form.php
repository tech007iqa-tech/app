<?php
/**
 * includes/hardware_form.php
 * A unified, reusable hardware specification form.
 *
 * Used by:
 *  - new_label.php (Add mode)
 *  - refurbished_view.php (Edit mode)
 *
 * Variables expected:
 *  - $item: (Array|null) Existing item data.
 *  - $formType: (String) 'add' or 'edit'.
 */
require_once __DIR__ . '/hardware_mapping.php';

$item = $item ?? [];
$isEdit = ($formType ?? 'add') === 'edit';
$condition = $item[HW_FIELDS['DESCRIPTION']] ?? 'Untested';
?>

<div class="form-grid">
    <!-- SECTION 1: Hardware Identity -->
    <div class="form-panel-section">
        <h3 class="form-section-header">
            <span class="icon">🔍</span> Identity & Generation
        </h3>

        <div class="form-group">
            <label for="<?= HW_FIELDS['BRAND'] ?>">Manufacturer *</label>
            <select id="<?= HW_FIELDS['BRAND'] ?>" name="<?= HW_FIELDS['BRAND'] ?>" required class="select-modern">
                <option value="" disabled <?= empty($item[HW_FIELDS['BRAND']]) ? 'selected' : '' ?>>Choose Brand...</option>
                <?php
                $brands = ['HP', 'Dell', 'Lenovo', 'Apple', 'Asus', 'Acer', 'Microsoft', 'MSI', 'Other'];
                foreach ($brands as $b):
                    $selected = ($item[HW_FIELDS['BRAND']] ?? '') === $b ? 'selected' : '';
                ?>
                    <option value="<?= $b ?>" <?= $selected ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group highlight-on-focus">
            <label for="<?= HW_FIELDS['MODEL'] ?>">Main Model (e.g., EliteBook) *</label>
            <input type="text" id="<?= HW_FIELDS['MODEL'] ?>" name="<?= HW_FIELDS['MODEL'] ?>" required value="<?= htmlspecialchars($item[HW_FIELDS['MODEL']] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="EliteBook / Latitude / ThinkPad" list="model-options">
            <datalist id="model-options"></datalist>
        </div>

        <div class="form-group highlight-on-focus">
            <label for="<?= HW_FIELDS['SERIES'] ?>">Series (e.g., 840 G3)</label>
            <input type="text" id="<?= HW_FIELDS['SERIES'] ?>" name="<?= HW_FIELDS['SERIES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIES']] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="G3 / G4 / G5 / E6400" list="series-options">
            <datalist id="series-options"></datalist>
        </div>

        <div class="form-group highlight-on-focus">
            <label for="<?= HW_FIELDS['SERIAL_NUMBER'] ?>">Serial Number / Asset Tag</label>
            <input type="text" id="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" name="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIAL_NUMBER']] ?? '') ?>" placeholder="S/N: (Last 6 or Full)" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false">
        </div>

        <div class="form-divider"></div>

        <div class="form-group" style="position: relative;">
            <label for="<?= HW_FIELDS['CPU_GEN'] ?>">CPU Family / Generation</label>
            <input type="text" id="<?= HW_FIELDS['CPU_GEN'] ?>" name="<?= HW_FIELDS['CPU_GEN'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_GEN']] ?? '') ?>" autocomplete="off" placeholder="8th Gen / 10th Gen">
            <div id="cpuSearchWrapper" class="search-suggestions" style="display:none; position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;">
                <!-- JS will inject options here -->
            </div>
        </div>

        <div class="form-group">
            <label for="cpu_specs_main">Technical Specs / Processor</label>
            <div class="input-group-prefix" id="cpu_specs_group">
                <span id="cpu_prefix_display"><?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[0] . '-' : '' ?></span>
                <input type="text" id="cpu_specs_main" value="<?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[1] : ($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>" placeholder="i5-8350U / Ryzen 5">
            </div>
            <!-- Hidden field for actual database submission -->
            <input type="hidden" id="<?= HW_FIELDS['CPU_SPECS'] ?>" name="<?= HW_FIELDS['CPU_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>">
        </div>

        <div class="form-row-mobile">
            <div class="form-group">
                <label for="<?= HW_FIELDS['CPU_CORES'] ?>">Core Count</label>
                <input type="text" id="<?= HW_FIELDS['CPU_CORES'] ?>" name="<?= HW_FIELDS['CPU_CORES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_CORES']] ?? '') ?>" inputmode="numeric" placeholder="e.g. 4 Cores">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['CPU_SPEED'] ?>">Clock Speed</label>
                <input type="text" id="<?= HW_FIELDS['CPU_SPEED'] ?>" name="<?= HW_FIELDS['CPU_SPEED'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPEED']] ?? '') ?>" inputmode="decimal" placeholder="e.g. 2.40 GHz">
            </div>
        </div>
    </div>

    <!-- SECTION 2: Internals & Inventory -->
    <div class="form-panel-section">
        <h3 class="form-section-header">
            <span class="icon">⚙️</span> Internals & Logistics
        </h3>

        <div class="form-row-mobile">
            <div class="form-group">
                <label for="<?= HW_FIELDS['RAM'] ?>">RAM Capacity</label>
                <input type="text" id="<?= HW_FIELDS['RAM'] ?>" name="<?= HW_FIELDS['RAM'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['RAM']] ?? '') ?>" inputmode="numeric" placeholder="e.g. 16 GB">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['STORAGE'] ?>">Storage Capacity</label>
                <input type="text" id="<?= HW_FIELDS['STORAGE'] ?>" name="<?= HW_FIELDS['STORAGE'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['STORAGE']] ?? '') ?>" inputmode="numeric" placeholder="e.g. 512 GB SSD">
            </div>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['DESCRIPTION'] ?>">Standard Condition *</label>
            <select id="<?= HW_FIELDS['DESCRIPTION'] ?>" name="<?= HW_FIELDS['DESCRIPTION'] ?>" required class="select-modern">
                <option value="Untested"    <?= $condition === 'Untested'    ? 'selected' : '' ?>>Untested (Standard Intake)</option>
                <option value="Refurbished" <?= $condition === 'Refurbished' ? 'selected' : '' ?>>Refurbished (Full Specs Ready)</option>
                <option value="For Parts"   <?= $condition === 'For Parts'   ? 'selected' : '' ?>>For Parts (Defects Present)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['STATUS'] ?>">Inventory Status / Grading</label>
            <select id="<?= HW_FIELDS['STATUS'] ?>" name="<?= HW_FIELDS['STATUS'] ?>" class="select-modern">
                <option value="In Warehouse" <?= (($item[HW_FIELDS['STATUS']] ?? 'In Warehouse') === 'In Warehouse') ? 'selected' : '' ?>>📦 In Stock / Live</option>
                <option value="Grade A"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade A'      ? 'selected' : '' ?>>🟢 Grade A (Mint)</option>
                <option value="Grade B"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade B'      ? 'selected' : '' ?>>🔵 Grade B (Clean)</option>
                <option value="Grade C"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade C'      ? 'selected' : '' ?>>🟠 Grade C (Significant Wear)</option>
                <option value="Tested"       <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Tested'       ? 'selected' : '' ?>>✅ Tested working</option>
                <option value="No Post"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Post'      ? 'selected' : '' ?>>🛑 No Post / Logo Only</option>
                <option value="No Power"     <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Power'     ? 'selected' : '' ?>>🔌 No Power / Dead</option>
                <option value="Sold"         <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Sold'         ? 'selected' : '' ?>>🚚 Archive / Sold</option>
            </select>
        </div>

        <div class="form-divider"></div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['BIOS_STATE'] ?>">BIOS Security</label>
            <select id="<?= HW_FIELDS['BIOS_STATE'] ?>" name="<?= HW_FIELDS['BIOS_STATE'] ?>" class="select-modern">
                <option value="Unknown"  <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unknown'  ? 'selected' : '' ?>>Unknown / Unset</option>
                <option value="Unlocked" <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unlocked' ? 'selected' : '' ?>>Open / Unlocked</option>
                <option value="Locked"   <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Locked'   ? 'selected' : '' ?>>Password Locked</option>
            </select>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                <label for="<?= HW_FIELDS['LOCATION'] ?>" style="margin-bottom:0;">Warehouse Location Tag</label>
                <?php if (!$isEdit): ?>
                <label for="pin_location" class="pin-toggle" title="Keep this location after saving">
                    <input type="checkbox" id="pin_location"> <span>📌 Pin Position</span>
                </label>
                <?php endif; ?>
            </div>
            <input type="text" id="<?= HW_FIELDS['LOCATION'] ?>" name="<?= HW_FIELDS['LOCATION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['LOCATION']] ?? '') ?>" placeholder="e.g. A-1-1">
        </div>
    </div>

    <!-- SECTION 3: Deep Technical Specs (Conditionally Hidden via CSS/JS) -->
    <div id="technicalSpecsSection" class="form-panel-section" style="grid-column: 1 / -1; margin-top: 20px; border-top: 1px dashed var(--border-color); padding-top: 20px; <?= $condition !== 'Refurbished' ? 'display:none;' : '' ?>">
        <h3 class="form-section-header" style="color: var(--accent-color);">
            <span class="icon">🛠️</span> Deep Technical Sheet
        </h3>

        <div class="form-row-mobile" style="margin-bottom: 12px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['GPU'] ?>">🎮 GPU (Graphics Card)</label>
                <input type="text" id="<?= HW_FIELDS['GPU'] ?>" name="<?= HW_FIELDS['GPU'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['GPU']] ?? '') ?>" placeholder="Intergraded / Dedicated">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['SCREEN_RES'] ?>">🖥️ Screen Resolution</label>
                <input type="text" id="<?= HW_FIELDS['SCREEN_RES'] ?>" name="<?= HW_FIELDS['SCREEN_RES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SCREEN_RES']] ?? '') ?>" placeholder="1920 x 1080 / 4K">
            </div>
        </div>

        <div class="form-row-mobile" style="margin-bottom: 12px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['BATTERY'] ?>">🔋 Battery Status</label>
                <select id="<?= HW_FIELDS['BATTERY'] ?>" name="<?= HW_FIELDS['BATTERY'] ?>" class="select-modern">
                    <option value="" <?= !isset($item[HW_FIELDS['BATTERY']]) ? 'selected' : '' ?>>— Unknown / Pending —</option>
                    <option value="1" <?= (isset($item[HW_FIELDS['BATTERY']]) && $item[HW_FIELDS['BATTERY']] == 1) ? 'selected' : '' ?>>Included / Good</option>
                    <option value="0" <?= (isset($item[HW_FIELDS['BATTERY']]) && $item[HW_FIELDS['BATTERY']] == '0') ? 'selected' : '' ?>>Missing / Dead</option>
                </select>
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['BATTERY_SPECS'] ?>">🔋 Health / Cycles</label>
                <input type="text" id="<?= HW_FIELDS['BATTERY_SPECS'] ?>" name="<?= HW_FIELDS['BATTERY_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['BATTERY_SPECS']] ?? '') ?>" placeholder="e.g. 85% Health / 120 Cycles">
            </div>
        </div>

        <div class="form-row-mobile" style="margin-bottom: 12px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['WEBCAM'] ?>">📷 Webcam Specs</label>
                <input type="text" id="<?= HW_FIELDS['WEBCAM'] ?>" name="<?= HW_FIELDS['WEBCAM'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['WEBCAM']] ?? '') ?>" placeholder="720p / 1080p / IR">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['BACKLIT_KB'] ?>">⌨️ Backlit Keyboard?</label>
                <select name="<?= HW_FIELDS['BACKLIT_KB'] ?>" id="<?= HW_FIELDS['BACKLIT_KB'] ?>" class="select-modern">
                    <option value="">— Unset —</option>
                    <option value="Yes" <?= ($item[HW_FIELDS['BACKLIT_KB']] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes, Illuminated</option>
                    <option value="No"  <?= ($item[HW_FIELDS['BACKLIT_KB']] ?? '') === 'No'  ? 'selected' : '' ?>>No Backlight</option>
                </select>
            </div>
        </div>

        <div class="form-row-mobile" style="margin-bottom: 12px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['OS_VERSION'] ?>">💿 OS Platform</label>
                <input type="text" id="<?= HW_FIELDS['OS_VERSION'] ?>" name="<?= HW_FIELDS['OS_VERSION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['OS_VERSION']] ?? '') ?>" placeholder="Win 11 Pro / macOS Sonoma">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['COSMETIC_GRADE'] ?>">✨ Cosmetic Grading</label>
                <select name="<?= HW_FIELDS['COSMETIC_GRADE'] ?>" id="<?= HW_FIELDS['COSMETIC_GRADE'] ?>" class="select-modern">
                    <option value="">— Unset —</option>
                    <option value="A" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'A' ? 'selected' : '' ?>>🌟 Grade A (Mint/Like New)</option>
                    <option value="B" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'B' ? 'selected' : '' ?>>✅ Grade B (Minor Scratches)</option>
                    <option value="C" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'C' ? 'selected' : '' ?>>⚠️ Grade C (Heavy Wear/Dents)</option>
                </select>
            </div>
        </div>

        <div class="form-divider"></div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['WORK_NOTES'] ?>">📝 Tech Work Notes (Hidden from label)</label>
            <textarea id="<?= HW_FIELDS['WORK_NOTES'] ?>" name="<?= HW_FIELDS['WORK_NOTES'] ?>" rows="4" style="width: 100%; font-family: inherit; border-radius: 8px; border: 1px solid var(--border-color); padding: 12px;" placeholder="Document repairs, thermal paste application, cleaning, and testing results..."><?= htmlspecialchars($item[HW_FIELDS['WORK_NOTES']] ?? '') ?></textarea>
        </div>
    </div>
</div>

<style>
.form-panel-section {
    background: #fff;
    border-radius: 12px;
    padding: 0;
    margin-bottom: 25px;
}
.form-section-header {
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 12px;
    margin-bottom: 20px;
    font-size: 1.15rem;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
}
.form-section-header .icon {
    font-size: 1.3rem;
}

.form-divider {
    height: 1px;
    background: var(--border-color);
    margin: 20px 0;
}

.select-modern {
    background-color: #f8fafc !important;
    border: 1px solid var(--border-color) !important;
    font-weight: 600;
    cursor: pointer;
}

.select-modern:focus {
    background-color: #fff !important;
    border-color: var(--accent-color) !important;
}

.highlight-on-focus input:focus {
    background-color: rgba(140, 198, 63, 0.03);
}

.search-suggestions {
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

/* Mobile-First Layout for form rows */
.form-row-mobile {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Stack fields on top of each other on mobile for better fit */
@media (max-width: 600px) {
    .form-row-mobile {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .form-section-header {
        margin-top: 10px;
        font-size: 1rem;
    }
}
</style>

