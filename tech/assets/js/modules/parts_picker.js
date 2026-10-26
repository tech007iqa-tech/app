/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: parts_picker.js
 * Hardware Repair Parts Consumed Tracker (Bridge to parts_inventory)
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Fetches available replacement components from ../api/get_inventory.php.
 * - Allows bench technicians to record components installed into the machine.
 * - Automatically populates hidden input 'parts_consumed' (JSON string) for add_log.php.
 * - Exposes `TechWorkbench.PartsPicker.init()`, `addPart()`, `removePart()`, `reset()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    let partsCatalog = [];
    let consumedList = [];

    const PartsPicker = {
        async init() {
            await this.loadCatalog();
            this.renderPickerUi();
        },

        async loadCatalog() {
            try {
                const res = await fetch('../api/get_inventory.php');
                const result = await res.json();
                if (result.success && Array.isArray(result.data)) {
                    partsCatalog = result.data;
                }
            } catch (e) {
                partsCatalog = [];
            }
        },

        renderPickerUi() {
            const container = document.getElementById('partsPickerMountPoint');
            if (!container) return;

            let optionsHtml = '<option value="">-- Select installed component --</option>';
            partsCatalog.forEach(p => {
                optionsHtml += `<option value="${p.id}" data-name="${escapeHtml(p.part_name)}" data-stock="${p.quantity}">[${escapeHtml(p.category || 'Part')}] ${escapeHtml(p.part_name)} (${p.quantity} in stock)</option>`;
            });

            container.innerHTML = `
                <div class="parts-consumed-card">
                    <div class="parts-consumed-header">
                        <span class="parts-consumed-title">
                            <span>🛠️</span> <span>Bench Upgrade &amp; Parts Used (Auto-Deducts from Inventory)</span>
                        </span>
                        <span style="font-size: 0.72rem; color: #64748b;">Optional</span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <select id="selConsumedPart" style="flex: 1; min-width: 200px; padding: 7px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.82rem; font-weight: 600; background: white;">
                            ${optionsHtml}
                        </select>
                        <input type="number" id="numConsumedQty" min="1" max="10" value="1" style="width: 60px; padding: 7px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700; font-size: 0.82rem;">
                        <button type="button" onclick="TechWorkbench.PartsPicker.addFromSelect()" class="btn-primary" style="padding: 7px 14px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; background: #059669; color: white; border: none; cursor: pointer;">+ Add Part</button>
                    </div>
                    <div id="consumedPartsChipsList" class="parts-consumed-chips-list">
                        <span style="color: #94a3b8; font-size: 0.78rem; font-style: italic;">No parts consumed on this unit.</span>
                    </div>
                    <input type="hidden" id="intake_parts_consumed" name="parts_consumed" value="[]">
                </div>
            `;
            this.syncChips();
        },

        addFromSelect() {
            const sel = document.getElementById('selConsumedPart');
            const qtyInput = document.getElementById('numConsumedQty');
            if (!sel || !sel.value) return;

            const partId = parseInt(sel.value);
            const qty = Math.max(1, parseInt(qtyInput?.value || 1));
            const partObj = partsCatalog.find(p => parseInt(p.id) === partId);
            if (!partObj) return;

            const existing = consumedList.find(c => c.id === partId);
            if (existing) {
                existing.qty += qty;
            } else {
                consumedList.push({
                    id: partId,
                    name: partObj.part_name,
                    qty: qty
                });
            }

            this.syncChips();
            if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playClick();
        },

        removePart(id) {
            consumedList = consumedList.filter(c => c.id !== id);
            this.syncChips();
            if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playClick();
        },

        syncChips() {
            const chipsContainer = document.getElementById('consumedPartsChipsList');
            const hiddenInput = document.getElementById('intake_parts_consumed');
            if (!chipsContainer || !hiddenInput) return;

            hiddenInput.value = JSON.stringify(consumedList);

            if (consumedList.length === 0) {
                chipsContainer.innerHTML = '<span style="color: #94a3b8; font-size: 0.78rem; font-style: italic;">No parts consumed on this unit.</span>';
                return;
            }

            let html = '';
            consumedList.forEach(c => {
                html += `
                    <div class="consumed-part-chip">
                        <span>📦 <strong>${c.qty}x</strong> ${escapeHtml(c.name)}</span>
                        <button type="button" onclick="TechWorkbench.PartsPicker.removePart(${c.id})" title="Remove part">&times;</button>
                    </div>
                `;
            });
            chipsContainer.innerHTML = html;
        },

        reset() {
            consumedList = [];
            this.syncChips();
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    exports.PartsPicker = PartsPicker;
})(window.TechWorkbench);
