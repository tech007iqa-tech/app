# 🔍 Code Review Checklist 9/5/2026 10:52 PM

This checklist must be used to evaluate all code changes and contributions to the **IQA Warehouse Systems** project.

---

## 1. Database & Sync Integrity
- [ ] **Prepared Statements**: Are all SQL queries written with PDO prepared statements and bound variables? Do NOT concatenate user input directly into queries.
- [ ] **Schema Migrations**: If your code requires new columns, tables, or index additions, did you add them to `prod/core/Schema.php`? Ensure they are written idempotently so they run safely on existing installations.
- [ ] **Master CRM Sync**: When registering leads, is the record added to `customers.db` using the correct customer registry layout?
- [ ] **Client Identifiers**: Does the code use the designated identifier schema for customer accounts (`CUST-XXXXXXXX`)?
- [ ] **Real-Time SSE Sync**: If you are rendering new dynamic data tables or lists (e.g. leads, orders), did you add `data-id` tracking attributes to individual rows and register the component container with `AppSync.register()` for timer-free real-time synchronization?
- [ ] **Optimistic Locking**: If you are editing warehouse stock inventory records, did you implement/preserve checks on the `updated_at` column to prevent concurrency issues?

---

## 2. Document & Output Generation
- [ ] **XML Well-Formedness**: If you altered Flat XML thermal labels, did you verify that the XML layout remains structurally valid?
- [ ] **Character Escaping**: Are dynamic string inclusions escaped with `htmlspecialchars($var, ENT_XML1, 'UTF-8')` before insertion into XML templates?
- [ ] **Zip Dependency**: Ensure label generation does not rely on the `ZipArchive` extension (maintain the flat `.fodt` layout).

---

## 3. UI/UX (Warehouse & iOS Safari Compliance)
- [ ] **Touch Target Size**: Are all interactive elements (buttons, inputs, status badges) at least `48px` × `48px`?
- [ ] **Prevent iOS Auto-Zoom**: Do all text inputs, selects, and textareas use a font size of at least `16px`?
- [ ] **Design Tokens**: Do CSS properties use variables from `prod/assets/styles/style.css` (HSL variables, glassmorphic filters)?
- [ ] **Hover Actions**: Ensure critical actions (edit, delete, save, print) do not depend on mouse-hover effects. All tools must be directly accessible on mobile touchscreens.

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
