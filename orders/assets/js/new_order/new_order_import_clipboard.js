/**
 * New Order Clipboard Smart Importer Module
 * Auto-detects delimiters (Tab, CSV, Semicolon), dynamically maps columns, renders live preview table, and uploads batch items.
 */

function parsePastedText(text) {
    if (!text.trim()) return {
        items: [],
        mapping: {
            brand: -1,
            model: -1,
            series: -1,
            cpu: -1,
            description: -1,
            price: -1,
            qty: -1,
            hasHeader: false,
            delimiterName: 'Tab'
        }
    };

    const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
    if (lines.length === 0) return {
        items: [],
        mapping: {
            brand: -1,
            model: -1,
            series: -1,
            cpu: -1,
            description: -1,
            price: -1,
            qty: -1,
            hasHeader: false,
            delimiterName: 'Tab'
        }
    };

    let tabCount = 0;
    let commaCount = 0;
    let semiCount = 0;
    const testLimit = Math.min(lines.length, 5);
    for (let i = 0; i < testLimit; i++) {
        tabCount += (lines[i].match(/\t/g) || []).length;
        commaCount += (lines[i].match(/,/g) || []).length;
        semiCount += (lines[i].match(/;/g) || []).length;
    }

    let delimiter = '\t';
    if (commaCount > tabCount && commaCount > semiCount) delimiter = ',';
    else if (semiCount > tabCount && semiCount > commaCount) delimiter = ';';

    const splitLine = (line, delim) => {
        if (delim === '\t' || delim === ';') {
            return line.split(delim).map(v => {
                let s = v.trim();
                if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
                return s;
            });
        }
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delim && !inQuotes) {
                result.push(cur.trim());
                cur = '';
            } else {
                cur += char;
            }
        }
        result.push(cur.trim());
        return result.map(s => {
            if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
            return s;
        });
    };

    const parsedRows = lines.map(line => splitLine(line, delimiter));

    let brandIdx = -1;
    let modelIdx = -1;
    let seriesIdx = -1;
    let cpuIdx = -1;
    let descIdx = -1;
    let notesIdx = -1;
    let priceIdx = -1;
    let qtyIdx = -1;
    let hasHeader = false;

    if (parsedRows.length > 0) {
        const firstRow = parsedRows[0];
        firstRow.forEach((col, idx) => {
            const colLower = col.toLowerCase().trim();
            if (colLower.includes('brand')) {
                brandIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('model')) {
                modelIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('series')) {
                seriesIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('cpu') || colLower.includes('processor')) {
                cpuIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('desc') || colLower.includes('description') || colLower.includes('spec')) {
                descIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('note')) {
                notesIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('price') || colLower.includes('value') || colLower.includes('cost') || colLower.includes('unit_price')) {
                priceIdx = idx;
                hasHeader = true;
            } else if (colLower.includes('qty') || colLower.includes('quantity') || colLower.includes('count') || colLower.includes('units')) {
                qtyIdx = idx;
                hasHeader = true;
            }
        });
    }

    const dataRows = hasHeader ? parsedRows.slice(1) : parsedRows;

    if (!hasHeader && parsedRows.length > 0) {
        const colCount = parsedRows[0].length;
        if (colCount >= 9) {
            brandIdx = 1; modelIdx = 2; seriesIdx = 3; cpuIdx = 4; descIdx = 5; notesIdx = 6; priceIdx = 7; qtyIdx = 8;
        } else if (colCount === 8) {
            brandIdx = 1; modelIdx = 2; seriesIdx = 3; cpuIdx = 4; descIdx = 5; priceIdx = 6; qtyIdx = 7;
        } else if (colCount === 7) {
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; cpuIdx = 3; descIdx = 4; priceIdx = 5; qtyIdx = 6;
        } else if (colCount === 6) {
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; cpuIdx = 3; priceIdx = 4; qtyIdx = 5;
        } else if (colCount === 5) {
            brandIdx = 0; modelIdx = 1; seriesIdx = 2; priceIdx = 3; qtyIdx = 4;
        } else if (colCount === 4) {
            brandIdx = 0; modelIdx = 1; priceIdx = 2; qtyIdx = 3;
        } else if (colCount === 3) {
            brandIdx = 0; modelIdx = 1; qtyIdx = 2;
        } else if (colCount === 2) {
            brandIdx = 0; modelIdx = 1;
        }
    }

    const items = [];
    dataRows.forEach(cols => {
        if (cols.length < 2) return;

        const brand = brandIdx !== -1 ? (cols[brandIdx] || '').trim() : 'Generic';
        const model = modelIdx !== -1 ? (cols[modelIdx] || '').trim() : 'Bulk Item';
        const series = seriesIdx !== -1 ? (cols[seriesIdx] || '').trim() : 'N/A';
        const cpu = cpuIdx !== -1 ? (cols[cpuIdx] || '').trim() : '';
        const description = descIdx !== -1 ? (cols[descIdx] || '').trim() : '';
        const notes = notesIdx !== -1 ? (cols[notesIdx] || '').trim() : '';

        let price = 0;
        if (priceIdx !== -1 && cols[priceIdx]) {
            const parsedPrice = parseFloat(cols[priceIdx].toString().replace(/[^-0-9.]/g, ''));
            if (!isNaN(parsedPrice)) price = parsedPrice;
        }

        let qty = 1;
        if (qtyIdx !== -1 && cols[qtyIdx]) {
            const parsedQty = parseInt(cols[qtyIdx].toString().replace(/[^-0-9]/g, ''), 10);
            if (!isNaN(parsedQty)) qty = parsedQty;
        }

        if (!brand && !model) return;

        items.push({
            brand: brand || 'Generic',
            model: model || 'Bulk Item',
            series: series || 'N/A',
            cpu: cpu || '',
            description: description || '',
            notes: notes || '',
            quantity: qty,
            unit_price: price
        });
    });

    const mapping = {
        brand: brandIdx,
        model: modelIdx,
        series: seriesIdx,
        cpu: cpuIdx,
        description: descIdx,
        notes: notesIdx,
        price: priceIdx,
        qty: qtyIdx,
        hasHeader,
        delimiterName: delimiter === '\t' ? 'Tab (Excel/Sheets)' : delimiter === ',' ? 'CSV (Comma)' : 'Semicolon'
    };

    return { items, mapping };
}

async function processImport() {
    const area = document.getElementById('import-paste-area');
    const btn = document.getElementById('btn-submit-import');
    if (!area || !area.value.trim() || !activeImportCustomerId || !activeImportOrderId) return;

    const originalBtnText = btn.innerHTML;
    btn.innerHTML = '⏳ Processing...';
    btn.disabled = true;

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    const { items } = parsePastedText(area.value);
    if (items.length === 0) {
        alert("No valid items detected to import.");
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
        return;
    }

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
            setTimeout(() => {
                window.location.href = 'index.php?customer_id=' + encodeURIComponent(activeImportCustomerId) + '&order_id=' + encodeURIComponent(activeImportOrderId) + '#batch-builder-top';
                window.location.reload();
            }, 1000);
        } else {
            alert("Import failed: " + (result.error || "Unknown error"));
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert("Network error during import.");
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const area = document.getElementById('import-paste-area');
    if (area) {
        area.addEventListener('input', function () {
            const text = this.value;
            const preview = document.getElementById('import-preview');
            const table = document.getElementById('import-preview-table');
            const count = document.getElementById('import-row-count');
            const mappingInfo = document.getElementById('import-mapping-info');

            if (!text.trim()) {
                if (preview) preview.style.display = 'none';
                return;
            }

            const { items, mapping } = parsePastedText(text);

            if (count) count.textContent = items.length;

            const detectedCols = [];
            if (mapping.brand !== -1) detectedCols.push('Brand');
            if (mapping.model !== -1) detectedCols.push('Model');
            if (mapping.series !== -1) detectedCols.push('Series');
            if (mapping.cpu !== -1) detectedCols.push('CPU');
            if (mapping.description !== -1) detectedCols.push('Desc');
            if (mapping.price !== -1) detectedCols.push('Price');
            if (mapping.qty !== -1) detectedCols.push('Qty');

            if (mappingInfo) {
                mappingInfo.innerHTML = `
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; font-size:0.7rem;">
                    <span style="background:#f1f5f9; padding:3px 10px; border-radius:20px; font-weight:700; color:#475569;">📌 ${escapeHTML(mapping.delimiterName)}</span>
                    ${mapping.hasHeader ? '<span style="background:#dcfce7; padding:3px 10px; border-radius:20px; font-weight:700; color:#16a34a;">✅ Header Detected</span>' : '<span style="background:#fef9c3; padding:3px 10px; border-radius:20px; font-weight:700; color:#854d0e;">⚠️ No Header (Auto-Mapped)</span>'}
                    ${detectedCols.map(c => `<span style="background:#e0f2fe; padding:3px 10px; border-radius:20px; font-weight:600; color:#0369a1;">${escapeHTML(c)}</span>`).join('')}
                </div>`;
            }

            if (items.length === 0) {
                if (preview) preview.style.display = 'none';
                return;
            }

            if (preview) preview.style.display = 'block';

            if (table) {
                const headers = ['Brand', 'Model', 'Series', 'CPU', 'Description', 'Price', 'Qty'];
                table.innerHTML = `
                <thead>
                    <tr style="background:#f8fafc;">${headers.map(h => `<th style="padding:8px 10px; font-size:0.65rem; font-weight:800; text-transform:uppercase; color:#94a3b8; text-align:left; border-bottom:1px solid #e2e8f0;">${h}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${items.slice(0, 20).map(item => `
                        <tr>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.brand)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.model)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.series)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.cpu)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.description)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; text-align:right;">${item.unit_price > 0 ? '$' + item.unit_price.toFixed(2) : '—'}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:700;">${item.quantity}</td>
                        </tr>`).join('')}
                    ${items.length > 20 ? `<tr><td colspan="7" style="padding:8px 10px; text-align:center; font-size:0.7rem; color:#94a3b8;">… and ${items.length - 20} more rows</td></tr>` : ''}
                </tbody>`;
            }
        });
    }
});
