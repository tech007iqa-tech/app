/**
 * Warehouse Bulk Actions & Utility Module
 * Handles multi-row selection, batch zone relocation, batch repricing, CSV exporting, label generation, and search filtering.
 */

let selectedIds = new Set();
let lastChecked = null;

function updateBulkBar() {
    const selectedCount = document.getElementById('selectedCount');
    const bulkBar = document.getElementById('bulkActionBar');
    const count = selectedIds.size;
    if (selectedCount) selectedCount.textContent = count;
    if (bulkBar) bulkBar.style.display = count > 0 ? 'flex' : 'none';
}

function initWarehouseBulkActions() {
    const selectAll = document.getElementById('selectAll');
    const tbody = document.getElementById('inventory-list');
    const cancelBulkBtn = document.getElementById('cancelBulkBtn');
    const applyBulkBtn = document.getElementById('applyBulkBtn');

    if (selectAll && tbody) {
        selectAll.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            const checkboxes = tbody.querySelectorAll('.row-select');
            checkboxes.forEach(cb => {
                const tr = cb.closest('tr');
                if (tr && tr.style.display !== 'none') {
                    cb.checked = isChecked;
                    const id = tr.dataset.id;
                    if (isChecked) {
                        selectedIds.add(id);
                        tr.classList.add('selected-row');
                    } else {
                        selectedIds.delete(id);
                        tr.classList.remove('selected-row');
                    }
                }
            });
            updateBulkBar();
        });
    }

    if (tbody) {
        tbody.addEventListener('click', (e) => {
            if (e.target.classList.contains('row-select')) {
                const currentCb = e.target;
                const checkboxes = Array.from(tbody.querySelectorAll('.row-select')).filter(cb => cb.closest('tr').style.display !== 'none');

                if (e.shiftKey && lastChecked && lastChecked !== currentCb) {
                    let start = checkboxes.indexOf(currentCb);
                    let end = checkboxes.indexOf(lastChecked);

                    if (start > -1 && end > -1) {
                        const range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);
                        const isChecked = currentCb.checked;

                        range.forEach(cb => {
                            cb.checked = isChecked;
                            const tr = cb.closest('tr');
                            const id = tr ? tr.dataset.id : null;
                            if (id) {
                                if (isChecked) {
                                    selectedIds.add(id);
                                    tr.classList.add('selected-row');
                                } else {
                                    selectedIds.delete(id);
                                    tr.classList.remove('selected-row');
                                }
                            }
                        });
                    }
                } else {
                    const tr = currentCb.closest('tr');
                    const id = tr ? tr.dataset.id : null;
                    if (id) {
                        if (currentCb.checked) {
                            selectedIds.add(id);
                            tr.classList.add('selected-row');
                        } else {
                            selectedIds.delete(id);
                            tr.classList.remove('selected-row');
                            if (selectAll) selectAll.checked = false;
                        }
                    }
                }

                lastChecked = currentCb;
                updateBulkBar();
            }
        });
    }

    if (cancelBulkBtn) {
        cancelBulkBtn.addEventListener('click', () => {
            selectedIds.clear();
            if (selectAll) selectAll.checked = false;
            if (tbody) {
                tbody.querySelectorAll('.row-select').forEach(cb => {
                    cb.checked = false;
                    cb.closest('tr')?.classList.remove('selected-row');
                });
            }
            updateBulkBar();
        });
    }

    if (applyBulkBtn) {
        applyBulkBtn.addEventListener('click', async () => {
            const location = document.getElementById('bulkLocation')?.value.trim() || '';
            const price = document.getElementById('bulkPrice')?.value.trim() || '';

            if (!location && !price) {
                alert("Please specify a new location or price to apply.");
                return;
            }

            if (!confirm(`Apply changes to ${selectedIds.size} items?`)) return;

            applyBulkBtn.disabled = true;
            applyBulkBtn.textContent = '⌛ Applying...';

            try {
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                const response = await fetch('api/bulk_update_inventory.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        ids: Array.from(selectedIds),
                        location: location,
                        price: price
                    })
                });

                const json = await response.json();
                if (json.success) {
                    if (window.IQA_Notify) {
                        window.IQA_Notify.success(`Successfully updated ${selectedIds.size} items!`);
                    }
                    selectedIds.clear();
                    if (selectAll) selectAll.checked = false;
                    updateBulkBar();
                    window.location.reload();
                } else {
                    if (window.IQA_Notify) {
                        window.IQA_Notify.error(`Error: ${json.error}`);
                    } else {
                        alert(`Error: ${json.error}`);
                    }
                }
            } catch (err) {
                if (window.IQA_Notify) {
                    window.IQA_Notify.error("Network error during bulk update.");
                } else {
                    alert("Network error during bulk update.");
                }
            } finally {
                applyBulkBtn.disabled = false;
                applyBulkBtn.textContent = 'Apply Batch Changes';
            }
        });
    }
}

/**
 * Global In-Memory Inventory Search Index & Performance State
 */
window.__whInventoryIndex = [];
let __searchDebounceTimer = null;
let __searchRafId = null;

/**
 * Builds / Rebuilds the in-memory compiled search index
 */
function buildWarehouseSearchIndex() {
    const listContainer = document.getElementById('inventory-list');
    if (!listContainer) {
        window.__whInventoryIndex = [];
        return;
    }

    const cards = listContainer.querySelectorAll('.inventory-card:not(.new-blank-row)');
    const index = [];

    cards.forEach(card => {
        const entry = extractWarehouseRowData(card);
        if (entry) index.push(entry);
    });

    window.__whInventoryIndex = index;
}

/**
 * Extracts normalized data from a table row element for the search index
 */
function extractWarehouseRowData(card) {
    if (!card || card.classList.contains('no-results-row') || card.classList.contains('new-blank-row')) return null;

    const id = card.getAttribute('data-id') || '';
    const sector = (card.getAttribute('data-sector') || card.getAttribute('data-sector-theme') || '').toLowerCase();
    
    // Read direct attributes or cell inputs
    let brand = card.getAttribute('data-brand') || '';
    let model = card.getAttribute('data-model') || '';
    let location = card.getAttribute('data-location') || '';
    let price = parseFloat(card.getAttribute('data-price') || '0');
    let qty = parseInt(card.getAttribute('data-qty') || '0', 10);
    
    // In spreadsheet mode, cell inputs take precedence if present
    const brandInput = card.querySelector('[data-field="brand"] .cell-input');
    if (brandInput) brand = brandInput.value.trim();
    
    const modelInput = card.querySelector('[data-field="model"] .cell-input');
    if (modelInput) model = modelInput.value.trim();
    
    const locInput = card.querySelector('[data-field="location_code"] .cell-input');
    if (locInput) location = locInput.value.trim();
    
    const locTag = card.querySelector('.location-tag');
    if (!location && locTag) location = locTag.textContent.trim();
    
    const qtyInput = card.querySelector('[data-field="quantity"] .cell-input');
    const qtyPill = card.querySelector('.qty-pill');
    if (qtyInput) qty = parseInt(qtyInput.value, 10) || 0;
    else if (qtyPill) qty = parseInt(qtyPill.textContent, 10) || 0;

    const priceInput = card.querySelector('[data-field="price"] .cell-input');
    if (priceInput) price = parseFloat(priceInput.value) || 0;

    const specsJson = card.getAttribute('data-specs') || '{}';
    let specs = {};
    try { specs = JSON.parse(specsJson); } catch (e) { specs = {}; }

    // Read spec cell inputs if present
    const cpuInput = card.querySelector('[data-field="cpu"] .cell-input') || card.querySelector('[data-field="cpu_gen"] .cell-input');
    const cpuVal = (cpuInput ? cpuInput.value.trim() : (specs.cpu || specs.cpu_gen || '')).toLowerCase();
    
    const ramInput = card.querySelector('[data-field="ram"] .cell-input');
    const ramVal = (ramInput ? ramInput.value.trim() : (specs.ram || '')).toLowerCase();
    
    const storageInput = card.querySelector('[data-field="storage"] .cell-input');
    const storageVal = (storageInput ? storageInput.value.trim() : (specs.storage || '')).toLowerCase();

    const seriesInput = card.querySelector('[data-field="series"] .cell-input');
    const seriesVal = (seriesInput ? seriesInput.value.trim() : (specs.series || '')).toLowerCase();

    const genInput = card.querySelector('[data-field="gen"] .cell-input');
    const genVal = (genInput ? genInput.value.trim() : (specs.gen || '')).toLowerCase();

    const gpuInput = card.querySelector('[data-field="gpu"] .cell-input');
    const gpuVal = (gpuInput ? gpuInput.value.trim() : (specs.gpu || '')).toLowerCase();

    const batteryInput = card.querySelector('[data-field="battery"] .cell-input');
    const batteryVal = (batteryInput ? batteryInput.value.trim() : (specs.battery || '')).toLowerCase();

    const conditionInput = card.querySelector('[data-field="condition"] .cell-input');
    const conditionVal = (conditionInput ? conditionInput.value.trim() : (specs.condition || 'Used')).toLowerCase();

    const notesInput = card.querySelector('[data-field="notes"] .cell-input');
    const notesVal = (notesInput ? notesInput.value.trim() : (specs.notes || '')).toLowerCase();

    const statusVal = (card.querySelector('.status-badge')?.textContent || '').toLowerCase();

    // Normalizations & Synonyms Expansion
    const rawTokens = [
        brand, model, location, sector, seriesVal, cpuVal, genVal, ramVal, storageVal, gpuVal, batteryVal, conditionVal, notesVal, statusVal
    ];
    const rawSearch = rawTokens.join(' ').toLowerCase();
    const cleanSearch = rawSearch.replace(/[-_.,/\\#;:()]/g, ' ');

    // Hardware Synonym and Prefix Tokens
    const expandedTokens = [];
    
    // RAM synonyms: e.g. "16gb" -> "16g", "16 ram", "16-gb"
    if (ramVal) {
        const numRam = ramVal.replace(/[^0-9]/g, '');
        if (numRam) {
            expandedTokens.push(numRam + 'g', numRam + 'gb', numRam + ' ram');
        }
    }

    // Storage synonyms: e.g. "512gb" -> "512g", "512 ssd", "512 nvme"
    if (storageVal) {
        const numStorage = storageVal.replace(/[^0-9]/g, '');
        if (numStorage) {
            expandedTokens.push(numStorage + 'g', numStorage + 'gb', numStorage + ' ssd', numStorage + ' nvme');
        }
    }

    // CPU Generation synonyms: e.g. "8th" -> "gen 8", "8th gen", "8gen"
    if (genVal || cpuVal) {
        const combinedCpu = (genVal + ' ' + cpuVal).toLowerCase();
        const genMatch = combinedCpu.match(/(\d+)(?:th|nd|rd|st)?\s*gen/i) || combinedCpu.match(/gen\s*(\d+)/i) || combinedCpu.match(/i[3579]-?(\d{1,2})\d{2,3}/i);
        if (genMatch) {
            const gNum = genMatch[1];
            expandedTokens.push(gNum + 'th', gNum + 'th gen', 'gen ' + gNum, 'gen' + gNum);
        }
    }

    // Location / Shelf synonyms: e.g. "A-1" -> "a1", "shelf a 1", "zone a"
    if (location) {
        expandedTokens.push(location.replace(/[-_]/g, ''), 'shelf ' + location);
    }

    const fullNormalizedSearch = (rawSearch + ' ' + cleanSearch + ' ' + expandedTokens.join(' ')).replace(/\s+/g, ' ').trim();

    return {
        el: card,
        id: id,
        brand: brand.toLowerCase(),
        model: model.toLowerCase(),
        location: location.toLowerCase(),
        sector: sector,
        series: seriesVal,
        cpu: cpuVal,
        gpu: gpuVal,
        gen: genVal,
        ram: ramVal,
        storage: storageVal,
        condition: conditionVal,
        notes: notesVal,
        status: statusVal,
        qty: qty,
        price: price,
        normalizedText: fullNormalizedSearch
    };
}

/**
 * Updates a single row inside the in-memory search index
 */
function updateWarehouseRowSearchIndex(row) {
    if (!row || !window.__whInventoryIndex) return;
    const id = row.getAttribute('data-id');
    const existingIdx = window.__whInventoryIndex.findIndex(item => item.el === row || (id && item.id === id));
    const newData = extractWarehouseRowData(row);

    if (newData) {
        if (existingIdx >= 0) {
            window.__whInventoryIndex[existingIdx] = newData;
        } else {
            window.__whInventoryIndex.push(newData);
        }
    } else if (existingIdx >= 0) {
        window.__whInventoryIndex.splice(existingIdx, 1);
    }
}

/**
 * Parses user search query into structured search tokens:
 * - Field filters: brand:dell, model:t480, loc:a-1, shelf:a-1, sec:laptops, cpu:i7, ram:16, storage:512, cond:tested
 * - Range/numeric filters: qty:>5, qty:<=10, qty:0, price:>100, price:<50
 * - Negation tokens: -broken, !parts, -dell
 * - Quoted exact terms: "ThinkPad T480"
 * - General words: space-separated order-independent matching
 */
function parseWarehouseQuery(queryStr) {
    if (!queryStr || typeof queryStr !== 'string') return null;
    const cleanStr = queryStr.trim();
    if (!cleanStr) return null;

    const terms = [];
    const fieldFilters = [];
    const numFilters = [];
    const negations = [];

    // Extract quoted strings first: e.g. "ThinkPad T480"
    const regex = /"([^"]+)"|(\S+)/g;
    let match;
    while ((match = regex.exec(cleanStr)) !== null) {
        const token = match[1] || match[2];
        if (!token) continue;

        // Negation: -word or !word
        if (token.startsWith('-') || token.startsWith('!')) {
            const negTerm = token.slice(1).toLowerCase().trim();
            if (negTerm) negations.push(negTerm);
            continue;
        }

        // Numeric Comparison: qty:>5, qty:<10, qty:0, price:>100
        const numMatch = token.match(/^(qty|quantity|price)([:=><]=?|>|<|=)(-?\d+(?:\.\d+)?)$/i);
        if (numMatch) {
            const field = numMatch[1].toLowerCase().startsWith('qty') ? 'qty' : 'price';
            const op = numMatch[2].replace(':', '=');
            const val = parseFloat(numMatch[3]);
            numFilters.push({ field, op, val });
            continue;
        }

        // Field filter: brand:dell, b:dell, model:t480, m:t480, shelf:a-1, loc:a-1, sec:laptops, cpu:i7, ram:16, storage:512, cond:tested
        const fieldMatch = token.match(/^([a-zA-Z_]+):(.+)$/);
        if (fieldMatch) {
            const rawField = fieldMatch[1].toLowerCase();
            const val = fieldMatch[2].toLowerCase().trim();
            let targetField = null;

            if (['brand', 'b', 'make'].includes(rawField)) targetField = 'brand';
            else if (['model', 'm'].includes(rawField)) targetField = 'model';
            else if (['location', 'loc', 'shelf', 'zone'].includes(rawField)) targetField = 'location';
            else if (['sector', 'sec', 'cat', 'category'].includes(rawField)) targetField = 'sector';
            else if (['cpu', 'processor'].includes(rawField)) targetField = 'cpu';
            else if (['ram', 'memory'].includes(rawField)) targetField = 'ram';
            else if (['storage', 'ssd', 'hdd', 'nvme', 'disk'].includes(rawField)) targetField = 'storage';
            else if (['gpu', 'graphics', 'video'].includes(rawField)) targetField = 'gpu';
            else if (['series'].includes(rawField)) targetField = 'series';
            else if (['cond', 'condition', 'grade'].includes(rawField)) targetField = 'condition';
            else if (['notes', 'note'].includes(rawField)) targetField = 'notes';
            else if (['status'].includes(rawField)) targetField = 'status';

            if (targetField && val) {
                fieldFilters.push({ field: targetField, val: val });
                continue;
            }
        }

        // General term
        terms.push(token.toLowerCase());
    }

    return { terms, fieldFilters, numFilters, negations };
}

/**
 * Evaluates whether an indexed item matches the parsed search query
 */
function matchesWarehouseRow(item, parsedQuery) {
    if (!parsedQuery) return true;

    // 1. Negations: if any negated term is found in normalizedText, reject
    for (let i = 0; i < parsedQuery.negations.length; i++) {
        const neg = parsedQuery.negations[i];
        if (item.normalizedText.includes(neg)) return false;
    }

    // 2. Numeric Range Filters
    for (let i = 0; i < parsedQuery.numFilters.length; i++) {
        const nf = parsedQuery.numFilters[i];
        const rowVal = nf.field === 'qty' ? item.qty : item.price;
        if (nf.op === '=' || nf.op === '==' || nf.op === ':=') {
            if (rowVal !== nf.val) return false;
        } else if (nf.op === '>') {
            if (rowVal <= nf.val) return false;
        } else if (nf.op === '>=') {
            if (rowVal < nf.val) return false;
        } else if (nf.op === '<') {
            if (rowVal >= nf.val) return false;
        } else if (nf.op === '<=') {
            if (rowVal > nf.val) return false;
        }
    }

    // 3. Field Filters
    for (let i = 0; i < parsedQuery.fieldFilters.length; i++) {
        const ff = parsedQuery.fieldFilters[i];
        const valOnRow = (item[ff.field] || '').toString();
        if (!valOnRow.includes(ff.val)) return false;
    }

    // 4. General Search Terms (all terms must match normalizedText)
    for (let i = 0; i < parsedQuery.terms.length; i++) {
        const term = parsedQuery.terms[i];
        const cleanTerm = term.replace(/[-_.,/\\#;:()]/g, '');
        if (!item.normalizedText.includes(term) && (!cleanTerm || !item.normalizedText.includes(cleanTerm))) {
            return false;
        }
    }

    return true;
}

/**
 * Synchronizes search input value, updates clear buttons, and triggers fast debounced filter
 */
function syncSearch(inputEl) {
    if (!inputEl) return;
    const query = inputEl.value;

    if (window.location.hash) {
        window.history.replaceState({}, '', window.location.pathname + window.location.search);
    }

    const otherId = inputEl.id === 'wh-search' ? 'wh-search-footer' : 'wh-search';
    const otherEl = document.getElementById(otherId);
    if (otherEl && otherEl.value !== query) otherEl.value = query;

    // Toggle clear button visibility
    const clearBtn = document.getElementById('search-clear-btn');
    if (clearBtn) {
        if (query.trim() !== '') {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
        }
    }

    sessionStorage.setItem('wh_active_search', query);

    // High performance RAF debouncing
    if (__searchDebounceTimer) clearTimeout(__searchDebounceTimer);
    __searchDebounceTimer = setTimeout(() => {
        if (__searchRafId) cancelAnimationFrame(__searchRafId);
        __searchRafId = requestAnimationFrame(() => {
            filterWarehouse();
        });
    }, 20);
}

/**
 * Handles keyboard navigation & shortcuts on search inputs
 */
function handleSearchKeydown(event, inputEl) {
    if (!event) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        clearWarehouseSearch();
        inputEl.blur();
    } else if (event.key === 'Enter') {
        event.preventDefault();
    }
}

/**
 * Clears search input across all bars and restores all rows instantly
 */
function clearWarehouseSearch() {
    const s1 = document.getElementById('wh-search');
    const s2 = document.getElementById('wh-search-footer');
    if (s1) s1.value = '';
    if (s2) s2.value = '';

    const clearBtn = document.getElementById('search-clear-btn');
    if (clearBtn) clearBtn.classList.remove('visible');

    sessionStorage.removeItem('wh_active_search');
    filterWarehouse();

    if (s1) s1.focus();
}

/**
 * High-Speed Filter Execution against the in-memory index
 */
function filterWarehouse() {
    const searchInput = document.getElementById('wh-search');
    const footerInput = document.getElementById('wh-search-footer');
    if (!searchInput && !footerInput) return;

    const rawValue = (searchInput ? searchInput.value : "") || (footerInput ? footerInput.value : "");
    const parsedQuery = parseWarehouseQuery(rawValue);

    // Ensure search index is populated
    if (!window.__whInventoryIndex || window.__whInventoryIndex.length === 0) {
        buildWarehouseSearchIndex();
    }

    const index = window.__whInventoryIndex;
    const noResultsRow = document.getElementById('wh-no-results');
    const matchCountBadge = document.getElementById('search-match-count');

    let visibleQtyTotal = 0;
    let visibleCount = 0;
    const totalItems = index.length;

    // Fast in-memory evaluation and batch DOM update
    for (let i = 0; i < totalItems; i++) {
        const item = index[i];
        if (!item || !item.el) continue;

        const isMatch = matchesWarehouseRow(item, parsedQuery);

        if (isMatch) {
            item.el.classList.remove('wh-row-hidden');
            item.el.style.display = "";
            visibleCount++;
            visibleQtyTotal += item.qty;
        } else {
            item.el.classList.add('wh-row-hidden');
            item.el.style.display = "none";
        }
    }

    // Toggle No Results Placeholder
    if (noResultsRow) {
        if (visibleCount === 0 && rawValue.trim() !== '') {
            noResultsRow.style.display = "";
            noResultsRow.classList.remove('wh-row-hidden');
        } else {
            noResultsRow.style.display = "none";
            noResultsRow.classList.add('wh-row-hidden');
        }
    }

    // Live Metrics Update
    const totalQtyElem = document.getElementById('table-total-qty') || document.getElementById('sidebar-total-qty');
    if (totalQtyElem) {
        totalQtyElem.textContent = visibleQtyTotal.toLocaleString() + " Units";
    }

    // Update Match Count Badge if filtered
    if (matchCountBadge) {
        if (rawValue.trim() !== '' && totalItems > 0) {
            matchCountBadge.textContent = `Showing ${visibleCount.toLocaleString()} of ${totalItems.toLocaleString()} items`;
            matchCountBadge.style.display = "inline-flex";
        } else {
            matchCountBadge.style.display = "none";
        }
    }
}

/**
 * Generates and downloads a CSV of the visible warehouse inventory
 */
function downloadWarehouseCSV() {
    const cards = document.querySelectorAll('.inventory-card');
    const activeLocElem = document.querySelector('.loc-text');
    const activeLoc = activeLocElem ? activeLocElem.innerText.trim() : 'Warehouse';
    const isGlobal = activeLoc === 'GLOBAL';

    let csv = `"Active Location","${activeLoc} 📍",,,,,,,\n\n`;
    const headers = ["Date", "Time", "Type", "Brand", "Model", "Series", "CPU / Gen", "Description", "Notes", "Battery", "Price", "QTY", "Total"];
    if (isGlobal) headers.splice(2, 0, "Location");

    csv += headers.map(h => `"${h}"`).join(",") + "\n";

    const sanitize = (val) => `"${(val || "").toString().trim().replace(/"/g, '""')}"`;
    let count = 0;

    cards.forEach(card => {
        if (card.style.display !== 'none') {
            const specs = JSON.parse(card.getAttribute('data-specs') || '{}');
            const brand = card.getAttribute('data-brand') || '';
            const model = card.getAttribute('data-model') || '';
            let qty = '0';
            const qtyElement = card.querySelector('.qty-pill');
            if (qtyElement) {
                qty = qtyElement.innerText.trim();
            } else {
                const qtyInput = card.querySelector('[data-field="quantity"] .cell-input');
                if (qtyInput) qty = qtyInput.value.trim();
            }

            let price = card.getAttribute('data-price') || '0.00';
            const priceInput = card.querySelector('[data-field="price"] .cell-input');
            if (priceInput) price = priceInput.value.trim();

            const total = (parseFloat(price) * parseInt(qty)).toFixed(2);
            const createdDate = card.getAttribute('data-created-date') || '';
            const createdTime = card.getAttribute('data-created-time') || '';

            const locTag = card.querySelector('.location-tag');
            const itemLoc = locTag ? locTag.innerText.trim() : '';

            let cpuGen = (specs.cpu || "") + (specs.gen ? " (" + specs.gen + ")" : "");
            if (card.getAttribute('data-sector-theme') === 'Desktops') {
                cpuGen = specs.cpu_gen || '';
            }
            const sectorTheme = card.getAttribute('data-sector-theme') || 'Laptops';

            const batteryVal = specs.battery || "";
            const isBatteryNo = (batteryVal.toLowerCase() === 'no' || batteryVal.toLowerCase() === 'missing' || batteryVal.toLowerCase() === 'dead');
            const descVal = isBatteryNo ? 'Parts' : 'Untested';

            let notesVal = "";
            const ram = specs.ram || "";
            const storage = specs.storage || "";
            if (ram || storage) {
                notesVal = `${ram}/${storage}`;
            }
            if (specs.notes) {
                notesVal += notesVal ? ` - ${specs.notes}` : specs.notes;
            }

            let itemType = "Laptop";
            if (sectorTheme === 'Desktops') itemType = "Desktop";
            else if (sectorTheme === 'Gaming') itemType = "Gaming";
            else if (sectorTheme === 'Electronics') itemType = "Electronics";

            const rowData = [
                sanitize(createdDate),
                sanitize(createdTime),
                sanitize(itemType),
                sanitize(brand),
                sanitize(model),
                sanitize(specs.series || ""),
                sanitize(cpuGen),
                sanitize(descVal),
                sanitize(notesVal),
                sanitize(batteryVal),
                sanitize(price),
                sanitize(qty),
                sanitize(total)
            ];

            if (isGlobal) rowData.splice(2, 0, sanitize(itemLoc));

            csv += rowData.join(",") + "\n";
            count++;
        }
    });

    if (count === 0) {
        alert("No visible items to export.");
        return;
    }

    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    const dateStamp = new Date().toISOString().slice(0, 10);
    const state = typeof getWarehouseState === 'function' ? getWarehouseState() : {};
    const sector = (state.activeSector || "Warehouse").replace(/\s+/g, '_');

    link.href = url;
    link.download = `LatinosPC_Inventory_${sector}_${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Generates and downloads a label (.odt) for a warehouse item.
 */
async function downloadWarehouseLabel(itemId, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '🏷️ ⏳';

    try {
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        const fd = new FormData();
        fd.append('id', itemId);
        fd.append('csrf_token', csrfToken);

        const response = await fetch('api/generate_warehouse_label.php', {
            method: 'POST',
            body: fd
        });

        const json = await response.json();
        if (json.success) {
            const filePath = json.data.file_path;
            const fileName = json.data.file_name;

            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            if (window.IQA_Notify) {
                window.IQA_Notify.success("Label generated and download started!");
            }
        } else {
            if (window.IQA_Notify) {
                window.IQA_Notify.error("Error: " + json.error);
            } else {
                alert("Error: " + json.error);
            }
        }
    } catch (err) {
        console.error(err);
        if (window.IQA_Notify) {
            window.IQA_Notify.error("Network error: Could not generate label.");
        } else {
            alert("Network error: Could not generate label.");
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
