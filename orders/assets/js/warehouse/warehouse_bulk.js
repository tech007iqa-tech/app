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
 * Synchronizes the two search bars (Header and Footer) and filters the table
 */
function syncSearch(inputEl) {
    const rect = inputEl.getBoundingClientRect();
    const offsetTop = rect.top;

    if (window.location.hash) {
        window.history.replaceState({}, '', window.location.pathname + window.location.search);
    }

    const otherId = inputEl.id === 'wh-search' ? 'wh-search-footer' : 'wh-search';
    const otherEl = document.getElementById(otherId);
    if (otherEl) otherEl.value = inputEl.value;

    sessionStorage.setItem('wh_active_search', inputEl.value);
    filterWarehouse();

    if (inputEl.id === 'wh-search-footer') {
        const newRect = inputEl.getBoundingClientRect();
        const diff = newRect.top - offsetTop;
        window.scrollBy(0, diff);
    }
}

/**
 * Filters the warehouse inventory list based on search input
 */
function filterWarehouse() {
    const searchInput = document.getElementById('wh-search');
    const footerInput = document.getElementById('wh-search-footer');
    if (!searchInput && !footerInput) return;

    const rawValue = (searchInput ? searchInput.value : "") || (footerInput ? footerInput.value : "");
    const terms = rawValue.toLowerCase().split(' ').filter(t => t.trim() !== '');

    const cards = document.getElementsByClassName('inventory-card');
    const noResultsRow = document.getElementById('wh-no-results');

    let visibleQtyTotal = 0;
    let visibleCount = 0;

    for (let i = 0; i < cards.length; i++) {
        let text = "";
        const cellInputs = cards[i].querySelectorAll('.cell-input');
        if (cellInputs.length > 0) {
            const values = [];
            cellInputs.forEach(input => {
                values.push(input.value);
            });
            text = values.join(' ').toLowerCase();
        } else {
            text = (cards[i].getAttribute('data-search') || "").toLowerCase();
        }

        const isMatch = terms.every(term => text.includes(term));

        if (isMatch) {
            cards[i].style.display = "";
            visibleCount++;

            let qty = 0;
            const qtyPill = cards[i].querySelector('.qty-pill');
            if (qtyPill) {
                qty = parseInt(qtyPill.innerText, 10) || 0;
            } else {
                const qtyInput = cards[i].querySelector('[data-field="quantity"] .cell-input');
                if (qtyInput) {
                    qty = parseInt(qtyInput.value, 10) || 0;
                }
            }
            visibleQtyTotal += qty;
        } else {
            cards[i].style.display = "none";
        }
    }

    if (noResultsRow) {
        noResultsRow.style.display = (visibleCount === 0 && terms.length > 0) ? "" : "none";
    }

    const totalQtyElem = document.getElementById('table-total-qty');
    if (totalQtyElem) {
        totalQtyElem.innerText = visibleQtyTotal.toLocaleString();
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
