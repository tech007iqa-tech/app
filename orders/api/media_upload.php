<?php
/**
 * Universal Media Upload API Endpoint
 * Accepts live webcam snapshots (base64) or standard file uploads.
 */

require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/MediaManager.php';

ApiResponse::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed();
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
ApiResponse::requireCsrf($csrfToken);

$locationCode = trim($_POST['location_code'] ?? '');
$sector = trim($_POST['sector'] ?? 'Laptops');
$category = trim($_POST['category'] ?? 'General');
$uploadedBy = $_SESSION['username'] ?? 'User';

if (empty($locationCode)) {
    ApiResponse::error('Location code is required.', 400);
}

try {
    // Ensure the location row exists in the locations table to satisfy foreign key constraint
    $db = Database::warehouse();
    $stmtCheck = $db->prepare("INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')");
    $stmtCheck->execute([$locationCode]);

    $mediaManager = new MediaManager($db);
    $photoRecord = null;

    // Check for base64 live camera snapshot
    if (!empty($_POST['photo_base64'])) {
        $base64Data = $_POST['photo_base64'];
        $filename = $_POST['filename'] ?? ('cam_snap_' . $locationCode . '_' . date('Ymd_His') . '.jpg');
        $photoRecord = $mediaManager->processUpload($base64Data, $filename, $locationCode, $sector, $category, $uploadedBy);
    } 
    // Check for standard file upload
    elseif (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['photo']['tmp_name'];
        $origName = $_FILES['photo']['name'];
        $photoRecord = $mediaManager->processUpload($tmpName, $origName, $locationCode, $sector, $category, $uploadedBy);
    } else {
        ApiResponse::error('No image file or camera snapshot received.', 400);
    }

    ApiResponse::success([
        'photo' => $photoRecord
    ], 'Photo saved successfully ✨');
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}

