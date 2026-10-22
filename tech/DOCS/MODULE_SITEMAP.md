# 🗺️ Module Sitemap — Technician Control Center (`/tech`)
*Last Updated: October 2026 | Timezone: America/Los_Angeles (Pacific Time)*

This document provides a complete, line-by-line file directory, route inventory, access-control matrix, and dependency graph for the **Technician Control Center (`/tech`)** module within the IQA Metal Warehouse Systems ecosystem.

---

## 📂 Directory Tree Overview

```text
c:\xampp\htdocs\app\tech\
├── DOCS/
│   ├── AI_AGENT_INSTRUCTIONS.md           # Mandatory rules, constraints, RBAC, timezone rules
│   ├── AI_TECHNICAL_DEEP_DIVE.md          # Database schemas, state machines, and API catalog
│   ├── CROSS_MODULE_INTEGRATION_GUIDE.md  # Inter-module protocols (/labels, /warehouse, /orders)
│   └── MODULE_SITEMAP.md                  # This exhaustive file and route inventory
├── README.md                              # Module overview and quick navigation index
├── index.php                              # Root executive dashboard (yields, stats, quick links)
├── api/                                   # 12 backend REST/JSON endpoints
│   ├── add_log.php                        # Hardware intake & spec collision resolver
│   ├── add_part.php                       # Component stock insertion
│   ├── admin_actions.php                  # Administrative approval & rejection of pending logs
│   ├── delete_part.php                    # Component stock deletion
│   ├── get_inventory.php                  # Component inventory data retrieval
│   ├── get_logs.php                       # Paginated & filtered hardware test logs
│   ├── lookup_label.php                   # Spec matching & master label lookup
│   ├── pass_warehouse.php                 # Handoff flag dispatch to warehouse racking
│   ├── print_label.php                    # Thermal FODT label generator (Page A + Page B)
│   ├── request_actions.php                # Tech deletion requests & status downgrades
│   ├── search_logs.php                    # Rapid serial/make/model autocomplete search
│   └── update_part.php                    # Component stock quantity adjustments
├── assets/
│   └── styles/                            # Dedicated page stylesheets
│       ├── admin.css                      # Admin audit ledger styling
│       ├── dashboard.css                  # Technician dashboard metrics & KPI cards
│       ├── inventory.css                  # Parts inventory table & stock warning badges
│       ├── labels.css                     # Label previewer & printer modal styles
│       ├── logs.css                       # Master logs workbench (23KB+, modals, tabs, chips)
│       └── settings.css                   # System settings & technician preferences
├── core/                                  # Core infrastructure & authentication
│   ├── auth.php                           # RBAC session validator & role helper functions
│   ├── database.php                       # Master Database proxy connecting to /app/core/Database.php
│   └── login.php                          # Standalone technician login & session initiator
├── exports/
│   └── labels/                            # Destination for generated 2x1 thermal .odt labels
└── pages/                                 # 5 primary user-facing views
    ├── admin_audit.php                    # Admin-only technician throughput & audit trail
    ├── inventory.php                      # Component stock tracking & reorder workbench
    ├── labels.php                         # Label directory & thermal reprint center
    ├── logs.php                           # Main Hardware Testing Workbench (Intake, Filter, Edit)
    └── settings.php                       # Module configuration & user profile
```

---

## 📄 File-by-File Technical Specification

### 1. Root & Core Files

#### [index.php](file:///c:/xampp/htdocs/app/tech/index.php)
- **Role**: Dashboard / KPI Command Center.
- **Access**: Operator (`tech`), Admin (`admin`).
- **Dependencies**: `core/auth.php`, `core/database.php`, `../../orders/assets/styles/components.css`, `assets/styles/dashboard.css`.
- **Description**: Displays daily test yields ("Tested Today", "Good" vs "Bad"), technician velocity rankings, low parts alerts, and fast navigation buttons. All metrics strictly bound to `$today = date('Y-m-d')` in `America/Los_Angeles`.

#### [core/auth.php](file:///c:/xampp/htdocs/app/tech/core/auth.php)
- **Role**: Authentication Guard & RBAC Engine.
- **Access**: Shared internal include.
- **Key Functions**:
  - `date_default_timezone_set('America/Los_Angeles')`: Enforces Pacific Time globally.
  - `session_start()`: Checks session validity.
  - `header("Location: login.php")`: Redirects unauthenticated requests.
  - Case-insensitive admin resolution: `is_admin()` evaluates `$_SESSION['role']` and `$_SESSION['username']`.

#### [core/database.php](file:///c:/xampp/htdocs/app/tech/core/database.php)
- **Role**: Database Connection Proxy.
- **Dependencies**: `../../core/Database.php`.
- **Description**: Bridges the `/tech/` module directly to the master centralized singleton PDO connection pool (`Database::tech()`, `Database::labels()`, `Database::warehouse()`, `Database::orders()`, `Database::users()`). Enforces SQLite WAL mode, foreign keys, and 5000ms busy timeout.

#### [core/login.php](file:///c:/xampp/htdocs/app/tech/core/login.php)
- **Role**: Technician Login & Session Bootstrap.
- **Access**: Public / Unauthenticated.
- **Description**: High-contrast, touch-optimized login view supporting direct PIN or username/password authentication against `users.db`. Sets session variables: `$_SESSION['user_id']`, `$_SESSION['username']`, and `$_SESSION['role']`.

---

### 2. User-Facing Pages (`pages/`)

#### [pages/logs.php](file:///c:/xampp/htdocs/app/tech/pages/logs.php)
- **Role**: Master Hardware Testing & Intake Workbench.
- **Access**: Operator, Admin.
- **Dependencies**: `../core/auth.php`, `../core/database.php`, `assets/styles/logs.css`.
- **Key Features**:
  - **Dynamic Ingest Form**: Tests Make, Model, CPU, Generation, RAM, Storage (SSD/NVMe/HDD), Cosmetic Grade (A/B/C/D), Screen Condition, Delivery Status, and Shelving.
  - **Smart Spec Collision Detection**: As technician types specs, polls `api/lookup_label.php` in real-time. If an identical hardware profile exists in `labels.db`, prompts the user to reuse the master label and append the location.
  - **Rapid Lot/Multi-Serial Ingest**: Space or comma-delimited serials automatically create individual hardware units linked to the shared master spec label.
  - **Daily Quota & Deletion Guard**: Technicians can request deletion (`Pending Delete`); admins see an instant "Approve Delete" button.
  - **Timezone Invariant**: Uses JavaScript helpers `formatPacificTime()` and `formatPacificDate()` for instant human-readable timestamps in Pacific Time.

#### [pages/admin_audit.php](file:///c:/xampp/htdocs/app/tech/pages/admin_audit.php)
- **Role**: Administrator-Only Throughput & Quality Audit.
- **Access**: Strict Admin (`admin`).
- **Dependencies**: `../core/auth.php`, `../core/database.php`, `assets/styles/admin.css`.
- **Key Features**:
  - Detailed daily/weekly technician breakdown (yield count, failure rate %, average test duration).
  - Pending Deletion queue with 1-click batch approvals or rejections (`api/admin_actions.php`).
  - Audit trail of status changes (`Good` -> `Bad`) with reason logs.

#### [pages/inventory.php](file:///c:/xampp/htdocs/app/tech/pages/inventory.php)
- **Role**: Warehouse Component & Diagnostic Tool Inventory.
- **Access**: Operator, Admin.
- **Dependencies**: `../core/auth.php`, `../core/database.php`, `assets/styles/inventory.css`.
- **Key Features**:
  - Real-time stock levels of RAM modules (DDR3/DDR4/DDR5), SSDs (SATA/M.2 NVMe), power adapters, and tools.
  - Low stock warning badges (`stock <= min_stock`).
  - Instant modal updates via `api/update_part.php`, `api/add_part.php`, and `api/delete_part.php`.

#### [pages/labels.php](file:///c:/xampp/htdocs/app/tech/pages/labels.php)
- **Role**: Thermal Label Directory & Reprint Station.
- **Access**: Operator, Admin.
- **Dependencies**: `../core/auth.php`, `../core/database.php`, `assets/styles/labels.css`.
- **Key Features**:
  - Visual directory of recent master labels generated by hardware tests.
  - Direct 1-click reprinting triggering `api/print_label.php`.
  - Deep-link bridge to `/labels/hardware_view.php?id={label_id}`.

#### [pages/settings.php](file:///c:/xampp/htdocs/app/tech/pages/settings.php)
- **Role**: Technician Preferences & Station Settings.
- **Access**: Operator, Admin.
- **Dependencies**: `../core/auth.php`, `../core/database.php`, `assets/styles/settings.css`.
- **Key Features**:
  - Default workbench test presets (default RAM, storage types, OS grade defaults).
  - Thermal printer queue selection.
  - Active user session details and password update.

---

### 3. Backend API Endpoints (`api/`)

| Endpoint | HTTP Method | Role Required | Output | Purpose & Primary Actions |
| :--- | :--- | :--- | :--- | :--- |
| [add_log.php](file:///c:/xampp/htdocs/app/tech/api/add_log.php) | `POST` | Operator, Admin | JSON | Validates hardware test, resolves master label collision in `labels.db`, creates records in `tech.db`, updates stock. |
| [lookup_label.php](file:///c:/xampp/htdocs/app/tech/api/lookup_label.php) | `GET`, `POST` | Operator, Admin | JSON | Matches specs (Make, Model, CPU, RAM, Disk) against `labels.db` to locate master `label_id` and existing shelf locations. |
| [get_logs.php](file:///c:/xampp/htdocs/app/tech/api/get_logs.php) | `GET` | Operator, Admin | JSON | Returns paginated, searchable test logs. Accepts `limit`, `offset`, `search`, `status`, and `date`. Converts UTC to Pacific Time. |
| [search_logs.php](file:///c:/xampp/htdocs/app/tech/api/search_logs.php) | `GET` | Operator, Admin | JSON | Fast lightweight lookup for topbar search widget matching serial number, make, or model. |
| [print_label.php](file:///c:/xampp/htdocs/app/tech/api/print_label.php) | `POST`, `GET` | Operator, Admin | JSON / File | Generates 2x1 dual-page thermal label in Flat XML (`.fodt`), saves to `exports/labels/`, triggers Windows printer launcher. |
| [pass_warehouse.php](file:///c:/xampp/htdocs/app/tech/api/pass_warehouse.php) | `POST` | Operator, Admin | JSON | Sets `ready_for_warehouse = 1` and `delivery_status = 'Delivered to Shelf'`, signaling the warehouse module for bin intake. |
| [request_actions.php](file:///c:/xampp/htdocs/app/tech/api/request_actions.php) | `POST` | Operator, Admin | JSON | Handles tech deletion requests (`delete_requested = 1`) and status changes to `Bad` (rate-limited to 5/day per tech). |
| [admin_actions.php](file:///c:/xampp/htdocs/app/tech/api/admin_actions.php) | `POST` | Strict Admin | JSON | Admin approvals for pending deletions, record rejections, or hard purges. Records audit log in `users.db`. |
| [get_inventory.php](file:///c:/xampp/htdocs/app/tech/api/get_inventory.php) | `GET` | Operator, Admin | JSON | Returns all component inventory items from `parts_inventory` with calculated low-stock alerts. |
| [add_part.php](file:///c:/xampp/htdocs/app/tech/api/add_part.php) | `POST` | Operator, Admin | JSON | Inserts a new component category, part name, specification, and initial stock into `parts_inventory`. |
| [update_part.php](file:///c:/xampp/htdocs/app/tech/api/update_part.php) | `POST` | Operator, Admin | JSON | Increments, decrements, or updates current stock levels and minimum reorder thresholds. |
| [delete_part.php](file:///c:/xampp/htdocs/app/tech/api/delete_part.php) | `POST` | Strict Admin | JSON | Deletes a discontinued component record from `parts_inventory`. |

---

### 4. Stylesheets (`assets/styles/`)

- [admin.css](file:///c:/xampp/htdocs/app/tech/assets/styles/admin.css): Administrator KPI charts, audit approval cards, and technician velocity comparison bars.
- [dashboard.css](file:///c:/xampp/htdocs/app/tech/assets/styles/dashboard.css): Touch-first dashboard grid, big statistic cards (Tested, Good, Bad, Yield %), and low-stock notification ticker.
- [inventory.css](file:///c:/xampp/htdocs/app/tech/assets/styles/inventory.css): Parts inventory table, inline quantity counters (`+` / `-`), and warning pill badges.
- [labels.css](file:///c:/xampp/htdocs/app/tech/assets/styles/labels.css): Thermal label layout previewer, dual-page toggle (Front Brand vs Back Specs), and print dialog modal.
- [logs.css](file:///c:/xampp/htdocs/app/tech/assets/styles/logs.css): Master stylesheet for hardware workbench (600+ lines). Includes fast-tag chips, cosmetic grade radio pills, multi-serial lot manager, status filters, and modal dialogues. Minimum touch target: 48x48px.
- [settings.css](file:///c:/xampp/htdocs/app/tech/assets/styles/settings.css): Station profile configuration forms and test intake preset defaults.

---

## 🔗 Cross-Module Route & URL Mapping

| URL Route | Target File | Default Params | Description |
| :--- | :--- | :--- | :--- |
| `http://localhost/app/tech/` | `index.php` | None | Primary technician command dashboard. |
| `http://localhost/app/tech/pages/logs.php` | `pages/logs.php` | None | Main testing and intake workbench. |
| `http://localhost/app/tech/pages/admin_audit.php` | `pages/admin_audit.php` | `?date=YYYY-MM-DD` | Admin-only technician throughput ledger. |
| `http://localhost/app/tech/pages/inventory.php` | `pages/inventory.php` | None | Component inventory and parts management. |
| `http://localhost/app/tech/pages/labels.php` | `pages/labels.php` | None | Master label reprint station. |
| `http://localhost/app/tech/pages/settings.php` | `pages/settings.php` | None | Technician station preferences. |
| `http://localhost/app/tech/core/login.php` | `core/login.php` | None | Authentication login terminal. |
