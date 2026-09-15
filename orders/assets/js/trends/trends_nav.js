function switchTrendsTab(tabId) {
    if (!tabId) return;

    // Persist active tab selection
    try {
        sessionStorage.setItem('trends_active_tab', tabId);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({}, '', url.toString());
    } catch(e) {}

    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(c => c.classList.remove('active'));

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(b => b.classList.remove('active'));

    const targetContent = document.getElementById(tabId);
    if (targetContent) targetContent.classList.add('active');

    const activeBtn = Array.from(buttons).find(b => b.getAttribute('onclick')?.includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');

    if (tabId === 'tab-pricing') {
        const mode = sessionStorage.getItem('pricing_chart_view_mode') || 'split';
        if (typeof setPricingChartViewMode === 'function') {
            setPricingChartViewMode(mode);
        } else if (typeof initializePricingCharts === 'function') {
            const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
            setTimeout(() => {
                initializePricingCharts(state.price_history);
            }, 50);
        }
    } else if (tabId === 'tab-cpu') {
        if (typeof initializeCpuCharts === 'function') {
            const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
            setTimeout(() => {
                initializeCpuCharts(state.cpu_distribution);
            }, 50);
        }
    }

    filterActiveTable();
}

function applyTrendsFilter(filterVal) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'trends');
    url.searchParams.set('filter', filterVal);
    const activeTab = document.querySelector('.tab-content.active')?.id || sessionStorage.getItem('trends_active_tab') || 'tab-velocity';
    url.searchParams.set('tab', activeTab);
    window.location.href = url.toString();
}

function filterActiveTable() {
    const searchInput = document.getElementById('trends-search');
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const activeTab = document.querySelector('.tab-content.active');
    if (!activeTab) return;

    const tables = activeTab.querySelectorAll('.trends-table');
    if (tables.length === 0) return;

    const showInStockOnly = activeTab.querySelector('.in-stock-only-checkbox')?.checked || false;
    const queryWords = query.split(/\s+/).filter(w => w.length > 0);
    const isSearchActive = queryWords.length > 0;

    let totalVisibleCount = 0;

    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr:not(.no-results-row)');
        let visibleCount = 0;

        if (table.id === 'table-velocity') {
            const rankHeaders = table.querySelectorAll('.rank-header');
            const buyerHeaders = table.querySelectorAll('.buyer-header');
            const rankCells = table.querySelectorAll('.rank-cell');
            const buyerCells = table.querySelectorAll('.buyer-cell');

            const stockHeaders = table.querySelectorAll('.stock-header');
            const orderHeaders = table.querySelectorAll('.order-header');
            const stockCells = table.querySelectorAll('.stock-cell');
            const orderCells = table.querySelectorAll('.order-cell');

            rankHeaders.forEach(el => el.style.display = isSearchActive ? 'none' : '');
            buyerHeaders.forEach(el => el.style.display = isSearchActive ? '' : 'none');
            rankCells.forEach(el => el.style.display = isSearchActive ? 'none' : '');
            buyerCells.forEach(el => el.style.display = isSearchActive ? '' : 'none');

            stockHeaders.forEach(el => el.style.display = isSearchActive ? 'none' : '');
            orderHeaders.forEach(el => el.style.display = isSearchActive ? '' : 'none');
            stockCells.forEach(el => el.style.display = isSearchActive ? 'none' : '');
            orderCells.forEach(el => el.style.display = isSearchActive ? '' : 'none');
        }

        rows.forEach(row => {
            const searchText = row.getAttribute('data-search') || '';
            const inStock = parseInt(row.getAttribute('data-instock') || '1', 10);

            const matchesSearch = !isSearchActive || queryWords.every(word => searchText.includes(word));
            const matchesStock = !showInStockOnly || inStock > 0;

            if (matchesSearch && matchesStock) {
                row.style.display = '';
                visibleCount++;
                totalVisibleCount++;
                highlightRowText(row, queryWords);
            } else {
                row.style.display = 'none';
                clearHighlight(row);
            }
        });

        const matrixBlock = table.closest('.matrix-category-block');
        const container = table.closest('.trends-table-container');

        if (matrixBlock) {
            if (visibleCount === 0 && isSearchActive) {
                matrixBlock.style.display = 'none';
            } else {
                matrixBlock.style.display = '';
            }
        } else if (container) {
            let prev = container.previousElementSibling;
            while (prev && prev.tagName !== 'H3' && prev.className !== 'tab-content') {
                prev = prev.previousElementSibling;
            }

            if (visibleCount === 0 && isSearchActive) {
                container.style.display = 'none';
                if (prev && prev.tagName === 'H3') {
                    prev.style.display = 'none';
                }
            } else {
                container.style.display = '';
                if (prev && prev.tagName === 'H3') {
                    prev.style.display = '';
                }
            }
        }

        let noResultsRow = table.querySelector('.no-results-row');
        if (visibleCount === 0 && !isSearchActive) {
            if (!noResultsRow) {
                const cols = table.querySelectorAll('thead th').length;
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `<td colspan="${cols}" style="text-align: center; padding: 35px 20px; color: var(--text-secondary);"><div style="font-size: 1.5rem; margin-bottom: 6px;">🔍</div><div style="font-weight: 600; font-size: 0.9rem;">No records match the current filters.</div></td>`;
                table.querySelector('tbody').appendChild(noResultsRow);
            }
            noResultsRow.style.display = '';
        } else if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    });

    let globalNoResults = activeTab.querySelector('.global-no-results');
    if (totalVisibleCount === 0 && isSearchActive) {
        if (!globalNoResults) {
            globalNoResults = document.createElement('div');
            globalNoResults.className = 'global-no-results';
            globalNoResults.style.cssText = 'text-align: center; padding: 45px 20px; color: var(--text-secondary); background: var(--bg-surface-2); border-radius: 14px; border: 1px dashed var(--border-color); margin: 25px 0;';
            activeTab.appendChild(globalNoResults);
        }
        globalNoResults.innerHTML = `
            <div style="font-size: 2.2rem; margin-bottom: 8px;">🔍</div>
            <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">No matching records found</div>
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 15px;">No rows matched your search criteria across this tab.</div>
            <button type="button" onclick="clearSearchInput()" style="padding: 7px 16px; border-radius: 8px; background: var(--bg-panel); border: 1px solid var(--border-color); color: var(--accent-color); font-weight: 700; font-size: 0.82rem; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                <span>✕</span> Clear Search Filter
            </button>
        `;
        globalNoResults.style.display = 'block';
    } else if (globalNoResults) {
        globalNoResults.style.display = 'none';
    }
}

function handleSearch(val) {
    const clearBtn = document.getElementById('clear-search');
    if (clearBtn) clearBtn.style.display = val ? 'block' : 'none';
    filterActiveTable();
}

function clearSearchInput() {
    const searchInput = document.getElementById('trends-search');
    if (!searchInput) return;
    searchInput.value = '';
    handleSearch('');
    searchInput.focus();
}

function highlightRowText(row, queryWords) {
    clearHighlight(row);
    if (!queryWords || queryWords.length === 0) return;

    const cells = row.querySelectorAll('td');
    cells.forEach(cell => {
        highlightNodeWords(cell, queryWords);
    });
}

function highlightNodeWords(node, queryWords) {
    if (node.nodeType === 3) {
        const val = node.nodeValue;
        let earliestIndex = -1;
        let matchedWord = '';

        queryWords.forEach(word => {
            const idx = val.toLowerCase().indexOf(word);
            if (idx > -1 && (earliestIndex === -1 || idx < earliestIndex)) {
                earliestIndex = idx;
                matchedWord = word;
            }
        });

        if (earliestIndex > -1 && matchedWord) {
            const span = document.createElement('span');
            span.className = 'highlight-container';

            const before = val.substring(0, earliestIndex);
            const match = val.substring(earliestIndex, earliestIndex + matchedWord.length);
            const after = val.substring(earliestIndex + matchedWord.length);

            const txtBefore = document.createTextNode(before);
            const mark = document.createElement('mark');
            mark.className = 'match-highlight';
            mark.appendChild(document.createTextNode(match));
            const txtAfter = document.createTextNode(after);

            span.appendChild(txtBefore);
            span.appendChild(mark);
            span.appendChild(txtAfter);

            node.parentNode.replaceChild(span, node);
            highlightNodeWords(txtAfter, queryWords);
        }
    } else if (node.nodeType === 1 && node.childNodes && !node.classList.contains('match-highlight') && node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE') {
        const children = Array.from(node.childNodes);
        children.forEach(child => {
            highlightNodeWords(child, queryWords);
        });
    }
}

function clearHighlight(row) {
    const highlights = row.querySelectorAll('.highlight-container');
    highlights.forEach(hl => {
        const textNode = document.createTextNode(hl.textContent);
        hl.parentNode.replaceChild(textNode, hl);
    });
}

function sortTable(tableId, colIndex, type) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results-row)'));
    const headers = table.querySelectorAll('thead th');
    const clickedHeader = headers[colIndex];
    if (!clickedHeader) return;

    const isAsc = !clickedHeader.classList.contains('sort-asc');

    headers.forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
    });

    clickedHeader.classList.add(isAsc ? 'sort-asc' : 'sort-desc');

    const searchInput = document.getElementById('trends-search');
    const isSearchActive = searchInput && searchInput.value.trim().length > 0;

    rows.sort((a, b) => {
        const cellA = a.cells[colIndex];
        const cellB = b.cells[colIndex];
        if (!cellA || !cellB) return 0;

        let valA, valB;
        let currentType = type;

        if (tableId === 'table-velocity') {
            if (colIndex === 0) {
                if (isSearchActive) {
                    currentType = 'str';
                    const buyerA = cellA.querySelector('.buyer-cell');
                    const buyerB = cellB.querySelector('.buyer-cell');
                    valA = buyerA ? buyerA.textContent.trim() : '';
                    valB = buyerB ? buyerB.textContent.trim() : '';
                } else {
                    currentType = 'num';
                    const rankA = cellA.querySelector('.rank-cell');
                    const rankB = cellB.querySelector('.rank-cell');
                    valA = rankA ? rankA.textContent.trim().replace('#', '') : '';
                    valB = rankB ? rankB.textContent.trim().replace('#', '') : '';
                }
            } else if (colIndex === 5) {
                if (isSearchActive) {
                    currentType = 'str';
                    const orderA = cellA.querySelector('.order-cell');
                    const orderB = cellB.querySelector('.order-cell');
                    valA = orderA ? orderA.textContent.trim() : '';
                    valB = orderB ? orderB.textContent.trim() : '';
                } else {
                    currentType = 'date';
                    valA = cellA.getAttribute('data-sort-val') ?? '';
                    valB = cellB.getAttribute('data-sort-val') ?? '';
                }
            } else {
                valA = cellA.getAttribute('data-sort-val') ?? cellA.textContent.trim();
                valB = cellB.getAttribute('data-sort-val') ?? cellB.textContent.trim();
            }
        } else {
            valA = cellA.getAttribute('data-sort-val') ?? cellA.textContent.trim();
            valB = cellB.getAttribute('data-sort-val') ?? cellB.textContent.trim();
        }

        if (currentType === 'num') {
            valA = parseFloat(valA.replace(/[^0-9.-]/g, '')) || 0;
            valB = parseFloat(valB.replace(/[^0-9.-]/g, '')) || 0;
        } else if (currentType === 'date') {
            valA = new Date(valA).getTime() || 0;
            valB = new Date(valB).getTime() || 0;
        } else {
            valA = valA.toLowerCase();
            valB = valB.toLowerCase();
        }

        if (valA < valB) return isAsc ? -1 : 1;
        if (valA > valB) return isAsc ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}

/**
 * 1-Click CSV Export for Financial Valuation & Settlement Ledger (Tab 2)
 */
function exportFinancialLedgerCSV() {
    const table = document.getElementById('table-pricing');
    if (!table) return;

    const sanitize = (val) => {
        const str = String(val ?? '').trim();
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    };

    let csv = "Settlement Month,Units Moved,Avg Unit Price,Gross Realized Valuation,MoM Revenue Growth,Share of Period\n";

    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(tr => {
        if (tr.style.display === 'none') return;
        const cells = tr.querySelectorAll('td');
        if (cells.length < 6) return;

        const month = cells[0].getAttribute('data-sort-val') || cells[0].textContent.replace(/[^\w-]/g, '');
        const units = cells[1].getAttribute('data-sort-val') || cells[1].textContent.replace(/[^\d]/g, '');
        const price = cells[2].getAttribute('data-sort-val') || cells[2].textContent.replace(/[^0-9.]/g, '');
        const val = cells[3].getAttribute('data-sort-val') || cells[3].textContent.replace(/[^0-9.]/g, '');
        const momVal = cells[4].getAttribute('data-sort-val');
        const mom = (momVal && momVal !== '-9999') ? (parseFloat(momVal).toFixed(1) + '%') : '—';
        const share = parseFloat(cells[5].getAttribute('data-sort-val') || 0).toFixed(1) + '%';

        csv += `${sanitize(month)},${sanitize(units)},${sanitize('$' + parseFloat(price || 0).toFixed(2))},${sanitize('$' + parseFloat(val || 0).toFixed(2))},${sanitize(mom)},${sanitize(share)}\n`;
    });

    // Append totals footer
    const tfoot = table.querySelector('tfoot tr');
    if (tfoot) {
        const fCells = tfoot.querySelectorAll('td');
        if (fCells.length >= 6) {
            const fLabel = fCells[0].textContent.trim();
            const fUnits = fCells[1].textContent.trim();
            const fPrice = fCells[2].textContent.trim();
            const fVal = fCells[3].textContent.trim();
            const fMom = fCells[4].textContent.trim();
            const fShare = fCells[5].textContent.trim();
            csv += `\n${sanitize(fLabel)},${sanitize(fUnits)},${sanitize(fPrice)},${sanitize(fVal)},${sanitize(fMom)},${sanitize(fShare)}\n`;
        }
    }

    const today = new Date().toISOString().split('T')[0];
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `Financial_Ledger_${today}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * 1-Click CSV Export for Model Demand Velocity (Tab 1)
 */
function exportDemandVelocityCSV() {
    const table = document.getElementById('table-velocity');
    if (!table) return;

    const sanitize = (val) => {
        const str = String(val ?? '').trim();
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    };

    let csv = "Rank,Brand,Model,Series,CPU,Avg Unit Price,Units Sold,Latest Sold Date,Customer Buyers,Order IDs\n";

    const rows = table.querySelectorAll('tbody tr');
    let exportIndex = 1;
    rows.forEach(tr => {
        if (tr.style.display === 'none') return;
        const brand = tr.getAttribute('data-brand') || '';
        const model = tr.getAttribute('data-model') || '';
        const series = tr.getAttribute('data-series') || '';
        const cpu = tr.getAttribute('data-cpu') || '';

        const cells = tr.querySelectorAll('td');
        if (cells.length < 7) return;

        const buyer = cells[0].querySelector('.buyer-cell')?.textContent.trim() || '';
        const avgPrice = cells[3].getAttribute('data-sort-val') || '';
        const dateVal = cells[5].getAttribute('data-sort-val') || '';
        const units = cells[6].getAttribute('data-sort-val') || '';
        const orderIds = cells[5].querySelector('.order-cell')?.textContent.trim() || '';

        csv += `${exportIndex++},${sanitize(brand)},${sanitize(model)},${sanitize(series)},${sanitize(cpu)},${sanitize('$' + parseFloat(avgPrice || 0).toFixed(2))},${sanitize(units)},${sanitize(dateVal)},${sanitize(buyer)},${sanitize(orderIds)}\n`;
    });

    const today = new Date().toISOString().split('T')[0];
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `Model_Demand_Velocity_${today}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

