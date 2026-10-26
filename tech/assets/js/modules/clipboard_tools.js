/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: clipboard_tools.js
 * 1-Click Clipboard Copying (Serial Numbers & Formatted Technical Specs)
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Provides reliable clipboard writing via navigator.clipboard or fallback.
 * - Formats full device hardware specifications into clean, standard e-commerce strings.
 * - Displays a temporary floating feedback toast notification on the bench screen.
 * - Exposes `TechWorkbench.Clipboard.copySerial()`, `copySpecs()`, `showToast()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    let toastTimeout = null;

    function writeToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback for non-HTTPS local IP benches
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                document.body.removeChild(textArea);
                return Promise.resolve();
            } catch (err) {
                document.body.removeChild(textArea);
                return Promise.reject(err);
            }
        }
    }

    const ClipboardTools = {
        init() {
            this.ensureToastElement();
        },

        ensureToastElement() {
            if (!document.getElementById('benchCopyToast')) {
                const toast = document.createElement('div');
                toast.id = 'benchCopyToast';
                toast.className = 'copy-feedback-toast';
                document.body.appendChild(toast);
            }
        },

        showToast(messageHtml) {
            this.ensureToastElement();
            const toast = document.getElementById('benchCopyToast');
            if (!toast) return;

            toast.innerHTML = messageHtml;
            toast.classList.add('show');

            if (toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 2200);

            if (window.TechWorkbench.Audio) {
                window.TechWorkbench.Audio.playClick();
            }
        },

        /**
         * Copies raw serial number to clipboard with visual toast.
         * @param {string} sn 
         */
        copySerial(sn) {
            if (!sn) return;
            writeToClipboard(sn).then(() => {
                this.showToast(`<span>📋</span> <span>Copied Serial: <strong>${sn}</strong></span>`);
            }).catch(() => {
                this.showToast(`<span>❌</span> <span>Failed to copy to clipboard</span>`);
            });
        },

        /**
         * Formats and copies a clean, complete technical specification string.
         * @param {Object} log
         */
        copySpecs(log) {
            if (!log) return;
            const parts = [];

            // 1. Identity
            const title = [log.make, log.model, log.series].filter(Boolean).join(' ');
            if (title) parts.push(title);

            // 2. CPU
            const cpu = log.cpu || log.cpu_specs || '';
            if (cpu) parts.push(cpu);

            // 3. RAM & Storage
            if (log.ram) parts.push(log.ram);
            if (log.storage) parts.push(log.storage);

            // 4. GPU / Screen
            if (log.gpu && !log.gpu.toLowerCase().includes('integrated')) parts.push(log.gpu);
            if (log.screen_res) parts.push(log.screen_res);

            // 5. OS & Grade
            if (log.os) parts.push(log.os);
            if (log.cosmetic_grade) parts.push(`Grade ${log.cosmetic_grade}`);

            // 6. Serial Number
            if (log.serial_number) parts.push(`S/N: ${log.serial_number}`);

            const specString = parts.join(' | ');

            writeToClipboard(specString).then(() => {
                this.showToast(`<span>📋</span> <span>Hardware specs copied to clipboard!</span>`);
            }).catch(() => {
                this.showToast(`<span>❌</span> <span>Failed to copy specs</span>`);
            });
        }
    };

    exports.Clipboard = ClipboardTools;
})(window.TechWorkbench);
