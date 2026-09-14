/**
 * Bulk Warehouse Import Coordinator Script
 * Loads sub-modules, configures CSV dropzone listeners, and manages initialization lifecycle.
 */

// 1. Synchronously load sub-modules in sequence
(function loadImportWarehouseModules() {
    const modules = [
        'assets/js/import_warehouse/import_warehouse_table.js',
        'assets/js/import_warehouse/import_warehouse_zone.js',
        'assets/js/import_warehouse/import_warehouse_bulk.js',
        'assets/js/import_warehouse/import_warehouse_clipboard.js'
    ];

    modules.forEach(src => {
        if (!document.querySelector(`script[src*="${src}"]`)) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            document.head.appendChild(script);
        }
    });
})();

document.addEventListener('DOMContentLoaded', () => {
    // 2. Initialize Dropzone for Upload Screen
    const dropZone = document.getElementById('drop-zone');
    const csvInput = document.getElementById('csv-input');
    const fileName = document.getElementById('file-name');

    if (dropZone && csvInput) {
        dropZone.onclick = () => csvInput.click();

        csvInput.onchange = (e) => {
            if (e.target.files.length > 0 && fileName) {
                fileName.innerText = e.target.files[0].name;
                fileName.style.color = 'var(--accent-color)';
                fileName.style.fontWeight = '900';
                dropZone.style.borderColor = 'var(--accent-color)';
                dropZone.style.background = '#f0fdf4';
            }
        };

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = 'var(--accent-color)';
                dropZone.style.background = '#f0fdf4';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#cbd5e1';
                dropZone.style.background = '#f8fafc';
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length > 0) {
                csvInput.files = e.dataTransfer.files;
                csvInput.dispatchEvent(new Event('change'));
            }
        }, false);
    }

    // 3. Initialize Verification Report Mode (if present)
    if (typeof initZoneState === 'function') {
        initZoneState();
    }
    if (typeof initImportWarehouseTable === 'function') {
        initImportWarehouseTable();
    }
    if (typeof onZoneChange === 'function') {
        onZoneChange();
    }
});
