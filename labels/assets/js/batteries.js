/**
 * labels/assets/js/batteries.js
 * Master Controller for Battery Cross-Matching, Search, Inventory & Thermal Printing
 */
'use strict';

let currentBatteries = [];
let currentSearchQuery = '';
let currentSearchMode = 'smart'; // 'smart', 'laptop', 'battery', 'all'
let currentBrandFilter = 'all';
let currentPrintBatteryId = null;

document.addEventListener('DOMContentLoaded', () => {
    initBatterySearch();
    initBrandFilters();
    initModeTabs();
    initBatteryForm();
    initPrintModal();
    initCSVExport();

    // Initial load from server / local catalog
    fetchBatteries();
});

/**
 * 1. FETCH & RENDER BATTERIES
 */
async function fetchBatteries() {
    const grid = document.getElementById('batteryCardsGrid');
    if (grid && currentBatteries.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--text-secondary);">⏳ Loading battery catalog...</div>';
    }

    try {
        const params = new URLSearchParams({
            q: currentSearchQuery,
            mode: currentSearchMode,
            brand: currentBrandFilter
        });

        const res = await fetch(`api/get_batteries.php?${params.toString()}`);
        const json = await res.json();

        if (json.success) {
            currentBatteries = json.data.batteries || [];
            updateKPIs(json.data.count, json.data.total_stock);
            renderBatteries(currentBatteries);
        } else {
            fallbackLocalCatalog();
        }
    } catch (err) {
        console.warn("Server search offline, falling back to client-side catalog", err);
        fallbackLocalCatalog();
    }
}

/**
 * Client-Side Catalog Fallback
 */
function fallbackLocalCatalog() {
    if (typeof BATTERY_CATALOG !== 'undefined') {
        let results = smartBatteryCrossSearch(currentSearchQuery);
        if (currentBrandFilter !== 'all') {
            results = results.filter(b => b.brand.toLowerCase() === currentBrandFilter.toLowerCase());
        }
        currentBatteries = results;
        updateKPIs(results.length, results.reduce((acc, b) => acc + (b.qty_in_stock || 0), 0));
        renderBatteries(results);
    }
}

/**
 * 2. UPDATE KPI COUNTERS
 */
function updateKPIs(count, totalStock) {
    const kpiCount = document.getElementById('kpiBatteryCount');
    const kpiStock = document.getElementById('kpiTotalStock');
    const kpiModels = document.getElementById('kpiModelsSupported');
    const filterCounter = document.getElementById('batteryFilterCounter');

    if (kpiCount) kpiCount.textContent = count;
    if (kpiStock) kpiStock.textContent = totalStock || 0;

    // Count distinct laptop models
    let totalLaptops = 0;
    currentBatteries.forEach(b => {
        const models = (b.compatible_models || '').split(',');
        totalLaptops += models.length;
    });
    if (kpiModels) kpiModels.textContent = totalLaptops;

    if (filterCounter) {
        filterCounter.textContent = `Showing ${count} matching battery profile(s)`;
    }
}

/**
 * 3. RENDER BATTERIES CARDS
 */
function renderBatteries(batteries) {
    const grid = document.getElementById('batteryCardsGrid');
    const banner = document.getElementById('matchHighlightBanner');
    if (!grid) return;

    if (batteries.length === 0) {
        if (banner) banner.style.display = 'none';
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: var(--bg-panel); border-radius: var(--border-radius-md); border: 1px dashed var(--border-color);">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">🔋</div>
                <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 6px;">No Matching Batteries Found</h3>
                <p style="color: var(--text-secondary); max-width: 460px; margin: 0 auto 16px auto; font-size: 0.9rem;">
                    We couldn't find a battery match for "<strong>${escapeHtml(currentSearchQuery)}</strong>". Try searching by partial model number (e.g. <code>5400</code>, <code>840</code>, <code>T480</code>) or battery part number (e.g. <code>WDX0R</code>, <code>CS03XL</code>).
                </p>
                <button type="button" class="btn btn-primary" onclick="openAddBatteryModal()">➕ Add Battery Profile</button>
            </div>
        `;
        return;
    }

    // Show Match banner if searching for a specific model
    if (banner && currentSearchQuery.trim().length >= 2) {
        banner.style.display = 'flex';
        const bannerText = document.getElementById('matchBannerText');
        if (bannerText) {
            bannerText.innerHTML = `<span>🎯 Cross-Match Results for:</span> <strong>"${escapeHtml(currentSearchQuery)}"</strong> (${batteries.length} battery option(s) fit this unit)`;
        }
    } else if (banner) {
        banner.style.display = 'none';
    }

    const qLower = currentSearchQuery.trim().toLowerCase();
    const qTokens = qLower.split(/\s+/).filter(t => t.length > 0);

    let html = '';
    batteries.forEach(bat => {
        const id = bat.id || 0;
        const brand = bat.brand || '';
        const partNumber = bat.part_number || '';
        const aliases = bat.aliases || '';
        const voltage = bat.voltage || '—';
        const wh = bat.capacity_wh || '—';
        const mah = bat.capacity_mah || '';
        const cells = bat.cell_count || '';
        const chemistry = bat.chemistry || 'Li-ion';
        const location = bat.warehouse_location || 'Unassigned';
        const qty = parseInt(bat.qty_in_stock, 10) || 0;
        const condition = bat.condition || 'Tested OEM 80%+';
        const modelsRaw = bat.compatible_models || '';
        const notes = bat.notes || '';

        // Process compatible models into clickable tags
        const modelsArr = modelsRaw.split(',').map(m => m.trim()).filter(m => m.length > 0);
        let modelsTagsHtml = '';
        modelsArr.forEach(m => {
            const mLower = m.toLowerCase();
            const isMatch = qTokens.length > 0 && qTokens.some(t => mLower.includes(t));
            const highlightClass = isMatch ? 'highlight' : '';
            modelsTagsHtml += `<span class="model-tag ${highlightClass}" onclick="searchBySpecificModel('${escapeQuotes(m)}')" title="Click to filter by this laptop">${escapeHtml(m)}</span>`;
        });

        // Determine stock badge color
        const isLow = qty > 0 && qty <= 3;
        const isOut = qty === 0;
        const stockStatusClass = isOut ? 'badge-danger' : (isLow ? 'badge-warning' : 'badge-success');
        const stockStatusText = isOut ? 'Out of Stock' : (isLow ? `${qty} Left (Low)` : `${qty} In Stock`);

        html += `
            <div class="battery-card ${qTokens.length > 0 ? 'match-highlight' : ''}" id="batteryCard_${id}">
                <div>
                    <div class="battery-card-header">
                        <div>
                            <span class="battery-brand-badge">${escapeHtml(brand)}</span>
                            <div class="battery-part-title">${escapeHtml(partNumber)}</div>
                            ${aliases ? `<div class="battery-aliases-line" title="Aliases & DP/N: ${escapeHtml(aliases)}">Alt: ${escapeHtml(aliases)}</div>` : ''}
                        </div>
                        <span class="badge ${stockStatusClass}" id="stockBadge_${id}">${stockStatusText}</span>
                    </div>

                    <div class="battery-specs-pills">
                        ${wh !== '—' ? `<span class="spec-pill pill-wh">⚡ ${escapeHtml(wh)}</span>` : ''}
                        ${voltage !== '—' ? `<span class="spec-pill pill-volts">${escapeHtml(voltage)}</span>` : ''}
                        ${cells ? `<span class="spec-pill">${escapeHtml(cells)}</span>` : ''}
                        <span class="spec-pill">${escapeHtml(chemistry)}</span>
                    </div>

                    <div class="compatible-models-box">
                        <div class="models-box-header">
                            <span>💻 Fits Laptops (${modelsArr.length})</span>
                            <button type="button" class="btn-copy-sm" onclick="copyModelsList('${id}', event)" title="Copy all compatible laptop models">📋 Copy</button>
                        </div>
                        <div class="models-tags-cloud">
                            ${modelsTagsHtml || '<span style="font-size:0.75rem; color:var(--text-muted);">No models cataloged</span>'}
                        </div>
                    </div>

                    ${notes ? `<div style="font-size:0.78rem; color:var(--text-secondary); margin:6px 0; font-style:italic;">ℹ️ ${escapeHtml(notes)}</div>` : ''}
                </div>

                <div>
                    <div class="card-location-stock-row">
                        <div class="bin-badge" title="Warehouse Bin Location">
                            <span>📍</span>
                            <span>${escapeHtml(location)}</span>
                        </div>

                        <div class="stock-stepper-group" title="Quick Adjust Stock">
                            <button type="button" class="stock-step-btn" onclick="adjustBatteryStock(${id}, -1)">−</button>
                            <span class="stock-step-qty" id="stockQtyDisplay_${id}">${qty}</span>
                            <button type="button" class="stock-step-btn" onclick="adjustBatteryStock(${id}, 1)">+</button>
                        </div>
                    </div>

                    <div class="battery-card-actions">
                        <button type="button" class="btn btn-sm btn-success" onclick="openBatteryPrintModal(${id})" title="Print 2x1 Thermal Label">
                            <span>🖨️ Label</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="openEditBatteryModal(${id})" title="Edit Battery Specifications">
                            <span>✏️ Edit</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger-soft" onclick="deleteBatteryRecord(${id})" title="Delete Battery Record">
                            <span>🗑️</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}

/**
 * 4. SEARCH & DEBOUNCE
 */
function initBatterySearch() {
    const input = document.getElementById('batterySearchInput');
    const clearBtn = document.getElementById('batterySearchClear');
    let debounceTimer = null;

    if (input) {
        input.addEventListener('input', (e) => {
            const val = e.target.value;
            currentSearchQuery = val;

            if (clearBtn) {
                clearBtn.style.display = val.length > 0 ? 'block' : 'none';
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchBatteries();
            }, 180);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (input) {
                input.value = '';
                input.focus();
            }
            currentSearchQuery = '';
            clearBtn.style.display = 'none';
            fetchBatteries();
        });
    }
}

/**
 * Search specifically by a clicked laptop model tag
 */
window.searchBySpecificModel = function(modelName) {
    const input = document.getElementById('batterySearchInput');
    if (input) {
        input.value = modelName;
        currentSearchQuery = modelName;
        const clearBtn = document.getElementById('batterySearchClear');
        if (clearBtn) clearBtn.style.display = 'block';
        fetchBatteries();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

/**
 * 5. BRAND FILTERS
 */
function initBrandFilters() {
    const pills = document.querySelectorAll('.brand-filter-pill');
    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            pills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentBrandFilter = pill.dataset.brand || 'all';
            fetchBatteries();
        });
    });
}

/**
 * 6. SEARCH MODE SWITCHER TABS
 */
function initModeTabs() {
    const tabs = document.querySelectorAll('.search-mode-tab');
    const input = document.getElementById('batterySearchInput');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentSearchMode = tab.dataset.mode || 'smart';

            if (input) {
                if (currentSearchMode === 'laptop') {
                    input.placeholder = "Type Laptop Model (e.g. 'Latitude 5400', 'ThinkPad T480', 'EliteBook 840 G5')...";
                } else if (currentSearchMode === 'battery') {
                    input.placeholder = "Type Battery Part # or DP/N (e.g. 'WDX0R', 'CS03XL', '01AV421', '6GTPY')...";
                } else {
                    input.placeholder = "🔍 Type laptop model or battery part number for instant cross-matching...";
                }
                input.focus();
            }

            fetchBatteries();
        });
    });
}

/**
 * 7. QUICK STOCK ADJUSTMENT (+ / -)
 */
window.adjustBatteryStock = async function(id, delta) {
    const qtyDisplay = document.getElementById(`stockQtyDisplay_${id}`);
    const badge = document.getElementById(`stockBadge_${id}`);
    let currentVal = parseInt(qtyDisplay ? qtyDisplay.textContent : '0', 10) || 0;
    let nextVal = Math.max(0, currentVal + delta);

    // Optimistic UI update
    if (qtyDisplay) qtyDisplay.textContent = nextVal;
    if (badge) {
        if (nextVal === 0) {
            badge.className = 'badge badge-danger';
            badge.textContent = 'Out of Stock';
        } else if (nextVal <= 3) {
            badge.className = 'badge badge-warning';
            badge.textContent = `${nextVal} Left (Low)`;
        } else {
            badge.className = 'badge badge-success';
            badge.textContent = `${nextVal} In Stock`;
        }
    }

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('delta', delta);
        fd.append('csrf_token', getCsrfToken());

        const res = await fetch('api/update_battery_stock.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            if (qtyDisplay) qtyDisplay.textContent = json.data.new_qty;
            Toast.success(`Stock updated to ${json.data.new_qty}`);
        } else {
            // Revert
            if (qtyDisplay) qtyDisplay.textContent = currentVal;
            Toast.error(json.error || 'Failed to update stock');
        }
    } catch (err) {
        if (qtyDisplay) qtyDisplay.textContent = currentVal;
        Toast.error('Network error communicating with warehouse server.');
    }
};

/**
 * 8. COPY COMPATIBLE MODELS LIST
 */
window.copyModelsList = function(id, event) {
    if (event) event.stopPropagation();
    const battery = currentBatteries.find(b => b.id == id);
    if (!battery || !battery.compatible_models) {
        Toast.warning("No compatible models found for this battery.");
        return;
    }

    const textToCopy = `${battery.brand} ${battery.part_number} (${battery.capacity_wh})\nFits Models:\n${battery.compatible_models}`;
    navigator.clipboard.writeText(textToCopy).then(() => {
        Toast.success(`Copied compatibility list for ${battery.part_number}!`);
    }).catch(() => {
        Toast.info(battery.compatible_models);
    });
};

/**
 * 9. BATTERY MODAL: ADD / EDIT
 */
window.openAddBatteryModal = function() {
    const modal = document.getElementById('batteryModal');
    const form = document.getElementById('batteryForm');
    const title = document.getElementById('batteryModalTitle');
    const idInput = document.getElementById('batInputId');

    if (form) form.reset();
    if (idInput) idInput.value = '';
    if (title) title.textContent = '➕ Add Battery Cross-Match Profile';
    if (modal) modal.style.display = 'flex';
};

window.openEditBatteryModal = function(id) {
    const battery = currentBatteries.find(b => b.id == id);
    if (!battery) return;

    const modal = document.getElementById('batteryModal');
    const title = document.getElementById('batteryModalTitle');

    document.getElementById('batInputId').value = battery.id;
    document.getElementById('batInputBrand').value = battery.brand || 'Dell';
    document.getElementById('batInputPart').value = battery.part_number || '';
    document.getElementById('batInputName').value = battery.model_name || '';
    document.getElementById('batInputAliases').value = battery.aliases || '';
    document.getElementById('batInputVoltage').value = battery.voltage || '';
    document.getElementById('batInputWh').value = battery.capacity_wh || '';
    document.getElementById('batInputMah').value = battery.capacity_mah || '';
    document.getElementById('batInputCells').value = battery.cell_count || '';
    document.getElementById('batInputChemistry').value = battery.chemistry || 'Li-ion';
    document.getElementById('batInputModels').value = battery.compatible_models || '';
    document.getElementById('batInputLocation').value = battery.warehouse_location || 'Bin BAT-01';
    document.getElementById('batInputQty').value = battery.qty_in_stock || 0;
    document.getElementById('batInputCondition').value = battery.condition || 'Tested OEM 80%+';
    document.getElementById('batInputNotes').value = battery.notes || '';

    if (title) title.textContent = `✏️ Edit Battery: ${battery.brand} ${battery.part_number}`;
    if (modal) modal.style.display = 'flex';
};

window.closeBatteryModal = function() {
    const modal = document.getElementById('batteryModal');
    if (modal) modal.style.display = 'none';
};

function initBatteryForm() {
    const form = document.getElementById('batteryForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const id = document.getElementById('batInputId').value;
        const isEdit = id && parseInt(id, 10) > 0;
        const endpoint = isEdit ? 'api/edit_battery.php' : 'api/add_battery.php';

        const fd = new FormData(form);
        fd.append('csrf_token', getCsrfToken());

        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span>⏳ Saving...</span>';
        submitBtn.disabled = true;

        try {
            const res = await fetch(endpoint, { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                Toast.success(json.data ? json.data.message : 'Battery profile saved successfully!');
                closeBatteryModal();
                fetchBatteries();
            } else {
                Toast.error(json.error || 'Failed to save battery profile');
            }
        } catch (err) {
            Toast.error('Network error saving battery profile.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}

/**
 * 10. DELETE BATTERY RECORD
 */
window.deleteBatteryRecord = async function(id) {
    const battery = currentBatteries.find(b => b.id == id);
    const label = battery ? `${battery.brand} ${battery.part_number}` : `#${id}`;

    if (!confirm(`Are you sure you want to delete battery record ${label}?`)) {
        return;
    }

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('csrf_token', getCsrfToken());

        const res = await fetch('api/delete_battery.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            Toast.success(`Deleted battery ${label}`);
            fetchBatteries();
        } else {
            Toast.error(json.error || 'Failed to delete battery');
        }
    } catch (err) {
        Toast.error('Network error communicating with server.');
    }
};

/**
 * 11. THERMAL PRINT MODAL
 */
window.openBatteryPrintModal = function(id) {
    currentPrintBatteryId = id;
    const battery = currentBatteries.find(b => b.id == id);
    const modal = document.getElementById('batteryPrintModal');
    const title = document.getElementById('batteryPrintTitle');

    if (title && battery) {
        title.textContent = `🖨️ Print Label: ${battery.brand} ${battery.part_number}`;
    }
    const qtyInput = document.getElementById('batPrintQty');
    if (qtyInput) qtyInput.value = 1;

    if (modal) modal.style.display = 'flex';
};

window.closeBatteryPrintModal = function() {
    const modal = document.getElementById('batteryPrintModal');
    if (modal) modal.style.display = 'none';
};

function initPrintModal() {
    const directBtn = document.getElementById('btnBatDirectPrint');
    const odtBtn = document.getElementById('btnBatOdtPrint');
    const qtyInput = document.getElementById('batPrintQty');

    if (directBtn) {
        directBtn.addEventListener('click', () => {
            if (!currentPrintBatteryId) return;
            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            const printUrl = `print_battery_label.php?id=${currentPrintBatteryId}&qty=${qty}&autoprint=1`;
            closeBatteryPrintModal();
            window.open(printUrl, `PrintBat_${currentPrintBatteryId}`, 'width=650,height=600,menubar=no,toolbar=no,location=no,status=no');
        });
    }

    if (odtBtn) {
        odtBtn.addEventListener('click', async () => {
            if (!currentPrintBatteryId) return;
            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            const originalHtml = odtBtn.innerHTML;
            odtBtn.innerHTML = '<span>⏳ Generating ODT...</span>';
            odtBtn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('id', currentPrintBatteryId);
                fd.append('qty', qty);

                const res = await fetch('api/reprint_battery_label.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    const link = document.createElement('a');
                    link.href = json.data.file_path;
                    link.download = json.data.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    Toast.success(`Generated: ${json.data.file_name}`);
                    closeBatteryPrintModal();
                } else {
                    Toast.error("ODT Error: " + (json.error || 'Failed'));
                }
            } catch (err) {
                Toast.error("Network error generating ODT.");
            } finally {
                odtBtn.innerHTML = originalHtml;
                odtBtn.disabled = false;
            }
        });
    }
}

/**
 * 12. CSV EXPORT WITH UTF-8 BOM
 */
function initCSVExport() {
    const btn = document.getElementById('btnExportBatteryCSV');
    if (!btn) return;

    btn.addEventListener('click', () => {
        if (currentBatteries.length === 0) {
            Toast.warning("No battery records to export.");
            return;
        }

        const headers = ["ID", "Brand", "Part Number", "Model Name", "Aliases", "Voltage", "Capacity Wh", "Capacity mAh", "Cell Count", "Chemistry", "Warehouse Location", "Stock Qty", "Condition", "Compatible Laptop Models", "Notes"];
        const rows = [headers];

        currentBatteries.forEach(b => {
            rows.push([
                b.id || '',
                b.brand || '',
                b.part_number || '',
                b.model_name || '',
                b.aliases || '',
                b.voltage || '',
                b.capacity_wh || '',
                b.capacity_mah || '',
                b.cell_count || '',
                b.chemistry || '',
                b.warehouse_location || '',
                b.qty_in_stock || 0,
                b.condition || '',
                `"${(b.compatible_models || '').replace(/"/g, '""')}"`,
                `"${(b.notes || '').replace(/"/g, '""')}"`
            ]);
        });

        const csvContent = "\uFEFF" + rows.map(e => e.join(",")).join("\n");
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `IQA_Battery_Catalog_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        Toast.success("Exported Battery Catalog to CSV (Excel UTF-8 BOM)");
    });
}

/**
 * Utilities
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeQuotes(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'");
}
