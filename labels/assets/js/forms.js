const newLabelForm = document.getElementById('newLabelForm');
const refurbForm   = document.getElementById('refurbForm');
const activeForm   = newLabelForm || refurbForm;

document.addEventListener("DOMContentLoaded", () => {
    const F = window.HW_FIELDS;
    if (!F) return; // Guard against missing mapping

    // Make it globally available for other scripts
    window.refreshPreview = updateLivePreview;

    /* --- AUTO-COMPLETE DATALISTS FOR BRAND / MODEL / SERIES --- */
    function initFormDatalists() {
        const brandSelect = document.getElementById(F.BRAND);
        const modelDatalist = document.getElementById('model-options');
        const seriesDatalist = document.getElementById('series-options');
        const modelInput = document.getElementById(F.MODEL);
        const seriesInput = document.getElementById(F.SERIES);

        const updateSeriesOptions = () => {
            const selectedBrand = brandSelect ? brandSelect.value : '';
            const selectedModel = modelInput ? modelInput.value.trim() : '';
            const data = window.IQA_Inventory ? window.IQA_Inventory[selectedBrand] : null;

            if (seriesDatalist) seriesDatalist.innerHTML = '';

            if (data) {
                let seriesList = [];
                if (selectedModel && data.modelSeries && data.modelSeries[selectedModel]) {
                    seriesList = data.modelSeries[selectedModel];
                } else {
                    seriesList = data.series || [];
                }

                const val = seriesInput ? seriesInput.value.trim().toLowerCase() : '';
                let filtered = seriesList;
                if (val.length >= 1) {
                    filtered = seriesList.filter(s => s.toLowerCase().startsWith(val));
                }
                if (seriesDatalist) {
                    seriesDatalist.innerHTML = filtered.map(s => `<option value="${s}">`).join('');
                }
            }
        };

        const populateInitialOptions = () => {
            const selectedBrand = brandSelect ? brandSelect.value : '';
            const data = window.IQA_Inventory ? window.IQA_Inventory[selectedBrand] : null;
            if (data) {
                if (modelDatalist) modelDatalist.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
                updateSeriesOptions();
            }
        };

        if (brandSelect) {
            brandSelect.addEventListener('change', (e) => {
                const selectedBrand = e.target.value;
                const data = window.IQA_Inventory ? window.IQA_Inventory[selectedBrand] : null;

                if (modelInput) modelInput.value = '';
                if (seriesInput) seriesInput.value = '';

                if (modelDatalist) modelDatalist.innerHTML = '';
                if (seriesDatalist) seriesDatalist.innerHTML = '';

                if (data) {
                    if (modelDatalist) modelDatalist.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
                    updateSeriesOptions();
                }
            });
        }

        if (modelInput) {
            modelInput.addEventListener('input', updateSeriesOptions);
            modelInput.addEventListener('change', updateSeriesOptions);
        }

        if (seriesInput) {
            seriesInput.addEventListener('input', updateSeriesOptions);
            seriesInput.addEventListener('focus', updateSeriesOptions);
        }

        // Initialize on load
        populateInitialOptions();
    }

    initFormDatalists();

    /**
     * Dynamic ghost suffix helper for RAM and Storage inputs
     */
    function initGhostSuffixes() {
        const ramInput = document.getElementById(F.RAM);
        const storageInput = document.getElementById(F.STORAGE);

        if (ramInput) {
            let dl = document.getElementById('ram-ghost-options');
            if (!dl) {
                dl = document.createElement('datalist');
                dl.id = 'ram-ghost-options';
                document.body.appendChild(dl);
                ramInput.setAttribute('list', 'ram-ghost-options');
            }

            // Restrict keyboard entries to digits only
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
                if (digits) {
                    dl.innerHTML = `<option value="${digits} GB">`;
                } else {
                    dl.innerHTML = '';
                }
            });

            // Auto-append GB on blur if only numbers were typed
            ramInput.addEventListener('blur', () => {
                const val = ramInput.value.trim();
                if (/^\d+$/.test(val)) {
                    ramInput.value = val + ' GB';
                    updateLivePreview();
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

            // Restrict keyboard entries to digits only
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
                        <option value="${digits} GB SSD">
                        <option value="${digits} GB NVMe">
                        <option value="${digits} TB SSD">
                        <option value="${digits} TB NVMe">
                    `;
                } else {
                    dl.innerHTML = '';
                }
            });

            // Auto-append GB SSD on blur if only numbers were typed
            storageInput.addEventListener('blur', () => {
                const val = storageInput.value.trim();
                if (/^\d+$/.test(val)) {
                    storageInput.value = val + ' GB SSD';
                    updateLivePreview();
                }
            });
        }
    }

    initGhostSuffixes();

    /* --- SECTION TOGGLING (Untested vs Refurbished) --- */
    const conditionSelect = document.getElementById(F.DESCRIPTION);
    const technicalSection = document.getElementById('technicalSpecsSection');
    const biosSelect = document.getElementById(F.BIOS_STATE);
    const statusSelect = document.getElementById(F.STATUS);

    if (conditionSelect) {
        const updateStatusOptions = (cond) => {
            if (!statusSelect) return;

            Array.from(statusSelect.options).forEach(opt => {
                let show = false;
                if (cond === 'Refurbished') show = (opt.value === 'Tested');
                else if (cond === 'Untested') show = ['In Warehouse', 'Grade A', 'Grade B', 'Grade C'].includes(opt.value);
                else if (cond === 'For Parts') show = ['In Warehouse', 'No Post', 'No Power'].includes(opt.value);

                opt.style.display = show ? 'block' : 'none';
                opt.disabled = !show;
            });

            // Force automatic status selection
            if (cond === 'Refurbished') {
                statusSelect.value = 'Tested';
            } else if (cond === 'Untested' && !['In Warehouse', 'Grade A', 'Grade B', 'Grade C'].includes(statusSelect.value)) {
                statusSelect.value = 'In Warehouse';
            } else if (statusSelect.options[statusSelect.selectedIndex] && statusSelect.options[statusSelect.selectedIndex].disabled) {
                const validOpt = Array.from(statusSelect.options).find(o => !o.disabled);
                if (validOpt) statusSelect.value = validOpt.value;
            }
        };

        const updateDynamicRequirements = (cond) => {
            const reqFields = [
                document.getElementById(F.CPU_GEN),
                document.getElementById(F.RAM),
                document.getElementById(F.STORAGE)
            ];
            reqFields.forEach(f => {
                if (f) {
                    const label = document.querySelector(`label[for="${f.id}"]`);
                    if (cond === 'Refurbished') {
                        f.setAttribute('required', 'true');
                        if (label && !label.innerHTML.includes('*')) label.innerHTML += ' <span style="color:var(--text-main);">*</span>';
                    } else {
                        f.removeAttribute('required');
                        if (label) label.innerHTML = label.innerHTML.replace(' <span style="color:var(--text-main);">*</span>', '');
                    }
                }
            });
        };

        conditionSelect.addEventListener('change', (e) => {
            const cond = e.target.value;

            if (technicalSection) technicalSection.style.display = (cond === 'Refurbished') ? 'block' : 'none';

            if (biosSelect) {
                if (cond === 'Untested' || cond === 'For Parts') biosSelect.value = 'Unknown';
                else if (cond === 'Refurbished') biosSelect.value = 'Unlocked';
            }

            updateStatusOptions(cond);
            updateDynamicRequirements(cond);
        });

        // Initialize state without overwriting BIOS defaults
        const initialCond = conditionSelect.value;
        if (technicalSection) technicalSection.style.display = (initialCond === 'Refurbished') ? 'block' : 'none';
        updateStatusOptions(initialCond);
        updateDynamicRequirements(initialCond);
    }

    /* --- PROFILE CLONING LOGIC --- */
    document.querySelectorAll('.clone-trigger').forEach(card => {
        card.addEventListener('click', () => {
            const data = card.dataset;

            // Mapping keys to their logic names in dataset (camelCase)
            const fieldsToClone = [
                F.BRAND, F.MODEL, F.SERIES, F.CPU_GEN, F.CPU_SPECS,
                F.CPU_CORES, F.CPU_SPEED, F.RAM, F.STORAGE
            ];

            fieldsToClone.forEach(f => {
                const el = document.getElementById(f);
                if (el) {
                    const val = data[f.replace(/_([a-z])/g, (g) => g[1].toUpperCase())] || '';
                    el.value = val;

                    // SPECIAL HANDLE FOR CPU SPECS CLONING
                    if (f === F.CPU_SPECS) {
                        const prefixDisplay = document.getElementById('cpu_prefix_display');
                        const mainInput = document.getElementById('cpu_specs_main');
                        if (prefixDisplay && mainInput) {
                            if (val.includes('-')) {
                                const parts = val.split('-');
                                prefixDisplay.textContent = parts[0] + '-';
                                mainInput.value = parts[1];
                            } else {
                                prefixDisplay.textContent = '';
                                mainInput.value = val;
                            }
                        }
                    }
                }
            });

            // Visual feedback
            card.style.borderColor = 'var(--accent-color)';
            card.style.background = 'rgba(140, 198, 63, 0.05)';
            setTimeout(() => {
                card.style.borderColor = 'var(--border-color)';
                card.style.background = 'var(--bg-panel)';
            }, 500);

            // Update preview
            updateLivePreview();

            // AUTO-SCROLL to form on mobile for speed
            if (window.innerWidth <= 1100) {
                const formStart = document.querySelector('.form-panel');
                if (formStart) {
                    formStart.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    /* --- CUSTOM NARROWING CPU SEARCH --- */
    const cpuInput = document.getElementById(F.CPU_GEN);
    const specsHidden = document.getElementById(F.CPU_SPECS); // Hidden system field
    const prefixDisplay = document.getElementById('cpu_prefix_display');
    const mainSpecsInput = document.getElementById('cpu_specs_main'); // User visible part
    const coresInput = document.getElementById(F.CPU_CORES);
    const speedInput = document.getElementById(F.CPU_SPEED);
    const cpuWrapper = document.getElementById('cpuSearchWrapper');

    const syncCpuSpecs = () => {
        if (!specsHidden || !prefixDisplay || !mainSpecsInput) return;
        const prefix = prefixDisplay.textContent.replace('-', '');
        const val = mainSpecsInput.value.trim();
        specsHidden.value = val ? `${prefix}-${val}` : '';

        // Auto-Fill Cores & Speed if model is recognized
        // Strip common prefixes like 'i5-' or 'Ryzen 5 ' to match catalog keys
        let lookup = val.toUpperCase().replace(/^(I[3579]-|RYZEN\s[3579]\s)/, '').trim();

        if (cpuTechnicalSpecs[lookup]) {
            if (coresInput) coresInput.value = cpuTechnicalSpecs[lookup].cores;
            if (speedInput) speedInput.value = cpuTechnicalSpecs[lookup].speed;
        }

        updateLivePreview(); // Manually trigger preview update since hidden fields don't fire events
    };

    if (mainSpecsInput) {
        mainSpecsInput.addEventListener('input', syncCpuSpecs);
    }

    // Structured CPU Catalog for Auto-Fill
    const cpuCatalog = {
        // Intel Core i3
        "i3 · 6th Gen": { gen: "6th Gen", specs: "i3-6", cores: "2 Cores", speed: "2.30GHz" },
        "i3 · 7th Gen": { gen: "7th Gen", specs: "i3-7", cores: "2 Cores", speed: "2.40GHz" },
        "i3 · 8th Gen": { gen: "8th Gen", specs: "i3-8", cores: "2 Cores", speed: "2.10GHz" },
        "i3 · 10th Gen": { gen: "10th Gen", specs: "i3-10", cores: "2 Cores", speed: "2.10GHz" },
        "i3 · 12th Gen": { gen: "12th Gen", specs: "i3-12", cores: "6 Cores", speed: "1.20GHz" },

        // Intel Core i5
        "i5 · 6th Gen": { gen: "6th Gen", specs: "i5-6", cores: "2 Cores", speed: "2.40GHz" },
        "i5 · 7th Gen": { gen: "7th Gen", specs: "i5-7", cores: "2 Cores", speed: "2.50GHz" },
        "i5 · 8th Gen": { gen: "8th Gen", specs: "i5-8", cores: "4 Cores", speed: "1.70GHz" },
        "i5 · 9th Gen": { gen: "9th Gen", specs: "i5-9", cores: "4 Cores", speed: "2.40GHz" },
        "i5 · 10th Gen": { gen: "10th Gen", specs: "i5-10", cores: "4 Cores", speed: "1.60GHz" },
        "i5 · 11th Gen": { gen: "11th Gen", specs: "i5-11", cores: "4 Cores", speed: "2.40GHz" },
        "i5 · 12th Gen": { gen: "12th Gen", specs: "i5-12", cores: "10 Cores", speed: "1.30GHz" },
        "i5 · 13th Gen": { gen: "13th Gen", specs: "i5-13", cores: "10 Cores", speed: "1.30GHz" },

        // Intel Core i7
        "i7 · 6th Gen": { gen: "6th Gen", specs: "i7-6", cores: "2 Cores", speed: "2.60GHz" },
        "i7 · 7th Gen": { gen: "7th Gen", specs: "i7-7", cores: "2 Cores", speed: "2.80GHz" },
        "i7 · 8th Gen": { gen: "8th Gen", specs: "i7-8", cores: "4 Cores", speed: "1.90GHz" },
        "i7 · 9th Gen": { gen: "9th Gen", specs: "i7-9", cores: "6 Cores", speed: "2.60GHz" },
        "i7 · 10th Gen": { gen: "10th Gen", specs: "i7-10", cores: "4 Cores", speed: "1.80GHz" },
        "i7 · 11th Gen": { gen: "11th Gen", specs: "i7-11", cores: "4 Cores", speed: "3.00GHz" },
        "i7-11850H (11th)": { gen: "11th Gen", specs: "i7-11850H", cores: "8 Cores", speed: "2.50GHz" },
        "i7 · 12th Gen": { gen: "12th Gen", specs: "i7-12", cores: "10 Cores", speed: "1.80GHz" },
        "i7 · 13th Gen": { gen: "13th Gen", specs: "i7-13", cores: "10 Cores", speed: "1.80GHz" },

        // Intel Core i9
        "i9 · 12th Gen": { gen: "12th Gen", specs: "i9-12", cores: "14 Cores", speed: "2.50GHz" },
        "i9 · 13th Gen": { gen: "13th Gen", specs: "i9-13", cores: "14 Cores", speed: "2.60GHz" },

        // AMD (Single Option)
        "AMD": { gen: "AMD", specs: "AMD-", cores: "", speed: "" }
    };

    // Specific CPU Details Catalog for Auto-Fill (Triggered by Specs Main Input)
    const cpuTechnicalSpecs = {
        // 6th Gen
        "6200U": { cores: "2 Cores", speed: "2.30 GHz" },
        "6300U": { cores: "2 Cores", speed: "2.40 GHz" },
        "6500U": { cores: "2 Cores", speed: "2.50 GHz" },
        "6600U": { cores: "2 Cores", speed: "2.60 GHz" },

        // 7th Gen
        "7200U": { cores: "2 Cores", speed: "2.50 GHz" },
        "7300U": { cores: "2 Cores", speed: "2.60 GHz" },
        "7500U": { cores: "2 Cores", speed: "2.70 GHz" },
        "7600U": { cores: "2 Cores", speed: "2.80 GHz" },

        // 8th Gen
        "8250U": { cores: "4 Cores", speed: "1.60 GHz" },
        "8350U": { cores: "4 Cores", speed: "1.70 GHz" },
        "8550U": { cores: "4 Cores", speed: "1.80 GHz" },
        "8650U": { cores: "4 Cores", speed: "1.90 GHz" },
        "8265U": { cores: "4 Cores", speed: "1.60 GHz" },
        "8365U": { cores: "4 Cores", speed: "1.60 GHz" },

        // 10th Gen
        "10210U": { cores: "4 Cores", speed: "1.60 GHz" },
        "10310U": { cores: "4 Cores", speed: "1.70 GHz" },
        "10510U": { cores: "4 Cores", speed: "1.80 GHz" },
        "10610U": { cores: "4 Cores", speed: "1.80 GHz" },
        "10710U": { cores: "6 Cores", speed: "1.10 GHz" },

        // 11th Gen
        "1135G7": { cores: "4 Cores", speed: "2.40 GHz" },
        "1145G7": { cores: "4 Cores", speed: "2.60 GHz" },
        "1165G7": { cores: "4 Cores", speed: "2.80 GHz" },
        "1185G7": { cores: "4 Cores", speed: "3.00 GHz" },

        // 12th Gen
        "1235U":  { cores: "10 Cores", speed: "1.30 GHz" },
        "1245U":  { cores: "10 Cores", speed: "1.60 GHz" },
        "1255U":  { cores: "10 Cores", speed: "1.70 GHz" },
        "1265U":  { cores: "10 Cores", speed: "1.80 GHz" },

        // Apple Silicon
        "M1":     { cores: "8 Cores", speed: "3.20 GHz" },
        "M1 PRO": { cores: "10 Cores", speed: "3.20 GHz" },
        "M1 MAX": { cores: "10 Cores", speed: "3.20 GHz" },
        "M2":     { cores: "8 Cores", speed: "3.50 GHz" },
        "M3":     { cores: "8 Cores", speed: "4.05 GHz" },

        // Common AMD Ryzen
        "3500U": { cores: "4 Cores", speed: "2.10 GHz" },
        "3700U": { cores: "4 Cores", speed: "2.30 GHz" },
        "4500U": { cores: "6 Cores", speed: "2.30 GHz" },
        "4700U": { cores: "8 Cores", speed: "2.00 GHz" },
        "5500U": { cores: "6 Cores", speed: "2.10 GHz" },
        "5700U": { cores: "8 Cores", speed: "1.80 GHz" },
        "5800U": { cores: "8 Cores", speed: "1.90 GHz" }
    };

    const cpuKeys = Object.keys(cpuCatalog);

    if (cpuInput && cpuWrapper) {
        cpuInput.addEventListener('input', () => {
            const val = cpuInput.value.toLowerCase();
            cpuWrapper.innerHTML = '';

            if (val.length < 1) {
                cpuWrapper.style.display = 'none';
                return;
            }

            const matches = cpuKeys.filter(g => g.toLowerCase().includes(val));

            if (matches.length > 0) {
                cpuWrapper.style.display = 'block';

                // Smart Positioning: shift up if not enough space below
                const rect = cpuInput.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const minSpaceNeeded = Math.min(250, matches.length * 45); // Approximate max height

                if (spaceBelow < minSpaceNeeded && rect.top > spaceBelow) {
                    cpuWrapper.classList.add('shift-up');
                } else {
                    cpuWrapper.classList.remove('shift-up');
                }

                matches.forEach(g => {
                    const item = document.createElement('div');
                    item.className = 'search-suggestion-item cpu-opt';
                    item.style.padding = '10px';
                    item.style.cursor = 'pointer';
                    item.style.borderBottom = '1px solid var(--border-color)';
                    item.innerHTML = `<strong>${g}</strong> <span style="font-size:0.7rem; color:var(--text-secondary); float:right;">Auto-Fill Specs</span>`;

                    item.addEventListener('mouseover', () => item.style.background = 'rgba(140, 198, 63, 0.1)');
                    item.addEventListener('mouseout', () => item.style.background = 'transparent');

                    item.addEventListener('click', () => {
                        const data = cpuCatalog[g];

                        // 1. Set the Generation
                        cpuInput.value = data.gen;

                        // 2. Auto-fill technical fields
                        if (prefixDisplay) {
                            const p = data.specs.split('-')[0];
                            prefixDisplay.textContent = p + '-';
                        }
                        if (mainSpecsInput) {
                            mainSpecsInput.value = data.specs.split('-')[1] || '';
                        }

                        syncCpuSpecs(); // Update hidden field

                        if (coresInput) coresInput.value = data.cores;
                        if (speedInput) speedInput.value = data.speed;

                        cpuWrapper.style.display = 'none';

                        // 3. Set focus and select
                        if (mainSpecsInput) {
                            // On mobile, jumping focus physically shifts the viewport and disrupts the keyboard.
                            // We completely bypass programmatic focus on narrow screens to stop the screen from violently panning.
                            if (window.innerWidth > 768) {
                                mainSpecsInput.focus({ preventScroll: true });
                                const len = mainSpecsInput.value.length;
                                mainSpecsInput.setSelectionRange(len, len);
                            }
                        }
                    });
                    cpuWrapper.appendChild(item);
                });
            } else {
                cpuWrapper.style.display = 'none';
            }
        });

        // Hide when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target !== cpuInput) cpuWrapper.style.display = 'none';
        });
    }

    /* --- NEW LABEL SUBMISSION (Success Overlay Logic) --- */
    const successOverlay = document.getElementById('successOverlay');
    const successMsg = document.getElementById('successMsg');
    let lastInsertedId = null;

    if (newLabelForm) {
        newLabelForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Support both Desktop and Mobile buttons
            const btnDesktop = document.getElementById('submitLabelBtnDesktop');
            const btnMobile = document.getElementById('submitLabelBtnMobile');

            const btns = [btnDesktop, btnMobile].filter(b => b !== null);
            const originalTexts = btns.map(b => b.innerHTML);

            btns.forEach(b => {
                b.innerHTML = '⏳ Processing...';
                b.disabled = true;
            });

            const formData = new FormData(newLabelForm);

            try {
                const response = await fetch('api/add_label.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    lastInsertedId = result.data.id;
                    const name = (formData.get(F.BRAND) || '') + ' ' + (formData.get(F.MODEL) || '');

                    let msg = `Saved <strong>${name}</strong> to ID #${String(lastInsertedId).padStart(5, '0')}.<br>Profile is ready for printing.`;
                    if (result.data.is_duplicate) {
                        msg = `Found existing profile for <strong>${name}</strong> (ID #${String(lastInsertedId).padStart(5, '0')}).`;
                    }

                    successMsg.innerHTML = msg;
                    successOverlay.style.display = 'flex';

                    // Trigger the print configuration modal for the new item
                    if (window.openPrintConfig) window.openPrintConfig(lastInsertedId);

                } else {
                    alert(`❌ Error: ${result.error}`);
                }
            } catch (err) {
                console.error("Submission Error:", err);
                alert(`❌ Network Error: Could not connect to the label engine. (Details: ${err.message})`);
            } finally {
                btns.forEach((b, i) => {
                    b.innerHTML = originalTexts[i];
                    b.disabled = false;
                });
            }
        });
    }

    // Success Overlay Buttons
    if (successOverlay) {
        // Quick Print (1 Copy, both pages)
        document.getElementById('btnQuickPrint').addEventListener('click', async () => {
            const btn = document.getElementById('btnQuickPrint');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Generating...';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('id', lastInsertedId);
            fd.append('qty', 1);
            fd.append('print_a', '1');
            fd.append('print_b', '1');

            try {
                const res = await fetch('api/reprint_label.php', { method: 'POST', body: fd});
                const json = await res.json();
                if (json.success) {
                    // Trigger browser download so the file opens on the user's machine
                    const link = document.createElement('a');
                    link.href = json.data.file_path;
                    link.download = json.data.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    btn.innerHTML = '✅ Downloaded!';
                    setTimeout(() => { btn.innerHTML = originalText; btn.disabled = false; }, 1500);
                } else {
                    alert("Label Error: " + json.error);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                alert("Network error.");
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

        // "Print Config" - Opens the config modal
        document.getElementById('btnAgain').addEventListener('click', () => {
            if (window.openPrintConfig) window.openPrintConfig(lastInsertedId);
        });

        // "Add Another (Same Model)" - Clears only identifiers
        document.getElementById('btnCloneNext').addEventListener('click', () => {
            const pinLoc = document.getElementById('pin_location');
            const snField = document.getElementById(F.SERIAL_NUMBER);
            const locField = document.getElementById(F.LOCATION);

            if (snField) snField.value = '';

            // If location isn't pinned, we might want to clear it or keep it?
            // Usually, batching means same location. Let's keep location unless they reset.

            successOverlay.style.display = 'none';
            if (snField) snField.focus();
        });

        // "Start Fresh" - Clears everything
        document.getElementById('btnReset').addEventListener('click', () => {
            const pinLoc = document.getElementById('pin_location');
            const locField = document.getElementById(F.LOCATION);
            const savedLoc = locField ? locField.value : '';

            if (activeForm) {
                activeForm.reset();
            }

            // Restore location if pinned
            if (pinLoc && pinLoc.checked && locField) {
                locField.value = savedLoc;
            }

            // Ensure RAM/Storage are enabled by default after reset (matching HTML defaults)
            if (typeof hasRam !== 'undefined') ramInput.disabled = !hasRam.checked;
            if (typeof hasStorage !== 'undefined') storageInput.disabled = !hasStorage.checked;

            // Reset CPU Prefix Display
            const prefixDisplay = document.getElementById('cpu_prefix_display');
            if (prefixDisplay) prefixDisplay.textContent = '';

            successOverlay.style.display = 'none';
        });
    }

    // Attach listeners via event delegation on the form itself
    if (activeForm) {
        activeForm.addEventListener('input', updateLivePreview);
        activeForm.addEventListener('change', updateLivePreview);
    }

    // Run once on load to catch cloned or default data
    setTimeout(updateLivePreview, 150);
});

/* --- LIVE PREVIEW ENGINE (Global Scope for resilience) --- */
function updateLivePreview() {
    if (typeof window.HW_FIELDS === 'undefined') return;
    const F = window.HW_FIELDS;

    const pvBrandModel = document.getElementById('prevBrandModel');
    const pvSeriesSpecs = document.getElementById('prevSeriesSpecs');
    const pvCpu = document.getElementById('prevCpu');
    const pvRam = document.getElementById('prevRam');
    const pvStorage = document.getElementById('prevStorage');
    const pvBattery = document.getElementById('prevBattery');
    const pvSN = document.getElementById('prevSN');
    const pvCond = document.getElementById('prevCond');

    if (!pvBrandModel) return;

    // Core fields lookup
    const elBrand = document.getElementById(F.BRAND);
    const elModel = document.getElementById(F.MODEL);
    const elSeries = document.getElementById(F.SERIES);
    const elSpecs = document.getElementById(F.CPU_SPECS);
    const elRam = document.getElementById(F.RAM);
    const elStorage = document.getElementById(F.STORAGE);
    const elBattery = document.getElementById(F.BATTERY);
    const elSN = document.getElementById(F.SERIAL_NUMBER);
    const elCond = document.getElementById(F.DESCRIPTION);

    const brand = elBrand ? elBrand.value : '';
    const model = elModel ? elModel.value : '';
    const specsHidden = elSpecs ? elSpecs.value : '';
    const series = elSeries ? elSeries.value : '';

    // Textual display logic
    pvBrandModel.textContent = (brand || model) ? (brand + ' ' + model).trim() : 'BRAND MODEL';
    pvSeriesSpecs.textContent = (series || specsHidden) ? (series + ' ' + specsHidden).trim() : 'SERIES SPECS';

    if (pvCpu) pvCpu.textContent = specsHidden || '—';
    if (pvRam) pvRam.textContent = elRam ? (elRam.value || '—') : '—';
    if (pvStorage) pvStorage.textContent = elStorage ? (elStorage.value || '—') : '—';

    if (pvBattery && elBattery) {
        if (elBattery.value === '') {
            pvBattery.textContent = '—';
        } else {
            pvBattery.textContent = (elBattery.value == '1') ? 'YES' : 'NO';
        }
    }

    if (pvSN) pvSN.textContent = 'S/N: ' + (elSN ? (elSN.value || 'XXXXXX') : 'XXXXXX');
    if (pvCond) pvCond.textContent = elCond ? (elCond.value || 'UNTESTED') : 'UNTESTED';
}

