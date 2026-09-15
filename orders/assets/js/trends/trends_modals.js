/**
 * Trends Modals & Matrix Actions Module
 * Handles the Order Manifest Preview popup, CPU Family Pricing Breakdown, and live B2B matrix editing.
 */

let activeOrderPreviewEscHandler = null;
let activeCpuPricingEscHandler = null;

window.globalModalZIndex = 10000;
function bringModalToFront(modalEl) {
    if (!modalEl) return;
    window.globalModalZIndex += 10;
    modalEl.style.zIndex = window.globalModalZIndex;
}

function openOrderPreviewModal(event, orderId) {
    let highlightParams = null;
    if (event && event.target) {
        const tr = event.target.closest('tr');
        if (tr) {
            highlightParams = {
                brand: tr.getAttribute('data-brand'),
                model: tr.getAttribute('data-model'),
                series: tr.getAttribute('data-series'),
                cpu: tr.getAttribute('data-cpu')
            };
        }
    }

    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const modal = document.getElementById('orderPreviewModal');
    bringModalToFront(modal);

    const loading = document.getElementById('preview-loading');
    const error = document.getElementById('preview-error');
    const body = document.getElementById('preview-body');

    const orderIdEl = document.getElementById('preview-order-id');
    const companyNameEl = document.getElementById('preview-company-name');
    if (orderIdEl) orderIdEl.innerText = orderId;
    if (companyNameEl) companyNameEl.innerText = 'Loading...';

    if (loading) loading.style.display = 'flex';
    if (error) error.style.display = 'none';
    if (body) body.style.display = 'none';
    if (modal) modal.style.display = 'flex';

    const localEscapeHTML = (str) => {
        if (!str) return '—';
        return str.toString().replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m]));
    };

    fetch(`api/get_order_details.php?order_id=${encodeURIComponent(orderId)}`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load');
            return res.json();
        })
        .then(data => {
            if (companyNameEl) companyNameEl.innerText = data.order.company_name || 'Unknown Account';
            const dateEl = document.getElementById('preview-date');
            if (dateEl) {
                dateEl.innerText = new Date(data.order.created_at.replace(/-/g, "/")).toLocaleDateString(undefined, {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
            }

            const statusEl = document.getElementById('preview-status');
            if (statusEl) {
                statusEl.innerText = data.order.status;
                statusEl.className = 'order-badge status-' + data.order.status.toLowerCase();
            }

            const editLink = document.getElementById('preview-full-details-link');
            if (editLink) {
                editLink.href = `checkout.php?customer_id=${encodeURIComponent(data.order.customer_id)}&order_id=${encodeURIComponent(data.order.order_id)}`;
            }

            const list = document.getElementById('preview-items-list');
            if (list) {
                list.innerHTML = '';
                let grandTotal = 0;

                if (data.items && data.items.length > 0) {
                    data.items.forEach(item => {
                        const qty = parseFloat(item.quantity) || 0;
                        const price = parseFloat(item.unit_price) || 0;
                        const subtotal = qty * price;
                        grandTotal += subtotal;

                        const desc = [item.series, item.cpu].filter(v => v && v !== 'N/A').join(' / ') || item.description || '';
                        const tr = document.createElement('tr');

                        let isHighlighted = false;
                        if (highlightParams && highlightParams.brand) {
                            const hBrand = highlightParams.brand || '';
                            const hModel = highlightParams.model || '';
                            const hSeries = highlightParams.series || '';
                            const hCpu = highlightParams.cpu || '';
                            const iBrand = item.brand || '';
                            const iModel = item.model || '';
                            const iSeries = item.series || '';
                            const iCpu = item.cpu || '';

                            if (hBrand === iBrand && hModel === iModel && hSeries === iSeries && hCpu === iCpu) {
                                isHighlighted = true;
                            }
                        }

                        tr.style.borderBottom = '1px solid var(--border-color)';
                        if (isHighlighted) {
                            tr.style.backgroundColor = 'rgba(132, 204, 22, 0.15)';
                        }
                        tr.innerHTML = `
                            <td style="padding: 12px 10px; ${isHighlighted ? 'border-left: 4px solid var(--accent-color);' : ''}">
                                <div style="font-weight: 700; color: var(--text-main);">${localEscapeHTML(item.brand)} ${localEscapeHTML(item.model)}</div>
                                ${desc ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">${localEscapeHTML(desc)}</div>` : ''}
                            </td>
                            <td style="padding: 12px 10px; text-align: center; font-weight: 700; color: var(--text-main);">${qty}</td>
                            <td style="padding: 12px 10px; text-align: right; font-weight: 600; color: var(--text-secondary);">$${price.toFixed(2)}</td>
                            <td style="padding: 12px 10px; text-align: right; font-weight: 700; color: var(--text-main);">$${subtotal.toFixed(2)}</td>
                        `;
                        list.appendChild(tr);
                    });
                } else {
                    list.innerHTML = `<tr><td colspan="4" style="padding: 30px; text-align: center; color: var(--text-secondary); font-style: italic;">No items in this batch.</td></tr>`;
                }

                const totalTr = document.createElement('tr');
                totalTr.style.fontWeight = '800';
                totalTr.innerHTML = `
                    <td style="padding: 15px 0; font-size: 0.95rem; color: var(--text-main);">Total Valuation</td>
                    <td></td>
                    <td></td>
                    <td style="padding: 15px 0; text-align: right; font-size: 1rem; color: var(--accent-color);">$${grandTotal.toFixed(2)}</td>
                `;
                list.appendChild(totalTr);
            }

            if (loading) loading.style.display = 'none';
            if (body) body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            if (companyNameEl) companyNameEl.innerText = 'Error';
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
        });

    activeOrderPreviewEscHandler = (e) => {
        if (e.key === 'Escape') closeOrderPreviewModal();
    };
    window.addEventListener('keydown', activeOrderPreviewEscHandler);
}

function closeOrderPreviewModal() {
    const modal = document.getElementById('orderPreviewModal');
    if (modal) modal.style.display = 'none';
    if (activeOrderPreviewEscHandler) {
        window.removeEventListener('keydown', activeOrderPreviewEscHandler);
        activeOrderPreviewEscHandler = null;
    }
}

function openCpuPricingModal(cpuCategory) {
    const modal = document.getElementById('cpuPricingModal');
    bringModalToFront(modal);

    const loading = document.getElementById('cpu-loading');
    const error = document.getElementById('cpu-error');
    const body = document.getElementById('cpu-body');

    const titleEl = document.getElementById('cpu-pricing-title');
    if (titleEl) titleEl.innerText = cpuCategory;

    if (loading) loading.style.display = 'flex';
    if (error) error.style.display = 'none';
    if (body) body.style.display = 'none';
    if (modal) modal.style.display = 'flex';

    const localEscapeHTML = (str) => {
        if (!str) return '—';
        return str.toString().replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m]));
    };

    const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
    const currentTrendsFilter = state.filter || 'all';

    fetch(`api/get_cpu_pricing_details.php?cpu=${encodeURIComponent(cpuCategory)}&filter=${encodeURIComponent(currentTrendsFilter)}`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to load');
            return res.json();
        })
        .then(data => {
            const modelsList = document.getElementById('cpu-models-list');
            if (modelsList) {
                modelsList.innerHTML = '';
                if (data.models && data.models.length > 0) {
                    data.models.forEach(model => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid var(--border-color)';
                        tr.innerHTML = `
                            <td style="padding: 10px 5px;">
                                <strong>${localEscapeHTML(model.brand)}</strong> ${localEscapeHTML(model.model)}
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">${localEscapeHTML(model.series)}</div>
                            </td>
                            <td style="padding: 10px 5px; text-align: center; font-weight: 700;">${model.total_qty}</td>
                            <td style="padding: 10px 5px; text-align: right; color: #10b981;">$${parseFloat(model.min_price).toFixed(2)}</td>
                            <td style="padding: 10px 5px; text-align: right; color: #3b82f6;">$${parseFloat(model.max_price).toFixed(2)}</td>
                            <td style="padding: 10px 5px; text-align: right; font-weight: 700;">$${parseFloat(model.avg_price).toFixed(2)}</td>
                        `;
                        modelsList.appendChild(tr);
                    });
                } else {
                    modelsList.innerHTML = `<tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--text-secondary); font-style: italic;">No model statistics found.</td></tr>`;
                }
            }

            const salesList = document.getElementById('cpu-sales-list');
            if (salesList) {
                salesList.innerHTML = '';
                if (data.recent_sales && data.recent_sales.length > 0) {
                    data.recent_sales.forEach(sale => {
                        const desc = [sale.series, sale.cpu].filter(v => v && v !== 'N/A').join(' / ') || sale.description || '';
                        const dateObj = new Date(sale.created_at.replace(/-/g, "/"));
                        const formattedDate = dateObj.toLocaleDateString(undefined, {
                            year: 'numeric', month: 'short', day: 'numeric'
                        });
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-brand', sale.brand || '');
                        tr.setAttribute('data-model', sale.model || '');
                        tr.setAttribute('data-series', sale.series || '');
                        tr.setAttribute('data-cpu', sale.cpu || '');
                        tr.style.borderBottom = '1px solid var(--border-color)';
                        tr.innerHTML = `
                            <td style="padding: 10px 5px; font-size: 0.8rem; white-space: nowrap;">${formattedDate}</td>
                            <td style="padding: 10px 5px; font-weight: 600; font-size: 0.85rem;">
                                <a href="#" onclick="openCustomerProfileModal(event, '', '${localEscapeHTML(sale.company_name)}')" class="customer-profile-link" style="color: var(--accent-color); text-decoration: underline; text-underline-offset: 2px;">${localEscapeHTML(sale.company_name)}</a>
                            </td>
                            <td style="padding: 10px 5px;">
                                <span style="font-weight: 700;">${localEscapeHTML(sale.brand)} ${localEscapeHTML(sale.model)}</span>
                                ${desc ? `<div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 1px;">${localEscapeHTML(desc)}</div>` : ''}
                            </td>
                            <td style="padding: 10px 5px; text-align: center; font-weight: 700;">${sale.quantity}</td>
                            <td style="padding: 10px 5px; text-align: right; font-weight: 600;">$${parseFloat(sale.unit_price).toFixed(2)}</td>
                            <td style="padding: 10px 5px; text-align: right; font-family: monospace;">
                                <a href="#" onclick="openOrderPreviewModal(event, '${localEscapeHTML(sale.order_id)}')" class="order-preview-link"><code>${localEscapeHTML(sale.order_id)}</code></a>
                            </td>
                        `;
                        salesList.appendChild(tr);
                    });
                } else {
                    salesList.innerHTML = `<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-secondary); font-style: italic;">No recent transactions.</td></tr>`;
                }
            }

            if (loading) loading.style.display = 'none';
            if (body) body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
        });

    activeCpuPricingEscHandler = (e) => {
        if (e.key === 'Escape') closeCpuPricingModal();
    };
    window.addEventListener('keydown', activeCpuPricingEscHandler);
}

function closeCpuPricingModal() {
    const modal = document.getElementById('cpuPricingModal');
    if (modal) modal.style.display = 'none';
    if (activeCpuPricingEscHandler) {
        window.removeEventListener('keydown', activeCpuPricingEscHandler);
        activeCpuPricingEscHandler = null;
    }
}

function showMatrixSaveToast(msg, targetInput) {
    let toast = document.getElementById('matrixSaveToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'matrixSaveToast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<span style="font-size: 1.1rem;">✓</span> <span>${msg || 'Price updated and saved'}</span>`;
    toast.classList.add('show');

    if (targetInput) {
        targetInput.classList.remove('cell-saved-pulse');
        void targetInput.offsetWidth; // trigger reflow
        targetInput.classList.add('cell-saved-pulse');
    }

    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2000);
}

function updateMatrixCell(category, cpu_gen, grade, price, targetInput) {
    const parsedPrice = parseFloat(price);
    const sanitizedPrice = isNaN(parsedPrice) ? 0.00 : parsedPrice;

    fetch('index.php?view=trends&action=update_pricing_matrix', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            category: category,
            cpu_gen: cpu_gen,
            grade: grade,
            price: sanitizedPrice
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const specLabel = cpu_gen === 'Default' ? '' : ` (${cpu_gen})`;
            showMatrixSaveToast(`${category}${specLabel} • ${grade} saved ($${sanitizedPrice.toFixed(2)})`, targetInput);
            if (window.IQA_Notify && typeof window.IQA_Notify.success === 'function') {
                window.IQA_Notify.success(`Successfully updated ${category} - ${cpu_gen === 'Default' ? '' : cpu_gen + ' - '}${grade} to $${sanitizedPrice.toFixed(2)}`);
            }
        } else {
            if (window.IQA_Notify && typeof window.IQA_Notify.error === 'function') {
                window.IQA_Notify.error(data.error || 'Failed to update pricing rule');
            } else {
                alert(data.error || 'Failed to update pricing rule');
            }
        }
    })
    .catch(error => {
        console.error('Error updating pricing rule:', error);
        if (window.IQA_Notify && typeof window.IQA_Notify.error === 'function') {
            window.IQA_Notify.error('Error connecting to server. Please try again.');
        } else {
            alert('Error connecting to server. Please try again.');
        }
    });
}

let activeCustomerProfileEscHandler = null;

function openCustomerProfileModal(event, customerId, companyName) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const modal = document.getElementById('customerProfileModal');
    if (!modal) return;

    if (typeof bringModalToFront === 'function') bringModalToFront(modal);
    modal.style.display = 'flex';

    const loading = document.getElementById('cust-modal-loading');
    const error = document.getElementById('cust-modal-error');
    const body = document.getElementById('cust-modal-body');

    if (loading) loading.style.display = 'flex';
    if (error) error.style.display = 'none';
    if (body) body.style.display = 'none';

    const localEscapeHTML = (str) => {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const targetUrl = `index.php?view=trends&action=get_customer_profile&customer_id=${encodeURIComponent(customerId || '')}&company_name=${encodeURIComponent(companyName || '')}`;

    fetch(targetUrl)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.customer) {
                if (loading) loading.style.display = 'none';
                if (error) {
                    error.textContent = '⚠️ ' + (data.error || 'Customer profile not found.');
                    error.style.display = 'block';
                }
                return;
            }

            const cust = data.customer;
            const stats = data.stats || {};
            const recentOrders = data.recent_orders || [];

            // Header Elements
            const compEl = document.getElementById('cust-modal-company');
            const idEl = document.getElementById('cust-modal-id');
            const badgeEl = document.getElementById('cust-modal-status-badge');

            if (compEl) compEl.textContent = cust.company_name || 'Customer Profile';
            if (idEl) idEl.textContent = cust.customer_id || 'CUST-EXTERNAL';
            if (badgeEl) {
                badgeEl.textContent = cust.status || 'Active';
                if (cust.status === 'Hot Lead') badgeEl.style.background = '#ef4444';
                else if (cust.status === 'Follow-Up') badgeEl.style.background = '#f59e0b';
                else badgeEl.style.background = '#10b981';
            }

            // Stats Elements
            const spendEl = document.getElementById('cust-modal-spend');
            const unitsEl = document.getElementById('cust-modal-units');
            const ordersEl = document.getElementById('cust-modal-orders');
            const lastDateEl = document.getElementById('cust-modal-last-date');

            if (spendEl) spendEl.textContent = '$' + parseFloat(stats.total_spend || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            if (unitsEl) unitsEl.textContent = parseInt(stats.total_units || 0).toLocaleString() + ' units';
            if (ordersEl) ordersEl.textContent = parseInt(stats.total_orders || 0).toLocaleString() + ' orders';
            if (lastDateEl) lastDateEl.textContent = stats.last_order_date ? stats.last_order_date.substring(0, 10) : '—';

            // Contact Info
            const contactEl = document.getElementById('cust-modal-contact');
            const phoneEl = document.getElementById('cust-modal-phone');
            const emailEl = document.getElementById('cust-modal-email');
            const firstDateEl = document.getElementById('cust-modal-first-date');

            if (contactEl) contactEl.textContent = cust.contact_person || '—';
            if (phoneEl) phoneEl.textContent = cust.phone || '—';
            if (emailEl) emailEl.textContent = cust.email || '—';
            if (firstDateEl) firstDateEl.textContent = stats.first_order_date ? stats.first_order_date.substring(0, 10) : '—';

            // Action Links
            const crmBtn = document.getElementById('cust-modal-crm-btn');
            const orderBtn = document.getElementById('cust-modal-order-btn');

            if (crmBtn) crmBtn.href = `index.php?view=leads&search=${encodeURIComponent(cust.company_name || '')}`;
            if (orderBtn) orderBtn.href = `index.php?customer_id=${encodeURIComponent(cust.customer_id || '')}`;

            // Recent Orders Table
            const ordersList = document.getElementById('cust-modal-orders-list');
            if (ordersList) {
                ordersList.innerHTML = '';
                if (recentOrders.length > 0) {
                    recentOrders.forEach(o => {
                        const tr = document.createElement('tr');
                        const orderDate = o.created_at ? o.created_at.substring(0, 10) : '—';
                        const units = parseInt(o.units_count || 0);
                        const val = parseFloat(o.order_total || 0);
                        const statusClass = (o.status === 'paid' || o.status === 'completed') ? 'order-status-paid' : 'order-status-pending';

                        tr.innerHTML = `
                            <td style="padding: 8px 12px; font-family: monospace; font-weight: 700;">
                                <a href="#" onclick="openOrderPreviewModal(event, '${localEscapeHTML(o.order_id)}')" class="order-preview-link"><code>${localEscapeHTML(o.order_id)}</code></a>
                            </td>
                            <td style="padding: 8px 12px; font-size: 0.85rem; color: var(--text-secondary);">${orderDate}</td>
                            <td style="padding: 8px 12px; text-align: center; font-weight: 700;">${units.toLocaleString()}</td>
                            <td style="padding: 8px 12px; text-align: right; font-weight: 800; color: #10b981;">$${val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            <td style="padding: 8px 12px; text-align: center;">
                                <span class="order-badge ${statusClass}" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;">${localEscapeHTML(o.status || 'Active')}</span>
                            </td>
                            <td style="padding: 8px 12px; text-align: center;">
                                <button type="button" onclick="openOrderPreviewModal(event, '${localEscapeHTML(o.order_id)}')" style="padding: 3px 8px; font-size: 0.75rem; border-radius: 6px; background: var(--bg-surface); border: 1px solid var(--border-color); color: var(--text-main); font-weight: 700; cursor: pointer;">
                                    View Manifest
                                </button>
                            </td>
                        `;
                        ordersList.appendChild(tr);
                    });
                } else {
                    ordersList.innerHTML = `<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-secondary); font-style: italic;">No orders recorded yet.</td></tr>`;
                }
            }

            if (loading) loading.style.display = 'none';
            if (body) body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            if (loading) loading.style.display = 'none';
            if (error) error.style.display = 'block';
        });

    activeCustomerProfileEscHandler = (e) => {
        if (e.key === 'Escape') closeCustomerProfileModal();
    };
    window.addEventListener('keydown', activeCustomerProfileEscHandler);
}

function closeCustomerProfileModal() {
    const modal = document.getElementById('customerProfileModal');
    if (modal) modal.style.display = 'none';
    if (activeCustomerProfileEscHandler) {
        window.removeEventListener('keydown', activeCustomerProfileEscHandler);
        activeCustomerProfileEscHandler = null;
    }
}
