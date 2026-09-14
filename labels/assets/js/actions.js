/**
 * assets/js/actions.js
 * Universal Hardware Actions: Open, Print, and Launch.
 */
'use strict';

/**
 * Universal Bridge to open any file in Windows via the server.
 * @param {string} relativePath - Path relative to the project root (e.g., 'exports/labels/file.odt').
 */
async function launchFile(relativePath) {
    try {
        const formData = new FormData();
        formData.append('path', relativePath);

        const response = await fetch('api/open_windows_file.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (!result.success) {
            alert('Launch Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        alert('Network error: Could not connect to Windows Bridge.');
    }
}

/**
 * Flash Launch a Label ODT.
 * Checks for existence first, then opens or generates.
 * @param {number} id
 * @param {string} brand
 * @param {string} model
 * @param {HTMLElement|null} btn - Optional button to show loading state
 */
async function flashOpenLabel(id, brand, model, btn = null) {
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '📂 ⏳';
    }

    try {
        // We always regenerate to ensure edits are reflected.
        const formData = new FormData();
        formData.append('id', id);
        formData.append('mode', 'download'); // Generate only — we download client-side
        formData.append('qty', 1);
        formData.append('print_a', 1);
        formData.append('print_b', 1);

        const genRes = await fetch('api/reprint_label.php', { method: 'POST', body: formData });
        const genJson = await genRes.json();

        if (!genJson.success) {
            alert('Generation failed: ' + genJson.error);
            return;
        }

        // Trigger a browser download so the file opens on the USER's machine
        const filePath = genJson.data.file_path; // e.g. 'exports/labels/Dell_Latitude_10thGen_ID36.odt'
        const fileName = genJson.data.file_name;

        const link = document.createElement('a');
        link.href = filePath;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

    } catch (err) {
        console.error(err);
        alert('Action error: Could not process technical label.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
}
