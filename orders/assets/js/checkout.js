/**
 * System — Checkout Manifest Logic
 * Managed with modern ES6+ standards and safe type-like documentation.
 */

/* ============================================================
   0. State Management
   ============================================================ */
let __checkoutState = null;
function getCheckoutState() {
    if (__checkoutState) return __checkoutState;
    const el = document.getElementById('checkout-state');
    __checkoutState = el ? JSON.parse(el.textContent) : {};
    return __checkoutState;
}

/**
 * @typedef {Object} ManifestItem
 * @property {number} id
 * @property {string} brand
 * @property {string} model
 * @property {string} series
 * @property {string} cpu
 * @property {string} ram
 * @property {string} storage
 * @property {string} battery
 * @property {string} description
 * @property {number} quantity
 * @property {number} unit_price
 */

/* ============================================================
   1. Manifest UI Interactivity
   ============================================================ */

/**
 * Saves changes and opens the system print dialog
 */
async function handlePrintManifest() {
    const form = document.querySelector('#checkout-form');
    if (!(form instanceof HTMLFormElement)) return;

    const formData = new FormData(form);
    formData.set('action', 'update_items');
    formData.set('finalize_status', 'true');
    // Ensure CSRF token is included if not already in FormData
    if (!formData.has('csrf_token')) {
        const csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl) formData.set('csrf_token', csrfEl.value);
    }

    const printBtn = document.querySelector('.btn-print-action');
    if (!printBtn) return;

    const originalText = printBtn.innerHTML;

    try {
        printBtn.innerHTML = 'Saving Manifest...';
        printBtn.disabled = true;

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok) {
            printBtn.innerHTML = 'Preparing Print...';
            setTimeout(() => {
                window.print();
                printBtn.innerHTML = originalText;
                printBtn.disabled = false;
            }, 400);
        } else {
            throw new Error('Network response was not ok');
        }
    } catch (err) {
        console.error("Print Save Failed", err);
        alert('Save failed. Please try saving changes normally first.');
        printBtn.innerHTML = originalText;
        printBtn.disabled = false;
    }
}

/**
 * Generates and downloads a B2B compliant CSV manifest
 * Optimized to pull directly from the active verification table.
 */
function downloadCSV() {
    const state = getCheckoutState();
    const cust = state.customerName || 'Account';
    const ord = state.orderID || 'ORD-000';
    const date = state.orderDate || '';

    // Header Template
    let csv = `"System B2B Purchase Form",,,,,,,,\n\n`;
    csv += `"Name","${cust}",,,,,,,\n`;
    csv += `"Date","${date}",,,,,,,\n`;
    csv += `"Order #","${ord}",,,,,,,\n\n`;

    // Column Headers
    csv += `"Type","Brand","Model","Series","CPU / Gen","Description","Price","QTY","Total","Notes","Battery"\n`;

    let totalQty = 0;
    let grandTotal = 0;
    let rowCount = 0;

    const rows = document.querySelectorAll('.item-row');
    const sanitize = (val) => `"${(val || '').toString().trim().replace(/"/g, '""')}"`;

    rows.forEach(row => {
        // Pull metadata from the description cell
        const brand = row.querySelector('.item-brand')?.innerText || '';
        const model = row.querySelector('.item-model')?.innerText || '';
        const metaText = row.querySelector('.copyable-text div:last-child')?.innerText || '';

        // Parse "Series | CPU | Description"
        const metaParts = metaText.split('|').map(p => p.trim());
        const series = metaParts[0] || '';
        const cpu = metaParts[1] || '';
        const desc = metaParts[2] || '';

        // Pull values from inputs
        const qtyIn = row.querySelector('.qty-input');
        const priceIn = row.querySelector('.price-input');

        const liveQty = qtyIn ? parseFloat(qtyIn.value) || 0 : 0;
        const livePrice = priceIn ? parseFloat(priceIn.value) || 0 : 0;
        const rowTotal = liveQty * livePrice;

        // Pull specific item fields directly from JS state using robust data-id matching
        const itemId = row.getAttribute('data-id');
        const itemData = state.rawItems ? state.rawItems.find(i => String(i.id) === String(itemId)) : null;

        const ram = itemData && itemData.ram ? itemData.ram : '';
        const storage = itemData && itemData.storage ? itemData.storage : '';
        const battery = itemData && itemData.battery ? itemData.battery : '';

        // Format description based on actual item data, fallback to battery if blank
        let descVal = itemData && itemData.description ? itemData.description : desc;
        if (!descVal || descVal.trim() === '') {
            const isBatteryNo = (battery.toLowerCase() === 'no' || battery.toLowerCase() === 'missing' || battery.toLowerCase() === 'dead');
            descVal = isBatteryNo ? 'Parts' : 'Untested';
        }

        // Format notes
        let notesVal = itemData && itemData.notes ? itemData.notes : '';
        if (!notesVal && (ram || storage)) {
            notesVal = `${ram}/${storage}`;
        }

        // Default type to Laptop
        const type = "Laptop";

        csv += `${sanitize(type)},${sanitize(brand)},${sanitize(model)},${sanitize(series)},${sanitize(cpu)},${sanitize(descVal)},${sanitize(livePrice)},${sanitize(liveQty)},${sanitize(rowTotal.toFixed(2))},${sanitize(notesVal)},${sanitize(battery)}\n`;

        totalQty += liveQty;
        grandTotal += rowTotal;
        rowCount++;
    });

    // Removed 42-row padding per user request

    // Alignment Fix:
    // "Total QTY" label in Col 7, Value in Col 8
    // "Total Amount" label in Col 8, Value in Col 9
    csv += `\n,,,,,,${sanitize("Total QTY")},${sanitize(totalQty)},,,\n`;
    csv += `,,,,,,,${sanitize("Total Amount")},${sanitize("$" + grandTotal.toFixed(2))},,\n`;

    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `LatinosPC_B2B_Form_${ord}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Recalculates all manifest totals dynamically based on input changes
 */
function recalculateTotals() {
    let grandTotal = 0;
    let totalQty = 0;
    const rows = document.querySelectorAll('.item-row');

    rows.forEach(row => {
        const qtyIn = row.querySelector('.qty-input');
        const priceIn = row.querySelector('.price-input');
        const subDisplay = row.querySelector('.row-subtotal');

        const qty = qtyIn ? parseFloat(qtyIn.value) || 0 : 0;
        const price = priceIn ? parseFloat(priceIn.value) || 0 : 0;
        const subtotal = qty * price;

        if (subDisplay) {
            subDisplay.innerText = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        const printSpans = row.querySelectorAll('.print-only');
        if (printSpans.length >= 2) {
            printSpans[0].innerText = qty.toString();
            printSpans[1].innerText = '$' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        totalQty += qty;
        grandTotal += subtotal;
    });

    const totalQtyDisplay = document.getElementById('total-qty-display');
    if (totalQtyDisplay) {
        totalQtyDisplay.innerText = totalQty.toString();
    }

    const grandDisplay = document.getElementById('grand-total-display');
    if (grandDisplay) {
        grandDisplay.innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

/**
 * Handle Auto-opening modal if anchor is present
 */
window.addEventListener('load', () => {
    if (window.location.hash.includes('modal-brand')) {
        const firstRow = document.querySelector('.item-row');
        if (firstRow) openEditModal(0);
    }

    // Automatically apply the selected sort on page load
    if (typeof sortCheckout === 'function') {
        sortCheckout();
    }
});

/**
 * Copies item details to clipboard
 * @param {HTMLElement} btn
 */
function copyEntry(btn) {
    const container = btn.closest('.col-desc');
    const textNode = container ? container.querySelector('.copyable-text') : null;
    if (!textNode) return;

    const text = textNode.innerText.trim();

    const performCopy = (textToCopy) => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(textToCopy);
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = textToCopy;
            Object.assign(textArea.style, { position: "fixed", left: "-9999px", top: "0" });
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try { document.execCommand('copy'); } catch (e) { }
            document.body.removeChild(textArea);
            return Promise.resolve();
        }
    };

    performCopy(text).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅';
        btn.style.opacity = '1';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.opacity = '0.4';
        }, 1500);
    });
}

/* ============================================================
   2. Modal Management (AJAX)
   ============================================================ */

/**
 * Opens the item metadata editor modal
 * @param {number} index
 */
function openEditModal(index) {
    // Robust check for injected data
    const state = getCheckoutState();
    const items = state.rawItems || null;
    const item = items ? items[index] : null;

    if (!item) {
        console.error("openEditModal: Item not found at index", index);
        return;
    }

    const modal = document.getElementById('editModal');
    if (!modal) return;

    modal.classList.add('active');

    // Map data to modal fields
    const fields = {
        'modal-item-id': item.id,
        'modal-item-index': index,
        'modal-brand': item.brand,
        'modal-model': item.model,
        'modal-series': item.series,
        'modal-cpu': item.cpu,
        'modal-ram': item.ram,
        'modal-storage': item.storage,
        'modal-battery': item.battery,
        'modal-desc': item.description,
        'modal-qty': item.quantity,
        'modal-price': parseFloat(item.unit_price || 0).toFixed(2)
    };

    Object.entries(fields).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) {
            if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
                // Ensure null/undefined doesn't crash .toString()
                el.value = (val ?? '').toString();
            }
        }
    });
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.remove('active');
}

/**
 * Asynchronously saves item changes from the modal
 */
async function saveItemChanges() {
    const getVal = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : '';
    };

    const parsePrice = (priceVal) => {
        const p = parseFloat(priceVal);
        return isNaN(p) ? 0 : p;
    };

    const formData = new FormData();
    formData.append('action', 'save_single_item');

    const csrfEl = document.querySelector('input[name="csrf_token"]');
    if (csrfEl) formData.append('csrf_token', csrfEl.value);

    formData.append('item_id', getVal('modal-item-id'));
    formData.append('brand', getVal('modal-brand'));
    formData.append('model', getVal('modal-model'));
    formData.append('series', getVal('modal-series'));
    formData.append('cpu', getVal('modal-cpu'));
    formData.append('ram', getVal('modal-ram'));
    formData.append('storage', getVal('modal-storage'));
    formData.append('battery', getVal('modal-battery'));
    formData.append('description', getVal('modal-desc'));
    formData.append('quantity', getVal('modal-qty'));
    formData.append('unit_price', parsePrice(getVal('modal-price')));

    const saveBtn = document.getElementById('btn-modal-save');
    if (!saveBtn) return;

    const origText = saveBtn.innerText;

    try {
        saveBtn.innerText = 'Syncing...';
        saveBtn.disabled = true;

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.status === 'success') {
            // Live-Sync UI without full reload
            const itemId = getVal('modal-item-id');
            const idx = parseInt(getVal('modal-item-index'));
            const brand = getVal('modal-brand');
            const model = getVal('modal-model');
            const series = getVal('modal-series');
            const cpu = getVal('modal-cpu');
            const ram = getVal('modal-ram');
            const storage = getVal('modal-storage');
            const battery = getVal('modal-battery');
            const desc = getVal('modal-desc');
            const qty = getVal('modal-qty');
            const price = parseFloat(getVal('modal-price')) || 0;

            // 1. Update the JS data source
            const state = getCheckoutState();
            if (state.rawItems && state.rawItems[idx]) {
                Object.assign(state.rawItems[idx], {
                    brand, model, series, cpu, ram, storage, battery, description: desc,
                    quantity: parseFloat(qty), unit_price: price
                });
            }

            // 2. Update the DOM table row directly
            const row = document.querySelector(`.item-row[data-id="${itemId}"]`);
            if (row) {
                const brandEl = row.querySelector('.item-brand');
                if (brandEl) brandEl.innerText = brand;

                const modelEl = row.querySelector('.item-model');
                if (modelEl) modelEl.innerText = model;

                const metaEl = row.querySelector('.item-metadata');
                if (metaEl) {
                    metaEl.innerHTML = `${series} | <span style="color: var(--accent-color); font-weight:800;">${cpu}</span> | ${desc}`;
                }

                const qtyIn = row.querySelector('.qty-input');
                if (qtyIn) qtyIn.value = qty;

                const priceIn = row.querySelector('.price-input');
                if (priceIn) priceIn.value = price.toFixed(2);
            }

            recalculateTotals();
            closeEditModal();

            saveBtn.innerText = origText;
            saveBtn.disabled = false;
        } else {
            throw new Error('Sync failed');
        }
    } catch (err) {
        saveBtn.innerText = origText;
        saveBtn.disabled = false;
        alert('Sync failed. Please check your connection.');
    }
}

/**
 * Submits labels to the ODT generator service
 */
function printLabel() {
    const getVal = (id) => {
        const el = document.getElementById(id);
        return el ? el.value : '';
    };

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_odt.php';

    const fields = {
        'csrf_token': document.querySelector('input[name="csrf_token"]')?.value || '',
        'brand': getVal('modal-brand'),
        'model': getVal('modal-model'),
        'series': getVal('modal-series'),
        'cpu': getVal('modal-cpu'),
        'description': getVal('modal-desc')
    };

    Object.entries(fields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/**
 * Client-side search for the manifest manifest rows
 */
function filterManifest() {
    const searchIn = document.getElementById('manifest-search');
    if (!searchIn) return;

    const filter = searchIn.value.toLowerCase();
    const rows = document.getElementsByClassName('item-row');

    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].innerText.toLowerCase();
        rows[i].style.display = text.includes(filter) ? "" : "none";
    }
}

/**
 * Sort checkout table rows interactively
 */
function getDescRank(desc) {
    const d = (desc || '').toLowerCase();
    if (d.includes('untested')) return 1;
    if (d.includes('tested')) return 2;
    if (d.includes('parts')) return 3;
    return 4;
}

function sortCheckout() {
    const sortBy = document.getElementById('checkout-sort').value;
    const tbody = document.getElementById('checkout-list');
    if (!tbody) return;

    // Select all valid item rows (exclude total rows, etc.)
    const rows = Array.from(tbody.querySelectorAll('.item-row'));

    rows.sort((a, b) => {
        if (sortBy === 'original') {
            return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
        }

        // Primary Sort: Group by Untested -> Tested -> Parts
        const metaA = a.querySelector('.item-metadata');
        const metaB = b.querySelector('.item-metadata');
        const rankA = getDescRank(metaA ? metaA.getAttribute('data-desc') : '');
        const rankB = getDescRank(metaB ? metaB.getAttribute('data-desc') : '');

        if (rankA !== rankB) {
            return rankA - rankB;
        }

        // Secondary Sort:
        if (sortBy === 'qty_desc') {
            const qtyA = a.querySelector('.qty-input') ? parseFloat(a.querySelector('.qty-input').value) : 0;
            const qtyB = b.querySelector('.qty-input') ? parseFloat(b.querySelector('.qty-input').value) : 0;
            return qtyB - qtyA;
        } else if (sortBy === 'price_desc') {
            const pA = a.querySelector('.price-input') ? parseFloat(a.querySelector('.price-input').value) : 0;
            const pB = b.querySelector('.price-input') ? parseFloat(b.querySelector('.price-input').value) : 0;
            return pB - pA;
        } else if (sortBy === 'default') {
            return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
        }
        return 0;
    });

    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
}
