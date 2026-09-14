<?php
/**
 * Non-Blocking Database Stream Endpoint
 * Responds immediately with latest mtime and closes connection to prevent server thread blocking.
 */
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: close');
header('X-Accel-Buffering: no');

require_once __DIR__ . '/../core/database.php';
$db_dir = Database::getDbDir();

$db_files = [
    $db_dir . '/customers.db',
    $db_dir . '/orders.db',
    $db_dir . '/warehouse.db'
];

$max_mtime = 0;
foreach ($db_files as $db_path) {
    $wal_path = $db_path . '-wal';
    $t1 = file_exists($db_path) ? filemtime($db_path) : 0;
    $t2 = file_exists($wal_path) ? filemtime($wal_path) : 0;
    $max_mtime = max($max_mtime, $t1, $t2);
}

echo "retry: 4000\n";
echo "event: database-change\n";
echo "data: " . json_encode(['mtime' => $max_mtime]) . "\n\n";
if (ob_get_level() > 0) ob_flush();
flush();
