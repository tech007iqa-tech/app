/**
 * Trends Navigation & Table Filtering Module
 * Handles tab switching, real-time multi-keyword search filtering, text highlight, and column sorting.
 */

function switchTrendsTab(tabId) {
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(c => c.classList.remove('active'));

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(b => b.classList.remove('active'));

    const targetContent = document.getElementById(tabId);
    if (targetContent) targetContent.classList.add('active');

    const activeBtn = Array.from(buttons).find(b => b.getAttribute('onclick')?.includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');

    filterActiveTable();
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
                noResultsRow.innerHTML = `<td colspan="${cols}" style="text-align: center; padding: 30px; font-style: italic; color: var(--text-secondary);">No records match the current filters.</td>`;
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
            globalNoResults.style.cssText = 'text-align: center; padding: 40px; font-style: italic; color: var(--text-secondary); background: var(--bg-surface); border-radius: 8px; border: 1px solid var(--border-color); margin-top: 20px;';
            globalNoResults.innerText = 'No records match the search query across any category.';
            activeTab.appendChild(globalNoResults);
        }
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
            } else if (colIndex === 4) {
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
