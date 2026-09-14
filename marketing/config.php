<?php
/**
 * Global Configuration for Marketing App
 */

// Session & Security Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration (Centralized outside HTTP scope)
require_once __DIR__ . '/../core/Database.php';
$marketing_db_dir = Database::getDbDir();
define('DB_PATH', $marketing_db_dir . '/marketing.db');
define('MASTER_CRM_DB_PATH', $marketing_db_dir . '/customers.db');
define('LABELS_DB_PATH', $marketing_db_dir . '/labels.sqlite');
define('WAREHOUSE_DB_PATH', $marketing_db_dir . '/warehouse.db');

// App Paths
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/marketing'), '/\\'));
define('INCLUDES_PATH', __DIR__ . '/includes');
define('MODULES_PATH', __DIR__ . '/modules');

// App Settings
define('APP_NAME', 'Marketing Hub');
define('VERSION', '1.1.0');

// Global Core UI & Security Helpers
require_once __DIR__ . '/../core/UI.php';
require_once __DIR__ . '/../core/Security.php';
Security::init();

/**
 * Universal HTML escape helper for XSS prevention
 */
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Authentication Guard - Any signed user has access
require_once __DIR__ . '/../core/Auth.php';
AuthGuard::check();

$user_role = $_SESSION['role'] ?? 'User';
$current_user = $_SESSION['username'] ?? 'User';


// Error Reporting (Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
