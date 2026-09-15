/**
 * IQA CRM — Leads & Interaction Logic
 */

function openLeadModal(lead) {
    document.getElementById('modal-company-name').innerText = lead.company_name;
    document.getElementById('modal-customer-id').innerText = lead.customer_id;
    document.getElementById('modal-customer-link').href = `index.php?view=orders&type=completed&q=${encodeURIComponent(lead.customer_id)}`;
    document.getElementById('lead_customer_id').value = lead.customer_id;

    document.getElementById('lead_status').value = lead.account_status || 'Lead';
    document.getElementById('lead_source').value = lead.lead_source || '';
    document.getElementById('lead_interest').value = lead.interest || '';
    document.getElementById('lead_message_date').value = lead.message_date || '';
    document.getElementById('lead_method').value = lead.contact_method || '';
    document.getElementById('lead_callback_date').value = lead.callback_date || '';
    document.getElementById('lead_notes').value = lead.internal_notes || '';

    // Clear the new interaction note
    const newNote = document.getElementById('new_interaction_note');
    if (newNote) newNote.value = '';

    // Load Interaction History
    loadInteractionHistory(lead.customer_id);

    document.getElementById('leadModal').style.display = 'flex';
}

function closeLeadModal() {
    document.getElementById('leadModal').style.display = 'none';
}

function quickLog(method) {
    const today = new Date().toISOString().split('T')[0];
    const methodInput = document.getElementById('lead_method');
    const dateInput = document.getElementById('lead_message_date');
    const noteInput = document.getElementById('new_interaction_note');

    if (methodInput) methodInput.value = method;
    if (dateInput) dateInput.value = today;

    const messages = {
        'Phone': 'Spoke with client via phone.',
        'Email': 'Sent follow-up email regarding outstanding items.',
        'Message': 'Sent text/WhatsApp message for quick check-in.'
    };

    if (noteInput) {
        noteInput.value = messages[method] || `Contacted via ${method}.`;
        noteInput.style.borderColor = 'var(--accent-color)';
        setTimeout(() => noteInput.style.borderColor = '#cbd5e1', 1000);
    }
}

async function loadInteractionHistory(customerId) {
    const historyContainer = document.getElementById('interaction-history');
    if (!historyContainer) return;

    historyContainer.innerHTML = '<div style="padding:20px; text-align:center; opacity:0.5;">Loading history...</div>';

    const icons = {
        'phone': '📞',
        'email': '📧',
        'message': '💬',
        'whatsapp': '📱',
        'meeting': '🤝'
    };

    try {
        const response = await fetch(`api/get_interaction_logs.php?customer_id=${encodeURIComponent(customerId)}`);
        const logs = await response.json();

        if (logs.length === 0) {
            historyContainer.innerHTML = '<div style="padding:20px; text-align:center; opacity:0.5; font-size:0.8rem;">No previous interaction logs.</div>';
            return;
        }

        historyContainer.innerHTML = logs.map(log => {
            const methodLower = (log.method || 'other').toLowerCase();
            const icon = icons[methodLower] || '📝';

            return `
                <div style="padding:15px; border-bottom:1px solid #f1f5f9; font-size:0.85rem; position:relative; padding-left:45px;">
                    <div style="position:absolute; left:0; top:15px; width:32px; height:32px; background:white; border:1px solid #e2e8f0; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; box-shadow:0 2px 4px rgba(0,0,0,0.03);">
                        ${icon}
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-weight:800; color:var(--text-main);">${log.contact_date}</span>
                        <span style="font-weight:700; color:#94a3b8; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.05em;">${log.method || 'Note'}</span>
                    </div>
                    <div style="color:#475569; line-height:1.5; font-weight:500;">${log.note}</div>
                </div>
            `;
        }).join('');

    } catch (err) {
        console.error("Failed to load history", err);
        historyContainer.innerHTML = '<div style="color:#ef4444; padding:20px; text-align:center;">Error loading history.</div>';
    }
}

async function saveLead(event) {
    event.preventDefault();
    const form = event.target;
    const btn = document.getElementById('btn-save-lead');
    const originalText = btn.innerText;

    try {
        btn.disabled = true;
        btn.innerText = 'Saving Changes...';

        const formData = new FormData(form);
        const response = await fetch('api/save_lead.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            // Success animation or feedback
            btn.style.background = '#22c55e';
            btn.innerText = '✓ Saved Successfully';

            setTimeout(() => {
                location.reload(); // Reload to refresh table data
            }, 800);
        } else {
            throw new Error(data.error || 'Update failed');
        }

    } catch (err) {
        console.error("Save failed", err);
        alert("Failed to save lead: " + err.message);
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

let currentSortCol = null;
let currentSortDir = 'asc';

/**
 * Sorts the leads table based on column index and data type
 * @param {number} colIndex
 * @param {'str'|'num'|'date'} type
 */
function sortLeadsTable(colIndex, type) {
    const table = document.querySelector(".orders-table");
    if (!table) return;
    const tbody = document.getElementById("leads-list");
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll("tr.lead-row"));
    if (rows.length === 0) return;

    const headers = table.querySelectorAll("thead th.sortable-th");
    const clickedHeader = headers[colIndex];

    // Toggle sort direction
    if (currentSortCol === colIndex) {
        currentSortDir = (currentSortDir === 'asc') ? 'desc' : 'asc';
    } else {
        currentSortCol = colIndex;
        currentSortDir = (type === 'num' || type === 'date') ? 'desc' : 'asc';
    }

    // Update header classes & indicators
    headers.forEach((th) => {
        th.classList.remove('sort-asc', 'sort-desc');
        const icon = th.querySelector('.sort-icon');
        if (icon) icon.textContent = '';
    });

    if (clickedHeader) {
        clickedHeader.classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
        const icon = clickedHeader.querySelector('.sort-icon');
        if (icon) icon.textContent = currentSortDir === 'asc' ? ' ▲' : ' ▼';
    }

    const multiplier = (currentSortDir === 'asc') ? 1 : -1;

    rows.sort((a, b) => {
        const cellA = a.cells[colIndex];
        const cellB = b.cells[colIndex];
        if (!cellA || !cellB) return 0;

        let valA = cellA.getAttribute('data-sort-val') ?? cellA.textContent.trim();
        let valB = cellB.getAttribute('data-sort-val') ?? cellB.textContent.trim();

        if (type === 'num') {
            const numA = parseFloat(valA.toString().replace(/[^0-9.-]/g, '')) || 0;
            const numB = parseFloat(valB.toString().replace(/[^0-9.-]/g, '')) || 0;
            return (numA - numB) * multiplier;
        } else if (type === 'date') {
            const dateA = valA ? new Date(valA).getTime() : 0;
            const dateB = valB ? new Date(valB).getTime() : 0;
            // Place empty dates at the bottom regardless of sort direction
            if (!valA && valB) return 1;
            if (valA && !valB) return -1;
            if (!valA && !valB) return 0;
            return (dateA - dateB) * multiplier;
        } else {
            valA = valA.toString().toLowerCase();
            valB = valB.toString().toLowerCase();
            if (valA < valB) return -1 * multiplier;
            if (valA > valB) return 1 * multiplier;
            return 0;
        }
    });

    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

function filterByStatus(status) {
    // Update tabs UI
    const event = window.event;
    if (event && event.target) {
        document.querySelectorAll('.orders-tab-link').forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
    }

    filterLeads();
}

function highlightLeadNodeWords(node, queryWords) {
    if (node.nodeType === 3) {
        const val = node.nodeValue;
        if (!val || !val.trim()) return;
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
            mark.style.cssText = 'background: rgba(250, 204, 21, 0.4); color: inherit; padding: 0 2px; border-radius: 3px;';
            mark.appendChild(document.createTextNode(match));
            const txtAfter = document.createTextNode(after);

            span.appendChild(txtBefore);
            span.appendChild(mark);
            span.appendChild(txtAfter);

            node.parentNode.replaceChild(span, node);
            highlightLeadNodeWords(txtAfter, queryWords);
        }
    } else if (node.nodeType === 1 && node.childNodes && !node.classList.contains('match-highlight') && node.tagName !== 'SCRIPT' && node.tagName !== 'STYLE' && !node.classList.contains('btn-order-view')) {
        const children = Array.from(node.childNodes);
        children.forEach(child => highlightLeadNodeWords(child, queryWords));
    }
}

function clearLeadHighlight(row) {
    const highlights = row.querySelectorAll('.highlight-container');
    highlights.forEach(hl => {
        const textNode = document.createTextNode(hl.textContent);
        hl.parentNode.replaceChild(textNode, hl);
    });
}

function filterLeads() {
    const input = document.getElementById('lead-search');
    if (!input) return;

    const query = input.value.trim().toLowerCase();
    const queryWords = query.split(/\s+/).filter(w => w.length > 0);
    const isSearchActive = queryWords.length > 0;
    const rows = document.getElementsByClassName('lead-row');

    // Get active status filter from tabs
    const activeTab = document.querySelector('.orders-tab-link.active');
    let activeStatus = 'all';
    if (activeTab) {
        const onclickText = activeTab.getAttribute('onclick') || '';
        const match = onclickText.match(/'([^']+)'/);
        if (match && match[1]) activeStatus = match[1];
    }

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const searchBlob = (row.getAttribute('data-search') || "").toLowerCase();
        const rowStatus = (row.getAttribute('data-status') || "").toLowerCase();

        const matchesSearch = !isSearchActive || queryWords.every(word => searchBlob.includes(word));
        let matchesStatus = true;
        if (activeStatus === 'lead') {
            matchesStatus = (rowStatus === 'lead');
        } else if (activeStatus === 'active') {
            matchesStatus = (rowStatus === 'active customer');
        }

        if (matchesSearch && matchesStatus) {
            row.style.display = "";
            clearLeadHighlight(row);
            if (isSearchActive) {
                highlightLeadNodeWords(row, queryWords);
            }
        } else {
            row.style.display = "none";
            clearLeadHighlight(row);
        }
    }
}

// Register leads list for background synchronization
function initLeadsSync() {
    AppSync.register({
        elementId: 'leads-list',
        url: 'index.php?view=leads&ajax=1',
        onUpdate: () => {
            // Re-apply sorting if a column was active
            if (currentSortCol !== null) {
                const table = document.querySelector(".orders-table");
                const headers = table ? table.querySelectorAll("thead th.sortable-th") : [];
                const activeTh = headers[currentSortCol];
                if (activeTh) {
                    // Extract type from onclick attribute
                    const onclickAttr = activeTh.getAttribute('onclick') || '';
                    const match = onclickAttr.match(/sortLeadsTable\(\s*\d+\s*,\s*'([^']+)'\s*\)/);
                    const type = match ? match[1] : 'str';
                    // Re-sort preserving currentSortDir
                    const prevDir = currentSortDir;
                    currentSortCol = null; // reset to allow toggling to same dir
                    currentSortDir = (prevDir === 'asc') ? 'desc' : 'asc';
                    sortLeadsTable(Array.from(headers).indexOf(activeTh), type);
                }
            }

            // Re-apply text filtering
            filterLeads();
            // Re-apply active tab status filtering
            const activeTab = document.querySelector('.orders-tab-link.active');
            if (activeTab) {
                const onclickText = activeTab.getAttribute('onclick') || '';
                const match = onclickText.match(/'([^']+)'/);
                if (match && match[1]) {
                    const status = match[1];
                    const rows = document.getElementsByClassName('lead-row');
                    for (let row of rows) {
                        const rowStatus = row.getAttribute('data-status').toLowerCase();
                        if (status === 'all') {
                            // Let filterLeads decide visibility
                        } else if (status === 'lead' && rowStatus !== 'lead') {
                            row.style.display = 'none';
                        } else if (status === 'active' && rowStatus !== 'active customer') {
                            row.style.display = 'none';
                        }
                    }
                }
            }
        }
    });
}

/**
 * 1-Click CSV Export for Leads & CRM Accounts List
 */
function exportLeadsCSV() {
    const rows = document.querySelectorAll('.orders-table tbody tr.lead-row');
    if (rows.length === 0) return;

    const sanitize = (val) => {
        const str = String(val ?? '').trim();
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    };

    let csv = "Customer ID,Company Name,Status,Lead Source,Interest,Last Order ID,Last Purchase Date,Total Balance,Last Contact Date,Contact Method,Next Call Date,Urgency Status,Internal Notes\n";

    rows.forEach(tr => {
        if (tr.style.display === 'none') return;

        const customerId = tr.getAttribute('data-id') || '';
        const cells = tr.querySelectorAll('td');
        if (cells.length < 9) return;

        const company = cells[0].getAttribute('data-sort-val') || '';
        const status = cells[1].getAttribute('data-sort-val') || '';
        const source = cells[2].getAttribute('data-sort-val') || '';
        const interest = cells[3].getAttribute('data-sort-val') || '';
        const lastPurchase = cells[4].getAttribute('data-sort-val') || '';
        const lastOrderId = cells[4].querySelector('a')?.textContent.trim().split('\n')[0] || '';
        const balance = parseFloat(cells[5].getAttribute('data-sort-val') || 0).toFixed(2);
        const contactDate = cells[6].getAttribute('data-sort-val') || '';
        const contactMethod = cells[6].querySelector('div:last-child')?.textContent.trim() || '';
        const callbackDate = cells[7].getAttribute('data-sort-val') || '';
        const urgency = cells[7].querySelector('.call-badge')?.textContent.trim() || '';
        const notes = cells[8].getAttribute('data-sort-val') || '';

        csv += `${sanitize(customerId)},${sanitize(company)},${sanitize(status)},${sanitize(source)},${sanitize(interest)},${sanitize(lastOrderId)},${sanitize(lastPurchase)},${sanitize('$' + balance)},${sanitize(contactDate)},${sanitize(contactMethod)},${sanitize(callbackDate)},${sanitize(urgency)},${sanitize(notes)}\n`;
    });

    const today = new Date().toISOString().split('T')[0];
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `Leads_Accounts_${today}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLeadsSync);
} else {
    initLeadsSync();
}
