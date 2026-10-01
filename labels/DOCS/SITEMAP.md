# Sitemap & Directory Structure

## 1. Directory Structure
```text
/labels/
│
├── /assets/                # Static frontend assets
│   ├── /css/
│   │   ├── style.css       # Master stylesheet (Tokens, Dark/Light modes, Pills, Suffixes)
│   │   └── dashboard.css   # Hero stats, KPI cards, and scanner locator
│   └── /js/
│       ├── inventory_catalog.js # Standalone hardware database & 1-click presets
│       ├── forms.js        # Form intake, dynamic suffixes, pill controls, live mockup
│       ├── labels.js       # CRUD, filtering, inline edit, CSV export for warehouse tracking
│       ├── actions.js      # Global Technical Action Bridge (Toasts, modals, print bridge)
│       ├── hardware_mapping.js  # Field name constants (mirrors PHP HW_FIELDS)
│       └── print_engine.js # Quantity & Layout Logic for Printer Direct
│
├── /db/                    # SQLite Database Files
│   ├── labels.sqlite       # Items & Inventory (WAL mode, auto-healing)
│   └── audit.sqlite        # System audit trail
│
├── /templates/             # Master files for PowerShell Injection
│   ├── label_template.odt  # Master hardware label
│   └── /scripts/
│       └── generate_odt.ps1  # Label document generator
│
├── /includes/              # Reusable PHP backend components
│   ├── config.php          # Modular configuration & standalone fallbacks
│   ├── auth.php            # Standalone workstation authentication guard
│   ├── db.php              # PDO Shared Connections with WAL mode
│   ├── header.php          # Sticky top bar, navigation, zero-FOUC theme controller
│   ├── footer.php          # Layout closing tags & DOM cleanup
│   ├── functions.php       # Formatting, sanitization, and JSON helpers
│   ├── hardware_form.php   # Unified form component (Full Technical Sheet default)
│   ├── hardware_mapping.php # Field name constants (HW_FIELDS)
│   ├── schema_guard.php    # Self-Healing Schema Logic
│   └── audit.php           # Audit trail logging function
│
├── /api/                   # API Endpoints (All return JSON)
│   ├── add_label.php       # POST: Insert new hardware + return ID
│   ├── edit_label.php      # POST: Update hardware record
│   ├── delete_label.php    # POST: Remove hardware record
│   ├── get_labels.php      # GET: Search/Filter warehouse
│   ├── search_item.php     # GET: Quick Locate lookup
│   ├── reprint_label.php   # POST: Regenerate/Open ODT
│   ├── check_file_exists.php # GET: Verify ODT exists on disk
│   └── open_windows_file.php # POST: Launch file in Windows app
│
├── /exports/               # Generated .odt files
│   └── /labels/            # Individual printer files
│
├── index.php               # Dashboard (KPI counters, action tiles, scanner locator, recent shelf)
├── labels.php              # Warehouse Table View (Main inventory, search, CSV export)
├── new_label.php           # Rapid Intake (One-touch presets, 4-action toolbar, live 2"x1" mockup)
├── hardware_view.php       # Technical Sheet Editor
├── print_label.php         # Universal 2" x 1" thermal label direct browser print engine
└── 404.php                 # Error page
```

## 2. UI View Hierarchy
- **Landing (`index.php`):** Quick Locate scanner + warehouse stat counter + primary action tiles.
- **Inventory (`labels.php`):** Filterable table with **Inline Editing**, **Print**, **Open**, and **Delete**.
- **New Label (`new_label.php`):** Hardware intake form with Full Technical Sheet default mode, 1-touch presets, dynamic integer/float suffixes (`Core`/`Cores`, `GHz`), `No RAM`/`No SSD` pills, and 4-action toolbar (`Print Thermal Label`, `Save & Windows ODT`, `Start Fresh`, `Save`).
- **Hardware View (`hardware_view.php`):** Deep technical profile editor with sidebar specs panel.
- **Print Label (`print_label.php`):** Browser-native 2" x 1" thermal label output with vector SVG barcodes.
