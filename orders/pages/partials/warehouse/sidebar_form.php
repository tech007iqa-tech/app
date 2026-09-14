<?php
/**
 * Warehouse Sidebar Form Partial
 * Handles manual single-item stock entry, sector-specific dynamic inputs, clone last submission, and session tracking.
 */
?>
<!-- Add Item Sidebar (Hidden in Global View) -->
<?php if ($selected_loc !== 'GLOBAL'): ?>
    <aside class="warehouse-sidebar">
        <div
            style="background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: sticky; top: 20px;">

            <?php
            $parent_zone_link = "index.php?view=warehouse&sector=" . urlencode($selected_sector);
            if ($selected_loc) {
                $stmt_wz_link = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
                $stmt_wz_link->execute([$selected_loc]);
                $wz_name_val = $stmt_wz_link->fetchColumn();
                if ($wz_name_val) {
                    $parent_zone_link .= "&zone=" . urlencode($wz_name_val);
                }
            }
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a href="<?= htmlspecialchars($parent_zone_link) ?>" title="Back to current zone" style="text-decoration: none; font-size: 1.1rem; vertical-align: middle;">🔙</a>
                    <h3 id="wh-form-title" style="font-weight: 800; margin: 0; display: inline-block; vertical-align: middle;">📥 Register Stock</h3>
                </div>
                <div id="session-counter"
                    style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 16px; border-radius: 14px; font-size: 0.75rem; font-weight: 700; color: #15803d; display: none; line-height: 1.4; min-width: 180px;">
                    <div>✨ <span id="session-count-val" style="font-weight: 900;">0</span> Added this session</div>
                    <div id="session-last-item-info"
                        style="font-size: 0.68rem; color: #166534; margin-top: 4px; border-top: 1px dashed #bbf7d0; padding-top: 4px; font-weight: 600; display: none;">
                        Last: <strong id="session-last-model-series"></strong> (Qty: <span
                            id="session-last-qty"></span>) @ <span id="session-last-time"></span>
                    </div>
                </div>
            </div>
            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <button type="button" id="btn-clone-last" onclick="fillLastEnteredData()"
                    style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; cursor: pointer; color: #475569; transition: all 0.2s;">
                    📋 Clone Last
                </button>
            </div>
            <form method="POST" action="" id="wh-main-form">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" id="wh-form-action" value="add_inventory">
                <input type="hidden" name="item_id" id="wh-edit-id" value="">
                <input type="hidden" name="last_updated_at" id="wh-last-updated" value="">
                <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="wh-location-code">Location Code (Zone/Shelf)</label>
                    <input type="text" id="wh-location-code" name="location_code"
                        value="<?= htmlspecialchars($selected_loc) ?>" readonly
                        style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; background:#f8fafc; color:#64748b; font-weight:700;">
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                        <label for="wh-brand">Brand</label>
                        <input type="text" name="brand" list="brand-options" id="wh-brand" placeholder="Dell"
                            required
                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                        <datalist id="brand-options"></datalist>
                    </div>
                    <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                        <label for="wh-model">Model</label>
                        <input type="text" name="model" list="model-options" id="wh-model" placeholder="Latitude"
                            required
                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                        <datalist id="model-options"></datalist>
                    </div>
                    <?php if ($selected_sector === 'Laptops'): ?>
                        <div class="form-group" style="flex: 1 1 130px; min-width: 130px;">
                            <label for="wh-spec-series">Series</label>
                            <input type="text" id="wh-spec-series" name="series" required placeholder="E7450"
                                style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sector Specific Fields -->
                <div id="sector-specific-fields"
                    style="border-top: 1px dashed #eee; padding-top: 15px; margin-bottom: 15px;">
                    <?php if ($selected_sector === 'Laptops'): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group"
                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                <label for="wh-spec-cpu">CPU</label>
                                <select id="wh-spec-cpu" name="cpu" required
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                    <option value="N/A" style="background-color: #f1f5f9; color: #64748b;">-</option>
                                    <option value="i3" style="background-color: #e0f2fe; color: #0369a1;">i3</option>
                                    <option value="i5" style="background-color: #e0f2fe; color: #0369a1;">i5</option>
                                    <option value="i7" style="background-color: #e0f2fe; color: #0369a1;">i7</option>
                                    <option value="i9" style="background-color: #e0f2fe; color: #0369a1;">i9</option>
                                    <option value="Ryzen 3" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 3
                                    </option>
                                    <option value="Ryzen 5" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 5
                                    </option>
                                    <option value="Ryzen 7" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 7
                                    </option>
                                    <option value="Ryzen 9" style="background-color: #fee2e2; color: #b91c1c;">Ryzen 9
                                    </option>
                                </select>
                            </div>
                            <div class="form-group"
                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                <label for="wh-spec-gen">Generation</label>
                                <input type="text" id="wh-spec-gen" name="gen" required list="gen-options"
                                    placeholder="11th Gen"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                <datalist id="gen-options">
                                    <option value="-">
                                    <option value="4th & 5th">
                                    <option value="6th & 7th">
                                    <option value="8th">
                                    <option value="9th">
                                    <option value="10th">
                                    <option value="11th">
                                    <option value="12th">
                                    <option value="13th">
                                    <option value="14th">
                                    <option value="Core 2 Duo">
                                    <option value="2nd">
                                    <option value="3rd">
                                    <option value="AMD">
                                </datalist>
                            </div>
                            <div class="form-group"
                                style="flex: 1 1 90px; min-width: 90px; display: flex; flex-direction: column;">
                                <label for="wh-spec-gpu">GPU</label>
                                <input type="text" id="wh-spec-gpu" name="gpu" placeholder="Integrated / RTX"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            </div>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group"
                                style="flex: 1 1 80px; min-width: 80px; display: flex; flex-direction: column;">
                                <label for="wh-spec-windows">OS</label>
                                <input type="text" id="wh-spec-windows" name="windows" list="os-options"
                                    placeholder="Win 11 Pro"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                <datalist id="os-options">
                                    <option value="Win11 Pro">
                                    <option value="Windows 10 Pro">
                                    <option value="Windows 11 Home"></option>
                                </datalist>
                            </div>
                            <div class="form-group" id="wh-bios-state-group"
                                style="flex: 1 1 80px; min-width: 80px; display: none; flex-direction: column;">
                                <label for="wh-spec-bios">Bios</label>
                                <select id="wh-spec-bios" name="bios"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                    <option value="—">-</option>
                                    <option value="Unlocked">Unlocked</option>
                                    <option value="Locked">Locked</option>
                                </select>
                            </div>
                            <div class="form-group"
                                style="flex: 1 1 80px; min-width: 80px; display: flex; flex-direction: column;">
                                <label for="wh-spec-battery">Battery</label>
                                <select id="wh-spec-battery" name="battery"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                    <option value=""></option>
                                    <option value="Yes">Yes</option>
                                    <option value="Unknown">Unknown</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group"
                                style="flex: 1 1 120px; min-width: 120px; display: flex; flex-direction: column;">
                                <label for="wh-spec-ram">RAM</label>
                                <input type="text" id="wh-spec-ram" name="ram" placeholder="16GB"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            </div>
                            <div class="form-group"
                                style="flex: 1 1 120px; min-width: 120px; display: flex; flex-direction: column;">
                                <label for="wh-spec-storage">Storage</label>
                                <input type="text" id="wh-spec-storage" name="storage" placeholder="512GB NVMe"
                                    style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            </div>
                        </div>
                    <?php elseif ($selected_sector === 'Gaming'): ?>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="wh-gaming-cat">Category</label>
                            <select name="gaming_category" id="wh-gaming-cat" onchange="toggleGamingFields()"
                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                                <option value="PC">PC / Custom Build</option>
                                <option value="Consoles">Consoles</option>
                                <option value="Controllers">Controllers</option>
                                <option value="Games">Games</option>
                            </select>
                        </div>

                        <!-- PC Specific -->
                        <div id="wh-gaming-pc-fields">
                            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                                <div class="form-group" style="flex: 1 1 200px;">
                                    <label for="wh-gaming-pc-cpu">CPU</label>
                                    <input type="text" id="wh-gaming-pc-cpu" name="cpu" placeholder="Ryzen 7"
                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1 1 200px;">
                                    <label for="wh-gaming-pc-gpu">GPU</label>
                                    <input type="text" id="wh-gaming-pc-gpu" name="gpu" placeholder="RTX 3070"
                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                        </div>

                        <!-- Specific Specs for everything else -->
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="wh-series" id="wh-gaming-spec-label">Specs / Series</label>
                            <input type="text" name="series" list="series-options" id="wh-series"
                                placeholder="Series / Edition"
                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            <datalist id="series-options"></datalist>
                            <div id="wh-gaming-extra-specs"
                                style="display: flex; flex-wrap: wrap; gap: 10px; margin-top:5px;">
                                <div class="form-group" style="flex: 1 1 200px;">
                                    <input type="text" name="ram" id="wh-ram" placeholder="RAM / Color"
                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                                <div class="form-group" style="flex: 1 1 200px;">
                                    <input type="text" name="storage" id="wh-storage" placeholder="Storage"
                                        style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                                </div>
                            </div>
                        </div>
                    <?php elseif ($selected_sector === 'Electronics'): ?>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="wh-elec-type">Device Type</label>
                            <input type="text" id="wh-elec-type" name="type" placeholder="Charger / Hub"
                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label for="wh-elec-spec">Specs / Condition</label>
                            <input type="text" id="wh-elec-spec" name="voltage" placeholder="65W / 19.5V"
                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px;">
                            <input type="text" name="condition" placeholder="New"
                                style="width:100%; height:38px; border-radius:8px; border:1px solid #ddd; padding: 0 10px; margin-top:5px;">
                        </div>
                    <?php elseif ($selected_sector === 'Desktops'): ?>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="wh-spec-cpu-gen">CPU / Gen Brand</label>
                            <input type="text" id="wh-spec-cpu-gen" name="cpu_gen" list="cpu-gen-options"
                                placeholder="i7 10th Gen / Intel"
                                style="width:100%; height:40px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 600;">
                            <datalist id="cpu-gen-options">
                                <option value="2nd-3rd Gen">
                                <option value="4th-5th Gen">
                                <option value="6th-7th Gen">
                                <option value="i5-8th Gen">
                                <option value="i7-8th Gen">
                                <option value="i5-9th Gen">
                                <option value="i7-9th Gen">
                                <option value="i5-10th Gen">
                                <option value="i7-10th Gen">
                                <option value="i5-11th Gen">
                                <option value="i7-11th Gen">
                                <option value="i5-12th Gen">
                                <option value="i7-12th Gen">
                                <option value="i5-13th Gen">
                                <option value="i7-13th Gen">
                            </datalist>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                        <label for="wh-condition">Condition</label>
                        <select id="wh-condition" name="condition" onchange="toggleBiosState()"
                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight:700;">
                            <option value="A Grade" style="background-color: #dcfce740; color: #0b3f1eff;">A Grade
                            </option>
                            <option value="B Grade" style="background-color: #e0f2fe40; color: #014468ff;">B Grade
                            </option>
                            <option value="C Grade" style="background-color: #faf5ff40; color: #531888ff;">C Grade
                            </option>
                            <option value="No Power" style="background-color: #fee2e240; color: #741212ff;">No Power
                            </option>
                            <option value="No Post" style="background-color: #fff7ed40; color: #9c3d18ff;">No Post
                            </option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                        <label for="wh-price">Price</label>
                        <div style="position:relative; display:flex; align-items:center;">
                            <span style="position:absolute; left:12px; font-weight:800; color:#64748b;">$</span>
                            <input type="number" step="1" id="wh-price" name="price" value=".97" placeholder="150"
                                min="0" required
                                style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px 0 25px; font-weight: 800;">
                        </div>
                    </div>
                    <div class="form-group" style="flex: 1 1 90px; min-width: 90px;">
                        <label for="wh-quantity">QTY</label>
                        <input type="number" id="wh-quantity" name="quantity" value="1" min="1" required
                            style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px; font-weight: 800;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="wh-notes">Notes / Observations</label>
                    <input type="text" id="wh-notes" name="notes" placeholder="Any scratches or specifics..."
                        style="width:100%; height:42px; border-radius:10px; border:1px solid #ddd; padding: 0 12px;">
                </div>

                <button type="submit" id="wh-submit-btn"
                    style="width:100%; height:50px; background:var(--text-main); color:white; border:none; border-radius:14px; font-weight:800; cursor:pointer;">
                    📥 Add to Stock
                </button>
                <button type="button" id="wh-cancel-edit" onclick="resetWarehouseForm()"
                    style="display:none; width:100%; margin-top:10px; background:none; border:none; color:#64748b; font-weight:700; cursor:pointer;">Cancel
                    Edit</button>
            </form>
        </div>
    </aside>
<?php else: ?>
    <aside class="warehouse-sidebar"
        style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:20px; padding:40px; text-align:center; color:#64748b;">
        <div style="font-size:2rem; margin-bottom:15px;">🚫</div>
        <h3 style="font-weight:800;">Registration Locked</h3>
        <p>You are in <b>Global View</b>. To add or edit specific stock, please select a specific <b>Working Zone</b> from the gate.</p>
        <a href="index.php?view=warehouse&sector=<?= urlencode($selected_sector) ?>"
            style="display:inline-block; margin-top:20px; color:var(--text-main); font-weight:800;">Back to Gate</a>
    </aside>
<?php endif; ?>
