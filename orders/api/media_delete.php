<?php
/**
 * Universal Media Delete API Endpoint
 * Safely removes photo database records and unlinks optimized & raw files.
 */

require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/MediaManager.php';

ApiResponse::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::methodNotAllowed();
}

$input = ApiResponse::getJsonInput();
if (empty($input)) {
    $input = $_POST;
}

$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
ApiResponse::requireCsrf($csrfToken);

$photoId = (int)($input['photo_id'] ?? 0);
if ($photoId <= 0) {
    ApiResponse::error('Valid photo ID is required.', 400);
}

try {
    $db = Database::warehouse();
    $mediaManager = new MediaManager($db);
    $deleted = $mediaManager->deletePhoto($photoId);

    if ($deleted) {
        ApiResponse::success(['photo_id' => $photoId], 'Photo deleted successfully.');
    } else {
        ApiResponse::notFound('Photo not found.');
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}

