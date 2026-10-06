/**
 * IQA Metal Warehouse Systems - Setup Wizard Client Controller
 */
let currentStep = 1;
const totalSteps = 4;
let pppPasscodes = [];
let pppActiveSeqKey = (window.SETUP_CONFIG && window.SETUP_CONFIG.activeSeqKey) || "";
let pppSelectedRow = (window.SETUP_CONFIG && window.SETUP_CONFIG.selectedRow) || 0;
let passcodeRevealed = false;

function updateStepUI() {
    for (let i = 1; i <= totalSteps; i++) {
        const el = document.getElementById('step-' + i);
        const nav = document.getElementById('nav-step-' + i);
        if (el) el.classList.remove('active');
        if (nav) {
            nav.classList.remove('active');
            if (i < currentStep) {
                nav.classList.add('completed');
            } else {
                nav.classList.remove('completed');
            }
        }
    }

    const activeEl = document.getElementById('step-' + currentStep);
    const activeNav = document.getElementById('nav-step-' + currentStep);
    if (activeEl) activeEl.classList.add('active');
    if (activeNav) activeNav.classList.add('active');

    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');

    if (btnPrev) btnPrev.style.visibility = (currentStep === 1) ? 'hidden' : 'visible';

    if (currentStep === totalSteps) {
        if (btnNext) btnNext.style.display = 'none';
        if (btnSubmit) btnSubmit.style.display = 'inline-flex';
        populateReview();
    } else {
        if (btnNext) btnNext.style.display = 'inline-flex';
        if (btnSubmit) btnSubmit.style.display = 'none';
    }

    // If navigating to step 3, ensure grid is loaded
    if (currentStep === 3 && pppPasscodes.length === 0) {
        loadPPPGrid();
    }
}

function nextStep() {
    if (currentStep < totalSteps) {
        if (validateStep(currentStep)) {
            currentStep++;
            updateStepUI();
        }
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepUI();
    }
}

function jumpToStep(step) {
    if (step < currentStep || validateStep(currentStep)) {
        currentStep = step;
        updateStepUI();
    }
}

function switchAuthMode(mode) {
    document.getElementById('auth_mode_input').value = mode;

    document.querySelectorAll('.auth-mode-card').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.auth-panel').forEach(p => p.style.display = 'none');

    if (mode === 'keep_existing') {
        const cardKeep = document.getElementById('mode-card-keep');
        const panelKeep = document.getElementById('auth-panel-keep');
        if (cardKeep) cardKeep.classList.add('active');
        if (panelKeep) panelKeep.style.display = 'block';
    } else if (mode === 'ppp') {
        document.getElementById('mode-card-ppp').classList.add('active');
        document.getElementById('auth-panel-ppp').style.display = 'block';
        if (pppPasscodes.length === 0) loadPPPGrid();
    } else if (mode === 'default_creds') {
        document.getElementById('mode-card-default').classList.add('active');
        document.getElementById('auth-panel-default').style.display = 'block';
        document.getElementById('admin_user').value = 'admin';
    } else if (mode === 'custom') {
        document.getElementById('mode-card-custom').classList.add('active');
        document.getElementById('auth-panel-custom').style.display = 'block';
    }
}

function onPPPConfigChange() {
    const lengthInput = document.getElementById('ppp_length_input');
    let length = parseInt(lengthInput.value, 10) || 30;
    if (length < 25) length = 25;
    if (length > 80) length = 80;
    lengthInput.value = length;
    document.getElementById('ppp_password_len_input').value = length;

    // Update entropy calculation (6 bits per character from 64-char alphabet)
    const entropyBits = Math.round(length * 5.95);
    document.getElementById('entropy-badge').textContent = `${entropyBits}-bit Entropy`;

    loadPPPGrid();
}

function sanitizeHexKey(raw) {
    return (raw || '').replace(/[^0-9a-fA-F]/g, '').toUpperCase();
}

function updateKeyBitBadge(key) {
    const badge = document.getElementById('key-bit-badge');
    if (!badge) return;
    const len = key ? key.length : 0;
    if (len === 32) {
        badge.textContent = '128-bit Key (GRC Standard)';
        badge.style.color = '#38bdf8';
        badge.style.background = 'rgba(56, 189, 248, 0.15)';
    } else if (len === 64) {
        badge.textContent = '256-bit Key (GRC Extended)';
        badge.style.color = '#4ade80';
        badge.style.background = 'rgba(74, 222, 128, 0.15)';
    } else if (len === 48) {
        badge.textContent = '192-bit Key';
        badge.style.color = '#38bdf8';
        badge.style.background = 'rgba(56, 189, 248, 0.15)';
    } else if (len >= 16 && len < 32) {
        badge.textContent = `${len * 4}-bit Key (Padded to 128-bit)`;
        badge.style.color = '#f59e0b';
        badge.style.background = 'rgba(245, 158, 11, 0.15)';
    } else if (len > 32 && len < 64) {
        badge.textContent = `${len * 4}-bit Key (Padded to 256-bit)`;
        badge.style.color = '#f59e0b';
        badge.style.background = 'rgba(245, 158, 11, 0.15)';
    } else if (len === 0) {
        badge.textContent = 'No Key Entered';
        badge.style.color = '#94a3b8';
        badge.style.background = 'rgba(148, 163, 184, 0.15)';
    } else {
        badge.textContent = `Invalid (${len} hex chars)`;
        badge.style.color = '#f87171';
        badge.style.background = 'rgba(248, 113, 113, 0.15)';
    }
}

function onKeyInputChange() {
    const input = document.getElementById('ppp_display_key');
    const key = sanitizeHexKey(input.value);
    updateKeyBitBadge(key);
    if (key.length >= 16 && key.length <= 64) {
        pppActiveSeqKey = key;
        document.getElementById('ppp_sequence_key_input').value = key;
        updateQR(key);
    }
}

function triggerGenKey() {
    const chars = '0123456789ABCDEF';
    let key = '';
    for (let i = 0; i < 64; i++) {
        key += chars[Math.floor(Math.random() * 16)];
    }
    pppActiveSeqKey = key;
    document.getElementById('ppp_display_key').value = key;
    document.getElementById('ppp_sequence_key_input').value = key;
    updateKeyBitBadge(key);
    updateQR(key);
    loadPPPGrid();
}

function copySequenceKey() {
    const key = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
    if (!key) return;
    navigator.clipboard.writeText(key).then(() => {
        alert("Sequence key copied to clipboard!");
    }).catch(() => {
        prompt("Copy Sequence Key:", key);
    });
}

async function applyManualKey() {
    const input = document.getElementById('ppp_display_key');
    const btn = document.getElementById('btn_load_key');
    let key = sanitizeHexKey(input.value);
    if (key.length < 16 || key.length > 64) {
        alert("Please enter a valid hexadecimal sequence key (between 32 and 64 hex characters, e.g. standard 32-hex 128-bit or 64-hex 256-bit).");
        return;
    }
    input.value = key;
    pppActiveSeqKey = key;
    document.getElementById('ppp_sequence_key_input').value = key;
    updateKeyBitBadge(key);
    updateQR(key);

    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Loading...';
    }

    const success = await loadPPPGrid();

    if (btn) {
        btn.disabled = false;
        if (success) {
            btn.textContent = '✅ Loaded!';
            setTimeout(() => { btn.textContent = '🔍 Load'; }, 1800);
        } else {
            btn.textContent = '🔍 Load';
        }
    }
}

function updateQR(key) {
    const encoded = encodeURIComponent(key);
    const img = document.getElementById('ppp_qr_img');
    if (img) img.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encoded}`;
}

function viewLargeQR() {
    const key = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
    if (!key) return;
    window.open(`https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(key)}`, '_blank');
}

async function loadPPPGrid() {
    const rawKey = pppActiveSeqKey || document.getElementById('ppp_display_key').value;
    const key = sanitizeHexKey(rawKey);
    const length = parseInt(document.getElementById('ppp_length_input').value, 10) || 30;
    if (key.length < 16 || key.length > 64) return false;

    let baseUrl = window.location.pathname.split('?')[0];
    if (!baseUrl.endsWith('.php')) {
        baseUrl = baseUrl.replace(/\/$/, '') + '/index.php';
    }

    try {
        const res = await fetch(`${baseUrl}?action=ajax_generate_ppp&seq_key=${encodeURIComponent(key)}&length=${length}`);
        const data = await res.json();
        if (data.success && data.passcodes) {
            pppPasscodes = data.passcodes;
            renderGrid(data.passcodes);
            return true;
        } else if (data.error) {
            console.error("PPP generation error:", data.error);
            alert("Error generating passcodes: " + data.error);
            return false;
        }
    } catch (err) {
        console.error("Failed to load PPP passcodes:", err);
        return false;
    }
    return false;
}

function renderGrid(passcodes) {
    const tbody = document.getElementById('ppp-grid-tbody');
    tbody.innerHTML = '';

    for (let r = 0; r < 25; r++) {
        const rowNum = r + 1;
        const isSelected = (pppSelectedRow === rowNum);
        const tr = document.createElement('tr');
        if (isSelected) tr.classList.add('active-row');
        tr.onclick = () => selectPasscodeRow(rowNum);

        const tdRow = document.createElement('td');
        tdRow.className = 'row-label';
        tdRow.textContent = String(rowNum).padStart(2, '0');
        tr.appendChild(tdRow);

        for (let c = 0; c < 5; c++) {
            const tdCell = document.createElement('td');
            tdCell.textContent = passcodes[r * 5 + c] || '';
            tr.appendChild(tdCell);
        }
        tbody.appendChild(tr);
    }

    // Auto-select row 1 if none chosen
    if (!pppSelectedRow || pppSelectedRow < 1 || pppSelectedRow > 25) {
        selectPasscodeRow(1);
    } else {
        selectPasscodeRow(pppSelectedRow);
    }

    updatePrintCardSource(passcodes);
}

function selectPasscodeRow(rowNum) {
    pppSelectedRow = rowNum;
    document.getElementById('ppp_row_index_input').value = rowNum;

    // Highlight row in table
    const rows = document.querySelectorAll('#ppp-grid-tbody tr');
    rows.forEach((r, idx) => {
        if (idx === (rowNum - 1)) {
            r.classList.add('active-row');
        } else {
            r.classList.remove('active-row');
        }
    });

    // Compute passcode string
    if (pppPasscodes.length >= (rowNum * 5)) {
        const rowSlice = pppPasscodes.slice((rowNum - 1) * 5, rowNum * 5);
        const passcodeStr = rowSlice.join('');
        document.getElementById('selected_passcode_input').value = passcodeStr;

        // Show callout
        const callout = document.getElementById('selected-passcode-callout');
        if (callout) callout.style.display = 'block';

        document.getElementById('active-row-badge').textContent = `Row ${String(rowNum).padStart(2, '0')}`;
        updatePasscodeDisplay(passcodeStr);
    }
}

function updatePasscodeDisplay(str) {
    const previewEl = document.getElementById('passcode-preview-str');
    if (!previewEl) return;
    if (passcodeRevealed) {
        previewEl.textContent = str;
        document.getElementById('btnTogglePasscode').textContent = '🔒 Hide';
    } else {
        previewEl.textContent = '•'.repeat(str.length || 30);
        document.getElementById('btnTogglePasscode').textContent = '👁️ Reveal';
    }
}

function togglePasscodeVisibility() {
    passcodeRevealed = !passcodeRevealed;
    const passcode = document.getElementById('selected_passcode_input').value;
    updatePasscodeDisplay(passcode);
}

function copyActivePasscode() {
    const passcode = document.getElementById('selected_passcode_input').value;
    if (!passcode) return;
    navigator.clipboard.writeText(passcode).then(() => {
        alert(`Row ${String(pppSelectedRow).padStart(2, '0')} passcode copied to clipboard!`);
    }).catch(() => {
        prompt("Passcode:", passcode);
    });
}

function togglePPPExplanation() {
    const box = document.getElementById('ppp-explanation-box');
    if (box) box.style.display = (box.style.display === 'none') ? 'block' : 'none';
}

function updatePrintCardSource(passcodes) {
    const source = document.getElementById('ppp-printable-card-source');
    if (!source) return;

    const companyName = document.getElementById('company_name').value || 'IQA Metal';
    const username = document.getElementById('admin_user').value || 'admin';
    const length = document.getElementById('ppp_length_input').value || 30;

    let tableRowsHtml = '';
    for (let r = 0; r < 25; r++) {
        const rowLabel = String(r + 1).padStart(2, '0');
        let cellsHtml = '';
        for (let c = 0; c < 5; c++) {
            cellsHtml += `<td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; letter-spacing: 0.5px;'>${passcodes[r * 5 + c] || ''}</td>`;
        }
        tableRowsHtml += `<tr>
            <td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; background: #fafafa;'>${rowLabel}</td>
            ${cellsHtml}
        </tr>`;
    }

    source.innerHTML = `
        <div style="border: 2px dashed #0056b3; border-radius: 12px; padding: 20px; max-width: 600px; margin: 20px auto; background: white; color: black; font-family: 'Courier New', Courier, monospace;">
            <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0056b3; padding-bottom: 8px; margin-bottom: 12px;">
                <div>
                    <strong style="font-size: 16px; color: #082d45;">${companyName} PASSCARD</strong>
                    <div style="font-size: 11px; color: #555;">Perfect Paper Passwords (Steve Gibson GRC)</div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 13px; font-weight: bold;">User: ${username}</span>
                </div>
            </div>
            <div style="font-size: 10px; margin-bottom: 12px; word-break: break-all; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 6px;">
                <strong>SEQUENCE KEY:</strong><br>${pppActiveSeqKey}
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center;">
                <thead>
                    <tr style="background: #e2e8f0;">
                        <th style="padding: 5px 3px; border: 1px solid #ccc; width: 45px;">Row</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc;">A</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc;">B</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc;">C</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc;">D</th>
                        <th style="padding: 5px 3px; border: 1px solid #ccc;">E</th>
                    </tr>
                </thead>
                <tbody>${tableRowsHtml}</tbody>
            </table>
            <div style="margin-top: 12px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #eee; padding-top: 6px;">
                Password Length: ${length} &bull; Keep this card secure and offline. Enter your secret row code at login.
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

function viewPPPCard() {
    const source = document.getElementById('ppp-printable-card-source');
    if (!source) return;
    const viewWindow = window.open('', '_blank');
    viewWindow.document.write('<html><head><title>PPP Passcard</title></head><body style="margin:20px; background:#f1f5f9;">' + source.innerHTML + '</body></html>');
    viewWindow.document.close();
    viewWindow.focus();
}

function togglePassVisibility(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function validateStep(step) {
    if (step === 1) {
        const name = document.getElementById('company_name').value.trim();
        const sys = document.getElementById('system_name').value.trim();
        if (!name || !sys) {
            alert('Please enter your company name and system title.');
            return false;
        }
    } else if (step === 3) {
        const authMode = document.getElementById('auth_mode_input').value;
        if (authMode === 'keep_existing') {
            return true;
        } else if (authMode === 'custom') {
            const pass = document.getElementById('admin_pass').value;
            const passConfirm = document.getElementById('admin_pass_confirm').value;
            if (!pass || pass.length < 4) {
                alert('Please provide an administrator password of at least 4 characters.');
                return false;
            }
            if (pass !== passConfirm) {
                alert('Administrator passwords do not match.');
                return false;
            }
        } else if (authMode === 'ppp') {
            const rawKey = document.getElementById('ppp_sequence_key_input').value.trim();
            const cleanKey = sanitizeHexKey(rawKey);
            const rowIdx = parseInt(document.getElementById('ppp_row_index_input').value, 10);
            if (cleanKey.length < 16 || cleanKey.length > 64) {
                alert('Please generate or enter a valid hexadecimal PPP sequence key (32-hex 128-bit or 64-hex 256-bit).');
                return false;
            }
            if (!rowIdx || rowIdx < 1 || rowIdx > 25) {
                alert('Please click to select an authentication row (Row 1-25) from the passcard grid to use as your passcode.');
                return false;
            }
        } else if (authMode === 'default_creds') {
            return true;
        }
    } else if (step === 4) {
        const confirmInput = document.getElementById('current_admin_password');
        if (confirmInput && !confirmInput.value.trim()) {
            alert('Please enter your current administrator password to authorize and commit changes.');
            confirmInput.focus();
            return false;
        }
    }
    return true;
}

function populateReview() {
    document.getElementById('rev-company-name').textContent = document.getElementById('company_name').value || 'IQA Metal';
    document.getElementById('rev-system-title').textContent = document.getElementById('system_name').value || 'IQA Metal Warehouse Systems';
    document.getElementById('rev-company-url').textContent = document.getElementById('company_url').value || 'https://iqametal.com';
    document.getElementById('rev-admin-user').textContent = document.getElementById('admin_user').value || 'admin';

    const authMode = document.getElementById('auth_mode_input').value;
    const revAuthMode = document.getElementById('rev-auth-mode');
    if (revAuthMode) {
        if (authMode === 'keep_existing') {
            revAuthMode.innerHTML = `<strong style="color:#38bdf8;">🛡️ Keep Existing Active Credentials</strong>`;
        } else if (authMode === 'ppp') {
            const rowIdx = document.getElementById('ppp_row_index_input').value || 1;
            revAuthMode.innerHTML = `<strong style="color:#38bdf8;">🔑 Perfect Paper Passwords (Row ${String(rowIdx).padStart(2, '0')})</strong>`;
        } else if (authMode === 'default_creds') {
            revAuthMode.innerHTML = `<strong style="color:#4ade80;">⚡ Default Credentials (admin / 123)</strong>`;
        } else {
            revAuthMode.innerHTML = `<strong style="color:#fbbf24;">🔒 Custom Password</strong>`;
        }
    }
}

// Check for direct jump to step in URL (e.g. ?reconfigure=1 3 or ?step=3 or #step-3)
document.addEventListener('DOMContentLoaded', () => {
    const initialMode = document.getElementById('auth_mode_input').value;
    if (initialMode === 'keep_existing') {
        switchAuthMode('keep_existing');
    }
    const urlParams = new URLSearchParams(window.location.search);
    let targetStep = 1;
    if (urlParams.has('step')) {
        targetStep = parseInt(urlParams.get('step'), 10) || 1;
    } else if (window.location.search.includes(' 3') || window.location.search.endsWith('3') || window.location.hash === '#step-3') {
        targetStep = 3;
    }
    currentStep = Math.min(Math.max(targetStep, 1), totalSteps);
    updateStepUI();
    updateKeyBitBadge(pppActiveSeqKey);
});
