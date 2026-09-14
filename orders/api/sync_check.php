<?php
/**
 * Ultra-Fast Non-Blocking Database Sync Check Endpoint
 * Returns instantly (<1ms) with the latest mtime of database files.
 * Does NOT hold server threads or lock sessions.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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
    clearstatcache(true, $db_path);
    clearstatcache(true, $wal_path);

    $t1 = file_exists($db_path) ? filemtime($db_path) : 0;
    $t2 = file_exists($wal_path) ? filemtime($wal_path) : 0;
    $max_mtime = max($max_mtime, $t1, $t2);
}

$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;
$changed = ($since > 0 && $max_mtime > $since);

echo json_encode([
    'status' => 'ok',
    'mtime' => $max_mtime,
    'changed' => $changed
]);
