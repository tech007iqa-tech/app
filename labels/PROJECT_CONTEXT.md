# 🤖 AI Agent Project Context
**File:** `PROJECT_CONTEXT.md`
**Purpose:** Read this single file to understand the entire application without scanning the codebase.

---

## 🏗️ 1. Project Overview & Tech Stack
**App:** IQA Metal Inventory & Label Printer.
**Goal:** Track physical hardware in a warehouse and print `.odt` labels for individual units.
**Tech Stack:**
- **Frontend:** Vanilla HTML5, Vanilla CSS3, Vanilla JS.
- **Backend:** PHP 8+ handling API endpoints in `/api/`.
- **Database:** SQLite3 using PDO (`includes/db.php`). Two `.sqlite` files (labels, audit).
- **File Generation:** Native PowerShell "Structural Surgery" injecting content into Master Templates.
- **Printing:**
  - **Labels (.odt):** High-fidelity thermal labels generated via PowerShell "Structural Surgery".
  - **Dual-Label approach:**
    - **Label A**: Branding (Brand, Model, Series, CPU).
    - **Label B**: Technical Specs (Consolidated 3-line layout: CPU, RAM/Storage/Battery, and GPU/OS/BIOS).
  - **Windows Launch:** Direct file opening via `api/open_windows_file.php`.

---

## 🗄️ 2. Database Architecture

### A. `labels.sqlite` — Hardware Inventory
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Hardware ID |
| `brand` | TEXT NOT NULL | Manufacturer |
| `model` | TEXT NOT NULL | Model name |
| `series` | TEXT | Series details |
| `cpu_gen` | TEXT | Processor generation |
| `cpu_specs` | TEXT | Exact processor model |
| `status` | TEXT | Default `'In Warehouse'` |
| `description` | TEXT | `'Untested'`, `'Refurbished'`, `'For Parts'` |
| `warehouse_location` | TEXT | Physical location |
| `serial_number` | TEXT | Device S/N |
| `created_at` | DATETIME | Intake timestamp |

### B. `audit.sqlite` — Change Tracking
| Column | Type | Notes |
|---|---|---|
| `id` | INTEGER PK | Log entry ID |
| `entity_type` | TEXT | `'Label'` |
| `entity_id` | TEXT | ID of affected record |
| `action` | TEXT | `'CREATED'`, `'UPDATED'`, `'DELETED'` |
| `summary` | TEXT | Human-readable description |
| `old_value` | TEXT (JSON) | State before change |
| `new_value` | TEXT (JSON) | State after change |

---

## 🗺️ 3. Folder & File Sitemap

```
/app/
├── /assets/js/
│   ├── forms.js            ← Intelligent CPU Intake logic
│   ├── actions.js          ← Global Technical Action Bridge (Flash Launch)
│   ├── labels.js           ← Inventory management logic
│   └── print_engine.js     ← Global Print Modal & Quantity Logic
├── /includes/
│   ├── hardware_form.php   ← Shared Technical Form component
│   ├── hardware_mapping.php ← Field name constants (HW_FIELDS)
│   ├── schema_guard.php    ← Self-healing database logic
│   ├── audit.php           ← Audit trail logging
│   └── db.php              ← PDO Master Connection
├── /db/                    ← SQLite3 Databases
├── /templates/             ← ODT Master Templates
│   └── /scripts/           ← PowerShell generation logic
├── /exports/labels/        ← Generated ODT documents
│
├── /api/                   ← JSON endpoints
│   ├── add_label.php       ← POST: Insert + generate label
│   ├── edit_label.php      ← POST: Update hardware record
│   ├── delete_label.php    ← POST: Remove hardware record
│   ├── get_labels.php      ← GET: Search/Filter inventory
│   ├── search_item.php     ← GET: Quick Locate lookup
│   ├── reprint_label.php   ← POST: Regenerate/Open ODT
│   └── check_file_exists.php ← GET: Verify file exists
│
├── index.php               ← Landing (Search & Stats)
├── labels.php              ← Warehouse Tracker (Print, Open, Edit)
├── new_label.php           ← Rapid Intake Form
└── hardware_view.php       ← Technical Sheet Editor
```

---

## 🎨 4. Design System / UI Vibe
- **Theme:** Robust Light Mode (High Contrast). Background `#fdfdfd`, panels `#ffffff`.
- **Accent Color:** Safety Green (`#8cc63f`).
- **Mobile First:** iPhone/Safari optimized via CSS Checkbox Hack (sidebar) and 48px touch targets.
- **Interactivity:** All forms use `fetch()` APIs; no full-page reloads.
- **Label Strategy:** Dual-label system (Branding + Consolidated Technical Specs).
- **Automation:** Native PowerShell engine for precise .odt generation on Windows hosts.
