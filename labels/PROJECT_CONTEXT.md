# 🤖 AI Agent Project Context
**File:** `PROJECT_CONTEXT.md`
**Purpose:** Read this single file to understand the entire application without scanning the codebase.

---

## 🏗️ 1. Project Overview & Tech Stack
**App:** IQA Metal Inventory & Thermal Label Engine (Modular v2.5).
**Goal:** Track physical hardware in a warehouse and print 2" × 1" thermal labels for individual units.
**Key Architecture:** Can run **100% STANDALONE** on any workstation without external dependencies, while seamlessly integrating with the main warehouse portal when present.
**Tech Stack:**
- **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom design system with automatic Dark/Light mode), Vanilla JS.
- **Backend:** PHP 8+ handling API endpoints in `/api/`.
- **Database:** SQLite3 using PDO (`includes/db.php`). Self-contained fallback in `/db/` with WAL mode and schema self-healing.
- **Dual Printing Engine:**
  1. **Direct Web Thermal (Universal):** Native browser print (`print_label.php`) calibrated with `@page { size: 2in 1in; margin: 0; }` and pure vector SVG barcodes. Works on any OS/browser with zero client software setup.
  2. **ODT Generation (Windows Workstation):** High-fidelity thermal labels generated via Flat XML and optional native PowerShell injection.

---

## 🗄️ 2. Database Architecture

### A. `labels.sqlite` — Hardware Inventory
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Hardware ID |
| `brand` | TEXT NOT NULL | Manufacturer (Dell, HP, Lenovo, Apple, etc.) |
| `model` | TEXT NOT NULL | Model name |
| `series` | TEXT | Series details (e.g. 5400, 840 G6) |
| `cpu_gen` | TEXT | Processor generation (e.g. '8th Gen') |
| `cpu_specs` | TEXT | Exact processor model (e.g. 'i5-8350U') |
| `status` | TEXT | Default `'In Warehouse'` |
| `description` | TEXT | `'Untested'`, `'Refurbished'`, `'For Parts'` |
| `warehouse_location` | TEXT | Physical shelf/bin location (e.g. 'Shelf A-1-2') |
| `serial_number` | TEXT | Device S/N |
| `created_at` | DATETIME | Intake timestamp |

### B. `audit.sqlite` — Change Tracking
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Log entry ID |
| `entity_type` | TEXT | `'Label'` |
| `entity_id` | TEXT | ID of affected record |
| `action` | TEXT | `'CREATED'`, `'UPDATED'`, `'DELETED'`, `'BULK_UPDATE'` |
| `summary` | TEXT | Human-readable description |
| `old_value` | TEXT (JSON) | State before change |
| `new_value` | TEXT (JSON) | State after change |

---

## 🗺️ 3. Folder & File Sitemap

```
/labels/
├── /assets/
│   ├── /css/
│   │   ├── style.css             ← Master design system (Tokens, Dark/Light mode, Modals, Toasts)
│   │   └── dashboard.css         ← Hero panel, KPI counters, and quick locator styling
│   └── /js/
│       ├── inventory_catalog.js  ← Built-in standalone hardware database & 1-click presets
│       ├── forms.js              ← Intelligent CPU intake, pill selectors, and live 2"x1" thermal preview
│       ├── actions.js            ← Toast notification system, quick view modal, and direct thermal bridge
│       ├── labels.js             ← Inventory table controller, live debounced filter, inline edit, CSV export
│       ├── hardware_mapping.js   ← Field name constants (HW_FIELDS)
│       └── print_engine.js       ← Print configuration modal (Web Thermal vs Windows ODT)
│
├── /includes/
│   ├── config.php                ← Modular configuration, standalone mode detection & fallback classes
│   ├── auth.php                  ← Modular auth guard (allows standalone workstation access)
│   ├── db.php                    ← PDO connection with WAL optimizations and standalone fallback
│   ├── header.php                ← Modern sticky header, drawer navigation, Google Fonts, zero-FOUC theme toggle
│   ├── footer.php                ← Layout closure & DOM cleanup
│   ├── functions.php             ← Formatting, sanitization, and JSON responses
│   ├── hardware_form.php         ← Unified form component with one-touch presets, brand/spec pills, and dynamic suffixes
│   ├── hardware_mapping.php      ← HW_FIELDS constant array
│   └── schema_guard.php          ← Self-healing schema engine
│
├── /api/
│   ├── add_label.php             ← POST: Insert hardware + return ID
│   ├── edit_label.php            ← POST: Update hardware record
│   ├── delete_label.php          ← POST: Remove hardware record
│   ├── get_labels.php            ← GET: Search/Filter warehouse records
│   ├── search_item.php           ← GET: Quick Locate lookup
│   ├── stats.php                 ← GET: Real-time inventory KPI counters
│   ├── bulk_update.php           ← POST: Bulk status or location update
│   ├── reprint_label.php         ← POST: Generate/download ODT
│   └── open_windows_file.php     ← POST: Launch file in Windows app
│
├── index.php                     ← Dashboard (KPI counts, action tiles, scanner locator, recent shelf)
├── labels.php                    ← Warehouse Inventory Tracker (Search, filter pills, CSV export, inline edit)
├── new_label.php                 ← Rapid Intake (One-touch presets, live 2"x1" sticker preview, batch intake)
├── hardware_view.php             ← Detailed Hardware Profile & Diagnostic Sheet
└── print_label.php               ← Universal 2" x 1" thermal label direct browser print engine
```

---

## 🎨 4. Design System & Feature Guidelines
- **Modular & Standalone:** Operates autonomously anywhere or integrated in the parent portal.
- **Immediate Zero-FOUC Theme Switcher:** Pre-paint execution in `header.php` synchronizes `iqa_theme` and `iqa_labels_theme` with instant CSS icon flipping (🌙/☀️) without requiring page reloads.
- **Intake Mode Architecture:** Defaults to **Full Technical Sheet** with deep diagnostic fields (GPU, Screen Res, Battery Status/Health, OS, Cosmetic Grade, Notes) expanded for comprehensive intake.
- **Action Toolbar (4 Core Actions):**
  1. `🖨️ Print Thermal Label`: Persists record and launches universal 2" × 1" thermal print roll.
  2. `📄 Save & Windows ODT`: Persists record and triggers Windows LibreOffice ODT generation.
  3. `✨ Start Fresh`: Clears fields, resets live thermal mockup, maintains Full Technical Sheet mode, and smoothly scrolls viewport to `Hardware Identity & Model`.
  4. `💾 Save`: Direct database intake saving without opening the thermal print dialog.
- **Dynamic Input Suffix Badges & Validation:**
  - **Core Count:** Strict numeric validation (letters/symbols blocked). Dynamic suffix (`Core` for 1, `Cores` for ≥2) appears only when a value is typed.
  - **Clock Frequency:** Strict float validation (digits + single dot). Dynamic suffix (`GHz`) appears only when a value is typed.
- **Component Availability Options (`No RAM` & `No SSD`):**
  - Dedicated `.pill-none` red-highlighted pills for `No RAM` and `No SSD` / `No Drive`.
  - Automatically pre-selected in the `🛠️ For Parts` salvage preset.
- **Physical Thermal Feedback:** Dual 2" × 1" live sticker preview (Label A: Brand & Barcode, Label B: Tech Specs) updates live before printing.
- **Direct Thermal Printing:** Web-native `@page { size: 2in 1in; margin: 0; }` printing requires zero host software.
- **High Aesthetic Standard:** Modern typography (`Plus Jakarta Sans` & `Inter`), curated emerald & sapphire palette, glassmorphism, responsive drawer, smooth micro-animations, and full Dark Mode support.
