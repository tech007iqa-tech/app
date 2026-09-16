<?php
/**
 * Universal Media Upload API Endpoint
 * Accepts live webcam snapshots (base64) or standard file uploads.
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/MediaManager.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$locationCode = trim($_POST['location_code'] ?? '');
$sector = trim($_POST['sector'] ?? 'Laptops');
$category = trim($_POST['category'] ?? 'General');
$uploadedBy = $_SESSION['username'] ?? 'User';

if (empty($locationCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Location code is required.']);
    exit;
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
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No image file or camera snapshot received.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Photo saved successfully ✨',
        'photo' => $photoRecord
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
