/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: shelf_chips.js
 * Recent & Favorite Shelf Location 1-Touch Chips
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Eliminates repetitive typing of warehouse shelf codes during bulk intakes.
 * - Stores up to 6 distinct shelf locations in localStorage: 'tech_recent_shelves'.
 * - Injects interactive touch-chips above location input fields.
 * - Exposes `TechWorkbench.ShelfChips.init()`, `recordLocation(loc)`, `render()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    const STORAGE_KEY = 'tech_recent_shelves';
    const DEFAULT_SHELVES = ['Shelf A-1-2', 'Shelf A-1-3', 'Rack B-1', 'Carts'];

    function getStoredShelves() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return DEFAULT_SHELVES;
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) && parsed.length > 0 ? parsed : DEFAULT_SHELVES;
        } catch (e) {
            return DEFAULT_SHELVES;
        }
    }

    function saveStoredShelves(shelves) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(shelves));
        } catch (e) {}
    }

    const ShelfChips = {
        init() {
            this.renderIntakeChips();
            this.renderDeliveryModalChips();
        },

        getShelves() {
            return getStoredShelves();
        },

        /**
         * Records a newly used shelf location, keeping the list deduplicated and capped at 6.
         * @param {string} loc 
         */
        recordLocation(loc) {
            if (!loc) return;
            const clean = String(loc).trim();
            if (clean.length < 2) return;

            let list = getStoredShelves().filter(s => s.toLowerCase() !== clean.toLowerCase());
            list.unshift(clean);
            if (list.length > 6) list = list.slice(0, 6);
            saveStoredShelves(list);
            this.renderIntakeChips();
            this.renderDeliveryModalChips();
        },

        /**
         * Renders chips container directly above #intake_location in intake modal.
         */
        renderIntakeChips() {
            const input = document.getElementById('intake_location');
            if (!input) return;

            let container = document.getElementById('recentShelfChipsIntake');
            if (!container) {
                container = document.createElement('div');
                container.id = 'recentShelfChipsIntake';
                container.className = 'recent-shelf-container';
                input.parentNode.insertBefore(container, input);
            }

            const shelves = getStoredShelves();
            let html = '<span class="recent-shelf-label">Recent Shelves:</span>';
            shelves.forEach(shelf => {
                html += `<button type="button" class="shelf-chip" onclick="TechWorkbench.ShelfChips.applyToIntake('${shelf.replace(/'/g, "\\'")}')">📍 ${shelf}</button>`;
            });
            container.innerHTML = html;
        },

        /**
         * Renders chips container inside delivery modal.
         */
        renderDeliveryModalChips() {
            const destInput = document.getElementById('deliveryDestinationInput');
            if (!destInput) return;

            let container = document.getElementById('recentShelfChipsDelivery');
            if (!container) {
                container = document.createElement('div');
                container.id = 'recentShelfChipsDelivery';
                container.className = 'recent-shelf-container';
                destInput.parentNode.insertBefore(container, destInput);
            }

            const shelves = getStoredShelves();
            let html = '<span class="recent-shelf-label">Recent:</span>';
            shelves.forEach(shelf => {
                html += `<button type="button" class="shelf-chip" onclick="TechWorkbench.ShelfChips.applyToDelivery('${shelf.replace(/'/g, "\\'")}')">📍 ${shelf}</button>`;
            });
            container.innerHTML = html;
        },

        applyToIntake(shelf) {
            const input = document.getElementById('intake_location');
            if (input) {
                input.value = shelf;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playClick();
            }
        },

        applyToDelivery(shelf) {
            const input = document.getElementById('deliveryDestinationInput');
            if (input) {
                input.value = shelf;
                if (window.TechWorkbench.Audio) window.TechWorkbench.Audio.playClick();
            }
        }
    };

    exports.ShelfChips = ShelfChips;
})(window.TechWorkbench);
