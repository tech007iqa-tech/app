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
            }

            setTimeout(() => {
                window.location.reload();
            }, 750);
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
            submitBtn.innerHTML = `<span>➕</span> Commit to Shelf`;
        }
    }
}

// Whole Location Reconciliation Sync
async function promptLocationSyncReconcile() {
    const allRows = Array.from(document.querySelectorAll('.modal-deplete-row'));
    const checkedBoxes = Array.from(document.querySelectorAll('.modal-deplete-checkbox:checked'));
    const uncheckedBoxes = Array.from(document.querySelectorAll('.modal-deplete-checkbox:not(:checked)'));

    const keptIds = checkedBoxes.map(cb => cb.value);
    const missingCount = uncheckedBoxes.length;
    const keptCount = checkedBoxes.length;

    const locMeta = document.getElementById('warehouse-metadata');
    const locCode = locMeta ? (locMeta.dataset.locationCode || 'N-1') : 'N-1';
    const sector = locMeta ? (locMeta.dataset.sector || 'Laptops') : 'Laptops';

    if (allRows.length === 0) {
        alert('No registered items to reconcile on this location.');
        return;
    }

    let confirmMsg = `🔄 FULL LOCATION AUDIT & SYNC — Shelf ${locCode}\n\n`;
    confirmMsg += `• Verified Physically Present: ${keptCount} item(s) (Retained on Shelf)\n`;
    confirmMsg += `• Missing / Omitted: ${missingCount} item(s)\n\n`;

    if (missingCount === 0) {
        confirmMsg += `All ${keptCount} items are verified present. Re-align shelf inventory?`;
    } else {
        confirmMsg += `⚠️ CRITICAL: The ${missingCount} missing item(s) will be automatically recorded as SOLD in the database and cleared from Shelf ${locCode}.\n\nProceed with full shelf sync?`;
    }

    if (!confirm(confirmMsg)) {
        return;
    }

    const csrfEl = document.querySelector('input[name="csrf_token"]') || document.getElementById('warehouse-metadata');
    const csrfToken = csrfEl ? (csrfEl.value || csrfEl.dataset.csrf || '') : '';

    const formData = new FormData();
    formData.append('action', 'reconcile_location_sync');
    formData.append('csrf_token', csrfToken);
    formData.append('location_code', locCode);
    formData.append('sector', sector);
    formData.append('kept_item_ids', JSON.stringify(keptIds));

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
            // Remove unchecked rows from DOM
            uncheckedBoxes.forEach(cb => {
                const row = cb.closest('.modal-deplete-row');
                if (row) row.remove();
                const mainRow = document.querySelector(`tr[data-id="${cb.value}"]`);
                if (mainRow) mainRow.remove();
            });

            updateModalBadgeCounts();
            updateTotalQuantityHeader();

            const statusBox = document.getElementById('modal-deplete-status');
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#dcfce7';
                statusBox.style.color = '#166534';
                statusBox.style.border = '1px solid #86efac';
                statusBox.innerHTML = `✅ ${escapeHtml(result && result.message ? result.message : `Shelf ${locCode} synchronized successfully.`)}`;
            }

            if (typeof Notifications !== 'undefined' && Notifications.success) {
                Notifications.success(`Shelf ${locCode} synchronized.`);
            }

            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert(result && result.message ? result.message : 'Failed to reconcile location sync.');
        }
    } catch (err) {
        console.error("Location Sync Error:", err);
        alert("An error occurred during location reconciliation.");
    }
}

// Deplete / Step Quantity for an item
async function depleteModalItemQty(itemId, delta) {
    const row = document.querySelector(`.modal-deplete-row[data-id="${itemId}"]`);
    if (!row) return;

    const qtyBadge = row.querySelector('.modal-item-qty-badge');
    const currentQty = parseInt(row.getAttribute('data-qty') || '1', 10);
    const newQty = currentQty + delta;

    if (newQty < 0) return;

    const csrfEl = document.querySelector('input[name="csrf_token"]') || document.getElementById('warehouse-metadata');
    const csrfToken = csrfEl ? (csrfEl.value || csrfEl.dataset.csrf || '') : '';

    const formData = new FormData();
    formData.append('action', 'deplete_inventory_item');
    formData.append('csrf_token', csrfToken);
    formData.append('item_id', itemId);
    formData.append('delta', delta);

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
            if (newQty <= 0) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    row.remove();
                    updateModalBadgeCounts();
                }, 300);

                const mainRow = document.querySelector(`tr[data-id="${itemId}"]`);
                if (mainRow) mainRow.remove();
            } else {
                row.setAttribute('data-qty', newQty);
                if (qtyBadge) qtyBadge.textContent = newQty;

                const mainRow = document.querySelector(`tr[data-id="${itemId}"]`);
                if (mainRow) {
                    const qtyInput = mainRow.querySelector('td[data-field="quantity"] input');
                    if (qtyInput) qtyInput.value = newQty;
                }
            }

            updateTotalQuantityHeader();
        } else {
            alert(result && result.message ? result.message : 'Error updating quantity.');
        }
    } catch (err) {
        console.error("Depletion Error:", err);
    }
}

// Purge Single Item (Record as sold & remove)
async function purgeModalItem(itemId, itemLabel) {
    if (!confirm(`Mark "${itemLabel}" as SOLD & remove from this shelf?\n\nThis will record the transaction in the sold database and delete the item from this location.`)) {
        return;
    }

    const row = document.querySelector(`.modal-deplete-row[data-id="${itemId}"]`);
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

// Batch Purge Selected Items (Record as sold)
async function batchPurgeSelectedModalItems() {
    const checkedBoxes = Array.from(document.querySelectorAll('.modal-deplete-checkbox:checked'));
    if (checkedBoxes.length === 0) {
        alert('Please select at least one item to purge from the shelf.');
        return;
    }

    const itemIds = checkedBoxes.map(cb => cb.value);
    if (!confirm(`CRITICAL: Record ${itemIds.length} selected item(s) as SOLD and remove from this shelf?\n\nThese items will be recorded in the sold history and cleared from inventory.`)) {
        return;
    }

    const csrfEl = document.querySelector('input[name="csrf_token"]') || document.getElementById('warehouse-metadata');
    const csrfToken = csrfEl ? (csrfEl.value || csrfEl.dataset.csrf || '') : '';

    const formData = new FormData();
    formData.append('action', 'purge_inventory_items');
    formData.append('csrf_token', csrfToken);
    formData.append('item_ids', JSON.stringify(itemIds));

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
            checkedBoxes.forEach(cb => {
                const row = cb.closest('.modal-deplete-row');
                if (row) row.remove();
                const mainRow = document.querySelector(`tr[data-id="${cb.value}"]`);
                if (mainRow) mainRow.remove();
            });

            updateModalBadgeCounts();
            updateTotalQuantityHeader();

            const statusBox = document.getElementById('modal-deplete-status');
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.style.background = '#fee2e2';
                statusBox.style.color = '#991b1b';
                statusBox.innerHTML = `🏷️ Recorded ${itemIds.length} item(s) as SOLD and removed from shelf.`;
                setTimeout(() => { statusBox.style.display = 'none'; }, 3000);
            }
        } else {
            alert('Failed to purge selected items.');
        }
    } catch (err) {
        console.error("Batch Purge Error:", err);
    }
}

// Filter items within the Deplete modal tab
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

// Toggle select all inside modal
function toggleSelectAllModalDeplete(masterCheckbox) {
    const isChecked = masterCheckbox.checked;
    const checkboxes = document.querySelectorAll('.modal-deplete-checkbox');
    checkboxes.forEach(cb => {
        const row = cb.closest('.modal-deplete-row');
        if (row && row.style.display !== 'none') {
            cb.checked = isChecked;
        }
    });
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

// Attach Escape key listener to close modal
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeInventoryModal();
    }
});
