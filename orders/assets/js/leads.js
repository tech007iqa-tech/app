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

function filterByStatus(status) {
    // Update tabs UI
    const event = window.event;
    if (event && event.target) {
        document.querySelectorAll('.orders-tab-link').forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
    }

    const rows = document.getElementsByClassName('lead-row');
    for (let row of rows) {
        const rowStatus = row.getAttribute('data-status').toLowerCase();
        if (status === 'all') {
            row.style.display = '';
        } else if (status === 'lead') {
            row.style.display = rowStatus === 'lead' ? '' : 'none';
        } else if (status === 'active') {
            row.style.display = rowStatus === 'active customer' ? '' : 'none';
        }
    }
}

function filterLeads() {
    const input = document.getElementById('lead-search');
    if (!input) return;

    const filter = input.value.toLowerCase();
    const rows = document.getElementsByClassName('lead-row');

    for (let i = 0; i < rows.length; i++) {
        const searchBlob = rows[i].getAttribute('data-search') || "";
        rows[i].style.display = searchBlob.includes(filter) ? "" : "none";
    }
}

// Register leads list for background synchronization
function initLeadsSync() {
    AppSync.register({
        elementId: 'leads-list',
        url: 'index.php?view=leads&ajax=1',
        onUpdate: () => {
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLeadsSync);
} else {
    initLeadsSync();
}
