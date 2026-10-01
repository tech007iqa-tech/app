# IQA Metal Inventory & Thermal Label Engine (Modular v2.5)

## 1. Project Overview
This application is a local-network warehouse hardware intake tracker and 2" × 1" thermal label engine. Built with a clean, modular philosophy: zero-bloat foundations, rich aesthetics, and intuitive workflows that unexperienced warehouse operators can use with zero friction.

At its core, the app provides:
1. **Physical Hardware Intake & Tracking:** Tracks laptops, desktops, and tech units in the warehouse with precise shelf/bin location tracking, cosmetic/condition grading, and deep technical diagnostics.
2. **Dual Thermal Printing Engine:**
   - **Universal Web Thermal Print (`print_label.php`):** Browser-native direct printing calibrated for continuous 2" × 1" thermal rolls (`@page { size: 2in 1in; margin: 0; }`) with vector SVG Code 128 barcodes. Works on any browser/OS with zero client software setup.
   - **Windows ODT Surgery:** Flat-XML PowerShell template injection for generating LibreOffice `.odt` documents on Windows workstations.
3. **100% Standalone Autonomy:** Operates independently on a dedicated workstation (`LABELS_STANDALONE = true`) without crashing on parent portal dependencies, while seamlessly bridging with the main orders portal when present.

---

## 2. The Tech Stack (Strict Rules)
* **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom design system tokens, zero-FOUC Dark/Light mode), and Vanilla JavaScript (ES6+).
* **Backend:** PHP 8+ handling modular endpoints in `/api/`.
* **Database:** SQLite3 using PDO with WAL mode and schema self-healing (`includes/schema_guard.php`).
* **Zero Bloat:** **NO** `node_modules`, **NO** Tailwind CSS or Bootstrap, **NO** PHP Frameworks, and **NO** Composer packages.

---

## 3. Core Architecture
The system uses SQLite databases:
1. `labels.sqlite`: Master inventory of physical hardware profiles with technical fingerprinting.
2. `audit.sqlite`: Audit trail tracking all creation, update, and bulk modification events.

*(See [`DOCS/ARCHITECTURE.md`](file:///c:/xampp/htdocs/app/labels/DOCS/ARCHITECTURE.md) for database schemas and SQL patterns).*

---

## 4. UI Philosophy & Features
*(See [`DOCS/DESIGN_SYSTEM.md`](file:///c:/xampp/htdocs/app/labels/DOCS/DESIGN_SYSTEM.md) for CSS tokens and component rules).*
* **Zero-FOUC Immediate Theme Switcher:** Pre-paint evaluation in `header.php` syncs `iqa_theme` and `iqa_labels_theme`. Instant dual-icon toggle (🌙/☀️) without page reloads.
* **Full Technical Sheet Default Mode:** Deep diagnostic sheet (GPU, Screen Res, Battery Health, OS, Cosmetic Grade, Notes) is expanded by default for thorough intake.
* **One-Touch Quick Presets:** 1-click profiles ("Office Fleet", "Executive Pro", "Budget Student", "MacBook M1", "Salvage / For Parts") autofill 90% of specs in 5 seconds.
* **Dynamic Suffix Validation:**
  - **Core Count:** Strict numeric validation. Suffix badge (`Core`/`Cores`) displays dynamically while typing.
  - **Clock Frequency:** Strict float validation (digits + single dot). Suffix badge (`GHz`) displays dynamically while typing.
* **Missing Component Selectors:** Dedicated `.pill-none` red warning pills for `No RAM` and `No SSD` / `No Drive`, pre-selected in the For Parts preset.
* **Action Toolbar:**
  - `🖨️ Print Thermal Label`: Saves record and launches browser-native 2" × 1" thermal roll print.
  - `📄 Save & Windows ODT`: Saves record and generates Windows LibreOffice ODT document.
  - `✨ Start Fresh`: Clears fields, resets live mockup, maintains Full Technical Sheet mode, and smoothly scrolls viewport to `Hardware Identity & Model`.
  - `💾 Save`: Saves hardware profile to inventory database without launching the print dialog.
* **Real-time 2" × 1" Thermal Feedback:** Live on-screen physical sticker mockup (Label A: Brand & Barcode, Label B: Tech Specs) updates on every keystroke.

---

## Agent Instructions (Read Before Coding)
If you are an AI Agent working in this repository:
1. **Read `DOCS/ARCHITECTURE.md`** for schemas and API pipelines.
2. **Read `DOCS/SITEMAP.md`** for the folder and component hierarchy.
3. **Read `DOCS/DESIGN_SYSTEM.md`** for CSS tokens and UI standards.
4. **Do not install npm or composer packages.** Everything is vanilla.
5. **Use `HW_FIELDS` mapping** from `includes/hardware_mapping.php` (PHP) and `assets/js/hardware_mapping.js` (JS) — never hardcode database field names.
