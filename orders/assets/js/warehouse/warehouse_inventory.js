/**
 * Warehouse Inventory Sub-System Controller (warehouse_inventory.js)
 * Coordinates modal dialogs, Smart Inferred Pricing lookup, Rapid Intake,
 * Shelf Stock Depletion, and Full Location Reconciliation Sync.
 */

let __inferredPriceValue = 0.00;
let __inferPriceTimeout = null;

function openInventoryModal(tab = 'intake') {
    const modal = document.getElementById('warehouse-inventory-modal');
    if (modal) {
        modal.style.display = 'flex';
        switchInventoryTab(tab);
        
        // Auto-focus first input on intake tab
        if (tab === 'intake') {
            const brandInput = document.getElementById('intake-brand');
            if (brandInput) setTimeout(() => brandInput.focus(), 50);
        }
    }
}

function closeInventoryModal() {
    const modal = document.getElementById('warehouse-inventory-modal');
    if (modal) {
        modal.style.display = 'none';
        const intakeStatus = document.getElementById('quick-intake-status');
        if (intakeStatus) intakeStatus.style.display = 'none';
        const depleteStatus = document.getElementById('modal-deplete-status');
        if (depleteStatus) depleteStatus.style.display = 'none';
    }
}

function switchInventoryTab(tabName) {
    const intakeBtn = document.getElementById('tab-btn-intake');
    const depleteBtn = document.getElementById('tab-btn-deplete');
    const intakePane = document.getElementById('inv-tab-pane-intake');
    const depletePane = document.getElementById('inv-tab-pane-deplete');

    if (!intakePane || !depletePane) return;

    if (tabName === 'intake') {
        intakePane.style.display = 'block';
        depletePane.style.display = 'none';
        if (intakeBtn) {
            intakeBtn.style.borderBottom = '3px solid #facc15';
            intakeBtn.style.fontWeight = '800';
            intakeBtn.style.color = 'var(--text-main, #0f172a)';
        }
        if (depleteBtn) {
            depleteBtn.style.borderBottom = '3px solid transparent';
            depleteBtn.style.fontWeight = '700';
            depleteBtn.style.color = 'var(--text-secondary, #64748b)';
        }
    } else {
        intakePane.style.display = 'none';
        depletePane.style.display = 'block';
        if (depleteBtn) {
            depleteBtn.style.borderBottom = '3px solid #facc15';
            depleteBtn.style.fontWeight = '800';
            depleteBtn.style.color = 'var(--text-main, #0f172a)';
        }
        if (intakeBtn) {
            intakeBtn.style.borderBottom = '3px solid transparent';
            intakeBtn.style.fontWeight = '700';
            intakeBtn.style.color = 'var(--text-secondary, #64748b)';
        }
        const filterIn = document.getElementById('modal-deplete-filter');
        if (filterIn) setTimeout(() => filterIn.focus(), 50);
        recalculateReconcileStats();
    }
}

// Smart Sold Price Inference: Debounce and query API
function debounceInferPrice() {
    clearTimeout(__inferPriceTimeout);
    __inferPriceTimeout = setTimeout(fetchInferredPrice, 250);
}

async function fetchInferredPrice() {
    const brandIn = document.getElementById('intake-brand');
    const modelIn = document.getElementById('intake-model');
    const cpuIn = document.getElementById('intake-cpu');
    const priceIn = document.getElementById('intake-price');

    const brand = brandIn ? brandIn.value.trim() : '';
    const model = modelIn ? modelIn.value.trim() : '';
    const cpu = cpuIn ? cpuIn.value.trim() : '';

    if (brand.length < 2 && model.length < 2 && cpu.length < 2) {
        hideSmartPriceBanner();
        return;
    }

    try {
        const url = new URL('api/infer_price.php', window.location.href);
        url.searchParams.set('brand', brand);
        url.searchParams.set('model', model);
        url.searchParams.set('cpu', cpu);
        
        const res = await fetch(url.toString());
        const data = await res.json().catch(() => null);

        if (data && data.success && data.inferred_price) {
            __inferredPriceValue = parseFloat(data.inferred_price);
            showSmartPriceBanner(data);

            // Auto-fill price if field is 0 or untouched
            if (priceIn && (parseFloat(priceIn.value || '0') === 0 || priceIn.dataset.autoFilled === 'true')) {
                priceIn.value = __inferredPriceValue.toFixed(2);
                priceIn.dataset.autoFilled = 'true';
            }
        } else {
            hideSmartPriceBanner();
        }
    } catch (err) {
        console.error("Smart Price Inference Error:", err);
    }
}

function showSmartPriceBanner(data) {
    const banner = document.getElementById('smart-price-banner');
    const summary = document.getElementById('smart-price-summary');
    const detail = document.getElementById('smart-price-detail');
    const valSpan = document.getElementById('smart-price-val');
    const headerBadge = document.getElementById('smart-price-badge-header');

    if (!banner) return;

    banner.style.display = 'flex';
    if (headerBadge) headerBadge.style.display = 'inline-block';

    const priceFormatted = `$${__inferredPriceValue.toFixed(2)}`;
    if (summary) summary.innerHTML = `💡 Inferred Sold Price: <strong>${priceFormatted}</strong>`;
    if (detail) detail.textContent = data.summary || 'Based on matching past sales database.';
    if (valSpan) valSpan.textContent = __inferredPriceValue.toFixed(2);
}

function hideSmartPriceBanner() {
    const banner = document.getElementById('smart-price-banner');
    const headerBadge = document.getElementById('smart-price-badge-header');
    if (banner) banner.style.display = 'none';
    if (headerBadge) headerBadge.style.display = 'none';
}

function applyInferredPrice() {
    const priceIn = document.getElementById('intake-price');
    if (priceIn && __inferredPriceValue > 0) {
        priceIn.value = __inferredPriceValue.toFixed(2);
        priceIn.focus();
    }
}

// Quick Inbound Intake AJAX Submission
async function submitQuickIntakeAjax(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btn-quick-intake-submit');
    const statusBox = document.getElementById('quick-intake-status');
    const formData = new FormData(form);

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>⏳</span> Committing...';
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const resData = await response.json().catch(() => null);

        if (response.ok && (!resData || resData.success !== false)) {
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#166534';
                statusBox.style.border = '1px solid #86efac';
                statusBox.innerHTML = `✅ Successfully committed ${formData.get('quantity')}x <strong>${escapeHtml(formData.get('brand'))} ${escapeHtml(formData.get('model'))}</strong> to shelf!`;
            }

            // Reset form fields except brand & location
            const preservedBrand = formData.get('brand');
            form.reset();
            hideSmartPriceBanner();

            const brandIn = document.getElementById('intake-brand');
            if (brandIn) {
                brandIn.value = preservedBrand;
                brandIn.focus();
            }

            if (typeof Notifications !== 'undefined' && Notifications.success) {
                Notifications.success('Inventory item committed to shelf.');
            } else if (typeof IQA_Notify !== 'undefined' && IQA_Notify.success) {
                IQA_Notify.success('Inventory item committed to shelf.');
            }

            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                window.AppSync.sync('inventory-list', true);
            }
            if (typeof updateTotalQuantityHeader === 'function') {
                updateTotalQuantityHeader();
            }

            setTimeout(() => {
                if (statusBox) {
                    statusBox.style.transition = 'opacity 0.4s';
                    statusBox.style.opacity = '0';
                    setTimeout(() => {
                        statusBox.style.display = 'none';
                        statusBox.style.opacity = '1';
                    }, 400);
                }
            }, 3000);
        } else {
            const err = resData && resData.message ? resData.message : 'Failed to commit item.';
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#991b1b';
                statusBox.style.border = '1px solid #fca5a5';
                statusBox.innerHTML = `❌ Error: ${escapeHtml(err)}`;
            }
        }
    } catch (err) {
        console.error("Intake Error:", err);
        if (statusBox) {
            statusBox.style.display = 'block';
            statusBox.style.background = '#fee2e2';
            statusBox.style.color = '#991b1b';
            statusBox.innerHTML = '❌ Network error or request failed.';
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>➕</span> Commit to Shelf';
        }
    }
}

// ==========================================
// OVERHAULED SHELF AUDIT & RECONCILE CONTROLLER
// ==========================================

// Toggle all checkboxes with mass buttons (✓ Check All / ✕ Uncheck All)
function massSetReconcileChecks(isChecked) {
    const checkboxes = document.querySelectorAll('.modal-deplete-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = isChecked;
        const row = cb.closest('.modal-deplete-row');
        if (row) updateRowVisualState(row, isChecked);
    });

    const masterCb = document.getElementById('modal-select-all-deplete');
    if (masterCb) masterCb.checked = isChecked;

    recalculateReconcileStats();
}

// Master checkbox toggle in the table header
function toggleSelectAllModalDeplete(masterCheckbox) {
    const isChecked = masterCheckbox.checked;
    const checkboxes = document.querySelectorAll('.modal-deplete-checkbox');
    checkboxes.forEach(cb => {
        const row = cb.closest('.modal-deplete-row');
        if (row && row.style.display !== 'none') {
            cb.checked = isChecked;
            updateRowVisualState(row, isChecked);
        }
    });
    recalculateReconcileStats();
}

// Single row checkbox change event
function onReconcileRowCheckChange(checkbox) {
    const row = checkbox.closest('.modal-deplete-row');
    if (row) {
        updateRowVisualState(row, checkbox.checked);
    }
    recalculateReconcileStats();
}

// Click on the item description text directly toggles the checkbox
function toggleRowCheckDirectly(itemId) {
    const row = document.getElementById(`reconcile-row-${itemId}`);
    if (!row) return;
    const cb = row.querySelector('.modal-deplete-checkbox');
    if (cb) {
        cb.checked = !cb.checked;
        updateRowVisualState(row, cb.checked);
        recalculateReconcileStats();
    }
}

// Adjust verified quantity with step buttons
function adjustVerifiedQty(itemId, delta) {
    const row = document.getElementById(`reconcile-row-${itemId}`);
    if (!row) return;

    const input = row.querySelector('.reconcile-qty-input');
    const cb = row.querySelector('.modal-deplete-checkbox');

    let currentQty = parseInt(row.getAttribute('data-verified-qty') || '1', 10);
    let newQty = Math.max(1, currentQty + delta);

    row.setAttribute('data-verified-qty', newQty);
    if (input) input.value = newQty;

    // Automatically check the item as verified when adjusting qty
    if (cb && !cb.checked && delta > 0) {
        cb.checked = true;
        updateRowVisualState(row, true);
    }

    recalculateReconcileStats();
}

// Direct numeric input change for verified quantity
function onVerifiedQtyInputChange(itemId, rawValue) {
    const row = document.getElementById(`reconcile-row-${itemId}`);
    if (!row) return;

    let newQty = Math.max(1, parseInt(rawValue || '1', 10));
    row.setAttribute('data-verified-qty', newQty);

    const cb = row.querySelector('.modal-deplete-checkbox');
    if (cb && !cb.checked) {
        cb.checked = true;
        updateRowVisualState(row, true);
    }

    recalculateReconcileStats();
}

// Visual state helper for row
function updateRowVisualState(row, isChecked) {
    const statusBadge = row.querySelector('.reconcile-row-status-badge');
    if (isChecked) {
        row.style.background = '#f0fdf4';
        row.style.borderColor = '#86efac';
        if (statusBadge) {
            statusBadge.style.background = '#dcfce7';
            statusBadge.style.color = '#166534';
            statusBadge.textContent = '✅ Verified';
        }
    } else {
        row.style.background = 'var(--bg-body, #ffffff)';
        row.style.borderColor = 'var(--border-color, #e2e8f0)';
        if (statusBadge) {
            statusBadge.style.background = '#fee2e2';
            statusBadge.style.color = '#991b1b';
            statusBadge.textContent = '❌ Missing';
        }
    }
}

// Filter items in Shelf Audit & Sync
function filterModalDepleteList(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.modal-deplete-row');

    rows.forEach(row => {
        const searchText = (row.getAttribute('data-search') || '').toLowerCase();
        if (!q || searchText.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Handle Enter key inside search box to automatically verify the top match
function handleReconcileSearchKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        verifyTopFilteredItem();
    }
}

// Verify the top visible matching row from the search filter
function verifyTopFilteredItem() {
    const visibleRows = Array.from(document.querySelectorAll('.modal-deplete-row')).filter(r => r.style.display !== 'none');
    
    if (visibleRows.length === 0) {
        alert('No matching item found on this shelf.');
        return;
    }

    // Pick top visible row
    const targetRow = visibleRows[0];
    const cb = targetRow.querySelector('.modal-deplete-checkbox');
    const input = targetRow.querySelector('.reconcile-qty-input');
    const itemId = targetRow.getAttribute('data-id');

    if (cb) {
        if (cb.checked) {
            // Already verified, increment count by 1
            adjustVerifiedQty(itemId, 1);
        } else {
            cb.checked = true;
            updateRowVisualState(targetRow, true);
        }
    }

    // Visual pulse highlight animation
    targetRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    targetRow.style.transition = 'transform 0.15s ease, background 0.2s';
    targetRow.style.transform = 'scale(1.02)';
    setTimeout(() => {
        targetRow.style.transform = 'scale(1)';
    }, 200);

    recalculateReconcileStats();

    // Clear search box for the next item scan
    const filterInput = document.getElementById('modal-deplete-filter');
    if (filterInput) {
        const searchedTerm = filterInput.value;
        filterInput.value = '';
        filterModalDepleteList('');
        filterInput.focus();

        const statusBox = document.getElementById('modal-deplete-status');
        if (statusBox) {
            statusBox.style.display = 'block';
            statusBox.style.background = '#dcfce7';
            statusBox.style.color = '#166534';
            statusBox.style.border = '1px solid #86efac';
            statusBox.innerHTML = `✅ Verified & Checked: <strong>${escapeHtml(targetRow.querySelector('div')?.textContent || searchedTerm)}</strong>`;
            setTimeout(() => { statusBox.style.display = 'none'; }, 2500);
        }
    }
}

// Recalculate Live Metrics (Verified Keep vs Missing Purge)
function recalculateReconcileStats() {
    const allRows = Array.from(document.querySelectorAll('.modal-deplete-row'));
    let verifiedCount = 0;
    let verifiedUnits = 0;
    let missingCount = 0;
    let missingUnits = 0;

    allRows.forEach(row => {
        const cb = row.querySelector('.modal-deplete-checkbox');
        const origQty = parseInt(row.getAttribute('data-orig-qty') || '1', 10);
        const verifiedQty = parseInt(row.getAttribute('data-verified-qty') || `${origQty}`, 10);

        if (cb && cb.checked) {
            verifiedCount++;
            verifiedUnits += verifiedQty;
        } else {
            missingCount++;
            missingUnits += origQty;
        }
    });

    const vCountEl = document.getElementById('reconcile-stat-verified-count');
    const vUnitsEl = document.getElementById('reconcile-stat-verified-units');
    const mCountEl = document.getElementById('reconcile-stat-missing-count');
    const mUnitsEl = document.getElementById('reconcile-stat-missing-units');
    const mainReconcileBtn = document.getElementById('btn-main-reconcile-shelf');

    if (vCountEl) vCountEl.textContent = verifiedCount;
    if (vUnitsEl) vUnitsEl.textContent = verifiedUnits;
    if (mCountEl) mCountEl.textContent = missingCount;
    if (mUnitsEl) mUnitsEl.textContent = missingUnits;

    if (mainReconcileBtn) {
        const locMeta = document.getElementById('warehouse-metadata');
        const locCode = locMeta ? (locMeta.dataset.locationCode || 'F-1') : 'F-1';
        mainReconcileBtn.innerHTML = `<span>⚡</span> Reconcile Shelf ${locCode} (Keep ${verifiedUnits}, Purge ${missingUnits})`;
    }
}

// Whole Location Reconciliation Sync Execution
async function promptLocationSyncReconcile() {
    const allRows = Array.from(document.querySelectorAll('.modal-deplete-row'));
    if (allRows.length === 0) {
        alert('No registered items to reconcile on this location.');
        return;
    }

    const verifiedMap = {}; // { id: qty }
    let verifiedItemsCount = 0;
    let verifiedUnitsCount = 0;
    let missingItemsCount = 0;
    let missingUnitsCount = 0;

    allRows.forEach(row => {
        const cb = row.querySelector('.modal-deplete-checkbox');
        const itemId = row.getAttribute('data-id');
        const origQty = parseInt(row.getAttribute('data-orig-qty') || '1', 10);
        const verifiedQty = parseInt(row.getAttribute('data-verified-qty') || `${origQty}`, 10);

        if (cb && cb.checked) {
            verifiedMap[itemId] = verifiedQty;
            verifiedItemsCount++;
            verifiedUnitsCount += verifiedQty;
        } else {
            missingItemsCount++;
            missingUnitsCount += origQty;
        }
    });

    const locMeta = document.getElementById('warehouse-metadata');
    const locCode = locMeta ? (locMeta.dataset.locationCode || 'F-1') : 'F-1';
    const sector = locMeta ? (locMeta.dataset.sector || 'Laptops') : 'Laptops';

    let confirmMsg = `⚡ RECONCILE SHELF ${locCode} — CONFIRMATION\n\n`;
    confirmMsg += `✅ VERIFIED TO KEEP ON SHELF:\n   • ${verifiedItemsCount} unique item(s) (${verifiedUnitsCount} total unit[s])\n\n`;
    confirmMsg += `❌ MISSING / UNCHECKED (WILL BE DELETED & RECORDED AS SOLD):\n   • ${missingItemsCount} item(s) (${missingUnitsCount} total unit[s])\n\n`;

    if (verifiedItemsCount === 0) {
        confirmMsg += `⚠️ WARNING: You have 0 items checked. ALL items on Shelf ${locCode} will be deleted and recorded as SOLD.\n\nAre you sure you want to proceed?`;
    } else {
        confirmMsg += `Proceed with updating verified inventory and purging missing items?`;
    }

    if (!confirm(confirmMsg)) {
        return;
    }

    const mainBtn = document.getElementById('btn-main-reconcile-shelf');
    if (mainBtn) {
        mainBtn.disabled = true;
        mainBtn.innerHTML = `<span>⏳</span> Reconciling Shelf ${locCode}...`;
    }

    const csrfEl = document.querySelector('input[name="csrf_token"]') || document.getElementById('warehouse-metadata');
    const csrfToken = csrfEl ? (csrfEl.value || csrfEl.dataset.csrf || '') : '';

    const formData = new FormData();
    formData.append('action', 'reconcile_location_sync');
    formData.append('csrf_token', csrfToken);
    formData.append('location_code', locCode);
    formData.append('sector', sector);
    formData.append('verified_items', JSON.stringify(verifiedMap));

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json().catch(() => null);

        if (response.ok && (!result || result.success !== false)) {
            const statusBox = document.getElementById('modal-deplete-status');
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#166534';
                statusBox.style.border = '1px solid #86efac';
                statusBox.innerHTML = `✅ ${escapeHtml(result && result.message ? result.message : `Shelf ${locCode} reconciled successfully!`)}`;
            }

            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                window.AppSync.sync('inventory-list', true);
            }

            setTimeout(() => {
                const modal = document.getElementById('modal-shelf-deplete');
                if (modal) modal.style.display = 'none';
            }, 900);
        } else {
            alert(result && result.message ? result.message : 'Failed to reconcile shelf.');
            if (mainBtn) {
                mainBtn.disabled = false;
                recalculateReconcileStats();
            }
        }
    } catch (err) {
        console.error("Reconcile Error:", err);
        alert("An error occurred during location reconciliation.");
        if (mainBtn) {
            mainBtn.disabled = false;
            recalculateReconcileStats();
        }
    }
}

// Purge Single Item (Record as sold & remove immediately)
async function purgeModalItem(itemId, itemLabel) {
    if (!confirm(`Mark "${itemLabel}" as SOLD & remove from this shelf immediately?\n\nThis will record the transaction in the sold database and delete the item from this location.`)) {
        return;
    }

    const row = document.getElementById(`reconcile-row-${itemId}`) || document.querySelector(`.modal-deplete-row[data-id="${itemId}"]`);
    const csrfEl = document.querySelector('input[name="csrf_token"]') || document.getElementById('warehouse-metadata');
    const csrfToken = csrfEl ? (csrfEl.value || csrfEl.dataset.csrf || '') : '';

    const formData = new FormData();
    formData.append('action', 'delete_inventory');
    formData.append('csrf_token', csrfToken);
    formData.append('item_id', itemId);
    formData.append('reason', 'Individual Shelf Depletion / Sale');

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (response.ok) {
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    row.remove();
                    updateModalBadgeCounts();
                    recalculateReconcileStats();
                }, 300);
            }

            const mainRow = document.querySelector(`tr[data-id="${itemId}"]`);
            if (mainRow) mainRow.remove();

            updateTotalQuantityHeader();

            if (typeof Notifications !== 'undefined' && Notifications.info) {
                Notifications.info(`Recorded ${itemLabel} as SOLD and removed from shelf.`);
            }
        } else {
            alert('Failed to remove item.');
        }
    } catch (err) {
        console.error("Purge Error:", err);
    }
}

// Helper: update modal item count badge
function updateModalBadgeCounts() {
    const rows = document.querySelectorAll('.modal-deplete-row');
    const badge = document.getElementById('modal-inv-count-badge');
    if (badge) badge.textContent = rows.length;

    const tbody = document.getElementById('modal-deplete-table-body');
    if (rows.length === 0 && tbody) {
        tbody.innerHTML = `
            <tr id="modal-empty-row">
                <td colspan="5" style="padding:30px; text-align:center; color:var(--text-secondary, #64748b);">
                    No inventory registered on this shelf.
                </td>
            </tr>
        `;
    }
}

// Helper: update total qty in header
function updateTotalQuantityHeader() {
    let total = 0;
    document.querySelectorAll('td[data-field="quantity"] input, .modal-deplete-row').forEach(el => {
        if (el.tagName === 'INPUT') {
            total += parseInt(el.value || '0', 10);
        }
    });
    const headerQty = document.getElementById('sidebar-total-qty');
    if (headerQty) {
        headerQty.textContent = `${total.toLocaleString()} Units`;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Initialize reconcile stats on tab switch
document.addEventListener('DOMContentLoaded', function () {
    recalculateReconcileStats();
});

// Attach Escape key listener to close modal
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeInventoryModal();
    }
});
