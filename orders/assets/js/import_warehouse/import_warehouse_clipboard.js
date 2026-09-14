/**
 * Warehouse Import Clipboard Smart Importer Module
 * Fast copy-and-paste parser for Google Sheets and Excel manifests (e.g. Brand, Model, Series, CPU/Gen, Description, Price, QTY).
 */

function parseWarehouseClipboard(text) {
    if (!text || !text.trim()) {
        return { items: [], mapping: { hasHeader: false, delimiterName: 'Tab', detectedSector: null } };
    }

    const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
    if (lines.length === 0) {
        return { items: [], mapping: { hasHeader: false, delimiterName: 'Tab', detectedSector: null } };
    }

    // Auto-detect delimiter
    let tabCount = 0;
    let commaCount = 0;
    let semiCount = 0;
    const testLimit = Math.min(lines.length, 5);
    for (let i = 0; i < testLimit; i++) {
        tabCount += (lines[i].match(/\t/g) || []).length;
        commaCount += (lines[i].match(/,/g) || []).length;
        semiCount += (lines[i].match(/;/g) || []).length;
    }

    let delimiter = '\t';
    if (commaCount > tabCount && commaCount > semiCount) delimiter = ',';
    else if (semiCount > tabCount && semiCount > commaCount) delimiter = ';';

    const splitLine = (line, delim) => {
        if (delim === '\t' || delim === ';') {
            return line.split(delim).map(v => {
                let s = v.trim();
                if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
                return s;
            });
        }
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delim && !inQuotes) {
                result.push(cur.trim());
                cur = '';
            } else {
                cur += char;
            }
        }
        result.push(cur.trim());
        return result.map(s => {
            if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
            return s;
        });
    };

    const parsedRows = lines.map(line => splitLine(line, delimiter));

    let brandIdx = -1;
    let modelIdx = -1;
    let seriesIdx = -1;
    let cpuIdx = -1;
    let descIdx = -1;
    let priceIdx = -1;
    let qtyIdx = -1;
    let typeIdx = -1;
    let locIdx = -1;
    let notesIdx = -1;
    let hasHeader = false;
    let detectedSector = null;

    if (parsedRows.length > 0) {
        const firstRow = parsedRows[0];
        firstRow.forEach((col, idx) => {
            const colLower = col.toLowerCase().trim();
            if (colLower === 'brand' || colLower.includes('brand') || colLower === 'make') {
                brandIdx = idx;
                hasHeader = true;
            } else if (colLower === 'model' || colLower.includes('model')) {
                modelIdx = idx;
                hasHeader = true;
            } else if (colLower === 'series' || colLower.includes('series')) {
                seriesIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('cpu') || colLower.includes('gen') || colLower.includes('processor')) {
                cpuIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('desc') || colLower.includes('description') || colLower.includes('spec')) {
                descIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('price') || colLower.includes('cost') || colLower.includes('value') || colLower.includes('rate')) {
                priceIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('qty') || colLower.includes('quantity') || colLower.includes('count') || colLower.includes('units')) {
                qtyIdx = idx;
                hasHeader = true;
            } else if (colLower === 'type' || colLower === 'sector' || colLower === 'category') {
                typeIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('loc') || colLower.includes('location') || colLower.includes('shelf')) {
                locIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('note')) {
                notesIdx = idx;
                hasHeader = true;
            }
        });
    }

    const dataRows = hasHeader ? parsedRows.slice(1) : parsedRows;

    // Positional fallback if no recognized header
    if (!hasHeader && parsedRows.length > 0) {
        const colCount = parsedRows[0].length;
        if (colCount >= 8) {
            // Type | Brand | Model | Series | CPU/Gen | Description | Price | QTY
            typeIdx = 0; brandIdx = 1; modelIdx = 2; seriesIdx = 3; cpuIdx = 4; descIdx = 5; priceIdx = 6; qtyIdx = 7;
            if (colCount >= 9) notesIdx = 8;
        } else if (colCount === 7) {
            // Brand | Model | Series | CPU/Gen | Description | Price | QTY
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; cpuIdx = 3; descIdx = 4; priceIdx = 5; qtyIdx = 6;
        } else if (colCount === 6) {
            // Brand | Model | Series | Description | Price | QTY
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; descIdx = 3; priceIdx = 4; qtyIdx = 5;
        } else if (colCount === 5) {
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; priceIdx = 3; qtyIdx = 4;
        } else if (colCount === 4) {
            brandIdx = 0; modelIdx = 1; priceIdx = 2; qtyIdx = 3;
        } else if (colCount === 3) {
            brandIdx = 0; modelIdx = 1; qtyIdx = 2;
        }
    }

    const items = [];
    dataRows.forEach(cols => {
        if (cols.length < 2) return;

        let brand = brandIdx !== -1 ? (cols[brandIdx] || '').trim() : '';
        let model = modelIdx !== -1 ? (cols[modelIdx] || '').trim() : '';
        const series = seriesIdx !== -1 ? (cols[seriesIdx] || '').trim() : '';
        const cpu = cpuIdx !== -1 ? (cols[cpuIdx] || '').trim() : '';
        const description = descIdx !== -1 ? (cols[descIdx] || '').trim() : '';
        const location = locIdx !== -1 ? (cols[locIdx] || '').trim() : '';
        const notes = notesIdx !== -1 ? (cols[notesIdx] || '').trim() : '';

        // Auto-detect sector if Type column is present
        if (typeIdx !== -1 && cols[typeIdx]) {
            const rawType = cols[typeIdx].trim();
            if (rawType && !detectedSector) {
                const lower = rawType.toLowerCase();
                if (lower.includes('game') || lower.includes('gaming')) detectedSector = 'Gaming';
                else if (lower.includes('laptop')) detectedSector = 'Laptops';
                else if (lower.includes('desk')) detectedSector = 'Desktops';
                else if (lower.includes('elec')) detectedSector = 'Electronics';
            }
        }

        // Price parsing ($7/lb, $30, $410.00)
        let price = 0;
        if (priceIdx !== -1 && cols[priceIdx]) {
            const match = cols[priceIdx].toString().match(/([0-9]+(?:\.[0-9]{1,2})?)/);
            if (match) price = parseFloat(match[1]);
        }

        // Qty parsing
        let qty = 1;
        if (qtyIdx !== -1 && cols[qtyIdx]) {
            const parsedQty = parseInt(cols[qtyIdx].toString().replace(/[^0-9]/g, ''), 10);
            if (!isNaN(parsedQty) && parsedQty > 0) qty = parsedQty;
        }

        if (!brand && !model) return;

        // Gracefully handle model if blank
        if (!model) {
            model = series ? series : 'Console';
        }
        if (!brand) {
            brand = 'Generic';
        }

        items.push({
            brand,
            model,
            series,
            cpu,
            description,
            price,
            quantity: qty,
            location,
            notes
        });
    });

    return {
        items,
        mapping: {
            brand: brandIdx,
            model: modelIdx,
            series: seriesIdx,
            cpu: cpuIdx,
            description: descIdx,
            price: priceIdx,
            qty: qtyIdx,
            hasHeader,
            detectedSector,
            delimiterName: delimiter === '\t' ? 'Tab (Excel/Sheets)' : delimiter === ',' ? 'CSV (Comma)' : 'Semicolon'
        }
    };
}

let parsedClipboardItems = [];

function onClipboardInput() {
    const textarea = document.getElementById('clipboard-paste-area');
    const previewContainer = document.getElementById('clipboard-preview-card');
    const tableBody = document.getElementById('clipboard-preview-tbody');
    const countEl = document.getElementById('clipboard-item-count');
    const pillsContainer = document.getElementById('clipboard-pills-info');
    const btnSubmit = document.getElementById('btn-clipboard-import');
    const hiddenJson = document.getElementById('clipboard-json-payload');

    if (!textarea) return;
    const text = textarea.value;

    if (!text.trim()) {
        if (previewContainer) previewContainer.style.display = 'none';
        if (btnSubmit) btnSubmit.disabled = true;
        parsedClipboardItems = [];
        return;
    }

    const { items, mapping } = parseWarehouseClipboard(text);
    parsedClipboardItems = items;

    if (hiddenJson) {
        hiddenJson.value = JSON.stringify(items);
    }

    if (items.length === 0) {
        if (previewContainer) previewContainer.style.display = 'none';
        if (btnSubmit) btnSubmit.disabled = true;
        return;
    }

    if (previewContainer) previewContainer.style.display = 'block';
    if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = `🚀 Instant Import (${items.length} Items)`;
    }

    if (countEl) countEl.textContent = items.length;

    // Auto-select Sector if detected
    if (mapping.detectedSector) {
        const sectorSelect = document.getElementById('clipboard-sector');
        if (sectorSelect) sectorSelect.value = mapping.detectedSector;
    }

    // Render diagnostic pills
    if (pillsContainer) {
        pillsContainer.innerHTML = `
            <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; color: #475569;">📌 ${escapeHTML(mapping.delimiterName)}</span>
            ${mapping.hasHeader ? '<span style="background: #dcfce7; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; color: #16a34a;">✅ Header Detected</span>' : '<span style="background: #fef9c3; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; color: #854d0e;">⚡ 7 Columns Auto-Mapped</span>'}
            <span style="background: #e0f2fe; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; color: #0284c7;">📊 ${items.length} items parsed</span>
        `;
    }

    // Render Preview Rows
    if (tableBody) {
        tableBody.innerHTML = items.slice(0, 25).map((item, i) => `
            <tr style="background: ${i % 2 === 0 ? '#fff' : '#f8fafc'}; border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 10px; font-weight: 800; color: #1e293b;">${escapeHTML(item.brand)}</td>
                <td style="padding: 8px 10px; font-weight: 700; color: #334155;">${escapeHTML(item.model)}</td>
                <td style="padding: 8px 10px; color: #64748b;">${escapeHTML(item.series || '—')}</td>
                <td style="padding: 8px 10px; color: #64748b;">${escapeHTML(item.cpu || '—')}</td>
                <td style="padding: 8px 10px; color: #475569; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHTML(item.description || '—')}</td>
                <td style="padding: 8px 10px; text-align: right; font-weight: 800; color: #059669;">${item.price > 0 ? '$' + item.price.toFixed(2) : '—'}</td>
                <td style="padding: 8px 10px; text-align: center; font-weight: 900; color: #0f172a;">${item.quantity}</td>
            </tr>
        `).join('');

        if (items.length > 25) {
            tableBody.innerHTML += `
                <tr>
                    <td colspan="7" style="padding: 10px; text-align: center; font-weight: 800; color: #94a3b8; background: #f8fafc;">
                        … and ${items.length - 25} more items will be imported
                    </td>
                </tr>
            `;
        }
    }
}

function onClipboardLocationSelectChange(selectEl) {
    const customInput = document.getElementById('clipboard-location-custom');
    const hiddenLoc = document.getElementById('clipboard-location');
    const zoneText = document.getElementById('clipboard-zone-text');

    if (!selectEl) return;
    const selectedVal = selectEl.value;

    if (selectedVal === '__NEW_LOC__') {
        if (customInput) {
            customInput.style.display = 'block';
            customInput.focus();
            if (hiddenLoc) hiddenLoc.value = customInput.value.trim().toUpperCase();
        }
        if (zoneText) zoneText.textContent = 'Auto-assigned on creation';
    } else {
        if (customInput) {
            customInput.style.display = 'none';
            customInput.value = '';
        }
        if (hiddenLoc) hiddenLoc.value = selectedVal.toUpperCase();

        const opt = selectEl.options[selectEl.selectedIndex];
        const zone = opt ? opt.getAttribute('data-zone') : '';
        if (zoneText) zoneText.textContent = zone || 'General';
    }
}

function onClipboardCustomLocationInput(inputEl) {
    const hiddenLoc = document.getElementById('clipboard-location');
    const zoneText = document.getElementById('clipboard-zone-text');
    if (!inputEl) return;

    const val = inputEl.value.trim().toUpperCase();
    if (hiddenLoc) hiddenLoc.value = val;

    if (zoneText) {
        if (!val) {
            zoneText.textContent = 'Auto-assigned on creation';
        } else {
            const match = val.match(/^([A-Z]+)/);
            if (match) {
                zoneText.textContent = 'Zone ' + match[1];
            } else {
                zoneText.textContent = 'General';
            }
        }
    }
}

async function submitWarehouseClipboardImport() {
    if (!parsedClipboardItems || parsedClipboardItems.length === 0) {
        alert("Please paste rows from your spreadsheet first.");
        return;
    }

    const btn = document.getElementById('btn-clipboard-import');
    const sectorSelect = document.getElementById('clipboard-sector');
    const locSelect = document.getElementById('clipboard-location-select');
    const locCustom = document.getElementById('clipboard-location-custom');
    const locHidden = document.getElementById('clipboard-location');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    const sector = sectorSelect ? sectorSelect.value : 'Gaming';

    let location = 'Inbound';
    if (locSelect && locSelect.value === '__NEW_LOC__') {
        location = locCustom ? locCustom.value.trim().toUpperCase() : 'Inbound';
    } else if (locHidden && locHidden.value.trim()) {
        location = locHidden.value.trim().toUpperCase();
    } else if (locSelect && locSelect.value) {
        location = locSelect.value.trim().toUpperCase();
    }
    if (!location) location = 'Inbound';

    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Importing to Database...';
    btn.disabled = true;

    try {
        const response = await fetch('api/bulk_import_inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                sector: sector,
                location: location,
                items: parsedClipboardItems
            })
        });

        const result = await response.json();
        if (result.success) {
            btn.innerHTML = `✅ Successfully Imported ${result.count} Items!`;
            btn.style.background = '#16a34a';

            const finalSector = result.sector || sector;
            const finalLoc = result.location || location;

            setTimeout(() => {
                let redirectUrl = `index.php?view=warehouse&sector=${encodeURIComponent(finalSector)}`;
                if (finalLoc && finalLoc !== 'GLOBAL') {
                    redirectUrl += `&loc=${encodeURIComponent(finalLoc)}`;
                }
                window.location.href = redirectUrl;
            }, 1200);
        } else {
            alert("Import failed: " + (result.error || "Unknown error"));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert("Network error during bulk import.");
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}


async function pasteFromSystemClipboard() {
    const textarea = document.getElementById('clipboard-paste-area');
    if (!textarea) return;

    try {
        const text = await navigator.clipboard.readText();
        if (text) {
            textarea.value = text;
            onClipboardInput();
            textarea.focus();
        } else {
            alert("Clipboard is empty or does not contain text.");
        }
    } catch (err) {
        textarea.focus();
        textarea.placeholder = "Press Ctrl+V to paste your spreadsheet columns...";
    }
}

function clearClipboardArea() {
    const textarea = document.getElementById('clipboard-paste-area');
    if (textarea) {
        textarea.value = '';
        onClipboardInput();
    }
}

function loadSampleGamingData() {
    const sample = `Brand\tModel\tSeries\tCPU / Gen\tDescription\tPrice\tQTY
Mix\tConsole\tConsole\tPer Pound\tUntested\t$7/lb\t29
Nintendo switch\tLite\tHDH-001\t\tUntested/Not Working\t$30\t1
Nintendo switch Deck\t\t\t\t\t$10\t1
Nintendo DS\tScratched\t\t\tUntested/Not Working\t$10\t1
PS2\tSCPH-39001\t\t\t\t$30\t1
PS2\tSCPH-70012\t\t\t\t$40\t1
SNES\tControl Deck\tSNS-001\t\t\t$50\t1
Sega\tMK-1631\t\t\t\t$20\t1
Sega\tMK-1631A\t\t\t\t$25\t1
PS4\tCUH-7215B\t\t\t\t$70\t1
PS4\tCUH-2115B\t\t\t\t$60\t1
Disk Drive\tCFI-ZDD1\tOriginal Sony PlayStation 5 PS5 SLIM / PRO Blu-Ray DVD\t\t\t$50\t1
PS1\tSCPH-7501\t\t\t\t$15\t1`;

    const textarea = document.getElementById('clipboard-paste-area');
    if (textarea) {
        textarea.value = sample;
        onClipboardInput();
        const sector = document.getElementById('clipboard-sector');
        if (sector) sector.value = 'Gaming';
    }
}

function switchUploadMode(mode) {
    const pasteBox = document.getElementById('mode-paste-content');
    const fileBox = document.getElementById('mode-file-content');
    const tabPaste = document.getElementById('tab-mode-paste');
    const tabFile = document.getElementById('tab-mode-file');

    if (mode === 'paste') {
        if (pasteBox) pasteBox.style.display = 'block';
        if (fileBox) fileBox.style.display = 'none';
        if (tabPaste) {
            tabPaste.style.background = 'white';
            tabPaste.style.color = 'var(--accent-color)';
            tabPaste.style.borderColor = '#cbd5e1';
            tabPaste.style.borderBottomColor = 'white';
        }
        if (tabFile) {
            tabFile.style.background = '#f1f5f9';
            tabFile.style.color = '#64748b';
            tabFile.style.borderColor = 'transparent';
        }
    } else {
        if (pasteBox) pasteBox.style.display = 'none';
        if (fileBox) fileBox.style.display = 'block';
        if (tabFile) {
            tabFile.style.background = 'white';
            tabFile.style.color = 'var(--accent-color)';
            tabFile.style.borderColor = '#cbd5e1';
            tabFile.style.borderBottomColor = 'white';
        }
        if (tabPaste) {
            tabPaste.style.background = '#f1f5f9';
            tabPaste.style.color = '#64748b';
            tabPaste.style.borderColor = 'transparent';
        }
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
