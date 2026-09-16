# 🔍 AI Code Review Checklist 9/16/2026 11:26 AM

This checklist must be used to evaluate all code changes and contributions to the **IQA Warehouse Systems** project.

## 1. Database & Sync Integrity
- [ ] Does the change modify the schema? If yes, is `includes/schema_guard.php` or `Schema.php` updated?
- [ ] **Sync Check**: If adding a person/lead, does it write to the Master CRM (`customers.db`)?
- [ ] **ID Format**: Does the new record use the `CUST-XXXXXXXX` string ID format?
- [ ] Are all queries using PDO Prepared Statements?
- [ ] Is `PRAGMA foreign_keys = ON;` being respected where applicable?
- [ ] **Location Status Isolation**: Are global status queries filtered with `(location_code IS NULL OR location_code = '' OR location_code = 'GLOBAL') GROUP BY name`? Are color joins using grouped subqueries to prevent duplicate location cards?
- [ ] **Foreign Key Guard on Photos**: Does photo upload logic verify/insert the parent `location_code` before creating records in `location_photos`?

## 2. Document & Media Generation
- [ ] If modifying label output, is the XML well-formed?
- [ ] Does it use `htmlspecialchars($var, ENT_XML1, 'UTF-8')` for all dynamic data?
- [ ] Is the "Flat XML" structure preserved (no Zip dependency)?
- [ ] **Ingest-Time WebP**: Are all uploaded photographs converted directly to WebP on ingestion via `MediaManager` instead of compressing on HTTP demand?
- [ ] **Date Partitioning**: Are photo assets stored under `YYYY/MM/` partitioned directories?
- [ ] **Cascading Deletion**: Does deleting a media item remove raw archives, optimized WebP, thumbnails, and database records?

## 3. UI/UX (Warehouse Ready)
- [ ] Is the contrast ratio high enough for warehouse lighting?
- [ ] Are buttons at least `48x48px` for touch input?
- [ ] **Palette Check**: If in `/marketing`, does it use the **Teal/Lime** design tokens?
- [ ] Are CSS variables used for colors and spacing?
- [ ] Does it handle "Empty States" gracefully?
- [ ] **Camera Viewfinder**: Does camera streaming gracefully fall back to file picker if `getUserMedia` fails or permissions are denied? Are video tracks properly stopped (`track.stop()`) on modal close?
- [ ] **Trends Table Column Order**: Model Demand Velocity table must display **Avg Price** before **Details**. Never reverse this.
- [ ] **Chart.js Hidden Canvas Lifecycle**: Re-render Chart.js on tab switches with `Chart.getChart(id)?.destroy()` and a ~50ms timeout.
- [ ] **Dual-Axis Performance Mode Switcher**: Ensure mode toggling (`setPricingChartViewMode`) switches between Split and Combo views and persists to `sessionStorage`.
- [ ] **Live Matrix Inline Save Feedback**: Ensure matrix edits trigger `showMatrixSaveToast()` and animate with `.cell-saved-pulse`.
- [ ] **Customer Profile Intelligence Modal (Phase 4)**: Ensure `#customerProfileModal` opens via `openCustomerProfileModal()`, cleans up `Escape` listeners, and supports cross-module intelligence links.
- [ ] **Global Empty States (Phase 4)**: Ensure search filters display styled empty states (`.global-no-results` / `.no-results-row`) with single-click filter resets.
- [ ] **CSV Exports**: Prepend `\uFEFF` UTF-8 BOM on all client-side CSV downloads for Excel compatibility.
- [ ] **Leads CRM**: Preserve 9-column bidirectional sort (`data-sort-val`), urgency badges, and multi-word keyword search highlighting.

## 4. Performance & Portability
- [ ] Are there any new external dependencies? (Goal is Zero-Dependency).
- [ ] Is the PHP code compatible with standard XAMPP (Windows)?
- [ ] Does it use absolute pathing (`__DIR__`)?

## 5. Security & Auditing
- [ ] Is the action being logged to `audit.sqlite` or `log_audit_event()`?
- [ ] Is user input sanitized using `sanitize_text()` or equivalent?
- [ ] Are `.sqlite` and `.db` files protected via `.htaccess` in their respective directories?
- [ ] Do AJAX endpoints validate CSRF tokens via `Security::validate_csrf_token()`?
