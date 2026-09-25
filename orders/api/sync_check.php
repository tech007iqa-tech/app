<?php
/**
 * Ultra-Fast Non-Blocking Database Sync Check Endpoint
 * Inspects SQLite PRAGMA data_version, table metrics (photo & inventory counts/IDs), and disk mtimes.
 * Returns instantly (<3ms) without holding sessions or locks.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../core/database.php';

$tokens = [];

// 1. Warehouse SQLite data_version & live metrics
try {
    $db_wh = Database::warehouse();
    $v_wh = $db_wh->query("PRAGMA data_version")->fetchColumn();
    $cnt_photos = $db_wh->query("SELECT COUNT(*), COALESCE(MAX(id), 0) FROM location_photos")->fetch(PDO::FETCH_NUM);
    $cnt_inv = $db_wh->query("SELECT COUNT(*), COALESCE(MAX(id), 0) FROM inventory")->fetch(PDO::FETCH_NUM);
    $tokens[] = "wh:{$v_wh}:p{$cnt_photos[0]}-{$cnt_photos[1]}:i{$cnt_inv[0]}-{$cnt_inv[1]}";
} catch (Exception $e) {}

// 2. Orders SQLite data_version & live metrics
try {
    $db_ord = Database::orders();
    $v_ord = $db_ord->query("PRAGMA data_version")->fetchColumn();
    $cnt_ord = $db_ord->query("SELECT COUNT(*), COALESCE(MAX(id), 0) FROM orders")->fetch(PDO::FETCH_NUM);
    $tokens[] = "ord:{$v_ord}:o{$cnt_ord[0]}-{$cnt_ord[1]}";
} catch (Exception $e) {}

// 3. File mtimes fallback
$db_dir = Database::getDbDir();
$max_mtime = 0;
foreach (['customers.db', 'orders.db', 'warehouse.db'] as $f) {
    $p = $db_dir . '/' . $f;
    $wp = $p . '-wal';
    clearstatcache(true, $p);
    clearstatcache(true, $wp);
    $t1 = file_exists($p) ? filemtime($p) : 0;
    $t2 = file_exists($wp) ? filemtime($wp) : 0;
    $max_mtime = max($max_mtime, $t1, $t2);
}
$tokens[] = "mt:{$max_mtime}";

$fingerprint = md5(implode('|', $tokens));
$current_time = time();

$since = $_GET['since'] ?? '';
$since_token = $_GET['token'] ?? '';
$client_token = !empty($since_token) ? $since_token : (!empty($since) ? (string)$since : '');

$changed = false;
if (!empty($client_token)) {
    if ($client_token !== $fingerprint) {
        $changed = true;
    }
}

echo json_encode([
    'status' => 'ok',
    'changed' => $changed,
    'token' => $fingerprint,
    'mtime' => $max_mtime,
    'timestamp' => $current_time
]);
