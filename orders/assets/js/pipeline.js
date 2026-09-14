/**
 * System — Pipeline Summary Detailed Overlay
 * Manages pipeline period switching and the detailed expanded overlay.
 */

(function() {
    // 1. Parse raw data embedded in the page
    const dataEl = document.getElementById('pipeline-data');
    if (!dataEl) return;

    let RAW = { values: {}, orders: {} };
    try {
        RAW = JSON.parse(dataEl.textContent);
    } catch (e) {
        console.error("Failed to parse pipeline data", e);
    }

    const STATUS_STYLES = {
        paid:       { bg: '#f0fdf4', color: '#166534', label: 'Paid' },
        active:     { bg: '#eff6ff', color: '#1d4ed8', label: 'Active' },
        finalized:  { bg: '#fefce8', color: '#854d0e', label: 'Finalized' },
        dispatched: { bg: '#f0f9ff', color: '#0369a1', label: 'Dispatched' },
        canceled:   { bg: '#fef2f2', color: '#991b1b', label: 'Canceled' },
    };

    let activePeriod = 'Monthly';

    // Inject styles for the expanded pipeline overlay
    const styles = `
        .pipeline-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .pipeline-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .pipeline-modal {
            background: #ffffff;
            width: 700px;
            max-width: 92vw;
            max-height: 85vh;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 1px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.95) translateY(15px);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .pipeline-overlay.active .pipeline-modal {
            transform: scale(1) translateY(0);
        }
        .pipe-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pipe-modal-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pipe-modal-close {
            background: #f1f5f9;
            border: none;
            color: #64748b;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .pipe-modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .pipe-kpis {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            padding: 16px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }
        .pipe-kpi-card {
            background: white;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .pipe-kpi-label {
            font-size: 0.62rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .pipe-kpi-value {
            font-size: 1.25rem;
            font-weight: 900;
            color: #0f172a;
        }
        .pipe-search-container {
            padding: 16px 24px 12px 24px;
        }
        .pipe-search-input {
            width: 100%;
            height: 42px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 0 16px;
            font-size: 0.88rem;
            font-weight: 500;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .pipe-search-input:focus {
            border-color: var(--accent-color, #0369a1);
            box-shadow: 0 0 0 3px rgba(3, 105, 161, 0.1);
        }
        .pipe-list-container {
            flex: 1;
            overflow-y: auto;
            padding: 0 24px 24px 24px;
        }
        .pipe-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            margin-bottom: 10px;
            background: #ffffff;
            transition: all 0.2s;
        }
        .pipe-item-row:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }
        .pipe-item-main {
            flex: 1;
            min-width: 0;
        }
        .pipe-item-name {
            font-weight: 800;
            color: #0f172a;
            font-size: 0.92rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .pipe-item-meta {
            font-size: 0.72rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pipe-item-aside {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-left: 12px;
        }
        .pipe-item-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--accent-color, #0369a1);
            text-align: right;
        }
        .pipe-item-action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #64748b;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        .pipe-item-action:hover {
            background: var(--accent-color, #0369a1);
            color: #ffffff;
            border-color: var(--accent-color, #0369a1);
        }
    `;

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);

    // Create Modal Element in body
    const modalHTML = `
        <div class="pipeline-overlay" id="pipeline-modal-overlay">
            <div class="pipeline-modal">
                <div class="pipe-modal-header">
                    <div class="pipe-modal-title">
                        <span>💰</span> Pipeline Details
                        <span id="pipe-modal-period-badge" style="font-size: 0.7rem; font-weight: 800; background: #f0f9ff; color: #0369a1; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;"></span>
                    </div>
                    <button class="pipe-modal-close" id="pipe-modal-close-btn" type="button">✕</button>
                </div>

                <div class="pipe-kpis">
                    <div class="pipe-kpi-card">
                        <span class="pipe-kpi-label">Total Value</span>
                        <span class="pipe-kpi-value" id="pipe-kpi-total" style="color: #166534;">$0</span>
                    </div>
                    <div class="pipe-kpi-card">
                        <span class="pipe-kpi-label">Order Volume</span>
                        <span class="pipe-kpi-value" id="pipe-kpi-count">0</span>
                    </div>
                    <div class="pipe-kpi-card">
                        <span class="pipe-kpi-label">Avg Order Value</span>
                        <span class="pipe-kpi-value" id="pipe-kpi-avg">$0</span>
                    </div>
                </div>

                <div class="pipe-search-container">
                    <input type="text" class="pipe-search-input" id="pipe-search-query" placeholder="🔍 Search by Customer Name or Order ID...">
                </div>

                <div class="pipe-list-container" id="pipe-modal-list">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>
    `;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = modalHTML.trim();
    document.body.appendChild(wrapper.firstChild);

    const overlay = document.getElementById('pipeline-modal-overlay');
    const closeBtn = document.getElementById('pipe-modal-close-btn');
    const searchInput = document.getElementById('pipe-search-query');

    // Close helper
    function closePipelineModal() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', closePipelineModal);
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closePipelineModal();
    });

    // Handle Esc key
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            closePipelineModal();
        }
    });

    // Formatting Helpers
    function fmt(dt) {
        if (!dt) return '';
        const d = new Date(dt);
        return isNaN(d) ? dt : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function renderModalList(filterQuery = '') {
        const orders = RAW.orders[activePeriod] || [];
        const listEl = document.getElementById('pipe-modal-list');
        const query = filterQuery.toLowerCase().trim();

        const filtered = orders.filter(o => {
            const name = (o.company_name || '').toLowerCase();
            const id = (o.order_id || '').toLowerCase();
            return name.includes(query) || id.includes(query);
        });

        // Compute aggregate values for stats based on the FULL activePeriod orders
        const rawTotalVal = orders.reduce((sum, o) => sum + (parseFloat(o.order_value) || 0), 0);
        const totalCount = orders.length;
        const avgVal = totalCount > 0 ? (rawTotalVal / totalCount) : 0;

        document.getElementById('pipe-kpi-total').textContent = '$' + Number(rawTotalVal).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        document.getElementById('pipe-kpi-count').textContent = totalCount + (totalCount === 1 ? ' Order' : ' Orders');
        document.getElementById('pipe-kpi-avg').textContent = '$' + Number(avgVal).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

        if (filtered.length === 0) {
            listEl.innerHTML = `
                <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                    <div style="font-size: 2rem; margin-bottom: 8px;">🔍</div>
                    <div style="font-weight: 700; font-size: 0.9rem;">No matching orders found</div>
                    <div style="font-size: 0.8rem; margin-top: 4px;">Try a different search term or check another period.</div>
                </div>
            `;
            return;
        }

        listEl.innerHTML = filtered.map(o => {
            const s = STATUS_STYLES[o.status] || { bg: '#f1f5f9', color: '#475569', label: o.status };
            const badge = `<span style="background:${s.bg};color:${s.color};font-size:0.62rem;font-weight:800;padding:3px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:0.02em;">${s.label}</span>`;
            const val = parseFloat(o.order_value) || 0;
            const qty = parseInt(o.total_qty) || 0;

            // Choose link destination based on status
            const isCompleted = ['finalized', 'paid', 'dispatched', 'canceled'].includes(o.status.toLowerCase());
            const targetUrl = isCompleted
                ? `checkout.php?customer_id=${encodeURIComponent(o.customer_id)}&order_id=${encodeURIComponent(o.order_id)}`
                : `index.php?customer_id=${encodeURIComponent(o.customer_id)}&order_id=${encodeURIComponent(o.order_id)}`;

            return `
                <div class="pipe-item-row">
                    <div class="pipe-item-main">
                        <div class="pipe-item-name">${o.company_name}</div>
                        <div class="pipe-item-meta">
                            <span style="font-family: monospace; font-weight: 700;">${o.order_id}</span>
                            <span>•</span>
                            <span>${qty} item${qty !== 1 ? 's' : ''}</span>
                            <span>•</span>
                            <span>${fmt(o.created_at)}</span>
                        </div>
                    </div>
                    <div class="pipe-item-aside">
                        ${badge}
                        <div class="pipe-item-value">$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</div>
                        <a href="${targetUrl}" class="pipe-item-action" title="Open Details">↗</a>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Bind period toggle button clicks and UI updates
    window.setPipelinePeriod = function(period) {
        activePeriod = period;
        const valueEl = document.getElementById('pipeline-value');
        const badge = document.getElementById('pipeline-period-badge');
        const countEl = document.getElementById('pipeline-count');
        const buttons = document.querySelectorAll('.pipeline-btn');

        const val = (RAW.values[period] ?? '$0');
        const orders = (RAW.orders[period] ?? []);

        // Animate value swap on the dashboard card
        if (valueEl) {
            valueEl.style.opacity = '0';
            setTimeout(() => {
                valueEl.textContent = val;
                valueEl.style.opacity = '1';
            }, 120);
        }

        if (badge) {
            badge.textContent = period;
        }

        if (countEl) {
            countEl.textContent = orders.length > 0
                ? `${orders.length} order${orders.length !== 1 ? 's' : ''}`
                : 'No orders';
        }

        // Button active styles
        buttons.forEach(btn => {
            const active = btn.dataset.period === period;
            btn.style.background = active ? 'var(--text-main)' : 'white';
            btn.style.color = active ? 'white' : '#64748b';
            btn.style.borderColor = active ? 'var(--text-main)' : '#e2e8f0';
        });

        localStorage.setItem('iqa_pipeline_pref', period);
    };

    // Open detailed view modal
    window.openPipelineModal = function() {
        const periodBadge = document.getElementById('pipe-modal-period-badge');
        if (periodBadge) {
            periodBadge.textContent = activePeriod;
        }
        if (searchInput) {
            searchInput.value = '';
        }
        renderModalList();
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    // Setup events on badge
    document.addEventListener('DOMContentLoaded', () => {
        const pref = localStorage.getItem('iqa_pipeline_pref') || 'Monthly';
        window.setPipelinePeriod(pref);

        const badge = document.getElementById('pipeline-period-badge');
        if (badge) {
            badge.addEventListener('click', window.openPipelineModal);
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                renderModalList(e.target.value);
            });
        }
    });

})();
