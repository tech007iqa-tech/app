<?php
/**
 * IQA System Audit Logger
 * Tracks sensitive actions across all modules for accountability.
 */

class Audit {
    /**
     * Records an action to the central audit log.
     *
     * @param string $action The action name (e.g., 'DELETE_ORDER', 'CHANGE_STATUS')
     * @param string $target_id The ID of the affected item (Order ID, Customer ID, etc.)
     * @param string $details JSON or text description of the change
     * @param string $module The module where the action occurred (orders, warehouse, crm)
     */
    public static function log($action, $target_id, $details = '', $module = 'system') {
        try {
            $conn = Database::users(); // Use users DB for centralized logs

            $stmt = $conn->prepare("INSERT INTO audit_log
                (user_id, user_name, module, action, target_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $_SESSION['user_id'] ?? 'SYSTEM',
                $_SESSION['username'] ?? 'Anonymous',
                $module,
                strtoupper($action),
                $target_id,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);

        } catch (Exception $e) {
            // --- Fallback: File-based Logging ---
            $log_dir = __DIR__ . '/../logs';
            if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

            $log_file = $log_dir . '/audit_fallback.log';
            $timestamp = date('Y-m-d H:i:s');
            $user = $_SESSION['username'] ?? 'Anonymous';
            $log_entry = "[{$timestamp}] [FAILBACK] User: {$user} | Action: {$action} | Target: {$target_id} | Error: " . $e->getMessage() . PHP_EOL;

            file_put_contents($log_file, $log_entry, FILE_APPEND);
            error_log("Audit Logging Failed, wrote to fallback log: " . $e->getMessage());
        }
    }

    /**
     * Fetches recent audit logs.
     */
    public static function getRecent($limit = 50) {
        try {
            $conn = Database::users();
            $stmt = $conn->prepare("SELECT * FROM audit_log ORDER BY timestamp DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
