/**
 * Storage & Donor Workbench Module
 * IQA Metal Warehouse Systems - Zero-Dependency ES6
 */

(function () {
    'use strict';

    window.TechWorkbench = window.TechWorkbench || {};

    const StorageWorkbench = {
        currentFilter: 'all_active',
        currentBrand: '',
        searchQuery: '',
        activeUnits: [],
        stats: {},

        init() {
            this.bindEvents();
            this.loadStorage();
        },

        bindEvents() {
            // Search Input with Debounce
            const searchInput = document.getElementById('storageSearchInput');
            if (searchInput) {
                let debounceTimer;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        this.searchQuery = e.target.value.trim();
                        this.loadStorage();
                    }, 250);
                });
            }

            // Brand Filter Dropdown
            const brandSelect = document.getElementById('storageBrandSelect');
            if (brandSelect) {
                brandSelect.addEventListener('change', (e) => {
                    this.currentBrand = e.target.value;
                    this.loadStorage();
                });
            }

            // Shelf / Rack Prefix Filter Dropdown
            const shelfSelect = document.getElementById('storageShelfSelect');
            if (shelfSelect) {
                shelfSelect.addEventListener('change', (e) => {
                    this.currentShelf = e.target.value;
                    this.loadStorage();
                });
            }

            // Stat Cards Click to Filter
            document.querySelectorAll('.storage-stat-card').forEach(card => {
                card.addEventListener('click', () => {
                    const filter = card.dataset.filter;
                    if (filter) {
                        this.setFilter(filter);
                    }
                });
            });

            // Forms
            const addForm = document.getElementById('addStorageForm');
            if (addForm) {
                addForm.addEventListener('submit', (e) => this.handleAddSubmit(e));
            }

            const harvestForm = document.getElementById('harvestPartForm');
            if (harvestForm) {
                harvestForm.addEventListener('submit', (e) => this.handleHarvestSubmit(e));
            }

            const promoteForm = document.getElementById('promoteGoodForm');
            if (promoteForm) {
                promoteForm.addEventListener('submit', (e) => this.handlePromoteSubmit(e));
            }

            const editForm = document.getElementById('editStorageForm');
            if (editForm) {
                editForm.addEventListener('submit', (e) => this.handleEditSubmit(e));
            }
        },

        setFilter(filter) {
            this.currentFilter = filter;
            document.querySelectorAll('.storage-stat-card').forEach(card => {
                card.classList.toggle('active', card.dataset.filter === filter);
            });
            this.loadStorage();
        },

        async loadStorage() {
            const tbody = document.getElementById('storageTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 40px; color: #64748b;">⏳ Loading storage units...</td></tr>';
            }

            let url = '../api/get_storage.php?';
            const params = new URLSearchParams();

            if (this.currentFilter === 'all_active') {
                params.set('status', 'In Storage');
            } else if (this.currentFilter === 'needs_screen') {
                params.set('status', 'In Storage');
                params.set('need', 'Screen');
            } else if (this.currentFilter === 'needs_battery') {
                params.set('status', 'In Storage');
                params.set('need', 'Battery');
            } else if (this.currentFilter === 'donors_screen') {
                params.set('status', 'In Storage');
                params.set('donor_part', 'screen');
                params.set('donor_model', '%'); // return any unit with good screen
            } else if (this.currentFilter === 'donors_battery') {
                params.set('status', 'In Storage');
                params.set('donor_part', 'battery');
                params.set('donor_model', '%'); // return any unit with good battery
            } else if (this.currentFilter === 'promoted') {
                params.set('status', 'Promoted to Good');
            } else if (this.currentFilter === 'harvested') {
                params.set('status', 'Harvested / Scrapped');
            } else {
                params.set('status', 'all');
            }

            if (this.currentBrand) {
                params.set('brand', this.currentBrand);
            }
            if (this.currentShelf) {
                params.set('prefix', this.currentShelf);
            }
            if (this.searchQuery) {
                params.set('search', this.searchQuery);
            }

            try {
                const res = await fetch(url + params.toString());
                const json = await res.json();

                if (json.success) {
                    this.activeUnits = json.data;
                    this.stats = json.stats;
                    this.renderStats();
                    this.renderTable();
                } else {
                    if (tbody) {
                        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: #dc2626; padding: 30px;">❌ Error: ${json.error || 'Failed to load'}</td></tr>`;
                    }
                }
            } catch (err) {
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: #dc2626; padding: 30px;">❌ Network error: ${err.message}</td></tr>`;
                }
            }
        },

        renderStats() {
            const s = this.stats || {};
            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val !== undefined ? val : '0';
            };

            setText('statTotalStorage', s.total_in_storage);
            setText('statNeedsScreen', s.needs_screen);
            setText('statNeedsBattery', s.needs_battery);
            setText('statDonorsScreen', s.donors_good_screen);
            setText('statDonorsBattery', s.donors_good_battery);
            setText('statPromoted', s.promoted_count);
            setText('statHarvested', s.harvested_count);
        },

        renderTable() {
            const tbody = document.getElementById('storageTableBody');
            if (!tbody) return;

            if (this.activeUnits.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="storage-empty-state">
                                <span>🗄️</span>
                                <h3>No Storage Units Found</h3>
                                <p>No units match the selected filters or search terms.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = this.activeUnits.map(u => {
                const screenPill = this.formatScreenPill(u.screen);
                const batteryPill = this.formatBatteryPill(u.battery);
                const isPromoted = u.status === 'Promoted to Good';
                const isHarvested = u.status === 'Harvested / Scrapped';

                return `
                    <tr data-id="${u.id}">
                        <td>
                            <span class="sku-badge" onclick="TechWorkbench.Storage.copySku('${u.sku}')" title="Click to copy SKU">
                                📋 ${this.escapeHtml(u.sku)}
                            </span>
                        </td>
                        <td style="color: #64748b; font-size: 0.82rem; white-space: nowrap;">
                            ${this.escapeHtml(u.date_added)}
                        </td>
                        <td>
                            <strong>${this.escapeHtml(u.brand)}</strong> 
                            ${u.series ? `<span style="color:#475569;">${this.escapeHtml(u.series)}</span>` : ''} 
                            <span style="font-weight:700; color:#1e293b;">${this.escapeHtml(u.model_num)}</span>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <div><small style="color:#64748b; font-weight:700;">SCR:</small> ${screenPill}</div>
                                <div><small style="color:#64748b; font-weight:700;">BAT:</small> ${batteryPill}</div>
                            </div>
                        </td>
                        <td>
                            ${u.issue ? `<span class="pill-condition pill-bad">${this.escapeHtml(u.issue)}</span>` : ''}
                            ${u.need ? `<span class="pill-condition pill-need" style="margin-top:4px; display:inline-block;">Need: ${this.escapeHtml(u.need)}</span>` : ''}
                        </td>
                        <td style="max-width: 280px; font-size: 0.82rem; color: #334155;">
                            ${this.escapeHtml(u.notes || '-')}
                        </td>
                        <td style="text-align: right;">
                            <div class="storage-row-actions">
                                ${!isPromoted && !isHarvested ? `
                                    <button type="button" class="btn-action-harvest" onclick="TechWorkbench.Storage.openHarvestModal(${u.id}, '${this.escapeHtml(u.sku)}', '${this.escapeHtml(u.brand)}', '${this.escapeHtml(u.model_num)}')" title="Harvest donor parts from this unit">
                                        🛠️ Harvest
                                    </button>
                                    <button type="button" class="btn-action-promote" onclick="TechWorkbench.Storage.openPromoteModal(${u.id}, '${this.escapeHtml(u.sku)}', '${this.escapeHtml(u.brand)}', '${this.escapeHtml(u.model_num)}', '${this.escapeHtml(u.series)}')" title="Promote repaired unit to Hardware Logs">
                                        ✅ Promote
                                    </button>
                                ` : `
                                    <span style="font-size: 0.78rem; font-weight:700; padding: 4px 8px; border-radius: 6px; background: #f1f5f9; color: #64748b;">
                                        ${this.escapeHtml(u.status)}
                                    </span>
                                `}
                                <button type="button" class="btn-action-edit" onclick="TechWorkbench.Storage.printLabel(${u.id}, 'sku_only')" title="Print 2x1 Thermal SKU Barcode Label">
                                    🖨️
                                </button>
                                <button type="button" class="btn-action-edit" onclick="TechWorkbench.Storage.openEditModal(${u.id})" title="Edit Details">
                                    ✏️
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        },

        formatScreenPill(val) {
            if (!val) return '<span style="color:#94a3b8;">-</span>';
            const vLower = val.toLowerCase();
            if (val === '✔' || vLower.includes('good') || vLower.includes('working')) {
                return '<span class="pill-condition pill-good">✔ Good Screen</span>';
            }
            if (val === '✘' || vLower.includes('bad') || vLower.includes('crack') || vLower.includes('flicker')) {
                return `<span class="pill-condition pill-bad">${this.escapeHtml(val)}</span>`;
            }
            if (vLower.includes('harvest') || vLower.includes('taken')) {
                return '<span class="pill-condition pill-harvested">Taken</span>';
            }
            return `<span class="pill-condition pill-warning">${this.escapeHtml(val)}</span>`;
        },

        formatBatteryPill(val) {
            if (!val) return '<span style="color:#94a3b8;">-</span>';
            const vLower = val.toLowerCase();
            if (val === '✔' || vLower.includes('good') || vLower.includes('included')) {
                return '<span class="pill-condition pill-good">✔ Good Bat</span>';
            }
            if (val === '✘' || vLower.includes('bad') || vLower.includes('no bat')) {
                return `<span class="pill-condition pill-bad">${this.escapeHtml(val)}</span>`;
            }
            if (vLower.includes('harvest') || vLower.includes('taken')) {
                return '<span class="pill-condition pill-harvested">Taken</span>';
            }
            return `<span class="pill-condition pill-warning">${this.escapeHtml(val)}</span>`;
        },

        // --- Modals ---
        openAddModal() {
            const m = document.getElementById('addStorageModal');
            if (m) m.classList.add('open');
            TechWorkbench.Audio?.playClick();
        },

        closeAddModal() {
            const m = document.getElementById('addStorageModal');
            if (m) m.classList.remove('open');
        },

        openHarvestModal(id, sku, brand, model) {
            document.getElementById('harvestUnitId').value = id;
            document.getElementById('harvestUnitTitle').textContent = `${sku} (${brand} ${model})`;
            const m = document.getElementById('harvestPartModal');
            if (m) m.classList.add('open');
            TechWorkbench.Audio?.playClick();
        },

        closeHarvestModal() {
            const m = document.getElementById('harvestPartModal');
            if (m) m.classList.remove('open');
        },

        openPromoteModal(id, sku, brand, model, series) {
            document.getElementById('promoteUnitId').value = id;
            document.getElementById('promoteUnitTitle').textContent = `${sku} (${brand} ${model})`;
            document.getElementById('promoteSerial').value = sku;
            const m = document.getElementById('promoteGoodModal');
            if (m) m.classList.add('open');
            TechWorkbench.Audio?.playClick();
        },

        closePromoteModal() {
            const m = document.getElementById('promoteGoodModal');
            if (m) m.classList.remove('open');
        },

        openEditModal(id) {
            const unit = this.activeUnits.find(u => u.id == id);
            if (!unit) return;

            document.getElementById('editUnitId').value = unit.id;
            document.getElementById('editUnitSku').textContent = unit.sku;
            const editSkuInput = document.getElementById('editSku');
            if (editSkuInput) editSkuInput.value = unit.sku;
            document.getElementById('editBrand').value = unit.brand;
            document.getElementById('editModelNum').value = unit.model_num;
            document.getElementById('editSeries').value = unit.series || '';
            document.getElementById('editBattery').value = unit.battery || '';
            document.getElementById('editScreen').value = unit.screen || '';
            document.getElementById('editIssue').value = unit.issue || '';
            document.getElementById('editNeed').value = unit.need || '';
            document.getElementById('editStatus').value = unit.status || 'In Storage';
            document.getElementById('editNotes').value = unit.notes || '';

            const m = document.getElementById('editStorageModal');
            if (m) m.classList.add('open');
            TechWorkbench.Audio?.playClick();
        },

        closeEditModal() {
            const m = document.getElementById('editStorageModal');
            if (m) m.classList.remove('open');
        },

        // --- Form Handlers ---
        async handleAddSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const submitter = e.submitter || document.activeElement;
            const isPrint = submitter && (
                submitter.value === 'save_and_print' || 
                (submitter.getAttribute('name') === 'print_action' && submitter.value === 'save_and_print') ||
                submitter.id === 'btnIngestPrint' ||
                (submitter.getAttribute('name') === 'print_label' && submitter.value === '1')
            );
            const formatRadio = form.querySelector('input[name="print_format_choice"]:checked');
            const printFormat = formatRadio ? formatRadio.value : 'sku_only';

            const data = new FormData(form);
            const submitBtns = form.querySelectorAll('button[type="submit"]');
            submitBtns.forEach(b => b.disabled = true);

            try {
                const res = await fetch('../api/add_storage.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    TechWorkbench.Audio?.playSuccess();
                    this.closeAddModal();
                    form.reset();
                    this.loadStorage();

                    // Automatically print the thermal barcode sticker if requested
                    if (isPrint && json.id) {
                        this.printLabel(json.id, printFormat);
                    }
                } else {
                    TechWorkbench.Audio?.playError();
                    alert(json.error || 'Failed to add storage unit.');
                }
            } catch (err) {
                TechWorkbench.Audio?.playError();
                alert('Network error: ' + err.message);
            } finally {
                submitBtns.forEach(b => b.disabled = false);
            }
        },

        async handleHarvestSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'harvest');
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('../api/update_storage.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    TechWorkbench.Audio?.playSuccess();
                    this.closeHarvestModal();
                    this.loadStorage();
                } else {
                    TechWorkbench.Audio?.playError();
                    alert(json.error || 'Failed to harvest part.');
                }
            } catch (err) {
                TechWorkbench.Audio?.playError();
                alert('Network error: ' + err.message);
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        async handlePromoteSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            data.append('action', 'promote_to_good');
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('../api/promote_storage.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    TechWorkbench.Audio?.playSuccess();
                    this.closePromoteModal();
                    alert(`✅ ${json.message}`);
                    this.loadStorage();
                } else {
                    TechWorkbench.Audio?.playError();
                    alert(json.error || 'Failed to promote unit.');
                }
            } catch (err) {
                TechWorkbench.Audio?.playError();
                alert('Network error: ' + err.message);
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        async handleEditSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const data = new FormData(form);
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('../api/update_storage.php', { method: 'POST', body: data });
                const json = await res.json();
                if (json.success) {
                    TechWorkbench.Audio?.playSuccess();
                    this.closeEditModal();
                    this.loadStorage();
                } else {
                    TechWorkbench.Audio?.playError();
                    alert(json.error || 'Failed to update unit.');
                }
            } catch (err) {
                TechWorkbench.Audio?.playError();
                alert('Network error: ' + err.message);
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        printLabel(id, format = 'sku_only') {
            if (!id) return;
            const url = `print_storage_label.php?id=${encodeURIComponent(id)}&format=${encodeURIComponent(format)}&autoprint=1`;
            const w = window.open(url, '_blank', 'width=460,height=380,resizable=yes,scrollbars=yes');
            if (w) w.focus();
        },

        printCurrentEdit() {
            const idInput = document.getElementById('editUnitId');
            const id = idInput ? idInput.value : null;
            if (id) {
                this.printLabel(id, 'sku_only');
            }
        },

        copySku(sku) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(sku).then(() => {
                    TechWorkbench.Audio?.playClick();
                    if (TechWorkbench.Clipboard) {
                        TechWorkbench.Clipboard.showToast(`📋 Copied SKU: <strong>${sku}</strong>`);
                    } else {
                        alert(`Copied SKU: ${sku}`);
                    }
                });
            }
        },

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    TechWorkbench.Storage = StorageWorkbench;

    document.addEventListener('DOMContentLoaded', () => {
        StorageWorkbench.init();
    });
})();
