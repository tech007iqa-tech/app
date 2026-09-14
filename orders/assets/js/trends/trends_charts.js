/**
 * Trends Chart Visualizations Module
 * Initializes Chart.js graphs for ASP Timeline, Valuation Trends, and CPU Manufacturer Share with real-time dark/light theme adaptation.
 */

function initializeCpuCharts(cpuData) {
    if (!cpuData || cpuData.length === 0) {
        const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
        cpuData = state.cpu_distribution || [];
    }
    if (!cpuData || cpuData.length === 0) return;

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

    const colors = labels.map(label => categoryColors[label] || '#a1a1aa');

    const ctxCpu = document.getElementById('cpuBrandChart')?.getContext('2d');
    if (ctxCpu) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const styles = getComputedStyle(document.documentElement);
        const textSecondary = styles.getPropertyValue('--text-secondary').trim() || (isDark ? '#cbd5e1' : '#4b5563');

        const cpuBrandChart = new Chart(ctxCpu, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: quantities,
                    backgroundColor: colors,
                    borderWidth: isDark ? 2 : 1,
                    borderColor: isDark ? '#1e293b' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textSecondary,
                            font: { family: 'Outfit, Inter, sans-serif', size: 11 },
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.parsed;
                                const pct = ((val / total) * 100).toFixed(1);
                                return ' ' + context.label + ': ' + val.toLocaleString() + ' units (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        const observer = new MutationObserver(() => {
            const isDarkNew = document.documentElement.getAttribute('data-theme') === 'dark';
            const stylesNew = getComputedStyle(document.documentElement);
            const textSecNew = stylesNew.getPropertyValue('--text-secondary').trim() || (isDarkNew ? '#cbd5e1' : '#4b5563');

            cpuBrandChart.options.plugins.legend.labels.color = textSecNew;
            cpuBrandChart.data.datasets[0].borderColor = isDarkNew ? '#1e293b' : '#ffffff';
            cpuBrandChart.data.datasets[0].borderWidth = isDarkNew ? 2 : 1;
            cpuBrandChart.update();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }
}

function initializePricingCharts(priceData) {
    if (!priceData || priceData.length === 0) {
        const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
        priceData = state.price_history ? [...state.price_history].slice(0, 12).reverse() : [];
    }
    if (!priceData || priceData.length === 0) return;

    const labels = priceData.map(d => d.sales_month);
    const avgPrices = priceData.map(d => parseFloat(d.avg_price));
    const valuations = priceData.map(d => parseFloat(d.total_valuation || (d.avg_price * d.total_qty)));

    const getThemeColors = () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const styles = getComputedStyle(document.documentElement);
        const accent = styles.getPropertyValue('--accent-color').trim() || (isDark ? '#38bdf8' : '#0056b3');
        const textMain = styles.getPropertyValue('--text-main').trim() || (isDark ? '#f8fafc' : '#0f172a');
        const textSecondary = styles.getPropertyValue('--text-secondary').trim() || (isDark ? '#cbd5e1' : '#4b5563');
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
        return { accent, textMain, textSecondary, gridColor };
    };

    let colors = getThemeColors();

    // 1. ASP Line Chart
    const canvasAsp = document.getElementById('aspChart');
    let aspChart;
    if (canvasAsp) {
        const ctxAsp = canvasAsp.getContext('2d');
        if (ctxAsp) {
            const gradient = ctxAsp.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, colors.accent + '33');
            gradient.addColorStop(1, colors.accent + '00');

            aspChart = new Chart(ctxAsp, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Avg Selling Price ($)',
                        data: avgPrices,
                        borderColor: colors.accent,
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: colors.accent,
                        pointBorderColor: '#fff',
                        pointHoverRadius: 7,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Avg Price: $' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: { color: colors.gridColor },
                            ticks: {
                                color: colors.textSecondary,
                                font: { family: 'Outfit, Inter, sans-serif', size: 10 },
                                callback: function(value) { return '$' + value; }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: colors.textSecondary,
                                font: { family: 'Outfit, Inter, sans-serif', size: 10 }
                            }
                        }
                    }
                }
            });
        }
    }

    // 2. Valuation Bar Chart
    const canvasVal = document.getElementById('valuationChart');
    let valuationChart;
    if (canvasVal) {
        const ctxVal = canvasVal.getContext('2d');
        if (ctxVal) {
            valuationChart = new Chart(ctxVal, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Valuation ($)',
                        data: valuations,
                        backgroundColor: '#3b82f6',
                        hoverBackgroundColor: '#2563eb',
                        borderRadius: 6,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Valuation: $' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            grid: { color: colors.gridColor },
                            ticks: {
                                color: colors.textSecondary,
                                font: { family: 'Outfit, Inter, sans-serif', size: 10 },
                                callback: function(value) {
                                    if (value >= 1000) return '$' + (value / 1000) + 'k';
                                    return '$' + value;
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: colors.textSecondary,
                                font: { family: 'Outfit, Inter, sans-serif', size: 10 }
                            }
                        }
                    }
                }
            });
        }
    }

    // Dynamic Theme Observer
    const observer = new MutationObserver(() => {
        const newColors = getThemeColors();
        if (aspChart) {
            aspChart.options.scales.y.grid.color = newColors.gridColor;
            aspChart.options.scales.y.ticks.color = newColors.textSecondary;
            aspChart.options.scales.x.ticks.color = newColors.textSecondary;
            aspChart.data.datasets[0].borderColor = newColors.accent;
            aspChart.data.datasets[0].pointBackgroundColor = newColors.accent;

            const ctxAsp = canvasAsp.getContext('2d');
            const newGrad = ctxAsp.createLinearGradient(0, 0, 0, 260);
            newGrad.addColorStop(0, newColors.accent + '33');
            newGrad.addColorStop(1, newColors.accent + '00');
            aspChart.data.datasets[0].backgroundColor = newGrad;

            aspChart.update();
        }
        if (valuationChart) {
            valuationChart.options.scales.y.grid.color = newColors.gridColor;
            valuationChart.options.scales.y.ticks.color = newColors.textSecondary;
            valuationChart.options.scales.x.ticks.color = newColors.textSecondary;
            valuationChart.update();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
}
