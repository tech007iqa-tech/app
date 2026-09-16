<?php
/**
 * Universal Media Delete API Endpoint
 * Safely removes photo database records and unlinks optimized & raw files.
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

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Security::validate($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$photoId = (int)($input['photo_id'] ?? 0);
if ($photoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid photo ID is required.']);
    exit;
}

try {
    $db = Database::warehouse();
    $mediaManager = new MediaManager($db);
    $deleted = $mediaManager->deletePhoto($photoId);

    if ($deleted) {
        echo json_encode(['success' => true, 'photo_id' => $photoId]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Photo not found.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
