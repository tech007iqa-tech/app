/**
 * Warehouse Import Spreadsheet In-Place Editing Sub-Module
 * Handles focusout blur updates, asynchronous cell saves to session, status recalculations, and warning badges.
 */

function initImportWarehouseTable() {
    const table = document.querySelector('.spreadsheet-table');
    if (!table) return;

    table.addEventListener('focusout', async (e) => {
        if (e.target && e.target.classList.contains('cell-input')) {
            const input = e.target;
            const cell = input.closest('td');
            const row = input.closest('tr');
            if (!cell || !row) return;

            const rowIndex = row.rowIndex - 1; // 0-based data rows
            const field = cell.getAttribute('data-field');
            const val = input.value.trim();

            try {
                const response = await fetch('index.php?view=import_warehouse&action=update_import_cell', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        row_index: rowIndex,
                        field: field,
                        val: val
                    })
                });
                const res = await response.json();
                if (res.success) {
                    // Update warnings locally
                    if (val === '' || val === '-' || val === 'Unknown') {
                        input.classList.add('warning-empty');
                    } else {
                        input.classList.remove('warning-empty');
                    }
                    if (field === 'qty') {
                        const qtyVal = parseInt(val, 10);
                        if (isNaN(qtyVal) || qtyVal <= 0) {
                            input.classList.add('warning-empty');
                        } else {
                            input.classList.remove('warning-empty');
                        }
                    }

                    // Update Status badge
                    const statusTd = row.cells[0];
                    const originalTd = row.cells[1];
                    const isAccepted = res.status === 'Accept';

                    row.style.backgroundColor = isAccepted ? 'rgba(236, 253, 245, 0.4)' : 'rgba(254, 242, 242, 0.6)';

                    if (isAccepted) {
                        statusTd.innerHTML = '<span style="color: #059669; background: #d1fae5; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;">Accept</span>';
                    } else {
                        const errorsText = res.errors.join(', ');
                        statusTd.innerHTML = `<span style="color: #dc2626; background: #fee2e2; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;" title="${errorsText}">Reject ⚠️</span>`;
                    }

                    // Update error text display
                    let errorDiv = originalTd.querySelector('.row-error-list');
                    if (res.errors.length > 0) {
                        if (!errorDiv) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'row-error-list';
                            errorDiv.style.color = '#b91c1c';
                            errorDiv.style.fontSize = '0.7rem';
                            errorDiv.style.fontWeight = '700';
                            errorDiv.style.marginTop = '4px';
                            originalTd.appendChild(errorDiv);
                        }
                        errorDiv.textContent = res.errors.join(', ');
                    } else if (errorDiv) {
                        errorDiv.remove();
                    }

                    // Update stats
                    const totalEl = document.getElementById('stats-total');
                    const acceptedEl = document.getElementById('stats-accepted');
                    const rejectedEl = document.getElementById('stats-rejected');
                    if (totalEl) totalEl.textContent = res.total;
                    if (acceptedEl) acceptedEl.textContent = res.accepted;
                    if (rejectedEl) rejectedEl.textContent = res.rejected;

                    // Update Confirm Import container
                    const confirmContainer = document.getElementById('confirm-import-container');
                    if (confirmContainer) {
                        if (res.accepted > 0) {
                            confirmContainer.style.display = 'block';
                            const btn = confirmContainer.querySelector('button');
                            if (btn) {
                                btn.textContent = `🚀 Confirm Import (${res.accepted} items)`;
                            }
                        } else {
                            confirmContainer.style.display = 'none';
                        }
                    }

                    // Cell flash feedback
                    cell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
                    setTimeout(() => { cell.style.backgroundColor = ''; }, 600);
                }
            } catch (err) {
                console.error('AJAX update error:', err);
            }
        }
    });
}
