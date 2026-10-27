/**
 * Cross-Module Smart Donor Matcher
 * IQA Metal Warehouse Systems - Zero-Dependency ES6
 * Detects matching donor units in storage as the technician types device specifications.
 */

(function () {
    'use strict';

    window.TechWorkbench = window.TechWorkbench || {};

    const StorageDonorMatch = {
        debounceTimer: null,
        lastQuery: '',
        currentMatches: [],

        init() {
            this.bindIntakeListeners();
        },

        bindIntakeListeners() {
            const modelInput = document.getElementById('intake_model');
            const seriesInput = document.getElementById('intake_series');
            const brandInput = document.getElementById('intake_brand');

            const check = () => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.runCheck(), 350);
            };

            if (modelInput) modelInput.addEventListener('input', check);
            if (seriesInput) seriesInput.addEventListener('input', check);
            if (brandInput) brandInput.addEventListener('change', check);
        },

        async runCheck() {
            const brand = document.getElementById('intake_brand')?.value?.trim() || '';
            const model = document.getElementById('intake_model')?.value?.trim() || '';
            const series = document.getElementById('intake_series')?.value?.trim() || '';

            const term = `${model} ${series}`.trim();
            if (term.length < 2 || term === this.lastQuery) {
                if (term.length < 2) this.clearAlert();
                return;
            }

            this.lastQuery = term;

            try {
                const params = new URLSearchParams({
                    status: 'In Storage',
                    donor_model: term
                });
                if (brand) params.set('brand', brand);

                const res = await fetch(`../api/get_storage.php?${params.toString()}`);
                const json = await res.json();

                if (json.success && json.data && json.data.length > 0) {
                    this.currentMatches = json.data;
                    this.renderAlert(json.data, term);
                } else {
                    this.clearAlert();
                }
            } catch (e) {
                // Fail quietly on network issues
            }
        },

        renderAlert(matches, term) {
            let container = document.getElementById('donorMatchAlertContainer');
            if (!container) {
                // If container not present, inject directly above the intake form
                const target = document.querySelector('.intake-form-column') || document.getElementById('techIntakeForm');
                if (!target) return;

                container = document.createElement('div');
                container.id = 'donorMatchAlertContainer';
                container.style.cssText = 'margin-bottom: 14px;';
                target.parentNode.insertBefore(container, target);
            }

            // Identify donors with Good Screen or Good Battery
            const withGoodScreen = matches.filter(m => m.screen === '✔' || (m.screen && m.screen.toLowerCase().includes('good')));
            const withGoodBattery = matches.filter(m => m.battery === '✔' || (m.battery && m.battery.toLowerCase().includes('good')));

            let highlightText = '';
            if (withGoodScreen.length > 0) {
                highlightText = `📺 ${withGoodScreen.length} with Good Screen (e.g. <strong>${this.escapeHtml(withGoodScreen[0].sku)}</strong>)`;
            } else if (withGoodBattery.length > 0) {
                highlightText = `🔋 ${withGoodBattery.length} with Good Battery (e.g. <strong>${this.escapeHtml(withGoodBattery[0].sku)}</strong>)`;
            } else {
                highlightText = `Found ${matches.length} storage unit(s)`;
            }

            container.innerHTML = `
                <div style="background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%); border: 1.5px solid #38bdf8; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);">
                    <div style="display: flex; align-items: center; gap: 10px; font-size: 0.92rem; color: #0369a1;">
                        <span style="font-size: 1.3rem;">⚡</span>
                        <div>
                            <strong style="color: #0c4a6e;">Storage Donor Match Available:</strong> 
                            ${highlightText} for <em>${this.escapeHtml(term)}</em>.
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" onclick="TechWorkbench.DonorMatch.toggleDrawer()" style="background: #0284c7; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.82rem; cursor: pointer;">
                            View Donors (${matches.length})
                        </button>
                        <a href="storage.php?search=${encodeURIComponent(term)}" target="_blank" style="background: white; color: #0369a1; border: 1px solid #7dd3fc; padding: 6px 10px; border-radius: 6px; font-weight: 700; font-size: 0.82rem; text-decoration: none;" title="Open Storage Hub in new tab">
                            Open Hub ↗
                        </a>
                    </div>
                </div>

                <!-- Collapsible Donor Details Drawer -->
                <div id="donorDetailsDrawer" style="display: none; background: #ffffff; border: 1px solid #bae6fd; border-radius: 8px; margin-top: 8px; padding: 12px; max-height: 200px; overflow-y: auto;">
                    <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: #0284c7; margin-bottom: 8px;">Available Matching Donors in Storage:</div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        ${matches.map(m => `
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem;">
                                <div>
                                    <span style="font-family: monospace; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px;">${this.escapeHtml(m.sku)}</span>
                                    <strong>${this.escapeHtml(m.brand)} ${this.escapeHtml(m.model_num)}</strong>
                                    <span style="color: #64748b; font-size: 0.78rem;">(${this.escapeHtml(m.issue || 'In Storage')})</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 0.78rem;">SCR: <strong>${this.escapeHtml(m.screen || '-')}</strong></span>
                                    <span style="font-size: 0.78rem;">BAT: <strong>${this.escapeHtml(m.battery || '-')}</strong></span>
                                    <button type="button" onclick="TechWorkbench.Storage?.copySku ? TechWorkbench.Storage.copySku('${m.sku}') : navigator.clipboard.writeText('${m.sku}')" style="background: none; border: 1px solid #cbd5e1; border-radius: 4px; padding: 3px 8px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">Copy SKU</button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        },

        toggleDrawer() {
            const drawer = document.getElementById('donorDetailsDrawer');
            if (drawer) {
                drawer.style.display = drawer.style.display === 'none' ? 'block' : 'none';
            }
        },

        clearAlert() {
            const container = document.getElementById('donorMatchAlertContainer');
            if (container) {
                container.innerHTML = '';
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

    TechWorkbench.DonorMatch = StorageDonorMatch;

    document.addEventListener('DOMContentLoaded', () => {
        StorageDonorMatch.init();
    });
})();
