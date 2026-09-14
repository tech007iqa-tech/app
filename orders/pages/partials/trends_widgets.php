<?php
/**
 * Trends Metrics & Summary Cards Widget Board Partial
 * Provides the interactive customization panel and container for drag-and-drop KPI cards.
 */
?>
<!-- Widgets Board Controller Bar -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
    <button type="button" class="btn-main dark" id="toggle-widgets-btn" onclick="toggleWidgetBoard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; box-shadow: none;">📊 Show Summary Cards</button>
    <button type="button" class="btn-main" id="config-widgets-btn" onclick="toggleConfigPanel()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; display: none; background: var(--bg-surface-2); color: var(--text-main) !important; border: 1px solid var(--border-color); box-shadow: none;">⚙️ Customize Board</button>
</div>

<!-- Widget Configurations Panel -->
<div id="widgets-config-panel" style="display: none; background: var(--bg-panel); border: 1px solid var(--border-color); padding: 20px; border-radius: var(--border-radius-lg); margin-bottom: 25px;">
    <h3 style="margin-top: 0; font-size: 1rem; margin-bottom: 12px; font-weight: 800;">Toggle Metrics Visibility</h3>
    <div id="widget-toggles-container" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
        <!-- Populated dynamically by JS -->
    </div>
    <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 15px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn-main" onclick="addNewNoteCard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #8b5cf6; box-shadow: none;">+ Add Note Card</button>
        <button type="button" class="btn-main" onclick="addNewCustomMetricCard()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #3b82f6; box-shadow: none;">+ Add Custom Metric</button>
        <button type="button" class="btn-main dark" onclick="resetWidgetsToDefault()" style="padding: 8px 16px; font-size: 0.85rem; height: auto; border-radius: 20px; background: #ef4444; box-shadow: none;">Reset Board</button>
    </div>
</div>

<!-- Overview Stats Grid (Dynamic Widget Board) -->
<div id="widget-board" class="trends-grid" style="display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); margin-bottom: 25px;">
    <!-- Populated dynamically by JS -->
</div>
