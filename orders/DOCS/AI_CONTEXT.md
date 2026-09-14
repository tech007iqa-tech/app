# 🤖 AI Agent Context & Guidelines 9/5/2026 10:52 PM

Welcome! This document provides the architectural and styling patterns for the **IQA Warehouse Systems** codebase. By adhering to these guidelines, you will write cleaner, more maintainable code and avoid redundant investigations that waste tokens.

---

## 🏗️ Architectural Core Patterns

### 1. View-Based Routing & Asset Autoloading
- **Entry Point**: `prod/index.php` acts as the router.
- **Param**: It handles routing using `$_GET['view']` (default is `customer_registry.php`).
- **Mapping**: The `$routes` array maps the requested view key to a specific file in `prod/pages/` and a dedicated stylesheet in `prod/assets/styles/`.
- **Script Autoloading**: `prod/index.php` automatically inserts CSS and JS files matching the active route, reducing manual header/footer dependencies.
- **RBAC**: Access is validated in `prod/core/auth.php`. Non-Admin roles (e.g., Operator) are forced to default to the `warehouse` view.

### 2. Event-Driven Real-Time Synchronization (SSE)
- **Concept**: To synchronize UI changes instantly across multiple workstations without timers, the system uses native browser Server-Sent Events (SSE).
- **Backend stream**: `api/sync_stream.php` maintains a streaming connection and checks `filemtime` on SQLite database and WAL files (`db/customers.db` and `db/customers.db-wal`) every 500ms. If a modification is found, it sends a `database-change` event.
- **Frontend listener**: `assets/js/sync.js` hosts the `AppSync` engine. When registered, it automatically creates a single global `EventSource` listener. On change, it requests `index.php?view=...&ajax=1` and performs a smart row-by-row virtual DOM diff (if it's the registered element ID) or a clean innerHTML swap (for other containers).
- **Registration Example**:
  ```javascript
  AppSync.register({
      elementId: 'leads-list',
      url: 'index.php?view=leads&ajax=1',
      onUpdate: () => { filterLeads(); }
  });
  ```
- **Input protection**: Swaps are paused if the user is typing/interacting with an input element inside the target element.

### 3. State Injection (PHP ➔ JS)
- **Rule**: Do **NOT** declare global JavaScript variables directly in PHP strings.
- **Pattern**:
  1. Wrap data in a JSON script block inside the PHP page:
     ```html
     <script id="module-state" type="application/json">
         <?= json_encode($data_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
     </script>
     ```
  2. Parse the text content inside the companion JS file:
     ```javascript
     const state = JSON.parse(document.getElementById('module-state').textContent);
     ```

### 3. Integrated Cross-DB Joins
- **Pattern**: SQLite databases are split by domain (`customers`, `orders`, `warehouse`, `users`, `calendar`).
- **Helper**: Use `Database::queryIntegrated($primary, $attachments, $sql, $params)` rather than manually attaching.
- **Syntax**:
  ```php
  $sql = "SELECT i.*, c.company_name FROM items i LEFT JOIN cust.customers c ON i.customer_id = c.customer_id";
  $stmt = Database::queryIntegrated('orders', ['cust' => 'customers'], $sql);
  ```

### 4. Self-Healing Schema Guard
- **Location**: `prod/core/Schema.php`.
- **Trigger**: Run automatically during `Database::getConnection()`.
- **Migration Policy**: New columns, indexes, or updates are added globally within `Schema::runMigrations()`. They are written idempotently so they run safely on every boot. Do not write `CREATE TABLE` or `ALTER TABLE` statements inside view pages or endpoints.

### 5. Audit Logging with Resilient Fallback
- **Helper**: Use `Audit::log($action, $target_id, $details, $module)` for sensitive alterations (deletions, checkouts, updates).
- **centralized**: DB logs are saved inside `users.db` under the `audit_log` table.
- **Resilience**: If the database is locked, it catches the exception and logs to a flat file at `prod/logs/audit_fallback.log`.

### 6. Intake Vocabulary Caching
- **Endpoint**: `prod/api/get_vocabulary.php`.
- **JS Caching**: `prod/assets/js/vocabulary.js` caches autocomplete terms (brands, models, CPUs) in browser `sessionStorage`. This prevents laggy AJAX completions during fast intake sessions.

### 7. Timezone Configuration
- **Requirement**: The application must enforce the Pacific timezone (`America/Los_Angeles`) to ensure `date()` and `time()` correctly align with the warehouse's physical location, preventing calendar day shifts (e.g., Thursday showing as Friday in UTC after hours).
- **Implementation**: Ensure `date_default_timezone_set('America/Los_Angeles');` is explicitly set in date-dependent views like `calendar.php`.

---

## 🎨 UI/UX Design System Guidelines

- **Typography**: Uses the Google Font *Outfit* (sans-serif) for high scannability in warehouse conditions.
- **Colors**: Defined in `prod/assets/styles/style.css` via CSS custom properties. Uses harmonized HSL variables:
  - `--text-main`: Sleek dark slate/charcoal.
  - `--accent-color`: Vibrant green (`#8cc63f`) representing fulfillment success.
  - `--border-color`: Light slate/gray border divider lines.
  - Glassmorphic backdrops are used for interactive cards and input overlays.

---

## 📱 Mobile (iOS Safari) Constraints
Many warehouse managers perform hardware audits using iPads. To prevent styling degradation and zoom behaviors on mobile iOS Safari, respect the following constraints:
- **Zoom Block**: Form inputs (inputs, selects, textareas) must have a font size of at least `16px`. iOS Safari will automatically zoom the page on focus if the font size is smaller.
- **Viewport Bounds**: Modal windows and overlays should use `100dvh` (Dynamic Viewport Height) to ensure they render cleanly behind the Safari navigation toolbars.
- **Touch Targets**: All clickable elements (buttons, badge inputs, close tags) must maintain a minimum bounding box of `48px` × `48px` to support error-free touch interaction.
- **Hover Dependency**: Never hide critical actions behind hover triggers. All edit and delete paths must be directly clickable on touchscreen interfaces.

---

## 🔍 Token-Saving Shortcuts for AI Agents
- **Working Zones Grid**: `warehouse.php` implements a drill-down architecture where parent zones control the sub-locations visible. Use the state parameters `sector` and `active_zone_name` to filter and render sub-locations.
- **CPU pricing popups**: `trends.php` uses companion JS functions `openCpuPricingModal` and `openOrderPreviewModal` that query standard AJAX endpoints. Keep dialog HTML blocks at the bottom of the PHP file layout.
- **Config & DB Check**: Look directly at `prod/core/database.php` and `prod/core/Schema.php` for table blueprints and schema changes.
- **Pathing reference**: Relative path references are calculated relative to `prod/index.php`. Use `__DIR__` in PHP includes to ensure correct file inclusion.
