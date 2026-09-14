<?php
/**
 * Warehouse Database Access Layer
 * Simplified to rely on the global Schema Registry for table management.
 */
require_once __DIR__ . '/database.php';

try {
    // Database::warehouse() automatically ensures the schema is up-to-date via Schema::ensure()
    $conn_wh = Database::warehouse();
} catch (PDOException $e) {
    die("Warehouse DB Connection Failed: " . $e->getMessage());
}
?>
