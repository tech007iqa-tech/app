# 📢 Marketing Hub — Engineering Hardening Mandate

Guidelines and architectural phases for refactoring and maintaining `/marketing`.

### Environment & Context
- **Stack**: Vanilla PHP 8.1+, SQLite 3 (PDO), Vanilla JS (ES6+), Modular CSS3, Apache/XAMPP.
- **Audience**: Internal warehouse operations and marketing teams accessed concurrently across workstations.
- **Key Risks**: Missing role verification, shared session pollution, unescaped queries, race conditions, lack of audit logging.

---

### Phase 1: Security Hardening & Zero-Trust Access
1. **Access & Role Verification**: Ensure every entry point and AJAX handler strictly validates user sessions and verifies the required role/permissions via `AuthGuard::check()`.
2. **Prepared Statements & SQL Injection Defense**: Audit every database query (`$marketingDb`, `$labelsDb`, `$crmDb`). Replace all interpolated strings with parameterized PDO prepared statements.
3. **XSS & Output Sanitization**: Wrap all dynamic PHP output in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
4. **CSRF & Action Safeguards**: Add CSRF token validation to all state-changing `POST`/`PUT`/`DELETE` requests via `Security::validate()`.

---

### Phase 2: Code Quality & Error Resilience
1. **Graceful Failures**: Wrap database transactions and multi-database joins in `try/catch` blocks. Fail gracefully with clean UI notices.
2. **Strict PHP 8+ Compatibility**: Eliminate deprecated functions, fix dynamic property warnings, and verify strict type safety.
3. **Audit Trail**: Implement lightweight action logging (`log_audit_event()`) for tracking campaign changes and lead updates.

---

### Phase 3: Performance, Concurrency & Data Flow
1. **Query Optimization**: Check dashboard aggregation queries (`COUNT(*)`, `GROUP BY`). Ensure indexes exist in `core/Schema.php`.
2. **Atomic Writes**: Wrap multi-table inserts or updates into database transactions (`beginTransaction` / `commit` / `rollBack`).
3. **State Hydration**: Keep UI hydration clean via valid, well-structured JSON data islands (`<script id="..." type="application/json">`).

---

### Phase 4: UI/UX & Visual Polish
1. **Design Tokens**: Retain Teal/Lime design tokens and CSS custom properties defined in `.agents/rules/marketing-ui-standards.md`.
2. **Interactive Feedback**: Show loading/disabled states and clear toast notifications on success/error.
3. **Non-Destructive UI**: Require confirmation dialogs before any destructive or bulk actions.
