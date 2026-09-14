/**
 * System — Batch Builder Spreadsheet Logic & Master Coordinator
 */

// 1. Synchronously load all new_order sub-modules in order
(function loadNewOrderModules() {
    const modules = [
        'assets/js/new_order/new_order_modals.js',
        'assets/js/new_order/new_order_import_clipboard.js',
        'assets/js/new_order/new_order_import_warehouse.js'
    ];

    modules.forEach(src => {
        if (!document.querySelector(`script[src*="${src}"]`)) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            document.head.appendChild(script);
        }
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    initSpreadsheetEvents();
    initSummarySearch();
    restoreCursorFocus();
});

function restoreCursorFocus() {
    const restoreField = sessionStorage.getItem('new_order_restore_field');
    const restoreItemId = sessionStorage.getItem('new_order_restore_item_id');

    if (restoreField && restoreItemId) {
        sessionStorage.removeItem('new_order_restore_field');
        sessionStorage.removeItem('new_order_restore_item_id');

        const row = document.querySelector(`.summary-row[data-id="${restoreItemId}"]`);
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

function initSpreadsheetEvents() {
    const listContainer = document.getElementById('summary-list');
    if (!listContainer) return;

    // Handle blur updates (Auto-save)
    listContainer.addEventListener('focusout', (e) => {
        if (e.target && e.target.classList.contains('cell-input')) {
            handleCellSave(e.target);
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
            focusCell(allRows, rowIndex + 1, colIndex);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusCell(allRows, rowIndex - 1, colIndex);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            input.blur();
            focusCell(allRows, rowIndex + 1, colIndex);
        }
    });

    // Handle click on ➕ indicator to clone a new blank row or copy row data
    listContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-add-row-indicator');
        const cloneBtn = e.target.closest('.btn-clone-row');

        if (btn) {
            e.preventDefault();
            const templateRow = listContainer.querySelector('.new-blank-row');
            if (templateRow) {
                const newRow = templateRow.cloneNode(true);
                newRow.querySelectorAll('.cell-input').forEach(input => {
                    input.value = '';
                });
                const newBtn = newRow.querySelector('.btn-add-row-indicator');
                if (newBtn) {
                    newBtn.textContent = '➕';
                    newBtn.style.opacity = '0.3';
                }
                templateRow.parentNode.appendChild(newRow);

                const firstInput = newRow.querySelector('.cell-input');
                if (firstInput) firstInput.focus();
            }
        } else if (cloneBtn) {
            e.preventDefault();
            const sourceRow = cloneBtn.closest('tr');
            const templateRow = listContainer.querySelector('.new-blank-row');
            if (sourceRow && templateRow) {
                const brand = sourceRow.querySelector('[data-field="brand"] .cell-input')?.value || '';
                const model = sourceRow.querySelector('[data-field="model"] .cell-input')?.value || '';
                const series = sourceRow.querySelector('[data-field="series"] .cell-input')?.value || '';
                const cpu = sourceRow.querySelector('[data-field="cpu"] .cell-input')?.value || '';
                const desc = sourceRow.querySelector('[data-field="description"] .cell-input')?.value || '';
                const notes = sourceRow.querySelector('[data-field="notes"] .cell-input')?.value || '';
                const price = sourceRow.querySelector('[data-field="unit_price"] .cell-input')?.value || '';

                const newRow = templateRow.cloneNode(true);
                newRow.querySelector('[data-field="brand"] .cell-input').value = brand;
                newRow.querySelector('[data-field="model"] .cell-input').value = model;
                newRow.querySelector('[data-field="series"] .cell-input').value = series;
                newRow.querySelector('[data-field="cpu"] .cell-input').value = cpu;
                newRow.querySelector('[data-field="description"] .cell-input').value = desc;
                newRow.querySelector('[data-field="notes"] .cell-input').value = notes;
                newRow.querySelector('[data-field="quantity"] .cell-input').value = '0';
                newRow.querySelector('[data-field="unit_price"] .cell-input').value = price;

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

function focusCell(rows, rowIndex, colIndex) {
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

// Auto-save logic
async function handleCellSave(input) {
    const cell = input.closest('td');
    const row = input.closest('tr');
    if (!cell || !row) return;

    const rowId = row.getAttribute('data-id');
    const field = cell.getAttribute('data-field');
    const val = input.value.trim();

    if (rowId === 'new') {
        const brandVal = row.querySelector('[data-field="brand"] .cell-input').value.trim();
        const modelVal = row.querySelector('[data-field="model"] .cell-input').value.trim();

        if (brandVal !== '' && modelVal !== '') {
            createNewRowFromBlank(row);
        }
        return;
    }

    const metadata = document.getElementById('batch-metadata');
    if (!metadata) return;
    const csrfToken = metadata.getAttribute('data-csrf');

    try {
        const response = await fetch('api/update_order_item_field.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                item_id: rowId,
                field: field,
                value: val
            })
        });

        const result = await response.json();
        if (result.success) {
            const counter = document.getElementById('sidebar-total-qty');
            if (counter && result.new_total !== undefined) {
                counter.textContent = result.new_total;
                counter.classList.add('pulse');
                setTimeout(() => counter.classList.remove('pulse'), 500);
            }
            cell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
            setTimeout(() => {
                cell.style.backgroundColor = '';
            }, 600);
        } else {
            console.error('Save failed:', result.error);
        }
    } catch (err) {
        console.error('Error updating cell field:', err);
    }
}

// Create new row when blank row at bottom is filled
async function createNewRowFromBlank(row) {
    const metadata = document.getElementById('batch-metadata');
    if (!metadata) return;

    const customerId = metadata.getAttribute('data-customer-id');
    const orderId = metadata.getAttribute('data-order-id');
    const csrfToken = metadata.getAttribute('data-csrf');

    const brand = row.querySelector('[data-field="brand"] .cell-input').value.trim();
    const model = row.querySelector('[data-field="model"] .cell-input').value.trim();
    const series = row.querySelector('[data-field="series"] .cell-input').value.trim();
    const cpu = row.querySelector('[data-field="cpu"] .cell-input').value.trim();
    const desc = row.querySelector('[data-field="description"] .cell-input').value.trim();
    const notes = row.querySelector('[data-field="notes"] .cell-input').value.trim();
    const qty = parseFloat(row.querySelector('[data-field="quantity"] .cell-input').value) || 1;
    const price = parseFloat(row.querySelector('[data-field="unit_price"] .cell-input').value) || 0.00;

    const btnIndicator = row.querySelector('.btn-add-row-indicator');
    if (btnIndicator) btnIndicator.textContent = '⏳';

    const formData = new FormData();
    formData.set('csrf_token', csrfToken);
    formData.set('customer_id', customerId);
    formData.set('order_id', orderId);
    formData.set('brand', brand);
    formData.set('model', model);
    formData.set('series', series);
    formData.set('cpu', cpu);
    formData.set('description', desc);
    formData.set('notes', notes);
    formData.set('quantity', qty);
    formData.set('unit_price', price);

    try {
        const response = await fetch('api/add_order_item.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            if (window.IQA_Notify) {
                window.IQA_Notify.success('Row successfully added ✨');
            }

            const activeEl = document.activeElement;
            if (activeEl && activeEl.classList.contains('cell-input')) {
                const cell = activeEl.closest('td');
                if (cell) {
                    const field = cell.getAttribute('data-field');
                    if (field) {
                        sessionStorage.setItem('new_order_restore_field', field);
                        sessionStorage.setItem('new_order_restore_item_id', result.item_id);
                    }
                }
            }

            window.location.reload();
        }
    } catch (err) {
        console.error('Error adding row:', err);
        if (btnIndicator) btnIndicator.textContent = '➕';
    }
}

function initSummarySearch() {
    const searchInput = document.getElementById('summary-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterSummary);
    }
}

function filterSummary() {
    const queryEl = document.getElementById('summary-search');
    const query = queryEl ? queryEl.value.toLowerCase() : '';
    const rows = document.querySelectorAll('.summary-list .summary-row, #summary-list .summary-row');
    rows.forEach(row => {
        if (row.classList.contains('new-blank-row')) return;
        const text = row.getAttribute('data-search') || '';
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

function getDescRank(desc) {
    const d = (desc || '').toLowerCase();
    if (d.includes('untested')) return 1;
    if (d.includes('tested')) return 2;
    if (d.includes('parts')) return 3;
    return 4;
}

function sortSummary() {
    const sortSelect = document.getElementById('summary-sort');
    if (!sortSelect) return;
    const sortBy = sortSelect.value;
    const tbody = document.getElementById('summary-list');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('.summary-row'));

    const blankRow = rows.find(row => row.classList.contains('new-blank-row'));
    const activeRows = rows.filter(row => !row.classList.contains('new-blank-row'));

    activeRows.sort((a, b) => {
        if (sortBy === 'original') {
            return parseInt(a.getAttribute('data-id'), 10) - parseInt(b.getAttribute('data-id'), 10);
        }

        const rankA = getDescRank(a.getAttribute('data-desc'));
        const rankB = getDescRank(b.getAttribute('data-desc'));

        if (rankA !== rankB) {
            return rankA - rankB;
        }

        if (sortBy === 'oldest') {
            return parseInt(a.getAttribute('data-id'), 10) - parseInt(b.getAttribute('data-id'), 10);
        } else if (sortBy === 'newest') {
            return parseInt(b.getAttribute('data-id'), 10) - parseInt(a.getAttribute('data-id'), 10);
        } else if (sortBy === 'qty_desc') {
            return parseInt(b.getAttribute('data-qty'), 10) - parseInt(a.getAttribute('data-qty'), 10);
        } else if (sortBy === 'price_desc') {
            return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        } else if (sortBy === 'default') {
            return parseInt(a.getAttribute('data-id'), 10) - parseInt(b.getAttribute('data-id'), 10);
        }
        return 0;
    });

    activeRows.forEach(row => tbody.appendChild(row));

    if (blankRow) {
        tbody.appendChild(blankRow);
    }
}

async function consolidateOrderRows() {
    if (!confirm("Are you sure you want to consolidate rows with identical values in this order? Duplicate items will be merged and their quantities added together.")) {
        return;
    }

    const meta = document.getElementById('batch-metadata');
    if (!meta) return;

    const customerId = meta.getAttribute('data-customer-id');
    const orderId = meta.getAttribute('data-order-id');
    const csrfToken = meta.getAttribute('data-csrf');

    const btn = document.getElementById('btn-consolidate-spreadsheet');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '⏳ Merging...';
        btn.disabled = true;
    }

    try {
        const response = await fetch('api/consolidate_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                customer_id: customerId,
                order_id: orderId
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
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    } catch (e) {
        console.error(e);
        alert("Network error during consolidation.");
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
}
