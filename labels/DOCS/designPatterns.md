# 🛠️ Design Patterns & Technical Guidelines
**File:** `designPatterns.md`
**Purpose:** This document defines the exact technical patterns, code structure, and best practices that any AI Agent or Developer must follow when writing code for this project.

By following these design patterns, the codebase remains consistent, fast, and easy to debug.

---

## 1. Frontend: The `fetch()` API Pattern
**Rule:** No full-page form submissions. Forms should never trigger a page reload unless absolutely necessary.
- We use Vanilla JavaScript to intercept form submissions.
- We send data to our PHP `/api/` endpoints asynchronously.
- We update the DOM based on the JSON response.

**Example Pattern (JS):**
```js
document.getElementById('myForm').addEventListener('submit', async (e) => {
    e.preventDefault(); // Stop page reload

    const formData = new FormData(e.target);
    const submitBtn = e.target.querySelector('button[type="submit"]');

    try {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

        const response = await fetch('api/endpoint.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Update UI on success
            showNotification('Success!', 'success');
            e.target.reset(); // Clear form
        } else {
            // Handle expected errors from the backend
            showNotification(result.error || 'An error occurred', 'error');
        }
    } catch (error) {
        // Handle network/unexpected errors
        console.error('API Error:', error);
        showNotification('Network error occurred.', 'error');
    } finally {
        // Always reset button state
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
    }
});
```

---

## 2. Backend: The PHP API Endpoint Pattern
**Rule:** Files in the `/api/` folder MUST strictly return JSON. They should never render HTML or use `echo` for debugging.
- Always set the `Content-Type: application/json` header.
- Always return a standard JSON structure: `{ "success": true/false, "data": {}, "error": "Message" }`.
- Wrap database operations in `try/catch` blocks.

**Example Pattern (PHP):**
```php
<?php
// api/example_endpoint.php
header('Content-Type: application/json');
require_once '../includes/db.php'; // Get PDO instance

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    // 1. Validate Input
    if (!isset($_POST['brand']) || empty($_POST['brand'])) {
        throw new Exception('Brand is required.');
    }

    $brand = trim($_POST['brand']);

    // 2. Database Operation using Prepared Statements
    $stmt = $pdo_labels->prepare("INSERT INTO items (brand) VALUES (:brand)");
    $stmt->execute([':brand' => $brand]);

    // 3. Set Success State
    $response['success'] = true;
    $response['data'] = ['inserted_id' => $pdo_labels->lastInsertId()];

} catch (Exception $e) {
    // 4. Handle Errors Cleanly
    http_response_code(400); // Bad Request
    $response['error'] = $e->getMessage();
}

// 5. Always output JSON and exit
echo json_encode($response);
exit;
```

---

## 3. Database: The PDO Prepared Statement Pattern
**Rule:** ALWAYS use Prepared Statements (`prepare` -> `execute`). NEVER concatenate directly into SQL strings.
- This prevents SQLite injection attacks.
- Explicitly name parameters (e.g., `:brand`, `:model`).

**Example Pattern:**
```php
// GOOD
$stmt = $pdo->prepare("SELECT * FROM items WHERE status = :status AND battery = :battery");
$stmt->execute([
    ':status' => 'In Warehouse',
    ':battery' => 1
]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// BAD (DO NOT DO THIS)
// $db->query("SELECT * FROM items WHERE status = '" . $_POST['status'] . "'");
```

---

## 4. UI: The Dashboard Component Pattern
**Rule:** PHP pages in the root folder (`index.php`, `labels.php`) should primarily render the HTML structure and load the relevant JS file.
- Keep PHP logic in view files to an absolute minimum (e.g., just checking session status or loading basic static components).
- Use `includes/header.php` and `includes/footer.php` to wrap the active page content.

**Example Pattern:**
```php
<?php require_once 'includes/header.php'; ?>

<main class="dashboard-container">
    <header class="page-header">
        <h1>Overview</h1>
        <button id="refreshBtn" class="btn-primary">Refresh Stats</button>
    </header>

    <div class="stats-grid" id="statsContainer">
        <!-- Rendered via JS -->
    </div>
</main>

<script src="assets/js/dashboard.js"></script>

<?php require_once 'includes/footer.php'; ?>
```

---

## 5. File Naming Conventions
- **PHP view files:** All lowercase, separated by underscores (e.g., `new_label.php`).
- **API endpoints:** Descriptive action inside the `/api/` folder (e.g., `api/add_laptop.php`).
- **JavaScript files:** Matched to the view or purpose (e.g., `labels.js`, `api-helpers.js`).
- **CSS global variables:** Prefixed with `--` (e.g., `--bg-panel`).
