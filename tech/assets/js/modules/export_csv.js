/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: export_csv.js
 * Filtered CSV Export Engine with Mandatory UTF-8 BOM
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Conforms to GEMINI.md Invariant: prepends UTF-8 BOM (\uFEFF) as byte 0.
 * - Exports filtered log records matching current bench filters.
 * - Exposes `TechWorkbench.Exporter.exportFilteredLogs()`.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    const CsvExporter = {
        /**
         * Fetches filtered logs and exports them as a UTF-8 BOM CSV spreadsheet.
         */
        async exportFilteredLogs() {
            const start = document.getElementById('start_date')?.value || '';
            const end = document.getElementById('end_date')?.value || '';
            const deliveryFilter = document.getElementById('delivery_filter')?.value || '';
            const search = document.getElementById('log_search')?.value.trim() || '';
            const currentStatus = window.currentStatus || 'Good';

            let url = `../api/get_logs.php?status=${encodeURIComponent(currentStatus)}`;
            if (start && end) url += `&start_date=${start}&end_date=${end}`;
            if (deliveryFilter) url += `&delivery_status=${encodeURIComponent(deliveryFilter)}`;
            if (search) url += `&q=${encodeURIComponent(search)}`;

            const techSelect = document.getElementById('tech_filter');
            if (techSelect && techSelect.value) {
                url += `&tech_id=${encodeURIComponent(techSelect.value)}`;
            }

            try {
                if (window.TechWorkbench.Clipboard) {
                    window.TechWorkbench.Clipboard.showToast('<span>⏳</span> <span>Generating CSV export...</span>');
                }

                const response = await fetch(url);
                const result = await response.json();

                if (!result.success || !Array.isArray(result.data) || result.data.length === 0) {
                    alert('No records found matching current filters to export.');
                    return;
                }

                const headers = [
                    'ID', 'Status', 'Make', 'Model', 'Series', 'Serial Number',
                    'CPU', 'CPU Gen', 'CPU Cores', 'CPU Speed', 'RAM', 'Storage',
                    'GPU', 'Screen', 'Battery', 'Battery Specs', 'BIOS State',
                    'OS', 'Cosmetic Grade', 'Shelf Location', 'Delivery Status',
                    'Delivery Destination', 'Batch / Lot #', 'Technician', 'Created At (UTC)', 'Notes'
                ];

                const csvRows = [];
                csvRows.push(headers.map(h => `"${h.replace(/"/g, '""')}"`).join(','));

                result.data.forEach(row => {
                    const line = [
                        row.id || '',
                        row.status || '',
                        row.make || '',
                        row.model || '',
                        row.series || '',
                        row.serial_number || '',
                        row.cpu || row.cpu_specs || '',
                        row.cpu_gen || '',
                        row.cpu_cores || '',
                        row.cpu_speed || '',
                        row.ram || '',
                        row.storage || '',
                        row.gpu || '',
                        row.screen_res || '',
                        row.battery || '',
                        row.battery_specs || '',
                        row.bios_state || '',
                        row.os || '',
                        row.cosmetic_grade || '',
                        row.location || '',
                        row.delivery_status || '',
                        row.delivery_destination || '',
                        row.batch_number || '',
                        row.tech_id || '',
                        row.created_at || '',
                        row.notes || ''
                    ];
                    csvRows.push(line.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','));
                });

                // Mandatory UTF-8 BOM (\uFEFF) for seamless Microsoft Excel compatibility
                const csvString = '\uFEFF' + csvRows.join('\r\n');
                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                const downloadUrl = URL.createObjectURL(blob);

                const a = document.createElement('a');
                a.href = downloadUrl;
                const todayStr = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
                a.download = `hardware_logs_${currentStatus.toLowerCase()}_${todayStr}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(downloadUrl);

                if (window.TechWorkbench.Audio) {
                    window.TechWorkbench.Audio.playSuccess();
                }
                if (window.TechWorkbench.Clipboard) {
                    window.TechWorkbench.Clipboard.showToast('<span>📥</span> <span>CSV downloaded successfully!</span>');
                }
            } catch (err) {
                alert('Failed to export CSV: ' + err.message);
            }
        }
    };

    exports.Exporter = CsvExporter;
})(window.TechWorkbench);
