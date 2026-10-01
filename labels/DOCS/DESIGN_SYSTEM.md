# Design System & UI Guidelines

## 1. Overview
The IQA Metal Label & Logistics Engine avoids heavy third-party CSS frameworks (Tailwind, Bootstrap) in favor of a bespoke, ultra-responsive **Modular Design System** defined in [`assets/css/style.css`](file:///c:/xampp/htdocs/app/labels/assets/css/style.css).

The UI emphasizes:
- **Zero-FOUC Dark/Light Modes**: Evaluated before initial paint to prevent white flash.
- **Physical Thermal Alignment**: Realistic on-screen 2" × 1" sticker mockups.
- **Tactile Feedback & Micro-animations**: Hardware buttons, smooth elevation, pill toggles, and live suffix badges.
- **Speed & Operator Ergonomics**: Large tap targets (≥44px), sticky bottom action bars, and keyboard-efficient input validation.

---

## 2. CSS Design Tokens

### Core Variables (`:root` — Light Mode)
```css
:root {
    /* Color System */
    --bg-page: #f8fafc;
    --bg-panel: #ffffff;
    --bg-panel-elevated: #ffffff;
    --bg-surface-2: #f1f5f9;
    --bg-surface-3: #e2e8f0;

    --text-main: #0f172a;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;

    /* Curated Accent Palette */
    --accent-color: #10b981;          /* Safety Emerald */
    --accent-hover: #059669;
    --accent-soft: rgba(16, 185, 129, 0.12);
    --accent-border: rgba(16, 185, 129, 0.3);

    --brand-blue: #3b82f6;            /* Electric Sapphire */
    --brand-blue-hover: #2563eb;
    --brand-blue-soft: rgba(59, 130, 246, 0.1);

    --color-warning: #f59e0b;
    --color-warning-soft: rgba(245, 158, 11, 0.12);
    --color-danger: #ef4444;
    --color-danger-soft: rgba(239, 68, 68, 0.12);
    --color-success: #10b981;

    --border-color: #e2e8f0;
    --border-color-subtle: #f1f5f9;

    /* Dimensions & Spacing */
    --header-height: 68px;
    --sidebar-width: 270px;
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 18px;
    --border-radius-full: 9999px;

    /* Typography */
    --font-heading: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --font-main: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --font-mono: 'JetBrains Mono', SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
```

### Dark Mode Palette (`[data-theme="dark"]`)
```css
[data-theme="dark"] {
    --bg-page: #0b0f19;
    --bg-panel: #131b2e;
    --bg-panel-elevated: #1a243d;
    --bg-surface-2: #1e293b;
    --bg-surface-3: #334155;

    --text-main: #f8fafc;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;

    --border-color: #1e293b;
    --border-color-subtle: #172136;

    --accent-color: #10b981;
    --accent-hover: #34d399;
    --accent-soft: rgba(16, 185, 129, 0.2);

    --shadow-card: 0 4px 14px 0 rgba(0, 0, 0, 0.4);
    --shadow-hover: 0 14px 32px 0 rgba(0, 0, 0, 0.6);
    --shadow-modal: 0 25px 60px 0 rgba(0, 0, 0, 0.8);
}
```

---

## 3. Theme Controller Architecture (Zero FOUC)
Theme selection is handled in `<head>` of [`includes/header.php`](file:///c:/xampp/htdocs/app/labels/includes/header.php) before DOM rendering:
```javascript
(function() {
    const saved = localStorage.getItem('iqa_theme') || localStorage.getItem('iqa_labels_theme') || 
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', saved);
})();

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('iqa_theme', next);
    localStorage.setItem('iqa_labels_theme', next);
    window.dispatchEvent(new CustomEvent('themechanged', { detail: { theme: next } }));
}
```
- **Icon Switching**: Controlled purely via CSS (`.theme-icon-sun`, `.theme-icon-moon`). Shows 🌙 in light mode and ☀️ in dark mode instantly on click with zero dual-listener collisions or page reloads.

---

## 4. Component Patterns

### A. The Action Toolbar (Sticky Intake Controls)
Located in [`new_label.php`](file:///c:/xampp/htdocs/app/labels/new_label.php):
```html
<div class="intake-action-bar">
    <button type="submit" class="btn btn-success btn-large" id="btnSubmitThermalPrint" data-action="print">
        <span>🖨️ Print Thermal Label</span>
    </button>
    <button type="submit" class="btn btn-dark btn-large" id="btnSubmitODT" data-action="odt">
        <span>📄 Save & Windows ODT</span>
    </button>
    <button type="button" class="btn btn-secondary btn-large" id="btnResetForm">
        <span>✨ Start Fresh</span>
    </button>
    <button type="submit" class="btn btn-brand btn-large" id="btnSubmitSaveOnly" data-action="save">
        <span>💾 Save</span>
    </button>
</div>
```
- **`btnSubmitThermalPrint`**: Submits data via AJAX, retrieves `newId`, and launches 2" × 1" thermal roll print preview.
- **`btnSubmitODT`**: Submits data via AJAX and triggers Windows LibreOffice ODT generation.
- **`btnResetForm`**: Resets form inputs/pills, resets the live sticker mockup, keeps Full Technical Sheet active, and **smoothly scrolls the viewport to `Hardware Identity & Model`** (`scroll-margin-top: 85px`).
- **`btnSubmitSaveOnly`**: Persists hardware item directly into the database with a toast notification without opening any print popups.

### B. Dynamic Input Suffix Wrapper
Used for **Core Count** and **Clock Frequency** to enforce clean operator input while preserving label formatting:
```html
<div class="input-suffix-wrapper">
    <input type="text" id="cpu_cores_display" inputmode="numeric" placeholder="e.g. 4" autocomplete="off">
    <span id="cpu_cores_suffix" class="input-suffix-badge" style="display:none;">Cores</span>
    <input type="hidden" name="cpu_cores" id="cpu_cores">
</div>
```
- **Validation**:
  - `Core Count`: Blocks non-digits. When `1`, displays badge `Core`. When `≥2`, displays badge `Cores`. Suffix badge only appears when a value is typed.
  - `Clock Frequency`: Blocks letters and multiple dots (`[0-9.]`). Displays badge `GHz` only when a value is typed.
- **Storage**: Hidden inputs automatically sync with the formatted strings (e.g., `4 Cores`, `2.40 GHz`) for direct database and thermal label consistency.

### C. Spec & Brand Pill Selectors
```html
<!-- Standard Spec Pill -->
<button type="button" class="spec-pill" onclick="selectSpecPill('ram', '16 GB')">16 GB</button>

<!-- Missing Hardware Warning Pill (.pill-none) -->
<button type="button" class="spec-pill pill-none" onclick="selectSpecPill('ram', 'No RAM')">No RAM</button>
<button type="button" class="spec-pill pill-none" onclick="selectSpecPill('storage', 'No SSD')">No SSD</button>
```
- Standard pills highlight in emerald (`--accent-color`).
- Missing component pills (`No RAM`, `No SSD`, `No Drive`) highlight in danger coral (`--color-danger`) when selected to immediately alert operators during intake.

---

## 5. Live Physical Feedback (2" × 1" Thermal Mockup)
The right-hand column of `new_label.php` renders high-fidelity SVG/HTML sticker representations:
- **Sticker A (Branding & Asset Tag)**: Top line brand/model, series, CPU detail (`i5-8350U (4 Cores @ 2.40 GHz)`), vector SVG Code 128 barcode, serial number, item ID, location, and condition.
- **Sticker B (Hardware Specifications)**: Grouped 3-line dense diagnostic sheet (CPU, RAM, SSD, Battery, GPU, OS, BIOS lock state, and location tag).
- **Physical Sync**: Every keystroke updates both stickers instantly via `updateLivePreview()`.
