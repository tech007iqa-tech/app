/**
 * assets/js/print_engine.js
 * Manages the global Print Configuration Modal and Direct Windows Printing.
 */

let currentPrintId = null;
let labelAStatus = true;
let labelBStatus = true;

document.addEventListener('DOMContentLoaded', () => {
    const modal       = document.getElementById('printModal');
    const btnA        = document.getElementById('prevLabelA');
    const btnB        = document.getElementById('prevLabelB');
    const qtyInput    = document.getElementById('printQty');
    const confirmBtn  = document.getElementById('confirmPrintBtn');

    if (!modal) return;

    // Toggle Label A
    btnA.addEventListener('click', () => {
        labelAStatus = !labelAStatus;
        btnA.style.borderColor = labelAStatus ? 'var(--accent-color)' : 'var(--border-color)';
        btnA.style.opacity = labelAStatus ? '1' : '0.5';
    });

    // Toggle Label B
    btnB.addEventListener('click', () => {
        labelBStatus = !labelBStatus;
        btnB.style.borderColor = labelBStatus ? 'var(--accent-color)' : 'var(--border-color)';
        btnB.style.opacity = labelBStatus ? '1' : '0.5';
    });

    // Confirm Print
    confirmBtn.addEventListener('click', async () => {
        if (!labelAStatus && !labelBStatus) {
            alert("Please select at least one page to print.");
            return;
        }

        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<span>⏳ Sending to Windows...</span>';
        confirmBtn.disabled = true;

        const fd = new FormData();
        fd.append('id', currentPrintId);
        fd.append('qty', qtyInput.value);
        fd.append('print_a', labelAStatus ? '1' : '0');
        fd.append('print_b', labelBStatus ? '1' : '0');
        fd.append('mode', 'open'); // CRITICAL: This triggers the local launch

        try {
            const res = await fetch('api/reprint_label.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (json.success) {
                // Success! The file should be opening in Windows on the server host.
                modal.style.display = 'none';
            } else {
                alert("Print Error: " + json.error);
            }
        } catch (err) {
            alert("Network error communicating with the Print Engine.");
        } finally {
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    });
});

/**
 * Global function to trigger the print modal from anywhere (labels.php, new_label.php, etc.)
 * @param {number} id - The database ID of the hardware item.
 */
window.openPrintConfig = function(id) {
    currentPrintId = id;

    // Reset defaults
    labelAStatus = true;
    labelBStatus = true;
    const btnA = document.getElementById('prevLabelA');
    const btnB = document.getElementById('prevLabelB');
    if(btnA) { btnA.style.borderColor = 'var(--accent-color)'; btnA.style.opacity = '1'; }
    if(btnB) { btnB.style.borderColor = 'var(--accent-color)'; btnB.style.opacity = '1'; }

    document.getElementById('printQty').value = 1;
    document.getElementById('printModal').style.display = 'flex';
};
