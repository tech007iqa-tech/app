# 🧩 Modular Technician Workbench System (`/tech`)
*IQA Metal Warehouse Systems | Engineering & Future AI Agent Guide*

---

## 🎯 Architecture & Purpose

The Technician Control Center (`/tech`) contains specialized hardware bench tooling designed to support high-speed laptop testing, barcode scanning, thermal printing, and parts management.

To keep the codebase maintainable and enable future AI agents and engineers to make targeted changes without wading through thousands of lines of monolithic code, bench enhancements are organized into **decoupled, zero-dependency ES6 modules** under [`tech/assets/js/modules/`](file:///c:/xampp/htdocs/app/tech/assets/js/modules/) coordinated by a centralized bootstrap orchestrator ([`tech/assets/js/tech_workbench.js`](file:///c:/xampp/htdocs/app/tech/assets/js/tech_workbench.js)).

---

## 📁 Directory Structure

```
📁 tech/
├── 📁 assets/
│   ├── 📁 js/
│   │   ├── 📄 tech_workbench.js            # Master orchestrator & DOM ready bootstrapper
│   │   └── 📁 modules/
│   │       ├── 📄 sound_fx.js              # Native Web Audio API synthesizer (no MP3 files)
│   │       ├── 📄 bench_hotkeys.js         # Keyboard shortcuts & modal navigation
│   │       ├── 📄 scanner_guard.js         # Barcode scanner autofocus & keystroke redirect
│   │       ├── 📄 shelf_chips.js           # Recent shelf locations & 1-touch chips
│   │       ├── 📄 clipboard_tools.js       # 1-click S/N and formatted specs copying
│   │       ├── 📄 custom_presets.js        # Pin custom presets for inbound pallets
│   │       ├── 📄 parts_picker.js          # Hardware parts consumed integration
│   │       └── 📄 export_csv.js            # Filtered CSV export with UTF-8 BOM (\uFEFF)
│   └── 📁 styles/
│       ├── 📄 logs.css                     # Core intake grid & table styling
│       └── 📄 qol_workbench.css            # Modular QoL styling (badges, chips, audio toggle)
├── 📁 api/
│   ├── 📄 add_log.php                      # Hardware log ingestion + parts deduction
│   └── 📄 get_inventory.php                # Parts catalog for parts_picker.js
├── 📁 pages/
│   └── 📄 logs.php                         # Master intake workbench view
└── 📁 DOCS/
    ├── 📄 AI_TECHNICAL_DEEP_DIVE.md        # Core module technical deep-dive
    └── 📄 MODULAR_BENCH_SYSTEM.md          # This documentation
```

---

## 🧭 Global Namespace: `window.TechWorkbench`

Every module registers itself cleanly under the global `window.TechWorkbench` object:

### 1. `TechWorkbench.Audio` ([sound_fx.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/sound_fx.js))
- **Purpose**: Generates real-time audio chimes directly in the browser via `window.AudioContext` without downloading or caching any audio files.
- **Methods**:
  - `playSuccess()`: Ascending two-tone chime ($587\text{Hz} \rightarrow 880\text{Hz}$) on successful save/print.
  - `playMatch()`: High chime ($1046\text{Hz}$) when a scanned barcode matches an existing master label.
  - `playError()`: Low double warning buzz ($220\text{Hz} \rightarrow 180\text{Hz}$) on validation errors or duplicates.
  - `playClick()`: Subtle tactile tick ($1200\text{Hz}$) on chip/button taps.
  - `toggleMute()`: Toggles mute state and saves to `localStorage` (`tech_sound_muted`).
  - `isMuted()`: Returns `true` if sound is currently muted.

---

### 2. `TechWorkbench.Hotkeys` ([bench_hotkeys.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/bench_hotkeys.js))
- **Purpose**: Touchless bench navigation so technicians with gloves or screwdrivers can operate the bench via keyboard shortcuts.
- **Keybindings**:
  - `Alt + N` / `F2`: Open New Hardware Intake dialog.
  - `Ctrl + Enter`: Save Hardware Log.
  - `Alt + P`: Print Thermal Label.
  - `Alt + 1` / `Alt + 2`: Toggle status between `Good` and `Bad`.
  - `Esc`: Close any open dialog/modal.
  - `Alt + /` / `F3`: Focus search input.
  - `Alt + M`: Toggle Audio Cues on/off.
  - `?`: Open keyboard shortcuts cheat-sheet modal.
- **Methods**:
  - `openHelpModal()`: Displays the keyboard shortcut cheat-sheet.
  - `closeHelpModal()`: Closes the cheat-sheet modal.

---

### 3. `TechWorkbench.ScannerGuard` ([scanner_guard.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/scanner_guard.js))
- **Purpose**: Hardware 2D/1D scanners emit keystrokes at rapid speeds ($<25$ms per char). If the technician clicks away from the input, this module intercepts the scan and redirects it into the serial number input so scans are never dropped.
- **Methods**:
  - `focusSerialInput()`: Automatically focuses `#intake_serial_number` or `#batch_sn_scanner`.
  - `getActiveSerialInput()`: Returns active serial input based on single vs. batch mode.

---

### 4. `TechWorkbench.ShelfChips` ([shelf_chips.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/shelf_chips.js))
- **Purpose**: Eliminates repetitive typing of warehouse shelf codes during pallet testing.
- **Methods**:
  - `recordLocation(loc)`: Saves shelf location to `localStorage` (`tech_recent_shelves`), capped at 6 items.
  - `applyToIntake(shelf)`: Sets `#intake_location` with 1 touch.
  - `applyToDelivery(shelf)`: Sets `#deliveryDestinationInput` with 1 touch.

---

### 5. `TechWorkbench.Clipboard` ([clipboard_tools.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/clipboard_tools.js))
- **Purpose**: Fast 1-click clipboard writing for serial numbers and standardized e-commerce listing specs.
- **Methods**:
  - `copySerial(sn)`: Writes raw S/N to clipboard and displays floating toast.
  - `copySpecs(log)`: Formats full device hardware specifications into a clean pipe-delimited string (e.g. `Dell Latitude 5400 | i5-8350U 1.70GHz (4 Cores) | 16GB RAM | 256GB SSD | Win 11 Pro | Grade A`) and writes to clipboard.
  - `showToast(html)`: Shows a temporary animated toast notification at the bottom of the screen.

---

### 6. `TechWorkbench.Presets` ([custom_presets.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/custom_presets.js))
- **Purpose**: Allows technicians to pin custom 1-touch presets for unusual incoming shipment pallets (e.g., a pallet of 30 Lenovo ThinkPads).
- **Methods**:
  - `pinCurrentSpecs()`: Captures filled specs into a new custom preset pill card.
  - `applyPreset(p)`: Populates all form fields from the custom preset with 1 click.
  - `removePreset(id)`: Deletes custom preset from `localStorage` (`tech_custom_presets`).

---

### 7. `TechWorkbench.PartsPicker` ([parts_picker.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/parts_picker.js))
- **Purpose**: Bridges hardware bench repairs directly with `parts_inventory` in `tech.db`.
- **Methods**:
  - `addFromSelect()`: Adds a component (e.g. `1x 8GB DDR4 RAM`, `1x 256GB SSD`) to the intake payload.
  - `removePart(id)`: Removes part from consumed list.
  - `syncChips()`: Updates hidden input `parts_consumed` (JSON) sent to `api/add_log.php`.
  - `reset()`: Clears consumed parts upon form reset.

---

### 8. `TechWorkbench.Exporter` ([export_csv.js](file:///c:/xampp/htdocs/app/tech/assets/js/modules/export_csv.js))
- **Purpose**: Client-side export of filtered log records matching current bench filters.
- **Invariant**: Prepends the mandatory UTF-8 Byte Order Mark (`\uFEFF`) as byte 0 for seamless Microsoft Excel compatibility (`GEMINI.md`).
- **Methods**:
  - `exportFilteredLogs()`: Fetches filtered records, builds CSV, and initiates browser download.

---

## 🛠️ How to Add a New Module (For Future Agents)

To introduce a new bench capability in the future:

1. **Create the Module File**:
   Create `tech/assets/js/modules/my_module.js`:
   ```javascript
   'use strict';
   window.TechWorkbench = window.TechWorkbench || {};
   (function(exports) {
       const MyModule = {
           init() {
               // Initialization logic here
           }
       };
       exports.MyModule = MyModule;
   })(window.TechWorkbench);
   ```

2. **Add Bootstrap Call**:
   In [`tech/assets/js/tech_workbench.js`](file:///c:/xampp/htdocs/app/tech/assets/js/tech_workbench.js):
   ```javascript
   if (window.TechWorkbench.MyModule) {
       window.TechWorkbench.MyModule.init();
   }
   ```

3. **Include Script Tag in Page**:
   In [`tech/pages/logs.php`](file:///c:/xampp/htdocs/app/tech/pages/logs.php):
   ```html
   <script src="../assets/js/modules/my_module.js"></script>
   ```

---

## 🔒 Architectural Guardrails Maintained

- **Strict Zero-Dependency**: Vanilla ES6, Vanilla CSS3, Vanilla PHP 8.1+.
- **Touch-First Accessibility**: Minimum $48\times 48$px touch targets for rugged monitors.
- **Mandatory Pacific Timezone**: All timestamp rendering respects `America/Los_Angeles`.
- **Prepared Statements**: All database operations use 100% prepared PDO statements.
- **Excel UTF-8 BOM**: All CSV streams prepend `\uFEFF`.
