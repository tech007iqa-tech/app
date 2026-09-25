<?php
/**
 * Location Photos Fetch Endpoint
 * Returns JSON photo objects and pre-rendered card HTML for live AJAX updating without page refresh.
 */

require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/MediaManager.php';

ApiResponse::requireAuth();

$locationCode = trim($_GET['location_code'] ?? '');
$sector = trim($_GET['sector'] ?? '');
$zone = trim($_GET['zone'] ?? '');

try {
    $db = Database::warehouse();
    $mediaManager = new MediaManager($db);

    if (!empty($locationCode) && $locationCode !== 'GLOBAL') {
        $photos = $mediaManager->getPhotosForLocation($locationCode, $sector ?: null);
    } elseif (!empty($zone)) {
        $photos = $mediaManager->getPhotosForZone($zone);
    } else {
        $photos = [];
    }

    ApiResponse::success([
        'photos' => $photos,
        'count'  => count($photos),
        'location_code' => $locationCode,
        'sector' => $sector
    ], 'Photos loaded successfully.');
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
