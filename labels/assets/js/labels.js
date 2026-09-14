
/**
 * assets/js/labels.js
 * Drives the labels.php warehouse inventory page.
 *
 * Features:
 *  - Filter bar: debounced live search + status dropdown → fetches api/get_labels.php
 *  - Inline Edit: clicking Edit transforms a row into editable input fields
 *  - Delete: confirm prompt → fetch api/delete_label.php → removes row from DOM
 */
'use strict';

// ─── CONFIGURATION & STATE ───────────────────────────────────────────────────

const CONFIG = {
    DEBOUNCE_DELAY: 300,
    API_ENDPOINTS: {
        GET_LABELS: 'api/get_labels.php',
        SEARCH_ITEM: 'api/search_item.php',
        EDIT_LABEL: 'api/edit_label.php',
        DELETE_LABEL: 'api/delete_label.php'
    }
};

const DOM = {
    filterSearch: document.getElementById('filterSearch'),
    filterStatus: document.getElementById('filterStatus'),
    filterMsg: document.getElementById('filterMsg'),
    tbody: document.getElementById('inventoryTableBody'),
    selectAll: document.getElementById('selectAll'),
    bulkBar: document.getElementById('bulkActionBar'),
    selectedCount: document.getElementById('selectedCount')
};

let filterTimer = null;
let selectedIds = new Set();

// ─── FILTER BAR ─────────────────────────────────────────────────────────────

/**
 * Executes the filter search by fetching data from the API.
 */
async function runFilter() {
    const query  = DOM.filterSearch.value.trim();
    const status = DOM.filterStatus ? DOM.filterStatus.value : 'In Warehouse';
    DOM.filterMsg.textContent = 'Searching…';

    try {
        const url = `${CONFIG.API_ENDPOINTS.GET_LABELS}?q=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}`;
        const response = await fetch(url);
        const json = await response.json();

        if (!json.success) {
            DOM.filterMsg.textContent = `Error: ${json.error || 'Unknown'}`;
            return;
        }

        DOM.tbody.innerHTML = '';

        if (json.data.length === 0) {
            DOM.filterMsg.textContent = 'No configurations match your filter.';
            DOM.tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center empty-table-message">
                        No matching label profiles found.
                    </td>
                </tr>`;
        } else {
            DOM.filterMsg.textContent = `${json.data.length} result(s)`;
            const fragment = document.createDocumentFragment();
            json.data.forEach(item => fragment.appendChild(buildRow(item)));
            DOM.tbody.appendChild(fragment);
        }
    } catch (error) {
        console.error('Filter error:', error);
        DOM.filterMsg.textContent = 'Network error.';
    }
}

DOM.filterSearch.addEventListener('input', () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(runFilter, CONFIG.DEBOUNCE_DELAY);
});

if (DOM.filterStatus) {
    DOM.filterStatus.addEventListener('change', runFilter);
}

// ─── ROW BUILDER ─────────────────────────────────────────────────────────────

// ─── ROW BUILDER ─────────────────────────────────────────────────────────────

/**
 * Builds a display <tr> element from an item data object using <template>.
 */
function buildRow(item) {
    const template = document.getElementById('inventoryRowTemplate');
    const clone = document.importNode(template.content, true);
    const tr = clone.querySelector('tr');
    tr.dataset.id = item.id;

    const F = window.HW_FIELDS;
    const brand = item[F.BRAND] || '';
    const model = item[F.MODEL] || '';
    const series = item[F.SERIES] || '';
    const sn = item[F.SERIAL_NUMBER] || '';
    const description = item[F.DESCRIPTION] || 'Untested';
    const status = item[F.STATUS] || 'In Warehouse';
    const location = item[F.LOCATION] || 'Unassigned';

    // Link & Name
    const link = tr.querySelector('.tpl-link');
    link.href = `hardware_view.php?id=${item.id}`;
    link.textContent = `${brand} ${model}`;
    if (description === 'Refurbished') link.classList.add('text-accent');

    tr.querySelector('.tpl-series').textContent = series;

    // Serial Number
    const snText   = tr.querySelector('.tpl-sn-text');
    const snEmpty  = tr.querySelector('.tpl-sn-empty');
    if (sn) {
        snText.textContent = `S/N: ${sn}`;
        snText.style.display = 'inline';
        snEmpty.style.display = 'none';
    } else {
        snText.style.display = 'none';
        snEmpty.style.display = 'inline';
    }

    // CPU
    tr.querySelector('.tpl-cpu-gen').textContent = item[F.CPU_GEN] || '—';
    tr.querySelector('.tpl-cpu-specs').textContent = item[F.CPU_SPECS] || '';

    // RAM / Storage
    tr.querySelector('.tpl-ram').textContent = item[F.RAM] || 'None';
    tr.querySelector('.tpl-storage').textContent = item[F.STORAGE] || 'None';

    // Location / Buyer (Handle Sold logic)
    const locBox = tr.querySelector('.tpl-location-box');
    const isSold = (status === 'Sold');

    if (isSold && item.buyer_name) {
        locBox.innerHTML = `
            <div class="text-xs text-secondary" style="margin-bottom:2px;">Sold to:</div>
            <div class="font-bold text-accent" style="font-size:0.85rem;">${esc(item.buyer_name)}</div>
            <div class="text-xs" style="color:var(--text-secondary); opacity:0.8;">${esc(item.buyer_order_num)}</div>
        `;
    } else {
        tr.querySelector('.tpl-location').textContent = location;
    }

    // Status Badge
    const badge = tr.querySelector('.tpl-badge');
    badge.textContent = description;

    if (description === 'For Parts') badge.classList.add('status-for-parts');
    else if (description === 'Refurbished') badge.classList.add('status-refurbished');
    else badge.classList.add('status-untested');

    if (isSold) {
        tr.querySelector('.tpl-sold-badge').style.display = 'block';
    }

    // Date
    tr.querySelector('.tpl-added').textContent = fmtDate(item.created_at);

    // Buttons
    const launchBtn = tr.querySelector('.launch-odt-btn');
    if (launchBtn) {
        launchBtn.dataset.id = item.id;
        launchBtn.dataset.brand = brand;
        launchBtn.dataset.model = model;
    }

    const editBtn = tr.querySelector('.edit-btn');
    if (editBtn) editBtn.dataset.id = item.id;

    const delBtn = tr.querySelector('.delete-btn');
    if (delBtn) {
        delBtn.dataset.id = item.id;
        delBtn.dataset.label = `${brand} ${model}`;
    }

    // Selection State & Highlighting
    const checkbox = tr.querySelector('.row-select');
    if (checkbox) {
        const isChecked = selectedIds.has(item.id.toString());
        checkbox.checked = isChecked;
        if (isChecked) tr.classList.add('selected-row');
    }

    return tr;
}

// ─── EVENT DELEGATION ────────────────────────────────────────────────────────

/**
 * Uses event delegation to handle clicks on action buttons within the table.
 */
DOM.tbody.addEventListener('click', (e) => {
    const target = e.target;
    const btn = target.closest('.btn');
    if (!btn) return;

    const id = btn.dataset.id;
    const tr = btn.closest('tr');

    if (btn.classList.contains('edit-btn')) {
        onEditClick(id, tr);
    } else if (btn.classList.contains('delete-btn')) {
        onDeleteClick(id, btn.dataset.label);
    } else if (btn.classList.contains('launch-odt-btn')) {
        onOpenClick(btn);
    } else if (btn.classList.contains('save-edit-btn')) {
        saveEdit(tr, id);
    } else if (btn.classList.contains('cancel-edit-btn')) {
        cancelEdit(tr);
    }
});

// ─── EDIT ────────────────────────────────────────────────────────────────────

/**
 * Handles the edit button click by fetching fresh data and opening the edit row.
 */
async function onEditClick(id, tr) {
    try {
        const response = await fetch(`${CONFIG.API_ENDPOINTS.SEARCH_ITEM}?id=${id}`);
        const json = await response.json();

        if (!json.success) {
            alert(`Could not load item: ${json.error}`);
            return;
        }

        const itemToEdit = json.data.results[0];
        openEditRow(tr, itemToEdit);
    } catch (error) {
        console.error('Edit load error:', error);
        alert('Network error while loading item data.');
    }
}

/**
 * Transforms a display row into an editable form using <template>.
 */
function openEditRow(tr, item) {
    tr.dataset.originalHtml = tr.innerHTML;
    const F = window.HW_FIELDS;

    const template = document.getElementById('editRowTemplate');
    const clone = document.importNode(template.content, true);
    const editTr = clone.querySelector('tr');

    // Populate Fields
    editTr.querySelector('input[name="id"]').value = item.id;
    editTr.querySelectorAll('.edit-field').forEach(field => {
        const val = item[field.name];
        if (field.tagName === 'SELECT') {
            field.value = val || 'Untested';
        } else {
            field.value = val || '';
        }
    });

    editTr.querySelector('.tpl-edit-added').textContent = fmtDate(item.created_at);
    editTr.querySelector('.save-edit-btn').dataset.id = item.id;

    // Add hidden fields for all mapping keys not present in the visible row inputs
    const hiddenContainer = editTr.querySelector('.tpl-edit-cell-main');
    const existingInputs = new Set([...editTr.querySelectorAll('input, select')].map(i => i.name));

    Object.values(F).forEach(dbField => {
        if (!existingInputs.has(dbField)) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = dbField;
            hidden.value = item[dbField] ?? '';
            hiddenContainer.appendChild(hidden);
        }
    });

    // Swap Row
    tr.innerHTML = '';
    while (editTr.firstChild) {
        tr.appendChild(editTr.firstChild);
    }
    tr.classList.add('edit-mode-row');
}

/**
 * Cancels the edit mode and restores the original row content.
 */
function cancelEdit(tr) {
    if (tr.dataset.originalHtml) {
        tr.innerHTML = tr.dataset.originalHtml;
        tr.classList.remove('edit-mode-row');
        delete tr.dataset.originalHtml;
    }
}

/**
 * Saves the edited data by sending it to the API.
 */
async function saveEdit(tr, id) {
    const saveBtn = tr.querySelector('.save-edit-btn');
    const originalBtnText = saveBtn.textContent;

    saveBtn.disabled = true;
    saveBtn.textContent = '⏳…';

    const formData = new FormData();
    formData.append('id', id);

    // Collect all inputs and selects (including hidden ones)
    tr.querySelectorAll('input, select').forEach(field => {
        if (field.name) formData.append(field.name, field.value);
    });

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.EDIT_LABEL, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Save failed: ${json.error || 'Unknown error'}`);
            saveBtn.disabled = false;
            saveBtn.textContent = originalBtnText;
            return;
        }

        const newTr = buildRow(json.data.item);
        tr.replaceWith(newTr);
    } catch (error) {
        console.error('Save error:', error);
        alert('Network error — changes were not saved.');
        saveBtn.disabled = false;
        saveBtn.textContent = originalBtnText;
    }
}


// ─── DELETE ──────────────────────────────────────────────────────────────────

/**
 * Handles the delete button click with a confirmation prompt.
 */
async function onDeleteClick(id, label) {
    if (!confirm(`Delete "${label}" (#${pad(id, 5)}) from the warehouse?\n\nThis cannot be undone.`)) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.DELETE_LABEL, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Delete failed: ${json.error || 'Unknown error'}`);
            return;
        }

        const tr = document.querySelector(`tr[data-id="${id}"]`);
        if (tr) {
            tr.style.transition = 'opacity 0.3s, transform 0.3s';
            tr.style.opacity = '0';
            tr.style.transform = 'translateX(20px)';
            setTimeout(() => tr.remove(), 300);
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Network error — item was not deleted.');
    }
}

// ─── REPRINT & OPEN ──────────────────────────────────────────────────────────

function onReprintClick(id) {
    if (window.openPrintConfig) {
        window.openPrintConfig(id);
    } else {
        alert('Print engine not loaded.');
    }
}

async function onOpenClick(btn) {
    const { id, brand, model } = btn.dataset;
    if (typeof flashOpenLabel === 'function') {
        await flashOpenLabel(id, brand, model, btn);
    } else {
        console.error('flashOpenLabel function not found.');
    }
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ─── INITIALIZATION (Phase 2 Hydration) ──────────────────────────────────────

(function init() {
    if (window.INITIAL_INVENTORY && Array.isArray(window.INITIAL_INVENTORY)) {
        const data = window.INITIAL_INVENTORY;
        if (data.length > 0) {
            DOM.tbody.innerHTML = '';
            const fragment = document.createDocumentFragment();
            data.forEach(item => fragment.appendChild(buildRow(item)));
            DOM.tbody.appendChild(fragment);
            DOM.filterMsg.textContent = `${data.length} items loaded`;
        } else {
            DOM.tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center empty-table-message" style="padding: 50px;">
                        No items found. <a href="new_label.php" class="btn btn-primary" style="margin-top:10px; display:inline-block;">Print your first label →</a>
                    </td>
                </tr>`;
            DOM.filterMsg.textContent = 'Warehouse empty.';
        }
    }
})();

// ─── BULK ACTIONS LOGIC ──────────────────────────────────────────────────────

function updateBulkBar() {
    const count = selectedIds.size;
    DOM.selectedCount.textContent = count;
    DOM.bulkBar.style.display = count > 0 ? 'flex' : 'none';
}

let lastLabelsChecked = null;

DOM.selectAll.addEventListener('change', (e) => {
    const isChecked = e.target.checked;
    const checkboxes = DOM.tbody.querySelectorAll('.row-select');
    checkboxes.forEach(cb => {
        const tr = cb.closest('tr');
        if (tr.style.display !== 'none') {
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

DOM.tbody.addEventListener('click', (e) => {
    if (e.target.classList.contains('row-select')) {
        const currentCb = e.target;
        const checkboxes = Array.from(DOM.tbody.querySelectorAll('.row-select')).filter(cb => cb.closest('tr').style.display !== 'none');

        if (e.shiftKey && lastLabelsChecked && lastLabelsChecked !== currentCb) {
            let start = checkboxes.indexOf(currentCb);
            let end = checkboxes.indexOf(lastLabelsChecked);

            if (start > -1 && end > -1) {
                const range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);
                const isChecked = currentCb.checked;

                range.forEach(cb => {
                    cb.checked = isChecked;
                    const tr = cb.closest('tr');
                    const id = tr.dataset.id;
                    if (isChecked) {
                        selectedIds.add(id);
                        tr.classList.add('selected-row');
                    } else {
                        selectedIds.delete(id);
                        tr.classList.remove('selected-row');
                    }
                });
            }
        } else {
            const tr = currentCb.closest('tr');
            const id = tr.dataset.id;
            if (currentCb.checked) {
                selectedIds.add(id);
                tr.classList.add('selected-row');
            } else {
                selectedIds.delete(id);
                tr.classList.remove('selected-row');
                DOM.selectAll.checked = false;
            }
        }

        lastLabelsChecked = currentCb;
        updateBulkBar();
    }
});

document.getElementById('cancelBulkBtn').addEventListener('click', () => {
    selectedIds.clear();
    DOM.selectAll.checked = false;
    DOM.tbody.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('selected-row');
    });
    updateBulkBar();
});

document.getElementById('applyBulkBtn').addEventListener('click', async () => {
    const status = document.getElementById('bulkStatus').value;
    const location = document.getElementById('bulkLocation').value.trim();

    if (!status && !location) {
        alert("Please select a status or enter a location to apply.");
        return;
    }

    if (!confirm(`Apply changes to ${selectedIds.size} items?`)) return;

    const btn = document.getElementById('applyBulkBtn');
    btn.disabled = true;
    btn.textContent = '⌛ Applying...';

    try {
        const response = await fetch('api/bulk_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ids: Array.from(selectedIds),
                status: status,
                location: location,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        });

        const json = await response.json();
        if (json.success) {
            IQA_Notify.success(`Successfully updated ${selectedIds.size} items!`);
            selectedIds.clear();
            DOM.selectAll.checked = false;
            updateBulkBar();
            runFilter(); // Refresh the list
        } else {
            IQA_Notify.error(`Error: ${json.error}`);
        }
    } catch (err) {
        IQA_Notify.error("Network error during bulk update.");
    } finally {
        btn.disabled = false;
        btn.textContent = 'Apply to All';
    }
});

function pad(n, len) {
    return String(n).padStart(len, '0');
}

function fmtDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
