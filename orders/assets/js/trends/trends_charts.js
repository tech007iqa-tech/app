/**
 * Trends Chart Visualizations Module (Accounting & Financial Analytics Edition)
 * Manages Chart.js graphs for ASP Timeline, Realized Valuation Trends, and CPU Distribution
 * with safe instance lifecycle, chronological time-series ordering, and real-time theme adaptation.
 */

let aspChartInstance = null;
let valuationChartInstance = null;
let cpuBrandChartInstance = null;

function getChartThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const styles = getComputedStyle(document.documentElement);
    const accent = styles.getPropertyValue('--accent-color').trim() || (isDark ? '#38bdf8' : '#2563eb');
    const textMain = styles.getPropertyValue('--text-main').trim() || (isDark ? '#f8fafc' : '#0f172a');
    const textSecondary = styles.getPropertyValue('--text-secondary').trim() || (isDark ? '#94a3b8' : '#64748b');
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.06)';
    const surfaceBg = isDark ? '#1e293b' : '#ffffff';
    return { isDark, accent, textMain, textSecondary, gridColor, surfaceBg };
}

/**
 * Initializes or updates CPU Brand Distribution doughnut chart
 */
function initializeCpuCharts(cpuData) {
    if (typeof Chart === 'undefined') {
        setTimeout(() => initializeCpuCharts(cpuData), 100);
        return;
    }

    if (!cpuData || cpuData.length === 0) {
        const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
        cpuData = state.cpu_distribution || [];
    }
    if (!cpuData || cpuData.length === 0) return;

    const canvasCpu = document.getElementById('cpuBrandChart');
    if (!canvasCpu) return;

    const existingChart = Chart.getChart('cpuBrandChart');
    if (existingChart) existingChart.destroy();
    if (cpuBrandChartInstance) {
        cpuBrandChartInstance.destroy();
        cpuBrandChartInstance = null;
    }

    const labels = cpuData.map(d => d.cpu);
    const quantities = cpuData.map(d => parseInt(d.total_qty || 0, 10));

    const baseColors = {
        'Core 2 Duo': '#94a3b8',
        '2nd & 3rd Gen': '#cbd5e1',
        '4th & 5th Gen': '#64748b',
        '6th & 7th Gen': '#475569',
        'Apple': '#a855f7',
        'Ryzen': '#f97316'
    };

    const categoryColors = { ...baseColors };
    const gens = ['8th', '9th', '10th', '11th', '12th', '13th', '14th'];
    const tiers = ['i3', 'i5', 'i7'];
    const genHue = {
        '8th': '#93c5fd',
        '9th': '#60a5fa',
        '10th': '#3b82f6',
        '11th': '#2563eb',
        '12th': '#1d4ed8',
        '13th': '#1e3a8a',
        '14th': '#0f172a'
    };

    gens.forEach(gen => {
        tiers.forEach(tier => {
            categoryColors[`${gen} Gen ${tier}`] = genHue[gen];
        });
    });

    const colors = labels.map(label => categoryColors[label] || '#38bdf8');
    const theme = getChartThemeColors();
    const ctxCpu = canvasCpu.getContext('2d');

    cpuBrandChartInstance = new Chart(ctxCpu, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: quantities,
                backgroundColor: colors,
                borderWidth: theme.isDark ? 2 : 1,
                borderColor: theme.isDark ? '#1e293b' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: theme.textSecondary,
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' },
                        padding: 12
                    }
                },
                tooltip: {
                    backgroundColor: theme.isDark ? '#0f172a' : '#1e293b',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const val = context.parsed;
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ' ' + context.label + ': ' + val.toLocaleString() + ' units (' + pct + '%)';
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
}

/**
 * Initializes or updates Accounting & Financial Trends graphs (ASP and Monthly Valuation)
 */
function initializePricingCharts(priceData) {
    if (typeof Chart === 'undefined') {
        setTimeout(() => initializePricingCharts(priceData), 100);
        return;
    }

    if (!priceData || priceData.length === 0) {
        const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
        priceData = state.price_history || [];
    }
    if (!priceData || priceData.length === 0) return;

    // 1. Sort data chronologically (left to right from oldest to newest month)
    const chronologicalData = [...priceData].sort((a, b) => (a.sales_month || '').localeCompare(b.sales_month || ''));

    const labels = chronologicalData.map(d => d.sales_month);
    const avgPrices = chronologicalData.map(d => parseFloat(d.avg_price || 0));
    const valuations = chronologicalData.map(d => parseFloat(d.total_valuation ?? (d.avg_price * d.total_qty)));
    const quantities = chronologicalData.map(d => parseInt(d.total_qty || 0, 10));

    const theme = getChartThemeColors();

    // 2. Average Selling Price (ASP) Line Chart
    const canvasAsp = document.getElementById('aspChart');
    if (canvasAsp) {
        const existingAsp = Chart.getChart('aspChart');
        if (existingAsp) existingAsp.destroy();
        if (aspChartInstance) {
            aspChartInstance.destroy();
            aspChartInstance = null;
        }

        const ctxAsp = canvasAsp.getContext('2d');
        const gradientAsp = ctxAsp.createLinearGradient(0, 0, 0, 260);
        gradientAsp.addColorStop(0, theme.isDark ? 'rgba(56, 189, 248, 0.35)' : 'rgba(37, 99, 235, 0.25)');
        gradientAsp.addColorStop(1, theme.isDark ? 'rgba(56, 189, 248, 0.00)' : 'rgba(37, 99, 235, 0.00)');

        const lineColor = theme.isDark ? '#38bdf8' : '#2563eb';

        aspChartInstance = new Chart(ctxAsp, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avg Selling Price ($ / unit)',
                    data: avgPrices,
                    borderColor: lineColor,
                    backgroundColor: gradientAsp,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: lineColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: theme.isDark ? '#0f172a' : '#1e293b',
                        titleColor: '#ffffff',
                        titleFont: { family: 'Outfit, Inter, sans-serif', weight: '700', size: 12 },
                        bodyColor: '#e2e8f0',
                        bodyFont: { family: 'Outfit, Inter, sans-serif', size: 11 },
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const price = context.parsed.y;
                                const qty = quantities[idx] || 0;
                                return [
                                    ' Realized ASP: $' + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' / unit',
                                    ' Volume Realized: ' + qty.toLocaleString() + ' units'
                                ];
                            },
                            afterBody: function(contexts) {
                                const idx = contexts[0].dataIndex;
                                if (idx > 0) {
                                    const prevPrice = avgPrices[idx - 1];
                                    const currPrice = avgPrices[idx];
                                    const diff = currPrice - prevPrice;
                                    const pct = prevPrice > 0 ? ((diff / prevPrice) * 100).toFixed(1) : '0.0';
                                    const sign = diff >= 0 ? '+' : '';
                                    return ' MoM Price Δ: ' + sign + '$' + diff.toFixed(2) + ' (' + sign + pct + '%)';
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: theme.gridColor },
                        ticks: {
                            color: theme.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' },
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: theme.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    }

    // 3. Monthly Gross Valuation & Revenue Bar Chart
    const canvasVal = document.getElementById('valuationChart');
    if (canvasVal) {
        const existingVal = Chart.getChart('valuationChart');
        if (existingVal) existingVal.destroy();
        if (valuationChartInstance) {
            valuationChartInstance.destroy();
            valuationChartInstance = null;
        }

        const ctxVal = canvasVal.getContext('2d');
        const gradientVal = ctxVal.createLinearGradient(0, 0, 0, 260);
        gradientVal.addColorStop(0, '#10b981'); // Emerald financial color
        gradientVal.addColorStop(1, '#059669');

        valuationChartInstance = new Chart(ctxVal, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Gross Realized Valuation ($)',
                    data: valuations,
                    backgroundColor: gradientVal,
                    hoverBackgroundColor: '#34d399',
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: theme.isDark ? '#0f172a' : '#1e293b',
                        titleColor: '#ffffff',
                        titleFont: { family: 'Outfit, Inter, sans-serif', weight: '700', size: 12 },
                        bodyColor: '#e2e8f0',
                        bodyFont: { family: 'Outfit, Inter, sans-serif', size: 11 },
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.parsed.y;
                                const qty = quantities[idx] || 0;
                                const asp = qty > 0 ? (val / qty) : 0;
                                return [
                                    ' Gross Valuation: $' + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                                    ' Invoiced Units: ' + qty.toLocaleString() + ' units (ASP: $' + asp.toFixed(2) + ')'
                                ];
                            },
                            afterBody: function(contexts) {
                                const idx = contexts[0].dataIndex;
                                if (idx > 0) {
                                    const prevVal = valuations[idx - 1];
                                    const currVal = valuations[idx];
                                    const diff = currVal - prevVal;
                                    const pct = prevVal > 0 ? ((diff / prevVal) * 100).toFixed(1) : '0.0';
                                    const sign = diff >= 0 ? '+' : '';
                                    return ' MoM Revenue Δ: ' + sign + '$' + diff.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + sign + pct + '%)';
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: theme.gridColor },
                        ticks: {
                            color: theme.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' },
                            callback: function(value) {
                                if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: theme.textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    }
}

let comboPricingChartInstance = null;

/**
 * Initializes or updates Dual-Axis Combo Pricing Chart (Valuation Bars + ASP Trendline)
 */
function initializeComboPricingChart(priceData) {
    if (typeof Chart === 'undefined') {
        setTimeout(() => initializeComboPricingChart(priceData), 100);
        return;
    }

    if (!priceData || priceData.length === 0) {
        const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
        priceData = state.price_history || [];
    }
    if (!priceData || priceData.length === 0) return;

    const chronologicalData = [...priceData].sort((a, b) => (a.sales_month || '').localeCompare(b.sales_month || ''));
    const labels = chronologicalData.map(d => d.sales_month);
    const avgPrices = chronologicalData.map(d => parseFloat(d.avg_price || 0));
    const valuations = chronologicalData.map(d => parseFloat(d.total_valuation ?? (d.avg_price * d.total_qty)));
    const quantities = chronologicalData.map(d => parseInt(d.total_qty || 0, 10));

    const canvasCombo = document.getElementById('comboPricingChart');
    if (!canvasCombo) return;

    const existingCombo = Chart.getChart('comboPricingChart');
    if (existingCombo) existingCombo.destroy();
    if (comboPricingChartInstance) {
        comboPricingChartInstance.destroy();
        comboPricingChartInstance = null;
    }

    const theme = getChartThemeColors();
    const ctxCombo = canvasCombo.getContext('2d');

    const gradientVal = ctxCombo.createLinearGradient(0, 0, 0, 320);
    gradientVal.addColorStop(0, 'rgba(16, 185, 129, 0.85)');
    gradientVal.addColorStop(1, 'rgba(16, 185, 129, 0.25)');

    const gradientAsp = ctxCombo.createLinearGradient(0, 0, 0, 320);
    gradientAsp.addColorStop(0, theme.isDark ? 'rgba(56, 189, 248, 0.35)' : 'rgba(37, 99, 235, 0.25)');
    gradientAsp.addColorStop(1, theme.isDark ? 'rgba(56, 189, 248, 0.00)' : 'rgba(37, 99, 235, 0.00)');

    const lineColor = theme.isDark ? '#38bdf8' : '#2563eb';

    comboPricingChartInstance = new Chart(ctxCombo, {
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Gross Realized Valuation ($)',
                    data: valuations,
                    backgroundColor: gradientVal,
                    hoverBackgroundColor: '#10b981',
                    borderRadius: 6,
                    barPercentage: 0.55,
                    categoryPercentage: 0.7,
                    yAxisID: 'yValuation',
                    order: 2
                },
                {
                    type: 'line',
                    label: 'Realized ASP ($ / unit)',
                    data: avgPrices,
                    borderColor: lineColor,
                    backgroundColor: gradientAsp,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: lineColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    yAxisID: 'yAsp',
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: theme.isDark ? '#0f172a' : '#1e293b',
                    titleColor: '#ffffff',
                    titleFont: { family: 'Outfit, Inter, sans-serif', weight: '700', size: 12 },
                    bodyColor: '#e2e8f0',
                    bodyFont: { family: 'Outfit, Inter, sans-serif', size: 11 },
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(context) {
                            const idx = context.dataIndex;
                            const qty = quantities[idx] || 0;
                            if (context.dataset.yAxisID === 'yValuation') {
                                const val = context.parsed.y;
                                return ' 💰 Gross Valuation: $' + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + qty.toLocaleString() + ' units)';
                            } else {
                                const price = context.parsed.y;
                                return ' 🏷️ Realized ASP: $' + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' / unit';
                            }
                        },
                        afterBody: function(contexts) {
                            const idx = contexts[0].dataIndex;
                            if (idx > 0) {
                                const prevVal = valuations[idx - 1];
                                const currVal = valuations[idx];
                                const diffVal = currVal - prevVal;
                                const pctVal = prevVal > 0 ? ((diffVal / prevVal) * 100).toFixed(1) : '0.0';
                                const signVal = diffVal >= 0 ? '+' : '';

                                const prevAsp = avgPrices[idx - 1];
                                const currAsp = avgPrices[idx];
                                const diffAsp = currAsp - prevAsp;
                                const pctAsp = prevAsp > 0 ? ((diffAsp / prevAsp) * 100).toFixed(1) : '0.0';
                                const signAsp = diffAsp >= 0 ? '+' : '';

                                return [
                                    ' MoM Valuation Δ: ' + signVal + '$' + diffVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + signVal + pctVal + '%)',
                                    ' MoM Price Δ: ' + signAsp + '$' + diffAsp.toFixed(2) + ' (' + signAsp + pctAsp + '%)'
                                ];
                            }
                            return '';
                        }
                    }
                }
            },
            scales: {
                yValuation: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: theme.gridColor },
                    ticks: {
                        color: '#10b981',
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '700' },
                        callback: function(value) {
                            if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
                            return '$' + value;
                        }
                    },
                    title: {
                        display: true,
                        text: 'Gross Realized Valuation ($)',
                        color: '#10b981',
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '700' }
                    }
                },
                yAsp: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: {
                        color: lineColor,
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '700' },
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    },
                    title: {
                        display: true,
                        text: 'Realized ASP ($ / unit)',
                        color: lineColor,
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '700' }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: theme.textSecondary,
                        font: { family: 'Outfit, Inter, sans-serif', size: 11, weight: '600' }
                    }
                }
            }
        }
    });
}

/**
 * Toggles between Split View and Dual-Axis Combo View on Tab 2
 */
function setPricingChartViewMode(mode) {
    const splitContainer = document.getElementById('pricingSplitViewContainer');
    const comboContainer = document.getElementById('pricingComboViewContainer');
    const splitBtn = document.getElementById('chartViewSplitBtn');
    const comboBtn = document.getElementById('chartViewComboBtn');

    sessionStorage.setItem('pricing_chart_view_mode', mode);

    if (mode === 'combo') {
        if (splitContainer) splitContainer.style.display = 'none';
        if (comboContainer) comboContainer.style.display = 'block';

        if (splitBtn) {
            splitBtn.style.background = 'transparent';
            splitBtn.style.color = 'var(--text-secondary)';
            splitBtn.classList.remove('active');
        }
        if (comboBtn) {
            comboBtn.style.background = '#3b82f6';
            comboBtn.style.color = '#ffffff';
            comboBtn.classList.add('active');
        }

        setTimeout(() => {
            initializeComboPricingChart();
        }, 50);
    } else {
        if (splitContainer) splitContainer.style.display = 'grid';
        if (comboContainer) comboContainer.style.display = 'none';

        if (splitBtn) {
            splitBtn.style.background = '#3b82f6';
            splitBtn.style.color = '#ffffff';
            splitBtn.classList.add('active');
        }
        if (comboBtn) {
            comboBtn.style.background = 'transparent';
            comboBtn.style.color = 'var(--text-secondary)';
            comboBtn.classList.remove('active');
        }

        setTimeout(() => {
            initializePricingCharts();
        }, 50);
    }
}

// Observe theme toggle and refresh chart color tokens
(function initTrendsThemeObserver() {
    const observer = new MutationObserver(() => {
        const theme = getChartThemeColors();

        if (aspChartInstance) {
            aspChartInstance.options.scales.y.grid.color = theme.gridColor;
            aspChartInstance.options.scales.y.ticks.color = theme.textSecondary;
            aspChartInstance.options.scales.x.ticks.color = theme.textSecondary;
            aspChartInstance.update('none');
        }

        if (valuationChartInstance) {
            valuationChartInstance.options.scales.y.grid.color = theme.gridColor;
            valuationChartInstance.options.scales.y.ticks.color = theme.textSecondary;
            valuationChartInstance.options.scales.x.ticks.color = theme.textSecondary;
            valuationChartInstance.update('none');
        }

        if (comboPricingChartInstance) {
            comboPricingChartInstance.options.scales.yValuation.grid.color = theme.gridColor;
            comboPricingChartInstance.options.scales.x.ticks.color = theme.textSecondary;
            comboPricingChartInstance.update('none');
        }

        if (cpuBrandChartInstance) {
            cpuBrandChartInstance.options.plugins.legend.labels.color = theme.textSecondary;
            cpuBrandChartInstance.data.datasets[0].borderColor = theme.isDark ? '#1e293b' : '#ffffff';
            cpuBrandChartInstance.update('none');
        }
    });

    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
