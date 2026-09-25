/**
 * Warehouse Spreadsheet Engine Module
 * Handles in-cell spreadsheet editing, arrow/tab key navigation, auto-save on blur, blank row instantiation, and consolidation.
 */

function initWarehouseSpreadsheetEvents() {
    const listContainer = document.getElementById('inventory-list');
    if (!listContainer) return;

    // Guard against duplicate binding
    if (listContainer.dataset.spreadsheetEventsBound === 'true') return;
    listContainer.dataset.spreadsheetEventsBound = 'true';

    // Check if we are in spreadsheet mode (metadata block is present)
    const metadata = document.getElementById('warehouse-metadata');
    if (!metadata) return;

    // Handle blur updates (Auto-save)
    listContainer.addEventListener('focusout', (e) => {
        if (e.target && e.target.classList.contains('cell-input')) {
            const row = e.target.closest('tr');
            if (!row) return;

            // For the blank row, do NOT auto-save if focus is simply moving between cells inside the same row
            if (row.getAttribute('data-id') === 'new') {
                if (e.relatedTarget && row.contains(e.relatedTarget)) {
                    return; // User is still typing across other cells in the new row
                }
                // Focus left the row completely: if brand and model are filled, save it
                const brandVal = row.querySelector('[data-field="brand"] .cell-input')?.value.trim() || '';
                const modelVal = row.querySelector('[data-field="model"] .cell-input')?.value.trim() || '';
                if (brandVal !== '' && modelVal !== '') {
                    createWarehouseRowFromBlank(row);
                }
                return;
            }

            handleWarehouseCellSave(e.target);
        }
    });

    // Keyboard navigation: arrow keys, Enter, and Tab handling
    listContainer.addEventListener('keydown', (e) => {
        if (!e.target || !e.target.classList.contains('cell-input')) return;

        const input = e.target;
        const cell = input.closest('td');
        const row = input.closest('tr');
        if (!cell || !row) return;

        const colIndex = Array.from(row.cells).indexOf(cell);
        const allRows = Array.from(listContainer.querySelectorAll('.summary-row'));
        const rowIndex = allRows.indexOf(row);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusWarehouseCell(allRows, rowIndex + 1, colIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusWarehouseCell(allRows, rowIndex - 1, colIndex);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (row.getAttribute('data-id') === 'new') {
                const brandVal = row.querySelector('[data-field="brand"] .cell-input')?.value.trim() || '';
                const modelVal = row.querySelector('[data-field="model"] .cell-input')?.value.trim() || '';
                if (brandVal && modelVal) {
                    createWarehouseRowFromBlank(row);
                } else {
                    focusWarehouseCell(allRows, rowIndex, colIndex + 1);
                }
                return;
            }
            input.blur();
            focusWarehouseCell(allRows, rowIndex + 1, colIndex);
        }
    });

    // Handle clicks: [+] indicator button to add row & ➕ button to clone/copy row data
    listContainer.addEventListener('click', (e) => {
        const addBtn = e.target.closest('.btn-add-row-indicator');
        if (addBtn) {
            e.preventDefault();
            const row = addBtn.closest('tr');
            if (row) {
                createWarehouseRowFromBlank(row);
            }
            return;
        }

        const cloneBtn = e.target.closest('.btn-clone-row');
        if (cloneBtn) {
            e.preventDefault();
            const sourceRow = cloneBtn.closest('tr');
            const templateRow = listContainer.querySelector('.new-blank-row');
            if (sourceRow && templateRow) {
                const brand = sourceRow.querySelector('[data-field="brand"] .cell-input')?.value || '';
                const model = sourceRow.querySelector('[data-field="model"] .cell-input')?.value || '';
                const qty = sourceRow.querySelector('[data-field="quantity"] .cell-input')?.value || '1';
                const price = sourceRow.querySelector('[data-field="price"] .cell-input')?.value || '0';
                const condition = sourceRow.querySelector('[data-field="condition"] .cell-input')?.value || 'Used';
                const notes = sourceRow.querySelector('[data-field="notes"] .cell-input')?.value || '';
                const locCode = sourceRow.querySelector('[data-field="location_code"] .cell-input')?.value || '';

                const newRow = templateRow.cloneNode(true);
                if (locCode && newRow.querySelector('[data-field="location_code"] .cell-input')) {
                    newRow.querySelector('[data-field="location_code"] .cell-input').value = locCode;
                }
                newRow.querySelector('[data-field="brand"] .cell-input').value = brand;
                newRow.querySelector('[data-field="model"] .cell-input').value = model;
                newRow.querySelector('[data-field="quantity"] .cell-input').value = qty;
                newRow.querySelector('[data-field="price"] .cell-input').value = price;
                newRow.querySelector('[data-field="condition"] .cell-input').value = condition;
                newRow.querySelector('[data-field="notes"] .cell-input').value = notes;

                const whMetadata = document.getElementById('warehouse-metadata');
                const sector = whMetadata ? whMetadata.getAttribute('data-sector') : '';

                if (sector === 'Laptops') {
                    newRow.querySelector('[data-field="series"] .cell-input').value = sourceRow.querySelector('[data-field="series"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="cpu"] .cell-input').value = sourceRow.querySelector('[data-field="cpu"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="gen"] .cell-input').value = sourceRow.querySelector('[data-field="gen"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="ram"] .cell-input').value = sourceRow.querySelector('[data-field="ram"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="storage"] .cell-input').value = sourceRow.querySelector('[data-field="storage"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="battery"] .cell-input').value = sourceRow.querySelector('[data-field="battery"] .cell-input')?.value || '';
                } else if (sector === 'Gaming') {
                    newRow.querySelector('[data-field="gaming_category"] .cell-input').value = sourceRow.querySelector('[data-field="gaming_category"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="series"] .cell-input').value = sourceRow.querySelector('[data-field="series"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="cpu"] .cell-input').value = sourceRow.querySelector('[data-field="cpu"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="gpu"] .cell-input').value = sourceRow.querySelector('[data-field="gpu"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="ram"] .cell-input').value = sourceRow.querySelector('[data-field="ram"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="storage"] .cell-input').value = sourceRow.querySelector('[data-field="storage"] .cell-input')?.value || '';
                } else if (sector === 'Desktops') {
                    newRow.querySelector('[data-field="cpu_gen"] .cell-input').value = sourceRow.querySelector('[data-field="cpu_gen"] .cell-input')?.value || '';
                } else {
                    newRow.querySelector('[data-field="type"] .cell-input').value = sourceRow.querySelector('[data-field="type"] .cell-input')?.value || '';
                    newRow.querySelector('[data-field="voltage"] .cell-input').value = sourceRow.querySelector('[data-field="voltage"] .cell-input')?.value || '';
                }

                templateRow.parentNode.appendChild(newRow);

                const qtyInput = newRow.querySelector('[data-field="quantity"] .cell-input');
                if (qtyInput) {
                    qtyInput.focus();
                    if (typeof qtyInput.select === 'function') qtyInput.select();
                }
            }
        }
    });
}

function focusWarehouseCell(rows, rowIndex, colIndex) {
    if (rowIndex >= 0 && rowIndex < rows.length) {
        const targetRow = rows[rowIndex];
        if (colIndex >= 0 && colIndex < targetRow.cells.length) {
            const targetCell = targetRow.cells[colIndex];
            const targetInput = targetCell.querySelector('.cell-input');
            if (targetInput) {
                targetInput.focus();
                if (typeof targetInput.select === 'function') {
                    targetInput.select();
                }
            }
        }
    }
}

async function handleWarehouseCellSave(input) {
    const cell = input.closest('td');
    const row = input.closest('tr');
    if (!cell || !row) return;

    const rowId = row.getAttribute('data-id');
    const field = cell.getAttribute('data-field');
    const val = input.value.trim();

    // Skip save if it's a new row (handled by createWarehouseRowFromBlank)
    if (rowId === 'new') {
        return;
    }

    const metadata = document.getElementById('warehouse-metadata');
    const activeZone = metadata ? (metadata.getAttribute('data-zone') || '') : '';

    try {
        const result = await AppSync.post('api/update_inventory_field.php', {
            item_id: rowId,
            field: field,
            value: val,
            zone: activeZone
        });

        if (result.success) {
            const data = result.data || result;
            const counter = document.getElementById('sidebar-total-qty');
            if (counter && data.new_total !== undefined) {
                counter.textContent = data.new_total + ' Units';
                counter.classList.add('pulse');
                setTimeout(() => counter.classList.remove('pulse'), 500);
            }
            cell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
            setTimeout(() => {
                cell.style.backgroundColor = '';
            }, 600);

            if (typeof updateWarehouseRowSearchIndex === 'function') {
                updateWarehouseRowSearchIndex(row);
            }
            if (typeof filterWarehouse === 'function') {
                filterWarehouse();
            }
        } else {
            console.error('Save failed:', result.message || result.error);
            cell.style.backgroundColor = 'rgba(239, 68, 68, 0.2)';
            setTimeout(() => {
                cell.style.backgroundColor = '';
            }, 1000);
        }
    } catch (err) {
        console.error('Error updating cell field:', err);
    }
}

async function createWarehouseRowFromBlank(row) {
    if (!row || row.dataset.isSubmitting === 'true') return;

    const metadata = document.getElementById('warehouse-metadata');
    if (!metadata) {
        console.error('Warehouse metadata missing');
        return;
    }

    const sector = metadata.getAttribute('data-sector') || 'Laptops';
    const activeZone = metadata.getAttribute('data-zone') || '';
    let locationCode = metadata.getAttribute('data-location-code');
    const locInput = row.querySelector('[data-field="location_code"] .cell-input');
    if (locInput && locInput.value.trim()) {
        locationCode = locInput.value.trim();
    }
    const csrfToken = metadata.getAttribute('data-csrf');

    const brandInput = row.querySelector('[data-field="brand"] .cell-input');
    const modelInput = row.querySelector('[data-field="model"] .cell-input');
    const brand = brandInput?.value.trim() || '';
    const model = modelInput?.value.trim() || '';

    if (!brand || !model) {
        const msg = 'To intake a new inventory item, please enter both Brand and Model. (To create an empty shelf, use "+ Add Shelf" in the grid above).';
        if (window.IQA_Notify) {
            window.IQA_Notify.warning(msg);
        } else {
            alert(msg);
        }
        if (!brand && brandInput) {
            brandInput.focus();
            brandInput.style.outline = '2px solid #ef4444';
            setTimeout(() => brandInput.style.outline = '', 2500);
        } else if (!model && modelInput) {
            modelInput.focus();
            modelInput.style.outline = '2px solid #ef4444';
            setTimeout(() => modelInput.style.outline = '', 2500);
        }
        return;
    }

    row.dataset.isSubmitting = 'true';
    const btnIndicator = row.querySelector('.btn-add-row-indicator');
    let originalBtnText = '➕';
    if (btnIndicator) {
        originalBtnText = btnIndicator.textContent;
        btnIndicator.textContent = '⏳';
        btnIndicator.disabled = true;
    }

    const qty = parseInt(row.querySelector('[data-field="quantity"] .cell-input')?.value) || 1;
    const price = parseFloat(row.querySelector('[data-field="price"] .cell-input')?.value) || 0.00;
    const condition = row.querySelector('[data-field="condition"] .cell-input')?.value.trim() || 'Used';
    const notes = row.querySelector('[data-field="notes"] .cell-input')?.value.trim() || '';

    const formData = new FormData();
    formData.set('csrf_token', csrfToken);
    formData.set('sector', sector);
    formData.set('location_code', locationCode);
    if (activeZone) {
        formData.set('zone', activeZone);
    }
    formData.set('brand', brand);
    formData.set('model', model);
    formData.set('quantity', qty);
    formData.set('price', price);
    formData.set('condition', condition);
    formData.set('notes', notes);

    if (sector === 'Laptops') {
        formData.set('series', row.querySelector('[data-field="series"] .cell-input')?.value.trim() || '');
        formData.set('cpu', row.querySelector('[data-field="cpu"] .cell-input')?.value.trim() || '');
        formData.set('gen', row.querySelector('[data-field="gen"] .cell-input')?.value.trim() || '');
        formData.set('ram', row.querySelector('[data-field="ram"] .cell-input')?.value.trim() || '');
        formData.set('storage', row.querySelector('[data-field="storage"] .cell-input')?.value.trim() || '');
        formData.set('battery', row.querySelector('[data-field="battery"] .cell-input')?.value.trim() || '');
    } else if (sector === 'Gaming') {
        formData.set('gaming_category', row.querySelector('[data-field="gaming_category"] .cell-input')?.value.trim() || 'PC');
        formData.set('series', row.querySelector('[data-field="series"] .cell-input')?.value.trim() || '');
        formData.set('cpu', row.querySelector('[data-field="cpu"] .cell-input')?.value.trim() || '');
        formData.set('gpu', row.querySelector('[data-field="gpu"] .cell-input')?.value.trim() || '');
        formData.set('ram', row.querySelector('[data-field="ram"] .cell-input')?.value.trim() || '');
        formData.set('storage', row.querySelector('[data-field="storage"] .cell-input')?.value.trim() || '');
    } else if (sector === 'Desktops') {
        formData.set('cpu_gen', row.querySelector('[data-field="cpu_gen"] .cell-input')?.value.trim() || '');
    } else {
        formData.set('type', row.querySelector('[data-field="type"] .cell-input')?.value.trim() || '');
        formData.set('voltage', row.querySelector('[data-field="voltage"] .cell-input')?.value.trim() || '');
    }

    try {
        const response = await AppSync.post('api/add_inventory_item.php', formData);

        if (response.success) {
            const notifyEngine = window.Notifications || window.IQA_Notify;
            if (notifyEngine && typeof notifyEngine.success === 'function') {
                notifyEngine.success('Item successfully added ✨');
            }

            // Clear the inputs on the blank row to ready for next entry
            const inputsToClear = row.querySelectorAll('.cell-input');
            inputsToClear.forEach(input => {
                const parentCell = input.closest('td');
                const fieldName = parentCell ? parentCell.getAttribute('data-field') : '';
                // Preserve default shelf location if present
                if (fieldName === 'location_code') {
                    return;
                }
                if (fieldName === 'quantity') {
                    input.value = '';
                } else if (fieldName === 'price') {
                    input.value = '';
                } else if (fieldName === 'condition') {
                    input.value = 'Used';
                } else {
                    input.value = '';
                }
            });

            // Sync the table via AppSync immediately
            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                await window.AppSync.sync('inventory-list', true);
            }

            const resData = response.data || response;
            if (resData.new_total !== undefined) {
                const counter = document.getElementById('sidebar-total-qty');
                if (counter) {
                    counter.textContent = resData.new_total + ' Units';
                    counter.classList.add('pulse');
                    setTimeout(() => counter.classList.remove('pulse'), 500);
                }
            }

            // Refocus to the blank brand input for fast rapid intake
            const brandIn = row.querySelector('[data-field="brand"] .cell-input');
            if (brandIn) {
                brandIn.focus();
            }
        } else {
            const errMsg = response.message || response.error || 'Failed to add item to inventory.';
            const notifyEngine = window.Notifications || window.IQA_Notify;
            if (notifyEngine && typeof notifyEngine.error === 'function') {
                notifyEngine.error(errMsg);
            } else {
                alert(errMsg);
            }
        }
    } catch (err) {
        console.error('Error adding row:', err);
        const notifyEngine = window.Notifications || window.IQA_Notify;
        if (notifyEngine && typeof notifyEngine.error === 'function') {
            notifyEngine.error('A network error occurred while adding row.');
        } else {
            alert('A network error occurred while adding row.');
        }
    } finally {
        delete row.dataset.isSubmitting;
        if (btnIndicator) {
            btnIndicator.textContent = originalBtnText;
            btnIndicator.disabled = false;
        }
    }
}

function restoreWarehouseCursorFocus() {
    const restoreField = sessionStorage.getItem('warehouse_restore_field');
    const restoreItemId = sessionStorage.getItem('warehouse_restore_item_id');

    if (restoreField && restoreItemId) {
        sessionStorage.removeItem('warehouse_restore_field');
        sessionStorage.removeItem('warehouse_restore_item_id');

        const row = document.querySelector(`.inventory-card[data-id="${restoreItemId}"]`);
        if (row) {
            const cell = row.querySelector(`[data-field="${restoreField}"]`);
            if (cell) {
                const input = cell.querySelector('.cell-input');
                if (input) {
                    setTimeout(() => {
                        input.focus();
                        if (typeof input.select === 'function') {
                            input.select();
                        }
                    }, 50);
                }
            }
        }
    }
}

/**
 * Consolidates duplicate rows with identical fields in current sector/location.
 */
async function consolidateWarehouseRows() {
    const metadata = document.getElementById('warehouse-metadata');
    if (!metadata) {
        alert("Spreadsheet metadata not found.");
        return;
    }

    const sector = metadata.getAttribute('data-sector');
    const locationCode = metadata.getAttribute('data-location-code');
    const csrfToken = metadata.getAttribute('data-csrf');

    if (!confirm("Are you sure you want to consolidate rows with identical values in this zone/shelf? Duplicate items will be merged and their quantities added together. Items with different notes will not be merged.")) {
        return;
    }

    const btn = document.getElementById('btn-consolidate-spreadsheet');
    let originalHtml = "";
    if (btn) {
        originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "⏳ Merging...";
    }

    try {
        const response = await fetch('api/consolidate_inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                sector: sector,
                location_code: locationCode
            })
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success(result.message || 'Rows consolidated successfully ✨');
            } else {
                alert(result.message || 'Rows consolidated successfully');
            }
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            if (window.IQA_Notify) {
                window.IQA_Notify.error('Failed to consolidate: ' + (result.error || 'Unknown error'));
            } else {
                alert('Failed to consolidate: ' + (result.error || 'Unknown error'));
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    } catch (err) {
        console.error('Error consolidating rows:', err);
        if (window.IQA_Notify) {
            window.IQA_Notify.error('An error occurred while consolidating rows.');
        } else {
            alert('An error occurred while consolidating rows.');
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
}

// Auto-initialize when file is loaded or DOM becomes ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWarehouseSpreadsheetEvents);
} else {
    initWarehouseSpreadsheetEvents();
}
