/**
 * Weekly Orders & Sales Summary Controller (weekly_orders.js)
 * Handles spreadsheet clipboard copy, CSV export, Chart.js lifecycle, and table interactions.
 */

let dailyChartInstance = null;
let historyChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    initWeeklyCharts();
});

/**
 * Initializes Chart.js visualization for daily distribution and multi-week trend
 */
function initWeeklyCharts() {
    const stateEl = document.getElementById('weekly-orders-state');
    if (!stateEl || typeof Chart === 'undefined') return;

    try {
        const state = JSON.parse(stateEl.textContent);

        // 1. Daily Chart Initialization
        const dailyCanvas = document.getElementById('weeklyDailyChart');
        if (dailyCanvas && state.daily_chart) {
            Chart.getChart(dailyCanvas)?.destroy();

            dailyChartInstance = new Chart(dailyCanvas, {
                type: 'bar',
                data: {
                    labels: state.daily_chart.labels,
                    datasets: [
                        {
                            label: 'Units Added/Sold',
                            data: state.daily_chart.units,
                            backgroundColor: 'rgba(140, 198, 63, 0.85)',
                            borderColor: '#8cc63f',
                            borderWidth: 1.5,
                            borderRadius: 6,
                            yAxisID: 'yUnits',
                            order: 2
                        },
                        {
                            label: 'Sold Price ($)',
                            data: state.daily_chart.amounts,
                            type: 'line',
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#3b82f6',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'yAmount',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            titleFont: { family: 'Outfit', size: 13, weight: '700' },
                            bodyFont: { family: 'Outfit', size: 12 },
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.yAxisID === 'yAmount') {
                                        return ' Sold Price: $' + context.raw.toLocaleString(undefined, { minimumFractionDigits: 2 });
                                    }
                                    return ' Units: ' + context.raw.toLocaleString() + ' items';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Outfit', weight: '600' }, color: '#64748b' }
                        },
                        yUnits: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Units', font: { family: 'Outfit', weight: '700', size: 11 }, color: '#8cc63f' },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' },
                            ticks: { font: { family: 'Outfit' }, color: '#64748b' }
                        },
                        yAmount: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            title: { display: true, text: 'Amount ($)', font: { family: 'Outfit', weight: '700', size: 11 }, color: '#3b82f6' },
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { family: 'Outfit' },
                                color: '#3b82f6',
                                callback: value => '$' + Number(value).toLocaleString()
                            }
                        }
                    }
                }
            });
        }

        // 2. Historical 12-Week Chart Initialization
        const historyCanvas = document.getElementById('weeklyHistoryChart');
        if (historyCanvas && state.history_chart) {
            Chart.getChart(historyCanvas)?.destroy();

            historyChartInstance = new Chart(historyCanvas, {
                type: 'bar',
                data: {
                    labels: state.history_chart.labels,
                    datasets: [
                        {
                            label: 'Total Units',
                            data: state.history_chart.units,
                            backgroundColor: 'rgba(140, 198, 63, 0.4)',
                            borderColor: '#8cc63f',
                            borderWidth: 1.5,
                            borderRadius: 6,
                            yAxisID: 'yUnits',
                            order: 2
                        },
                        {
                            label: 'Gross Revenue ($)',
                            data: state.history_chart.amounts,
                            type: 'line',
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.15)',
                            borderWidth: 3,
                            pointBackgroundColor: '#10b981',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.25,
                            fill: true,
                            yAxisID: 'yAmount',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 12,
                            titleFont: { family: 'Outfit', size: 13, weight: '700' },
                            bodyFont: { family: 'Outfit', size: 12 },
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.yAxisID === 'yAmount') {
                                        return ' Revenue: $' + context.raw.toLocaleString(undefined, { minimumFractionDigits: 2 });
                                    }
                                    return ' Units: ' + context.raw.toLocaleString() + ' items';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Outfit', weight: '600' }, color: '#64748b' }
                        },
                        yUnits: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Units', font: { family: 'Outfit', weight: '700', size: 11 }, color: '#8cc63f' },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' },
                            ticks: { font: { family: 'Outfit' }, color: '#64748b' }
                        },
                        yAmount: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            title: { display: true, text: 'Revenue ($)', font: { family: 'Outfit', weight: '700', size: 11 }, color: '#10b981' },
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { family: 'Outfit' },
                                color: '#10b981',
                                callback: value => '$' + Number(value).toLocaleString()
                            }
                        }
                    }
                }
            });
        }
    } catch (err) {
        console.error("Error initializing weekly orders charts:", err);
    }
}

/**
 * 1-Click "Copy for Report": Copies spreadsheet-ready TSV + HTML to clipboard
 * Pastes cleanly into Microsoft Excel, Google Sheets, Word, and Slack
 */
async function copyWeeklyReportTable() {
    const stateEl = document.getElementById('weekly-orders-state');
    if (!stateEl) return;

    try {
        const state = JSON.parse(stateEl.textContent);
        const days = state.days;
        const isShowWeekend = document.querySelector('.col-weekend')?.style.display !== 'none';
        const dayLimit = isShowWeekend ? 7 : 5;

        // Header title
        const title = state.label_month + ' (Weekly Orders Report)';

        // 1. Build TSV (Tab Separated Values)
        let tsv = title + '\n';
        
        // Day Row with Day numbers & names (e.g. 5 Monday, 6 Tuesday)
        let rowHeader = 'Day';
        for (let d = 1; d <= dayLimit; d++) {
            rowHeader += '\t' + days[d].day_num + ' ' + days[d].name;
        }
        rowHeader += '\tTotal';
        tsv += rowHeader + '\n';

        // Units Row
        let rowUnits = 'Units';
        for (let d = 1; d <= dayLimit; d++) {
            const u = days[d].units;
            rowUnits += '\t' + (u > 0 ? u : '-');
        }
        rowUnits += '\t' + (state.total_units > 0 ? state.total_units : '-');
        tsv += rowUnits + '\n';

        // Sold Price Row
        let rowPrice = 'Sold Price';
        for (let d = 1; d <= dayLimit; d++) {
            const amt = days[d].amount;
            rowPrice += '\t' + (amt > 0 ? '$' + Number(amt).toLocaleString(undefined, { minimumFractionDigits: (amt % 1 === 0 ? 0 : 2), maximumFractionDigits: 2 }) : '-');
        }
        rowPrice += '\t' + (state.total_amount > 0 ? '$' + Number(state.total_amount).toLocaleString(undefined, { minimumFractionDigits: (state.total_amount % 1 === 0 ? 0 : 2), maximumFractionDigits: 2 }) : '-');
        tsv += rowPrice + '\n';

        // Batches Row
        let rowBatches = 'Batches';
        for (let d = 1; d <= dayLimit; d++) {
            const b = days[d].batches.length;
            rowBatches += '\t' + (b > 0 ? b : '-');
        }
        rowBatches += '\t' + (state.batch_count > 0 ? state.batch_count : '-');
        tsv += rowBatches + '\n';

        // Avg Price / Unit Row
        let rowAsp = 'Avg Price / Unit';
        for (let d = 1; d <= dayLimit; d++) {
            const u = days[d].units;
            const amt = days[d].amount;
            const asp = u > 0 ? (amt / u) : 0;
            rowAsp += '\t' + (asp > 0 ? '$' + asp.toFixed(2) : '-');
        }
        const totalAsp = state.total_units > 0 ? (state.total_amount / state.total_units) : 0;
        rowAsp += '\t' + (totalAsp > 0 ? '$' + totalAsp.toFixed(2) : '-');
        tsv += rowAsp;

        // 2. Build Rich HTML Table
        let html = `<table border="1" cellpadding="6" cellspacing="0" style="font-family: Arial, sans-serif; font-size: 13px; border-collapse: collapse; text-align: center;">`;
        html += `<thead><tr style="background-color: #f1f5f9; font-weight: bold;"><th style="text-align: left; padding: 8px;">Day</th>`;
        for (let d = 1; d <= dayLimit; d++) {
            html += `<th style="padding: 8px;">${days[d].day_num}<br>${days[d].name}</th>`;
        }
        html += `<th style="background-color: #e2e8f0; padding: 8px;">Total</th></tr></thead><tbody>`;

        // Units HTML
        html += `<tr><td style="text-align: left; font-weight: bold; padding: 8px;">Units</td>`;
        for (let d = 1; d <= dayLimit; d++) {
            const u = days[d].units;
            html += `<td style="padding: 8px;">${u > 0 ? Number(u).toLocaleString() : '-'}</td>`;
        }
        html += `<td style="font-weight: bold; background-color: #f8fafc; padding: 8px;">${state.total_units > 0 ? Number(state.total_units).toLocaleString() : '-'}</td></tr>`;

        // Sold Price HTML
        html += `<tr><td style="text-align: left; font-weight: bold; padding: 8px;">Sold Price</td>`;
        for (let d = 1; d <= dayLimit; d++) {
            const amt = days[d].amount;
            html += `<td style="padding: 8px;">${amt > 0 ? '$' + Number(amt).toLocaleString(undefined, { minimumFractionDigits: (amt % 1 === 0 ? 0 : 2), maximumFractionDigits: 2 }) : '-'}</td>`;
        }
        html += `<td style="font-weight: bold; background-color: #f8fafc; color: #15803d; padding: 8px;">${state.total_amount > 0 ? '$' + Number(state.total_amount).toLocaleString(undefined, { minimumFractionDigits: (state.total_amount % 1 === 0 ? 0 : 2), maximumFractionDigits: 2 }) : '-'}</td></tr>`;

        // Batches HTML
        html += `<tr><td style="text-align: left; padding: 8px;">Batches</td>`;
        for (let d = 1; d <= dayLimit; d++) {
            const b = days[d].batches.length;
            html += `<td style="padding: 8px;">${b > 0 ? b : '-'}</td>`;
        }
        html += `<td style="background-color: #f8fafc; padding: 8px;">${state.batch_count > 0 ? state.batch_count : '-'}</td></tr>`;

        // Avg Price HTML
        html += `<tr><td style="text-align: left; padding: 8px;">Avg Price / Unit</td>`;
        for (let d = 1; d <= dayLimit; d++) {
            const u = days[d].units;
            const amt = days[d].amount;
            const asp = u > 0 ? (amt / u) : 0;
            html += `<td style="padding: 8px;">${asp > 0 ? '$' + asp.toFixed(2) : '-'}</td>`;
        }
        html += `<td style="background-color: #f8fafc; padding: 8px;">${totalAsp > 0 ? '$' + totalAsp.toFixed(2) : '-'}</td></tr>`;

        html += `</tbody></table>`;

        // 3. Write to Clipboard
        if (navigator.clipboard && window.ClipboardItem) {
            const blobHtml = new Blob([html], { type: 'text/html' });
            const blobText = new Blob([tsv], { type: 'text/plain' });
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': blobHtml,
                    'text/plain': blobText
                })
            ]);
        } else if (navigator.clipboard) {
            await navigator.clipboard.writeText(tsv);
        } else {
            // Fallback for older environments
            const textarea = document.createElement('textarea');
            textarea.value = tsv;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        if (window.IQA_Notify && typeof window.IQA_Notify.success === 'function') {
            window.IQA_Notify.success("✓ Weekly report copied to clipboard! Paste directly into Excel or Google Sheets.");
        } else if (window.Notifications && typeof window.Notifications.success === 'function') {
            window.Notifications.success("✓ Weekly report copied to clipboard! Paste directly into Excel or Google Sheets.");
        } else {
            alert("✓ Weekly report copied to clipboard! You can now paste directly into Excel, Google Sheets, or your report.");
        }
    } catch (err) {
        console.error("Failed to copy weekly report:", err);
        alert("Failed to copy table: " + err.message);
    }
}

/**
 * 1-Click "Export CSV": Generates CSV with UTF-8 BOM encoding for Microsoft Excel
 */
function exportWeeklyReportCSV() {
    const stateEl = document.getElementById('weekly-orders-state');
    if (!stateEl) return;

    try {
        const state = JSON.parse(stateEl.textContent);
        const days = state.days;
        const isShowWeekend = document.querySelector('.col-weekend')?.style.display !== 'none';
        const dayLimit = isShowWeekend ? 7 : 5;

        let csv = '\uFEFF'; // Mandatory UTF-8 BOM
        csv += `"Weekly Orders Report: ${state.label_full}"\n\n`;

        // Column headers
        let headers = ['"Metric"'];
        for (let d = 1; d <= dayLimit; d++) {
            headers.push(`"${days[d].day_num} ${days[d].name}"`);
        }
        headers.push('"Total"');
        csv += headers.join(',') + '\n';

        // Units
        let rowUnits = ['"Units"'];
        for (let d = 1; d <= dayLimit; d++) {
            rowUnits.push(days[d].units);
        }
        rowUnits.push(state.total_units);
        csv += rowUnits.join(',') + '\n';

        // Sold Price
        let rowPrice = ['"Sold Price ($)"'];
        for (let d = 1; d <= dayLimit; d++) {
            rowPrice.push(days[d].amount.toFixed(2));
        }
        rowPrice.push(state.total_amount.toFixed(2));
        csv += rowPrice.join(',') + '\n';

        // Batches
        let rowBatches = ['"Batches"'];
        for (let d = 1; d <= dayLimit; d++) {
            rowBatches.push(days[d].batches.length);
        }
        rowBatches.push(state.batch_count);
        csv += rowBatches.join(',') + '\n';

        // Avg Price / Unit
        let rowAsp = ['"Avg Price / Unit ($)"'];
        for (let d = 1; d <= dayLimit; d++) {
            const u = days[d].units;
            const amt = days[d].amount;
            rowAsp.push(u > 0 ? (amt / u).toFixed(2) : '0.00');
        }
        const totalAsp = state.total_units > 0 ? (state.total_amount / state.total_units) : 0;
        rowAsp.push(totalAsp.toFixed(2));
        csv += rowAsp.join(',') + '\n';

        // Create download trigger
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `weekly_orders_${state.active_week_key}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (err) {
        console.error("Export CSV failed:", err);
    }
}

/**
 * Copies a single week summary from the historical overview table
 */
async function copySingleWeekSummary(weekKey) {
    const table = document.getElementById('allWeeksTable');
    if (!table) return;

    const row = table.querySelector(`tr[onclick*="${weekKey}"], a[href*="${weekKey}"]`)?.closest('tr');
    if (!row) return;

    const cells = Array.from(row.querySelectorAll('td')).map(td => td.innerText.replace('Active View', '').trim());
    const tsv = `Week Period\tBatches\tTotal Units\tTotal Sold Price\tAvg Price / Unit\tDaily Avg\n${cells.slice(0, 6).join('\t')}`;

    try {
        await navigator.clipboard.writeText(tsv);
        if (window.IQA_Notify) {
            window.IQA_Notify.success(`✓ Summary for week copied!`);
        } else {
            alert(`✓ Summary for week copied!`);
        }
    } catch (e) {
        console.error(e);
    }
}

/**
 * Copies the entire historical weeks overview table to clipboard
 */
async function copyAllWeeksHistoricalTable() {
    const table = document.getElementById('allWeeksTable');
    if (!table) return;

    let tsv = 'Week Period\tBatches\tTotal Units\tTotal Sold Price ($)\tAvg Price / Unit ($)\tDaily Mon-Fri Avg Units\n';
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(tr => {
        const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.replace('Active View', '').trim());
        if (cells.length >= 6) {
            tsv += cells.slice(0, 6).join('\t') + '\n';
        }
    });

    try {
        await navigator.clipboard.writeText(tsv);
        if (window.IQA_Notify) {
            window.IQA_Notify.success("✓ All historical weeks table copied to clipboard! Ready to paste into Excel.");
        } else {
            alert("✓ All historical weeks table copied to clipboard! Ready to paste into Excel.");
        }
    } catch (e) {
        alert("Copy failed: " + e.message);
    }
}

/**
 * Toggles visibility of weekend columns (Saturday and Sunday)
 */
function toggleWeekendDays() {
    const weekendCols = document.querySelectorAll('.col-weekend');
    const label = document.getElementById('toggleDaysLabel');
    if (!weekendCols.length) return;

    const isCurrentlyHidden = weekendCols[0].style.display === 'none';

    weekendCols.forEach(col => {
        col.style.display = isCurrentlyHidden ? '' : 'none';
    });

    if (label) {
        label.textContent = isCurrentlyHidden ? 'Show 5 Days' : 'Show 7 Days';
    }
}

/**
 * Smoothly scrolls to the batches placed on a selected day
 */
function scrollToDayOrders(dateStr) {
    const el = document.getElementById('day-group-' + dateStr);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'background 0.3s ease';
        const origBg = el.style.background;
        el.style.background = 'rgba(140, 198, 63, 0.25)';
        setTimeout(() => {
            el.style.background = origBg;
        }, 1500);
    }
}
