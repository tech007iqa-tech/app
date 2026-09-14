/**
 * Trends Master Coordinator
 * Central coordinator managing state hydration, sub-module loading, Chart.js graphs, and widget lifecycle.
 */

let __trendsState = null;
function getTrendsState() {
    if (__trendsState) return __trendsState;
    const el = document.getElementById('trends-state');
    __trendsState = el ? JSON.parse(el.textContent) : {};
    return __trendsState;
}

// 1. Synchronously load all trends sub-modules in order
(function loadTrendsModules() {
    const modules = [
        'assets/js/trends/trends_nav.js',
        'assets/js/trends/trends_charts.js',
        'assets/js/trends/trends_widgets.js',
        'assets/js/trends/trends_modals.js'
    ];

    modules.forEach(src => {
        if (!document.querySelector(`script[src*="${src}"]`)) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            document.head.appendChild(script);
        }
    });
})();

// 2. Initialize trends dashboard on DOMContentLoaded
function initTrendsApp() {
    const state = getTrendsState();

    // Handle initial tab selection & search listeners
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tested_cat') || urlParams.get('tab') === 'tab-tested') {
        if (typeof switchTrendsTab === 'function') switchTrendsTab('tab-tested');
    } else {
        if (typeof filterActiveTable === 'function') filterActiveTable();
    }

    const searchInput = document.getElementById('trends-search');
    const clearBtn = document.getElementById('clear-search');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (clearBtn) clearBtn.style.display = searchInput.value ? 'block' : 'none';
            if (typeof filterActiveTable === 'function') filterActiveTable();
        });
    }

    // Initialize Chart.js Graphs
    if (typeof initializePricingCharts === 'function') initializePricingCharts(state.price_history);
    if (typeof initializeCpuCharts === 'function') initializeCpuCharts(state.cpu_distribution);

    // Initialize Summary Cards Widget Board
    if (typeof loadWidgetConfig === 'function') loadWidgetConfig();
    if (typeof renderWidgetToggles === 'function') renderWidgetToggles();
    if (typeof renderWidgetBoard === 'function') renderWidgetBoard();

    // Disable matrix editing if non-Admin
    if (state.user_role && state.user_role !== 'Admin') {
        const matrixInputs = document.querySelectorAll('.matrix-cell-input');
        matrixInputs.forEach(input => {
            input.disabled = true;
            input.style.opacity = '0.7';
            input.style.cursor = 'not-allowed';
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTrendsApp);
} else {
    initTrendsApp();
}
