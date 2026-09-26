<?php
// includes/db.php
// Initializes PDO connections to the 3 SQLite files with strict error handling.

// Resolve secure DB directory outside HTTP scope
$core_db_file = dirname(__DIR__, 2) . '/core/Database.php';
if (file_exists($core_db_file)) {
    require_once $core_db_file;
    $db_dir = rtrim(Database::getDbDir(), '/\\') . '/';
} else {
    $candidates = [
        dirname(__DIR__, 3) . '/data/db/',
        dirname(__DIR__, 4) . '/data/db/',
        '/home4/latinspc/data/db/',
        __DIR__ . '/../db/'
    ];

    $db_dir = __DIR__ . '/../db/';
    foreach ($candidates as $cand) {
        if (is_dir($cand)) {
            $db_dir = rtrim($cand, '/\\') . '/';
            break;
        }
    }

    // Ensure directory exists
    if (!is_dir($db_dir)) {
        @mkdir($db_dir, 0755, true);
    }
}

// Database paths
$labels_db_path = $db_dir . 'labels.sqlite';
$orders_db_path = $db_dir . 'orders.sqlite';
$rolodex_db_path = $db_dir . 'rolodex.sqlite';
$audit_db_path   = $db_dir . 'audit.sqlite';

/**
 * Applies concurrency optimizations to a SQLite connection.
 */
function apply_sqlite_optimizations($pdo) {
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA busy_timeout = 5000;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");
    $pdo->exec("PRAGMA foreign_keys = ON;");
}

try {
    // 1. Labels Database
    $pdo_labels = new PDO("sqlite:" . $labels_db_path);
    $pdo_labels->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_labels->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    apply_sqlite_optimizations($pdo_labels);

    // 2. Orders Database
    $pdo_orders = new PDO("sqlite:" . $orders_db_path);
    $pdo_orders->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_orders->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    apply_sqlite_optimizations($pdo_orders);

    // 3. Rolodex Database
    $pdo_rolodex = new PDO("sqlite:" . $rolodex_db_path);
    $pdo_rolodex->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_rolodex->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    apply_sqlite_optimizations($pdo_rolodex);

    // 4. Audit Database
    $pdo_audit = new PDO("sqlite:" . $audit_db_path);
    $pdo_audit->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_audit->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    apply_sqlite_optimizations($pdo_audit);

    // 5. Schema Guard (Self-Healing)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['schemas_verified'])) {
        require_once __DIR__ . '/schema_guard.php';
        check_and_rebuild_schemas($pdo_labels, $pdo_orders, $pdo_rolodex, $pdo_audit);
        $_SESSION['schemas_verified'] = true;
    }

    // 6. Global Audit Support
    require_once __DIR__ . '/audit.php';

} catch (PDOException $e) {
    // Return early if called from an API endpoint expecting JSON (Vibe Code standard)
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }

    // Fallback for direct HTML view
    die("Database Connection Error: " . $e->getMessage());
}
