<!-- STEP 2: TRADE PRESETS -->
<div class="step-content" id="step-2">
    <h2 style="font-size: 1.25rem; margin-bottom: 6px;">💻 Used Computer &amp; Electronics Trade Presets</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Tailor diagnostic
        checklists, inventory types, and grading standards to your refurbishing workflow.</p>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label>Primary Hardware Inventory Lines</label>
        <div class="preset-grid">
            <?php
            $lines = ['Laptops & Notebooks', 'Desktop PCs', 'Gaming PCs', 'Enterprise Servers', 'Monitors & Displays', 'RAM & Storage (SSDs)', 'Smartphones & Tablets', 'E-Waste / Scrap'];
            foreach ($lines as $line):
                ?>
                <label class="preset-checkbox">
                    <input type="checkbox" name="hardware_lines[]" value="<?= htmlspecialchars($line) ?>" checked>
                    <span class="preset-label"><?= htmlspecialchars($line) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 1.5rem;">
        <label>Refurbishing Grading Categories</label>
        <div class="preset-grid">
            <?php
            $grades = ['A-Grade (Like New)', 'B-Grade (Minor Scuffs)', 'C-Grade (Heavy Wear)', 'Untested (As-Is)', 'Parts / Repair Only', 'E-Waste / Scrap'];
            foreach ($grades as $grade):
                ?>
                <label class="preset-checkbox">
                    <input type="checkbox" name="grading_standards[]" value="<?= htmlspecialchars($grade) ?>" checked>
                    <span class="preset-label"><?= htmlspecialchars($grade) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label for="label_preset">Hardware Label &amp; Barcode Standard</label>
            <select id="label_preset" name="label_preset">
                <option value="4x6_thermal">4" x 6" Thermal Shipping / Manifest (Zebra / Rollo)</option>
                <option value="2x1_asset">2" x 1" Small Hardware Asset Tag / Barcode</option>
                <option value="compact_qr">Compact 2.25" x 1.25" QR Code &amp; Specs</option>
            </select>
        </div>

        <div class="form-group">
            <label for="db_security_mode">Storage &amp; Database Architecture</label>
            <input type="text" id="db_security_mode" value="Isolated Outside HTTP Root (data/db)"
                disabled style="opacity: 0.75; cursor: not-allowed;">
            <span class="input-hint" style="color: #10b981;">✓ Secure path automatically active.</span>
        </div>
    </div>
</div>
