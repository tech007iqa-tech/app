/**
 * Filters the global batch list based on search input
 */
function filterOrders() {
    const input = document.getElementById('order-search');
    if (!input) return;

    const terms = input.value.toLowerCase().split(' ').filter(t => t.trim() !== '');
    const rows = document.getElementsByClassName('order-row');
    let hasResults = false;

    for (let i = 0; i < rows.length; i++) {
        const searchBlob = (rows[i].getAttribute('data-search') || "").toLowerCase();

        // Every term must be present (AND logic)
        const isMatch = terms.every(term => searchBlob.includes(term));

        if (isMatch) {
            rows[i].style.display = "";
            hasResults = true;
        } else {
            rows[i].style.display = "none";
        }
    }

    // Handle empty state during search
    let emptyState = document.querySelector('.orders-empty-state');
    const tbody = document.getElementById('orders-list');

    if (!hasResults) {
        if (!emptyState && tbody) {
            emptyState = document.createElement('tr');
            emptyState.className = 'orders-empty-state';
            const td = document.createElement('td');
            td.colSpan = 5;
            td.style.cssText = 'padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;';
            emptyState.appendChild(td);
            tbody.appendChild(emptyState);
        }

        if (emptyState) {
            emptyState.style.display = '';
            emptyState.querySelector('td').innerText = `No batches found matching "${input.value}"`;
        }
    } else if (emptyState) {
        emptyState.style.display = 'none';
    }
}

/**
 * Sorts the orders table based on column index
 * @param {number} n
 */
function sortOrdersTable(n) {
    const table = document.querySelector(".orders-table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr.order-row"));

    // Determine sort direction
    const currentDir = table.getAttribute("data-sort-dir") === "asc" ? "desc" : "asc";
    table.setAttribute("data-sort-dir", currentDir);
    const multiplier = currentDir === "asc" ? 1 : -1;

    rows.sort((a, b) => {
        const x = a.getElementsByTagName("TD")[n].innerText.trim().toLowerCase();
        const y = b.getElementsByTagName("TD")[n].innerText.trim().toLowerCase();

        // Special handling for dates (column index 2)
        if (n === 2) {
            const dateX = new Date(x);
            const dateY = new Date(y);
            return (dateX - dateY) * multiplier;
        }

        if (x < y) return -1 * multiplier;
        if (x > y) return 1 * multiplier;
        return 0;
    });

    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Updates the order status via AJAX
 * @param {HTMLSelectElement} select
 * @param {string} orderId
 */
async function updateOrderStatus(select, orderId) {
    const newStatus = select.value;
    const originalValue = select.getAttribute('data-original-value');
    const badge = select.closest('.order-row').querySelector('.order-badge');

    try {
        select.disabled = true;
        if (badge) badge.style.opacity = '0.5';

        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('new_status', newStatus);

        const csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl) formData.append('csrf_token', csrfEl.value);

        const response = await fetch('api/update_order_status.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            // Update UI
            select.setAttribute('data-original-value', newStatus);
            if (badge) {
                // Remove old status classes
                badge.className = 'order-badge status-' + newStatus.toLowerCase();
                badge.innerText = newStatus;
                badge.style.opacity = '1';
            }
        } else {
            throw new Error(data.error || 'Update failed');
        }
    } catch (err) {
        console.error("Status update failed", err);
        alert('Failed to update status: ' + err.message);
        select.value = originalValue; // Revert
        if (badge) badge.style.opacity = '1';
    } finally {
        select.disabled = false;
    }
}

/**
 * Transfers an order to a new customer via AJAX
 */
async function transferOrder(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const origText = submitBtn.innerText;

    try {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Transferring...';

        const formData = new FormData(form);
        const csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl && !formData.has('csrf_token')) formData.append('csrf_token', csrfEl.value);

        const response = await fetch('api/transfer_order.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            alert('Order transferred successfully!');
            location.reload(); // Reload to reflect changes in the list
        } else {
            throw new Error(data.error || 'Transfer failed');
        }
    } catch (err) {
        console.error("Transfer failed", err);
        alert('Failed to transfer order: ' + err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = origText;
    }
}

// Handle automatic filtering from URL parameters (e.g., index.php?view=orders&q=CUST-123)
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const query = params.get('q');

    if (query) {
        const searchInput = document.getElementById('order-search');
        if (searchInput) {
            searchInput.value = query;
            filterOrders(); // Trigger the existing filter logic
        }
    }

    // Register for real-time synchronization
    if (document.getElementById('orders-list')) {
        AppSync.register({
            elementId: 'orders-list',
            url: window.location.pathname + window.location.search + (window.location.search ? '&ajax=1' : '?ajax=1'),
            onUpdate: () => {
                filterOrders();

                // Keep selected IDs up-to-date with what actually exists in DOM now
                const currentIds = new Set(Array.from(document.querySelectorAll('#orders-list .row-select')).map(cb => cb.closest('tr').dataset.id));
                for (let id of selectedOrderIds) {
                    if (!currentIds.has(id)) {
                        selectedOrderIds.delete(id);
                    }
                }
                updateOrderBulkBar();
            }
        });
    }
});

// ─── BULK ACTIONS LOGIC ──────────────────────────────────────────────────────

let selectedOrderIds = new Set();
const OrderDOM = {
    selectAll: document.getElementById('selectAll'),
    bulkBar: document.getElementById('bulkActionBar'),
    selectedCount: document.getElementById('selectedCount'),
    tbody: document.getElementById('orders-list')
};

function updateOrderBulkBar() {
    const count = selectedOrderIds.size;
    if (OrderDOM.selectedCount) OrderDOM.selectedCount.textContent = count;
    if (OrderDOM.bulkBar) OrderDOM.bulkBar.style.display = count > 0 ? 'flex' : 'none';
}

let lastOrderChecked = null;

if (OrderDOM.selectAll) {
    OrderDOM.selectAll.addEventListener('change', (e) => {
        const isChecked = e.target.checked;
        const checkboxes = OrderDOM.tbody.querySelectorAll('.row-select');
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr.style.display !== 'none') {
                cb.checked = isChecked;
                const id = tr.dataset.id;
                if (isChecked) {
                    selectedOrderIds.add(id);
                    tr.classList.add('selected-row');
                } else {
                    selectedOrderIds.delete(id);
                    tr.classList.remove('selected-row');
                }
            }
        });
        updateOrderBulkBar();
    });
}

if (OrderDOM.tbody) {
    OrderDOM.tbody.addEventListener('click', (e) => {
        if (e.target.classList.contains('row-select')) {
            const currentCb = e.target;
            const checkboxes = Array.from(OrderDOM.tbody.querySelectorAll('.row-select')).filter(cb => cb.closest('tr').style.display !== 'none');

            if (e.shiftKey && lastOrderChecked && lastOrderChecked !== currentCb) {
                let start = checkboxes.indexOf(currentCb);
                let end = checkboxes.indexOf(lastOrderChecked);

                if (start > -1 && end > -1) {
                    const range = checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1);
                    const isChecked = currentCb.checked;

                    range.forEach(cb => {
                        cb.checked = isChecked;
                        const tr = cb.closest('tr');
                        const id = tr.dataset.id;
                        if (isChecked) {
                            selectedOrderIds.add(id);
                            tr.classList.add('selected-row');
                        } else {
                            selectedOrderIds.delete(id);
                            tr.classList.remove('selected-row');
                        }
                    });
                }
            } else {
                const tr = currentCb.closest('tr');
                const id = tr.dataset.id;
                if (currentCb.checked) {
                    selectedOrderIds.add(id);
                    tr.classList.add('selected-row');
                } else {
                    selectedOrderIds.delete(id);
                    tr.classList.remove('selected-row');
                    if (OrderDOM.selectAll) OrderDOM.selectAll.checked = false;
                }
            }

            lastOrderChecked = currentCb;
            updateOrderBulkBar();
        }
    });
}

document.getElementById('cancelBulkBtn')?.addEventListener('click', () => {
    selectedOrderIds.clear();
    if (OrderDOM.selectAll) OrderDOM.selectAll.checked = false;
    OrderDOM.tbody.querySelectorAll('.row-select').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('selected-row');
    });
    updateOrderBulkBar();
});

document.getElementById('applyBulkBtn')?.addEventListener('click', async () => {
    const status = document.getElementById('bulkStatus').value;

    if (!status) {
        alert("Please select a status to apply.");
        return;
    }

    if (!confirm(`Update status to "${status}" for ${selectedOrderIds.size} orders?`)) return;

    const btn = document.getElementById('applyBulkBtn');
    btn.disabled = true;
    btn.textContent = '⌛ Updating...';

    try {
        const response = await fetch('api/bulk_update_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ids: Array.from(selectedOrderIds),
                status: status,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        });

        const json = await response.json();
        if (json.success) {
            IQA_Notify.success(`Successfully updated ${selectedOrderIds.size} orders!`);
            selectedOrderIds.clear();
            if (OrderDOM.selectAll) OrderDOM.selectAll.checked = false;
            updateOrderBulkBar();
            window.location.reload();
        } else {
            IQA_Notify.error(`Error: ${json.error}`);
        }
    } catch (err) {
        IQA_Notify.error("Network error during bulk update.");
    } finally {
        btn.disabled = false;
        btn.textContent = 'Apply to Selected';
    }
});
