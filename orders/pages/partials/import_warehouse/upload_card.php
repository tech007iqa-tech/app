<?php
/**
 * Warehouse Import Upload Card & Guidelines Partial
 * Initial upload form with drag-and-drop CSV file selector and data migration criteria guide.
 */
?>
<div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px;">
    <!-- COPY & PASTE SMART IMPORTER (Primary) -->
    <div style="background: white; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
        <!-- Mode Switcher Tabs -->
        <div style="display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px;">
            <button type="button" id="tab-mode-paste" onclick="switchUploadMode('paste')" style="padding: 10px 20px; font-weight: 800; font-size: 0.95rem; border: none; border-radius: 12px; background: white; color: var(--accent-color); cursor: pointer; box-shadow: var(--shadow-sm); border: 1px solid #cbd5e1; outline: none;">
                📋 Paste Spreadsheet (Sheets / Excel)
            </button>
            <button type="button" id="tab-mode-file" onclick="switchUploadMode('file')" style="padding: 10px 20px; font-weight: 700; font-size: 0.95rem; border: 1px solid transparent; border-radius: 12px; background: #f1f5f9; color: #64748b; cursor: pointer; outline: none;">
                📁 Upload CSV File
            </button>
        </div>

        <!-- TAB 1: PASTE SYSTEM -->
        <div id="mode-paste-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <label for="clipboard-paste-area" style="display: block; font-weight: 900; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;">
                        1. Copy & Paste Spreadsheet Rows
                    </label>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem;">
                        Copy columns <code>Brand | Model | Series | CPU / Gen | Description | Price | QTY</code> and paste below.
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="pasteFromSystemClipboard()" class="btn-main" style="padding: 6px 12px; font-size: 0.8rem; background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; box-shadow: none; border-radius: 8px; font-weight: 800; cursor: pointer;">
                        📋 Paste
                    </button>
                    <button type="button" onclick="loadSampleGamingData()" class="btn-main" style="padding: 6px 12px; font-size: 0.8rem; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; box-shadow: none; border-radius: 8px; font-weight: 800; cursor: pointer;" title="Loads sample Gaming consoles from spreadsheet">
                        🎮 Sample Data
                    </button>
                    <button type="button" onclick="clearClipboardArea()" class="btn-main" style="padding: 6px 10px; font-size: 0.8rem; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; box-shadow: none; border-radius: 8px; font-weight: 700; cursor: pointer;">
                        🧹
                    </button>
                </div>
            </div>

            <!-- Target Sector & Location selectors -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; background: #f8fafc; padding: 15px; border-radius: 14px; border: 1px solid #e2e8f0;">
                <div>
                    <label for="clipboard-sector" style="display: block; font-size: 0.8rem; font-weight: 800; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Target Sector</label>
                    <?php
                    $sector_icons = [
                        'Gaming' => '🎮',
                        'Laptops' => '💻',
                        'Desktops' => '🖥️',
                        'Electronics' => '📱',
                        'Master' => '📦'
                    ];
                    ?>
                    <select id="clipboard-sector" style="width: 100%; height: 40px; padding: 0 12px; border-radius: 10px; border: 1px solid #cbd5e1; font-weight: 800; background: white; font-size: 0.9rem; outline: none;">
                        <?php foreach ($available_sectors as $sec): ?>
                            <?php $icon = $sector_icons[$sec] ?? '🏷️'; ?>
                            <option value="<?= htmlspecialchars($sec) ?>" <?= (strcasecmp($sec, $active_param_sector) === 0) ? 'selected' : '' ?>>
                                <?= $icon ?> <?= htmlspecialchars($sec) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="clipboard-location-select" style="display: block; font-size: 0.8rem; font-weight: 800; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Default Shelf / Location</label>
                    <select id="clipboard-location-select" onchange="onClipboardLocationSelectChange(this)" style="width: 100%; height: 40px; padding: 0 10px; border-radius: 10px; border: 1px solid #cbd5e1; font-weight: 800; background: white; font-size: 0.9rem; outline: none;">
                        <option value="Inbound" data-zone="Inbound" <?= ($active_param_loc === 'Inbound' || empty($active_param_loc)) ? 'selected' : '' ?>>
                            📥 Inbound (Dock / Receiving)
                        </option>
                        <?php
                        $rendered_locs = ['INBOUND'];
                        $is_param_loc_found = false;
                        ?>
                        <?php foreach ($working_zones as $wz): ?>
                            <?php if (!empty($zone_locations_map[$wz])): ?>
                                <optgroup label="📍 <?= htmlspecialchars($wz) ?>">
                                    <?php foreach ($zone_locations_map[$wz] as $lcode): ?>
                                        <?php
                                        $rendered_locs[] = strtoupper($lcode);
                                        $is_selected = (strcasecmp($lcode, $active_param_loc) === 0);
                                        if ($is_selected) $is_param_loc_found = true;
                                        ?>
                                        <option value="<?= htmlspecialchars($lcode) ?>" data-zone="<?= htmlspecialchars($wz) ?>" <?= $is_selected ? 'selected' : '' ?>>
                                            📍 <?= htmlspecialchars($lcode) ?> (<?= htmlspecialchars($wz) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (!empty($active_param_loc) && !$is_param_loc_found): ?>
                            <optgroup label="🎯 Selected Target">
                                <option value="<?= htmlspecialchars($active_param_loc) ?>" data-zone="<?= htmlspecialchars($active_param_zone ?: 'General') ?>" selected>
                                    📍 <?= htmlspecialchars($active_param_loc) ?> (<?= htmlspecialchars($active_param_zone ?: 'General') ?>)
                                </option>
                            </optgroup>
                        <?php endif; ?>

                        <option value="__NEW_LOC__">➕ Custom / New Shelf Code...</option>
                    </select>

                    <input type="text" id="clipboard-location-custom" oninput="onClipboardCustomLocationInput(this)" placeholder="Type new shelf code (e.g. N4)..." style="display: none; width: 100%; height: 38px; margin-top: 8px; padding: 0 12px; border-radius: 8px; border: 1px solid #94a3b8; font-weight: 800; font-size: 0.9rem; text-transform: uppercase; outline: none; background: #fff;">
                    <input type="hidden" id="clipboard-location" value="<?= htmlspecialchars($active_param_loc ?: 'Inbound') ?>">

                    <div id="clipboard-location-zone-badge" style="margin-top: 6px; font-size: 0.78rem; font-weight: 800; color: #475569; display: flex; align-items: center; justify-content: space-between;">
                        <span>Assigned Zone:</span>
                        <span id="clipboard-zone-text" style="background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 6px; color: #0284c7;">
                            <?= htmlspecialchars($active_param_zone ?: ($active_param_loc === 'Inbound' || empty($active_param_loc) ? 'Inbound' : 'General')) ?>
                        </span>
                    </div>
                </div>
            </div>


            <!-- Paste Textarea -->
            <textarea id="clipboard-paste-area" oninput="onClipboardInput()" placeholder="Brand	Model	Series	CPU / Gen	Description	Price	QTY&#10;Mix	Console	Console	Per Pound	Untested	$7/lb	29&#10;Nintendo switch	Lite	HDH-001		Untested/Not Working	$30	1&#10;PS4	CUH-7215B				$70	1" style="width: 100%; height: 190px; border-radius: 14px; border: 2px dashed #cbd5e1; padding: 15px; font-family: monospace; font-size: 0.85rem; resize: vertical; margin-bottom: 15px; outline: none; transition: all 0.2s ease; background: #fafafa; line-height: 1.5;" onfocus="this.style.borderColor='var(--accent-color)'; this.style.background='#fff';" onblur="this.style.borderColor='#cbd5e1'; if(!this.value) this.style.background='#fafafa';"></textarea>

            <input type="hidden" name="csrf_token" value="<?= Security::getToken() ?>">

            <!-- Live Preview Card (shown when data is pasted) -->
            <div id="clipboard-preview-card" style="display: none; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <div id="clipboard-pills-info" style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;"></div>
                    <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Instant Live Preview</span>
                </div>

                <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; background: white;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; text-align: left;">
                        <thead style="background: #f1f5f9; position: sticky; top: 0; z-index: 5;">
                            <tr>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569;">Brand</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569;">Model</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569;">Series</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569;">CPU/Gen</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569;">Description</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569; text-align: right;">Price</th>
                                <th style="padding: 8px 10px; font-weight: 800; color: #475569; text-align: center;">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="clipboard-preview-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button type="button" id="btn-clipboard-import" onclick="submitWarehouseClipboardImport()" disabled class="btn-main" style="width: 100%; height: 56px; font-size: 1.1rem; border-radius: 14px; font-weight: 900; background: var(--accent-color); color: white; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    🚀 Instant Import (0 Items)
                </button>

                <form action="index.php?view=import_warehouse" method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="preview_clipboard">
                    <input type="hidden" name="clipboard_text" id="clipboard-form-text">
                    <input type="hidden" name="sector" id="clipboard-form-sector" value="Gaming">
                    <input type="hidden" name="location" id="clipboard-form-location" value="Inbound">
                    <button type="submit" class="btn-main" onclick="document.getElementById('clipboard-form-text').value = document.getElementById('clipboard-paste-area').value; document.getElementById('clipboard-form-sector').value = document.getElementById('clipboard-sector').value; document.getElementById('clipboard-form-location').value = document.getElementById('clipboard-location').value;" style="width: 100%; height: 42px; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; font-size: 0.88rem; border-radius: 12px; font-weight: 700;">
                        🔍 Open in Full Verification Report
                    </button>
                </form>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <a href="../sampleWHdata/intake.pdf" target="_blank" style="color: #475569; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.2s ease;">
                    🖨️ Need a blank intake sheet? <span style="text-decoration: underline; color: #16a34a;">Print Intake PDF</span>
                </a>
            </div>
        </div>

        <!-- TAB 2: TRADITIONAL CSV FILE UPLOAD (Fallback) -->
        <div id="mode-file-content" style="display: none;">
            <form action="index.php?view=import_warehouse" method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 25px;">
                    <label for="csv-input" style="display: block; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 12px;">Select CSV Manifest</label>
                    <div id="drop-zone" style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 50px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;">
                        <input type="file" name="inventory_csv" id="csv-input" accept=".csv" style="display: none;">
                        <div style="font-size: 3.5rem; margin-bottom: 12px;">📂</div>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b; margin-bottom: 6px;">Click to Upload CSV</div>
                        <p id="file-name" style="color: #64748b; font-size: 0.9rem;">File must contain columns: Date, QTY, Item, Serial, location, notes</p>
                    </div>
                </div>

                <button type="submit" class="btn-main" style="width: 100%; height: 54px; font-size: 1.05rem; border-radius: 14px;">
                    🔍 Validate CSV & Preview Import
                </button>
            </form>
        </div>
    </div>

    <!-- GUIDE -->
    <div style="background: #f8fafc; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0;">
        <h3 style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.4rem;">📘</span> CSV Data Migration Criteria
        </h3>
        <p style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px;">
            Upload inventory documents containing details about devices. The system will parse the properties out of the fields automatically.
        </p>

        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
            <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #bbf7d0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <code style="font-weight: 800; color: #15803d; font-size: 0.85rem;">Brand | Model | Series | CPU/Gen | Desc | Price | QTY</code>
                <span style="font-size: 0.75rem; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 6px; font-weight: 800;">Fast Sheets</span>
            </div>
            <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <code style="font-weight: 800; color: var(--accent-dark);">Date | QTY | Item | Serial | Loc</code>
                <span style="font-size: 0.85rem; color: #94a3b8;">Standard CSV Format</span>
            </div>
        </div>

        <div style="padding: 20px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px; margin-bottom: 16px;">
            <h4 style="font-weight: 900; color: #92400e; margin-bottom: 5px; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                <span>💡</span> Auto-Creating Locations
            </h4>
            <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5; margin: 0;">
                If a location listed in the CSV (e.g. <strong>N4</strong>) doesn't exist, the system will automatically create it and map it to its corresponding working zone (e.g. <strong>Zone N</strong>).
            </p>
        </div>

        <!-- PRINTABLE INTAKE FORM LINK -->
        <div style="padding: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
                <h4 style="font-weight: 900; color: #166534; margin: 0; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                    <span>📋</span> Physical Intake Sheet
                </h4>
                <a href="../sampleWHdata/intake.pdf" target="_blank" class="btn-main" style="padding: 6px 14px; font-size: 0.85rem; background: #16a34a; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);">
                    🖨️ Print Form
                </a>
            </div>
            <p style="color: #15803d; font-size: 0.85rem; line-height: 1.5; margin: 0 0 10px 0;">
                Need paper sheets for warehouse floor staff? Print official blank Daily Intake Sheets to log hardware specs and shelf locations.
            </p>
            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="../sampleWHdata/intake.pdf" target="_blank" style="font-size: 0.8rem; font-weight: 800; color: #166534; text-decoration: underline;">
                    Open Printable PDF ↗
                </a>
                <span style="color: #86efac;">•</span>
                <a href="../sampleWHdata/intake.pdf" download="Warehouse_Intake_Form.pdf" style="font-size: 0.8rem; font-weight: 800; color: #166534; text-decoration: underline;">
                    Download PDF 📥
                </a>
            </div>
        </div>
    </div>
</div>
