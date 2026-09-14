# Development Guidelines for Engineers & AI Agents
*Last Updated: September 5, 2026 10:52 PM*

## Objective
To maintain and extend a high-performance, zero-trust, and modular marketing ecosystem within the IQA Warehouse Systems.

---

## 🚀 Workflow for New Modules & Features

Every feature in the Marketing Hub follows an isolated, self-contained architecture to allow multiple developers and AI subagents to collaborate without merge collisions or regressions.

### 1. Register the Module
Add the new module to the strict `$allowed_modules` whitelist in [`marketing/index.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/index.php):
```php
$allowed_modules = [
    'dashboard'       => 'Dashboard',
    'leads'           => 'Leads',
    'model_templates' => 'Templates',
    'ad_generator'    => 'Ad Generator',
    'campaigns'       => 'Campaigns',
    'photo_bucket'    => 'Photo Bucket',
    'reports'         => 'Reports',
    'docs'            => 'Docs',
    'my_feature'      => 'My Feature Title' // <-- Register here
];
```

### 2. Create the Module Entry Point
Create a dedicated folder and entry point in `modules/`:
```text
marketing/modules/my_feature/
└── index.php
```
When `index.php` executes, the front controller has already initialized the session, security engine, database handles, and global UI helpers.

### 3. Setup Database & Schema Migrations
The Marketing Hub uses **Self-Healing Schemas**. Never rely on loose `.sql` files. Instead, add table blueprints and automated index migrations to `init_marketing_db()` inside [`marketing/includes/db.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/includes/db.php):
```php
$pdo->exec("
    CREATE TABLE IF NOT EXISTS my_feature (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        status TEXT DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_my_feature_status ON my_feature(status);
");
```

---

## 🛡️ Zero-Trust Security & Engineering Checklist

All modules must adhere to the 6 mandatory security safeguards:

### 1. CSRF Protection on Every State Change
- **Rule**: All state-changing actions (`POST`/`PUT`/`DELETE`) must contain a valid CSRF token.
- **Form Output**:
  ```php
  <form method="POST" action="?page=my_feature">
      <?= UI::csrf_field() ?>
      <button type="submit" name="action" value="save">Save</button>
  </form>
  ```
- **Handler Validation**:
  ```php
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!Security::validate($_POST['csrf_token'] ?? '')) {
          $_SESSION['notify'] = ['message' => 'Security check failed: Invalid CSRF token.', 'type' => 'error'];
          header("Location: ?page=my_feature");
          exit;
      }
      // Process validated action...
  }
  ```

### 2. Parameterized Queries (Zero SQL Injection)
- **Rule**: Never concatenate or interpolate PHP variables into SQL strings.
- **PDO Prepared Statements**:
  ```php
  // ✅ SECURE
  $stmt = $marketingDb->prepare("SELECT * FROM my_feature WHERE status = ? AND category = ?");
  $stmt->execute([$status, $category]);
  $results = $stmt->fetchAll();
  ```

### 3. Strict XSS Output Escaping
- **Rule**: Wrap every dynamic PHP variable rendered into HTML in `h()` (which maps to `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`):
  ```php
  <h2><?= h($item['name']) ?></h2>
  <input type="text" name="title" value="<?= h($item['title'] ?? '') ?>">
  ```
- For inline JSON hydration in `<script>`, use `json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`.

### 4. Role-Based Access Control (RBAC)
- The global variable `$user_role` reflects the authenticated user's session role (`Admin`, `Manager`, `Sales`, `Marketing`).
- Restrict sensitive actions (deletions, bulk exports, configuration):
  ```php
  if (!in_array($user_role, ['Admin', 'Manager'])) {
      $_SESSION['notify'] = ['message' => 'Unauthorized: Elevated permissions required.', 'type' => 'error'];
      header("Location: ?page=my_feature");
      exit;
  }
  ```

### 5. Central Audit Logging
- Log every mutation (Insert, Update, Delete) to the central `marketing_audit` table using the built-in helper:
  ```php
  log_marketing_audit($marketingDb, 'Feature', $itemId, 'UPDATE', "Updated title to '{$newTitle}'");
  ```

### 6. Atomic Transactions & Multi-Database Integrity
- When saving records that touch multiple tables or external databases (`customers.db` or `warehouse.db`), wrap them in an atomic transaction:
  ```php
  db_transaction($marketingDb, function($db) use ($itemId, $payload) {
      $stmt = $db->prepare("UPDATE items SET ... WHERE id = ?");
      $stmt->execute([$itemId]);
  });
  ```

---

## 🎨 UI/UX & Design System

1. **Shared Component Helpers**: Leverage [`core/UI.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/core/UI.php) for consistent portal aesthetics:
   - `UI::stat_card($title, $value, $trend, $icon)`
   - `UI::badge($label, $type)` (types: `success`, `warning`, `danger`, `info`)
   - `UI::csrf_field()`
2. **Color Tokens**: Utilize CSS custom properties defined in `main.css`:
   - Primary Accent: `var(--accent-primary)` (#007268 Teal)
   - Secondary Accent: `var(--accent-secondary)` (#84cc16 Lime)
   - Card Surfaces: `var(--bg-surface)`
   - Text Colors: `var(--text-main)`, `var(--text-dim)`
3. **Non-Destructive UI**: Always prompt for confirmation on destructive actions:
   ```html
   <form method="POST" onsubmit="return confirm('Are you sure you want to delete this record? This action cannot be undone.');">
   ```
4. **Touch & Mobile Readiness**: Ensure action buttons have a minimum touch target of `48px` height for warehouse tablet and phone operators.

---

## 🧪 Code Quality & Verification
Before committing or presenting changes:
1. Validate PHP syntax:
   ```bash
   php -l modules/my_feature/index.php
   ```
2. Verify SQLite compatibility: Test both empty database initialization and queries against existing data.
3. Test CSRF rejection by deliberately submitting with a tampered token.
