<?php
/**
 * includes/audit.php
 * Logic for recording system-wide actions into audit.sqlite.
 */

/**
 * Records an entry into the audit trail.
 *
 * @param PDO $pdo_audit - Connection to audit.sqlite
 * @param string $entity_type - 'Label', 'Order', 'Customer'
 * @param mixed $entity_id - The primary key of the record
 * @param string $action - 'CREATED', 'UPDATED', 'DELETED', 'STATUS_CHANGE'
 * @param string|null $summary - Human readable summary of what happened
 * @param mixed|null $old_value - Data BEFORE change (Array or String)
 * @param mixed|null $new_value - Data AFTER change (Array or String)
 */
function log_audit_event($pdo_audit, $entity_type, $entity_id, $action, $summary = null, $old_value = null, $new_value = null) {
    try {
        $stmt = $pdo_audit->prepare("
            INSERT INTO audit_logs (entity_type, entity_id, action, summary, old_value, new_value)
            VALUES (:type, :id, :action, :summary, :old, :new)
        ");

        $stmt->execute([
            ':type'    => $entity_type,
            ':id'      => (string)$entity_id,
            ':action'  => $action,
            ':summary' => $summary,
            ':old'     => is_array($old_value) ? json_encode($old_value) : $old_value,
            ':new'     => is_array($new_value) ? json_encode($new_value) : $new_value
        ]);

        return true;
    } catch (Exception $e) {
        // We shouldn't crash the main app if auditing fails, but let's log it.
        error_log("Audit Logging Failed: " . $e->getMessage());
        return false;
    }
}
