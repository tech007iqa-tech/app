/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TECHNICIAN WORKBENCH — MASTER BOOTSTRAP ORCHESTRATOR
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * This file serves as the unified entry point for all bench QoL modules.
 * Every subsystem is accessible under window.TechWorkbench:
 *   - TechWorkbench.Audio        -> sound_fx.js
 *   - TechWorkbench.Hotkeys      -> bench_hotkeys.js
 *   - TechWorkbench.ScannerGuard  -> scanner_guard.js
 *   - TechWorkbench.ShelfChips   -> shelf_chips.js
 *   - TechWorkbench.Clipboard    -> clipboard_tools.js
 *   - TechWorkbench.Presets      -> custom_presets.js
 *   - TechWorkbench.PartsPicker  -> parts_picker.js
 *   - TechWorkbench.Exporter     -> export_csv.js
 * 
 * To add a new module in the future:
 * 1. Create your module in tech/assets/js/modules/my_module.js
 * 2. Register it on window.TechWorkbench.MyModule
 * 3. Include the script tag in tech/pages/logs.php
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Audio Synthesizer & Sound Toggle Buttons
    if (window.TechWorkbench.Audio) {
        window.TechWorkbench.Audio.updateToggleButtons();
    }

    // 2. Initialize Warehouse Bench Keyboard Shortcuts
    if (window.TechWorkbench.Hotkeys) {
        window.TechWorkbench.Hotkeys.init();
    }

    // 3. Initialize Barcode Scanner Auto-Focus Guard
    if (window.TechWorkbench.ScannerGuard) {
        window.TechWorkbench.ScannerGuard.init();
    }

    // 4. Initialize Shelf Location Quick-Chips
    if (window.TechWorkbench.ShelfChips) {
        window.TechWorkbench.ShelfChips.init();
    }

    // 5. Initialize Clipboard Tools & Toast
    if (window.TechWorkbench.Clipboard) {
        window.TechWorkbench.Clipboard.init();
    }

    // 6. Initialize Custom Pallet Presets
    if (window.TechWorkbench.Presets) {
        window.TechWorkbench.Presets.init();
    }

    // 7. Initialize Parts Consumed Picker
    if (window.TechWorkbench.PartsPicker) {
        window.TechWorkbench.PartsPicker.init();
    }
});
