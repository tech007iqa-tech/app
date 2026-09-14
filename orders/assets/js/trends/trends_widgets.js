/**
 * Trends Dynamic Draggable Widget Board Module
 * Manages KPI summary cards, custom metrics, notes, drag-and-drop reordering, and localStorage persistence.
 */

let WIDGET_METRICS_DATA = {};

const DEFAULT_WIDGET_CONFIG = [
    { id: 'units_sold', type: 'metric', visible: true },
    { id: 'orders_count', type: 'metric', visible: true },
    { id: 'avg_value', type: 'metric', visible: true },
    { id: 'top_buyer', type: 'metric', visible: false },
    { id: 'popular_brand', type: 'metric', visible: false },
    { id: 'peak_month', type: 'metric', visible: false },
    { id: 'ryzen_qty', type: 'metric', visible: false }
];

let widgetsConfig = [];
let dragSourceEl = null;

function initWidgetMetricsData() {
    const state = typeof getTrendsState === 'function' ? getTrendsState() : {};
    const totals = state.totals || { total_qty: 0, total_orders: 0, avg_order_val: 0 };

    WIDGET_METRICS_DATA = {
        'units_sold': { emoji: '📦', title: 'Total Units Sold', value: Number(totals.total_qty || 0).toLocaleString() },
        'orders_count': { emoji: '📝', title: 'Total Orders', value: Number(totals.total_orders || 0).toLocaleString() },
        'avg_value': { emoji: '💵', title: 'Avg. Order Value', value: '$' + Number(totals.avg_order_val || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) },
        'top_buyer': { emoji: '🤝', title: 'Top Buyer', value: (state.top_buyer_name || 'None') + ' (' + Number(state.top_buyer_qty || 0).toLocaleString() + ' units)' },
        'popular_brand': { emoji: '👑', title: 'Most Popular Brand', value: (state.popular_brand || 'None') + ' (' + Number(state.popular_brand_qty || 0).toLocaleString() + ' units)' },
        'peak_month': { emoji: '🔥', title: 'Peak Sales Month', value: '📅 ' + (state.peak_month || 'N/A') },
        'ryzen_qty': { emoji: '💻', title: 'Ryzen Units Sold', value: Number(state.total_ryzen_sold || 0).toLocaleString() + ' units' }
    };
}

function loadWidgetConfig() {
    initWidgetMetricsData();

    const saved = localStorage.getItem('trends_widgets_config');
    if (saved) {
        try {
            widgetsConfig = JSON.parse(saved);
        } catch (e) {
            widgetsConfig = [...DEFAULT_WIDGET_CONFIG];
        }
    } else {
        widgetsConfig = [...DEFAULT_WIDGET_CONFIG];
    }

    DEFAULT_WIDGET_CONFIG.forEach(def => {
        if (!widgetsConfig.some(w => w.id === def.id)) {
            widgetsConfig.push(def);
        }
    });

    const boardVisible = localStorage.getItem('trends_widgets_board_visible') === 'true';
    const board = document.getElementById('widget-board');
    const toggleBtn = document.getElementById('toggle-widgets-btn');
    const configBtn = document.getElementById('config-widgets-btn');

    if (board && toggleBtn && configBtn) {
        if (boardVisible) {
            board.style.display = 'grid';
            toggleBtn.textContent = '🙈 Hide Summary Cards';
            toggleBtn.classList.remove('dark');
            configBtn.style.display = 'inline-block';
        } else {
            board.style.display = 'none';
            toggleBtn.textContent = '📊 Show Summary Cards';
            toggleBtn.classList.add('dark');
            configBtn.style.display = 'none';
            const panel = document.getElementById('widgets-config-panel');
            if (panel) panel.style.display = 'none';
        }
    }
}

function saveWidgetConfig() {
    localStorage.setItem('trends_widgets_config', JSON.stringify(widgetsConfig));
}

function toggleWidgetBoard() {
    const board = document.getElementById('widget-board');
    const toggleBtn = document.getElementById('toggle-widgets-btn');
    const configBtn = document.getElementById('config-widgets-btn');
    if (!board || !toggleBtn || !configBtn) return;

    const isHidden = board.style.display === 'none';

    if (isHidden) {
        board.style.display = 'grid';
        toggleBtn.textContent = '🙈 Hide Summary Cards';
        toggleBtn.classList.remove('dark');
        configBtn.style.display = 'inline-block';
        localStorage.setItem('trends_widgets_board_visible', 'true');
    } else {
        board.style.display = 'none';
        toggleBtn.textContent = '📊 Show Summary Cards';
        toggleBtn.classList.add('dark');
        configBtn.style.display = 'none';
        const panel = document.getElementById('widgets-config-panel');
        if (panel) panel.style.display = 'none';
        localStorage.setItem('trends_widgets_board_visible', 'false');
    }
}

function toggleConfigPanel() {
    const panel = document.getElementById('widgets-config-panel');
    if (panel) {
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }
}

function renderWidgetToggles() {
    const container = document.getElementById('widget-toggles-container');
    if (!container) return;
    container.innerHTML = '';

    widgetsConfig.forEach(item => {
        if (item.type !== 'metric') return;
        const data = WIDGET_METRICS_DATA[item.id];
        if (!data) return;

        const label = document.createElement('label');
        label.style.cssText = 'display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; background: var(--bg-surface-2); padding: 8px 14px; border-radius: 20px; border: 1px solid var(--border-color);';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.style.cssText = 'width: 16px; height: 16px; margin: 0; cursor: pointer;';
        checkbox.checked = item.visible;
        checkbox.addEventListener('change', () => {
            item.visible = checkbox.checked;
            saveWidgetConfig();
            renderWidgetBoard();
        });

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(' ' + data.emoji + ' ' + data.title));
        container.appendChild(label);
    });
}

function renderWidgetBoard() {
    const board = document.getElementById('widget-board');
    if (!board) return;
    board.innerHTML = '';

    widgetsConfig.forEach(item => {
        if (!item.visible) return;

        const card = document.createElement('div');
        card.className = 'trend-card';
        card.setAttribute('draggable', 'true');
        card.setAttribute('data-id', item.id);
        card.style.cssText = 'position: relative; align-items: center; text-align: center; gap: 8px; cursor: grab; padding: 20px;';

        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragover', handleDragOver);
        card.addEventListener('dragenter', handleDragEnter);
        card.addEventListener('dragleave', handleDragLeave);
        card.addEventListener('drop', handleDrop);
        card.addEventListener('dragend', handleDragEnd);

        if (item.type !== 'metric') {
            const delBtn = document.createElement('button');
            delBtn.innerHTML = '✕';
            delBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; font-size: 1rem; font-weight: bold; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;';
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                removeWidget(item.id);
            });
            card.appendChild(delBtn);
        }

        if (item.type === 'metric') {
            const data = WIDGET_METRICS_DATA[item.id];
            if (!data) return;
            card.innerHTML += `
                <div style="font-size: 2rem;">${data.emoji}</div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${data.title}</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: ${item.id === 'avg_value' ? 'var(--accent-color)' : 'var(--text-main)'};">${data.value}</div>
            `;
        } else if (item.type === 'note') {
            const noteText = item.text || '';
            card.innerHTML += `
                <div style="font-size: 2rem;">📝</div>
                <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${item.title || 'Note'}</div>
                <textarea style="width: 100%; height: 80px; background: var(--bg-surface-2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); font-family: inherit; font-size: 0.85rem; padding: 8px; resize: none; outline: none; margin-top: 4px;"
                          placeholder="Type your notes here..."
                          oninput="updateNoteText('${item.id}', this.value)">${noteText}</textarea>
            `;
        } else if (item.type === 'custom') {
            if (item.editing) {
                const POPULAR_EMOJIS = ['⭐', '📦', '📝', '💵', '🤝', '👑', '🔥', '💻', '📈', '📉', '🎯', '🚀', '💡', '🛡️', '📅', '👥', '🔋', '🔌', '🖥️', '⌨️', '🖱️', '💾', '💿', '🖨️', '⚙️', '🔔', '❤️', '👍', '🏆', '🎉'];
                let emojiButtonsHtml = '';
                POPULAR_EMOJIS.forEach(emo => {
                    emojiButtonsHtml += `<button type="button" style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.15s;" onclick="selectPickerEmoji(event, '${item.id}', '${emo}')" onmouseover="this.style.background='var(--bg-surface-2)'" onmouseout="this.style.background='transparent'">${emo}</button>`;
                });

                card.innerHTML += `
                    <div style="display: flex; flex-direction: column; gap: 8px; width: 100%; text-align: left;">
                        <div style="display: flex; gap: 6px; position: relative;">
                            <div style="position: relative; display: inline-block;">
                                <input type="text" id="cust-emoji-${item.id}" value="${item.emoji || '⭐'}" placeholder="Emoji" style="width: 50px; text-align: center; height: 36px; padding: 0;" onclick="toggleEmojiPicker(event, '${item.id}')">
                                <div id="emoji-picker-${item.id}" style="display: none; position: absolute; top: calc(100% + 5px); left: 0; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: var(--border-radius-md); box-shadow: var(--shadow-lg); padding: 8px; width: 180px; z-index: 1000; grid-template-columns: repeat(5, 1fr); gap: 4px;">
                                    ${emojiButtonsHtml}
                                </div>
                            </div>
                            <input type="text" id="cust-title-${item.id}" value="${item.title || ''}" placeholder="Title" style="flex: 1; height: 36px; padding: 0 8px;">
                        </div>
                        <input type="text" id="cust-val-${item.id}" value="${item.value || ''}" placeholder="Value" style="width: 100%; height: 36px; padding: 0 8px;">
                        <button type="button" class="btn-main" onclick="saveCustomMetricCard('${item.id}')" style="height: 32px; padding: 0; font-size: 0.8rem; border-radius: 6px; box-shadow: none;">Save Card</button>
                    </div>
                `;
            } else {
                card.innerHTML += `
                    <div style="font-size: 2rem;">${item.emoji || '⭐'}</div>
                    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary);">${item.title || 'Custom Metric'}</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: var(--text-main);">${item.value || '0'}</div>
                    <button type="button" style="background: transparent; border: none; color: var(--accent-color); font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-top: 4px;" onclick="editCustomMetricCard('${item.id}')">✏️ Edit</button>
                `;
            }
        }

        board.appendChild(card);
    });

    if (board.children.length === 0) {
        board.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 30px; border: 1px dashed var(--border-color); border-radius: var(--border-radius-lg); color: var(--text-secondary); font-style: italic;">No summary cards are currently visible. Click "Customize Board" to add cards.</div>`;
    }
}

function toggleEmojiPicker(event, id) {
    event.stopPropagation();
    document.querySelectorAll('[id^="emoji-picker-"]').forEach(el => {
        if (el.id !== `emoji-picker-${id}`) el.style.display = 'none';
    });
    const picker = document.getElementById(`emoji-picker-${id}`);
    if (picker) {
        picker.style.display = picker.style.display === 'grid' ? 'none' : 'grid';
    }
}

function selectPickerEmoji(event, id, emoji) {
    event.stopPropagation();
    const input = document.getElementById(`cust-emoji-${id}`);
    if (input) input.value = emoji;
    const picker = document.getElementById(`emoji-picker-${id}`);
    if (picker) picker.style.display = 'none';
}

document.addEventListener('click', () => {
    document.querySelectorAll('[id^="emoji-picker-"]').forEach(el => {
        el.style.display = 'none';
    });
});

function updateNoteText(id, val) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.text = val;
        saveWidgetConfig();
    }
}

function addNewNoteCard() {
    const newId = 'note_' + Math.random().toString(36).substr(2, 9);
    widgetsConfig.push({
        id: newId,
        type: 'note',
        visible: true,
        title: 'Board Note',
        text: ''
    });
    saveWidgetConfig();
    renderWidgetBoard();
}

function addNewCustomMetricCard() {
    const newId = 'custom_' + Math.random().toString(36).substr(2, 9);
    widgetsConfig.push({
        id: newId,
        type: 'custom',
        visible: true,
        emoji: '⭐',
        title: 'New Metric',
        value: '0',
        editing: true
    });
    saveWidgetConfig();
    renderWidgetBoard();
}

function saveCustomMetricCard(id) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.emoji = document.getElementById(`cust-emoji-${id}`)?.value || '⭐';
        item.title = document.getElementById(`cust-title-${id}`)?.value || 'Custom Metric';
        item.value = document.getElementById(`cust-val-${id}`)?.value || '0';
        item.editing = false;
        saveWidgetConfig();
        renderWidgetBoard();
    }
}

function editCustomMetricCard(id) {
    const item = widgetsConfig.find(w => w.id === id);
    if (item) {
        item.editing = true;
        renderWidgetBoard();
    }
}

function removeWidget(id) {
    widgetsConfig = widgetsConfig.filter(w => w.id !== id);
    saveWidgetConfig();
    renderWidgetBoard();
}

function resetWidgetsToDefault() {
    if (confirm("Reset layout, custom metrics, and notes back to system defaults?")) {
        widgetsConfig = JSON.parse(JSON.stringify(DEFAULT_WIDGET_CONFIG));
        saveWidgetConfig();
        renderWidgetToggles();
        renderWidgetBoard();
    }
}

function handleDragStart(e) {
    dragSourceEl = this;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', this.getAttribute('data-id'));
    this.style.opacity = '0.4';
    this.style.border = '2px dashed var(--accent-color)';
}

function handleDragOver(e) {
    if (e.preventDefault) e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragEnter(e) {
    this.style.border = '2px dashed var(--accent-color)';
}

function handleDragLeave(e) {
    this.style.border = '1px solid var(--border-color)';
}

function handleDrop(e) {
    e.stopPropagation();
    const draggedId = e.dataTransfer.getData('text/plain');
    const targetId = this.getAttribute('data-id');

    if (draggedId && targetId && draggedId !== targetId) {
        const idxA = widgetsConfig.findIndex(w => w.id === draggedId);
        const idxB = widgetsConfig.findIndex(w => w.id === targetId);
        if (idxA > -1 && idxB > -1) {
            const temp = widgetsConfig[idxA];
            widgetsConfig[idxA] = widgetsConfig[idxB];
            widgetsConfig[idxB] = temp;
            saveWidgetConfig();
            renderWidgetBoard();
        }
    }
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '1';
    this.style.border = '1px solid var(--border-color)';
    const cols = document.querySelectorAll('#widget-board .trend-card');
    cols.forEach(col => col.style.border = '1px solid var(--border-color)');
}
