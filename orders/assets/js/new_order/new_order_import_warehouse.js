/**
 * New Order Warehouse Stock Importer Module
 * Searches warehouse inventory, manages Shift+Click checkbox selection, and imports selected stock items.
 */

let whImportItems = [];
let lastChecked = null;

async function searchWarehouseImport() {
    const searchInput = document.getElementById('wh-import-q');
    const q = searchInput ? searchInput.value : '';
    const list = document.getElementById('wh-import-list');
    const selectAll = document.getElementById('wh-import-select-all');
    if (selectAll) selectAll.checked = false;

    if (list) {
        list.innerHTML = `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">Loading warehouse stock...</td></tr>`;
    }

    try {
        const response = await fetch(`api/get_warehouse_stock.php?q=${encodeURIComponent(q)}`);
        if (!response.ok) throw new Error("API error");
        whImportItems = await response.json();

        if (!list) return;

        if (whImportItems.length === 0) {
            list.innerHTML = `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No matching warehouse stock found.</td></tr>`;
            return;
        }

        list.innerHTML = whImportItems.map((item, idx) => {
            const specNotes = item.specs?.notes || '';
            const cpu = item.specs?.cpu || '';
            const ram = item.specs?.ram || '';
            const storage = item.specs?.storage || '';
            const specsStr = [cpu, ram, storage, specNotes].filter(Boolean).join(' | ');

            return `
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 10px; text-align: center;"><input type="checkbox" class="wh-import-row-select" data-index="${idx}"></td>
                <td style="padding: 10px;"><span class="location-tag" style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">${escapeHTML(item.location_code)}</span></td>
                <td style="padding: 10px; font-weight: 600;">${escapeHTML(item.brand)} ${escapeHTML(item.model)}</td>
                <td style="padding: 10px; text-align: center; font-weight: 700;">${item.quantity}</td>
                <td style="padding: 10px; text-align: right;">$${parseFloat(item.price || 0).toFixed(2)}</td>
                <td style="padding: 10px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;" title="${escapeHTML(specsStr)}">${escapeHTML(specsStr)}</td>
            </tr>
        `;
        }).join('');
    } catch (err) {
        console.error(err);
        if (list) {
            list.innerHTML = `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #ef4444;">Failed to fetch stock from warehouse.</td></tr>`;
        }
    }
}

function handleCheckboxClick(cb, shiftKey) {
    const checkboxes = Array.from(document.querySelectorAll('.wh-import-row-select'));
    if (shiftKey && lastChecked) {
        let start = checkboxes.indexOf(cb);
        let end = checkboxes.indexOf(lastChecked);
        checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1)
            .forEach(c => c.checked = lastChecked.checked);
    }
    lastChecked = cb;
}

function toggleAllWarehouseImport(master) {
    const checkboxes = document.querySelectorAll('.wh-import-row-select');
    checkboxes.forEach(cb => cb.checked = master.checked);
}

async function submitWarehouseImport() {
    const checkboxes = document.querySelectorAll('.wh-import-row-select:checked');
    if (checkboxes.length === 0) {
        alert("Please select at least one warehouse item to import.");
        return;
    }

    const btn = document.getElementById('btn-submit-wh-import');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '⏳ Importing...';
        btn.disabled = true;
    }

    const itemsToImport = [];
    const whIds = [];
    checkboxes.forEach(cb => {
        const idx = parseInt(cb.getAttribute('data-index'), 10);
        const item = whImportItems[idx];
        if (item) {
            whIds.push(item.id);

            const specNotes = item.specs?.notes || '';
            const cpu = item.specs?.cpu || '';
            const ram = item.specs?.ram || '';
            const storage = item.specs?.storage || '';
            const battery = item.specs?.battery ? 'Battery: ' + item.specs.battery : '';
            const condition = item.specs?.condition || '';
            const series = item.specs?.series || '';

            let extraDesc = [condition, battery, specNotes].filter(Boolean).join(' | ');
            if (extraDesc) {
                extraDesc += ' | ';
            }
            extraDesc += `[Warehouse Location: ${item.location_code} - Flagged for Deletion]`;

            itemsToImport.push({
                brand: item.brand,
                model: item.model,
                series: series || 'Warehouse Import',
                cpu: cpu || '',
                description: extraDesc,
                quantity: item.quantity,
                unit_price: item.price || 0
            });
        }
    });

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    try {
        const importResponse = await fetch('api/bulk_update_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'bulk_import',
                csrf_token: csrfToken,
                customer_id: activeImportCustomerId,
                order_id: activeImportOrderId,
                items: itemsToImport
            })
        });

        const importResult = await importResponse.json();
        if (!importResult.success) {
            throw new Error(importResult.error || "Failed to add items to order.");
        }

        const flagResponse = await fetch('api/bulk_update_inventory.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                ids: whIds,
                location: '',
                price: '',
                status: 'Pending Delete'
            })
        });

        const flagResult = await flagResponse.json();
        if (!flagResult.success) {
            console.error("Failed to flag warehouse items:", flagResult.error);
        }

        if (btn) btn.innerHTML = '✅ Success!';
        setTimeout(() => {
            window.location.reload();
        }, 1000);

    } catch (e) {
        console.error(e);
        alert("Import failed: " + e.message);
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const whImportList = document.getElementById('wh-import-list');
    if (whImportList) {
        whImportList.addEventListener('click', function (e) {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const cb = tr.querySelector('.wh-import-row-select');
            if (!cb) return;

            if (e.target === cb) {
                handleCheckboxClick(cb, e.shiftKey);
                return;
            }

            cb.checked = !cb.checked;
            handleCheckboxClick(cb, e.shiftKey);
        });
    }
});
