<?php
/**
 * Test Storage APIs
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';

// Test 1: GET
$_GET = ['donor_model' => '7420', 'donor_part' => 'screen'];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include __DIR__ . '/../api/get_storage.php';
$getOut = ob_get_clean();
$getJson = json_decode($getOut, true);
echo "1. GET Donor search count: " . ($getJson['count'] ?? 0) . " (success=" . ($getJson['success'] ? 'true':'false') . ")\n";

// Test 2: ADD
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'brand' => 'Dell',
    'model_num' => '7490',
    'series' => 'Latitude',
    'battery' => 'Good',
    'screen' => 'Cracked / Bad',
    'issue' => 'Cracked display',
    'need' => 'Screen',
    'notes' => 'CLI automated test entry'
];
ob_start();
include __DIR__ . '/../api/add_storage.php';
$addOut = ob_get_clean();
$addJson = json_decode($addOut, true);
$createdId = $addJson['id'] ?? 0;
$createdSku = $addJson['sku'] ?? '';
echo "2. ADD Storage: success=" . ($addJson['success'] ? 'true':'false') . " | SKU=$createdSku | ID=$createdId\n";

// Test 3: UPDATE / HARVEST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'id' => $createdId,
    'action' => 'harvest',
    'part' => 'Battery',
    'recipient' => 'Laptop #ORD-999'
];
ob_start();
include __DIR__ . '/../api/update_storage.php';
$upOut = ob_get_clean();
$upJson = json_decode($upOut, true);
echo "3. HARVEST Part: success=" . ($upJson['success'] ? 'true':'false') . " | " . ($upJson['message'] ?? '') . "\n";

// Test 4: PROMOTE TO GOOD
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'id' => $createdId,
    'action' => 'promote_to_good',
    'resolution_notes' => 'Installed donor screen from RM-10. Tested good.',
    'ram' => '16 GB',
    'storage' => '256 GB SSD',
    'cpu' => 'i7-8650U'
];
ob_start();
include __DIR__ . '/../api/promote_storage.php';
$promOut = ob_get_clean();
$promJson = json_decode($promOut, true);
$promLogId = $promJson['log_id'] ?? 0;
echo "4. PROMOTE TO GOOD: success=" . ($promJson['success'] ? 'true':'false') . " | Log ID=$promLogId | " . ($promJson['message'] ?? '') . "\n";

// Verify database states
$db = Database::tech();
$checkUnit = $db->query("SELECT status, battery, notes FROM storage_units WHERE id = $createdId")->fetch(PDO::FETCH_ASSOC);
echo "   Storage Unit final status: {$checkUnit['status']}\n";
echo "   Storage Unit battery: {$checkUnit['battery']}\n";

$checkLog = $db->query("SELECT id, status, make, series, notes FROM logs WHERE id = $promLogId")->fetch(PDO::FETCH_ASSOC);
echo "   Hardware Log created: ID={$checkLog['id']} | Status={$checkLog['status']} | Notes={$checkLog['notes']}\n";

// Cleanup test records
if ($createdId) {
    $db->prepare("DELETE FROM storage_units WHERE id = ?")->execute([$createdId]);
}
if ($promLogId) {
    $db->prepare("DELETE FROM logs WHERE id = ?")->execute([$promLogId]);
}
echo "5. Cleanup completed successfully.\n";
