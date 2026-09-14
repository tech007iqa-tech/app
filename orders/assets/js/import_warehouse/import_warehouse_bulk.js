/**
 * Warehouse Import Bulk Operations Sub-Module
 * Handles bulk default CPU selection, menu toggling, and asynchronous batch cell updates.
 */

function toggleCpuBulkMenu(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('cpu-bulk-menu');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
}

async function bulkUpdateDefaultCpu(event, targetCpu) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const menu = document.getElementById('cpu-bulk-menu');
    if (menu) menu.style.display = 'none';

    const rows = document.querySelectorAll('.spreadsheet-table tbody tr');
    let updatePromises = [];

    rows.forEach((row, index) => {
        const cpuCell = row.querySelector('td[data-field="cpu"]');
        const genCell = row.querySelector('td[data-field="gen"]');
        if (cpuCell && genCell) {
            const input = cpuCell.querySelector('input.cell-input');
            const genInput = genCell.querySelector('input.cell-input');
            if (input && genInput) {
                const currentVal = input.value.trim();
                const genVal = genInput.value.trim();
                if ((currentVal === 'i5' || currentVal === '') && genVal !== '' && genVal !== '-') {
                    input.value = targetCpu;
                    cpuCell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
                    setTimeout(() => { cpuCell.style.backgroundColor = ''; }, 600);

                    const rowIndex = index;
                    const promise = fetch('index.php?view=import_warehouse&action=update_import_cell', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            row_index: rowIndex,
                            field: 'cpu',
                            val: targetCpu
                        })
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
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

                            const totalEl = document.getElementById('stats-total');
                            const acceptedEl = document.getElementById('stats-accepted');
                            const rejectedEl = document.getElementById('stats-rejected');
                            if (totalEl) totalEl.textContent = res.total;
                            if (acceptedEl) acceptedEl.textContent = res.accepted;
                            if (rejectedEl) rejectedEl.textContent = res.rejected;

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
                        }
                    });
                    updatePromises.push(promise);
                }
            }
        }
    });

    await Promise.all(updatePromises);
}

document.addEventListener('click', () => {
    const menu = document.getElementById('cpu-bulk-menu');
    if (menu) {
        menu.style.display = 'none';
    }
});
