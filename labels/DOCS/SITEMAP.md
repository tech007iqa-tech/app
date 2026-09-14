# Sitemap & Directory Structure

## 1. Directory Structure
```text
/app/
│
├── /assets/                # Static frontend assets
│   ├── /css/
│   │   └── style.css       # Global light-theme stylesheet
│   └── /js/
│       ├── forms.js        # Logic for new_label form (Intelligent CPU Intake)
│       ├── labels.js       # CRUD & Filtering for warehouse tracking
│       ├── actions.js      # Global Technical Action Bridge (Open/Launch/Reprint)
│       ├── hardware_mapping.js  # Field name constants (mirrors PHP)
│       └── print_engine.js # Quantity & Layout Logic for Printer Direct
│
├── /db/                    # SQLite Database Files
│   ├── labels.sqlite       # Items & Inventory
│   └── audit.sqlite        # System audit trail
│
├── /templates/             # Master files for PowerShell Injection
│   ├── label_template.odt  # Master hardware label
│   └── /scripts/
│       └── generate_odt.ps1  # Label document generator
│
├── /includes/              # Reusable PHP backend components
│   ├── db.php              # PDO Shared Connections
│   ├── header.php          # Sidebar Nav & HTML Head
│   ├── footer.php
│   ├── functions.php       # Formatting & Sanitization
│   ├── hardware_form.php   # Unified Intake/Edit Component
│   ├── hardware_mapping.php # Field name constants (HW_FIELDS)
│   ├── schema_guard.php    # Self-Healing Schema Logic
│   └── audit.php           # Audit trail logging function
│
├── /api/                   # API Endpoints (All return JSON)
│   ├── add_label.php       # POST: Insert new hardware + generate label
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
├── index.php               # Landing Page (Quick Search & Stats)
├── labels.php              # Warehouse Table View (main inventory)
├── new_label.php           # Add Item Form (CPU Intake)
├── hardware_view.php       # Technical Sheet Editor
├── print_label.php         # High-Quality 2" x 1" HTML Printing
└── 404.php                 # Error page
```

## 2. UI View Hierarchy
- **Landing (`index.php`):** Quick Locate search + warehouse stat counter + quick actions.
- **Inventory (`labels.php`):** Filterable table with **Inline Editing**, **Print**, **Open**, and **Delete**.
- **New Label (`new_label.php`):** Hardware intake form with Intelligent CPU Auto-fill and Profile Cloning.
- **Hardware View (`hardware_view.php`):** Deep technical editor with sidebar specs panel.
- **Print Label (`print_label.php`):** Browser-native 2" x 1" thermal label output.
