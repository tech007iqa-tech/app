# Marketing Hub Modules Architecture Guide

Welcome, Agent / Developer! This directory houses the modular subsystem of the **Marketing Hub**.

## Core Philosophy
Every module in this directory is **isolated, self-contained, and zero-trust**. This design enables multiple AI subagents and developers to work on separate modules concurrently without collision or regression.

---

## Standard Module Contract
Every module is a directory inside `modules/` with an `index.php` entrypoint:
`modules/<module_name>/index.php`

### Global Environment Provided to Modules
When `modules/<module_name>/index.php` executes, the front controller (`index.php`) has already initialized:
- `session_start()` and `Security::init()` (Active session & CSRF engine).
- `$user_role`: The authenticated user role (`Admin`, `Manager`, `Sales`, `Marketing`).
- `$marketingDb`: PDO instance for local marketing database (`marketing.db`).
- `$labelsDb`: PDO instance for live warehouse inventory (`labels.sqlite` - nullable if offline).
- `$crmDb`: PDO instance for company Master CRM (`customers.db`).
- `UI`: Global UI component builder (`UI::stat_card()`, `UI::badge()`, `UI::csrf_field()`, etc.).
- `h($string)`: Global HTML escape shorthand (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
- `log_marketing_audit($pdo, $entity, $id, $action, $summary)`: Automatic user audit tracker.
- `db_transaction($pdo, callable $callback)`: Atomic multi-query wrapper.

---

## Security & Architectural Checklist for New Features

### 1. CSRF Protection on Every State Change
- State-changing actions MUST use `POST` (never `GET`).
- Form elements MUST include:
  ```php
  <?= UI::csrf_field() ?>
  ```
- Handlers MUST validate the token before executing business logic:
  ```php
  if (!Security::validate($_POST['csrf_token'] ?? '')) {
      $_SESSION['notify'] = ['message' => 'Security check failed: Invalid CSRF token.', 'type' => 'error'];
      header("Location: ?page=<module_name>");
      exit;
  }
  ```

### 2. Parameterized Queries Only
- NEVER concatenate or interpolate variables into SQL queries:
  ```php
  // ❌ INSECURE
  $db->query("SELECT * FROM leads WHERE name = '$name'");

  // ✅ SECURE
  $stmt = $db->prepare("SELECT * FROM leads WHERE name = ?");
  $stmt->execute([$name]);
  ```

### 3. Strict XSS Output Escaping
- Always pass dynamic output through `h()`:
  ```php
  <input type="text" name="company" value="<?= h($lead['company']) ?>">
  ```

### 4. Non-Destructive Actions & Client Confirmation
- Destructive actions (deletions, bulk purges) must use a POST form with `onsubmit="return confirmAction('Are you sure?', this)"`.

### 5. Multi-DB Concurrency & Transactions
- When writing to both `customers.db` and `marketing.db`, wrap both operations so failures rollback cleanly.

---

## Adding a New Module in 2 Steps
1. Create `modules/<my_module>/index.php`.
2. Add `'my_module' => 'My Module Title'` to the `$allowed_modules` whitelist array in `marketing/index.php`.
