/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: bench_hotkeys.js
 * Warehouse Bench Keyboard Shortcuts & Modal Navigation
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Listens globally on document keydown.
 * - Respects active inputs so technicians can type freely without triggering shortcuts.
 * - Exposes `TechWorkbench.Hotkeys.init()`, `openHelpModal()`, `closeHelpModal()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    const BenchHotkeys = {
        init() {
            this.bindGlobalShortcuts();
            this.injectHelpModal();
        },

        bindGlobalShortcuts() {
            document.addEventListener('keydown', (e) => {
                const target = e.target;
                const isTyping = (
                    target.tagName === 'INPUT' || 
                    target.tagName === 'TEXTAREA' || 
                    target.tagName === 'SELECT' || 
                    target.isContentEditable
                );

                // 1. ESCAPE: Always closes any active modal regardless of focus
                if (e.key === 'Escape') {
                    if (document.getElementById('hotkeyHelpModal')?.style.display === 'flex') {
                        e.preventDefault();
                        this.closeHelpModal();
                        return;
                    }
                    if (document.getElementById('intakeModal')?.style.display === 'flex') {
                        e.preventDefault();
                        if (typeof window.closeIntakeModal === 'function') window.closeIntakeModal();
                        return;
                    }
                    if (document.getElementById('deliveryModal')?.style.display === 'flex') {
                        e.preventDefault();
                        if (typeof window.closeDeliveryModal === 'function') window.closeDeliveryModal();
                        return;
                    }
                    if (document.getElementById('editModal')?.style.display === 'flex') {
                        e.preventDefault();
                        if (typeof window.closeEditModal === 'function') window.closeEditModal();
                        return;
                    }
                }

                // 2. CTRL + ENTER: Quick Save Hardware Log inside intake modal
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    const intakeModal = document.getElementById('intakeModal');
                    if (intakeModal && intakeModal.style.display === 'flex') {
                        e.preventDefault();
                        if (typeof window.submitIntake === 'function') {
                            window.submitIntake('save');
                        }
                        return;
                    }
                }

                // 3. ALT + N or F2: Open New Hardware Intake dialog
                if ((e.altKey && (e.key === 'n' || e.key === 'N')) || e.key === 'F2') {
                    e.preventDefault();
                    if (typeof window.openIntakeModal === 'function') {
                        window.openIntakeModal();
                    }
                    return;
                }

                // 4. ALT + P: Save & Print Thermal Label (when modal is open)
                if (e.altKey && (e.key === 'p' || e.key === 'P')) {
                    const intakeModal = document.getElementById('intakeModal');
                    if (intakeModal && intakeModal.style.display === 'flex') {
                        e.preventDefault();
                        if (typeof window.submitIntake === 'function') {
                            window.submitIntake('thermal');
                        }
                        return;
                    }
                }

                // 5. ALT + 1 / ALT + 2: Toggle Good vs. Bad laptop status
                if (e.altKey && (e.key === '1' || e.key === '2')) {
                    const intakeModal = document.getElementById('intakeModal');
                    if (intakeModal && intakeModal.style.display === 'flex') {
                        e.preventDefault();
                        const status = (e.key === '1') ? 'Good' : 'Bad';
                        if (typeof window.setIntakeStatus === 'function') {
                            window.setIntakeStatus(status);
                        }
                        return;
                    }
                }

                // 6. ALT + M: Toggle Sound Effects
                if (e.altKey && (e.key === 'm' || e.key === 'M')) {
                    e.preventDefault();
                    if (window.TechWorkbench.Audio) {
                        window.TechWorkbench.Audio.toggleMute();
                    }
                    return;
                }

                // 7. ALT + / or F3: Focus Search Box
                if ((e.altKey && e.key === '/') || e.key === 'F3') {
                    e.preventDefault();
                    const searchInput = document.getElementById('log_search');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                    return;
                }

                // 8. SHIFT + ? (Question mark): Open Keyboard Shortcuts Modal (only when NOT typing)
                if (e.key === '?' && !isTyping) {
                    e.preventDefault();
                    this.openHelpModal();
                }
            });
        },

        injectHelpModal() {
            if (document.getElementById('hotkeyHelpModal')) return;

            const modalHtml = `
                <div id="hotkeyHelpModal" class="hotkey-help-overlay">
                    <div class="hotkey-help-card">
                        <div class="hotkey-help-header">
                            <h3><span>⌨️</span> Warehouse Bench Keyboard Shortcuts</h3>
                            <button type="button" onclick="TechWorkbench.Hotkeys.closeHelpModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
                        </div>
                        <div class="hotkey-help-grid">
                            <div class="hotkey-item">
                                <span class="hotkey-desc">New Hardware Intake</span>
                                <div><kbd class="hotkey-badge">Alt</kbd> + <kbd class="hotkey-badge">N</kbd> <span style="font-size:0.75rem; color:#64748b;">or F2</span></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Save Hardware Log</span>
                                <div><kbd class="hotkey-badge">Ctrl</kbd> + <kbd class="hotkey-badge">Enter</kbd></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Print Thermal Label</span>
                                <div><kbd class="hotkey-badge">Alt</kbd> + <kbd class="hotkey-badge">P</kbd></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Set Status: Good / Bad</span>
                                <div><kbd class="hotkey-badge">Alt</kbd> + <kbd class="hotkey-badge">1</kbd> / <kbd class="hotkey-badge">2</kbd></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Close Modal / Dialog</span>
                                <div><kbd class="hotkey-badge">Esc</kbd></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Focus Search Logs</span>
                                <div><kbd class="hotkey-badge">Alt</kbd> + <kbd class="hotkey-badge">/</kbd> <span style="font-size:0.75rem; color:#64748b;">or F3</span></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Toggle Audio Cues</span>
                                <div><kbd class="hotkey-badge">Alt</kbd> + <kbd class="hotkey-badge">M</kbd></div>
                            </div>
                            <div class="hotkey-item">
                                <span class="hotkey-desc">Open Shortcuts Cheat Sheet</span>
                                <div><kbd class="hotkey-badge">?</kbd></div>
                            </div>
                        </div>
                        <div style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                            <button type="button" onclick="TechWorkbench.Hotkeys.closeHelpModal()" class="btn-primary" style="padding: 8px 18px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; background: #2563eb; color: white; border: none;">Got It</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },

        openHelpModal() {
            const m = document.getElementById('hotkeyHelpModal');
            if (m) m.style.display = 'flex';
        },

        closeHelpModal() {
            const m = document.getElementById('hotkeyHelpModal');
            if (m) m.style.display = 'none';
        }
    };

    exports.Hotkeys = BenchHotkeys;
})(window.TechWorkbench);
