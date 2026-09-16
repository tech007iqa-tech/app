# 🔍 Code Review Checklist 9/16/2026 11:26 AM

This checklist must be used to evaluate all code changes and contributions to the **IQA Warehouse Systems** project.

---

## 1. Database & Sync Integrity
- [ ] **Prepared Statements**: Are all SQL queries written with PDO prepared statements and bound variables? Do NOT concatenate user input directly into queries.
- [ ] **Schema Migrations**: If your code requires new columns, tables, or index additions, did you add them to `prod/core/Schema.php`? Ensure they are written idempotently so they run safely on existing installations.
- [ ] **Location Status Isolation**: Are global status dropdowns filtered to `(location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL') GROUP BY name`? Are color joins using grouped subqueries?
- [ ] **Foreign Key Safety on Uploads**: Does media upload logic insert/verify the parent `locations` row before writing to `location_photos`?
- [ ] **Master CRM Sync**: When registering leads, is the record added to `customers.db` using the correct customer registry layout?
- [ ] **Client Identifiers**: Does the code use the designated identifier schema for customer accounts (`CUST-XXXXXXXX`)?
- [ ] **Real-Time SSE Sync**: If you are rendering new dynamic data tables or lists (e.g. leads, orders), did you add `data-id` tracking attributes to individual rows and register the component container with `AppSync.register()` for timer-free real-time synchronization?
- [ ] **Optimistic Locking**: If you are editing warehouse stock inventory records, did you implement/preserve checks on the `updated_at` column to prevent concurrency issues?

---

## 2. Document & Media Generation
- [ ] **XML Well-Formedness**: If you altered Flat XML thermal labels, did you verify that the XML layout remains structurally valid?
- [ ] **Character Escaping**: Are dynamic string inclusions escaped with `htmlspecialchars($var, ENT_XML1, 'UTF-8')` before insertion into XML templates?
- [ ] **Zip Dependency**: Ensure label generation does not rely on the `ZipArchive` extension (maintain the flat `.fodt` layout).
- [ ] **Ingest-Time WebP**: Are all uploaded photographs converted directly to WebP on ingestion via `MediaManager` instead of compressing on HTTP demand?
- [ ] **Date Partitioning**: Are photo assets stored under `YYYY/MM/` partitioned directories?
- [ ] **Cascading Deletion**: Does deleting a media item remove raw archives, optimized WebP, thumbnails, and database records?

---

## 3. UI/UX (Warehouse & iOS Safari Compliance)
- [ ] **Touch Target Size**: Are all interactive elements (buttons, inputs, status badges) at least `48px` × `48px`?
- [ ] **Prevent iOS Auto-Zoom**: Do all text inputs, selects, and textareas use a font size of at least `16px`?
- [ ] **Design Tokens**: Do CSS properties use variables from `prod/assets/styles/style.css` (HSL variables, glassmorphic filters)?
- [ ] **Camera Viewfinder**: Does camera streaming gracefully fall back to file picker if `getUserMedia` fails or permissions are denied? Are video tracks properly stopped (`track.stop()`) on modal close?
- [ ] **Hover Actions**: Ensure critical actions (edit, delete, save, print) do not depend on mouse-hover effects. All tools must be directly accessible on mobile touchscreens.
- [ ] **Trends Table Column Ordering**: In the Model Demand Velocity table (`trends_tab_velocity.php`), verify that the **Avg Price** column is placed **BEFORE** the **Details** column. Never invert this order.
- [ ] **Chart.js Hidden Canvas Lifecycle**: When initializing or re-rendering Chart.js instances inside tabbed interfaces (e.g. Trends tabs), always verify if an instance already exists via `Chart.getChart(id)` and destroy it before instantiating. When switching tabs, dispatch chart initialization with a ~50ms timeout to allow DOM reflow from `display: none`.
- [ ] **Dual-Axis Performance Mode Switcher**: Ensure mode toggling (`setPricingChartViewMode`) smoothly shows/hides `#pricingSplitViewContainer` and `#pricingComboViewContainer`, updates active toggle buttons, and persists to `sessionStorage` (`pricing_chart_view_mode`).
- [ ] **Live Matrix Inline Save Feedback**: Verify that inline matrix edits in `b2b_untested_matrix.php` and `tested_market_tab.php` trigger `showMatrixSaveToast()` and animate the modified cell with `.cell-saved-pulse`.
- [ ] **Customer Profile Intelligence Modal (Phase 4)**: Ensure `#customerProfileModal` correctly opens via `openCustomerProfileModal()`, cleans up `Escape` key listeners upon closing, and handles customer linking from Tab 4 (*Customer Insights*), Tab 1 (*Model Demand Buyer Names*), and CPU Pricing modal sales lists.
- [ ] **Global Empty States (Phase 4)**: Verify that search filters across all Trends tabs display styled empty state cards (`.global-no-results` / `.no-results-row`) with working "Clear Search Filter" buttons.
- [ ] **CSV Exports & UTF-8 BOM**: All client-side CSV export functions (e.g., `exportFinancialLedgerCSV()`, `exportDemandVelocityCSV()`, `exportLeadsCSV()`) must prepend the UTF-8 Byte Order Mark (`\uFEFF`) to ensure Excel correctly renders all currency symbols and characters.
- [ ] **Leads CRM Sorting & Search**: Ensure all table columns support bidirectional sort via `data-sort-val` attributes, urgency tags (`🔴 Overdue`, `🟡 Due Today`, `🟢 Upcoming`) are preserved, and search keyword highlighting functions across combined status tabs.

---

## 4. Code Quality & Portability
- [ ] **Frameworks**: Did you write logic using pure Vanilla PHP 8+ and Vanilla JS? Do not introduce libraries like TailwindCSS, React, or jQuery.
- [ ] **Absolute Inclusion Paths**: Are all PHP inclusion statements structured using `__DIR__` to prevent path failures?
- [ ] **Asset Autoloading**: Do your view templates rely on the routing engine inside `prod/index.php` to autoload assets instead of manual headers/footers?

---

## 5. Security & Audits
- [ ] **Mutations Tracking**: Did you trigger `Audit::log()` for sensitive mutations (creating customer profiles, deleting records, checking out manifest orders)?
- [ ] **Numeric Sanitization**: Did you pass currency and number parameters through `Security::sanitize_float()` or `Security::sanitize_int()`?
- [ ] **CSRF Safety**: Do form elements include the token snippet `<?= UI::csrf_field() ?>`? Are AJAX queries passing the token value?
- [ ] **Access Gating**: Are view pages secured by inclusion of `core/auth.php`?
- [ ] **Database Protections**: Are direct downloads of `.db` files blocked inside `db/` and `prod/` via `.htaccess` records?
