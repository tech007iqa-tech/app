/**
 * Settings Perfect Paper Passwords (PPP) Module
 * Handles cryptographic passcode generation, live grid preview, QR code rendering, and physical passcard printing.
 */

let activeSeqKey = "";
let pendingSeqKey = "";
let selectedRowIdx = 0;

function initPPPSettingsState() {
    const state = typeof getSettingsState === 'function' ? getSettingsState() : {};
    activeSeqKey = state.seq_key || "";
    selectedRowIdx = parseInt(state.saved_row_index || 0, 10);
}

function onManualKeyInput(val) {
    const clean = (val || '').replace(/[^0-9a-fA-F]/g, '');
    const isValid = clean.length >= 16 && clean.length <= 64;
    const errForced = document.getElementById('manual_key_error_forced');
    const errSecure = document.getElementById('manual_key_error_secure');

    if (errForced) {
        errForced.style.display = (val.trim() === '' || isValid) ? 'none' : 'block';
    }
    if (errSecure) {
        errSecure.style.display = (val.trim() === '' || isValid) ? 'none' : 'block';
    }
}

function applyManualKey(isForced) {
    const inputId = isForced ? 'manual_seq_key_input_forced' : 'manual_seq_key_input_secure';
    const inputEl = document.getElementById(inputId);
    if (!inputEl) return;

    const rawVal = (inputEl.value || '').replace(/[^0-9a-fA-F]/g, '');
    if (rawVal.length < 16 || rawVal.length > 64) {
        alert("Please enter a valid hexadecimal Sequence Key (32-hex 128-bit or 64-hex 256-bit).");
        return;
    }

    pendingSeqKey = rawVal.toUpperCase();

    // Update display key text field and hidden inputs
    const displayKeyInput = document.getElementById('ppp_display_key');
    if (displayKeyInput) {
        displayKeyInput.value = pendingSeqKey;
    }
    const hiddenKeyInput = document.getElementById('ppp_sequence_key_input');
    if (hiddenKeyInput) {
        hiddenKeyInput.value = pendingSeqKey;
    }

    // Reset selected row
    selectedRowIdx = 0;
    const rowInput = document.getElementById('ppp_row_index_input');
    if (rowInput) {
        rowInput.value = '0';
    }

    // Show QR and grid preview if they are hidden
    const qrWrapper = document.getElementById('qr-container-wrapper');
    if (qrWrapper) qrWrapper.style.display = 'flex';
    const gridSection = document.getElementById('ppp-grid-section');
    if (gridSection) gridSection.style.display = 'block';

    // Update QR images
    const encodedKey = encodeURIComponent(pendingSeqKey);
    const qrImg = document.getElementById('ppp_qr_img');
    if (qrImg) {
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
    }
    const qrLargeImg = document.getElementById('ppp_qr_large_img');
    if (qrLargeImg) {
        qrLargeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;
    }

    // Uncheck show-active checkbox
    const showActiveCheckbox = document.getElementById('ppp_show_active_checkbox');
    if (showActiveCheckbox) {
        showActiveCheckbox.checked = false;
    }
    const showActiveContainer = document.getElementById('ppp_show_active_container');
    if (showActiveContainer) {
        showActiveContainer.style.display = 'flex';
    }

    // Fetch new grid preview
    fetchGridPreview(pendingSeqKey);

    // Close modal
    if (window.dialogEngine) {
        window.dialogEngine.closeAnyOpenDialogs();
    }

    // Scroll to PPP card
    const pppCard = document.getElementById('ppp-card');
    if (pppCard) {
        pppCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function generateRandomHexKey() {
    const chars = '0123456789ABCDEF';
    let result = '';
    for (let i = 0; i < 64; i++) {
        result += chars[Math.floor(Math.random() * 16)];
    }
    return result;
}

function triggerGenKey() {
    pendingSeqKey = generateRandomHexKey();
    const displayKey = document.getElementById('ppp_display_key');
    if (displayKey) displayKey.value = pendingSeqKey;
    const hiddenKey = document.getElementById('ppp_sequence_key_input');
    if (hiddenKey) hiddenKey.value = pendingSeqKey;

    // Show QR container and grid if hidden
    const qrWrapper = document.getElementById('qr-container-wrapper');
    if (qrWrapper) qrWrapper.style.display = 'flex';
    const gridSection = document.getElementById('ppp-grid-section');
    if (gridSection) gridSection.style.display = 'block';

    // Update QR images
    const encodedKey = encodeURIComponent(pendingSeqKey);
    const qrImg = document.getElementById('ppp_qr_img');
    if (qrImg) qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
    const qrLargeImg = document.getElementById('ppp_qr_large_img');
    if (qrLargeImg) qrLargeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;

    // Reset selected row
    selectedRowIdx = 0;
    const rowInput = document.getElementById('ppp_row_index_input');
    if (rowInput) rowInput.value = '0';

    // Reset show-active checkbox
    const showActiveCheckbox = document.getElementById('ppp_show_active_checkbox');
    if (showActiveCheckbox) {
        showActiveCheckbox.checked = false;
    }
    const showActiveContainer = document.getElementById('ppp_show_active_container');
    if (showActiveContainer) {
        showActiveContainer.style.display = 'flex';
    }

    // Render grid for the pending key
    fetchGridPreview(pendingSeqKey);

    // Scroll to PPP card view
    const pppCard = document.getElementById('ppp-card');
    if (pppCard) {
        pppCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function fetchGridPreview(seqKey) {
    const lengthInput = document.getElementById('ppp_length_input');
    const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

    try {
        const response = await fetch(`index.php?view=settings&action=ajax_generate_ppp&seq_key=${seqKey}&length=${length}`);
        const data = await response.json();
        if (data.success) {
            renderGrid(data.passcodes, seqKey);
        }
    } catch(e) {
        console.error("Failed to load passcodes preview:", e);
    }
}

function renderGrid(passcodes, seqKey) {
    const tbody = document.getElementById('ppp-grid-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const state = typeof getSettingsState === 'function' ? getSettingsState() : {};
    const savedRowIdx = parseInt(state.saved_row_index || 0, 10);

    const checkbox = document.getElementById('ppp_show_active_checkbox');
    const showActive = checkbox && checkbox.checked;
    const currentSelectedIdx = showActive ? savedRowIdx : selectedRowIdx;

    for (let r = 0; r < 25; r++) {
        const rowNum = r + 1;
        const isSelected = (currentSelectedIdx === rowNum);
        const rowLabel = String(rowNum).padStart(2, '0');

        const tr = document.createElement('tr');
        tr.setAttribute('data-row-num', rowNum);
        tr.style.cursor = 'pointer';
        tr.style.background = isSelected ? '#e0f2fe' : ((r % 2 === 0) ? '#f8fafc' : '#ffffff');
        tr.onclick = function() { onRowClick(this, rowNum); };

        let tdRow = document.createElement('td');
        tdRow.style.padding = (r === 0) ? '14px 4px 10px 4px' : '10px 4px 10px 4px';
        tdRow.style.fontWeight = 'bold';
        tdRow.style.color = '#64748b';
        tdRow.style.borderRight = '1px solid #e2e8f0';
        tdRow.style.borderBottom = '1px solid #e2e8f0';
        tdRow.style.width = '60px';
        tdRow.innerText = rowLabel;
        tr.appendChild(tdRow);

        for (let c = 0; c < 5; c++) {
            let tdCell = document.createElement('td');
            tdCell.className = 'ppp-cell';
            tdCell.style.padding = (r === 0) ? '14px 4px 10px 4px' : '10px 4px 10px 4px';
            tdCell.style.fontWeight = 'bold';
            tdCell.style.letterSpacing = '0.5px';
            tdCell.style.borderBottom = '1px solid #e2e8f0';
            if (c < 4) tdCell.style.borderRight = '1px solid #e2e8f0';
            tdCell.style.wordBreak = 'break-all';

            tdCell.innerText = passcodes[r * 5 + c] || '';
            tr.appendChild(tdCell);
        }
        tbody.appendChild(tr);
    }

    // Update Sequence Key display text and QR codes
    const displayKeyInput = document.getElementById('ppp_display_key');
    if (displayKeyInput) {
        displayKeyInput.value = seqKey;
    }
    const encodedKey = encodeURIComponent(seqKey);
    const qrImg = document.getElementById('ppp_qr_img');
    if (qrImg) {
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
    }
    const qrLargeImg = document.getElementById('ppp_qr_large_img');
    if (qrLargeImg) {
        qrLargeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;
    }
    const qrCaptionKey = document.getElementById('ppp_qr_caption_key');
    if (qrCaptionKey) {
        qrCaptionKey.innerText = seqKey;
    }

    updatePrintCardSource(passcodes, seqKey);
}

function updatePrintCardSource(passcodes, seqKey) {
    const source = document.getElementById('ppp-printable-card-source');
    if (!source) return;

    const state = typeof getSettingsState === 'function' ? getSettingsState() : {};
    const username = state.username || '';

    const lengthInput = document.getElementById('ppp_length_input');
    const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

    let tableRowsHtml = '';
    for (let r = 0; r < 25; r++) {
        const rowLabel = String(r + 1).padStart(2, '0');
        let cellsHtml = '';
        for (let c = 0; c < 5; c++) {
            cellsHtml += `<td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; letter-spacing: 0.5px; white-space: nowrap;'>${passcodes[r * 5 + c] || ''}</td>`;
        }
        tableRowsHtml += `<tr>
            <td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; background: #fafafa; white-space: nowrap;'>${rowLabel}</td>
            ${cellsHtml}
        </tr>`;
    }

    source.innerHTML = `
        <div style="border: 2px dashed #333; border-radius: 12px; padding: 20px; max-width: 100%; width: 100%; box-sizing: border-box; background: white; color: black; font-family: 'Courier New', Courier, monospace; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin: 20px auto;">
            <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px;">
                <strong style="font-size: 16px; letter-spacing: 1px;">PERFECT PAPER PASSCARD</strong>
                <span style="font-size: 14px; font-weight: bold;">User: ${username}</span>
            </div>
            <div style="font-size: 10px; margin-bottom: 15px; word-break: break-all; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 6px;">
                <strong>SEQUENCE KEY:</strong><br>${seqKey}
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center; table-layout: auto;">
                <thead>
                    <tr style="border-bottom: 2px solid #000; background: #eee;">
                        <th style="padding: 5px 3px; border: 1px solid #ccc; width: 50px;">Row</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">A</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">B</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">C</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">D</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">E</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRowsHtml}
                </tbody>
            </table>
            <div style="margin-top: 15px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #eee; padding-top: 8px;">
                GRC Perfect Paper Passwords &bull; Password Length: ${length} &bull; Keep this card secure and offline.
            </div>
        </div>
    `;
}

function printPPPCard() {
    const source = document.getElementById('ppp-printable-card-source');
    if (!source) return;
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

function printQRCode() {
    const qrImg = document.getElementById('ppp_qr_large_img');
    const qrImgSrc = qrImg ? qrImg.src : '';
    const captionEl = document.getElementById('ppp_qr_caption_key');
    const key = captionEl ? captionEl.innerText : '';
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print QR Code</title><style>body{display:flex;flex-direction:column;align-items:center;justify-content:center;height:90vh;margin:0;font-family:monospace;text-align:center;}img{max-width:300px;margin-bottom:20px;}.key{font-size:1.2rem;word-break:break-all;max-width:600px;}</style></head><body><img src="' + qrImgSrc + '" alt="QR Code"><div class="key"><strong>Sequence Key:</strong><br>' + key + '</div></body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

function viewPPPCard() {
    const source = document.getElementById('ppp-printable-card-source');
    if (!source) return;
    const viewWindow = window.open('', '_blank');
    viewWindow.document.write('<html><head><title>PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
    viewWindow.document.close();
    viewWindow.focus();
}

async function onRowClick(rowElement, rowNum) {
    const state = typeof getSettingsState === 'function' ? getSettingsState() : {};
    const savedRowIdx = parseInt(state.saved_row_index || 0, 10);

    const checkbox = document.getElementById('ppp_show_active_checkbox');
    const showActive = checkbox && checkbox.checked;

    let targetKey = showActive ? activeSeqKey : (pendingSeqKey ? pendingSeqKey : activeSeqKey);
    if (!targetKey) {
        alert("Please generate a sequence key first!");
        return;
    }

    const currentSelectedIdx = showActive ? savedRowIdx : selectedRowIdx;

    if (rowNum === currentSelectedIdx) {
        const cells = rowElement.querySelectorAll('.ppp-cell');
        let passcodeStr = '';
        cells.forEach(c => passcodeStr += c.innerText);

        const confirmInput = document.getElementById('confirm_password');
        if (confirmInput) {
            confirmInput.value = passcodeStr;
        }

        navigator.clipboard.writeText(passcodeStr).then(() => {
            alert(`Row ${String(rowNum).padStart(2, '0')} passcode copied to clipboard and confirmed!`);
        }).catch(() => {
            alert(`Row ${String(rowNum).padStart(2, '0')} passcode confirmed!`);
        });
        return;
    }

    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput && newPasswordInput.value && newPasswordInput.value.trim() !== '') {
        if (rowNum !== currentSelectedIdx) {
            const proceed = confirm("You have already entered a password. Are you sure you want to change it to the passcodes in Row " + String(rowNum).padStart(2, '0') + "?");
            if (!proceed) {
                return;
            }
        }
    }

    if (showActive) {
        checkbox.checked = false;
        pendingSeqKey = "";
        const container = document.getElementById('ppp_show_active_container');
        if (container) container.style.display = 'none';
    } else if (pendingSeqKey) {
        activeSeqKey = pendingSeqKey;
        pendingSeqKey = "";
        const container = document.getElementById('ppp_show_active_container');
        if (container) container.style.display = 'none';
    }

    const hiddenSeqKeyInput = document.getElementById('ppp_sequence_key_input');
    if (hiddenSeqKeyInput) hiddenSeqKeyInput.value = activeSeqKey;

    selectedRowIdx = rowNum;
    const hiddenRowInput = document.getElementById('ppp_row_index_input');
    if (hiddenRowInput) hiddenRowInput.value = rowNum;

    // Update row highlights
    const tbody = document.getElementById('ppp-grid-tbody');
    if (tbody) {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((r, idx) => {
            const rNum = parseInt(r.getAttribute('data-row-num'), 10);
            if (rNum === selectedRowIdx) {
                r.style.background = '#e0f2fe';
            } else {
                r.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
            }
        });
    }

    const updatedRowElement = document.querySelector(`tr[data-row-num='${rowNum}']`);
    if (updatedRowElement) {
        const cells = updatedRowElement.querySelectorAll('.ppp-cell');
        let passcodeStr = '';
        cells.forEach(c => passcodeStr += c.innerText);

        if (newPasswordInput) newPasswordInput.value = passcodeStr;
        const confirmInput = document.getElementById('confirm_password');
        if (confirmInput) {
            confirmInput.value = '';
            confirmInput.focus();
        }
    }
}

function onLengthChange() {
    const input = document.getElementById('ppp_length_input');
    let val = parseInt(input.value) || 30;
    if (val < 25) val = 25;
    if (val > 80) val = 80;
    input.value = val;

    const checkbox = document.getElementById('ppp_show_active_checkbox');
    const showActive = checkbox && checkbox.checked;
    const targetKey = showActive ? activeSeqKey : (pendingSeqKey ? pendingSeqKey : activeSeqKey);
    if (targetKey) {
        fetchGridPreview(targetKey);
    }
}

function toggleShowActive() {
    const checkbox = document.getElementById('ppp_show_active_checkbox');
    if (!checkbox) return;

    if (checkbox.checked) {
        fetchGridPreview(activeSeqKey);
    } else {
        fetchGridPreview(pendingSeqKey || activeSeqKey);
    }
}

function copySequenceKey() {
    const input = document.getElementById('ppp_display_key');
    if (!input || !input.value) {
        alert("No sequence key generated yet!");
        return;
    }
    const key = input.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(() => {
            alert('Sequence Key copied!');
        }).catch(err => {
            fallbackCopyText(input);
        });
    } else {
        fallbackCopyText(input);
    }
}

function fallbackCopyText(input) {
    try {
        const wasReadOnly = input.readOnly;
        input.readOnly = false;
        input.select();
        input.setSelectionRange(0, 99999);
        const successful = document.execCommand('copy');
        input.readOnly = wasReadOnly;
        if (successful) {
            alert('Sequence Key copied!');
        } else {
            alert('Failed to copy. Please manually copy the text.');
        }
    } catch (err) {
        alert('Failed to copy. Please manually copy the text.');
    }
}
