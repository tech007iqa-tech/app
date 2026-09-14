/**
 * B2B Work Order AI Import script
 */

let woUploadedFile = null;
let woConfidence = 98;
let woItems = [];

const woViewer = {
    angle: 0,
    zoomLevel: 1,
    rotate(deg) {
        this.angle = (this.angle + deg) % 360;
        this.apply();
    },
    zoom(factor) {
        this.zoomLevel = Math.max(0.1, this.zoomLevel + factor);
        this.apply();
    },
    apply() {
        const el = document.getElementById('workorder-preview-element');
        if (el) {
            el.style.transform = `rotate(${this.angle}deg) scale(${this.zoomLevel})`;
        }
    },
    reset() {
        this.angle = 0;
        this.zoomLevel = 1;
        this.apply();
    }
};

const woGrid = {
    toggleAll(master) {
        const checkboxes = document.querySelectorAll('.wo-row-select');
        checkboxes.forEach(cb => cb.checked = master.checked);
    },

    handlePriceChange(input) {
        // Remove the suggested price styling and tag once the user touches it
        input.style.fontStyle = 'normal';
        input.style.color = '';
        input.style.fontWeight = 'bold';

        const badge = input.parentNode.querySelector('.suggested-price-badge');
        if (badge) {
            badge.style.display = 'none';
        }
    }
};

window.woViewer = woViewer;
window.woGrid = woGrid;

async function checkApiKeyConfig() {
    try {
        const response = await fetch('api/process_work_order.php?action=get_config');
        if (response.ok) {
            const res = await response.json();
            const warningEl = document.getElementById('workorder-key-warning');
            if (res.success && (!res.config || !res.config.gemini_api_key)) {
                if (warningEl) warningEl.style.display = 'block';
            } else {
                if (warningEl) warningEl.style.display = 'none';
            }
        }
    } catch (e) {
        console.error('Error checking API key:', e);
    }
}

function initWorkOrderImport() {
    checkApiKeyConfig();

    const dropzone = document.getElementById('workorder-dropzone');
    const fileInput = document.getElementById('workorder-file-input');

    if (!dropzone || dropzone.dataset.initialized) return;
    dropzone.dataset.initialized = 'true';

    // Click to select
    dropzone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleWorkOrderFile(e.target.files[0]);
        }
    });

    // Drag and drop event handlers
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--accent-color)';
            dropzone.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '#cbd5e1';
            dropzone.style.backgroundColor = '#f8fafc';
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        if (dt.files.length > 0) {
            handleWorkOrderFile(dt.files[0]);
        }
    });
}

async function handleWorkOrderFile(file) {
    woUploadedFile = file;

    const dropzone = document.getElementById('workorder-dropzone');
    const loadingOverlay = document.getElementById('workorder-loading-overlay');
    const stage = document.getElementById('workorder-stage');
    const consoleCard = document.getElementById('workorder-console-card');
    const importBtn = document.getElementById('btn-submit-workorder-import');

    if (dropzone) dropzone.style.display = 'none';
    if (loadingOverlay) loadingOverlay.style.display = 'flex';
    if (stage) stage.style.display = 'none';
    if (consoleCard) consoleCard.style.display = 'none';
    if (importBtn) importBtn.style.display = 'none';

    // Renders Preview Element
    const viewerContainer = document.getElementById('workorder-viewer-container');
    if (viewerContainer) {
        viewerContainer.innerHTML = '';
        woViewer.reset();

        const fileUrl = URL.createObjectURL(file);
        if (file.type === 'application/pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = fileUrl + '#toolbar=0';
            iframe.id = 'workorder-preview-element';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            viewerContainer.appendChild(iframe);
        } else {
            const img = document.createElement('img');
            img.src = fileUrl;
            img.id = 'workorder-preview-element';
            img.style.maxWidth = '100%';
            img.style.maxHeight = '100%';
            img.style.objectFit = 'contain';
            viewerContainer.appendChild(img);
        }
    }

    // Call OCR Backend
    const formData = new FormData();
    formData.append('images[]', file);

    try {
        const response = await fetch('api/process_work_order.php?action=extract', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (loadingOverlay) loadingOverlay.style.display = 'none';

        if (result.success) {
            const ocrData = result.data || {};
            const rows = ocrData.rows || [];
            const rawOcr = ocrData.RawOCR || '';
            const avgConf = ocrData.AvgConfidence || 98;

            // Render OCR console
            const consoleArea = document.getElementById('workorder-ocr-console');
            if (consoleArea) consoleArea.textContent = rawOcr;
            if (consoleCard) consoleCard.style.display = 'flex';

            // Render average confidence
            const confSummary = document.getElementById('workorder-avg-confidence');
            if (confSummary) {
                confSummary.textContent = `Confidence: ${avgConf}%`;
            }

            renderWorkOrderGrid(rows);

            if (stage) stage.style.display = 'flex';
            if (importBtn) importBtn.style.display = 'block';

            if (window.IQA_Notify) {
                window.IQA_Notify.success('Work order AI extraction complete! ✨');
            }
        } else {
            alert('OCR extraction failed: ' + (result.error || 'Unknown error.'));
            resetWorkOrderImportUI();
        }
    } catch (err) {
        console.error(err);
        alert('API call failed or was interrupted.');
        if (loadingOverlay) loadingOverlay.style.display = 'none';
        resetWorkOrderImportUI();
    }
}

function renderWorkOrderGrid(rows) {
    const list = document.getElementById('workorder-list');
    if (!list) return;

    if (rows.length === 0) {
        list.innerHTML = `<tr><td colspan="8" style="padding:30px; text-align:center; color:#94a3b8;">No table rows could be parsed. Try a clearer scan.</td></tr>`;
        return;
    }

    list.innerHTML = rows.map((row, idx) => {
        const priceStyle = row.is_suggested_price ? 'font-style: italic; color: #2563eb; font-weight: 500;' : '';
        const suggestedBadge = row.is_suggested_price ? '<span class="suggested-price-badge" style="position: absolute; right: 8px; top: 12px; font-size: 0.65rem; color: #3b82f6; font-weight: bold; cursor: help;" title="CPU average market price suggestion">💡</span>' : '';

        return `
            <tr class="wo-item-row" style="border-bottom:1px solid #e2e8f0;">
                <td style="padding: 10px; text-align: center;">
                    <input type="checkbox" class="wo-row-select" checked>
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-brand" value="${escapeHTML(row.brand)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-model" value="${escapeHTML(row.model)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-series" value="${escapeHTML(row.series)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-cpu" value="${escapeHTML(row.cpu)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-desc" value="${escapeHTML(row.description)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
                <td style="padding: 8px 5px;">
                    <input type="number" class="wo-cell-qty" value="${parseInt(row.quantity || 1)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem; text-align: center; font-weight: bold;">
                </td>
                <td style="padding: 8px 5px; position: relative;">
                    <input type="number" step="0.01" class="wo-cell-price" value="${parseFloat(row.unit_price || 0.00).toFixed(2)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 20px 4px 6px; font-size: 0.75rem; text-align: right; ${priceStyle}" oninput="woGrid.handlePriceChange(this)">
                    ${suggestedBadge}
                </td>
                <td style="padding: 8px 5px;">
                    <input type="text" class="wo-cell-notes" value="${escapeHTML(row.notes)}" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; font-size: 0.75rem;">
                </td>
            </tr>
        `;
    }).join('');
}

function resetWorkOrderImportUI() {
    woUploadedFile = null;
    const dropzone = document.getElementById('workorder-dropzone');
    const loadingOverlay = document.getElementById('workorder-loading-overlay');
    const stage = document.getElementById('workorder-stage');
    const consoleCard = document.getElementById('workorder-console-card');
    const importBtn = document.getElementById('btn-submit-workorder-import');

    if (dropzone) dropzone.style.display = 'block';
    if (loadingOverlay) loadingOverlay.style.display = 'none';
    if (stage) stage.style.display = 'none';
    if (consoleCard) consoleCard.style.display = 'none';
    if (importBtn) importBtn.style.display = 'none';

    const fileInput = document.getElementById('workorder-file-input');
    if (fileInput) fileInput.value = '';
}

async function submitWorkOrderImport() {
    const checkedRows = document.querySelectorAll('.wo-item-row:has(.wo-row-select:checked)');
    if (checkedRows.length === 0) {
        alert("Please select at least one row to import.");
        return;
    }

    const btn = document.getElementById('btn-submit-workorder-import');
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Importing...';
    btn.disabled = true;

    const items = [];
    let isValid = true;

    checkedRows.forEach(tr => {
        const brand = tr.querySelector('.wo-cell-brand').value.trim();
        const model = tr.querySelector('.wo-cell-model').value.trim();
        const series = tr.querySelector('.wo-cell-series').value.trim();
        const cpu = tr.querySelector('.wo-cell-cpu').value.trim();
        const desc = tr.querySelector('.wo-cell-desc').value.trim();
        const notes = tr.querySelector('.wo-cell-notes').value.trim();
        const qty = parseInt(tr.querySelector('.wo-cell-qty').value) || 1;
        const price = parseFloat(tr.querySelector('.wo-cell-price').value) || 0.00;

        if (!brand || !model) {
            isValid = false;
        }

        items.push({
            brand: brand,
            model: model,
            series: series || 'N/A',
            cpu: cpu,
            description: desc,
            notes: notes,
            quantity: qty,
            unit_price: price
        });
    });

    if (!isValid) {
        alert("Each imported row must contain both a Brand and Model name.");
        btn.innerHTML = originalText;
        btn.disabled = false;
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    try {
        const response = await fetch('api/bulk_update_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'bulk_import',
                csrf_token: csrfToken,
                customer_id: activeImportCustomerId,
                order_id: activeImportOrderId,
                items: items
            })
        });

        const result = await response.json();
        if (result.success) {
            btn.innerHTML = '✅ Success!';
            if (window.IQA_Notify) {
                window.IQA_Notify.success('Items successfully imported to order builder! ✨');
            }
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('Import failed: ' + (result.error || 'Unknown error.'));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert('Network error during import.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[m]));
}

window.initWorkOrderImport = initWorkOrderImport;
window.submitWorkOrderImport = submitWorkOrderImport;
