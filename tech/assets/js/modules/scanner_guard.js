/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: scanner_guard.js
 * Barcode Scanner Auto-Focus Guard & Keystroke Redirect Buffer
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Handheld 2D/1D scanners emit keystrokes at superhuman speed (<25ms per char).
 * - If focus drifted away from the serial input while the modal is open, this module
 *   intercepts the scanner burst, focuses the serial input, and pipes the buffer in.
 * - Exposes `TechWorkbench.ScannerGuard.init()`, `focusSerialInput()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    let keyBuffer = '';
    let lastKeyTime = 0;
    const SCANNER_MAX_CHAR_INTERVAL_MS = 60; // Max ms between chars to qualify as hardware scanner

    const ScannerGuard = {
        init() {
            this.bindAutofocusHooks();
            this.bindKeystrokeRedirector();
        },

        /**
         * Returns the currently active serial input element depending on intake mode.
         */
        getActiveSerialInput() {
            const batchCard = document.getElementById('batchIntakeCard');
            const isBatchVisible = batchCard && !batchCard.classList.contains('hidden') && batchCard.style.display !== 'none';
            if (isBatchVisible) {
                return document.getElementById('batch_sn_scanner');
            }
            return document.getElementById('intake_serial_number');
        },

        /**
         * Safely focus and highlight the serial number input.
         */
        focusSerialInput() {
            const input = this.getActiveSerialInput();
            if (input) {
                setTimeout(() => {
                    input.focus();
                    if (input.select) input.select();
                }, 80);
            }
        },

        /**
         * Ensure opening intake modal automatically positions cursor into serial input.
         */
        bindAutofocusHooks() {
            // Observe intake modal style changes for 'display: flex'
            const modal = document.getElementById('intakeModal');
            if (modal) {
                const observer = new MutationObserver((mutations) => {
                    for (const m of mutations) {
                        if (m.type === 'attributes' && m.attributeName === 'style') {
                            if (modal.style.display === 'flex') {
                                ScannerGuard.focusSerialInput();
                            }
                        }
                    }
                });
                observer.observe(modal, { attributes: true });
            }
        },

        /**
         * Hardware scanner burst detector.
         */
        bindKeystrokeRedirector() {
            document.addEventListener('keydown', (e) => {
                const modal = document.getElementById('intakeModal');
                const isModalOpen = modal && modal.style.display === 'flex';
                if (!isModalOpen) return;

                const activeEl = document.activeElement;
                const input = ScannerGuard.getActiveSerialInput();
                if (!input) return;

                // If input is already focused, let normal input events take charge
                if (activeEl === input) return;

                // Ignore control, alt, meta combinations
                if (e.ctrlKey || e.altKey || e.metaKey) return;

                // Ignore single non-character keys (Tab, Esc, Arrow, etc.)
                if (e.key.length > 1 && e.key !== 'Enter') return;

                const now = Date.now();
                const interval = now - lastKeyTime;
                lastKeyTime = now;

                if (e.key === 'Enter') {
                    if (keyBuffer.length >= 3 && interval < 100) {
                        // Rapid scanner scan completed while unfocused
                        input.value = keyBuffer;
                        input.focus();
                        keyBuffer = '';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        e.preventDefault();
                    } else {
                        keyBuffer = '';
                    }
                    return;
                }

                // Accumulate scanner burst
                if (interval < SCANNER_MAX_CHAR_INTERVAL_MS || keyBuffer.length === 0) {
                    keyBuffer += e.key;
                } else {
                    keyBuffer = e.key;
                }

                // If at least 3 chars have arrived in rapid succession, force focus into input
                if (keyBuffer.length >= 3 && interval < SCANNER_MAX_CHAR_INTERVAL_MS) {
                    input.value = (input.value ? input.value + keyBuffer : keyBuffer);
                    input.focus();
                    keyBuffer = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }
    };

    exports.ScannerGuard = ScannerGuard;
})(window.TechWorkbench);
