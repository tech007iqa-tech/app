# System Architecture & Database Schema

## 1. Overview
This document outlines how the IQA Metal Inventory & Label app manages its data.
The application uses **SQLite 3** database files (`.sqlite`) located in the `/db/` directory, with PHP Data Objects (PDO) for all database interactions. All queries use Prepared Statements (`$stmt->prepare()`) to prevent SQL Injection.

---

## 2. Database: `labels.sqlite`
This database is the single source of truth for **Hardware Label Profiles**. Each row represents a device configuration.

### Flexible Search Logic
The application implements a multi-keyword search engine. Queries are split into individual tokens and an item is only returned if **every keyword** is found in at least one of its major technical fields (Brand, Model, Series, CPU, etc.). This "AND" logic allows precise filtering like "HP 840 i5".

### Table: `items`
* **`id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`): System ID.
* **`type`** (`TEXT`): Hardware category (e.g., 'Laptop', 'Desktop', 'Gaming Console').
* **`brand`** (`TEXT NOT NULL`): Manufacturer.
* **`model`** (`TEXT NOT NULL`): Primary model name.
* **`series`** (`TEXT`): Exact series details.
* **`cpu_gen`** (`TEXT`): Processor generation (e.g., '11th Gen').
* **`cpu_specs`** (`TEXT`): Exact processor model (e.g., 'i7-11850H').
* **`cpu_cores`** (`TEXT`): Number of physical cores.
* **`cpu_speed`** (`TEXT`): Clock frequency (e.g., '2.40GHz').
* **`ram`** (`TEXT`): Memory capacity.
* **`storage`** (`TEXT`): Drive capacity.
* **`battery`** (`BOOLEAN`): Battery included (1/0).
* **`battery_specs`** (`TEXT`): Health % and cycle counts.
* **`gpu`** (`TEXT`): Graphics processing details.
* **`screen_res`** (`TEXT`): Screen Resolution / Size.
* **`webcam`** (`TEXT`): Camera specs.
* **`backlit_kb`** (`TEXT`): Backlit keyboard status (Yes/No).
* **`os_version`** (`TEXT`): Operating system details.
* **`cosmetic_grade`** (`TEXT`): Grade A, B, or C.
* **`work_notes`** (`TEXT`): Technical/reparation notes for refurbished units.
* **`bios_state`** (`TEXT`): BIOS lock status.
* **`description`** (`TEXT`): Internal condition (e.g., 'Untested', 'Refurbished', 'For Parts').
* **`status`** (`TEXT`): Current status (default: `'In Warehouse'`).
* **`warehouse_location`** (`TEXT`): Physical location in the warehouse.
* **`serial_number`** (`TEXT`): Device serial number.
* **`updated_at`** (`DATETIME`): Last edit timestamp.
* **`created_at`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)

---

## 3. Database: `audit.sqlite`
Tracks all system-wide changes to hardware records for accountability.

### Table: `audit_logs`
* **`id`** (`INTEGER PRIMARY KEY AUTOINCREMENT`)
* **`entity_type`** (`TEXT NOT NULL`): Always `'Label'` in current scope.
* **`entity_id`** (`TEXT NOT NULL`): ID of the affected record.
* **`action`** (`TEXT NOT NULL`): `'CREATED'`, `'UPDATED'`, `'DELETED'`.
* **`summary`** (`TEXT`): Human-readable description.
* **`old_value`** (`TEXT`): JSON state before the action.
* **`new_value`** (`TEXT`): JSON state after the action.
* **`user_name`** (`TEXT DEFAULT 'System'`)
* **`timestamp`** (`DATETIME DEFAULT CURRENT_TIMESTAMP`)

---

---

## 4. Dual Printing Engine Architecture

### A. Direct Web Thermal Printing (Universal & Zero Setup)
Located at [`print_label.php`](file:///c:/xampp/htdocs/app/labels/print_label.php).
- **Format:** Calibrated specifically for thermal continuous rolls using `@page { size: 2in 1in; margin: 0; }`.
- **Zero Host Software:** Works natively in Google Chrome, Microsoft Edge, Safari, and Firefox on Windows, macOS, or Linux without any desktop daemon.
- **Vector Barcodes:** Barcodes are rendered as lightweight, lossless SVG vectors (`<svg viewBox="0 0 100 20">`) ensuring razor-sharp scanning on 203 DPI and 300 DPI thermal print heads (Zebra, Rollo, Brother, MUNBYN).
- **Print Modes:** Supports printing Sticker A (Brand/Barcode), Sticker B (Specs), or Dual Batches (`mode=both`, `mode=a`, `mode=b`).

### B. Document Generation (Structural Surgery — ODT)
The app maintains a native **"Structural Surgery"** approach to generate OpenDocument files (`.odt`) for Windows workstations:
1. **Master Template**: A clean ODF file (`templates/label_template.odt`) serves as the structural backbone.
2. **Structural XML Surgery**: The PowerShell engine (`templates/scripts/generate_odt.ps1`) extracts the original XML from the template and uses **Regex grafting** to inject dynamic data into the `<office:text>` container. It utilizes custom inheriting styles (e.g., `P5B` for Specs with Page Breaks) to ensure precise top-alignment on labels without empty-paragraph artifacts.
3. **ODF Compliance**: The engine surgically removes `Configurations2/` and `manifest.rdf` to prevent LibreOffice warnings, then rebuilds `manifest.xml` for strict ODF 1.2 ISO compliance.
4. **Persistent Documents:** All generated labels are stored in `exports/labels/` for audit trail compliance.

---

## 5. Intake Action Pipeline & Validation

### A. The Four Core Actions (`new_label.php`)
1. **`print` (`🖨️ Print Thermal Label`)**: Validates brand/model, posts to `api/add_label.php`, obtains item ID, and triggers browser-native 2" × 1" thermal roll print.
2. **`odt` (`📄 Save & Windows ODT`)**: Validates brand/model, posts to `api/add_label.php`, and triggers PowerShell ODT compilation and download.
3. **`save` (`💾 Save`)**: Persists hardware profile directly into SQLite inventory with confirmation toast without triggering print dialogs.
4. **`reset` (`✨ Start Fresh`)**: Clears inputs, pills, resets mockup, retains Full Technical Sheet default mode, and smoothly animates viewport back to `Hardware Identity & Model`.

### B. Dynamic Input Validation & Suffix Engine
- **Core Count**: Validates keystrokes for integers `[0-9]`. Displays dynamic suffix badge (`Core` when 1, `Cores` when ≥2) only while active. Syncs combined formatted string (`4 Cores`) to database.
- **Clock Frequency**: Validates keystrokes for floating point numbers `[0-9.]` with single-dot enforcement. Displays dynamic suffix badge `GHz` only while active. Syncs formatted string (`2.40 GHz`) to database.
- **Missing Hardware Flags**: Dedicated `.pill-none` selectors for `No RAM` and `No SSD` / `No Drive` integrated into the live thermal mockup and database schema.

---

## 5. API Hardening (Path Resolution)
All API endpoints in `/api/` use absolute directory resolution:
```php
require_once __DIR__ . '/../includes/db.php';
```
This ensures file writes always resolve to the correct project root.

---

## 6. System Fortification (Self-Healing)

### Schema Guard
Located in `includes/schema_guard.php`. Every time the app connects, the Guard verifies all tables and columns exist. Missing schemas are silently rebuilt.

### Duplicate Prevention Engine
The API layer (`api/add_label.php`) and frontend (`assets/js/forms.js`) work together to prevent inventory clutter via technical fingerprinting and contextual reuse.

---

## 7. Hardware Mapping Layer (Single Source of Truth)
The system implements a **Dual-Path Mapping Layer** to prevent field name errors.

- **PHP** (`includes/hardware_mapping.php`): Defines the `HW_FIELDS` constant array for all backend SQL queries.
- **JavaScript** (`assets/js/hardware_mapping.js`): Mirrors the mapping in `window.HW_FIELDS` for all frontend rendering.
- **Rule**: Never hardcode field names. Always reference the mapping (e.g., `HW_FIELDS['CPU_SPECS']`).
