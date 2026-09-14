const Grid = {
    renderTableRows(rows, dictionary = {}) {
        const tbody = document.getElementById('audit-table-body');
        if (!tbody) return;

        if (tbody.rows.length === 1 && (tbody.rows[0].cells.length === 1 || tbody.rows[0].querySelector('td')?.getAttribute('colspan') === '8')) {
            tbody.innerHTML = '';
        }

        if (rows.length === 0) {
            if (tbody.rows.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                            No rows parsed. Try uploading a sheet.
                        </td>
                    </tr>`;
            }
            return;
        }

        rows.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.className = 'audit-row-item';

            let cleanItem = row.Item || '';

            tr.innerHTML = `
                <td style="text-align: center;"><input type="checkbox" class="cell-approve" checked style="width: auto; transform: scale(1.2); display: block; margin: 0 auto; cursor: pointer;"></td>
                <td><input type="date" value="${row.Date}" class="cell-date"></td>
                <td><input type="number" value="${row.QTY}" class="cell-qty" min="1"></td>
                <td><input type="text" value="${cleanItem}" class="cell-item" placeholder="Item Name"></td>
                <td><input type="text" value="${row.Serial || ''}" class="cell-serial" placeholder="Serial tag (appends)"></td>
                <td><input type="text" value="${row.Location}" class="cell-location" placeholder="Loc"></td>
                <td><textarea rows="1" class="cell-notes" placeholder="Notes">${row.Notes || ''}</textarea></td>
                <td style="text-align: center;">
                    <button class="btn-table-action" onclick="Grid.deleteRow(this)">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        this.updateActionButtons();

        if (document.getElementById('mode-selector')?.value === 'overlay') {
            this.renderOverlayGrid();
        }
    },

    updateActionButtons() {
        const tbody = document.getElementById('audit-table-body');
        const btn = document.getElementById('btn-validate-warehouse');
        if (btn && tbody) {
            const hasRows = tbody.querySelectorAll('.audit-row-item').length > 0;
            btn.disabled = !hasRows;
            btn.style.opacity = hasRows ? '1' : '0.5';
            btn.style.cursor = hasRows ? 'pointer' : 'not-allowed';
        }
    },

    addBlankRow() {
        const tbody = document.getElementById('audit-table-body');
        if (!tbody) return;

        if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
            tbody.innerHTML = '';
        }

        const today = new Date().toISOString().split('T')[0];
        const tr = document.createElement('tr');
        tr.className = 'audit-row-item';
        tr.innerHTML = `
            <td style="text-align: center;"><input type="checkbox" class="cell-approve" checked style="width: auto; transform: scale(1.2); display: block; margin: 0 auto; cursor: pointer;"></td>
            <td><input type="date" value="${today}" class="cell-date"></td>
            <td><input type="number" value="1" class="cell-qty" min="1"></td>
            <td><input type="text" value="" class="cell-item" placeholder="Item Name"></td>
            <td><input type="text" value="" class="cell-serial" placeholder="Serial tag (appends)"></td>
            <td><input type="text" value="" class="cell-location" placeholder="Loc"></td>
            <td><textarea rows="1" class="cell-notes" placeholder="Notes"></textarea></td>
            <td style="text-align: center;">
                <button class="btn-table-action" onclick="Grid.deleteRow(this)">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);

        this.updateActionButtons();

        if (document.getElementById('mode-selector')?.value === 'overlay') {
            this.renderOverlayGrid();
        }
    },

    deleteRow(button) {
        const tr = button.closest('tr');
        tr.remove();

        const tbody = document.getElementById('audit-table-body');
        if (tbody.rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                        No rows parsed. Click "+ Add Blank Row" to start adding manually.
                    </td>
                </tr>`;
        }

        this.updateActionButtons();

        if (document.getElementById('mode-selector')?.value === 'overlay') {
            this.renderOverlayGrid();
        }
    },

    toggleMode() {
        const mode = document.getElementById('mode-selector').value;
        const overlayContainer = document.getElementById('grid-overlay-container');
        const adjustmentControls = document.getElementById('overlay-adjustment-controls');
        const floatingAdd = document.getElementById('floating-add-row');

        if (mode === 'overlay') {
            overlayContainer.style.display = 'block';
            adjustmentControls.style.display = 'flex';
            if (floatingAdd) floatingAdd.style.display = 'flex';

            const tbody = document.getElementById('audit-table-body');
            const rows = document.querySelectorAll('.audit-row-item');
            if (rows.length === 0 || (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1)) {
                tbody.innerHTML = '';
                this.addBlankRow();
            }
            this.renderOverlayGrid();
            this.adjustOverlay();
        } else {
            overlayContainer.style.display = 'none';
            adjustmentControls.style.display = 'none';
            if (floatingAdd) floatingAdd.style.display = 'none';
        }
    },

    renderOverlayGrid() {
        const overlayBody = document.getElementById('overlay-grid-body');
        if (!overlayBody) return;
        overlayBody.innerHTML = '';

        const allRows = document.querySelectorAll('.audit-row-item');
        allRows.forEach((tr, index) => {
            const dateInput = tr.querySelector('.cell-date');
            const qtyInput = tr.querySelector('.cell-qty');
            const itemInput = tr.querySelector('.cell-item');
            const serialInput = tr.querySelector('.cell-serial');
            const locInput = tr.querySelector('.cell-location');
            const notesInput = tr.querySelector('.cell-notes');

            if (!dateInput || !qtyInput || !itemInput || !serialInput || !locInput || !notesInput) return;

            const overlayTr = document.createElement('tr');
            overlayTr.className = 'overlay-row';
            overlayTr.innerHTML = `
                <td><input type="text" class="overlay-input overlay-cell-date" value="${dateInput.value}"></td>
                <td><input type="number" class="overlay-input overlay-cell-qty" value="${qtyInput.value}"></td>
                <td><input type="text" class="overlay-input overlay-cell-item" value="${itemInput.value}"></td>
                <td><input type="text" class="overlay-input overlay-cell-serial" value="${serialInput.value}"></td>
                <td><input type="text" class="overlay-input overlay-cell-location" value="${locInput.value}"></td>
                <td><input type="text" class="overlay-input overlay-cell-notes" value="${notesInput.value}"></td>
            `;

            const overlayInputs = [
                overlayTr.querySelector('.overlay-cell-date'),
                overlayTr.querySelector('.overlay-cell-qty'),
                overlayTr.querySelector('.overlay-cell-item'),
                overlayTr.querySelector('.overlay-cell-serial'),
                overlayTr.querySelector('.overlay-cell-location'),
                overlayTr.querySelector('.overlay-cell-notes')
            ];

            overlayInputs.forEach((inp, colIndex) => {
                const targetInput = [dateInput, qtyInput, itemInput, serialInput, locInput, notesInput][colIndex];

                inp.oninput = (e) => { targetInput.value = e.target.value; };
                targetInput.oninput = (e) => { inp.value = e.target.value; };

                inp.addEventListener('keydown', (e) => {
                    const isLastRow = (index === allRows.length - 1);

                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (isLastRow) {
                            Grid.addBlankRow();
                            setTimeout(() => {
                                const newRows = document.querySelectorAll('.overlay-row');
                                const nextRow = newRows[newRows.length - 1];
                                const nextInp = nextRow.querySelectorAll('.overlay-input')[colIndex];
                                if (nextInp) {
                                    nextInp.focus();
                                    nextInp.select();
                                }
                            }, 50);
                        } else {
                            const nextRow = overlayTr.nextElementSibling;
                            if (nextRow) {
                                const nextInp = nextRow.querySelectorAll('.overlay-input')[colIndex];
                                if (nextInp) {
                                    nextInp.focus();
                                    nextInp.select();
                                }
                            }
                        }
                    }

                    if (e.key === 'Tab' && isLastRow && colIndex === overlayInputs.length - 1) {
                        Grid.addBlankRow();
                    }
                });
            });

            overlayBody.appendChild(overlayTr);
        });
    },

    adjustOverlay() {
        const offset = document.getElementById('slider-offset').value;
        const height = document.getElementById('slider-height').value;

        document.getElementById('label-offset').textContent = `${offset}px`;
        document.getElementById('label-height').textContent = `${height}px`;

        const container = document.getElementById('grid-overlay-container');
        if (container) {
            container.style.paddingTop = `${offset}px`;
        }

        const rows = document.querySelectorAll('.overlay-row td');
        rows.forEach(td => {
            td.style.height = `${height}px`;
        });
    },

    loadExistingCSV(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const text = e.target.result;
            const lines = text.split('\n');

            let loadedRows = [];
            for (let i = 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;

                let cols = [];
                let inQuotes = false;
                let current = '';
                for (let c = 0; c < line.length; c++) {
                    const char = line[c];
                    if (char === '"') {
                        inQuotes = !inQuotes;
                    } else if (char === ',' && !inQuotes) {
                        cols.push(current);
                        current = '';
                    } else {
                        current += char;
                    }
                }
                cols.push(current);

                const row = {
                    Date: cols[0] || new Date().toISOString().split('T')[0],
                    QTY: cols[1] || '1',
                    Item: cols[2] || '',
                    Serial: cols[3] || '',
                    Location: cols[4] || '',
                    Notes: cols[5] || '',
                    Confidence: 100
                };

                const serialMatch = /\(Serial:\s*([^\)]+)\)/i.exec(row.Item);
                if (serialMatch && !row.Serial) {
                    row.Serial = serialMatch[1];
                    row.Item = row.Item.replace(serialMatch[0], '').trim();
                }

                loadedRows.push(row);
            }

            if (loadedRows.length === 0) {
                window.showToast?.('No rows found in the CSV file.', 'warning');
                return;
            }

            (async () => {
                const normalized = await API.normalizeRows(loadedRows);
                Grid.renderTableRows(normalized);
                window.showToast?.(`Successfully loaded and normalized ${loadedRows.length} rows from CSV!`, 'success');
                event.target.value = '';
            })();
        };
        reader.readAsText(file);
    },

    downloadCSV() {
        const allRows = document.querySelectorAll('.audit-row-item');
        const checkedRows = Array.from(allRows).filter(tr => {
            const chk = tr.querySelector('.cell-approve');
            return chk && chk.checked;
        });

        if (checkedRows.length === 0) {
            window.showToast?.('No approved rows to download', 'error');
            return;
        }

        let csvContent = "Date,QTY,Item,Serial,Location,Notes\n";
        let isValid = true;

        checkedRows.forEach(tr => {
            const date = tr.querySelector('.cell-date').value;
            const qty = tr.querySelector('.cell-qty').value;
            let item = tr.querySelector('.cell-item').value.trim();
            let serial = tr.querySelector('.cell-serial').value.trim();
            const location = tr.querySelector('.cell-location').value.trim();
            const notes = tr.querySelector('.cell-notes').value.trim();

            if (!item || !location) {
                isValid = false;
            }

            if (serial) {
                if (item.toLowerCase().indexOf('serial:') === -1) {
                    item = `${item} (Serial: ${serial})`;
                }
                serial = '';
            }

            const escapeCSV = (val) => {
                if (val.indexOf(',') !== -1 || val.indexOf('"') !== -1 || val.indexOf('\n') !== -1) {
                    return `"${val.replace(/"/g, '""')}"`;
                }
                return val;
            };

            csvContent += `${date},${qty},${escapeCSV(item)},${escapeCSV(serial)},${escapeCSV(location)},${escapeCSV(notes)}\n`;
        });

        if (!isValid) {
            window.showToast?.('All approved rows must have an Item Name and Location', 'error');
            return;
        }

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `intake_audit_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    async sendToWarehouseImport() {
        const allRows = document.querySelectorAll('.audit-row-item');
        const checkedRows = Array.from(allRows).filter(tr => {
            const chk = tr.querySelector('.cell-approve');
            return chk && chk.checked;
        });

        if (checkedRows.length === 0) {
            window.showToast?.('No approved rows to send', 'error');
            return;
        }

        let csvContent = "Date,QTY,Item,Serial,Location,Notes\n";
        let isValid = true;

        checkedRows.forEach(tr => {
            const date = tr.querySelector('.cell-date').value;
            const qty = tr.querySelector('.cell-qty').value;
            let item = tr.querySelector('.cell-item').value.trim();
            let serial = tr.querySelector('.cell-serial').value.trim();
            const location = tr.querySelector('.cell-location').value.trim();
            const notes = tr.querySelector('.cell-notes').value.trim();

            if (!item || !location) {
                isValid = false;
            }

            if (serial) {
                if (item.toLowerCase().indexOf('serial:') === -1) {
                    item = `${item} (Serial: ${serial})`;
                }
                serial = '';
            }

            const escapeCSV = (val) => {
                if (val.indexOf(',') !== -1 || val.indexOf('"') !== -1 || val.indexOf('\n') !== -1) {
                    return `"${val.replace(/"/g, '""')}"`;
                }
                return val;
            };

            csvContent += `${date},${qty},${escapeCSV(item)},${escapeCSV(serial)},${escapeCSV(location)},${escapeCSV(notes)}\n`;
        });

        if (!isValid) {
            window.showToast?.('All approved rows must have an Item Name and Location', 'error');
            return;
        }

        try {
            window.showToast?.('Exporting to warehouse import...', 'success');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const file = new File([blob], 'temp_import.csv', { type: 'text/csv' });

            const formData = new FormData();
            formData.append('inventory_csv', file);

            const response = await fetch('../orders/index.php?view=import_warehouse', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                window.showToast?.('Redirecting to Warehouse Import preview...', 'success');
                setTimeout(() => {
                    window.parent.location.href = '../orders/index.php?view=import_warehouse';
                }, 800);
            } else {
                window.showToast?.('Failed to process CSV on server.', 'error');
            }
        } catch (e) {
            console.error('Error sending CSV to warehouse:', e);
            window.showToast?.('Network or system error occurred.', 'error');
        }
    }
};
window.Grid = Grid;
