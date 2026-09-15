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

## 📈 Trends Center Architecture & Rules (`view=trends`)
- **Model Demand Velocity Table Order**: The column order must strictly be: `Rank/Customer`, `Brand`, `Model`, `Avg Price`, `Details`, `Latest Sold/Customer Order`, `Units Sold`. Avg Price must always appear before Details.
- **Chart.js Instance Lifecycle**: Always destroy active chart instances (`Chart.getChart('aspChart')`, `Chart.getChart('valuationChart')`, `Chart.getChart('comboPricingChart')`, `Chart.getChart('cpuBrandChart')`) before initializing new instances to prevent canvas collision errors upon tab activation and theme changes.
- **Tab State & Filter Persistence**: Tab navigation must sync with `sessionStorage` (`trends_active_tab`) and the URL search parameter (`?view=trends&tab=...`). Changing the date filter dropdown must invoke `applyTrendsFilter()` to preserve the user's active tab.
- **Dual-Axis Performance Model & View Switcher (Phase 3)**:
  - Tab 2 (*Pricing Curves*) supports dynamic mode toggling via `setPricingChartViewMode('split' | 'combo')`.
  - Mode selection is persisted in `sessionStorage` (`pricing_chart_view_mode`) and hydrated on load.
  - `comboPricingChart` plots Gross Realized Valuation bars on Left Axis (`yValuation`) and Realized ASP curve on Right Axis (`yAsp`) with synchronized dual metrics.
- **Live Matrix Micro-Feedback & Cell Glow (Phase 3)**:
  - In `tab-matrix` (*B2B Untested*) and `tab-tested` (*Tested Market Reference*), inline cell changes call `showMatrixSaveToast(msg, targetInput)` in `trends_modals.js`.
  - The edited cell dynamically animates with `.cell-saved-pulse` and a floating confirmation toast (`#matrixSaveToast`) confirms the saved value.
- **Financial Timeline Ordering**: Time-series charts must sort chronologically from left to right (oldest to newest month), while the audit table maintains reverse-chronological order for rapid auditing.
- **1-Click CSV Exports**:
  - **Tab 2 Financial Ledger**: `exportFinancialLedgerCSV()` exports all ledger rows, MoM growth %, share %, and the period totals footer with UTF-8 BOM encoding.
  - **Tab 1 Demand Velocity**: `exportDemandVelocityCSV()` exports all model velocity ranks, pricing, inventory stock, buyer names, and order IDs.
- **Cross-Module Customer Profile & Order History Intelligence (Phase 4)**:
  - Client company names in Tab 4 (*Customer Insights*), Tab 1 (*Model Demand Buyer Names*), and CPU Pricing modal sales lists are clickable (`.customer-profile-link`) to open `#customerProfileModal`.
  - The modal dynamically queries `index.php?view=trends&action=get_customer_profile` in `trends_actions.php` to fetch lifetime spend, total units bought, completed order count, account tenure, recent transaction manifests, and CRM contact details.
  - Features quick-action launch buttons: `View in CRM` (`index.php?view=leads&search=...`) and `New Order Batch` (`index.php?customer_id=...`), plus individual manifest 1-click preview triggers.
- **Global Empty-State Polish (Phase 4)**:
  - Both table-level (`.no-results-row`) and tab-level (`.global-no-results`) empty states display styled search icons, clear typography, and a "Clear Search Filter" button (`clearSearchInput()`).

---

## 🎯 Leads & CRM Architecture & Rules (`view=leads`)
- **Table Sorting**: All 9 data columns (`Customer / Lead`, `Status`, `Source`, `Interest`, `Last Order`, `Balance`, `Last Contact`, `Next Call`, `Notes`) must be sortable using `sortLeadsTable(colIndex, type)` with raw unformatted values in `data-sort-val`.
- **Follow-Up Urgency Tagging**: The `Next Call` column must dynamically display visual status chips:
  - 🔴 **Overdue** (`callback_date < today`)
  - 🟡 **Due Today** (`callback_date == today`)
  - 🟢 **Upcoming** (`callback_date > today`)
- **Real-Time Text Highlighting**: Real-time multi-term search keyword highlighting must be preserved across company names, notes, contact channels, and statuses via `highlightLeadNodeWords()`.
- **1-Click Leads CSV Export**: `exportLeadsCSV()` exports active and filtered accounts with company names, status, urgency, balance, contact dates, and internal notes.

---

## 🔍 Token-Saving Shortcuts for AI Agents
- **Working Zones Grid**: `warehouse.php` implements a drill-down architecture where parent zones control the sub-locations visible. Use the state parameters `sector` and `active_zone_name` to filter and render sub-locations.
- **CPU pricing popups**: `trends.php` uses companion JS functions `openCpuPricingModal` and `openOrderPreviewModal` that query standard AJAX endpoints. Keep dialog HTML blocks at the bottom of the PHP file layout.
- **Config & DB Check**: Look directly at `prod/core/database.php` and `prod/core/Schema.php` for table blueprints and schema changes.
- **Pathing reference**: Relative path references are calculated relative to `prod/index.php`. Use `__DIR__` in PHP includes to ensure correct file inclusion.

