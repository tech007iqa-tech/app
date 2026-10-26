/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: custom_presets.js
 * "Pin as Custom Preset" for Pallet & Inbound Shipment Batches
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Allows bench technicians to save dynamic presets for unusual incoming shipments.
 * - Stores custom presets in localStorage: 'tech_custom_presets'.
 * - Dynamically mounts new preset pill cards to .preset-cards-grid.
 * - Exposes `TechWorkbench.Presets.init()`, `pinCurrentSpecs()`, `removePreset(id)`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    const STORAGE_KEY = 'tech_custom_presets';

    function getCustomPresets() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveCustomPresets(presets) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(presets));
        } catch (e) {}
    }

    const CustomPresets = {
        init() {
            this.injectPinButton();
            this.renderCustomPresetCards();
        },

        injectPinButton() {
            const shelfHeader = document.querySelector('.preset-shelf-header');
            if (!shelfHeader || document.getElementById('btnPinCustomPreset')) return;

            const btn = document.createElement('button');
            btn.id = 'btnPinCustomPreset';
            btn.type = 'button';
            btn.className = 'btn-pin-preset';
            btn.innerHTML = '<span>⭐ Pin Specs as Preset</span>';
            btn.title = 'Save currently filled specs as a 1-touch preset for this shipment';
            btn.onclick = () => this.pinCurrentSpecs();

            shelfHeader.appendChild(btn);
        },

        pinCurrentSpecs() {
            const brand = document.getElementById('intake_brand')?.value.trim();
            const model = document.getElementById('intake_model')?.value.trim();
            const series = document.getElementById('intake_series')?.value.trim();

            if (!brand || !model) {
                alert('Please enter at least a Brand and Model before pinning a preset.');
                return;
            }

            const defaultTitle = `Pallet: ${brand} ${model} ${series}`.trim();
            const title = prompt('Enter a nickname for this custom preset:', defaultTitle);
            if (!title) return;

            const preset = {
                id: 'custom_' + Date.now(),
                title: title.trim(),
                brand: brand,
                model: model,
                series: series,
                cpu_gen: document.getElementById('intake_cpu_gen')?.value.trim() || '',
                cpu_specs: document.getElementById('intake_cpu_specs')?.value.trim() || '',
                cpu_cores: document.getElementById('intake_cpu_cores')?.value.trim() || '',
                cpu_speed: document.getElementById('intake_cpu_speed')?.value.trim() || '',
                ram: document.getElementById('intake_ram')?.value.trim() || '16 GB',
                storage: document.getElementById('intake_storage')?.value.trim() || '256 GB SSD',
                condition: document.getElementById('intake_condition')?.value || 'Refurbished',
                gpu: document.getElementById('intake_gpu')?.value.trim() || '',
                os: document.getElementById('intake_os')?.value.trim() || 'Windows 11 Pro'
            };

            const list = getCustomPresets();
            list.unshift(preset);
            if (list.length > 4) list.pop(); // Keep max 4 custom presets
            saveCustomPresets(list);

            this.renderCustomPresetCards();
            if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playSuccess();
        },

        renderCustomPresetCards() {
            const grid = document.querySelector('.preset-cards-grid');
            if (!grid) return;

            // Remove any previously rendered custom cards
            grid.querySelectorAll('.preset-pill-card.custom-preset').forEach(el => el.remove());

            const presets = getCustomPresets();
            presets.forEach(p => {
                const card = document.createElement('div');
                card.className = 'preset-pill-card custom-preset';
                card.id = `preset_card_${p.id}`;
                card.style.position = 'relative';

                const subtitle = `${p.brand} · ${p.cpu_specs || p.cpu_gen || 'Specs'} · ${p.ram} · ${p.storage}`;

                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div class="preset-card-title">⭐ ${escapeHtml(p.title)}</div>
                        <button type="button" onclick="event.stopPropagation(); TechWorkbench.Presets.removePreset('${p.id}')" style="background: none; border: none; font-size: 0.8rem; font-weight: 800; color: #94a3b8; cursor: pointer; padding: 0 4px;" title="Remove preset">&times;</button>
                    </div>
                    <div class="preset-card-subtitle">${escapeHtml(subtitle)}</div>
                    <span class="preset-card-badge" style="background: #eff6ff; color: #1d4ed8;">Custom Lot</span>
                `;

                card.onclick = () => this.applyPreset(p);
                grid.appendChild(card);
            });
        },

        applyPreset(p) {
            if (typeof window.selectBrandPill === 'function') window.selectBrandPill(p.brand);
            if (document.getElementById('intake_model')) document.getElementById('intake_model').value = p.model;
            if (document.getElementById('intake_series')) document.getElementById('intake_series').value = p.series;
            if (document.getElementById('intake_cpu_gen')) document.getElementById('intake_cpu_gen').value = p.cpu_gen;
            if (document.getElementById('intake_cpu_specs')) document.getElementById('intake_cpu_specs').value = p.cpu_specs;
            if (typeof window.onCpuCoresInput === 'function' && p.cpu_cores) window.onCpuCoresInput(p.cpu_cores);
            if (typeof window.onCpuSpeedInput === 'function' && p.cpu_speed) window.onCpuSpeedInput(p.cpu_speed);
            if (typeof window.selectSpecPill === 'function') {
                window.selectSpecPill('ram', p.ram);
                window.selectSpecPill('storage', p.storage);
            }
            if (document.getElementById('intake_condition') && p.condition) document.getElementById('intake_condition').value = p.condition;
            if (document.getElementById('intake_gpu') && p.gpu) document.getElementById('intake_gpu').value = p.gpu;
            if (document.getElementById('intake_os') && p.os) document.getElementById('intake_os').value = p.os;

            if (typeof window.updateLivePreview === 'function') window.updateLivePreview();
            if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playClick();
        },

        removePreset(id) {
            let list = getCustomPresets().filter(p => p.id !== id);
            saveCustomPresets(list);
            this.renderCustomPresetCards();
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    exports.Presets = CustomPresets;
})(window.TechWorkbench);
