/**
 * labels/assets/js/labels.js
 * Modular Inventory Controller: Live Search, Filter Chips, Inline Edit, Delete Modal, Bulk Actions & CSV Export.
 */
'use strict';

let currentInventory = [];
let currentFilterStatus = 'In Warehouse';
let selectedIds = new Set();
let deleteCandidateId = null;
let filterDebounceTimer = null;

// ── 1. ROW BUILDER ───────────────────────────────────────────────────────────
function buildInventoryRow(item) {
    const template = document.getElementById('inventoryRowTemplate');
    const clone = document.importNode(template.content, true);
    const tr = clone.querySelector('tr');
    tr.dataset.id = item.id;

    // Checkbox
    const chk = tr.querySelector('.row-select');
    if (chk) {
        chk.checked = selectedIds.has(String(item.id));
        chk.addEventListener('change', () => {
            if (chk.checked) selectedIds.add(String(item.id));
            else selectedIds.delete(String(item.id));
            updateBulkActionBar();
        });
    }

    // Brand Model & Series
    const brandModel = `${item.brand || ''} ${item.model || ''}`.trim();
    const link = tr.querySelector('.tpl-link');
    if (link) {
        link.textContent = brandModel || 'Hardware Unit';
        link.href = `hardware_view.php?id=${item.id}`;
    }

    const seriesEl = tr.querySelector('.tpl-series');
    if (seriesEl) seriesEl.textContent = item.series || '—';

    const snEl = tr.querySelector('.tpl-sn-text');
    if (snEl) snEl.textContent = item.serial_number || 'No S/N';

    // CPU
    const cpuSpecsEl = tr.querySelector('.tpl-cpu-specs');
    if (cpuSpecsEl) cpuSpecsEl.textContent = item.cpu_specs || item.cpu_gen || '—';

    const cpuGenEl = tr.querySelector('.tpl-cpu-gen');
    if (cpuGenEl) cpuGenEl.textContent = item.cpu_gen || '';

    // RAM & Storage
    const ramEl = tr.querySelector('.tpl-ram');
    if (ramEl) ramEl.textContent = item.ram || 'None';

    const storageEl = tr.querySelector('.tpl-storage');
    if (storageEl) storageEl.textContent = item.storage || 'None';

    const battEl = tr.querySelector('.tpl-battery');
    if (battEl) {
        const b = item.battery;
        battEl.textContent = 'Batt: ' + (b == 1 ? 'YES' : (b == '0' ? 'NO' : '—'));
    }

    // Location
    const locEl = tr.querySelector('.tpl-location');
    if (locEl) locEl.textContent = '📍 ' + (item.warehouse_location || 'Unassigned');

    // Condition Badge
    const badgeEl = tr.querySelector('.tpl-badge');
    const cond = item.description || 'Untested';
    if (badgeEl) {
        badgeEl.textContent = cond.toUpperCase();
        badgeEl.className = 'badge ' + (cond === 'Refurbished' ? 'badge-success' : (cond === 'For Parts' ? 'badge-danger' : 'badge-warning'));
    }

    const statusSubEl = tr.querySelector('.tpl-status-sub');
    if (statusSubEl) statusSubEl.textContent = item.status || 'In Warehouse';

    // Added Date
    const addedEl = tr.querySelector('.tpl-added');
    if (addedEl) {
        if (item.created_at) {
            const d = new Date(item.created_at);
            addedEl.textContent = isNaN(d.getTime()) ? item.created_at.substring(0, 10) : d.toLocaleDateString();
        } else {
            addedEl.textContent = '—';
        }
    }

    // Actions
    const btnPrint = tr.querySelector('.tpl-btn-print');
    if (btnPrint) {
        btnPrint.addEventListener('click', () => printThermalLabel(item.id));
    }

    const btnView = tr.querySelector('.tpl-btn-view');
    if (btnView) {
        btnView.addEventListener('click', () => quickViewHardware(item.id));
    }

    const btnEdit = tr.querySelector('.tpl-btn-edit');
    if (btnEdit) {
        btnEdit.addEventListener('click', () => openInlineEditRow(item.id, tr));
    }

    const btnDel = tr.querySelector('.tpl-btn-del');
    if (btnDel) {
        btnDel.addEventListener('click', () => openDeleteModal(item.id, brandModel));
    }

    return tr;
}

// ── 2. TABLE HYDRATION & RENDERING ───────────────────────────────────────────
function renderInventoryTable(items) {
    const tbody = document.getElementById('inventoryTableBody');
    const counter = document.getElementById('filterMsg');
    if (!tbody) return;

    currentInventory = items;
    tbody.innerHTML = '';

    if (counter) {
        counter.textContent = `Showing ${items.length} item(s)`;
    }

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align:center; padding: 40px; color: var(--text-muted); font-style: italic;">
                    No hardware units matched your filter.
                </td>
            </tr>`;
        return;
    }

    const fragment = document.createDocumentFragment();
    items.forEach(it => fragment.appendChild(buildInventoryRow(it)));
    tbody.appendChild(fragment);
}

// ── 3. FILTERING & SEARCH ENGINE ─────────────────────────────────────────────
async function executeInventoryFilter() {
    const searchInput = document.getElementById('filterSearch');
    const query = searchInput ? searchInput.value.trim() : '';
    const counter = document.getElementById('filterMsg');

    if (counter) counter.textContent = 'Searching records...';

    try {
        const url = `api/get_labels.php?q=${encodeURIComponent(query)}&status=${encodeURIComponent(currentFilterStatus)}`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.success && Array.isArray(json.data)) {
            renderInventoryTable(json.data);
        } else {
            Toast.error("Failed to load inventory filter: " + (json.error || ''));
        }
    } catch (err) {
        console.error(err);
        Toast.error("Network error fetching inventory.");
    }
}

// ── 4. INLINE ROW EDITING ───────────────────────────────────────────────────
function openInlineEditRow(id, tr) {
    const item = currentInventory.find(it => String(it.id) === String(id));
    if (!item) return;

    const tpl = document.getElementById('editRowTemplate');
    const clone = document.importNode(tpl.content, true);
    const editTr = clone.querySelector('tr');
    editTr.dataset.id = id;

    // Fill fields
    const F = window.HW_FIELDS || {};
    editTr.querySelector('input[name="id"]').value = id;
    editTr.querySelector(`input[name="${F.BRAND || 'brand'}"]`).value = item.brand || '';
    editTr.querySelector(`input[name="${F.MODEL || 'model'}"]`).value = item.model || '';
    editTr.querySelector(`input[name="${F.SERIES || 'series'}"]`).value = item.series || '';
    editTr.querySelector(`input[name="${F.SERIAL_NUMBER || 'serial_number'}"]`).value = item.serial_number || '';
    editTr.querySelector(`input[name="${F.CPU_SPECS || 'cpu_specs'}"]`).value = item.cpu_specs || '';
    editTr.querySelector(`input[name="${F.CPU_GEN || 'cpu_gen'}"]`).value = item.cpu_gen || '';
    editTr.querySelector(`input[name="${F.RAM || 'ram'}"]`).value = item.ram || '';
    editTr.querySelector(`input[name="${F.STORAGE || 'storage'}"]`).value = item.storage || '';
    editTr.querySelector(`input[name="${F.LOCATION || 'warehouse_location'}"]`).value = item.warehouse_location || '';

    const descSel = editTr.querySelector(`select[name="${F.DESCRIPTION || 'description'}"]`);
    if (descSel && item.description) descSel.value = item.description;

    // Save Button
    const saveBtn = editTr.querySelector('.save-edit-btn');
    saveBtn.addEventListener('click', async () => {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const fd = new FormData();
        editTr.querySelectorAll('.edit-field, input[name="id"]').forEach(input => {
            fd.append(input.name, input.value);
        });

        try {
            const res = await fetch('api/edit_label.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                Toast.success("Updated successfully!");
                // Update local model
                Object.assign(item, {
                    brand: fd.get(F.BRAND || 'brand'),
                    model: fd.get(F.MODEL || 'model'),
                    series: fd.get(F.SERIES || 'series'),
                    serial_number: fd.get(F.SERIAL_NUMBER || 'serial_number'),
                    cpu_specs: fd.get(F.CPU_SPECS || 'cpu_specs'),
                    cpu_gen: fd.get(F.CPU_GEN || 'cpu_gen'),
                    ram: fd.get(F.RAM || 'ram'),
                    storage: fd.get(F.STORAGE || 'storage'),
                    warehouse_location: fd.get(F.LOCATION || 'warehouse_location'),
                    description: fd.get(F.DESCRIPTION || 'description')
                });
                const newRow = buildInventoryRow(item);
                editTr.replaceWith(newRow);
            } else {
                Toast.error("Update failed: " + (json.error || ''));
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save';
            }
        } catch (err) {
            Toast.error("Network error saving changes.");
            saveBtn.disabled = false;
            saveBtn.textContent = '💾 Save';
        }
    });

    // Cancel Button
    const cancelBtn = editTr.querySelector('.cancel-edit-btn');
    cancelBtn.addEventListener('click', () => {
        editTr.replaceWith(tr);
    });

    tr.replaceWith(editTr);
}

// ── 5. DELETE MODAL & CONFIRMATION ──────────────────────────────────────────
function openDeleteModal(id, label) {
    deleteCandidateId = id;
    const modal = document.getElementById('deleteModal');
    const text = document.getElementById('deleteConfirmText');
    if (text) {
        text.innerHTML = `Are you sure you want to delete <strong>${label}</strong> (#${String(id).padStart(5, '0')}) from inventory?`;
    }
    if (modal) modal.style.display = 'flex';
}

function closeDeleteModal() {
    deleteCandidateId = null;
    const modal = document.getElementById('deleteModal');
    if (modal) modal.style.display = 'none';
}

// ── 6. BULK ACTIONS & SELECTION ─────────────────────────────────────────────
function updateBulkActionBar() {
    const bar = document.getElementById('bulkActionBar');
    const countSpan = document.getElementById('selectedCount');
    const selectAll = document.getElementById('selectAll');

    if (countSpan) countSpan.textContent = selectedIds.size;

    if (bar) {
        bar.style.display = selectedIds.size > 0 ? 'flex' : 'none';
    }

    if (selectAll) {
        const visibleCheckboxes = document.querySelectorAll('#inventoryTableBody .row-select');
        selectAll.checked = visibleCheckboxes.length > 0 && Array.from(visibleCheckboxes).every(c => c.checked);
    }
}

// ── 7. CSV EXPORT ────────────────────────────────────────────────────────────
function exportInventoryToCSV() {
    if (!currentInventory || currentInventory.length === 0) {
        Toast.warning("No records to export.");
        return;
    }

    const headers = [
        "Item ID", "Brand", "Model", "Series", "CPU Specs", "CPU Gen", "Cores",
        "RAM", "Storage", "Location", "Condition", "Status", "Serial Number",
        "GPU", "Battery", "OS", "Created At"
    ];

    const escapeCsv = (str) => {
        if (str === null || str === undefined) return '""';
        const s = String(str).replace(/"/g, '""');
        return `"${s}"`;
    };

    const rows = [headers.join(',')];

    currentInventory.forEach(it => {
        const line = [
            it.id,
            escapeCsv(it.brand),
            escapeCsv(it.model),
            escapeCsv(it.series),
            escapeCsv(it.cpu_specs),
            escapeCsv(it.cpu_gen),
            escapeCsv(it.cpu_cores),
            escapeCsv(it.ram),
            escapeCsv(it.storage),
            escapeCsv(it.warehouse_location),
            escapeCsv(it.description),
            escapeCsv(it.status),
            escapeCsv(it.serial_number),
            escapeCsv(it.gpu),
            it.battery == 1 ? "Yes" : (it.battery == '0' ? "No" : ""),
            escapeCsv(it.os_version),
            escapeCsv(it.created_at)
        ];
        rows.push(line.join(','));
    });

    const csvContent = rows.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const timestamp = new Date().toISOString().substring(0, 10);
    link.href = url;
    link.download = `Warehouse_Inventory_${timestamp}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    Toast.success(`Exported ${currentInventory.length} items to CSV`);
}

// ── 8. DOM INITIALIZATION ───────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    // 1. Initial Table Render from injected data
    if (window.INITIAL_INVENTORY && Array.isArray(window.INITIAL_INVENTORY)) {
        renderInventoryTable(window.INITIAL_INVENTORY);
    }

    // 2. Filter Pills Click
    document.querySelectorAll('.filter-status-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-status-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilterStatus = btn.dataset.status;
            executeInventoryFilter();
        });
    });

    // 3. Search Input Debouncing
    const searchInput = document.getElementById('filterSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(filterDebounceTimer);
            filterDebounceTimer = setTimeout(executeInventoryFilter, 280);
        });
    }

    const clearBtn = document.getElementById('clearFilterBtn');
    if (clearBtn && searchInput) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            executeInventoryFilter();
            searchInput.focus();
        });
    }

    // 4. Select All Checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            const isChecked = selectAll.checked;
            document.querySelectorAll('#inventoryTableBody .row-select').forEach(chk => {
                chk.checked = isChecked;
                const tr = chk.closest('tr');
                if (tr && tr.dataset.id) {
                    if (isChecked) selectedIds.add(String(tr.dataset.id));
                    else selectedIds.delete(String(tr.dataset.id));
                }
            });
            updateBulkActionBar();
        });
    }

    // 5. Bulk Actions Apply
    const applyBulkBtn = document.getElementById('applyBulkBtn');
    if (applyBulkBtn) {
        applyBulkBtn.addEventListener('click', async () => {
            if (selectedIds.size === 0) return;

            const bulkStatus = document.getElementById('bulkStatus').value;
            const bulkLoc = document.getElementById('bulkLocation').value.trim();

            if (!bulkStatus && !bulkLoc) {
                Toast.warning("Please choose a status or location to apply.");
                return;
            }

            const csrfToken = document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '';

            applyBulkBtn.disabled = true;
            applyBulkBtn.textContent = 'Applying...';

            try {
                const res = await fetch('api/bulk_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        ids: Array.from(selectedIds),
                        status: bulkStatus || null,
                        location: bulkLoc || null
                    })
                });
                const json = await res.json();

                if (json.success) {
                    Toast.success(`Updated ${selectedIds.size} items!`);
                    selectedIds.clear();
                    updateBulkActionBar();
                    executeInventoryFilter();
                } else {
                    Toast.error("Bulk update failed: " + (json.error || ''));
                }
            } catch (err) {
                Toast.error("Network error performing bulk update.");
            } finally {
                applyBulkBtn.disabled = false;
                applyBulkBtn.textContent = 'Apply Changes';
            }
        });
    }

    // Cancel Bulk
    const cancelBulkBtn = document.getElementById('cancelBulkBtn');
    if (cancelBulkBtn) {
        cancelBulkBtn.addEventListener('click', () => {
            selectedIds.clear();
            document.querySelectorAll('#inventoryTableBody .row-select').forEach(c => c.checked = false);
            updateBulkActionBar();
        });
    }

    // 6. Delete Confirmation
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            if (!deleteCandidateId) return;

            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            const fd = new FormData();
            fd.append('id', deleteCandidateId);

            try {
                const res = await fetch('api/delete_label.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    Toast.success("Item deleted from inventory.");
                    const row = document.querySelector(`tr[data-id="${deleteCandidateId}"]`);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.95)';
                        setTimeout(() => row.remove(), 250);
                    }
                    currentInventory = currentInventory.filter(it => String(it.id) !== String(deleteCandidateId));
                    selectedIds.delete(String(deleteCandidateId));
                    updateBulkActionBar();
                    closeDeleteModal();
                } else {
                    Toast.error("Delete failed: " + (json.error || ''));
                }
            } catch (err) {
                Toast.error("Network error deleting item.");
            } finally {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Yes, Delete Item';
            }
        });
    }

    // 7. CSV Export Button
    const exportBtn = document.getElementById('btnExportCSV');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportInventoryToCSV);
    }
});
