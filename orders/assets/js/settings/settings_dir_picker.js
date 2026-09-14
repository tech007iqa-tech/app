/**
 * Settings Directory Picker Module
 * Provides an interactive file-system directory navigator for configuring raw photo storage paths.
 */

let pickerCurrentPath = '';

function openDirPicker() {
    const modal = document.getElementById('dir-picker-modal');
    if (modal) modal.style.display = 'flex';
    const pathInput = document.getElementById('archive_photos_path');
    const startPath = pathInput ? pathInput.value : '';
    loadDir(startPath);
}

function closeDirPicker() {
    const modal = document.getElementById('dir-picker-modal');
    if (modal) modal.style.display = 'none';
}

async function loadDir(path) {
    try {
        const response = await fetch(`index.php?view=settings&action=list_dirs&path=${encodeURIComponent(path)}`);
        const data = await response.json();
        if (data.error) {
            loadDir('');
            return;
        }

        pickerCurrentPath = data.current;
        const currentPathEl = document.getElementById('dir-picker-current-path');
        if (currentPathEl) {
            currentPathEl.textContent = pickerCurrentPath || 'System Drives';
        }

        const listContainer = document.getElementById('dir-picker-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';

        if (pickerCurrentPath && data.parent !== undefined) {
            const item = document.createElement('div');
            item.style.padding = '8px 12px';
            item.style.cursor = 'pointer';
            item.style.fontWeight = 'bold';
            item.style.color = '#3b82f6';
            item.textContent = '📁 .. (Up one level)';
            item.onclick = () => loadDir(data.parent);
            listContainer.appendChild(item);
        }

        if (data.drives && data.drives.length > 0) {
            data.drives.forEach(drive => {
                const item = document.createElement('div');
                item.style.padding = '8px 12px';
                item.style.cursor = 'pointer';
                item.style.borderBottom = '1px solid var(--border-color)';
                item.textContent = '💾 ' + drive;
                item.onclick = () => loadDir(drive);
                listContainer.appendChild(item);
            });
        }

        if (data.dirs && data.dirs.length > 0) {
            data.dirs.forEach(dir => {
                const item = document.createElement('div');
                item.style.padding = '8px 12px';
                item.style.cursor = 'pointer';
                item.style.borderBottom = '1px solid var(--border-color)';
                item.textContent = '📁 ' + dir;
                item.ondblclick = () => loadDir(pickerCurrentPath + dir + '/');
                item.onclick = () => {
                    Array.from(listContainer.children).forEach(c => c.style.background = '');
                    item.style.background = 'rgba(59, 130, 246, 0.15)';
                    pickerCurrentPath = data.current + dir + '/';
                    const pathDisplay = document.getElementById('dir-picker-current-path');
                    if (pathDisplay) pathDisplay.textContent = pickerCurrentPath;
                };
                listContainer.appendChild(item);
            });
        } else if (!data.drives || data.drives.length === 0) {
            const item = document.createElement('div');
            item.style.padding = '12px';
            item.style.color = 'var(--text-dim)';
            item.style.textAlign = 'center';
            item.textContent = 'No subfolders found.';
            listContainer.appendChild(item);
        }

    } catch (err) {
        console.error(err);
    }
}

function selectCurrentDir() {
    if (pickerCurrentPath) {
        const input = document.getElementById('archive_photos_path');
        if (input) input.value = pickerCurrentPath;
    }
    closeDirPicker();
}
