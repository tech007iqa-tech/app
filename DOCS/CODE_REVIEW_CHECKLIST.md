# 🔍 AI Code Review Checklist 9/5/2026 10:52 PM

When reviewing pull requests or changes in this codebase, ensure the following standards are met:

## 1. Database & Sync Integrity
- [ ] Does the change modify the schema? If yes, is `includes/schema_guard.php` updated?
- [ ] **Sync Check**: If adding a person/lead, does it write to the Master CRM (`customers.db`)?
- [ ] **ID Format**: Does the new record use the `CUST-XXXXXXXX` string ID format?
- [ ] Are all queries using PDO Prepared Statements?
- [ ] Is `PRAGMA foreign_keys = ON;` being respected where applicable?

## 2. Document Generation
- [ ] If modifying label output, is the XML well-formed?
- [ ] Does it use `htmlspecialchars($var, ENT_XML1, 'UTF-8')` for all dynamic data?
- [ ] Is the "Flat XML" structure preserved (no Zip dependency)?

## 3. UI/UX (Warehouse Ready)
- [ ] Is the contrast ratio high enough for warehouse lighting?
- [ ] Are buttons at least `48x48px` for touch input?
- [ ] **Palette Check**: If in `/marketing`, does it use the **Teal/Lime** design tokens?
- [ ] Are CSS variables used for colors and spacing?
- [ ] Does it handle "Empty States" gracefully?

## 4. Performance & Portability
- [ ] Are there any new external dependencies? (Goal is Zero-Dependency).
- [ ] Is the PHP code compatible with standard XAMPP (Windows)?
- [ ] Does it use absolute pathing (`__DIR__`)?

## 5. Security & Auditing
- [ ] Is the action being logged to `audit.sqlite`?
- [ ] Is user input sanitized using `sanitize_text()` or equivalent?
- [ ] Are `.sqlite` files protected via `.htaccess` in their respective directories?
