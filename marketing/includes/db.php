<?php
/**
 * Database Connection Handler for Marketing Module
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/schema_guard.php';

function get_marketing_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Ensure data directory exists
            $db_dir = dirname(DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }

            $pdo = new PDO("sqlite:" . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = 5000;");
            marketing_schema_guard($pdo);
        } catch (PDOException $e) {
            die("Marketing DB connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

function get_labels_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Ensure labels database directory exists
            $db_dir = dirname(LABELS_DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $pdo = new PDO("sqlite:" . LABELS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = 5000;");
            labels_schema_guard($pdo);
        } catch (PDOException $e) {
            error_log("Labels DB connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

/**
 * Warehouse Control Center Database Connection (Shared with orders/pages/warehouse.php)
 */
function get_warehouse_db() {
    static $wh_pdo = null;
    if ($wh_pdo === null) {
        try {
            $db_dir = dirname(WAREHOUSE_DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $wh_pdo = new PDO("sqlite:" . WAREHOUSE_DB_PATH);
            $wh_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $wh_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $wh_pdo->exec("PRAGMA journal_mode = WAL;");
            $wh_pdo->exec("PRAGMA busy_timeout = 5000;");
        } catch (PDOException $e) {
            error_log("Warehouse DB connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $wh_pdo;
}

/**
 * Master CRM Database Connection (Shared with Orders module)
 */
function get_master_crm_db() {
    static $crm_pdo = null;
    if ($crm_pdo === null) {
        try {
            // Ensure CRM database directory exists
            $db_dir = dirname(MASTER_CRM_DB_PATH);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $crm_pdo = new PDO("sqlite:" . MASTER_CRM_DB_PATH);
            $crm_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $crm_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $crm_pdo->exec("PRAGMA journal_mode = WAL;");
            $crm_pdo->exec("PRAGMA busy_timeout = 5000;");
            crm_schema_guard($crm_pdo);
        } catch (PDOException $e) {
            error_log("Master CRM Connection failed: " . $e->getMessage());
            die("Master CRM database is currently unreachable. Please verify database health.");
        }
    }
    return $crm_pdo;
}

/**
 * Executes a callable inside a database transaction with automatic commit/rollback.
 */
function db_transaction(PDO $pdo, callable $callback) {
    $alreadyInTransaction = $pdo->inTransaction();
    if (!$alreadyInTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $result = $callback($pdo);
        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Global Audit Logger helper - Records actor username for company-wide traceability
 */
function log_marketing_audit($pdo, $entity_type, $entity_id, $action, $summary = '', $old_value = '', $new_value = '') {
    try {
        $user_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? ($_SESSION['role'] ?? 'Staff');
        $stmt = $pdo->prepare("INSERT INTO audit_logs (entity_type, entity_id, action, summary, old_value, new_value, user_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$entity_type, (string)$entity_id, strtoupper($action), $summary, (string)$old_value, (string)$new_value, $user_name]);
    } catch (Throwable $e) {
        error_log("Audit log recording failed: " . $e->getMessage());
    }
}
?>
