/**
 * labels/assets/js/actions.js
 * Universal Hardware Actions: Direct Web Print, ODT Launch, Quick View Modal, and Sleek Toasts.
 */
'use strict';

// ── 1. MODERN TOAST NOTIFICATION ENGINE ──────────────────────────────────────
const Toast = {
    container: null,

    init() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'info', duration = 3500) {
        this.init();

        const toast = document.createElement('div');
        toast.className = `toast-pill toast-${type} animate-fade-in`;

        const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : (type === 'warning' ? '⚠️' : 'ℹ️'));
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()">✕</button>
        `;

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-fade-out');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error', 4500); },
    info(msg) { this.show(msg, 'info'); },
    warning(msg) { this.show(msg, 'warning'); }
};

// ── 2. DIRECT THERMAL WEB PRINTING ──────────────────────────────────────────
/**
 * Opens the high-fidelity 2" x 1" thermal label direct print view.
 * @param {number} id - Hardware Item ID
 * @param {string} mode - 'both', 'a', or 'b'
 * @param {number} qty - Number of copies
 */
function printThermalLabel(id, mode = 'both', qty = 1) {
    if (!id || id <= 0) {
        Toast.error("Invalid hardware ID for printing.");
        return;
    }

    const printUrl = `print_label.php?id=${id}&mode=${encodeURIComponent(mode)}&qty=${qty}&autoprint=1`;
    const printWindow = window.open(printUrl, `PrintLabel_${id}`, 'width=650,height=600,menubar=no,toolbar=no,location=no,status=no');
    if (printWindow) {
        printWindow.focus();
    } else {
        // Fallback if popup blocked: open in new tab
        window.open(printUrl, '_blank');
    }
}

// ── 3. WINDOWS ODT LAUNCH / DOWNLOAD ─────────────────────────────────────────
/**
 * Generates and downloads or launches the LibreOffice .odt file.
 * @param {number} id
 * @param {string} brand
 * @param {string} model
 * @param {HTMLElement|null} btn
 */
async function flashOpenLabel(id, brand = '', model = '', btn = null) {
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-inline">⏳</span> Generating...';
    }

    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('mode', 'download');
        formData.append('qty', 1);
        formData.append('print_a', '1');
        formData.append('print_b', '1');

        const res = await fetch('api/reprint_label.php', { method: 'POST', body: formData });
        const json = await res.json();

        if (!json.success) {
            Toast.error('ODT Generation failed: ' + (json.error || 'Unknown error'));
            return;
        }

        const filePath = json.data.file_path;
        const fileName = json.data.file_name;

        // Auto-download file for client workstation
        const link = document.createElement('a');
        link.href = filePath;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        Toast.success(`Label file ready: ${fileName}`);

    } catch (err) {
        console.error(err);
        Toast.error('Network error generating ODT label.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}

// ── 4. QUICK VIEW MODAL ──────────────────────────────────────────────────────
/**
 * Opens a quick modal showing device specifications without navigating away.
 * @param {number} id
 */
async function quickViewHardware(id) {
    const modal = document.getElementById('quickViewModal');
    const title = document.getElementById('qvTitle');
    const content = document.getElementById('qvContent');
    const footer = document.getElementById('qvFooter');

    if (!modal) return;

    modal.style.display = 'flex';
    title.textContent = `Hardware Profile #${String(id).padStart(5, '0')}`;
    content.innerHTML = '<div class="modal-loading-spinner">⏳ Loading hardware specs...</div>';

    try {
        const res = await fetch(`api/search_item.php?id=${encodeURIComponent(id)}`);
        const json = await res.json();

        if (!json.success || !json.data || !json.data.results || json.data.results.length === 0) {
            content.innerHTML = '<div class="modal-error-notice">Hardware record not found.</div>';
            return;
        }

        const item = json.data.results[0];
        const brandModel = `${item.brand || ''} ${item.model || ''} ${item.series || ''}`.trim();
        const cpuSpecs = item.cpu_specs || item.cpu_gen || '—';
        const ram = item.ram || 'None';
        const storage = item.storage || 'None';
        const location = item.warehouse_location || 'Unassigned';
        const condition = item.description || 'Untested';
        const status = item.status || 'In Warehouse';
        const sn = item.serial_number || 'No S/N';
        const battery = item.battery == 1 ? 'Yes / Included' : (item.battery == '0' ? 'No / Missing' : '—');
        const gpu = item.gpu || 'Integrated';
        const os = item.os_version || 'None';
        const bios = item.bios_state || 'Unknown';

        content.innerHTML = `
            <div class="qv-detail-grid">
                <div class="qv-hero-card">
                    <div class="qv-hero-header">
                        <h4>${brandModel}</h4>
                        <span class="badge ${condition === 'Refurbished' ? 'badge-success' : (condition === 'For Parts' ? 'badge-danger' : 'badge-warning')}">${condition}</span>
                    </div>
                    <div class="qv-sn-tag">S/N: <code>${sn}</code> &nbsp;|&nbsp; 📍 <strong>${location}</strong></div>
                </div>

                <div class="qv-specs-table">
                    <div class="qv-row"><span>Processor (CPU):</span> <strong>${cpuSpecs}</strong></div>
                    <div class="qv-row"><span>Memory (RAM):</span> <strong>${ram}</strong></div>
                    <div class="qv-row"><span>Storage Drive:</span> <strong>${storage}</strong></div>
                    <div class="qv-row"><span>Graphics (GPU):</span> <strong>${gpu}</strong></div>
                    <div class="qv-row"><span>Operating System:</span> <strong>${os}</strong></div>
                    <div class="qv-row"><span>Battery Status:</span> <strong>${battery}</strong></div>
                    <div class="qv-row"><span>BIOS State:</span> <strong>${bios}</strong></div>
                    <div class="qv-row"><span>Warehouse Status:</span> <strong>${status}</strong></div>
                </div>
            </div>
        `;

        footer.innerHTML = `
            <button type="button" class="btn btn-secondary" onclick="closeQuickViewModal()">Close</button>
            <a href="hardware_view.php?id=${id}" class="btn btn-primary">🛠️ Full Tech Sheet</a>
            <button type="button" class="btn btn-success" onclick="printThermalLabel(${id}); closeQuickViewModal();">🖨️ Thermal Print</button>
            <button type="button" class="btn btn-dark" onclick="flashOpenLabel(${id}, '${item.brand}', '${item.model}', this)">📄 Open ODT</button>
        `;

    } catch (err) {
        content.innerHTML = '<div class="modal-error-notice">Failed to load hardware record.</div>';
    }
}

function closeQuickViewModal() {
    const modal = document.getElementById('quickViewModal');
    if (modal) modal.style.display = 'none';
}
