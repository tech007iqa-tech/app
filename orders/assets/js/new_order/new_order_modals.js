/**
 * New Order Modals & UI Helper Module
 * Manages the Item Edit modal, batch import tab switching, keyword insertion, and Apple prefix formatting.
 */

let activeImportCustomerId = null;
let activeImportOrderId = null;

function escapeHTML(str) {
    if (!str) return '—';
    return str.toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[m]));
}

function toggleDescriptionKeyword(keyword) {
    const descArea = document.getElementById('description') || document.getElementById('edit-desc');
    if (!descArea) return;

    let val = descArea.value.trim();
    if (val) {
        if (val.endsWith(',')) {
            descArea.value = val + ' ' + keyword;
        } else {
            descArea.value = val + ', ' + keyword;
        }
    } else {
        descArea.value = keyword;
    }
    descArea.focus();
    descArea.dispatchEvent(new Event('input'));
}
window.toggleDescriptionKeyword = toggleDescriptionKeyword;

function openEditModal(item) {
    const editId = document.getElementById('edit-id');
    const editBrand = document.getElementById('edit-brand');
    const editModel = document.getElementById('edit-model');
    const editSeries = document.getElementById('edit-series');
    const editDesc = document.getElementById('edit-desc');
    const editNotes = document.getElementById('edit-notes');
    const editQty = document.getElementById('edit-qty');
    const editPrice = document.getElementById('edit-price');
    const modal = document.getElementById('editModal');

    if (editId) editId.value = item.id;
    if (editBrand) editBrand.value = item.brand;
    if (editModel) editModel.value = item.model;
    if (editSeries) editSeries.value = item.series;

    let cpuSeries = '';
    let cpuGen = '';
    if (item.cpu) {
        const cpuStr = item.cpu.trim();
        const parts = cpuStr.split(/\s+/);
        if (parts.length > 0) {
            if (parts[0].toLowerCase() === 'ryzen' && parts.length > 1) {
                cpuSeries = parts[0] + ' ' + parts[1];
                cpuGen = parts.slice(2).join(' ');
            } else {
                cpuSeries = parts[0];
                cpuGen = parts.slice(1).join(' ');
            }
        }
    }

    const editCpuSeriesEl = document.getElementById('edit-cpu-series');
    const editCpuGenEl = document.getElementById('edit-cpu-gen');
    if (editCpuSeriesEl) {
        const optionExists = Array.from(editCpuSeriesEl.options).some(opt => opt.value === cpuSeries);
        if (optionExists) {
            editCpuSeriesEl.value = cpuSeries;
            if (editCpuGenEl) editCpuGenEl.value = cpuGen;
        } else {
            editCpuSeriesEl.value = '';
            if (editCpuGenEl) editCpuGenEl.value = item.cpu || '';
        }
    }

    if (editDesc) editDesc.value = item.description;
    if (editNotes) editNotes.value = item.notes || '';
    if (editQty) editQty.value = item.quantity;
    if (editPrice) editPrice.value = item.unit_price;

    if (modal) modal.style.display = 'flex';
    if (window.triggerEditModalDatalistSync) {
        window.triggerEditModalDatalistSync();
    }
    location.hash = '#summary-list';
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) modal.style.display = 'none';
}

function openImportModal(customerId, orderId) {
    activeImportCustomerId = customerId;
    activeImportOrderId = orderId;
    const modal = document.getElementById('import-modal');
    const area = document.getElementById('import-paste-area');
    if (modal) {
        modal.style.display = 'flex';
        switchImportTab('clipboard');
        if (area) {
            area.value = '';
            area.focus();
        }
    }
}

function closeImportModal() {
    const modal = document.getElementById('import-modal');
    if (modal) modal.style.display = 'none';
    activeImportCustomerId = null;
    activeImportOrderId = null;
}

function switchImportTab(tab) {
    const isClipboard = tab === 'clipboard';
    const isWarehouse = tab === 'warehouse';
    const isWorkOrder = tab === 'workorder';

    const clipContent = document.getElementById('import-tab-clipboard-content');
    const whContent = document.getElementById('import-tab-warehouse-content');
    const woContent = document.getElementById('import-tab-workorder-content');

    if (clipContent) clipContent.style.display = isClipboard ? 'block' : 'none';
    if (whContent) whContent.style.display = isWarehouse ? 'block' : 'none';
    if (woContent) woContent.style.display = isWorkOrder ? 'block' : 'none';

    const clipBtn = document.getElementById('tab-btn-clipboard');
    const whBtn = document.getElementById('tab-btn-warehouse');
    const woBtn = document.getElementById('tab-btn-workorder');

    [clipBtn, whBtn, woBtn].forEach(btn => {
        if (btn) {
            btn.style.borderBottom = '3px solid transparent';
            btn.style.color = '#64748b';
            btn.style.fontWeight = '700';
        }
    });

    const activeBtn = isClipboard ? clipBtn : (isWarehouse ? whBtn : woBtn);
    if (activeBtn) {
        activeBtn.style.borderBottom = '3px solid var(--accent-color)';
        activeBtn.style.color = 'var(--text-main)';
        activeBtn.style.fontWeight = '800';
    }

    if (isWarehouse) {
        setTimeout(() => {
            const searchField = document.getElementById('wh-import-q');
            if (searchField) {
                searchField.value = '';
                searchField.focus();
                if (typeof searchWarehouseImport === 'function') {
                    searchWarehouseImport();
                }
            }
        }, 50);
    } else if (isWorkOrder) {
        if (window.initWorkOrderImport) {
            window.initWorkOrderImport();
        }
    }
}

// Attach Datalist Sync and Apple UI formatting listeners
document.addEventListener('DOMContentLoaded', () => {
    const editBrand = document.getElementById('edit-brand');
    const editModel = document.getElementById('edit-model');
    const editSeries = document.getElementById('edit-series');
    const editModelDl = document.getElementById('edit-model-options');
    const editSeriesDl = document.getElementById('edit-series-options');

    const updateEditSeriesOptions = () => {
        const selectedBrand = editBrand ? editBrand.value : '';
        const selectedModel = editModel ? editModel.value.trim() : '';
        const inventory = window.IQA_Inventory || {};
        const data = inventory[selectedBrand];

        if (editSeriesDl) editSeriesDl.innerHTML = '';

        if (data) {
            let seriesList = [];
            if (selectedModel && data.modelSeries && data.modelSeries[selectedModel]) {
                seriesList = data.modelSeries[selectedModel];
            } else {
                seriesList = data.series || [];
            }

            const val = editSeries ? editSeries.value.trim().toLowerCase() : '';
            let filtered = seriesList;
            if (val.length >= 1) {
                filtered = seriesList.filter(s => s.toLowerCase().startsWith(val));
            }
            if (editSeriesDl) {
                editSeriesDl.innerHTML = filtered.map(s => `<option value="${s}">`).join('');
            }
        }
    };

    const updateEditModelOptions = () => {
        const selectedBrand = editBrand ? editBrand.value : '';
        const inventory = window.IQA_Inventory || {};
        const data = inventory[selectedBrand];
        if (editModelDl) editModelDl.innerHTML = '';

        if (data && data.models) {
            editModelDl.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
        }
        updateEditSeriesOptions();
    };

    if (editBrand) {
        editBrand.addEventListener('change', updateEditModelOptions);
        editBrand.addEventListener('input', updateEditModelOptions);
    }
    if (editModel) {
        editModel.addEventListener('input', updateEditSeriesOptions);
        editModel.addEventListener('change', updateEditSeriesOptions);
    }
    if (editSeries) {
        editSeries.addEventListener('input', updateEditSeriesOptions);
        editSeries.addEventListener('focus', updateEditSeriesOptions);
    }

    window.triggerEditModalDatalistSync = () => {
        updateEditModelOptions();
    };
});
