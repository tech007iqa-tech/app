# Marketing Hub UI Standards & Component Invariants

All new or modified modules in `/marketing` must adhere to the design standards and component structures established in [`marketing/modules/model_templates/index.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/modules/model_templates/index.php) ("Existing Templates").

## 1. Color Palette & Visual Theme
- **Theme**: Pure Warehouse Light Standard (never introduce standalone dark cards into marketing views).
  - Page Background: `var(--bg-page)` (`#fdfdfd`)
  - Panels & Cards: `var(--bg-panel)` (`#ffffff`)
  - Borders: `var(--border-color)` (`#e2e8f0`)
  - Primary Text: `var(--text-main)` (`#0f172a`, deep legible slate)
  - Secondary/Muted: `var(--text-secondary)` (`#64748b`) & `var(--text-dim)` (`#475569`)
  - Primary Brand Accent: `var(--accent-primary)` (`#007268`, Aqua Teal)
  - Secondary Accents: `var(--accent-secondary)` (`#7aff6b6e`) & `var(--accent-tertiary)` (`#f1ffd765`, Mint Wash)

## 2. Card & Grid Isolation Rules
- **Grid Column Reset**: Generic `.card` has `grid-column: span 3;` in `style.css`. When placing `.card` elements inside custom multi-column grids (such as 2-column split views or template grids), always explicitly specify `grid-column: auto !important;` or exact column indices to prevent layout breakage.
- **Sticky Sidebars & Hover Reset**: Generic `.card:hover` applies `transform: translateY(-4px);`. On any sticky sidebar or full-height canvas, ALWAYS disable hover transforms (`transform: none !important;`) to prevent scrolling jitter and sticky displacement.

## 3. Template & Component Patterns (from model_templates)
- **Section Headers**: Use `<header class="page-header">` for top titles, and standard `<section class="card"><h2>Section Title</h2>` with uppercase sub-headers.
- **Action Buttons**:
  - Primary Submission: `.btn-action` (`background: var(--accent-primary); color: white;`)
  - Secondary Action: `.btn-small` (neutral bordered button)
  - Highlighted Action: `.btn-small.btn-highlight` (`background: var(--accent-tertiary); color: var(--accent-primary); border-color: var(--accent-secondary);`)
  - Destructive Actions: Red-accented `.btn-small` wrapped in a POST form with `onsubmit="return confirmAction('...', this);"` and `UI::csrf_field()`.

## 4. Script Scoping & Safety
- Never declare variables or functions with top-level `const` or `let` in global scripts (`app.js`). Always encapsulate within an IIFE (`(function() { 'use strict'; ... })();`) and attach public APIs explicitly to `window` (e.g. `window.notify`, `window.confirmAction`) to prevent duplicate identifier collisions.
