# Daily Intake Form Audit & Normalization Terminal

A self-contained, offline-capable AI-powered daily intake auditing interface. It processes user-uploaded handwritten device images, extracts structured data via **Gemini Vision OCR**, normalizes values using a configurable dictionary and rule system, and commits them to a local SQLite database.

The terminal is also **integrated into the main Warehouse System** via a seamless iframe under `orders/index.php?view=inbound`, while remaining fully independent and offline-usable.

---

## System Schema

The pipeline maps the extracted data into the primary `committed_intakes` SQLite table:

| Column       | Type    | Format / Normalization Rule                                               |
| :----------- | :------ | :------------------------------------------------------------------------ |
| **Date**     | Date    | `YYYY-MM-DD` (defaults to current local date)                             |
| **QTY**      | Integer | Standard count (defaults to `1`)                                          |
| **Item**     | String  | Cleaned brand + model name. Detected serial numbers are appended: `Brand Model (Serial: XYZ123)` |
| **Serial**   | String  | CPU config or serial tag extracted from intake sheet's Serial column.     |
| **Location** | String  | Standard uppercase location bin code in `Letter-Number` format (e.g., `C-1`, `A-3`, `E-9`) |
| **Notes**    | String  | Free-text technical details, condition notes, and specifications.         |

---

## File Structure

- **`audit.html`**: Main intake terminal UI — glassmorphic dark-mode with drag-and-drop file support, dynamic image pan/zoom/rotate, AI OCR mode, Manual Grid Overlay mode, confidence indicators, and thumbnail strip for multi-image sessions.
- **`history.html`**: Committed intake log viewer with:
  - **Hierarchical location breadcrumbs** (shelf-level then bin-level, e.g. `Shelf E` → `E-1`, `E-2`)
  - Sortable columns, full-text search, Download CSV export
  - **Upload to Warehouse Orders** integration link
- **`settings.html`**: AI OCR & System Prompt configuration panel with:
  - Gemini API Key management
  - System prompt tuning (Persona, Field Schema guidance, Brand Abbreviation Mappings, Normalization Rules)
  - Compiled prompt preview
  - **Database Management** module — admin section to permanently clear committed intake records
- **`process.php`**: Backend router implementing:
  - `action=extract`: Sends the uploaded image to the Gemini Vision API. Builds the prompt dynamically from saved settings and normalizes extracted rows.
  - `action=save`: Inserts verified and normalized rows into the SQLite database.
  - `action=get_committed`: Returns all committed rows from the database.
  - `action=clear_committed`: Deletes all committed rows from the database.
  - `action=get_config` / `action=save_config`: Reads/writes `config.json` with Gemini API key and prompt settings.
- **`config.json`**: Stores the Gemini API key and all prompt settings (role, translations, schema rules, formatting rules).
- **`src/OcrEngine.php`**: Builds and sends the Gemini Vision multipart API request. Dynamically assembles the prompt from settings.
- **`src/Normalizer.php`**: Applies dictionary abbreviation expansion and formatting rules to each extracted row.
- **`src/DbHandler.php`**: SQLite PDO handler for the `committed_intakes` table in `sample_data/intake.sqlite`.
- **`src/Config.php`**: Reads and writes `config.json`.
- **`assets/css/audit.css`**: Full stylesheet for the terminal (dark glassmorphic theme, buttons, tables, dropzone, toasts, spinner).
- **`assets/css/settings.css`**: Stylesheet for the settings page.
- **`assets/js/api.js`**: Fetch wrapper for all `process.php` API calls (`extractOCR`, `saveRows`, `getCommitted`, `clearCommitted`, `getConfig`, `saveConfig`).
- **`assets/js/audit.js`**: Manages file uploads, OCR orchestration, undo state, table commit, and reset logic.
- **`assets/js/grid.js`**: Table rendering, row addition/deletion, Manual Grid Overlay mode, CSV load/download.
- **`assets/js/viewer.js`**: Image pan, zoom, rotation transforms.
- **`assets/js/dragdrop.js`**: Drag-and-drop file handling.
- **`sample_data/intake.sqlite`**: Auto-created SQLite database file for committed intakes.

---

## Integration with Warehouse System

The Audit Terminal is embedded inside the main Order Management system at:

```
http://localhost/orders/index.php?view=inbound
```

This is achieved via a seamless `<iframe>` in `orders/pages/inbound.php` pointing to `../sampleWHdata/audit.html`. The outer container padding/borders are suppressed to make it feel native.

- All relative asset paths (`assets/js/*`, `assets/css/*`) and API requests (`process.php`) resolve correctly from within the iframe context.
- The terminal remains fully operational when opened directly as a standalone page.

---

## Setup & Testing

1. Start Apache in your **XAMPP Control Panel**.
2. Open your web browser and navigate to:
   ```
   http://localhost/WarehouseSystems-main/sampleWHdata/audit.html
   ```
   Or access it from the main system:
   ```
   http://localhost/WarehouseSystems-main/orders/index.php?view=inbound
   ```
3. Go to **⚙ Dictionary Settings** to enter your **Gemini API Key** and configure AI prompt settings.
4. Drag and drop handwritten intake sheet images into the intake zone.
5. The AI will extract and normalize tabular records. Verify, edit, approve rows, and click **✅ Commit to Database** to save.
6. View committed history at `history.html`. Filter by shelf letter and bin location using the interactive breadcrumb bar.
7. To clear all committed records, go to **Dictionary Settings** → **Database Management**.
