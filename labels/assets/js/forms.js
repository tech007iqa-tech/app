/**
 * labels/assets/js/forms.js
 * Modular Form Controller: One-Touch Presets, Brand/Spec Pills, Live 2"x1" Thermal Mockup, and Instant Print Flow.
 */
'use strict';

// ── 1. GLOBAL PRESET & MODE HANDLERS ─────────────────────────────────────────

window.setCpuCores = function(val) {
    const display = document.getElementById('cpu_cores_display');
    const suffix = document.getElementById('cpu_cores_suffix');
    const F = window.HW_FIELDS || {};
    const hidden = document.getElementById(F.CPU_CORES || 'cpu_cores');

    if (val === null || val === undefined || val === '') {
        if (display) display.value = '';
        if (suffix) suffix.style.display = 'none';
        if (hidden) hidden.value = '';
        return;
    }

    const numStr = String(val).replace(/\D/g, '');
    if (numStr) {
        if (display) display.value = numStr;
        const n = parseInt(numStr, 10);
        const sfx = (n === 1 ? 'Core' : 'Cores');
        if (suffix) {
            suffix.textContent = sfx;
            suffix.style.display = 'inline-flex';
        }
        if (hidden) hidden.value = numStr + ' ' + sfx;
    } else {
        if (display) display.value = '';
        if (suffix) suffix.style.display = 'none';
        if (hidden) hidden.value = '';
    }
};

window.setCpuSpeed = function(val) {
    const display = document.getElementById('cpu_speed_display');
    const suffix = document.getElementById('cpu_speed_suffix');
    const F = window.HW_FIELDS || {};
    const hidden = document.getElementById(F.CPU_SPEED || 'cpu_speed');

    if (val === null || val === undefined || val === '') {
        if (display) display.value = '';
        if (suffix) suffix.style.display = 'none';
        if (hidden) hidden.value = '';
        return;
    }

    const match = String(val).match(/[0-9]+(\.[0-9]+)?/);
    if (match) {
        if (display) display.value = match[0];
        if (suffix) {
            suffix.textContent = 'GHz';
            suffix.style.display = 'inline-flex';
        }
        if (hidden) hidden.value = match[0] + ' GHz';
    } else {
        if (display) display.value = '';
        if (suffix) suffix.style.display = 'none';
        if (hidden) hidden.value = '';
    }
};

window.applyPreset = function(presetId) {
    if (!window.IQA_Catalog || !window.IQA_Catalog.presets) return;
    const preset = window.IQA_Catalog.presets.find(p => p.id === presetId);
    if (!preset) return;

    const data = preset.data;
    const F = window.HW_FIELDS || {};

    if (data.brand) selectBrandPill(data.brand);

    const modelInput = document.getElementById(F.MODEL || 'model');
    const seriesInput = document.getElementById(F.SERIES || 'series');
    const cpuGenInput = document.getElementById(F.CPU_GEN || 'cpu_gen');
    const cpuSpecsHidden = document.getElementById(F.CPU_SPECS || 'cpu_specs');
    const cpuSpecsMain = document.getElementById('cpu_specs_main');
    const cpuPrefixDisplay = document.getElementById('cpu_prefix_display');
    const coresInput = document.getElementById(F.CPU_CORES || 'cpu_cores');
    const speedInput = document.getElementById(F.CPU_SPEED || 'cpu_speed');
    const ramInput = document.getElementById(F.RAM || 'ram');
    const storageInput = document.getElementById(F.STORAGE || 'storage');
    const descSelect = document.getElementById(F.DESCRIPTION || 'description');
    const statusSelect = document.getElementById(F.STATUS || 'status');
    const biosSelect = document.getElementById(F.BIOS_STATE || 'bios_state');
    const battSelect = document.getElementById(F.BATTERY || 'battery');
    const gpuInput = document.getElementById(F.GPU || 'gpu');
    const osInput = document.getElementById(F.OS_VERSION || 'os_version');

    if (modelInput && data.model) modelInput.value = data.model;
    if (seriesInput && data.series) seriesInput.value = data.series;
    if (cpuGenInput && data.cpu_gen) cpuGenInput.value = data.cpu_gen;

    if (data.cpu_specs) {
        if (cpuSpecsHidden) cpuSpecsHidden.value = data.cpu_specs;
        if (cpuSpecsMain) {
            if (data.cpu_specs.includes('-')) {
                const parts = data.cpu_specs.split('-');
                if (cpuPrefixDisplay) cpuPrefixDisplay.textContent = parts[0] + '-';
                cpuSpecsMain.value = parts.slice(1).join('-');
            } else {
                if (cpuPrefixDisplay) cpuPrefixDisplay.textContent = '';
                cpuSpecsMain.value = data.cpu_specs;
            }
        }
    }

    if (data.cpu_cores !== undefined) setCpuCores(data.cpu_cores);
    if (data.cpu_speed !== undefined) setCpuSpeed(data.cpu_speed);

    if (data.ram) selectSpecPill('ram', data.ram);
    if (data.storage) selectSpecPill('storage', data.storage);
    if (descSelect && data.description) descSelect.value = data.description;
    if (statusSelect && data.status) statusSelect.value = data.status;
    if (biosSelect && data.bios_state) biosSelect.value = data.bios_state;
    if (battSelect && data.battery !== undefined) battSelect.value = data.battery;
    if (gpuInput && data.gpu) gpuInput.value = data.gpu;
    if (osInput && data.os_version) osInput.value = data.os_version;

    updateLivePreview();
    Toast.success(`Loaded "${preset.title}" preset`);
};

window.switchFormMode = function(mode) {
    const advSection = document.getElementById('advancedTechSection');
    const btnSimple = document.getElementById('btnModeSimple');
    const btnAdv = document.getElementById('btnModeAdvanced');

    if (mode === 'advanced') {
        if (advSection) advSection.style.display = 'block';
        if (btnAdv) btnAdv.classList.add('active');
        if (btnSimple) btnSimple.classList.remove('active');
    } else {
        if (advSection) advSection.style.display = 'none';
        if (btnSimple) btnSimple.classList.add('active');
        if (btnAdv) btnAdv.classList.remove('active');
    }
};

window.selectBrandPill = function(brandName) {
    const F = window.HW_FIELDS || {};
    const brandSelect = document.getElementById(F.BRAND || 'brand');
    if (brandSelect) {
        brandSelect.value = brandName;
        brandSelect.dispatchEvent(new Event('change'));
    }

    document.querySelectorAll('#brandPillsContainer .brand-pill').forEach(btn => {
        btn.classList.toggle('selected', btn.dataset.brand === brandName);
    });

    // Populate Model Datalist
    const modelDatalist = document.getElementById('model-options');
    const seriesDatalist = document.getElementById('series-options');
    if (modelDatalist && window.IQA_Catalog && window.IQA_Catalog.data[brandName]) {
        const brandObj = window.IQA_Catalog.data[brandName];
        modelDatalist.innerHTML = brandObj.models.map(m => `<option value="${m}">`).join('');
    }

    updateLivePreview();
};

window.selectSpecPill = function(type, val) {
    const F = window.HW_FIELDS || {};
    if (type === 'ram') {
        const input = document.getElementById(F.RAM || 'ram');
        if (input) input.value = val;
        document.querySelectorAll('#ramPillsContainer .spec-pill').forEach(p => {
            p.classList.toggle('selected', p.textContent.trim() === val);
        });
    } else if (type === 'storage') {
        const input = document.getElementById(F.STORAGE || 'storage');
        if (input) input.value = val;
        document.querySelectorAll('#storagePillsContainer .spec-pill').forEach(p => {
            p.classList.toggle('selected', p.textContent.trim() === val);
        });
    }
    updateLivePreview();
};

window.switchPreviewTab = function(tab) {
    const labelA = document.getElementById('mockupLabelA');
    const labelB = document.getElementById('mockupLabelB');
    const tabBoth = document.getElementById('tabPrevBoth');
    const tabA = document.getElementById('tabPrevA');
    const tabB = document.getElementById('tabPrevB');

    [tabBoth, tabA, tabB].forEach(t => t && t.classList.remove('active'));

    if (tab === 'both') {
        if (labelA) labelA.style.display = 'flex';
        if (labelB) labelB.style.display = 'flex';
        if (tabBoth) tabBoth.classList.add('active');
    } else if (tab === 'a') {
        if (labelA) labelA.style.display = 'flex';
        if (labelB) labelB.style.display = 'none';
        if (tabA) tabA.classList.add('active');
    } else if (tab === 'b') {
        if (labelA) labelA.style.display = 'none';
        if (labelB) labelB.style.display = 'flex';
        if (tabB) tabB.classList.add('active');
    }
};

// ── 2. LIVE PREVIEW UPDATE ENGINE ────────────────────────────────────────────

window.updateLivePreview = function() {
    const F = window.HW_FIELDS || {};

    const elBrand = document.getElementById(F.BRAND || 'brand');
    const elModel = document.getElementById(F.MODEL || 'model');
    const elSeries = document.getElementById(F.SERIES || 'series');
    const elCpuGen = document.getElementById(F.CPU_GEN || 'cpu_gen');
    const elCpuSpecs = document.getElementById(F.CPU_SPECS || 'cpu_specs');
    const elCpuMain = document.getElementById('cpu_specs_main');
    const elCpuPrefix = document.getElementById('cpu_prefix_display');
    const elRam = document.getElementById(F.RAM || 'ram');
    const elStorage = document.getElementById(F.STORAGE || 'storage');
    const elLoc = document.getElementById(F.LOCATION || 'warehouse_location');
    const elCond = document.getElementById(F.DESCRIPTION || 'description');
    const elSN = document.getElementById(F.SERIAL_NUMBER || 'serial_number');
    const elBatt = document.getElementById(F.BATTERY || 'battery');
    const elGpu = document.getElementById(F.GPU || 'gpu');
    const elOs = document.getElementById(F.OS_VERSION || 'os_version');
    const elBios = document.getElementById(F.BIOS_STATE || 'bios_state');

    const brand = elBrand ? elBrand.value.trim() : '';
    const model = elModel ? elModel.value.trim() : '';
    const series = elSeries ? elSeries.value.trim() : '';
    const cpuGen = elCpuGen ? elCpuGen.value.trim() : '';
    const prefix = elCpuPrefix ? elCpuPrefix.textContent.trim() : '';
    const cpuMain = elCpuMain ? elCpuMain.value.trim() : '';
    const cpuSpecs = (prefix + cpuMain) || (elCpuSpecs ? elCpuSpecs.value.trim() : '');

    const ram = elRam ? elRam.value.trim() : '';
    const storage = elStorage ? elStorage.value.trim() : '';
    const loc = elLoc ? elLoc.value.trim() : '';
    const cond = elCond ? elCond.value.trim() : 'UNTESTED';
    const sn = elSN ? elSN.value.trim() : '';
    const gpu = elGpu ? elGpu.value.trim() : '';
    const os = elOs ? elOs.value.trim() : '';
    const bios = elBios ? elBios.value.trim() : 'UNKNOWN';

    // Battery text
    let battText = '—';
    if (elBatt) {
        if (elBatt.value === '1') battText = 'YES';
        else if (elBatt.value === '0') battText = 'NO';
    }

    // --- Update Mockup A (Branding) ---
    const prevBrandModel = document.getElementById('prevBrandModel');
    const prevSeries = document.getElementById('prevSeries');
    const prevCpu = document.getElementById('prevCpu');
    const prevSN = document.getElementById('prevSN');
    const prevLoc = document.getElementById('prevLoc');
    const prevCond = document.getElementById('prevCond');

    const elCpuCores = document.getElementById(F.CPU_CORES || 'cpu_cores');
    const elCpuSpeed = document.getElementById(F.CPU_SPEED || 'cpu_speed');
    const cpuCores = elCpuCores ? elCpuCores.value.trim() : '';
    const cpuSpeed = elCpuSpeed ? elCpuSpeed.value.trim() : '';
    const cpuDetail = [cpuCores, cpuSpeed].filter(Boolean).join(' @ ');

    let fullCpuDisplay = cpuSpecs || 'Processor N/A';
    if (cpuDetail) {
        fullCpuDisplay += ` (${cpuDetail})`;
    } else if (cpuGen) {
        fullCpuDisplay += ` (${cpuGen})`;
    }

    if (prevBrandModel) prevBrandModel.textContent = (brand || model) ? `${brand} ${model}`.trim() : 'BRAND MODEL';
    if (prevSeries) prevSeries.textContent = series || (model ? 'Series Standard' : 'Series Unknown');
    if (prevCpu) prevCpu.textContent = fullCpuDisplay;
    if (prevSN) prevSN.textContent = sn ? `S/N: ${sn}` : 'S/N: XXXXXX';
    if (prevLoc) prevLoc.textContent = loc ? `LOC: ${loc}` : 'LOC: —';
    if (prevCond) prevCond.textContent = (cond || 'UNTESTED').toUpperCase();

    // --- Update Mockup B (Specs) ---
    const prevSpecsHeaderBrand = document.getElementById('prevSpecsHeaderBrand');
    const prevSpecsCpu = document.getElementById('prevSpecsCpu');
    const prevSpecsRam = document.getElementById('prevSpecsRam');
    const prevSpecsStorage = document.getElementById('prevSpecsStorage');
    const prevSpecsBatt = document.getElementById('prevSpecsBatt');
    const prevSpecsGpu = document.getElementById('prevSpecsGpu');
    const prevSpecsOs = document.getElementById('prevSpecsOs');
    const prevSpecsBios = document.getElementById('prevSpecsBios');
    const prevSpecsLoc = document.getElementById('prevSpecsLoc');
    const prevSpecsCond = document.getElementById('prevSpecsCond');

    if (prevSpecsHeaderBrand) prevSpecsHeaderBrand.textContent = `${brand} ${model} ${series ? '(' + series + ')' : ''}`.trim() || 'HARDWARE SPECS';
    if (prevSpecsCpu) prevSpecsCpu.textContent = fullCpuDisplay;
    if (prevSpecsRam) prevSpecsRam.textContent = ram || 'None';
    if (prevSpecsStorage) prevSpecsStorage.textContent = storage || 'None';
    if (prevSpecsBatt) prevSpecsBatt.textContent = battText;
    if (prevSpecsGpu) prevSpecsGpu.textContent = gpu || 'Integrated';
    if (prevSpecsOs) prevSpecsOs.textContent = os || 'Win 11 / macOS';
    if (prevSpecsBios) prevSpecsBios.textContent = bios.toUpperCase();
    if (prevSpecsLoc) prevSpecsLoc.textContent = loc ? `📍 LOC: ${loc}` : '📍 LOC: —';
    if (prevSpecsCond) prevSpecsCond.textContent = (cond || 'UNTESTED').toUpperCase();
};

// ── 3. FORM SUBMISSION & CLONING INITIALIZATION ──────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById('newLabelForm');
    const F = window.HW_FIELDS || {};

    // 1. Sync CPU prefix and main input into hidden CPU_SPECS
    const cpuMainInput = document.getElementById('cpu_specs_main');
    const cpuPrefix = document.getElementById('cpu_prefix_display');
    const cpuHidden = document.getElementById(F.CPU_SPECS || 'cpu_specs');

    if (cpuMainInput) {
        cpuMainInput.addEventListener('input', () => {
            const prefix = cpuPrefix ? cpuPrefix.textContent.trim() : '';
            const val = cpuMainInput.value.trim();
            if (cpuHidden) cpuHidden.value = (prefix + val).trim();
            updateLivePreview();
        });
    }

    // 1b. Dynamic Cores & Speed Suffix Engine (Strict Numeric & Float Validation)
    const coresDisplay = document.getElementById('cpu_cores_display');
    const coresSuffix = document.getElementById('cpu_cores_suffix');
    const coresHidden = document.getElementById(F.CPU_CORES || 'cpu_cores');

    if (coresDisplay) {
        coresDisplay.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
                e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown' ||
                e.key === 'Home' || e.key === 'End' || e.key === 'Enter' || e.ctrlKey || e.metaKey) {
                return;
            }
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });

        coresDisplay.addEventListener('input', () => {
            const cleaned = coresDisplay.value.replace(/\D/g, '');
            if (coresDisplay.value !== cleaned) {
                coresDisplay.value = cleaned;
            }

            if (cleaned === '') {
                if (coresSuffix) coresSuffix.style.display = 'none';
                if (coresHidden) coresHidden.value = '';
            } else {
                const num = parseInt(cleaned, 10);
                const sfx = (num === 1 ? 'Core' : 'Cores');
                if (coresSuffix) {
                    coresSuffix.textContent = sfx;
                    coresSuffix.style.display = 'inline-flex';
                }
                if (coresHidden) {
                    coresHidden.value = cleaned + ' ' + sfx;
                }
            }
            updateLivePreview();
        });
    }

    const speedDisplay = document.getElementById('cpu_speed_display');
    const speedSuffix = document.getElementById('cpu_speed_suffix');
    const speedHidden = document.getElementById(F.CPU_SPEED || 'cpu_speed');

    if (speedDisplay) {
        speedDisplay.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'Tab' || 
                e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown' ||
                e.key === 'Home' || e.key === 'End' || e.key === 'Enter' || e.ctrlKey || e.metaKey) {
                return;
            }
            if (e.key === '.') {
                if (speedDisplay.value.includes('.')) {
                    e.preventDefault();
                }
                return;
            }
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });

        speedDisplay.addEventListener('input', () => {
            let val = speedDisplay.value.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }
            if (speedDisplay.value !== val) {
                speedDisplay.value = val;
            }

            if (val.trim() === '' || val.trim() === '.') {
                if (speedSuffix) speedSuffix.style.display = 'none';
                if (speedHidden) speedHidden.value = '';
            } else {
                if (speedSuffix) {
                    speedSuffix.textContent = 'GHz';
                    speedSuffix.style.display = 'inline-flex';
                }
                if (speedHidden) {
                    speedHidden.value = val.trim() + ' GHz';
                }
            }
            updateLivePreview();
        });
    }

    // Initialize display & suffixes from hidden inputs if present
    if (coresHidden && coresHidden.value) {
        setCpuCores(coresHidden.value);
    }
    if (speedHidden && speedHidden.value) {
        setCpuSpeed(speedHidden.value);
    }

    // 2. Clone from recent cards
    document.querySelectorAll('.clone-recent-card').forEach(card => {
        card.addEventListener('click', () => {
            const d = card.dataset;
            if (d.brand) selectBrandPill(d.brand);

            const mInput = document.getElementById(F.MODEL || 'model');
            const sInput = document.getElementById(F.SERIES || 'series');
            const gInput = document.getElementById(F.CPU_GEN || 'cpu_gen');
            const locInput = document.getElementById(F.LOCATION || 'warehouse_location');

            if (mInput && d.model) mInput.value = d.model;
            if (sInput && d.series) sInput.value = d.series;
            if (gInput && d.cpuGen) gInput.value = d.cpuGen;
            if (d.cpuCores) setCpuCores(d.cpuCores);
            if (d.cpuSpeed) setCpuSpeed(d.cpuSpeed);
            if (locInput && d.location) locInput.value = d.location;

            if (d.cpuSpecs) {
                if (cpuHidden) cpuHidden.value = d.cpuSpecs;
                if (cpuMainInput) {
                    if (d.cpuSpecs.includes('-')) {
                        const pts = d.cpuSpecs.split('-');
                        if (cpuPrefix) cpuPrefix.textContent = pts[0] + '-';
                        cpuMainInput.value = pts.slice(1).join('-');
                    } else {
                        if (cpuPrefix) cpuPrefix.textContent = '';
                        cpuMainInput.value = d.cpuSpecs;
                    }
                }
            }

            if (d.ram) selectSpecPill('ram', d.ram);
            if (d.storage) selectSpecPill('storage', d.storage);

            updateLivePreview();
            Toast.success(`Cloned specs for ${d.brand || ''} ${d.model || ''}`);
        });
    });

    // 3. Form input change listeners for Live Preview
    if (form) {
        form.addEventListener('input', updateLivePreview);
        form.addEventListener('change', updateLivePreview);
    }

    // 4. Form Reset Handler
    const resetBtn = document.getElementById('btnResetForm');
    if (resetBtn && form) {
        resetBtn.addEventListener('click', () => {
            const pinLoc = document.getElementById('pin_location');
            const locInput = document.getElementById(F.LOCATION || 'warehouse_location');
            const savedLoc = locInput ? locInput.value : '';

            form.reset();

            if (pinLoc && pinLoc.checked && locInput) {
                locInput.value = savedLoc;
            }

            document.querySelectorAll('.brand-pill').forEach(b => b.classList.remove('selected'));
            document.querySelectorAll('.spec-pill').forEach(b => b.classList.remove('selected'));
            if (cpuPrefix) cpuPrefix.textContent = '';

            // Reset dynamic cores & speed
            setCpuCores('');
            setCpuSpeed('');

            // Reset to Full Technical Sheet mode
            if (typeof switchFormMode === 'function') {
                switchFormMode('advanced');
            }

            updateLivePreview();
            Toast.info("Form reset — ready for new hardware");

            // Bring user back to Hardware Identity & Model without having to scroll up
            const identitySection = document.getElementById('sectionHardwareIdentity');
            if (identitySection) {
                const headerOffset = 85;
                const elementPosition = identitySection.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                window.scrollTo({
                    top: Math.max(0, offsetPosition),
                    behavior: 'smooth'
                });
            }
        });
    }

    // 5. Submission Execution
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Determine which submit button triggered the submission
            const submitter = e.submitter || document.getElementById('btnSubmitThermalPrint');
            const action = submitter ? submitter.dataset.action : 'print';

            // Ensure hidden CPU_SPECS is synced
            const prefix = cpuPrefix ? cpuPrefix.textContent.trim() : '';
            const mainVal = cpuMainInput ? cpuMainInput.value.trim() : '';
            if (cpuHidden) {
                cpuHidden.value = (prefix + mainVal).trim();
            }

            // Ensure hidden CPU_CORES and CPU_SPEED are synced
            if (coresDisplay && coresHidden) {
                const rawCores = coresDisplay.value.trim();
                if (rawCores) {
                    const num = parseInt(rawCores, 10);
                    coresHidden.value = rawCores + ' ' + (num === 1 ? 'Core' : 'Cores');
                } else {
                    coresHidden.value = '';
                }
            }
            if (speedDisplay && speedHidden) {
                const rawSpeed = speedDisplay.value.trim();
                if (rawSpeed && rawSpeed !== '.') {
                    speedHidden.value = rawSpeed + ' GHz';
                } else {
                    speedHidden.value = '';
                }
            }

            const formData = new FormData(form);
            const brand = formData.get(F.BRAND || 'brand');
            const model = formData.get(F.MODEL || 'model');

            if (!brand || !model) {
                Toast.warning("Brand and Model are required.");
                return;
            }

            const originalHtml = submitter.innerHTML;
            submitter.innerHTML = '<span>⏳ Saving to Warehouse...</span>';
            submitter.disabled = true;

            try {
                const response = await fetch('api/add_label.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!result.success) {
                    Toast.error("Save Failed: " + (result.error || 'Unknown error'));
                    return;
                }

                const newId = result.data.id;
                const isDup = result.data.is_duplicate;

                if (action === 'save') {
                    Toast.success(isDup ? `Matched existing profile (#${newId})` : `💾 Saved ${brand} ${model} (#${newId}) to Inventory`);
                } else {
                    Toast.success(isDup ? `Existing profile matched (#${newId})` : `Saved ${brand} ${model} (#${newId})`);
                }

                // Execute selected action
                if (action === 'print') {
                    printThermalLabel(newId, 'both', 1);
                } else if (action === 'odt') {
                    flashOpenLabel(newId, brand, model);
                }

                // Rapid Batching: Clear Serial Number but keep model/location
                const snInput = document.getElementById(F.SERIAL_NUMBER || 'serial_number');
                if (snInput) {
                    snInput.value = '';
                    snInput.focus();
                }

                // If location is NOT pinned, clear location too
                const pinLoc = document.getElementById('pin_location');
                if (!pinLoc || !pinLoc.checked) {
                    const locInput = document.getElementById(F.LOCATION || 'warehouse_location');
                    if (locInput) locInput.value = '';
                }

                updateLivePreview();

            } catch (err) {
                console.error(err);
                Toast.error("Network error saving hardware record.");
            } finally {
                submitter.innerHTML = originalHtml;
                submitter.disabled = false;
            }
        });
    }

    // Run preview once on load
    updateLivePreview();
});
