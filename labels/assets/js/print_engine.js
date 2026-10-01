/**
 * labels/assets/js/print_engine.js
 * Controls the Global Print Configuration Modal (Dual Choice: Direct Web Thermal vs Windows ODT)
 */
'use strict';

let currentPrintId = null;
let labelAStatus = true;
let labelBStatus = true;

/**
 * Open the print modal for an item
 * @param {number} id
 */
window.openPrintConfig = function(id) {
    currentPrintId = id;
    labelAStatus = true;
    labelBStatus = true;

    const modal = document.getElementById('printModal');
    const btnA = document.getElementById('prevLabelA');
    const btnB = document.getElementById('prevLabelB');
    const qtyInput = document.getElementById('printQty');

    if (btnA) {
        btnA.classList.add('active');
        btnA.style.opacity = '1';
    }
    if (btnB) {
        btnB.classList.add('active');
        btnB.style.opacity = '1';
    }
    if (qtyInput) qtyInput.value = 1;

    if (modal) modal.style.display = 'flex';
};

window.closePrintModal = function() {
    const modal = document.getElementById('printModal');
    if (modal) modal.style.display = 'none';
};

window.toggleLabelPage = function(page) {
    if (page === 'a') {
        labelAStatus = !labelAStatus;
        const btn = document.getElementById('prevLabelA');
        if (btn) {
            btn.classList.toggle('active', labelAStatus);
            btn.style.opacity = labelAStatus ? '1' : '0.4';
        }
    } else if (page === 'b') {
        labelBStatus = !labelBStatus;
        const btn = document.getElementById('prevLabelB');
        if (btn) {
            btn.classList.toggle('active', labelBStatus);
            btn.style.opacity = labelBStatus ? '1' : '0.4';
        }
    }
};

window.adjustPrintQty = function(delta) {
    const qtyInput = document.getElementById('printQty');
    if (!qtyInput) return;
    let val = parseInt(qtyInput.value, 10) || 1;
    val = Math.max(1, Math.min(100, val + delta));
    qtyInput.value = val;
};

document.addEventListener('DOMContentLoaded', () => {
    const directPrintBtn = document.getElementById('btnBrowserDirectPrint');
    const odtPrintBtn = document.getElementById('confirmPrintBtn');
    const qtyInput = document.getElementById('printQty');

    // 1. Direct Web Thermal Print
    if (directPrintBtn) {
        directPrintBtn.addEventListener('click', () => {
            if (!labelAStatus && !labelBStatus) {
                Toast.warning("Please select at least one sticker page.");
                return;
            }

            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            let mode = 'both';
            if (labelAStatus && !labelBStatus) mode = 'a';
            if (!labelAStatus && labelBStatus) mode = 'b';

            closePrintModal();
            printThermalLabel(currentPrintId, mode, qty);
        });
    }

    // 2. Windows ODT Generation
    if (odtPrintBtn) {
        odtPrintBtn.addEventListener('click', async () => {
            if (!labelAStatus && !labelBStatus) {
                Toast.warning("Please select at least one sticker page.");
                return;
            }

            const originalHtml = odtPrintBtn.innerHTML;
            odtPrintBtn.innerHTML = '<span>⏳ Generating...</span>';
            odtPrintBtn.disabled = true;

            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            const fd = new FormData();
            fd.append('id', currentPrintId);
            fd.append('qty', qty);
            fd.append('print_a', labelAStatus ? '1' : '0');
            fd.append('print_b', labelBStatus ? '1' : '0');
            fd.append('mode', 'download');

            try {
                const res = await fetch('api/reprint_label.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    const link = document.createElement('a');
                    link.href = json.data.file_path;
                    link.download = json.data.file_name;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    Toast.success(`Generated: ${json.data.file_name}`);
                    closePrintModal();
                } else {
                    Toast.error("ODT Error: " + (json.error || 'Failed'));
                }
            } catch (err) {
                Toast.error("Network error communicating with the label engine.");
            } finally {
                odtPrintBtn.innerHTML = originalHtml;
                odtPrintBtn.disabled = false;
            }
        });
    }
});
