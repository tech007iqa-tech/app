/**
 * Warehouse Form & Intake Module
 * Handles sidebar stock registration, autocomplete datalists, CPU/Gen chips, session counting, and clone-last persistence.
 */

/**
 * Fills the registration form with data from the last submission
 */
function fillLastEnteredData() {
    const raw = localStorage.getItem('wh_last_entry');
    if (!raw) return;

    const data = JSON.parse(raw);
    const form = document.getElementById('wh-main-form');
    if (!form) return;

    // Direct mapping for common fields
    const fields = ['brand', 'model', 'quantity', 'price', 'condition', 'notes', 'cpu', 'gpu', 'ram', 'storage', 'battery', 'windows', 'series', 'gen', 'cpu_gen', 'gaming_category', 'bios'];

    fields.forEach(f => {
        if (form[f] && data[f] !== undefined) {
            form[f].value = data[f];
        }
    });

    // Trigger UI updates for specific sectors (like Gaming category toggle)
    if (typeof toggleGamingFields === 'function') toggleGamingFields();
    if (typeof toggleBiosState === 'function') toggleBiosState();

    // Success micro-feedback on the button
    const btn = document.getElementById('btn-clone-last');
    if (btn) {
        const orig = btn.innerText;
        btn.innerText = '✅ Cloned';
        setTimeout(() => btn.innerText = orig, 1000);
    }

    // Sync chips after cloning
    syncCpuGenChips();

    // Update select colors
    updateSelectColors();
}

/**
 * Initializes CPU/Gen chips click events
 */
function initCpuGenChips() {
    const chips = document.querySelectorAll('#cpu-gen-chips .chip-item');
    const input = document.getElementById('wh-spec-cpu-gen');
    if (!chips.length || !input) return;

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            input.value = chip.getAttribute('data-value');
            syncCpuGenChips();
            input.dispatchEvent(new Event('input'));
        });
    });

    // Also sync when the user types manually
    input.addEventListener('input', () => {
        syncCpuGenChips();
    });
}

/**
 * Syncs the active state of chips with the hidden input value
 */
function syncCpuGenChips() {
    const input = document.getElementById('wh-spec-cpu-gen');
    const chips = document.querySelectorAll('#cpu-gen-chips .chip-item');
    if (!input || !chips.length) return;

    chips.forEach(c => {
        if (c.getAttribute('data-value') === input.value) {
            c.classList.add('active');
        } else {
            c.classList.remove('active');
        }
    });
}

/**
 * Initializes the brand/model/series datalists using the shared inventory data.
 */
function initWarehouseDatalists() {
    const brandIn = document.getElementById('wh-brand');
    const modelIn = document.getElementById('wh-model');
    const modelDl = document.getElementById('model-options');
    const brandDl = document.getElementById('brand-options');
    const seriesDl = document.getElementById('series-options');

    // Determine target inventory based on active sector
    const state = typeof getWarehouseState === 'function' ? getWarehouseState() : {};
    let targetInventory = window.IQA_LaptopInventory || {};
    if (state.activeSector === 'Gaming') targetInventory = window.IQA_GamingInventory || {};
    if (state.activeSector === 'Desktops') targetInventory = window.IQA_DesktopInventory || {};

    // Populate Brands
    if (brandDl) {
        brandDl.innerHTML = Object.keys(targetInventory).map(b => `<option value="${b}">`).join('');
    }

    if (brandIn) {
        brandIn.addEventListener('change', (e) => {
            const selectedBrand = e.target.value;
            const data = targetInventory[selectedBrand];

            if (modelIn) modelIn.value = '';
            if (modelDl) modelDl.innerHTML = '';
            if (seriesDl) seriesDl.innerHTML = '';

            if (data) {
                // Smart Handling for Desktops: User usually types everything in 'Model'
                if (state.activeSector === 'Desktops' && modelDl) {
                    const allOptions = [...(data.models || []), ...(data.series || [])];
                    modelDl.innerHTML = allOptions.map(m => `<option value="${m}">`).join('');
                } else {
                    // Standard Split (Laptops/Gaming)
                    if (modelDl) modelDl.innerHTML = (data.models || []).map(m => `<option value="${m}">`).join('');
                    if (seriesDl) seriesDl.innerHTML = (data.series || []).map(s => `<option value="${s}">`).join('');
                }
            }
        });
    }

    if (modelIn) {
        modelIn.addEventListener('input', (e) => {
            if (brandIn && brandIn.value === '') {
                const val = e.target.value.toLowerCase();
                if (val.length < 3) return;

                for (const [brand, data] of Object.entries(targetInventory)) {
                    const found = (data.models || []).some(m => m.toLowerCase() === val) ||
                        (data.series || []).some(s => s.toLowerCase() === val);

                    if (found) {
                        brandIn.value = brand;
                        brandIn.dispatchEvent(new Event('change'));
                        break;
                    }
                }
            }
            highlightExistingMatches();
        });
    }

    if (brandIn) {
        brandIn.addEventListener('input', highlightExistingMatches);
        brandIn.addEventListener('change', highlightExistingMatches);
    }
}

/**
 * Highlights rows in the table that match the current Brand and Model in the form
 */
function highlightExistingMatches() {
    const brandEl = document.getElementById('wh-brand');
    const modelEl = document.getElementById('wh-model');
    if (!brandEl || !modelEl) return;

    const brand = brandEl.value.toLowerCase().trim();
    const model = modelEl.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.inventory-card');

    cards.forEach(card => {
        const cardBrand = (card.getAttribute('data-brand') || '').toLowerCase();
        const cardModel = (card.getAttribute('data-model') || '').toLowerCase();

        card.classList.remove('match-highlight');

        if (brand && model) {
            if (cardBrand.includes(brand) && cardModel.includes(model)) {
                card.classList.add('match-highlight');
            }
        }
    });
}

/**
 * Toggles visibility and labels of gaming-specific fields based on category
 */
function toggleGamingFields() {
    const cat = document.getElementById('wh-gaming-cat');
    const pcFields = document.getElementById('wh-gaming-pc-fields');
    const specLabel = document.getElementById('wh-gaming-spec-label');
    const seriesIn = document.getElementById('wh-series');
    const ramIn = document.getElementById('wh-ram');
    const storageIn = document.getElementById('wh-storage');

    if (!cat) return;

    const val = cat.value;

    if (pcFields) pcFields.style.display = (val === 'PC' ? 'block' : 'none');

    if (!specLabel || !seriesIn || !ramIn || !storageIn) return;

    ramIn.style.display = 'block';
    storageIn.style.display = 'block';
    seriesIn.placeholder = 'Series / Edition';
    specLabel.innerText = 'Specs / Series';

    if (val === 'Consoles') {
        specLabel.innerText = 'Series / Edition';
        seriesIn.placeholder = 'e.g. Slim / Pro / Disc / Digital';
        ramIn.placeholder = 'Color / Region';
        storageIn.placeholder = 'Capacity (1TB/512GB)';
    } else if (val === 'Controllers') {
        specLabel.innerText = 'Controller Specs';
        seriesIn.placeholder = 'e.g. DualSense / Elite';
        ramIn.placeholder = 'Color (Midnight Black)';
        storageIn.style.display = 'none';
    } else if (val === 'Games') {
        specLabel.innerText = 'Game Edition';
        seriesIn.placeholder = 'e.g. Deluxe / Steelbook';
        ramIn.style.display = 'none';
        storageIn.style.display = 'none';
    } else if (val === 'PC') {
        specLabel.innerText = 'Additional Specs';
    }
}

/**
 * Toggles the visibility of the BIOS State input group based on current Condition select value.
 */
function toggleBiosState() {
    const condSelect = document.getElementById('wh-condition');
    const biosGroup = document.getElementById('wh-bios-state-group');
    if (!condSelect || !biosGroup) return;

    const val = condSelect.value;
    if (val === 'A Grade' || val === 'B Grade') {
        biosGroup.style.display = 'flex';
    } else {
        biosGroup.style.display = 'none';
        const biosSelect = document.getElementById('wh-spec-bios');
        if (biosSelect) biosSelect.value = '';
    }
}

/**
 * Dynamic ghost suffix helper for RAM and Storage inputs
 */
function initGhostSuffixes() {
    const ramInput = document.getElementById('wh-spec-ram');
    const storageInput = document.getElementById('wh-spec-storage');

    if (ramInput) {
        let dl = document.getElementById('ram-ghost-options');
        if (!dl) {
            dl = document.createElement('datalist');
            dl.id = 'ram-ghost-options';
            document.body.appendChild(dl);
            ramInput.setAttribute('list', 'ram-ghost-options');
        }

        ramInput.addEventListener('keypress', (e) => {
            if (e.key < '0' || e.key > '9') {
                e.preventDefault();
            }
        });

        ramInput.addEventListener('paste', (e) => {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^\d+$/.test(text)) {
                e.preventDefault();
            }
        });

        ramInput.addEventListener('input', () => {
            const val = ramInput.value.trim();
            const digits = val.replace(/[^0-9]/g, '');
            dl.innerHTML = digits ? `<option value="${digits}GB">` : '';
        });

        ramInput.addEventListener('blur', () => {
            const val = ramInput.value.trim();
            if (/^\d+$/.test(val)) {
                ramInput.value = val + 'GB';
            }
        });
    }

    if (storageInput) {
        let dl = document.getElementById('storage-ghost-options');
        if (!dl) {
            dl = document.createElement('datalist');
            dl.id = 'storage-ghost-options';
            document.body.appendChild(dl);
            storageInput.setAttribute('list', 'storage-ghost-options');
        }

        storageInput.addEventListener('keypress', (e) => {
            if (e.key < '0' || e.key > '9') {
                e.preventDefault();
            }
        });

        storageInput.addEventListener('paste', (e) => {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^\d+$/.test(text)) {
                e.preventDefault();
            }
        });

        storageInput.addEventListener('input', () => {
            const val = storageInput.value.trim();
            const digits = val.replace(/[^0-9]/g, '');
            if (digits) {
                dl.innerHTML = `
                    <option value="${digits}GB">
                    <option value="${digits}TB">
                    <option value="${digits}PB">
                `;
            } else {
                dl.innerHTML = '';
            }
        });

        storageInput.addEventListener('blur', () => {
            const val = storageInput.value.trim();
            if (/^\d+$/.test(val)) {
                storageInput.value = val + 'GB';
            }
        });
    }
}

function updateSelectColors() {
    ['wh-spec-cpu', 'wh-condition'].forEach(id => {
        const selectEl = document.getElementById(id);
        if (!selectEl) return;
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        if (selectedOpt) {
            selectEl.style.backgroundColor = selectedOpt.style.backgroundColor || '';
            selectEl.style.color = selectedOpt.style.color || '';
        }
    });
}

/**
 * Handles editing an existing warehouse item
 */
function editWarehouseItem(item) {
    const form = document.getElementById('wh-main-form');
    const title = document.getElementById('wh-form-title');
    const action = document.getElementById('wh-form-action');
    const editId = document.getElementById('wh-edit-id');
    const submitBtn = document.getElementById('wh-submit-btn');
    const cancelBtn = document.getElementById('wh-cancel-edit');

    if (!form || !title || !action || !editId || !submitBtn || !cancelBtn) return;

    title.innerText = '📝 Update Inventory';
    action.value = 'edit_inventory';
    editId.value = item.id;
    const lastUpdatedInput = document.getElementById('wh-last-updated');
    if (lastUpdatedInput) lastUpdatedInput.value = item.updated_at || '';

    submitBtn.innerText = '💾 Save Changes';
    cancelBtn.style.display = 'block';

    form.brand.value = item.brand;
    form.model.value = item.model;
    form.quantity.value = item.quantity;
    form.price.value = item.price || '0.00';

    const specs = JSON.parse(item.specs_json || '{}');
    if (form.condition) form.condition.value = specs.condition || 'Used';
    if (form.notes) form.notes.value = specs.notes || '';

    if (item.sector === 'Laptops') {
        if (form.cpu) form.cpu.value = specs.cpu || '';
        if (form.gpu) form.gpu.value = specs.gpu || '';
        if (form.ram) form.ram.value = specs.ram || '';
        if (form.storage) form.storage.value = specs.storage || '';
        if (form.battery) form.battery.value = specs.battery || '';
        if (form.windows) form.windows.value = specs.windows || '';
        if (form.gen) form.gen.value = specs.gen || '';
        if (form.series) form.series.value = specs.series || '';
        if (form.bios) form.bios.value = specs.bios || '';
    } else if (item.sector === 'Gaming') {
        if (form.gaming_category) {
            form.gaming_category.value = specs.category || 'PC';
            toggleGamingFields();
        }
        if (form.series) form.series.value = specs.series || '';
        if (form.ram) form.ram.value = specs.ram || '';
        if (form.storage) form.storage.value = specs.storage || '';
        if (form.cpu) form.cpu.value = specs.cpu || '';
        if (form.gpu) form.gpu.value = specs.gpu || '';
    } else if (item.sector === 'Desktops') {
        if (form.cpu_gen) {
            form.cpu_gen.value = specs.cpu_gen || '';
            syncCpuGenChips();
        }
    }

    if (typeof toggleBiosState === 'function') toggleBiosState();
    updateSelectColors();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Resets the warehouse form back to 'Add' mode
 */
function resetWarehouseForm() {
    const form = document.getElementById('wh-main-form');
    const title = document.getElementById('wh-form-title');
    const action = document.getElementById('wh-form-action');
    const editId = document.getElementById('wh-edit-id');
    const submitBtn = document.getElementById('wh-submit-btn');
    const cancelBtn = document.getElementById('wh-cancel-edit');

    if (!form) return;

    form.reset();
    title.innerText = '📥 Register Stock';
    action.value = 'add_inventory';
    editId.value = '';
    const lastUpdatedInput = document.getElementById('wh-last-updated');
    if (lastUpdatedInput) lastUpdatedInput.value = '';

    submitBtn.innerText = '📥 Add to Stock';
    cancelBtn.style.display = 'none';

    if (typeof toggleGamingFields === 'function') toggleGamingFields();
    if (typeof toggleBiosState === 'function') toggleBiosState();
    syncCpuGenChips();
    updateSelectColors();
}

/**
 * Initializes the session counter from sessionStorage
 */
function initSessionCounter() {
    const counter = document.getElementById('session-counter');
    const valSpan = document.getElementById('session-count-val');
    if (!counter || !valSpan) return;

    let count = parseInt(sessionStorage.getItem('wh_session_added') || '0', 10);
    if (count > 0) {
        valSpan.innerText = count;
        counter.style.display = 'block';

        const lastItemRaw = sessionStorage.getItem('wh_session_last_item');
        if (lastItemRaw) {
            const lastItem = JSON.parse(lastItemRaw);
            const infoDiv = document.getElementById('session-last-item-info');
            const modelSeriesSpan = document.getElementById('session-last-model-series');
            const qtySpan = document.getElementById('session-last-qty');
            const timeSpan = document.getElementById('session-last-time');

            if (infoDiv && modelSeriesSpan && qtySpan && timeSpan) {
                modelSeriesSpan.innerText = lastItem.model + (lastItem.series ? ' ' + lastItem.series : '');
                qtySpan.innerText = lastItem.quantity;
                timeSpan.innerText = lastItem.time;
                infoDiv.style.display = 'block';
            }
        }
    }
}

/**
 * Increments the session counter and saves to sessionStorage
 */
function incrementSessionCounter() {
    let count = parseInt(sessionStorage.getItem('wh_session_added') || '0', 10);
    count++;
    sessionStorage.setItem('wh_session_added', count);

    const valSpan = document.getElementById('session-count-val');
    if (valSpan) valSpan.innerText = count;

    const counter = document.getElementById('session-counter');
    if (counter) counter.style.display = 'block';

    const lastEntryRaw = localStorage.getItem('wh_last_entry');
    if (lastEntryRaw) {
        const lastEntry = JSON.parse(lastEntryRaw);
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const timeStr = `${hours}:${minutes} ${ampm}`;

        const lastItemData = {
            model: lastEntry.model || 'Unknown',
            series: lastEntry.series || '',
            quantity: lastEntry.quantity || 1,
            time: timeStr
        };

        sessionStorage.setItem('wh_session_last_item', JSON.stringify(lastItemData));

        const infoDiv = document.getElementById('session-last-item-info');
        const modelSeriesSpan = document.getElementById('session-last-model-series');
        const qtySpan = document.getElementById('session-last-qty');
        const timeSpan = document.getElementById('session-last-time');

        if (infoDiv && modelSeriesSpan && qtySpan && timeSpan) {
            modelSeriesSpan.innerText = lastItemData.model + (lastItemData.series ? ' ' + lastItemData.series : '');
            qtySpan.innerText = lastItemData.quantity;
            timeSpan.innerText = lastItemData.time;
            infoDiv.style.display = 'block';
        }
    }
}
