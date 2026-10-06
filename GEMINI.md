# 📦 IQA Metal Warehouse Systems — AI Agent Workspace Guidelines (GEMINI.md)

## 1. Core Architecture & Zero-Dependency Philosophy
- **Strict Zero-Dependency Stack**: Build exclusively with **Vanilla PHP 8.1+**, **Vanilla JavaScript (ES6+)**, and **Vanilla CSS3**. 
- **NO Frameworks or Build Tools**: NEVER introduce Node.js build steps, `npm`/`yarn` dependencies, Webpack/Vite bundlers, Composer packages, or heavy external PHP libraries.
- **Portability (Windows / XAMPP / Linux)**: Always use `__DIR__` for file includes/requires (e.g., `require_once __DIR__ . '/../core/Database.php';`) to ensure seamless execution across local XAMPP and production hosting.
- **Real-Time Sync via SSE**: Prefer Server-Sent Events (`api/sync_stream.php` + `sync.js`) monitoring database `filemtime` and WAL caches over client-side polling timers.

---

## 2. Database & Data Integrity Standards
- **Centralized Connection Pool**: Access databases via `core/Database.php` using `Database::getConnection($db_name)` or convenience methods (e.g., `Database::orders()`, `Database::customers()`, `Database::warehouse()`).
- **100% Prepared Statements**: Every SQL query with dynamic parameters MUST use PDO prepared statements with bound parameters (`$stmt->prepare()` / `$stmt->execute()`). NEVER concatenate or interpolate variables into SQL strings.
- **Mandatory SQLite Pragmas**: All SQLite connections must maintain:
  ```sql
  PRAGMA journal_mode = WAL;
  PRAGMA busy_timeout = 5000;
  PRAGMA synchronous = NORMAL;
  PRAGMA foreign_keys = ON;
  ```
- **Self-Healing Schemas**: Centralize all table definitions and column migrations inside `Schema::ensure()` or module schema guards. NEVER run ad-hoc `ALTER TABLE` statements inside page controllers or view templates.
- **Audit Logging**: Every database mutation (INSERT, UPDATE, DELETE) must record an audit entry using `log_audit_event()`.

---

## 3. Security, Authentication & File Uploads
- **Strict CSRF Validation**: Every POST, PUT, DELETE, or state-changing AJAX endpoint must validate CSRF tokens via `Security::validate($_POST['csrf_token'] ?? '')`.
- **Authentication Guard**: Enforce `AuthGuard::check()` across all protected pages and endpoints. AJAX requests must return `401 Unauthorized` JSON when unauthenticated.
- **Credential & Key Protection**: NEVER expose raw API keys (e.g., Gemini Vision API key) or database filesystem paths in client-facing JSON endpoints.
- **Media Ingest Optimization**: All uploaded photos must be processed at ingest time via `MediaManager`:
  - Convert to WebP format immediately (no Zip-on-demand compression).
  - Store partitioned on disk by `YYYY/MM/`.
  - Maintain cascading physical deletion (raw archive, optimized WebP, thumbnail, and DB records).

---

## 4. Physical Warehouse UI/UX & Hardware Constraints
- **Touch-First Accessibility**: All buttons, inputs, and interactive controls must have a minimum touch target of **48x48px** to accommodate warehouse barcode scanners, iPads, and rugged touch terminals.
- **No Hover-Dependent Logic**: Never hide essential actions or data exclusively behind `:hover` states.
- **High-Contrast & HSL Design Tokens**: Maintain high-contrast palettes, glassmorphic surfaces, and responsive layouts readable under bright or variable warehouse lighting.
- **Flat XML Label Generation**: Generate thermal `.odt` labels using dependency-free Flat XML (FODT). NEVER introduce `ZipArchive` for label generation.
- **Windows Integration**: Preserve compatibility with Windows host launching scripts (e.g., `api/open_windows_file.php`).

---

## 5. Domain Invariants & Business Logic
- **UTF-8 BOM on CSV Downloads**: All client-side CSV exports must prepend the UTF-8 Byte Order Mark (`\uFEFF`) as the first byte for seamless Microsoft Excel compatibility.
- **Trends Table Column Ordering**: In the Model Demand Velocity table (`trends_tab_velocity.php`), the **Avg Price** column MUST be rendered before the **Details** column. Never invert this order.
- **Chart.js Hidden Canvas Lifecycle**: When canvases reside in tabbed views with `display: none`, always destroy previous instances via `Chart.getChart(id)?.destroy()` and dispatch chart initialization with a ~50ms timeout upon tab switching.
- **Foreign Key Safety on Uploads**: When attaching photos to a warehouse location/shelf, verify or ensure the parent location exists (`INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')`) before inserting into `location_photos`.

---

## 6. AI Agent Behavior & Token Optimization
- **Be Concise and Direct**: Focus on delivering clean, working code. Avoid unnecessary conversational preambles, conceptual lectures, or restating the entire prompt.
- **Surgical Edits**: Provide targeted diffs or surgical replacement blocks. NEVER reprint entire multi-hundred-line files when modifying a few lines.
- **Targeted Code Inspection**: Read specific line ranges with `view_file` or use `grep_search` rather than dumping entire files or bulk directories into context.
- **Preserve Documentation & Comments**: Maintain existing code comments, docstrings, and architectural notes unless explicitly asked to modify them.
